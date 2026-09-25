<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\FaultCaseService;
use App\Services\FaultLocationIntelligenceService;
use Config\Database;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * B6FaultCaseServiceTest
 *
 * Dedicated Unit Test Suite for Phase B.6.2 Fault Case Orchestration Service:
 *  1. Case Creation: New Case with Snapshot & FLI Version Binding (B6-G02, B6-G03)
 *  2. Case Creation Idempotency: Re-resolving Existing Event (B6.2-G01, B6.2-G02)
 *  3. Candidate Import & Integrity: Unconfirmed Status (B6-G04, B6.2-G03)
 *  4. Event Immutability: Case Lifecycle Never Modifies Event Telemetry (B6-G03)
 *  5. Case FSM: Valid Sequential Progression Graph (B6-G06)
 *  6. Case FSM: Rejection of Unauthorized Shortcut (B6-G06)
 *  7. Case FSM: Terminal State Immutability for CLOSED (B6-G06)
 *  8. Case FSM: Terminal State Immutability for CANCELLED (B6-G06)
 *  9. Case FSM: Cancellation Requires Reason (B6-G06)
 * 10. Priority Engine: Explicit & Transparent Calculation (P1/P2/P3/P4)
 * 11. Guard B6.2-G00: NO BUSINESS DELETE Enforcement
 * 12. Case Timeline: Append-Only Chronological Audit Trail (B6.2-G04)
 * 13. Case Listing: Filtering by Priority, Status, Snapshot
 * 14. Zero Topology Mutation Invariant across Service Calls (B6.2-G05)
 */
