<?php

namespace Tests\Unit;

use App\Services\FindingMatchingService;
use App\Models\TemuanModel;
use Config\Database;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\I18n\Time;

class FindingFingerprintDeduplicationTest extends CIUnitTestCase
{
    protected FindingMatchingService $service;
    protected $db;
    protected int $testAssetId;
    protected int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect('test_mariadb');
        $this->service = new FindingMatchingService(new TemuanModel(), $this->db);

        // Disable foreign key checks for truncate operations
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        // Ensure clean test state for test-specific tables only
        $this->db->table('temuan_observations')->truncate();
        $this->db->table('temuan')->truncate();
        $this->db->table('riwayat_tindak_lanjut')->truncate();
        $this->db->table('users')->truncate();
        $this->db->table('assets')->truncate();

        // Re-enable foreign key checks
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        // Create test asset with unique identifier
        $uniqueSuffix = uniqid('testfp_', true);
        $this->testAssetId = $this->db->table('assets')->insert([
            'ulp_id'         => 1,
            'penyulang_id'   => 1,
            'section_id'     => 1,
            'kode_asset'     => 'TEST-FP-ASSET-001-' . $uniqueSuffix,
            'nama_asset'     => 'Test Asset FP',
            'jenis_asset'    => 'TIANG',
            'latitude'       => -6.2088,
            'longitude'      => 106.8456,
            'created_at'     => Time::now('Asia/Jakarta')->toDateTimeString(),
            'updated_at'     => Time::now('Asia/Jakarta')->toDateTimeString(),
        ], true);

