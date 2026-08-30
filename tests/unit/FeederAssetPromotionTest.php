<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FeederAssetReviewService;
use App\Services\FeederAssetPromotionService;
use App\Commands\Ar01PromoteCommand;

/**
 * Unit Tests for AR-01 Phase 5F: Controlled Master Asset Promotion
 */
class FeederAssetPromotionTest extends CIUnitTestCase
{
    protected $db;
    protected FeederAssetReviewService $reviewService;
    protected FeederAssetPromotionService $promotionService;
    protected array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        $this->ensureTablesExist($forge, $this->db);

        $this->reviewService = new FeederAssetReviewService($this->db);
        $this->promotionService = new FeederAssetPromotionService($this->db);

        // Ensure penyulang entries exist for tests
        $penyulangList = [
            ['id' => 12, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-012', 'nama_penyulang' => 'GEMURUNG'],
            ['id' => 13, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-013', 'nama_penyulang' => 'GEDANGAN'],
            ['id' => 14, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-014', 'nama_penyulang' => 'GADING KIRANA'],
            ['id' => 15, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-015', 'nama_penyulang' => 'ECCO'],
        ];
        foreach ($penyulangList as $p) {
            $exists = $this->db->table('penyulang')->where('id', $p['id'])->get()->getRowArray();
            if (!$exists) {
                $this->db->table('penyulang')->insert($p);
            }
        }
    }

    protected function createFreshCsv(): string
    {
        $uniqueId = uniqid();
        $path = WRITEPATH . "test_promo_{$uniqueId}.csv";
        $header = "UP3,ULP,Jenis Asset,Nama Asset JTM,Penyulang,Konstruksi (e.g. TM1),Material Conductor,Kapasitas / Panjang,Alamat / Lokasi,Latitude,Longitude\n";
        $row1 = "UP3 Sidoarjo,ULP Sidoarjo Kota,JTM,ECCO_{$uniqueId},ECCO,TM1,A3CS,100,Lokasi 1,-7.4501,112.7101\n";
        $row2 = "UP3 Sidoarjo,ULP Sidoarjo Kota,JTM,GEDANGAN_{$uniqueId},GEDANGAN,TM2,A3CS,100,Lokasi 2,-7.4502,112.7102\n";
        $row3 = "UP3 Sidoarjo,ULP Sidoarjo Kota,JTM,GADING_KIRANA_{$uniqueId},GADING KIRANA,GTT2T,A3CS,100,Lokasi 3,-7.4503,112.7103\n";
        file_put_contents($path, $header . $row1 . $row2 . $row3);
        $this->createdFiles[] = $path;
        return $path;
    }

    private function ensureTablesExist($forge, $db): void
    {
        if (!$db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 20],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('ulps', true);
            $db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'ULP SIDOARJO KOTA']);
        }

        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('penyulang', true);
            $db->table('penyulang')->insert([
                'id'             => 1,
                'ulp_id'         => 1,
                'kode_penyulang' => 'PYL-001',
                'nama_penyulang' => 'SIWALAN PANJI',
            ]);
        }

        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id'   => ['type' => 'INTEGER'],
                'nama_seksi'     => ['type' => 'VARCHAR', 'constraint' => 100],
                'sequence_order' => ['type' => 'INTEGER'],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
            ]);
            $forge->createTable('sections', true);
            $db->table('sections')->insertBatch([
                ['id' => 1, 'penyulang_id' => 1, 'nama_seksi' => 'GI - LBSM PDAM', 'sequence_order' => 1, 'status' => 'ACTIVE'],
            ]);
        }

        if (!$db->tableExists('construction_types')) {
            $forge->addField([
                'id'                => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'construction_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'construction_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            ]);
            $forge->createTable('construction_types', true);
        }

        $codeCol = $db->fieldExists('construction_code', 'construction_types') ? 'construction_code' : 'kode_konstruksi';
        $nameCol = $db->fieldExists('construction_name', 'construction_types') ? 'construction_name' : 'nama_konstruksi';
        $typesToAdd = [
            ['TM1', 'Tiang Awal/Tumpu TM1'],
            ['TM2', 'Tiang Sudut TM2'],
            ['GTT-2T', 'Gardu Trafo Tiang 2 Portal GTT-2T'],
            ['TMMVTIC', 'Tiang TM MVTIC'],
        ];
        foreach ($typesToAdd as $t) {
            $check = $db->table('construction_types')->where($codeCol, $t[0])->countAllResults();
            if ($check === 0) {
                $db->table('construction_types')->insert([$codeCol => $t[0], $nameCol => $t[1]]);
            }
        }

        if (!$db->tableExists('assets')) {
            $forge->addField([
                'id'                    => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_asset'            => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'            => ['type' => 'VARCHAR', 'constraint' => 255],
                'jenis_asset'           => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'penyulang_id'          => ['type' => 'INTEGER', 'null' => true],
                'section_id'            => ['type' => 'INTEGER', 'null' => true],
                'construction_type_id'  => ['type' => 'INTEGER', 'null' => true],
                'ulp_id'                => ['type' => 'INTEGER', 'null' => true],
                'latitude'              => ['type' => 'DOUBLE', 'null' => true],
                'longitude'             => ['type' => 'DOUBLE', 'null' => true],
                'status'                => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPERATIONAL'],
                'lokasi'                => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at'            => ['type' => 'DATETIME', 'null' => true],
                'updated_at'            => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'            => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('assets', true);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        parent::tearDown();
    }

    public function testDryRunPerformsZeroDatabaseWrites(): void
    {
        $csvPath = $this->createFreshCsv();
        $staged = $this->reviewService->getOrCreateStagedBatch($csvPath);
        $batchId = $staged['batch_id'];

        // Approve all rows to unlock gate
        $this->reviewService->approveBatchScope($batchId, 'PASS', '198501012010011001', 'Bulk Pass');
        $this->reviewService->approveSingleRow($batchId, 4, 'APPROVED', '198501012010011001', 'Approve GTT2T');

        $assetsCountBefore = $this->db->table('assets')->countAllResults();

        $dryRunRes = $this->promotionService->promoteBatch($batchId, '198501012010011001', null, true);

        $this->assertTrue($dryRunRes['success']);
        $this->assertSame('DRY-RUN', $dryRunRes['mode']);
        $this->assertSame(0, $dryRunRes['database_writes']);

        $assetsCountAfter = $this->db->table('assets')->countAllResults();
        $this->assertSame($assetsCountBefore, $assetsCountAfter);
    }

    public function testPromotionFailsWhenGateIsLocked(): void
    {
        $csvPath = $this->createFreshCsv();
        $staged = $this->reviewService->getOrCreateStagedBatch($csvPath);
        $batchId = $staged['batch_id'];

        // Do not approve rows (Gate is LOCKED)
        $res = $this->promotionService->promoteBatch($batchId, '198501012010011001', null, false);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Promotion Gate is LOCKED', $res['error']);
    }

    public function testLivePromotionInsertsAssetsAtomically(): void
    {
        $csvPath = $this->createFreshCsv();
        $staged = $this->reviewService->getOrCreateStagedBatch($csvPath);
        $batchId = $staged['batch_id'];

        // 1. Approve all rows
        $this->reviewService->approveBatchScope($batchId, 'PASS', '198501012010011001', 'Bulk Pass');
        $this->reviewService->approveSingleRow($batchId, 4, 'APPROVED', '198501012010011001', 'Approve GTT2T');

        // 2. Execute Live Promotion
        $execRes = $this->promotionService->promoteBatch($batchId, '198501012010011001', null, false);

        $this->assertTrue($execRes['success'], $execRes['error'] ?? 'Execution failed');
        $this->assertSame('LIVE_EXECUTION', $execRes['mode']);
        $this->assertGreaterThan(0, $execRes['database_writes']);

        // Verify batch status is now PROMOTED
        $batch = $this->db->table('ar01_ingestion_batches')->where('batch_id', $batchId)->get()->getRowArray();
        $this->assertSame('PROMOTED', $batch['status']);
    }
}
