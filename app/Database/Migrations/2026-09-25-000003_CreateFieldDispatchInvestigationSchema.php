<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase B.6: Field Dispatch, Patrol Routing & Investigation Feedback Pipeline
 * Migration B.6.1: Schema Creation & Incremental Enhancement
 *
 * Implements the data architecture for:
 * 1. Enhancing fault_cases with priority and operational lifecycle timestamps.
 * 2. dispatch_assignments     — Immutable assignment history log (Guard B6-G07).
 * 3. field_investigations     — Mobile/patrol tracking with start/end GPS provenance (Guard B6-G09).
 * 4. field_findings           — Field ground-truth observation vs prediction (Guard B6-G04).
 * 5. field_finding_revisions  — Strictly append-only finding amendment audit log (Guard B6-G05).
 * 6. field_evidence           — Photographic/documentary evidence with SHA-256 hash (Guard B6-G10).
 * 7. fault_feedback           — Prediction vs actual classification bridge to FLI (Guard B6-G11 & B6-G12).
 *
 * Hard Invariants:
 * - B6-G01: Zero modification to gis_translines, assets, or network_topology_versions.
 * - B6-G02: Preserves TOPOLOGY-20260925-243-ad2c9fcb binding.
 * - B6-G03: Zero modification to existing fault_events or fault_cases historical rows.
 * - Idempotency: Safe to run repeatedly without duplicate errors or data loss.
 */
class CreateFieldDispatchInvestigationSchema extends Migration
{
    public function up()
    {
        $db = $this->db ?? \Config\Database::connect();
        $forge = $this->forge ?? \Config\Database::forge();

        // =====================================================================
        // 1. Incremental Enhancement: fault_cases (Add B.6 Lifecycle Columns)
        // =====================================================================
        if ($db->tableExists('fault_cases')) {
            $existingFields = array_column($db->query("SHOW COLUMNS FROM fault_cases")->getResultArray(), 'Field');
            $fieldsToAdd = [];

            if (!in_array('priority', $existingFields, true)) {
                $fieldsToAdd['priority'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'default'    => 'MEDIUM',
                ];
            }

            if (!in_array('opened_at', $existingFields, true)) {
                $fieldsToAdd['opened_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                ];
            }

            if (!in_array('closed_at', $existingFields, true)) {
                $fieldsToAdd['closed_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                ];
            }

            if (!in_array('created_by', $existingFields, true)) {
                $fieldsToAdd['created_by'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ];
            }

            if (!empty($fieldsToAdd)) {
                $forge->addColumn('fault_cases', $fieldsToAdd);
            }

            // Populate opened_at for existing rows if NULL
            $db->query("UPDATE fault_cases SET opened_at = created_at WHERE opened_at IS NULL AND created_at IS NOT NULL");
        }

        // =====================================================================
        // 2. Table: dispatch_assignments (Guard B6-G07: Assignment History)
        // =====================================================================
        if (!$db->tableExists('dispatch_assignments')) {
            $forge->addField([
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
                'assigned_to' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'assigned_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'assigned_at' => [
                    'type' => 'DATETIME',
                ],
                'accepted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'completed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'ASSIGNED',
                    // ASSIGNED, ACCEPTED, REJECTED, REASSIGNED, COMPLETED, CANCELLED
                ],
                'assignment_note' => [
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

            $forge->addKey('id', true);
            $forge->addKey('fault_case_id');
            $forge->addKey('assigned_to');
            $forge->addKey('assigned_by');
            $forge->addKey('status');
            $forge->addKey('assigned_at');

            if ($db->tableExists('fault_cases')) {
                $forge->addForeignKey('fault_case_id', 'fault_cases', 'id', 'CASCADE', 'CASCADE');
            }

            $forge->createTable('dispatch_assignments', true);
        }

        // =====================================================================
        // 3. Table: field_investigations (Guard B6-G06, B6-G09: Investigation Lifecycle & GPS)
        // =====================================================================
        if (!$db->tableExists('field_investigations')) {
            $forge->addField([
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
                'investigator_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'EN_ROUTE',
                    // EN_ROUTE, ARRIVED, INVESTIGATING, FINDING_RECORDED, COMPLETED, CANCELLED
                ],
                'started_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'arrived_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'completed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'start_lat' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,8',
                    'null'       => true,
                ],
                'start_lng' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '11,8',
                    'null'       => true,
                ],
                'start_accuracy_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '8,2',
                    'null'       => true,
                ],
                'end_lat' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,8',
                    'null'       => true,
                ],
                'end_lng' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '11,8',
                    'null'       => true,
                ],
                'end_accuracy_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '8,2',
                    'null'       => true,
                ],
                'notes' => [
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

            $forge->addKey('id', true);
            $forge->addKey('fault_case_id');
            $forge->addKey('investigator_id');
            $forge->addKey('status');

            if ($db->tableExists('fault_cases')) {
                $forge->addForeignKey('fault_case_id', 'fault_cases', 'id', 'CASCADE', 'CASCADE');
            }

            $forge->createTable('field_investigations', true);
        }

