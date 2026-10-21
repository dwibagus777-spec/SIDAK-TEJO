<?php

namespace Tests\Unit;

use App\Services\FaultLocatorWorkspaceService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

class UlpFeederCascadingTest extends CIUnitTestCase
{
    protected FaultLocatorWorkspaceService $service;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('tests');
        
        // Setup SQLite test schema with prefix awareness
        $ulpsTable = $this->db->prefixTable('ulps');
        $feederTable = $this->db->prefixTable('penyulang');

        $this->db->query("DROP TABLE IF EXISTS {$ulpsTable}");
        $this->db->query("CREATE TABLE {$ulpsTable} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kode_ulp VARCHAR(20),
            nama_ulp VARCHAR(100),
            status VARCHAR(20) DEFAULT 'AKTIF'
        )");

        $this->db->query("DROP TABLE IF EXISTS {$feederTable}");
        $this->db->query("CREATE TABLE {$feederTable} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ulp_id INTEGER,
            kode_penyulang VARCHAR(50),
            nama_penyulang VARCHAR(100),
            status VARCHAR(20) DEFAULT 'AKTIF'
        )");

        // Seed ULPs (IDs: 1=Sidoarjo Kota, 2=Krian, 3=Porong)
        $this->db->table('ulps')->insertBatch([
            ['id' => 1, 'kode_ulp' => '51180', 'nama_ulp' => 'ULP Sidoarjo Kota', 'status' => 'AKTIF'],
            ['id' => 2, 'kode_ulp' => '51181', 'nama_ulp' => 'ULP Krian', 'status' => 'AKTIF'],
            ['id' => 3, 'kode_ulp' => '51182', 'nama_ulp' => 'ULP Porong', 'status' => 'AKTIF'],
        ]);

        // Seed Feeders for Krian (ulp_id = 2)
        $this->db->table('penyulang')->insertBatch([
            ['id' => 46, 'ulp_id' => 2, 'kode_penyulang' => '5130-001', 'nama_penyulang' => 'ADI PRIMA 1', 'status' => 'AKTIF'],
            ['id' => 47, 'ulp_id' => 2, 'kode_penyulang' => '5130-003', 'nama_penyulang' => 'ADI PRIMA 2', 'status' => 'AKTIF'],
            ['id' => 61, 'ulp_id' => 2, 'kode_penyulang' => '5130-031', 'nama_penyulang' => 'ALAM PESONA', 'status' => 'AKTIF'],
        ]);

        // Seed Feeders for Porong (ulp_id = 3)
        $this->db->table('penyulang')->insertBatch([
            ['id' => 91, 'ulp_id' => 3, 'kode_penyulang' => '5130-091', 'nama_penyulang' => 'BULANG', 'status' => 'AKTIF'],
            ['id' => 101, 'ulp_id' => 3, 'kode_penyulang' => '5130-101', 'nama_penyulang' => 'GEMPOLSARI', 'status' => 'AKTIF'],
            ['id' => 98, 'ulp_id' => 3, 'kode_penyulang' => '5130-098', 'nama_penyulang' => 'PERMISAN', 'status' => 'AKTIF'],
            ['id' => 96, 'ulp_id' => 3, 'kode_penyulang' => '5130-096', 'nama_penyulang' => 'GLAGAHARUM', 'status' => 'AKTIF'],
        ]);

        // Seed Feeders for Sidoarjo Kota (ulp_id = 1)
        $this->db->table('penyulang')->insertBatch([
            ['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => '5130-002', 'nama_penyulang' => 'BANJAR KEMANTREN', 'status' => 'AKTIF'],
            ['id' => 50, 'ulp_id' => 1, 'kode_penyulang' => '5130-050', 'nama_penyulang' => 'KAHURIPAN', 'status' => 'AKTIF'],
            ['id' => 51, 'ulp_id' => 1, 'kode_penyulang' => '5130-051', 'nama_penyulang' => 'SAMALEAK', 'status' => 'AKTIF'],
        ]);

        $this->service = new FaultLocatorWorkspaceService(null, $this->db);
    }

    public function testResolveUlpIdWithVariousIdentifiers()
    {
        // 1. By primary key ID
        $this->assertSame(1, $this->service->resolveUlpId(1));
        $this->assertSame(2, $this->service->resolveUlpId(2));
        $this->assertSame(3, $this->service->resolveUlpId(3));

        // 2. By kode_ulp
        $this->assertSame(1, $this->service->resolveUlpId('51180'));
        $this->assertSame(2, $this->service->resolveUlpId('51181'));
        $this->assertSame(3, $this->service->resolveUlpId('51182'));

        // 3. By normalized nama_ulp
        $this->assertSame(3, $this->service->resolveUlpId('ULP Porong'));
        $this->assertSame(3, $this->service->resolveUlpId('porong'));
        $this->assertSame(2, $this->service->resolveUlpId('Krian'));
        $this->assertSame(1, $this->service->resolveUlpId('sidoarjo kota'));

        // 4. Invalid identifiers
        $this->assertFalse($this->service->resolveUlpId(99999));
        $this->assertFalse($this->service->resolveUlpId('ULP_FIKTIF'));
        $this->assertFalse($this->service->resolveUlpId(''));
        $this->assertFalse($this->service->resolveUlpId(null));
    }

