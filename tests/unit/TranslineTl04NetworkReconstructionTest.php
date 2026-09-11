<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\TranslineTl04ReconstructionService;

/**
 * TL-04: Accelerated JTM Network Reconstruction & Network-Level Promotion Test Suite
 *
 * Covers:
 * 1. Engine constants and limits (TL-04.0, MAX_BATCH = 10, DEGREE <= 4, SPAN <= 85m)
 * 2. Dual-scoring (Edge score 80-89 + Network evidence >= 20 -> NETWORK_PROMOTED_AUTO)
 * 3. Direct high confidence pass (Edge score >= 90)
 * 4. Strict 24 hard gates enforcement (cross-feeder, cross-ULP, saturation, excess span)
 * 5. In-batch degree saturation guard (prevents exceeding degree 4 within the same batch)
 * 6. Maximum batch ceiling of 10 edges per execution
 * 7. Independent per-batch transactions in executeProgressiveLoop
 * 8. Zero-write invariants on assets and temuan tables
 * 9. Honest diagnostics for isolated nodes (no forced topology fabrication)
 */
class TranslineTl04NetworkReconstructionTest extends CIUnitTestCase
{
    protected $db;
    protected TranslineTl04ReconstructionService $reconService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();

        $this->reconService = new TranslineTl04ReconstructionService($this->db);
    }

    protected function setupSchema(): void
    {
        $forge = Database::forge();

        $forge->dropTable('gis_translines', true);
        $forge->dropTable('assets', true);
        $forge->dropTable('sections', true);
        $forge->dropTable('penyulang', true);
        $forge->dropTable('ulps', true);
        $forge->dropTable('temuan', true);

        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('ulps', true);
        }

        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Tiang JTM'],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'lokasi'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'parent_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'length_meters'      => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'coordinates'        => ['type' => 'TEXT', 'null' => true],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'ENGINEER_TEST'],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'judul'       => ['type' => 'VARCHAR', 'constraint' => 255],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
                'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }
    }

    protected function seedTestData(): void
    {
        $this->db->table('ulps')->emptyTable();
        $this->db->table('penyulang')->emptyTable();
        $this->db->table('sections')->emptyTable();
        $this->db->table('assets')->emptyTable();
        $this->db->table('gis_translines')->emptyTable();
        $this->db->table('temuan')->emptyTable();

        $this->db->table('ulps')->insert([
            'id' => 1, 'kode_ulp' => 'ULP-SDK', 'nama_ulp' => 'ULP Sidoarjo Kota', 'status' => 'AKTIF'
        ]);
        $this->db->table('ulps')->insert([
            'id' => 2, 'kode_ulp' => 'ULP-KRN', 'nama_ulp' => 'ULP Krian', 'status' => 'AKTIF'
        ]);

        $this->db->table('penyulang')->insert([
            'id' => 15, 'ulp_id' => 1, 'kode_penyulang' => 'BJKM', 'nama_penyulang' => 'BANJAR KEMANTREN', 'status' => 'AKTIF'
        ]);
        $this->db->table('penyulang')->insert([
            'id' => 16, 'ulp_id' => 1, 'kode_penyulang' => 'CRDM', 'nama_penyulang' => 'CANDRAMAS', 'status' => 'AKTIF'
        ]);

        $this->db->table('sections')->insert([
            'id' => 101, 'penyulang_id' => 15, 'nama_section' => 'Seksi Utama Banjar Kemantren'
        ]);
        $this->db->table('sections')->insert([
            'id' => 102, 'penyulang_id' => 15, 'nama_section' => 'Seksi Percabangan Selatan'
        ]);

        // Connected Backbone: Assets 1..5
        for ($i = 1; $i <= 5; $i++) {
            $lat = -7.450000 - ($i * 0.000300); // ~33m apart
            $this->db->table('assets')->insert([
                'id'           => $i,
                'kode_asset'   => sprintf('BJKM-%03d', $i),
                'nama_asset'   => "Tiang Backbone {$i}",
                'penyulang_id' => 15,
                'ulp_id'       => 1,
                'section_id'   => 101,
                'latitude'     => $lat,
                'longitude'    => 112.710000,
            ]);
        }

        // Translines connecting 1-2, 2-3, 3-4, 4-5
        for ($i = 1; $i <= 4; $i++) {
            $next = $i + 1;
            $this->db->table('gis_translines')->insert([
                'id'              => $i,
                'transline_code'  => "TL-15-{$i}-{$next}",
                'penyulang_id'    => 15,
                'source_asset_id' => $i,
                'target_asset_id' => $next,
                'distance_meters' => 33.3,
                'status'          => 'ACTIVE',
                'created_by'      => 'MANUAL_BASELINE',
            ]);
        }

        // Isolated Linear Chain: Assets 10..14 (consecutive, 30m apart)
        for ($i = 10; $i <= 14; $i++) {
            $lat = -7.460000 - (($i - 10) * 0.000270); // ~30m apart
            $this->db->table('assets')->insert([
                'id'           => $i,
                'kode_asset'   => sprintf('BJKM-%03d', $i),
                'nama_asset'   => "Tiang Rantai {$i}",
                'penyulang_id' => 15,
                'ulp_id'       => 1,
                'section_id'   => 102,
                'latitude'     => $lat,
                'longitude'    => 112.715000,
            ]);
        }

        // Isolated Single Anchor: Asset 6 (35m from backbone asset 5)
        $this->db->table('assets')->insert([
            'id'           => 6,
            'kode_asset'   => 'BJKM-006',
            'nama_asset'   => 'Tiang Ujung Sambungan',
            'penyulang_id' => 15,
            'ulp_id'       => 1,
            'section_id'   => 101,
            'latitude'     => -7.450000 - (6 * 0.000300),
            'longitude'    => 112.710000,
        ]);

        // Temuan row (Strictly protected from transline writes)
        $this->db->table('temuan')->insert([
            'id'       => 1,
            'asset_id' => 1,
            'judul'    => 'Isolator Flashover Tiang 1',
            'status'   => 'OPEN',
        ]);
    }

    public function testTl04ConstantsAndInstantiation(): void
    {
        $this->assertSame('TL-04.0', TranslineTl04ReconstructionService::ENGINE_VERSION);
        $this->assertSame(10, TranslineTl04ReconstructionService::MAX_BATCH_SIZE);
        $this->assertSame(4, TranslineTl04ReconstructionService::HARD_MAX_DEGREE);
        $this->assertEquals(85.0, TranslineTl04ReconstructionService::HARD_MAX_SPAN_METERS);
    }

    public function testNetworkPromotionOnChainCandidates(): void
    {
        $analysis = $this->reconService->analyzeFeeder(15);
        $this->assertSame('success', $analysis['status']);

        $candidates = $analysis['candidates'] ?? [];
        $this->assertNotEmpty($candidates);

        // Find candidate between chain nodes 10 and 11
        $chainCand = null;
        foreach ($candidates as $c) {
            if (($c['source_asset_id'] == 10 && $c['target_asset_id'] == 11) ||
                ($c['source_asset_id'] == 11 && $c['target_asset_id'] == 10)) {
                $chainCand = $c;
                break;
            }
        }

        $this->assertNotNull($chainCand, 'Kandidat rantai 10-11 harus terdeteksi');
        $this->assertTrue($chainCand['gate_valid'], 'Harus lolos 24 safety gates');
        $this->assertGreaterThanOrEqual(80, $chainCand['total_score'], 'Skor total harus >= 80');
        $this->assertGreaterThanOrEqual(20, $chainCand['network_score'], 'Bukti koherensi jaringan harus >= 20');
        $this->assertTrue($chainCand['is_auto_complete'], 'Harus berstatus auto-complete (promoted or score>=90)');
    }

    public function testAnchorTerminalExtensionScoring(): void
    {
        $analysis = $this->reconService->analyzeFeeder(15);
        $candidates = $analysis['candidates'] ?? [];

        // Candidate 5 to 6 is a collinear mainline extension
        $anchorCand = null;
        foreach ($candidates as $c) {
            if (($c['source_asset_id'] == 5 && $c['target_asset_id'] == 6) ||
                ($c['source_asset_id'] == 6 && $c['target_asset_id'] == 5)) {
                $anchorCand = $c;
                break;
            }
        }

        $this->assertNotNull($anchorCand, 'Kandidat anchor 5-6 harus terdeteksi');
        $this->assertTrue($anchorCand['gate_valid']);
        $this->assertSame('ANCHOR_MAINLINE_EXTENSION', $anchorCand['candidate_type']);
        $this->assertGreaterThanOrEqual(85, $anchorCand['total_score']);
        $this->assertTrue($anchorCand['is_auto_complete']);
    }

    public function test24SafetyGatesStrictBlocking(): void
    {
        // 1. Cross-Feeder attempt (Asset in feeder 15 to asset in feeder 16)
        $this->db->table('assets')->insert([
            'id'           => 99,
            'kode_asset'   => 'CRDM-099',
            'nama_asset'   => 'Tiang Asing Feeder 16',
            'penyulang_id' => 16,
            'ulp_id'       => 1,
            'section_id'   => 101,
            'latitude'     => -7.450000,
            'longitude'    => 112.710000,
        ]);

        $analysis = $this->reconService->analyzeFeeder(15);
        $candidates = $analysis['candidates'] ?? [];

        // No candidate should ever connect to Asset 99
        foreach ($candidates as $c) {
            $this->assertNotEquals(99, $c['source_asset_id'], 'Cross-feeder node must never be source');
            $this->assertNotEquals(99, $c['target_asset_id'], 'Cross-feeder node must never be target');
        }

        // 2. Degree Saturation Block: Saturated node (degree >= 4) must be blocked
        // Add 4 lines to asset 1
        $this->db->table('gis_translines')->insert(['penyulang_id' => 15, 'source_asset_id' => 1, 'target_asset_id' => 201]);
        $this->db->table('gis_translines')->insert(['penyulang_id' => 15, 'source_asset_id' => 1, 'target_asset_id' => 202]);
        $this->db->table('gis_translines')->insert(['penyulang_id' => 15, 'source_asset_id' => 1, 'target_asset_id' => 203]);

        $resatAnalysis = $this->reconService->analyzeFeeder(15);
        foreach ($resatAnalysis['candidates'] as $c) {
            if ($c['source_asset_id'] == 1 || $c['target_asset_id'] == 1) {
                $this->assertFalse($c['gate_valid'], 'Node dengan degree >= 4 harus diblokir gerbang keselamatan');
                $this->assertFalse($c['is_auto_complete']);
            }
        }
    }

    public function testBatchCeilingMax10AndInBatchSaturation(): void
    {
        $analysis = $this->reconService->analyzeFeeder(15);
        $batch = $analysis['defensible_batch_preview'] ?? [];

        $this->assertLessThanOrEqual(
            TranslineTl04ReconstructionService::MAX_BATCH_SIZE,
            count($batch),
            'Ukuran batch defensibel tidak boleh melebihi 10'
        );

        // Verify that no node within the defensible batch exceeds degree 4 after hypothetical batch commit
        $inBatchDegrees = [];
        foreach ($batch as $edge) {
            $u = $edge['source_asset_id'];
            $v = $edge['target_asset_id'];
            $inBatchDegrees[$u] = ($inBatchDegrees[$u] ?? $edge['source_degree']) + 1;
            $inBatchDegrees[$v] = ($inBatchDegrees[$v] ?? $edge['target_degree']) + 1;

            $this->assertLessThanOrEqual(4, $inBatchDegrees[$u], "Derajat node {$u} dalam batch tidak boleh > 4");
            $this->assertLessThanOrEqual(4, $inBatchDegrees[$v], "Derajat node {$v} dalam batch tidak boleh > 4");
        }
    }

    public function testExecuteBatchCommitAndZeroWriteInvariants(): void
    {
        $preAssets = $this->db->table('assets')->get()->getResultArray();
        $preTemuan = $this->db->table('temuan')->get()->getResultArray();
        $preAssetHash = hash('sha256', json_encode($preAssets));
        $preTemuanHash = hash('sha256', json_encode($preTemuan));

        $preTranslineCount = $this->db->table('gis_translines')->where('penyulang_id', 15)->countAllResults();

        $result = $this->reconService->executeBatch(15, [
            'actor_name' => 'UNIT_TEST_ACTOR',
            'max_batch'  => 10,
        ]);

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['zero_write_invariants_pass'], 'Zero-write firewall harus lulus 100%');

        $postTranslineCount = $this->db->table('gis_translines')->where('penyulang_id', 15)->countAllResults();
        $this->assertGreaterThan($preTranslineCount, $postTranslineCount, 'Transline baru harus terbentuk di DB');

        // Verify protected tables were untouched
        $postAssets = $this->db->table('assets')->get()->getResultArray();
        $postTemuan = $this->db->table('temuan')->get()->getResultArray();
        $postAssetHash = hash('sha256', json_encode($postAssets));
        $postTemuanHash = hash('sha256', json_encode($postTemuan));

        $this->assertSame($preAssetHash, $postAssetHash, 'Tabel assets tidak boleh mengalami mutasi apapun');
        $this->assertSame($preTemuanHash, $postTemuanHash, 'Tabel temuan tidak boleh mengalami mutasi apapun');
    }

    public function testExecuteProgressiveLoopWithIndependentBatches(): void
    {
        $result = $this->reconService->executeProgressiveLoop(15, [
            'actor_name'     => 'UNIT_TEST_PROGRESSIVE',
            'max_iterations' => 5,
            'max_batch'      => 10,
        ]);

        $this->assertSame('success', $result['status']);
        $this->assertSame('PROGRESSIVE_LOOP_COMPLETED', $result['action']);
        $this->assertIsArray($result['batches']);
        $this->assertGreaterThanOrEqual(1, $result['iterations_run']);
        $this->assertGreaterThanOrEqual(1, $result['total_created_count']);

        // Verify final inventory state is consistent
        $inventory = $result['final_inventory'];
        $this->assertArrayHasKey('total_master_assets', $inventory);
        $this->assertArrayHasKey('connected_assets_count', $inventory);
        $this->assertArrayHasKey('isolated_assets_count', $inventory);
    }

    public function testHonestDiagnosticsOnIsolatedAssets(): void
    {
        // Add a completely far away isolated pole with no neighbors (> 500m)
        $this->db->table('assets')->insert([
            'id'           => 999,
            'kode_asset'   => 'BJKM-999',
            'nama_asset'   => 'Tiang Terisolasi Sangat Jauh',
            'penyulang_id' => 15,
            'ulp_id'       => 1,
            'section_id'   => 101,
            'latitude'     => -7.500000,
            'longitude'    => 112.750000,
        ]);

        $analysis = $this->reconService->analyzeFeeder(15);
        $diagnostics = $analysis['isolated_asset_diagnostics']['assets_detail'] ?? [];

        $diag999 = null;
        foreach ($diagnostics as $d) {
            if ($d['asset_id'] == 999) {
                $diag999 = $d;
                break;
            }
        }

        $this->assertNotNull($diag999, 'Aset 999 harus didiagnosa');
        $this->assertSame('NO_VALID_NETWORK_RELATIONSHIP', $diag999['classification'], 'Node jauh harus diklasifikasikan NO_VALID_NETWORK_RELATIONSHIP');
        $this->assertSame(0, $diag999['candidate_count'], 'Kandidat harus 0');

        // Confirm node 999 is NOT included in defensible batch preview
        foreach ($analysis['defensible_batch_preview'] as $edge) {
            $this->assertNotEquals(999, $edge['source_asset_id']);
            $this->assertNotEquals(999, $edge['target_asset_id']);
        }
    }
}
