<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use App\Repositories\TemuanRepository;
use App\Services\AssetContextService;
use Config\Database;

/**
 * CR-HOTFIX-03: Temuan Network Asset Context Unit Test
 *
 * Verifies that:
 * 1. TemuanRepository::getDetail() joins network asset context (kode, nama, jenis, coordinates).
 * 2. TemuanRepository::getDataTables() provides asset columns and supports asset_id filtering.
 * 3. AssetContextService provides active/total findings count and view_temuan_url.
 * 4. All read operations guarantee zero database mutation (Delta-DB = 0).
 *
 * @internal
 */
final class TemuanNetworkAssetContextTest extends CIUnitTestCase
{
    private TemuanRepository $repository;
    private AssetContextService $assetContextService;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $forge = Database::forge();

        // Drop tables to guarantee fresh test schema
        $forge->dropTable('temuan', true);
        $forge->dropTable('assets', true);
        $forge->dropTable('sections', true);
        $forge->dropTable('penyulang', true);
        $forge->dropTable('ulps', true);
        $forge->dropTable('construction_types', true);
        $forge->dropTable('users', true);

        // 1. Users table
        $forge->addField([
            'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'nama'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'username' => ['type' => 'VARCHAR', 'constraint' => 50],
        ]);
        $forge->createTable('users', true);

