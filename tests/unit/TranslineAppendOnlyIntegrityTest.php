<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\GisTranslineService;
use App\Services\TranslineIntegrityAuditService;
use Config\Database;

/**
 * CR-HOTFIX-05: Transline Append-Only Integrity Test Suite
 *
 * Verifies the 6 Architectural Acceptance Gates:
 * 1. 10 -> 11 Append-Only Count Transition.
 * 2. 10 Original Records Remain 100% Intact.
 * 3. Idempotent Duplicate Edge Protection (11 remains 11).
 * 4. Feeder Isolation (Cross-feeder rejected, other feeders unaffected).
 * 5. Reload & Query Persistence.
 * 6. Zero-Deactivation Invariant (ADD never deactivates other edges).
 */
class TranslineAppendOnlyIntegrityTest extends CIUnitTestCase
{
    protected $db;
    protected GisTranslineService $service;
    protected TranslineIntegrityAuditService $auditService;

    protected int $testFeederA = 991;
    protected int $testFeederB = 992;
    protected array $assetIdsA = [];
    protected array $assetIdsB = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->service = new GisTranslineService($this->db);
        $this->auditService = new TranslineIntegrityAuditService($this->db);

        $this->setupTestEnvironment();
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    protected function setupTestEnvironment(): void
    {
        $this->cleanTestData();

        // 1. Create 15 assets for Feeder A (991)
        for ($i = 1; $i <= 15; $i++) {
            $lat = -7.40000000 + ($i * 0.0005);
            $lng = 112.70000000 + ($i * 0.0005);
            $this->db->table('assets')->insert([
                'kode_asset'   => "TEST_A_{$i}",
                'nama_asset'   => "Tiang Test A-{$i}",
                'jenis_asset'  => 'JTM',
                'penyulang_id' => $this->testFeederA,
                'ulp_id'       => 1,
                'latitude'     => $lat,
                'longitude'    => $lng,
                'status'       => 'NORMAL',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $this->assetIdsA[$i] = (int)$this->db->insertID();
        }

        // 2. Create 5 assets for Feeder B (992)
        for ($i = 1; $i <= 5; $i++) {
            $lat = -7.50000000 + ($i * 0.0005);
            $lng = 112.80000000 + ($i * 0.0005);
            $this->db->table('assets')->insert([
                'kode_asset'   => "TEST_B_{$i}",
                'nama_asset'   => "Tiang Test B-{$i}",
                'jenis_asset'  => 'JTM',
                'penyulang_id' => $this->testFeederB,
                'ulp_id'       => 1,
                'latitude'     => $lat,
                'longitude'    => $lng,
                'status'       => 'NORMAL',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $this->assetIdsB[$i] = (int)$this->db->insertID();
        }

        // 3. Seed exactly 10 initial transline edges for Feeder A (chain 1->2, 2->3, ..., 10->11)
        for ($i = 1; $i <= 10; $i++) {
            $src = $this->assetIdsA[$i];
            $tgt = $this->assetIdsA[$i + 1];
            $this->db->table('gis_translines')->insert([
                'transline_code'     => "TL-TEST-A-{$i}-" . ($i + 1),
                'penyulang_id'       => $this->testFeederA,
                'source_asset_id'    => $src,
                'target_asset_id'    => $tgt,
                'geometry'           => json_encode([[112.700, -7.400], [112.701, -7.401]]),
                'geometry_type'      => 'LineString',
                'conductor_type'     => 'AAAC',
                'conductor_size'     => '150 mm²',
                'conductor_material' => 'ALUMINUM_ALLOY',
                'installation_type'  => 'OVERHEAD',
                'circuit_config'     => '3_PHASE',
                'distance_meters'    => 55.5,
                'status'             => 'ACTIVE',
                'is_active'          => 1,
                'created_by'         => 'TEST_SUITE',
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
        }

        // 4. Seed 2 initial transline edges for Feeder B (chain 1->2, 2->3)
        for ($i = 1; $i <= 2; $i++) {
            $src = $this->assetIdsB[$i];
            $tgt = $this->assetIdsB[$i + 1];
            $this->db->table('gis_translines')->insert([
                'transline_code'     => "TL-TEST-B-{$i}-" . ($i + 1),
                'penyulang_id'       => $this->testFeederB,
                'source_asset_id'    => $src,
                'target_asset_id'    => $tgt,
                'geometry'           => json_encode([[112.800, -7.500], [112.801, -7.501]]),
                'geometry_type'      => 'LineString',
                'conductor_type'     => 'A3CS',
                'conductor_size'     => '150 mm²',
                'conductor_material' => 'ALUMINUM_ALLOY',
                'installation_type'  => 'OVERHEAD_INSULATED',
                'circuit_config'     => '3_PHASE',
                'distance_meters'    => 62.0,
                'status'             => 'ACTIVE',
                'is_active'          => 1,
                'created_by'         => 'TEST_SUITE',
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
        }
    }

    protected function cleanTestData(): void
    {
        $this->db->table('gis_translines')->whereIn('penyulang_id', [$this->testFeederA, $this->testFeederB])->delete();
        $this->db->table('assets')->whereIn('penyulang_id', [$this->testFeederA, $this->testFeederB])->delete();
        if ($this->db->tableExists('network_topology_versions')) {
            $this->db->table('network_topology_versions')->whereIn('penyulang_id', [$this->testFeederA, $this->testFeederB])->delete();
        }
    }

    /**
     * GATE 1: Append-Only Count Transition (10 -> 11)
     */
    public function test10To11AppendOnly(): void
    {
        $initialRows = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederA)
            ->where('is_active', 1)
            ->get()
            ->getResultArray();
        $this->assertCount(10, $initialRows, 'Baseline harus memiliki tepat 10 active translines.');

        // Add 11th edge: Asset 11 -> Asset 12
        $result = $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[11],
            'target_asset_id'    => $this->assetIdsA[12],
            'conductor_type'     => 'AAAC',
            'conductor_size'     => '150 mm²',
            'connection_mode'    => 'ADD',
        ], ['name' => 'OPERATOR_TEST']);

        $this->assertEquals('success', $result['status'], 'Penyimpanan transline ke-11 harus sukses.');
        $this->assertFalse($result['is_duplicate'], 'Edge baru tidak boleh ditandai sebagai duplikat.');

        $postRows = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederA)
            ->where('is_active', 1)
            ->get()
            ->getResultArray();
        $this->assertCount(11, $postRows, 'Active translines setelah penambahan harus tepat 11 (10 -> 11).');
    }

    /**
     * GATE 2: 10 Original Records Invariant Intact
     */
    public function testExisting10RecordsIntact(): void
    {
        // Capture initial 10 records snapshot
        $baseline = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederA)
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $this->assertCount(10, $baseline);

        $baselineMap = [];
        foreach ($baseline as $row) {
            $baselineMap[(int)$row['id']] = $row;
        }

        // Add 11th edge
        $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[11],
            'target_asset_id'    => $this->assetIdsA[12],
            'conductor_type'     => 'AAAC',
            'conductor_size'     => '150 mm²',
            'connection_mode'    => 'ADD',
        ]);

        // Query the original 10 records and verify zero mutation
        foreach ($baselineMap as $id => $orig) {
            $current = $this->db->table('gis_translines')->where('id', $id)->get()->getRowArray();
            $this->assertNotNull($current, "Record lama #{$id} harus tetap ada di database.");
            $this->assertEquals(1, (int)$current['is_active'], "Record lama #{$id} harus tetap is_active=1.");
            $this->assertNull($current['deleted_at'], "Record lama #{$id} tidak boleh memiliki deleted_at.");
            $this->assertEquals($orig['source_asset_id'], $current['source_asset_id'], "Source asset ID #{$id} tidak boleh berubah.");
            $this->assertEquals($orig['target_asset_id'], $current['target_asset_id'], "Target asset ID #{$id} tidak boleh berubah.");
            $this->assertEquals($orig['transline_code'], $current['transline_code'], "Transline code #{$id} tidak boleh berubah.");
            $this->assertEquals($orig['geometry'], $current['geometry'], "Geometry #{$id} tidak boleh berubah.");
        }
    }

    /**
     * GATE 3: Idempotent Duplicate Edge Protection (11 remains 11)
     */
    public function testDuplicateIsIdempotent(): void
    {
        // 1. Add 11th edge
        $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[11],
            'target_asset_id'    => $this->assetIdsA[12],
            'connection_mode'    => 'ADD',
        ]);

