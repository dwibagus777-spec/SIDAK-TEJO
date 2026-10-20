<?php

namespace App\Commands;

use App\Services\AssetReconciliationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * MapDeterministicAssetsCommand
 *
 * Deterministic high-confidence network feeder mapping for unassigned assets.
 * 
 * Strict Safety Guarantee:
 * - Reversible transaction.
 * - Mandatory pre-mutation JSON backup written to writable/backups/.
 * - Explicit audit trail (section_resolution_method = 'TOPOLOGY_DETERMINISTIC_RECON').
 * - Preserves user-assigned assets and leaves ambiguous candidates in review queue.
 */
class MapDeterministicAssetsCommand extends BaseCommand
{
    protected $group       = 'Asset';
    protected $name        = 'asset:map-deterministic';
    protected $description = 'Maps unassigned master assets to feeders using deterministic slug topology with rollback backup.';
    protected $usage       = 'asset:map-deterministic [--dry-run] [--execute] [--limit=<n>] [--feeder=<name_or_id>]';
    protected $options     = [
        '--dry-run' => 'Simulate mapping without modifying the database (Default)',
        '--execute' => 'Execute the deterministic mapping inside a transaction with pre-backup',
        '--limit'   => 'Limit number of assets to process in this run',
        '--feeder'  => 'Filter mapping for a specific target feeder ID or name',
    ];

    public function run(array $params)
    {
        $isExecute    = CLI::getOption('execute') !== null;
        $isDryRun     = CLI::getOption('dry-run') !== null || !$isExecute;
        $limit        = CLI::getOption('limit') ? (int)CLI::getOption('limit') : null;
        $feederFilter = CLI::getOption('feeder') ?? null;

        CLI::write("==================================================================", 'cyan');
        CLI::write("   SIDAK TEJO — DETERMINISTIC ASSET FEEDER RECONCILIATION ENGINE  ", 'cyan');
        CLI::write("==================================================================", 'cyan');
        CLI::write("Mode: " . ($isExecute ? "LIVE EXECUTION (WITH BACKUP)" : "DRY-RUN (READ-ONLY SIMULATION)"), $isExecute ? 'yellow' : 'green');

        $service = new AssetReconciliationService();
        $result = $service->runDeterministicMapping($isExecute, $limit, $feederFilter);

        if ($result['status'] === 'error') {
            CLI::error("[ERROR] " . $result['message']);
            return;
        }

        CLI::write("\nDiagnostik Inventaris Aset:", 'white');
        CLI::write("  • Total Aset Aktif  : " . ($result['diagnostics_before']['assets_total_active'] ?? '-') . " unit", 'cyan');
        CLI::write("  • Aset Unassigned   : " . ($result['total_unassigned'] ?? 0) . " unit", 'yellow');
        CLI::write("  • High Confidence   : " . ($result['matched_count'] ?? 0) . " unit", 'green');
        CLI::write("  • Ambigu (Review)   : " . ($result['ambiguous_count'] ?? 0) . " unit", 'light_gray');

        if (!empty($result['top_feeders'])) {
            CLI::write("\nDistribusi Calon Pemetaan per Penyulang (Top 10):", 'white');
            $c = 0;
            foreach ($result['top_feeders'] as $fName => $cnt) {
                CLI::write(sprintf("  - %-30s : %5d unit", $fName, $cnt), 'cyan');
                $c++;
                if ($c >= 10) break;
            }
        }

        if (!$isExecute) {
            CLI::write("\n[DRY RUN SELESAI] " . $result['message'], 'yellow');
            CLI::write("Untuk menerapkan perubahan secara permanen dengan backup JSON otomatis, jalankan:", 'white');
            CLI::write("  php spark asset:map-deterministic --execute\n", 'cyan');
            return;
        }

        CLI::write("\n[SUKSES] " . $result['message'], 'green');
        CLI::write("Snapshot backup tersimpan di: " . ($result['backup_path'] ?? '-'), 'white');
        CLI::write("Perintah Rollback: " . ($result['rollback_command'] ?? '-'), 'yellow');
    }
}
