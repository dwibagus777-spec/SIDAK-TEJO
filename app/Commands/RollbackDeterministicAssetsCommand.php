<?php

namespace App\Commands;

use App\Services\AssetReconciliationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

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

        $service = new AssetReconciliationService();
        $result = $service->rollbackDeterministicMapping($backupArg);

        if ($result['status'] === 'error') {
            CLI::error("[ERROR] " . $result['message']);
            return;
        }

        CLI::write("[SUKSES] " . $result['message'], 'green');
    }
}
