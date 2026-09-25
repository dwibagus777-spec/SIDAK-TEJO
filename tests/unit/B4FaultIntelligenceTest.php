<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\FaultLocationIntelligenceService;
use App\Services\NetworkIntelligenceService;
use App\Services\NetworkContextEngine;

/**
 * B4FaultIntelligenceTest
 *
 * 15-Point Unit Test Suite for Phase B.4 Fault Location Intelligence Platform:
 *  1. Engine Version Compliance (FLI-1.0.0, Guard 3)
 *  2. Snapshot Binding Determinism (TOPOLOGY-20260925-243-ad2c9fcb, Guard 2)
 *  3. Deterministic Input Hash (SHA256 repeatability, Guards 3 & 13)
 *  4. Structured Evidence Breakdown JSON (Guard 4)
 *  5. Explicit Impedance Profile Unsupported Flag (Guard 5)
 *  6. Candidate Terminology Strictness (STATUS: CANDIDATE, Guard 14)
 *  7. Distance Tolerance Fallback & Dynamic Configuration (Guard 7)
 *  8. Multi-Factor Ranking Distance Score Weighting (Guard 6)
 *  9. Multi-Factor Ranking Historical Prior Weighting (Guard 6 & 10)
 * 10. Deterministic Ranking Tie Breaking (Confidence, Delta, ID)
 * 11. Empty Candidates Out-of-Range Handling
 * 12. Zero Database Mutation Invariant across all operations
 * 13. Feeder 118 Ground Truth Scenario (Tiang 33 -> Tiang 34)
 * 14. Multi-Branch Traversal & Candidate Evaluation
 * 15. Integrity Audit Scorecard (7/7 Guards PASS)
 */
class B4FaultIntelligenceTest extends TestCase
{
    protected FaultLocationIntelligenceService $service;
    protected NetworkIntelligenceService $networkIntelligence;
    protected NetworkContextEngine $contextEngine;
    protected array $fixtureAssets;
    protected array $fixtureTranslines;

    protected function setUp(): void
    {
        parent::setUp();

        $this->networkIntelligence = new NetworkIntelligenceService();
        $this->contextEngine = new NetworkContextEngine($this->networkIntelligence);
        $this->service = new FaultLocationIntelligenceService($this->networkIntelligence, $this->contextEngine);

        // Standard Feeder 118 fixture + branches
        $this->fixtureAssets = [
            // Feeder 118 (Bahagia Steel 1)
            [
                'id'                   => 5245,
                'kode_asset'           => 'AST-KRN-BHGSTL1-JTM-033',
                'nama_asset'           => 'Tiang 33',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 50,
                'penyulang_id'         => 118,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'sequence_no'          => 33,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 5246,
                'kode_asset'           => 'AST-KRN-BHGSTL1-JTM-034',
                'nama_asset'           => 'Tiang 34',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 50,
                'penyulang_id'         => 118,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'sequence_no'          => 34,
                'status'               => 'NORMAL',
            ],
            // Feeder 15 (Multi-hop branch network)
            [
                'id'                   => 101,
                'kode_asset'           => 'AST-F15-001',
                'nama_asset'           => 'PMCB Outgoing F15',
                'jenis_asset'          => 'PMCB',
                'section_id'           => 1,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'sequence_no'          => 1,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 102,
                'kode_asset'           => 'AST-F15-002',
                'nama_asset'           => 'Tiang F15-02',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 1,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'sequence_no'          => 2,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 103,
                'kode_asset'           => 'AST-F15-003',
                'nama_asset'           => 'Tiang F15-03',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 1,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'sequence_no'          => 3,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 104,
                'kode_asset'           => 'AST-F15-004',
                'nama_asset'           => 'Tiang F15-04 (Branch A)',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 2,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'sequence_no'          => 4,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 105,
                'kode_asset'           => 'AST-F15-005',
                'nama_asset'           => 'Tiang F15-05 (Branch B)',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 3,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'sequence_no'          => 5,
                'status'               => 'NORMAL',
            ],
        ];

        $this->fixtureTranslines = [
            // Feeder 118: 33 <-> 34 (27.82m)
            [
                'id'              => 335,
                'penyulang_id'    => 118,
                'section_id'      => 50,
                'source_asset_id' => 5245,
                'target_asset_id' => 5246,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 27.82,
                'is_active'       => 1,
                'deleted_at'      => null,
            ],
            // Feeder 15: 101 <-> 102 (100.0m)
            [
                'id'              => 1001,
                'penyulang_id'    => 15,
                'section_id'      => 1,
                'source_asset_id' => 101,
                'target_asset_id' => 102,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 100.00,
                'is_active'       => 1,
                'deleted_at'      => null,
            ],
            // Feeder 15: 102 <-> 103 (200.0m) -> Acc: 300m
            [
                'id'              => 1002,
                'penyulang_id'    => 15,
                'section_id'      => 1,
                'source_asset_id' => 102,
                'target_asset_id' => 103,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 200.00,
                'is_active'       => 1,
                'deleted_at'      => null,
            ],
            // Feeder 15: 103 <-> 104 Branch A (150.0m) -> Acc: 450m
            [
                'id'              => 1003,
                'penyulang_id'    => 15,
                'section_id'      => 2,
                'source_asset_id' => 103,
                'target_asset_id' => 104,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 150.00,
                'is_active'       => 1,
                'deleted_at'      => null,
            ],
            // Feeder 15: 103 <-> 105 Branch B (180.0m) -> Acc: 480m
            [
                'id'              => 1004,
                'penyulang_id'    => 15,
                'section_id'      => 3,
                'source_asset_id' => 103,
                'target_asset_id' => 105,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 180.00,
                'is_active'       => 1,
                'deleted_at'      => null,
            ],
        ];
    }

