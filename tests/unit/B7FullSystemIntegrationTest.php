<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Config\Database;
use CodeIgniter\Database\BaseConnection;
use App\Services\FaultCaseService;
use App\Services\FaultDispatchService;
use App\Services\FieldFindingsService;
use App\Services\FaultFeedbackService;
use App\Services\MobileSyncService;
use App\Services\MobileEvidenceUploadService;
use App\Services\MobileSyncPullService;

/**
 * B7FullSystemIntegrationTest
 *
 * Phase B.7.5: Full System Integration & Adversarial Suite
 *
 * END-TO-END VERIFICATION MATRIX:
 * 1. Offline complete lifecycle -> Reconnect -> Domain state consistency
 * 2. Push batch partial success (isolated per-operation atomic transactions)
 * 3. Idempotent replay of full batch & individual client_submission_uuids
 * 4. Evidence chunk out-of-order arrival, duplicate deduplication, and hash conflict rejection
 * 5. Mid-upload disconnection, incomplete sealing rejection, and resume on reconnect
 * 6. Full-file SHA-256 checksum mismatch aborts sealing & purges staging
 * 7. Device revocation prior to reconnect blocks Push, Upload, and Pull
 * 8. FSM transition conflict rejection with independent audit journaling
 * 9. Ground truth separation: Finding asset differs from FLI prediction
 * 10. Pull after push with concurrent journal append resilience
 * 11. User scoping isolation (User A never sees User B records)
 * 12. Exclusion of REJECTED operations from downstream delta stream
 * 13. Zero topology mutation throughout E2E pipeline (Delta = 0)
 * 14. Deterministic full replay
 * 15. E2E Correlation ID chain: SYNC -> OP -> DOMAIN -> EVIDENCE -> JOURNAL -> PULL
 */
class B7FullSystemIntegrationTest extends TestCase
{
    protected ?BaseConnection $db;
    protected FaultCaseService $caseService;
    protected FaultDispatchService $dispatchService;
    protected FieldFindingsService $findingsService;
    protected FaultFeedbackService $feedbackService;
    protected MobileSyncService $syncService;
    protected MobileEvidenceUploadService $uploadService;
    protected MobileSyncPullService $pullService;

    protected string $testDeviceId = 'DEV-PLN-E2E-001';
    protected string $revokedDeviceId = 'DEV-PLN-E2E-REVOKED';
    protected int $testUserId = 1;
    protected int $secondaryUserId = 99;