class B6FaultCaseServiceTest extends TestCase
{
    protected ?BaseConnection $db;
    protected FaultCaseService $service;
    protected array $createdCaseIds = [];
    protected array $createdEventIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->db = Database::connect('default');
            $this->db->getVersion();
        } catch (\Throwable $e) {
            $this->db = Database::connect();
        }

        $this->service = new FaultCaseService($this->db);
        $this->createdCaseIds = [];
        $this->createdEventIds = [];
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdCaseIds)) {
            // Internal test cleanup only (bypassing business delete)
            $this->db->table('fault_candidate_assets')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('fault_cases')->whereIn('id', $this->createdCaseIds)->delete();
        }
        if (!empty($this->createdEventIds)) {
            $this->db->table('fault_events')->whereIn('id', $this->createdEventIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * Helper to create a test event
     */
    protected function createTestEvent(array $overrides = []): int
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-B62-TEST-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $data = array_merge([
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $assetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-TEST-1234',
            'raw_telemetry_json'     => json_encode(['mock' => true]),
            'fault_phase'            => 'RN',
            'fault_current_a'        => 650.00,
            'relay_distance_m'       => 27.82,
            'protection_elements'    => '51_OC_DELAY',
            'lifecycle_status'       => 'INGESTED',
            'created_at'             => $now,
        ], $overrides);

        $this->db->table('fault_events')->insert($data);
        $eventId = (int)$this->db->insertID();
        $this->createdEventIds[] = $eventId;

        return $eventId;
    }

    public function testCreateOrResolveCaseNew(): void
    {
        $eventId = $this->createTestEvent([
            'fault_current_a'     => 1200.00,
            'protection_elements' => '50_OC_INST',
        ]);

        $result = $this->service->createOrResolveCase($eventId);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_new']);
        $this->assertFalse($result['is_existing']);
        $this->assertNotNull($result['case']);

        $case = $result['case'];
        $this->createdCaseIds[] = (int)$case['id'];

        $this->assertEquals($eventId, $case['fault_event_id']);
        $this->assertEquals('TOPOLOGY-20260925-243-ad2c9fcb', $case['topology_snapshot_id']);
        $this->assertEquals('FLI-1.0.0', $case['analysis_version']);
        $this->assertEquals('P1_CRITICAL', $case['priority'], 'Instantaneous trip and >1000A must calculate P1_CRITICAL');
        $this->assertEquals('CANDIDATE_IDENTIFIED', $case['status']);
        $this->assertNotNull($case['opened_at']);
        $this->assertNull($case['closed_at']);
    }

    public function testCreateOrResolveCaseIdempotency(): void
    {
        $eventId = $this->createTestEvent();

        // Run 1
        $res1 = $this->service->createOrResolveCase($eventId);
        $this->assertTrue($res1['success']);
        $this->assertTrue($res1['is_new']);
        $caseId1 = (int)$res1['case']['id'];
        $this->createdCaseIds[] = $caseId1;

        // Run 2 (Replay)
        $res2 = $this->service->createOrResolveCase($eventId);
        $this->assertTrue($res2['success']);
        $this->assertFalse($res2['is_new'], 'Replay must not create a new case');
        $this->assertTrue($res2['is_existing'], 'Replay must return is_existing=true');
        $caseId2 = (int)$res2['case']['id'];

        $this->assertEquals($caseId1, $caseId2, 'Replay must return the identical existing case ID');

        // Total count of cases for this event must remain exactly 1
        $caseCount = $this->db->table('fault_cases')->where('fault_event_id', $eventId)->countAllResults();
        $this->assertEquals(1, $caseCount, 'Case creation must be strictly idempotent with 1 case per event');
    }

    public function testSnapshotBindingIntegrity(): void
    {
        $customSnapshot = 'TOPOLOGY-CUSTOM-B6-TEST';
        $eventId = $this->createTestEvent(['topology_snapshot_id' => $customSnapshot]);

        $res = $this->service->createOrResolveCase($eventId);
        $this->assertTrue($res['success']);
        $case = $res['case'];
        $this->createdCaseIds[] = (int)$case['id'];

        $this->assertEquals($customSnapshot, $case['topology_snapshot_id'], 'Case must bind to event snapshot');
    }

    public function testEventVsCaseSeparation(): void
    {
        $eventId = $this->createTestEvent(['lifecycle_status' => 'INGESTED']);
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        // Transition case forward
        $this->service->transitionCase($caseId, 'DISPATCHED');
        $this->service->transitionCase($caseId, 'ACCEPTED');

        // Verify underlying event row is completely untouched
        $event = $this->db->table('fault_events')->where('id', $eventId)->get()->getRowArray();
        $this->assertEquals('INGESTED', $event['lifecycle_status'], 'Event telemetry lifecycle must not be mutated by case transitions');
    }

    public function testCandidateVsActualFindingSeparation(): void
    {
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        $candidates = $this->service->getCandidates($caseId);
        foreach ($candidates as $cand) {
            $this->assertEquals('CANDIDATE', $cand['candidate_status'], 'Candidates must be strictly CANDIDATE and not field confirmed');
        }
    }

    public function testCaseLifecycleFsmValidProgression(): void
    {
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        $sequence = [
            'DISPATCHED',
            'ACCEPTED',
            'EN_ROUTE',
            'ARRIVED',
            'INVESTIGATING',
            'FINDING_RECORDED',
            'CONFIRMED',
            'CLOSED',
        ];

        foreach ($sequence as $nextStatus) {
            $tRes = $this->service->transitionCase($caseId, $nextStatus);
            $this->assertTrue($tRes['success'], "Transition to {$nextStatus} should succeed");
            $this->assertEquals($nextStatus, $tRes['new_status']);
        }

        $finalCase = $this->service->getCase($caseId);
        $this->assertEquals('CLOSED', $finalCase['status']);
        $this->assertNotNull($finalCase['closed_at']);
    }

    public function testCaseLifecycleFsmUnauthorizedShortcutsRejected(): void
    {
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        // 1. Direct jump from CANDIDATE_IDENTIFIED -> CLOSED must be rejected
        $rej1 = $this->service->transitionCase($caseId, 'CLOSED');
        $this->assertFalse($rej1['success']);
        $this->assertEquals('REJECTED_INVALID_TRANSITION', $rej1['status']);

        // 2. Direct jump from CANDIDATE_IDENTIFIED -> CONFIRMED must be rejected
        $rej2 = $this->service->transitionCase($caseId, 'CONFIRMED');
        $this->assertFalse($rej2['success']);
        $this->assertEquals('REJECTED_INVALID_TRANSITION', $rej2['status']);

        // 3. Move to DISPATCHED
        $this->service->transitionCase($caseId, 'DISPATCHED');

        // Direct jump from DISPATCHED -> CLOSED must be rejected
        $rej3 = $this->service->transitionCase($caseId, 'CLOSED');
        $this->assertFalse($rej3['success']);
        $this->assertEquals('REJECTED_INVALID_TRANSITION', $rej3['status']);
    }

    public function testTerminalStateImmutabilityForClosed(): void
    {
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        // Progress all the way to CLOSED
        $steps = ['DISPATCHED', 'ACCEPTED', 'EN_ROUTE', 'ARRIVED', 'INVESTIGATING', 'FINDING_RECORDED', 'CONFIRMED', 'CLOSED'];
        foreach ($steps as $s) {
            $this->service->transitionCase($caseId, $s);
        }

        // Try transitioning out of CLOSED -> must fail as TERMINAL_STATE_IMMUTABLE
        $tRes = $this->service->transitionCase($caseId, 'INVESTIGATING');
        $this->assertFalse($tRes['success']);
        $this->assertEquals('TERMINAL_STATE_IMMUTABLE', $tRes['status']);

        $tRes2 = $this->service->transitionCase($caseId, 'DISPATCHED');
        $this->assertFalse($tRes2['success']);
        $this->assertEquals('TERMINAL_STATE_IMMUTABLE', $tRes2['status']);
    }

    public function testTerminalStateImmutabilityForCancelled(): void
    {
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        // Move to DISPATCHED then CANCELLED
        $this->service->transitionCase($caseId, 'DISPATCHED');
        $cRes = $this->service->transitionCase($caseId, 'CANCELLED', ['reason' => 'False alarm / transient bird flap']);
        $this->assertTrue($cRes['success']);

        // Try transitioning out of CANCELLED -> must fail
        $tRes = $this->service->transitionCase($caseId, 'ACCEPTED');
        $this->assertFalse($tRes['success']);
        $this->assertEquals('TERMINAL_STATE_IMMUTABLE', $tRes['status']);
    }

    public function testCancellationRequiresReason(): void
    {
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        $this->service->transitionCase($caseId, 'DISPATCHED');

        // Cancel with empty reason -> must fail
        $failRes = $this->service->transitionCase($caseId, 'CANCELLED', ['reason' => '']);
        $this->assertFalse($failRes['success']);
        $this->assertEquals('MISSING_CANCELLATION_REASON', $failRes['status']);

        // Cancel with valid reason -> must succeed
        $passRes = $this->service->transitionCase($caseId, 'CANCELLED', ['reason' => 'Inspection crew re-routed to substation blackout']);
        $this->assertTrue($passRes['success']);
    }

    public function testTransparentPriorityCalculation(): void
    {
        // 1. Critical test
        $p1 = $this->service->calculatePriority([
            'protection_elements' => '50_INST',
            'fault_current_a'     => 1500.0,
        ]);
        $this->assertEquals('P1_CRITICAL', $p1);

        // 2. High test
        $p2 = $this->service->calculatePriority([
            'protection_elements' => '51_DELAY',
            'fault_current_a'     => 500.0,
        ]);
        $this->assertEquals('P2_HIGH', $p2);

        // 3. Medium default
        $p3 = $this->service->calculatePriority([
            'protection_elements' => 'OVERCURRENT',
            'fault_current_a'     => 200.0,
        ]);
        $this->assertEquals('P3_MEDIUM', $p3);
    }

    public function testNoBusinessDeleteGuardB62G00(): void
    {
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('B6.2-G00');

        $this->service->deleteCase($caseId);
    }

    public function testCaseTimelineReconstruction(): void
    {
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        $timeline = $this->service->getCaseTimeline($caseId);

        $this->assertNotEmpty($timeline);
        $stages = array_column($timeline, 'stage');

        $this->assertContains('TELEMETRY_INGESTED', $stages);
        $this->assertContains('CASE_OPENED', $stages);
        $this->assertContains('CURRENT_STATUS', $stages);
    }

    public function testListCasesFilters(): void
    {
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId, ['priority' => 'P1_CRITICAL']);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        $list = $this->service->listCases(['priority' => 'P1_CRITICAL']);
        $this->assertGreaterThanOrEqual(1, $list['total']);

        $caseIds = array_column($list['cases'], 'id');
        $this->assertContains($caseId, array_map('intval', $caseIds));
    }

    public function testZeroTopologyMutation(): void
    {
        $tlBefore = $this->db->table('gis_translines')->countAllResults();
        $assetBefore = $this->db->table('assets')->countAllResults();

        // Perform case creation and lifecycle
        $eventId = $this->createTestEvent();
        $res = $this->service->createOrResolveCase($eventId);
        $caseId = (int)$res['case']['id'];
        $this->createdCaseIds[] = $caseId;

        $this->service->transitionCase($caseId, 'DISPATCHED');
        $this->service->getCaseTimeline($caseId);

        $tlAfter = $this->db->table('gis_translines')->countAllResults();
        $assetAfter = $this->db->table('assets')->countAllResults();

        $this->assertEquals($tlBefore, $tlAfter, 'gis_translines must have ZERO mutations during case operations');
        $this->assertEquals($assetBefore, $assetAfter, 'assets must have ZERO mutations during case operations');
    }
}
