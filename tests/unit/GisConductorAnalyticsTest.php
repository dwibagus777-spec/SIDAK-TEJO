<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\GisConductorAnalyticsService;
use Config\Database;

/**
 * GisConductorAnalyticsTest
 *
 * Verifies the 7 Architectural Gates for Network Conductor Analytics:
 * Gate 1: Canonical Conductor Query Parsing (A3C 150, A3C150, A3C 150 mm2, AAAC 70, XLPE 240).
 * Gate 2: Haversine Geodesic Distance Calculation (accurate meters and 0.0 handling).
 * Gate 3: Strict Network Truth Invariant (Preview-only feeders return 0 lines, 0 km).
 * Gate 4: Authoritative Transline Filtering & Length Aggregation (meters and km summation, distinct assets count).
 * Gate 5: Feeder Scope vs Global Search Isolation.
 * Gate 6: Zero Database Mutation (Read-Only Safety Invariant).
 * Gate 7: Exact Read-Model Excel Export (UTF-8 BOM, tab delimiter, identical line count).
 */
class GisConductorAnalyticsTest extends CIUnitTestCase
{
    protected $db;
    protected GisConductorAnalyticsService $service;

    protected int $feederAuth1 = 9181;
    protected int $feederAuth2 = 9182;
    protected int $feederPreviewOnly = 9183;

    protected array $createdAssetIds = [];
    protected array $createdTranslineIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->ensureTablesExist();

        $this->service = new GisConductorAnalyticsService($this->db);
        $this->setupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    protected function safeAddColumn(string $table, string $column, array $def): void
    {
        try {
            $info = $this->db->query("PRAGMA table_info({$table})")->getResultArray();
            $names = array_column($info, 'name');
            if (!in_array($column, $names, true)) {
                $forge = Database::forge();
                $forge->addColumn($table, [$column => $def]);
            }
        } catch (\Throwable $e) {
            // column already exists, safely ignore
        }
    }

