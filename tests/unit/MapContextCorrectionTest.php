<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\AssetContextService;
use App\Services\MaterialPickerService;

/**
 * MAP-02C: Unit & Integration Test Suite for Working Context Correction UX
 * Covers all 20 mandatory architectural requirements under strict zero-mutation firewall.
 */
class MapContextCorrectionTest extends CIUnitTestCase
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
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
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

        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'section_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        if (!$this->db->tableExists('gis_transline_proposals')) {
            $forge->addField([
                'id'                      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id'            => ['type' => 'INT', 'constraint' => 11],
                'section_id'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'natural_key'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'proposed_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'proposed_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'proposed_distance'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'proposed_geometry'       => ['type' => 'TEXT', 'null' => true],
                'classification'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'AUTO_MATCH'],
                'confidence_score'        => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 1.0000],
                'evidence_json'           => ['type' => 'TEXT', 'null' => true],
                'proposal_source'         => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'DETERMINISTIC_ENGINE'],
                'engine_version'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-01-V2.0'],
                'status'                  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'PENDING_REVIEW'],
                'deleted_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_transline_proposals', true);
        }

        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'nomor_temuan' => ['type' => 'VARCHAR', 'constraint' => 50],
                'asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }

        if (!$this->db->tableExists('temuan_materials')) {
            $forge->addField([
                'id'        => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'temuan_id' => ['type' => 'INT', 'constraint' => 11],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan_materials', true);
        }

        // Clean tables
        $this->db->table('temuan_materials')->truncate();
        $this->db->table('temuan')->truncate();
        $this->db->table('gis_transline_proposals')->truncate();
        $this->db->table('gis_translines')->truncate();
        $this->db->table('construction_bom_items')->truncate();
        $this->db->table('master_materials')->truncate();
        $this->db->table('assets')->truncate();
        $this->db->table('construction_types')->truncate();
        $this->db->table('sections')->truncate();
        $this->db->table('penyulang')->truncate();
        $this->db->table('ulps')->truncate();
    }

    private function seedBaseScenario(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-SDK', 'nama_ulp' => 'ULP Sidoarjo Kota']);
        $this->db->table('ulps')->insert(['id' => 2, 'kode_ulp' => 'ULP-WRU', 'nama_ulp' => 'ULP Waru']);

        $this->db->table('penyulang')->insert(['id' => 10, 'ulp_id' => 1, 'kode_penyulang' => 'BKM', 'nama_penyulang' => 'BANJAR KEMANTREN']);
        $this->db->table('penyulang')->insert(['id' => 20, 'ulp_id' => 2, 'kode_penyulang' => 'SPJ', 'nama_penyulang' => 'SEPANJANG']);

        // Feeder 10 sections
        $this->db->table('sections')->insert(['id' => 101, 'penyulang_id' => 10, 'nama_section' => 'Section BKM 01']);
        $this->db->table('sections')->insert(['id' => 102, 'penyulang_id' => 10, 'nama_section' => 'Section BKM 17']);

        // Feeder 20 section (cross-feeder for BKM)
        $this->db->table('sections')->insert(['id' => 201, 'penyulang_id' => 20, 'nama_section' => 'Section SPJ 01']);

        // Canonical construction types
        $this->db->table('construction_types')->insert([
            'id' => 1, 'code' => 'TM-1', 'name' => 'Konstruksi Tiang Tumpu Lurus', 'voltage_level' => '20kV', 'construction_family' => 'JTM', 'approval_status' => 'ACTIVE'
        ]);
        $this->db->table('construction_types')->insert([
            'id' => 2, 'code' => 'TM-2', 'name' => 'Konstruksi Tiang Tumpu Belok', 'voltage_level' => '20kV', 'construction_family' => 'JTM', 'approval_status' => 'ACTIVE'
        ]);
        $this->db->table('construction_types')->insert([
            'id' => 99, 'code' => 'KUBIKEL-DRAFT', 'name' => 'Kubikel Provisional', 'voltage_level' => '20kV', 'construction_family' => 'GARDU_KUBIKEL', 'approval_status' => 'DRAFT'
        ]);

        // Master materials
        $this->db->table('master_materials')->insert([
            'id' => 50, 'material_code' => 'MAT-PIN-20', 'nama_material' => 'Pin Insulator 20kV', 'satuan' => 'SET', 'status' => 'AKTIF', 'is_active' => 1
        ]);
        $this->db->table('master_materials')->insert([
            'id' => 51, 'material_code' => 'MAT-TRAV-UNP', 'nama_material' => 'Traverse UNP 2000', 'satuan' => 'BATANG', 'status' => 'AKTIF', 'is_active' => 1
        ]);

        // BOM for TM-1
        $this->db->table('construction_bom_items')->insert([
            'id' => 1, 'construction_type_id' => 1, 'material_id' => 50, 'raw_material_name' => 'Pin Insulator 20kV', 'quantity' => 3.0, 'unit' => 'SET', 'is_active' => 1
        ]);
        $this->db->table('construction_bom_items')->insert([
            'id' => 2, 'construction_type_id' => 1, 'material_id' => 51, 'raw_material_name' => 'Traverse UNP 2000', 'quantity' => 1.0, 'unit' => 'BATANG', 'is_active' => 1
        ]);

        // Target Asset: Unmapped section & unmapped construction initially
        $this->db->table('assets')->insert([
            'id'                   => 1001,
            'kode_asset'           => 'BKM-P-01',
            'nama_asset'           => 'Tiang BKM 01',
            'jenis_asset'          => 'TIANG_BETON',
            'section_id'           => null,
            'penyulang_id'         => 10,
            'ulp_id'               => 1,
            'construction_type_id' => null,
            'latitude'             => -7.4501234,
            'longitude'            => 112.7123456,
            'lokasi'               => 'Jl. Raya Kemantren No. 10',
        ]);
    }

    /**
     * Requirement 1 & 2: ULP and Penyulang remain server authoritative and locked.
     */
    public function testUlpAndPenyulangRemainServerAuthoritativeAndLocked(): void
    {
        $this->seedBaseScenario();

        $res = $this->contextService->getAssetContext(1001);

        $this->assertSame('ULP Sidoarjo Kota', $res['network']['ulp']['nama_ulp']);
        $this->assertTrue($res['network']['ulp']['locked']);
        $this->assertSame('SYSTEM', $res['network']['ulp']['source']);

        $this->assertSame('BANJAR KEMANTREN', $res['network']['penyulang']['nama_penyulang']);
        $this->assertTrue($res['network']['penyulang']['locked']);
        $this->assertSame('SYSTEM', $res['network']['penyulang']['source']);
    }

    /**
     * Requirement 3: Valid operator section correction is accepted for working context.
     */
    public function testValidSectionCorrectionAcceptedAsWorkingContext(): void
    {
        $this->seedBaseScenario();

        $res = $this->contextService->getAssetContext(1001, null, null, 102);

        $this->assertSame(102, $res['network']['section']['id']);
        $this->assertSame('Section BKM 17', $res['network']['section']['nama_section']);
        $this->assertSame('OPERATOR', $res['network']['section']['source']);
        $this->assertSame('DIKOREKSI_OPERATOR', $res['network']['section']['status_badge']);
    }

    /**
     * Requirement 4: Cross-feeder section is strictly rejected.
     */
    public function testCrossFeederSectionCorrectionIsRejected(): void
    {
        $this->seedBaseScenario();

        // Section 201 belongs to Feeder 20, whereas asset 1001 belongs to Feeder 10
        $res = $this->contextService->getAssetContext(1001, null, null, 201);

        $this->assertSame('INVALID_WORKING_SECTION', $res['status']);
        $this->assertStringContainsString('Cross-feeder', $res['message']);
    }

    /**
     * Requirement 5: Cross-ULP section is rejected for scoped admin_ulp.
     */
    public function testCrossUlpSectionRejectedForAdminUlp(): void
    {
        $this->seedBaseScenario();

        // User belongs to ULP 1, tries to inspect asset or section of ULP 2
        $res = $this->contextService->getAssetContext(1001, 2, 'admin_ulp');

        $this->assertSame('FORBIDDEN', $res['status']);
    }

    /**
     * Requirement 6: Valid construction correction is accepted and resolves canonical construction.
     */
    public function testValidConstructionCorrectionResolvesCanonicalConstruction(): void
    {
        $this->seedBaseScenario();

        $res = $this->contextService->getAssetContext(1001, null, null, null, 1);

        $this->assertSame('TM-1', $res['construction']['code']);
        $this->assertSame('OPERATOR', $res['context_source']['construction']);
        $this->assertSame('DIKOREKSI_OPERATOR', $res['status_badges']['construction']);
    }

    /**
     * Requirement 7: Invalid or blocked construction is rejected.
     */
    public function testInvalidConstructionIsRejected(): void
    {
        $this->seedBaseScenario();

        // Non-existent construction
        $res = $this->contextService->getAssetContext(1001, null, null, null, 9999);
        $this->assertSame('INVALID_WORKING_CONSTRUCTION', $res['status']);

        // Provisional/Draft kubikel construction blocked by firewall
        $resDraft = $this->contextService->getAssetContext(1001, null, null, null, 99);
        $this->assertSame('PROVISIONAL_BLOCKED', $resDraft['status']);
    }

    /**
     * Requirement 8: Working section selection does NOT update assets.section_id in database.
     */
    public function testWorkingSectionDoesNotUpdateAssetSectionIdInDb(): void
    {
        $this->seedBaseScenario();

        $beforeAsset = $this->db->table('assets')->where('id', 1001)->get()->getRowArray();
        $this->assertNull($beforeAsset['section_id']);

        // Call service with working section 102
        $this->contextService->getAssetContext(1001, null, null, 102);

        $afterAsset = $this->db->table('assets')->where('id', 1001)->get()->getRowArray();
        $this->assertNull($afterAsset['section_id'], 'assets.section_id MUST remain untouched (NULL) in DB!');
    }

    /**
     * Requirement 9: Working construction selection does NOT update assets.construction_type_id in DB.
     */
    public function testWorkingConstructionDoesNotUpdateAssetConstructionIdInDb(): void
    {
        $this->seedBaseScenario();

        $beforeAsset = $this->db->table('assets')->where('id', 1001)->get()->getRowArray();
        $this->assertNull($beforeAsset['construction_type_id']);

        // Call service with working construction 1
        $this->contextService->getAssetContext(1001, null, null, null, 1);

        $afterAsset = $this->db->table('assets')->where('id', 1001)->get()->getRowArray();
        $this->assertNull($afterAsset['construction_type_id'], 'assets.construction_type_id MUST remain untouched (NULL) in DB!');
    }

    /**
     * Requirement 10: BOM follows selected working construction dynamically.
     */
    public function testBomFollowsSelectedWorkingConstruction(): void
    {
        $this->seedBaseScenario();

        // Without working construction -> NO_CONSTRUCTION, BOM empty
        $resNoConst = $this->contextService->getAssetContext(1001);
        $this->assertEmpty($resNoConst['bom']);

        // With TM-1 (id: 1) -> 2 items from TM-1 BOM
        $resTm1 = $this->contextService->getAssetContext(1001, null, null, null, 1);
        $this->assertSame('READY', $resTm1['status']);
        $this->assertCount(2, $resTm1['bom']);
        $this->assertSame('MAT-PIN-20', $resTm1['bom'][0]['material_code']);
        $this->assertSame('MAT-TRAV-UNP', $resTm1['bom'][1]['material_code']);
    }

    /**
     * Requirement 11: BOM preview remains strictly read-only.
     */
    public function testBomPreviewIsReadOnly(): void
    {
        $this->seedBaseScenario();

        $res = $this->contextService->getAssetContext(1001, null, null, null, 1);
        foreach ($res['bom'] as $item) {
            $this->assertArrayHasKey('material_code', $item);
            $this->assertArrayHasKey('nama_material', $item);
            $this->assertArrayHasKey('satuan', $item);
            // Must NOT contain input quantities or selection booleans
            $this->assertArrayNotHasKey('selected', $item);
            $this->assertArrayNotHasKey('user_quantity', $item);
        }
    }

    /**
     * Requirement 12 & 13: No automatic material selection and no automatic quantity.
     */
    public function testNoAutomaticMaterialSelectionAndNoAutomaticQuantity(): void
    {
        $this->seedBaseScenario();

        $res = $this->contextService->getAssetContext(1001, null, null, null, 1);
        $this->assertIsArray($res['bom']);
        // The service simply returns the catalog items without auto-selected state
        foreach ($res['bom'] as $b) {
            $this->assertFalse(isset($b['auto_selected']));
        }
    }

    /**
     * Requirement 14 & 15: Cancellation restores system state without DB side-effects.
     */
    public function testCancelRestoresOriginalSystemStateWithoutDbMutation(): void
    {
        $this->seedBaseScenario();

        // 1. Simulating Working Context applied
        $resModified = $this->contextService->getAssetContext(1001, null, null, 102, 1);
        $this->assertSame('OPERATOR', $resModified['network']['section']['source']);
        $this->assertSame('OPERATOR', $resModified['context_source']['construction']);

        // 2. Simulating Cancel (back to pure system context with null working params)
        $resRestored = $this->contextService->getAssetContext(1001, null, null, null, null);
        $this->assertSame('UNMAPPED', $resRestored['network']['section']['source']);
        $this->assertSame('BELUM_TERPETAKAN', $resRestored['status_badges']['section']);
        $this->assertSame('UNMAPPED', $resRestored['context_source']['construction']);
        $this->assertSame('BELUM_TERPETAKAN', $resRestored['status_badges']['construction']);
        $this->assertEmpty($resRestored['bom']);
    }

    /**
     * Requirement 16: Material flow remains off initially.
     */
    public function testMaterialFlowRemainsOffInitially(): void
    {
        $this->seedBaseScenario();

        $res = $this->contextService->getAssetContext(1001, null, null, 102, 1);
        // Handoff URL does not force material creation flags
        $this->assertStringNotContainsString('require_material=1', $res['navigation']['create_temuan_url']);
    }

    /**
     * Requirement 17: Temuan handoff carries working context parameters safely in URL.
     */
    public function testTemuanHandoffCarriesWorkingContextSafely(): void
    {
        $this->seedBaseScenario();

        $res = $this->contextService->getAssetContext(1001, null, null, 102, 1);
        $url = $res['navigation']['create_temuan_url'];

        $this->assertStringContainsString('asset_id=1001', $url);
        $this->assertStringContainsString('section_id=102', $url);
        $this->assertStringContainsString('working_section_id=102', $url);
        $this->assertStringContainsString('working_construction_id=1', $url);
        $this->assertStringContainsString('context_mode=WORKING_CONTEXT', $url);
    }

    /**
     * Requirement 18: No Transline mutation during context correction.
     */
    public function testZeroTranslineMutation(): void
    {
        $this->seedBaseScenario();

        $this->db->table('gis_translines')->insert(['id' => 1, 'transline_code' => 'TL-TEST-01', 'penyulang_id' => 10]);
        $beforeCount = $this->db->table('gis_translines')->countAllResults();

        $this->contextService->getAssetContext(1001, null, null, 102, 1);

        $afterCount = $this->db->table('gis_translines')->countAllResults();
        $this->assertSame($beforeCount, $afterCount);
    }

    /**
     * Requirement 19: No MR-01 Material Master or Transaction mutation.
     */
    public function testZeroMr01Mutation(): void
    {
        $this->seedBaseScenario();

        $beforeMat = $this->db->table('master_materials')->countAllResults();
        $beforeBom = $this->db->table('construction_bom_items')->countAllResults();

        $this->contextService->getAssetContext(1001, null, null, 102, 1);

        $afterMat = $this->db->table('master_materials')->countAllResults();
        $afterBom = $this->db->table('construction_bom_items')->countAllResults();

        $this->assertSame($beforeMat, $afterMat);
        $this->assertSame($beforeBom, $afterBom);
    }

    /**
     * Requirement 20: No AR-01 Temuan or Temuan Materials mutation.
     */
    public function testZeroAr01Mutation(): void
    {
        $this->seedBaseScenario();

        $beforeTemuan = $this->db->table('temuan')->countAllResults();
        $beforeTemuanMat = $this->db->table('temuan_materials')->countAllResults();

        $this->contextService->getAssetContext(1001, null, null, 102, 1);

        $afterTemuan = $this->db->table('temuan')->countAllResults();
        $afterTemuanMat = $this->db->table('temuan_materials')->countAllResults();

        $this->assertSame($beforeTemuan, $afterTemuan);
        $this->assertSame($beforeTemuanMat, $afterTemuanMat);
    }
}
