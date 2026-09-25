<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\FaultFeedbackService;
use App\Services\FieldFindingsService;
use App\Services\FaultDispatchService;
use App\Services\FaultCaseService;
use Config\Database;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * B6FaultFeedbackServiceTest
 *
 * Dedicated Unit Test Suite for Phase B.6.5 Fault Feedback & Model Calibration Engine:
 *  1. Exact Match Feedback (B6-G11, B6.5-G04)
 *  2. Near Match Feedback (B6.5-G04)
 *  3. Wrong Asset Feedback (B6.5-G04)
 *  4. No Fault Found Feedback (B6.5-G05)
 *  5. In-flight Case Rejected (B6.5-G03)
 *  6. Feedback Determinism & Idempotency (B6.5-G02)
 *  7. Prediction vs Actual Decoupled (B6-G11)
 *  8. Model Immutability Guard (B6-G12)
 *  9. Top-1 and Top-3 Use Candidate Rank (B6.5-G06)
 * 10. No Finding Excluded From Distance Metrics (B6.5-G05)
 * 11. Metric Denominator Is Explicit (B6.5-G05)
 * 12. Feedback Stores Model Version (B6-G02)
 * 13. Feedback Stores Topology Snapshot (B6-G02)
 * 14. Candidate Set Fingerprint Integrity (B6-G01)
 * 15. Existing Feedback Is Immutable (B6.5-G07)
 * 16. Spatial Threshold Metrics (B6.5-G04)
 * 17. Insufficient Sample Governance Status (B6-G12)
 * 18. Pending Governance Review When Sample Sufficient (B6-G12)
 * 19. Calibration Report Contains Exclusions (B6.5-G03)
 * 20. Cause Distribution Is Observational (B6.5-G06)
 * 21. Case Without Finding Rejected (B6.5-G03)
 * 22. Cross-Case Isolation (B6.5-G02)
 * 23. No Business Delete Guard (B6.5-G10)
 * 24. Zero Topology Mutation Invariant across Service Calls (B6.5-G01)
 */
class B6FaultFeedbackServiceTest extends TestCase
{
    protected ?BaseConnection $db;
    protected FaultCaseService $caseService;
    protected FaultDispatchService $dispatchService;
    protected FieldFindingsService $findingsService;
    protected FaultFeedbackService $feedbackService;

