<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ConstructionAssetIntelligenceService;
use App\Services\ConstructionIntelligenceService;
use App\Database\Seeds\ConstructionIntelligenceSeeder;

/**
 * Test Suite for CR-06G Construction-to-Asset Intelligence (Contract v1.0)
 * Tests all 8 Hardening Gates (G0 - G8).
 *
 * @internal
 */
final class ConstructionAssetIntelligenceTest extends CIUnitTestCase
{
    private ConstructionAssetIntelligenceService $intelService;
    private ConstructionIntelligenceService $ciService;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        // 1. Ensure required tables exist
        $this->ensureTablesExist($forge, $db);

        // 2. Initialize service
        $this->ciService    = new ConstructionIntelligenceService($db);
        $this->intelService = new ConstructionAssetIntelligenceService($db);

        // 3. Seed canonical baseline data
        $seeder = new ConstructionIntelligenceSeeder(new \Config\Database());
        $seeder->run();

        // 4. Truncate test tables
        $db->table('assets')->truncate();
        $db->table('asset_intelligence_snapshots')->truncate();
        $db->table('temuan')->truncate();
    }

    private function ensureTablesExist($forge, $db): void
    {
        // master_materials
        if (!$db->tableExists('master_materials')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'material_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'nama_material' => ['type' => 'VARCHAR', 'constraint' => 150],
                'nama_lapangan' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'satuan' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET'],
                'material_domain' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'material_category' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'HARDWARE'],
                'specification' => ['type' => 'TEXT', 'null' => true],
                'source_workbook' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'KONSTRUKSI.xlsx'],
                'source_sheet' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_row' => ['type' => 'INTEGER', 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('master_materials', true);
        }

        // material_aliases
        if (!$db->tableExists('material_aliases')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'material_id' => ['type' => 'INTEGER'],
                'alias_name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'normalized_alias' => ['type' => 'VARCHAR', 'constraint' => 100],
                'alias_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'FIELD_TERM'],
                'source' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'KONSTRUKSI.xlsx'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('material_aliases', true);
        }

        // construction_types
        if (!$db->tableExists('construction_types')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'construction_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'construction_name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'construction_family' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'network_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'JTM'],
                'asset_domain' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG'],
                'approval_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'ACTIVE'],
                'source_sheet' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_row' => ['type' => 'INTEGER', 'null' => true],
                'is_active' => ['type' => 'INTEGER', 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('construction_types', true);
        }

        // construction_bom_items
        if (!$db->tableExists('construction_bom_items')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'construction_type_id' => ['type' => 'INTEGER'],
                'material_id' => ['type' => 'INTEGER', 'null' => true],
                'raw_material_name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'material_alias' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'quantity' => ['type' => 'DECIMAL', 'null' => true],
                'quantity_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'KNOWN'],
                'unit' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'mandatory' => ['type' => 'INTEGER', 'default' => 1],
                'component_category' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_sheet' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_row' => ['type' => 'INTEGER', 'null' => true],
                'mapping_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'RESOLVED'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('construction_bom_items', true);
        }

        // inspection_programs
        if (!$db->tableExists('inspection_programs')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'program_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_pekerjaan' => ['type' => 'VARCHAR', 'constraint' => 150],
                'asset_domain' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'inspection_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'VISUAL_L1'],
                'executor_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'INSPEKSI'],
                'inspection_category' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'PREVENTIVE'],
                'active' => ['type' => 'INTEGER', 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('inspection_programs', true);
        }

        // inspection_measurement_templates
        if (!$db->tableExists('inspection_measurement_templates')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'inspection_program_id' => ['type' => 'INTEGER', 'null' => true],
                'template_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'template_name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'asset_domain' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'GTT'],
                'active' => ['type' => 'INTEGER', 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('inspection_measurement_templates', true);
        }

        // inspection_measurement_points
        if (!$db->tableExists('inspection_measurement_points')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'template_id' => ['type' => 'INTEGER'],
                'point_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'point_name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'phase' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'line' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'measurement_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'CURRENT_AMPERE'],
                'unit' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'A'],
                'sequence_order' => ['type' => 'INTEGER', 'default' => 1],
                'mandatory' => ['type' => 'INTEGER', 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('inspection_measurement_points', true);
        }

        // feeder_health_policy_versions
        if (!$db->tableExists('feeder_health_policy_versions')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'policy_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'policy_name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'description' => ['type' => 'TEXT', 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'ACTIVE'],
                'effective_from' => ['type' => 'DATETIME', 'null' => true],
                'effective_to' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('feeder_health_policy_versions', true);
        }

        // feeder_health_policy_rules
        if (!$db->tableExists('feeder_health_policy_rules')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'policy_version_id' => ['type' => 'INTEGER'],
                'metric_key' => ['type' => 'VARCHAR', 'constraint' => 50],
                'weight' => ['type' => 'DECIMAL', 'default' => 0.2],
                'threshold_sempurna_min' => ['type' => 'DECIMAL', 'default' => 85.00],
                'threshold_sakit_min' => ['type' => 'DECIMAL', 'default' => 70.00],
                'threshold_kronis_min' => ['type' => 'DECIMAL', 'default' => 50.00],
                'threshold_kritis_max' => ['type' => 'DECIMAL', 'default' => 49.99],
                'rule_params_json' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('feeder_health_policy_rules', true);
        }

        // feeder_health_classifications
        if (!$db->tableExists('feeder_health_classifications')) {
            $forge->addField([
                'id'                            => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id'                  => ['type' => 'INTEGER'],
                'calculation_policy_version'    => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'FHI-v1.0'],
                'period_month'                  => ['type' => 'VARCHAR', 'constraint' => 7],
                'health_score'                  => ['type' => 'DECIMAL', 'default' => 100.00],
                'health_classification'         => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'SEMPURNA'],
                'health_category'               => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'SEMPURNA'],
                'fhi_code'                      => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'FHI_SEMPURNA'],
                'interruption_count'            => ['type' => 'INTEGER', 'default' => 0],
                'interruption_duration_minutes' => ['type' => 'DECIMAL', 'default' => 0.00],
                'critical_findings_count'       => ['type' => 'INTEGER', 'default' => 0],
                'recurring_findings_count'      => ['type' => 'INTEGER', 'default' => 0],
                'bom_degradation_score'         => ['type' => 'DECIMAL', 'default' => 0.00],
                'overload_events_count'         => ['type' => 'INTEGER', 'default' => 0],
                'explanation_json'              => ['type' => 'TEXT', 'null' => true],
                'calculated_at'                 => ['type' => 'DATETIME', 'null' => true],
                'created_at'                    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'                    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('feeder_health_classifications', true);
        }

        // asset_intelligence_snapshots
        if (!$db->tableExists('asset_intelligence_snapshots')) {
            $forge->addField([
                'id'                         => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'asset_id'                   => ['type' => 'INTEGER'],
                'penyulang_id'               => ['type' => 'INTEGER', 'null' => true],
                'section_id'                 => ['type' => 'INTEGER', 'null' => true],
                'construction_type_id'       => ['type' => 'INTEGER', 'null' => true],
                'resolution_status'          => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'UNRESOLVED'],
                'bom_completeness_ratio'     => ['type' => 'DECIMAL', 'null' => true],
                'active_findings_count'      => ['type' => 'INTEGER', 'default' => 0],
                'recurring_findings_count'   => ['type' => 'INTEGER', 'default' => 0],
                'asset_degradation_index'    => ['type' => 'DECIMAL', 'null' => true],
                'asset_health_score'         => ['type' => 'DECIMAL', 'null' => true],
                'health_category'            => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'UNRESOLVED'],
                'degradation_breakdown_json' => ['type' => 'TEXT', 'null' => true],
                'snapshot_version'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'CR-06G-v1.0'],
                'calculated_at'              => ['type' => 'DATETIME', 'null' => true],
                'created_at'                 => ['type' => 'DATETIME', 'null' => true],
                'updated_at'                 => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('asset_intelligence_snapshots', true);
        }

        // assets
        if (!$db->tableExists('assets')) {
            $forge->addField([
                'id'                             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_asset'                     => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'                     => ['type' => 'VARCHAR', 'constraint' => 150],
                'jenis_asset'                    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'                         => ['type' => 'INTEGER', 'null' => true],
                'penyulang_id'                   => ['type' => 'INTEGER', 'null' => true],
                'section_id'                     => ['type' => 'INTEGER', 'null' => true],
                'construction_type_id'           => ['type' => 'INTEGER', 'null' => true],
                'health_score'                   => ['type' => 'DECIMAL', 'null' => true],
                'health_category'                => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'degradation_index'              => ['type' => 'DECIMAL', 'null' => true],
                'intelligence_resolution_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'UNRESOLVED'],
                'created_at'                     => ['type' => 'DATETIME', 'null' => true],
                'updated_at'                     => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'                     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('assets', true);
        }

        // temuan
        if (!$db->tableExists('temuan')) {
            $forge->addField([
                'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'nomor_temuan'         => ['type' => 'VARCHAR', 'constraint' => 100],
                'ulp_id'               => ['type' => 'INTEGER', 'null' => true],
                'penyulang_id'         => ['type' => 'INTEGER', 'null' => true],
                'section_id'           => ['type' => 'INTEGER', 'null' => true],
                'asset_id'             => ['type' => 'INTEGER', 'null' => true],
                'jenis_temuan'         => ['type' => 'VARCHAR', 'constraint' => 100],
                'pelaksana'            => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'PDKB'],
                'prioritas'            => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'RINGAN'],
                'potensi_gangguan'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'DGR'],
                'konduktor'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'noga'                 => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'material'             => ['type' => 'TEXT', 'null' => true],
                'detail_temuan'        => ['type' => 'TEXT', 'null' => true],
                'alamat'               => ['type' => 'TEXT', 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'null' => true],
                'component_code'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'recurrence_count'     => ['type' => 'INTEGER', 'default' => 0],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
                'tanggal_temuan'       => ['type' => 'DATE', 'null' => true],
                'tanggal_selesai'      => ['type' => 'DATE', 'null' => true],
                'foto'                 => ['type' => 'TEXT', 'null' => true],
                'foto_path'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_by'           => ['type' => 'INTEGER', 'null' => true],
                'updated_by'           => ['type' => 'INTEGER', 'null' => true],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
        }

        // ulps & penyulang & sections
        if (!$db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('ulps', true);
        }
        $db->table('ulps')->truncate();
        $db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'ULP SIDOARJO KOTA']);

        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('penyulang', true);
        }
        $db->table('penyulang')->truncate();
        $db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);

        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER', 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('sections', true);
        }
        $db->table('sections')->truncate();
        $db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'GI-LBSM PDAM']);
    }

    public function testAssetConstructionTypeResolutionDirectFk(): void
    {
        $db = \Config\Database::connect();
        $tm1 = $db->table('construction_types')->where('construction_code', 'TM1')->get()->getFirstRow('array');
        $this->assertNotNull($tm1);

        $asset = [
            'id'                   => 1,
            'kode_asset'           => 'AST-SWP-001',
            'construction_type_id' => (int)$tm1['id'],
            'jenis_asset'          => 'TIANG',
        ];

        $res = $this->intelService->resolveAssetConstructionType($asset);
        $this->assertEquals('RESOLVED', $res['status']);
        $this->assertEquals('DIRECT_FOREIGN_KEY', $res['method']);
        $this->assertEquals('TM1', $res['construction_type']['construction_code']);
    }

    public function testAssetConstructionTypeResolutionTagInference(): void
    {
        $asset = [
            'id'                   => 2,
            'kode_asset'           => 'AST-SWP-002',
            'construction_type_id' => null,
            'jenis_asset'          => 'TM-1',
        ];

        $res = $this->intelService->resolveAssetConstructionType($asset);
        $this->assertEquals('RESOLVED', $res['status']);
        $this->assertEquals('TAG_INFERENCE', $res['method']);
        $this->assertEquals('TM1', $res['construction_type']['construction_code']);
    }

    public function testAssetUnresolvedWhenNoMatchingConstructionType(): void
    {
        $asset = [
            'id'                   => 3,
            'kode_asset'           => 'AST-SWP-003',
            'construction_type_id' => 99999, // Invalid ID
            'jenis_asset'          => 'UNKNOWN_TYPE_XYZ',
        ];

        $res = $this->intelService->resolveAssetConstructionType($asset);
        $this->assertEquals('UNRESOLVED', $res['status']);
        $this->assertNull($res['construction_type']);
    }

    public function testBomResolutionCanonicalMaterials(): void
    {
        $db = \Config\Database::connect();
        $tm1 = $db->table('construction_types')->where('construction_code', 'TM1')->get()->getFirstRow('array');
        $this->assertNotNull($tm1);

        $bom = $this->intelService->resolveBom((int)$tm1['id']);
        $this->assertEquals('RESOLVED', $bom['status']);
        $this->assertGreaterThan(0.0, $bom['completeness_ratio']);
        $this->assertNotEmpty($bom['items']);
    }

    public function testFindingClassificationComponents(): void
    {
        $f1 = ['jenis_temuan' => 'KONSTRUKSI', 'detail_temuan' => 'Isolator tumpu retak fasa R'];
        $this->assertEquals('INSULATOR', $this->intelService->classifyFindingComponent($f1));

        $f2 = ['jenis_temuan' => 'KONSTRUKSI', 'detail_temuan' => 'Tiang beton miring 15 derajat'];
        $this->assertEquals('POLE', $this->intelService->classifyFindingComponent($f2));

        $f3 = ['jenis_temuan' => 'KONSTRUKSI', 'detail_temuan' => 'Travers UNP korosi berat'];
        $this->assertEquals('CROSS_ARM', $this->intelService->classifyFindingComponent($f3));

        $f4 = ['jenis_temuan' => 'HOTSPOT', 'detail_temuan' => 'Lightning Arrester pecah'];
        $this->assertEquals('PROTECTION', $this->intelService->classifyFindingComponent($f4));

        $f5 = ['jenis_temuan' => 'ROW', 'detail_temuan' => 'Pohon bambu menyentuh konduktor'];
        $this->assertEquals('ROW', $this->intelService->classifyFindingComponent($f5));
    }

    public function testCalculateAssetHealthWithFindingsCalculatesAccurateAdiAndAhs(): void
    {
        $db = \Config\Database::connect();
        $tm1 = $db->table('construction_types')->where('construction_code', 'TM1')->get()->getFirstRow('array');

        // Insert Master Asset
        $db->table('assets')->insert([
            'kode_asset'           => 'AST-SWP-010',
            'nama_asset'           => 'Tiang 10 SIWALAN PANJI',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 1,
            'section_id'           => 1,
            'construction_type_id' => (int)$tm1['id'],
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        $assetId = (int)$db->insertID();

        // Insert Finding 1: Isolator retak (INSULATOR weight 0.25, KRITIS severity 0.8 => 0.20)
        $db->table('temuan')->insert([
            'nomor_temuan'     => 'TMN-001',
            'penyulang_id'     => 1,
            'section_id'       => 1,
            'asset_id'         => $assetId,
            'jenis_temuan'     => 'KONSTRUKSI',
            'detail_temuan'    => 'Isolator retak fasa R',
            'prioritas'        => 'KRITIS',
            'recurrence_count' => 0,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        $health = $this->intelService->calculateAssetHealth((int)$assetId);

        $this->assertTrue($health['success']);
        $this->assertEquals('RESOLVED', $health['resolution_status']);
        $this->assertEquals(0.20, $health['asset_degradation_index']);
        $this->assertEquals(80.0, $health['asset_health_score']);
        $this->assertEquals('WARNING', $health['health_category']);
        $this->assertEquals(1, $health['active_findings_count']);

        // Verify snapshot was created in asset_intelligence_snapshots
        $snapshot = $db->table('asset_intelligence_snapshots')->where('asset_id', $assetId)->get()->getFirstRow('array');
        $this->assertNotNull($snapshot);
        $this->assertEquals(80.0, (float)$snapshot['asset_health_score']);
    }

    public function testNoDataDoesNotEqualHealthyInvariantReturnsNullAndUnresolvedStatus(): void
    {
        $db = \Config\Database::connect();

        // Asset with unresolvable construction type
        $db->table('assets')->insert([
            'kode_asset'           => 'AST-NO-DATA',
            'nama_asset'           => 'Unmapped Asset',
            'jenis_asset'          => 'UNKNOWN_JUNK',
            'penyulang_id'         => 1,
            'section_id'           => 1,
            'construction_type_id' => null,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        $assetId = (int)$db->insertID();

        $health = $this->intelService->calculateAssetHealth((int)$assetId);

        $this->assertTrue($health['success']);
        $this->assertEquals('UNRESOLVED', $health['resolution_status']);
        $this->assertNull($health['asset_health_score']); // MUST NOT BE 100
        $this->assertNull($health['asset_degradation_index']);
        $this->assertEquals('UNRESOLVED', $health['health_category']);
    }

    public function testRecurrenceAmplifiesDegradationFactor(): void
    {
        $db = \Config\Database::connect();
        $tm1 = $db->table('construction_types')->where('construction_code', 'TM1')->get()->getFirstRow('array');

        $db->table('assets')->insert([
            'kode_asset'           => 'AST-REC-001',
            'nama_asset'           => 'Tiang Recurring Finding',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 1,
            'section_id'           => 1,
            'construction_type_id' => (int)$tm1['id'],
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        $assetId = (int)$db->insertID();

        // Finding with recurrence = 2 (Multiplier: 1 + 0.5 * 2 = 2.0x)
        // Insulator (0.25) * KRITIS (0.8) * 2.0 = 0.40 ADI
        $db->table('temuan')->insert([
            'nomor_temuan'     => 'TMN-REC-001',
            'penyulang_id'     => 1,
            'section_id'       => 1,
            'asset_id'         => $assetId,
            'jenis_temuan'     => 'KONSTRUKSI',
            'detail_temuan'    => 'Isolator retak berulang kali',
            'prioritas'        => 'KRITIS',
            'recurrence_count' => 2,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        $health = $this->intelService->calculateAssetHealth((int)$assetId);
        $this->assertEquals(0.40, $health['asset_degradation_index']);
        $this->assertEquals(60.0, $health['asset_health_score']);
        $this->assertEquals('POOR', $health['health_category']);
    }

    public function testSectionAndFeederIntelligenceSummaryGeneratesCc04Payload(): void
    {
        $db = \Config\Database::connect();
        $tm1 = $db->table('construction_types')->where('construction_code', 'TM1')->get()->getFirstRow('array');

        $db->table('assets')->insert([
            'kode_asset'           => 'AST-SEC-001',
            'nama_asset'           => 'Tiang Section 1',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 1,
            'section_id'           => 1,
            'construction_type_id' => (int)$tm1['id'],
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        $assetId = (int)$db->insertID();

        $secSum = $this->intelService->getSectionIntelligenceSummary(1);
        $this->assertEquals(1, $secSum['total_assets']);
        $this->assertEquals(1, $secSum['resolved_assets']);
        $this->assertEquals(100.0, $secSum['average_health_score']);

        $feederSum = $this->intelService->getFeederIntelligenceSummary(1);
        $this->assertEquals('SIWALAN PANJI', $feederSum['nama_penyulang']);
        $this->assertEquals(1, $feederSum['total_sections']);
        $this->assertEquals(1, $feederSum['total_assets']);
        $this->assertEquals(100.0, $feederSum['overall_health_score']);
    }

    public function testAuditCr06gCommandExecution(): void
    {
        $cmd = new \App\Commands\AuditCr06gCommand(\Config\Services::logger(), \Config\Services::commands());

        \CodeIgniter\Test\Filters\CITestStreamFilter::registration();
        \CodeIgniter\Test\Filters\CITestStreamFilter::addOutputFilter();

        $exitCode = $cmd->run([]);
        $output = \CodeIgniter\Test\Filters\CITestStreamFilter::$buffer;

        \CodeIgniter\Test\Filters\CITestStreamFilter::removeOutputFilter();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('CR-06G CONSTRUCTION-TO-ASSET INTELLIGENCE AUDIT', $output);
        $this->assertStringContainsString('ASSET RESOLUTION (Gate G1)', $output);
        $this->assertStringContainsString('BOM RESOLUTION & CANONICAL MATERIALS (Gate G3)', $output);
        $this->assertStringContainsString('FINDING ATTRIBUTION (Gate G4)', $output);
        $this->assertStringContainsString('CR-06G HARDENING GATES VERIFICATION', $output);
    }
}
