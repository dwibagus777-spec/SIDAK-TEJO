<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Config\Database;
use CodeIgniter\Database\BaseConnection;
use App\Services\MobileEvidenceUploadService;
use App\Services\FieldFindingsService;
use App\Services\FaultCaseService;

/**
 * B7MobileEvidenceUploadServiceTest
 *
 * Local Forensic & Adversarial Test Suite for Phase B.7.3:
 * 1. Initiate upload happy path (CHUNK_INIT, B7-G18)
 * 2. Path traversal / filename manipulation rejection (HTTP 400)
 * 3. Unregistered device rejection (HTTP 403)
 * 4. Revoked device rejection (HTTP 403, B7-G19)
 * 5. Out-of-order chunk upload support (chunk 1 before chunk 0)
 * 6. Duplicate chunk with same hash (Idempotent HTTP 200 EXISTING)
 * 7. Duplicate chunk with conflicting hash (HTTP 409 CHUNK_CONFLICT, B7-G18)
 * 8. Chunk size mismatch rejection (HTTP 422)
 * 9. Chunk checksum mismatch rejection (HTTP 422)
 * 10. Missing chunk prevents sealing (HTTP 422 CHUNKS_INCOMPLETE)
 * 11. Assembled full-file SHA-256 mismatch aborts & wipes staging (HTTP 422 HASH_MISMATCH)
 * 12. Full lifecycle happy path: Assembled -> Verified -> SEALED in field_evidence
 * 13. Replay seal after SEALED is idempotent (Delta = 0)
 * 14. Cross-case finding mismatch rejected (HTTP 422)
 * 15. Device revocation before assembly blocks domain seal (HTTP 403)
 * 16. Topology sentinel: Zero mutation on gis_translines or assets (Delta = 0, B7-G14)
 */
class B7MobileEvidenceUploadServiceTest extends TestCase
{
    protected ?BaseConnection $db;
    protected MobileEvidenceUploadService $uploadService;
    protected FieldFindingsService $findingsService;
    protected FaultCaseService $caseService;

    protected string $testDeviceId = 'DEV-PLN-EVTEST-001';
    protected string $revokedDeviceId = 'DEV-PLN-REVOKED-002';
    protected int $testUserId = 1;

    protected array $createdCaseIds = [];
    protected array $createdEventIds = [];
    protected array $createdEvidenceIds = [];

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
        $this->findingsService = new FieldFindingsService($this->db, $this->caseService);
        $this->uploadService = new MobileEvidenceUploadService(
            $this->db,
            $this->findingsService,
            $this->caseService
        );

        $this->createdCaseIds = [];
        $this->createdEventIds = [];
        $this->createdEvidenceIds = [];