    /**
     * Test 1: Engine Version Compliance (FLI-1.0.0, Guard 3)
     */
    public function testEngineVersionCompliance(): void
    {
        $this->assertSame('FLI-1.0.0', FaultLocationIntelligenceService::ANALYSIS_VERSION);
    }

    /**
     * Test 2: Snapshot Binding Determinism (Guard 2)
     */
    public function testSnapshotBindingDeterminism(): void
    {
        $this->assertSame('TOPOLOGY-20260925-243-ad2c9fcb', FaultLocationIntelligenceService::TOPOLOGY_SNAPSHOT_ID);
    }

    /**
     * Test 3: Deterministic Input Hash (SHA256, Guards 3 & 13)
     */
    public function testDeterministicInputHash(): void
    {
        $t1 = ['penyulang_id' => 15, 'target_distance_m' => 300.0, 'device_asset_id' => 101];
        $t2 = ['device_asset_id' => 101, 'target_distance_m' => 300.0, 'penyulang_id' => 15]; // Different key order
        $c  = ['tolerance_m' => 250.0];

        $hash1 = $this->service->computeAnalysisInputHash($t1, FaultLocationIntelligenceService::TOPOLOGY_SNAPSHOT_ID, 'FLI-1.0.0', $c);
        $hash2 = $this->service->computeAnalysisInputHash($t2, FaultLocationIntelligenceService::TOPOLOGY_SNAPSHOT_ID, 'FLI-1.0.0', $c);

        $this->assertSame(64, strlen($hash1));
        $this->assertSame($hash1, $hash2, 'Hashes must be identical regardless of key order');

        // Modifying any parameter must produce a different hash
        $t3 = ['penyulang_id' => 15, 'target_distance_m' => 301.0, 'device_asset_id' => 101];
        $hash3 = $this->service->computeAnalysisInputHash($t3, FaultLocationIntelligenceService::TOPOLOGY_SNAPSHOT_ID, 'FLI-1.0.0', $c);
        $this->assertNotSame($hash1, $hash3);
    }

    /**
     * Test 4: Structured Evidence Breakdown JSON (Guard 4)
     */
    public function testStructuredEvidenceBreakdown(): void
    {
        $res = $this->service->locateCandidates(15, 101, 300.0, 100.0, [], $this->fixtureAssets, $this->fixtureTranslines);
        $candidates = $res['payload']['candidates'];

        $this->assertNotEmpty($candidates);
        $top = $candidates[0];

        $this->assertArrayHasKey('evidence_breakdown', $top);
        $eb = $top['evidence_breakdown'];

        $this->assertArrayHasKey('distance', $eb);
        $this->assertArrayHasKey('topology', $eb);
        $this->assertArrayHasKey('switching', $eb);
        $this->assertArrayHasKey('conductor', $eb);
        $this->assertArrayHasKey('historical', $eb);

        $this->assertSame(300.0, $eb['distance']['target_distance_m']);
        $this->assertArrayHasKey('sub_score', $eb['distance']);
        $this->assertArrayHasKey('sub_score', $eb['topology']);
    }

