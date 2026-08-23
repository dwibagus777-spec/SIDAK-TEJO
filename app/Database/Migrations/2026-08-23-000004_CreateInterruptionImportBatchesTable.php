<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInterruptionImportBatchesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'batch_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'comment'    => 'Unique batch tracking code (e.g. BATCH-SDA-2025-01)',
            ],
            'source_system' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'GOOGLE_SPREADSHEET_PLN_SDA',
                'comment'    => 'Origin source system identifier',
            ],
            'source_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Spreadsheet / CSV source reference',
            ],
            'source_sheet_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => 'Sheet / tab name in source spreadsheet',
            ],
            'total_rows_read' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'comment'    => 'Total rows scanned from source',
            ],
            'total_rows_imported' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'comment'    => 'Total rows successfully ingested',
            ],
            'total_rows_duplicate' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'comment'    => 'Duplicate records detected and skipped',
            ],
            'total_rows_flagged' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'comment'    => 'Rows with unmapped causes or data quality warnings',
            ],
            'batch_checksum' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => true,
                'comment'    => 'SHA-256 batch provenance checksum',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['PROCESSING', 'COMPLETED', 'FAILED', 'PARTIAL'],
                'default'    => 'COMPLETED',
                'comment'    => 'Batch processing status',
            ],
            'imported_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'SYSTEM_INGESTION_SERVICE',
            ],
            'notes' => [
                'type'    => 'TEXT',
                'null'    => true,
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

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('batch_code', false, false, 'idx_batch_code');
        $this->forge->addKey('status', false, false, 'idx_batch_status');

        $this->forge->createTable('interruption_import_batches', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('interruption_import_batches', true);
    }
}
