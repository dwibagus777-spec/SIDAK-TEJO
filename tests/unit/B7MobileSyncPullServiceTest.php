<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Config\Database;
use CodeIgniter\Database\BaseConnection;
use App\Services\MobileSyncPullService;
use App\Services\FaultCaseService;

/**
 * B7MobileSyncPullServiceTest
 *
 * Comprehensive Test Suite for Phase B.7.4: Downstream Delta Pull Engine
 *
 * HARD GUARDS VERIFIED:
 * - B7-G11: Device Registration Governance
 * - B7-G12: Monotonic Server Sequence Cursor (journal_seq BIGINT AUTO_INCREMENT)
 * - B7-G13: Push / Pull Architectural Separation
 * - B7-G14: Topology Read-Only Read Model (gis_translines = 0, assets = 0, Delta = 0)
 * - B7-G16: Monotonic Server Cursor High-Water Mark (Strict sequence integrity)
 * - B7-G17: Cursor Pagination Safety (next_cursor is strictly last fetched record)
 * - B7-G19: Device Revocation Non-Cascade & Pull Prohibition
 */
class B7MobileSyncPullServiceTest extends TestCase
{
    protected ?BaseConnection $db;
    protected MobileSyncPullService $pullService;
    protected FaultCaseService $caseService;

    protected string $testDeviceId = 'DEV-PULL-UNITTEST-001';
    protected string $revokedDeviceId = 'DEV-PULL-REVOKED-001';
    protected int $testUserId = 1;

    protected array $createdCaseIds = [];
    protected array $createdEventIds = [];
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

        $this->caseService = new FaultCaseService($this->db);
        $this->pullService = new MobileSyncPullService($this->db, $this->caseService);

        $this->createdCaseIds = [];
        $this->createdEventIds = [];
        $this->createdJournalSeqs = [];

        $now = date('Y-m-d H:i:s');

        // 1. Ensure active test device exists
        $existingActive = $this->db->table('mobile_devices')
            ->where('device_id', $this->testDeviceId)
            ->get()
            ->getRowArray();

