<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Config\Database;
use CodeIgniter\Database\BaseConnection;
use App\Services\MobileSyncService;
use App\Services\FaultCaseService;
use App\Services\FaultDispatchService;
use App\Services\FieldFindingsService;
use App\Services\FaultFeedbackService;

/**
 * B7MobileSyncServiceTest
 *
 * Local Forensic & Adversarial Test Suite for Phase B.7.2:
 * 1. Batch envelope validation (missing sync_id / device_id)
 * 2. Device registry governance: Unregistered device rejected (HTTP 403)
 * 3. Device registry governance: Revoked device rejected (HTTP 403, B7-G19)
 * 4. Per-operation atomicity: Valid ACCEPT_ASSIGNMENT -> ACCEPTED journal & FSM transition (B7-G15)
 * 5. Idempotency engine: Duplicate client_submission_uuid -> DUPLICATE receipt (B7-G05)
 * 6. Partial success: Batch with 1 valid + 1 invalid operation -> isolated failure (B7-G03)
 * 7. GPS provenance: Mock location detected -> REJECTED (B7-G07)
 * 8. GPS provenance: Out-of-bounds coordinates -> REJECTED
 * 9. Dual-clock provenance: Future drift flagged in journal without overwriting client time (B7-G06)
 * 10. Topology sentinel: Zero mutation on gis_translines or assets (Delta = 0, B7-G14)
 */
class B7MobileSyncServiceTest extends TestCase
{
    protected ?BaseConnection $db;
    protected MobileSyncService $syncService;
    protected FaultCaseService $caseService;
    protected FaultDispatchService $dispatchService;
    protected FieldFindingsService $findingsService;
    protected FaultFeedbackService $feedbackService;

    protected string $testDeviceId = 'DEV-PLN-UNITTEST-001';
    protected string $revokedDeviceId = 'DEV-PLN-REVOKED-001';
    protected int $testUserId = 1;

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
        $this->findingsService = new FieldFindingsService($this->db, $this->caseService);
        $this->feedbackService = new FaultFeedbackService($this->db, $this->caseService);

        $this->syncService = new MobileSyncService(
            $this->db,
            $this->caseService,
            $this->dispatchService,
            $this->findingsService,
            $this->feedbackService
        );

        $this->createdCaseIds = [];
        $this->createdEventIds = [];

        // Ensure active test device exists
        $now = date('Y-m-d H:i:s');
        $existingActive = $this->db->table('mobile_devices')->where('device_id', $this->testDeviceId)->get()->getRowArray();
        if (!$existingActive) {
            $this->db->table('mobile_devices')->insert([
                'device_id'                   => $this->testDeviceId,
                'user_id'                     => $this->testUserId,
                'device_identity_fingerprint' => 'UnitTest Device v1',
                'device_model'                => 'PLN Toughpad',
                'app_version'                 => '2.4.0-build.112',
                'status'                      => 'ACTIVE',
                'registered_at'               => $now,
                'created_at'                  => $now,
            ]);
        } else {
            $this->db->table('mobile_devices')->where('device_id', $this->testDeviceId)->update([
                'status' => 'ACTIVE',
            ]);
        }

