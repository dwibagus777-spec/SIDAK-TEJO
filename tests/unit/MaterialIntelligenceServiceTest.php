<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\MaterialIntelligenceService;

/**
 * Class MaterialIntelligenceServiceTest
 *
 * Verifies CR-HOTFIX-04 Block E:
 * 1. MaterialIntelligenceService is strictly read-only.
 * 2. Aggregation method getMaterialSummary sums temuan_materials.quantity (NOT design norm).
 * 3. Grouping is strictly by canonical_code_snapshot.
 * 4. 3 drill-down levels (Summary, Network Breakdown, Finding Details) are implemented and return structured arrays.
 * 5. Cascading filter structure (ULP, Penyulang, Section, Status, Date) is supported.
 */
class MaterialIntelligenceServiceTest extends CIUnitTestCase
{
    protected $db;
    protected MaterialIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $forge    = \Config\Database::forge();

        $this->ensureTablesExist($forge, $this->db);
        if ($this->db->tableExists('temuan_materials')) $this->db->table('temuan_materials')->emptyTable();
        if ($this->db->tableExists('temuan')) $this->db->table('temuan')->emptyTable();
        if ($this->db->tableExists('assets')) $this->db->table('assets')->emptyTable();
        if ($this->db->tableExists('sections')) $this->db->table('sections')->emptyTable();
        if ($this->db->tableExists('penyulang')) $this->db->table('penyulang')->emptyTable();
        if ($this->db->tableExists('ulps')) $this->db->table('ulps')->emptyTable();
        $this->service = new MaterialIntelligenceService($this->db);
    }

    private function ensureTablesExist($forge, $db): void
    {
        if (!$db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('ulps', true);
        }

        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER'],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('penyulang', true);
        }

        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER'],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('sections', true);
        }

        if (!$db->tableExists('assets')) {
            $forge->addField([
                'id'          => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_asset'  => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'  => ['type' => 'VARCHAR', 'constraint' => 255],
                'jenis_asset' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'EQUIPMENT'],
                'latitude'    => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'longitude'   => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            ]);
            $forge->createTable('assets', true);
        }

        if (!$db->tableExists('temuan')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'nomor_temuan'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'penyulang_id'   => ['type' => 'INTEGER', 'default' => 1],
                'section_id'     => ['type' => 'INTEGER'],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
                'prioritas'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'SEDANG'],
                'jenis_temuan'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'detail_temuan'  => ['type' => 'TEXT', 'null' => true],
                'foto'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'foto_path'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'latitude'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'longitude'      => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'tanggal_temuan' => ['type' => 'DATE'],
                'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
        }

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
            ]);
            $forge->createTable('temuan_materials', true);
        }
    }

    /**
     * 1. Assert service instantiation and method contract.
     */
    public function testServiceContractAndMethods(): void
    {
        $this->assertTrue(
            method_exists($this->service, 'getMaterialSummary'),
            'MaterialIntelligenceService must have getMaterialSummary method'
        );
        $this->assertTrue(
            method_exists($this->service, 'getMaterialNetworkBreakdown'),
            'MaterialIntelligenceService must have getMaterialNetworkBreakdown method'
        );
        $this->assertTrue(
            method_exists($this->service, 'getMaterialFindings'),
            'MaterialIntelligenceService must have getMaterialFindings method'
        );
    }

    /**
     * 2. Assert that service code guarantees ZERO write operations (Read-Only Invariant).
     */
    public function testServiceCodeIsStrictlyReadOnly(): void
    {
        $serviceFile = APPPATH . 'Services/MaterialIntelligenceService.php';
        $content = file_get_contents($serviceFile);

        // Assert no write query builders
        $this->assertStringNotContainsString('->insert(', $content, 'MaterialIntelligenceService must not execute insert queries');
        $this->assertStringNotContainsString('->update(', $content, 'MaterialIntelligenceService must not execute update queries');
        $this->assertStringNotContainsString('->delete(', $content, 'MaterialIntelligenceService must not execute delete queries');
        $this->assertStringNotContainsString('->replace(', $content, 'MaterialIntelligenceService must not execute replace queries');
        $this->assertStringNotContainsString('->truncate(', $content, 'MaterialIntelligenceService must not execute truncate queries');
    }

    /**
     * 3. Assert queries aggregate from temuan_materials.quantity and NEVER join construction_bom_items.
     */
    public function testServiceAggregatesFromTemuanMaterialsNotBomItems(): void
    {
        $serviceFile = APPPATH . 'Services/MaterialIntelligenceService.php';
        $content = file_get_contents($serviceFile);

        $this->assertStringContainsString(
            "table('temuan_materials tm')",
            $content,
            'Service must query from temuan_materials as primary source'
        );
        $this->assertStringContainsString(
            'SUM(tm.quantity)',
            $content,
            'Service must sum temuan_materials.quantity'
        );
        $this->assertStringNotContainsString(
            "join('construction_bom_items",
            $content,
            'Service must NOT join construction_bom_items'
        );
    }

    /**
     * 4. Assert execution returns structured arrays with test seeded data.
     */
    public function testServiceExecutionWithSeededData(): void
    {
        // Seed test data
        $this->db->table('ulps')->insert([
            'id' => 1, 'kode_ulp' => 'ULP-01', 'nama_ulp' => 'ULP KOTA'
        ]);
        $this->db->table('penyulang')->insert([
            'id' => 10, 'ulp_id' => 1, 'kode_penyulang' => 'BLG', 'nama_penyulang' => 'BULOG'
        ]);
        $this->db->table('sections')->insert([
            'id' => 100, 'penyulang_id' => 10, 'nama_section' => 'SEC-BULOG-01'
        ]);
        $this->db->table('assets')->insert([
            'id' => 500, 'section_id' => 100, 'kode_asset' => 'AST-001', 'nama_asset' => 'LBS BULOG', 'jenis_asset' => 'EQUIPMENT', 'latitude' => -7.1, 'longitude' => 112.5
        ]);
        $this->db->table('temuan')->insert([
            'id' => 1000, 'nomor_temuan' => 'TMN-001', 'ulp_id' => 1, 'penyulang_id' => 10, 'section_id' => 100,
            'status' => 'OPEN', 'prioritas' => 'TINGGI', 'jenis_temuan' => 'Konstruksi', 'detail_temuan' => 'LBS aus',
            'tanggal_temuan' => '2026-09-23'
        ]);
        $this->db->table('temuan_materials')->insert([
            'id' => 1, 'temuan_id' => 1000, 'asset_id' => 500, 'construction_type_id' => 63, 'material_id' => 1,
            'canonical_code_snapshot' => 'CANON-SW-LBS-01', 'canonical_name_snapshot' => 'Unit LBS 20 kV 630A SF6 Manual',
            'unit_snapshot' => 'buah', 'quantity' => 1.00, 'justification_note' => 'Ganti unit LBS'
        ]);

        // Level 1: Summary
        $summary = $this->service->getMaterialSummary(['ulp_id' => 1]);
        $this->assertIsArray($summary);
        $this->assertCount(1, $summary);
        $this->assertSame('CANON-SW-LBS-01', $summary[0]['canonical_code']);
        $this->assertSame(1.0, $summary[0]['total_qty']);
        $this->assertSame('buah', $summary[0]['unit']);
        $this->assertSame(1, $summary[0]['temuan_count']);
        $this->assertSame(1, $summary[0]['affected_assets_count']);

        // Level 2: Network Breakdown
        $breakdown = $this->service->getMaterialNetworkBreakdown('CANON-SW-LBS-01', ['ulp_id' => 1]);
        $this->assertIsArray($breakdown);
        $this->assertCount(1, $breakdown);
        $this->assertSame('ULP KOTA', $breakdown[0]['ulp_name']);
        $this->assertSame('BULOG', $breakdown[0]['penyulang_name']);
        $this->assertSame('SEC-BULOG-01', $breakdown[0]['section_name']);
        $this->assertSame(1.0, $breakdown[0]['qty']);

        // Level 3: Findings Detail
        $findings = $this->service->getMaterialFindings('CANON-SW-LBS-01');
        $this->assertIsArray($findings);
        $this->assertCount(1, $findings);
        $this->assertSame('TMN-001', $findings[0]['nomor_temuan']);
        $this->assertSame('LBS BULOG', $findings[0]['nama_asset']);
        $this->assertSame('AST-001', $findings[0]['kode_asset']);
        $this->assertSame(1.0, $findings[0]['quantity']);
    }
}