        // 2. Ulps table
        $forge->addField([
            'id'        => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'kode_ulp'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'nama_ulp'  => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $forge->createTable('ulps', true);

        // 3. Penyulang table
        $forge->addField([
            'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            'ulp_id'         => ['type' => 'INTEGER', 'null' => true],
        ]);
        $forge->createTable('penyulang', true);

        // 4. Sections table
        $forge->addField([
            'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            'penyulang_id' => ['type' => 'INTEGER', 'null' => true],
        ]);
        $forge->createTable('sections', true);

        // 5. Construction types table
        $forge->addField([
            'id'                => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'construction_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'construction_name' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $forge->createTable('construction_types', true);

        // 6. Assets table
        $forge->addField([
            'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG'],
            'ulp_id'               => ['type' => 'INTEGER', 'null' => true],
            'penyulang_id'         => ['type' => 'INTEGER', 'null' => true],
            'section_id'           => ['type' => 'INTEGER', 'null' => true],
            'construction_type_id' => ['type' => 'INTEGER', 'null' => true],
            'latitude'             => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('assets', true);

        // 7. Temuan table
        $forge->addField([
            'id'                  => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'nomor_temuan'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'ulp_id'              => ['type' => 'INTEGER', 'null' => true],
            'penyulang_id'        => ['type' => 'INTEGER', 'null' => true],
            'section_id'          => ['type' => 'INTEGER', 'null' => true],
            'asset_id'            => ['type' => 'INTEGER', 'null' => true],
            'jenis_temuan'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'prioritas'           => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MEDIUM'],
            'pelaksana'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'potensi_gangguan'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'BELUM'],
            'tanggal_temuan'      => ['type' => 'DATE', 'null' => true],
            'tanggal_selesai'     => ['type' => 'DATE', 'null' => true],
            'target_penyelesaian' => ['type' => 'DATE', 'null' => true],
            'detail_temuan'       => ['type' => 'TEXT', 'null' => true],
            'foto'                => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'foto_path'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deskripsi'           => ['type' => 'TEXT', 'null' => true],
            'latitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'longitude'           => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'created_by'          => ['type' => 'INTEGER', 'null' => true],
            'updated_by'          => ['type' => 'INTEGER', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('temuan', true);

        // Seed reference data
        $this->db->table('users')->insert([
            'id'       => 1,
            'nama'     => 'System Admin',
            'username' => 'admin',
        ]);

        $this->db->table('ulps')->insert([
            'id'       => 1,
            'kode_ulp' => '51301',
            'nama_ulp' => 'ULP SIDOARJO KOTA',
        ]);

        $this->db->table('penyulang')->insert([
            'id'             => 10,
            'nama_penyulang' => 'PUCANG',
            'ulp_id'         => 1,
        ]);

        $this->db->table('sections')->insert([
            'id'           => 100,
            'nama_section' => 'SEC-PUCANG-01',
            'penyulang_id' => 10,
        ]);

        $this->db->table('assets')->insert([
            'id'           => 301,
            'kode_asset'   => 'AST-CTX-301',
            'nama_asset'   => 'Tiang Uji Konteks Aset',
            'jenis_asset'  => 'TIANG_BETON',
            'ulp_id'       => 1,
            'penyulang_id' => 10,
            'section_id'   => 100,
            'latitude'     => -7.447812,
            'longitude'    => 112.718324,
            'status'       => 'NORMAL',
            'deleted_at'   => null,
        ]);

        $this->db->table('temuan')->insert([
            'id'             => 401,
            'nomor_temuan'   => 'TMN-CTX-401',
            'ulp_id'         => 1,
            'penyulang_id'   => 10,
            'section_id'     => 100,
            'asset_id'       => 301,
            'jenis_temuan'   => 'ANOMALI_JARINGAN',
            'prioritas'      => 'HIGH',
            'status'         => 'BELUM',
            'tanggal_temuan' => date('Y-m-d'),
            'detail_temuan'  => 'Temuan terikat pada asset 301',
            'deskripsi'      => 'Temuan terikat pada asset 301',
            'latitude'       => -7.447812,
            'longitude'      => 112.718324,
            'deleted_at'     => null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->repository = new TemuanRepository();
        $this->assetContextService = new AssetContextService($this->db);
    }

    /**
     * Test getDetail() includes authoritative asset information when finding has asset_id.
     */
    public function testGetDetailIncludesAuthoritativeNetworkAssetContext(): void
    {
        $detail = $this->repository->getDetail(401);
        $this->assertNotNull($detail);
        $this->assertEquals(401, $detail['id']);

        // Check asset fields presence
        $this->assertArrayHasKey('asset_id', $detail);
        $this->assertArrayHasKey('nama_asset', $detail);
        $this->assertArrayHasKey('kode_asset', $detail);
        $this->assertArrayHasKey('jenis_asset', $detail);
        $this->assertArrayHasKey('asset_latitude', $detail);
        $this->assertArrayHasKey('asset_longitude', $detail);

        $this->assertEquals('Tiang Uji Konteks Aset', $detail['nama_asset']);
        $this->assertEquals('AST-CTX-301', $detail['kode_asset']);
        $this->assertEquals(-7.447812, (float)$detail['asset_latitude']);
        $this->assertEquals(112.718324, (float)$detail['asset_longitude']);
    }

    /**
     * Test getDataTables() includes asset columns and handles asset_id filter.
     */
    public function testGetDataTablesWithAssetFilter(): void
    {
        $requestData = [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'asset_id' => 301,
        ];

        $dtResult = $this->repository->getDataTables($requestData);
        $this->assertIsArray($dtResult);
        $this->assertArrayHasKey('data', $dtResult);
        $this->assertArrayHasKey('recordsFiltered', $dtResult);
        $this->assertGreaterThanOrEqual(1, $dtResult['recordsFiltered']);

        // Every row in the filtered result must belong to the specified asset_id
        foreach ($dtResult['data'] as $row) {
            $this->assertEquals(301, (int) $row['asset_id']);
            $this->assertArrayHasKey('nama_asset', $row);
            $this->assertArrayHasKey('kode_asset', $row);
        }
    }

    /**
     * Test AssetContextService returns active_findings_count and view_temuan_url.
     */
    public function testAssetContextIncludesFindingsCountAndNavigation(): void
    {
        $context = $this->assetContextService->getAssetContext(301);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('asset', $context);
        $this->assertArrayHasKey('navigation', $context);

        $asset = $context['asset'];
        $this->assertArrayHasKey('active_findings_count', $asset);
        $this->assertArrayHasKey('total_findings_count', $asset);
        $this->assertEquals(1, $asset['active_findings_count']);
        $this->assertEquals(1, $asset['total_findings_count']);

        $nav = $context['navigation'];
        $this->assertArrayHasKey('create_temuan_url', $nav);
        $this->assertArrayHasKey('view_temuan_url', $nav);
        $this->assertStringContainsString('asset_id=301', $nav['view_temuan_url']);
    }

    /**
     * Test invariant: repository read queries cause zero database modifications.
     */
    public function testZeroDbWritesOnRepositoryReads(): void
    {
        $countsBefore = [
            'temuan' => $this->db->table('temuan')->countAllResults(),
            'assets' => $this->db->table('assets')->countAllResults(),
        ];

        $this->repository->getDataTables(['draw' => 1, 'start' => 0, 'length' => 10]);
        $this->repository->getDetail(401);

        $countsAfter = [
            'temuan' => $this->db->table('temuan')->countAllResults(),
            'assets' => $this->db->table('assets')->countAllResults(),
        ];

        $this->assertEquals($countsBefore, $countsAfter, 'Read queries must not alter database state.');
    }

    protected function tearDown(): void
    {
        $forge = Database::forge();
        $forge->dropTable('temuan', true);
        $forge->dropTable('assets', true);
        $forge->dropTable('sections', true);
        $forge->dropTable('penyulang', true);
        $forge->dropTable('ulps', true);
        $forge->dropTable('construction_types', true);
        $forge->dropTable('users', true);

        parent::tearDown();
    }
}