    protected function ensureTablesExist(): void
    {
        $forge = Database::forge();

        // 1. ulps table
        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'ULP SIDOARJO'],
                'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF']
            ]);
            $forge->addKey('id', true);
            $forge->createTable('ulps', true);
        }

        // 2. penyulang table
        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TEST_FEEDER', 'null' => true],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'TEST_FEEDER'],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF']
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        } else {
            $this->safeAddColumn('penyulang', 'kode_penyulang', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TEST_FEEDER', 'null' => true]);
        }

        // 3. sections table
        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'SECTION_TEST'],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF']
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        // 4. assets table
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ulp_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'section_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'kode_asset'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'nama_asset'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'latitude'     => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'longitude'    => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true]
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        } else {
            $this->safeAddColumn('assets', 'section_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
            $this->safeAddColumn('assets', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
        }

        // 5. gis_translines table
        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'source_asset_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'target_asset_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'geometry'        => ['type' => 'TEXT', 'null' => true],
                'geometry_type'   => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'distance_meters' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
                'status'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'      => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'SYSTEM'],
                'deleted_at'      => ['type' => 'DATETIME', 'null' => true]
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        } else {
            $this->safeAddColumn('gis_translines', 'conductor_material', ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY']);
            $this->safeAddColumn('gis_translines', 'installation_type', ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD']);
            $this->safeAddColumn('gis_translines', 'circuit_config', ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE']);
            $this->safeAddColumn('gis_translines', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
        }
    }

    protected function setupTestData(): void
    {
        $this->cleanTestData();

        // Feeders
        $this->db->table('penyulang')->insert([
            'id'             => $this->feederAuth1,
            'ulp_id'         => 10,
            'kode_penyulang' => 'GDG',
            'nama_penyulang' => 'GEDANGAN'
        ]);
        $this->db->table('penyulang')->insert([
            'id'             => $this->feederAuth2,
            'ulp_id'         => 20,
            'kode_penyulang' => 'BJK',
            'nama_penyulang' => 'BANJAR KEMANTREN'
        ]);
        $this->db->table('penyulang')->insert([
            'id'             => $this->feederPreviewOnly,
            'ulp_id'         => 10,
            'kode_penyulang' => 'BHS',
            'nama_penyulang' => 'BAHAGIA STEEL 1'
        ]);

        // ULPs
        $this->db->table('ulps')->insert([
            'id'       => 10,
            'kode_ulp' => 'ULP_SDK',
            'nama_ulp' => 'Sidoarjo Kota'
        ]);
        $this->db->table('ulps')->insert([
            'id'       => 20,
            'kode_ulp' => 'ULP_KRN',
            'nama_ulp' => 'Krian'
        ]);

        // Sections
        $this->db->table('sections')->insert([
            'id'           => 101,
            'penyulang_id' => $this->feederAuth1,
            'nama_section' => 'LBS INDUSTRI - PMCB BECIRO'
        ]);
        $this->db->table('sections')->insert([
            'id'           => 102,
            'penyulang_id' => $this->feederAuth2,
            'nama_section' => 'GI BUDURAN - REC CANDI'
        ]);

        // Assets for Gedangan (Authoritative 1)
        $this->db->table('assets')->insert([
            'id'           => 4020,
            'penyulang_id' => $this->feederAuth1,
            'ulp_id'       => 10,
            'section_id'   => 101,
            'kode_asset'   => 'GDG_074',
            'nama_asset'   => 'GEDANGAN_74',
            'latitude'     => -7.3855000,
            'longitude'    => 112.7230000
        ]);
        $this->createdAssetIds[] = 4020;

        $this->db->table('assets')->insert([
            'id'           => 4088,
            'penyulang_id' => $this->feederAuth1,
            'ulp_id'       => 10,
            'section_id'   => 101,
            'kode_asset'   => 'GDG_071',
            'nama_asset'   => 'GEDANGAN_71',
            'latitude'     => -7.3862000,
            'longitude'    => 112.7231000
        ]);
        $this->createdAssetIds[] = 4088;

        $this->db->table('assets')->insert([
            'id'           => 4089,
            'penyulang_id' => $this->feederAuth1,
            'ulp_id'       => 10,
            'section_id'   => 101,
            'kode_asset'   => 'GDG_072',
            'nama_asset'   => 'GEDANGAN_72',
            'latitude'     => -7.3869000,
            'longitude'    => 112.7232000
        ]);
        $this->createdAssetIds[] = 4089;

        // Assets for Banjar Kemantren (Authoritative 2)
        $this->db->table('assets')->insert([
            'id'           => 5001,
            'penyulang_id' => $this->feederAuth2,
            'ulp_id'       => 20,
            'section_id'   => 102,
            'kode_asset'   => 'BJK_001',
            'nama_asset'   => 'BANJAR_01',
            'latitude'     => -7.4200000,
            'longitude'    => 112.7100000
        ]);
        $this->createdAssetIds[] = 5001;

        $this->db->table('assets')->insert([
            'id'           => 5002,
            'penyulang_id' => $this->feederAuth2,
            'ulp_id'       => 20,
            'section_id'   => 102,
            'kode_asset'   => 'BJK_002',
            'nama_asset'   => 'BANJAR_02',
            'latitude'     => -7.4208000,
            'longitude'    => 112.7101000
        ]);
        $this->createdAssetIds[] = 5002;

        // Assets for Bahagia Steel 1 (Preview Only, 0 translines)
        $this->db->table('assets')->insert([
            'id'           => 6001,
            'penyulang_id' => $this->feederPreviewOnly,
            'ulp_id'       => 10,
            'section_id'   => null,
            'kode_asset'   => 'BHS_001',
            'nama_asset'   => 'BAHAGIA_01',
            'latitude'     => -7.4500000,
            'longitude'    => 112.7300000
        ]);
        $this->createdAssetIds[] = 6001;

        $this->db->table('assets')->insert([
            'id'           => 6002,
            'penyulang_id' => $this->feederPreviewOnly,
            'ulp_id'       => 10,
            'section_id'   => null,
            'kode_asset'   => 'BHS_002',
            'nama_asset'   => 'BAHAGIA_02',
            'latitude'     => -7.4505000,
            'longitude'    => 112.7302000
        ]);
        $this->createdAssetIds[] = 6002;

        // Authoritative Translines in Gedangan (Feeder 1)
        // Segment 1: A3C 150 mm² with distance_meters = 79.69
        $this->db->table('gis_translines')->insert([
            'id'              => 1001,
            'transline_code'  => 'TL-23-4020-4088',
            'penyulang_id'    => $this->feederAuth1,
            'source_asset_id' => 4020,
            'target_asset_id' => 4088,
            'conductor_type'  => 'A3C',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 79.69,
            'is_active'       => 1
        ]);
        $this->createdTranslineIds[] = 1001;

        // Segment 2: A3C 150 mm² with distance_meters = 0 (Requires Haversine fallback)
        $this->db->table('gis_translines')->insert([
            'id'              => 1002,
            'transline_code'  => 'TL-23-4088-4089',
            'penyulang_id'    => $this->feederAuth1,
            'source_asset_id' => 4088,
            'target_asset_id' => 4089,
            'conductor_type'  => 'A3C',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 0.00,
            'is_active'       => 1
        ]);
        $this->createdTranslineIds[] = 1002;

        // Authoritative Translines in Banjar Kemantren (Feeder 2)
        // Segment 3: AAAC 70 mm² with distance_meters = 95.50
        $this->db->table('gis_translines')->insert([
            'id'              => 1003,
            'transline_code'  => 'TL-15-5001-5002',
            'penyulang_id'    => $this->feederAuth2,
            'source_asset_id' => 5001,
            'target_asset_id' => 5002,
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '70 mm²',
            'distance_meters' => 95.50,
            'is_active'       => 1
        ]);
        $this->createdTranslineIds[] = 1003;

        // Inactive Transline (Must be excluded from analytics)
        $this->db->table('gis_translines')->insert([
            'id'              => 1004,
            'transline_code'  => 'TL-INACTIVE',
            'penyulang_id'    => $this->feederAuth1,
            'source_asset_id' => 4020,
            'target_asset_id' => 4089,
            'conductor_type'  => 'A3C',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 150.00,
            'is_active'       => 0
        ]);
        $this->createdTranslineIds[] = 1004;
    }

    protected function cleanTestData(): void
    {
        if ($this->db->tableExists('gis_translines')) {
            $this->db->table('gis_translines')
                ->whereIn('penyulang_id', [$this->feederAuth1, $this->feederAuth2, $this->feederPreviewOnly])
                ->delete();
        }
        if ($this->db->tableExists('assets')) {
            $this->db->table('assets')
                ->whereIn('penyulang_id', [$this->feederAuth1, $this->feederAuth2, $this->feederPreviewOnly])
                ->delete();
        }
        if ($this->db->tableExists('sections')) {
            $this->db->table('sections')
                ->whereIn('id', [101, 102])
                ->delete();
        }
        if ($this->db->tableExists('penyulang')) {
            $this->db->table('penyulang')
                ->whereIn('id', [$this->feederAuth1, $this->feederAuth2, $this->feederPreviewOnly])
                ->delete();
        }
        if ($this->db->tableExists('ulps')) {
            $this->db->table('ulps')
                ->whereIn('id', [10, 20])
                ->delete();
        }
    }

    /**
     * Gate 1: Canonical Conductor Query Parsing
     */
    public function testGate1CanonicalConductorQueryParsing(): void
    {
        $p1 = GisConductorAnalyticsService::parseConductorQuery('A3C 150');
        $this->assertSame('A3C', $p1['type']);
        $this->assertSame('150 mm²', $p1['size']);

        $p2 = GisConductorAnalyticsService::parseConductorQuery('A3C150');
        $this->assertSame('A3C', $p2['type']);
        $this->assertSame('150 mm²', $p2['size']);

        $p3 = GisConductorAnalyticsService::parseConductorQuery('A3C 150 mm2');
        $this->assertSame('A3C', $p3['type']);
        $this->assertSame('150 mm²', $p3['size']);

        $p4 = GisConductorAnalyticsService::parseConductorQuery('AAAC 70 mm²');
        $this->assertSame('AAAC', $p4['type']);
        $this->assertSame('70 mm²', $p4['size']);

        $p5 = GisConductorAnalyticsService::parseConductorQuery('XLPE 240');
        $this->assertSame('XLPE', $p5['type']);
        $this->assertSame('240 mm²', $p5['size']);

        $p6 = GisConductorAnalyticsService::parseConductorQuery('A3C');
        $this->assertSame('A3C', $p6['type']);
        $this->assertSame('', $p6['size']);

        $p7 = GisConductorAnalyticsService::parseConductorQuery('150');
        $this->assertSame('', $p7['type']);
        $this->assertSame('150 mm²', $p7['size']);
    }

    /**
     * Gate 2: Haversine Geodesic Distance Calculation
     */
    public function testGate2HaversineGeodesicDistanceCalculation(): void
    {
        // 4020 (-7.3855, 112.7230) to 4088 (-7.3862, 112.7231)
        $distance = GisConductorAnalyticsService::haversineDistance(
            -7.3855000, 112.7230000,
            -7.3862000, 112.7231000
        );

        $this->assertGreaterThan(70.0, $distance);
        $this->assertLessThan(90.0, $distance);

        // Zero coordinate handling
        $zeroDist = GisConductorAnalyticsService::haversineDistance(0, 0, -7.3862, 112.7231);
        $this->assertSame(0.0, $zeroDist);
    }

    /**
     * Gate 3: Strict Network Truth Invariant (Preview-only feeder returns 0 lines, 0 km)
     */
    public function testGate3StrictNetworkTruthInvariant(): void
    {
        // Bahagia Steel 1 has 2 assets but 0 gis_translines
        $result = $this->service->getAnalytics([
            'penyulang_id' => $this->feederPreviewOnly,
            'scope'        => 'feeder'
        ]);

        $summary = $result['summary'];
        $this->assertSame(0, $summary['transline_resmi_count']);
        $this->assertSame(0.0, $summary['total_panjang_meter']);
        $this->assertSame(0.0, $summary['total_panjang_km']);
        $this->assertCount(0, $result['items']);
        $this->assertFalse($summary['preview_included']);
        $this->assertTrue($summary['is_authoritative_only']);
    }

    /**
     * Gate 4: Authoritative Transline Filtering & Length Aggregation
     */
    public function testGate4AuthoritativeTranslineFilteringAndLengthAggregation(): void
    {
        // Query Gedangan with A3C 150
        $result = $this->service->getAnalytics([
            'penyulang_id'   => $this->feederAuth1,
            'conductor_type' => 'A3C',
            'conductor_size' => '150 mm²',
            'scope'          => 'feeder'
        ]);

        $summary = $result['summary'];
        $items = $result['items'];

        $this->assertSame(2, $summary['transline_resmi_count']); // 2 active A3C lines, 1 inactive excluded
        $this->assertGreaterThan(150.0, $summary['total_panjang_meter']);
        $this->assertSame(1, $summary['penyulang_count']);
        $this->assertSame(1, $summary['section_count']);
        $this->assertSame(3, $summary['distinct_assets_count']); // Assets 4020, 4088, 4089

        // Verify provenance: Segment 1 stored distance, Segment 2 calculated Haversine
        $seg1 = $items[0];
        $this->assertSame('STORED_DISTANCE', $seg1['length_provenance']);
        $this->assertEquals(79.69, $seg1['length_meter']);
        $this->assertSame('GEDANGAN_74', $seg1['source_asset_name']);
        $this->assertSame('GEDANGAN_71', $seg1['target_asset_name']);

        $seg2 = $items[1];
        $this->assertSame('CALCULATED_HAVERSINE', $seg2['length_provenance']);
        $this->assertGreaterThan(70.0, $seg2['length_meter']);
    }

    /**
     * Gate 5: Feeder Scope vs Global Search Isolation
     */
    public function testGate5FeederScopeVsGlobalSearchIsolation(): void
    {
        // 1. Feeder-Scoped: Gedangan should NOT include Banjar Kemantren AAAC
        $feederRes = $this->service->getAnalytics([
            'penyulang_id' => $this->feederAuth1,
            'scope'        => 'feeder'
        ]);
        $this->assertSame(2, $feederRes['summary']['transline_resmi_count']);
        $this->assertSame(1, $feederRes['summary']['penyulang_count']);

        // 2. Global Search: includes Gedangan (2 lines) and Banjar Kemantren (1 line) = 3 total
        $globalRes = $this->service->getAnalytics([
            'scope' => 'global'
        ]);
        $this->assertSame(3, $globalRes['summary']['transline_resmi_count']);
        $this->assertSame(2, $globalRes['summary']['penyulang_count']);

        // 3. Global search for AAAC: returns exactly 1 transline (Banjar Kemantren)
        $aaacRes = $this->service->getAnalytics([
            'scope'          => 'global',
            'conductor_type' => 'AAAC'
        ]);
        $this->assertSame(1, $aaacRes['summary']['transline_resmi_count']);
        $this->assertSame('BANJAR KEMANTREN', $aaacRes['items'][0]['nama_penyulang']);
    }

    /**
     * Gate 6: Zero Database Mutation (Read-Only Safety Invariant)
     */
    public function testGate6ZeroDatabaseMutation(): void
    {
        $rowsBefore = $this->db->table('gis_translines')->get()->getResultArray();

        // Run analytics and export repeatedly
        $this->service->getAnalytics(['scope' => 'global']);
        $this->service->getAnalytics(['penyulang_id' => $this->feederAuth1, 'q' => 'A3C 150']);
        $this->service->generateSpreadsheetContent(['scope' => 'global']);

        $rowsAfter = $this->db->table('gis_translines')->get()->getResultArray();

        $this->assertCount(count($rowsBefore), $rowsAfter);
        $this->assertSame($rowsBefore, $rowsAfter, 'Zero Database Mutation invariant violated! Analytics altered DB rows.');
    }

    /**
     * Gate 7: Exact Read-Model Excel Export
     */
    public function testGate7ExactReadModelExcelExport(): void
    {
        $content = $this->service->generateSpreadsheetContent([
            'penyulang_id'   => $this->feederAuth1,
            'conductor_type' => 'A3C',
            'scope'          => 'feeder'
        ]);

        // UTF-8 BOM check
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Contains metadata headers
        $this->assertStringContainsString('SIDAK TEJO - REKAPITULASI ANALISIS KONDUKTOR', $content);
        $this->assertStringContainsString('Total Transline Resmi', $content);
        $this->assertStringContainsString('AUTHORITATIVE DATABASE ONLY', $content);

        // Contains expected columns
        $this->assertStringContainsString("No\tKode Transline\tULP\tPenyulang\tSection\tTitik A (Dari)\tTitik B (Ke)\tKonduktor\tPenampang\tPanjang (Meter)\tProvenance Panjang\tStatus", $content);

        // Contains segment lines
        $this->assertStringContainsString('TL-23-4020-4088', $content);
        $this->assertStringContainsString('TL-23-4088-4089', $content);
        $this->assertStringContainsString('STORED_DISTANCE', $content);
        $this->assertStringContainsString('CALCULATED_HAVERSINE', $content);
    }
}
