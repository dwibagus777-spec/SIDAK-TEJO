<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FieldSectionResolutionService;

/**
 * Phase AR-01 Phase 5G: Enterprise Read-Only Reconnaissance Command for Feeder Topology Truth
 * Usage: php spark ar01:sections [FEEDER_ID_OR_CODE] [--limit=50]
 */
class Ar01SectionsReconCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:sections';
    protected $description = 'AR-01 Phase 5G: Enterprise Read-Only Reconnaissance of Feeder Sections, Physical Sequence & Unresolved Assets';

    protected $arguments = [
        'feeder' => 'Feeder ID or Code (e.g. 1, 4, 15, 19, PYL-001, PYL-004)',
    ];

    protected $options = [
        'feeder' => 'Feeder ID or Code (alternative option)',
        'limit'  => 'Maximum assets to display per list (default: 50)',
    ];

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $sectionService = new FieldSectionResolutionService($db);

        $feederArg = $params[0] ?? CLI::getOption('feeder') ?? null;
        $limit = (int)(CLI::getOption('limit') ?? 50);

        // Mode 1: Summary of all Feeders
        if (!$feederArg) {
            CLI::write("\n==================================================================", 'yellow');
            CLI::write("   AR-01 PHASE 5G: FEEDER TOPOLOGY RECONNAISSANCE OVERVIEW       ", 'yellow');
            CLI::write("==================================================================\n", 'yellow');

            $builder = $db->table('penyulang');
            if ($db->fieldExists('is_active', 'penyulang')) {
                $builder->where('is_active', 1);
            }
            $feeders = $builder->get()->getResultArray();

            if (empty($feeders)) {
                CLI::error("Tidak ada penyulang aktif yang ditemukan di database.");
                return 1;
            }

            CLI::write(sprintf("%-8s | %-12s | %-28s | %-10s | %-10s | %-12s", "PK ID", "Kode", "Nama Penyulang", "Sections", "Assets", "Completeness"));
            CLI::write(str_repeat("-", 90));

            foreach ($feeders as $f) {
                $sum = $sectionService->getFeederSectionResolutionSummary((int)$f['id']);
                if (!$sum['success']) continue;

                $secCount = count($sum['configured_sections']);
                $ratioColor = $sum['completeness_ratio'] >= 80 ? 'green' : ($sum['completeness_ratio'] > 0 ? 'yellow' : ($sum['total_assets'] > 0 ? 'red' : 'white'));
                
                CLI::write(sprintf(
                    "#%-7d | %-12s | %-28s | %-10d | %-10d | %s",
                    $f['id'],
                    $f['kode_penyulang'] ?? 'N/A',
                    $f['nama_penyulang'] ?? 'N/A',
                    $secCount,
                    $sum['total_assets'],
                    CLI::color("{$sum['completeness_ratio']}%", $ratioColor)
                ));
            }

            CLI::write(str_repeat("-", 90));
            CLI::write("Tip: Jalankan dengan feeder ID/Kode untuk audit topologi detail:", 'cyan');
            CLI::write("     php spark ar01:sections 4    (GEMURUNG)", 'yellow');
            CLI::write("     php spark ar01:sections 19   (GADING KIRANA)", 'yellow');
            CLI::write("     php spark ar01:sections 15   (ECCO)\n", 'yellow');
            return 0;
        }

        // Mode 2: Detailed Feeder Topology Reconnaissance
        $builder = $db->table('penyulang');
        if (is_numeric($feederArg)) {
            $builder->where('id', (int)$feederArg);
        } else {
            $builder->where('kode_penyulang', (string)$feederArg);
        }
        $feeder = $builder->get()->getRowArray();

        if (!$feeder) {
            CLI::error("ERROR: Penyulang '{$feederArg}' tidak ditemukan di database.");
            return 1;
        }

        $feederId = (int)$feeder['id'];
        $summary = $sectionService->getFeederSectionResolutionSummary($feederId);

        // Calculate GPS and Assigned counts
        $assetsBuilder = $db->table('assets')->where('penyulang_id', $feederId);
        if ($db->fieldExists('deleted_at', 'assets')) {
            $assetsBuilder->where('deleted_at IS NULL');
        }
        $allAssets = $assetsBuilder->get()->getResultArray();

        $gpsCount = 0;
        $assignedCount = 0;
        foreach ($allAssets as $a) {
            if (!empty($a['latitude']) && !empty($a['longitude'])) {
                $gpsCount++;
            }
            if (!empty($a['section_id']) && (int)$a['section_id'] > 0) {
                $assignedCount++;
            }
        }

        CLI::write("\n==================================================================", 'cyan');
        CLI::write(sprintf("  TOPOLOGY RECONNAISSANCE: [%s] %s (ID: #%d)", $feeder['kode_penyulang'], $feeder['nama_penyulang'], $feederId), 'cyan');
        CLI::write("==================================================================\n", 'cyan');

        // 1. INVENTARIS TOPOLOGI
        CLI::write("1. INVENTARIS TOPOLOGI (Strictly Read-Only)", 'yellow');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  • Total Master Assets         : {$summary['total_assets']} assets");
        CLI::write("  • Assigned Section Assets     : {$assignedCount} assets");
        CLI::write("  • Field-Verified Assets       : " . CLI::color((string)$summary['verified_assets'], 'green') . " assets");
        CLI::write("  • Unresolved Section Assets   : " . CLI::color((string)$summary['unresolved_assets'], $summary['unresolved_assets'] > 0 ? 'yellow' : 'green') . " assets");
        CLI::write("  • Section Completeness Ratio  : " . CLI::color("{$summary['completeness_ratio']}%", $summary['completeness_ratio'] >= 80 ? 'green' : ($summary['completeness_ratio'] > 0 ? 'yellow' : 'red')));

        // 2. SEKSI CR-06F INVENTORY
        CLI::write("\n2. SEKSI CR-06F INVENTORY & PHYSICAL SEQUENCE", 'yellow');
        CLI::write("------------------------------------------------------------------");

        if (empty($summary['configured_sections'])) {
            CLI::write("  Belum ada seksi CR-06F yang terdaftar untuk penyulang ini.", 'yellow');
        } else {
            foreach ($summary['configured_sections'] as $sec) {
                $secName = $sec['nama_section'] ?? $sec['nama_seksi'] ?? ('Seksi #' . $sec['id']);
                $secSeq  = $sec['sequence_order'] ?? $sec['urutan'] ?? $sec['id'];
                
                $secAssetsBuilder = $db->table('assets')
                    ->where('penyulang_id', $feederId)
                    ->where('section_id', $sec['id']);
                if ($db->fieldExists('deleted_at', 'assets')) {
                    $secAssetsBuilder->where('deleted_at IS NULL');
                }
                $seqCol = $db->fieldExists('field_sequence', 'assets') ? 'field_sequence' : 'id';
                $secAssets = $secAssetsBuilder->orderBy($seqCol, 'ASC')->limit($limit)->get()->getResultArray();

                CLI::write(sprintf("  ⚡ Section #%-3d (Urutan: #%d) : %-35s [Assigned: %d]", $sec['id'], $secSeq, $secName, count($secAssets)), 'green');

                if (empty($secAssets)) {
                    CLI::write("     └─ 0 assets assigned (Pending Field Verification)", 'yellow');
                } else {
                    foreach ($secAssets as $sa) {
                        $pSeq = $sa['field_sequence'] ?? $sa['sequence_no'] ?? '-';
                        CLI::write(sprintf("     └─ [Seq: %-3s] ID: #%-6d | Kode: %-20s | %s", $pSeq, $sa['id'], $sa['kode_asset'] ?? $sa['kode_aset'], $sa['nama_asset'] ?? $sa['nama_aset']));
                    }
                }
            }
        }

        // 3. TOPOLOGY & SPATIAL EVIDENCE
        CLI::write("\n3. TOPOLOGY & SPATIAL EVIDENCE", 'yellow');
        CLI::write("------------------------------------------------------------------");
        CLI::write(sprintf("  • Coordinates Available       : %d / %d assets (%s)", $gpsCount, $summary['total_assets'], $summary['total_assets'] > 0 && $gpsCount === $summary['total_assets'] ? '100% COMPLETE' : 'PARTIAL'));
        CLI::write("  • Sequence Assignment         : " . ($summary['verified_assets'] > 0 ? 'PARTIALLY VERIFIED' : 'NONE (UNASSIGNED)'));
        CLI::write("  • Section Assignment Status   : " . ($assignedCount > 0 ? "{$assignedCount} ASSIGNED" : 'NONE (UNRESOLVED)'));
        CLI::write("  • Human Verification         : " . ($summary['verified_assets'] > 0 ? "{$summary['verified_assets']} VERIFIED" : 'NONE (PENDING SURVEY)'));

        // 4. UNRESOLVED ASSETS QUEUE
        if ($summary['unresolved_assets'] > 0) {
            CLI::write("\n4. UNRESOLVED ASSET QUEUE (Menunggu Field Verification)", 'yellow');
            CLI::write("------------------------------------------------------------------");
            $unresBuilder = $db->table('assets')
                ->where('penyulang_id', $feederId)
                ->groupStart()
                    ->where('section_id IS NULL')
                    ->orWhere('section_id', 0)
                ->groupEnd();
            if ($db->fieldExists('deleted_at', 'assets')) {
                $unresBuilder->where('deleted_at IS NULL');
            }
            $unresAssets = $unresBuilder->orderBy('id', 'ASC')->limit($limit)->get()->getResultArray();

            CLI::write(sprintf("  Menampilkan %d dari %d aset unresolved:", count($unresAssets), $summary['unresolved_assets']), 'yellow');
            foreach ($unresAssets as $ua) {
                $coords = (!empty($ua['latitude']) && !empty($ua['longitude'])) ? "GPS: ({$ua['latitude']}, {$ua['longitude']})" : "GPS: NULL";
                CLI::write(sprintf("  • PK ID: #%-6d | Kode: %-22s | %s", $ua['id'], $ua['kode_asset'] ?? $ua['kode_aset'] ?? 'N/A', $coords), 'yellow');
            }
        }

        // 5. GOVERNANCE & SAFETY
        CLI::write("\n5. GOVERNANCE & SAFETY SAFEGUARDS", 'yellow');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  • Mutation Applied            : " . CLI::color("ZERO (Strictly Read-Only)", 'green'));
        CLI::write("  • Master Promotion Allowed    : " . CLI::color("NO (Gate LOCKED)", 'red'));
        CLI::write("  • Field Verification Required : " . CLI::color("YES (Human Engineering Sign-Off)", 'cyan'));

        CLI::write("\n==================================================================", 'cyan');
        CLI::write("🟢 READ-ONLY RECONNAISSANCE COMPLETE: Truthful State Preserved", 'green');
        CLI::write("==================================================================\n", 'cyan');

        return 0;
    }
}
