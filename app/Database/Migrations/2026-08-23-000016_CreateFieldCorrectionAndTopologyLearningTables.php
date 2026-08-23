<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 049 — Wave 3 Phase PH-AI-GIS-01
 * Field Asset Correction, Transline Geometry Editor, and Human-in-the-Loop AI Learning Tables
 */
class CreateFieldCorrectionAndTopologyLearningTables extends Migration
{
    public function up()
    {
        // 1. Table: field_corrections (Proposals and Governance Lifecycle)
        if (!$this->db->tableExists('field_corrections')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'correction_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'correction_type' => [
                    'type'       => 'ENUM',
                    'constraint' => [
                        'ASSET_CONSTRUCTION',
                        'ASSET_LOCATION',
                        'ASSET_ADD',
                        'ASSET_MISSING',
                        'TRANSLINE_TOPOLOGY',
                        'ASSET_CONDITION'
                    ],
                    'default'    => 'ASSET_CONSTRUCTION',
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'penyulang_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'ulp_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'before_payload' => [
                    'type' => 'JSON',
                    'null' => true,
                ],
                'after_payload' => [
                    'type' => 'JSON',
                    'null' => true,
                ],
                'rationale' => [
                    'type' => 'TEXT',
                ],
                'evidence_photo_uri' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'latitude' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,8',
                    'null'       => true,
                ],
                'longitude' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '11,8',
                    'null'       => true,
                ],
                'reporter_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'reporter_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'reporter_role' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'PETUGAS_LAPANGAN',
                ],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => [
                        'DRAFT',
                        'SUBMITTED',
                        'UNDER_REVIEW',
                        'APPROVED',
                        'APPLIED',
                        'REJECTED',
                        'WITHDRAWN',
                        'SUPERSEDED',
                        'ROLLED_BACK'
                    ],
                    'default'    => 'SUBMITTED',
                ],
                'approver_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'approver_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'approver_notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'applied_at' => [
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
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('correction_code', 'uk_field_corr_code');
            $this->forge->addKey('asset_id', false, false, 'idx_fc_asset_id');
            $this->forge->addKey('penyulang_id', false, false, 'idx_fc_penyulang_id');
            $this->forge->addKey('status', false, false, 'idx_fc_status');
            $this->forge->addKey('correction_type', false, false, 'idx_fc_corr_type');
            $this->forge->addKey('created_at', false, false, 'idx_fc_created_at');
            $this->forge->createTable('field_corrections', true);
        }

        // 2. Table: asset_change_history (Append-Only Physical Asset Audit Trail)
        if (!$this->db->tableExists('asset_change_history')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'correction_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'change_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'field_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'previous_value' => [
                    'type'       => 'TEXT',
                    'null'       => true,
                ],
                'new_value' => [
                    'type'       => 'TEXT',
                    'null'       => true,
                ],
                'actor_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'actor_role' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'rationale' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'evidence_photo_uri' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('asset_id', false, false, 'idx_ach_asset_id');
            $this->forge->addKey('correction_id', false, false, 'idx_ach_corr_id');
            $this->forge->addKey('change_type', false, false, 'idx_ach_change_type');
            $this->forge->addKey('created_at', false, false, 'idx_ach_created_at');
            $this->forge->createTable('asset_change_history', true);
        }

        // 3. Table: network_topology_versions (Versioned Feeder Geometries for Rollback & Audit)
        if (!$this->db->tableExists('network_topology_versions')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'penyulang_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'version_no' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'correction_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'geojson_topology' => [
                    'type' => 'MEDIUMTEXT',
                ],
                'nodes_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'segments_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'version_status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['PROPOSED', 'ACTIVE', 'HISTORICAL', 'ROLLED_BACK'],
                    'default'    => 'PROPOSED',
                ],
                'created_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['penyulang_id', 'version_no'], 'uk_feeder_version');
            $this->forge->addKey('penyulang_id', false, false, 'idx_ntv_penyulang_id');
            $this->forge->addKey('is_active', false, false, 'idx_ntv_is_active');
            $this->forge->createTable('network_topology_versions', true);
        }

        // 4. Table: ai_correction_feedback (Validated Human-in-the-Loop Knowledge Base)
        if (!$this->db->tableExists('ai_correction_feedback')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'correction_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'penyulang_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'feature_context' => [
                    'type' => 'JSON',
                    'null' => true,
                ],
                'predicted_value' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'ground_truth_value' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'confidence_score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'default'    => 0.0000,
                ],
                'learning_status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['PENDING', 'VALIDATED', 'REJECTED', 'PROMOTED'],
                    'default'    => 'PENDING',
                ],
                'validated_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'validated_at' => [
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
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('correction_id', false, false, 'idx_acf_corr_id');
            $this->forge->addKey('asset_id', false, false, 'idx_acf_asset_id');
            $this->forge->addKey('penyulang_id', false, false, 'idx_acf_penyulang_id');
            $this->forge->addKey('learning_status', false, false, 'idx_acf_learning_status');
            $this->forge->createTable('ai_correction_feedback', true);
        }

        // 5. Table: asset_sequence_counters (Collision-Proof Sequence Counter per Feeder & Type)
        if (!$this->db->tableExists('asset_sequence_counters')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'penyulang_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'asset_type_prefix' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'last_sequence_no' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['penyulang_id', 'asset_type_prefix'], 'uk_feeder_asset_prefix');
            $this->forge->createTable('asset_sequence_counters', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('asset_sequence_counters', true);
        $this->forge->dropTable('ai_correction_feedback', true);
        $this->forge->dropTable('network_topology_versions', true);
        $this->forge->dropTable('asset_change_history', true);
        $this->forge->dropTable('field_corrections', true);
    }
}
