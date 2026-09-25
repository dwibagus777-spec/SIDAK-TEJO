<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase B.5: Fault Event Ingestion Pipeline & Deduplication Schema
 *
 * Migration enhancing fault intelligence schema for ingestion:
 * 1. Alter fault_events:
 *    - event_fingerprint (VARCHAR 64 UNIQUE, NULL for legacy)
 *    - fingerprint_status (VARCHAR 32, 'CALCULATED', 'LEGACY_UNFINGERPRINTED')
 *    - fault_phase (VARCHAR 16)
 *    - fault_current_a (DECIMAL 10,2)
 *    - phase_currents_json (TEXT)
 *    - protection_elements (VARCHAR 128)
 *    - trip_sequence (VARCHAR 32)
 *    - relay_distance_m (DECIMAL 10,2)
 *    - lifecycle_status (VARCHAR 32, INGESTED -> ANALYZING -> CANDIDATE_IDENTIFIED -> DISPATCHED -> INVESTIGATING -> CONFIRMED/UNRESOLVED -> CLOSED)
 * 2. Table fault_event_revisions (Guard 1: Event Immutability, Append-Only Revisions)
 * 3. Table fault_ingestion_batches (Batch Telemetry Tracking & Batch Idempotency)
 *
 * Backward Compatibility Guarantees (User Acceptance Criterion B.5.1):
 * - Existing fault_events rows are 100% preserved with zero event loss.
 * - Existing rows receive fingerprint_status = 'LEGACY_UNFINGERPRINTED' with event_fingerprint = NULL (no fake hash).
 * - Existing lifecycle_status is cleanly mapped from existing status column.
 * - Strictly 0 modifications to gis_translines or master_assets/assets.
 */
class EnhanceFaultEventsIngestion extends Migration
{
    public function up()
    {
        $db = $this->db ?? \Config\Database::connect();
        $forge = $this->forge ?? \Config\Database::forge();

        // Record pre-migration baseline to verify zero topology mutation
        $tlCountBefore = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
        $assetCountBefore = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;

        // =====================================================================
        // 1. Alter Table: fault_events (Add Ingestion Telemetry & Deduplication)
        // =====================================================================
        if ($db->tableExists('fault_events')) {
            $existingFields = $db->getFieldNames('fault_events');
            $rowCountBefore = $db->table('fault_events')->countAllResults();

            $fieldsToAdd = [];

            if (!in_array('event_fingerprint', $existingFields, true)) {
                $fieldsToAdd['event_fingerprint'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'after'      => 'event_number',
                ];
            }

            if (!in_array('fingerprint_status', $existingFields, true)) {
                $fieldsToAdd['fingerprint_status'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'CALCULATED',
                    'after'      => 'event_fingerprint',
                ];
            }

            if (!in_array('fault_phase', $existingFields, true)) {
                $fieldsToAdd['fault_phase'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'null'       => true,
                    'after'      => 'source_reference',
                ];
            }

            if (!in_array('fault_current_a', $existingFields, true)) {
                $fieldsToAdd['fault_current_a'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                    'after'      => 'fault_phase',
                ];
            }

            if (!in_array('phase_currents_json', $existingFields, true)) {
                $fieldsToAdd['phase_currents_json'] = [
                    'type'  => 'TEXT',
                    'null'  => true,
                    'after' => 'fault_current_a',
                ];
            }

            if (!in_array('protection_elements', $existingFields, true)) {
                $fieldsToAdd['protection_elements'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                    'null'       => true,
                    'after'      => 'phase_currents_json',
                ];
            }

            if (!in_array('trip_sequence', $existingFields, true)) {
                $fieldsToAdd['trip_sequence'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => true,
                    'after'      => 'protection_elements',
                ];
            }

            if (!in_array('relay_distance_m', $existingFields, true)) {
                $fieldsToAdd['relay_distance_m'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                    'after'      => 'trip_sequence',
                ];
            }

            if (!in_array('lifecycle_status', $existingFields, true)) {
                $fieldsToAdd['lifecycle_status'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'INGESTED',
                    'after'      => 'status',
                ];
            }

            if (!empty($fieldsToAdd)) {
                $forge->addColumn('fault_events', $fieldsToAdd);
            }

            // Indexes on fault_events
            $indexes = $db->getIndexData('fault_events');
            $existingIndexNames = array_map(function ($idx) {
                return $idx->name;
            }, $indexes);

            if (!in_array('idx_fault_events_fingerprint', $existingIndexNames, true) &&
                !in_array('event_fingerprint', $existingIndexNames, true)) {
                // In MySQL, unique key on nullable column allows multiple NULLs (used for legacy rows)
                $db->query("ALTER TABLE `fault_events` ADD UNIQUE KEY `idx_fault_events_fingerprint` (`event_fingerprint`)");
            }

            if (!in_array('idx_fault_events_lifecycle', $existingIndexNames, true)) {
                $db->query("ALTER TABLE `fault_events` ADD KEY `idx_fault_events_lifecycle` (`lifecycle_status`)");
            }

            // =================================================================
            // Backward Compatibility: Populate existing rows safely
            // =================================================================
            if ($rowCountBefore > 0) {
                // Mark legacy rows explicitly as LEGACY_UNFINGERPRINTED
                $db->table('fault_events')
                    ->where('event_fingerprint IS NULL', null, false)
                    ->update(['fingerprint_status' => 'LEGACY_UNFINGERPRINTED']);

                // Map lifecycle_status from legacy status column
                $legacyRows = $db->table('fault_events')->get()->getResultArray();
                foreach ($legacyRows as $row) {
                    $mappedLifecycle = 'INGESTED';
                    $oldStatus = strtoupper(trim((string)($row['status'] ?? '')));
                    if ($oldStatus === 'ANALYZED') {
                        $mappedLifecycle = 'CANDIDATE_IDENTIFIED';
                    } elseif ($oldStatus === 'RESOLVED') {
                        $mappedLifecycle = 'CONFIRMED';
                    } elseif ($oldStatus === 'CLOSED') {
                        $mappedLifecycle = 'CLOSED';
                    } elseif ($oldStatus === 'OPEN') {
                        $mappedLifecycle = 'INGESTED';
                    }

                    $db->table('fault_events')
                        ->where('id', $row['id'])
                        ->update(['lifecycle_status' => $mappedLifecycle]);
                }
            }

            // Assert row count preserved 100%
            $rowCountAfter = $db->table('fault_events')->countAllResults();
            if ($rowCountBefore !== $rowCountAfter) {
                throw new \RuntimeException(
                    "Integrity violation: fault_events row count changed during migration! Before: {$rowCountBefore}, After: {$rowCountAfter}"
                );
            }
        }

        // =====================================================================
        // 2. Table: fault_event_revisions (Guard 1: Event Immutability)
        // =====================================================================
        if (!$db->tableExists('fault_event_revisions')) {
            $forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'fault_event_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'revision_no' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                ],
                'amended_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'amendment_reason' => [
                    'type' => 'TEXT',
                ],
                'previous_values_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'amended_fields_json' => [
                    'type' => 'TEXT',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $forge->addKey('id', true);
            $forge->addKey('fault_event_id');
            $forge->addKey(['fault_event_id', 'revision_no']);
            $forge->createTable('fault_event_revisions', true);
        }

