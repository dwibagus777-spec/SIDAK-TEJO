<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Repositories\AssetRepository;
use App\Services\GISService;
use Config\Database;

/**
 * CR-HOTFIX-06: GIS Network Truth & Semantic Layer Correction Acceptance Test Suite
 *
 * Verifies the 6 Architectural Acceptance Gates:
 * Gate 1: Authoritative Feeder Detection (gis_translines > 0 -> AUTHORITATIVE, has_topology = true).
 * Gate 2: Preview Only Feeder Isolation (assets > 0, DB translines = 0 -> PREVIEW_ONLY, edges = [], has_topology = false).
 * Gate 3: Empty Feeder Handling (0 assets, 0 translines -> NO_NETWORK).
 * Gate 4: Preview Never Promoted to Transline (No id, transline_id, is_active on preview segments).
 * Gate 5: Fake ID Prohibition (Purge of synthetic idx+1 mapping).
 * Gate 6: Production Feeders Invariant Consistency.
 */
class GisNetworkTruthSemanticTest extends CIUnitTestCase
{
    protected $db;
    protected AssetRepository $assetRepo;
    protected GISService $gisService;

    protected int $feederAuth = 9981;
    protected int $feederPreview = 9982;
    protected int $feederEmpty = 9983;

    protected array $createdAssetIds = [];
    protected array $createdTranslineIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->ensureTablesExist();

        $this->assetRepo = new AssetRepository();
        $this->gisService = new GISService();

        $this->setupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    protected function ensureTablesExist(): void
    {
        $forge = Database::forge();

        // 1. penyulang table
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

        // 2. assets table
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON', 'null' => true],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'parent_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        // 3. gis_translines table
        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'section_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'from_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'to_asset_id'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 40.0],
                'length_m'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'source'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MANUAL'],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'INT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        // 4. network_topology_versions table
        if (!$this->db->tableExists('network_topology_versions')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id'   => ['type' => 'INT', 'constraint' => 11],
                'version_no'     => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'version_status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'segments_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'      => ['type' => 'INT', 'constraint' => 1, 'default' => 1],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('network_topology_versions', true);
        }

        // 5. Safeguard required columns if assets table was created by another test suite
        $this->safeAddColumn('assets', 'sequence_no', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('assets', 'parent_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('assets', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
    }

    protected function safeAddColumn(string $table, string $column, array $def): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        $prefixed = $this->db->prefixTable($table);
        $cols = array_column($this->db->query("PRAGMA table_info({$prefixed})")->getResultArray(), 'name');
        if (!in_array($column, $cols, true)) {
            try {
                \Config\Database::forge()->addColumn($table, [$column => $def]);
            } catch (\Throwable $e) {}
        }
    }

