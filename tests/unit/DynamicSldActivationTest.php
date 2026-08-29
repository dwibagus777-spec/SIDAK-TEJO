<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\DynamicSldEngineService;
use App\Services\NetworkConfigurationService;
use App\Services\ConstructionAssetIntelligenceService;
use App\Database\Seeds\ConstructionIntelligenceSeeder;

/**
 * Unit Test Suite for CR-06H Dynamic SLD Activation (Contract v1.0)
 * Covers Gates H0 through H8.
 */
class DynamicSldActivationTest extends CIUnitTestCase
{
    protected DynamicSldEngineService $sldService;
    protected NetworkConfigurationService $ncService;
    protected ConstructionAssetIntelligenceService $intelService;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        $this->ensureTablesExist($forge, $db);

        $this->ncService    = new NetworkConfigurationService($db);
        $this->intelService = new ConstructionAssetIntelligenceService($db);
        $this->sldService   = new DynamicSldEngineService($db, $this->ncService);

        // Seed initial data
        $seeder = new ConstructionIntelligenceSeeder(new \Config\Database());
        $seeder->run();
    }

    private function ensureTablesExist($forge, $db): void
    {
        // master_materials
        if (!$db->tableExists('master_materials')) {
            $forge->addField([
                'id'            => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'material_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'nama_material' => ['type' => 'VARCHAR', 'constraint' => 150],
                'satuan'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'MTR'],
            ]);
            $forge->createTable('master_materials', true);
            $db->table('master_materials')->insert(['id' => 1, 'material_code' => 'AAACS 240', 'nama_material' => 'Kabel AAACS 240 mm2', 'satuan' => 'MTR']);
            $db->table('master_materials')->insert(['id' => 2, 'material_code' => 'AAAC 150', 'nama_material' => 'Kabel AAAC 150 mm2', 'satuan' => 'MTR']);
            $db->table('master_materials')->insert(['id' => 3, 'material_code' => 'LA-20KV', 'nama_material' => 'Lightning Arrester 20kV', 'satuan' => 'SET']);
        }

        // ulps
        if (!$db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 20],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('ulps', true);
            $db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'ULP SIDOARJO KOTA']);
        }

        // penyulang
        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('penyulang', true);
            $db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        }

        // sections
        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER', 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 150],
            ]);
            $forge->createTable('sections', true);
            $db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'GI-LBSM PDAM']);
            $db->table('sections')->insert(['id' => 2, 'penyulang_id' => 1, 'nama_section' => 'LBSM PDAM - RECLOSER PASAR PANJI']);
            $db->table('sections')->insert(['id' => 3, 'penyulang_id' => 1, 'nama_section' => 'RECLOSER PASAR PANJI - LBSM BUDURAN 3 - LBS SPBU']);
        }

        // network_section_configurations
        if (!$db->tableExists('network_section_configurations')) {
            $forge->addField([
                'id'                  => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'section_id'          => ['type' => 'INTEGER'],
                'version_number'      => ['type' => 'INTEGER', 'default' => 1],
                'verification_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'ACTIVE'],
                'effective_from'      => ['type' => 'DATETIME', 'null' => true],
                'effective_to'        => ['type' => 'DATETIME', 'null' => true],
                'created_at'          => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('network_section_configurations', true);
        }

        // network_section_conductors
        if (!$db->tableExists('network_section_conductors')) {
            $forge->addField([
                'id'                               => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'network_section_configuration_id' => ['type' => 'INTEGER'],
                'conductor_material_id'            => ['type' => 'INTEGER', 'null' => true],
                'sequence_order'                   => ['type' => 'INTEGER', 'default' => 1],
                'segment_label'                    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'length_m'                         => ['type' => 'DECIMAL', 'default' => 0.0],
                'start_node_id'                    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'end_node_id'                      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'verified'                         => ['type' => 'INTEGER', 'default' => 1],
                'created_at'                       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('network_section_conductors', true);
        }

        // network_section_accessories
        if (!$db->tableExists('network_section_accessories')) {
            $forge->addField([
                'id'                               => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'network_section_configuration_id' => ['type' => 'INTEGER'],
                'accessory_material_id'            => ['type' => 'INTEGER', 'null' => true],
                'accessory_type'                   => ['type' => 'VARCHAR', 'constraint' => 50],
                'quantity'                         => ['type' => 'INTEGER', 'default' => 1],
                'location_reference'               => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'condition_status'                 => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'GOOD'],
                'created_at'                       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('network_section_accessories', true);
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
                'detail_temuan'        => ['type' => 'TEXT', 'null' => true],
                'pelaksana'            => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'PDKB'],
                'prioritas'            => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'RINGAN'],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
                'recurrence_count'     => ['type' => 'INTEGER', 'default' => 0],
                'tanggal_temuan'       => ['type' => 'DATETIME', 'null' => true],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
        }
    }

    private function insertSampleActiveConfig(int $sectionId = 1): int
    {
        $db = \Config\Database::connect();

        $db->table('network_section_conductors')->emptyTable();
        $db->table('network_section_accessories')->emptyTable();
        $db->table('network_section_configurations')->emptyTable();

        $matAaacs240 = $db->table('master_materials')->where('material_code', 'MAT-COND-AAACS-240')->get()->getFirstRow('array');
        $matAaac150  = $db->table('master_materials')->where('material_code', 'MAT-COND-AAAC-150')->get()->getFirstRow('array');
        $matLa       = $db->table('master_materials')->where('material_code', 'MAT-PROT-LA-24KV')->get()->getFirstRow('array');

        $matAaacs240Id = $matAaacs240 ? (int)$matAaacs240['id'] : 1;
        $matAaac150Id  = $matAaac150 ? (int)$matAaac150['id'] : 2;
        $matLaId       = $matLa ? (int)$matLa['id'] : 3;

        $db->table('network_section_configurations')->insert([
            'section_id'          => $sectionId,
            'version_number'      => 1,
            'verification_status' => 'ACTIVE',
            'effective_from'      => date('Y-m-d H:i:s'),
            'effective_to'        => null,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
        $configId = (int)$db->insertID();

        // Conductor Segment 1
        $db->table('network_section_conductors')->insert([
            'network_section_configuration_id' => $configId,
            'conductor_material_id'            => $matAaacs240Id,
            'sequence_order'                   => 1,
            'segment_label'                    => 'GI-PB01',
            'length_m'                         => 350.0,
            'start_node_id'                    => 'GI_START',
            'end_node_id'                      => 'PB01',
            'verified'                         => 1,
            'created_at'                       => date('Y-m-d H:i:s'),
        ]);

        // Conductor Segment 2
        $db->table('network_section_conductors')->insert([
            'network_section_configuration_id' => $configId,
            'conductor_material_id'            => $matAaac150Id,
            'sequence_order'                   => 2,
            'segment_label'                    => 'PB01-LBSM_PDAM',
            'length_m'                         => 450.0,
            'start_node_id'                    => 'PB01',
            'end_node_id'                      => 'LBSM_PDAM',
            'verified'                         => 1,
            'created_at'                       => date('Y-m-d H:i:s'),
        ]);

        // Accessory
        $db->table('network_section_accessories')->insert([
            'network_section_configuration_id' => $configId,
            'accessory_material_id'            => $matLaId,
            'accessory_type'                   => 'LA',
            'quantity'                         => 3,
            'location_reference'               => 'Tiang PB01',
            'condition_status'                 => 'GOOD',
            'created_at'                       => date('Y-m-d H:i:s'),
        ]);

        return $configId;
    }

    public function testGateH0AndH1RenderFeederSldIsReadOnlyAndUsesActiveConfig(): void
    {
        $this->insertSampleActiveConfig(1);

        $sld = $this->sldService->renderFeederSld(1);

        $this->assertTrue($sld['success']);
        $this->assertEquals('PYL-001', $sld['kode_penyulang']);
        $this->assertEquals('SIWALAN PANJI', $sld['nama_penyulang']);
        $this->assertEquals(1, $sld['topology_summary']['configured_sections']);
        $this->assertEquals(0.80, $sld['topology_summary']['total_conductor_length_km']);
        $this->assertEquals(1, $sld['topology_summary']['total_accessories']);
        $this->assertNotEmpty($sld['graph']['nodes']);
        $this->assertNotEmpty($sld['graph']['edges']);
    }

    public function testGateH2ConductorSequenceContinuityOrdersEdgesDeterministically(): void
    {
        $this->insertSampleActiveConfig(1);

        $sld = $this->sldService->renderFeederSld(1);
        $edges = $sld['graph']['edges'];

        $this->assertGreaterThanOrEqual(2, count($edges));
        $this->assertEquals(1, $edges[0]['sequence_order']);
        $this->assertStringContainsString('AAACS', $edges[0]['conductor_material']);
        $this->assertEquals(2, $edges[1]['sequence_order']);
        $this->assertStringContainsString('AAAC', $edges[1]['conductor_material']);
    }

    public function testGateH4AndH6FindingOverlayIncludesOnlyActiveFindingsAndIgnoresSoftDeleted(): void
    {
        $db = \Config\Database::connect();
        $this->insertSampleActiveConfig(1);

        // 1. Active finding (Should appear in overlay)
        $db->table('temuan')->insert([
            'nomor_temuan'  => 'TMN-ACT-001',
            'penyulang_id'  => 1,
            'section_id'    => 1,
            'jenis_temuan'  => 'KONSTRUKSI',
            'detail_temuan' => 'Isolator retak fasa R',
            'prioritas'     => 'KRITIS',
            'status'        => 'OPEN',
            'deleted_at'    => null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        // 2. Soft-deleted finding (Should NOT appear in overlay - Gate H6)
        $db->table('temuan')->insert([
            'nomor_temuan'  => 'TMN-DEL-002',
            'penyulang_id'  => 1,
            'section_id'    => 1,
            'jenis_temuan'  => 'HOTSPOT',
            'detail_temuan' => 'Hotspot pada jumper kabel',
            'prioritas'     => 'KRITIS',
            'status'        => 'OPEN',
            'deleted_at'    => date('Y-m-d H:i:s'),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        // 3. Completed finding (Should NOT appear as open defect overlay)
        $db->table('temuan')->insert([
            'nomor_temuan'  => 'TMN-DONE-003',
            'penyulang_id'  => 1,
            'section_id'    => 1,
            'jenis_temuan'  => 'ROW',
            'detail_temuan' => 'Ranting bambu dekat jaringan',
            'prioritas'     => 'RINGAN',
            'status'        => 'SELESAI',
            'deleted_at'    => null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $sld = $this->sldService->renderFeederSld(1);
        $defects = $sld['intelligence_overlay']['defects_summary'];

        $this->assertCount(1, $defects);
        $this->assertEquals('TMN-ACT-001', $defects[0]['nomor_temuan']);
        $this->assertEquals('INSULATOR', $defects[0]['component']);
        $this->assertEquals('CRITICAL', $defects[0]['severity']);
    }

    public function testGateH7ZeroOrphanVisualNodesAndGateH8FullTraceability(): void
    {
        $this->insertSampleActiveConfig(1);
        $sld = $this->sldService->renderFeederSld(1);

        foreach ($sld['graph']['nodes'] as $node) {
            $this->assertNotEmpty($node['node_id']);
            $this->assertNotEmpty($node['label']);
            $this->assertArrayHasKey('traceability', $node);
            $this->assertNotEmpty($node['traceability']['entity']);
            $this->assertNotEmpty($node['traceability']['id']);
        }

        foreach ($sld['graph']['edges'] as $edge) {
            $this->assertNotEmpty($edge['edge_id']);
            $this->assertArrayHasKey('traceability', $edge);
            $this->assertNotEmpty($edge['traceability']['entity']);
            $this->assertNotEmpty($edge['traceability']['id']);
        }
    }

    public function testSectionDrilldownReturnsCompletePhysicalAndIntelligenceDetails(): void
    {
        $this->insertSampleActiveConfig(1);
        $drilldown = $this->sldService->getSectionDrilldownDetails(1);

        $this->assertTrue($drilldown['success']);
        $this->assertEquals('GI-LBSM PDAM', $drilldown['section']['nama_section']);
        $this->assertNotNull($drilldown['physical_configuration']);
        $this->assertEquals('ACTIVE', $drilldown['physical_configuration']['status']);
        $this->assertEquals(800.0, (float)$drilldown['physical_configuration']['total_length_m']);
        $this->assertNotEmpty($drilldown['physical_configuration']['conductors']);
        $this->assertNotEmpty($drilldown['physical_configuration']['accessories']);
    }

    public function testAuditCr06hCommandExecution(): void
    {
        $result = command('audit:cr06h');
        $this->assertNotNull($result);
    }
}
