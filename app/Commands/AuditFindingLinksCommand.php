<?php

namespace App\Commands;

use App\Services\PlnStandardBomRuleService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * AuditFindingLinksCommand
 *
 * Audits unlinked legacy findings and deterministically links candidates
 * while preventing guesses on ambiguous candidate poles.
 *
 * Usage:
 *   php spark finding:audit-links [--radius=50] [--execute]
 */
class AuditFindingLinksCommand extends BaseCommand
{
    protected $group       = 'Finding';
    protected $name        = 'finding:audit-links';
    protected $description = 'Audits unlinked findings and deterministically links single-candidate poles (strict no-guess).';
    protected $usage       = 'finding:audit-links [--radius=<meters>] [--execute]';
    protected $options     = [
        '--radius'  => 'Search radius in meters (default: 50.0)',
        '--execute' => 'Execute deterministic link persistence (default: dry-run)',
    ];

    public function run(array $params)
    {
        $radius    = (float)(CLI::getOption('radius') ?: 50.0);
        $isExecute = CLI::getOption('execute') !== null;

        CLI::write("==================================================================", 'yellow');
        CLI::write("   SIDAK TEJO — AUDIT & REKONSILIASI PENAUTAN TEMUAN LAMA          ", 'yellow');
        CLI::write("==================================================================", 'yellow');
        CLI::write("Mode   : " . ($isExecute ? "LIVE EXECUTION (PERMANEN LINK TRANSAKSIONAL)" : "DRY-RUN (READ-ONLY AUDIT)"), $isExecute ? 'red' : 'green');
        CLI::write("Radius : " . $radius . " meter", 'cyan');
        CLI::newLine();

        $service = new PlnStandardBomRuleService();
        $audit = $service->auditAndReconcileLegacyFindings($isExecute, $radius);

        CLI::write("Hasil Audit Keseluruhan Temuan:", 'white');
        CLI::write("  • Total Temuan Aktif           : " . number_format($audit['total_findings']) . " temuan", 'white');
        CLI::write("  • Sudah Terikat Aset           : " . number_format($audit['already_linked']) . " temuan", 'green');
        CLI::write("  • Belum Terikat Aset (Target)  : " . number_format($audit['unlinked_count']) . " temuan", 'yellow');
        CLI::write("    - Tanpa Koordinat (Lat/Lng 0): " . number_format($audit['no_coordinates']) . " temuan", 'yellow');
        CLI::write("    - Tiada Tiang di Radius " . $radius . "m: " . number_format($audit['no_candidate_radius']) . " temuan", 'yellow');
        CLI::write("    - Ambigu (>1 Kandidat, Ragu) : " . number_format($audit['ambiguous_count']) . " temuan", 'red');
        CLI::write("    - Deterministik (Tepat 1 Aset): " . number_format($audit['deterministic_count']) . " temuan", 'green');
        if ($isExecute) {
            CLI::write("  • Berhasil Ditautkan Permanen  : " . number_format($audit['auto_linked_count']) . " temuan", 'green');
        }
        CLI::newLine();

        CLI::write("Breakdown Berdasarkan Pelaksana:", 'white');
        foreach ($audit['pelaksana_breakdown'] as $pel => $stats) {
            CLI::write(sprintf("  [%-14s] Total: %3d | Linked: %3d | Unlinked: %3d (Det: %d, Ambigu: %d, NoCoord: %d, NoCand: %d)",
                $pel, $stats['total'], $stats['linked'], $stats['unlinked'],
                $stats['deterministic'] ?? 0, $stats['ambiguous'], $stats['no_coord'], $stats['no_candidate']
            ), 'cyan');
        }
        CLI::newLine();

        if (!empty($audit['deterministic_samples'])) {
            CLI::write("Sampel Temuan Deterministik (Siap / Berhasil Ditautkan):", 'white');
            foreach ($audit['deterministic_samples'] as $d) {
                CLI::write(sprintf("  • #%d [%s] -> Aset #%d (%s - %s) | Jarak: %.1fm | Alasan: %s",
                    $d['temuan_id'], $d['nomor_temuan'], $d['asset_id'], $d['kode_asset'], $d['nama_asset'], $d['distance_m'], $d['match_reason']
                ), 'green');
            }
            CLI::newLine();
        }

        if (!empty($audit['ambiguous_samples'])) {
            CLI::write("Sampel Temuan Ambigu (TIDAK DITEBAK, Menunggu Pilihan Pengguna):", 'white');
            foreach ($audit['ambiguous_samples'] as $a) {
                CLI::write(sprintf("  • #%d [%s]: %s | Alasan: %s",
                    $a['temuan_id'], $a['nomor_temuan'], substr($a['detail_temuan'], 0, 60), $a['reason']
                ), 'yellow');
                foreach ($a['candidates'] as $c) {
                    CLI::write(sprintf("      - Kandidat #%d (%s - %s) Jarak: %.1fm", $c['id'], $c['kode_asset'], $c['nama_asset'], $c['distance_m']), 'white');
                }
            }
            CLI::newLine();
        }

        CLI::write("Verifikasi Kasus Sampel Spesifik:", 'white');
        foreach ($audit['samples_verified'] as $sid => $info) {
            if (!$info['found']) {
                CLI::write("  • Temuan #{$sid}: TIDAK DITEMUKAN pada database aktif.", 'yellow');
                continue;
            }
            CLI::write("  • Temuan #{$sid} [{$info['nomor_temuan']} - {$info['pelaksana']}]:", 'white');
            CLI::write("    - Detail    : {$info['detail_temuan']}", 'white');
            CLI::write("    - NOGA      : Lama='{$info['noga_raw']}' -> Bersih=" . var_export($info['noga_clean'], true), 'cyan');
            CLI::write("    - Asset ID  : " . var_export($info['asset_id'], true), 'cyan');
            CLI::write("    - Status BOM: {$info['bom_status']} ({$info['bom_component']})", 'green');
            CLI::write("    - Kandidat  : {$info['candidate_count']} tiang (Deterministik: " . ($info['is_deterministic'] ? 'YA' : 'TIDAK') . ", Ambigu: " . ($info['is_ambiguous'] ? 'YA' : 'TIDAK') . ")", 'yellow');
        }
        CLI::write("==================================================================", 'yellow');
    }
}
