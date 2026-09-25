<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase B.4: Network Context Engine & Fault Location Intelligence Contract
 *
 * Migration creating 5 fault intelligence tables:
 * 1. fault_cause_categories   — Canonical fault cause taxonomy (Guard 10)
 * 2. fault_events             — Historical fault events bound to topology snapshot (Guards 2 & 11)
 * 3. fault_cases              — Deterministic analysis cases with input hash (Guards 3, 12, 13)
 * 4. fault_candidate_assets   — Ranked candidates with structured evidence (Guards 4, 5, 14)
 * 5. fault_actual_findings    — Immutable append-only ground-truth findings (Guards 8 & 9)
 *
 * Invariants Enforced:
 * - 0 modification to authoritative gis_translines or master_assets.
 * - Clean 1:N relationship from fault_cases to fault_actual_findings (Guard 12: No circular FK).
 * - Snapshot-bound at event and case level (Guard 2).
 * - Version tagged FLI-1.0.0 (Guard 3).
 * - Candidate terminology strictly unconfirmed (Guard 14).
 */
class CreateFaultIntelligenceTables extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // =====================================================================
        // 1. Table: fault_cause_categories (Guard 10)
        // =====================================================================
        if (!$db->tableExists('fault_cause_categories')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                ],
                'parent_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'sort_order' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('code');
            $this->forge->addKey('parent_code');
            $this->forge->addKey('is_active');
            $this->forge->createTable('fault_cause_categories', true);

            // Seed canonical fault cause categories
            $seedCategories = [
                ['code' => 'VEGETATION',         'name' => 'Sentuhan Pohon / Vegetasi', 'parent_code' => null, 'sort_order' => 10],
                ['code' => 'VEG_FALLEN_TREE',    'name' => 'Pohon Tumbang',             'parent_code' => 'VEGETATION', 'sort_order' => 11],
                ['code' => 'VEG_BRANCH_TOUCH',   'name' => 'Ranting / Dahan Menempel',  'parent_code' => 'VEGETATION', 'sort_order' => 12],
                ['code' => 'ANIMAL',             'name' => 'Sentuhan Binatang',         'parent_code' => null, 'sort_order' => 20],
                ['code' => 'ANIMAL_BIRD',        'name' => 'Burung / Kelelawar',        'parent_code' => 'ANIMAL', 'sort_order' => 21],
                ['code' => 'ANIMAL_REPTILE',     'name' => 'Ular / Reptil',             'parent_code' => 'ANIMAL', 'sort_order' => 22],
                ['code' => 'LIGHTNING',          'name' => 'Sambaran Petir',            'parent_code' => null, 'sort_order' => 30],
                ['code' => 'EQUIPMENT_FAILURE',  'name' => 'Kerusakan Peralatan / Material', 'parent_code' => null, 'sort_order' => 40],
                ['code' => 'EQ_INSULATOR_FLASH', 'name' => 'Flashover Isoliator',       'parent_code' => 'EQUIPMENT_FAILURE', 'sort_order' => 41],
                ['code' => 'EQ_CONDUCTOR_BROKEN','name' => 'Kawat Putus / Rantas',      'parent_code' => 'EQUIPMENT_FAILURE', 'sort_order' => 42],
                ['code' => 'EQ_JUMPER_BURNT',    'name' => 'Jumper Terbakar / Lepas',   'parent_code' => 'EQUIPMENT_FAILURE', 'sort_order' => 43],
                ['code' => 'EXTERNAL_OBJECT',    'name' => 'Benda Asing (Benang Layangan/Spanduk)', 'parent_code' => null, 'sort_order' => 50],
                ['code' => 'THIRD_PARTY_ACCIDENT','name' => 'Tertabrak Kendaraan / Pihak Ketiga', 'parent_code' => null, 'sort_order' => 60],
                ['code' => 'OVERLOAD',           'name' => 'Kelebihan Beban (Overload)','parent_code' => null, 'sort_order' => 70],
                ['code' => 'OTHER',              'name' => 'Lain-lain',                 'parent_code' => null, 'sort_order' => 90],
                ['code' => 'UNKNOWN',            'name' => 'Penyebab Belum Diketahui',  'parent_code' => null, 'sort_order' => 99],
            ];

            $now = date('Y-m-d H:i:s');
            foreach ($seedCategories as &$cat) {
                $cat['is_active']  = 1;
                $cat['created_at'] = $now;
                $cat['updated_at'] = $now;
            }
            unset($cat);

            $db->table('fault_cause_categories')->ignore(true)->insertBatch($seedCategories);
        }

        // =====================================================================
        // 2. Table: fault_events (Guards 2 & 11)
        // =====================================================================
        if (!$db->tableExists('fault_events')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'event_number' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'penyulang_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'source_device_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'event_time' => [
                    'type' => 'DATETIME',
                ],
                'topology_snapshot_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'TOPOLOGY-20260925-243-ad2c9fcb',
                ],
                'source_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'MANUAL_ENTRY',
                    // Allowed: SCADA, PMCB_RELAY, RECLOSER, MANUAL_ENTRY, IMPORT, API
                ],
                'source_reference' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                    'null'       => true,
                ],
                'raw_telemetry_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'OPEN',
                    // OPEN, ANALYZED, RESOLVED, CLOSED
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('event_number');
            $this->forge->addKey('penyulang_id');
            $this->forge->addKey('source_device_asset_id');
            $this->forge->addKey('topology_snapshot_id');
            $this->forge->addKey('source_type');
            $this->forge->addKey('status');

            if ($db->tableExists('penyulang')) {
                $this->forge->addForeignKey('penyulang_id', 'penyulang', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($db->tableExists('assets')) {
                $this->forge->addForeignKey('source_device_asset_id', 'assets', 'id', 'SET NULL', 'CASCADE');
            }

            $this->forge->createTable('fault_events', true);
        }

        // =====================================================================
        // 3. Table: fault_cases (Guards 3, 12, 13)
        // =====================================================================
        if (!$db->tableExists('fault_cases')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'case_number' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'fault_event_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'topology_snapshot_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'TOPOLOGY-20260925-243-ad2c9fcb',
                ],
                'analysis_version' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'FLI-1.0.0',
                ],
                'analysis_input_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'device_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'target_distance_meters' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                ],
                'distance_tolerance_meters' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 250.00,
                ],
                'fault_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'UNKNOWN',
                    // 1P-G, 2P-G, 2P, 3P, UNKNOWN
                ],
                'impedance_supported' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'impedance_reason' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                    'default'    => 'NO_CANONICAL_IMPEDANCE_PROFILE',
                ],
                'candidate_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'top_candidate_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'top_confidence_score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'CANDIDATE_IDENTIFIED',
                    // CANDIDATE_IDENTIFIED, FIELD_VERIFIED, RESOLVED
                ],
                'analysis_timestamp' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('case_number');
            $this->forge->addKey('fault_event_id');
            $this->forge->addKey('topology_snapshot_id');
            $this->forge->addKey('analysis_input_hash');
            $this->forge->addKey('device_asset_id');
            $this->forge->addKey('top_candidate_asset_id');
            $this->forge->addKey('status');

            if ($db->tableExists('fault_events')) {
                $this->forge->addForeignKey('fault_event_id', 'fault_events', 'id', 'CASCADE', 'CASCADE');
            }
            if ($db->tableExists('assets')) {
                $this->forge->addForeignKey('device_asset_id', 'assets', 'id', 'RESTRICT', 'CASCADE');
                $this->forge->addForeignKey('top_candidate_asset_id', 'assets', 'id', 'SET NULL', 'CASCADE');
            }

            $this->forge->createTable('fault_cases', true);
        }

        // =====================================================================
        // 4. Table: fault_candidate_assets (Guards 4, 5, 14)
        // =====================================================================
        if (!$db->tableExists('fault_candidate_assets')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'fault_case_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'rank' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                ],
                'candidate_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'CANDIDATE',
                    // Must be CANDIDATE, NOT FIELD CONFIRMED (Guard 14)
                ],
                'graph_distance_from_device_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                ],
                'distance_delta_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                ],
                'confidence_score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 0.00,
                ],
                'evidence_breakdown_json' => [
                    'type' => 'TEXT',
                    'null' => false,
                    // Guard 4: distance, topology, switching, conductor, historical
                ],
                'path_asset_ids_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('fault_case_id');
            $this->forge->addKey('asset_id');
            $this->forge->addKey('rank');
            $this->forge->addKey('candidate_status');

            if ($db->tableExists('fault_cases')) {
                $this->forge->addForeignKey('fault_case_id', 'fault_cases', 'id', 'CASCADE', 'CASCADE');
            }
            if ($db->tableExists('assets')) {
                $this->forge->addForeignKey('asset_id', 'assets', 'id', 'RESTRICT', 'CASCADE');
            }

            $this->forge->createTable('fault_candidate_assets', true);
        }

        // =====================================================================
        // 5. Table: fault_actual_findings (Guards 8, 9, 12)
        // =====================================================================
        if (!$db->tableExists('fault_actual_findings')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'fault_case_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'revision_no' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                    // Append-only revisions (Guard 9)
                ],
                'found_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'cause_category_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'actual_observed_distance_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                    // Field GPS / odometer (Guard 8)
                ],
                'actual_graph_distance_from_device_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                    // Authoritative graph distance (Guard 8)
                ],
                'matched_candidate_rank' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'technician_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'finding_notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'photo_evidence_url' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'verified_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                // NOTICE: Strictly append-only. NO updated_at or deleted_at! (Guard 9)
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey(['fault_case_id', 'revision_no']);
            $this->forge->addKey('found_asset_id');
            $this->forge->addKey('cause_category_code');

            if ($db->tableExists('fault_cases')) {
                $this->forge->addForeignKey('fault_case_id', 'fault_cases', 'id', 'CASCADE', 'CASCADE');
            }
            if ($db->tableExists('assets')) {
                $this->forge->addForeignKey('found_asset_id', 'assets', 'id', 'RESTRICT', 'CASCADE');
            }

            $this->forge->createTable('fault_actual_findings', true);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        // Drop in reverse dependency order
        $tables = [
            'fault_actual_findings',
            'fault_candidate_assets',
            'fault_cases',
            'fault_events',
            'fault_cause_categories',
        ];

        foreach ($tables as $tbl) {
            if ($db->tableExists($tbl)) {
                $this->forge->dropTable($tbl, true);
            }
        }
    }
}
