<?php

namespace App\Commands;

use App\Services\GlobalAssetDeduplicationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * GlobalAssetDeduplicationCommand
 *
 * Usage:
 *   php spark asset:dedup-global [--dry-run]
 *   php spark asset:dedup-global --execute [--feeder=KALISOGO]
 *   php spark asset:dedup-global --rollback=dedup_global_backup_20261021_120000.json
 */
class GlobalAssetDeduplicationCommand extends BaseCommand
{
    protected $group       = 'Asset';
    protected $name        = 'asset:dedup-global';
    protected $description = 'Audits and deduplicates master assets globally across all ULPs/feeders using composite 7-column key with backup & rollback.';
    protected $usage       = 'asset:dedup-global [--dry-run] [--execute] [--rollback=<backup_file>] [--feeder=<name>]';
    protected $options     = [
        '--dry-run'  => 'Run audit and projection simulation without DB mutations (default)',
        '--execute'  => 'Perform transactional deduplication with pre-backup and FK rerouting',
        '--rollback' => 'Rollback a previous deduplication using its JSON backup file',
        '--feeder'   => 'Filter evaluation to a specific feeder name',
    ];

    public function run(array $params)
    {
        $service = new GlobalAssetDeduplicationService();

        $rollbackFile = CLI::getOption('rollback');
        if (!empty($rollbackFile)) {
            CLI::write("==================================================================", 'yellow');
            CLI::write("   SIDAK TEJO — ROLLBACK DEDUPLIKASI ASET GLOBAL                  ", 'yellow');
            CLI::write("==================================================================", 'yellow');
            CLI::write("Memulihkan dari berkas backup: " . $rollbackFile, 'cyan');

            $res = $service->rollback((string)$rollbackFile);
            if (($res['status'] ?? '') === 'success') {
                CLI::write("[SUCCESS] " . $res['message'], 'green');
                CLI::write("Total record dipulihkan: " . ($res['restored_count'] ?? 0), 'white');
            } else {
                CLI::error("[ERROR] " . ($res['message'] ?? 'Rollback gagal'));
            }
            return;
        }

        $isExecute    = CLI::getOption('execute') !== null;
        $feederFilter = CLI::getOption('feeder');

        CLI::write("==================================================================", 'yellow');
        CLI::write("   SIDAK TEJO — AUDIT & DEDUPLIKASI ASET GLOBAL (7-KUNCI)         ", 'yellow');
        CLI::write("==================================================================", 'yellow');
        CLI::write("Kunci Komposit: Unit + ULP + Penyulang + Nama Aset + Section + Latitude + Longitude", 'white');
        CLI::write("Mode: " . ($isExecute ? "LIVE EXECUTION (TRANSAKSI DENGAN BACKUP & REROUTE FK)" : "DRY-RUN (SIMULASI READ-ONLY)"), $isExecute ? 'red' : 'green');
        if (!empty($feederFilter)) {
            CLI::write("Filter Penyulang: " . $feederFilter, 'cyan');
        }
        CLI::newLine();

        $result = $service->executeDeduplication($isExecute, $feederFilter ? (string)$feederFilter : null);

        if (($result['status'] ?? '') === 'error') {
            CLI::error("[ERROR] " . ($result['message'] ?? 'Terjadi kesalahan sistem'));
            return;
        }

        CLI::write("Ringkasan Hasil:", 'white');
        CLI::write("  • Total Aset Aktif Dievaluasi : " . number_format($result['total_active_before'] ?? 0) . " unit", 'cyan');
        CLI::write("  • Kelompok Duplikat Ditemukan : " . number_format($result['duplicate_groups_found'] ?? 0) . " kelompok", ($result['duplicate_groups_found'] ?? 0) > 0 ? 'yellow' : 'green');
        CLI::write("  • Record Duplikat Redundan    : " . number_format($result['duplicate_records_to_soft_delete'] ?? 0) . " unit", 'light_gray');
        CLI::write("  • Relasi Temuan Dialihkan    : " . number_format($result['dependencies_to_reroute']['temuan_count'] ?? 0) . " baris", 'magenta');
        CLI::write("  • Relasi Transline Dialihkan  : " . number_format(($result['dependencies_to_reroute']['translines_source_count'] ?? 0) + ($result['dependencies_to_reroute']['translines_target_count'] ?? 0)) . " segmen", 'magenta');

        if (!empty($result['backup_file'])) {
            CLI::write("  • Berkas Snapshot Backup     : " . $result['backup_file'], 'light_cyan');
        }
        CLI::write("  • Status Terverifikasi Bersih : " . (($result['verified_clean'] ?? false) ? 'YA (BERSIH)' : 'TIDAK'), ($result['verified_clean'] ?? false) ? 'green' : 'red');
        CLI::newLine();

        if (!empty($result['feeder_breakdown'])) {
            CLI::write("Rincian per Penyulang:", 'white');
            CLI::write(sprintf("  %-25s | %-16s | %10s | %10s | %10s", "NAMA PENYULANG", "ULP", "AKTIF", "KEL. DUP", "REDUNDAN"), 'yellow');
            CLI::write(str_repeat("-", 82), 'yellow');
            foreach ($result['feeder_breakdown'] as $fName => $fStats) {
                $color = ($fStats['duplicate_groups'] > 0) ? 'red' : 'green';
                CLI::write(sprintf("  %-25s | %-16s | %10s | %10s | %10s",
                    substr($fName, 0, 25),
                    substr($fStats['ulp'], 0, 16),
                    number_format($fStats['total_active']),
                    number_format($fStats['duplicate_groups']),
                    number_format($fStats['duplicates_count'])
                ), $color);
            }
            CLI::write(str_repeat("-", 82), 'yellow');
        }

        CLI::newLine();
        CLI::write("Eksekusi selesai. Integritas data terjamin.", 'green');
    }
}