    public function testGetFeedersForUlpPorongExclusively()
    {
        $feeders = $this->service->getFeeders(3);
        $this->assertNotEmpty($feeders, 'ULP Porong must have registered feeders.');
        $this->assertCount(4, $feeders);

        $feederNames = array_column($feeders, 'nama_penyulang');
        $this->assertContains('BULANG', $feederNames);
        $this->assertContains('GEMPOLSARI', $feederNames);
        $this->assertContains('PERMISAN', $feederNames);
        $this->assertContains('GLAGAHARUM', $feederNames);

        // Strict isolation: must NEVER contain ADI PRIMA 1 (which belongs to Krian)
        $this->assertNotContains('ADI PRIMA 1', $feederNames, 'Porong feeder list MUST NEVER contain ADI PRIMA 1!');
        $this->assertNotContains('BANJAR KEMANTREN', $feederNames, 'Porong feeder list MUST NOT contain Sidoarjo Kota feeders!');

        foreach ($feeders as $f) {
            $this->assertEquals(3, (int)$f['ulp_id'], "Feeder {$f['nama_penyulang']} must have ulp_id 3.");
        }
    }

    public function testGetFeedersForUlpKrianExclusively()
    {
        $feeders = $this->service->getFeeders(2);
        $this->assertNotEmpty($feeders);
        $this->assertCount(3, $feeders);

        $feederNames = array_column($feeders, 'nama_penyulang');
        $this->assertContains('ADI PRIMA 1', $feederNames);
        $this->assertNotContains('BULANG', $feederNames);
        $this->assertNotContains('BANJAR KEMANTREN', $feederNames);

        foreach ($feeders as $f) {
            $this->assertEquals(2, (int)$f['ulp_id']);
        }
    }

    public function testGetFeedersForUlpSidoarjoKotaExclusively()
    {
        $feeders = $this->service->getFeeders(1);
        $this->assertNotEmpty($feeders);
        $this->assertCount(3, $feeders);

        $feederNames = array_column($feeders, 'nama_penyulang');
        $this->assertContains('BANJAR KEMANTREN', $feederNames);
        $this->assertContains('KAHURIPAN', $feederNames);
        $this->assertContains('SAMALEAK', $feederNames);
        $this->assertNotContains('ADI PRIMA 1', $feederNames);
        $this->assertNotContains('BULANG', $feederNames);

        foreach ($feeders as $f) {
            $this->assertEquals(1, (int)$f['ulp_id']);
        }
    }

    public function testInvalidUlpReturnsEmptyNeverDefaults()
    {
        $feeders = $this->service->getFeeders(99999);
        $this->assertIsArray($feeders);
        $this->assertEmpty($feeders, 'Invalid ULP must return empty array, never default or all feeders.');

        $feedersStr = $this->service->getFeeders('NONEXISTENT');
        $this->assertIsArray($feedersStr);
        $this->assertEmpty($feedersStr);
    }

    public function testControllerFeedersByUlpResponses()
    {
        $controller = new \App\Controllers\FaultLocatorController();
        $controller->initController(\Config\Services::request(), \Config\Services::response(), new \Psr\Log\NullLogger());
        
        // Inject service with test DB
        $prop = new \ReflectionProperty($controller, 'service');
        $prop->setAccessible(true);
        $prop->setValue($controller, $this->service);

        // 1. Query Porong by ID
        $resPorong = $controller->feedersByUlp(3);
        $jsonPorong = json_decode($resPorong->getBody(), true);
        $this->assertSame('success', $jsonPorong['status']);
        $this->assertSame(3, $jsonPorong['ulp_id']);
        $this->assertSame(4, $jsonPorong['count']);
        $this->assertContains('BULANG', array_column($jsonPorong['data'], 'nama_penyulang'));
        $this->assertNotContains('ADI PRIMA 1', array_column($jsonPorong['data'], 'nama_penyulang'));

        // 2. Query Porong by Code
        $resCode = $controller->feedersByUlp('51182');
        $jsonCode = json_decode($resCode->getBody(), true);
        $this->assertSame('success', $jsonCode['status']);
        $this->assertSame(3, $jsonCode['ulp_id']);

        // 3. Query Porong by Name
        $resName = $controller->feedersByUlp('porong');
        $jsonName = json_decode($resName->getBody(), true);
        $this->assertSame('success', $jsonName['status']);
        $this->assertSame(3, $jsonName['ulp_id']);

        // 4. Invalid ULP
        $resInvalid = $controller->feedersByUlp('ULP_TIDAK_ADA');
        $jsonInvalid = json_decode($resInvalid->getBody(), true);
        $this->assertSame('error', $jsonInvalid['status']);
        $this->assertSame(404, $resInvalid->getStatusCode());
        $this->assertEmpty($jsonInvalid['data']);

        // 5. Empty ULP
        $resEmpty = $controller->feedersByUlp('');
        $jsonEmpty = json_decode($resEmpty->getBody(), true);
        $this->assertSame('error', $jsonEmpty['status']);
        $this->assertSame(400, $resEmpty->getStatusCode());
    }
}
