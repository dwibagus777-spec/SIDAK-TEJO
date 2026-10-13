<?php

namespace Tests\Unit;

use App\Services\LocationContextService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * MAP-03: Location Context Assistant Dedicated Test Suite
 *
 * Guaranteed Invariants:
 * - Deterministic resolution: GPS -> Nearest Asset -> Section -> Feeder -> ULP
 * - Strict Read-Only: 0 Operational Mutations
 * - No Silent Asset Binding: nearest_asset is an advisory suggestion only
 * - Quality Classification: <=20m HIGH, <=50m ACCEPTABLE, >50m LOW_ACCURACY
 * - Scoped Authorization: Admin ULP restrictions enforced server-side
 */
class GisLocationContextAssistantTest extends CIUnitTestCase
{
    protected $db;
    protected LocationContextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->service = new LocationContextService($this->db);
        $this->setupSchema();
    }

    protected function setupSchema(): void
    {
        $forge = \Config\Database::forge();

        // 1. ulps table
        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('ulps', true);
        }
        try {
            if (!$this->db->fieldExists('kode_ulp', 'ulps')) {
                $forge->addColumn('ulps', ['kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]]);
            }
        } catch (\Throwable $e) {}
        try {
            if (!$this->db->fieldExists('status', 'ulps')) {
                $forge->addColumn('ulps', ['status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF']]);
            }
        } catch (\Throwable $e) {}

        // 2. penyulang table
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

        // 4. assets table
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Aset Test'],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'parent_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'lokasi'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        try {
            if (!$this->db->fieldExists('lokasi', 'assets')) {
                $forge->addColumn('assets', ['lokasi' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]]);
            }
        } catch (\Throwable $e) {}
        try {
            if (!$this->db->fieldExists('status', 'assets')) {
                $forge->addColumn('assets', ['status' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL']]);
            }
        } catch (\Throwable $e) {}
        try {
            if (!$this->db->fieldExists('parent_asset_id', 'assets')) {
                $forge->addColumn('assets', ['parent_asset_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true]]);
            }
        } catch (\Throwable $e) {}
        try {
            if (!$this->db->fieldExists('sequence_no', 'assets')) {
                $forge->addColumn('assets', ['sequence_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 0]]);
            }
        } catch (\Throwable $e) {}

        // Delete previous test rows if present
        $this->db->table('assets')->whereIn('id', [991001, 991002])->delete();
        $this->db->table('sections')->where('id', 9920)->delete();
        $this->db->table('penyulang')->whereIn('id', [9910, 9920])->delete();
        $this->db->table('ulps')->whereIn('id', [991, 992])->delete();

        // Seed baseline test network with isolated IDs
        // ULP 991: Sidoarjo Kota, Penyulang 9910: BANJAR KEMANTREN, Section 9920: Section 2
        $this->db->table('ulps')->insert(['id' => 991, 'kode_ulp' => 'SDK', 'nama_ulp' => 'ULP Sidoarjo Kota']);
        $this->db->table('ulps')->insert(['id' => 992, 'kode_ulp' => 'PRG', 'nama_ulp' => 'ULP Porong']);

        $this->db->table('penyulang')->insert([
            'id'             => 9910,
            'ulp_id'         => 991,
            'kode_penyulang' => 'BJK',
            'nama_penyulang' => 'BANJAR KEMANTREN',
        ]);
        $this->db->table('penyulang')->insert([
            'id'             => 9920,
            'ulp_id'         => 992,
            'kode_penyulang' => 'PRG',
            'nama_penyulang' => 'PORONG JAYA',
        ]);

        $this->db->table('sections')->insert([
            'id'           => 9920,
            'penyulang_id' => 9910,
            'nama_section' => 'Section 2 Banjar',
        ]);

        // Asset AST-001 at (-7.416250, 112.723120)
        $this->db->table('assets')->insert([
            'id'           => 991001,
            'kode_asset'   => 'BANJARKEMANTRAN_38',
            'nama_asset'   => 'Tiang BJK 38',
            'jenis_asset'  => 'TIANG_BETON',
            'ulp_id'       => 991,
            'penyulang_id' => 9910,
            'section_id'   => 9920,
            'latitude'     => -7.416250,
            'longitude'    => 112.723120,
            'deleted_at'   => null,
        ]);

        // Asset AST-002 at (-7.416500, 112.723300) ~ 35 meters away
        $this->db->table('assets')->insert([
            'id'           => 991002,
            'kode_asset'   => 'BANJARKEMANTRAN_39',
            'nama_asset'   => 'Tiang BJK 39',
            'jenis_asset'  => 'TIANG_BETON',
            'ulp_id'       => 991,
            'penyulang_id' => 9910,
            'section_id'   => 9920,
            'latitude'     => -7.416500,
            'longitude'    => 112.723300,
            'deleted_at'   => null,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->db->tableExists('assets')) {
            $this->db->table('assets')->whereIn('id', [991001, 991002])->delete();
        }
        if ($this->db->tableExists('sections')) {
            $this->db->table('sections')->where('id', 9920)->delete();
        }
        if ($this->db->tableExists('penyulang')) {
            $this->db->table('penyulang')->whereIn('id', [9910, 9920])->delete();
        }
        if ($this->db->tableExists('ulps')) {
            $this->db->table('ulps')->whereIn('id', [991, 992])->delete();
        }
        parent::tearDown();
    }

    private function getFingerprint(string $table): array
    {
        if (!$this->db->tableExists($table)) {
            return ['count' => 0, 'hash' => hash('sha256', 'NO_TABLE')];
        }
        $count = (int)$this->db->table($table)->countAllResults();
        $rows = $this->db->table($table)->orderBy('id', 'ASC')->limit(100)->get()->getResultArray();
        return [
            'count' => $count,
            'hash'  => hash('sha256', json_encode($rows)),
        ];
    }

    public function testValidGpsContextResolvesNetwork()
    {
        // Operator is ~10m from AST-991001
        $res = $this->service->resolveContext(-7.416280, 112.723140, 12.5);

        $this->assertEquals(LocationContextService::STATUS_READY, $res['status']);
        $this->assertEquals(LocationContextService::QUALITY_HIGH_QUALITY, $res['gps_quality']);
        $this->assertNotNull($res['context']);
        $this->assertEquals(991, $res['context']['ulp']['id']);
        $this->assertEquals('ULP Sidoarjo Kota', $res['context']['ulp']['nama_ulp']);
        $this->assertEquals(9910, $res['context']['penyulang']['id']);
        $this->assertEquals('BANJAR KEMANTREN', $res['context']['penyulang']['nama_penyulang']);
        $this->assertEquals(9920, $res['context']['section']['id']);
        $this->assertEquals('Section 2 Banjar', $res['context']['section']['nama_section']);
    }

    public function testInvalidLatitudeRejected()
    {
        $res = $this->service->resolveContext(95.123, 112.723140, 10.0);
        $this->assertEquals(LocationContextService::STATUS_INVALID_COORDINATES, $res['status']);
        $this->assertNull($res['context']);
    }

    public function testInvalidLongitudeRejected()
    {
        $res = $this->service->resolveContext(-7.416280, 195.723140, 10.0);
        $this->assertEquals(LocationContextService::STATUS_INVALID_COORDINATES, $res['status']);
        $this->assertNull($res['context']);
    }

    public function testNullIslandRejected()
    {
        $res = $this->service->resolveContext(0.0, 0.0, 10.0);
        $this->assertEquals(LocationContextService::STATUS_INVALID_COORDINATES, $res['status']);
        $this->assertNull($res['context']);
    }

    public function testGpsUnavailableReturnsStatus()
    {
        $res = $this->service->resolveContext(null, null, null);
        $this->assertEquals(LocationContextService::STATUS_GPS_UNAVAILABLE, $res['status']);
        $this->assertNull($res['context']);
    }

    public function testLowAccuracyReturnsStatusAndWarning()
    {
        // Accuracy 65m (> 50m)
        $res = $this->service->resolveContext(-7.416280, 112.723140, 65.0);
        $this->assertEquals(LocationContextService::STATUS_LOW_ACCURACY, $res['status']);
        $this->assertEquals(LocationContextService::QUALITY_LOW_ACCURACY, $res['gps_quality']);
        $this->assertNotNull($res['context']);
    }

    public function testNoNetworkContextBeyondOneKm()
    {
        // Far away coordinates: Surabaya center (-7.2575, 112.7521) ~ 20km from Sidoarjo
        $res = $this->service->resolveContext(-7.2575, 112.7521, 10.0);
        $this->assertEquals(LocationContextService::STATUS_NO_CONTEXT, $res['status']);
        $this->assertNull($res['context']);
        $this->assertNull($res['nearest_asset']);
    }

    public function testValidUlpResolution()
    {
        $res = $this->service->resolveContext(-7.416280, 112.723140, 15.0);
        $this->assertEquals(991, $res['context']['ulp']['id']);
        $this->assertEquals('ULP Sidoarjo Kota', $res['context']['ulp']['nama_ulp']);
    }

    public function testValidFeederResolution()
    {
        $res = $this->service->resolveContext(-7.416280, 112.723140, 15.0);
        $this->assertEquals(9910, $res['context']['penyulang']['id']);
        $this->assertEquals('BJK', $res['context']['penyulang']['kode_penyulang']);
    }

    public function testValidSectionResolution()
    {
        $res = $this->service->resolveContext(-7.416280, 112.723140, 15.0);
        $this->assertEquals(9920, $res['context']['section']['id']);
        $this->assertEquals('Section 2 Banjar', $res['context']['section']['nama_section']);
    }

    public function testNearestAssetReturnedAsSuggestionOnly()
    {
        $res = $this->service->resolveContext(-7.416280, 112.723140, 10.0);
        $this->assertNotNull($res['nearest_asset']);
        $this->assertEquals(991001, $res['nearest_asset']['id']);
        $this->assertEquals('BANJARKEMANTRAN_38', $res['nearest_asset']['kode_asset']);
        $this->assertLessThan(20.0, (float)$res['nearest_asset']['distance_m']);
    }

    public function testNearestAssetDoesNotPersistAssetId()
    {
        $countBefore = $this->db->table('assets')->countAllResults();
        $res = $this->service->resolveContext(-7.416280, 112.723140, 10.0);
        $countAfter = $this->db->table('assets')->countAllResults();

        // 0 rows created, 0 rows modified
        $this->assertEquals($countBefore, $countAfter);
        $this->assertArrayHasKey('nearest_asset', $res);
    }

    public function testAuthorizationBoundaryEnforcedForAdminUlp()
    {
        // User is admin_ulp for ULP 992 (Porong), but nearest asset belongs to ULP 991 (Sidoarjo Kota)
        $res = $this->service->resolveContext(-7.416280, 112.723140, 10.0, 992, 'admin_ulp');
        $this->assertEquals(LocationContextService::STATUS_FORBIDDEN, $res['status']);
        $this->assertNull($res['context']);
    }

    public function testContextHandoffPreservesStateA()
    {
        $res = $this->service->resolveContext(-7.416280, 112.723140, 10.0);
        $this->assertEquals(LocationContextService::STATUS_READY, $res['status']);

        // Network prefill values are provided
        $prefillUlp = $res['context']['ulp']['id'];
        $prefillFeeder = $res['context']['penyulang']['id'];
        $prefillSection = $res['context']['section']['id'];

        // Invariant: asset_id is NOT returned inside context (remains purely optional suggestion)
        $this->assertArrayNotHasKey('asset_id', $res['context']);
        $this->assertEquals(991, $prefillUlp);
        $this->assertEquals(9910, $prefillFeeder);
        $this->assertEquals(9920, $prefillSection);
    }

    public function testMaterialFlowRemainsOff()
    {
        $res = $this->service->resolveContext(-7.416280, 112.723140, 10.0);
        // Guarantee response contains NO material, NO BOM, NO quantity
        $this->assertArrayNotHasKey('materials', $res);
        $this->assertArrayNotHasKey('bom', $res);
        $this->assertArrayNotHasKey('quantity', $res);
    }

    public function testZeroOperationalMutationAudit()
    {
        $tables = ['gis_translines', 'assets', 'temuan', 'temuan_materials'];
        $fpBefore = [];
        foreach ($tables as $t) {
            $fpBefore[$t] = $this->getFingerprint($t);
        }

        // Execute service multiple times
        $this->service->resolveContext(-7.416280, 112.723140, 10.0);
        $this->service->resolveContext(-7.2575, 112.7521, 5.0);
        $this->service->resolveContext(95.0, 0.0, null);

        foreach ($tables as $t) {
            $fpAfter = $this->getFingerprint($t);
            $this->assertEquals($fpBefore[$t]['count'], $fpAfter['count'], "Table {$t} row count must remain unchanged.");
            $this->assertEquals($fpBefore[$t]['hash'], $fpAfter['hash'], "Table {$t} content hash must remain identical.");
        }
    }
}
