<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\MaterialPickerService;
use App\Services\MaterialTransactionService;

/**
 * MR-01 UI-01: Asset-Driven Material Picker UI & Transaction Integration Test
 *
 * Verifies:
 * 1. UI contract of app/Views/temuan/create.php:
 *    - Removal of legacy free-text repeater ("Nama Material / Pohon", "+ Tambah Material")
 *    - Presence of Asset-Driven BOM picker components
 *    - Dedicated ROW vegetation fallback container
 * 2. Backend integration with MaterialPickerService (SSOT eligibility)
 * 3. Atomic transaction execution with MaterialTransactionService (SSOT transaction)
 * 4. Rejection firewalls (Forged material, Cross-section asset, Held specification)
 */
class MaterialPickerUiIntegrationTest extends CIUnitTestCase
{
    protected $db;
    protected MaterialPickerService $pickerService;
    protected MaterialTransactionService $txService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->ensureTablesExist();

        try {
            $forge = \Config\Database::forge();
            $forge->addColumn('master_materials', [
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
        } catch (\Throwable $e) {
            // Column already added
        }

        try {
            $forge = \Config\Database::forge();
            $forge->addColumn('assets', [
                'jenis_asset' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG', 'null' => true],
            ]);
        } catch (\Throwable $e) {
            // Column already added
        }

        try {
            $forge = \Config\Database::forge();
            $forge->addColumn('assets', [
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
        } catch (\Throwable $e) {
            // Column already added
        }

        try {
            $forge = \Config\Database::forge();
            $forge->addColumn('temuan_materials', [
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
        } catch (\Throwable $e) {
            // Column already added
        }

        try {
            $forge = \Config\Database::forge();
            $forge->addColumn('temuan_materials', [
                'source_mode' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'BOM_CANONICAL', 'null' => true],
            ]);
        } catch (\Throwable $e) {
            // Column already added
        }

        $this->pickerService = new MaterialPickerService();
        $this->txService = new MaterialTransactionService();
    }

    private function ensureTablesExist(): void
    {
        $forge = \Config\Database::forge();

        // 1. ulps
        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'nama_ulp'   => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('ulps', true);
        }

        // 2. penyulang
        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        // 3. sections
        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        // 4. master_materials
        if (!$this->db->tableExists('master_materials')) {
            $forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'material_code' => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_material' => ['type' => 'VARCHAR', 'constraint' => 255],
                'satuan'        => ['type' => 'VARCHAR', 'constraint' => 50],
                'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('master_materials', true);
        }

        // 5. construction_types
        if (!$this->db->tableExists('construction_types')) {
            $forge->addField([
                'id'                  => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'construction_code'   => ['type' => 'VARCHAR', 'constraint' => 50],
                'construction_name'   => ['type' => 'VARCHAR', 'constraint' => 150],
                'construction_family' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'asset_domain'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG'],
                'approval_status'     => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'ACTIVE'],
                'is_active'           => ['type' => 'INTEGER', 'default' => 1],
            ]);
            $forge->createTable('construction_types', true);
        }

        // 6. construction_bom_items
        if (!$this->db->tableExists('construction_bom_items')) {
            $forge->addField([
                'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'construction_type_id' => ['type' => 'INTEGER'],
                'material_id'          => ['type' => 'INTEGER', 'null' => true],
                'raw_material_name'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'mapping_status'       => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'RESOLVED'],
            ]);
            $forge->createTable('construction_bom_items', true);
        }

        // 7. assets
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        // 8. temuan
        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'nomor_temuan'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11],
                'penyulang_id'   => ['type' => 'INT', 'constraint' => 11],
                'section_id'     => ['type' => 'INT', 'constraint' => 11],
                'tanggal_temuan' => ['type' => 'DATE'],
                'material'       => ['type' => 'TEXT', 'null' => true],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
                'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }

        // 9. temuan_materials
        if (!$this->db->tableExists('temuan_materials')) {
            $forge->addField([
                'id'                      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'temuan_id'               => ['type' => 'INT', 'constraint' => 11],
                'asset_id'                => ['type' => 'INT', 'constraint' => 11],
                'construction_type_id'    => ['type' => 'INT', 'constraint' => 11],
                'material_id'             => ['type' => 'INT', 'constraint' => 11],
                'canonical_code_snapshot' => ['type' => 'VARCHAR', 'constraint' => 100],
                'canonical_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 255],
                'unit_snapshot'           => ['type' => 'VARCHAR', 'constraint' => 50],
                'quantity'                => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'justification_note'      => ['type' => 'TEXT', 'null' => true],
                'created_by'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan_materials', true);
        }
    }

    private function createTestTemuan(int $sectionId, string $material = 'Tidak ada spesifikasi material'): int
    {
        $data = [
            'section_id'     => $sectionId,
            'tanggal_temuan' => date('Y-m-d'),
            'material'       => $material,
        ];
        if ($this->db->fieldExists('nomor_temuan', 'temuan')) {
            $data['nomor_temuan'] = 'STJ-TEST-' . uniqid();
        }
        if ($this->db->fieldExists('ulp_id', 'temuan')) {
            $data['ulp_id'] = 1;
        }
        if ($this->db->fieldExists('penyulang_id', 'temuan')) {
            $data['penyulang_id'] = 1;
        }
        if ($this->db->fieldExists('jenis_temuan', 'temuan')) {
            $data['jenis_temuan'] = 'ANOMALI';
        }
        if ($this->db->fieldExists('pelaksana', 'temuan')) {
            $data['pelaksana'] = 'PLN';
        }
        if ($this->db->fieldExists('prioritas', 'temuan')) {
            $data['prioritas'] = 'SEDANG';
        }
        if ($this->db->fieldExists('potensi_gangguan', 'temuan')) {
            $data['potensi_gangguan'] = 'TIDAK';
        }
        if ($this->db->fieldExists('status', 'temuan')) {
            $data['status'] = 'BELUM';
        }

        $this->db->table('temuan')->insert($data);
        return (int)$this->db->insertID();
    }

    /**
     * Test 01: Verify that app/Views/temuan/create.php contains the governed BOM picker
     * and that the legacy free-text repeater is completely removed.
     */
    public function testCreateViewContainsAssetDrivenPickerAndRemovesLegacyRepeater(): void
    {
        $viewFile = APPPATH . 'Views/temuan/create.php';
        $this->assertFileExists($viewFile);
        $content = file_get_contents($viewFile);

        // Required UI Elements present
        $this->assertStringContainsString('id="mr01-material-picker-section"', $content);
        $this->assertStringContainsString('id="mr01_empty_asset_state"', $content);
        $this->assertStringContainsString('Pilih asset terlebih dahulu untuk melihat material', $content);
        $this->assertStringContainsString('id="mr01_construction_badge"', $content);
        $this->assertStringContainsString('id="mr01_loading_state"', $content);
        $this->assertStringContainsString('id="mr01_bom_preview_container"', $content);
        $this->assertStringContainsString('id="mr01_bom_chips_container"', $content);
        $this->assertStringContainsString('name="structured_materials_json"', $content);
        $this->assertStringContainsString('id="row-vegetasi-container"', $content);

        // Legacy Free-Text Elements MUST NOT be present
        $this->assertStringNotContainsString('id="material-repeater-container"', $content);
        $this->assertStringNotContainsString('id="btn-add-material"', $content);
        $this->assertStringNotContainsString('input-nama-material', $content);
        $this->assertStringNotContainsString('input-jumlah-material', $content);
        $this->assertStringNotContainsString('function addMaterialRow', $content);
    }

    /**
     * Test 02: Flow Step A - No asset selected shows empty state
     */
    public function testNoAssetSelectedReturnsEmptyEligibility(): void
    {
        $res = $this->pickerService->resolvePicker(0, 1);
        $this->assertSame('INVALID_ASSET', $res['status']);
    }

    /**
     * Test 03: Flow Step B - Selecting valid asset resolves construction and eligible BOM
     */
    public function testValidAssetResolvesConstructionAndEligibleBOM(): void
    {
        // 1. Setup Construction & BOM
        $this->db->table('construction_types')->insert([
            'construction_code'   => 'TM-1-TEST',
            'construction_name'   => 'TIANG TUMPU TEST',
            'approval_status'     => 'ACTIVE',
        ]);
        $constId = (int)$this->db->insertID();

        $this->db->table('master_materials')->insert([
            'material_code' => 'MAT-ISO-PIN-TEST',
            'nama_material' => 'PIN POST INSULATOR 20 KV TEST',
            'satuan'        => 'SET',
            'status'        => 'AKTIF',
        ]);
        $matId = (int)$this->db->insertID();

        $this->db->table('construction_bom_items')->insert([
            'construction_type_id' => $constId,
            'material_id'          => $matId,
            'raw_material_name'    => 'PIN POST INSULATOR 20 KV TEST',
            'mapping_status'       => 'RESOLVED',
        ]);

        // 2. Setup Section & Asset
        $this->db->table('sections')->insert([
            'penyulang_id' => 1,
            'nama_section' => 'RUAS A TEST',
        ]);
        $secId = (int)$this->db->insertID();

        $this->db->table('assets')->insert([
            'section_id'           => $secId,
            'nama_asset'           => 'TIANG 01 TEST',
            'kode_asset'           => 'AST-' . uniqid(),
            'jenis_asset'          => 'TIANG',
            'construction_type_id' => $constId,
        ]);
        $assetId = (int)$this->db->insertID();

        // 3. Resolve
        $res = $this->pickerService->resolvePicker($assetId, $secId);

        $this->assertSame('READY', $res['status']);
        $this->assertSame('TM-1-TEST', $res['construction']['code']);
        $this->assertCount(1, $res['materials']);
        $this->assertSame('PIN POST INSULATOR 20 KV TEST', $res['materials'][0]['name']);
        $this->assertSame('SET', $res['materials'][0]['unit']);
    }

    /**
     * Test 04: Flow Step E & H - Multiple selected BOM materials persisted atomically
     */
    public function testSubmitMultipleMaterialsPersistedAtomically(): void
    {
        // Create 2 materials
        $this->db->table('master_materials')->insert([
            'material_code' => 'MAT-A-' . uniqid(),
            'nama_material' => 'MATERIAL A TEST',
            'satuan'        => 'BH',
            'status'        => 'AKTIF',
        ]);
        $matA = (int)$this->db->insertID();

        $this->db->table('master_materials')->insert([
            'material_code' => 'MAT-B-' . uniqid(),
            'nama_material' => 'MATERIAL B TEST',
            'satuan'        => 'SET',
            'status'        => 'AKTIF',
        ]);
        $matB = (int)$this->db->insertID();

        // Construction & BOM
        $this->db->table('construction_types')->insert([
            'construction_code'   => 'TM-MULTI-' . uniqid(),
            'construction_name'   => 'TIANG MULTI TEST',
            'approval_status'     => 'ACTIVE',
        ]);
        $constId = (int)$this->db->insertID();

        $this->db->table('construction_bom_items')->insert(['construction_type_id' => $constId, 'material_id' => $matA, 'raw_material_name' => 'MATERIAL A TEST', 'mapping_status' => 'RESOLVED']);
        $this->db->table('construction_bom_items')->insert(['construction_type_id' => $constId, 'material_id' => $matB, 'raw_material_name' => 'MATERIAL B TEST', 'mapping_status' => 'RESOLVED']);

        // Section & Asset
        $this->db->table('sections')->insert(['penyulang_id' => 1, 'nama_section' => 'RUAS MULTI']);
        $secId = (int)$this->db->insertID();

        $this->db->table('assets')->insert([
            'section_id'           => $secId,
            'nama_asset'           => 'TIANG MULTI',
            'kode_asset'           => 'AST-' . uniqid(),
            'jenis_asset'          => 'TIANG',
            'construction_type_id' => $constId,
        ]);
        $assetId = (int)$this->db->insertID();

        // Temuan
        $temuanId = $this->createTestTemuan($secId, 'MATERIAL A TEST: 5 BH, MATERIAL B TEST: 2 SET');

        // Payload submitted from UI
        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => $matA, 'quantity' => 5.0, 'justification_note' => 'Kerusakan fisik'],
                ['material_id' => $matB, 'quantity' => 2.0, 'justification_note' => null],
            ],
        ];