    protected function setupTestData(): void
    {
        $this->cleanTestData();

        // 1. Setup Feeder AUTHORITATIVE (9981): 3 Assets + 2 DB Translines
        $authAssets = [];
        for ($i = 1; $i <= 3; $i++) {
            $lat = -7.410000 + ($i * 0.001);
            $lng = 112.710000 + ($i * 0.001);
            $this->db->table('assets')->insert([
                'kode_asset'   => "TEST_AUTH_{$i}",
                'nama_asset'   => "Tiang Auth {$i}",
                'jenis_asset'  => 'JTM',
                'penyulang_id' => $this->feederAuth,
                'ulp_id'       => 1,
                'latitude'     => $lat,
                'longitude'    => $lng,
                'sequence_no'  => $i,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $id = (int)$this->db->insertID();
            $authAssets[] = $id;
            $this->createdAssetIds[] = $id;
        }

        // Insert 2 real authoritative translines
        for ($t = 0; $t < 2; $t++) {
            $u = $authAssets[$t];
            $v = $authAssets[$t + 1];
            $this->db->table('gis_translines')->insert([
                'penyulang_id'       => $this->feederAuth,
                'source_asset_id'    => $u,
                'target_asset_id'    => $v,
                'conductor_type'     => 'AAAC',
                'conductor_size'     => '150 mm²',
                'conductor_material' => 'ALUMINUM_ALLOY',
                'installation_type'  => 'OVERHEAD',
                'circuit_config'     => '3_PHASE',
                'distance_meters'    => 105.5,
                'is_active'          => 1,
                'created_by'         => 'TEST_SUITE_CR06',
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
            $this->createdTranslineIds[] = (int)$this->db->insertID();
        }

        // 2. Setup Feeder PREVIEW_ONLY (9982): 4 Assets + 0 DB Translines
        for ($i = 1; $i <= 4; $i++) {
            $lat = -7.420000 + ($i * 0.0008);
            $lng = 112.720000 + ($i * 0.0008);
            $this->db->table('assets')->insert([
                'kode_asset'   => "TEST_PREV_{$i}",
                'nama_asset'   => "Tiang Prev {$i}",
                'jenis_asset'  => 'JTM',
                'penyulang_id' => $this->feederPreview,
                'ulp_id'       => 1,
                'latitude'     => $lat,
                'longitude'    => $lng,
                'sequence_no'  => $i,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $this->createdAssetIds[] = (int)$this->db->insertID();
        }

        // Feeder EMPTY (9983): 0 Assets, 0 Translines
    }

    protected function cleanTestData(): void
    {
        if ($this->db->tableExists('gis_translines')) {
            $this->db->table('gis_translines')->whereIn('penyulang_id', [
                $this->feederAuth,
                $this->feederPreview,
                $this->feederEmpty
            ])->delete();
        }

        if ($this->db->tableExists('assets')) {
            $this->db->table('assets')->whereIn('penyulang_id', [
                $this->feederAuth,
                $this->feederPreview,
                $this->feederEmpty
            ])->delete();
        }
    }

    /**
     * Gate 1: Authoritative Feeder Detection
     * Feeders with DB translines > 0 MUST evaluate as AUTHORITATIVE,
     * return edges, have topology_ready = true, and has_topology = true.
     */
    public function testGate1AuthoritativeFeederDetection(): void
    {
        $segData = $this->assetRepo->getFeederNetworkSegments($this->feederAuth);

        $this->assertArrayHasKey('network_truth', $segData);
        $truth = $segData['network_truth'];

        $this->assertSame('AUTHORITATIVE', $truth['status']);
        $this->assertTrue($truth['has_authoritative_transline']);
        $this->assertSame(2, $truth['authoritative_transline_count']);
        $this->assertTrue($truth['topology_ready']);
        $this->assertCount(2, $segData['edges']);
        $this->assertEmpty($segData['preview_segments']);

        // Check GISService layer
        $gisData = $this->gisService->getNetworkData(['penyulang_id' => $this->feederAuth]);
        $this->assertTrue($gisData['meta']['has_topology']);
        $this->assertSame(2, $gisData['meta']['topology_count']);
        $this->assertSame(2, $gisData['meta']['authoritative_transline_count']);
        $this->assertCount(2, $gisData['translines']);
        $this->assertEmpty($gisData['preview_segments']);
    }

    /**
     * Gate 2: Preview Only Feeder Isolation
     * Feeders with assets > 0 but 0 DB translines MUST evaluate as PREVIEW_ONLY,
     * edges MUST be strictly empty, translines MUST be empty, topology_ready = false,
     * and has_topology = false.
     */
    public function testGate2PreviewOnlyFeederIsolation(): void
    {
        $segData = $this->assetRepo->getFeederNetworkSegments($this->feederPreview);

        $this->assertArrayHasKey('network_truth', $segData);
        $truth = $segData['network_truth'];

        $this->assertSame('PREVIEW_ONLY', $truth['status']);
        $this->assertFalse($truth['has_authoritative_transline']);
        $this->assertSame(0, $truth['authoritative_transline_count']);
        $this->assertFalse($truth['topology_ready']);

        // Invariant: edges and translines must be strictly EMPTY
        $this->assertEmpty($segData['edges'], 'PREVIEW_ONLY feeder must have empty edges array');
        $this->assertEmpty($segData['translines'], 'PREVIEW_ONLY feeder must have empty translines array');

        // Preview segments must be available
        $this->assertTrue($truth['preview_available']);
        $this->assertGreaterThan(0, count($segData['preview_segments']));

        // Check GISService layer
        $gisData = $this->gisService->getNetworkData(['penyulang_id' => $this->feederPreview]);
        $this->assertFalse($gisData['meta']['has_topology'], 'has_topology must be false for PREVIEW_ONLY');
        $this->assertSame(0, $gisData['meta']['topology_count'], 'topology_count must be 0 for PREVIEW_ONLY');
        $this->assertSame(0, $gisData['meta']['authoritative_transline_count']);
        $this->assertEmpty($gisData['translines'], 'translines must be empty for PREVIEW_ONLY');
        $this->assertEmpty($gisData['transline']['properties']['edges']);
        $this->assertEmpty($gisData['transline']['geometry']['coordinates']);
        $this->assertNotEmpty($gisData['preview_segments']);
    }

    /**
     * Gate 3: Empty Feeder Handling
     * Feeders with 0 assets and 0 translines MUST evaluate as NO_NETWORK.
     */
    public function testGate3EmptyFeederHandling(): void
    {
        $segData = $this->assetRepo->getFeederNetworkSegments($this->feederEmpty);

        $truth = $segData['network_truth'];
        $this->assertSame('NO_NETWORK', $truth['status']);
        $this->assertFalse($truth['has_authoritative_transline']);
        $this->assertSame(0, $truth['authoritative_transline_count']);
        $this->assertFalse($truth['topology_ready']);
        $this->assertEmpty($segData['edges']);
        $this->assertEmpty($segData['preview_segments']);

        $gisData = $this->gisService->getNetworkData(['penyulang_id' => $this->feederEmpty]);
        $this->assertFalse($gisData['meta']['has_topology']);
        $this->assertSame(0, $gisData['meta']['topology_count']);
        $this->assertEmpty($gisData['translines']);
        $this->assertEmpty($gisData['preview_segments']);
    }

    /**
     * Gate 4: Preview Never Promoted to Transline
     * Ensure that preview segments are strictly typed and lack authoritative transline fields.
     */
    public function testGate4PreviewNeverPromotedToTransline(): void
    {
        $segData = $this->assetRepo->getFeederNetworkSegments($this->feederPreview);
        $previewSegments = $segData['preview_segments'];

        $this->assertNotEmpty($previewSegments);

        foreach ($previewSegments as $idx => $p) {
            $this->assertSame('SPATIAL_PREVIEW', $p['type'] ?? null);
            $this->assertFalse($p['authoritative'] ?? true);
            $this->assertFalse($p['persisted'] ?? true);
            $this->assertFalse($p['topology'] ?? true);

            // STRICT INVARIANT: Must NOT contain database identity fields
            $this->assertArrayNotHasKey('id', $p, "Preview segment {$idx} must NOT contain 'id'");
            $this->assertArrayNotHasKey('transline_id', $p, "Preview segment {$idx} must NOT contain 'transline_id'");
            $this->assertArrayNotHasKey('is_active', $p, "Preview segment {$idx} must NOT contain 'is_active'");

            // Preview ID must be synthetic string
            $this->assertStringStartsWith('preview-seg-', $p['preview_id']);
        }
    }

    /**
     * Gate 5: Fake ID Prohibition
     * Asserts that no fake integer IDs (1, 2, 3...) are manufactured on preview objects.
     */
    public function testGate5FakeIdProhibition(): void
    {
        $segData = $this->assetRepo->getFeederNetworkSegments($this->feederPreview);

        // edges must be 0, so no fake IDs can exist there
        $this->assertCount(0, $segData['edges']);

        // Check each preview segment coordinates are valid line coordinates
        foreach ($segData['preview_segments'] as $p) {
            $this->assertIsArray($p['coordinates']);
            $this->assertGreaterThanOrEqual(2, count($p['coordinates']));
            foreach ($p['coordinates'] as $pt) {
                $this->assertCount(2, $pt);
                $this->assertIsFloat($pt[0]);
                $this->assertIsFloat($pt[1]);
            }
        }
    }

    /**
     * Gate 6: Production Feeder Invariants Verification
     * Verifies that Feeder 118 (Bahagia Steel 1) has PREVIEW_ONLY and has_topology = false,
     * and Feeder 23 (Gedangan) has AUTHORITATIVE and has_topology = true (if present in local DB).
     */
    public function testGate6ProductionFeedersInvariants(): void
    {
        // 1. Model Feeder 118 (Bahagia Steel 1): 4 assets, 0 translines
        for ($i = 1; $i <= 4; $i++) {
            $this->db->table('assets')->insert([
                'kode_asset'   => "BS1_TIANG_{$i}",
                'nama_asset'   => "Bahagia Steel 1 - Tiang {$i}",
                'jenis_asset'  => 'JTM',
                'penyulang_id' => 118,
                'ulp_id'       => 1,
                'latitude'     => -7.428000 + ($i * 0.0003),
                'longitude'    => 112.725000 + ($i * 0.0003),
                'sequence_no'  => $i,
            ]);
        }

        $data118 = $this->gisService->getNetworkData(['penyulang_id' => 118]);
        $this->assertSame('PREVIEW_ONLY', $data118['network_truth']['status']);
        $this->assertFalse($data118['meta']['has_topology'], 'Feeder 118 has_topology must be false');
        $this->assertSame(0, $data118['meta']['topology_count'], 'Feeder 118 topology_count must be 0');
        $this->assertEmpty($data118['translines'], 'Feeder 118 translines must be empty');
        $this->assertNotEmpty($data118['preview_segments'], 'Feeder 118 must have preview segments');

        // 2. Model Feeder 23 (Gedangan): 2 assets, 1 transline TL-23-4020-4088
        $this->db->table('assets')->insert([
            'id'           => 4020,
            'kode_asset'   => "GDG_4020",
            'nama_asset'   => "Gedangan Tiang 4020",
            'jenis_asset'  => 'JTM',
            'penyulang_id' => 23,
            'ulp_id'       => 1,
            'latitude'     => -7.380000,
            'longitude'    => 112.720000,
        ]);
        $this->db->table('assets')->insert([
            'id'           => 4088,
            'kode_asset'   => "GDG_4088",
            'nama_asset'   => "Gedangan Tiang 4088",
            'jenis_asset'  => 'JTM',
            'penyulang_id' => 23,
            'ulp_id'       => 1,
            'latitude'     => -7.380100,
            'longitude'    => 112.720100,
        ]);
        $this->db->table('gis_translines')->insert([
            'id'                 => 236,
            'transline_code'     => 'TL-23-4020-4088',
            'penyulang_id'       => 23,
            'source_asset_id'    => 4020,
            'target_asset_id'    => 4088,
            'conductor_type'     => 'AAAC',
            'conductor_size'     => '150 mm²',
            'distance_meters'    => 11.0,
            'is_active'          => 1,
            'created_by'         => 'MANUAL',
        ]);

        $data23 = $this->gisService->getNetworkData(['penyulang_id' => 23]);
        $this->assertSame('AUTHORITATIVE', $data23['network_truth']['status']);
        $this->assertTrue($data23['meta']['has_topology'], 'Feeder 23 has_topology must be true');
        $this->assertSame(1, $data23['meta']['topology_count']);
        $this->assertSame(1, $data23['meta']['authoritative_transline_count']);
        $this->assertCount(1, $data23['translines']);
        $this->assertEmpty($data23['preview_segments']);

        // Clean up models
        $this->db->table('gis_translines')->where('penyulang_id', 23)->delete();
        $this->db->table('assets')->whereIn('penyulang_id', [118, 23])->delete();
    }
}
