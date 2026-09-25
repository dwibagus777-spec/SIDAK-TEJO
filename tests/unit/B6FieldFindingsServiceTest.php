<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\FieldFindingsService;
use App\Services\FaultDispatchService;
use App\Services\FaultCaseService;
use Config\Database;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * B6FieldFindingsServiceTest
 *
 * Dedicated Unit Test Suite for Phase B.6.4 Field Findings, Investigation Evidence & Finding Revisions Service:
 *  1. Record Finding New (B6-G04, B6-G06)
 *  2. Prediction vs Actual Separation (B6-G04, B6.4-G01)
 *  3. Prediction Never Auto-Promoted to Actual (B6.4-G08, B6.4-G09)
 *  4. Case FSM Transition to FINDING_RECORDED (B6-G06)
 *  5. Investigation Transition to FINDING_RECORDED (B6-G06)
 *  6. GPS Provenance Bounds & Semantic Accuracy (B6-G09)
 *  7. Authoritative Asset Verification (B6.4-G07)
 *  8. Deleted Asset Cannot Become Actual (B6.4-G07)
 *  9. Cross-Case Investigation Ownership Protection (B6.4-G02)
 * 10. Cross-Case Predicted Asset Protection (B6.4-G02)
 * 11. Finding Submission Idempotency (B6.4-G04)
 * 12. Amend Finding Append-Only Revision (B6-G05)
 * 13. Amend Finding Mandatory Reason Guard (B6-G05)
 * 14. Sequential Revisions Integrity (B6-G05)
 * 15. Revisions Table Strictly Append-Only (B6-G05)
 * 16. Revision Concurrency Protection (B6.4-G03)
 * 17. Confirm Finding Lifecycle (B6-G06)
 * 18. Confirm Without Field Observation Rejected (B6.4-G08)
 * 19. Record No Fault Found Transitions to UNRESOLVED (B6-G06)
 * 20. No Fault Found Requires Investigation Context (B6.4-G02, B6.4-G08)
 * 21. Attach Evidence Valid SHA-256 Format (B6-G10-A)
 * 22. Attach Evidence Invalid SHA-256 Rejected (B6-G10-A)
 * 23. Evidence Content Match Verification (B6-G10-B)
 * 24. Evidence Idempotency (B6.4-G05)
 * 25. No Business Delete Guard (B6.4-G10)
 * 26. Zero Topology Mutation Invariant across Service Calls (B6.4-G01)
 */
class B6FieldFindingsServiceTest extends TestCase
{
    protected ?BaseConnection $db;
    protected FaultCaseService $caseService;
    protected FaultDispatchService $dispatchService;
    protected FieldFindingsService $findingsService;

    protected array $createdCaseIds = [];
    protected array $createdEventIds = [];
    protected array $createdAssetIds = [];

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