        if (!$existingActive) {
            $this->db->table('mobile_devices')->insert([
                'device_id'                   => $this->testDeviceId,
                'user_id'                     => $this->testUserId,
                'device_identity_fingerprint' => 'Pull UnitTest Device v1',
                'device_model'                => 'PLN Toughpad 5G',
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

        // 2. Ensure revoked test device exists (B7-G19)
        $existingRevoked = $this->db->table('mobile_devices')
            ->where('device_id', $this->revokedDeviceId)
            ->get()
            ->getRowArray();

        if (!$existingRevoked) {
            $this->db->table('mobile_devices')->insert([
                'device_id'                   => $this->revokedDeviceId,
                'user_id'                     => $this->testUserId,
                'device_identity_fingerprint' => 'Revoked Pull UnitTest Device',
                'device_model'                => 'Compromised Device',
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
        // Cleanup test cases, assignments, investigations, findings, evidence
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

        // Cleanup test journal entries created during test
        if (!empty($this->createdJournalSeqs)) {
            $this->db->table('mobile_sync_journal')->whereIn('journal_seq', $this->createdJournalSeqs)->delete();
        }
        $this->db->table('mobile_sync_journal')->where('device_id', $this->testDeviceId)->delete();

        parent::tearDown();
    }

    /**
     * Helper to insert a synthetic journal entry with explicit parameters.
     */
    protected function insertJournalRecord(
        string $opType = 'FINDING',
        string $entityType = 'field_finding',
        ?int $entityId = null,
        string $syncStatus = 'ACCEPTED',
        int $userId = 1,
        ?string $deviceId = null
    ): int {
        $now = date('Y-m-d H:i:s');
        $uuid = 'pull-uuid-' . bin2hex(random_bytes(8));
        $syncId = 'pull-sync-' . bin2hex(random_bytes(6));

        $this->db->table('mobile_sync_journal')->insert([
            'client_submission_uuid' => $uuid,
            'sync_id'                => $syncId,
            'device_id'              => $deviceId ?? $this->testDeviceId,
            'user_id'                => $userId,
            'operation_type'         => $opType,
            'entity_type'            => $entityType,
            'entity_id'              => $entityId,
            'payload_sha256'         => hash('sha256', $uuid),
            'payload_json'           => json_encode(['test' => true, 'op' => $opType]),
            'client_created_at'      => $now,
            'client_timezone_offset' => '+07:00',
            'server_received_at'     => $now,
            'server_processed_at'    => $now,
            'attempt_no'             => 1,
            'sync_status'            => $syncStatus,
            'response_http_code'     => ($syncStatus === 'ACCEPTED' ? 200 : 422),
            'domain_status'          => ($syncStatus === 'ACCEPTED' ? 'RECORDED' : 'VALIDATION_FAILED'),
            'created_at'             => $now,
        ]);

        $seq = (int)$this->db->insertID();
        $this->createdJournalSeqs[] = $seq;
        return $seq;
    }

    /**
     * Test 1: Empty pull with cursor=0 on clean state returns empty deltas,
     * next_cursor=0, has_more=false, and includes read-only topology model.
     */
    public function testEmptyPullOnZeroCursor(): void
    {
        // Ensure no leftover records for this test device
        $this->db->table('mobile_sync_journal')->where('device_id', $this->testDeviceId)->delete();

        // Query current maximum journal_seq to test pulling above it
        $maxSeqRow = $this->db->table('mobile_sync_journal')->selectMax('journal_seq')->get()->getRowArray();
        $currentMax = (int)($maxSeqRow['journal_seq'] ?? 0);

        // Pull with cursor = currentMax (no newer records)
        $res = $this->pullService->pullDelta($currentMax, 50, $this->testDeviceId, $this->testUserId);

        $this->assertEquals(200, $res['http_code']);
        $this->assertEquals('SUCCESS', $res['status']);
        $this->assertEquals($currentMax, $res['cursor_in']);
        $this->assertEquals($currentMax, $res['next_cursor']);
        $this->assertFalse($res['has_more']);
        $this->assertEquals(0, $res['count']);
        $this->assertIsArray($res['deltas']);
        $this->assertEmpty($res['deltas']['journal_records']);

        // Pull with cursor = 0 includes topology read model by default (Initial Sync)
        $resInitial = $this->pullService->pullDelta(0, 10, $this->testDeviceId, $this->testUserId);
        $this->assertEquals(200, $resInitial['http_code']);
        $this->assertNotNull($resInitial['topology_read_model']);
        $this->assertEquals('DELTA_TOPOLOGY_ZERO', $resInitial['topology_read_model']['read_only_invariant']);
        $this->assertTrue($resInitial['topology_read_model']['authoritative_bound']);
        $this->assertFalse($resInitial['topology_read_model']['mutation_permitted']);
    }

    /**
     * Test 2: Device Authorization Governance (B7-G11, B7-G19).
     * Missing device_id -> 403
     * Unregistered device_id -> 403
     * Revoked device_id -> 403 (Guard B7-G19)
     */
    public function testDeviceAuthorizationGovernance(): void
    {
        // 2a. Missing device_id
        $resMissing = $this->pullService->pullDelta(0, 50, '', $this->testUserId);
        $this->assertEquals(403, $resMissing['http_code']);
        $this->assertEquals('DEVICE_NOT_REGISTERED', $resMissing['status']);

        // 2b. Unregistered device_id
        $resUnknown = $this->pullService->pullDelta(0, 50, 'DEV-UNKNOWN-999-FAKE', $this->testUserId);
        $this->assertEquals(403, $resUnknown['http_code']);
        $this->assertEquals('DEVICE_NOT_REGISTERED', $resUnknown['status']);

        // 2c. Revoked device_id (Guard B7-G19)
        $resRevoked = $this->pullService->pullDelta(0, 50, $this->revokedDeviceId, $this->testUserId);
        $this->assertEquals(403, $resRevoked['http_code']);
        $this->assertEquals('DEVICE_REVOKED', $resRevoked['status']);
    }

    /**
     * Test 3: Cursor Validation & Sanitization (Guard B7-G16).
     * Negative cursor values are rejected with HTTP 422 INVALID_CURSOR.
     */
    public function testNegativeCursorRejection(): void
    {
        $res = $this->pullService->pullDelta(-1, 50, $this->testDeviceId, $this->testUserId);

        $this->assertEquals(422, $res['http_code']);
        $this->assertEquals('INVALID_CURSOR', $res['status']);
        $this->assertEquals(0, $res['next_cursor']);
        $this->assertFalse($res['has_more']);
        $this->assertStringContainsString('Cursor sequence cannot be negative', $res['message']);
    }

    /**
     * Test 4: Bounded Pagination Limit (Guard B7-G17).
     * When limit=3 and 5 records are available, exactly 3 are returned,
     * next_cursor is the journal_seq of the 3rd record, and has_more is true.
     */
    public function testBoundedPaginationLimit(): void
    {
        $maxSeqBefore = (int)($this->db->table('mobile_sync_journal')->selectMax('journal_seq')->get()->getRowArray()['journal_seq'] ?? 0);

        // Insert 5 records
        $seqs = [];
        for ($i = 1; $i <= 5; $i++) {
            $seqs[] = $this->insertJournalRecord('FINDING', 'field_finding', 100 + $i);
        }

        // Pull with limit = 3 starting from maxSeqBefore
        $res = $this->pullService->pullDelta($maxSeqBefore, 3, $this->testDeviceId, $this->testUserId);

        $this->assertEquals(200, $res['http_code']);
        $this->assertEquals('SUCCESS', $res['status']);
        $this->assertEquals(3, $res['count']);
        $this->assertEquals(3, count($res['deltas']['journal_records']));

        // Verify next_cursor equals the 3rd record's journal_seq (Guard B7-G17)
        $expectedNextCursor = $seqs[2]; // 3rd item (index 2)
        $this->assertEquals($expectedNextCursor, $res['next_cursor']);
        $this->assertTrue($res['has_more']);

        // Verify journal records are strictly in ascending order
        $returnedSeqs = array_column($res['deltas']['journal_records'], 'journal_seq');
        $this->assertEquals([$seqs[0], $seqs[1], $seqs[2]], $returnedSeqs);
    }

    /**
     * Test 5: Multi-Page Deterministic Sequence (Guards B7-G12, B7-G16, B7-G17).
     * Verifies that traversing consecutive pages yields all records in strictly
     * monotonic sequence with 0 duplicates and 0 gaps.
     */
    public function testMultiPageDeterministicSequence(): void
    {
        $maxSeqBefore = (int)($this->db->table('mobile_sync_journal')->selectMax('journal_seq')->get()->getRowArray()['journal_seq'] ?? 0);

        // Insert 6 records
        $allInsertedSeqs = [];
        for ($i = 1; $i <= 6; $i++) {
            $allInsertedSeqs[] = $this->insertJournalRecord('INVESTIGATION', 'field_investigation', 200 + $i);
        }

        $allReceivedSeqs = [];
        $currentCursor = $maxSeqBefore;
        $pageLimit = 2; // 3 pages of 2 items each
        $pagesFetched = 0;

        do {
            $res = $this->pullService->pullDelta($currentCursor, $pageLimit, $this->testDeviceId, $this->testUserId);
            $this->assertEquals(200, $res['http_code']);

            $pageSeqs = array_column($res['deltas']['journal_records'], 'journal_seq');
            $allReceivedSeqs = array_merge($allReceivedSeqs, $pageSeqs);

            $currentCursor = $res['next_cursor'];
            $pagesFetched++;

            // Failsafe to prevent infinite loop
            $this->assertLessThanOrEqual(5, $pagesFetched, 'Exceeded expected page count');
        } while ($res['has_more']);

        // Total received must exactly match inserted records
        $this->assertEquals(3, $pagesFetched, 'Expected exactly 3 pages for 6 records with limit 2');
        $this->assertEquals($allInsertedSeqs, $allReceivedSeqs, 'Received records must match inserted sequence deterministically');
        $this->assertEquals(end($allInsertedSeqs), $currentCursor, 'Final next_cursor must equal the highest sequence in the batch');
    }

    /**
     * Test 6: Concurrent Append Resilience (Guards B7-G16, B7-G17).
     * Records appended to journal while client is halfway through pagination
     * are never skipped or duplicated.
     */
    public function testConcurrentAppendResilience(): void
    {
        $maxSeqBefore = (int)($this->db->table('mobile_sync_journal')->selectMax('journal_seq')->get()->getRowArray()['journal_seq'] ?? 0);

        // Initial 3 records
        $s1 = $this->insertJournalRecord('FINDING', 'field_finding', 301);
        $s2 = $this->insertJournalRecord('FINDING', 'field_finding', 302);
        $s3 = $this->insertJournalRecord('FINDING', 'field_finding', 303);

        // Client pulls Page 1 (limit 2)
        $page1 = $this->pullService->pullDelta($maxSeqBefore, 2, $this->testDeviceId, $this->testUserId);
        $this->assertEquals(2, $page1['count']);
        $this->assertEquals($s2, $page1['next_cursor']);
        $this->assertTrue($page1['has_more']);

        // CONCURRENT APPEND: Server ingests 2 more records while client is offline/idle
        $s4 = $this->insertJournalRecord('FINDING', 'field_finding', 304);
        $s5 = $this->insertJournalRecord('FINDING', 'field_finding', 305);

        // Client pulls Page 2 with cursor = $s2, limit 2
        $page2 = $this->pullService->pullDelta($page1['next_cursor'], 2, $this->testDeviceId, $this->testUserId);
        $this->assertEquals(2, $page2['count']);
        $page2Seqs = array_column($page2['deltas']['journal_records'], 'journal_seq');
        $this->assertEquals([$s3, $s4], $page2Seqs, 'Page 2 must pick up s3 and s4 without skip');
        $this->assertEquals($s4, $page2['next_cursor']);
        $this->assertTrue($page2['has_more'], 'has_more must be true because s5 is still pending');

        // Client pulls Page 3 with cursor = $s4, limit 2
        $page3 = $this->pullService->pullDelta($page2['next_cursor'], 2, $this->testDeviceId, $this->testUserId);
        $this->assertEquals(1, $page3['count']);
        $page3Seqs = array_column($page3['deltas']['journal_records'], 'journal_seq');
        $this->assertEquals([$s5], $page3Seqs);
        $this->assertEquals($s5, $page3['next_cursor']);
        $this->assertFalse($page3['has_more']);

        // Verify union of all pages
        $combinedSeqs = array_merge(
            array_column($page1['deltas']['journal_records'], 'journal_seq'),
            $page2Seqs,
            $page3Seqs
        );
        $this->assertEquals([$s1, $s2, $s3, $s4, $s5], $combinedSeqs);
    }

    /**
     * Test 7: Domain Entity Hydration.
     * Pull service hydrates assigned cases, candidate assets, investigations,
     * findings with revisions, and evidence.
     */
    public function testDomainEntityHydration(): void
    {
        $now = date('Y-m-d H:i:s');
        $eventNumber = 'EVT-PULL-TEST-' . bin2hex(random_bytes(4));

        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $assetId = (int)($as['id'] ?? 1);

        // 1. Create fault event & case using schema-valid columns
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

        $resCase = $this->caseService->createOrResolveCase($eventId);
        $case = $resCase['case'];
        $caseId = (int)$case['id'];
        $this->createdCaseIds[] = $caseId;
        $caseNumber = $case['case_number'];

        // 3. Dispatch assignment to testUserId
        $this->db->table('dispatch_assignments')->insert([
            'fault_case_id'   => $caseId,
            'assigned_to'     => $this->testUserId,
            'assigned_by'     => $this->testUserId,
            'assigned_at'     => $now,
            'status'          => 'ASSIGNED',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        // 4. Investigation
        $this->db->table('field_investigations')->insert([
            'fault_case_id'    => $caseId,
            'investigator_id'  => $this->testUserId,
            'status'           => 'EN_ROUTE',
            'started_at'       => $now,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        $investigationId = (int)$this->db->insertID();

        // 5. Finding & Revision
        $this->db->table('field_findings')->insert([
            'fault_case_id'         => $caseId,
            'investigation_id'      => $investigationId,
            'predicted_asset_id'    => $assetId,
            'actual_asset_id'       => $assetId,
            'actual_lat'            => -7.123456,
            'actual_lng'            => 110.123456,
            'finding_status'        => 'RECORDED',
            'cause_category'        => 'LIGHTNING',
            'condition_description' => 'Insulator flashover detected on crossarm',
            'captured_by'           => $this->testUserId,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
        $findingId = (int)$this->db->insertID();

        $this->db->table('field_finding_revisions')->insert([
            'field_finding_id'     => $findingId,
            'revision_no'          => 1,
            'previous_values_json' => json_encode(['cause_category' => 'UNKNOWN']),
            'amended_fields_json'  => json_encode(['cause_category' => 'LIGHTNING']),
            'amended_by'           => $this->testUserId,
            'amendment_reason'     => 'Initial hydrated finding revision notes',
            'created_at'           => $now,
        ]);

        // 6. Evidence
        $this->db->table('field_evidence')->insert([
            'fault_case_id'    => $caseId,
            'field_finding_id' => $findingId,
            'asset_id'         => $assetId,
            'evidence_type'    => 'PHOTO',
            'file_reference'   => 'uploads/pull_test/evidence.jpg',
            'sha256'           => hash('sha256', 'hydrated_evidence_content'),
            'captured_at'      => $now,
            'captured_by'      => $this->testUserId,
            'created_at'       => $now,
        ]);
        $evidenceId = (int)$this->db->insertID();

        // 7. Insert journal records for these entities
        $maxSeqBefore = (int)($this->db->table('mobile_sync_journal')->selectMax('journal_seq')->get()->getRowArray()['journal_seq'] ?? 0);
        $this->insertJournalRecord('INVESTIGATION', 'field_investigation', $investigationId);
        $this->insertJournalRecord('FINDING', 'field_finding', $findingId);
        $this->insertJournalRecord('EVIDENCE', 'field_evidence', $evidenceId);

        // Pull delta
        $res = $this->pullService->pullDelta($maxSeqBefore, 50, $this->testDeviceId, $this->testUserId);

        $this->assertEquals(200, $res['http_code']);
        $this->assertEquals(3, $res['count']);

        // Assert assigned_cases hydrated
        $hydratedCases = $res['deltas']['assigned_cases'];
        $this->assertNotEmpty($hydratedCases);
        $foundCase = array_filter($hydratedCases, fn($c) => (int)$c['id'] === $caseId);
        $this->assertNotEmpty($foundCase);
        $caseData = reset($foundCase);
        $this->assertEquals($caseNumber, $caseData['case_number']);

        // Assert investigations hydrated
        $hydratedInv = $res['deltas']['investigations'];
        $this->assertNotEmpty($hydratedInv);
        $foundInv = array_filter($hydratedInv, fn($i) => (int)$i['id'] === $investigationId);
        $this->assertNotEmpty($foundInv);

        // Assert findings & revisions hydrated
        $hydratedFindings = $res['deltas']['findings'];
        $this->assertNotEmpty($hydratedFindings);
        $foundFinding = array_filter($hydratedFindings, fn($f) => (int)$f['id'] === $findingId);
        $this->assertNotEmpty($foundFinding);
        $findingData = reset($foundFinding);
        $this->assertEquals('LIGHTNING', $findingData['cause_category']);
        $this->assertNotEmpty($findingData['revisions']);
        $this->assertEquals(1, (int)$findingData['revisions'][0]['revision_no']);
        $this->assertEquals('Initial hydrated finding revision notes', $findingData['revisions'][0]['amendment_reason']);

        // Assert evidence hydrated
        $hydratedEv = $res['deltas']['evidence'];
        $this->assertNotEmpty($hydratedEv);
        $foundEv = array_filter($hydratedEv, fn($e) => (int)$e['id'] === $evidenceId);
        $this->assertNotEmpty($foundEv);
    }

    /**
     * Test 8: Read-Only Topology Model & Zero Topology Mutation (Guard B7-G14).
     * Topology tables (gis_translines, assets) must remain 100% untouched (Delta = 0).
     */
    public function testReadOnlyTopologyReadModelZeroMutation(): void
    {
        // 1. Record baseline topology counts
        $baselineTl = (int)$this->db->table('gis_translines')->countAllResults();
        $baselineAssets = (int)$this->db->table('assets')->countAllResults();

        // 2. Fetch read-only model directly
        $topoModel = $this->pullService->getReadOnlyTopologyModel();
        $this->assertIsArray($topoModel);
        $this->assertEquals('DELTA_TOPOLOGY_ZERO', $topoModel['read_only_invariant']);
        $this->assertTrue($topoModel['authoritative_bound']);
        $this->assertFalse($topoModel['mutation_permitted']);
        $this->assertEquals($baselineTl, $topoModel['total_translines']);
        $this->assertEquals($baselineAssets, $topoModel['total_assets']);

        // 3. Execute multiple pullDelta operations
        for ($i = 0; $i < 3; $i++) {
            $this->pullService->pullDelta(0, 10, $this->testDeviceId, $this->testUserId, ['include_topology' => true]);
        }

        // 4. Verify post-pull counts are strictly identical (Delta = 0)
        $postTl = (int)$this->db->table('gis_translines')->countAllResults();
        $postAssets = (int)$this->db->table('assets')->countAllResults();

        $this->assertEquals($baselineTl, $postTl, 'Delta topology gis_translines MUST be strictly 0');
        $this->assertEquals($baselineAssets, $postAssets, 'Delta topology assets MUST be strictly 0');
    }

    /**
     * Test 9: User Scoping Isolation.
     * When user_scope_only is enabled, only journal records for the requested user are returned.
     */
    public function testUserScopingIsolation(): void
    {
        $maxSeqBefore = (int)($this->db->table('mobile_sync_journal')->selectMax('journal_seq')->get()->getRowArray()['journal_seq'] ?? 0);

        $userAId = 1;
        $userBId = 99; // Different user

        // Insert record for User A and User B
        $seqA = $this->insertJournalRecord('FINDING', 'field_finding', 401, 'ACCEPTED', $userAId);
        $seqB = $this->insertJournalRecord('FINDING', 'field_finding', 402, 'ACCEPTED', $userBId);

        // Pull with user_scope_only = true for User A
        $resA = $this->pullService->pullDelta($maxSeqBefore, 50, $this->testDeviceId, $userAId, [
            'user_scope_only' => true,
        ]);

        $this->assertEquals(200, $resA['http_code']);
        $returnedSeqs = array_map('intval', array_column($resA['deltas']['journal_records'], 'journal_seq'));

        $this->assertContains($seqA, $returnedSeqs, 'User A should receive their own record');
        $this->assertNotContains($seqB, $returnedSeqs, 'User A must NOT receive User B records when user_scope_only is set');
    }

    /**
     * Test 10: Device Last Seen Touch.
     * Active device pull updates mobile_devices.last_seen_at.
     */
    public function testDeviceLastSeenTouch(): void
    {
        $beforePull = date('Y-m-d H:i:s', time() - 2);

        $this->db->table('mobile_devices')->where('device_id', $this->testDeviceId)->update([
            'last_seen_at' => $beforePull,
        ]);

        $this->pullService->pullDelta(0, 10, $this->testDeviceId, $this->testUserId);

        $deviceAfter = $this->db->table('mobile_devices')->where('device_id', $this->testDeviceId)->get()->getRowArray();
        $this->assertNotNull($deviceAfter['last_seen_at']);
        $this->assertGreaterThanOrEqual($beforePull, $deviceAfter['last_seen_at']);
    }

    /**
     * Test 11: Rejected Operations Excluded from Delta Stream.
     * Journal records with sync_status != 'ACCEPTED' are audit artifacts and
     * must never be pushed downstream to mobile clients.
     */
    public function testRejectedOperationsExcludedFromDelta(): void
    {
        $maxSeqBefore = (int)($this->db->table('mobile_sync_journal')->selectMax('journal_seq')->get()->getRowArray()['journal_seq'] ?? 0);

        $acceptedSeq = $this->insertJournalRecord('FINDING', 'field_finding', 501, 'ACCEPTED');
        $rejectedSeq = $this->insertJournalRecord('FINDING', 'field_finding', 502, 'REJECTED');

        $res = $this->pullService->pullDelta($maxSeqBefore, 50, $this->testDeviceId, $this->testUserId);

        $this->assertEquals(200, $res['http_code']);
        $returnedSeqs = array_map('intval', array_column($res['deltas']['journal_records'], 'journal_seq'));

        $this->assertContains($acceptedSeq, $returnedSeqs, 'ACCEPTED record must be in delta stream');
        $this->assertNotContains($rejectedSeq, $returnedSeqs, 'REJECTED record must be excluded from delta stream');
    }
}
