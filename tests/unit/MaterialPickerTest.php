<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\MaterialPickerService;
use App\Services\ConstructionIntelligenceService;
use App\Database\Seeds\ConstructionIntelligenceSeeder;

/**
 * MR-01 Phase 3A: Asset-Driven Material Picker Test Suite
 * Validates Read-Only Proof Gate, 4 Hard Firewalls, and Zero Database Writes.
 *
 * @internal
 */
final class MaterialPickerTest extends CIUnitTestCase
{
    private MaterialPickerService $pickerService;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $forge    = \Config\Database::forge();

        $this->ensureTablesExist($forge, $this->db);
        $this->seedCanonicalTestData();

        $this->pickerService = new MaterialPickerService($this->db);
    }

    private function seedCanonicalTestData(): void
    {
        // 1. Ensure Master Material exists
        $mat = $this->db->table('master_materials')->where('material_code', 'MAT-ISO-PIN-20KV')->get()->getRowArray();
        if (!$mat) {
            $this->db->table('master_materials')->insert([
                'material_code'     => 'MAT-ISO-PIN-20KV',
                'nama_material'     => 'Pin Post Insulator 20 kV Porcelain/Polymer',
                'nama_lapangan'     => 'PIN',
                'satuan'            => 'SET',
                'material_domain'   => 'JTM',
                'material_category' => 'INSULATOR',
                'status'            => 'AKTIF',
            ]);
            $matId = (int)$this->db->insertID();
        } else {
            $matId = (int)$mat['id'];
        }

        // 2. Ensure Construction Type TM1 exists
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

        // 3. Ensure BOM Item linking TM1 to Pin Post exists
        $bom = $this->db->table('construction_bom_items')
            ->where('construction_type_id', $constId)
            ->where('material_id', $matId)
            ->get()->getRowArray();
        if (!$bom) {
            $this->db->table('construction_bom_items')->insert([
                'construction_type_id' => $constId,
                'material_id'          => $matId,
                'raw_material_name'    => 'Pin Post Insulator 20 kV Porcelain/Polymer',
                'quantity'             => null, // STRICTLY NULL
                'unit'                 => 'SET',
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

        // material_aliases
        if (!$db->tableExists('material_aliases')) {
            $forge->addField([
                'id'               => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'material_id'      => ['type' => 'INTEGER'],
                'alias_name'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'normalized_alias' => ['type' => 'VARCHAR', 'constraint' => 100],
                'alias_type'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'FIELD_TERM'],
                'source'           => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'KONSTRUKSI.xlsx'],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('material_aliases', true);
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
                'ulp_id'               => ['type' => 'INTEGER', 'null' => true],
                'penyulang_id'         => ['type' => 'INTEGER', 'null' => true],
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
    }

    /**
     * Case 1: Valid asset + valid construction + valid BOM => READY
     */
    public function testResolvePickerSuccessReturnsReadyWithBOM(): void
    {
        // Find seeded TM1 construction
        $const = $this->db->table('construction_types')
            ->where('construction_code', 'TM1')
            ->get()
            ->getRowArray();
        $this->assertNotNull($const, 'Seeded TM1 construction must exist');

        // Insert test asset linked to TM1 in Section 100
        $this->db->table('assets')->insert([
            'kode_asset'           => 'POLE-TEST-001',
            'nama_asset'           => 'Tiang Uji TM1',
            'jenis_asset'          => 'TIANG',
            'section_id'           => 100,
            'construction_type_id' => (int)$const['id'],
        ]);
        $assetId = (int)$this->db->insertID();

        $result = $this->pickerService->resolvePicker($assetId, 100);

        $this->assertSame('READY', $result['status']);
        $this->assertSame('Material sesuai BOM konstruksi', $result['message']);
        $this->assertSame('TM1', $result['construction']['code']);
        $this->assertNotEmpty($result['materials']);
        $this->assertArrayHasKey('unit', $result['materials'][0]);
    }

    /**
     * Case 2: Asset without construction_type_id => NO_CONSTRUCTION
     * Zero silent inference, zero AI guessing.
     */
    public function testResolvePickerAssetWithoutConstructionReturnsNoConstruction(): void
    {
        $this->db->table('assets')->insert([
            'kode_asset'           => 'POLE-TEST-NOCONST',
            'nama_asset'           => 'Tiang Tanpa Konstruksi',
            'jenis_asset'          => 'TIANG',
            'section_id'           => 100,
            'construction_type_id' => null, // STRICTLY NULL
        ]);
        $assetId = (int)$this->db->insertID();

        $result = $this->pickerService->resolvePicker($assetId, 100);

        $this->assertSame('NO_CONSTRUCTION', $result['status']);
        $this->assertSame('KONSTRUKSI BELUM TERPETAKAN', $result['message']);
        $this->assertEmpty($result['materials']);
        $this->assertNull($result['construction']);
    }

    /**
     * Case 3: Construction without BOM => NO_BOM
     */
    public function testResolvePickerConstructionWithoutBOMReturnsNoBom(): void
    {
        // Create an empty construction without any BOM items
        $this->db->table('construction_types')->insert([
            'construction_code'   => 'CUSTOM-EMPTY',
            'construction_name'   => 'Konstruksi Khusus Tanpa BOM',
            'construction_family' => 'JTM',
            'approval_status'     => 'ACTIVE',
        ]);
        $constId = (int)$this->db->insertID();

        $this->db->table('assets')->insert([
            'kode_asset'           => 'POLE-TEST-NOBOM',
            'nama_asset'           => 'Tiang BOM Kosong',
            'jenis_asset'          => 'TIANG',
            'section_id'           => 100,
            'construction_type_id' => $constId,
        ]);
        $assetId = (int)$this->db->insertID();

        $result = $this->pickerService->resolvePicker($assetId, 100);

        $this->assertSame('NO_BOM', $result['status']);
        $this->assertSame('BOM KONSTRUKSI BELUM TERSEDIA', $result['message']);
        $this->assertEmpty($result['materials']);
    }

    /**
     * Case 4: Asset outside selected Section => INVALID_ASSET
     */
    public function testResolvePickerAssetOutsideSectionReturnsInvalidAsset(): void
    {
        $this->db->table('assets')->insert([
            'kode_asset'           => 'POLE-TEST-CROSS-SECTION',
            'nama_asset'           => 'Tiang Ruas Lain',
            'jenis_asset'          => 'TIANG',
            'section_id'           => 200, // Belongs to section 200
            'construction_type_id' => 1,
        ]);
        $assetId = (int)$this->db->insertID();

        // Requested with section 100
        $result = $this->pickerService->resolvePicker($assetId, 100);

        $this->assertSame('INVALID_ASSET', $result['status']);
        $this->assertSame('Asset tidak sesuai Section yang dipilih', $result['message']);
        $this->assertEmpty($result['materials']);
        $this->assertNull($result['asset']);
    }

    /**
     * Case 5: Held material is NOT returned by picker (Firewall Enforced)
     */
    public function testResolvePickerHeldSpecificationsExcludedByFirewall(): void
    {
        // Create a construction with a held item
        $this->db->table('construction_types')->insert([
            'construction_code'   => 'TM-HELD-TEST',
            'construction_name'   => 'Konstruksi Uji Held',
            'construction_family' => 'JTM',
            'approval_status'     => 'ACTIVE',
        ]);
        $constId = (int)$this->db->insertID();

        // Add held item to BOM
        $this->db->table('construction_bom_items')->insert([
            'construction_type_id' => $constId,
            'material_id'          => null,
            'raw_material_name'    => 'LIGHTNING ARRESTER 20KV', // HELD item!
            'quantity'             => null,
            'mapping_status'       => 'MANUAL_REVIEW_REQUIRED',
        ]);

        $this->db->table('assets')->insert([
            'kode_asset'           => 'POLE-HELD-TEST',
            'nama_asset'           => 'Tiang Arrester 20kV',
            'jenis_asset'          => 'TIANG',
            'section_id'           => 100,
            'construction_type_id' => $constId,
        ]);
        $assetId = (int)$this->db->insertID();

        $result = $this->pickerService->resolvePicker($assetId, 100);

        // Should return NO_BOM because the only item was excluded by the firewall
        $this->assertSame('NO_BOM', $result['status']);
        $this->assertEmpty($result['materials']);
    }

    /**
     * Case 6: Provisional Kubikel material is NOT returned (Firewall Enforced)
     */
    public function testResolvePickerProvisionalKubikelBlockedByFirewall(): void
    {
        $this->db->table('construction_types')->insert([
            'construction_code'   => 'KUBIKEL-OUTGOING',
            'construction_name'   => 'Kubikel Outgoing 20kV',
            'construction_family' => 'GARDU_KUBIKEL',
            'approval_status'     => 'DRAFT', // PROVISIONAL DRAFT
        ]);
        $constId = (int)$this->db->insertID();

        $this->db->table('assets')->insert([
            'kode_asset'           => 'KUBIKEL-TEST-001',
            'nama_asset'           => 'Kubikel Gardu Induk',
            'jenis_asset'          => 'KUBIKEL',
            'section_id'           => 100,
            'construction_type_id' => $constId,
        ]);
        $assetId = (int)$this->db->insertID();

        $result = $this->pickerService->resolvePicker($assetId, 100);

        $this->assertSame('PROVISIONAL_BLOCKED', $result['status']);
        $this->assertStringContainsString('PROVISIONAL', $result['message']);
        $this->assertEmpty($result['materials']);
    }

    /**
     * Case 7: Duplicate material in BOM => deduplicated into exactly one picker option
     */
    public function testResolvePickerDuplicateMaterialDeduplicated(): void
    {
        // Seed TM1 material
        $mat = $this->db->table('master_materials')->where('status', 'AKTIF')->get()->getRowArray();
        $this->assertNotNull($mat);

        $this->db->table('construction_types')->insert([
            'construction_code'   => 'TM-DUP-TEST',
            'construction_name'   => 'Konstruksi Uji Duplikat',
            'construction_family' => 'JTM',
            'approval_status'     => 'ACTIVE',
        ]);
        $constId = (int)$this->db->insertID();

        // Insert TWO BOM items referencing the exact same canonical material
        $this->db->table('construction_bom_items')->insert([
            'construction_type_id' => $constId,
            'material_id'          => (int)$mat['id'],
            'raw_material_name'    => $mat['nama_material'],
            'quantity'             => null,
            'mapping_status'       => 'RESOLVED',
        ]);
        $this->db->table('construction_bom_items')->insert([
            'construction_type_id' => $constId,
            'material_id'          => (int)$mat['id'],
            'raw_material_name'    => $mat['nama_material'],
            'quantity'             => null,
            'mapping_status'       => 'RESOLVED',
        ]);

        $this->db->table('assets')->insert([
            'kode_asset'           => 'POLE-DUP-TEST',
            'nama_asset'           => 'Tiang Duplikat BOM',
            'jenis_asset'          => 'TIANG',
            'section_id'           => 100,
            'construction_type_id' => $constId,
        ]);
        $assetId = (int)$this->db->insertID();

        $result = $this->pickerService->resolvePicker($assetId, 100);

        $this->assertSame('READY', $result['status']);
        // Must only return 1 option for this canonical material!
        $this->assertCount(1, $result['materials']);
        $this->assertSame((int)$mat['id'], $result['materials'][0]['id']);
    }

    /**
     * Case 8: Zero database writes audit across all relevant tables
     */
    public function testResolvePickerZeroDatabaseWritesAudit(): void
    {
        $countsBefore = [
            'assets'                 => $this->db->table('assets')->countAllResults(),
            'master_materials'       => $this->db->table('master_materials')->countAllResults(),
            'construction_types'     => $this->db->table('construction_types')->countAllResults(),
            'construction_bom_items' => $this->db->table('construction_bom_items')->countAllResults(),
            'temuan'                 => $this->db->table('temuan')->countAllResults(),
        ];

        // Execute multiple picker resolutions
        $this->pickerService->resolvePicker(1, 100);
        $this->pickerService->resolvePicker(999, 999);
        $this->pickerService->resolvePicker(-1, 0);

        $countsAfter = [
            'assets'                 => $this->db->table('assets')->countAllResults(),
            'master_materials'       => $this->db->table('master_materials')->countAllResults(),
            'construction_types'     => $this->db->table('construction_types')->countAllResults(),
            'construction_bom_items' => $this->db->table('construction_bom_items')->countAllResults(),
            'temuan'                 => $this->db->table('temuan')->countAllResults(),
        ];

        $this->assertSame($countsBefore, $countsAfter, 'Picker resolution MUST NOT perform any database writes.');
    }
}