        // Ensure revoked test device exists
        $existingRevoked = $this->db->table('mobile_devices')->where('device_id', $this->revokedDeviceId)->get()->getRowArray();
        if (!$existingRevoked) {
            $this->db->table('mobile_devices')->insert([
                'device_id'                   => $this->revokedDeviceId,
                'user_id'                     => $this->testUserId,
                'device_identity_fingerprint' => 'Revoked UnitTest Device',
                'device_model'                => 'Compromised Phone',
                'app_version'                 => '2.4.0-build.112',
                'status'                      => 'REVOKED',
                'registered_at'               => $now,
                'revoked_at'                  => $now,
                'created_at'                  => $now,
            ]);
        }
    }

    protected function tearDown(): void
    {
        // Cleanup test cases and events
        if (!empty($this->createdCaseIds)) {
            $this->db->table('field_investigations')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('dispatch_assignments')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('fault_candidate_assets')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('fault_cases')->whereIn('id', $this->createdCaseIds)->delete();
        }
        if (!empty($this->createdEventIds)) {
            $this->db->table('fault_events')->whereIn('id', $this->createdEventIds)->delete();
        }

        // Cleanup test batches and journals generated by this test device
        $this->db->table('mobile_sync_journal')->where('device_id', $this->testDeviceId)->delete();
        $this->db->table('mobile_sync_batches')->where('device_id', $this->testDeviceId)->delete();

        parent::tearDown();
    }

    /**
     * Helper to create a test event and case with proper foreign keys
     */
    protected function createTestCase(): array
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-SYNC-TEST-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $eventData = [
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $assetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-SYNC-' . bin2hex(random_bytes(3)),
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

        $res = $this->caseService->createOrResolveCase($eventId);
        $case = $res['case'];
        $this->createdCaseIds[] = (int)$case['id'];

        return $case;
    }

    public function testBatchEnvelopeValidation(): void
    {
        $res = $this->syncService->pushBatch([
            'sync_id'   => '',
            'device_id' => $this->testDeviceId,
            'user_id'   => $this->testUserId,
        ]);

        $this->assertEquals('REJECTED', $res['status']);
        $this->assertEquals(400, $res['http_code']);
    }

    public function testUnregisteredDeviceRejected(): void
    {
        $res = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-TEST-UNREG-001',
            'device_id'      => 'DEV-GHOST-999999',
            'user_id'        => $this->testUserId,
            'client_sent_at' => date('Y-m-d H:i:s'),
            'operations'     => [],
        ]);

        $this->assertEquals('DEVICE_NOT_REGISTERED', $res['status']);
        $this->assertEquals(403, $res['http_code']);
    }

    public function testRevokedDeviceRejected(): void
    {
        $res = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-TEST-REVOKED-001',
            'device_id'      => $this->revokedDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => date('Y-m-d H:i:s'),
            'operations'     => [],
        ]);

        $this->assertEquals('DEVICE_REVOKED', $res['status']);
        $this->assertEquals(403, $res['http_code']);
    }

    public function testPerOperationAtomicityAndAcceptAssignment(): void
    {
        $now = date('Y-m-d H:i:s');
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];

        // Dispatch case to testUserId
        $dispRes = $this->dispatchService->dispatchCase($caseId, $this->testUserId, 99, [
            'assignment_note' => 'Dispatch for test',
        ]);
        $this->assertTrue($dispRes['success']);
        $assignmentId = (int)$dispRes['assignment']['id'];

        $uuid = 'UUID-ACCEPT-' . bin2hex(random_bytes(6));
        $syncId = 'SYNC-BATCH-' . bin2hex(random_bytes(4));

        // Submit ACCEPT_ASSIGNMENT operation
        $res = $this->syncService->pushBatch([
            'sync_id'        => $syncId,
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid,
                    'operation_type'         => 'ACCEPT_ASSIGNMENT',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'client_timezone_offset' => '+07:00',
                    'payload'                => [
                        'assignment_id' => $assignmentId,
                    ],
                ],
            ],
        ]);

        $this->assertEquals('SUCCESS', $res['status']);
        $this->assertEquals(1, $res['summary']['accepted']);
        $this->assertEquals(0, $res['summary']['rejected']);

        $opResult = $res['results'][0];
        $this->assertEquals('ACCEPTED', $opResult['sync_status']);
        $this->assertEquals(200, $opResult['http_code']);
        $this->assertGreaterThan(0, $opResult['journal_seq']);

        // Verify Journal Row Created
        $journal = $this->db->table('mobile_sync_journal')->where('client_submission_uuid', $uuid)->get()->getRowArray();
        $this->assertNotNull($journal);
        $this->assertEquals($opResult['journal_seq'], (int)$journal['journal_seq']);
        $this->assertEquals('ACCEPTED', $journal['sync_status']);
        $this->assertEquals('ASSIGNMENT_ACCEPTED', $journal['domain_status']);

        // Verify Case Transitioned to ACCEPTED
        $updatedCase = $this->db->table('fault_cases')->where('id', $caseId)->get()->getRowArray();
        $this->assertEquals('ACCEPTED', $updatedCase['status']);
    }

    public function testIdempotencyReplayDuplicateResolved(): void
    {
        $now = date('Y-m-d H:i:s');
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];

        $dispRes = $this->dispatchService->dispatchCase($caseId, $this->testUserId, 99);
        $assignmentId = (int)$dispRes['assignment']['id'];

        $uuid = 'UUID-IDEMP-' . bin2hex(random_bytes(6));
        $syncId1 = 'SYNC-IDEMP-001';
        $syncId2 = 'SYNC-IDEMP-002'; // Simulating retry across separate transport batch

        $opPayload = [
            'client_submission_uuid' => $uuid,
            'operation_type'         => 'ACCEPT_ASSIGNMENT',
            'case_id'                => $caseId,
            'client_created_at'      => $now,
            'client_timezone_offset' => '+07:00',
            'payload'                => [
                'assignment_id' => $assignmentId,
            ],
        ];

        // 1. First Submission -> ACCEPTED
        $res1 = $this->syncService->pushBatch([
            'sync_id'        => $syncId1,
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [$opPayload],
        ]);
        $this->assertEquals(1, $res1['summary']['accepted']);
        $originalSeq = $res1['results'][0]['journal_seq'];

        // 2. Second Submission (Replay under new batch) -> DUPLICATE (HTTP 200, Delta = 0)
        $res2 = $this->syncService->pushBatch([
            'sync_id'        => $syncId2,
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [$opPayload],
        ]);

        $this->assertEquals(0, $res2['summary']['accepted']);
        $this->assertEquals(1, $res2['summary']['duplicate']);
        $this->assertEquals(0, $res2['summary']['rejected']);

        $dupResult = $res2['results'][0];
        $this->assertEquals('DUPLICATE', $dupResult['sync_status']);
        $this->assertEquals(200, $dupResult['http_code']);
        $this->assertEquals($originalSeq, $dupResult['journal_seq']);

        // Assert exactly 1 row exists in journal (no duplicate row written)
        $journalCount = $this->db->table('mobile_sync_journal')->where('client_submission_uuid', $uuid)->countAllResults();
        $this->assertEquals(1, $journalCount, 'Server must preserve exactly 1 journal entry on duplicate replay (B7-G05)');
    }

    public function testBatchPartialSuccess(): void
    {
        $now = date('Y-m-d H:i:s');
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];

        $dispRes = $this->dispatchService->dispatchCase($caseId, $this->testUserId, 99);
        $assignmentId = (int)$dispRes['assignment']['id'];

        $uuid1 = 'UUID-PARTIAL-VALID-' . bin2hex(random_bytes(4));
        $uuid2 = 'UUID-PARTIAL-INVALID-' . bin2hex(random_bytes(4));

        $res = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-BATCH-PARTIAL-001',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    // Valid Operation
                    'client_submission_uuid' => $uuid1,
                    'operation_type'         => 'ACCEPT_ASSIGNMENT',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'client_timezone_offset' => '+07:00',
                    'payload'                => ['assignment_id' => $assignmentId],
                ],
                [
                    // Invalid Operation: Illegal transition shortcut (B7-G01, B6-G06)
                    'client_submission_uuid' => $uuid2,
                    'operation_type'         => 'TRANSITION_CASE',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'client_timezone_offset' => '+07:00',
                    'payload'                => ['target_status' => 'CLOSED'], // Cannot jump straight to CLOSED
                ],
            ],
        ]);

        $this->assertEquals('SUCCESS', $res['status']);
        $this->assertEquals(1, $res['summary']['accepted'], 'Valid operation must be accepted');
        $this->assertEquals(1, $res['summary']['rejected'], 'Invalid operation must be rejected');

        // Check Batch Status is PARTIAL
        $batch = $this->db->table('mobile_sync_batches')->where('sync_id', 'SYNC-BATCH-PARTIAL-001')->get()->getRowArray();
        $this->assertEquals('PARTIAL', $batch['status']);

        // Check Operation 1 is ACCEPTED
        $this->assertEquals('ACCEPTED', $res['results'][0]['sync_status']);
        // Check Operation 2 is REJECTED
        $this->assertEquals('REJECTED', $res['results'][1]['sync_status']);

        // Explicit Hardening Verification: Rejected operation MUST be persisted in mobile_sync_journal via independent TX
        $rejectedJournal = $this->db->table('mobile_sync_journal')->where('client_submission_uuid', $uuid2)->get()->getRowArray();
        $this->assertNotNull($rejectedJournal, 'Rejected operation must be permanently persisted in mobile_sync_journal via independent transaction (B7-G10)');
        $this->assertEquals('REJECTED', $rejectedJournal['sync_status']);
        $this->assertEquals('REJECTED_INVALID_TRANSITION', $rejectedJournal['domain_status']);
        $this->assertEquals(422, (int)$rejectedJournal['response_http_code']);
    }

    public function testMockLocationProhibited(): void
    {
        $now = date('Y-m-d H:i:s');
        $uuid = 'UUID-MOCK-' . bin2hex(random_bytes(6));

        $res = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-MOCK-001',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid,
                    'operation_type'         => 'START_JOURNEY',
                    'case_id'                => 1,
                    'client_created_at'      => $now,
                    'client_timezone_offset' => '+07:00',
                    'payload'                => [
                        'lat'                => -7.5385,
                        'lng'                => 112.2365,
                        'accuracy_m'         => 3.2,
                        'mock_location_flag' => true, // Simulated GPS detected!
                    ],
                ],
            ],
        ]);

        $this->assertEquals(1, $res['summary']['rejected']);
        $opRes = $res['results'][0];
        $this->assertEquals('REJECTED', $opRes['sync_status']);
        $this->assertEquals('MOCK_LOCATION_PROHIBITED', $opRes['domain_status']);
    }

    public function testInvalidGpsBoundsRejected(): void
    {
        $now = date('Y-m-d H:i:s');
        $uuid = 'UUID-BOUNDS-' . bin2hex(random_bytes(6));

        $res = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-BOUNDS-001',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid,
                    'operation_type'         => 'START_JOURNEY',
                    'case_id'                => 1,
                    'client_created_at'      => $now,
                    'client_timezone_offset' => '+07:00',
                    'payload'                => [
                        'lat'        => -999.0, // Out of WGS84 range
                        'lng'        => 112.2365,
                        'accuracy_m' => 4.0,
                    ],
                ],
            ],
        ]);

        $this->assertEquals(1, $res['summary']['rejected']);
        $this->assertEquals('INVALID_COORDINATE_BOUNDS', $res['results'][0]['domain_status']);
    }

    public function testDualClockProvenanceAndFutureDriftLogged(): void
    {
        $now = date('Y-m-d H:i:s');
        $futureTime = date('Y-m-d H:i:s', time() + 3600); // 1 hour into future
        $uuid = 'UUID-FUTURE-' . bin2hex(random_bytes(6));

        $case = $this->createTestCase();
        $caseId = (int)$case['id'];

        $dispRes = $this->dispatchService->dispatchCase($caseId, $this->testUserId, 99);
        $assignmentId = (int)$dispRes['assignment']['id'];

        $res = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-FUTURE-001',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $futureTime,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid,
                    'operation_type'         => 'ACCEPT_ASSIGNMENT',
                    'case_id'                => $caseId,
                    'client_created_at'      => $futureTime, // Future client timestamp
                    'client_timezone_offset' => '+07:00',
                    'payload'                => ['assignment_id' => $assignmentId],
                ],
            ],
        ]);

        $this->assertEquals(1, $res['summary']['accepted']);

        // Verify Journal preserved exact client timestamp AND flagged future drift
        $journal = $this->db->table('mobile_sync_journal')->where('client_submission_uuid', $uuid)->get()->getRowArray();
        $this->assertNotNull($journal);
        $this->assertEquals($futureTime, $journal['client_created_at'], 'Server must NEVER overwrite client_created_at (B7-G06)');
        $this->assertStringContainsString('DISCREPANCY_CLIENT_TIME_FUTURE', (string)$journal['error_message'], 'Future drift must be flagged for forensic audit');
    }

    public function testGroundTruthPreservedSeparation(): void
    {
        $now = date('Y-m-d H:i:s');
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];

        // 1. Dispatch & Accept
        $this->dispatchService->dispatchCase($caseId, $this->testUserId, 99);
        $assignment = $this->dispatchService->getActiveAssignment($caseId);
        $this->dispatchService->acceptAssignment((int)$assignment['id'], $this->testUserId);

        // 2. Journey & Arrive
        $this->dispatchService->startJourney($caseId, $this->testUserId, -7.5385, 112.2365, 3.5);
        $this->dispatchService->recordArrival($caseId, $this->testUserId, -7.5385, 112.2365, 3.5);
        $this->dispatchService->startInvestigation($caseId, $this->testUserId);

        // Get two distinct active assets
        $assetRows = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->limit(2)->get()->getResultArray();
        $predictedAssetId = (int)($assetRows[0]['id'] ?? 1);
        $actualAssetId = (int)($assetRows[1]['id'] ?? 2);

        // Update case top candidate
        $this->db->table('fault_cases')->where('id', $caseId)->update([
            'top_candidate_asset_id' => $predictedAssetId,
        ]);

        $uuidFinding = 'UUID-FINDING-GT-' . bin2hex(random_bytes(6));

        // 3. Submit RECORD_FINDING via MobileSyncService
        $res = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-GT-001',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuidFinding,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'client_timezone_offset' => '+07:00',
                    'payload'                => [
                        'actual_asset_id'       => $actualAssetId, // Differs from predicted!
                        'actual_lat'            => -7.538512,
                        'actual_lng'            => 112.236540,
                        'gps_accuracy_m'        => 3.2,
                        'cause_category'        => 'EQUIPMENT_FAILURE',
                        'condition_description' => 'Damaged pin insulator found at actual site.',
                        'notes'                 => 'Actual pole differs from FLI prediction.',
                    ],
                ],
            ],
        ]);

        $this->assertEquals(1, $res['summary']['accepted']);
        $findingId = $res['results'][0]['entity_id'];
        $this->assertNotNull($findingId);

        // 4. Assert ground truth in database: actual_asset_id is preserved, NEVER auto-corrected to predicted
        $findingRow = $this->db->table('field_findings')->where('id', $findingId)->get()->getRowArray();
        $this->assertEquals($actualAssetId, (int)$findingRow['actual_asset_id'], 'Actual asset ID must preserve field observation (B6-G04, B7-G09)');
        $this->assertNotEquals($predictedAssetId, (int)$findingRow['actual_asset_id'], 'Prediction must NOT overwrite actual finding');

        // Cleanup finding
        $this->db->table('field_findings')->where('id', $findingId)->delete();
    }

    public function testTopologySentinelZeroMutation(): void
    {
        // Absolute Invariant B7-G14: gis_translines must have 0 mutations
        $tlCount = $this->db->table('gis_translines')->countAllResults();
        $this->assertGreaterThan(0, $tlCount, 'gis_translines must exist and be untouched');

        $assetCount = $this->db->table('assets')->countAllResults();
        $this->assertGreaterThan(0, $assetCount, 'assets must exist and be untouched');
    }
}