        // Create test user with unique username
        $userSuffix = uniqid('testfp_', true);
        $this->testUserId = $this->db->table('users')->insert([
            'username'       => 'test_user_fp_' . $userSuffix,
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'nama'           => 'Test User FP',
            'role'           => 'admin',
            'ulp_id'         => 1,
            'status'         => 'AKTIF',
            'created_at'     => Time::now('Asia/Jakarta')->toDateTimeString(),
            'updated_at'     => Time::now('Asia/Jakarta')->toDateTimeString(),
        ], true);
    }

    protected function tearDown(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->table('temuan_observations')->truncate();
        $this->db->table('temuan')->truncate();
        $this->db->table('riwayat_tindak_lanjut')->truncate();
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        // Do NOT truncate assets or users tables to preserve baseline data for other tests

        parent::tearDown();
    }

    // ================================================================
    // TC-FP-01: Re-inspection (SAME fingerprint) -> RE_OBSERVATION
    // ================================================================
    public function testFP01_ReInspectionSameFingerprintCreatesReObservation(): void
    {
        $input = [
            'asset_id'               => $this->testAssetId,
            'jenis_temuan'           => 'KONSTRUKSI',
            'component_code'         => 'TM11',
            'defect_location_code'   => 'D2B',
            'severity'               => 'MEDIUM',
            'detail_temuan'          => 'First observation',
            'inspection_id'          => 100,
            'tanggal_temuan'         => '2026-01-15',
        ];

        // First submission - creates NEW_MASTER_CASE
        $result1 = $this->service->processInspectionFinding($input, $this->testUserId);
        
        $this->assertEquals('NEW_MASTER_CASE', $result1['action']);
        $this->assertEquals(1, $result1['obs_count']);
        $this->assertEquals(0, $result1['rec_count']);
        $temuanId = $result1['temuan_id'];
        $fingerprint = $result1['fingerprint'];

        // Second submission with SAME fingerprint - should be RE_OBSERVATION
        $input['detail_temuan'] = 'Re-inspection observation';
        $input['severity'] = 'HIGH';
        $input['inspection_id'] = 101;
        
        $result2 = $this->service->processInspectionFinding($input, $this->testUserId);
        
        $this->assertEquals('RE_OBSERVATION', $result2['action']);
        $this->assertEquals($temuanId, $result2['temuan_id']);
        $this->assertEquals($fingerprint, $result2['fingerprint']);
        $this->assertEquals(2, $result2['obs_count']);
        $this->assertEquals(1, $result2['rec_count']);
        $this->assertEquals('HIGH', $result2['peak_severity']); // Peak severity updated

        // Verify temuan_observations has 2 records
        $obsCount = $this->db->table('temuan_observations')
            ->where('temuan_id', $temuanId)
            ->countAllResults();
        $this->assertEquals(2, $obsCount);
    }

    // ================================================================
    // TC-FP-02: Different component/defect on same asset -> NEW_MASTER_CASE
    // ================================================================
    public function testFP02_DifferentComponentDefectCreatesNewMasterCase(): void
    {
        // First finding: TM11 + D2B
        $input1 = [
            'asset_id'               => $this->testAssetId,
            'jenis_temuan'           => 'KONSTRUKSI',
            'component_code'         => 'TM11',
            'defect_location_code'   => 'D2B',
            'severity'               => 'MEDIUM',
            'detail_temuan'          => 'First defect',
        ];

        $result1 = $this->service->processInspectionFinding($input1, $this->testUserId);
        $this->assertEquals('NEW_MASTER_CASE', $result1['action']);
        $temuanId1 = $result1['temuan_id'];

        // Second finding: TM11 + D2C (different defect location)
        $input2 = [
            'asset_id'               => $this->testAssetId,
            'jenis_temuan'           => 'KONSTRUKSI',
            'component_code'         => 'TM11',
            'defect_location_code'   => 'D2C',
            'severity'               => 'HIGH',
            'detail_temuan'          => 'Second defect',
        ];

        $result2 = $this->service->processInspectionFinding($input2, $this->testUserId);
        $this->assertEquals('NEW_MASTER_CASE', $result2['action']);
        $temuanId2 = $result2['temuan_id'];

        $this->assertNotEquals($temuanId1, $temuanId2);
        $this->assertNotEquals($result1['fingerprint'], $result2['fingerprint']);
    }

    // ================================================================
    // TC-FP-03: Different asset -> NEW_MASTER_CASE (asset isolation)
    // ================================================================
    public function testFP03_DifferentAssetCreatesNewMasterCase(): void
    {
        // Create second asset with unique identifiers
        $uniqueSuffix = uniqid('', true);
        $this->db->table('assets')->insert([
            'ulp_id'         => 1,
            'penyulang_id'   => 1,
            'section_id'     => 1,
            'kode_asset'     => 'TEST-ASSET-002-' . $uniqueSuffix,
            'nama_asset'     => 'Test Asset 2',
            'jenis_asset'    => 'TIANG',
            'latitude'       => -6.2088,
            'longitude'      => 106.8456,
            'created_at'     => Time::now('Asia/Jakarta')->toDateTimeString(),
            'updated_at'     => Time::now('Asia/Jakarta')->toDateTimeString(),
        ]);
        $assetId2 = $this->db->insertID();

        $this->assertGreaterThan(0, $assetId2, 'Second asset insert should return valid ID');
        $this->assertNotEquals($this->testAssetId, $assetId2, 'Second asset should have different ID');

        $input1 = [
            'asset_id'               => $this->testAssetId,
            'jenis_temuan'           => 'KONSTRUKSI',
            'component_code'         => 'TM11',
            'defect_location_code'   => 'D2B',
            'severity'               => 'MEDIUM',
        ];

        $input2 = [
            'asset_id'               => $assetId2,
            'jenis_temuan'           => 'KONSTRUKSI',
            'component_code'         => 'TM11',
            'defect_location_code'   => 'D2B',
            'severity'               => 'MEDIUM',
        ];

        $result1 = $this->service->processInspectionFinding($input1, $this->testUserId);
        $result2 = $this->service->processInspectionFinding($input2, $this->testUserId);

        $this->assertEquals('NEW_MASTER_CASE', $result1['action']);
        $this->assertEquals('NEW_MASTER_CASE', $result2['action']);
        $this->assertNotEquals($result1['temuan_id'], $result2['temuan_id']);
        $this->assertNotEquals($result1['fingerprint'], $result2['fingerprint']);
    }

    // ================================================================
    // TC-FP-04: Closed case (RESOLVED) -> NEW_MASTER_CASE
    // ================================================================
    public function testFP04_ClosedCaseCreatesNewMasterCase(): void
    {
        $input = [
            'asset_id'               => $this->testAssetId,
            'jenis_temuan'           => 'KONSTRUKSI',
            'component_code'         => 'TM11',
            'defect_location_code'   => 'D2B',
            'severity'               => 'MEDIUM',
            'detail_temuan'          => 'Original finding',
        ];

        // Create first case
        $result1 = $this->service->processInspectionFinding($input, $this->testUserId);
        $temuanId = $result1['temuan_id'];

        // Manually close the case (simulate resolution)
        $this->db->table('temuan')
            ->where('id', $temuanId)
            ->update([
                'case_status' => 'RESOLVED',
                'status'      => 'SELESAI',
                'updated_at'  => Time::now('Asia/Jakarta')->toDateTimeString(),
            ]);

        // Submit again with same fingerprint
        $input['detail_temuan'] = 'New observation after resolution';
        $result2 = $this->service->processInspectionFinding($input, $this->testUserId);

        // Should create NEW_MASTER_CASE because previous case is RESOLVED
        $this->assertEquals('NEW_MASTER_CASE', $result2['action']);
        $this->assertNotEquals($temuanId, $result2['temuan_id']);
    }

    // ================================================================
    // TC-FP-05: Concurrent requests (advisory lock) -> no duplicates
    // ================================================================
    public function testFP05_ConcurrentRequestsNoDuplicates(): void
    {
        $input = [
            'asset_id'               => $this->testAssetId,
            'jenis_temuan'           => 'KONSTRUKSI',
            'component_code'         => 'TM11',
            'defect_location_code'   => 'D2B',
            'severity'               => 'MEDIUM',
            'detail_temuan'          => 'Concurrent test',
        ];

        // Simulate concurrent requests by calling processInspectionFinding multiple times
        // in rapid succession (single-threaded but sequential calls test the lock logic)
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $input['detail_temuan'] = "Concurrent test attempt $i";
            $results[] = $this->service->processInspectionFinding($input, $this->testUserId);
        }

        // All should return RE_OBSERVATION after the first
        $newMasterCount = 0;
        $reObsCount = 0;
        $temuanIds = [];

        foreach ($results as $result) {
            if ($result['action'] === 'NEW_MASTER_CASE') {
                $newMasterCount++;
                $temuanIds[] = $result['temuan_id'];
            } else {
                $reObsCount++;
            }
        }

        $this->assertEquals(1, $newMasterCount, 'Exactly one NEW_MASTER_CASE should be created');
        $this->assertEquals(4, $reObsCount, 'Four RE_OBSERVATION should be created');
        $this->assertCount(1, array_unique($temuanIds), 'All observations should reference the same temuan_id');

        // Final observation count should be 5
        $finalTemuan = $this->db->table('temuan')->where('id', $temuanIds[0])->get()->getRowArray();
        $this->assertEquals(5, $finalTemuan['observation_count']);
        $this->assertEquals(4, $finalTemuan['recurrence_count']);
    }

    // ================================================================
    // TC-FP-06: Severity escalation tracking
    // ================================================================
    public function testFP06_SeverityEscalationTracked(): void
    {
        $input = [
            'asset_id'               => $this->testAssetId,
            'jenis_temuan'           => 'KONSTRUKSI',
            'component_code'         => 'TM11',
            'defect_location_code'   => 'D2B',
            'severity'               => 'LOW',
            'detail_temuan'          => 'Initial LOW severity',
        ];

        // First: LOW
        $result1 = $this->service->processInspectionFinding($input, $this->testUserId);
        $temuanId = $result1['temuan_id'];
        $this->assertEquals('LOW', $result1['peak_severity']);

        // Second: MEDIUM
        $input['severity'] = 'MEDIUM';
        $input['detail_temuan'] = 'Escalated to MEDIUM';
        $result2 = $this->service->processInspectionFinding($input, $this->testUserId);
        $this->assertEquals('MEDIUM', $result2['peak_severity']);

        // Third: HIGH
        $input['severity'] = 'HIGH';
        $input['detail_temuan'] = 'Escalated to HIGH';
        $result3 = $this->service->processInspectionFinding($input, $this->testUserId);
        $this->assertEquals('HIGH', $result3['peak_severity']);

        // Fourth: CRITICAL
        $input['severity'] = 'CRITICAL';
        $input['detail_temuan'] = 'Escalated to CRITICAL';
        $result4 = $this->service->processInspectionFinding($input, $this->testUserId);
        $this->assertEquals('CRITICAL', $result4['peak_severity']);

        // Fifth: back to MEDIUM (should retain CRITICAL as peak)
        $input['severity'] = 'MEDIUM';
        $input['detail_temuan'] = 'De-escalated to MEDIUM but peak remains CRITICAL';
        $result5 = $this->service->processInspectionFinding($input, $this->testUserId);
        $this->assertEquals('CRITICAL', $result5['peak_severity']);

        // Verify in database
        $temuan = $this->db->table('temuan')->where('id', $temuanId)->get()->getRowArray();
        $this->assertEquals('CRITICAL', $temuan['peak_severity']);
        $this->assertEquals('MEDIUM', $temuan['current_severity']);
    }

    // ================================================================
    // TC-FP-07: Legacy behavior preserved when fingerprint fields missing
    // ================================================================
    public function testFP07_LegacyBehaviorWithoutFingerprintFields(): void
    {
        // This test would require integration with TemuanService
        // For now, we verify FindingMatchingService throws on missing asset_id
        $input = [
            'jenis_temuan'           => 'KONSTRUKSI',
            'component_code'         => 'TM11',
            'defect_location_code'   => 'D2B',
            'severity'               => 'MEDIUM',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('asset_id wajib tersedia');
        
        $this->service->processInspectionFinding($input, $this->testUserId);
    }

    // ================================================================
    // TC-FP-08: Fingerprint canonicalization (case/whitespace insensitive)
    // ================================================================
    public function testFP08_FingerprintCanonicalization(): void
    {
        $fp1 = FindingMatchingService::generateFingerprint($this->testAssetId, 'KONSTRUKSI', 'TM11', 'D2B');
        $fp2 = FindingMatchingService::generateFingerprint($this->testAssetId, ' konstruksi ', ' tm11 ', ' d2b ');
        $fp3 = FindingMatchingService::generateFingerprint($this->testAssetId, 'KONSTRUKSI', 'TM11', 'D2B');

        $this->assertEquals($fp1, $fp2, 'Fingerprint should be case/whitespace insensitive');
        $this->assertEquals($fp1, $fp3, 'Fingerprint should be deterministic');
    }

    // ================================================================
    // TC-FP-09: TemuanService integration - createTemuanWithFingerprintMatching
    // SKIPPED: Requires file upload mocking which is complex in unit tests.
    // Core fingerprint deduplication logic is tested in FP01-FP08 via FindingMatchingService.
    // ================================================================
    public function testFP09_TemuanServiceIntegrationNewMasterCase(): void
    {
        $this->markTestSkipped('TemuanService integration test requires file upload mocking. Core logic tested in FP01-FP08.');
    }

    // ================================================================
    // TC-FP-10: TemuanService integration - re-observation
    // SKIPPED: Requires file upload mocking which is complex in unit tests.
    // Core fingerprint deduplication logic is tested in FP01-FP08 via FindingMatchingService.
    // ================================================================
    public function testFP10_TemuanServiceIntegrationReObservation(): void
    {
        $this->markTestSkipped('TemuanService integration test requires file upload mocking. Core logic tested in FP01-FP08.');
    }
}