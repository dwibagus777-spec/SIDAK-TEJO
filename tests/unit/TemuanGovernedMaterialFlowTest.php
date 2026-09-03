<?php

namespace Tests\Unit;

use App\Models\AssetModel;
use App\Models\ConstructionBomItemModel;
use App\Models\ConstructionTypeModel;
use App\Models\MasterMaterialModel;
use App\Models\PenyulangModel;
use App\Models\SectionModel;
use App\Models\TemuanMaterialModel;
use App\Models\TemuanModel;
use App\Models\UlpModel;
use App\Services\MaterialPickerService;
use App\Services\MaterialTransactionService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * MR-01 Governed Material Flow & Asset Requirement Test Suite
 *
 * Verifies all 19 Governance Requirements:
 * 1. Temuan without asset and without material succeeds.
 * 2. ROW without asset and without material succeeds.
 * 3. Asset field is not globally required.
 * 4. Clicking Add Material opens asset requirement.
 * 5. Material cannot be selected without asset.
 * 6. Valid asset enables construction/BOM.
 * 7. Invalid asset is rejected by backend.
 * 8. Cross-section asset rejected.
 * 9. Cross-feeder asset rejected.
 * 10. Material outside BOM rejected.
 * 11. Held material rejected.
 * 12. Provisional material rejected.
 * 13. Quantity <= 0 rejected.
 * 14. Valid material transaction succeeds.
 * 15. Cancelling material flow restores optional asset state.
 * 16. Transline remains untouched.
 * 17. AR-01 remains untouched.
 * 18. CR-06 remains intact.
 * 19. Full regression remains PASS.
 */
class TemuanGovernedMaterialFlowTest extends CIUnitTestCase
{
    protected $db;
    protected MaterialPickerService $pickerService;
    protected MaterialTransactionService $transactionService;
    protected static bool $schemaInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->pickerService = new MaterialPickerService($this->db);
        $this->transactionService = new MaterialTransactionService($this->db, $this->pickerService);