    protected array $createdCaseIds = [];
    protected array $createdEventIds = [];
    protected array $createdEvidenceIds = [];
    protected array $createdJournalSeqs = [];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->db = Database::connect('default');
            $this->db->getVersion();
        } catch (\Throwable $e) {
            $this->db = Database::connect();
        }

        // Initialize full B.6 domain suite
        $this->caseService = new FaultCaseService($this->db);
        $this->dispatchService = new FaultDispatchService($this->db, $this->caseService);
        $this->findingsService = new FieldFindingsService($this->db, $this->caseService);
        $this->feedbackService = new FaultFeedbackService($this->db, $this->caseService);

        // Initialize full B.7 sync suite
        $this->syncService = new MobileSyncService(
            $this->db,
            $this->caseService,
            $this->dispatchService,
            $this->findingsService,
            $this->feedbackService
        );

        $this->uploadService = new MobileEvidenceUploadService(
            $this->db,
            $this->findingsService,
            $this->caseService
        );

        $this->pullService = new MobileSyncPullService(
            $this->db,
            $this->caseService
        );

        $this->createdCaseIds = [];
        $this->createdEventIds = [];
        $this->createdEvidenceIds = [];
        $this->createdJournalSeqs = [];

        $now = date('Y-m-d H:i:s');

        // Ensure active test device exists
        $existingActive = $this->db->table('mobile_devices')
            ->where('device_id', $this->testDeviceId)
            ->get()
            ->getRowArray();

        if (!$existingActive) {
            $this->db->table('mobile_devices')->insert([
                'device_id'                   => $this->testDeviceId,
                'user_id'                     => $this->testUserId,
                'device_identity_fingerprint' => 'E2E Integration Toughpad',
                'device_model'                => 'PLN Toughpad 5G Pro',
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
        $existingRevoked = $this->db->table('mobile_devices')
            ->where('device_id', $this->revokedDeviceId)
            ->get()
            ->getRowArray();

        if (!$existingRevoked) {
            $this->db->table('mobile_devices')->insert([
                'device_id'                   => $this->revokedDeviceId,
                'user_id'                     => $this->testUserId,
                'device_identity_fingerprint' => 'Compromised Test Device',
                'device_model'                => 'Compromised Phone',
                'app_version'                 => '2.4.0-build.112',
                'status'                      => 'REVOKED',
                'registered_at'               => $now,
                'revoked_at'                  => $now,
                'created_at'                  => $now,
            ]);
        } else {
            $this->db->table('mobile_devices')->where('device_id', $this->revokedDeviceId)->update([
                'status' => 'REVOKED',
            ]);
        }
    }

    protected function tearDown(): void
    {
        // Cleanup domain entities
        if (!empty($this->createdCaseIds)) {
            $findingRows = $this->db->table('field_findings')
                ->select('id')
                ->whereIn('fault_case_id', $this->createdCaseIds)
                ->get()
                ->getResultArray();
            $findingIdList = !empty($findingRows) ? array_column($findingRows, 'id') : [];

            if (!empty($findingIdList)) {
                $this->db->table('field_finding_revisions')->whereIn('field_finding_id', $findingIdList)->delete();
            }
            $this->db->table('field_evidence')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('field_findings')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('field_investigations')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('dispatch_assignments')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('fault_candidate_assets')->whereIn('fault_case_id', $this->createdCaseIds)->delete();
            $this->db->table('fault_cases')->whereIn('id', $this->createdCaseIds)->delete();
        }

        if (!empty($this->createdEventIds)) {
            $this->db->table('fault_events')->whereIn('id', $this->createdEventIds)->delete();
        }

        // Cleanup evidence upload staging and chunks
        if (!empty($this->createdEvidenceIds)) {
            $this->db->table('mobile_evidence_chunks')->whereIn('evidence_id', $this->createdEvidenceIds)->delete();
            foreach ($this->createdEvidenceIds as $evId) {
                $stagingDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'evidence_staging' . DIRECTORY_SEPARATOR . $evId;
                if (is_dir($stagingDir)) {
                    $files = glob($stagingDir . DIRECTORY_SEPARATOR . '*');
                    foreach ($files as $f) {
                        @unlink($f);
                    }
                    @rmdir($stagingDir);
                }
            }
        }

        // Cleanup test journal entries and batches
        if (!empty($this->createdJournalSeqs)) {
            $this->db->table('mobile_sync_journal')->whereIn('journal_seq', $this->createdJournalSeqs)->delete();
        }
        $this->db->table('mobile_sync_journal')->where('device_id', $this->testDeviceId)->delete();
        $this->db->table('mobile_sync_batches')->where('device_id', $this->testDeviceId)->delete();

        parent::tearDown();
    }

    /**
     * Helper to create a fully valid test case with candidate assets.
     */
    protected function createTestCase(): array
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-E2E-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $eventData = [
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $assetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-E2E-' . bin2hex(random_bytes(3)),
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

        $resCase = $this->caseService->createOrResolveCase($eventId);
        $case = $resCase['case'];
        $caseId = (int)$case['id'];
        $this->createdCaseIds[] = $caseId;

        return $case;
    }

    /**
     * Helper to create a case transitioned through FSM to INVESTIGATING status.
     */
    protected function createInvestigatingCase(): array
    {
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];

        // FSM Progression: DISPATCHED -> ACCEPTED -> EN_ROUTE -> ARRIVED -> INVESTIGATING
        $this->dispatchService->dispatchCase($caseId, $this->testUserId, $this->testUserId);
        $this->caseService->transitionCase($caseId, 'ACCEPTED', ['actor_id' => $this->testUserId]);
        $this->caseService->transitionCase($caseId, 'EN_ROUTE', ['actor_id' => $this->testUserId]);
        $this->caseService->transitionCase($caseId, 'ARRIVED', ['actor_id' => $this->testUserId]);
        $this->caseService->transitionCase($caseId, 'INVESTIGATING', ['actor_id' => $this->testUserId]);

        return $this->caseService->getCase($caseId);
    }

    /**
     * Helper to upload a single test chunk with all 10 arguments properly formed.
     */
    protected function uploadTestChunk(
        string $evidenceId,
        int $chunkIndex,
        int $totalChunks,
        string $data,
        string $targetSha,
        ?string $deviceId = null,
        ?int $userId = null,
        ?string $uuid = null
    ): array {
        return $this->uploadService->uploadChunk(
            $evidenceId,
            $chunkIndex,
            $totalChunks,
            $data,
            hash('sha256', $data),
            strlen($data),
            $targetSha,
            $uuid ?? ('UUID-CH-' . $chunkIndex . '-' . bin2hex(random_bytes(3))),
            $deviceId ?? $this->testDeviceId,
            $userId ?? $this->testUserId
        );
    }

    /**
     * Test 1: Complete Offline Inspection Lifecycle, Reconnect, and E2E Correlation ID Chain.
     * Validates the end-to-end trace:
     * SYNC -> OPERATION -> DOMAIN ENTITY -> EVIDENCE -> JOURNAL -> PULL DELTA
     */
    public function testE2EOfflineCompleteLifecycleAndCorrelationChain(): void
    {
        $now = date('Y-m-d H:i:s');
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];

        // 1. Dispatch case to testUserId
        $dispatchRes = $this->dispatchService->dispatchCase($caseId, $this->testUserId, $this->testUserId, [
            'assignment_note' => 'E2E offline inspection dispatch',
        ]);
        $this->assertTrue($dispatchRes['success']);
        $assignmentId = $dispatchRes['assignment']['id'];

        // 2. Mobile queues 5 operations offline with dedicated Correlation IDs
        $syncId = 'SYNC-E2E-FLOW-001';
        $corrAccept = 'corr-uuid-accept-' . bin2hex(random_bytes(4));
        $corrJourney = 'corr-uuid-journey-' . bin2hex(random_bytes(4));
        $corrArrival = 'corr-uuid-arrival-' . bin2hex(random_bytes(4));
        $corrInves = 'corr-uuid-inves-' . bin2hex(random_bytes(4));
        $corrFinding = 'corr-uuid-finding-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $offlineOperations = [
            [
                'client_submission_uuid' => $corrAccept,
                'operation_type'         => 'ACCEPT_ASSIGNMENT',
                'case_id'                => $caseId,
                'client_created_at'      => $now,
                'payload'                => [
                    'assignment_id' => $assignmentId,
                    'note'          => 'Accepted on mobile device',
                ],
            ],
            [
                'client_submission_uuid' => $corrJourney,
                'operation_type'         => 'START_JOURNEY',
                'case_id'                => $caseId,
                'client_created_at'      => $now,
                'payload'                => [
                    'lat'        => -7.123450,
                    'lng'        => 110.123450,
                    'accuracy_m' => 8.5,
                ],
            ],
            [
                'client_submission_uuid' => $corrArrival,
                'operation_type'         => 'RECORD_ARRIVAL',
                'case_id'                => $caseId,
                'client_created_at'      => $now,
                'payload'                => [
                    'lat'        => -7.123460,
                    'lng'        => 110.123460,
                    'accuracy_m' => 6.2,
                ],
            ],
            [
                'client_submission_uuid' => $corrInves,
                'operation_type'         => 'START_INVESTIGATION',
                'case_id'                => $caseId,
                'client_created_at'      => $now,
                'payload'                => [
                    'notes' => 'Commencing physical inspection on pole',
                ],
            ],
            [
                'client_submission_uuid' => $corrFinding,
                'operation_type'         => 'RECORD_FINDING',
                'case_id'                => $caseId,
                'client_created_at'      => $now,
                'payload'                => [
                    'predicted_asset_id'    => $assetId,
                    'actual_asset_id'       => $assetId,
                    'cause_category'        => 'LIGHTNING',
                    'condition_description' => 'Direct flashover observed on crossarm',
                    'actual_lat'            => -7.123465,
                    'actual_lng'            => 110.123465,
                    'gps_accuracy_m'        => 4.0,
                    'notes'                 => 'Recorded offline in field',
                ],
            ],
        ];

        // 3. Reconnect & Ingest Push Batch (B.7.2)
        $batchPayload = [
            'sync_id'        => $syncId,
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => $offlineOperations,
        ];

        $pushRes = $this->syncService->pushBatch($batchPayload);
        $this->assertEquals(200, $pushRes['http_code']);
        $this->assertEquals(5, $pushRes['summary']['accepted']);
        $this->assertEquals(0, $pushRes['summary']['rejected']);

        // 4. Capture created finding ID
        $findingResult = array_filter($pushRes['results'], fn($r) => $r['client_submission_uuid'] === $corrFinding);
        $this->assertNotEmpty($findingResult);
        $findingItem = reset($findingResult);
        $createdFindingId = (int)$findingItem['entity_id'];
        $this->assertGreaterThan(0, $createdFindingId);

        // 5. Upload Multi-Chunk Evidence for this finding (B.7.3)
        $evidenceId = 'EVID-E2E-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;
        $corrEvidence = 'corr-uuid-evidence-' . bin2hex(random_bytes(4));

        $chunk0Data = 'JPG_HEADER_CHUNK_0_CONTENT_' . str_repeat('A', 500);
        $chunk1Data = 'JPG_DATA_CHUNK_1_CONTENT_END_' . str_repeat('B', 500);
        $fullContent = $chunk0Data . $chunk1Data;
        $fullSha256 = hash('sha256', $fullContent);

        $initRes = $this->uploadService->initiateUpload(
            $evidenceId,
            $corrEvidence,
            $caseId,
            2,
            strlen($fullContent),
            $fullSha256,
            $this->testDeviceId,
            $this->testUserId
        );
        $this->assertEquals(200, $initRes['http_code']);

        $up0 = $this->uploadTestChunk($evidenceId, 0, 2, $chunk0Data, $fullSha256);
        $this->assertEquals(200, $up0['http_code']);

        $up1 = $this->uploadTestChunk($evidenceId, 1, 2, $chunk1Data, $fullSha256);
        $this->assertEquals(200, $up1['http_code']);

        // Assemble & Seal with Correlation ID
        $sealRes = $this->uploadService->assembleAndSealEvidence($evidenceId, $caseId, $this->testUserId, $this->testDeviceId, [
            'field_finding_id'       => $createdFindingId,
            'asset_id'               => $assetId,
            'correlation_id'         => $corrEvidence,
            'client_submission_uuid' => $corrEvidence,
            'sync_id'                => $syncId,
            'metadata'               => ['captured_offline' => true],
        ]);
        $this->assertEquals(200, $sealRes['http_code']);
        $this->assertEquals('SEALED', $sealRes['status']);
        $createdFieldEvidenceId = (int)$sealRes['field_evidence_id'];
        $this->assertGreaterThan(0, $createdFieldEvidenceId);

        // 6. Mobile performs Downstream Delta Pull (B.7.4)
        $pullRes = $this->pullService->pullDelta(0, 50, $this->testDeviceId, $this->testUserId);
        $this->assertEquals(200, $pullRes['http_code']);
        $this->assertGreaterThanOrEqual(6, $pullRes['count']); // 5 push ops + 1 evidence op

        // =====================================================================
        // 7. VERIFY THE ENTIRE E2E CORRELATION ID CHAIN
        // =====================================================================
        // Trace finding correlation
        $journalFinding = $this->db->table('mobile_sync_journal')
            ->where('client_submission_uuid', $corrFinding)
            ->get()
            ->getRowArray();
        $this->assertNotNull($journalFinding, 'Correlation ID must exist in journal for finding');
        $this->assertEquals('ACCEPTED', $journalFinding['sync_status']);
        $this->assertEquals('field_finding', $journalFinding['entity_type']);
        $this->assertEquals($createdFindingId, (int)$journalFinding['entity_id']);

        // Trace evidence correlation
        $journalEvidence = $this->db->table('mobile_sync_journal')
            ->where('client_submission_uuid', $corrEvidence)
            ->get()
            ->getRowArray();
        $this->assertNotNull($journalEvidence, 'Correlation ID must exist in journal for evidence');
        $this->assertEquals('ACCEPTED', $journalEvidence['sync_status']);
        $this->assertEquals('field_evidence', $journalEvidence['entity_type']);
        $this->assertEquals($createdFieldEvidenceId, (int)$journalEvidence['entity_id']);

        // Trace evidence metadata
        $evidenceRow = $this->db->table('field_evidence')->where('id', $createdFieldEvidenceId)->get()->getRowArray();
        $this->assertNotNull($evidenceRow);
        $evidenceMeta = json_decode($evidenceRow['metadata_json'], true);
        $this->assertEquals($corrEvidence, $evidenceMeta['correlation_id']);
        $this->assertEquals($fullSha256, $evidenceRow['sha256']);

        // Verify mobile reconstruction from delta pull
        $hydratedFindings = $pullRes['deltas']['findings'];
        $reconstructedFinding = array_filter($hydratedFindings, fn($f) => (int)$f['id'] === $createdFindingId);
        $this->assertNotEmpty($reconstructedFinding);

        $hydratedEvidence = $pullRes['deltas']['evidence'];
        $reconstructedEvidence = array_filter($hydratedEvidence, fn($e) => (int)$e['id'] === $createdFieldEvidenceId);
        $this->assertNotEmpty($reconstructedEvidence);
    }

    /**
     * Test 2: Push Batch with Partial Success (Atomicity Isolation per Operation).
     * 1 valid operation and 1 invalid operation in the same batch.
     */
    public function testPushBatchPartialSuccessIsolation(): void
    {
        $now = date('Y-m-d H:i:s');
        $case = $this->createInvestigatingCase();
        $caseId = (int)$case['id'];

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $uuidValid = 'part-valid-' . bin2hex(random_bytes(4));
        $uuidInvalid = 'part-invalid-mock-' . bin2hex(random_bytes(4));

        $batchPayload = [
            'sync_id'        => 'SYNC-PARTIAL-001',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuidValid,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $assetId,
                        'actual_asset_id'       => $assetId,
                        'cause_category'        => 'OTHER',
                        'condition_description' => 'Valid partial batch finding',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'gps_accuracy_m'        => 5.0,
                    ],
                ],
                [
                    'client_submission_uuid' => $uuidInvalid,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $assetId,
                        'actual_asset_id'       => $assetId,
                        'cause_category'        => 'OTHER',
                        'condition_description' => 'Mock GPS attempt',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'mock_location_flag'    => true, // VIOLATION
                    ],
                ],
            ],
        ];

        $res = $this->syncService->pushBatch($batchPayload);

        $this->assertEquals(207, $res['http_code']);
        $this->assertEquals('SUCCESS', $res['status']);
        $this->assertEquals(1, $res['summary']['accepted']);
        $this->assertEquals(1, $res['summary']['rejected']);

        // Check journal for valid op
        $jValid = $this->db->table('mobile_sync_journal')->where('client_submission_uuid', $uuidValid)->get()->getRowArray();
        $this->assertNotNull($jValid);
        $this->assertEquals('ACCEPTED', $jValid['sync_status']);

        // Check journal for rejected op (logged via independent transaction)
        $jInvalid = $this->db->table('mobile_sync_journal')->where('client_submission_uuid', $uuidInvalid)->get()->getRowArray();
        $this->assertNotNull($jInvalid);
        $this->assertEquals('REJECTED', $jInvalid['sync_status']);
        $this->assertEquals(422, (int)$jInvalid['response_http_code']);
    }

    /**
     * Test 3: Idempotent Replay of Full Batch & Individual client_submission_uuids.
     */
    public function testBatchReplayAndPerUuidIdempotency(): void
    {
        $now = date('Y-m-d H:i:s');
        $case = $this->createInvestigatingCase();
        $caseId = (int)$case['id'];

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $uuid = 'replay-test-' . bin2hex(random_bytes(4));

        $batchPayload = [
            'sync_id'        => 'SYNC-REPLAY-001',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $assetId,
                        'actual_asset_id'       => $assetId,
                        'cause_category'        => 'VEGETATION',
                        'condition_description' => 'Branch touching line',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'gps_accuracy_m'        => 3.0,
                    ],
                ],
            ],
        ];

        // First Push: Ingestion succeeds
        $res1 = $this->syncService->pushBatch($batchPayload);
        $this->assertEquals(200, $res1['http_code']);
        $this->assertEquals('ACCEPTED', $res1['results'][0]['sync_status']);
        $firstSeq = $res1['results'][0]['journal_seq'];

        $findingsCountBefore = (int)$this->db->table('field_findings')->countAllResults();

        // Second Push (Full Replay): Resolved idempotently from cached receipt
        $res2 = $this->syncService->pushBatch($batchPayload);
        $this->assertEquals(200, $res2['http_code']);
        $this->assertEquals('DUPLICATE', $res2['results'][0]['sync_status']);
        $this->assertEquals($firstSeq, $res2['results'][0]['journal_seq']);

        // Assert 0 duplicate domain rows created
        $findingsCountAfter = (int)$this->db->table('field_findings')->countAllResults();
        $this->assertEquals($findingsCountBefore, $findingsCountAfter, 'Zero extra domain rows on replay');
    }

    /**
     * Test 4: Evidence Chunk Out-of-Order, Duplicate, and Conflicting Hash.
     */
    public function testEvidenceChunkOutOrderDuplicateAndConflict(): void
    {
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];
        $evidenceId = 'EVID-ORDER-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $chunk0Data = 'CHUNK_0_BYTES';
        $chunk1Data = 'CHUNK_1_BYTES';
        $fullData = $chunk0Data . $chunk1Data;
        $targetSha = hash('sha256', $fullData);

        $this->uploadService->initiateUpload(
            $evidenceId,
            'UUID-' . bin2hex(random_bytes(4)),
            $caseId,
            2,
            strlen($fullData),
            $targetSha,
            $this->testDeviceId,
            $this->testUserId
        );

        // 1. Out-of-order: Upload Chunk 1 first
        $up1 = $this->uploadTestChunk($evidenceId, 1, 2, $chunk1Data, $targetSha);
        $this->assertEquals(200, $up1['http_code']);

        // 2. Duplicate chunk with identical hash -> HTTP 200 EXISTING
        $up1Dup = $this->uploadTestChunk($evidenceId, 1, 2, $chunk1Data, $targetSha);
        $this->assertEquals(200, $up1Dup['http_code']);
        $this->assertEquals('EXISTING', $up1Dup['status']);

        // 3. Duplicate chunk with conflicting hash -> HTTP 409 CHUNK_CONFLICT
        $conflictData = 'CONFLICTING_BYTES';
        $upConflict = $this->uploadService->uploadChunk(
            $evidenceId,
            1,
            2,
            $conflictData,
            hash('sha256', $conflictData),
            strlen($conflictData),
            $targetSha,
            'UUID-CONF',
            $this->testDeviceId,
            $this->testUserId
        );
        $this->assertEquals(409, $upConflict['http_code']);
        $this->assertEquals('CHUNK_CONFLICT', $upConflict['status']);

        // 4. Complete upload with Chunk 0
        $up0 = $this->uploadTestChunk($evidenceId, 0, 2, $chunk0Data, $targetSha);
        $this->assertEquals(200, $up0['http_code']);

        // 5. Seal succeeds deterministically
        $seal = $this->uploadService->assembleAndSealEvidence($evidenceId, $caseId, $this->testUserId, $this->testDeviceId);
        $this->assertEquals(200, $seal['http_code']);
        $this->assertEquals('SEALED', $seal['status']);
    }

    /**
     * Test 5: Disconnect Mid-Upload & Resume on Reconnect.
     */
    public function testDisconnectMidUploadAndResumeReconnect(): void
    {
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];
        $evidenceId = 'EVID-DISCONN-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $c0 = 'CHUNK_0';
        $c1 = 'CHUNK_1';
        $c2 = 'CHUNK_2';
        $fullData = $c0 . $c1 . $c2;
        $targetSha = hash('sha256', $fullData);

        $this->uploadService->initiateUpload(
            $evidenceId,
            'UUID-' . bin2hex(random_bytes(4)),
            $caseId,
            3,
            strlen($fullData),
            $targetSha,
            $this->testDeviceId,
            $this->testUserId
        );

        // Upload chunk 0 and 1
        $this->uploadTestChunk($evidenceId, 0, 3, $c0, $targetSha);
        $this->uploadTestChunk($evidenceId, 1, 3, $c1, $targetSha);

        // DISCONNECTION HAPPENS: Premature seal attempt rejected
        $prematureSeal = $this->uploadService->assembleAndSealEvidence($evidenceId, $caseId, $this->testUserId, $this->testDeviceId);
        $this->assertEquals(422, $prematureSeal['http_code']);
        $this->assertEquals('CHUNKS_INCOMPLETE', $prematureSeal['status']);

        // RECONNECT HAPPENS: Mobile uploads missing chunk 2
        $up2 = $this->uploadTestChunk($evidenceId, 2, 3, $c2, $targetSha);
        $this->assertEquals(200, $up2['http_code']);

        // Now seal succeeds
        $resumedSeal = $this->uploadService->assembleAndSealEvidence($evidenceId, $caseId, $this->testUserId, $this->testDeviceId);
        $this->assertEquals(200, $resumedSeal['http_code']);
        $this->assertEquals('SEALED', $resumedSeal['status']);
    }

    /**
     * Test 6: Full-file SHA-256 Checksum Mismatch Aborts Sealing & Purges Staging.
     */
    public function testEvidenceSha256MismatchAbortsSealing(): void
    {
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];
        $evidenceId = 'EVID-TAMPER-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $validChunkData = 'GENUINE_CHUNK_DATA';
        $validSha = hash('sha256', $validChunkData);

        $this->uploadService->initiateUpload(
            $evidenceId,
            'UUID-' . bin2hex(random_bytes(4)),
            $caseId,
            1,
            strlen($validChunkData),
            $validSha,
            $this->testDeviceId,
            $this->testUserId
        );

        $fraudulentSha256 = hash('sha256', 'CORRUPTED_OR_TAMPERED_CONTENT');

        $this->uploadService->uploadChunk(
            $evidenceId,
            0,
            1,
            $validChunkData,
            hash('sha256', $validChunkData),
            strlen($validChunkData),
            $fraudulentSha256,
            'UUID-TAMPER',
            $this->testDeviceId,
            $this->testUserId
        );

        $seal = $this->uploadService->assembleAndSealEvidence($evidenceId, $caseId, $this->testUserId, $this->testDeviceId);

        $this->assertEquals(422, $seal['http_code']);
        $this->assertEquals(MobileEvidenceUploadService::STATUS_HASH_MISMATCH, $seal['status']);

        // Zero rows in field_evidence
        $evCount = (int)$this->db->table('field_evidence')->where('fault_case_id', $caseId)->countAllResults();
        $this->assertEquals(0, $evCount);
    }

    /**
     * Test 7: Device Revocation Prior to Reconnect Blocks Push, Upload, and Pull.
     */
    public function testDeviceRevocationPriorToReconnect(): void
    {
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];

        // 1. Push rejected
        $push = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-REVOKED-001',
            'device_id'      => $this->revokedDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => date('Y-m-d H:i:s'),
            'operations'     => [],
        ]);
        $this->assertEquals(403, $push['http_code']);
        $this->assertEquals('DEVICE_REVOKED', $push['status']);

        // 2. Upload rejected
        $upload = $this->uploadService->initiateUpload(
            'EVID-REVOKED',
            'UUID-' . bin2hex(random_bytes(4)),
            $caseId,
            1,
            100,
            hash('sha256', 'dummy'),
            $this->revokedDeviceId,
            $this->testUserId
        );
        $this->assertEquals(403, $upload['http_code']);
        $this->assertEquals('DEVICE_REVOKED', $upload['status']);

        // 3. Pull rejected
        $pull = $this->pullService->pullDelta(0, 50, $this->revokedDeviceId, $this->testUserId);
        $this->assertEquals(403, $pull['http_code']);
        $this->assertEquals('DEVICE_REVOKED', $pull['status']);
    }

    /**
     * Test 8: FSM Transition Conflict Rejection with Independent Audit Journaling.
     */
    public function testFsmTransitionConflictRejection(): void
    {
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];
        $now = date('Y-m-d H:i:s');

        // Case is currently DISPATCHED (or OPEN). Attempt illegal direct jump to CLOSED without findings
        $uuidConflict = 'fsm-conflict-' . bin2hex(random_bytes(4));

        $res = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-FSM-CONFLICT',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuidConflict,
                    'operation_type'         => 'TRANSITION_CASE',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'target_status' => 'CLOSED',
                    ],
                ],
            ],
        ]);

        $this->assertEquals(422, $res['results'][0]['http_code']);
        $this->assertEquals('REJECTED', $res['results'][0]['sync_status']);

        // Assert rejection permanently recorded in journal
        $journalRow = $this->db->table('mobile_sync_journal')->where('client_submission_uuid', $uuidConflict)->get()->getRowArray();
        $this->assertNotNull($journalRow);
        $this->assertEquals('REJECTED', $journalRow['sync_status']);
    }

    /**
     * Test 9: Ground Truth Separation — Actual Finding Differs from FLI Prediction.
     */
    public function testFindingDifferentFromFliPrediction(): void
    {
        $case = $this->createInvestigatingCase();
        $caseId = (int)$case['id'];
        $now = date('Y-m-d H:i:s');

        // Get two distinct assets
        $assets = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->limit(2)->get()->getResultArray();
        $predAssetId = (int)$assets[0]['id'];
        $actualAssetId = (int)($assets[1]['id'] ?? $assets[0]['id']);

        $uuid = 'fli-diff-' . bin2hex(random_bytes(4));

        $res = $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-FLI-DIFF',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $predAssetId,
                        'actual_asset_id'       => $actualAssetId,
                        'cause_category'        => 'LIGHTNING',
                        'condition_description' => 'Damage discovered on adjacent asset',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'gps_accuracy_m'        => 3.0,
                    ],
                ],
            ],
        ]);

        $this->assertEquals(200, $res['http_code']);
        $findingId = $res['results'][0]['entity_id'];

        $findingRow = $this->db->table('field_findings')->where('id', $findingId)->get()->getRowArray();
        $this->assertNotNull($findingRow);
        $this->assertEquals($predAssetId, (int)$findingRow['predicted_asset_id']);
        $this->assertEquals($actualAssetId, (int)$findingRow['actual_asset_id']);
    }

    /**
     * Test 10: Pull After Push and Concurrent Append Resilience.
     */
    public function testPullAfterPushAndConcurrentAppend(): void
    {
        // Case 1 for initial push
        $case1 = $this->createInvestigatingCase();
        $case1Id = (int)$case1['id'];
        $now = date('Y-m-d H:i:s');

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $uuid1 = 'conc-1-' . bin2hex(random_bytes(4));
        $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-CONC-1',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid1,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $case1Id,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $assetId,
                        'actual_asset_id'       => $assetId,
                        'cause_category'        => 'OTHER',
                        'condition_description' => 'Concurrent test finding 1',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'gps_accuracy_m'        => 3.0,
                    ],
                ],
            ],
        ]);

        // Pull Page 1
        $pull1 = $this->pullService->pullDelta(0, 50, $this->testDeviceId, $this->testUserId);
        $this->assertEquals(200, $pull1['http_code']);
        $nextCursor1 = $pull1['next_cursor'];

        // Case 2 for concurrent append on server
        $case2 = $this->createInvestigatingCase();
        $case2Id = (int)$case2['id'];

        $uuid2 = 'conc-2-' . bin2hex(random_bytes(4));
        $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-CONC-2',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid2,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $case2Id,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $assetId,
                        'actual_asset_id'       => $assetId,
                        'cause_category'        => 'OTHER',
                        'condition_description' => 'Concurrent test finding 2',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'gps_accuracy_m'        => 3.0,
                    ],
                ],
            ],
        ]);

        // Pull Page 2 with cursor = nextCursor1
        $pull2 = $this->pullService->pullDelta($nextCursor1, 50, $this->testDeviceId, $this->testUserId);
        $this->assertEquals(200, $pull2['http_code']);
        $this->assertGreaterThanOrEqual(1, $pull2['count']);

        $pull2Uuids = array_column($pull2['deltas']['journal_records'], 'client_submission_uuid');
        $this->assertContains($uuid2, $pull2Uuids);
        $this->assertNotContains($uuid1, $pull2Uuids, 'Page 2 must not repeat uuid1');
    }

    /**
     * Test 11: User Scoping Isolation (User A Cannot See User B Records).
     */
    public function testUserScopingIsolation(): void
    {
        $case1 = $this->createInvestigatingCase();
        $case1Id = (int)$case1['id'];
        $now = date('Y-m-d H:i:s');

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $uuidUser1 = 'user1-' . bin2hex(random_bytes(4));
        $uuidUser2 = 'user2-' . bin2hex(random_bytes(4));

        // Operation by User 1
        $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-USER1',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuidUser1,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $case1Id,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $assetId,
                        'actual_asset_id'       => $assetId,
                        'cause_category'        => 'OTHER',
                        'condition_description' => 'User 1 finding',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'gps_accuracy_m'        => 3.0,
                    ],
                ],
            ],
        ]);

        // Operation by User 2 on Case 2
        $case2 = $this->createTestCase();
        $case2Id = (int)$case2['id'];
        $this->dispatchService->dispatchCase($case2Id, $this->secondaryUserId, $this->testUserId);
        $this->caseService->transitionCase($case2Id, 'ACCEPTED', ['actor_id' => $this->secondaryUserId]);
        $this->caseService->transitionCase($case2Id, 'EN_ROUTE', ['actor_id' => $this->secondaryUserId]);
        $this->caseService->transitionCase($case2Id, 'ARRIVED', ['actor_id' => $this->secondaryUserId]);
        $this->caseService->transitionCase($case2Id, 'INVESTIGATING', ['actor_id' => $this->secondaryUserId]);

        $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-USER2',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->secondaryUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuidUser2,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $case2Id,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $assetId,
                        'actual_asset_id'       => $assetId,
                        'cause_category'        => 'OTHER',
                        'condition_description' => 'User 2 finding',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'gps_accuracy_m'        => 3.0,
                    ],
                ],
            ],
        ]);

        // Pull scoped to User 1
        $pullUser1 = $this->pullService->pullDelta(0, 50, $this->testDeviceId, $this->testUserId, [
            'user_scope_only' => true,
        ]);
        $returnedUuids = array_column($pullUser1['deltas']['journal_records'], 'client_submission_uuid');

        $this->assertContains($uuidUser1, $returnedUuids);
        $this->assertNotContains($uuidUser2, $returnedUuids, 'User 1 must NEVER receive User 2 delta');
    }

    /**
     * Test 12: REJECTED Operations Excluded from Downstream Delta Stream.
     */
    public function testRejectedOperationsExcludedFromDelta(): void
    {
        $case = $this->createInvestigatingCase();
        $caseId = (int)$case['id'];
        $now = date('Y-m-d H:i:s');

        $uuidRejected = 'rejected-delta-' . bin2hex(random_bytes(4));

        // Submit invalid operation that gets REJECTED
        $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-REJECT-STREAM',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuidRejected,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'actual_lat'         => -7.123456,
                        'actual_lng'         => 110.123456,
                        'mock_location_flag' => true, // Triggers rejection
                    ],
                ],
            ],
        ]);

        // Verify it is in journal as REJECTED
        $jRow = $this->db->table('mobile_sync_journal')->where('client_submission_uuid', $uuidRejected)->get()->getRowArray();
        $this->assertNotNull($jRow);
        $this->assertEquals('REJECTED', $jRow['sync_status']);

        // Pull delta
        $pull = $this->pullService->pullDelta(0, 50, $this->testDeviceId, $this->testUserId);
        $pulledUuids = array_column($pull['deltas']['journal_records'], 'client_submission_uuid');

        $this->assertNotContains($uuidRejected, $pulledUuids, 'REJECTED operation must NEVER be delivered downstream');
    }

    /**
     * Test 13: Zero Topology Mutation Throughout E2E (Delta = 0).
     */
    public function testZeroTopologyMutationThroughoutE2E(): void
    {
        $activeTlBefore = (int)$this->db->table('gis_translines')->where('is_active', 1)->where('deleted_at IS NULL')->countAllResults();
        $totalTlBefore = (int)$this->db->table('gis_translines')->countAllResults();
        $activeAssetsBefore = (int)$this->db->table('assets')->where('deleted_at IS NULL')->countAllResults();
        $totalAssetsBefore = (int)$this->db->table('assets')->countAllResults();

        // Perform E2E cycle
        $case = $this->createInvestigatingCase();
        $caseId = (int)$case['id'];
        $now = date('Y-m-d H:i:s');

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $uuid = 'topo-guard-' . bin2hex(random_bytes(4));
        $this->syncService->pushBatch([
            'sync_id'        => 'SYNC-TOPO-GUARD',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $assetId,
                        'actual_asset_id'       => $assetId,
                        'cause_category'        => 'OTHER',
                        'condition_description' => 'Topology invariant check',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'gps_accuracy_m'        => 3.0,
                    ],
                ],
            ],
        ]);

        $this->pullService->pullDelta(0, 50, $this->testDeviceId, $this->testUserId, ['include_topology' => true]);

        $activeTlAfter = (int)$this->db->table('gis_translines')->where('is_active', 1)->where('deleted_at IS NULL')->countAllResults();
        $totalTlAfter = (int)$this->db->table('gis_translines')->countAllResults();
        $activeAssetsAfter = (int)$this->db->table('assets')->where('deleted_at IS NULL')->countAllResults();
        $totalAssetsAfter = (int)$this->db->table('assets')->countAllResults();

        $this->assertEquals($activeTlBefore, $activeTlAfter, 'Active translines must not mutate');
        $this->assertEquals($totalTlBefore, $totalTlAfter, 'Total translines must not mutate');
        $this->assertEquals($activeAssetsBefore, $activeAssetsAfter, 'Active assets must not mutate');
        $this->assertEquals($totalAssetsBefore, $totalAssetsAfter, 'Total assets must not mutate');
    }

    /**
     * Test 14: Deterministic Full Replay.
     */
    public function testDeterministicFullReplay(): void
    {
        $case = $this->createInvestigatingCase();
        $caseId = (int)$case['id'];
        $now = date('Y-m-d H:i:s');

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $uuid = 'determ-replay-' . bin2hex(random_bytes(4));
        $batch = [
            'sync_id'        => 'SYNC-DETERM-001',
            'device_id'      => $this->testDeviceId,
            'user_id'        => $this->testUserId,
            'client_sent_at' => $now,
            'operations'     => [
                [
                    'client_submission_uuid' => $uuid,
                    'operation_type'         => 'RECORD_FINDING',
                    'case_id'                => $caseId,
                    'client_created_at'      => $now,
                    'payload'                => [
                        'predicted_asset_id'    => $assetId,
                        'actual_asset_id'       => $assetId,
                        'cause_category'        => 'OTHER',
                        'condition_description' => 'Deterministic execution',
                        'actual_lat'            => -7.123456,
                        'actual_lng'            => 110.123456,
                        'gps_accuracy_m'        => 3.0,
                    ],
                ],
            ],
        ];

        $run1 = $this->syncService->pushBatch($batch);
        $this->assertEquals(200, $run1['http_code']);
        $this->assertEquals('ACCEPTED', $run1['results'][0]['sync_status']);
        $firstEntityId = $run1['results'][0]['entity_id'];

        $run2 = $this->syncService->pushBatch($batch);
        $this->assertEquals(200, $run2['http_code']);
        $this->assertEquals('DUPLICATE', $run2['results'][0]['sync_status']);
        $this->assertEquals($firstEntityId, $run2['results'][0]['entity_id']);
    }
}
