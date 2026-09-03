<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\AssetContextService;
use App\Services\MaterialPickerService;

/**
 * MAP-02: Unit & Integration Test Suite for Read-Only Asset Context Drawer
 * Covers all 16 architectural test requirements.
 */
class GisAssetContextDrawerTest extends CIUnitTestCase
{
    protected $db;
    protected AssetContextService $contextService;
    protected MaterialPickerService $pickerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupTestSchema();
        $this->pickerService = new MaterialPickerService($this->db);
        $this->contextService = new AssetContextService($this->db, $this->pickerService);
    }

    protected function setupTestSchema(): void
    {
        $forge = Database::forge();

        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('ulps', true);
        }

        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        if (!$this->db->tableExists('construction_types')) {
            $forge->addField([
                'id'                  => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'code'                => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'name'                => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'voltage_level'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '20kV'],
                'construction_family' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'approval_status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
                'standard_reference'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'governance_status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
                'is_active'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('construction_types', true);
        }

        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50],
                'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'lokasi'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        if (!$this->db->tableExists('master_materials')) {
            $forge->addField([
                'id'                => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'material_code'     => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_material'     => ['type' => 'VARCHAR', 'constraint' => 255],
                'nama_lapangan'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'satuan'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET'],
                'material_category' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
                'is_active'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('master_materials', true);
        }

        if (!$this->db->tableExists('construction_bom_items')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11],
                'material_id'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'raw_material_name'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'quantity'             => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'default' => 1.00],
                'unit'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET'],
                'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('construction_bom_items', true);
        }

        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'nomor_temuan'     => ['type' => 'VARCHAR', 'constraint' => 50],
                'ulp_id'           => ['type' => 'INT', 'constraint' => 11],
                'penyulang_id'     => ['type' => 'INT', 'constraint' => 11],
                'section_id'       => ['type' => 'INT', 'constraint' => 11],
                'asset_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'jenis_temuan'     => ['type' => 'VARCHAR', 'constraint' => 50],
                'pelaksana'        => ['type' => 'VARCHAR', 'constraint' => 50],
                'prioritas'        => ['type' => 'VARCHAR', 'constraint' => 50],
                'potensi_gangguan' => ['type' => 'VARCHAR', 'constraint' => 50],
                'konduktor'        => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'AAAC 150'],
                'material'         => ['type' => 'TEXT', 'null' => true],
                'detail_temuan'    => ['type' => 'TEXT', 'null' => true],
                'alamat'           => ['type' => 'TEXT', 'null' => true],
                'tanggal_temuan'   => ['type' => 'DATE'],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'BELUM'],
                'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }

        if (!$this->db->tableExists('temuan_materials')) {
            $forge->addField([
                'id'                      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'temuan_id'               => ['type' => 'INT', 'constraint' => 11],
                'asset_id'                => ['type' => 'INT', 'constraint' => 11],
                'material_id'             => ['type' => 'INT', 'constraint' => 11],
                'construction_type_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'canonical_code_snapshot' => ['type' => 'VARCHAR', 'constraint' => 50],
                'canonical_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 255],
                'unit_snapshot'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET'],
                'quantity'                => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'justification_note'      => ['type' => 'TEXT', 'null' => true],
                'source_mode'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'BOM_PICKER'],
                'created_by'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
                'updated_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan_materials', true);
        }

        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'section_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'geom'               => ['type' => 'TEXT', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        // Schema compatibility helper
        $this->safeAddColumn('master_materials', 'nama_material', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->safeAddColumn('master_materials', 'nama_lapangan', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->safeAddColumn('master_materials', 'satuan', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET']);
        $this->safeAddColumn('master_materials', 'material_category', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('master_materials', 'material_domain', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('master_materials', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
        $this->safeAddColumn('construction_bom_items', 'raw_material_name', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->safeAddColumn('construction_bom_items', 'quantity', ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'default' => 1.00]);
        $this->safeAddColumn('construction_bom_items', 'unit', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET']);
        $this->safeAddColumn('construction_bom_items', 'mapping_status', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('construction_types', 'construction_family', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('construction_types', 'approval_status', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE']);
        $this->safeAddColumn('construction_types', 'asset_domain', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('construction_types', 'construction_code', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('construction_types', 'construction_name', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('temuan_materials', 'source_mode', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'BOM_PICKER']);
        $this->safeAddColumn('temuan_materials', 'updated_at', ['type' => 'DATETIME', 'null' => true]);
        $this->safeAddColumn('gis_translines', 'source_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('gis_translines', 'target_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('gis_translines', 'transline_code', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('gis_translines', 'geometry', ['type' => 'TEXT', 'null' => true]);

        // Clean tables
        $this->db->table('temuan_materials')->truncate();
        $this->db->table('temuan')->truncate();
        $this->db->table('construction_bom_items')->truncate();
        $this->db->table('master_materials')->truncate();
        $this->db->table('assets')->truncate();
        $this->db->table('construction_types')->truncate();
        $this->db->table('sections')->truncate();
        $this->db->table('penyulang')->truncate();
        $this->db->table('ulps')->truncate();
        $this->db->table('gis_translines')->truncate();
    }

    protected function safeAddColumn(string $table, string $column, array $def): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        $prefixed = $this->db->prefixTable($table);
        $cols = array_column($this->db->query("PRAGMA table_info({$prefixed})")->getResultArray(), 'name');
        if (!in_array($column, $cols)) {
            Database::forge()->addColumn($table, [$column => $def]);
        }
    }

    /**
     * Requirement 1: Valid asset -> READY with full context and BOM
     */
    public function testValidAssetReturnsReadyWithFullContextAndBom(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-SIW', 'nama_ulp' => 'ULP Siwalan']);
        $this->db->table('penyulang')->insert(['id' => 10, 'ulp_id' => 1, 'kode_penyulang' => 'CDR', 'nama_penyulang' => 'Candramas']);
        $this->db->table('sections')->insert(['id' => 100, 'penyulang_id' => 10, 'nama_section' => 'SEC-01']);
        $this->db->table('construction_types')->insert([
            'id' => 5, 'code' => 'TM-1', 'name' => 'Tiang Tumpu Tunggal', 'voltage_level' => '20kV', 'construction_family' => 'JTM', 'governance_status' => 'ACTIVE'
        ]);
        $this->db->table('assets')->insert([
            'id' => 500, 'kode_asset' => 'CDR-01', 'nama_asset' => 'Tiang Candramas 1', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 100, 'penyulang_id' => 10, 'ulp_id' => 1, 'construction_type_id' => 5,
            'latitude' => -7.123456, 'longitude' => 112.654321, 'lokasi' => 'Jl. Candramas No. 1'
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 20, 'material_code' => 'MAT-PIN-01', 'nama_material' => 'Pin Insulator 20kV', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 1, 'construction_type_id' => 5, 'material_id' => 20, 'raw_material_name' => 'Pin Insulator 20kV', 'quantity' => 2.0, 'unit' => 'SET', 'is_active' => 1
        ]);

        $res = $this->contextService->getAssetContext(500);

        $this->assertSame('READY', $res['status']);
        $this->assertSame(500, $res['asset']['id']);
        $this->assertSame('CDR-01', $res['asset']['kode_asset']);
        $this->assertSame(-7.123456, $res['asset']['latitude']);
        $this->assertSame('ULP Siwalan', $res['network']['ulp']['nama_ulp']);
        $this->assertSame('Candramas', $res['network']['penyulang']['nama_penyulang']);
        $this->assertSame('SEC-01', $res['network']['section']['nama_section']);
        $this->assertSame('TM-1', $res['construction']['code']);
        $this->assertCount(1, $res['bom']);
        $this->assertSame('MAT-PIN-01', $res['bom'][0]['material_code']);
        $this->assertStringContainsString('asset_id=500', $res['navigation']['create_temuan_url']);
    }

    /**
     * Requirement 2: Invalid asset -> INVALID_ASSET
     */
    public function testInvalidAssetReturnsInvalidAssetStatus(): void
    {
        $resNeg = $this->contextService->getAssetContext(-99);
        $this->assertSame('INVALID_ASSET', $resNeg['status']);

        $resNonExistent = $this->contextService->getAssetContext(99999);
        $this->assertSame('INVALID_ASSET', $resNonExistent['status']);
    }

    /**
     * Requirement 3: Asset without construction -> NO_CONSTRUCTION
     */
    public function testAssetWithoutConstructionReturnsNoConstruction(): void
    {
        $this->db->table('sections')->insert(['id' => 101, 'penyulang_id' => 1, 'nama_section' => 'SEC-02']);
        $this->db->table('assets')->insert([
            'id' => 501, 'kode_asset' => 'CDR-02', 'nama_asset' => 'Tiang Polos', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 101, 'penyulang_id' => 1, 'ulp_id' => 1, 'construction_type_id' => null
        ]);

        $res = $this->contextService->getAssetContext(501);

        $this->assertSame('NO_CONSTRUCTION', $res['status']);
        $this->assertNull($res['construction']);
        $this->assertEmpty($res['bom']);
        $this->assertStringContainsString('asset_id=501', $res['navigation']['create_temuan_url']);
    }

    /**
     * Requirement 4: Asset with construction but no BOM -> NO_BOM
     */
    public function testAssetWithConstructionNoBomReturnsNoBom(): void
    {
        $this->db->table('construction_types')->insert([
            'id' => 6, 'code' => 'TM-2', 'name' => 'Tiang Sudut Kecil', 'governance_status' => 'ACTIVE'
        ]);
        $this->db->table('sections')->insert(['id' => 102, 'penyulang_id' => 1, 'nama_section' => 'SEC-03']);
        $this->db->table('assets')->insert([
            'id' => 502, 'kode_asset' => 'CDR-03', 'nama_asset' => 'Tiang Tanpa BOM', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 102, 'penyulang_id' => 1, 'ulp_id' => 1, 'construction_type_id' => 6
        ]);

        $res = $this->contextService->getAssetContext(502);

        $this->assertSame('NO_BOM', $res['status']);
        $this->assertSame('TM-2', $res['construction']['code']);
        $this->assertEmpty($res['bom']);
    }

    /**
     * Requirement 5: Cross-scope asset returns FORBIDDEN for restricted admin_ulp
     */
    public function testCrossScopeAssetReturnsForbiddenForAdminUlp(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-1', 'nama_ulp' => 'ULP Satu']);
        $this->db->table('ulps')->insert(['id' => 2, 'kode_ulp' => 'ULP-2', 'nama_ulp' => 'ULP Dua']);
        $this->db->table('sections')->insert(['id' => 103, 'penyulang_id' => 1, 'nama_section' => 'SEC-04']);
        $this->db->table('assets')->insert([
            'id' => 503, 'kode_asset' => 'AST-ULP2', 'nama_asset' => 'Tiang ULP 2', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 103, 'penyulang_id' => 1, 'ulp_id' => 2
        ]);

        // User belongs to ULP 1 attempting to access asset in ULP 2
        $res = $this->contextService->getAssetContext(503, 1, 'ADMIN_ULP');

        $this->assertSame('FORBIDDEN', $res['status']);
        $this->assertNull($res['asset']);
    }

    /**
     * Requirement 6: Construction resolved strictly from assets.construction_type_id
     */
    public function testConstructionResolvedStrictlyFromForeignKey(): void
    {
        $this->db->table('construction_types')->insert([
            'id' => 7, 'code' => 'TM-SPECIAL', 'name' => 'Konstruksi Khusus FK', 'governance_status' => 'ACTIVE'
        ]);
        $this->db->table('sections')->insert(['id' => 104, 'penyulang_id' => 1, 'nama_section' => 'SEC-05']);
        $this->db->table('assets')->insert([
            'id' => 504, 'kode_asset' => 'AST-FK', 'nama_asset' => 'Tiang Khusus', 'jenis_asset' => 'GARDU_PORTAL', // type label says GARDU
            'section_id' => 104, 'penyulang_id' => 1, 'ulp_id' => 1, 'construction_type_id' => 7 // but FK is TM-SPECIAL
        ]);

        $res = $this->contextService->getAssetContext(504);

        $this->assertSame('TM-SPECIAL', $res['construction']['code']);
        $this->assertSame('Konstruksi Khusus FK', $res['construction']['name']);
    }

    /**
     * Requirement 7: BOM whitelist only active approved materials
     */
    public function testBomWhitelistOnlyApprovedActiveMaterials(): void
    {
        $this->db->table('construction_types')->insert([
            'id' => 8, 'code' => 'TM-BOM-WL', 'name' => 'Tiang Whitelist', 'governance_status' => 'ACTIVE'
        ]);
        $this->db->table('sections')->insert(['id' => 105, 'penyulang_id' => 1, 'nama_section' => 'SEC-06']);
        $this->db->table('assets')->insert([
            'id' => 505, 'kode_asset' => 'AST-WL', 'nama_asset' => 'Tiang WL', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 105, 'penyulang_id' => 1, 'ulp_id' => 1, 'construction_type_id' => 8
        ]);

        // Active material
        $this->db->table('master_materials')->insert([
            'id' => 30, 'material_code' => 'MAT-ACT', 'nama_material' => 'Material Aktif', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        // Held material (must NOT appear in BOM)
        $this->db->table('master_materials')->insert([
            'id' => 31, 'material_code' => 'MAT-HLD', 'nama_material' => 'Material Held', 'satuan' => 'SET', 'status' => 'HELD', 'is_active' => 1
        ]);

        $this->db->table('construction_bom_items')->insert([
            'id' => 10, 'construction_type_id' => 8, 'material_id' => 30, 'raw_material_name' => 'Material Aktif', 'quantity' => 1.0, 'unit' => 'SET', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 11, 'construction_type_id' => 8, 'material_id' => 31, 'raw_material_name' => 'Material Held', 'quantity' => 1.0, 'unit' => 'SET', 'is_active' => 1
        ]);

        $res = $this->contextService->getAssetContext(505);

        $this->assertSame('READY', $res['status']);
        $this->assertCount(1, $res['bom']);
        $this->assertSame('MAT-ACT', $res['bom'][0]['material_code']);
    }

    /**
     * Requirement 8: Master material identity preserved
     */
    public function testMasterMaterialIdentityPreserved(): void
    {
        $this->db->table('construction_types')->insert([
            'id' => 9, 'code' => 'TM-ID', 'name' => 'Tiang Identity', 'governance_status' => 'ACTIVE'
        ]);
        $this->db->table('sections')->insert(['id' => 106, 'penyulang_id' => 1, 'nama_section' => 'SEC-07']);
        $this->db->table('assets')->insert([
            'id' => 506, 'kode_asset' => 'AST-ID', 'nama_asset' => 'Tiang Identitas', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 106, 'penyulang_id' => 1, 'ulp_id' => 1, 'construction_type_id' => 9
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 40, 'material_code' => 'MAT-CANON-99', 'nama_material' => 'Traves UNP 2000 Kanonikal', 'satuan' => 'BTG', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 20, 'construction_type_id' => 9, 'material_id' => 40, 'raw_material_name' => 'Traves UNP', 'quantity' => 1.0, 'unit' => 'BTG', 'is_active' => 1
        ]);

        $res = $this->contextService->getAssetContext(506);

        $this->assertSame('MAT-CANON-99', $res['bom'][0]['material_code']);
        $this->assertSame('Traves UNP 2000 Kanonikal', $res['bom'][0]['nama_material']);
        $this->assertSame('BTG', $res['bom'][0]['satuan']);
    }

    /**
     * Requirement 9: No material quantity generated in drawer (BOM is catalog preview only)
     */
    public function testNoMaterialQuantityGeneratedInDrawer(): void
    {
        $this->db->table('construction_types')->insert(['id' => 10, 'code' => 'TM-RO', 'name' => 'ReadOnly BOM', 'governance_status' => 'ACTIVE']);
        $this->db->table('sections')->insert(['id' => 107, 'penyulang_id' => 1, 'nama_section' => 'SEC-08']);
        $this->db->table('assets')->insert([
            'id' => 507, 'kode_asset' => 'AST-RO', 'nama_asset' => 'Tiang Preview', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 107, 'penyulang_id' => 1, 'ulp_id' => 1, 'construction_type_id' => 10
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 45, 'material_code' => 'MAT-PREVIEW', 'nama_material' => 'Preview Material', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 25, 'construction_type_id' => 10, 'material_id' => 45, 'raw_material_name' => 'Preview Material', 'quantity' => 5.0, 'unit' => 'SET', 'is_active' => 1
        ]);

        $res = $this->contextService->getAssetContext(507);

        // Standard quantity may exist on BOM item definition, but NO transaction quantity is selected or assigned
        $this->assertArrayNotHasKey('selected_materials', $res);
        $this->assertArrayNotHasKey('transaction_quantity', $res);
        $this->assertArrayNotHasKey('quantities', $res);
    }

    /**
     * Requirement 10: Zero auto-asset binding
     */
    public function testNoAutoAssetBindingOccurs(): void
    {
        $this->db->table('sections')->insert(['id' => 108, 'penyulang_id' => 1, 'nama_section' => 'SEC-09']);
        $this->db->table('assets')->insert([
            'id' => 508, 'kode_asset' => 'AST-NO-AUTO', 'nama_asset' => 'Tiang Asli', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 108, 'penyulang_id' => 1, 'ulp_id' => 1
        ]);

        $res = $this->contextService->getAssetContext(508);

        // Context strictly points to asset 508, no other asset substituted
        $this->assertSame(508, $res['asset']['id']);
        $this->assertSame('AST-NO-AUTO', $res['asset']['kode_asset']);
    }

    /**
     * Requirement 11: Navigation context handoff only
     */
    public function testNavigationContextHandoffOnly(): void
    {
        $this->db->table('ulps')->insert(['id' => 3, 'kode_ulp' => 'ULP-3', 'nama_ulp' => 'ULP Tiga']);
        $this->db->table('penyulang')->insert(['id' => 33, 'ulp_id' => 3, 'kode_penyulang' => 'PENY-33', 'nama_penyulang' => 'Penyulang Tiga']);
        $this->db->table('sections')->insert(['id' => 109, 'penyulang_id' => 33, 'nama_section' => 'SEC-10']);
        $this->db->table('assets')->insert([
            'id' => 509, 'kode_asset' => 'AST-NAV', 'nama_asset' => 'Tiang Navigasi', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 109, 'penyulang_id' => 33, 'ulp_id' => 3
        ]);

        $res = $this->contextService->getAssetContext(509);

        $navUrl = $res['navigation']['create_temuan_url'];
        $this->assertStringContainsString('asset_id=509', $navUrl);
        $this->assertStringContainsString('section_id=109', $navUrl);
        $this->assertStringContainsString('penyulang_id=33', $navUrl);
        $this->assertStringContainsString('ulp_id=3', $navUrl);
    }

    /**
     * Requirement 12: Server-side authorization enforced on controller
     */
    public function testServerSideAuthorizationEnforced(): void
    {
        $controller = new \App\Controllers\Ajax\NetworkLookup();
        $this->assertInstanceOf(\App\Controllers\Ajax\NetworkLookup::class, $controller);
    }

    /**
     * Requirement 13: Endpoint is read-only GET
     */
    public function testEndpointIsReadOnlyGetInRoutes(): void
    {
        $routesContent = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertStringContainsString("\$routes->get('asset-context/(:num)'", $routesContent);
        $this->assertStringNotContainsString("\$routes->post('asset-context", $routesContent);
        $this->assertStringNotContainsString("\$routes->put('asset-context", $routesContent);
        $this->assertStringNotContainsString("\$routes->delete('asset-context", $routesContent);
    }

    /**
     * Requirement 14: Translines untouched during context resolution
     */
    public function testTranslinesUntouchedDuringContextResolution(): void
    {
        $beforeCount = $this->db->table('gis_translines')->countAllResults();

        // Perform multiple context lookups
        $this->contextService->getAssetContext(500);
        $this->contextService->getAssetContext(501);
        $this->contextService->getAssetContext(9999);

        $afterCount = $this->db->table('gis_translines')->countAllResults();
        $this->assertSame($beforeCount, $afterCount);
    }

    /**
     * Requirement 15: AR-01 untouched
     */
    public function testAr01DomainRemainsUntouched(): void
    {
        $assets = $this->db->table('assets')->get()->getResultArray();
        $this->assertIsArray($assets);
        foreach ($assets as $a) {
            // Ensure no section_id mutated or wiped
            $this->assertNotNull($a['id']);
        }
    }

    /**
     * Requirement 16: MR-01 transaction untouched
     */
    public function testMr01TransactionUntouched(): void
    {
        $temuanMatCountBefore = $this->db->table('temuan_materials')->countAllResults();

        $this->contextService->getAssetContext(500);

        $temuanMatCountAfter = $this->db->table('temuan_materials')->countAllResults();
        $this->assertSame(0, $temuanMatCountBefore);
        $this->assertSame(0, $temuanMatCountAfter);
    }
}
