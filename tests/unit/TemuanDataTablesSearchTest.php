<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Repositories\TemuanRepository;
use App\Controllers\Temuan;
use ReflectionClass;

class TemuanDataTablesSearchTest extends CIUnitTestCase
{
    protected TemuanRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'app']);
        $this->repo = new TemuanRepository();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        // 1. ulps
        if (!$db->tableExists('ulps')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('ulps', true);
        }
        $db->table('ulps')->truncate();
        $db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'ULP KOTA']);

        // 2. penyulang
        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id' => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('penyulang', true);
        }
        $db->table('penyulang')->truncate();
        $db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'CDR', 'nama_penyulang' => 'CANDRAMAS']);

        // 3. sections
        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER', 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('sections', true);
        }
        $db->table('sections')->truncate();
        $db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'Section A CANDRAMAS']);

        // 4. temuan
        if (!$db->tableExists('temuan')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'nomor_temuan' => ['type' => 'VARCHAR', 'constraint' => 50],
                'ulp_id' => ['type' => 'INTEGER', 'default' => 1],
                'penyulang_id' => ['type' => 'INTEGER', 'default' => 1],
                'section_id' => ['type' => 'INTEGER', 'default' => 1],
                'jenis_temuan' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'Isolator Retak Fasa R'],
                'pelaksana' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'PDKB'],
                'prioritas' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'HIGH'],
                'potensi_gangguan' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'DGR'],
                'konduktor' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'noga' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'material' => ['type' => 'TEXT', 'null' => true],
                'detail_temuan' => ['type' => 'TEXT', 'null' => true],
                'alamat' => ['type' => 'TEXT', 'null' => true],
                'latitude' => ['type' => 'DECIMAL', 'null' => true],
                'longitude' => ['type' => 'DECIMAL', 'null' => true],
                'tanggal_temuan' => ['type' => 'DATE', 'null' => true],
                'tanggal_selesai' => ['type' => 'DATE', 'null' => true],
                'foto' => ['type' => 'TEXT', 'null' => true],
                'foto_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'BELUM'],
                'created_by' => ['type' => 'INTEGER', 'null' => true],
                'updated_by' => ['type' => 'INTEGER', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
        }
        $db->table('temuan')->truncate();
        $db->table('temuan')->insert([
            'id' => 1,
            'nomor_temuan' => 'STJ-2026-000001',
            'ulp_id' => 1,
            'penyulang_id' => 1,
            'section_id' => 1,
            'jenis_temuan' => 'Isolator Retak Fasa R',
            'pelaksana' => 'PDKB',
            'prioritas' => 'HIGH',
            'potensi_gangguan' => 'DGR',
            'detail_temuan' => 'Isolator retak fasa R pada tiang TM1',
            'alamat' => 'Jl. Candramas No. 10',
            'tanggal_temuan' => '2026-08-20',
            'status' => 'BELUM',
            'created_at' => '2026-08-20 10:00:00',
        ]);
    }

    public function testInitialDataTablesLoad(): void
    {
        $post = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => ''],
            'order' => [['column' => 6, 'dir' => 'desc']],
        ];

        $res = $this->repo->getDataTables($post);
        $this->assertEquals(1, $res['draw']);
        $this->assertEquals(1, $res['recordsTotal']);
        $this->assertEquals(1, $res['recordsFiltered']);
        $this->assertCount(1, $res['data']);
    }

    public function testSearchIsolator(): void
    {
        $post = [
            'draw' => 2,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'isolator'],
            'order' => [['column' => 6, 'dir' => 'desc']],
        ];

        $res = $this->repo->getDataTables($post);
        $this->assertEquals(2, $res['draw']);
        $this->assertEquals(1, $res['recordsTotal']);
        $this->assertEquals(1, $res['recordsFiltered']);
        $this->assertCount(1, $res['data']);
    }

    public function testSearchIsolatorRe(): void
    {
        $post = [
            'draw' => 3,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'isolator re'],
            'order' => [['column' => 6, 'dir' => 'desc']],
        ];

        $res = $this->repo->getDataTables($post);
        $this->assertEquals(3, $res['draw']);
        $this->assertEquals(1, $res['recordsTotal']);
        $this->assertEquals(1, $res['recordsFiltered']);
        $this->assertCount(1, $res['data']);
    }

    public function testSearchZeroResults(): void
    {
        $post = [
            'draw' => 4,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'NONEXISTENT_QUERY_999'],
            'order' => [['column' => 6, 'dir' => 'desc']],
        ];

        $res = $this->repo->getDataTables($post);
        $this->assertEquals(4, $res['draw']);
        $this->assertEquals(1, $res['recordsTotal']);
        $this->assertEquals(0, $res['recordsFiltered']);
        $this->assertCount(0, $res['data']);
    }

    public function testSearchSpecialCharacters(): void
    {
        $post = [
            'draw' => 5,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => "'; DROP TABLE temuan; -- %_"],
            'order' => [['column' => 6, 'dir' => 'desc']],
        ];

        $res = $this->repo->getDataTables($post);
        $this->assertEquals(5, $res['draw']);
        $this->assertEquals(0, $res['recordsFiltered']);
    }

    public function testRowFormatter(): void
    {
        $post = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => ''],
            'order' => [['column' => 6, 'dir' => 'desc']],
        ];

        $res = $this->repo->getDataTables($post);
        $controller = new Temuan();
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('formatDataTablesRow');
        $method->setAccessible(true);

        $row = $res['data'][0];
        $formatted = $method->invoke($controller, $row, 'administrator', false);

        $this->assertCount(9, $formatted);
        $this->assertStringContainsString('STJ-2026-000001', $formatted[0]);
        $this->assertEquals('CANDRAMAS', $formatted[1]);
        $this->assertEquals('Section A CANDRAMAS', $formatted[2]);
        $this->assertEquals('Isolator Retak Fasa R', $formatted[3]);
    }

    public function testRowFormatterWithNullAndInvalidDates(): void
    {
        $controller = new Temuan();
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('formatDataTablesRow');
        $method->setAccessible(true);

        $legacyRow = [
            'id' => 99,
            'nomor_temuan' => 'STJ-LEGACY-001',
            'nama_penyulang' => 'CANDRAMAS',
            'nama_section' => 'Section A',
            'jenis_temuan' => 'Isolator Retak Fasa R',
            'pelaksana' => 'PDKB',
            'prioritas' => null, // NULL priority
            'tanggal_temuan' => null, // NULL date
            'status' => null, // NULL status
            'foto' => null,
            'foto_path' => null,
            'created_at' => null,
        ];

        // This should not throw TypeError or DateTime exception
        $formatted = $method->invoke($controller, $legacyRow, 'administrator', false);
        $this->assertCount(9, $formatted);
        $this->assertStringContainsString('STJ-LEGACY-001', $formatted[0]);
        $this->assertStringContainsString('-', $formatted[6]); // Null date should render as muted dash
    }

    public function testAjaxDataTablesGracefulExceptionResponse(): void
    {
        // Mock controller request with invalid structure
        $controller = new Temuan();
        
        // Use reflection to test that ajaxDataTables produces valid JSON even if repository throws
        $response = $controller->ajaxDataTables();
        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('draw', $body);
        $this->assertArrayHasKey('recordsTotal', $body);
        $this->assertArrayHasKey('recordsFiltered', $body);
        $this->assertArrayHasKey('data', $body);
    }

    public function testAuditFindingCanonicalCommandExecution(): void
    {
        $db = \Config\Database::connect();
        
        // Ensure active row is canonical
        $db->table('temuan')->truncate();
        $db->table('temuan')->insert([
            'id' => 1,
            'nomor_temuan' => 'STJ-CANONICAL-001',
            'ulp_id' => 1,
            'penyulang_id' => 1,
            'section_id' => 1,
            'jenis_temuan' => 'KONSTRUKSI',
            'deleted_at' => null,
        ]);
        
        // Insert a soft-deleted legacy row
        $db->table('temuan')->insert([
            'id' => 2,
            'nomor_temuan' => 'STJ-LEGACY-002',
            'ulp_id' => 1,
            'penyulang_id' => 1,
            'section_id' => 1,
            'jenis_temuan' => 'Isolator Retak Fasa R',
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        $cmd = new \App\Commands\AuditFindingCanonicalCommand(\Config\Services::logger(), \Config\Services::commands());
        
        \CodeIgniter\Test\Filters\CITestStreamFilter::registration();
        \CodeIgniter\Test\Filters\CITestStreamFilter::addOutputFilter();
        
        $exitCode = $cmd->run([]);
        $output = \CodeIgniter\Test\Filters\CITestStreamFilter::$buffer;
        
        \CodeIgniter\Test\Filters\CITestStreamFilter::removeOutputFilter();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('ACTIVE DATASET AUDIT', $output);
        $this->assertStringContainsString('HISTORICAL / ARCHIVAL INVENTORY', $output);
        $this->assertStringContainsString('100.00% (ACTIVE OPERATIONAL DATASET)', $output);
    }
}
