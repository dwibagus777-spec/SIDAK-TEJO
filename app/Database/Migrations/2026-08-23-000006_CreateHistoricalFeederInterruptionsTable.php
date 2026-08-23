<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHistoricalFeederInterruptionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // ─────────────────────────────────────────────────────────────
            // LAYER A: SOURCE EVIDENCE LAYER (IMMUTABLE PROVENANCE)
            // ─────────────────────────────────────────────────────────────
            'batch_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK to interruption_import_batches.id',
            ],
            'source_system' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'GOOGLE_SPREADSHEET_PLN_SDA',
            ],
            'source_sheet_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'source_row_number' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'comment'    => '1-indexed row number in origin spreadsheet',
            ],
            'source_record_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'SHA-256 hash of origin row for deduplication',
            ],
            'raw_payload_json' => [
                'type'       => 'LONGTEXT',
                'null'       => false,
                'comment'    => 'Immutable JSON snapshot of all 31 origin raw columns',
            ],

            // ─────────────────────────────────────────────────────────────
            // LAYER B: CANONICAL OPERATIONAL LAYER (NORMALIZED TIME & GRID)
            // ─────────────────────────────────────────────────────────────
            'event_date' => [
                'type'    => 'DATE',
                'null'    => false,
                'comment' => 'Standardized event date (YYYY-MM-DD)',
            ],
            'interruption_started_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
            ],
            'interruption_ended_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
            ],
            'outage_duration_minutes' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
                'comment'    => 'Duration of interruption in minutes',
            ],
            'substation_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'ulp_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'feeder_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'switching_device_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'RECL-PMCB',
                'comment'    => 'PMT or RECL-PMCB',
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => 'Specific Recloser / PMCB name',
            ],
            'relay_trip_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'comment'    => 'DGR, OCR-INST, OCR, EF, etc.',
            ],
            'faulted_phase' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'comment'    => 'R, S, T, R-S, R-T, S-T, R-S-T',
            ],
            'weather_condition' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'comment'    => 'cerah, hujan, berangin, hujan-angin, etc.',
            ],
            'fault_current_amperes' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'energy_not_supplied_kwh' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
            ],
            'interruption_category' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'TEMPORARY',
                'comment'    => 'TEMPORARY vs PERMANENT',
            ],
            'interruption_group' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'GGN_PENYULANG',
            ],

            // ─────────────────────────────────────────────────────────────
            // LAYER C: HISTORICAL INTELLIGENCE & CAUSE ANCHOR LAYER
            // ─────────────────────────────────────────────────────────────
            'cause_raw' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
                'comment'    => 'Authoritative raw cause from PENYEBAB SESUAI KODE GANGGUAN',
            ],
            'cause_canonical_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'comment'    => 'Governed normalized code (e.g. CAUSE_ANIMAL_CONTACT)',
            ],
            'cause_category' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'comment'    => 'Taxonomy category cluster',
            ],
            'cause_mapping_status' => [
                'type'       => 'ENUM',
                'constraint' => ['RESOLVED', 'PARTIALLY_RESOLVED', 'UNMAPPED'],
                'default'    => 'RESOLVED',
            ],
            'field_narrative_raw' => [
                'type'       => 'TEXT',
                'null'       => true,
                'comment'    => 'Original narrative from KETERANGAN column',
            ],
            'restoration_action_raw' => [
                'type'       => 'TEXT',
                'null'       => true,
                'comment'    => 'Original action from TINDAK LANJUT column',
            ],
            'extracted_zone_section' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => 'e.g. Zona 2 Section 3 parsed from narrative',
            ],
            'extracted_distance_kms' => [
                'type'       => 'DECIMAL',
                'constraint' => '6,2',
                'null'       => true,
                'comment'    => 'Distance in km from substation / recloser if stated',
            ],
            'asset_reference_raw' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'comment'    => 'e.g. PA 1003, GTT PC 328, Tiang 76 parsed from narrative',
            ],
            'asset_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Nullable FK to assets.id if matched',
            ],
            'section_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Nullable FK to sections.id if matched',
            ],
            'data_quality_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => 1.00,
                'comment'    => 'Completeness factor (0.00 to 1.00)',
            ],
            'ingestion_status' => [
                'type'       => 'ENUM',
                'constraint' => ['VALID', 'FLAGGED', 'ARCHIVED'],
                'default'    => 'VALID',
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
        $this->forge->addKey('source_record_hash', false, false, 'idx_rec_hash');
        $this->forge->addKey(['feeder_name', 'event_date'], false, false, 'idx_feeder_date');
        $this->forge->addKey(['cause_canonical_code', 'relay_trip_type'], false, false, 'idx_cause_relay');
        $this->forge->addKey(['substation_name', 'feeder_name', 'interruption_category'], false, false, 'idx_sub_fdr_cat');
        $this->forge->addKey('batch_id', false, false, 'idx_hist_batch');

        $this->forge->createTable('historical_feeder_interruptions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('historical_feeder_interruptions', true);
    }
}
