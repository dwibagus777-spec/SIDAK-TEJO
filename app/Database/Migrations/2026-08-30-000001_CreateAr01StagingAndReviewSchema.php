<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration for AR-01 Phase 5E: Staging, Review Decisions, and Audit Log Schema
 */
class CreateAr01StagingAndReviewSchema extends Migration
{
    public function up()
    {
        // 1. ar01_ingestion_batches
        if (!$this->db->tableExists('ar01_ingestion_batches')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'batch_id'        => ['type' => 'VARCHAR', 'constraint' => 100, 'unique' => true],
                'source_filename' => ['type' => 'VARCHAR', 'constraint' => 255],
                'source_path'     => ['type' => 'VARCHAR', 'constraint' => 255],
                'source_sha256'   => ['type' => 'VARCHAR', 'constraint' => 64],
                'source_size'     => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
                'row_count'       => ['type' => 'INT', 'default' => 0],
                'pass_count'      => ['type' => 'INT', 'default' => 0],
                'warning_count'   => ['type' => 'INT', 'default' => 0],
                'reject_count'    => ['type' => 'INT', 'default' => 0],
                'status'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'STAGED'],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'created_by'      => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'SYSTEM_PARSER'],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->createTable('ar01_ingestion_batches', true);
        }

        // 2. ar01_staging_assets
        if (!$this->db->tableExists('ar01_staging_assets')) {
            $this->forge->addField([
                'id'                         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'batch_id'                   => ['type' => 'VARCHAR', 'constraint' => 100],
                'source_row_number'          => ['type' => 'INT'],
                'source_asset_code'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'source_asset_name'          => ['type' => 'VARCHAR', 'constraint' => 255],
                'source_feeder_name'         => ['type' => 'VARCHAR', 'constraint' => 150],
                'source_section_name'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'source_latitude'            => ['type' => 'DOUBLE', 'null' => true],
                'source_longitude'           => ['type' => 'DOUBLE', 'null' => true],
                'source_construction_code'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_conductor_material'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_asset_type'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'proposed_penyulang_id'      => ['type' => 'INT', 'null' => true],
                'proposed_section_id'        => ['type' => 'INT', 'null' => true],
                'proposed_construction_type_id' => ['type' => 'INT', 'null' => true],
                'normalized_feeder_name'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'normalized_construction_code'=> ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'normalization_score'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '100.00'],
                'validation_status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PASS'],
                'validation_messages'        => ['type' => 'TEXT', 'null' => true],
                'review_status'              => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'READY_FOR_REVIEW'],
                'approved_at'                => ['type' => 'DATETIME', 'null' => true],
                'rejected_at'                => ['type' => 'DATETIME', 'null' => true],
                'created_at'                 => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['batch_id', 'source_row_number']);
            $this->forge->createTable('ar01_staging_assets', true);
        }

        // 3. ar01_review_decisions
        if (!$this->db->tableExists('ar01_review_decisions')) {
            $this->forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'batch_id'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'staging_asset_id'   => ['type' => 'INT', 'null' => true],
                'source_row_number'  => ['type' => 'INT', 'null' => true],
                'scope'              => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'SINGLE_ROW'],
                'decision'           => ['type' => 'VARCHAR', 'constraint' => 30],
                'decision_reason'    => ['type' => 'TEXT'],
                'approver_nip'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'approver_name'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'approver_role'      => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'ENGINEERING_REVIEWER'],
                'signed_sha256'      => ['type' => 'VARCHAR', 'constraint' => 64],
                'approved_at'        => ['type' => 'DATETIME', 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['batch_id', 'decision']);
            $this->forge->createTable('ar01_review_decisions', true);
        }

        // 4. ar01_audit_log
        if (!$this->db->tableExists('ar01_audit_log')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'batch_id'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'event_type'  => ['type' => 'VARCHAR', 'constraint' => 100],
                'actor'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'SYSTEM'],
                'event_data'  => ['type' => 'TEXT', 'null' => true],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'SUCCESS'],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->createTable('ar01_audit_log', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('ar01_audit_log', true);
        $this->forge->dropTable('ar01_review_decisions', true);
        $this->forge->dropTable('ar01_staging_assets', true);
        $this->forge->dropTable('ar01_ingestion_batches', true);
    }
}
