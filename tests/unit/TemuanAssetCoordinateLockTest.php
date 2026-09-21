<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use App\Controllers\Temuan;

/**
 * CR-HOTFIX-02 Part B: Authoritative Asset Coordinate Locking Unit Test
 *
 * Verifies that:
 * 1. ajaxAssetCoordinates resolves authoritative coordinates correctly.
 * 2. An asset without coordinates returns has_coordinates = false.
 * 3. In Temuan::store(), when an asset is selected, the server authoritative
 *    coordinates override any client-submitted lat/lng.
 * 4. In Temuan::store(), an asset without coordinates is strictly rejected with
 *    "Asset belum memiliki koordinat authoritative."
 *
 * @internal
 */
final class TemuanAssetCoordinateLockTest extends CIUnitTestCase
{
    private Temuan $controller;
    protected $db;
    protected $session;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'app']);

        $this->db = \Config\Database::connect();
        $forge    = \Config\Database::forge();

        // 1. Ensure ulps table exists
        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('ulps', true);
        }
        $this->db->table('ulps')->truncate();
        $this->db->table('ulps')->insert([
            'id'       => 1,
            'kode_ulp' => '51301',
            'nama_ulp' => 'ULP SIDOARJO KOTA',
            'status'   => 'AKTIF',
        ]);

        // 2. Ensure penyulang table exists
        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('penyulang', true);
        }
        $this->db->table('penyulang')->truncate();
        $this->db->table('penyulang')->insert([
            'id'             => 1,
            'ulp_id'         => 1,
            'kode_penyulang' => 'SPJ',
            'nama_penyulang' => 'SIWALAN PANJI',
            'status'         => 'AKTIF',
        ]);

        // 3. Ensure sections table exists
        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER', 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('sections', true);
        }
        $this->db->table('sections')->truncate();
        $this->db->table('sections')->insert([
            'id'           => 1,
            'penyulang_id' => 1,
            'nama_section' => 'Section Buduran 01',
        ]);

        // 4. Ensure assets table exists
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_asset'   => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_asset'   => ['type' => 'VARCHAR', 'constraint' => 100],
                'jenis_asset'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG BETON'],
                'ulp_id'       => ['type' => 'INTEGER', 'default' => 1],
                'penyulang_id' => ['type' => 'INTEGER', 'default' => 1],
                'section_id'   => ['type' => 'INTEGER', 'default' => 1],
                'latitude'     => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'longitude'    => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'OPERASI'],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('assets', true);
        }
        $this->db->table('assets')->truncate();

        // Seed Asset 1 (With authoritative coordinates)
        $this->db->table('assets')->insert([
            'id'           => 101,
            'kode_asset'   => 'AST-SPJ-001',
            'nama_asset'   => 'Tiang Beton SPJ No 1',
            'jenis_asset'  => 'TIANG BETON',
            'ulp_id'       => 1,
            'penyulang_id' => 1,
            'section_id'   => 1,
            'latitude'     => -7.44781200,
            'longitude'    => 112.71832400,
            'status'       => 'OPERASI',
        ]);

        // Seed Asset 2 (Without coordinates / NULL)
        $this->db->table('assets')->insert([
            'id'           => 102,
            'kode_asset'   => 'AST-SPJ-002',
            'nama_asset'   => 'Tiang Beton SPJ No 2 (No Coords)',
            'jenis_asset'  => 'TIANG BETON',
            'ulp_id'       => 1,
            'penyulang_id' => 1,
            'section_id'   => 1,
            'latitude'     => null,
            'longitude'    => null,
            'status'       => 'OPERASI',
        ]);

        // 5. Ensure temuan table exists
        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'               => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'nomor_temuan'     => ['type' => 'VARCHAR', 'constraint' => 50],
                'ulp_id'           => ['type' => 'INTEGER', 'default' => 1],
                'penyulang_id'     => ['type' => 'INTEGER', 'default' => 1],
                'section_id'       => ['type' => 'INTEGER', 'default' => 1],
                'asset_id'         => ['type' => 'INTEGER', 'null' => true],
                'jenis_temuan'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'KONSTRUKSI'],
                'pelaksana'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'HAR KONSTRUKSI'],
                'prioritas'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'HIGH'],
                'potensi_gangguan' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'DGR'],
                'konduktor'        => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'A3CS 150'],
                'noga'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'material'         => ['type' => 'TEXT', 'null' => true],
                'detail_temuan'    => ['type' => 'TEXT', 'null' => true],
                'alamat'           => ['type' => 'TEXT', 'null' => true],
                'latitude'         => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'longitude'        => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'BELUM'],
                'tanggal_temuan'   => ['type' => 'DATE'],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
        }

        $this->session = Services::session();
        $this->session->set([
            'user_id'   => 1,
            'logged_in' => true,
            'user_role' => 'administrator',
        ]);

        $this->controller = new Temuan();
        $this->controller->initController(Services::request(), Services::response(), Services::logger());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $request = Services::request();
        $request->setGlobal('get', []);
        $request->setGlobal('post', []);
        $request->setGlobal('request', []);
        $_POST = [];
        $_GET = [];
    }

    /**
     * Test ajaxAssetCoordinates returns 200 and has_coordinates=true for asset with valid coordinates
     */
    public function testAjaxAssetCoordinatesReturnsAuthoritativeData(): void
    {
        $request = Services::request();
        $request->setGlobal('get', ['asset_id' => 101]);
        $this->controller->initController($request, Services::response(), Services::logger());

        $response = $this->controller->ajaxAssetCoordinates();
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('SUCCESS', $body['status'] ?? null);
        $this->assertNotNull($body['asset'] ?? null);
        $this->assertEquals(101, $body['asset']['id']);
        $this->assertEquals('AST-SPJ-001', $body['asset']['kode_asset']);
        $this->assertTrue($body['asset']['has_coordinates']);
        $this->assertEqualsWithDelta(-7.44781200, (float)$body['asset']['latitude'], 0.000001);
        $this->assertEqualsWithDelta(112.71832400, (float)$body['asset']['longitude'], 0.000001);
    }

    /**
     * Test ajaxAssetCoordinates returns has_coordinates=false for asset without coordinates
     */
    public function testAjaxAssetCoordinatesDetectsMissingCoordinates(): void
    {
        $request = Services::request();
        $request->setGlobal('get', ['asset_id' => 102]);
        $this->controller->initController($request, Services::response(), Services::logger());

        $response = $this->controller->ajaxAssetCoordinates();
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('SUCCESS', $body['status'] ?? null);
        $this->assertNotNull($body['asset'] ?? null);
        $this->assertFalse($body['asset']['has_coordinates']);
        $this->assertNull($body['asset']['latitude']);
        $this->assertNull($body['asset']['longitude']);
    }

    /**
     * Test that Temuan::create pre-populates and locks coordinates when ?asset_id=101 is passed
     */
    public function testCreateWithPreselectedAssetLocksCoordinatesInView(): void
    {
        $request = Services::request();
        $request->setGlobal('get', ['asset_id' => 101]);
        $this->controller->initController($request, Services::response(), Services::logger());

        $html = $this->controller->create();
        $this->assertIsString($html);

        // Preselected asset card must be visible and have the asset code
        $this->assertStringContainsString('AST-SPJ-001', $html);
        $this->assertStringContainsString('ASET TERPILIH (AUTHORITATIVE)', $html);
        $this->assertStringContainsString('Koordinat Terkunci', $html);
        $this->assertStringContainsString('-7.447812', $html);
        $this->assertStringContainsString('112.718324', $html);
        $this->assertStringContainsString('authoritative_asset_id', $html);
    }

    /**
     * Test that Temuan::store strictly overrides client-submitted coordinates with asset coordinates
     */
    public function testStoreWithAssetOverridesClientCoordinates(): void
    {
        $postData = [
            'ulp_id'           => 1,
            'penyulang_id'     => 1,
            'section_id'       => 1,
            'asset_id'         => 101, // Asset 101 has -7.44781200, 112.71832400
            'jenis_temuan'     => 'KONSTRUKSI',
            'pelaksana'        => 'HAR KONSTRUKSI',
            'prioritas'        => 'MEDIUM',
            'potensi_gangguan' => 'DGR',
            'konduktor'        => 'A3CS 150',
            'detail_temuan'    => 'Isolator tumpu retak dekat tiang SPJ No 1',
            'alamat'           => 'Jl. Raya Siwalanpanji No 10',
            'latitude'         => -8.99999999, // SPOOFED / CLIENT COORDINATE
            'longitude'        => 115.99999999, // SPOOFED / CLIENT COORDINATE
            'tanggal_temuan'   => date('Y-m-d'),
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $postData;
        $request = Services::request();
        $request->setGlobal('server', ['REQUEST_METHOD' => 'POST']);
        $request->setGlobal('request', $postData);
        $request->setGlobal('post', $postData);
        $request->setMethod('POST');
        $this->controller->initController($request, Services::response(), Services::logger());

        // Mock TemuanService to verify that data received by createTemuan contains authoritative coordinates
        $refProp = new \ReflectionProperty(Temuan::class, 'temuanService');
        $refProp->setAccessible(true);
        $mockService = $this->createMock(\App\Services\TemuanService::class);
        $mockService->expects($this->once())
            ->method('createTemuan')
            ->with($this->callback(function (array $data) {
                return abs((float)$data['latitude'] - (-7.44781200)) < 0.0001
                    && abs((float)$data['longitude'] - 112.71832400) < 0.0001
                    && (int)$data['asset_id'] === 101;
            }), $this->anything())
            ->willReturn(['success' => true, 'id' => 1001, 'message' => 'Temuan berhasil disimpan']);
        $refProp->setValue($this->controller, $mockService);

        $response = $this->controller->store();

        if (is_string($response)) {
            $validator = $this->controller->validator ?? null;
            $errors = $validator ? $validator->getErrors() : [];
            $this->fail('store() returned view. Errors: ' . json_encode($errors) . ' | Preview: ' . substr(strip_tags($response), 0, 300));
        }

        // Must redirect to detail on success
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);
        $this->assertStringContainsString('temuan/detail/1001', $response->getHeaderLine('Location'));
    }

    /**
     * Test that Temuan::store strictly rejects an asset without coordinates
     */
    public function testStoreWithAssetWithoutCoordinatesIsRejected(): void
    {
        $postData = [
            'ulp_id'           => 1,
            'penyulang_id'     => 1,
            'section_id'       => 1,
            'asset_id'         => 102, // Asset 102 has NULL coordinates
            'jenis_temuan'     => 'KONSTRUKSI',
            'pelaksana'        => 'HAR KONSTRUKSI',
            'prioritas'        => 'MEDIUM',
            'potensi_gangguan' => 'DGR',
            'konduktor'        => 'A3CS 150',
            'detail_temuan'    => 'Isolator tumpu retak',
            'alamat'           => 'Jl. Raya Siwalanpanji No 12',
            'latitude'         => -7.123456,
            'longitude'        => 112.123456,
            'tanggal_temuan'   => date('Y-m-d'),
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $postData;
        $request = Services::request();
        $request->setGlobal('server', ['REQUEST_METHOD' => 'POST']);
        $request->setGlobal('request', $postData);
        $request->setGlobal('post', $postData);
        $request->setMethod('POST');
        $this->controller->initController($request, Services::response(), Services::logger());

        $response = $this->controller->store();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);
        // Must flash an error regarding authoritative coordinates
        $errorFlash = $this->session->getFlashdata('error');
        $this->assertEquals('Asset belum memiliki koordinat authoritative.', $errorFlash);
    }
}
