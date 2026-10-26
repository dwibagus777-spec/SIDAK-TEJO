<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

class GisConnectTopologyScenariosTest extends CIUnitTestCase
{
    protected $db;
    protected int $feederId = 9999;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect('default');

        // Route 'tests' connection group to 'default' connection instance so controller calls use same DB
        $ref = new \ReflectionClass(\CodeIgniter\Database\Config::class);
        $prop = $ref->getProperty('instances');
        $prop->setAccessible(true);
        $instances = $prop->getValue();
        $instances['tests'] = $this->db;
        $prop->setValue(null, $instances);

        // Clean any leftover test data
        $this->db->table('gis_translines')->where('penyulang_id', $this->feederId)->delete();
        $this->db->table('gis_translines')->whereIn('source_asset_id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('gis_translines')->whereIn('target_asset_id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('asset_relationships')->whereIn('parent_asset_id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('asset_relationships')->whereIn('child_asset_id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('assets')->whereIn('id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('assets')->where('penyulang_id', $this->feederId)->delete();
        $this->db->table('penyulang')->where('id', $this->feederId)->delete();

        // Setup test feeder with valid ulp_id
        $ulp = $this->db->table('ulps')->select('id')->limit(1)->get()->getRowArray();
        $ulpId = $ulp ? (int)$ulp['id'] : 1;

        $this->db->table('penyulang')->insert([
            'id' => $this->feederId,
            'ulp_id' => $ulpId,
            'nama_penyulang' => 'TEST_FEEDER_TOPOLOGY',
            'kode_penyulang' => 'TF-9999',
            'status' => 'ACTIVE'
        ]);

        // Pole A (9901)
        $this->db->table('assets')->insert([
            'id' => 9901,
            'nama_asset' => 'TIANG_SOURCE_56',
            'kode_asset' => 'AST-TEST-56',
            'jenis_asset' => 'JTM_TIANG',
            'penyulang_id' => $this->feederId,
            'latitude' => '-7.420160',
            'longitude' => '112.724046'
        ]);

        // Pole B (9902)
        $this->db->table('assets')->insert([
            'id' => 9902,
            'nama_asset' => 'TIANG_MOUNTING_67',
            'kode_asset' => 'AST-TEST-67',
            'jenis_asset' => 'JTM_TIANG',
            'penyulang_id' => $this->feederId,
            'latitude' => '-7.419681',
            'longitude' => '112.724145'
        ]);

        // PMCB mounted on Pole B
        $this->db->table('assets')->insert([
            'id' => 9903,
            'nama_asset' => 'PMCB GUDANG BULOG TEST',
            'kode_asset' => 'AST-TEST-PMCB',
            'jenis_asset' => 'PMCB',
            'parent_asset_id' => 9902, // Mounted on Pole B
            'penyulang_id' => $this->feederId,
            'latitude' => '-7.419889',
            'longitude' => '112.724100'
        ]);

        // Pre-existing Transline between Pole A and Pole B
        $this->db->table('gis_translines')->insert([
            'id' => 99001,
            'transline_code' => 'TL-9999-9901-9902',
            'penyulang_id' => $this->feederId,
            'source_asset_id' => 9901,
            'target_asset_id' => 9902,
            'conductor_type' => 'AAAC',
            'conductor_size' => '150 mm²',
            'distance_meters' => 54.37,
            'status' => 'ACTIVE',
            'is_active' => 1
        ]);

        // Admin Session
        session()->set([
            'user_id' => 1,
            'username' => 'admin',
            'role' => 'administrator',
            'isLoggedIn' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('gis_translines')->where('penyulang_id', $this->feederId)->delete();
        $this->db->table('gis_translines')->whereIn('source_asset_id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('gis_translines')->whereIn('target_asset_id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('asset_relationships')->whereIn('parent_asset_id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('asset_relationships')->whereIn('child_asset_id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('assets')->whereIn('id', [9901, 9902, 9903, 9904, 9905])->delete();
        $this->db->table('assets')->where('penyulang_id', $this->feederId)->delete();
        $this->db->table('penyulang')->where('id', $this->feederId)->delete();
        parent::tearDown();
    }

    /**
     * Test 1: Connect Pole A (9901) -> PMCB (9903)
     * PMCB is on Pole B (9902). Transline A-B already exists.
     * Must return 200 OK with duplicate: true and existing transline, NOT 400 circular topology.
     */
    public function testConnectToMountedPmcbReturnsDuplicateTransline(): void
    {
        $a1 = $this->db->table('assets')->where('id', 9901)->get()->getRowArray();
        $a3 = $this->db->table('assets')->where('id', 9903)->get()->getRowArray();
        $this->assertNotEmpty($a1, 'A1 in test db');
        $this->assertNotEmpty($a3, 'A3 in test db');

        $req = service('request')->withMethod('POST')->setBody(json_encode([
            'penyulang_id' => $this->feederId,
            'source_asset_id' => 9901,
            'target_asset_id' => 9903,
            'conductor_type' => 'AAAC',
            'conductor_size' => '150 mm²'
        ]));
        $controller = new \App\Controllers\GisController();
        $controller->initController($req, service('response'), service('logger'));

        $res = $controller->apiConnectTopology();
        $data = json_decode($res->getBody(), true);
        $this->assertEquals(200, $res->getStatusCode(), 'Body was: ' . $res->getBody());
        $this->assertArrayHasKey('duplicate', $data, 'Body was: ' . $res->getBody());
        $this->assertTrue($data['duplicate']);
        $this->assertStringContainsString('TL-9999-9901-9902', $data['message']);
        $this->assertStringContainsString('TIANG_MOUNTING_67', $data['message']);
    }

    /**
     * Test 2: Connect Pole B (9902) -> PMCB (9903)
     * PMCB is mounted on Pole B itself.
     * Must return is_equipment_mounted: true, no transline created.
     */
    public function testConnectPoleToItsOwnMountedPmcb(): void
    {
        $req = service('request')->withMethod('POST')->setBody(json_encode([
            'penyulang_id' => $this->feederId,
            'source_asset_id' => 9902,
            'target_asset_id' => 9903,
        ]));
        $controller = new \App\Controllers\GisController();
        $controller->initController($req, service('response'), service('logger'));

        $res = $controller->apiConnectTopology();
        $this->assertEquals(200, $res->getStatusCode(), 'Response: ' . $res->getBody());

        $data = json_decode($res->getBody(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertTrue($data['is_equipment_mounted']);
        $this->assertStringContainsString('telah terpasang pada tiang tumpu', $data['message']);
    }

    /**
     * Test 3: Unresolved PMCB (no parent_asset_id / no mounting relation)
     * Must return 422 Unprocessable Entity with UNRESOLVED_MOUNTING_POLE.
     */
    public function testConnectToUnresolvedPmcbReturns422(): void
    {
        $this->db->table('assets')->insert([
            'id' => 9904,
            'nama_asset' => 'PMCB ORPHAN',
            'kode_asset' => 'AST-ORPHAN-PMCB',
            'jenis_asset' => 'PMCB',
            'parent_asset_id' => null,
            'penyulang_id' => $this->feederId,
            'latitude' => '-7.419889',
            'longitude' => '112.724100'
        ]);

        $req = service('request')->withMethod('POST')->setBody(json_encode([
            'penyulang_id' => $this->feederId,
            'source_asset_id' => 9901,
            'target_asset_id' => 9904,
        ]));
        $controller = new \App\Controllers\GisController();
        $controller->initController($req, service('response'), service('logger'));

        $res = $controller->apiConnectTopology();
        $this->assertEquals(422, $res->getStatusCode());

        $data = json_decode($res->getBody(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('UNRESOLVED_MOUNTING_POLE', $data['error_code']);
    }

    /**
     * Test 4: Genuine Circular Loop (A -> B -> C, then connect C -> A)
     * Must return 400 with CIRCULAR_TOPOLOGY and explicit cycle_path.
     */
    public function testGenuineCycleReturnsDetailedCyclePath(): void
    {
        // Pole C (9905)
        $this->db->table('assets')->insert([
            'id' => 9905,
            'nama_asset' => 'TIANG_C_9905',
            'kode_asset' => 'AST-TEST-C',
            'jenis_asset' => 'JTM_TIANG',
            'penyulang_id' => $this->feederId,
            'parent_asset_id' => 9902,
            'latitude' => '-7.419000',
            'longitude' => '112.724100'
        ]);
        $this->db->table('asset_relationships')->insert([
            'parent_asset_id' => 9902,
            'child_asset_id' => 9905,
            'is_active' => 1
        ]);
        $this->db->table('asset_relationships')->insert([
            'parent_asset_id' => 9901,
            'child_asset_id' => 9902,
            'is_active' => 1
        ]);

        $req = service('request')->withMethod('POST')->setBody(json_encode([
            'penyulang_id' => $this->feederId,
            'source_asset_id' => 9905,
            'target_asset_id' => 9901,
        ]));
        $controller = new \App\Controllers\GisController();
        $controller->initController($req, service('response'), service('logger'));

        $res = $controller->apiConnectTopology();
        $this->assertEquals(400, $res->getStatusCode());

        $data = json_decode($res->getBody(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('CIRCULAR_TOPOLOGY', $data['error_code']);
        $this->assertNotEmpty($data['cycle_path']);
        $this->assertStringContainsString('TIANG_C_9905', $data['message']);
        $this->assertStringContainsString('TIANG_SOURCE_56', $data['message']);
        $this->assertStringContainsString('Jalur loop:', $data['message']);
    }
}