        $countAfterFirst = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederA)
            ->where('is_active', 1)
            ->countAllResults();
        $this->assertEquals(11, $countAfterFirst, 'Harus ada 11 baris aktif.');

        // 2. Submit duplicate forward (11 -> 12)
        $dupForward = $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[11],
            'target_asset_id'    => $this->assetIdsA[12],
            'connection_mode'    => 'ADD',
        ]);
        $this->assertEquals('success', $dupForward['status']);
        $this->assertTrue($dupForward['is_duplicate'], 'Duplicate forward harus terdeteksi.');

        $countAfterForward = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederA)
            ->where('is_active', 1)
            ->countAllResults();
        $this->assertEquals(11, $countAfterForward, 'Total rows harus tetap 11 setelah duplicate forward.');

        // 3. Submit duplicate reverse (12 -> 11)
        $dupReverse = $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[12],
            'target_asset_id'    => $this->assetIdsA[11],
            'connection_mode'    => 'ADD',
        ]);
        $this->assertEquals('success', $dupReverse['status']);
        $this->assertTrue($dupReverse['is_duplicate'], 'Duplicate reverse harus terdeteksi.');

        $countAfterReverse = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederA)
            ->where('is_active', 1)
            ->countAllResults();
        $this->assertEquals(11, $countAfterReverse, 'Total rows harus tetap 11 setelah duplicate reverse.');
    }

    /**
     * GATE 4: Feeder Isolation Protection
     */
    public function testFeederIsolation(): void
    {
        $feederBInitial = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederB)
            ->where('is_active', 1)
            ->countAllResults();
        $this->assertEquals(2, $feederBInitial, 'Feeder B harus memiliki tepat 2 edges.');

        // 1. Add edge in Feeder A (Asset 11 -> 12)
        $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[11],
            'target_asset_id'    => $this->assetIdsA[12],
            'connection_mode'    => 'ADD',
        ]);

        // Verify Feeder B is completely untouched
        $feederBAfterA = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederB)
            ->where('is_active', 1)
            ->countAllResults();
        $this->assertEquals(2, $feederBAfterA, 'Feeder B tidak boleh bertambah atau berkurang saat Feeder A dimodifikasi.');

        // 2. Attempt cross-feeder connection: Feeder A asset -> Feeder B asset
        $crossResult = $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[1],
            'target_asset_id'    => $this->assetIdsB[1],
            'connection_mode'    => 'ADD',
        ]);

        $this->assertEquals('error', $crossResult['status'], 'Cross-feeder connection harus ditolak.');
        $this->assertStringContainsString('antar-penyulang dilarang', $crossResult['message']);

        // Verify no edge was created
        $feederACount = $this->db->table('gis_translines')->where('penyulang_id', $this->testFeederA)->where('is_active', 1)->countAllResults();
        $this->assertEquals(11, $feederACount, 'Feeder A count harus tetap 11.');
    }

    /**
     * GATE 5: Reload & Query Persistence
     */
    public function testReloadPersistence(): void
    {
        // Add 11th edge
        $saveResult = $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[11],
            'target_asset_id'    => $this->assetIdsA[12],
            'conductor_type'     => 'AAAC',
            'conductor_size'     => '150 mm²',
            'connection_mode'    => 'ADD',
        ]);
        $newTranslineId = (int)$saveResult['transline_id'];

        // Read back via getFeederTranslines
        $allActive = $this->service->getFeederTranslines($this->testFeederA);
        $this->assertCount(11, $allActive, 'getFeederTranslines harus mengembalikan tepat 11 translines.');

        $found = false;
        foreach ($allActive as $t) {
            if ((int)$t['id'] === $newTranslineId) {
                $found = true;
                $this->assertEquals((int)$this->assetIdsA[11], (int)$t['source_asset_id']);
                $this->assertEquals((int)$this->assetIdsA[12], (int)$t['target_asset_id']);
                break;
            }
        }
        $this->assertTrue($found, 'Transline baru harus dapat dibaca kembali dengan data yang konsisten.');

        // Read back via authoritative read service
        $authRead = $this->service->getAuthoritativeTranslines(['penyulang_id' => $this->testFeederA]);
        $this->assertTrue($authRead['success']);
        $this->assertEquals(11, $authRead['total'], 'Authoritative read service harus mengembalikan total 11.');
    }

    /**
     * GATE 6: Zero-Deactivation Invariant (ADD Never Deactivates Other Edges)
     */
    public function testAddNeverDeactivatesExistingEdges(): void
    {
        // Sequentially add 3 edges: 11->12, 12->13, 13->14
        $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[11],
            'target_asset_id'    => $this->assetIdsA[12],
            'connection_mode'    => 'ADD',
        ]);
        $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[12],
            'target_asset_id'    => $this->assetIdsA[13],
            'connection_mode'    => 'ADD',
        ]);
        $this->service->saveTransline([
            'penyulang_id'       => $this->testFeederA,
            'source_asset_id'    => $this->assetIdsA[13],
            'target_asset_id'    => $this->assetIdsA[14],
            'connection_mode'    => 'ADD',
        ]);

        // Total should be 10 + 3 = 13
        $allRows = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederA)
            ->get()
            ->getResultArray();
        $this->assertCount(13, $allRows, 'Total row gis_translines harus 13.');

        // Hard Invariant Check: 0 inactive rows, 0 deleted_at rows
        $inactiveRows = $this->db->table('gis_translines')
            ->where('penyulang_id', $this->testFeederA)
            ->groupStart()
                ->where('is_active', 0)
                ->orWhere('status', 'DELETED')
                ->orWhere('deleted_at IS NOT NULL')
            ->groupEnd()
            ->get()
            ->getResultArray();

        $this->assertCount(0, $inactiveRows, 'Mode ADD TIDAK BOLEH menonaktifkan atau men-soft-delete baris transline mana pun (Harus 0 inactive rows).');

        // Audit via TranslineIntegrityAuditService
        $audit = $this->auditService->auditFeeder($this->testFeederA);
        $this->assertTrue($audit['is_healthy'], 'Audit integritas transline harus bernilai healthy.');
        $this->assertEquals(0, $audit['critical_anomalies'], 'Harus ada 0 anomali kritis.');
        $this->assertEquals(13, $audit['active_edges_count'], 'Harus ada tepat 13 active edges.');
    }

    /**
     * GATE 7: Audit Service Anomaly Detection
     */
    public function testAuditServiceAnomalyDetection(): void
    {
        // Insert a self-loop edge manually into Feeder A
        $this->db->table('gis_translines')->insert([
            'transline_code'  => 'TL-SELF-LOOP-TEST',
            'penyulang_id'    => $this->testFeederA,
            'source_asset_id' => $this->assetIdsA[1],
            'target_asset_id' => $this->assetIdsA[1], // SELF LOOP
            'status'          => 'ACTIVE',
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Insert an edge with orphan target ID (non-existent asset ID 999999)
        $this->db->table('gis_translines')->insert([
            'transline_code'  => 'TL-ORPHAN-TEST',
            'penyulang_id'    => $this->testFeederA,
            'source_asset_id' => $this->assetIdsA[1],
            'target_asset_id' => 999999, // ORPHAN
            'status'          => 'ACTIVE',
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $audit = $this->auditService->auditFeeder($this->testFeederA);
        $this->assertFalse($audit['is_healthy'], 'Feeder dengan self-loop dan orphan target tidak boleh sehat (is_healthy=false).');
        $this->assertGreaterThanOrEqual(2, $audit['critical_anomalies']);

        $anomalyTypes = array_column($audit['anomalies'], 'type');
        $this->assertContains(TranslineIntegrityAuditService::ANOMALY_SELF_LOOP, $anomalyTypes);
        $this->assertContains(TranslineIntegrityAuditService::ANOMALY_ORPHAN_TARGET, $anomalyTypes);
    }

    /**
     * GATE 8: Audit Endpoint Security Guard
     */
    public function testTranslineAuditEndpointSecurity(): void
    {
        // 1. Without session and without token -> 401 Unauthorized
        $_GET = [];
        $controller1 = new \App\Controllers\MigrateController();
        $controller1->initController(
            \Config\Services::request(null, false),
            new \CodeIgniter\HTTP\Response(new \Config\App()),
            \Config\Services::logger()
        );
        $resUnauthorized = $controller1->translineAudit();
        $this->assertEquals(401, $resUnauthorized->getStatusCode());
        $bodyUnauth = json_decode($resUnauthorized->getBody(), true);
        $this->assertEquals('error', $bodyUnauth['status']);
        $this->assertStringContainsString('Unauthorized', $bodyUnauth['message']);

        // 2. With valid operational token -> 200 OK
        $_GET['key'] = 'sidak_transline_audit_2026';
        $_GET['feeder_id'] = $this->testFeederA;
        $controller2 = new \App\Controllers\MigrateController();
        $controller2->initController(
            \Config\Services::request(null, false),
            new \CodeIgniter\HTTP\Response(new \Config\App()),
            \Config\Services::logger()
        );
        $resAuthorized = $controller2->translineAudit();
        $this->assertEquals(200, $resAuthorized->getStatusCode());
        $bodyAuth = json_decode($resAuthorized->getBody(), true);
        $this->assertEquals('success', $bodyAuth['status']);
        $this->assertArrayHasKey('data', $bodyAuth);
        $this->assertEquals($this->testFeederA, $bodyAuth['data']['feeder_id']);
        $this->assertEquals('GIS_TRANSLINE_APPEND_ONLY', $bodyAuth['data']['audit_invariant']);
    }
}