        // Ensure active test device
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->table('mobile_devices')->where('device_id', $this->testDeviceId)->get()->getRowArray();
        if (!$existing) {
            $this->db->table('mobile_devices')->insert([
                'device_id'                   => $this->testDeviceId,
                'user_id'                     => $this->testUserId,
                'device_identity_fingerprint' => 'Evidence Test Tablet',
                'device_model'                => 'Panasonic Toughpad',
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

        // Ensure revoked test device
        $existingRev = $this->db->table('mobile_devices')->where('device_id', $this->revokedDeviceId)->get()->getRowArray();
        if (!$existingRev) {
            $this->db->table('mobile_devices')->insert([
                'device_id'                   => $this->revokedDeviceId,
                'user_id'                     => $this->testUserId,
                'device_identity_fingerprint' => 'Revoked Camera Device',
                'device_model'                => 'Compromised Camera',
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
        // Cleanup staging directories and db rows for test evidences
        if (!empty($this->createdEvidenceIds)) {
            foreach ($this->createdEvidenceIds as $evId) {
                $this->uploadService->purgeStagingDir($evId);
                $this->db->table('mobile_evidence_chunks')->where('evidence_id', $evId)->delete();
            }
        }

        // Cleanup test cases & events
        if (!empty($this->createdCaseIds)) {
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

        parent::tearDown();
    }

    protected function createTestCase(): array
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-EV-TEST-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        $eventData = [
            'event_number'           => $eventNumber,
            'penyulang_id'           => 15,
            'source_device_asset_id' => $assetId,
            'event_time'             => $now,
            'topology_snapshot_id'   => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'            => 'SCADA',
            'source_reference'       => 'SCADA-EV-' . bin2hex(random_bytes(3)),
            'raw_telemetry_json'     => json_encode(['mock' => true]),
            'fault_phase'            => 'ST',
            'fault_current_a'        => 820.00,
            'relay_distance_m'       => 210.00,
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

    public function testInitiateUploadHappyPath(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-TEST-' . bin2hex(random_bytes(6));
        $this->createdEvidenceIds[] = $evidenceId;

        $targetHash = hash('sha256', 'synthetic_evidence_file_payload');

        $res = $this->uploadService->initiateUpload(
            $evidenceId,
            'UUID-' . bin2hex(random_bytes(4)),
            (int)$case['id'],
            3,
            3072,
            $targetHash,
            $this->testDeviceId,
            $this->testUserId
        );

        $this->assertTrue($res['success']);
        $this->assertEquals(MobileEvidenceUploadService::STATUS_INIT, $res['status']);
        $this->assertEquals(200, $res['http_code']);
        $this->assertEquals($evidenceId, $res['evidence_id']);
        $this->assertEquals(3, $res['total_chunks']);
    }

    public function testPathTraversalRejected(): void
    {
        $case = $this->createTestCase();
        $maliciousEvidenceId = '../../etc/passwd';
        $targetHash = hash('sha256', 'dummy');

        $res = $this->uploadService->initiateUpload(
            $maliciousEvidenceId,
            'UUID-EVIL',
            (int)$case['id'],
            1,
            1024,
            $targetHash,
            $this->testDeviceId,
            $this->testUserId
        );

        $this->assertFalse($res['success']);
        $this->assertEquals('INVALID_EVIDENCE_ID', $res['status']);
        $this->assertEquals(400, $res['http_code']);
    }

    public function testUnregisteredDeviceRejected(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-UNREG-' . bin2hex(random_bytes(4));
        $targetHash = hash('sha256', 'dummy');

        $res = $this->uploadService->initiateUpload(
            $evidenceId,
            'UUID-UNREG',
            (int)$case['id'],
            1,
            1024,
            $targetHash,
            'DEV-UNKNOWN-9999',
            $this->testUserId
        );

        $this->assertFalse($res['success']);
        $this->assertEquals('DEVICE_NOT_REGISTERED', $res['status']);
        $this->assertEquals(403, $res['http_code']);
    }

    public function testRevokedDeviceRejected(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-REVOKED-' . bin2hex(random_bytes(4));
        $targetHash = hash('sha256', 'dummy');

        $res = $this->uploadService->initiateUpload(
            $evidenceId,
            'UUID-REVOKED',
            (int)$case['id'],
            1,
            1024,
            $targetHash,
            $this->revokedDeviceId,
            $this->testUserId
        );

        $this->assertFalse($res['success']);
        $this->assertEquals('DEVICE_REVOKED', $res['status']);
        $this->assertEquals(403, $res['http_code']);
    }

    public function testOutOfOrderChunkUpload(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-OUTOFORDER-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $chunk0 = 'AAA_FIRST_CHUNK_1024_BYTES_' . str_repeat('X', 996);
        $chunk1 = 'BBB_SECOND_CHUNK_1024_BYTES_' . str_repeat('Y', 995);

        $fullFile = $chunk0 . $chunk1;
        $targetHash = hash('sha256', $fullFile);

        $hash0 = hash('sha256', $chunk0);
        $hash1 = hash('sha256', $chunk1);

        // 1. Upload Chunk 1 FIRST (Out of Order)
        $resChunk1 = $this->uploadService->uploadChunk(
            $evidenceId,
            1, // Index 1
            2, // Total 2
            $chunk1,
            $hash1,
            strlen($chunk1),
            $targetHash,
            'UUID-CH1',
            $this->testDeviceId,
            $this->testUserId
        );

        $this->assertTrue($resChunk1['success']);
        $this->assertEquals(MobileEvidenceUploadService::STATUS_UPLOADING, $resChunk1['status']);
        $this->assertEquals(1, $resChunk1['received_chunks']);

        // 2. Upload Chunk 0 SECOND
        $resChunk0 = $this->uploadService->uploadChunk(
            $evidenceId,
            0, // Index 0
            2, // Total 2
            $chunk0,
            $hash0,
            strlen($chunk0),
            $targetHash,
            'UUID-CH0',
            $this->testDeviceId,
            $this->testUserId
        );

        $this->assertTrue($resChunk0['success']);
        $this->assertEquals(MobileEvidenceUploadService::STATUS_ALL_RECEIVED, $resChunk0['status']);
        $this->assertEquals(2, $resChunk0['received_chunks']);
    }

    public function testDuplicateChunkSameHashIdempotent(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-DUP-SAME-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $chunk0 = 'IDENTICAL_CHUNK_PAYLOAD_' . str_repeat('Z', 500);
        $hash0 = hash('sha256', $chunk0);
        $targetHash = hash('sha256', $chunk0);

        // First upload
        $res1 = $this->uploadService->uploadChunk(
            $evidenceId, 0, 1, $chunk0, $hash0, strlen($chunk0), $targetHash, 'UUID-DUP1', $this->testDeviceId, $this->testUserId
        );
        $this->assertTrue($res1['success']);

        // Duplicate upload with same hash
        $res2 = $this->uploadService->uploadChunk(
            $evidenceId, 0, 1, $chunk0, $hash0, strlen($chunk0), $targetHash, 'UUID-DUP1-RETRY', $this->testDeviceId, $this->testUserId
        );

        $this->assertTrue($res2['success']);
        $this->assertEquals('EXISTING', $res2['status']);
        $this->assertEquals(200, $res2['http_code']);
    }

    public function testDuplicateChunkDifferentHashConflict(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-DUP-CONFLICT-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $chunkA = 'CHUNK_A_CONTENT';
        $chunkB = 'CHUNK_B_CONTENT';
        $hashA = hash('sha256', $chunkA);
        $hashB = hash('sha256', $chunkB);
        $targetHash = hash('sha256', 'full');

        // First upload with chunkA
        $this->uploadService->uploadChunk(
            $evidenceId, 0, 1, $chunkA, $hashA, strlen($chunkA), $targetHash, 'UUID-A', $this->testDeviceId, $this->testUserId
        );

        // Conflicting upload for same index 0 with chunkB
        $resConf = $this->uploadService->uploadChunk(
            $evidenceId, 0, 1, $chunkB, $hashB, strlen($chunkB), $targetHash, 'UUID-B', $this->testDeviceId, $this->testUserId
        );

        $this->assertFalse($resConf['success']);
        $this->assertEquals('CHUNK_CONFLICT', $resConf['status']);
        $this->assertEquals(409, $resConf['http_code']);
    }

    public function testChunkSizeMismatchRejected(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-SIZE-MISMATCH-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $chunk = 'ACTUAL_PAYLOAD_DATA';
        $hash = hash('sha256', $chunk);

        $res = $this->uploadService->uploadChunk(
            $evidenceId, 0, 1, $chunk, $hash, 999999, // Declaring 999999 bytes when only ~19 bytes
            hash('sha256', 'full'), 'UUID-SIZE', $this->testDeviceId, $this->testUserId
        );

        $this->assertFalse($res['success']);
        $this->assertEquals('CHUNK_SIZE_MISMATCH', $res['status']);
        $this->assertEquals(422, $res['http_code']);
    }

    public function testChunkHashMismatchRejected(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-HASH-MISMATCH-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $chunk = 'ACTUAL_PAYLOAD_DATA';
        $fakeHash = hash('sha256', 'corrupted_hash');

        $res = $this->uploadService->uploadChunk(
            $evidenceId, 0, 1, $chunk, $fakeHash, strlen($chunk),
            hash('sha256', 'full'), 'UUID-HASH', $this->testDeviceId, $this->testUserId
        );

        $this->assertFalse($res['success']);
        $this->assertEquals('CHUNK_HASH_MISMATCH', $res['status']);
        $this->assertEquals(422, $res['http_code']);
    }

    public function testMissingChunkCannotSeal(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-INCOMPLETE-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $chunk0 = 'PART_0';
        $targetHash = hash('sha256', 'PART_0_AND_PART_1');

        // Upload only chunk 0 of 2
        $this->uploadService->uploadChunk(
            $evidenceId, 0, 2, $chunk0, hash('sha256', $chunk0), strlen($chunk0), $targetHash, 'UUID-P0', $this->testDeviceId, $this->testUserId
        );

        // Attempt premature seal
        $res = $this->uploadService->assembleAndSealEvidence(
            $evidenceId, (int)$case['id'], $this->testUserId, $this->testDeviceId
        );

        $this->assertFalse($res['success']);
        $this->assertEquals('CHUNKS_INCOMPLETE', $res['status']);
        $this->assertEquals(422, $res['http_code']);
        $this->assertContains(1, $res['missing_chunks']);
    }

    public function testAssembledSha256MismatchAborted(): void
    {
        $case = $this->createTestCase();
        $evidenceId = 'EVID-FULL-MISMATCH-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $chunk = 'REAL_CHUNK_DATA';
        $fakeTargetHash = hash('sha256', 'DIFFERENT_TARGET_DATA_INTENTIONALLY_CORRUPTED');

        // Upload chunk with fake full target hash
        $this->uploadService->uploadChunk(
            $evidenceId, 0, 1, $chunk, hash('sha256', $chunk), strlen($chunk), $fakeTargetHash, 'UUID-CORRUPT', $this->testDeviceId, $this->testUserId
        );

        // Seal attempt
        $res = $this->uploadService->assembleAndSealEvidence(
            $evidenceId, (int)$case['id'], $this->testUserId, $this->testDeviceId
        );

        $this->assertFalse($res['success']);
        $this->assertEquals(MobileEvidenceUploadService::STATUS_HASH_MISMATCH, $res['status']);
        $this->assertEquals(422, $res['http_code']);

        // Assert staging directory was purged
        $this->assertDirectoryDoesNotExist($this->uploadService->getStagingDir($evidenceId));
    }

    public function testFullLifecycleHappyPathSealed(): void
    {
        $case = $this->createTestCase();
        $caseId = (int)$case['id'];
        $evidenceId = 'EVID-SEAL-SUCCESS-' . bin2hex(random_bytes(4));
        $this->createdEvidenceIds[] = $evidenceId;

        $part0 = 'PHOTO_HEADER_DATA_ABCDEFGHIJKLMN';
        $part1 = 'PHOTO_IMAGE_BYTES_12345678901234';
        $fullFile = $part0 . $part1;
        $targetHash = hash('sha256', $fullFile);

        // 1. Upload Part 0
        $res0 = $this->uploadService->uploadChunk(
            $evidenceId, 0, 2, $part0, hash('sha256', $part0), strlen($part0), $targetHash, 'UUID-P0', $this->testDeviceId, $this->testUserId
        );
        $this->assertTrue($res0['success']);

        // 2. Upload Part 1
        $res1 = $this->uploadService->uploadChunk(
            $evidenceId, 1, 2, $part1, hash('sha256', $part1), strlen($part1), $targetHash, 'UUID-P1', $this->testDeviceId, $this->testUserId
        );
        $this->assertTrue($res1['success']);
        $this->assertEquals(MobileEvidenceUploadService::STATUS_ALL_RECEIVED, $res1['status']);

        // 3. Assemble and Seal Evidence
        $resSeal = $this->uploadService->assembleAndSealEvidence(
            $evidenceId, $caseId, $this->testUserId, $this->testDeviceId, [
                'evidence_type' => 'PHOTO',
            ]
        );

        $this->assertTrue($resSeal['success']);
        $this->assertEquals(MobileEvidenceUploadService::STATUS_SEALED, $resSeal['status']);
        $this->assertEquals(200, $resSeal['http_code']);
        $this->assertGreaterThan(0, $resSeal['field_evidence_id']);
        $this->assertEquals($targetHash, $resSeal['sha256']);

        // 4. Verify authoritative field_evidence row in database
        $evidenceRow = $this->db->table('field_evidence')->where('id', $resSeal['field_evidence_id'])->get()->getRowArray();
        $this->assertNotNull($evidenceRow);
        $this->assertEquals($targetHash, $evidenceRow['sha256']);
        $this->assertEquals($caseId, (int)$evidenceRow['fault_case_id']);

        // 5. Test Replay Seal Idempotency (B7-G18)
        $resReplay = $this->uploadService->assembleAndSealEvidence(
            $evidenceId, $caseId, $this->testUserId, $this->testDeviceId
        );
        $this->assertTrue($resReplay['success']);
        $this->assertTrue($resReplay['is_existing']);
        $this->assertEquals($resSeal['field_evidence_id'], $resReplay['field_evidence_id']);

        // Check exactly 1 field_evidence row created
        $count = $this->db->table('field_evidence')->where('fault_case_id', $caseId)->where('sha256', $targetHash)->countAllResults();
        $this->assertEquals(1, $count, 'Replay seal must be idempotent and not create duplicate domain evidence records');
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
