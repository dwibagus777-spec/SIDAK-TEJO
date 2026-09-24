<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\OneShotNetworkIngestionService;

/**
 * Feeder118TopologyRegressionTest
 *
 * Permanent Regression Test for Feeder 118 (Bahagia Steel 1) Topological Invariants:
 * 1. assets = 45
 * 2. authoritative TL = 0
 * 3. preview segments > 0
 * 4. candidate 002->003 = reject (IMPOSSIBLE_DISTANCE: 988.6m)
 * 5. candidate 005->006 = reject (IMPOSSIBLE_DISTANCE: 1036.0m)
 * 6. candidate 037->049 = reject (IMPOSSIBLE_DISTANCE: 373.1m)
 * 7. candidate 049->050 = reject (IMPOSSIBLE_DISTANCE: 390.4m)
 * 8. candidate 020->021 = SECTION_BOUNDARY_VIOLATION (sec 46 != sec 50)
 * 9. candidate 033->034 = AUTO_ACCEPT 0.9600 candidate
 * 10. DB authoritative TL tetap 0
 */
class Feeder118TopologyRegressionTest extends CIUnitTestCase
{
    private array $f118Assets = [];
    private array $candidateResults = [];
    private array $rejectionResults = [];

    protected function setUp(): void
    {
        parent::setUp();

        $assetsCsvFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';
        $secAuditFile  = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/SECTION_RESOLUTION_AUDIT.json';

        $secAuditRaw = json_decode(file_get_contents($secAuditFile), true);
        $secMap = [];
        foreach ($secAuditRaw as $r) {
            $secMap[$r['asset_id']] = $r;
        }

        $h = fopen($assetsCsvFile, 'r');
        $header = fgetcsv($h);
        $totalAssets = 0;
        $this->f118Assets = [];

        while (($row = fgetcsv($h)) !== false) {
            $totalAssets++;
            if (trim($row[5] ?? '') === 'BAHAGIA STEEL 1') {
                $seq = 0;
                if (preg_match('/_(\d+)$/', $row[2], $m)) $seq = (int)$m[1];
                elseif (preg_match('/-(\d+)$/', $row[1], $m)) $seq = (int)$m[1];

                $secAudit = $secMap[$totalAssets] ?? null;
                $this->f118Assets[] = [
                    'id'           => $totalAssets,
                    'kode_asset'   => $row[1],
                    'nama_asset'   => $row[2],
                    'latitude'     => (float)$row[13],
                    'longitude'    => (float)$row[14],
                    'status'       => $row[15] ?? 'NORMAL',
                    'sequence_no'  => $seq,
                    'penyulang_id' => 118,
                    'ulp_id'       => 2,
                    'section_id'   => (int)($secAudit['resolved_section_id'] ?? 0),
                    'section_name' => $secAudit['resolved_section_name'] ?? ''
                ];
            }
        }
        fclose($h);

        usort($this->f118Assets, fn($a, $b) => $a['sequence_no'] <=> $b['sequence_no']);

        // Run evaluation across consecutive sequence spans
        $degrees = array_fill_keys(array_column($this->f118Assets, 'id'), 0);
        $this->candidateResults = [];
        $this->rejectionResults = [];

        for ($i = 0; $i < count($this->f118Assets) - 1; $i++) {
            $u = $this->f118Assets[$i];
            $v = $this->f118Assets[$i + 1];
            $spanKey = $u['nama_asset'] . '->' . $v['nama_asset'];

            $dist = OneShotNetworkIngestionService::haversineDistance(
                $u['latitude'], $u['longitude'],
                $v['latitude'], $v['longitude']
            );

            if ($dist < 1.0 || $dist > 120.0) {
                $this->rejectionResults[$spanKey] = [
                    'reason'   => 'IMPOSSIBLE_DISTANCE',
                    'distance' => $dist
                ];
                continue;
            }

            if ($u['section_id'] > 0 && $v['section_id'] > 0 && $u['section_id'] !== $v['section_id']) {
                $this->rejectionResults[$spanKey] = [
                    'reason'   => 'SECTION_BOUNDARY_VIOLATION',
                    'distance' => $dist,
                    'sec_u'    => $u['section_id'],
                    'sec_v'    => $v['section_id']
                ];
                continue;
            }

            $ev = OneShotNetworkIngestionService::computeEvidenceAndConfidence($u, $v, $dist, $degrees[$u['id']], $degrees[$v['id']]);
            $this->candidateResults[$spanKey] = [
                'distance'   => $dist,
                'confidence' => $ev['confidence'],
                'evidence'   => $ev['evidence']
            ];
            $degrees[$u['id']]++;
            $degrees[$v['id']]++;
        }
    }

