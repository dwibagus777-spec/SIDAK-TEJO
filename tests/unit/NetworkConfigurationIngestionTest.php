<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ConstructionIntelligenceService;
use App\Services\NetworkConfigurationService;
use App\Services\NetworkConfigurationIngestionService;
use App\Database\Seeds\ConstructionIntelligenceSeeder;

/**
 * Unit Test Suite for CR-06F Network Configuration Activation & Ingestion Governance (Contract v1.1.1)
 * Covers all 8 Hardening Gates (F1 - F8) and 19 Acceptance Tests.
 *
 * @internal
 */
final class NetworkConfigurationIngestionTest extends CIUnitTestCase
{
    private NetworkConfigurationIngestionService $ingestService;
    private NetworkConfigurationService $ncService;
    private ConstructionIntelligenceService $ciService;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        // 1. Ensure all SQLite in-memory tables exist
        $this->ensureTablesExist($forge, $db);

        // 2. Initialize services
        $this->ciService     = new ConstructionIntelligenceService($db);
        $this->ncService     = new NetworkConfigurationService($db);
        $this->ingestService = new NetworkConfigurationIngestionService($db);

        // 3. Seed canonical baseline data
        $seeder = new ConstructionIntelligenceSeeder(new \Config\Database());
        $seeder->run();

