<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\FaultDispatchService;
use App\Services\FaultCaseService;
use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * B6FaultDispatchServiceTest
 *
 * Dedicated Unit Test Suite for Phase B.6.3 Fault Dispatch & Patrol Routing Service:
 *  1. Dispatch Creation: Case transitions CANDIDATE_IDENTIFIED -> DISPATCHED (B6-G06)
 *  2. Assignment Idempotency: Re-dispatching to same assignee returns existing record (B6.3-G02)
 *  3. One Active Assignment Policy: Reassignment supersedes previous with REASSIGNED (B6.3-G01, B6.3-G03)
 *  4. Dispatch Terminal Case Guard: Cannot dispatch CLOSED or CANCELLED cases (B6-G06)
 *  5. Assignment Acceptance: Designated crew accepts; case -> ACCEPTED (B6-G06)
 *  6. Assignment Acceptance Authorization: Unauthorized non-assignee rejected (B6.3)
 *  7. Assignment Rejection: Crew rejects with mandatory reason; status -> REJECTED (B6-G06)
 *  8. Assignment Rejection Guard: Missing rejection reason rejected (B6.3)
 *  9. Patrol Journey Start & GPS Provenance: Coordinates & accuracy enforced; case -> EN_ROUTE (B6-G06, B6-G09)
 * 10. GPS Provenance Bounds Guard: Invalid coordinates rejected (B6-G09)
 * 11. Arrival Recording & GPS Provenance: Captures end coordinates; case -> ARRIVED (B6-G06, B6-G09)
 * 12. Investigation Start: Physical inspection begins; case -> INVESTIGATING (B6-G06)
 * 13. Patrol Routing Engine: Nearest-neighbor sequencing decoupled from topology (B6-G08)
 * 14. Assignment History Immutable: Complete append-only audit trail preserved (B6-G07)
 * 15. Dispatch Timeline Reconstruction: Chronological multi-stage event ordering (B6.3-G04)
 * 16. Zero Topology Mutation Invariant across Service Calls (B6.3-G05)
 */
class B6FaultDispatchServiceTest extends TestCase
{
    protected ?BaseConnection $db;
    protected FaultCaseService $caseService;
    protected FaultDispatchService $dispatchService;

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

        $this->caseService = new FaultCaseService($this->db);
        $this->dispatchService = new FaultDispatchService($this->db, $this->caseService);

