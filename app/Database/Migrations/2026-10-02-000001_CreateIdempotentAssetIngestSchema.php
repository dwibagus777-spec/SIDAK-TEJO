<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateIdempotentAssetIngestSchema extends Migration
{
    public function up()
    {
        // 1. asset_ingest_batches
        if (!$this->db->tableExists('asset_ingest_batches')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'batch_uuid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'source_file' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'source_part' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                ],
                'total_rows' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'matched_existing' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'source_duplicate' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'conflict_review' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'quarantine' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'candidate_new_asset' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'inserted' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'already_processed' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'duplicate_created' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'topology_delta' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['PREPARED', 'ASSET_COMMIT_IN_PROGRESS', 'BUILDING_NETWORK', 'COMMITTED', 'COMPLETED', 'FAILED', 'CANCELLED'],
                    'default'    => 'PREPARED',
                ],
                'fingerprint' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'created_by' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'processed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('batch_uuid');
            $this->forge->addKey('status');
            $this->forge->createTable('asset_ingest_batches', true);
        }

        // 2. asset_ingest_rows
        if (!$this->db->tableExists('asset_ingest_rows')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'batch_uuid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'source_file' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'source_part' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                ],
                'source_row_number' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                ],
                'source_fingerprint' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'canonical_identity' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'unit_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'ulp_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'feeder_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'asset_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'section_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
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
                'raw_data' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'processing_status' => [
                    'type'       => 'ENUM',
                    'constraint' => [
                        'RECEIVED',
                        'VALIDATED',
                        'MATCHED_EXISTING',
                        'SOURCE_DUPLICATE',
                        'CONFLICT_REVIEW',
                        'QUARANTINE',
                        'CANDIDATE_NEW_ASSET',
                        'INSERTED',
                        'SKIPPED_ALREADY_PROCESSED',
                        'FAILED'
                    ],
                    'default'    => 'RECEIVED',
                ],
                'classification' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'UNCLASSIFIED',
                ],
                'matched_asset_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'error_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'processed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('source_fingerprint'); // CRITICAL FOR IDEMPOTENCY
            $this->forge->addKey('batch_uuid');
            $this->forge->addKey('processing_status');
            $this->forge->addKey('matched_asset_id');
            $this->forge->createTable('asset_ingest_rows', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('asset_ingest_rows', true);
        $this->forge->dropTable('asset_ingest_batches', true);
    }
}