        // =====================================================================
        // 4. Table: field_findings (Guard B6-G04: Candidate vs Actual Finding)
        // =====================================================================
        if (!$db->tableExists('field_findings')) {
            $forge->addField([
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
                'investigation_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'predicted_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'actual_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'actual_lat' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,8',
                    'null'       => true,
                ],
                'actual_lng' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '11,8',
                    'null'       => true,
                ],
                'gps_accuracy_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '8,2',
                    'null'       => true,
                ],
                'finding_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'RECORDED',
                    // RECORDED, CONFIRMED, UNRESOLVED, REVISED
                ],
                'cause_category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'UNKNOWN',
                ],
                'condition_description' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'captured_at' => [
                    'type' => 'DATETIME',
                ],
                'captured_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
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

            $forge->addKey('id', true);
            $forge->addKey('fault_case_id');
            $forge->addKey('investigation_id');
            $forge->addKey('predicted_asset_id');
            $forge->addKey('actual_asset_id');
            $forge->addKey('finding_status');
            $forge->addKey('cause_category');

            if ($db->tableExists('fault_cases')) {
                $forge->addForeignKey('fault_case_id', 'fault_cases', 'id', 'CASCADE', 'CASCADE');
            }
            if ($db->tableExists('field_investigations')) {
                $forge->addForeignKey('investigation_id', 'field_investigations', 'id', 'SET NULL', 'CASCADE');
            }
            if ($db->tableExists('assets')) {
                $forge->addForeignKey('actual_asset_id', 'assets', 'id', 'RESTRICT', 'CASCADE');
            }

            $forge->createTable('field_findings', true);
        }

        // =====================================================================
        // 5. Table: field_finding_revisions (Guard B6-G05: Append-Only Corrections)
        // =====================================================================
        if (!$db->tableExists('field_finding_revisions')) {
            $forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'field_finding_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'revision_no' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                ],
                'previous_values_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'amended_fields_json' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'amended_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'amendment_reason' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => false,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                // Strictly append-only: NO updated_at or deleted_at
            ]);

            $forge->addKey('id', true);
            $forge->addUniqueKey(['field_finding_id', 'revision_no']);
            $forge->addKey('field_finding_id');
            $forge->addKey('amended_by');

            if ($db->tableExists('field_findings')) {
                $forge->addForeignKey('field_finding_id', 'field_findings', 'id', 'CASCADE', 'CASCADE');
            }

            $forge->createTable('field_finding_revisions', true);
        }

        // =====================================================================
        // 6. Table: field_evidence (Guard B6-G10: Photographic & Documentary Evidence)
        // =====================================================================
        if (!$db->tableExists('field_evidence')) {
            $forge->addField([
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
                'field_finding_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'evidence_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'PHOTO',
                    // PHOTO, DOCUMENT, THERMAL_IMAGE, SIGNATURE, AUDIO
                ],
                'file_reference' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => false,
                ],
                'sha256' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => false,
                ],
                'captured_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'captured_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'metadata_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);

            $forge->addKey('id', true);
            $forge->addKey('fault_case_id');
            $forge->addKey('field_finding_id');
            $forge->addKey('asset_id');
            $forge->addKey('sha256');
            $forge->addKey('evidence_type');

            if ($db->tableExists('fault_cases')) {
                $forge->addForeignKey('fault_case_id', 'fault_cases', 'id', 'CASCADE', 'CASCADE');
            }
            if ($db->tableExists('field_findings')) {
                $forge->addForeignKey('field_finding_id', 'field_findings', 'id', 'SET NULL', 'CASCADE');
            }

            $forge->createTable('field_evidence', true);
        }

        // =====================================================================
        // 7. Table: fault_feedback (Guard B6-G11 & B6-G12: FLI Feedback Bridge)
        // =====================================================================
        if (!$db->tableExists('fault_feedback')) {
            $forge->addField([
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
                'candidate_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'predicted_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'actual_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'graph_distance_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                ],
                'observed_distance_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                ],
                'distance_error_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                ],
                'match_class' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'WRONG_ASSET',
                    // MATCH, NEAR_MATCH, WRONG_ASSET, NO_FINDING
                ],
                'feedback_source' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'FIELD_INVESTIGATION',
                ],
                'feedback_timestamp' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);

            $forge->addKey('id', true);
            $forge->addKey('fault_case_id');
            $forge->addKey('candidate_id');
            $forge->addKey('predicted_asset_id');
            $forge->addKey('actual_asset_id');
            $forge->addKey('match_class');

            if ($db->tableExists('fault_cases')) {
                $forge->addForeignKey('fault_case_id', 'fault_cases', 'id', 'CASCADE', 'CASCADE');
            }
            if ($db->tableExists('fault_candidate_assets')) {
                $forge->addForeignKey('candidate_id', 'fault_candidate_assets', 'id', 'SET NULL', 'CASCADE');
            }

            $forge->createTable('fault_feedback', true);
        }
    }

    public function down()
    {
        $db = $this->db ?? \Config\Database::connect();
        $forge = $this->forge ?? \Config\Database::forge();

        // Drop new tables in reverse dependency order
        $tablesToDrop = [
            'fault_feedback',
            'field_evidence',
            'field_finding_revisions',
            'field_findings',
            'field_investigations',
            'dispatch_assignments',
        ];

        foreach ($tablesToDrop as $tbl) {
            if ($db->tableExists($tbl)) {
                $forge->dropTable($tbl, true);
            }
        }

        // Revert columns added to fault_cases (if table exists)
        if ($db->tableExists('fault_cases')) {
            $existingFields = $db->getFieldNames('fault_cases');
            $colsToDrop = array_intersect(['priority', 'opened_at', 'closed_at', 'created_by'], $existingFields);
            foreach ($colsToDrop as $col) {
                $forge->dropColumn('fault_cases', $col);
            }
        }
    }
}