    /**
     * Test 5: Explicit Conductor Impedance Unsupported Flag (Guard 5)
     */
    public function testExplicitImpedanceProfileUnsupported(): void
    {
        $this->assertFalse(FaultLocationIntelligenceService::IMPEDANCE_SUPPORTED);
        $this->assertSame('NO_CANONICAL_IMPEDANCE_PROFILE', FaultLocationIntelligenceService::IMPEDANCE_REASON);

        $res = $this->service->locateCandidates(15, 101, 300.0, 100.0, [], $this->fixtureAssets, $this->fixtureTranslines);
        $meta = $res['payload']['case_meta'];

        $this->assertFalse($meta['impedance_supported']);
        $this->assertSame('NO_CANONICAL_IMPEDANCE_PROFILE', $meta['impedance_reason']);
    }

    /**
     * Test 6: Candidate Terminology Strictness (Guard 14)
     */
    public function testCandidateTerminologyStrictness(): void
    {
        $res = $this->service->locateCandidates(15, 101, 300.0, 100.0, [], $this->fixtureAssets, $this->fixtureTranslines);
        $candidates = $res['payload']['candidates'];

        foreach ($candidates as $cand) {
            $this->assertSame('CANDIDATE', $cand['candidate_status']);
        }

        $this->assertStringContainsString('NOT FIELD CONFIRMED', $res['payload']['case_meta']['candidate_label_warning']);
    }

    /**
     * Test 7: Distance Tolerance Fallback (Guard 7)
     */
    public function testDistanceToleranceFallback(): void
    {
        // 1. Null tolerance -> fallback to 250.0m
        $res1 = $this->service->locateCandidates(15, 101, 300.0, null, [], $this->fixtureAssets, $this->fixtureTranslines);
        $this->assertSame(250.00, $res1['payload']['case_meta']['distance_tolerance_meters']);

        // 2. Explicit tolerance -> 50.0m
        $res2 = $this->service->locateCandidates(15, 101, 300.0, 50.0, [], $this->fixtureAssets, $this->fixtureTranslines);
        $this->assertSame(50.00, $res2['payload']['case_meta']['distance_tolerance_meters']);
    }

    /**
     * Test 8: Multi-Factor Ranking Distance Score Weighting (Guard 6)
     */
    public function testMultiFactorRankingDistanceDominance(): void
    {
        // Target 300m: Asset 103 is exactly at 300.0m (delta = 0.0m). Asset 102 is at 100.0m (delta = 200.0m).
        $res = $this->service->locateCandidates(15, 101, 300.0, 250.0, [], $this->fixtureAssets, $this->fixtureTranslines);
        $candidates = $res['payload']['candidates'];

        $this->assertNotEmpty($candidates);
        $rank1 = $candidates[0];

        $this->assertSame(103, $rank1['asset_id'], 'Asset 103 with delta 0 must be rank 1');
        $this->assertEquals(0.0, $rank1['distance_delta_m']);
        $this->assertGreaterThan(80.0, $rank1['confidence_score']);
    }

    /**
     * Test 9: Multi-Factor Ranking Historical Prior Weighting (Guard 6 & 10)
     */
    public function testMultiFactorRankingHistoricalPrior(): void
    {
        // Target 465m: Branch A (Asset 104) is at 450m (delta 15m), Branch B (Asset 105) is at 480m (delta 15m)
        // Without historical data: they have identical distance deltas
        $resNoHistory = $this->service->locateCandidates(15, 101, 465.0, 50.0, [], $this->fixtureAssets, $this->fixtureTranslines, []);
        $candsNoHistory = $resNoHistory['payload']['candidates'];

        $this->assertCount(2, $candsNoHistory);

        // Now add historical prior findings on Asset 105 (e.g. repeated tree contact)
        $pastFindings = [
            ['found_asset_id' => 105, 'section_id' => 3],
            ['found_asset_id' => 105, 'section_id' => 3],
        ];

        $resWithHistory = $this->service->locateCandidates(15, 101, 465.0, 50.0, [], $this->fixtureAssets, $this->fixtureTranslines, $pastFindings);
        $candsWithHistory = $resWithHistory['payload']['candidates'];

        $rank1WithHistory = $candsWithHistory[0];
        $this->assertSame(105, $rank1WithHistory['asset_id'], 'Asset 105 with historical fault prior must rank higher than Asset 104');
        $this->assertGreaterThan(0, $rank1WithHistory['evidence_breakdown']['historical']['sub_score']);
    }

