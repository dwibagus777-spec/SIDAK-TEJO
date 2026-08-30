<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FeederAssetStagingService;
use App\Database\Seeds\ConstructionIntelligenceSeeder;

/**
 * Unit Test Suite for AR-01 Phase 5A - 5D: Feeder Asset Staging & Validation Pipeline
 */
class FeederAssetStagingTest extends CIUnitTestCase
{
    protected FeederAssetStagingService $stager;
    protected string $sampleCsvPath;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        $this->ensureTablesExist($forge, $db);

        $this->stager = new FeederAssetStagingService($db);
        $this->sampleCsvPath = WRITEPATH . 'Template_Import_JTM_SIWALAN_PANJI.csv';
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
                ['id' => 2, 'penyulang_id' => 1, 'nama_seksi' => 'LBSM PDAM - RECLOSER PASAR PANJI', 'sequence_order' => 2, 'status' => 'ACTIVE'],
                ['id' => 3, 'penyulang_id' => 1, 'nama_seksi' => 'RECLOSER PASAR PANJI - LBSM BUDURAN 3 - LBS SPBU', 'sequence_order' => 3, 'status' => 'ACTIVE'],
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
            ['TM4', 'Tiang Penegang TM4'],
            ['TM5', 'Tiang Sudut Besar TM5'],
            ['TM8', 'Tiang Akhir TM8'],
            ['TM10', 'Tiang Percabangan TM10'],
            ['TM11', 'Tiang Portal TM11'],
            ['GTT2', 'Gardu Trafo Tiang 2 Tiang GTT2'],
            ['GTT1', 'Gardu Trafo Tiang 1 Tiang GTT1'],
            ['GTT', 'Gardu Trafo Tiang GTT'],
            ['GTT2T', 'Gardu Trafo Tiang 2 Portal GTT2T'],
            ['TMTP', 'Tiang TMTP'],
            ['TMMVTIC', 'Tiang TM MVTIC'],
            ['PMT', 'Pemutus Tenaga PMT'],
            ['PMS', 'Pemisah PMS'],
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
                'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER', 'null' => true],
                'kode_asset'   => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'   => ['type' => 'VARCHAR', 'constraint' => 255],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('assets', true);
        }
    }

    public function testPhase5ASourceRegistrationAndFingerprint(): void
    {
        $result = $this->stager->stageAndValidateSourceFile($this->sampleCsvPath, 1);

        $this->assertTrue($result['success']);
        $sr = $result['source_registration'];
        $this->assertNotEmpty($sr['file_sha256']);
        $this->assertStringStartsWith('BATCH-PYL001-', $sr['ingestion_batch_id']);
        $this->assertGreaterThan(0, $sr['file_size']);
        $this->assertStringContainsString('AR-01-P5-I', $sr['source_immutability']);
    }

    public function testPhase5BStagingRowCountsAndFeederContext(): void
    {
        $result = $this->stager->stageAndValidateSourceFile($this->sampleCsvPath, 1);

        $this->assertTrue($result['success']);
        $sm = $result['staging_summary'];
        $tf = $result['target_feeder'];

        $this->assertSame(655, $sm['total_staged_rows']);
        $this->assertSame(655, $sm['unique_asset_names']);
        $this->assertSame(0, $sm['duplicate_names']);
        $this->assertSame(1, $tf['id']);
        $this->assertSame('PYL-001', $tf['kode_penyulang']);
        $this->assertGreaterThanOrEqual(1, $tf['active_sections']);
    }

    public function testPhase5CValidationAndGpsAnomalyDetection(): void
    {
        $result = $this->stager->stageAndValidateSourceFile($this->sampleCsvPath, 1);

        $this->assertTrue($result['success']);
        $sm = $result['staging_summary'];
        $anomalies = $result['detected_anomalies'];

        // 654 PASS, 1 WARNING (row 516 with positive latitude)
        $this->assertSame(654, $sm['pass_candidates']);
        $this->assertSame(1, $sm['warning_candidates']);
        $this->assertSame(0, $sm['reject_candidates']);

        $this->assertCount(1, $anomalies);
        $this->assertSame('SIWALANPANJI_324', $anomalies[0]['asset_name']);
        $this->assertSame('WARNING', $anomalies[0]['status']);
        $this->assertStringContainsString('Latitude bernilai positif', $anomalies[0]['warnings'][0]);
    }

    public function testPhase5ZeroMutationOnAssetsTable(): void
    {
        $db = \Config\Database::connect();
        $countBefore = $db->table('assets')->countAllResults();

        $result = $this->stager->stageAndValidateSourceFile($this->sampleCsvPath, 1);

        $this->assertTrue($result['success']);
        $countAfter = $db->table('assets')->countAllResults();

        $this->assertSame($countBefore, $countAfter, 'Zero mutation guarantee on table assets');
        $this->assertSame(0, $result['database_mutation_guard']['assets_table_writes']);
    }

    public function testSourceFileDeduplicationProtection(): void
    {
        // Simulate two identical files with different names
        $fileA = $this->sampleCsvPath;
        $fileB = WRITEPATH . 'Template_Import_DUPLICATE_COPY.csv';
        copy($fileA, $fileB);

        $recon = $this->stager->reconcileSourceFiles([$fileA, $fileB]);

        $this->assertSame(2, $recon['total_files_evaluated']);
        $this->assertSame(1, $recon['unique_sources_count']);
        $this->assertSame(1, $recon['duplicate_sources_count']);
        $this->assertTrue($recon['has_identical_files']);
        $this->assertSame('SKIPPED_DUPLICATE_SOURCE', $recon['duplicate_files'][0]['action']);

        if (file_exists($fileB)) {
            unlink($fileB);
        }
    }

    public function testMultiFeederStagingReconciliation(): void
    {
        $multiPath = WRITEPATH . 'Template_Import_MULTI_PENYULANG_PART1.csv';
        if (!file_exists($multiPath)) {
            $this->markTestSkipped('Multi-feeder file not present');
        }

        $result = $this->stager->stageAndValidateSourceFile($multiPath, null);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_multi_feeder']);
        $this->assertGreaterThanOrEqual(4, $result['total_feeders_found']);
        $this->assertArrayHasKey('ECCO', $result['feeder_distribution']);
        $this->assertArrayHasKey('GADING KIRANA', $result['feeder_distribution']);
        $this->assertArrayHasKey('GEMURUNG', $result['feeder_distribution']);
        $this->assertSame(0, $result['database_mutation_guard']['assets_table_writes']);
    }
}
