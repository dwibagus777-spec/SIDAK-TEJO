<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\MaterialRecapService;

/**
 * MR-01 Phase 3C: Material Recap & Reporting Test Suite (Read-Only Proof Gate)
 *
 * Tests multi-level aggregation, mathematical reconciliation across hierarchy levels,
 * CR-06 business date authority, unit variance detection, join multiplication prevention,
 * and zero operational data mutation.
 *
 * @internal
 */
final class MaterialRecapTest extends CIUnitTestCase
{
    private MaterialRecapService $recapService;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $forge    = \Config\Database::forge();

        $this->ensureTablesExist($forge, $this->db);
        $this->db->table('temuan_materials')->emptyTable();
        $this->recapService = new MaterialRecapService($this->db);
    }

    private function ensureTablesExist($forge, $db): void
    {
        // ulps
        if (!$db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('ulps', true);
        }

        // penyulang
        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER'],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('penyulang', true);
        }

        // sections
        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER'],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('sections', true);
        }

        // assets
        if (!$db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'TIANG'],
                'section_id'           => ['type' => 'INTEGER'],
                'construction_type_id' => ['type' => 'INTEGER', 'null' => true],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('assets', true);
        }

        // construction_types
        if (!$db->tableExists('construction_types')) {
            $forge->addField([
                'id'                  => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'construction_code'   => ['type' => 'VARCHAR', 'constraint' => 50],
                'construction_name'   => ['type' => 'VARCHAR', 'constraint' => 150],
                'construction_family' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
            ]);
            $forge->createTable('construction_types', true);
        }

        // master_materials
        if (!$db->tableExists('master_materials')) {
            $forge->addField([
                'id'            => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'material_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'nama_material' => ['type' => 'VARCHAR', 'constraint' => 150],
                'satuan'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET'],
                'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('master_materials', true);
        }

        // temuan
        if (!$db->tableExists('temuan')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'nomor_temuan'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'penyulang_id'   => ['type' => 'INTEGER', 'default' => 1],
                'section_id'     => ['type' => 'INTEGER'],
                'material'       => ['type' => 'TEXT', 'null' => true],
                'tanggal_temuan' => ['type' => 'DATE'],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
        }

        // temuan_materials
        if (!$db->tableExists('temuan_materials')) {
            $forge->addField([
                'id'                      => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'temuan_id'               => ['type' => 'INTEGER'],
                'asset_id'                => ['type' => 'INTEGER'],
                'construction_type_id'    => ['type' => 'INTEGER'],
                'material_id'             => ['type' => 'INTEGER'],
                'canonical_code_snapshot' => ['type' => 'VARCHAR', 'constraint' => 60],
                'canonical_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 150],
                'unit_snapshot'           => ['type' => 'VARCHAR', 'constraint' => 20],
                'quantity'                => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'justification_note'      => ['type' => 'TEXT', 'null' => true],
                'source_mode'             => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'BOM_PICKER'],
                'created_by'              => ['type' => 'INTEGER', 'null' => true],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
                'updated_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan_materials', true);
        }
    }

    private function createRecapTemuan(int $ulpId, int $pylId, int $secId, string $tanggal, string $createdAt): int
    {
        $data = [
            'section_id'     => $secId,
            'material'       => 'Legacy text irrelevant',
            'tanggal_temuan' => $tanggal,
            'created_at'     => $createdAt,
        ];

        if ($this->db->fieldExists('nomor_temuan', 'temuan')) {
            $data['nomor_temuan'] = 'TM-TEST-' . uniqid();
        }
        if ($this->db->fieldExists('ulp_id', 'temuan')) {
            $data['ulp_id'] = $ulpId;
        }
        if ($this->db->fieldExists('penyulang_id', 'temuan')) {
            $data['penyulang_id'] = $pylId;
        }
        if ($this->db->fieldExists('jenis_temuan', 'temuan')) {
            $data['jenis_temuan'] = 'KONSTRUKSI';
        }
        if ($this->db->fieldExists('pelaksana', 'temuan')) {
            $data['pelaksana'] = 'HAR KONSTRUKSI';
        }
        if ($this->db->fieldExists('prioritas', 'temuan')) {
            $data['prioritas'] = 'MEDIUM';
        }
        if ($this->db->fieldExists('potensi_gangguan', 'temuan')) {
            $data['potensi_gangguan'] = 'OCR';
        }
        if ($this->db->fieldExists('status', 'temuan')) {
            $data['status'] = 'BELUM';
        }

        $this->db->table('temuan')->insert($data);
        return (int)$this->db->insertID();
    }

    /**
     * Helper to seed deterministic hierarchy test data.
     */
    private function seedRecapTestData(): array
    {
        // 1. Ensure ULP A and ULP B
        $ulpA = $this->db->table('ulps')->where('kode_ulp', '51301')->get()->getRowArray();
        $ulpAId = $ulpA ? (int)$ulpA['id'] : 1;
        if (!$ulpA) {
            $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'ULP KOTA']);
            $ulpAId = 1;
        }

        $this->db->table('ulps')->insert(['kode_ulp' => '51302-' . uniqid(), 'nama_ulp' => 'ULP PINGGIRAN']);
        $ulpBId = (int)$this->db->insertID();

        // 2. Ensure Penyulang 1 (under ULP A) & Penyulang 2 (under ULP B)
        $this->db->table('penyulang')->insert(['ulp_id' => $ulpAId, 'kode_penyulang' => 'PYL-A1-' . uniqid(), 'nama_penyulang' => 'PENYULANG KOTA 1']);
        $pylA1Id = (int)$this->db->insertID();

        $this->db->table('penyulang')->insert(['ulp_id' => $ulpBId, 'kode_penyulang' => 'PYL-B1-' . uniqid(), 'nama_penyulang' => 'PENYULANG TIMUR 1']);
        $pylB1Id = (int)$this->db->insertID();

        // 3. Ensure Sections
        $this->db->table('sections')->insert(['penyulang_id' => $pylA1Id, 'nama_section' => 'Section A1-Ruas 1']);
        $secA1Id = (int)$this->db->insertID();

        $this->db->table('sections')->insert(['penyulang_id' => $pylB1Id, 'nama_section' => 'Section B1-Ruas 1']);
        $secB1Id = (int)$this->db->insertID();

        // 4. Ensure Construction TM1
        $this->db->table('construction_types')->insert(['construction_code' => 'TM1-' . uniqid(), 'construction_name' => 'Tiang Penumpu']);
        $constId = (int)$this->db->insertID();

        // 5. Ensure Assets
        $this->db->table('assets')->insert([
            'kode_asset'           => 'AST-A1-' . uniqid(),
            'nama_asset'           => 'Tiang A1',
            'jenis_asset'          => 'TIANG',
            'section_id'           => $secA1Id,
            'construction_type_id' => $constId,
        ]);
        $assetA1Id = (int)$this->db->insertID();

        $this->db->table('assets')->insert([
            'kode_asset'           => 'AST-B1-' . uniqid(),
            'nama_asset'           => 'Tiang B1',
            'jenis_asset'          => 'TIANG',
            'section_id'           => $secB1Id,
            'construction_type_id' => $constId,
        ]);
        $assetB1Id = (int)$this->db->insertID();

        // 6. Ensure Master Materials
        $this->db->table('master_materials')->insert(['material_code' => 'MAT-CLAMP-' . uniqid(), 'nama_material' => 'ANCHOR ROD CLAMP', 'satuan' => 'BH']);
        $matClampId = (int)$this->db->insertID();

        $this->db->table('master_materials')->insert(['material_code' => 'MAT-BOLT-' . uniqid(), 'nama_material' => 'BOLT & NUT M16X50', 'satuan' => 'SET']);
        $matBoltId = (int)$this->db->insertID();

        // 7. Create Temuan 1 in ULP A (Date: 2026-08-15)
        $temuan1Id = $this->createRecapTemuan($ulpAId, $pylA1Id, $secA1Id, '2026-08-15', '2026-08-20 10:00:00');

        // 8. Create Temuan 2 in ULP A (Date: 2026-08-16)
        $temuan2Id = $this->createRecapTemuan($ulpAId, $pylA1Id, $secA1Id, '2026-08-16', '2026-08-20 11:00:00');

        // 9. Create Temuan 3 in ULP B (Date: 2026-08-25)
        $temuan3Id = $this->createRecapTemuan($ulpBId, $pylB1Id, $secB1Id, '2026-08-25', '2026-08-25 09:00:00');

        // 10. Populate temuan_materials transactions:
        // Temuan 1: 12 BH Clamp, 24 SET Bolt
        $this->db->table('temuan_materials')->insert([
            'temuan_id'               => $temuan1Id,
            'asset_id'                => $assetA1Id,
            'construction_type_id'    => $constId,
            'material_id'             => $matClampId,
            'canonical_code_snapshot' => 'MAT-CLAMP',
            'canonical_name_snapshot' => 'ANCHOR ROD CLAMP',
            'unit_snapshot'           => 'BH',
            'quantity'                => 12.00,
        ]);
        $this->db->table('temuan_materials')->insert([
            'temuan_id'               => $temuan1Id,
            'asset_id'                => $assetA1Id,
            'construction_type_id'    => $constId,
            'material_id'             => $matBoltId,
            'canonical_code_snapshot' => 'MAT-BOLT',
            'canonical_name_snapshot' => 'BOLT & NUT M16X50',
            'unit_snapshot'           => 'SET',
            'quantity'                => 24.00,
        ]);

        // Temuan 2: 8 BH Clamp (Same material in same section/ULP)
        $this->db->table('temuan_materials')->insert([
            'temuan_id'               => $temuan2Id,
            'asset_id'                => $assetA1Id,
            'construction_type_id'    => $constId,
            'material_id'             => $matClampId,
            'canonical_code_snapshot' => 'MAT-CLAMP',
            'canonical_name_snapshot' => 'ANCHOR ROD CLAMP',
            'unit_snapshot'           => 'BH',
            'quantity'                => 8.00,
        ]);

        // Temuan 3 (ULP B): 5 BH Clamp
        $this->db->table('temuan_materials')->insert([
            'temuan_id'               => $temuan3Id,
            'asset_id'                => $assetB1Id,
            'construction_type_id'    => $constId,
            'material_id'             => $matClampId,
            'canonical_code_snapshot' => 'MAT-CLAMP',
            'canonical_name_snapshot' => 'ANCHOR ROD CLAMP',
            'unit_snapshot'           => 'BH',
            'quantity'                => 5.00,
        ]);

        return [
            'ulpAId'     => $ulpAId,
            'ulpBId'     => $ulpBId,
            'pylA1Id'    => $pylA1Id,
            'pylB1Id'    => $pylB1Id,
            'secA1Id'    => $secA1Id,
            'secB1Id'    => $secB1Id,
            'assetA1Id'  => $assetA1Id,
            'assetB1Id'  => $assetB1Id,
            'constId'    => $constId,
            'matClampId' => $matClampId,
            'matBoltId'  => $matBoltId,
            'temuan1Id'  => $temuan1Id,
            'temuan2Id'  => $temuan2Id,
            'temuan3Id'  => $temuan3Id,
        ];
    }

    /**
     * Test 01: Global Material SUM(quantity)
     * Expected: CLAMP = 12 + 8 + 5 = 25 BH; BOLT = 24 SET.
     */
    public function testGlobalMaterialSumQuantity(): void
    {
        $d = $this->seedRecapTestData();
        $res = $this->recapService->getRecap();

        $this->assertSame('SUCCESS', $res['status']);
        $this->assertCount(2, $res['global_recap']);

        // Find Clamp and Bolt
        $clamp = null;
        $bolt  = null;
        foreach ($res['global_recap'] as $g) {
            if ($g['material_id'] === $d['matClampId']) $clamp = $g;
            if ($g['material_id'] === $d['matBoltId']) $bolt = $g;
        }

        $this->assertNotNull($clamp);
        $this->assertSame('25.00', $clamp['total_quantity']);
        $this->assertSame('BH', $clamp['unit_snapshot']);

        $this->assertNotNull($bolt);
        $this->assertSame('24.00', $bolt['total_quantity']);
        $this->assertSame('SET', $bolt['unit_snapshot']);
    }

    /**
     * Test 02: ULP Grouping
     * Expected: ULP A: Clamp = 20 BH, Bolt = 24 SET. ULP B: Clamp = 5 BH.
     */
    public function testUlpGrouping(): void
    {
        $d = $this->seedRecapTestData();
        $res = $this->recapService->getRecap();

        $this->assertSame('SUCCESS', $res['status']);

        $ulpAClamp = 0.0;
        $ulpBClamp = 0.0;
        foreach ($res['ulp_recap'] as $u) {
            if ($u['ulp_id'] === $d['ulpAId'] && $u['material_id'] === $d['matClampId']) {
                $ulpAClamp = (float)$u['total_quantity'];
            }
            if ($u['ulp_id'] === $d['ulpBId'] && $u['material_id'] === $d['matClampId']) {
                $ulpBClamp = (float)$u['total_quantity'];
            }
        }

        $this->assertEquals(20.00, $ulpAClamp);
        $this->assertEquals(5.00, $ulpBClamp);
    }

    /**
     * Test 03: Penyulang Grouping
     */
    public function testPenyulangGrouping(): void
    {
        $d = $this->seedRecapTestData();
        $res = $this->recapService->getRecap();

        $pylA1Bolt = 0.0;
        foreach ($res['penyulang_recap'] as $p) {
            if ($p['penyulang_id'] === $d['pylA1Id'] && $p['material_id'] === $d['matBoltId']) {
                $pylA1Bolt = (float)$p['total_quantity'];
            }
        }

        $this->assertEquals(24.00, $pylA1Bolt);
    }

    /**
     * Test 04: Section Grouping
     */
    public function testSectionGrouping(): void
    {
        $d = $this->seedRecapTestData();
        $res = $this->recapService->getRecap();

        $secA1Clamp = 0.0;
        foreach ($res['section_recap'] as $s) {
            if ($s['section_id'] === $d['secA1Id'] && $s['material_id'] === $d['matClampId']) {
                $secA1Clamp = (float)$s['total_quantity'];
            }
        }

        $this->assertEquals(20.00, $secA1Clamp);
    }

    /**
     * Test 05: Asset Detail
     */
    public function testAssetDetailRows(): void
    {
        $this->seedRecapTestData();
        $res = $this->recapService->getRecap();

        $this->assertCount(4, $res['detail_rows']);
        $this->assertSame(4, $res['kpi']['total_material_lines']);
    }

    /**
     * Test 06 & 07: Period Filter uses tanggal_temuan, NOT created_at
     */
    public function testPeriodFilterUsesTanggalTemuanNotCreatedAt(): void
    {
        $this->seedRecapTestData();

        // Filtering for 2026-08-15 to 2026-08-15 (Temuan 1 only)
        // Note: Temuan 1 created_at was 2026-08-20, so if created_at was wrongly used, it would yield 0 results.
        $res = $this->recapService->getRecap([
            'start_date' => '2026-08-15',
            'end_date'   => '2026-08-15',
        ]);

        $this->assertSame('SUCCESS', $res['status']);
        $this->assertCount(2, $res['detail_rows']); // 12 Clamp + 24 Bolt
        $this->assertSame(2, $res['kpi']['total_material_lines']);

        $clamp = null;
        foreach ($res['global_recap'] as $g) {
            if ($g['canonical_code_snapshot'] === 'MAT-CLAMP') $clamp = $g;
        }
        $this->assertSame('12.00', $clamp['total_quantity']);
    }

    /**
     * Test 08: Legacy temuan.material is completely excluded from aggregation
     */
    public function testLegacyTemuanMaterialExcluded(): void
    {
        $this->seedRecapTestData();
        $res = $this->recapService->getRecap();

        foreach ($res['global_recap'] as $g) {
            $this->assertStringNotContainsString('Legacy text', $g['canonical_name_snapshot']);
        }
    }

    /**
     * Test 09: Same material ID across multiple findings sums correctly
     */
    public function testSameMaterialAcrossMultipleFindingsSums(): void
    {
        $d = $this->seedRecapTestData();
        // Temuan 1 (12 BH) + Temuan 2 (8 BH) in Section A1
        $res = $this->recapService->getRecap(['section_id' => $d['secA1Id']]);

        $clamp = null;
        foreach ($res['global_recap'] as $g) {
            if ($g['material_id'] === $d['matClampId']) $clamp = $g;
        }

        $this->assertSame('20.00', $clamp['total_quantity']);
        $this->assertSame(2, $clamp['finding_count']);
    }

    /**
     * Test 10: Different material IDs with same name do NOT merge
     */
    public function testDifferentMaterialIdsDoNotMerge(): void
    {
        $d = $this->seedRecapTestData();

        // Add second material with exact same name but different ID
        $this->db->table('master_materials')->insert(['material_code' => 'MAT-CLAMP-2-' . uniqid(), 'nama_material' => 'ANCHOR ROD CLAMP', 'satuan' => 'BH']);
        $newMatId = (int)$this->db->insertID();

        $this->db->table('temuan_materials')->insert([
            'temuan_id'               => $d['temuan3Id'],
            'asset_id'                => $d['assetB1Id'],
            'construction_type_id'    => $d['constId'],
            'material_id'             => $newMatId,
            'canonical_code_snapshot' => 'MAT-CLAMP-2',
            'canonical_name_snapshot' => 'ANCHOR ROD CLAMP',
            'unit_snapshot'           => 'BH',
            'quantity'                => 10.00,
        ]);

        $res = $this->recapService->getRecap();

        // Should produce 3 distinct global rows, not 2!
        $this->assertCount(3, $res['global_recap']);
    }

    /**
     * Test 11: Same material with different units remains separated (UNIT_VARIANCE)
     */
    public function testUnitVarianceDetectedAndSeparated(): void
    {
        $d = $this->seedRecapTestData();

        // In temuan 2, matBoltId was not yet added. Add matBoltId with unit 'BH' instead of 'SET' to create unit variance
        $this->db->table('temuan_materials')->insert([
            'temuan_id'               => $d['temuan2Id'],
            'asset_id'                => $d['assetA1Id'],
            'construction_type_id'    => $d['constId'],
            'material_id'             => $d['matBoltId'],
            'canonical_code_snapshot' => 'MAT-BOLT',
            'canonical_name_snapshot' => 'BOLT & NUT M16X50',
            'unit_snapshot'           => 'BH', // Unit Variance! Originally SET, now BH
            'quantity'                => 4.00,
        ]);

        $res = $this->recapService->getRecap();

        // Bolt SET and Bolt BH should be separate rows in global recap
        $boltRows = array_filter($res['global_recap'], fn($r) => $r['material_id'] === $d['matBoltId']);
        $this->assertCount(2, $boltRows);

        foreach ($boltRows as $br) {
            $this->assertTrue($br['has_unit_variance']);
        }

        $this->assertNotEmpty($res['data_quality']['unit_variances']);
    }

    /**
     * Test 12: No cross-section leakage
     */
    public function testNoCrossSectionLeakage(): void
    {
        $d = $this->seedRecapTestData();
        $res = $this->recapService->getRecap(['section_id' => $d['secB1Id']]);

        // Only Temuan 3 in Section B1 (5 BH Clamp)
        $this->assertCount(1, $res['global_recap']);
        $this->assertSame('5.00', $res['global_recap'][0]['total_quantity']);
    }

    /**
     * Test 13: No cross-ULP leakage
     */
    public function testNoCrossUlpLeakage(): void
    {
        $d = $this->seedRecapTestData();
        // Restrict user to ULP B
        $res = $this->recapService->getRecap([], $d['ulpBId']);

        $this->assertCount(1, $res['global_recap']);
        $this->assertSame('5.00', $res['global_recap'][0]['total_quantity']);
    }

    /**
     * Test 14: Join Multiplication Prevention
     * Verifies that each row in temuan_materials contributes EXACTLY once.
     */
    public function testJoinMultiplicationPrevention(): void
    {
        $this->seedRecapTestData();
        $res = $this->recapService->getRecap();

        $txCount = $this->db->table('temuan_materials')->countAllResults();
        $this->assertSame($txCount, count($res['detail_rows']));
    }

    /**
     * Test 15: Detail -> Section -> Penyulang -> ULP -> Global Mathematical Reconciliation
     */
    public function testMathematicalReconciliationAcrossAllLevels(): void
    {
        $this->seedRecapTestData();
        $res = $this->recapService->getRecap();

        $this->assertSame('BALANCED', $res['reconciliation']['status']);
        $this->assertTrue($res['reconciliation']['checks']['BH']['balanced']);
        $this->assertTrue($res['reconciliation']['checks']['SET']['balanced']);
    }

    /**
     * Test 16: Empty Dataset returns deterministic empty state
     */
    public function testEmptyDatasetReturnsDeterministicEmptyState(): void
    {
        // Query for a non-existent ULP ID
        $res = $this->recapService->getRecap(['ulp_id' => 99999]);

        $this->assertSame('SUCCESS', $res['status']);
        $this->assertSame(0, $res['kpi']['total_material_lines']);
        $this->assertEmpty($res['global_recap']);
        $this->assertEmpty($res['detail_rows']);
    }

    /**
     * Test 17: Invalid Cross-Scope Filter Rejected
     */
    public function testInvalidCrossScopeFilterRejected(): void
    {
        $d = $this->seedRecapTestData();

        // Section A1 belongs to Penyulang A1, passing Penyulang B1 must be rejected!
        $res = $this->recapService->getRecap([
            'penyulang_id' => $d['pylB1Id'],
            'section_id'   => $d['secA1Id'],
        ]);

        $this->assertSame('INVALID_FILTER', $res['status']);
        $this->assertStringContainsString('tidak sesuai', $res['message']);
    }

    /**
     * Test 18: Zero Operational Data Mutations
     */
    public function testZeroOperationalDataMutations(): void
    {
        $this->seedRecapTestData();

        $countTmBefore = $this->db->table('temuan_materials')->countAllResults();
        $countTBefore  = $this->db->table('temuan')->countAllResults();
        $countABefore  = $this->db->table('assets')->countAllResults();

        // Run recap
        $this->recapService->getRecap();

        $countTmAfter = $this->db->table('temuan_materials')->countAllResults();
        $countTAfter  = $this->db->table('temuan')->countAllResults();
        $countAAfter  = $this->db->table('assets')->countAllResults();

        $this->assertSame($countTmBefore, $countTmAfter);
        $this->assertSame($countTBefore, $countTAfter);
        $this->assertSame($countABefore, $countAAfter);
    }
}
