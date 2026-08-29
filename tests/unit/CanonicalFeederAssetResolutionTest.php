<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\CanonicalFeederAssetResolutionService;
use App\Services\ConstructionAssetIntelligenceService;
use App\Services\NetworkConfigurationService;
use App\Database\Seeds\ConstructionIntelligenceSeeder;

/**
 * Unit Test Suite for Phase AR-01: Canonical Feeder–Asset Resolution (Contract AR-01)
 * Tests Invariants AR-01-A through AR-01-H.
 */
class CanonicalFeederAssetResolutionTest extends CIUnitTestCase
{
    protected CanonicalFeederAssetResolutionService $resolver;
    protected ConstructionAssetIntelligenceService $assetIntelService;
    protected NetworkConfigurationService $configService;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        $this->ensureTablesExist($forge, $db);

        $this->resolver          = new CanonicalFeederAssetResolutionService($db);
        $this->assetIntelService = new ConstructionAssetIntelligenceService($db);
        $this->configService     = new NetworkConfigurationService($db);

        // Seed baseline
        $seeder = new ConstructionIntelligenceSeeder(new \Config\Database());
        $seeder->run();
    }

    private function ensureTablesExist($forge, $db): void
    {
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

        // penyulang & db_penyulang
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
        if (!$db->tableExists('db_penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('db_penyulang', true);
            $db->table('db_penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        }

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
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
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
                'kode_section' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
            ]);
            $forge->createTable('sections', true);
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

        // asset_intelligence_snapshots
        if (!$db->tableExists('asset_intelligence_snapshots')) {
            $forge->addField([
                'id'                         => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'asset_id'                   => ['type' => 'INTEGER'],
                'ulp_id'                     => ['type' => 'INTEGER', 'null' => true],
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
                'component_code'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'defect_location_code' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'finding_fingerprint'  => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
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
                'tanggal_temuan'       => ['type' => 'DATE', 'null' => true],
                'tanggal_selesai'      => ['type' => 'DATE', 'null' => true],
                'first_detected_at'    => ['type' => 'DATETIME', 'null' => true],
                'last_observed_at'     => ['type' => 'DATETIME', 'null' => true],
                'observation_count'    => ['type' => 'INTEGER', 'default' => 1],
                'recurrence_count'     => ['type' => 'INTEGER', 'default' => 0],
                'foto'                 => ['type' => 'TEXT', 'null' => true],
                'foto_path'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'BELUM'],
                'status_temuan'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'BELUM_DITANGANI'],
                'created_by'           => ['type' => 'INTEGER', 'null' => true],
                'updated_by'           => ['type' => 'INTEGER', 'null' => true],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
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
                'health_score'                  => ['type' => 'DECIMAL', 'null' => true],
                'health_classification'         => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'UNRESOLVED'],
                'fhi_status'                    => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'UNRESOLVED'],
                'data_completeness_ratio'       => ['type' => 'DECIMAL', 'default' => 0.0000],
                'physical_coverage_ratio'       => ['type' => 'DECIMAL', 'default' => 0.0000],
                'asset_health_score'            => ['type' => 'DECIMAL', 'null' => true],
                'finding_severity_score'        => ['type' => 'DECIMAL', 'null' => true],
                'reliability_score'             => ['type' => 'DECIMAL', 'null' => true],
                'recurrence_score'              => ['type' => 'DECIMAL', 'null' => true],
                'primary_driver'                => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'primary_driver_score'          => ['type' => 'DECIMAL', 'null' => true],
                'assigned_unit'                 => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'priority_level'                => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'interruption_count'            => ['type' => 'INTEGER', 'default' => 0],
                'interruption_duration_minutes' => ['type' => 'DECIMAL', 'default' => 0.00],
                'critical_findings_count'       => ['type' => 'INTEGER', 'default' => 0],
                'recurring_findings_count'      => ['type' => 'INTEGER', 'default' => 0],
                'bom_degradation_score'         => ['type' => 'DECIMAL', 'default' => 0.00],
                'overload_events_count'         => ['type' => 'INTEGER', 'default' => 0],
                'fingerprint_json'              => ['type' => 'TEXT', 'null' => true],
                'explanation_json'              => ['type' => 'TEXT', 'null' => true],
                'advisory_narrative'            => ['type' => 'TEXT', 'null' => true],
                'calculated_at'                 => ['type' => 'DATETIME', 'null' => true],
                'created_at'                    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'                    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('feeder_health_classifications', true);
        }
    }

    public function testValidCanonicalChainResolvesToResolved(): void
    {
        $db = \Config\Database::connect();

        // 1. Create a fully valid active section on Feeder 1
        $db->table('sections')->insert([
            'id'             => 10,
            'penyulang_id'   => 1,
            'kode_section'   => 'SEC-TEST-VALID',
            'nama_section'   => 'Seksi Uji Valid',
            'status'         => 'ACTIVE',
        ]);

        // Ingest active configuration for section 10
        $cfg = $this->configService->createSectionConfiguration(10, [
            'verification_status' => 'ACTIVE',
            'conductors' => [
                [
                    'conductor_material_id' => 2,
                    'length_m'              => 1500.0,
                ],
            ],
            'accessories' => [
                [
                    'accessory_material_id' => 3,
                    'accessory_type'        => 'LA',
                    'quantity'              => 1,
                ],
            ],
        ]);

        // Create a valid asset with known construction type TM1
        $tm1 = $db->table('construction_types')->groupStart()->where('construction_code', 'TM1')->orWhere('construction_code', 'TM-1')->groupEnd()->get()->getFirstRow('array');
        $this->assertNotNull($tm1);

        $db->table('assets')->insert([
            'id'                   => 901,
            'kode_asset'           => 'AST-VALID-001',
            'nama_asset'           => 'Tiang 01 TM1 Valid',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 1,
            'section_id'           => 10,
            'construction_type_id' => $tm1['id'],
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $this->assertTrue($res['success']);

        $resolvedList = $res['breakdown'][CanonicalFeederAssetResolutionService::STATUS_RESOLVED];
        $assetIds = array_column($resolvedList, 'asset_id');

        $this->assertContains(901, $assetIds);
        $match = array_values(array_filter($resolvedList, fn($a) => $a['asset_id'] === 901))[0];
        $this->assertEquals('RESOLVED', $match['canonical_status']);
        $this->assertNotNull($match['cr06g_health']['asset_health_score']);
    }

    public function testMissingSectionFkYieldsPartial(): void
    {
        $db = \Config\Database::connect();

        // Asset with penyulang_id = 1, but section_id is NULL
        $db->table('assets')->insert([
            'id'                   => 902,
            'kode_asset'           => 'AST-NO-SEC-002',
            'nama_asset'           => 'Asset Tanpa Seksi',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 1,
            'section_id'           => null,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $partialList = $res['breakdown'][CanonicalFeederAssetResolutionService::STATUS_PARTIAL];
        $assetIds = array_column($partialList, 'asset_id');

        $this->assertContains(902, $assetIds);
    }

    public function testCrossFeederSectionConflictYieldsConflict(): void
    {
        $db = \Config\Database::connect();

        // Section 20 belongs to Feeder 2
        $db->table('sections')->insert([
            'id'             => 20,
            'penyulang_id'   => 2,
            'kode_section'   => 'SEC-FEEDER-2',
            'nama_section'   => 'Seksi Milik Feeder 2',
        ]);

        // Asset specifies penyulang_id = 1, but section_id = 20 (Conflict!)
        $db->table('assets')->insert([
            'id'                   => 903,
            'kode_asset'           => 'AST-CONFLICT-003',
            'nama_asset'           => 'Asset Konflik Feeder',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 1,
            'section_id'           => 20,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $conflictList = $res['breakdown'][CanonicalFeederAssetResolutionService::STATUS_CONFLICT];
        $assetIds = array_column($conflictList, 'asset_id');

        $this->assertContains(903, $assetIds);
        $this->assertGreaterThan(0, $res['governance']['cross_section_contaminations']);
    }

    public function testInactiveSectionYieldsUnresolved(): void
    {
        $db = \Config\Database::connect();

        // Section 30 belongs to Feeder 1, but has NO active configuration in CR-06F
        $db->table('sections')->insert([
            'id'             => 30,
            'penyulang_id'   => 1,
            'kode_section'   => 'SEC-UNCONFIGURED',
            'nama_section'   => 'Seksi Tanpa Konfigurasi Fisik',
        ]);

        $db->table('assets')->insert([
            'id'                   => 904,
            'kode_asset'           => 'AST-INACTIVE-SEC-004',
            'nama_asset'           => 'Asset Seksi Inaktif',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 1,
            'section_id'           => 30,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $unresolvedList = $res['breakdown'][CanonicalFeederAssetResolutionService::STATUS_UNRESOLVED];
        $assetIds = array_column($unresolvedList, 'asset_id');

        $this->assertContains(904, $assetIds);
    }

    public function testNonExistentSectionFkYieldsOrphan(): void
    {
        $db = \Config\Database::connect();

        // Asset specifies section_id = 9999 (Non-existent in DB)
        $db->table('assets')->insert([
            'id'                   => 905,
            'kode_asset'           => 'AST-ORPHAN-005',
            'nama_asset'           => 'Asset Yatim Piatu',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 1,
            'section_id'           => 9999,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $orphanList = $res['breakdown'][CanonicalFeederAssetResolutionService::STATUS_ORPHAN];
        $assetIds = array_column($orphanList, 'asset_id');

        $this->assertContains(905, $assetIds);
    }

    public function testZeroBlindAssignmentInvariantAR01A(): void
    {
        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $this->assertEquals(0, $res['governance']['blind_assignments']);
    }

    public function testDeterministicRepeatability(): void
    {
        $res1 = $this->resolver->analyzeFeederAssetResolution(1);
        $res2 = $this->resolver->analyzeFeederAssetResolution(1);

        $this->assertEquals($res1['inventory']['resolved_count'], $res2['inventory']['resolved_count']);
        $this->assertEquals($res1['inventory']['partial_count'], $res2['inventory']['partial_count']);
        $this->assertEquals($res1['inventory']['unresolved_count'], $res2['inventory']['unresolved_count']);
        $this->assertEquals($res1['inventory']['conflict_count'], $res2['inventory']['conflict_count']);
    }

    public function testAuditCommandExecution(): void
    {
        $result = command('audit:ar01 1');
        $this->assertNotNull($result);
    }

    public function testEmptyOrDuplicateAssetCodeFlagged(): void
    {
        $db = \Config\Database::connect();

        $db->table('assets')->insert([
            'id'                   => 906,
            'kode_asset'           => '',
            'nama_asset'           => 'Asset Tanpa Kode',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 1,
            'section_id'           => 1,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $orphanList = $res['breakdown'][CanonicalFeederAssetResolutionService::STATUS_ORPHAN];
        $assetIds = array_column($orphanList, 'asset_id');

        $this->assertContains(906, $assetIds);
    }

    public function testUnresolvedConstructionTypeYieldsUnresolved(): void
    {
        $db = \Config\Database::connect();

        // Section 10 is active from earlier test or we create section 11
        $db->table('sections')->insert([
            'id'             => 11,
            'penyulang_id'   => 1,
            'kode_section'   => 'SEC-TEST-CTYPE-UNRES',
            'nama_section'   => 'Seksi Ctype Unresolved',
            'status'         => 'ACTIVE',
        ]);
        $this->configService->createSectionConfiguration(11, [
            'verification_status' => 'ACTIVE',
            'conductors' => [
                ['conductor_material_id' => 2, 'length_m' => 1000.0],
            ],
        ]);

        // Asset specifies invalid / non-existent construction_type_id 9999
        $db->table('assets')->insert([
            'id'                   => 907,
            'kode_asset'           => 'AST-BAD-CTYPE-007',
            'nama_asset'           => 'Asset Konstruksi Rusak',
            'jenis_asset'          => 'TIANG_UNKNOWN_XYZ',
            'penyulang_id'         => 1,
            'section_id'           => 11,
            'construction_type_id' => 9999,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $unresolvedList = $res['breakdown'][CanonicalFeederAssetResolutionService::STATUS_UNRESOLVED];
        $assetIds = array_column($unresolvedList, 'asset_id');

        $this->assertContains(907, $assetIds);
    }

    public function testUpstreamImmutabilityZeroWritesDuringRecon(): void
    {
        $db = \Config\Database::connect();

        $assetCountBefore  = $db->table('assets')->countAllResults();
        $sectionCountBefore= $db->table('sections')->countAllResults();
        $configCountBefore = $db->table('network_section_configurations')->countAllResults();

        $this->resolver->analyzeFeederAssetResolution(1);

        $assetCountAfter   = $db->table('assets')->countAllResults();
        $sectionCountAfter = $db->table('sections')->countAllResults();
        $configCountAfter  = $db->table('network_section_configurations')->countAllResults();

        $this->assertEquals($assetCountBefore, $assetCountAfter);
        $this->assertEquals($sectionCountBefore, $sectionCountAfter);
        $this->assertEquals($configCountBefore, $configCountAfter);
    }

    public function testDirectCrossFeederAssetYieldsConflict(): void
    {
        $db = \Config\Database::connect();

        // Ensure Section 15 on Feeder 1 exists
        $db->table('sections')->insert([
            'id'           => 15,
            'penyulang_id' => 1,
            'nama_section' => 'Seksi Uji 15 Feeder 1',
        ]);

        // Asset belongs to feeder 2, but erroneously linked to section 15 of feeder 1
        $db->table('assets')->insert([
            'id'                   => 908,
            'kode_asset'           => 'AST-DIRECT-CONFLICT-008',
            'nama_asset'           => 'Asset Beda Feeder Tapi Nyasar Seksi',
            'jenis_asset'          => 'TIANG',
            'penyulang_id'         => 2, // Feeder 2
            'section_id'           => 15, // Section of Feeder 1
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $conflictList = $res['breakdown'][CanonicalFeederAssetResolutionService::STATUS_CONFLICT];
        $assetIds = array_column($conflictList, 'asset_id');

        $this->assertContains(908, $assetIds);
    }

    public function testFeederCandidateDiscoveryIntegrity(): void
    {
        $res = $this->resolver->analyzeFeederAssetResolution(1);
        $this->assertTrue($res['success']);
        $this->assertIsArray($res['inventory']);
        $this->assertArrayHasKey('feeder_candidate_assets', $res['inventory']);
        $this->assertArrayHasKey('resolved_count', $res['inventory']);
        $this->assertArrayHasKey('partial_count', $res['inventory']);
        $this->assertArrayHasKey('unresolved_count', $res['inventory']);
    }
}