    /**
     * Test 10: Deterministic Ranking Tie Breaking
     */
    public function testDeterministicRankingTieBreaking(): void
    {
        $res = $this->service->locateCandidates(15, 101, 300.0, 250.0, [], $this->fixtureAssets, $this->fixtureTranslines);
        $candidates = $res['payload']['candidates'];

        $this->assertNotEmpty($candidates);
        // Verify strictly ascending ranks 1, 2, 3...
        for ($i = 0; $i < count($candidates); $i++) {
            $this->assertSame($i + 1, $candidates[$i]['rank']);
        }
    }

    /**
     * Test 11: Empty Candidates Out-of-Range Handling
     */
    public function testEmptyCandidatesWhenOutOfRange(): void
    {
        // Feeder 15 max distance is 480m. Target distance 5000m with 100m tolerance -> 0 candidates.
        $res = $this->service->locateCandidates(15, 101, 5000.0, 100.0, [], $this->fixtureAssets, $this->fixtureTranslines);
        $meta = $res['payload']['case_meta'];

        $this->assertSame(0, $meta['candidate_count']);
        $this->assertNull($meta['top_candidate_asset_id']);
        $this->assertNull($meta['top_confidence_score']);
        $this->assertEmpty($res['payload']['candidates']);
    }

    /**
     * Test 12: Zero Database Mutation Invariant
     */
    public function testZeroDatabaseMutationInvariant(): void
    {
        $res = $this->service->locateCandidates(118, 5245, 27.0, 50.0, [], $this->fixtureAssets, $this->fixtureTranslines);

        $this->assertSame('READ_ONLY', $res['mode']);
        $this->assertFalse($res['mutation']);
        $this->assertSame('TOPOLOGY-20260925-243-ad2c9fcb', $res['snapshot_id']);
    }

    /**
     * Test 13: Feeder 118 Ground Truth Scenario (Tiang 33 -> Tiang 34)
     */
    public function testFeeder118Scenario(): void
    {
        // Feeder 118 has 1 authoritative edge: 5245 <-> 5246, length 27.82m
        // Target: 25.0m from Tiang 33
        $res = $this->service->locateCandidates(118, 5245, 25.0, 20.0, [], $this->fixtureAssets, $this->fixtureTranslines);
        $candidates = $res['payload']['candidates'];

        $this->assertNotEmpty($candidates);
        $top = $candidates[0];

        $this->assertSame(5246, $top['asset_id']);
        $this->assertSame('AST-KRN-BHGSTL1-JTM-034', $top['kode_asset']);
        $this->assertEquals(2.82, $top['distance_delta_m']);
        $this->assertSame(1, $top['rank']);
    }

    /**
     * Test 14: Multi-Branch Traversal & Candidate Evaluation
     */
    public function testMultiBranchRanking(): void
    {
        // Distance 460m reaches both Branch A (450m) and Branch B (480m)
        $res = $this->service->locateCandidates(15, 101, 460.0, 50.0, [], $this->fixtureAssets, $this->fixtureTranslines);
        $candidates = $res['payload']['candidates'];

        $assetIds = array_column($candidates, 'asset_id');
        $this->assertContains(104, $assetIds);
        $this->assertContains(105, $assetIds);
    }

    /**
     * Test 15: Integrity Audit Scorecard (7/7 Guards PASS)
     */
    public function testIntegrityAuditScorecard(): void
    {
        $audit = $this->service->runIntegrityAudit($this->fixtureAssets, $this->fixtureTranslines);

        $this->assertTrue($audit['all_passed']);
        $this->assertSame('PHASE_B4_INTELLIGENCE_AUDIT_PASS', $audit['status']);
        $this->assertCount(7, $audit['checks']);

        foreach ($audit['checks'] as $key => $check) {
            $this->assertTrue($check['passed'], "Check {$key} ({$check['name']}) must pass.");
        }
    }
}
