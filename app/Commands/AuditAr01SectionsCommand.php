<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FieldSectionResolutionService;

/**
 * Phase AR-01 Phase 5G: Enterprise Audit Command for Field Section Resolution Matrix
 * Usage: php spark audit:ar01-sections [FEEDER-ID] [--detail]
 */
class AuditAr01SectionsCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:ar01-sections';
    protected $description = 'AR-01 Phase 5G: Audit Feeder Section Verification Completeness & Topology Lineage (Strictly Read-Only)';

    protected $arguments = [
        'feeder' => 'The Feeder ID to audit (optional, default: scans all registered feeders)',
    ];

    protected $options = [
        'detail' => 'Display sample asset IDs and codes in each category',
    ];

    public function run(array $params)
    {
        $feederId = !empty($params[0]) && is_numeric($params[0]) ? (int)$params[0] : null;
        $isDetail = in_array('--detail', $params, true) || CLI::getOption('detail') !== null;

        $db = \Config\Database::connect();
        $sectionService = new FieldSectionResolutionService($db);

        $feeders = [];
        if ($feederId) {
            $f = $db->table('penyulang')->where('id', $feederId)->get();
            if ($f && ($row = $f->getRowArray())) {
                $feeders[] = $row;
            }
        } else {
            $builder = $db->table('penyulang');
            if ($db->fieldExists('is_active', 'penyulang')) {
                $builder->where('is_active', 1);
            }
            $res = $builder->get();
            if ($res) {
                $feeders = $res->getResultArray();
            }
        }

        if (empty($feeders)) {
            CLI::error("ERROR: Data penyulang tidak ditemukan di database.");
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
                    if ($isDetail && !empty($sd['sample_assets'])) {
                        foreach ($sd['sample_assets'] as $sa) {
                            CLI::write("         └─ {$sa}", 'green');
                        }
                    }
                }
            } else {
                CLI::write("  Section Breakdown            : " . CLI::color("Belum ada konfigurasi seksi CR-06F pada penyulang ini", 'yellow'));
            }

            if ($summary['unresolved_assets'] > 0 && !empty($summary['unresolved_samples'])) {
                CLI::write("  Sample Unresolved Assets (PK ID & Code):", 'yellow');
                foreach ($summary['unresolved_samples'] as $us) {
                    CLI::write("    • {$us}", 'yellow');
                }
            }

            CLI::write("");
        }

        CLI::write("==================================================================", 'yellow');
        CLI::write("🟢 PHASE 5G AUDIT COMPLETE: Human-in-the-Loop Section Mapping Active", 'green');
        CLI::write("   Gunakan: php spark ar01:verify-section --list --feeder=<ID> untuk melihat ID aset nyata.", 'cyan');
        CLI::write("==================================================================\n", 'yellow');

        return 0;
    }
}
