<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\MaterialTransactionService;
use App\Services\MaterialPickerService;
use App\Models\TemuanMaterialModel;

/**
 * MR-01 Phase 3B: Finding Material Transaction Test Suite
 * Tests Controlled Write Gate, Atomic Batch Persistence, Snapshot Immutability,
 * 4 Hard Firewalls, and Strict Quantity Governance.
 *
 * @internal
 */
final class MaterialTransactionTest extends CIUnitTestCase
{
    private MaterialTransactionService $txService;
    private MaterialPickerService $pickerService;
    private TemuanMaterialModel $txModel;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $forge    = \Config\Database::forge();

        $this->ensureTablesExist($forge, $this->db);
        $this->seedCanonicalTestData();

        $this->pickerService = new MaterialPickerService($this->db);
        $this->txService     = new MaterialTransactionService($this->db, $this->pickerService);
        $this->txModel       = new TemuanMaterialModel();
    }

    private function seedCanonicalTestData(): void
    {
        // 1. Ensure Pin Post Material exists
        $mat1 = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();
        if (!$mat1) {
            $this->db->table('master_materials')->insert([
                'material_code'     => 'MAT-ISO-PIN-20KV',
                'nama_material'     => 'Pin Post Insulator 20 kV Porcelain/Polymer',
                'nama_lapangan'     => 'PIN',
                'satuan'            => 'SET',
                'material_domain'   => 'JTM',
                'material_category' => 'INSULATOR',
                'status'            => 'AKTIF',
            ]);
            $mat1Id = (int)$this->db->insertID();
        } else {
            $mat1Id = (int)$mat1['id'];
        }

        // 2. Ensure Cross Arm Material exists
        $mat2 = $this->db->table('master_materials')->where('material_code', 'CANON-HDW-005')->get()->getRowArray();
        if (!$mat2) {
            $this->db->table('master_materials')->insert([
                'material_code'     => 'CANON-HDW-005',
                'nama_material'     => 'CROSS ARM UNP 8X50X2000',
                'nama_lapangan'     => 'TRAVES 2000',
                'satuan'            => 'BTG',
                'material_domain'   => 'JTM',
                'material_category' => 'CROSSARM',
                'status'            => 'AKTIF',
            ]);
            $mat2Id = (int)$this->db->insertID();
        } else {
            $mat2Id = (int)$mat2['id'];
        }

        // 3. Ensure Construction Type TM1 exists
        $const = $this->db->table('construction_types')->where('construction_code', 'TM1')->get()->getRowArray();
        if (!$const) {
            $this->db->table('construction_types')->insert([
                'construction_code'   => 'TM1',
                'construction_name'   => 'Tiang Penumpu Tunggal (Single Pin Post)',
                'construction_family' => 'JTM',
                'asset_domain'        => 'TIANG',
                'approval_status'     => 'ACTIVE',
            ]);
            $constId = (int)$this->db->insertID();
        } else {
            $constId = (int)$const['id'];
        }

        // 4. Ensure BOM Items for TM1 exist
        $bom1 = $this->db->table('construction_bom_items')
            ->where('construction_type_id', $constId)
            ->where('material_id', $mat1Id)
            ->get()->getRowArray();
        if (!$bom1) {
            $this->db->table('construction_bom_items')->insert([
                'construction_type_id' => $constId,
                'material_id'          => $mat1Id,
                'raw_material_name'    => 'Pin Post Insulator 20 kV Porcelain/Polymer',
                'quantity'             => null,
                'unit'                 => 'SET',
                'mapping_status'       => 'RESOLVED',
            ]);
        }

        $bom2 = $this->db->table('construction_bom_items')
            ->where('construction_type_id', $constId)
            ->where('material_id', $mat2Id)
            ->get()->getRowArray();
        if (!$bom2) {
            $this->db->table('construction_bom_items')->insert([
                'construction_type_id' => $constId,
                'material_id'          => $mat2Id,
                'raw_material_name'    => 'CROSS ARM UNP 8X50X2000',
                'quantity'             => null,
                'unit'                 => 'BTG',
                'mapping_status'       => 'RESOLVED',
            ]);
        }
    }

    private function ensureTablesExist($forge, $db): void
    {
        // master_materials
        if (!$db->tableExists('master_materials')) {
            $forge->addField([
                'id'                => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'material_code'     => ['type' => 'VARCHAR', 'constraint' => 60],
                'nama_material'     => ['type' => 'VARCHAR', 'constraint' => 150],
                'nama_lapangan'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'satuan'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET'],
                'material_domain'   => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'material_category' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'HARDWARE'],
                'specification'     => ['type' => 'TEXT', 'null' => true],
                'source_workbook'   => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'KONSTRUKSI.xlsx'],
                'source_sheet'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_row'        => ['type' => 'INTEGER', 'null' => true],
                'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('master_materials', true);
        }

        // construction_types
        if (!$db->tableExists('construction_types')) {
            $forge->addField([
                'id'                  => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'construction_code'   => ['type' => 'VARCHAR', 'constraint' => 50],
                'construction_name'   => ['type' => 'VARCHAR', 'constraint' => 150],
                'construction_family' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'asset_domain'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG'],
                'approval_status'     => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'ACTIVE'],
                'source_sheet'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_row'          => ['type' => 'INTEGER', 'null' => true],
                'is_active'           => ['type' => 'INTEGER', 'default' => 1],
                'created_at'          => ['type' => 'DATETIME', 'null' => true],
                'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('construction_types', true);
        }

        // construction_bom_items
        if (!$db->tableExists('construction_bom_items')) {
            $forge->addField([
                'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'construction_type_id' => ['type' => 'INTEGER'],
                'material_id'          => ['type' => 'INTEGER', 'null' => true],
                'raw_material_name'    => ['type' => 'VARCHAR', 'constraint' => 150],
                'material_alias'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'quantity'             => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'quantity_status'      => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'KNOWN'],
                'unit'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'mandatory'            => ['type' => 'INTEGER', 'default' => 1],
                'component_category'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_sheet'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_row'           => ['type' => 'INTEGER', 'null' => true],
                'mapping_status'       => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'RESOLVED'],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('construction_bom_items', true);
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
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'BELUM'],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
        }

        // temuan_materials (NEW Phase 3B table)
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

    /**
     * Helper to create test temuan in Section 100
     */
    private function createTestTemuan(int $sectionId = 100): int
    {
        $data = [
            'section_id'     => $sectionId,
            'material'       => 'Historical text finding material legacy',
            'tanggal_temuan' => date('Y-m-d'),
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        // If running in full migration schema with strict NOT NULL columns:
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
     * Helper to create test asset in Section 100 with TM1
     */
    private function createTestAsset(int $sectionId = 100, ?int $constructionId = null): int
    {
        if ($constructionId === null) {
            $const = $this->db->table('construction_types')->where('construction_code', 'TM1')->get()->getRowArray();
            $constructionId = (int)$const['id'];
        }

        $this->db->table('assets')->insert([
            'kode_asset'           => 'AST-TEST-' . uniqid(),
            'nama_asset'           => 'Tiang Uji TM1',
            'jenis_asset'          => 'TIANG',
            'section_id'           => $sectionId,
            'construction_type_id' => $constructionId,
        ]);
        return (int)$this->db->insertID();
    }

    /**
     * Test 01: Valid single material transaction => SUCCESS & Snapshot Stored
     */
    public function testValidSingleMaterialTransaction(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);

        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                [
                    'material_id'        => (int)$mat['id'],
                    'quantity'           => 3.0,
                    'justification_note' => 'Pin post pecah terkena petir',
                ]
            ],
        ];

        $res = $this->txService->persistTransaction($payload, 42);

        $this->assertSame('SUCCESS', $res['status']);
        $this->assertSame(1, $res['data']['transaction_count']);

        // Verify in DB
        $row = $this->db->table('temuan_materials')->where('temuan_id', $temuanId)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('MAT-ISO-PIN-20KV', $row['canonical_code_snapshot']);
        $this->assertSame('Pin Post Insulator 20 kV Porcelain/Polymer', $row['canonical_name_snapshot']);
        $this->assertSame('SET', $row['unit_snapshot']);
        $this->assertEquals(3.00, (float)$row['quantity']);
        $this->assertSame(42, (int)$row['created_by']);
    }

    /**
     * Test 02: Valid multi-material transaction => SUCCESS for all
     */
    public function testValidMultiMaterialTransaction(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);

        $mat1 = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();
        $mat2 = $this->db->table('master_materials')->where('material_code', 'CANON-HDW-005')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                [
                    'material_id'        => (int)$mat1['id'],
                    'quantity'           => 3,
                    'justification_note' => 'Ganti 3 pin post',
                ],
                [
                    'material_id'        => (int)$mat2['id'],
                    'quantity'           => 1,
                    'justification_note' => 'Traves bengkok',
                ],
            ],
        ];

        $res = $this->txService->persistTransaction($payload, 42);

        $this->assertSame('SUCCESS', $res['status']);
        $this->assertSame(2, $res['data']['transaction_count']);

        $rows = $this->db->table('temuan_materials')->where('temuan_id', $temuanId)->get()->getResultArray();
        $this->assertCount(2, $rows);
    }

    /**
     * Test 03: Quantity zero => REJECT
     */
    public function testQuantityZeroRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);
        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => (int)$mat['id'], 'quantity' => 0]
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('VALIDATION_ERROR', $res['status']);
        $this->assertStringContainsString('positif', $res['message']);
    }

    /**
     * Test 04: Quantity negative => REJECT
     */
    public function testQuantityNegativeRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);
        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => (int)$mat['id'], 'quantity' => -5]
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('VALIDATION_ERROR', $res['status']);
    }

    /**
     * Test 05: Quantity invalid / non-numeric => REJECT
     */
    public function testQuantityInvalidRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);
        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => (int)$mat['id'], 'quantity' => 'dua belas']
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('VALIDATION_ERROR', $res['status']);
    }

    /**
     * Test 06: Material not in BOM => REJECT
     */
    public function testMaterialNotInBomRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);

        // Create unlinked material
        $this->db->table('master_materials')->insert([
            'material_code' => 'MAT-UNLINKED-99',
            'nama_material' => 'Material Diluar BOM',
            'status'        => 'AKTIF',
        ]);
        $unlinkedId = (int)$this->db->insertID();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => $unlinkedId, 'quantity' => 1]
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('VALIDATION_ERROR', $res['status']);
        $this->assertStringContainsString('tidak sah', $res['message']);
    }

    /**
     * Test 07: Held material => REJECT
     */
    public function testHeldMaterialRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);

        // Attempting to submit a held specification material ID
        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => 99999, 'quantity' => 2]
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('VALIDATION_ERROR', $res['status']);
    }

    /**
     * Test 08: Provisional Kubikel Construction => REJECT
     */
    public function testProvisionalKubikelRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);

        // Create Kubikel construction
        $this->db->table('construction_types')->insert([
            'construction_code'   => 'KUBIKEL-DRAFT',
            'construction_name'   => 'Kubikel Sel Ingoing',
            'construction_family' => 'GARDU_KUBIKEL',
            'approval_status'     => 'DRAFT',
        ]);
        $kubikelConstId = (int)$this->db->insertID();

        $assetId = $this->createTestAsset(100, $kubikelConstId);

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => 1, 'quantity' => 1]
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('PROVISIONAL_BLOCKED', $res['status']);
    }

    /**
     * Test 09: NO_CONSTRUCTION => REJECT
     */
    public function testAssetWithoutConstructionRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);

        // Asset with null construction_type_id
        $this->db->table('assets')->insert([
            'kode_asset'           => 'AST-NOCONST',
            'nama_asset'           => 'Tiang Tanpa Konstruksi',
            'jenis_asset'          => 'TIANG',
            'section_id'           => 100,
            'construction_type_id' => null,
        ]);
        $assetId = (int)$this->db->insertID();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => 1, 'quantity' => 1]
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('NO_CONSTRUCTION', $res['status']);
    }

    /**
     * Test 10: NO_BOM => REJECT
     */
    public function testConstructionWithoutBomRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);

        $this->db->table('construction_types')->insert([
            'construction_code'   => 'TM-EMPTY-BOM',
            'construction_name'   => 'Konstruksi Tanpa BOM',
            'construction_family' => 'JTM',
            'approval_status'     => 'ACTIVE',
        ]);
        $emptyConstId = (int)$this->db->insertID();

        $assetId = $this->createTestAsset(100, $emptyConstId);

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => 1, 'quantity' => 1]
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('NO_BOM', $res['status']);
    }

    /**
     * Test 11: Cross-section asset => REJECT
     */
    public function testCrossSectionAssetRejected(): void
    {
        $temuanId = $this->createTestTemuan(100); // Section 100
        $assetId  = $this->createTestAsset(200);  // Section 200

        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => (int)$mat['id'], 'quantity' => 1]
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('INVALID_ASSET', $res['status']);
    }

    /**
     * Test 12: Duplicate transaction in database => REJECT
     */
    public function testDuplicateDatabaseTransactionRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);
        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => (int)$mat['id'], 'quantity' => 2]
            ],
        ];

        // First insert => SUCCESS
        $res1 = $this->txService->persistTransaction($payload);
        $this->assertSame('SUCCESS', $res1['status']);

        // Second insert of same combination => CONFLICT
        $res2 = $this->txService->persistTransaction($payload);
        $this->assertSame('CONFLICT', $res2['status']);
    }

    /**
     * Test 12B: Duplicate material within incoming batch => REJECT
     */
    public function testDuplicateMaterialInBatchRejected(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);
        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => (int)$mat['id'], 'quantity' => 2],
                ['material_id' => (int)$mat['id'], 'quantity' => 3], // Duplicate in batch!
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('CONFLICT', $res['status']);
    }

    /**
     * Test 13: Forged canonical_name from client => Server writes true master snapshot
     */
    public function testForgedClientDataIgnoredInSnapshot(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);
        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                [
                    'material_id'             => (int)$mat['id'],
                    'canonical_name_snapshot' => 'FORGED_HACKED_NAME', // Client forgery!
                    'unit_snapshot'           => 'FORGED_KG',          // Client forgery!
                    'construction_type_id'    => 99999,                // Client forgery!
                    'quantity'                => 1.0,
                ]
            ],
        ];

        $res = $this->txService->persistTransaction($payload);
        $this->assertSame('SUCCESS', $res['status']);

        $row = $this->db->table('temuan_materials')->where('temuan_id', $temuanId)->get()->getRowArray();
        $this->assertSame('Pin Post Insulator 20 kV Porcelain/Polymer', $row['canonical_name_snapshot']);
        $this->assertSame('SET', $row['unit_snapshot']);
        $this->assertNotEquals(99999, (int)$row['construction_type_id']);
    }

    /**
     * Test 14: Partial batch failure => ZERO partial inserts (Atomic Rollback)
     */
    public function testPartialBatchFailureRollsBackAll(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);
        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $countBefore = $this->db->table('temuan_materials')->countAllResults();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => (int)$mat['id'], 'quantity' => 2], // VALID
                ['material_id' => 99999, 'quantity' => 5],           // INVALID (not in BOM)
            ],
        ];

        $res = $this->txService->persistTransaction($payload);

        $this->assertSame('VALIDATION_ERROR', $res['status']);
        $countAfter = $this->db->table('temuan_materials')->countAllResults();
        $this->assertSame($countBefore, $countAfter, 'Batch failure must result in zero persisted rows.');
    }

    /**
     * Test 15: Historical temuan.material remains untouched
     */
    public function testHistoricalTemuanMaterialUntouched(): void
    {
        $temuanId = $this->createTestTemuan(100);
        $assetId  = $this->createTestAsset(100);
        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();

        $payload = [
            'temuan_id' => $temuanId,
            'asset_id'  => $assetId,
            'materials' => [
                ['material_id' => (int)$mat['id'], 'quantity' => 1]
            ],
        ];

        $this->txService->persistTransaction($payload);

        $t = $this->db->table('temuan')->where('id', $temuanId)->get()->getRowArray();
        $this->assertSame('Historical text finding material legacy', $t['material']);
    }
}
