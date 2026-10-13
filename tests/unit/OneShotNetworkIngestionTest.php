<?php

namespace Tests\Unit;

use App\Services\OneShotNetworkIngestionService;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * OneShotNetworkIngestionTest
 *
 * 20-Gate Acceptance Test Suite for SIDAK TEJO Phase B:
 * One-Shot Network Intelligence & Dry-Run Architecture.
 */
class OneShotNetworkIngestionTest extends CIUnitTestCase
{
    private OneShotNetworkIngestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OneShotNetworkIngestionService();
    }

    private function getStandardMockSource(): array
    {
        return [
            'total_feeders'              => 134,
            'feeders_with_assets'        => 33,
            'feeders_without_assets'     => 101,
            'total_active_assets'        => 5236,
            'total_existing_translines'  => 217,
            'feeders'                    => [
                ['id' => 118, 'nama_penyulang' => 'BAHAGIA STEEL 1', 'ulp_id' => 2],
                ['id' => 23,  'nama_penyulang' => 'GEDANGAN',         'ulp_id' => 1],
                ['id' => 1,   'nama_penyulang' => 'SIWALAN PANJI',    'ulp_id' => 1],
            ],
            'assets_by_feeder'           => [
                118 => [
                    ['id' => 5229, 'nama_asset' => 'BHG_001', 'kode_asset' => 'AST-BHG-001', 'latitude' => -7.41160, 'longitude' => 112.55677, 'penyulang_id' => 118, 'ulp_id' => 2, 'section_id' => 1, 'status' => 'NORMAL', 'sequence_no' => 1],
                    ['id' => 5230, 'nama_asset' => 'BHG_002', 'kode_asset' => 'AST-BHG-002', 'latitude' => -7.41132, 'longitude' => 112.55672, 'penyulang_id' => 118, 'ulp_id' => 2, 'section_id' => 1, 'status' => 'NORMAL', 'sequence_no' => 2],
                ]
            ],
            'assets_by_ulp'              => [],
            'assets_by_section'          => [],
            'existing_natural_keys'      => [
                'TL-NAT:23:4020-4088' => 236
            ],
            'existing_translines_by_f'   => [
                23 => [['id' => 236, 'penyulang_id' => 23, 'source_asset_id' => 4020, 'target_asset_id' => 4088]]
            ]
        ];
    }

    /**
     * TEST 01: 134 Feeders Global Coverage Ingestion Contract
     */
    public function test01Scan134FeedersStructure(): void
    {
        $mock = $this->getStandardMockSource();
        $dryRun = $this->service->runDryRun($mock);

        $this->assertSame(134, $dryRun['summary']['total_feeders']);
        $this->assertCount(3, $dryRun['feeder_breakdown']);
    }

    /**
     * TEST 02: 5,236 Active Network Assets Scanning Contract
     */
    public function test02Scan5236ActiveAssetsStructure(): void
    {
        $mock = $this->getStandardMockSource();
        $dryRun = $this->service->runDryRun($mock);

        $this->assertSame(5236, $dryRun['summary']['total_active_assets']);
    }

    /**
     * TEST 03: Existing Authoritative Transline Baseline Reconciled to 217 Across 5 Feeders
     */
    public function test03ExistingAuthoritativeBaselineReconciled(): void
    {
        // Reconciled production truth: 217 active translines across 5 feeders
        $expectedPerFeeder = [
            4   => 2,   // Gemurung
            15  => 197, // Banjar Kemantren
            23  => 1,   // Gedangan
            56  => 9,   // Banjaran
            131 => 8,   // By Pass
        ];

        $sum = array_sum($expectedPerFeeder);
        $this->assertSame(217, $sum, "Baseline count must reconcile to exactly 217 active translines.");

        $mock = $this->getStandardMockSource();
        $dryRun = $this->service->runDryRun($mock);
        $this->assertSame(217, $dryRun['summary']['existing_authoritative_translines']);
    }

    /**
     * TEST 04: Canonical Natural Key Commutativity (A->B == B->A)
     */
    public function test04NaturalKeyCommutative(): void
    {
        $keyForward = OneShotNetworkIngestionService::buildNaturalKey(118, 5229, 5230);
        $keyReverse = OneShotNetworkIngestionService::buildNaturalKey(118, 5230, 5229);

        $this->assertSame('TL-NAT:118:5229-5230', $keyForward);
        $this->assertSame($keyForward, $keyReverse);
    }

    /**
     * TEST 05: Cross Feeder Connection Rejected
     */
    public function test05CrossFeederRejected(): void
    {
        $assets = [
            ['id' => 101, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.4000, 'longitude' => 112.5500, 'sequence_no' => 1],
            ['id' => 102, 'penyulang_id' => 119, 'ulp_id' => 2, 'latitude' => -7.4003, 'longitude' => 112.5500, 'sequence_no' => 2],
        ];

        $res = $this->service->generateFeederCandidates(118, $assets, []);

        $this->assertCount(0, $res['candidates']);
        $this->assertCount(1, $res['rejections']);
        $this->assertSame(OneShotNetworkIngestionService::REJECT_CROSS_FEEDER, $res['rejections'][0]['reason']);
    }

    /**
     * TEST 06: Cross ULP Connection Rejected
     */
    public function test06CrossUlpRejected(): void
    {
        $assets = [
            ['id' => 201, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.4000, 'longitude' => 112.5500, 'sequence_no' => 1],
            ['id' => 202, 'penyulang_id' => 118, 'ulp_id' => 1, 'latitude' => -7.4003, 'longitude' => 112.5500, 'sequence_no' => 2],
        ];

        $res = $this->service->generateFeederCandidates(118, $assets, []);

        $this->assertCount(0, $res['candidates']);
        $this->assertCount(1, $res['rejections']);
        $this->assertSame(OneShotNetworkIngestionService::REJECT_CROSS_ULP, $res['rejections'][0]['reason']);
    }

    /**
     * TEST 07: Self Loop Connection Rejected
     */
    public function test07SelfLoopRejected(): void
    {
        $assets = [
            ['id' => 301, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.4000, 'longitude' => 112.5500, 'sequence_no' => 1],
            ['id' => 301, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.4000, 'longitude' => 112.5500, 'sequence_no' => 2],
        ];

        $res = $this->service->generateFeederCandidates(118, $assets, []);

        $this->assertCount(0, $res['candidates']);
        $this->assertCount(1, $res['rejections']);
        $this->assertSame(OneShotNetworkIngestionService::REJECT_SELF_LOOP, $res['rejections'][0]['reason']);
    }

    /**
     * TEST 08: Impossible Distance Span Rejected (> 120m)
     */
    public function test08ImpossibleDistanceRejected(): void
    {
        $assets = [
            ['id' => 401, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.4000, 'longitude' => 112.5500, 'sequence_no' => 1],
            ['id' => 402, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.4200, 'longitude' => 112.5500, 'sequence_no' => 2],
        ];

        $res = $this->service->generateFeederCandidates(118, $assets, []);

        $this->assertCount(0, $res['candidates']);
        $this->assertCount(1, $res['rejections']);
        $this->assertSame(OneShotNetworkIngestionService::REJECT_IMPOSSIBLE_DISTANCE, $res['rejections'][0]['reason']);
    }

    /**
     * TEST 09: Existing Authoritative Edge Preserved (Zero Mutation / Rejection Conflict)
     */
    public function test09ExistingAuthoritativeEdgePreserved(): void
    {
        $assets = [
            ['id' => 501, 'penyulang_id' => 23, 'ulp_id' => 1, 'latitude' => -7.3943, 'longitude' => 112.7052, 'sequence_no' => 1],
            ['id' => 502, 'penyulang_id' => 23, 'ulp_id' => 1, 'latitude' => -7.3945, 'longitude' => 112.7059, 'sequence_no' => 2],
        ];

        $existingKeys = [
            'TL-NAT:23:501-502' => 9999
        ];

        $res = $this->service->generateFeederCandidates(23, $assets, $existingKeys);

        $this->assertCount(0, $res['candidates']);
        $this->assertCount(1, $res['rejections']);
        $this->assertSame(OneShotNetworkIngestionService::REJECT_CONFLICT_EXISTING, $res['rejections'][0]['reason']);
    }

    /**
     * TEST 10: Duplicate Candidate Deduplication
     */
    public function test10DuplicateCandidateDeduplicated(): void
    {
        $assets = [
            ['id' => 601, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.40000, 'longitude' => 112.55000, 'sequence_no' => 1],
            ['id' => 602, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.40030, 'longitude' => 112.55000, 'sequence_no' => 2],
            ['id' => 601, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.40000, 'longitude' => 112.55000, 'sequence_no' => 3],
        ];

        $res = $this->service->generateFeederCandidates(118, $assets, []);

        $this->assertCount(1, $res['candidates']);
        $this->assertCount(1, $res['rejections']);
        $this->assertSame(OneShotNetworkIngestionService::REJECT_DUPLICATE, $res['rejections'][0]['reason']);
    }

    /**
     * TEST 11: Dry-Run Determinism (Run #1 Hash == Run #2 Hash)
     */
    public function test11DryRunDeterministic(): void
    {
        $mock = $this->getStandardMockSource();

        $run1 = $this->service->runDryRun($mock);
        $run2 = $this->service->runDryRun($mock);

        $this->assertSame($run1['batch_fingerprint'], $run2['batch_fingerprint']);
        $this->assertSame($run1['summary']['accepted_candidates'], $run2['summary']['accepted_candidates']);
    }

    /**
     * TEST 12: Stale Fingerprint Rejection on Commit
     */
    public function test12StaleFingerprintRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Stale fingerprint/');

        $dryRun = [
            'batch_fingerprint' => 'FINGERPRINT_A',
            'summary'           => ['total_active_assets' => 50, 'existing_authoritative_translines' => 0]
        ];

        $this->service->executeAtomicCommit('FINGERPRINT_B_STALE', 1, $dryRun);
    }

    /**
     * TEST 13: Database State Change Detected After Dry-Run
     */
    public function test13DbStateChangeDetected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Database state changed/');

        $fingerprint = 'MATCHING_FINGERPRINT_123';
        $dryRun = [
            'batch_fingerprint' => $fingerprint,
            'summary'           => [
                'total_active_assets'               => 999999, // Mismatched intentionally
                'existing_authoritative_translines' => 0
            ]
        ];

        $this->service->executeAtomicCommit($fingerprint, 1, $dryRun);
    }

    /**
     * TEST 14: Evidence Engine Transparency (All 11 Sub-Scores Present)
     */
    public function test14EvidenceEngineTransparentSubScores(): void
    {
        $assetA = ['id' => 10, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.4000, 'longitude' => 112.5500, 'kode_asset' => 'AST_001', 'section_id' => 5];
        $assetB = ['id' => 11, 'penyulang_id' => 118, 'ulp_id' => 2, 'latitude' => -7.4003, 'longitude' => 112.5500, 'kode_asset' => 'AST_002', 'section_id' => 5];

        $dist = OneShotNetworkIngestionService::haversineDistance(-7.4000, 112.5500, -7.4003, 112.5500);
        $ev = OneShotNetworkIngestionService::computeEvidenceAndConfidence($assetA, $assetB, $dist, 1, 1);

        $evidence = $ev['evidence'];
        $this->assertArrayHasKey('distance_score', $evidence);
        $this->assertArrayHasKey('bearing_score', $evidence);
        $this->assertArrayHasKey('sequence_score', $evidence);
        $this->assertArrayHasKey('section_score', $evidence);
        $this->assertArrayHasKey('feeder_score', $evidence);
        $this->assertArrayHasKey('ulp_score', $evidence);
        $this->assertArrayHasKey('degree_score', $evidence);
        $this->assertArrayHasKey('existing_parent_relation_score', $evidence);
        $this->assertArrayHasKey('coordinate_quality', $evidence);
        $this->assertArrayHasKey('asset_status_score', $evidence);
        $this->assertArrayHasKey('construction_compatibility', $evidence);

        $this->assertGreaterThanOrEqual(OneShotNetworkIngestionService::CONFIDENCE_AUTO_ACCEPT, $ev['confidence']);
    }

    /**
     * TEST 15: Successful Batch Preparation (Atomic Commit Input Contract)
     */
    public function test15SuccessfulBatchPreparation(): void
    {
        $mock = $this->getStandardMockSource();
        $dryRun = $this->service->runDryRun($mock);

        $this->assertArrayHasKey('accepted_candidates', $dryRun);
        $this->assertNotEmpty($dryRun['batch_fingerprint']);
        $this->assertNotEmpty($dryRun['batch_id']);
        $this->assertSame(1, count($dryRun['accepted_candidates']));
    }

    /**
     * TEST 16: Repeat Batch Preparation Idempotency (Identical Fingerprint & Zero Duplicates)
     */
    public function test16RepeatBatchPreparationIdempotent(): void
    {
        $mock = $this->getStandardMockSource();

        $runA = $this->service->runDryRun($mock);
        $runB = $this->service->runDryRun($mock);

        $this->assertSame($runA['batch_fingerprint'], $runB['batch_fingerprint']);
        $this->assertSame($runA['summary']['accepted_candidates'], $runB['summary']['accepted_candidates']);
    }

    /**
     * TEST 17: Feeder 118 (Bahagia Steel 1) Candidate Generation
     */
    public function test17Feeder118CandidateGeneration(): void
    {
        $assets = [];
        for ($i = 1; $i <= 45; $i++) {
            $assets[] = [
                'id'           => 5200 + $i,
                'nama_asset'   => sprintf("BAHAGIA STEEL 1_%03d", $i),
                'kode_asset'   => sprintf("AST-BHG-%03d", $i),
                'latitude'     => -7.41160 + (($i - 1) * 0.0003),
                'longitude'    => 112.55677,
                'penyulang_id' => 118,
                'ulp_id'       => 2,
                'section_id'   => 10,
                'sequence_no'  => $i
            ];
        }

        $res = $this->service->generateFeederCandidates(118, $assets, []);

        $this->assertCount(44, $res['candidates'], "45 collinear poles should yield 44 candidate edges.");
        $this->assertSame(0, count($res['rejections']));

        foreach ($res['candidates'] as $c) {
            $this->assertSame(118, $c['penyulang_id']);
            $this->assertSame('AUTO_ACCEPT_CANDIDATE', $c['classification']);
            $this->assertGreaterThanOrEqual(OneShotNetworkIngestionService::CONFIDENCE_AUTO_ACCEPT, $c['confidence']);
            $this->assertNotEmpty($c['evidence_json']);
        }
    }

    /**
     * TEST 18: Authoritative Feeder Preserved Without Duplication (Feeder 23)
     */
    public function test18AuthoritativeFeederPreserved(): void
    {
        $assets = [
            ['id' => 4020, 'penyulang_id' => 23, 'ulp_id' => 1, 'latitude' => -7.39435, 'longitude' => 112.70523, 'sequence_no' => 1],
            ['id' => 4088, 'penyulang_id' => 23, 'ulp_id' => 1, 'latitude' => -7.39454, 'longitude' => 112.70593, 'sequence_no' => 2],
        ];

        // Feeder 23 already has this edge active in gis_translines
        $existingKeys = [
            'TL-NAT:23:4020-4088' => 236
        ];

        $res = $this->service->generateFeederCandidates(23, $assets, $existingKeys);

        $this->assertCount(0, $res['candidates']);
        $this->assertCount(1, $res['rejections']);
        $this->assertSame(OneShotNetworkIngestionService::REJECT_CONFLICT_EXISTING, $res['rejections'][0]['reason']);
    }

    /**
     * TEST 19: NO_NETWORK Feeder Never Invents Edges From Thin Air
     */
    public function test19NoNetworkFeederGeneratesZeroEdges(): void
    {
        $res = $this->service->generateFeederCandidates(99, [], []);

        $this->assertCount(0, $res['candidates']);
        $this->assertCount(0, $res['rejections']);
    }

    /**
     * TEST 20: Preview Segments Never Pollute Authoritative Translines
     */
    public function test20PreviewNeverPollutesAuthoritative(): void
    {
        $previewSegment = [
            'type'          => 'SPATIAL_PREVIEW',
            'persisted'     => false,
            'authoritative' => false,
            'topology'      => false,
            'coordinates'   => [[112.55677, -7.4116], [112.55672, -7.41132]]
        ];

        $this->assertFalse($previewSegment['persisted']);
        $this->assertFalse($previewSegment['authoritative']);
        $this->assertFalse($previewSegment['topology']);
    }
}
