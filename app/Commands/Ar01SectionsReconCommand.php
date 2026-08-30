<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FieldSectionResolutionService;

/**
 * Phase AR-01 Phase 5G: Read-Only Reconnaissance Command for Feeder Sections & Physical Sequence
 * Usage: php spark ar01:sections [FEEDER_ID] [--feeder=CODE_OR_ID] [--limit=50]
 */
class Ar01SectionsReconCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:sections';
    protected $description = 'AR-01 Phase 5G: Read-Only Reconnaissance of Feeder Sections, Physical Sequence & Unresolved Assets';

    protected $arguments = [
        'feeder' => 'Feeder ID or Code (e.g. 1, 15, PYL-001, PYL-015)',
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

        if (!$feederArg) {
            CLI::write("\n==================================================================", 'yellow');
            CLI::write("   AR-01 PHASE 5G: FEEDER TOPOLOGY RECONNAISSANCE OVERVIEW       ", 'yellow');
            CLI::write("==================================================================\n", 'yellow');

            // Scan all registered feeders
            $builder = $db->table('penyulang');
            if ($db->fieldExists('is_active', 'penyulang')) {
                $builder->where('is_active', 1);
            }
            $feeders = $builder->get()->getResultArray();

            if (empty($feeders)) {
                CLI::error("Tidak ada penyulang aktif yang ditemukan.");
                return 1;
            }

            CLI::write(sprintf("%-8s | %-15s | %-30s | %-12s | %-12s", "PK ID", "Kode", "Nama Penyulang", "Total Aset", "Kelengkapan"));
            CLI::write(str_repeat("-", 88));

            foreach ($feeders as $f) {
                $sum = $sectionService->getFeederSectionResolutionSummary((int)$f['id']);
                if (!$sum['success']) continue;

                $ratioColor = $sum['completeness_ratio'] >= 80 ? 'green' : ($sum['completeness_ratio'] > 0 ? 'yellow' : 'red');
                CLI::write(sprintf(
                    "#%-7d | %-15s | %-30s | %-12d | %s",
                    $f['id'],
                    $f['kode_penyulang'] ?? 'N/A',
                    $f['nama_penyulang'] ?? 'N/A',
                    $sum['total_assets'],
                    CLI::color("{$sum['completeness_ratio']}%", $ratioColor)
                ));
            }

            CLI::write(str_repeat("-", 88));
            CLI::write("Tip: Jalankan dengan feeder ID/Kode untuk melihat detail seksi & urutan fisik tiang:", 'cyan');
            CLI::write("     php spark ar01:sections 15", 'yellow');
            CLI::write("     php spark ar01:sections PYL-001\n", 'yellow');
            return 0;
        }

        // Locate specific feeder by ID or Code
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

        CLI::write("\n==================================================================", 'cyan');
        CLI::write(sprintf("  TOPOLOGY RECONNAISSANCE: [%s] %s (ID: #%d)", $feeder['kode_penyulang'], $feeder['nama_penyulang'], $feederId), 'cyan');
        CLI::write("==================================================================\n", 'cyan');

        CLI::write("1. INVENTARIS TOPOLOGI (Strictly Read-Only)");
        CLI::write("------------------------------------------------------------------");
        CLI::write("  • Total Master Assets        : {$summary['total_assets']} assets");
        CLI::write("  • Field-Verified Assets      : " . CLI::color((string)$summary['verified_assets'], 'green') . " assets");
        CLI::write("  • Unresolved Section Assets  : " . CLI::color((string)$summary['unresolved_assets'], $summary['unresolved_assets'] > 0 ? 'yellow' : 'green') . " assets");
        CLI::write("  • Section Completeness Ratio : " . CLI::color("{$summary['completeness_ratio']}%", $summary['completeness_ratio'] >= 80 ? 'green' : ($summary['completeness_ratio'] > 0 ? 'yellow' : 'red')));

        CLI::write("\n2. SEKSI CR-06F & URUTAN FISIK ASET (Physical Sequence GI -> Ujung)");
        CLI::write("------------------------------------------------------------------");

        if (empty($summary['configured_sections'])) {
            CLI::write("  Belum ada seksi CR-06F yang dikonfigurasi untuk penyulang ini.", 'yellow');
        } else {
            foreach ($summary['configured_sections'] as $sec) {
                $secName = $sec['nama_seksi'] ?? $sec['nama_section'] ?? ('Seksi #' . $sec['id']);
                $secSeq  = $sec['sequence_order'] ?? $sec['urutan'] ?? $sec['id'];
                
                // Get verified assets in this section
                $secAssetsBuilder = $db->table('assets')
                    ->where('penyulang_id', $feederId)
                    ->where('section_id', $sec['id']);
                if ($db->fieldExists('deleted_at', 'assets')) {
                    $secAssetsBuilder->where('deleted_at IS NULL');
                }
                $seqCol = $db->fieldExists('field_sequence', 'assets') ? 'field_sequence' : 'id';
                $secAssets = $secAssetsBuilder->orderBy($seqCol, 'ASC')->limit($limit)->get()->getResultArray();

                CLI::write(sprintf("\n  ⚡ Section #%d (Urutan Jaringan #%d): %s [%d assets linked]", $sec['id'], $secSeq, $secName, count($secAssets)), 'green');

                if (empty($secAssets)) {
                    CLI::write("     (Belum ada aset yang ditetapkan ke seksi ini)", 'yellow');
                } else {
                    foreach ($secAssets as $sa) {
                        $pSeq = $sa['field_sequence'] ?? $sa['sequence_no'] ?? '-';
                        CLI::write(sprintf("     └─ [Seq: %-3s] ID: #%-6d | Kode: %-20s | %s", $pSeq, $sa['id'], $sa['kode_asset'] ?? $sa['kode_aset'], $sa['nama_asset'] ?? $sa['nama_aset']));
                    }
                }
            }
        }

        // Unresolved Assets
        if ($summary['unresolved_assets'] > 0) {
            CLI::write("\n3. DAFTAR ASET UNRESOLVED (Belum Terpetakan ke Seksi)");
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
                CLI::write(sprintf("  • PK ID: #%-6d | Kode: %-22s | Nama: %s", $ua['id'], $ua['kode_asset'] ?? $ua['kode_aset'] ?? 'N/A', $ua['nama_asset'] ?? $ua['nama_aset'] ?? 'N/A'), 'yellow');
            }

            CLI::write("\n  Untuk memetakan aset ke seksi yang valid:", 'cyan');
            CLI::write('  php spark ar01:verify-section --asset=<PK_ID> --section=<SECTION_ID> --sequence=<NO> --user=<NIP> --reason="<ALASAN>"', 'cyan');
            CLI::write('  php spark ar01:verify-section --code=<KODE_ASSET> --section=<SECTION_ID> --sequence=<NO> --user=<NIP> --reason="<ALASAN>"', 'cyan');
        }

        CLI::write("\n==================================================================", 'cyan');
        CLI::write("🟢 READ-ONLY RECONNAISSANCE COMPLETE: Zero Mutation Applied", 'green');
        CLI::write("==================================================================\n", 'cyan');

        return 0;
    }
}