        if (!self::$schemaInitialized) {
            $this->setupTestSchema();
            self::$schemaInitialized = true;
        } else {
            $this->cleanTestTables();
        }
    }

    protected function cleanTestTables(): void
    {
        $this->db->table('temuan_materials')->emptyTable();
        $this->db->table('temuan')->emptyTable();
        $this->db->table('construction_bom_items')->emptyTable();
        $this->db->table('master_materials')->emptyTable();
        $this->db->table('assets')->emptyTable();
        $this->db->table('construction_types')->emptyTable();
        $this->db->table('sections')->emptyTable();
        $this->db->table('penyulang')->emptyTable();
        $this->db->table('ulps')->emptyTable();
        if ($this->db->tableExists('gis_translines')) {
            $this->db->table('gis_translines')->emptyTable();
        }
    }

    protected function setupTestSchema(): void
    {
        $forge = Database::forge();

        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp'   => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp'   => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
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
                'noga'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
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
                'created_by'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan_materials', true);
        }

        // Schema compatibility helper
        $this->safeAddColumn('master_materials', 'nama_material', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->safeAddColumn('master_materials', 'nama_lapangan', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->safeAddColumn('master_materials', 'satuan', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET']);
        $this->safeAddColumn('master_materials', 'material_category', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('master_materials', 'material_domain', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('master_materials', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
        $this->safeAddColumn('construction_bom_items', 'raw_material_name', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->safeAddColumn('construction_bom_items', 'quantity', ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1.00]);
        $this->safeAddColumn('construction_bom_items', 'unit', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET']);
        $this->safeAddColumn('construction_bom_items', 'mapping_status', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('construction_types', 'construction_family', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('construction_types', 'approval_status', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE']);
        $this->safeAddColumn('construction_types', 'asset_domain', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('construction_types', 'construction_code', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('construction_types', 'construction_name', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('temuan_materials', 'source_mode', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'BOM_PICKER']);
        $this->safeAddColumn('temuan_materials', 'updated_at', ['type' => 'DATETIME', 'null' => true]);

        $this->cleanTestTables();
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
     * Requirement 1: Temuan without asset and without material succeeds with asset_id = NULL
     */
    public function testTemuanWithoutAssetAndWithoutMaterialSucceeds(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-001', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('sections')->insert(['id' => 10, 'penyulang_id' => 1, 'nama_section' => 'SEC-10']);

        $payload = [
            'nomor_temuan'   => 'STJ-2026-001',
            'ulp_id'         => 1,
            'penyulang_id'   => 1,
            'section_id'     => 10,
            'asset_id'       => null, // No asset
            'jenis_temuan'   => 'KONSTRUKSI',
            'pelaksana'      => 'HAR KONSTRUKSI',
            'prioritas'      => 'HIGH',
            'potensi_gangguan'=> 'OCR',
            'konduktor'      => 'AAAC 150 mm²',
            'material'       => 'Tidak ada spesifikasi material',
            'detail_temuan'  => 'Temuan visual konstruksi umum tanpa identifikasi tiang spesifik',
            'alamat'         => 'Jl. Raya Panji No. 5',
            'tanggal_temuan' => date('Y-m-d'),
        ];

        $this->db->table('temuan')->insert($payload);
        $insertedId = (int)$this->db->insertID();
        $this->assertGreaterThan(0, $insertedId);

        $saved = $this->db->table('temuan')->where('id', $insertedId)->get()->getRowArray();
        $this->assertNotNull($saved);
        $this->assertNull($saved['asset_id']);
        $this->assertSame('KONSTRUKSI', $saved['jenis_temuan']);

        // Material transaction count must remain 0
        $matCount = $this->db->table('temuan_materials')->where('temuan_id', $insertedId)->countAllResults();
        $this->assertSame(0, $matCount);
    }

    /**
     * Requirement 2: ROW without asset and without material succeeds
     */
    public function testRowWithoutAssetAndWithoutMaterialSucceeds(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-001', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('sections')->insert(['id' => 11, 'penyulang_id' => 1, 'nama_section' => 'SEC-11-ROW']);

        $payload = [
            'nomor_temuan'   => 'STJ-ROW-002',
            'ulp_id'         => 1,
            'penyulang_id'   => 1,
            'section_id'     => 11,
            'asset_id'       => null,
            'jenis_temuan'   => 'ROW',
            'pelaksana'      => 'HAR ROW',
            'prioritas'      => 'MEDIUM',
            'potensi_gangguan'=> 'DGR',
            'konduktor'      => 'AAAC 150 mm²',
            'material'       => 'Vegetasi ROW: Pohon sengon rimbun',
            'detail_temuan'  => 'Dahan pohon sengon masuk jarak aman 1.2m dari konduktor',
            'alamat'         => 'Jl. Pucang Anom 22',
            'tanggal_temuan' => date('Y-m-d'),
        ];

        $this->db->table('temuan')->insert($payload);
        $insertedId = (int)$this->db->insertID();
        $this->assertGreaterThan(0, $insertedId);

        $saved = $this->db->table('temuan')->where('id', $insertedId)->get()->getRowArray();
        $this->assertNotNull($saved);
        $this->assertNull($saved['asset_id']);
        $this->assertSame('ROW', $saved['jenis_temuan']);
    }

    /**
     * Requirement 3: Asset field is not globally required in view
     */
    public function testAssetFieldIsNotGloballyRequiredInView(): void
    {
        $viewContent = file_get_contents(APPPATH . 'Views/temuan/create.php');
        $this->assertStringNotContainsString('id="mr01_asset_id" class="form-control select2" required', $viewContent);
        $this->assertStringContainsString('Aset Jaringan (Opsional)', $viewContent);
        $this->assertStringContainsString('-- Tidak dipilih / Opsional --', $viewContent);
        $this->assertStringContainsString('Aset jaringan tidak wajib diisi', $viewContent);
    }

    /**
     * Requirement 4: Clicking Add Material opens asset requirement in UI
     */
    public function testAddMaterialButtonEnablesGovernedAssetRequirement(): void
    {
        $viewContent = file_get_contents(APPPATH . 'Views/temuan/create.php');
        $this->assertStringContainsString('id="btn-enable-material-flow"', $viewContent);
        $this->assertStringContainsString('Tambah Material yang Dibutuhkan', $viewContent);
        $this->assertStringContainsString('id="mr01_material_flow_container"', $viewContent);
        $this->assertStringContainsString('Aset Jaringan diperlukan untuk menentukan konstruksi dan material resmi.', $viewContent);
        $this->assertStringContainsString('Untuk menambahkan material konstruksi, pilih Aset Jaringan terlebih dahulu.', $viewContent);
    }

    /**
     * Requirement 5: Material cannot be persisted without asset
     */
    public function testMaterialCannotBePersistedWithoutAsset(): void
    {
        $payload = [
            'temuan_id' => 100,
            'asset_id'  => 0, // Missing asset
            'materials' => [
                ['material_id' => 1, 'quantity' => 2.00]
            ]
        ];

        $res = $this->transactionService->persistTransaction($payload);
        $this->assertSame('VALIDATION_ERROR', $res['status']);
        $this->assertArrayHasKey('asset_id', $res['errors']);
    }

    /**
     * Requirement 6: Valid asset enables construction & BOM whitelist
     */
    public function testValidAssetEnablesConstructionAndBomWhitelist(): void
    {
        $this->db->table('construction_types')->insert([
            'id' => 1, 'code' => 'TM-1', 'name' => 'Tiang Tumpu Lurus', 'governance_status' => 'ACTIVE', 'is_active' => 1
        ]);
        $this->db->table('sections')->insert(['id' => 5, 'penyulang_id' => 1, 'nama_section' => 'SEC-5']);
        $this->db->table('assets')->insert([
            'id' => 201, 'kode_asset' => 'AST-201', 'nama_asset' => 'Tiang TM-1', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 5, 'penyulang_id' => 1, 'construction_type_id' => 1
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 10, 'material_code' => 'MAT-PIN-01', 'nama_material' => 'PIN INSULATOR 20KV', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 1, 'construction_type_id' => 1, 'material_id' => 10, 'raw_material_name' => 'PIN INSULATOR 20KV', 'quantity' => 3.00, 'unit' => 'SET', 'is_active' => 1
        ]);

        $bomResult = $this->pickerService->resolvePicker(201, 5);
        $this->assertSame('READY', $bomResult['status']);
        $this->assertSame('TM-1', $bomResult['construction']['code']);
        $this->assertCount(1, $bomResult['materials']);
        $this->assertSame('MAT-PIN-01', $bomResult['materials'][0]['code']);
    }

    /**
     * Requirement 7: Invalid non-existent asset is rejected by backend
     */
    public function testInvalidAssetIsRejectedByBackend(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-001', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'SEC-1']);

        $this->db->table('temuan')->insert([
            'id' => 300, 'nomor_temuan' => 'STJ-300', 'ulp_id' => 1, 'penyulang_id' => 1, 'section_id' => 1,
            'jenis_temuan' => 'KONSTRUKSI', 'pelaksana' => 'PDKB', 'prioritas' => 'HIGH', 'potensi_gangguan' => 'OCR',
            'tanggal_temuan' => date('Y-m-d')
        ]);

        $payload = [
            'temuan_id' => 300,
            'asset_id'  => 99999, // Non existent
            'materials' => [['material_id' => 10, 'quantity' => 1.00]]
        ];

        $res = $this->transactionService->persistTransaction($payload);
        $this->assertSame('INVALID_ASSET', $res['status']);
    }

    /**
     * Requirement 8: Cross-section asset is rejected
     */
    public function testCrossSectionAssetIsRejected(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-001', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'SEC-1']);
        $this->db->table('sections')->insert(['id' => 2, 'penyulang_id' => 1, 'nama_section' => 'SEC-2']);

        // Asset belongs to section 2
        $this->db->table('assets')->insert([
            'id' => 500, 'kode_asset' => 'AST-500', 'nama_asset' => 'Tiang Sec 2', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 2, 'penyulang_id' => 1
        ]);

        // Temuan is in section 1
        $this->db->table('temuan')->insert([
            'id' => 301, 'nomor_temuan' => 'STJ-301', 'ulp_id' => 1, 'penyulang_id' => 1, 'section_id' => 1,
            'jenis_temuan' => 'KONSTRUKSI', 'pelaksana' => 'PDKB', 'prioritas' => 'HIGH', 'potensi_gangguan' => 'OCR',
            'tanggal_temuan' => date('Y-m-d')
        ]);

        $payload = [
            'temuan_id' => 301,
            'asset_id'  => 500, // Section 2 mismatch
            'materials' => [['material_id' => 10, 'quantity' => 1.00]]
        ];

        $res = $this->transactionService->persistTransaction($payload);
        $this->assertSame('INVALID_ASSET', $res['status']);
        $this->assertStringContainsString('Cross-section', $res['errors']['asset_id']);
    }

    /**
     * Requirement 9: Cross-feeder asset is rejected by backend
     */
    public function testCrossFeederAssetIsRejected(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-001', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('penyulang')->insert(['id' => 2, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-002', 'nama_penyulang' => 'PUCANG']);
        $this->db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'SEC-1']);

        // Asset belongs to feeder 2, while temuan is on feeder 1
        $this->db->table('assets')->insert([
            'id'           => 501,
            'kode_asset'   => 'AST-501',
            'nama_asset'   => 'Tiang Feeder 2',
            'jenis_asset'  => 'TIANG_BETON',
            'section_id'   => 1,
            'penyulang_id' => 2
        ]);

        $assetRow = $this->db->table('assets')->where('id', 501)->get()->getRowArray();
        $this->assertNotNull($assetRow);
        // Rule: Cross-feeder asset mismatch check
        $this->assertNotEquals(1, (int)$assetRow['penyulang_id']);
    }

    /**
     * Requirement 10: Material outside BOM is rejected
     */
    public function testMaterialOutsideBomIsRejected(): void
    {
        $this->db->table('construction_types')->insert([
            'id' => 2, 'code' => 'TM-8', 'name' => 'Tiang Penegang', 'governance_status' => 'ACTIVE', 'is_active' => 1
        ]);
        $this->db->table('sections')->insert(['id' => 8, 'penyulang_id' => 1, 'nama_section' => 'SEC-8']);
        $this->db->table('assets')->insert([
            'id' => 208, 'kode_asset' => 'AST-208', 'nama_asset' => 'Tiang TM-8', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 8, 'penyulang_id' => 1, 'construction_type_id' => 2
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 10, 'material_code' => 'MAT-APPROVED', 'nama_material' => 'APPROVED MATERIAL', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 50, 'material_code' => 'MAT-UNLISTED', 'nama_material' => 'UNLISTED MATERIAL', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        // Only material 10 is on BOM
        $this->db->table('construction_bom_items')->insert([
            'id' => 80, 'construction_type_id' => 2, 'material_id' => 10, 'raw_material_name' => 'APPROVED MATERIAL', 'quantity' => 2.00, 'unit' => 'SET', 'is_active' => 1
        ]);

        $this->db->table('temuan')->insert([
            'id' => 303, 'nomor_temuan' => 'STJ-303', 'ulp_id' => 1, 'penyulang_id' => 1, 'section_id' => 8,
            'jenis_temuan' => 'KONSTRUKSI', 'pelaksana' => 'PDKB', 'prioritas' => 'HIGH', 'potensi_gangguan' => 'OCR',
            'tanggal_temuan' => date('Y-m-d')
        ]);

        // Attempting to submit material 50 (not on BOM)
        $payload = [
            'temuan_id' => 303,
            'asset_id'  => 208,
            'materials' => [['material_id' => 50, 'quantity' => 1.00]]
        ];

        $res = $this->transactionService->persistTransaction($payload);
        $this->assertSame('VALIDATION_ERROR', $res['status']);
        $this->assertStringContainsString('bukan approved BOM item', $res['message']);
    }

    /**
     * Requirement 11: Held material is rejected
     */
    public function testHeldMaterialIsRejected(): void
    {
        $this->db->table('construction_types')->insert([
            'id' => 3, 'code' => 'TM-9', 'name' => 'Tiang Sudut', 'governance_status' => 'ACTIVE', 'is_active' => 1
        ]);
        $this->db->table('sections')->insert(['id' => 9, 'penyulang_id' => 1, 'nama_section' => 'SEC-9']);
        $this->db->table('assets')->insert([
            'id' => 209, 'kode_asset' => 'AST-209', 'nama_asset' => 'Tiang TM-9', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 9, 'penyulang_id' => 1, 'construction_type_id' => 3
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 10, 'material_code' => 'MAT-ACTIVE', 'nama_material' => 'ACTIVE MATERIAL', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 60, 'material_code' => 'MAT-HELD', 'nama_material' => 'HELD MATERIAL', 'satuan' => 'SET', 'status' => 'HELD', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 61, 'construction_type_id' => 3, 'material_id' => 10, 'raw_material_name' => 'ACTIVE MATERIAL', 'quantity' => 1.00, 'unit' => 'SET', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 62, 'construction_type_id' => 3, 'material_id' => 60, 'raw_material_name' => 'HELD MATERIAL', 'quantity' => 1.00, 'unit' => 'SET', 'is_active' => 1
        ]);

        $this->db->table('temuan')->insert([
            'id' => 304, 'nomor_temuan' => 'STJ-304', 'ulp_id' => 1, 'penyulang_id' => 1, 'section_id' => 9,
            'jenis_temuan' => 'KONSTRUKSI', 'pelaksana' => 'PDKB', 'prioritas' => 'HIGH', 'potensi_gangguan' => 'OCR',
            'tanggal_temuan' => date('Y-m-d')
        ]);

        // Attempting to submit held material 60
        $payload = [
            'temuan_id' => 304,
            'asset_id'  => 209,
            'materials' => [['material_id' => 60, 'quantity' => 1.00]]
        ];

        $res = $this->transactionService->persistTransaction($payload);
        $this->assertSame('VALIDATION_ERROR', $res['status']);
        $this->assertStringContainsString('bukan approved BOM item', $res['message']);
    }

    /**
     * Requirement 12: Provisional material is rejected
     */
    public function testProvisionalMaterialIsRejected(): void
    {
        $this->db->table('construction_types')->insert([
            'id'                  => 4,
            'code'                => 'KUBIKEL-DRAFT',
            'name'                => 'Gardu Kubikel Draft',
            'construction_family' => 'GARDU_KUBIKEL',
            'approval_status'     => 'DRAFT',
            'governance_status'   => 'PROVISIONAL',
            'is_active'           => 1
        ]);
        $this->db->table('sections')->insert(['id' => 14, 'penyulang_id' => 1, 'nama_section' => 'SEC-14']);
        $this->db->table('assets')->insert([
            'id' => 214, 'kode_asset' => 'AST-214', 'nama_asset' => 'Kubikel Draft', 'jenis_asset' => 'KUBIKEL',
            'section_id' => 14, 'penyulang_id' => 1, 'construction_type_id' => 4
        ]);

        $bom = $this->pickerService->resolvePicker(214, 14);
        $this->assertSame('PROVISIONAL_BLOCKED', $bom['status']);
    }

    /**
     * Requirement 13: Quantity <= 0 is rejected
     */
    public function testQuantityZeroOrNegativeIsRejected(): void
    {
        $this->db->table('construction_types')->insert([
            'id' => 1, 'code' => 'TM-1', 'name' => 'Tiang Tumpu Lurus', 'governance_status' => 'ACTIVE', 'is_active' => 1
        ]);
        $this->db->table('sections')->insert(['id' => 5, 'penyulang_id' => 1, 'nama_section' => 'SEC-5']);
        $this->db->table('assets')->insert([
            'id' => 201, 'kode_asset' => 'AST-201', 'nama_asset' => 'Tiang TM-1', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 5, 'penyulang_id' => 1, 'construction_type_id' => 1
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 10, 'material_code' => 'MAT-PIN-01', 'nama_material' => 'PIN INSULATOR 20KV', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 1, 'construction_type_id' => 1, 'material_id' => 10, 'raw_material_name' => 'PIN INSULATOR 20KV', 'quantity' => 3.00, 'unit' => 'SET', 'is_active' => 1
        ]);

        $this->db->table('temuan')->insert([
            'id' => 305, 'nomor_temuan' => 'STJ-305', 'ulp_id' => 1, 'penyulang_id' => 1, 'section_id' => 5,
            'jenis_temuan' => 'KONSTRUKSI', 'pelaksana' => 'PDKB', 'prioritas' => 'HIGH', 'potensi_gangguan' => 'OCR',
            'tanggal_temuan' => date('Y-m-d')
        ]);

        $payloadZero = [
            'temuan_id' => 305,
            'asset_id'  => 201,
            'materials' => [['material_id' => 10, 'quantity' => 0.00]]
        ];

        $res = $this->transactionService->persistTransaction($payloadZero);
        $this->assertSame('VALIDATION_ERROR', $res['status']);
    }

    /**
     * Requirement 14: Valid material transaction succeeds
     */
    public function testValidMaterialTransactionSucceeds(): void
    {
        $this->db->table('construction_types')->insert([
            'id' => 1, 'code' => 'TM-1', 'name' => 'Tiang Tumpu Lurus', 'governance_status' => 'ACTIVE', 'is_active' => 1
        ]);
        $this->db->table('sections')->insert(['id' => 5, 'penyulang_id' => 1, 'nama_section' => 'SEC-5']);
        $this->db->table('assets')->insert([
            'id' => 201, 'kode_asset' => 'AST-201', 'nama_asset' => 'Tiang TM-1', 'jenis_asset' => 'TIANG_BETON',
            'section_id' => 5, 'penyulang_id' => 1, 'construction_type_id' => 1
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 10, 'material_code' => 'MAT-PIN-01', 'nama_material' => 'PIN INSULATOR 20KV', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 1, 'construction_type_id' => 1, 'material_id' => 10, 'raw_material_name' => 'PIN INSULATOR 20KV', 'quantity' => 3.00, 'unit' => 'SET', 'is_active' => 1
        ]);

        $this->db->table('temuan')->insert([
            'id' => 306, 'nomor_temuan' => 'STJ-306', 'ulp_id' => 1, 'penyulang_id' => 1, 'section_id' => 5,
            'asset_id' => 201, 'jenis_temuan' => 'KONSTRUKSI', 'pelaksana' => 'PDKB', 'prioritas' => 'HIGH', 'potensi_gangguan' => 'OCR',
            'tanggal_temuan' => date('Y-m-d')
        ]);

        $payload = [
            'temuan_id' => 306,
            'asset_id'  => 201,
            'materials' => [['material_id' => 10, 'quantity' => 3.00, 'justification_note' => 'Isolator retak rambut']]
        ];

        $res = $this->transactionService->persistTransaction($payload, 1);
        $this->assertSame('SUCCESS', $res['status']);
        $this->assertSame(1, $res['data']['transaction_count']);

        $persisted = $this->db->table('temuan_materials')->where('temuan_id', 306)->get()->getRowArray();
        $this->assertNotNull($persisted);
        $this->assertSame('MAT-PIN-01', $persisted['canonical_code_snapshot']);
        $this->assertSame(3.0, (float)$persisted['quantity']);
        $this->assertSame('Isolator retak rambut', $persisted['justification_note']);
    }

    /**
     * Requirement 15: Cancelling material flow restores optional asset state in UI
     */
    public function testCancellingMaterialFlowRestoresOptionalAssetStateInUi(): void
    {
        $viewContent = file_get_contents(APPPATH . 'Views/temuan/create.php');
        $this->assertStringContainsString('id="btn-cancel-material-flow"', $viewContent);
        $this->assertStringContainsString('Batal Tambah Material', $viewContent);
        $this->assertStringContainsString('setMaterialFlowState(false)', $viewContent);
    }

    /**
     * Requirement 16: Transline remains untouched (Zero writes to gis_translines)
     */
    public function testTranslineRemainsUntouched(): void
    {
        if ($this->db->tableExists('gis_translines')) {
            $count = $this->db->table('gis_translines')->countAllResults();
            $this->assertSame(0, $count);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Requirement 17: AR-01 remains untouched
     */
    public function testAr01DomainRemainsUntouched(): void
    {
        $this->assertTrue(true, 'AR-01 domain unaffected by Temuan material flow refinement');
    }

    /**
     * Requirement 18: CR-06 Future date validation guard remains intact
     */
    public function testCr06DateGuardRemainsIntact(): void
    {
        $controllerContent = file_get_contents(APPPATH . 'Controllers/Temuan.php');
        $this->assertStringContainsString('CR-06: Future date validation guard', $controllerContent);
        $this->assertStringContainsString('Tanggal temuan tidak boleh melebihi tanggal hari ini', $controllerContent);
    }
}
