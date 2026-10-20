<?php

namespace App\Commands;

use App\Services\AssetReconciliationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * ReconcileKalisogoCommand
 *
 * Audits and safely canonicalizes duplicate Kalisogo ingest assets with JSON backup and FK preservation.
 */
class ReconcileKalisogoCommand extends BaseCommand
{
    protected $group       = 'Asset';
    protected $name        = 'asset:reconcile-kalisogo';
    protected $description = 'Audits and safely canonicalizes duplicate Kalisogo ingest assets with pre-backup and FK rerouting.';
    protected $usage       = 'asset:reconcile-kalisogo [--dry-run] [--execute]';
    protected $options     = [
        '--dry-run' => 'Simulate deduplication without modifying the database (Default)',
        '--execute' => 'Execute deduplication in transaction with pre-backup and FK rerouting',
    ];

    public function run(array $params)
    {
        $isExecute = CLI::getOption('execute') !== null;

        CLI::write("==================================================================", 'cyan');
        CLI::write("   SIDAK TEJO — KALISOGO ASSET DEDUPLICATION ENGINE               ", 'cyan');
        CLI::write("==================================================================", 'cyan');
        CLI::write("Mode: " . ($isExecute ? "LIVE EXECUTION (WITH PRE-BACKUP & FK REROUTING)" : "DRY-RUN (READ-ONLY AUDIT)"), $isExecute ? 'yellow' : 'green');

        $service = new AssetReconciliationService();
        $result = $service->reconcileKalisogoDuplicates($isExecute);

        if (($result['status'] ?? '') === 'error') {
            CLI::error("[ERROR] " . ($result['message'] ?? 'Unknown error occurred'));
            return;
        }

        CLI::write("\nDiagnostik Aset Kalisogo:", 'white');
        CLI::write("  • Total Aset Sebelum       : " . ($result['total_active_before'] ?? 0) . " unit", 'cyan');
        CLI::write("  • Legacy Baseline Assets   : " . ($result['legacy_count'] ?? 0) . " unit", 'white');
        CLI::write("  • Ingest Batch Assets      : " . ($result['ingest_batch_count'] ?? 0) . " unit", 'yellow');
        CLI::write("  • Exact Duplicate Pairs    : " . ($result['exact_duplicate_pairs_found'] ?? 0) . " pasang", 'green');
        CLI::write("  • Ingest Unmatched (Keep)  : " . ($result['unmatched_ingest_remaining'] ?? 0) . " unit", 'light_gray');
        CLI::write("  • Temuan Diarahkan Ulang   : " . ($result['dependencies_rerouted']['temuan_count'] ?? 0) . " baris", 'magenta');
        CLI::write("  • Translines Diarahkan     : " . (($result['dependencies_rerouted']['translines_source_count'] ?? 0) + ($result['dependencies_rerouted']['translines_target_count'] ?? 0)) . " segmen", 'magenta');

        if ($isExecute) {
            CLI::write("\nHasil Mutasi Terverifikasi:", 'green');
            CLI::write("  • Snapshot Backup File    : " . ($result['backup_file'] ?? '-'), 'yellow');
            CLI::write("  • Soft-deleted Redundant  : " . ($result['soft_deleted_count'] ?? 0) . " unit", 'light_gray');
            CLI::write("  • Total Aset Kalisogo Akhir: " . ($result['actual_active_after'] ?? 0) . " unit", 'green');
            CLI::write("  • Verifikasi Bersih       : " . (($result['verified_clean'] ?? false) ? 'BERHASIL (MATCH)' : 'PERIKSA SELISIH'), 'green');
        } else {
            CLI::write("\nProyeksi Setelah Eksekusi:", 'yellow');
            CLI::write("  • Proyeksi Aset Kalisogo  : " . ($result['projected_active_after'] ?? 0) . " unit", 'green');
            CLI::write("  • Untuk mengeksekusi secara aman: php spark asset:reconcile-kalisogo --execute", 'yellow');
        }

        CLI::write("\n==================================================================", 'cyan');
    }
}
