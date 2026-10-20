<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * RollbackDeterministicAssetsCommand
 *
 * Restores asset feeder relations from a pre-mutation JSON backup file.
 */
class RollbackDeterministicAssetsCommand extends BaseCommand
{
    protected $group       = 'Asset';
    protected $name        = 'asset:rollback-deterministic';
    protected $description = 'Rolls back deterministic asset mappings using a generated JSON backup file.';
    protected $usage       = 'asset:rollback-deterministic --backup=<filename_or_path>';
    protected $options     = [
        '--backup' => 'Backup JSON file name or relative path in writable/backups/ (Required)',
    ];

    public function run(array $params)
    {
        $backupArg = CLI::getOption('backup') ?? ($params['backup'] ?? null);

        if (!$backupArg) {
            CLI::error("Parameter --backup wajib diisi. Contoh: php spark asset:rollback-deterministic --backup=asset_mapping_backup_20261021_010000.json");
            return;
        }

        $filePath = $backupArg;
        if (!file_exists($filePath)) {
            $filePath = WRITEPATH . 'backups/' . ltrim($backupArg, '/\\');
        }

        if (!file_exists($filePath)) {
            CLI::error("File backup tidak ditemukan di: {$filePath}");
            return;
        }

        $raw = file_get_contents($filePath);
        $data = json_decode($raw, true);

        if (!$data || !isset($data['records']) || !is_array($data['records'])) {
            CLI::error("Format file backup tidak valid.");
            return;
        }

        $records = $data['records'];
        $count = count($records);
        CLI::write("Memulihkan {$count} rekaman aset dari: " . basename($filePath), 'yellow');

        $db = Database::connect();
        $db->transBegin();
        try {
            $restored = 0;
            foreach ($records as $r) {
                $assetId = (int)$r['id'];
                $db->table('assets')
                    ->where('id', $assetId)
                    ->update([
                        'penyulang_id'                   => $r['penyulang_id'] !== null ? (int)$r['penyulang_id'] : null,
                        'ulp_id'                         => $r['ulp_id'] !== null ? (int)$r['ulp_id'] : null,
                        'section_resolution_method'      => $r['section_resolution_method'] ?? 'UNRESOLVED',
                        'intelligence_resolution_status' => $r['intelligence_resolution_status'] ?? 'UNRESOLVED',
                        'updated_at'                     => date('Y-m-d H:i:s'),
                    ]);
                $restored++;
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException("Transaction failed during rollback execution.");
            }

            $db->transCommit();
            CLI::write("\n[ROLLBACK SUKSES] {$restored} aset berhasil dikembalikan ke status data awal.", 'green');
        } catch (\Throwable $e) {
            $db->transRollback();
            CLI::error("\n[ROLLBACK GAGAL] Transaksi dibatalkan: " . $e->getMessage());
        }
    }
}
