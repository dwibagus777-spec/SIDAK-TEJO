<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FeederAssetReviewService;
use App\Services\FeederAssetStagingService;

/**
 * AR-01 Phase 5E: Human Engineering Review & Sign-Off Engine Unit Tests
 */
class FeederAssetReviewTest extends CIUnitTestCase
{
    protected $db;
    protected FeederAssetReviewService $reviewService;
    protected FeederAssetStagingService $stagingService;
    protected string $testCsvPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        $this->ensureTablesExist($forge, $this->db);

        $this->reviewService = new FeederAssetReviewService($this->db);
        $this->stagingService = new FeederAssetStagingService($this->db);

        // Seed 4 feeders for multi-feeder test
        $feedersToSeed = [
            ['id' => 12, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-012', 'nama_penyulang' => 'GEMURUNG'],
            ['id' => 13, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-013', 'nama_penyulang' => 'GEDANGAN'],
            ['id' => 14, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-014', 'nama_penyulang' => 'GADING KIRANA'],
            ['id' => 15, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-015', 'nama_penyulang' => 'ECCO'],
        ];
        foreach ($feedersToSeed as $f) {
            if (!$this->db->table('penyulang')->where('id', $f['id'])->get()->getRowArray()) {
                $this->db->table('penyulang')->insert($f);
            }
        }

        // Create temporary test CSV
        $this->testCsvPath = WRITEPATH . 'test_phase5e_' . uniqid() . '.csv';
        $rows = [
            ["UP3","ULP","Jenis Asset","Nama Asset JTM","Penyulang","Konstruksi (e.g. TM1)","Material Conductor","Kapasitas / Panjang","Alamat / Lokasi","Latitude","Longitude"],
            ["UP3 Sidoarjo","ULP Sidoarjo Kota","JTM","ECCO_01","ECCO","TM1","A3CS","0","0","-7.4478","112.7183"],
            ["UP3 Sidoarjo","ULP Sidoarjo Kota","JTM","ECCO_02","ECCO","TM1","A3CS","0","0","-7.4479","112.7184"],
            ["UP3 Sidoarjo","ULP Sidoarjo Kota","JTM","GADING_KIRANA_01","GADING KIRANA","GTT2T","A3CS","0","0","-7.4480","112.7185"],
        ];
        $fp = fopen($this->testCsvPath, 'w');
        foreach ($rows as $r) {
            fputcsv($fp, $r);
        }
        fclose($fp);
    }

    private function ensureTablesExist($forge, $db): void
    {
        // ulps
        if (!$db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 20],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('ulps', true);
            $db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'ULP SIDOARJO KOTA']);
        }

        // penyulang
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

        // sections
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

        // construction_types
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

        // assets
        if (!$db->tableExists('assets')) {
            $forge->addField([
                'id'                    => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_asset'            => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'            => ['type' => 'VARCHAR', 'constraint' => 255],
                'penyulang_id'          => ['type' => 'INTEGER', 'null' => true],
                'section_id'            => ['type' => 'INTEGER', 'null' => true],
                'construction_type_id'  => ['type' => 'INTEGER', 'null' => true],
                'deleted_at'            => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('assets', true);
            $db->table('assets')->insertBatch([
                ['id' => 1, 'kode_asset' => 'ECCO_HIST_1', 'nama_asset' => 'ECCO_HIST_1', 'penyulang_id' => 15, 'deleted_at' => null],
                ['id' => 2, 'kode_asset' => 'CANDRA_QUAR_1', 'nama_asset' => 'CANDRA_QUAR_1', 'penyulang_id' => null, 'deleted_at' => '2026-08-30 00:00:00'],
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testCsvPath)) {
            unlink($this->testCsvPath);
        }
        if ($this->db->tableExists('ar01_review_decisions')) {
            $this->db->table('ar01_review_decisions')->emptyTable();
        }
        if ($this->db->tableExists('ar01_staging_assets')) {
            $this->db->table('ar01_staging_assets')->emptyTable();
        }
        if ($this->db->tableExists('ar01_ingestion_batches')) {
            $this->db->table('ar01_ingestion_batches')->emptyTable();
        }
        if ($this->db->tableExists('ar01_audit_log')) {
            $this->db->table('ar01_audit_log')->emptyTable();
        }
        parent::tearDown();
    }

    public function testPhase5ECannotWriteToAssets(): void
    {
        $assetsInitialCount = $this->db->table('assets')->countAllResults();

        $staged = $this->reviewService->getOrCreateStagedBatch($this->testCsvPath);
        $this->assertTrue($staged['success']);

        $summary = $this->reviewService->getBatchReviewSummary($staged['batch_id']);
        $this->assertTrue($summary['success']);

        $assetsFinalCount = $this->db->table('assets')->countAllResults();
        $this->assertSame($assetsInitialCount, $assetsFinalCount, "Phase 5E operations must result in 0 writes to 'assets' table");
    }

    public function testPassRowCanBeApproved(): void
    {
        $staged = $this->reviewService->getOrCreateStagedBatch($this->testCsvPath);
        $batchId = $staged['batch_id'];

        $result = $this->reviewService->approveSingleRow($batchId, 2, 'APPROVED', '198501012010011001', 'Engineering validation verified');
        $this->assertTrue($result['success']);
        $this->assertSame('APPROVED', $result['decision']);
        $this->assertNotEmpty($result['signed_sha256']);
        $this->assertSame(0, $result['database_writes']);

        $stagedRow = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('source_row_number', 2)->get()->getRowArray();
        $this->assertSame('APPROVED', $stagedRow['review_status']);
    }

    public function testWarningRowCannotBeBulkApproved(): void
    {
        $staged = $this->reviewService->getOrCreateStagedBatch($this->testCsvPath);
        $batchId = $staged['batch_id'];

        $bulkResult = $this->reviewService->approveBatchScope($batchId, 'PASS', '198501012010011001', 'Bulk approve deterministic pass');
        $this->assertTrue($bulkResult['success']);
        $this->assertSame(2, $bulkResult['approved_count']); // Rows 2 and 3 (ECCO_01, ECCO_02)

        // Row 4 (GTT2T anomaly) MUST remain NEEDS_REVIEW
        $gttRow = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('source_row_number', 4)->get()->getRowArray();
        $this->assertSame('NEEDS_REVIEW', $gttRow['review_status'], "Row with GTT2T anomaly must remain NEEDS_REVIEW after bulk PASS approval");
    }

    public function testGtt2tRequiresExplicitHumanApproval(): void
    {
        $staged = $this->reviewService->getOrCreateStagedBatch($this->testCsvPath);
        $batchId = $staged['batch_id'];

        // Gate must be locked prior to explicit anomaly review
        $gateBefore = $this->reviewService->evaluatePromotionGate($batchId);
        $this->assertSame('LOCKED', $gateBefore['promotion_eligibility']);

        // Explicit single-row human approval for row 4 (GTT2T -> GTT-2T)
        $explicitApproval = $this->reviewService->approveSingleRow(
            $batchId,
            4,
            'APPROVED',
            '198501012010011001',
            'Verified GTT-2T transformer portal structure on site'
        );
        $this->assertTrue($explicitApproval['success']);

        $gttRow = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('source_row_number', 4)->get()->getRowArray();
        $this->assertSame('APPROVED', $gttRow['review_status']);
    }

    public function testMissingApproverOrReasonBlocksApproval(): void
    {
        $staged = $this->reviewService->getOrCreateStagedBatch($this->testCsvPath);
        $batchId = $staged['batch_id'];

        // Missing NIP
        $resNoNip = $this->reviewService->approveSingleRow($batchId, 2, 'APPROVED', '', 'Valid reason');
        $this->assertFalse($resNoNip['success']);

        // Missing Reason
        $resNoReason = $this->reviewService->approveSingleRow($batchId, 2, 'APPROVED', '198501012010011001', '');
        $this->assertFalse($resNoReason['success']);
    }

    public function testSourceShaMismatchBlocksApproval(): void
    {
        $staged = $this->reviewService->getOrCreateStagedBatch($this->testCsvPath);
        $batchId = $staged['batch_id'];

        // Mutate source file content on disk
        file_put_contents($this->testCsvPath, "CORRUPTED DATA FILE", FILE_APPEND);

        $res = $this->reviewService->approveSingleRow($batchId, 2, 'APPROVED', '198501012010011001', 'Reason');
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('BATCH INTEGRITY FAILURE', $res['error']);
    }

    public function testPromotionGateUnlocksOnlyWhenAllRowsApproved(): void
    {
        $staged = $this->reviewService->getOrCreateStagedBatch($this->testCsvPath);
        $batchId = $staged['batch_id'];

        // 1. Bulk approve PASS rows
        $this->reviewService->approveBatchScope($batchId, 'PASS', '198501012010011001', 'Bulk pass');
        $gate1 = $this->reviewService->evaluatePromotionGate($batchId);
        $this->assertSame('LOCKED', $gate1['promotion_eligibility']);

        // 2. Explicitly approve remaining GTT2T row
        $this->reviewService->approveSingleRow($batchId, 4, 'APPROVED', '198501012010011001', 'Approved GTT-2T');
        $gate2 = $this->reviewService->evaluatePromotionGate($batchId);
        $this->assertSame('UNLOCKED', $gate2['promotion_eligibility']);
        $this->assertNotEmpty($gate2['certificate_token']);
    }

    public function testQuarantinedAndHistoricalAssetsUntouched(): void
    {
        $pyl015CountBefore = $this->db->table('assets')->where('penyulang_id', 15)->countAllResults();
        $softDeletedBefore = $this->db->fieldExists('deleted_at', 'assets') ? 
            $this->db->table('assets')->where('deleted_at IS NOT NULL')->countAllResults() : 0;

        $staged = $this->reviewService->getOrCreateStagedBatch($this->testCsvPath);
        $this->reviewService->approveBatchScope($staged['batch_id'], 'PASS', '198501012010011001', 'Approve');

        $pyl015CountAfter = $this->db->table('assets')->where('penyulang_id', 15)->countAllResults();
        $softDeletedAfter = $this->db->fieldExists('deleted_at', 'assets') ? 
            $this->db->table('assets')->where('deleted_at IS NOT NULL')->countAllResults() : 0;

        $this->assertSame($pyl015CountBefore, $pyl015CountAfter);
        $this->assertSame($softDeletedBefore, $softDeletedAfter);
    }
}
