<?php

namespace App\Commands;

use App\Services\AssetReconciliationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * AuditAssetCompositeCommand
 *
 * Usage:
 *   php spark asset:audit-composite7 [--feeder=KALISOGO] [--execute]
 */
class AuditAssetCompositeCommand extends BaseCommand
{
    protected $group       = 'Asset';
    protected $name        = 'asset:audit-composite7';
    protected $description = 'Audits and safely canonicalizes duplicate assets on 7-column composite key.';
    protected $usage       = 'asset:audit-composite7 [--feeder=<name>] [--execute]';
    protected $options     = [
        '--feeder'  => 'Filter by feeder name (optional)',
        '--execute' => 'Execute deduplication in transaction with pre-backup and FK rerouting',
    ];

    public function run(array $params)
    {
        $feederFilter = CLI::getOption('feeder');
        $isExecute    = CLI::getOption('execute') !== null;

        CLI::write("==================================================================", 'yellow');
        CLI::write("   SIDAK TEJO — ASSET 7-COLUMN COMPOSITE DUPLICATE AUDIT          ", 'yellow');
        CLI::write("==================================================================", 'yellow');
        CLI::write("Kunci Evaluasi: Nama Unit + Nama ULP + Nama Penyulang + Nama Aset + Section + Latitude + Longitude", 'white');
        CLI::write("Mode: " . ($isExecute ? "LIVE EXECUTION (WITH PRE-BACKUP & FK REROUTING)" : "DRY-RUN (READ-ONLY AUDIT)"), $isExecute ? 'red' : 'green');
        if (!empty($feederFilter)) {
            CLI::write("Filter Penyulang: " . $feederFilter, 'cyan');
        }
        CLI::newLine();

        $service = new AssetReconciliationService();
        $db = \Config\Database::connect();

        $overallStats = $db->query("
            SELECT 
                COUNT(*) AS total_assets,
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS active_assets,
                SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS soft_deleted_assets
            FROM assets
        ")->getRowArray();

        $delMethods = $db->query("
            SELECT COALESCE(NULLIF(section_resolution_method, ''), 'MANUAL_OR_LEGACY') AS reason, COUNT(*) as cnt
            FROM assets
            WHERE deleted_at IS NOT NULL
            GROUP BY reason
        ")->getResultArray();

        CLI::write("Status Keseluruhan Tabel Assets:", 'white');
        CLI::write(sprintf("  • Total Seluruh Record Aset : %s unit", number_format($overallStats['total_assets'])), 'cyan');
        CLI::write(sprintf("  • Aset Aktif Operasional    : %s unit", number_format($overallStats['active_assets'])), 'green');
        CLI::write(sprintf("  • Aset Soft-Deleted (Arsip) : %s unit", number_format($overallStats['soft_deleted_assets'])), 'yellow');
        if (!empty($delMethods)) {
            CLI::write("  • Komposisi Alasan Soft-Delete Sebelumnya:", 'light_gray');
            foreach ($delMethods as $dm) {
                CLI::write(sprintf("      - %-35s : %s unit", $dm['reason'], number_format($dm['cnt'])), 'light_gray');
            }
        }
        CLI::newLine();

        $result = $service->reconcileComposite7KeyDuplicates($isExecute, $feederFilter);

        if (($result['status'] ?? '') === 'error') {
            CLI::error("[ERROR] " . ($result['message'] ?? 'Unknown error'));
            return;
        }

        CLI::write("Hasil Audit Singkat:", 'white');
        CLI::write("  • Total Aset Aktif Dievaluasi : " . number_format($result['total_active_before'] ?? 0) . " unit", 'cyan');
        CLI::write("  • Jumlah Kelompok Duplikat   : " . number_format($result['duplicate_groups_found'] ?? 0) . " kelompok", ($result['duplicate_groups_found'] ?? 0) > 0 ? 'yellow' : 'green');
        CLI::write("  • Record Duplikat Redundan    : " . number_format($result['duplicate_records_to_soft_delete'] ?? 0) . " unit", 'light_gray');
        CLI::write("  • Relasi Temuan Dialihkan    : " . number_format($result['dependencies_to_reroute']['temuan_count'] ?? 0) . " baris", 'magenta');
        CLI::write("  • Relasi Transline Dialihkan  : " . number_format(($result['dependencies_to_reroute']['translines_source_count'] ?? 0) + ($result['dependencies_to_reroute']['translines_target_count'] ?? 0)) . " segmen", 'magenta');
        CLI::newLine();

        if (!empty($result['feeder_breakdown'])) {
            CLI::write("Rincian Audit per Penyulang (Seluruh ULP):", 'white');
            CLI::write(sprintf("  %-25s | %-16s | %10s | %10s | %10s", "NAMA PENYULANG", "ULP", "AKTIF AWAL", "KEL. DUP", "REDUNDAN"), 'yellow');
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
            CLI::newLine();
        }

        if (!empty($result['duplicate_groups_detail'])) {
            CLI::newLine();
            CLI::write("Daftar Kelompok Duplikat Terdeteksi:", 'yellow');
            foreach ($result['duplicate_groups_detail'] as $idx => $g) {
                CLI::write(sprintf("  [%d] Kunci: %s", $idx + 1, $g['composite_key']), 'cyan');
                CLI::write(sprintf("      - Record Kanonis Dipertahankan: ID %d (%s - %s)",
                    $g['canonical_record']['id'],
                    $g['canonical_record']['kode_asset'],
                    $g['canonical_record']['nama_asset']
                ), 'green');
                CLI::write("      - Record Duplikat Terdeteksi:", 'yellow');
                foreach ($g['duplicate_records'] as $dup) {
                    CLI::write(sprintf("        * ID %d (%s - %s) | Temuan: %d, Transline: %d",
                        $dup['id'],
                        $dup['kode_asset'],
                        $dup['nama_asset'],
                        $dup['dependencies']['temuan_count'],
                        $dup['dependencies']['total_dependencies'] - $dup['dependencies']['temuan_count']
                    ), 'light_gray');
                }
            }
        }

        if ($isExecute) {
            CLI::newLine();
            CLI::write("Hasil Eksekusi Mutasi Terverifikasi:", 'green');
            CLI::write("  • Backup Snapshot JSON   : " . ($result['backup_file'] ?? '-'), 'yellow');
            CLI::write("  • Aset Di-soft-delete     : " . number_format($result['soft_deleted_count'] ?? 0) . " unit", 'light_gray');
            CLI::write("  • Total Aset Aktif Akhir : " . number_format($result['actual_active_after'] ?? 0) . " unit", 'green');
            CLI::write("  • Status Verifikasi      : " . (($result['verified_clean'] ?? false) ? 'BERHASIL BERSIH (0 DUPLIKAT)' : 'PERIKSA ANOMALI'), 'green');
        } else {
            CLI::newLine();
            CLI::write("Proyeksi Pasca Eksekusi:", 'yellow');
            CLI::write("  • Proyeksi Aset Aktif    : " . number_format($result['projected_active_after'] ?? 0) . " unit", 'green');
            if (($result['duplicate_records_to_soft_delete'] ?? 0) > 0) {
                CLI::write("  • Jalankan dengan flag --execute untuk mengeksekusi dengan aman.", 'yellow');
            }
        }
        CLI::write("==================================================================", 'yellow');
    }
}
