<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceHistoricalFeederInterruptionsSchema extends Migration
{
    public function up(): void
    {
        $db = $this->db;

        // 1. Add workbook specific provenance columns if they don't already exist
        $fieldsToAdd = [];

        if (!$db->fieldExists('up3_name', 'historical_feeder_interruptions')) {
            $fieldsToAdd['up3_name'] = [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => 'SDA',
                'after'      => 'substation_name',
                'comment'    => 'Origin UP3 (e.g. SDA)',
            ];
        }

        if (!$db->fieldExists('masuk_total_raw', 'historical_feeder_interruptions')) {
            $fieldsToAdd['masuk_total_raw'] = [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'interruption_ended_at',
                'comment'    => 'Raw masuk total time from workbook',
            ];
        }

        if (!$db->fieldExists('lama_padam_raw', 'historical_feeder_interruptions')) {
            $fieldsToAdd['lama_padam_raw'] = [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'outage_duration_minutes',
                'comment'    => 'Raw duration string from workbook (e.g. 0.04.00)',
            ];
        }

        if (!$db->fieldExists('kategori_raw', 'historical_feeder_interruptions')) {
            $fieldsToAdd['kategori_raw'] = [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'interruption_category',
                'comment'    => 'Raw category string from workbook (including #REF!, Temporer, etc.)',
            ];
        }

        if (!empty($fieldsToAdd)) {
            $this->forge->addColumn('historical_feeder_interruptions', $fieldsToAdd);
        }

        // 2. Modify cause fields to be nullable to support imperfect raw import records safely
        $modifyFields = [
            'cause_raw' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => 'Belum Diketemukan',
            ],
            'cause_canonical_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => 'CAUSE_UNKNOWN',
            ],
            'cause_category' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => 'UNKNOWN',
            ],
        ];
        $this->forge->modifyColumn('historical_feeder_interruptions', $modifyFields);

        // 3. Ensure indexing on event_date, penyulang_id, ulp_id for fast analytics
        // Safely check and add indexes
        try {
            $db->query("CREATE INDEX `idx_hfi_event_date` ON `historical_feeder_interruptions` (`event_date`)");
        } catch (\Throwable $e) {}

        try {
            $db->query("CREATE INDEX `idx_hfi_penyulang_date` ON `historical_feeder_interruptions` (`penyulang_id`, `event_date`)");
        } catch (\Throwable $e) {}

        try {
            $db->query("CREATE INDEX `idx_hfi_ulp_date` ON `historical_feeder_interruptions` (`ulp_id`, `event_date`)");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        $db = $this->db;

        // Drop indexes
        try { $db->query("DROP INDEX `idx_hfi_event_date` ON `historical_feeder_interruptions`"); } catch (\Throwable $e) {}
        try { $db->query("DROP INDEX `idx_hfi_penyulang_date` ON `historical_feeder_interruptions`"); } catch (\Throwable $e) {}
        try { $db->query("DROP INDEX `idx_hfi_ulp_date` ON `historical_feeder_interruptions`"); } catch (\Throwable $e) {}

        // Drop added columns
        $colsToDrop = ['up3_name', 'masuk_total_raw', 'lama_padam_raw', 'kategori_raw'];
        foreach ($colsToDrop as $col) {
            if ($db->fieldExists($col, 'historical_feeder_interruptions')) {
                $this->forge->dropColumn('historical_feeder_interruptions', $col);
            }
        }
    }
}
