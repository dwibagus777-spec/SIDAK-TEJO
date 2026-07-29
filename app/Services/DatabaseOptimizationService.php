<?php

namespace App\Services;

class DatabaseOptimizationService
{
    public static function optimizeIndexes(): array
    {
        $db = \Config\Database::connect();
        $logs = [];

        $indexesToEnsure = [
            'temuan' => [
                'idx_temuan_ulp'        => ['ulp_id'],
                'idx_temuan_penyulang'  => ['penyulang_id'],
                'idx_temuan_section'    => ['section_id'],
                'idx_temuan_status'     => ['status'],
                'idx_temuan_prioritas'  => ['prioritas'],
                'idx_temuan_jenis'      => ['jenis_temuan'],
                'idx_temuan_tgl'        => ['tanggal_temuan'],
                'idx_temuan_deleted'    => ['deleted_at'],
                'idx_temuan_composite'  => ['ulp_id', 'status', 'deleted_at'],
            ],
            'work_orders' => [
                'idx_wo_asset'     => ['asset_id'],
                'idx_wo_temuan'    => ['temuan_id'],
                'idx_wo_status'    => ['status'],
                'idx_wo_prioritas' => ['prioritas'],
                'idx_wo_deleted'   => ['deleted_at'],
            ],
            'assets' => [
                'idx_assets_ulp'       => ['ulp_id'],
                'idx_assets_penyulang' => ['penyulang_id'],
                'idx_assets_section'   => ['section_id'],
                'idx_assets_status'    => ['status'],
                'idx_assets_deleted'   => ['deleted_at'],
            ],
            'documents' => [
                'idx_doc_checksum' => ['checksum'],
                'idx_doc_status'   => ['status'],
                'idx_doc_jenis'    => ['jenis_dokumen'],
                'idx_doc_deleted'  => ['deleted_at'],
            ],
            'api_logs' => [
                'idx_apilog_method'   => ['method'],
                'idx_apilog_status'   => ['status_code'],
                'idx_apilog_created'  => ['created_at'],
            ],
            'notifications' => [
                'idx_notif_user'   => ['user_id'],
                'idx_notif_read'   => ['read_at'],
            ],
        ];

        foreach ($indexesToEnsure as $table => $indexes) {
            if (!$db->tableExists($table)) continue;

            $existing = [];
            try {
                $query = $db->query("SHOW INDEX FROM {$table}");
                if ($query) {
                    foreach ($query->getResultArray() as $row) {
                        $existing[] = $row['Key_name'];
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', "[DatabaseOptimizer] Error checking index on {$table}: " . $e->getMessage());
            }

            foreach ($indexes as $indexName => $columns) {
                if (in_array($indexName, $existing)) continue;

                $colList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
                try {
                    $db->query("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$colList})");
                    $logs[] = "Added index {$indexName} to {$table}";
                } catch (\Throwable $e) {
                    $logs[] = "Index {$indexName} on {$table} skipped: " . $e->getMessage();
                }
            }
        }

        return $logs;
    }
}