        $this->createdCaseIds = [];
        $this->createdEventIds = [];
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdCaseIds)) {
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
     * Helper to create a test event and case
     */
    protected function createTestCase(array $caseOptions = []): array
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-B63-TEST-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $eventData = [
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $assetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-B63-' . bin2hex(random_bytes(3)),
            'raw_telemetry_json'     => json_encode(['mock' => true]),
            'fault_phase'            => 'RN',
            'fault_current_a'        => 750.00,
            'relay_distance_m'       => 150.00,
            'protection_elements'    => '51_OC_DELAY',
            'lifecycle_status'       => 'INGESTED',
            'created_at'             => $now,
        ];

        $this->db->table('fault_events')->insert($eventData);
        $eventId = (int)$this->db->insertID();
        $this->createdEventIds[] = $eventId;

        $res = $this->caseService->createOrResolveCase($eventId, $caseOptions);
        $case = $res['case'];
        $this->createdCaseIds[] = (int)$case['id'];

        return $case;
    }

    /**
     * Test 1: Case transitions CANDIDATE_IDENTIFIED -> DISPATCHED and creates assignment
     */
    public function testDispatchCaseNew(): void
    {
        $case = $this->createTestCase();
        $this->assertEquals('CANDIDATE_IDENTIFIED', $case['status']);

        $res = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1, [
            'assignment_note' => 'Immediate patrol required for Feeder 15.'
        ]);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['is_new']);
        $this->assertFalse($res['is_existing']);
        $this->assertNotNull($res['assignment']);
        $this->assertEquals(FaultDispatchService::STATUS_ASSIGNED, $res['assignment']['status']);
        $this->assertEquals(101, (int)$res['assignment']['assigned_to']);
        $this->assertEquals(1, (int)$res['assignment']['assigned_by']);

        // Case status updated to DISPATCHED
        $updatedCase = $this->caseService->getCase((int)$case['id']);
        $this->assertEquals('DISPATCHED', $updatedCase['status']);
    }

    /**
     * Test 2: Assignment Idempotency (B6.3-G02)
     */
    public function testDispatchCaseIdempotency(): void
    {
        $case = $this->createTestCase();

        // First dispatch
        $res1 = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->assertTrue($res1['success']);
        $this->assertTrue($res1['is_new']);

        // Re-dispatch identical assignee while ASSIGNED
        $res2 = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->assertTrue($res2['success']);
        $this->assertFalse($res2['is_new']);
        $this->assertTrue($res2['is_existing']);
        $this->assertEquals($res1['assignment']['id'], $res2['assignment']['id']);

        // Assignment count remains exactly 1
        $count = $this->db->table('dispatch_assignments')->where('fault_case_id', $case['id'])->countAllResults();
        $this->assertEquals(1, $count);
    }

    /**
     * Test 3: One Active Assignment Policy & Reassignment (B6.3-G01, B6.3-G03)
     */
    public function testOneActiveAssignmentPolicy(): void
    {
        $case = $this->createTestCase();

        // Initial dispatch to User 101
        $res1 = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->assertTrue($res1['success']);

        // Reassign to User 202
        $res2 = $this->dispatchService->dispatchCase((int)$case['id'], 202, 1, [
            'assignment_note' => 'Reassigned due to shift rotation.'
        ]);
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['is_new']);

        // History check: first assignment should be REASSIGNED, second ASSIGNED
        $history = $this->dispatchService->getAssignmentHistory((int)$case['id']);
        $this->assertCount(2, $history);

        $this->assertEquals(FaultDispatchService::STATUS_REASSIGNED, $history[0]['status']);
        $this->assertNotNull($history[0]['completed_at']);
        $this->assertEquals(101, (int)$history[0]['assigned_to']);

        $this->assertEquals(FaultDispatchService::STATUS_ASSIGNED, $history[1]['status']);
        $this->assertNull($history[1]['completed_at']);
        $this->assertEquals(202, (int)$history[1]['assigned_to']);

        // Active assignment must return exactly the second assignment
        $active = $this->dispatchService->getActiveAssignment((int)$case['id']);
        $this->assertNotNull($active);
        $this->assertEquals(202, (int)$active['assigned_to']);
        $this->assertEquals(FaultDispatchService::STATUS_ASSIGNED, $active['status']);
    }

    /**
     * Test 4: Cannot dispatch case in terminal state (CLOSED, CANCELLED)
     */
    public function testDispatchTerminalCaseFails(): void
    {
        $case = $this->createTestCase();

        // Cancel case
        $cancelRes = $this->caseService->transitionCase((int)$case['id'], 'CANCELLED', [
            'actor_id' => 1,
            'reason'   => 'Event was synthetic test drill.',
        ]);
        $this->assertTrue($cancelRes['success']);

        // Attempt dispatch on cancelled case
        $res = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->assertFalse($res['success']);
        $this->assertEquals('CASE_TERMINAL_STATE', $res['status']);
    }

    /**
     * Test 5: Designated technician accepts assignment (FSM: DISPATCHED -> ACCEPTED)
     */
    public function testAcceptAssignment(): void
    {
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $assignmentId = (int)$dispRes['assignment']['id'];

        $acceptRes = $this->dispatchService->acceptAssignment($assignmentId, 101);
        $this->assertTrue($acceptRes['success']);
        $this->assertEquals('ASSIGNMENT_ACCEPTED', $acceptRes['status']);
        $this->assertEquals(FaultDispatchService::STATUS_ACCEPTED, $acceptRes['assignment']['status']);
        $this->assertNotNull($acceptRes['assignment']['accepted_at']);

        // Case status is now ACCEPTED
        $updatedCase = $this->caseService->getCase((int)$case['id']);
        $this->assertEquals('ACCEPTED', $updatedCase['status']);
    }

    /**
     * Test 6: Non-designated user rejected from accepting assignment without admin flag
     */
    public function testAcceptAssignmentUnauthorized(): void
    {
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $assignmentId = (int)$dispRes['assignment']['id'];

        // Unauthorized technician (User 999 attempting to accept User 101's dispatch)
        $rejectRes = $this->dispatchService->acceptAssignment($assignmentId, 999);
        $this->assertFalse($rejectRes['success']);
        $this->assertEquals('UNAUTHORIZED_ACTOR', $rejectRes['status']);

        // Authorized with admin override
        $adminRes = $this->dispatchService->acceptAssignment($assignmentId, 999, ['is_admin' => true]);
        $this->assertTrue($adminRes['success']);
    }

    /**
     * Test 7: Technician rejects assignment with reason
     */
    public function testRejectAssignment(): void
    {
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $assignmentId = (int)$dispRes['assignment']['id'];

        $res = $this->dispatchService->rejectAssignment($assignmentId, 101, 'Patrol vehicle experiencing mechanical failure.');
        $this->assertTrue($res['success']);
        $this->assertEquals('ASSIGNMENT_REJECTED', $res['status']);
        $this->assertEquals(FaultDispatchService::STATUS_REJECTED, $res['assignment']['status']);

        $dbRow = $this->db->table('dispatch_assignments')->where('id', $assignmentId)->get()->getRowArray();
        $this->assertEquals(FaultDispatchService::STATUS_REJECTED, $dbRow['status']);
        $this->assertNotNull($dbRow['completed_at']);
        $this->assertStringContainsString('mechanical failure', $dbRow['assignment_note']);
    }

    /**
     * Test 8: Rejection without reason is rejected
     */
    public function testRejectAssignmentMissingReason(): void
    {
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $assignmentId = (int)$dispRes['assignment']['id'];

        $res = $this->dispatchService->rejectAssignment($assignmentId, 101, '   ');
        $this->assertFalse($res['success']);
        $this->assertEquals('MISSING_REJECTION_REASON', $res['status']);
    }

    /**
     * Test 9: Start Journey records GPS provenance (B6-G06, B6-G09: ACCEPTED -> EN_ROUTE)
     */
    public function testStartJourneyGpsProvenance(): void
    {
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->dispatchService->acceptAssignment((int)$dispRes['assignment']['id'], 101);

        $res = $this->dispatchService->startJourney(
            (int)$case['id'],
            101,
            -7.5361234,
            112.2345678,
            4.2,
            ['notes' => 'Departing base camp towards Substation Tejo.']
        );

        $this->assertTrue($res['success']);
        $this->assertEquals('JOURNEY_STARTED', $res['status']);
        $this->assertEquals('EN_ROUTE', $res['case_status']);
        $this->assertNotNull($res['investigation']);
        $this->assertEquals(-7.5361234, (float)$res['investigation']['start_lat']);
        $this->assertEquals(112.2345678, (float)$res['investigation']['start_lng']);
        $this->assertEquals(4.2, (float)$res['investigation']['start_accuracy_m']);

        $updatedCase = $this->caseService->getCase((int)$case['id']);
        $this->assertEquals('EN_ROUTE', $updatedCase['status']);
    }

    /**
     * Test 10: Invalid GPS coordinate bounds are rejected (B6-G09)
     */
    public function testStartJourneyInvalidGps(): void
    {
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->dispatchService->acceptAssignment((int)$dispRes['assignment']['id'], 101);

        // Invalid latitude (> 90)
        $resLat = $this->dispatchService->startJourney((int)$case['id'], 101, 95.0, 112.0);
        $this->assertFalse($resLat['success']);
        $this->assertEquals('INVALID_GPS_PROVENANCE', $resLat['status']);

        // Invalid longitude (< -180)
        $resLng = $this->dispatchService->startJourney((int)$case['id'], 101, -7.5, -185.0);
        $this->assertFalse($resLng['success']);
        $this->assertEquals('INVALID_GPS_PROVENANCE', $resLng['status']);

        // Negative accuracy
        $resAcc = $this->dispatchService->startJourney((int)$case['id'], 101, -7.5, 112.0, -1.0);
        $this->assertFalse($resAcc['success']);
        $this->assertEquals('INVALID_GPS_PROVENANCE', $resAcc['status']);
    }

    /**
     * Test 11: Record Arrival at candidate site (B6-G06, B6-G09: EN_ROUTE -> ARRIVED)
     */
    public function testRecordArrival(): void
    {
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->dispatchService->acceptAssignment((int)$dispRes['assignment']['id'], 101);
        $this->dispatchService->startJourney((int)$case['id'], 101, -7.5360, 112.2340, 5.0);

        $res = $this->dispatchService->recordArrival((int)$case['id'], 101, -7.5385, 112.2365, 3.1);
        $this->assertTrue($res['success']);
        $this->assertEquals('ARRIVAL_RECORDED', $res['status']);
        $this->assertEquals('ARRIVED', $res['case_status']);
        $this->assertNotNull($res['investigation']['arrived_at']);
        $this->assertEquals(-7.5385, (float)$res['investigation']['end_lat']);
        $this->assertEquals(112.2365, (float)$res['investigation']['end_lng']);
        $this->assertEquals(3.1, (float)$res['investigation']['end_accuracy_m']);

        $updatedCase = $this->caseService->getCase((int)$case['id']);
        $this->assertEquals('ARRIVED', $updatedCase['status']);
    }

    /**
     * Test 12: Start Physical Investigation (B6-G06: ARRIVED -> INVESTIGATING)
     */
    public function testStartInvestigation(): void
    {
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->dispatchService->acceptAssignment((int)$dispRes['assignment']['id'], 101);
        $this->dispatchService->startJourney((int)$case['id'], 101, -7.5360, 112.2340, 5.0);
        $this->dispatchService->recordArrival((int)$case['id'], 101, -7.5385, 112.2365, 3.1);

        $res = $this->dispatchService->startInvestigation((int)$case['id'], 101);
        $this->assertTrue($res['success']);
        $this->assertEquals('INVESTIGATION_ACTIVE', $res['status']);
        $this->assertEquals('INVESTIGATING', $res['case_status']);

        $updatedCase = $this->caseService->getCase((int)$case['id']);
        $this->assertEquals('INVESTIGATING', $updatedCase['status']);
    }

    /**
     * Test 13: Patrol Route Calculation is Decoupled from Topology (B6-G08)
     */
    public function testPatrolRouteCalculationDecoupled(): void
    {
        // Fetch 3 real assets to serve as candidates
        $assets = $this->db->table('assets')
            ->select('id, kode_asset, latitude, longitude')
            ->where('deleted_at IS NULL')
            ->limit(3)
            ->get()
            ->getResultArray();

        $candidates = [];
        foreach ($assets as $idx => $ast) {
            $candidates[] = [
                'asset_id'                     => (int)$ast['id'],
                'rank'                         => $idx + 1,
                'graph_distance_from_device_m' => 100.0 * ($idx + 1),
                'distance_delta_m'             => 5.0 * $idx,
                'confidence_score'             => 95.0 - ($idx * 5.0),
            ];
        }

        $case = $this->createTestCase(['candidates' => $candidates]);
        $startLat = -7.5300;
        $startLng = 112.2300;

        $route = $this->dispatchService->calculatePatrolRoute((int)$case['id'], $startLat, $startLng);

        $this->assertEquals((int)$case['id'], $route['case_id']);
        $this->assertCount(3, $route['ordered_stops']);
        $this->assertArrayHasKey('total_route_distance_m', $route);
        $this->assertArrayHasKey('total_route_distance_km', $route);
        $this->assertArrayHasKey('estimated_duration_s', $route);
        $this->assertArrayHasKey('route_payload_hash', $route);
        $this->assertEquals(64, strlen($route['route_payload_hash'])); // SHA-256 hash length

        // Verify stops have sequences
        $seqs = array_column($route['ordered_stops'], 'stop_sequence');
        $this->assertEquals([1, 2, 3], $seqs);
    }

    /**
     * Test 14: Assignment History Immutable (B6-G07)
     */
    public function testAssignmentHistoryImmutable(): void
    {
        $case = $this->createTestCase();

        // Sequence of 3 assignments
        $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->dispatchService->dispatchCase((int)$case['id'], 102, 1);
        $this->dispatchService->dispatchCase((int)$case['id'], 103, 1);

        $history = $this->dispatchService->getAssignmentHistory((int)$case['id']);
        $this->assertCount(3, $history);

        // First two are superseded (REASSIGNED), third is ASSIGNED
        $this->assertEquals(FaultDispatchService::STATUS_REASSIGNED, $history[0]['status']);
        $this->assertEquals(FaultDispatchService::STATUS_REASSIGNED, $history[1]['status']);
        $this->assertEquals(FaultDispatchService::STATUS_ASSIGNED, $history[2]['status']);

        // None are deleted or overwritten
        $this->assertEquals(101, (int)$history[0]['assigned_to']);
        $this->assertEquals(102, (int)$history[1]['assigned_to']);
        $this->assertEquals(103, (int)$history[2]['assigned_to']);
    }

    /**
     * Test 15: Reconstruct Chronological Dispatch Timeline (B6.3-G04)
     */
    public function testDispatchTimelineReconstruction(): void
    {
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->dispatchService->acceptAssignment((int)$dispRes['assignment']['id'], 101);
        $this->dispatchService->startJourney((int)$case['id'], 101, -7.5360, 112.2340, 5.0);
        $this->dispatchService->recordArrival((int)$case['id'], 101, -7.5385, 112.2365, 3.1);

        $timeline = $this->dispatchService->getDispatchTimeline((int)$case['id']);
        $this->assertNotEmpty($timeline);

        $stages = array_column($timeline, 'stage');
        $this->assertContains('DISPATCH_ASSIGNMENT', $stages);
        $this->assertContains('DISPATCH_ACCEPTED', $stages);
        $this->assertContains('JOURNEY_STARTED', $stages);
        $this->assertContains('CREW_ARRIVED', $stages);

        // Check chronological monotonicity
        for ($i = 0; $i < count($timeline) - 1; $i++) {
            $this->assertLessThanOrEqual(
                $timeline[$i + 1]['timestamp'],
                $timeline[$i]['timestamp']
            );
        }
    }

    /**
     * Test 16: Zero Topology Mutation Invariant across Service Calls (B6.3-G05)
     */
    public function testZeroTopologyMutation(): void
    {
        $translinesBefore = $this->db->table('gis_translines')->countAllResults();
        $assetsBefore     = $this->db->table('assets')->countAllResults();

        // Run complete dispatch and patrol lifecycle
        $case = $this->createTestCase();
        $dispRes = $this->dispatchService->dispatchCase((int)$case['id'], 101, 1);
        $this->dispatchService->acceptAssignment((int)$dispRes['assignment']['id'], 101);
        $this->dispatchService->calculatePatrolRoute((int)$case['id'], -7.5300, 112.2300);
        $this->dispatchService->startJourney((int)$case['id'], 101, -7.5360, 112.2340, 5.0);
        $this->dispatchService->recordArrival((int)$case['id'], 101, -7.5385, 112.2365, 3.1);
        $this->dispatchService->startInvestigation((int)$case['id'], 101);

        $translinesAfter = $this->db->table('gis_translines')->countAllResults();
        $assetsAfter     = $this->db->table('assets')->countAllResults();

        $this->assertEquals($translinesBefore, $translinesAfter, "ZERO_MUTATION: gis_translines must have 0 delta.");
        $this->assertEquals($assetsBefore, $assetsAfter, "ZERO_MUTATION: assets must have 0 delta.");
    }
}
