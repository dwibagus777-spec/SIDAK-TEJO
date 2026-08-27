<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ConstructionIntelligenceService;
use App\Services\NetworkConfigurationService;
use App\Services\InspectionMeasurementService;
use App\Services\DynamicSldEngineService;
use App\Services\FeederHealthIntelligenceService;
use App\Database\Seeds\ConstructionIntelligenceSeeder;

/**
 * @internal
 */
final class ConstructionIntelligenceTest extends CIUnitTestCase
{
    private ConstructionIntelligenceService $ciService;
    private NetworkConfigurationService $ncService;
    private InspectionMeasurementService $inspService;
    private DynamicSldEngineService $sldService;
    private FeederHealthIntelligenceService $fhiService;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        // 1. Create SQLite in-memory tables for tests if not exists
        $this->ensureTablesExist($forge, $db);

        // 2. Instantiate Services
        $this->ciService   = new ConstructionIntelligenceService($db);
        $this->ncService   = new NetworkConfigurationService($db);
        $this->inspService = new InspectionMeasurementService($db);
        $this->sldService  = new DynamicSldEngineService($db, $this->ncService);
        $this->fhiService  = new FeederHealthIntelligenceService($db);

        // 3. Seed initial canonical data
        $seeder = new ConstructionIntelligenceSeeder(new \Config\Database());
        $seeder->run();
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