    protected array $createdCaseIds = [];
    protected array $createdEventIds = [];
    protected array $createdFeedbackIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->db = Database::connect('default');
            $this->db->getVersion();
        } catch (\Throwable $e) {
            $this->db = Database::connect();
        }

        $this->caseService = new FaultCaseService($this->db);
        $this->dispatchService = new FaultDispatchService($this->db, $this->caseService);
        $this->findingsService = new FieldFindingsService($this->db, $this->caseService);
        $this->feedbackService = new FaultFeedbackService($this->db, $this->caseService);

        $this->createdCaseIds = [];
        $this->createdEventIds = [];
        $this->createdFeedbackIds = [];
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdFeedbackIds)) {
            $this->db->table('fault_feedback')->whereIn('id', $this->createdFeedbackIds)->delete();
        }

        if (!empty($this->createdCaseIds)) {
            // Delete feedback
            $this->db->table('fault_feedback')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            // Delete evidence
            $this->db->table('field_evidence')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            // Delete revisions
            $findingIds = array_column(
                $this->db->table('field_findings')->select('id')->whereIn('fault_case_id', $this->createdCaseIds)->get()->getResultArray(),
                'id'
            );
            if (!empty($findingIds)) {
                $this->db->table('field_finding_revisions')->whereIn('field_finding_id', $findingIds)->delete();
            }
            $this->db->table('field_findings')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('field_investigations')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('dispatch_assignments')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('fault_candidate_assets')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('fault_cases')->whereIn('id', $this->createdCaseIds)->delete();
        }
        if (!empty($this->createdEventIds)) {
            $this->db->table('fault_events')->whereIn('id', $this->createdEventIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * Helper to create a fully confirmed fault case with candidates and findings
     */
    protected function createConfirmedCase(int $targetCandidateRank = 1, ?int $customActualAssetId = null, float $relayDist = 200.0, float $targetDist = 200.0, string $cause = 'LIGHTNING'): array
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-B65-TEST-' . bin2hex(random_bytes(4));

        $assets = $this->db->table('assets')
            ->select('id, kode_asset, latitude, longitude')
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'ASC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $sourceAssetId = (int)($assets[0]['id'] ?? 1);

        $eventData = [
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $sourceAssetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-B65-' . bin2hex(random_bytes(3)),
            'raw_telemetry_json'     => json_encode(['mock' => true]),
            'fault_phase'            => 'RN',
            'fault_current_a'        => 850.00,
            'relay_distance_m'       => $relayDist,
            'protection_elements'    => '51_OC_DELAY',
            'lifecycle_status'       => 'INGESTED',
            'created_at'             => $now,
        ];

        $this->db->table('fault_events')->insert($eventData);
        $eventId = (int)$this->db->insertID();
        $this->createdEventIds[] = $eventId;

        $candidates = [
            [
                'asset_id'                     => (int)$assets[0]['id'],
                'rank'                         => 1,
                'graph_distance_from_device_m' => 205.0,
                'distance_delta_m'             => 5.0,
                'confidence_score'             => 95.0,
            ],
            [
                'asset_id'                     => (int)($assets[1]['id'] ?? $assets[0]['id']),
                'rank'                         => 2,
                'graph_distance_from_device_m' => 220.0,
                'distance_delta_m'             => 20.0,
                'confidence_score'             => 80.0,
            ],
            [
                'asset_id'                     => (int)($assets[2]['id'] ?? $assets[0]['id']),
                'rank'                         => 3,
                'graph_distance_from_device_m' => 250.0,
                'distance_delta_m'             => 50.0,
                'confidence_score'             => 60.0,
            ],
        ];

        $res = $this->caseService->createOrResolveCase($eventId, [
            'candidates'             => $candidates,
            'target_distance_meters' => $targetDist,
        ]);
        $case = $res['case'];
        $caseId = (int)$case['id'];
        $this->createdCaseIds[] = $caseId;

        // Dispatch -> Accept -> Journey -> Arrival -> Investigate
        $disp = $this->dispatchService->dispatchCase($caseId, 101, 1);
        $this->dispatchService->acceptAssignment((int)$disp['assignment']['id'], 101);
        $this->dispatchService->startJourney($caseId, 101, -7.5360, 112.2340, 5.0);
        $this->dispatchService->recordArrival($caseId, 101, -7.5385, 112.2365, 3.0);
        $this->dispatchService->startInvestigation($caseId, 101);

        $investigation = $this->db->table('field_investigations')
            ->where('fault_case_id', $caseId)
            ->where('status', 'INVESTIGATING')
            ->get()
            ->getRowArray();

        // Determine actual asset
        if ($targetCandidateRank === 1) {
            $actualAssetId = (int)$assets[0]['id'];
        } elseif ($targetCandidateRank === 2) {
            $actualAssetId = (int)($assets[1]['id'] ?? $assets[0]['id']);
        } elseif ($targetCandidateRank === 3) {
            $actualAssetId = (int)($assets[2]['id'] ?? $assets[0]['id']);
        } else {
            $actualAssetId = $customActualAssetId ?? (int)($assets[4]['id'] ?? $assets[3]['id'] ?? 9999);
        }

        $findingRes = $this->findingsService->recordFinding($caseId, 101, [
            'investigation_id'      => (int)$investigation['id'],
            'actual_asset_id'       => $actualAssetId,
            'actual_lat'            => (float)($assets[0]['latitude'] ?? -7.5385),
            'actual_lng'            => (float)($assets[0]['longitude'] ?? 112.2365),
            'gps_accuracy_m'        => 3.0,
            'cause_category'        => $cause,
            'condition_description' => 'Field verified damage.',
        ]);

        $findingId = (int)$findingRes['finding']['id'];
        $this->findingsService->confirmFinding($findingId, 1);

        return [
            'case'          => $this->caseService->getCase($caseId),
            'finding'       => $findingRes['finding'],
            'investigation' => $investigation,
            'candidates'    => $candidates,
            'assets'        => $assets,
        ];
    }

    /**
     * Helper to create an UNRESOLVED case (No Fault Found)
     */
    protected function createUnresolvedCase(): array
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-B65-UNRES-' . bin2hex(random_bytes(4));

        $assets = $this->db->table('assets')
            ->select('id, kode_asset, latitude, longitude')
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'ASC')
            ->limit(2)
            ->get()
            ->getResultArray();

        $sourceAssetId = (int)($assets[0]['id'] ?? 1);

        $eventData = [
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $sourceAssetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-B65-' . bin2hex(random_bytes(3)),
            'raw_telemetry_json'     => json_encode(['mock' => true]),
            'fault_phase'            => 'RN',
            'fault_current_a'        => 850.00,
            'relay_distance_m'       => 200.00,
            'protection_elements'    => '51_OC_DELAY',
            'lifecycle_status'       => 'INGESTED',
            'created_at'             => $now,
        ];

        $this->db->table('fault_events')->insert($eventData);
        $eventId = (int)$this->db->insertID();
        $this->createdEventIds[] = $eventId;

        $candidates = [
            [
                'asset_id'                     => (int)$assets[0]['id'],
                'rank'                         => 1,
                'graph_distance_from_device_m' => 205.0,
                'distance_delta_m'             => 5.0,
                'confidence_score'             => 95.0,
            ],
        ];

        $res = $this->caseService->createOrResolveCase($eventId, ['candidates' => $candidates]);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        $disp = $this->dispatchService->dispatchCase($caseId, 101, 1);
        $this->dispatchService->acceptAssignment((int)$disp['assignment']['id'], 101);
        $this->dispatchService->startJourney($caseId, 101, -7.5360, 112.2340, 5.0);
        $this->dispatchService->recordArrival($caseId, 101, -7.5385, 112.2365, 3.0);
        $this->dispatchService->startInvestigation($caseId, 101);

        $investigation = $this->db->table('field_investigations')
            ->where('fault_case_id', $caseId)
            ->where('status', 'INVESTIGATING')
            ->get()
            ->getRowArray();

        $this->findingsService->recordNoFaultFound($caseId, (int)$investigation['id'], 101, [
            'notes'          => 'Inspection complete, no physical anomaly detected.',
            'actual_lat'     => -7.5385,
            'actual_lng'     => 112.2365,
            'gps_accuracy_m' => 3.5,
        ]);

        return [
            'case'          => $this->caseService->getCase($caseId),
            'investigation' => $investigation,
        ];
    }

    /**
     * Test 1: Exact Match Feedback (Rank 1 exact match)
     */
    public function testGenerateFeedbackExactMatch(): void
    {
        $context = $this->createConfirmedCase(1, null, 200.0, 200.0);
        $caseId = (int)$context['case']['id'];

        $res = $this->feedbackService->generateCaseFeedback($caseId);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['is_new']);
        $this->assertFalse($res['is_existing']);
        $this->assertEquals('FEEDBACK_GENERATED', $res['status']);
        $this->assertEquals(FaultFeedbackService::MATCH_CLASS_MATCH, $res['feedback']['match_class']);
        $this->assertEquals(1, $res['feedback']['provenance']['prediction_rank']);
        $this->assertEquals($res['feedback']['predicted_asset_id'], $res['feedback']['actual_asset_id']);
        $this->assertNotNull($res['feedback']['distance_error_m']);
    }

    /**
     * Test 2: Near Match Feedback (Rank 2 candidate match)
     */
    public function testGenerateFeedbackNearMatch(): void
    {
        $context = $this->createConfirmedCase(2);
        $caseId = (int)$context['case']['id'];

        $res = $this->feedbackService->generateCaseFeedback($caseId);

        $this->assertTrue($res['success']);
        $this->assertEquals('FEEDBACK_GENERATED', $res['status']);
        $this->assertEquals(FaultFeedbackService::MATCH_CLASS_NEAR_MATCH, $res['feedback']['match_class']);
        $this->assertEquals(2, $res['feedback']['provenance']['prediction_rank']);
        $this->assertNotEquals($res['feedback']['predicted_asset_id'], $res['feedback']['actual_asset_id']);
    }

    /**
     * Test 3: Wrong Asset Feedback (Asset not in candidates and distance > 50m)
     */
    public function testGenerateFeedbackWrongAsset(): void
    {
        $allAssets = $this->db->table('assets')
            ->select('id')
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();
        $remoteAssetId = (int)$allAssets['id'];

        $context = $this->createConfirmedCase(0, $remoteAssetId);
        $caseId = (int)$context['case']['id'];

        $res = $this->feedbackService->generateCaseFeedback($caseId, ['spatial_threshold_m' => 10.0]);

        $this->assertTrue($res['success']);
        $this->assertEquals('FEEDBACK_GENERATED', $res['status']);
        $this->assertNull($res['feedback']['provenance']['prediction_rank']);
        $this->assertContains($res['feedback']['match_class'], [FaultFeedbackService::MATCH_CLASS_WRONG_ASSET, FaultFeedbackService::MATCH_CLASS_NEAR_MATCH]);
    }

    /**
     * Test 4: No Fault Found Feedback (UNRESOLVED case)
     */
    public function testGenerateFeedbackNoFaultFound(): void
    {
        $context = $this->createUnresolvedCase();
        $caseId = (int)$context['case']['id'];

        $res = $this->feedbackService->generateCaseFeedback($caseId);

        $this->assertTrue($res['success']);
        $this->assertEquals('FEEDBACK_GENERATED', $res['status']);
        $this->assertEquals(FaultFeedbackService::MATCH_CLASS_NO_FINDING, $res['feedback']['match_class']);
        $this->assertNull($res['feedback']['actual_asset_id']);
        $this->assertNull($res['feedback']['distance_error_m']);
        $this->assertEquals('UNRESOLVED', $res['feedback']['provenance']['case_outcome']);
    }

    /**
     * Test 5: In-flight Case Rejected (Guard B6.5-G03)
     */
    public function testInflightCaseRejected(): void
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-B65-INFLIGHT-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->limit(1)->get()->getRowArray();
        $sourceAssetId = (int)$as['id'];

        $this->db->table('fault_events')->insert([
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $sourceAssetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-TEST',
            'raw_telemetry_json'     => json_encode(['mock' => true]),
            'fault_phase'            => 'RN',
            'fault_current_a'        => 500.0,
            'relay_distance_m'       => 150.0,
            'protection_elements'    => '51_OC_DELAY',
            'lifecycle_status'       => 'INGESTED',
            'created_at'             => $now,
        ]);
        $eventId = (int)$this->db->insertID();
        $this->createdEventIds[] = $eventId;

        $caseRes = $this->caseService->createOrResolveCase($eventId, ['candidates' => [
            ['asset_id' => $sourceAssetId, 'rank' => 1, 'confidence_score' => 90.0]
        ]]);
        $caseId = (int)$caseRes['case']['id'];
        $this->createdCaseIds[] = $caseId;

        // Case status is OPEN / DISPATCHED / INVESTIGATING
        $res = $this->feedbackService->generateCaseFeedback($caseId);

        $this->assertFalse($res['success']);
        $this->assertEquals('CASE_NOT_READY_FOR_FEEDBACK', $res['status']);
        $this->assertStringContainsString('B6.5-G03', $res['message']);
    }

    /**
     * Test 6: Feedback Determinism & Idempotency (Guard B6.5-G02)
     */
    public function testFeedbackDeterminismAndIdempotency(): void
    {
        $context = $this->createConfirmedCase(1);
        $caseId = (int)$context['case']['id'];

        // Call 1
        $res1 = $this->feedbackService->generateCaseFeedback($caseId);
        $this->assertTrue($res1['success']);
        $this->assertTrue($res1['is_new']);
        $feedbackId1 = (int)$res1['feedback']['id'];

        $countBefore = $this->db->table('fault_feedback')->where('fault_case_id', $caseId)->countAllResults();

        // Call 2
        $res2 = $this->feedbackService->generateCaseFeedback($caseId);
        $this->assertTrue($res2['success']);
        $this->assertFalse($res2['is_new']);
        $this->assertTrue($res2['is_existing']);
        $this->assertEquals('FEEDBACK_IDEMPOTENT_RESOLVED', $res2['status']);
        $this->assertEquals($feedbackId1, (int)$res2['feedback']['id']);

        $countAfter = $this->db->table('fault_feedback')->where('fault_case_id', $caseId)->countAllResults();
        $this->assertEquals($countBefore, $countAfter, "Idempotent call MUST NOT insert duplicate feedback rows.");
    }

    /**
     * Test 7: Prediction vs Actual Decoupled (Guard B6-G11)
     */
    public function testPredictionVsActualDecoupled(): void
    {
        $context = $this->createConfirmedCase(2); // Rank 2 candidate is actual
        $caseId = (int)$context['case']['id'];

        $res = $this->feedbackService->generateCaseFeedback($caseId);
        $this->assertTrue($res['success']);

        $predictedId = (int)$res['feedback']['predicted_asset_id'];
        $actualId = (int)$res['feedback']['actual_asset_id'];

        $this->assertNotEquals($predictedId, $actualId);

        // Check fault_candidate_assets was not altered
        $cands = $this->caseService->getCandidates($caseId);
        $this->assertEquals($predictedId, (int)$cands[0]['asset_id']);
        $this->assertEquals(1, (int)$cands[0]['rank']);

        // Check fault_cases target_distance_meters was NOT modified
        $case = $this->caseService->getCase($caseId);
        $this->assertNotNull($case);
        $this->assertEquals(200.0, (float)$case['target_distance_meters']);
    }

    /**
     * Test 8: Model Immutability Guard (Guard B6-G12)
     */
    public function testModelImmutableGuard(): void
    {
        $context = $this->createConfirmedCase(1);
        $caseId = (int)$context['case']['id'];

        // Capture model version before
        $versionBefore = FaultFeedbackService::SERVICE_VERSION;

        $res = $this->feedbackService->generateCaseFeedback($caseId);
        $this->assertTrue($res['success']);

        // Report generation must have strict analytical notice
        $report = $this->feedbackService->exportCalibrationReport();
        $this->assertArrayHasKey('hard_governance_notice', $report);
        $this->assertStringContainsString('B6-G12', $report['hard_governance_notice']);
        $this->assertStringContainsString('NEVER mutates', $report['hard_governance_notice']);
        $this->assertEquals($versionBefore, FaultFeedbackService::SERVICE_VERSION);
    }

    /**
     * Test 9: Top-1 and Top-3 Use Candidate Rank (Guard B6.5-G06)
     */
    public function testTop1AndTop3UseCandidateRank(): void
    {
        $case1 = $this->createMinimalCase();
        $case2 = $this->createMinimalCase();
        $case3 = $this->createMinimalCase();

        $now = date('Y-m-d H:i:s');
        $feedbackRows = [
            // Rank 1 hit
            [
                'fault_case_id'       => $case1,
                'candidate_id'        => null,
                'predicted_asset_id'  => 10,
                'actual_asset_id'     => 10,
                'graph_distance_m'    => 100.0,
                'observed_distance_m' => 100.0,
                'distance_error_m'    => 0.0,
                'match_class'         => 'MATCH',
                'feedback_source'     => 'FIELD_INVESTIGATION',
                'feedback_timestamp'  => $now,
                'notes'               => json_encode(['prediction_rank' => 1, 'cause_category' => 'LIGHTNING']),
                'created_at'          => $now,
            ],
            // Rank 2 hit
            [
                'fault_case_id'       => $case2,
                'candidate_id'        => null,
                'predicted_asset_id'  => 10,
                'actual_asset_id'     => 20,
                'graph_distance_m'    => 120.0,
                'observed_distance_m' => 100.0,
                'distance_error_m'    => 20.0,
                'match_class'         => 'NEAR_MATCH',
                'feedback_source'     => 'FIELD_INVESTIGATION',
                'feedback_timestamp'  => $now,
                'notes'               => json_encode(['prediction_rank' => 2, 'cause_category' => 'LIGHTNING']),
                'created_at'          => $now,
            ],
            // Rank null (wrong asset)
            [
                'fault_case_id'       => $case3,
                'candidate_id'        => null,
                'predicted_asset_id'  => 10,
                'actual_asset_id'     => 99,
                'graph_distance_m'    => 100.0,
                'observed_distance_m' => 100.0,
                'distance_error_m'    => 80.0,
                'match_class'         => 'WRONG_ASSET',
                'feedback_source'     => 'FIELD_INVESTIGATION',
                'feedback_timestamp'  => $now,
                'notes'               => json_encode(['prediction_rank' => null, 'cause_category' => 'VEGETATION']),
                'created_at'          => $now,
            ],
        ];

        foreach ($feedbackRows as $row) {
            $this->db->table('fault_feedback')->insert($row);
            $this->createdFeedbackIds[] = (int)$this->db->insertID();
        }

        $metrics = $this->feedbackService->calculateFeedbackMetrics();

        $this->assertGreaterThanOrEqual(1, $metrics['ranking_metrics']['top_1_hits']);
        $this->assertGreaterThanOrEqual(2, $metrics['ranking_metrics']['top_3_hits']);
    }

    /**
     * Test 10: No Finding Excluded From Distance Metrics (Guard B6.5-G05)
     */
    public function testNoFindingExcludedFromDistanceMetrics(): void
    {
        $case1 = $this->createMinimalCase();
        $case2 = $this->createMinimalCase();
        $case3 = $this->createMinimalCase();

        $now = date('Y-m-d H:i:s');
        $testRows = [
            [
                'fault_case_id'       => $case1,
                'candidate_id'        => null,
                'predicted_asset_id'  => 10,
                'actual_asset_id'     => 10,
                'distance_error_m'    => 10.0,
                'match_class'         => 'MATCH',
                'feedback_source'     => 'FIELD_INVESTIGATION',
                'feedback_timestamp'  => $now,
                'notes'               => json_encode(['prediction_rank' => 1, 'fli_engine_version' => 'TEST-VER-1']),
                'created_at'          => $now,
            ],
            [
                'fault_case_id'       => $case2,
                'candidate_id'        => null,
                'predicted_asset_id'  => 10,
                'actual_asset_id'     => 20,
                'distance_error_m'    => 20.0,
                'match_class'         => 'NEAR_MATCH',
                'feedback_source'     => 'FIELD_INVESTIGATION',
                'feedback_timestamp'  => $now,
                'notes'               => json_encode(['prediction_rank' => 2, 'fli_engine_version' => 'TEST-VER-1']),
                'created_at'          => $now,
            ],
            [
                'fault_case_id'       => $case3,
                'candidate_id'        => null,
                'predicted_asset_id'  => 10,
                'actual_asset_id'     => null,
                'distance_error_m'    => null, // NO_FINDING must be null
                'match_class'         => 'NO_FINDING',
                'feedback_source'     => 'FIELD_INVESTIGATION',
                'feedback_timestamp'  => $now,
                'notes'               => json_encode(['prediction_rank' => null, 'fli_engine_version' => 'TEST-VER-1']),
                'created_at'          => $now,
            ],
        ];

        foreach ($testRows as $tr) {
            $this->db->table('fault_feedback')->insert($tr);
            $this->createdFeedbackIds[] = (int)$this->db->insertID();
        }

        $metrics = $this->feedbackService->calculateFeedbackMetrics(['fli_engine_version' => 'TEST-VER-1']);

        $this->assertEquals(3, $metrics['total_cases_evaluated']);
        $this->assertEquals(2, $metrics['distance_denominator_n'], "NO_FINDING must be excluded from distance denominator N.");
        // MAE = (10 + 20) / 2 = 15.0
        $this->assertEquals(15.0, $metrics['distance_error_metrics']['mae_meters']);
    }

    /**
     * Test 11: Metric Denominator Is Explicit (Guard B6.5-G05)
     */
    public function testMetricDenominatorIsExplicit(): void
    {
        $metrics = $this->feedbackService->calculateFeedbackMetrics();

        $this->assertArrayHasKey('total_cases_evaluated', $metrics);
        $this->assertArrayHasKey('hit_rate_denominator', $metrics);
        $this->assertArrayHasKey('distance_denominator_n', $metrics);
        $this->assertArrayHasKey('mae_meters', $metrics['distance_error_metrics']);
        $this->assertArrayHasKey('rmse_meters', $metrics['distance_error_metrics']);
    }

    /**
     * Test 12: Feedback Stores Model Version (Guard B6-G02)
     */
    public function testFeedbackStoresModelVersion(): void
    {
        $context = $this->createConfirmedCase(1);
        $caseId = (int)$context['case']['id'];

        $res = $this->feedbackService->generateCaseFeedback($caseId);
        $this->assertTrue($res['success']);

        $this->assertEquals('FLI-1.0.0', $res['feedback']['provenance']['fli_engine_version']);
    }

    /**
     * Test 13: Feedback Stores Topology Snapshot (Guard B6-G02)
     */
    public function testFeedbackStoresTopologySnapshot(): void
    {
        $context = $this->createConfirmedCase(1);
        $caseId = (int)$context['case']['id'];

        $res = $this->feedbackService->generateCaseFeedback($caseId);
        $this->assertTrue($res['success']);

        $this->assertEquals('TOPOLOGY-20260925-243-ad2c9fcb', $res['feedback']['provenance']['topology_snapshot_id']);
    }

    /**
     * Test 14: Candidate Set Fingerprint Integrity (Guard B6-G01)
     */
    public function testCandidateSetFingerprintIntegrity(): void
    {
        $context = $this->createConfirmedCase(1);
        $caseId = (int)$context['case']['id'];

        $res = $this->feedbackService->generateCaseFeedback($caseId);
        $this->assertTrue($res['success']);

        $fingerprint = $res['feedback']['provenance']['candidate_set_fingerprint'];
        $this->assertNotEmpty($fingerprint);
        $this->assertEquals(64, strlen($fingerprint));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint);
    }

    /**
     * Test 15: Existing Feedback Is Immutable (Guard B6.5-G07)
     */
    public function testExistingFeedbackIsImmutable(): void
    {
        $context = $this->createConfirmedCase(1);
        $caseId = (int)$context['case']['id'];

        $res1 = $this->feedbackService->generateCaseFeedback($caseId);
        $feedbackId = (int)$res1['feedback']['id'];

        // Call again without force_reevaluate
        $res2 = $this->feedbackService->generateCaseFeedback($caseId);
        $this->assertFalse($res2['is_new']);
        $this->assertTrue($res2['is_existing']);
        $this->assertEquals($feedbackId, (int)$res2['feedback']['id']);
    }

    /**
     * Test 16: Spatial Threshold Metrics (Guard B6.5-G04)
     */
    public function testSpatialThresholdMetrics(): void
    {
        $now = date('Y-m-d H:i:s');
        $spatialTestRows = [
            ['dist' => 5.0,  'class' => 'MATCH'],
            ['dist' => 15.0, 'class' => 'NEAR_MATCH'],
            ['dist' => 35.0, 'class' => 'NEAR_MATCH'],
            ['dist' => 65.0, 'class' => 'WRONG_ASSET'],
        ];

        foreach ($spatialTestRows as $idx => $st) {
            $cId = $this->createMinimalCase();
            $this->db->table('fault_feedback')->insert([
                'fault_case_id'       => $cId,
                'candidate_id'        => null,
                'predicted_asset_id'  => 10,
                'actual_asset_id'     => 10 + $idx,
                'distance_error_m'    => $st['dist'],
                'match_class'         => $st['class'],
                'feedback_source'     => 'FIELD_INVESTIGATION',
                'feedback_timestamp'  => $now,
                'notes'               => json_encode(['fli_engine_version' => 'SPATIAL-TEST-VER']),
                'created_at'          => $now,
            ]);
            $this->createdFeedbackIds[] = (int)$this->db->insertID();
        }

        $metrics = $this->feedbackService->calculateFeedbackMetrics(['fli_engine_version' => 'SPATIAL-TEST-VER']);

        $this->assertEquals(1, $metrics['spatial_metrics']['within_10m_count']);
        $this->assertEquals(2, $metrics['spatial_metrics']['within_25m_count']);
        $this->assertEquals(3, $metrics['spatial_metrics']['within_50m_count']);
        $this->assertEquals(1, $metrics['spatial_metrics']['beyond_50m_count']);
    }

    /**
     * Test 17: Insufficient Sample Governance Status (Guard B6-G12)
     */
    public function testInsufficientSampleGovernanceStatus(): void
    {
        $report = $this->feedbackService->exportCalibrationReport(['min_sample_size' => 1000]);

        $this->assertEquals('INSUFFICIENT_SAMPLE', $report['governance_status']);
    }

    /**
     * Test 18: Pending Governance Review When Sample Sufficient (Guard B6-G12)
     */
    public function testPendingGovernanceReviewWhenSampleSufficient(): void
    {
        $now = date('Y-m-d H:i:s');
        // Insert at least 5 feedback rows
        for ($i = 0; $i < 5; $i++) {
            $cId = $this->createMinimalCase();
            $this->db->table('fault_feedback')->insert([
                'fault_case_id'       => $cId,
                'candidate_id'        => null,
                'predicted_asset_id'  => 10,
                'actual_asset_id'     => 10,
                'distance_error_m'    => 5.0,
                'match_class'         => 'MATCH',
                'feedback_source'     => 'FIELD_INVESTIGATION',
                'feedback_timestamp'  => $now,
                'notes'               => json_encode(['prediction_rank' => 1]),
                'created_at'          => $now,
            ]);
            $this->createdFeedbackIds[] = (int)$this->db->insertID();
        }

        $report = $this->feedbackService->exportCalibrationReport(['min_sample_size' => 5]);

        $this->assertEquals('PENDING_HUMAN_GOVERNANCE_REVIEW', $report['governance_status']);
        $this->assertNotEmpty($report['recommended_action']);
    }

    /**
     * Test 19: Calibration Report Contains Exclusions (Guard B6.5-G03)
     */
    public function testCalibrationReportContainsExclusions(): void
    {
        // Create an in-flight case
        $context = $this->createInvestigatingCaseOnly();
        $caseId = (int)$context['case']['id'];

        $report = $this->feedbackService->exportCalibrationReport();

        $this->assertGreaterThanOrEqual(1, $report['cases_excluded_count']);
        $excludedIds = array_column($report['excluded_cases'], 'case_id');
        $this->assertContains($caseId, $excludedIds);
    }

    /**
     * Test 20: Cause Distribution Is Observational (Guard B6.5-G06)
     */
    public function testCauseDistributionIsObservational(): void
    {
        $now = date('Y-m-d H:i:s');
        $c1 = $this->createMinimalCase();
        $this->db->table('fault_feedback')->insert([
            'fault_case_id'       => $c1,
            'candidate_id'        => null,
            'predicted_asset_id'  => 10,
            'actual_asset_id'     => 10,
            'distance_error_m'    => 0.0,
            'match_class'         => 'MATCH',
            'feedback_source'     => 'FIELD_INVESTIGATION',
            'feedback_timestamp'  => $now,
            'notes'               => json_encode(['cause_category' => 'LIGHTNING', 'fli_engine_version' => 'CAUSE-TEST']),
            'created_at'          => $now,
        ]);
        $this->createdFeedbackIds[] = (int)$this->db->insertID();

        $c2 = $this->createMinimalCase();
        $this->db->table('fault_feedback')->insert([
            'fault_case_id'       => $c2,
            'candidate_id'        => null,
            'predicted_asset_id'  => 10,
            'actual_asset_id'     => 10,
            'distance_error_m'    => 0.0,
            'match_class'         => 'MATCH',
            'feedback_source'     => 'FIELD_INVESTIGATION',
            'feedback_timestamp'  => $now,
            'notes'               => json_encode(['cause_category' => 'VEGETATION', 'fli_engine_version' => 'CAUSE-TEST']),
            'created_at'          => $now,
        ]);
        $this->createdFeedbackIds[] = (int)$this->db->insertID();

        $metrics = $this->feedbackService->calculateFeedbackMetrics(['fli_engine_version' => 'CAUSE-TEST']);

        $this->assertEquals(1, $metrics['cause_distribution']['LIGHTNING']);
        $this->assertEquals(1, $metrics['cause_distribution']['VEGETATION']);
    }

    /**
     * Test 21: Case Without Finding Rejected (Guard B6.5-G03)
     */
    public function testCaseWithoutFindingRejected(): void
    {
        // Create a confirmed case manually but remove its finding row
        $context = $this->createConfirmedCase(1);
        $caseId = (int)$context['case']['id'];

        // Remove findings
        $this->db->table('field_finding_revisions')->where('field_finding_id', (int)$context['finding']['id'])->delete();
        $this->db->table('field_findings')->where('fault_case_id', $caseId)->delete();

        $res = $this->feedbackService->generateCaseFeedback($caseId);

        $this->assertFalse($res['success']);
        $this->assertEquals('NO_FINDING_RECORDED_FOR_CASE', $res['status']);
    }

    /**
     * Test 22: Cross-Case Isolation (Guard B6.5-G02)
     */
    public function testCrossCaseIsolation(): void
    {
        $ctxA = $this->createConfirmedCase(1);
        $ctxB = $this->createConfirmedCase(2);

        $caseAId = (int)$ctxA['case']['id'];
        $caseBId = (int)$ctxB['case']['id'];

        $resA = $this->feedbackService->generateCaseFeedback($caseAId);
        $resB = $this->feedbackService->generateCaseFeedback($caseBId);

        $this->assertTrue($resA['success']);
        $this->assertTrue($resB['success']);

        $feedbackA = $this->feedbackService->getFeedbackByCase($caseAId);
        $feedbackB = $this->feedbackService->getFeedbackByCase($caseBId);

        $this->assertEquals((int)$resA['feedback']['id'], (int)$feedbackA['id']);
        $this->assertEquals((int)$resB['feedback']['id'], (int)$feedbackB['id']);
        $this->assertNotEquals((int)$feedbackA['id'], (int)$feedbackB['id']);
    }

    /**
     * Test 23: No Business Delete Guard (Guard B6.5-G10)
     */
    public function testNoBusinessDeleteGuard(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Guard B6.5-G10 Violation');

        $this->feedbackService->deleteFeedback(999);
    }

    /**
     * Test 24: Zero Topology Mutation Invariant across Service Calls (Guard B6.5-G01)
     */
    public function testZeroTopologyMutation(): void
    {
        // 1. Read baseline before
        $translinesBefore = (int)$this->db->table('gis_translines')->countAllResults();
        $assetsBefore     = (int)$this->db->table('assets')->countAllResults();

        // 2. Perform operations: generate feedback, calculate metrics, export calibration report
        $context = $this->createConfirmedCase(1);
        $caseId = (int)$context['case']['id'];

        $this->feedbackService->generateCaseFeedback($caseId);
        $this->feedbackService->calculateFeedbackMetrics();
        $this->feedbackService->exportCalibrationReport();

        // 3. Read baseline after
        $translinesAfter = (int)$this->db->table('gis_translines')->countAllResults();
        $assetsAfter     = (int)$this->db->table('assets')->countAllResults();

        $deltaTranslines = $translinesAfter - $translinesBefore;
        $deltaAssets     = $assetsAfter - $assetsBefore;

        $this->assertEquals(0, $deltaTranslines, "Guard B6.5-G01 Violation: gis_translines mutated! Delta: {$deltaTranslines}");
        $this->assertEquals(0, $deltaAssets, "Guard B6.5-G01 Violation: assets mutated! Delta: {$deltaAssets}");
    }

    /**
     * Helper to create an in-flight case in INVESTIGATING status without confirmed finding
     */
    protected function createInvestigatingCaseOnly(): array
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-B65-INVES-' . bin2hex(random_bytes(4));

        $assets = $this->db->table('assets')
            ->select('id')
            ->where('deleted_at IS NULL')
            ->limit(1)
            ->get()
            ->getResultArray();
        $sourceAssetId = (int)$assets[0]['id'];

        $eventData = [
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $sourceAssetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-TEST',
            'raw_telemetry_json'     => json_encode(['mock' => true]),
            'fault_phase'            => 'RN',
            'fault_current_a'        => 500.0,
            'relay_distance_m'       => 150.0,
            'protection_elements'    => '51_OC_DELAY',
            'lifecycle_status'       => 'INGESTED',
            'created_at'             => $now,
        ];

        $this->db->table('fault_events')->insert($eventData);
        $eventId = (int)$this->db->insertID();
        $this->createdEventIds[] = $eventId;

        $res = $this->caseService->createOrResolveCase($eventId, ['candidates' => [
            ['asset_id' => $sourceAssetId, 'rank' => 1, 'confidence_score' => 90.0]
        ]]);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        $disp = $this->dispatchService->dispatchCase($caseId, 101, 1);
        $this->dispatchService->acceptAssignment((int)$disp['assignment']['id'], 101);
        $this->dispatchService->startJourney($caseId, 101, -7.5360, 112.2340, 5.0);
        $this->dispatchService->recordArrival($caseId, 101, -7.5385, 112.2365, 3.0);
        $this->dispatchService->startInvestigation($caseId, 101);

        return [
            'case' => $this->caseService->getCase($caseId),
        ];
    }

    /**
     * Helper to create a minimal fault case for metric calculation tests
     */
    protected function createMinimalCase(): int
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-MIN-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->limit(1)->get()->getRowArray();
        $sourceAssetId = (int)($as['id'] ?? 1);

        $this->db->table('fault_events')->insert([
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $sourceAssetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-TEST',
            'raw_telemetry_json'     => json_encode(['mock' => true]),
            'fault_phase'            => 'RN',
            'fault_current_a'        => 500.0,
            'relay_distance_m'       => 150.0,
            'protection_elements'    => '51_OC_DELAY',
            'lifecycle_status'       => 'INGESTED',
            'created_at'             => $now,
        ]);
        $eventId = (int)$this->db->insertID();
        $this->createdEventIds[] = $eventId;

        $res = $this->caseService->createOrResolveCase($eventId, ['candidates' => [
            ['asset_id' => $sourceAssetId, 'rank' => 1, 'confidence_score' => 90.0]
        ]]);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        return $caseId;
    }
}

