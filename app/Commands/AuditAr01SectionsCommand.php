<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FieldSectionResolutionService;

/**
 * Phase AR-01 Phase 5G: Enterprise Audit Command for Field Section Resolution Matrix
 * Usage: php spark audit:ar01-sections [FEEDER-ID]
 */
class AuditAr01SectionsCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:ar01-sections';
    protected $description = 'AR-01 Phase 5G: Audit Feeder Section Verification Completeness & Topology Lineage (Strictly Read-Only)';

    protected $arguments = [
        'feeder' => 'The Feeder ID to audit (optional, default: scans all active feeders)',
    ];

    public function run(array $params)
    {
        $feederId = !empty($params[0]) ? (int)$params[0] : null;

        $db = \Config\Database::connect();
        $sectionService = new FieldSectionResolutionService($db);

        $feeders = [];
        if ($feederId) {
            $f = $db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
            if ($f) $feeders[] = $f;
        } else {
            $feeders = $db->table('penyulang')->where('is_active', 1)->get()->getResultArray();
        }

        if (empty($feeders)) {
            CLI::error("ERROR: Penyulang tidak ditemukan.");
            return 1;
        }

        CLI::write("\n==================================================================", 'yellow');
        CLI::write("    AR-01 PHASE 5G: FIELD SECTION RESOLUTION & TOPOLOGY AUDIT    ", 'yellow');
        CLI::write("==================================================================\n", 'yellow');

        foreach ($feeders as $f) {
            $summary = $sectionService->getFeederSectionResolutionSummary((int)$f['id']);
            if (!$summary['success']) continue;

            CLI::write(sprintf("⚡ [%s] %s (ID: #%d)", $summary['kode_penyulang'], $summary['nama_penyulang'], $summary['feeder_id']), 'cyan');
            CLI::write("------------------------------------------------------------------");
            CLI::write("  • Total Grid Master Assets   : {$summary['total_assets']} assets");
            CLI::write("  • Field-Verified Section     : " . CLI::color((string)$summary['verified_assets'], 'green') . " assets");
            CLI::write("  • Unresolved Section         : " . CLI::color((string)$summary['unresolved_assets'], $summary['unresolved_assets'] > 0 ? 'yellow' : 'green') . " assets");
            CLI::write("  • Section Completeness Ratio : " . CLI::color("{$summary['completeness_ratio']}%", $summary['completeness_ratio'] >= 80 ? 'green' : ($summary['completeness_ratio'] > 0 ? 'yellow' : 'red')));

            if (!empty($summary['section_distribution'])) {
                CLI::write("  Section Breakdown (CR-06F Truth):");
                foreach ($summary['section_distribution'] as $sd) {
                    CLI::write(sprintf("    [%d] %-35s : %d assets linked", $sd['sequence_order'], $sd['nama_seksi'], $sd['asset_count']));
                }
            } else {
                CLI::write("  Section Breakdown            : " . CLI::color("Belum ada konfigurasi seksi CR-06F", 'yellow'));
            }
            CLI::write("");
        }

        CLI::write("==================================================================", 'yellow');
        CLI::write("🟢 PHASE 5G AUDIT COMPLETE: Human-in-the-Loop Section Mapping Active", 'green');
        CLI::write("==================================================================\n", 'yellow');

        return 0;
    }
}
