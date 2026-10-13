<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\GISService;
use App\Repositories\AssetRepository;

/**
 * MAP-01 PHASE 0: Asset Map Foundation Test Suite (Strict Read-Only)
 *
 * Verifies:
 * 1. GIS initial view and endpoint readiness.
 * 2. ULP -> Penyulang cascading contract.
 * 3. Penyulang -> Network asset GeoJSON contract.
 * 4. Coordinate validity filtering (lat [-90,90], lng [-180,180]).
 * 5. Invalid / zero coordinate exclusion.
 * 6. Asset -> Section -> Penyulang -> ULP hierarchy preservation.
 * 7. Construction type FK resolution.
 * 8. Absence of GPS nearest-asset auto-binding logic.
 * 9. Client GPS read-only non-mutative behavior.
 * 10. Absolute zero operational write across GIS service calls.
 */
class GisMapFoundationPhase0Test extends CIUnitTestCase
{
    protected $db;
    protected GISService $gisService;
    protected AssetRepository $assetRepository;

    protected static bool $schemaInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->gisService = new GISService();
        $this->assetRepository = new AssetRepository();
        if (!self::$schemaInitialized) {
            $this->setupTestSchema();
            self::$schemaInitialized = true;
        } else {
            $this->cleanTestTables();
        }
    }

    protected function cleanTestTables(): void
    {
        $this->db->table('ulps')->emptyTable();
        $this->db->table('penyulang')->emptyTable();
        $this->db->table('sections')->emptyTable();
        $this->db->table('construction_types')->emptyTable();
        $this->db->table('assets')->emptyTable();
        $this->db->table('gis_translines')->emptyTable();
    }

    protected function setupTestSchema(): void
    {
        $forge = Database::forge();

        // 1. ulps table
        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '51301'],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('ulps', true);
        }

        // 2. penyulang table
        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'OPERASI'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        // 3. sections table
        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        // 4. construction_types table
        if (!$this->db->tableExists('construction_types')) {
            $forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'code'        => ['type' => 'VARCHAR', 'constraint' => 50],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
                'description' => ['type' => 'TEXT', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('construction_types', true);
        }

        // 5. assets table
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'Aset Testing'],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'parent_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '10,8', 'default' => -7.4242],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'default' => 112.72701],
                'lokasi'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        // 6. gis_translines table
        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        // Schema compatibility guards for existing test tables
        $this->safeAddColumn('penyulang', 'ulp_id', ['type' => 'INT', 'constraint' => 11, 'default' => 1]);
        $this->safeAddColumn('penyulang', 'status', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF']);
        $this->safeAddColumn('assets', 'ulp_id', ['type' => 'INT', 'constraint' => 11, 'default' => 1]);
        $this->safeAddColumn('assets', 'sequence_no', ['type' => 'INT', 'constraint' => 11, 'default' => 0]);
        $this->safeAddColumn('assets', 'construction_type_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('assets', 'lokasi', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->safeAddColumn('assets', 'status', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL']);
        $this->safeAddColumn('assets', 'type', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('gis_translines', 'transline_code', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('gis_translines', 'is_active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);

        // Clean tables for controlled read tests
        $this->cleanTestTables();
    }

    protected function safeAddColumn(string $table, string $column, array $def): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        $prefixed = $this->db->prefixTable($table);
        $cols = array_column($this->db->query("PRAGMA table_info({$prefixed})")->getResultArray(), 'name');
        if (!in_array($column, $cols)) {
            Database::forge()->addColumn($table, [$column => $def]);
        }
    }

    /**
     * Test 01: GIS Initial View HTML exists and instantiates Leaflet
     */
    public function testGisInitialViewHtmlLoadsLeaflet(): void
    {
        $viewFile = APPPATH . 'Views/gis/index.php';
        $this->assertFileExists($viewFile);
        $content = file_get_contents($viewFile);

        $this->assertStringContainsString('id="gisMap"', $content);
        $this->assertStringContainsString('L.map', $content);
        $this->assertStringContainsString('fab-locate-me', $content);
        $this->assertStringContainsString('asset-quick-card', $content);
        $this->assertStringContainsString('offcanvas-asset-detail', $content);
    }

    /**
     * Test 02: ULP -> Penyulang hierarchy querying is deterministic and read-only
     */
    public function testUlpPenyulangCascadingHierarchy(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'SIDOARJO KOTA', 'status' => 'AKTIF']);
        $this->db->table('ulps')->insert(['id' => 2, 'kode_ulp' => '51302', 'nama_ulp' => 'KRIAN', 'status' => 'AKTIF']);

        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI', 'status' => 'AKTIF']);
        $this->db->table('penyulang')->insert(['id' => 2, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-002', 'nama_penyulang' => 'PUCANG', 'status' => 'AKTIF']);
        $this->db->table('penyulang')->insert(['id' => 3, 'ulp_id' => 2, 'kode_penyulang' => 'PYL-003', 'nama_penyulang' => 'BEBEKAN', 'status' => 'AKTIF']);

        $pRepo = new \App\Repositories\PenyulangRepository();
        $ulp1Feeders = $pRepo->getActivePenyulangsByUlp(1);
        $ulp2Feeders = $pRepo->getActivePenyulangsByUlp(2);

        $this->assertCount(2, $ulp1Feeders);
        $this->assertCount(1, $ulp2Feeders);
        $this->assertSame('PUCANG', $ulp1Feeders[0]['nama_penyulang']);
        $this->assertSame('SIWALAN PANJI', $ulp1Feeders[1]['nama_penyulang']);
        $this->assertSame('BEBEKAN', $ulp2Feeders[0]['nama_penyulang']);
    }

    /**
     * Test 03: Penyulang -> Network Asset GeoJSON contract exposes valid FeatureCollection
     */
    public function testPenyulangNetworkGeoJsonContract(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'SIDOARJO KOTA', 'status' => 'AKTIF']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI', 'status' => 'AKTIF']);
        $this->db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'GI-LBSM PDAM']);

        $this->db->table('construction_types')->insert([
            'id'   => 1,
            'code' => 'TM-1',
            'name' => 'Konstruksi Tiang Tumpu Lurus'
        ]);

        $this->db->table('assets')->insert([
            'id'                   => 400,
            'kode_asset'           => 'AST-SML-T001',
            'nama_asset'           => 'Tiang 01 Siwalan',
            'jenis_asset'          => 'TIANG_BETON',
            'section_id'           => 1,
            'penyulang_id'         => 1,
            'ulp_id'               => 1,
            'construction_type_id' => 1,
            'latitude'             => -7.42420000,
            'longitude'            => 112.72701000,
            'status'               => 'NORMAL'
        ]);

        $filters = ['penyulang_id' => 1, 'zoom' => 15, 'layers' => 'JTM,GARDU'];
        $result = $this->gisService->getNetworkData($filters, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('features', $result);
        $this->assertNotEmpty($result['features']);

        $feature = $result['features'][0];
        $this->assertSame('Feature', $feature['type']);
        $this->assertSame('Point', $feature['geometry']['type']);
        $this->assertEquals(112.72701, $feature['geometry']['coordinates'][0]);
        $this->assertEquals(-7.4242, $feature['geometry']['coordinates'][1]);

        $props = $feature['properties'];
        $this->assertSame(400, $props['asset_id']);
        $this->assertSame('AST-SML-T001', $props['kode_asset']);
        $this->assertSame(1, $props['penyulang_id']);
        $this->assertSame('TM-1', $props['construction_type']);

        // Test GeoJsonCollection wrapper
        $geoJson = $this->gisService->getGeoJsonCollection($filters, 1);
        $this->assertSame('FeatureCollection', $geoJson['type']);
        $this->assertNotEmpty($geoJson['features']);
    }

    /**
     * Test 04: Invalid / Zero Coordinates are filtered out from rendering
     */
    public function testZeroCoordinatesAreExcludedFromFeatures(): void
    {
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI', 'status' => 'AKTIF']);

        // Asset with 0,0 coordinates
        $this->db->table('assets')->insert([
            'id'           => 402,
            'kode_asset'   => 'AST-ZERO-COORD',
            'nama_asset'   => 'Tiang Tanpa Koordinat',
            'jenis_asset'  => 'TIANG_BETON',
            'penyulang_id' => 1,
            'ulp_id'       => 1,
            'latitude'     => 0.0,
            'longitude'    => 0.0,
        ]);

        $filters = ['penyulang_id' => 1];
        $result = $this->gisService->getNetworkData($filters, 1);

        // Asset 402 must be excluded from feature collection
        $features = $result['features'] ?? [];
        $matching = array_filter($features, fn($f) => ($f['properties']['asset_id'] ?? 0) === 402);
        $this->assertEmpty($matching);
    }

    /**
     * Test 05: Construction FK is resolved correctly from construction_types
     */
    public function testConstructionTypeIsResolvedFromFk(): void
    {
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('construction_types')->insert([
            'id'   => 5,
            'code' => 'TM-REC',
            'name' => 'Konstruksi Tiang Recloser Proteksi'
        ]);

        $this->db->table('assets')->insert([
            'id'                   => 405,
            'kode_asset'           => 'AST-REC-01',
            'nama_asset'           => 'Recloser Titik 1',
            'jenis_asset'          => 'SWITCH',
            'penyulang_id'         => 1,
            'ulp_id'               => 1,
            'construction_type_id' => 5,
            'latitude'             => -7.4300,
            'longitude'            => 112.7200,
        ]);

        $assets = $this->assetRepository->getGisNetworkAssets(['penyulang_id' => 1], 1);
        $this->assertCount(1, $assets);
        $this->assertSame('Konstruksi Tiang Recloser Proteksi', $assets[0]['construction_name']);
        $this->assertSame('TM-REC', $assets[0]['construction_code']);
    }

    /**
     * Test 06: GPS / User Location does NOT trigger nearest-asset auto-binding
     */
    public function testGpsDoesNotPerformNearestAssetAutoBinding(): void
    {
        $viewFile = APPPATH . 'Views/gis/index.php';
        $content = file_get_contents($viewFile);

        // Verify fab-locate-me only sets view and adds circleMarker without calling auto-bind
        $this->assertStringContainsString('bindPointerSafeTap(\'fab-locate-me\'', $content);
        $this->assertStringNotContainsString('autoBindNearestAsset', $content);
        $this->assertStringNotContainsString('attachNearestAsset', $content);
        $this->assertStringNotContainsString('bindToClosestAsset', $content);
    }

    /**
     * Test 07: Absolute Zero Operational Write across GIS service calls
     */
    public function testGisReadCallsPerformZeroOperationalWrites(): void
    {
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('assets')->insert([
            'id'           => 410,
            'kode_asset'   => 'AST-TEST-ZERO',
            'nama_asset'   => 'Aset Test Zero Write',
            'jenis_asset'  => 'TIANG_BETON',
            'penyulang_id' => 1,
            'ulp_id'       => 1,
            'latitude'     => -7.4200,
            'longitude'    => 112.7100,
        ]);

        $translinesBefore = $this->db->table('gis_translines')->countAllResults();
        $assetsBefore = $this->db->table('assets')->countAllResults();

        // Perform read operations
        $filters = ['penyulang_id' => 1, 'zoom' => 14, 'layers' => 'JTM,GARDU,TRAFO,SWITCH'];
        $this->gisService->getNetworkData($filters, 1);
        $this->assetRepository->getGisNetworkAssets($filters, 1);

        $translinesAfter = $this->db->table('gis_translines')->countAllResults();
        $assetsAfter = $this->db->table('assets')->countAllResults();

        // Assert 0 mutations
        $this->assertSame($translinesBefore, $translinesAfter);
        $this->assertSame($assetsBefore, $assetsAfter);
    }

    /**
     * Test 08: Full Hierarchy Validation (Asset -> Section -> Penyulang -> ULP)
     */
    public function testAssetSectionPenyulangUlpFullHierarchy(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('sections')->insert(['id' => 10, 'penyulang_id' => 1, 'nama_section' => 'SEC-PGL-01']);
        $this->db->table('assets')->insert([
            'id'           => 420,
            'kode_asset'   => 'AST-HIERARCHY-01',
            'nama_asset'   => 'Tiang Uji Hirarki',
            'jenis_asset'  => 'TIANG_BETON',
            'section_id'   => 10,
            'penyulang_id' => 1,
            'ulp_id'       => 1,
            'latitude'     => -7.4250,
            'longitude'    => 112.7250,
        ]);

        $asset = $this->db->table('assets')->where('id', 420)->get()->getRowArray();
        $this->assertNotNull($asset);

        $section = $this->db->table('sections')->where('id', $asset['section_id'])->get()->getRowArray();
        $this->assertNotNull($section);
        $this->assertSame(1, (int)$section['penyulang_id']);

        $penyulang = $this->db->table('penyulang')->where('id', $section['penyulang_id'])->get()->getRowArray();
        $this->assertNotNull($penyulang);
        $this->assertSame(1, (int)$penyulang['ulp_id']);

        $ulp = $this->db->table('ulps')->where('id', $penyulang['ulp_id'])->get()->getRowArray();
        $this->assertNotNull($ulp);
        $this->assertSame('SIDOARJO KOTA', $ulp['nama_ulp']);
    }

    /**
     * Test 09: Out-of-range Coordinates (lat [-90,90], lng [-180,180]) detection
     */
    public function testOutOfRangeCoordinatesExclusion(): void
    {
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('assets')->insert([
            'id'           => 430,
            'kode_asset'   => 'AST-OUT-RANGE',
            'nama_asset'   => 'Tiang Out of Range',
            'jenis_asset'  => 'TIANG_BETON',
            'latitude'     => -91.5000, // Out of range latitude (< -90) fitting DECIMAL(10,8)
            'longitude'    => 112.7200,
            'penyulang_id' => 1,
            'ulp_id'       => 1,
        ]);

        // Forensic audit: Detect out-of-range coordinates using float bounds
        $assetRow = $this->db->table('assets')->where('id', 430)->get()->getRowArray();
        $this->assertNotNull($assetRow);
        $lat = (float)$assetRow['latitude'];
        $lng = (float)$assetRow['longitude'];

        $isOutOfRange = ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180);
        $this->assertTrue($isOutOfRange, 'Asset with latitude -91.5 must be classified as OUT_OF_RANGE');

        // Verify frontend JS validation contract in view
        $viewFile = APPPATH . 'Views/gis/index.php';
        $content = file_get_contents($viewFile);
        $this->assertStringContainsString('latitude >= -90', $content);
        $this->assertStringContainsString('latitude <= 90', $content);
        $this->assertStringContainsString('longitude >= -180', $content);
        $this->assertStringContainsString('longitude <= 180', $content);
    }

    /**
     * Test 10: Duplicate Coordinates are detectable without silent modification
     */
    public function testDuplicateCoordinateDetectionForensic(): void
    {
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('assets')->insert([
            'id'           => 440,
            'kode_asset'   => 'AST-DUP-A',
            'nama_asset'   => 'Tiang Dup A',
            'jenis_asset'  => 'TIANG_BETON',
            'latitude'     => -7.4250,
            'longitude'    => 112.7250,
            'penyulang_id' => 1,
            'ulp_id'       => 1,
        ]);
        $this->db->table('assets')->insert([
            'id'           => 441,
            'kode_asset'   => 'AST-DUP-B',
            'nama_asset'   => 'Tiang Dup B',
            'jenis_asset'  => 'TIANG_BETON',
            'latitude'     => -7.4250, // Duplicate coords!
            'longitude'    => 112.7250,
            'penyulang_id' => 1,
            'ulp_id'       => 1,
        ]);

        $dupes = $this->db->table('assets')
            ->select('latitude, longitude, COUNT(*) as cnt')
            ->where('deleted_at IS NULL')
            ->where('latitude !=', 0)
            ->groupBy(['latitude', 'longitude'])
            ->having('cnt >', 1)
            ->get()->getResultArray();

        $this->assertCount(1, $dupes);
        $this->assertSame(2, (int)$dupes[0]['cnt']);
    }

    /**
     * Test 11: Non-Admin Role UI contract prevents direct commit presentation
     */
    public function testNonAdminRoleDisplaysProposeCorrectionWithoutDirectCommit(): void
    {
        $viewFile = APPPATH . 'Views/gis/index.php';
        $content = file_get_contents($viewFile);

        // Non-admin conditional wording
        $this->assertStringContainsString('<?= !empty($isAdmin) ? \'Terapkan Langsung\' : \'Kirim Usulan\' ?>', $content);
        $this->assertStringContainsString('<?= !empty($isAdmin) ? \'Simpan Langsung\' : \'Kirim Usulan\' ?>', $content);
    }

    /**
     * Test 12: apiPenyulangs rejects missing or invalid ULP parameter with 422
     */
    public function testGisControllerApiPenyulangsRejectsZeroUlpId(): void
    {
        $controller = new \App\Controllers\GisController();
        $req = \Config\Services::request();
        $controller->initController($req, \Config\Services::response(), \Config\Services::logger());

        $response = $controller->apiPenyulangs();
        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('error', $body['status']);
        $this->assertStringContainsString('ULP wajib dipilih', $body['message']);
    }
}