        // =====================================================================
        // 3. Table: fault_ingestion_batches (Batch Provenance & Deduplication)
        // =====================================================================
        if (!$db->tableExists('fault_ingestion_batches')) {
            $forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'batch_uuid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'source_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                ],
                'batch_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'total_records' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'accepted_records' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'duplicate_records' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'COMPLETED',
                ],
                'metadata_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $forge->addKey('id', true);
            $forge->addUniqueKey('batch_uuid');
            $forge->addKey('batch_hash');
            $forge->addKey('source_type');
            $forge->createTable('fault_ingestion_batches', true);
        }

        // =====================================================================
        // Post-migration Invariant Check: Verify 0 physical topology mutation
        // =====================================================================
        $tlCountAfter = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
        $assetCountAfter = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;

        if ($tlCountBefore !== $tlCountAfter || $assetCountBefore !== $assetCountAfter) {
            throw new \RuntimeException(
                "CRITICAL GOVERNANCE VIOLATION: Authoritative physical topology was mutated during Phase B.5 migration! " .
                "gis_translines: {$tlCountBefore} -> {$tlCountAfter}, assets: {$assetCountBefore} -> {$assetCountAfter}"
            );
        }
    }

    public function down()
    {
        $db = $this->db ?? \Config\Database::connect();
        $forge = $this->forge ?? \Config\Database::forge();

        if ($db->tableExists('fault_ingestion_batches')) {
            $forge->dropTable('fault_ingestion_batches', true);
        }

        if ($db->tableExists('fault_event_revisions')) {
            $forge->dropTable('fault_event_revisions', true);
        }

        if ($db->tableExists('fault_events')) {
            $columnsToDrop = [
                'event_fingerprint',
                'fingerprint_status',
                'fault_phase',
                'fault_current_a',
                'phase_currents_json',
                'protection_elements',
                'trip_sequence',
                'relay_distance_m',
                'lifecycle_status',
            ];

            $existing = $db->getFieldNames('fault_events');
            foreach ($columnsToDrop as $col) {
                if (in_array($col, $existing, true)) {
                    $forge->dropColumn('fault_events', $col);
                }
            }
        }
    }
}