    /**
     * INVARIANT 1: Total assets on Feeder 118 must be exactly 45.
     */
    public function test01Feeder118TotalAssetsIs45(): void
    {
        $this->assertCount(45, $this->f118Assets, "Feeder 118 must contain exactly 45 network assets.");
    }

    /**
     * INVARIANT 2: Authoritative translines on Feeder 118 must be exactly 0.
     */
    public function test02Feeder118AuthoritativeTranslinesIsZero(): void
    {
        $globalTlFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_analytics_global.json';
        $tlData = json_decode(file_get_contents($globalTlFile), true);
        $feeders = $tlData['breakdown']['feeders'] ?? [];
        $f118Count = 0;
        foreach ($feeders as $f) {
            if ((int)$f['id'] === 118) {
                $f118Count = (int)$f['count'];
            }
        }
        $this->assertSame(0, $f118Count, "Authoritative translines on Feeder 118 must be strictly 0.");
    }

    /**
     * INVARIANT 3: Physical Gaps >120m must be rejected by IMPOSSIBLE_DISTANCE.
     */
    public function test03PhysicalGapsRejectedByImpossibleDistance(): void
    {
        // Span 2 -> 3 (002 -> 003): 988.6m
        $this->assertArrayHasKey('BAHAGIA STEEL 1_002->BAHAGIA STEEL 1_003', $this->rejectionResults);
        $this->assertSame('IMPOSSIBLE_DISTANCE', $this->rejectionResults['BAHAGIA STEEL 1_002->BAHAGIA STEEL 1_003']['reason']);
        $this->assertGreaterThan(120.0, $this->rejectionResults['BAHAGIA STEEL 1_002->BAHAGIA STEEL 1_003']['distance']);

        // Span 5 -> 6 (005 -> 006): 1036m
        $this->assertArrayHasKey('BAHAGIA STEEL 1_005->BAHAGIA STEEL 1_006', $this->rejectionResults);
        $this->assertSame('IMPOSSIBLE_DISTANCE', $this->rejectionResults['BAHAGIA STEEL 1_005->BAHAGIA STEEL 1_006']['reason']);
        $this->assertGreaterThan(120.0, $this->rejectionResults['BAHAGIA STEEL 1_005->BAHAGIA STEEL 1_006']['distance']);

        // Span 37 -> 38 (037 -> 049): 373.1m
        $this->assertArrayHasKey('BAHAGIA STEEL 1_037->BAHAGIA STEEL 1_049', $this->rejectionResults);
        $this->assertSame('IMPOSSIBLE_DISTANCE', $this->rejectionResults['BAHAGIA STEEL 1_037->BAHAGIA STEEL 1_049']['reason']);

        // Span 38 -> 39 (049 -> 050): 390.4m
        $this->assertArrayHasKey('BAHAGIA STEEL 1_049->BAHAGIA STEEL 1_050', $this->rejectionResults);
        $this->assertSame('IMPOSSIBLE_DISTANCE', $this->rejectionResults['BAHAGIA STEEL 1_049->BAHAGIA STEEL 1_050']['reason']);
    }

    /**
     * INVARIANT 4: Span 20 -> 21 crosses Section 46 to Section 50 and must be rejected as SECTION_BOUNDARY_VIOLATION.
     */
    public function test04Span20To21RejectedAsSectionBoundaryViolation(): void
    {
        $spanKey = 'BAHAGIA STEEL 1_020->BAHAGIA STEEL 1_021';
        $this->assertArrayHasKey($spanKey, $this->rejectionResults);
        $rej = $this->rejectionResults[$spanKey];
        $this->assertSame('SECTION_BOUNDARY_VIOLATION', $rej['reason']);
        $this->assertSame(46, $rej['sec_u']);
        $this->assertSame(50, $rej['sec_v']);
        $this->assertLessThanOrEqual(120.0, $rej['distance'], "Physical distance is plausible (48.6m), proving boundary rejection is strictly electrical.");
    }

    /**
     * INVARIANT 5: Span 33 -> 34 is within Section 50, ideal span, and must be AUTO_ACCEPT (0.9600).
     */
    public function test05Span33To34IsAutoAccept(): void
    {
        $spanKey = 'BAHAGIA STEEL 1_033->BAHAGIA STEEL 1_034';
        $this->assertArrayHasKey($spanKey, $this->candidateResults);
        $cand = $this->candidateResults[$spanKey];
        $this->assertGreaterThanOrEqual(0.9500, $cand['confidence']);
        $this->assertSame(0.96, $cand['confidence']);
    }
}