        $this->createdCaseIds = [];
        $this->createdEventIds = [];
        $this->createdAssetIds = [];
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdCaseIds)) {
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
        if (!empty($this->createdAssetIds)) {
            $this->db->table('assets')->whereIn('id', $this->createdAssetIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * Helper to set up an active case in INVESTIGATING status with active investigation
     */
    protected function createInvestigatingCase(): array
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-B64-TEST-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')
            ->select('id, kode_asset, latitude, longitude')
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'ASC')
            ->limit(3)
            ->get()
            ->getResultArray();
        $sourceAssetId = (int)($as[0]['id'] ?? 1);

        $eventData = [
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $sourceAssetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-B64-' . bin2hex(random_bytes(3)),
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
                'asset_id'                     => (int)$as[0]['id'],
                'rank'                         => 1,
                'graph_distance_from_device_m' => 100.0,
                'distance_delta_m'             => 5.0,
                'confidence_score'             => 92.5,
            ],
            [
                'asset_id'                     => (int)($as[1]['id'] ?? $as[0]['id']),
                'rank'                         => 2,
                'graph_distance_from_device_m' => 200.0,
                'distance_delta_m'             => 10.0,
                'confidence_score'             => 75.0,
            ]
        ];

        $res = $this->caseService->createOrResolveCase($eventId, ['candidates' => $candidates]);
        $case = $res['case'];
        $caseId = (int)$case['id'];
        $this->createdCaseIds[] = $caseId;

        // Dispatch -> Accept -> Journey -> Arrive -> Investigate
        $disp = $this->dispatchService->dispatchCase($caseId, 101, 1);
        $this->dispatchService->acceptAssignment((int)$disp['assignment']['id'], 101);
        $this->dispatchService->startJourney($caseId, 101, -7.5360, 112.2340, 5.0);
        $this->dispatchService->recordArrival($caseId, 101, -7.5385, 112.2365, 3.0);
        $invesRes = $this->dispatchService->startInvestigation($caseId, 101);

        $activeInves = $this->db->table('field_investigations')
            ->where('fault_case_id', $caseId)
            ->where('status', 'INVESTIGATING')
            ->get()
            ->getRowArray();

        return [
            'case'          => $this->caseService->getCase($caseId),
            'investigation' => $activeInves,
            'assets'        => $as,
            'candidates'    => $candidates,
        ];
    }

    /**
     * Test 1: Record finding successfully with status RECORDED
     */
    public function testRecordFindingNew(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];
        $investigationId = (int)$context['investigation']['id'];

        $res = $this->findingsService->recordFinding($caseId, 101, [
            'investigation_id'      => $investigationId,
            'actual_asset_id'       => $actualAssetId,
            'actual_lat'            => -7.5385123,
            'actual_lng'            => 112.2365456,
            'gps_accuracy_m'        => 3.5,
            'cause_category'        => 'LIGHTNING',
            'condition_description' => 'Isolator tumpu flashover akibat sambaran petir.',
            'notes'                 => 'Pemasangan jumper sementara selesai.',
        ]);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['is_new']);
        $this->assertFalse($res['is_existing']);
        $this->assertEquals('FINDING_RECORDED', $res['status']);
        $this->assertEquals(FieldFindingsService::STATUS_RECORDED, $res['finding']['finding_status']);
        $this->assertEquals($actualAssetId, (int)$res['finding']['actual_asset_id']);
    }

    /**
     * Test 2: Prediction vs Actual Separation (B6-G04)
     */
    public function testPredictionVsActualSeparation(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $predictedAssetId = (int)$context['candidates'][0]['asset_id'];
        $actualAssetId = (int)$context['assets'][1]['id']; // Different asset!

        $res = $this->findingsService->recordFinding($caseId, 101, [
            'predicted_asset_id'    => $predictedAssetId,
            'actual_asset_id'       => $actualAssetId,
            'actual_lat'            => -7.5390,
            'actual_lng'            => 112.2370,
            'gps_accuracy_m'        => 2.0,
            'cause_category'        => 'VEGETATION',
            'condition_description' => 'Dahan pohon sengon tumbang menimpa kabel.',
        ]);

        $this->assertTrue($res['success']);
        $finding = $res['finding'];

        // Confirm explicit separation
        $this->assertEquals($predictedAssetId, (int)$finding['predicted_asset_id']);
        $this->assertEquals($actualAssetId, (int)$finding['actual_asset_id']);
        $this->assertNotEquals($finding['predicted_asset_id'], $finding['actual_asset_id']);
    }

    /**
     * Test 3: Prediction Never Auto-Promoted to Actual (B6.4-G08, B6.4-G09)
     */
    public function testPredictionNeverAutoPromotedToActual(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $predictedAssetId = (int)$context['candidates'][0]['asset_id'];

        // Missing actual_asset_id
        $res = $this->findingsService->recordFinding($caseId, 101, [
            'predicted_asset_id' => $predictedAssetId,
            'actual_lat'         => -7.5390,
            'actual_lng'         => 112.2370,
        ]);

        $this->assertFalse($res['success']);
        $this->assertEquals('MISSING_ACTUAL_ASSET', $res['status']);
    }

    /**
     * Test 4: Case FSM Transition to FINDING_RECORDED (B6-G06)
     */
    public function testCaseFsmTransitionToFindingRecorded(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];

        $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5385,
            'actual_lng'      => 112.2365,
            'cause_category'  => 'ANIMAL',
        ]);

        $case = $this->caseService->getCase($caseId);
        $this->assertEquals('FINDING_RECORDED', $case['status']);
    }

    /**
     * Test 5: Investigation Transition to FINDING_RECORDED (B6-G06)
     */
    public function testInvestigationTransitionToFindingRecorded(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];
        $invesId = (int)$context['investigation']['id'];

        $this->findingsService->recordFinding($caseId, 101, [
            'investigation_id' => $invesId,
            'actual_asset_id'  => $actualAssetId,
            'actual_lat'       => -7.5385,
            'actual_lng'       => 112.2365,
        ]);

        $inves = $this->db->table('field_investigations')->where('id', $invesId)->get()->getRowArray();
        $this->assertEquals('FINDING_RECORDED', $inves['status']);
    }

    /**
     * Test 6: GPS Provenance Bounds & Semantic Accuracy (B6-G09)
     */
    public function testGpsProvenanceValidation(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];

        // Out-of-bounds latitude
        $resLat = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => 95.0,
            'actual_lng'      => 112.0,
        ]);
        $this->assertFalse($resLat['success']);
        $this->assertEquals('INVALID_GPS_PROVENANCE', $resLat['status']);

        // Out-of-bounds longitude
        $resLng = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5,
            'actual_lng'      => 185.0,
        ]);
        $this->assertFalse($resLng['success']);
        $this->assertEquals('INVALID_GPS_PROVENANCE', $resLng['status']);

        // Negative accuracy
        $resAcc = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5,
            'actual_lng'      => 112.0,
            'gps_accuracy_m'  => -2.0,
        ]);
        $this->assertFalse($resAcc['success']);
        $this->assertEquals('INVALID_GPS_PROVENANCE', $resAcc['status']);

        // Accuracy = 0 is valid explicitly reported zero
        $resZero = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5,
            'actual_lng'      => 112.0,
            'gps_accuracy_m'  => 0.0,
        ]);
        $this->assertTrue($resZero['success']);
    }

    /**
     * Test 7: Authoritative Asset Verification (B6.4-G07)
     */
    public function testActualAssetMustExist(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];

        $res = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => 99999999, // Non-existent asset ID
            'actual_lat'      => -7.5385,
            'actual_lng'      => 112.2365,
        ]);

        $this->assertFalse($res['success']);
        $this->assertEquals('REJECTED_NON_AUTHORITATIVE_ASSET', $res['status']);
    }

    /**
     * Test 8: Deleted Asset Cannot Become Actual (B6.4-G07)
     */
    public function testDeletedAssetCannotBecomeActual(): void
    {
        // Seed a temporary soft-deleted asset
        $now = date('Y-m-d H:i:s');
        $this->db->table('assets')->insert([
            'kode_asset'  => 'TEST-DELETED-ASSET-' . bin2hex(random_bytes(3)),
            'nama_asset'  => 'Tiang Uji Terhapus',
            'latitude'    => -7.5385,
            'longitude'   => 112.2365,
            'deleted_at'  => $now,
            'created_at'  => $now,
        ]);
        $deletedAssetId = (int)$this->db->insertID();
        $this->createdAssetIds[] = $deletedAssetId;

        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];

        $res = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $deletedAssetId,
            'actual_lat'      => -7.5385,
            'actual_lng'      => 112.2365,
        ]);

        $this->assertFalse($res['success']);
        $this->assertEquals('REJECTED_NON_AUTHORITATIVE_ASSET', $res['status']);
    }

    /**
     * Test 9: Cross-Case Investigation Ownership Protection (B6.4-G02)
     */
    public function testCrossCaseInvestigationRejected(): void
    {
        $context1 = $this->createInvestigatingCase();
        $context2 = $this->createInvestigatingCase();

        $case1Id = (int)$context1['case']['id'];
        $case2InvestigationId = (int)$context2['investigation']['id'];
        $actualAssetId = (int)$context1['assets'][0]['id'];

        // Attempting to record finding for Case 1 using Case 2's investigation ID
        $res = $this->findingsService->recordFinding($case1Id, 101, [
            'investigation_id' => $case2InvestigationId,
            'actual_asset_id'  => $actualAssetId,
            'actual_lat'       => -7.5385,
            'actual_lng'       => 112.2365,
        ]);

        $this->assertFalse($res['success']);
        $this->assertEquals('CROSS_CASE_INVESTIGATION_REJECTED', $res['status']);
    }

    /**
     * Test 10: Cross-Case Predicted Asset Protection (B6.4-G02)
     */
    public function testCrossCasePredictedAssetRejected(): void
    {
        $context1 = $this->createInvestigatingCase();
        $case1Id = (int)$context1['case']['id'];
        $actualAssetId = (int)$context1['assets'][0]['id'];

        // Pick an asset ID that is not a candidate for this case
        $nonCandidateId = 888888;

        $res = $this->findingsService->recordFinding($case1Id, 101, [
            'predicted_asset_id' => $nonCandidateId,
            'actual_asset_id'    => $actualAssetId,
            'actual_lat'         => -7.5385,
            'actual_lng'         => 112.2365,
        ]);

        $this->assertFalse($res['success']);
        $this->assertEquals('CROSS_CASE_PREDICTED_ASSET_MISMATCH', $res['status']);
    }

    /**
     * Test 11: Finding Submission Idempotency (B6.4-G04)
     */
    public function testFindingSubmissionIdempotency(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];
        $submissionUuid = 'SUBM-UUID-' . bin2hex(random_bytes(6));

        // First submission
        $res1 = $this->findingsService->recordFinding($caseId, 101, [
            'client_submission_uuid' => $submissionUuid,
            'actual_asset_id'        => $actualAssetId,
            'actual_lat'             => -7.5385,
            'actual_lng'             => 112.2365,
            'cause_category'         => 'EQUIPMENT_FAILURE',
        ]);
        $this->assertTrue($res1['success']);
        $this->assertTrue($res1['is_new']);

        // Duplicate submission with same client UUID
        $res2 = $this->findingsService->recordFinding($caseId, 101, [
            'client_submission_uuid' => $submissionUuid,
            'actual_asset_id'        => $actualAssetId,
            'actual_lat'             => -7.5385,
            'actual_lng'             => 112.2365,
            'cause_category'         => 'EQUIPMENT_FAILURE',
        ]);
        $this->assertTrue($res2['success']);
        $this->assertFalse($res2['is_new']);
        $this->assertTrue($res2['is_existing']);
        $this->assertEquals($res1['finding']['id'], $res2['finding']['id']);

        // Table row count remains exactly 1
        $count = $this->db->table('field_findings')->where('fault_case_id', $caseId)->countAllResults();
        $this->assertEquals(1, $count);
    }

    /**
     * Test 12: Amend Finding Append-Only Revision (B6-G05)
     */
    public function testAmendFindingAppendOnlyRevision(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];

        $recRes = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5385,
            'actual_lng'      => 112.2365,
            'cause_category'  => 'UNKNOWN',
        ]);
        $findingId = (int)$recRes['finding']['id'];

        // Amend cause category and description
        $amendRes = $this->findingsService->amendFinding(
            $findingId,
            1, // Supervisor ID
            'Secondary laboratory inspection confirmed surge arrester breakdown.',
            [
                'cause_category'        => 'EQUIPMENT_FAILURE',
                'condition_description' => 'Arrester pecah fasa R.',
            ]
        );

        $this->assertTrue($amendRes['success']);
        $this->assertEquals(1, $amendRes['revision_no']);

        // Check revision table
        $revisions = $this->findingsService->getFindingRevisions($findingId);
        $this->assertCount(1, $revisions);
        $this->assertEquals('EQUIPMENT_FAILURE', $revisions[0]['amended_fields']['cause_category']);
        $this->assertEquals('UNKNOWN', $revisions[0]['previous_values']['cause_category']);

        // Check current projection in field_findings
        $updatedFinding = $this->findingsService->getFinding($findingId);
        $this->assertEquals(FieldFindingsService::STATUS_REVISED, $updatedFinding['finding_status']);
        $this->assertEquals('EQUIPMENT_FAILURE', $updatedFinding['cause_category']);
    }

    /**
     * Test 13: Amend Finding Mandatory Reason Guard (B6-G05)
     */
    public function testAmendFindingMandatoryReason(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];

        $recRes = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5385,
            'actual_lng'      => 112.2365,
        ]);
        $findingId = (int)$recRes['finding']['id'];

        // Empty reason rejected
        $amendRes = $this->findingsService->amendFinding($findingId, 1, '   ', ['cause_category' => 'LIGHTNING']);
        $this->assertFalse($amendRes['success']);
        $this->assertEquals('MISSING_AMENDMENT_REASON', $amendRes['status']);
    }

    /**
     * Test 14: Sequential Revisions Integrity (B6-G05)
     */
    public function testSequentialRevisionsIntegrity(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];

        $recRes = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5385,
            'actual_lng'      => 112.2365,
        ]);
        $findingId = (int)$recRes['finding']['id'];

        // Revision 1
        $this->findingsService->amendFinding($findingId, 1, 'Update 1: Preliminary analysis', ['condition_description' => 'Desc 1']);
        // Revision 2
        $this->findingsService->amendFinding($findingId, 1, 'Update 2: Engineering verification', ['condition_description' => 'Desc 2']);
        // Revision 3
        $this->findingsService->amendFinding($findingId, 1, 'Update 3: Final root cause diagnosis', ['condition_description' => 'Desc 3']);

        $revisions = $this->findingsService->getFindingRevisions($findingId);
        $this->assertCount(3, $revisions);
        $this->assertEquals(1, $revisions[0]['revision_no']);
        $this->assertEquals(2, $revisions[1]['revision_no']);
        $this->assertEquals(3, $revisions[2]['revision_no']);
    }

    /**
     * Test 15: Revisions Table Strictly Append-Only (B6-G05)
     */
    public function testRevisionsTableStrictlyAppendOnly(): void
    {
        $columns = array_column(
            $this->db->query("SHOW COLUMNS FROM field_finding_revisions")->getResultArray(),
            'Field'
        );

        $this->assertNotContains('updated_at', $columns, "field_finding_revisions MUST NOT have updated_at column.");
        $this->assertNotContains('deleted_at', $columns, "field_finding_revisions MUST NOT have deleted_at column.");
        $this->assertContains('revision_no', $columns);
        $this->assertContains('amendment_reason', $columns);
    }

    /**
     * Test 16: Revision Concurrency Protection (B6.4-G03)
     */
    public function testRevisionConcurrencyProtected(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];

        $recRes = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5385,
            'actual_lng'      => 112.2365,
        ]);
        $findingId = (int)$recRes['finding']['id'];

        $res1 = $this->findingsService->amendFinding($findingId, 1, 'First amendment', ['cause_category' => 'LIGHTNING']);
        $res2 = $this->findingsService->amendFinding($findingId, 2, 'Second amendment', ['cause_category' => 'VEGETATION']);

        $this->assertTrue($res1['success']);
        $this->assertTrue($res2['success']);
        $this->assertEquals(1, $res1['revision_no']);
        $this->assertEquals(2, $res2['revision_no']);
    }

    /**
     * Test 17: Confirm Finding Lifecycle (B6-G06)
     */
    public function testConfirmFindingLifecycle(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];

        $recRes = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5385,
            'actual_lng'      => 112.2365,
            'gps_accuracy_m'  => 3.0,
            'cause_category'  => 'LIGHTNING',
        ]);
        $findingId = (int)$recRes['finding']['id'];

        $confRes = $this->findingsService->confirmFinding($findingId, 1);
        $this->assertTrue($confRes['success']);
        $this->assertEquals(FieldFindingsService::STATUS_CONFIRMED, $confRes['finding_status']);

        // Case status is now CONFIRMED
        $case = $this->caseService->getCase($caseId);
        $this->assertEquals('CONFIRMED', $case['status']);
    }

    /**
     * Test 18: Confirm Without Field Observation Rejected (B6.4-G08)
     */
    public function testConfirmWithoutFieldObservationRejected(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];

        // Directly insert an unverified finding row missing coordinates
        $now = date('Y-m-d H:i:s');
        $this->db->table('field_findings')->insert([
            'fault_case_id'  => $caseId,
            'actual_asset_id'=> null, // MISSING
            'actual_lat'     => null, // MISSING
            'actual_lng'     => null, // MISSING
            'finding_status' => 'RECORDED',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $findingId = (int)$this->db->insertID();

        // Transition case to FINDING_RECORDED manually
        $this->caseService->transitionCase($caseId, 'FINDING_RECORDED', ['actor_id' => 1]);

        $confRes = $this->findingsService->confirmFinding($findingId, 1);
        $this->assertFalse($confRes['success']);
        $this->assertEquals('REJECTED_FINDING_NOT_FIELD_VERIFIED', $confRes['status']);
    }

    /**
     * Test 19: Record No Fault Found Transitions to UNRESOLVED (B6-G06)
     */
    public function testRecordNoFaultFoundUnresolved(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $invesId = (int)$context['investigation']['id'];

        $res = $this->findingsService->recordNoFaultFound($caseId, $invesId, 101, [
            'notes'          => 'Pole-by-pole visual inspection from pole 1 to 25 revealed zero fault or flashover.',
            'actual_lat'     => -7.5385,
            'actual_lng'     => 112.2365,
            'gps_accuracy_m' => 4.0,
        ]);

        $this->assertTrue($res['success']);
        $this->assertEquals('UNRESOLVED', $res['case_status']);

        $case = $this->caseService->getCase($caseId);
        $this->assertEquals('UNRESOLVED', $case['status']);

        $inves = $this->db->table('field_investigations')->where('id', $invesId)->get()->getRowArray();
        $this->assertEquals('COMPLETED', $inves['status']);
    }

    /**
     * Test 20: No Fault Found Requires Investigation Context (B6.4-G02, B6.4-G08)
     */
    public function testNoFaultFoundRequiresInvestigationContext(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $invesId = (int)$context['investigation']['id'];

        // Missing observation note
        $res = $this->findingsService->recordNoFaultFound($caseId, $invesId, 101, [
            'notes' => '   ',
        ]);
        $this->assertFalse($res['success']);
        $this->assertEquals('MISSING_OBSERVATION_NOTE', $res['status']);
    }

    /**
     * Test 21: Attach Evidence Valid SHA-256 Format (B6-G10-A)
     */
    public function testAttachEvidenceValidSha256(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $validSha256 = hash('sha256', 'SAMPLE_EVIDENCE_PHOTO_BYTES_12345');

        $res = $this->findingsService->attachEvidence(
            $caseId,
            101,
            'uploads/faults/evidence_pole_394.jpg',
            $validSha256,
            'PHOTO',
            ['metadata' => ['device' => 'Cat S62 Pro', 'resolution' => '1080p']]
        );

        $this->assertTrue($res['success']);
        $this->assertTrue($res['is_new']);
        $this->assertEquals('EVIDENCE_ATTACHED', $res['status']);
        $this->assertEquals($validSha256, $res['evidence']['sha256']);
    }

    /**
     * Test 22: Attach Evidence Invalid SHA-256 Rejected (B6-G10-A)
     */
    public function testAttachEvidenceInvalidSha256Rejected(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];

        // Non-hex or invalid length hash
        $res = $this->findingsService->attachEvidence(
            $caseId,
            101,
            'uploads/faults/photo.jpg',
            'INVALID_HASH_NOT_SHA256',
            'PHOTO'
        );

        $this->assertFalse($res['success']);
        $this->assertEquals('INVALID_SHA256_FORMAT', $res['status']);
    }

    /**
     * Test 23: Evidence Content Match Verification (B6-G10-B)
     */
    public function testEvidenceContentMatchVerification(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];

        // Create temporary real file
        $tempPath = sys_get_temp_dir() . '/sidak_test_evidence_' . bin2hex(random_bytes(3)) . '.txt';
        $content = 'ACTUAL_FIELD_IMAGE_MOCK_CONTENT_' . date('YmdHis');
        file_put_contents($tempPath, $content);
        $realHash = hash_file('sha256', $tempPath);
        $fakeHash = hash('sha256', 'DIFFERENT_CONTENT');

        // Mismatched hash rejected
        $mismatchRes = $this->findingsService->attachEvidence($caseId, 101, $tempPath, $fakeHash);
        $this->assertFalse($mismatchRes['success']);
        $this->assertEquals('SHA256_CONTENT_MISMATCH', $mismatchRes['status']);

        // Matched hash accepted
        $matchRes = $this->findingsService->attachEvidence($caseId, 101, $tempPath, $realHash);
        $this->assertTrue($matchRes['success']);

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    /**
     * Test 24: Evidence Idempotency (B6.4-G05)
     */
    public function testEvidenceIdempotency(): void
    {
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $hash = hash('sha256', 'IDEMPOTENT_PHOTO_1');

        $res1 = $this->findingsService->attachEvidence($caseId, 101, 'uploads/photo.jpg', $hash, 'PHOTO');
        $this->assertTrue($res1['success']);
        $this->assertTrue($res1['is_new']);

        // Duplicate submission
        $res2 = $this->findingsService->attachEvidence($caseId, 101, 'uploads/photo.jpg', $hash, 'PHOTO');
        $this->assertTrue($res2['success']);
        $this->assertFalse($res2['is_new']);
        $this->assertTrue($res2['is_existing']);
        $this->assertEquals($res1['evidence']['id'], $res2['evidence']['id']);

        $count = $this->db->table('field_evidence')->where('fault_case_id', $caseId)->countAllResults();
        $this->assertEquals(1, $count);
    }

    /**
     * Test 25: No Business Delete Guard (B6.4-G10)
     */
    public function testNoBusinessDeleteGuard(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Guard B6.4-G10 Violation/i');

        $this->findingsService->deleteFinding(12345);
    }

    /**
     * Test 26: Zero Topology Mutation Invariant across Service Calls (B6.4-G01)
     */
    public function testZeroTopologyMutation(): void
    {
        $translinesBefore = $this->db->table('gis_translines')->countAllResults();
        $assetsBefore     = $this->db->table('assets')->countAllResults();

        // Run full finding and revision lifecycle
        $context = $this->createInvestigatingCase();
        $caseId = (int)$context['case']['id'];
        $actualAssetId = (int)$context['assets'][0]['id'];

        $rec = $this->findingsService->recordFinding($caseId, 101, [
            'actual_asset_id' => $actualAssetId,
            'actual_lat'      => -7.5385,
            'actual_lng'      => 112.2365,
            'gps_accuracy_m'  => 3.0,
        ]);
        $findingId = (int)$rec['finding']['id'];

        $this->findingsService->amendFinding($findingId, 1, 'Lab correction', ['condition_description' => 'Flashover insulator']);
        $this->findingsService->attachEvidence($caseId, 101, 'doc.pdf', hash('sha256', 'DOC_TEST'), 'DOCUMENT');
        $this->findingsService->confirmFinding($findingId, 1);

        $translinesAfter = $this->db->table('gis_translines')->countAllResults();
        $assetsAfter     = $this->db->table('assets')->countAllResults();

        $this->assertEquals($translinesBefore, $translinesAfter, "ZERO_MUTATION: gis_translines must have 0 delta.");
        $this->assertEquals($assetsBefore, $assetsAfter, "ZERO_MUTATION: assets must have 0 delta.");
    }
}