        // 4. Truncate network config tables for isolated per-test state
        $db->table('network_section_configurations')->truncate();
        $db->table('network_section_conductors')->truncate();
        $db->table('network_section_accessories')->truncate();
        $db->table('network_configuration_import_batches')->truncate();
    }

    private function ensureTablesExist($forge, $db): void
    {
        // ulps
        if (!$db->tableExists('ulps')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('ulps', true);
        }
        $db->table('ulps')->truncate();
        $db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'ULP KOTA']);

        // penyulang
        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id' => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('penyulang', true);
        }
        $db->table('penyulang')->truncate();
        $db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'CDR', 'nama_penyulang' => 'CANDRAMAS']);

        // sections
        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER', 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('sections', true);
        }
        $db->table('sections')->truncate();
        $db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'Section A CANDRAMAS']);
        $db->table('sections')->insert(['id' => 2, 'penyulang_id' => 1, 'nama_section' => 'Section B CANDRAMAS']);

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

        // network_configuration_import_batches
        if (!$db->tableExists('network_configuration_import_batches')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'batch_uuid' => ['type' => 'VARCHAR', 'constraint' => 64],
                'source_filename' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'source_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'EXCEL'],
                'import_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'VALIDATING'],
                'total_sections' => ['type' => 'INTEGER', 'default' => 0],
                'committed_sections' => ['type' => 'INTEGER', 'default' => 0],
                'rejected_sections' => ['type' => 'INTEGER', 'default' => 0],
                'validation_summary' => ['type' => 'TEXT', 'null' => true],
                'imported_by' => ['type' => 'INTEGER', 'null' => true],
                'started_at' => ['type' => 'DATETIME', 'null' => true],
                'completed_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('network_configuration_import_batches', true);
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
    }

    private function getValidTestPayload(): array
    {
        return [
            'SECTION_CONFIGURATIONS' => [
                [
                    'SECTION_REF'          => 'SEC-CDR-001',
                    'KODE_ULP'             => '51301',
                    'KODE_PENYULANG'       => 'CDR',
                    'NAMA_SECTION'         => 'Section A CANDRAMAS',
                    'IMPORT_ACTION'        => 'ACTIVATE_NEW_VERSION',
                    'CONFIGURATION_SOURCE' => 'INITIAL_AUDIT',
                    'CHANGE_REASON'        => 'Line Audit 2026',
                ],
            ],
            'CONDUCTOR_SEGMENTS' => [
                [
                    'SECTION_REF'              => 'SEC-CDR-001',
                    'SEQUENCE_ORDER'           => 1,
                    'KODE_MATERIAL_KONDUKTOR'  => 'AAACS 240',
                    'PANJANG_METER'            => 250.0,
                    'START_NODE'               => 'GI_CANDRAMAS',
                    'END_NODE'                 => 'PB01',
                    'SEGMENT_LABEL'            => 'Main Feeder Trunk 1',
                ],
                [
                    'SECTION_REF'              => 'SEC-CDR-001',
                    'SEQUENCE_ORDER'           => 2,
                    'KODE_MATERIAL_KONDUKTOR'  => 'AAAC 150',
                    'PANJANG_METER'            => 450.0,
                    'START_NODE'               => 'PB01',
                    'END_NODE'                 => 'TM1_CANDRAMAS',
                    'SEGMENT_LABEL'            => 'Overhead Main Segment 2',
                ],
            ],
            'NETWORK_ACCESSORIES' => [
                [
                    'SECTION_REF'                => 'SEC-CDR-001',
                    'JENIS_AKSESORIS'            => 'GSW',
                    'KODE_MATERIAL'              => 'MAT-ACC-GSW',
                    'JUMLAH'                     => 1,
                    'LOKASI_REFERENSI'           => 'Span Tiang 1 - 12',
                    'INITIAL_OBSERVED_CONDITION' => 'GOOD',
                ],
                [
                    'SECTION_REF'                => 'SEC-CDR-001',
                    'JENIS_AKSESORIS'            => 'LA',
                    'KODE_MATERIAL'              => 'LA',
                    'JUMLAH'                     => 3,
                    'LOKASI_REFERENSI'           => 'Portal PB01',
                    'INITIAL_OBSERVED_CONDITION' => 'GOOD',
                ],
            ],
        ];
    }

    private function getSectionAId(): int
    {
        $db = \Config\Database::connect();
        $sec = $db->table('sections')->where('nama_section', 'Section A CANDRAMAS')->get()->getFirstRow('array');
        return $sec ? (int)$sec['id'] : 1;
    }

    // =========================================================================
    // 19 AUTOMATED ACCEPTANCE TESTS FOR CR-06F
    // =========================================================================

    public function testSectionScopedResolution(): void
    {
        $payload = $this->getValidTestPayload();
        $result = $this->ingestService->processStructuredPayload($payload);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['committed_sections']);

        $secId  = $this->getSectionAId();
        $active = $this->ncService->getActiveConfiguration($secId);
        $this->assertNotNull($active);
        $this->assertEquals($secId, (int)$active['section_id']);
        $this->assertEquals('SEC-CDR-001', $active['section_ref']);
    }

    public function testHonestEmptySectionReporting(): void
    {
        $db = \Config\Database::connect();
        $db->table('network_section_configurations')->truncate();

        $metrics = $this->ncService->getSectionCoverageMetrics();
        $this->assertEquals(0, $metrics['configured_sections']);
        $this->assertEquals(0.00, $metrics['coverage_pct']);
        $this->assertEquals('HONEST_EMPTY_STATE', $metrics['status']);
    }

    public function testConductorMixedSegmentsOrder(): void
    {
        $payload = $this->getValidTestPayload();
        $this->ingestService->processStructuredPayload($payload);

        $secId  = $this->getSectionAId();
        $active = $this->ncService->getActiveConfiguration($secId);
        $this->assertCount(2, $active['conductors']);
        $this->assertEquals(1, (int)$active['conductors'][0]['sequence_order']);
        $this->assertEquals('MAT-COND-AAACS-240', $active['conductors'][0]['material_code']);
        $this->assertEquals(2, (int)$active['conductors'][1]['sequence_order']);
        $this->assertEquals('MAT-COND-AAAC-150', $active['conductors'][1]['material_code']);
    }

    public function testRejectSegmentSequenceGap(): void
    {
        $payload = $this->getValidTestPayload();
        // Introduce sequence gap: 1, 3 (missing 2)
        $payload['CONDUCTOR_SEGMENTS'][1]['SEQUENCE_ORDER'] = 3;

        $result = $this->ingestService->processStructuredPayload($payload);
        $this->assertFalse($result['success']);
        $this->assertEquals('REJECTED', $result['status']);
        $this->assertStringContainsString('Gate F3A', implode(' ', $result['errors']));
    }

    public function testRejectDuplicateSequenceOrder(): void
    {
        $payload = $this->getValidTestPayload();
        // Introduce duplicate sequence: 1, 1
        $payload['CONDUCTOR_SEGMENTS'][1]['SEQUENCE_ORDER'] = 1;

        $result = $this->ingestService->processStructuredPayload($payload);
        $this->assertFalse($result['success']);
        $this->assertEquals('REJECTED', $result['status']);
        $this->assertStringContainsString('Duplikat SEQUENCE_ORDER', implode(' ', $result['errors']));
    }

    public function testAccessoryIndependentRegistration(): void
    {
        $payload = $this->getValidTestPayload();
        $this->ingestService->processStructuredPayload($payload);

        $secId  = $this->getSectionAId();
        $active = $this->ncService->getActiveConfiguration($secId);
        $this->assertCount(2, $active['accessories']);
        $types = array_column($active['accessories'], 'accessory_type');
        $this->assertContains('GSW', $types);
        $this->assertContains('LA', $types);
    }

    public function testAtomicVersionSupersedeOnActivation(): void
    {
        $secId = $this->getSectionAId();

        // Batch 1: Version 1 Active
        $payload1 = $this->getValidTestPayload();
        $res1 = $this->ingestService->processStructuredPayload($payload1);
        $this->assertTrue($res1['success']);

        $v1 = $this->ncService->getActiveConfiguration($secId);
        $this->assertEquals(1, (int)$v1['version_number']);
        $this->assertEquals('ACTIVE', $v1['verification_status']);
        $this->assertNull($v1['effective_to']);

        // Batch 2: Version 2 Active
        $payload2 = $this->getValidTestPayload();
        $payload2['SECTION_CONFIGURATIONS'][0]['SECTION_REF'] = 'SEC-CDR-001-V2';
        $payload2['CONDUCTOR_SEGMENTS'][0]['SECTION_REF']     = 'SEC-CDR-001-V2';
        $payload2['CONDUCTOR_SEGMENTS'][1]['SECTION_REF']     = 'SEC-CDR-001-V2';
        $payload2['NETWORK_ACCESSORIES'][0]['SECTION_REF']    = 'SEC-CDR-001-V2';
        $payload2['NETWORK_ACCESSORIES'][1]['SECTION_REF']    = 'SEC-CDR-001-V2';
        $res2 = $this->ingestService->processStructuredPayload($payload2);
        $this->assertTrue($res2['success']);

        // Assert v2 is ACTIVE, v1 is SUPERSEDED
        $v2 = $this->ncService->getActiveConfiguration($secId);
        $this->assertEquals(2, (int)$v2['version_number']);
        $this->assertEquals('ACTIVE', $v2['verification_status']);
        $this->assertNull($v2['effective_to']);

        $v1Reloaded = $this->ncService->getFullConfiguration((int)$v1['id']);
        $this->assertEquals('SUPERSEDED', $v1Reloaded['verification_status']);
        $this->assertNotNull($v1Reloaded['effective_to']);
    }

    public function testFindingDoesNotMutatePhysicalTopology(): void
    {
        $payload = $this->getValidTestPayload();
        $this->ingestService->processStructuredPayload($payload);

        $secId = $this->getSectionAId();
        $before = $this->ncService->getActiveConfiguration($secId);
        $conductorCountBefore = count($before['conductors']);

        // Simulated inspection finding (e.g. Broken LA)
        $db = \Config\Database::connect();
        $acc = $db->table('network_section_accessories')->where('accessory_type', 'LA')->get()->getFirstRow('array');
        if ($acc) {
            $db->table('network_section_accessories')->where('id', $acc['id'])->update(['condition_status' => 'DEFECTIVE']);
        }

        // Verify physical topology conductors remain untouched
        $after = $this->ncService->getActiveConfiguration($secId);
        $this->assertEquals($conductorCountBefore, count($after['conductors']));
        $this->assertEquals('MAT-COND-AAACS-240', $after['conductors'][0]['material_code']);
    }

    public function testDomainInvariantIxEquipmentRejection(): void
    {
        $payload = $this->getValidTestPayload();
        // Register illegal equipment as conductor
        $trafo = $this->ciService->registerMaterial([
            'material_code'   => 'MAT-ILLEGAL-TRAFO-100KVA',
            'nama_material'   => 'Trafo Distribusi 100 kVA',
            'material_domain' => 'TRAFO',
        ]);

        $payload['CONDUCTOR_SEGMENTS'][0]['KODE_MATERIAL_KONDUKTOR'] = 'MAT-ILLEGAL-TRAFO-100KVA';

        $result = $this->ingestService->processStructuredPayload($payload);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Domain Invariant IX Violation', implode(' ', $result['errors']));
    }

    public function testAllOrNothingRejectionOnSingleError(): void
    {
        $db = \Config\Database::connect();
        $initialConfigCount = $db->table('network_section_configurations')->countAllResults();

        $payload = $this->getValidTestPayload();
        // Inject invalid material on segment 2
        $payload['CONDUCTOR_SEGMENTS'][1]['KODE_MATERIAL_KONDUKTOR'] = 'MATERIAL_NON_EXISTENT_XYZ_999';

        $result = $this->ingestService->processStructuredPayload($payload);
        $this->assertFalse($result['success']);

        // Verify 0 database rows were inserted
        $afterCount = $db->table('network_section_configurations')->countAllResults();
        $this->assertEquals($initialConfigCount, $afterCount);
    }

    public function testRejectDuplicateSectionRefInBatch(): void
    {
        $payload = $this->getValidTestPayload();
        // Add duplicate SECTION_REF row
        $payload['SECTION_CONFIGURATIONS'][] = [
            'SECTION_REF'   => 'SEC-CDR-001', // Duplicate
            'KODE_ULP'      => '51301',
            'KODE_PENYULANG'=> 'CDR',
            'NAMA_SECTION'  => 'Section B CANDRAMAS',
        ];

        $result = $this->ingestService->processStructuredPayload($payload);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Duplikat SECTION_REF', implode(' ', $result['errors']));
    }

    public function testTopologyContinuityWhenNodesAreComplete(): void
    {
        $payload = $this->getValidTestPayload();
        // Segment 1: GI -> PB01, Segment 2: PB01 -> TM1 (Continuous)
        $payload['CONDUCTOR_SEGMENTS'][0]['START_NODE'] = 'GI';
        $payload['CONDUCTOR_SEGMENTS'][0]['END_NODE']   = 'PB01';
        $payload['CONDUCTOR_SEGMENTS'][1]['START_NODE'] = 'PB01';
        $payload['CONDUCTOR_SEGMENTS'][1]['END_NODE']   = 'TM1';

        $result = $this->ingestService->processStructuredPayload($payload);
        $this->assertTrue($result['success']);

        $secId  = $this->getSectionAId();
        $active = $this->ncService->getActiveConfiguration($secId);
        $this->assertEquals('VERIFIED', $active['topology_connectivity_status']);
    }

    public function testTopologyContinuityDiscontinuityRejection(): void
    {
        $payload = $this->getValidTestPayload();
        // Discontinuous: Segment 1 ends at PB01, Segment 2 starts at PB99
        $payload['CONDUCTOR_SEGMENTS'][0]['START_NODE'] = 'GI';
        $payload['CONDUCTOR_SEGMENTS'][0]['END_NODE']   = 'PB01';
        $payload['CONDUCTOR_SEGMENTS'][1]['START_NODE'] = 'PB99'; // Discontinuous!
        $payload['CONDUCTOR_SEGMENTS'][1]['END_NODE']   = 'TM1';

        $result = $this->ingestService->processStructuredPayload($payload);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Diskontinuitas topologi simpul', implode(' ', $result['errors']));
    }

    public function testImportBatchRollbackProvenance(): void
    {
        $payload = $this->getValidTestPayload();
        $payload['CONDUCTOR_SEGMENTS'][0]['PANJANG_METER'] = -50.0; // Invalid length

        $result = $this->ingestService->processStructuredPayload($payload);
        $this->assertFalse($result['success']);
        $this->assertEquals('REJECTED', $result['status']);

        $db = \Config\Database::connect();
        $batch = $db->table('network_configuration_import_batches')->where('id', $result['batch_id'])->get()->getRowArray();
        $this->assertNotNull($batch);
        $this->assertEquals('REJECTED', $batch['import_status']);
    }

    public function testAuditCr06fPassOnValidData(): void
    {
        $payload = $this->getValidTestPayload();
        $this->ingestService->processStructuredPayload($payload);

        $db = \Config\Database::connect();
        $multiActive = $db->table('network_section_configurations')
            ->select('section_id, COUNT(*) as active_count')
            ->where('verification_status', 'ACTIVE')
            ->where('effective_to IS NULL')
            ->groupBy('section_id')
            ->having('COUNT(*) > 1')
            ->get()
            ->getResultArray();

        $this->assertEmpty($multiActive);
    }

    public function testSectionRefUniquenessScopedToImportBatch(): void
    {
        // Batch 1 uses SEC-ALPHA
        $payload1 = $this->getValidTestPayload();
        $payload1['SECTION_CONFIGURATIONS'][0]['SECTION_REF'] = 'SEC-ALPHA';
        $payload1['CONDUCTOR_SEGMENTS'][0]['SECTION_REF']     = 'SEC-ALPHA';
        $payload1['CONDUCTOR_SEGMENTS'][1]['SECTION_REF']     = 'SEC-ALPHA';
        $payload1['NETWORK_ACCESSORIES'][0]['SECTION_REF']    = 'SEC-ALPHA';
        $payload1['NETWORK_ACCESSORIES'][1]['SECTION_REF']    = 'SEC-ALPHA';
        $res1 = $this->ingestService->processStructuredPayload($payload1);
        $this->assertTrue($res1['success']);

        // Batch 2 CAN safely reuse SEC-ALPHA because it is scoped to import batch
        $payload2 = $this->getValidTestPayload();
        $payload2['SECTION_CONFIGURATIONS'][0]['SECTION_REF'] = 'SEC-ALPHA';
        $payload2['CONDUCTOR_SEGMENTS'][0]['SECTION_REF']     = 'SEC-ALPHA';
        $payload2['CONDUCTOR_SEGMENTS'][1]['SECTION_REF']     = 'SEC-ALPHA';
        $payload2['NETWORK_ACCESSORIES'][0]['SECTION_REF']    = 'SEC-ALPHA';
        $payload2['NETWORK_ACCESSORIES'][1]['SECTION_REF']    = 'SEC-ALPHA';
        $res2 = $this->ingestService->processStructuredPayload($payload2);
        $this->assertTrue($res2['success']);
    }

    public function testCreateDraftDoesNotSupersedeActiveConfiguration(): void
    {
        $secId = $this->getSectionAId();

        // 1. Initial ACTIVE configuration
        $payload1 = $this->getValidTestPayload();
        $payload1['SECTION_CONFIGURATIONS'][0]['IMPORT_ACTION'] = 'ACTIVATE_NEW_VERSION';
        $this->ingestService->processStructuredPayload($payload1);

        $activeBefore = $this->ncService->getActiveConfiguration($secId);
        $this->assertNotNull($activeBefore);
        $this->assertEquals('ACTIVE', $activeBefore['verification_status']);
        $this->assertNull($activeBefore['effective_to']);

        // 2. Import second configuration as CREATE_DRAFT (Amendment F-02)
        $payloadDraft = $this->getValidTestPayload();
        $payloadDraft['SECTION_CONFIGURATIONS'][0]['SECTION_REF']   = 'SEC-CDR-DRAFT-01';
        $payloadDraft['SECTION_CONFIGURATIONS'][0]['IMPORT_ACTION'] = 'CREATE_DRAFT';
        $payloadDraft['CONDUCTOR_SEGMENTS'][0]['SECTION_REF']       = 'SEC-CDR-DRAFT-01';
        $payloadDraft['CONDUCTOR_SEGMENTS'][1]['SECTION_REF']       = 'SEC-CDR-DRAFT-01';
        $payloadDraft['NETWORK_ACCESSORIES'][0]['SECTION_REF']      = 'SEC-CDR-DRAFT-01';
        $payloadDraft['NETWORK_ACCESSORIES'][1]['SECTION_REF']      = 'SEC-CDR-DRAFT-01';
        $resDraft = $this->ingestService->processStructuredPayload($payloadDraft);
        $this->assertTrue($resDraft['success']);

        // 3. Verify active configuration was NOT superseded
        $activeAfter = $this->ncService->getActiveConfiguration($secId);
        $this->assertEquals($activeBefore['id'], $activeAfter['id']);
        $this->assertEquals('ACTIVE', $activeAfter['verification_status']);
        $this->assertNull($activeAfter['effective_to']);

        // 4. Verify DRAFT configuration exists
        $db = \Config\Database::connect();
        $draftCfg = $db->table('network_section_configurations')->where('section_ref', 'SEC-CDR-DRAFT-01')->get()->getFirstRow('array');
        $this->assertNotNull($draftCfg);
        $this->assertEquals('DRAFT', $draftCfg['verification_status']);
    }

    public function testPartialNodeDataProducesUnverifiedConnectivity(): void
    {
        $payload = $this->getValidTestPayload();
        // Partial nodes (START_NODE empty)
        $payload['CONDUCTOR_SEGMENTS'][0]['START_NODE'] = null;
        $payload['CONDUCTOR_SEGMENTS'][0]['END_NODE']   = 'PB01';
        $payload['CONDUCTOR_SEGMENTS'][1]['START_NODE'] = null;
        $payload['CONDUCTOR_SEGMENTS'][1]['END_NODE']   = null;

        $result = $this->ingestService->processStructuredPayload($payload);
        $this->assertTrue($result['success']);

        $secId  = $this->getSectionAId();
        $active = $this->ncService->getActiveConfiguration($secId);
        $this->assertEquals('UNVERIFIED', $active['topology_connectivity_status']);
    }

    public function testZeroCoveragePassesAsHonestEmptyState(): void
    {
        $db = \Config\Database::connect();
        $db->table('network_section_configurations')->truncate();

        $metrics = $this->ncService->getSectionCoverageMetrics();
        $this->assertEquals(0.00, $metrics['coverage_pct']);
        $this->assertEquals('HONEST_EMPTY_STATE', $metrics['status']);
    }
}