        // sections
        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER', 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('sections', true);
            $db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'Section A CANDRAMAS']);
        }

        // network_section_configurations
        if (!$db->tableExists('network_section_configurations')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'section_id' => ['type' => 'INTEGER'],
                'import_batch_id' => ['type' => 'INTEGER', 'null' => true],
                'section_ref' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'version_number' => ['type' => 'INTEGER', 'default' => 1],
                'effective_from' => ['type' => 'DATETIME', 'null' => true],
                'effective_to' => ['type' => 'DATETIME', 'null' => true],
                'verification_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'ACTIVE'],
                'topology_connectivity_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'UNVERIFIED'],
                'configuration_source' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'INITIAL_AUDIT'],
                'inspection_id' => ['type' => 'INTEGER', 'null' => true],
                'changed_by' => ['type' => 'INTEGER', 'null' => true],
                'change_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('network_section_configurations', true);
        }

        // network_section_conductors
        if (!$db->tableExists('network_section_conductors')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'network_section_configuration_id' => ['type' => 'INTEGER'],
                'conductor_material_id' => ['type' => 'INTEGER'],
                'sequence_order' => ['type' => 'INTEGER', 'default' => 1],
                'segment_label' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'start_node_id' => ['type' => 'INTEGER', 'null' => true],
                'end_node_id' => ['type' => 'INTEGER', 'null' => true],
                'length_m' => ['type' => 'DECIMAL', 'null' => true],
                'verified' => ['type' => 'INTEGER', 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('network_section_conductors', true);
        }

        // network_section_accessories
        if (!$db->tableExists('network_section_accessories')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'network_section_configuration_id' => ['type' => 'INTEGER'],
                'accessory_material_id' => ['type' => 'INTEGER'],
                'accessory_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'GSW'],
                'quantity' => ['type' => 'INTEGER', 'default' => 1],
                'location_reference' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'condition_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'GOOD'],
                'initial_observed_condition' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'GOOD'],
                'verified' => ['type' => 'INTEGER', 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('network_section_accessories', true);
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
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER'],
                'calculation_policy_version' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'FHI-v1.0'],
                'period_month' => ['type' => 'VARCHAR', 'constraint' => 7],
                'health_score' => ['type' => 'DECIMAL', 'default' => 100.00],
                'health_classification' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'SEMPURNA'],
                'interruption_count' => ['type' => 'INTEGER', 'default' => 0],
                'interruption_duration_minutes' => ['type' => 'DECIMAL', 'default' => 0.00],
                'critical_findings_count' => ['type' => 'INTEGER', 'default' => 0],
                'recurring_findings_count' => ['type' => 'INTEGER', 'default' => 0],
                'bom_degradation_score' => ['type' => 'DECIMAL', 'default' => 0.00],
                'overload_events_count' => ['type' => 'INTEGER', 'default' => 0],
                'explanation_json' => ['type' => 'TEXT', 'null' => true],
                'calculated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('feeder_health_classifications', true);
        }
    }

    // =========================================================================
    // 14 AUTOMATED ACCEPTANCE TESTS
    // =========================================================================

    public function testMaterialAliasResolution(): void
    {
        $pin = $this->ciService->resolveMaterialAlias('PIN');
        $this->assertNotNull($pin);
        $this->assertEquals('MAT-ISO-PIN-20KV', $pin['material_code']);

        $la = $this->ciService->resolveMaterialAlias('LIGHTNING ARRESTER');
        $this->assertNotNull($la);
        $this->assertEquals('MAT-PROT-LA-24KV', $la['material_code']);

        $fco = $this->ciService->resolveMaterialAlias('SEKRING JTM');
        $this->assertNotNull($fco);
        $this->assertEquals('MAT-PROT-FCO-24KV', $fco['material_code']);

        $gsw = $this->ciService->resolveMaterialAlias('KAWAT GSW');
        $this->assertNotNull($gsw);
        $this->assertEquals('MAT-ACC-GSW', $gsw['material_code']);
    }

    public function testPureMaterialIdentityGate1(): void
    {
        $db = \Config\Database::connect();
        $fields = $db->getFieldNames('master_materials');

        $this->assertContains('material_code', $fields);
        $this->assertContains('nama_material', $fields);
        $this->assertNotContains('stok_gudang', $fields);
        $this->assertNotContains('harga_satuan', $fields);
        $this->assertNotContains('supplier_id', $fields);
    }

    public function testSetNullFkPolicyGate2(): void
    {
        $c = $this->ciService->registerConstructionType([
            'construction_code' => 'TEST_TM_CUSTOM',
            'construction_name' => 'Custom TM for Test',
        ]);

        $bomItem = $this->ciService->addBomItem($c['id'], [
            'raw_material_name' => 'MATERIAL_YANG_BELUM_DIKENAL_XYZ_999',
            'quantity'          => null,
            'quantity_status'   => 'UNKNOWN',
        ]);

        $this->assertNull($bomItem['material_id']);
        $this->assertEquals('UNRESOLVED', $bomItem['mapping_status']);
        $this->assertEquals('UNKNOWN', $bomItem['quantity_status']);
    }

    public function testQuantityStatusEnumGate3(): void
    {
        $c = $this->ciService->registerConstructionType([
            'construction_code' => 'TEST_TM_QTY',
            'construction_name' => 'Custom TM Qty',
        ]);

        $b1 = $this->ciService->addBomItem($c['id'], [
            'raw_material_name' => 'PIN',
            'quantity'          => 3,
            'quantity_status'   => 'KNOWN',
        ]);
        $this->assertEquals('KNOWN', $b1['quantity_status']);
        $this->assertEquals(3.0, (float)$b1['quantity']);

        $b2 = $this->ciService->addBomItem($c['id'], [
            'raw_material_name' => 'LA',
            'quantity'          => null,
            'quantity_status'   => 'UNKNOWN',
        ]);
        $this->assertEquals('UNKNOWN', $b2['quantity_status']);
        $this->assertNull($b2['quantity']);
    }

    public function testSingleActiveConfigurationInvariantGate4(): void
    {
        // 1. Create first configuration as ACTIVE
        $cfg1 = $this->ncService->createSectionConfiguration(1, [
            'verification_status' => 'ACTIVE',
            'change_reason'       => 'Initial Version 1',
        ]);
        $this->assertEquals(1, $cfg1['version_number']);
        $this->assertEquals('ACTIVE', $cfg1['verification_status']);
        $this->assertNull($cfg1['effective_to']);

        // 2. Create second configuration as ACTIVE
        $cfg2 = $this->ncService->createSectionConfiguration(1, [
            'verification_status' => 'ACTIVE',
            'change_reason'       => 'Upgrade Version 2',
        ]);
        $this->assertEquals(2, $cfg2['version_number']);
        $this->assertEquals('ACTIVE', $cfg2['verification_status']);
        $this->assertNull($cfg2['effective_to']);

        // 3. Verify Version 1 is now SUPERSEDED with non-null effective_to
        $cfg1Reloaded = $this->ncService->getFullConfiguration($cfg1['id']);
        $this->assertEquals('SUPERSEDED', $cfg1Reloaded['verification_status']);
        $this->assertNotNull($cfg1Reloaded['effective_to']);

        // 4. Verify getActiveConfiguration returns Version 2
        $activeCfg = $this->ncService->getActiveConfiguration(1);
        $this->assertEquals($cfg2['id'], $activeCfg['id']);
    }

    public function testDynamicSldSeparationGate5(): void
    {
        $cond = $this->ciService->resolveMaterialAlias('AAACS 240');

        $cfg = $this->ncService->createSectionConfiguration(1, [
            'verification_status' => 'ACTIVE',
            'conductors'          => [
                ['conductor_material_id' => $cond['id'], 'segment_label' => 'PB01 -> NODE1', 'length_m' => 150.0],
            ],
            'accessories'         => [
                ['accessory_material_id' => $this->ciService->resolveMaterialAlias('LA')['id'], 'accessory_type' => 'LA', 'condition_status' => 'DEFECTIVE'],
            ]
        ]);

        $sld = $this->sldService->renderSectionSld(1);

        $this->assertTrue($sld['success']);
        $this->assertArrayHasKey('topology_truth', $sld);
        $this->assertArrayHasKey('health_overlay', $sld);

        // Topology truth reflects conductor length and segments
        $this->assertEquals(1, $sld['topology_truth']['total_segments']);
        $this->assertEquals('MAT-COND-AAACS-240', $sld['topology_truth']['segments'][0]['conductor_code']);

        // Visual overlay reflects the defective LA without breaking topology
        $this->assertEquals(1, $sld['health_overlay']['defect_count']);
        $this->assertEquals('DEFECTIVE', $sld['health_overlay']['defect_overlays'][0]['condition']);
        $this->assertStringContainsString('icon-warning-la', $sld['health_overlay']['defect_overlays'][0]['sld_icon']);
    }

    public function testFeederHealthPolicyVersioningGate6(): void
    {
        $health = $this->fhiService->calculateFeederHealth(1, '2026-08');
        $this->assertNotNull($health);
        $this->assertEquals('FHI-v1.0', $health['calculation_policy_version']);
        $this->assertNotEmpty($health['explanation_json']);
    }

    public function testParameterizedWeightsAndThresholdsGate7(): void
    {
        $policy = $this->fhiService->ensureDefaultPolicy();
        $this->assertEquals('FHI-v1.0', $policy['policy_code']);

        $db = \Config\Database::connect();
        $rules = $db->table('feeder_health_policy_rules')->where('policy_version_id', $policy['id'])->get()->getResultArray();

        $this->assertNotEmpty($rules);
        $keys = array_column($rules, 'metric_key');
        $this->assertContains('GANGGUAN_FREQUENCY', $keys);
        $this->assertContains('CRITICAL_FINDINGS', $keys);
    }

    public function testKubikelConstructionRemainsDraft(): void
    {
        $db = \Config\Database::connect();
        $kubikel = $db->table('construction_types')->where('construction_family', 'GARDU_KUBIKEL')->get()->getResultArray();

        $this->assertNotEmpty($kubikel);
        foreach ($kubikel as $k) {
            $this->assertEquals('DRAFT', $k['approval_status']);
        }
    }

    public function testEquipmentCannotBeTranslineInvariant9(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Domain Invariant IX Violation');

        // Create dummy equipment material in GARDU domain
        $equip = $this->ciService->registerMaterial([
            'material_code'   => 'MAT-TEST-TRAFO-100KVA',
            'nama_material'   => 'Trafo Distribusi 100 kVA',
            'material_domain' => 'TRAFO',
        ]);

        $cfg = $this->ncService->createSectionConfiguration(1, [
            'verification_status' => 'DRAFT',
        ]);

        // Attempt to insert Trafo as a conductor transline
        $this->ncService->addConductorSegment($cfg['id'], [
            'conductor_material_id' => $equip['id'],
            'segment_label'         => 'Illegal Conductor Segment',
        ]);
    }

    public function testMixedConductorSupport(): void
    {
        $aaacs240 = $this->ciService->resolveMaterialAlias('AAACS 240');
        $aaac150  = $this->ciService->resolveMaterialAlias('AAAC 150');

        $cfg = $this->ncService->createSectionConfiguration(1, [
            'verification_status' => 'ACTIVE',
            'conductors'          => [
                ['conductor_material_id' => $aaacs240['id'], 'segment_label' => 'PB01 -> NODE_04', 'length_m' => 200.0],
                ['conductor_material_id' => $aaac150['id'],  'segment_label' => 'NODE_04 -> TM1',  'length_m' => 350.0],
            ]
        ]);

        $this->assertCount(2, $cfg['conductors']);
        $this->assertEquals('MAT-COND-AAACS-240', $cfg['conductors'][0]['material_code']);
        $this->assertEquals('MAT-COND-AAAC-150', $cfg['conductors'][1]['material_code']);
    }

    public function testHistoricalTimeTravelConfiguration(): void
    {
        $pastDate = '2026-08-01 10:00:00';
        $nowDate  = '2026-08-27 12:00:00';

        $cfg1 = $this->ncService->createSectionConfiguration(1, [
            'effective_from'      => $pastDate,
            'verification_status' => 'ACTIVE',
            'change_reason'       => 'Historic Old Config',
        ]);

        // Upgrade now
        $cfg2 = $this->ncService->createSectionConfiguration(1, [
            'effective_from'      => $nowDate,
            'verification_status' => 'ACTIVE',
            'change_reason'       => 'New Present Config',
        ]);

        // Query historical state as of 2026-08-15
        $histConfig = $this->ncService->getConfigurationAt(1, '2026-08-15 12:00:00');
        $this->assertNotNull($histConfig);
        $this->assertEquals($cfg1['id'], $histConfig['id']);

        // Query current state
        $currConfig = $this->ncService->getActiveConfiguration(1);
        $this->assertEquals($cfg2['id'], $currConfig['id']);
    }

    public function testInspectionProgramDomainNormalization(): void
    {
        $db = \Config\Database::connect();
        $domains = $db->table('inspection_programs')->select('asset_domain')->distinct()->get()->getResultArray();
        $domainList = array_column($domains, 'asset_domain');

        $this->assertContains('JTM', $domainList);
        $this->assertContains('TRAFO', $domainList);
        $this->assertContains('GARDU_KUBIKEL', $domainList);
        $this->assertContains('JTR', $domainList);
    }

    public function testGttMeasurementPointSchema(): void
    {
        $db = \Config\Database::connect();
        $points = $db->table('inspection_measurement_points')->get()->getResultArray();
        $codes = array_column($points, 'point_code');

        $this->assertContains('MAIN_R', $codes);
        $this->assertContains('MAIN_S', $codes);
        $this->assertContains('MAIN_T', $codes);
        $this->assertContains('MAIN_N', $codes);
        $this->assertContains('LINE_A_R', $codes);
        $this->assertContains('VOLT_RN', $codes);
    }
}
