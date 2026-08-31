<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\SpatialSectionCandidateService;

/**
 * Phase AR-01 Phase 5G: Spatial Section Candidate Recommendation CLI Command
 * Usage: php spark ar01:candidates [FEEDER_ID] [--asset=ID] [--limit=50] [--json]
 */
class Ar01CandidatesCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:candidates';
    protected $description = 'AR-01 Phase 5G: Spatial Section Candidate Recommendations with Topology Evidence (Advisory Only)';

    protected $arguments = [
        'feeder' => 'Feeder ID or Code (e.g. 4, 19, 15, PYL-004)',
    ];

    protected $options = [
        'feeder' => 'Feeder ID or Code (alternative option)',
        'asset'  => 'Analyze single Asset PK ID (e.g. --asset=3711)',
        'limit'  => 'Maximum unresolved assets to analyze (default: 20)',
        'json'   => 'Output raw machine-readable Evidence JSON',
    ];

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $candidateService = new SpatialSectionCandidateService($db);

        $feederArg = null;
        foreach ($params as $p) {
            if (!str_starts_with($p, '-')) {
                $feederArg = $p;
                break;
            }
        }
        if ($feederArg === null) {
            $feederArg = CLI::getOption('feeder');
        }

        $assetArg  = CLI::getOption('asset') ?? null;
        $limit     = (int)(CLI::getOption('limit') ?? 20);
        $isJson    = (bool)(CLI::getOption('json') ?? false);

        // Resolve Feeder if specified
        $feeder = null;
        $feederId = null;
        if ($feederArg !== null) {
            $feederBuilder = $db->table('penyulang');
            if (is_numeric($feederArg)) {
                $feederBuilder->where('id', (int)$feederArg);
            } else {
                $feederBuilder->where('kode_penyulang', (string)$feederArg);
            }
            $getFeeder = $feederBuilder->get();
            $feeder = $getFeeder ? $getFeeder->getRowArray() : null;

            if (!$feeder) {
                CLI::error("ERROR: Penyulang '{$feederArg}' tidak ditemukan di database.");
                return 1;
            }
            $feederId = (int)$feeder['id'];
        }

        // Mode 1: Single Asset Analysis
        if ($assetArg !== null) {
            $assetId = (int)$assetArg;
            $result = $candidateService->analyzeAsset($assetId);

            if (!$result['success']) {
                CLI::error("ERROR: " . ($result['error'] ?? 'Gagal menganalisis aset.'));
                return 1;
            }

            // Cross-feeder verification if feeder was explicitly supplied
            if ($feederId !== null && (int)$result['feeder_id'] !== $feederId) {
                CLI::error("ERROR: Aset #{$assetId} bukan merupakan bagian dari penyulang #{$feederId} ([{$feeder['kode_penyulang']}] {$feeder['nama_penyulang']}). Cross-feeder analysis ditolak.");
                return 1;
            }

            if ($isJson) {
                CLI::write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return 0;
            }

            CLI::write("\n==============================================================", 'cyan');
            CLI::write("AR-01 PHASE 5G: SPATIAL SECTION CANDIDATE ENGINE", 'cyan');
            CLI::write("==============================================================", 'cyan');
            CLI::write("MODE        : ADVISORY / READ-ONLY", 'yellow');
            CLI::write("ASSET       : #{$result['asset_id']} [{$result['kode_asset']}]", 'yellow');
            CLI::write("FEEDER      : [{$result['feeder_id']}] {$result['feeder_name']}", 'yellow');
            CLI::write("MUTATION    : ZERO (Strictly Read-Only)", 'green');
            CLI::write("PROMOTION   : DISABLED (Gate LOCKED)\n", 'red');

            $gpsStr = ($result['asset_gps']['latitude'] !== null) ? "({$result['asset_gps']['latitude']}, {$result['asset_gps']['longitude']})" : "NULL (MISSING GPS)";
            $decStatus = $result['decision']['status'] ?? 'UNRESOLVED';
            $decColor = ($decStatus === 'CLEAR') ? 'green' : (($decStatus === 'AMBIGUOUS') ? 'yellow' : 'red');
            CLI::write("GPS Coordinates : {$gpsStr}");
            CLI::write("Decision Status : " . CLI::color($decStatus, $decColor));
            CLI::write("Margin Top1-Top2: {$result['decision']['margin_percent']}%\n");

            CLI::write("TOP SECTION CANDIDATES (Top-3 Ranking):", 'cyan');
            CLI::write(str_repeat("-", 80));
            CLI::write(sprintf("%-5s | %-10s | %-35s | %-8s | %-12s", "Rank", "Section ID", "Nama Seksi", "Skor", "Confidence"));
            CLI::write(str_repeat("-", 80));

            foreach ($result['section_candidates'] as $c) {
                $confColor = $c['confidence'] === 'HIGH' ? 'green' : ($c['confidence'] === 'AMBIGUOUS' ? 'yellow' : 'white');
                CLI::write(sprintf(
                    "#%-4d | #%-9d | %-35s | %-8.1f | %s",
                    $c['rank'],
                    $c['section_id'],
                    mb_strimwidth($c['section_name'], 0, 35, '...'),
                    $c['score'],
                    CLI::color($c['confidence'], $confColor)
                ));
            }
            CLI::write(str_repeat("-", 80));

            CLI::write("\nEVIDENCE BREAKDOWN (Top Candidate):", 'cyan');
            $top = $result['section_candidates'][0] ?? null;
            if ($top) {
                $ev = $top['evidence'];
                $spSemantics = $ev['spatial']['score_semantics'] ?? 'N/A';
                $bdSemantics = $ev['boundary']['score_semantics'] ?? 'N/A';
                $tpSemantics = $ev['continuity']['score_semantics'] ?? 'N/A';

                CLI::write(sprintf("  • Spatial Proximity Score   : %.1f pts [%s] (Dist: %s m, Src: %s)", $ev['spatial']['spatial_score'], $spSemantics, $ev['spatial']['distance_to_boundary'] ?? 'N/A', $ev['spatial']['source'] ?? 'N/A'));
                CLI::write(sprintf("  • Boundary Evidence Score   : %.1f pts [%s] (Status: %s, Landmarks: %d)", $ev['boundary']['boundary_score'], $bdSemantics, $ev['boundary']['boundary_status'], $ev['boundary']['resolved_count'] ?? 0));
                CLI::write(sprintf("  • Feeder Match Score        : %.1f pts [%s]", $ev['feeder']['feeder_score'], $ev['feeder']['score_semantics'] ?? 'N/A'));
                CLI::write(sprintf("  • Topology Continuity Score : %.1f pts [%s] (Alignment: %s, Linked: %d assets)", $ev['continuity']['continuity_score'], $tpSemantics, $ev['continuity']['alignment'] ?? 'N/A', $ev['continuity']['linked_assets_count']));
            }

            CLI::write("\n==============================================================", 'cyan');
            CLI::write("🟢 ADVISORY ANALYSIS COMPLETE: Gunakan 'ar01:verify-section' untuk human sign-off", 'green');
            CLI::write("==============================================================\n", 'cyan');
            return 0;
        }

        // Mode 2: Batch Feeder Analysis
        if ($feederId === null) {
            CLI::error("Harap tentukan Feeder ID atau Kode Penyulang (misal: php spark ar01:candidates 4).");
            return 1;
        }

        $result = $candidateService->analyzeFeeder($feederId, $limit);

        if ($isJson) {
            CLI::write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        if (!$result['success']) {
            CLI::error("ERROR: " . ($result['error'] ?? 'Gagal menganalisis penyulang.'));
            return 1;
        }

        CLI::write("\n==============================================================", 'cyan');
        CLI::write("AR-01 PHASE 5G: SPATIAL SECTION CANDIDATE ENGINE", 'cyan');
        CLI::write("==============================================================", 'cyan');
        CLI::write("MODE        : ADVISORY / READ-ONLY", 'yellow');
        CLI::write("FEEDER      : [{$result['kode_penyulang']}] {$result['nama_penyulang']} (ID: #{$feederId})", 'yellow');
        CLI::write("ASSETS      : {$result['total_unresolved']} Unresolved (Dianalisis: {$result['analyzed_count']})", 'yellow');
        CLI::write("MUTATION    : ZERO (Strictly Read-Only)", 'green');
        CLI::write("PROMOTION   : DISABLED (Gate LOCKED)\n", 'red');

        CLI::write("STATISTIK KEYAKINAN REKOMENDASI (Confidence Distribution):", 'cyan');
        CLI::write(sprintf("  • High Confidence   : %s assets", CLI::color((string)$result['statistics']['high_confidence'], 'green')));
        CLI::write(sprintf("  • Ambiguous (Margin < 5%%): %s assets (Perlu Konfirmasi Lapangan)", CLI::color((string)$result['statistics']['ambiguous'], 'yellow')));
        CLI::write(sprintf("  • Low / Unresolved  : %s assets\n", CLI::color((string)$result['statistics']['low_unresolved'], 'red')));

        CLI::write(sprintf("DAFTAR REKOMENDASI KANDIDAT (Menampilkan %d aset):", count($result['assets'])), 'cyan');
        CLI::write(str_repeat("-", 95));
        CLI::write(sprintf("%-8s | %-20s | %-18s | %-6s | %-12s | %-14s", "PK ID", "Kode Asset", "Top Candidate", "Skor", "Confidence", "Margin Top2"));
        CLI::write(str_repeat("-", 95));

        foreach ($result['assets'] as $a) {
            $top = $a['top_recommendation'];
            if (!$top) continue;

            $confColor = $top['confidence'] === 'HIGH' ? 'green' : ($top['confidence'] === 'AMBIGUOUS' ? 'yellow' : 'white');
            CLI::write(sprintf(
                "#%-7d | %-20s | Section #%-9d | %-6.1f | %-12s | Δ %.1f%%",
                $a['asset_id'],
                mb_strimwidth($a['kode_asset'], 0, 20, '...'),
                $top['section_id'],
                $top['score'],
                CLI::color($top['confidence'], $confColor),
                $a['margin_percent']
            ));
        }
        CLI::write(str_repeat("-", 95));

        CLI::write("\nTip: Untuk bedah evidence detail pada aset tunggal:", 'cyan');
        $sampleId = $result['assets'][0]['asset_id'] ?? 3711;
        CLI::write("     php spark ar01:candidates {$feederId} --asset={$sampleId}", 'yellow');
        CLI::write("     php spark ar01:candidates {$feederId} --asset={$sampleId} --json\n", 'yellow');

        CLI::write("==============================================================", 'cyan');
        CLI::write("🟢 READ-ONLY CANDIDATE AUDIT COMPLETE: Zero Mutation Applied", 'green');
        CLI::write("==============================================================\n", 'cyan');

        return 0;
    }
}