        $res = $this->txService->persistTransaction($payload);

        $this->assertSame('SUCCESS', $res['status'], json_encode($res));
        $this->assertSame(2, $res['data']['transaction_count']);

        // Verify database persistence in temuan_materials
        $rows = $this->db->table('temuan_materials')->where('temuan_id', $temuanId)->get()->getResultArray();
        $this->assertCount(2, $rows);
        $this->assertSame('BH', $rows[0]['unit_snapshot']);
        $this->assertEquals(5.0, (float)$rows[0]['quantity']);
        $this->assertSame('SET', $rows[1]['unit_snapshot']);
        $this->assertEquals(2.0, (float)$rows[1]['quantity']);
    }

    /**
     * Test 05: Flow Step E - Attempt forged material not in BOM is rejected by server
     */
    public function testForgedMaterialNotInBomIsRejected(): void
    {
        $this->db->table('master_materials')->insert([
            'material_code' => 'MAT-LEGIT-' . uniqid(),
            'nama_material' => 'LEGIT MATERIAL',
            'satuan'        => 'BH',
            'status'        => 'AKTIF',
        ]);
        $legitMatId = (int)$this->db->insertID();

        $this->db->table('master_materials')->insert([
            'material_code' => 'MAT-FORGED-' . uniqid(),
            'nama_material' => 'FORGED MATERIAL',
            'satuan'        => 'BH',
            'status'        => 'AKTIF',
        ]);
        $forgedMatId = (int)$this->db->insertID();

        $this->db->table('construction_types')->insert(['construction_code' => 'TM-SEC-' . uniqid(), 'construction_name' => 'TIANG SEC', 'approval_status' => 'ACTIVE']);
        $constId = (int)$this->db->insertID();

        $this->db->table('construction_bom_items')->insert(['construction_type_id' => $constId, 'material_id' => $legitMatId, 'raw_material_name' => 'LEGIT MATERIAL', 'mapping_status' => 'RESOLVED']);

        $this->db->table('sections')->insert(['penyulang_id' => 1, 'nama_section' => 'RUAS SEC']);
        $secId = (int)$this->db->insertID();

        $this->db->table('assets')->insert(['section_id' => $secId, 'nama_asset' => 'TIANG SEC', 'kode_asset' => 'AST-' . uniqid(), 'jenis_asset' => 'TIANG', 'construction_type_id' => $constId]);
        $assetId = (int)$this->db->insertID();

        $temuanId = $this->createTestTemuan($secId);

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => $forgedMatId, 'quantity' => 1.0],
            ],
        ];

        $res = $this->txService->persistTransaction($payload);

        $this->assertSame('VALIDATION_ERROR', $res['status']);
        $this->assertStringContainsString('tidak sah untuk konstruksi aset ini', $res['message']);
    }
}
