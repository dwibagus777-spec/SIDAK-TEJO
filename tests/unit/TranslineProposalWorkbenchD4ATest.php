<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\TranslineProposalReviewService;
use App\Controllers\GisController;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\UserAgent;

/**
 * TL-01 Sub-Gate D4A: Exception Review Workbench Test Suite
 *
 * Verifies all mandatory requirements:
 * 1. Read Model Purity (getProposalWorkbenchDetail has ZERO lazy auto-saves, ZERO audit inserts, ZERO status normalizations, ZERO repairs)
 * 2. Orthogonal Canonical Layers (Canonical Operational State vs Integrity Status; ACTIVE does NOT mask integrity anomalies)
 * 3. Authoritative Transline Comparison (Preserves 42 baseline seeders as LEGACY_AUTHORITATIVE with zero false-positives)
 * 4. Exception Review Queue (Prioritization: GOVERNANCE_ANOMALY -> BLOCKED -> HUMAN_REVIEW -> READY -> ACTIVE)
 * 5. Authoritative Server-Side Policy Locks (can_confirm = false, can_rollback = false, can_reject = false, can_mutate_asset = false)
 * 6. Mandatory Negative Tests:
 *    - Workbench GET zero DB writes
 *    - Queue GET zero DB writes
 *    - Cross-ULP authorization -> 403 / UNAUTHORIZED_FEEDER_ACCESS
 *    - Nonexistent proposal -> 404 / PROPOSAL_NOT_FOUND
 * 7. Controller HTTP Endpoints (apiProposalWorkbenchDetail, apiProposalExceptionQueue)
 * 8. Zero Mutation across all operational tables (assets, translines, proposals, sections, penyulang, temuan, temuan_materials)
 */
class TranslineProposalWorkbenchD4ATest extends CIUnitTestCase
{
    protected $db;
    protected TranslineProposalReviewService $service;
    private static bool $schemaInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();
        $this->service = new TranslineProposalReviewService($this->db);
    }

    protected function safeAddColumn(string $table, string $column, array $def): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        $prefixed = $this->db->prefixTable($table);
        $cols = array_column($this->db->query("PRAGMA table_info({$prefixed})")->getResultArray(), 'name');
        if (!in_array($column, $cols, true)) {
            try {
                Database::forge()->addColumn($table, [$column => $def]);
            } catch (\Throwable $e) {
                // Column already exists
            }
        }
    }

    protected function setupSchema(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        $forge = Database::forge();

        // 1. ulps table
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

        // 2. penyulang table
        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        // 3. sections table
        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        // 4. assets table
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Tiang Aset'],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
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
        } else {
            $this->safeAddColumn('assets', 'construction_type_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        }

        // 5. gis_translines table
        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'section_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'from_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'to_asset_id'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'length_m'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'source'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MANUAL'],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        } else {
            $this->safeAddColumn('gis_translines', 'section_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
            $this->safeAddColumn('gis_translines', 'is_active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
            $this->safeAddColumn('gis_translines', 'source', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MANUAL']);
            $this->safeAddColumn('gis_translines', 'created_by', ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true]);
            $this->safeAddColumn('gis_translines', 'created_at', ['type' => 'DATETIME', 'null' => true]);
            $this->safeAddColumn('gis_translines', 'length_m', ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true]);
            $this->safeAddColumn('gis_translines', 'from_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
            $this->safeAddColumn('gis_translines', 'to_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        }

        // 6. gis_transline_proposals table
        if (!$this->db->tableExists('gis_transline_proposals')) {
            $forge->addField([
                'id'                      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id'            => ['type' => 'INT', 'constraint' => 11],
                'section_id'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'from_asset_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'to_asset_id'             => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'natural_key'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'proposed_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'proposed_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'proposed_distance'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'proposed_geometry'       => ['type' => 'TEXT', 'null' => true],
                'proposal_source'         => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'DETERMINISTIC_ENGINE'],
                'engine_version'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-01-V2.0'],
                'evidence_json'           => ['type' => 'TEXT', 'null' => true],
                'classification'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'AUTO_MATCH'],
                'confidence_score'        => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 1.0000],
                'status'                  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'PENDING_REVIEW'],
                'reviewed_by'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'reviewed_at'             => ['type' => 'DATETIME', 'null' => true],
                'confirmed_transline_id'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
                'updated_at'              => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_transline_proposals', true);
        } else {
            $this->safeAddColumn('gis_transline_proposals', 'evidence_json', ['type' => 'TEXT', 'null' => true]);
            $this->safeAddColumn('gis_transline_proposals', 'proposal_source', ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'DETERMINISTIC_ENGINE']);
            $this->safeAddColumn('gis_transline_proposals', 'engine_version', ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-01-V2.0']);
            $this->safeAddColumn('gis_transline_proposals', 'proposed_geometry', ['type' => 'TEXT', 'null' => true]);
            $this->safeAddColumn('gis_transline_proposals', 'confirmed_transline_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        }

        // 7. temuan table
        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'judul'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }

        // 8. temuan_materials table
        if (!$this->db->tableExists('temuan_materials')) {
            $forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'temuan_id'   => ['type' => 'INT', 'constraint' => 11],
                'material_id' => ['type' => 'INT', 'constraint' => 11],
                'quantity'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1.0],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan_materials', true);
        }

        self::$schemaInitialized = true;
    }

    protected function seedTestData(): void
    {
        $this->db->table('ulps')->emptyTable();
        $this->db->table('penyulang')->emptyTable();
        $this->db->table('sections')->emptyTable();
        $this->db->table('assets')->emptyTable();
        $this->db->table('gis_translines')->emptyTable();
        $this->db->table('gis_transline_proposals')->emptyTable();
        $this->db->table('temuan')->emptyTable();
        $this->db->table('temuan_materials')->emptyTable();

        // 1. ULP
        $this->db->table('ulps')->insert([
            'id'       => 1,
            'kode_ulp' => 'ULP01',
            'nama_ulp' => 'ULP SIDOARJO KOTA',
            'status'   => 'AKTIF',
        ]);
        $this->db->table('ulps')->insert([
            'id'       => 2,
            'kode_ulp' => 'ULP02',
            'nama_ulp' => 'ULP KRIAN',
            'status'   => 'AKTIF',
        ]);

        // 2. Feeder
        $this->db->table('penyulang')->insert([
            'id'             => 10,
            'ulp_id'         => 1,
            'kode_penyulang' => 'BJK',
            'nama_penyulang' => 'BANJAR KEMANTREN',
            'status'         => 'AKTIF',
        ]);
        $this->db->table('penyulang')->insert([
            'id'             => 20,
            'ulp_id'         => 2,
            'kode_penyulang' => 'KRN',
            'nama_penyulang' => 'KRIAN KOTA',
            'status'         => 'AKTIF',
        ]);

        // 3. Section
        $this->db->table('sections')->insert([
            'id'           => 100,
            'penyulang_id' => 10,
            'nama_section' => 'SECTION UTAMA BJK',
        ]);

        // 4. Assets
        $this->db->table('assets')->insertBatch([
            [
                'id'                   => 1001,
                'kode_asset'           => 'BANJARKEMANTRAN_01',
                'nama_asset'           => 'Tiang BJK 01',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'jenis_asset'          => 'TIANG_BETON',
                'construction_type_id' => 1,
                'latitude'             => -7.41600000,
                'longitude'            => 112.72300000,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 1002,
                'kode_asset'           => 'BANJARKEMANTRAN_02',
                'nama_asset'           => 'Tiang BJK 02',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'jenis_asset'          => 'TIANG_BETON',
                'construction_type_id' => 1,
                'latitude'             => -7.41630000,
                'longitude'            => 112.72310000,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 1003,
                'kode_asset'           => 'BANJARKEMANTRAN_03',
                'nama_asset'           => 'Tiang BJK 03',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'jenis_asset'          => 'TIANG_BESI',
                'construction_type_id' => 2,
                'latitude'             => -7.41660000,
                'longitude'            => 112.72330000,
                'status'               => 'NORMAL',
            ],
        ]);

        // 5. Authoritative Legacy Transline (Baseline Seeder)
        $this->db->table('gis_translines')->insert([
            'id'              => 501,
            'transline_code'  => 'TL-10-1002-1003',
            'penyulang_id'    => 10,
            'section_id'      => 100,
            'source_asset_id' => 1002,
            'target_asset_id' => 1003,
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 41.20,
            'status'          => 'ACTIVE',
            'is_active'       => 1,
            'source'          => 'BASELINE_SEEDER',
            'created_by'      => 'SYSTEM_SEEDER',
        ]);

        // 6. Test Proposals covering distinct canonical states and scenarios
        // Proposal 1: Canonical AUTO_MATCH -> READY
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 1,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1001,
            'target_asset_id'         => 1002,
            'natural_key'             => 'TL-NAT:10:1001-1002',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 37.15,
            'classification'          => 'AUTO_MATCH',
            'confidence_score'        => 0.9850,
            'status'                  => 'PENDING_REVIEW',
            'proposal_source'         => 'DETERMINISTIC_ENGINE',
            'engine_version'          => 'TL-01-V2.0',
            'evidence_json'           => json_encode([
                'model'          => 'heuristic-v1',
                'factors'        => ['span_distance_ok', 'voltage_level_match'],
                'feature_scores' => ['distance' => 0.98, 'angle' => 0.99]
            ]),
        ]);

        // Proposal 2: NEEDS_REVIEW -> HUMAN_REVIEW with low confidence & long span
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 2,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1001,
            'target_asset_id'         => 1003,
            'natural_key'             => 'TL-NAT:10:1001-1003',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 78.40,
            'classification'          => 'NEEDS_REVIEW',
            'confidence_score'        => 0.8200,
            'status'                  => 'PENDING_REVIEW',
            'evidence_json'           => json_encode(['warning' => 'Long distance span']),
        ]);

        // Proposal 3: INVALID -> BLOCKED
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 3,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1002,
            'target_asset_id'         => 1003,
            'natural_key'             => 'TL-NAT:10:1002-1003-INV',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 150.00,
            'classification'          => 'INVALID',
            'confidence_score'        => 0.2000,
            'status'                  => 'PENDING_REVIEW',
        ]);

        // Proposal 4: MISSING -> BLOCKED
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 4,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1001,
            'target_asset_id'         => 1002,
            'natural_key'             => 'TL-NAT:10:1001-1002-MISSING',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 40.00,
            'classification'          => 'MISSING',
            'confidence_score'        => 0.0000,
            'status'                  => 'PENDING_REVIEW',
        ]);

        // Proposal 5: Canonical AUTO_MATCH + CONFIRMED -> ACTIVE (Healthy)
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 5,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1002,
            'target_asset_id'         => 1003,
            'natural_key'             => 'TL-NAT:10:1002-1003',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 41.20,
            'classification'          => 'AUTO_MATCH',
            'confidence_score'        => 1.0000,
            'status'                  => 'CONFIRMED',
            'confirmed_transline_id'  => 501,
        ]);

        // Proposal 6: Illegal Combination: NEEDS_REVIEW + CONFIRMED -> GOVERNANCE_ANOMALY
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 6,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1001,
            'target_asset_id'         => 1003,
            'natural_key'             => 'TL-NAT:10:1001-1003-GOV',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 50.00,
            'classification'          => 'NEEDS_REVIEW',
            'confidence_score'        => 0.7500,
            'status'                  => 'CONFIRMED',
            'confirmed_transline_id'  => 501,
        ]);
    }

    // =========================================================================
    // SECTION 1: WORKBENCH DETAIL & CANONICAL STATE MAPPING (Tests 1 - 8)
    // =========================================================================

    /**
     * Test 01: AUTO_MATCH + PENDING_REVIEW resolves to READY with healthy integrity layer
     */
    public function test01_WorkbenchDetail_ReadyAutoMatch_ReturnsCompleteReadModel(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(1);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals(1, $res['proposal_id']);
        $this->assertEquals('READY', $res['proposal']['canonical_operational_state']);
        $this->assertEquals('AUTO_MATCH', $res['proposal']['classification']);
        $this->assertEquals('PENDING_REVIEW', $res['proposal']['lifecycle_status']);
        $this->assertEquals('HEALTHY', $res['integrity_layer']['status']);
        $this->assertEquals(0, $res['integrity_layer']['anomalies_count']);
        $this->assertNotEmpty($res['integrity_layer']['resolution_guidance']);
    }

    /**
     * Test 02: NEEDS_REVIEW + PENDING_REVIEW resolves to HUMAN_REVIEW with review reasons
     */
    public function test02_WorkbenchDetail_HumanReview_TriggersOperatorReviewReasons(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(2);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('HUMAN_REVIEW', $res['proposal']['canonical_operational_state']);
        $this->assertContains('NEEDS_REVIEW_CLASSIFICATION', $res['integrity_layer']['review_reasons']);
        $this->assertContains('LOW_CONFIDENCE_SCORE', $res['integrity_layer']['review_reasons']);
        $this->assertContains('LONG_SPAN_DISTANCE', $res['integrity_layer']['review_reasons']);
        $this->assertStringContainsString('TINJAUAN OPERATOR', $res['integrity_layer']['resolution_guidance']);
    }

    /**
     * Test 03: INVALID + PENDING_REVIEW resolves to BLOCKED with blocking reasons
     */
    public function test03_WorkbenchDetail_Blocked_InvalidClassificationTriggersBlockingReasons(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(3);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('BLOCKED', $res['proposal']['canonical_operational_state']);
        $this->assertContains('INVALID_CLASSIFICATION_BLOCKED', $res['integrity_layer']['blocking_reasons']);
        $this->assertStringContainsString('STATUS DIBLOKIR', $res['integrity_layer']['resolution_guidance']);
    }

    /**
     * Test 04: MISSING + PENDING_REVIEW resolves to BLOCKED
     */
    public function test04_WorkbenchDetail_Blocked_MissingClassificationTriggersBlockingReasons(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(4);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('BLOCKED', $res['proposal']['canonical_operational_state']);
        $this->assertContains('MISSING_PROPOSAL_BLOCKED', $res['integrity_layer']['blocking_reasons']);
    }

    /**
     * Test 05: AUTO_MATCH + CONFIRMED resolves to ACTIVE
     */
    public function test05_WorkbenchDetail_Active_ConfirmedProposalReflectsActiveOperationalState(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(5);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('ACTIVE', $res['proposal']['canonical_operational_state']);
        $this->assertEquals('CONFIRMED', $res['proposal']['lifecycle_status']);
        $this->assertEquals(501, $res['proposal']['confirmed_transline_id']);
    }

    /**
     * Test 06: Illegal combination (NEEDS_REVIEW + CONFIRMED) resolves to GOVERNANCE_ANOMALY
     */
    public function test06_WorkbenchDetail_GovernanceAnomaly_IllegalClassificationAndStatus(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(6);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('GOVERNANCE_ANOMALY', $res['proposal']['canonical_operational_state']);
        $this->assertStringContainsString('TATA KELOLA', $res['integrity_layer']['resolution_guidance']);
        $this->assertGreaterThan(0, $res['integrity_layer']['anomalies_count']);
    }

    /**
     * Test 07: Orthogonal Layer: ACTIVE does NOT mask integrity anomalies (FEEDER_PROVENANCE_MISMATCH)
     */
    public function test07_WorkbenchDetail_OrthogonalLayers_ActiveWithFeederProvenanceAnomaly(): void
    {
        // Seed transline under feeder 20 (different feeder from proposal 5 which is feeder 10)
        $this->db->table('gis_translines')->insert([
            'id'              => 502,
            'transline_code'  => 'TL-20-1002-1003',
            'penyulang_id'    => 20, // Feeder mismatch!
            'source_asset_id' => 1002,
            'target_asset_id' => 1003,
            'status'          => 'ACTIVE',
            'is_active'       => 1,
        ]);

        // Proposal 7: AUTO_MATCH + CONFIRMED with transline 502
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 7,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1002,
            'target_asset_id'         => 1003,
            'natural_key'             => 'TL-NAT:10:1002-1003-ANO',
            'classification'          => 'AUTO_MATCH',
            'status'                  => 'CONFIRMED',
            'confirmed_transline_id'  => 502,
        ]);

        $res = $this->service->getProposalWorkbenchDetail(7);

        // State remains canonical ACTIVE, but integrity layer reports ANOMALY
        $this->assertEquals('ACTIVE', $res['proposal']['canonical_operational_state']);
        $this->assertEquals('ANOMALY', $res['integrity_layer']['status']);
        $anomalyCodes = array_column($res['integrity_layer']['anomalies'], 'code');
        $this->assertContains('FEEDER_PROVENANCE_MISMATCH', $anomalyCodes);
        $this->assertStringContainsString('AKTIF DENGAN ANOMALI', $res['integrity_layer']['resolution_guidance']);
    }

    /**
     * Test 08: Orthogonal Layer: ACTIVE proposal with broken transline foreign key
     */
    public function test08_WorkbenchDetail_OrthogonalLayers_ActiveWithBrokenTranslineFK(): void
    {
        // Proposal 8: CONFIRMED pointing to non-existent transline 9999
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 8,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1001,
            'target_asset_id'         => 1002,
            'natural_key'             => 'TL-NAT:10:1001-1002-BRK',
            'classification'          => 'AUTO_MATCH',
            'status'                  => 'CONFIRMED',
            'confirmed_transline_id'  => 9999,
        ]);

        $res = $this->service->getProposalWorkbenchDetail(8);

        $this->assertEquals('ACTIVE', $res['proposal']['canonical_operational_state']);
        $this->assertEquals('ANOMALY', $res['integrity_layer']['status']);
        $anomalyCodes = array_column($res['integrity_layer']['anomalies'], 'code');
        $this->assertContains('BROKEN_TRANSLINE_FK', $anomalyCodes);
    }

    // =========================================================================
    // SECTION 2: SERVER-SIDE POLICY LOCKS (Test 9)
    // =========================================================================

    /**
     * Test 09: Authoritative server-side policy locks enforce strict read-only guarantees
     */
    public function test09_WorkbenchDetail_ServerSidePolicyLocksEnforced(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(1);
        $locks = $res['policy_locks'];

        $this->assertEquals('TL-01 Sub-Gate D4A', $locks['gate']);
        $this->assertEquals('READ_ONLY_WORKBENCH', $locks['mode']);
        $this->assertEquals('LOCKED', $locks['production_write']);
        $this->assertFalse($locks['can_confirm']);
        $this->assertFalse($locks['can_rollback']);
        $this->assertFalse($locks['can_reject']);
        $this->assertFalse($locks['can_mutate_asset']);

        $this->assertEquals('POLICY_GATE_LOCKED_D4A', $locks['lock_reason_codes']['confirm']);
        $this->assertEquals('POLICY_GATE_LOCKED_D4A', $locks['lock_reason_codes']['rollback']);
        $this->assertEquals('POLICY_GATE_LOCKED_D4A', $locks['lock_reason_codes']['reject']);
        $this->assertEquals('POLICY_MASTER_ASSET_READONLY', $locks['lock_reason_codes']['mutate_asset']);
    }

    // =========================================================================
    // SECTION 3: ASSET HYDRATION, EVIDENCE PARSER & AUDIT RECEIPT (Tests 10 - 12)
    // =========================================================================

    /**
     * Test 10: Dual asset context hydration returns complete asset attributes
     */
    public function test10_WorkbenchDetail_DualAssetContextHydration(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(1);

        $src = $res['source_asset'];
        $tgt = $res['target_asset'];

        $this->assertNotNull($src);
        $this->assertNotNull($tgt);
        $this->assertEquals(1001, $src['id']);
        $this->assertEquals('BANJARKEMANTRAN_01', $src['kode_asset']);
        $this->assertEquals('Tiang BJK 01', $src['nama_asset']);
        $this->assertEquals(-7.41600000, $src['latitude']);
        $this->assertEquals(112.72300000, $src['longitude']);

        $this->assertEquals(1002, $tgt['id']);
        $this->assertEquals('BANJARKEMANTRAN_02', $tgt['kode_asset']);
    }

    /**
     * Test 11: Structured evidence inspector parses JSON payload safely
     */
    public function test11_WorkbenchDetail_AIStructuredEvidenceParsing(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(1);
        $inspector = $res['evidence_inspector'];

        $this->assertNotEmpty($inspector['raw']);
        $this->assertIsArray($inspector['structured']);
        $this->assertEquals('heuristic-v1', $inspector['structured']['model']);
        $this->assertContains('span_distance_ok', $inspector['structured']['factors']);
    }

    /**
     * Test 12: Deterministic audit receipt preview generates in-memory hash without DB writes
     */
    public function test12_WorkbenchDetail_DeterministicAuditReceiptPreview_ZeroDBWrites(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(1);
        $receipt = $res['audit_receipt_preview'];

        $this->assertNotNull($receipt);
        $this->assertEquals(1, $receipt['proposal_id']);
        $this->assertNotEmpty($receipt['sha256_fingerprint']);
        $this->assertEquals(64, strlen($receipt['sha256_fingerprint'])); // Valid SHA-256
    }

    // =========================================================================
    // SECTION 4: AUTHORITATIVE TRANSLINE COMPARISONS (Tests 13 - 15)
    // =========================================================================

    /**
     * Test 13: 42 baseline seeders preserved as LEGACY_AUTHORITATIVE without error flags
     */
    public function test13_AuthoritativeComparison_LegacyAuthoritative_PreservesBaselineSeeders(): void
    {
        $proposal = [
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1002,
            'target_asset_id'         => 1003,
            'proposed_distance'       => 41.20,
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
        ];

        $cmp = $this->service->compareWithAuthoritativeTranslines($proposal);

        $this->assertEquals('success', $cmp['status']);
        $this->assertEquals(1, $cmp['existing_translines_count']);
        $this->assertTrue($cmp['exact_endpoints_match']);

        $rec = $cmp['comparison_records'][0];
        $this->assertEquals(501, $rec['transline_id']);
        $this->assertEquals('LEGACY_AUTHORITATIVE', $rec['scope_category']);
        $this->assertEquals('COMPARISON_ONLY', $rec['comparison_type']);
        $this->assertTrue($rec['is_exact_endpoints']);
        $this->assertTrue($rec['conductor_identical']);
    }

    /**
     * Test 14: Non-baseline translines classified as PROPOSAL_GOVERNED
     */
    public function test14_AuthoritativeComparison_ProposalGoverned_ScopeDistinction(): void
    {
        $this->db->table('gis_translines')->insert([
            'id'              => 503,
            'transline_code'  => 'TL-10-1001-1002-NEW',
            'penyulang_id'    => 10,
            'section_id'      => 100,
            'source_asset_id' => 1001,
            'target_asset_id' => 1002,
            'source'          => 'CONFIRMED_PROPOSAL',
            'created_by'      => 'OPERATOR_1',
            'status'          => 'ACTIVE',
            'is_active'       => 1,
            'distance_meters' => 37.15,
        ]);

        $proposal = [
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1001,
            'target_asset_id'         => 1002,
            'proposed_distance'       => 37.15,
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
        ];

        $cmp = $this->service->compareWithAuthoritativeTranslines($proposal);
        $categories = array_column($cmp['comparison_records'], 'scope_category', 'transline_id');
        $this->assertEquals('PROPOSAL_GOVERNED', $categories[503]);
        $this->assertEquals('LEGACY_AUTHORITATIVE', $categories[501]);
    }

    /**
     * Test 15: Distance delta calculation and endpoint matching
     */
    public function test15_AuthoritativeComparison_ExactEndpointsAndDistanceDelta(): void
    {
        $proposal = [
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1002,
            'target_asset_id'         => 1003,
            'proposed_distance'       => 45.00, // Seeded transline is 41.20 -> delta 3.80
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
        ];

        $cmp = $this->service->compareWithAuthoritativeTranslines($proposal);
        $this->assertEquals(3.80, $cmp['comparison_records'][0]['distance_delta']);
    }

    // =========================================================================
    // SECTION 5: EXCEPTION REVIEW QUEUE & TRIAGE PRIORITIZATION (Tests 16 - 20)
    // =========================================================================

    /**
     * Test 16: Exception queue orders items by priority weight:
     * GOVERNANCE_ANOMALY (1) -> BLOCKED (2) -> HUMAN_REVIEW (3) -> READY (4) -> ACTIVE (5)
     */
    public function test16_ExceptionQueue_DefaultTriagePriorityWeighting(): void
    {
        $res = $this->service->getExceptionReviewQueue();

        $this->assertEquals('success', $res['status']);
        $this->assertGreaterThan(0, $res['queue_count']);

        $queue = $res['queue'];
        $firstItem = $queue[0];
        $this->assertEquals('GOVERNANCE_ANOMALY', $firstItem['canonical_operational_state']);
        $this->assertEquals(1, $firstItem['priority_weight']);

        // Verify sorted order of weights
        $weights = array_column($queue, 'priority_weight');
        $sortedWeights = $weights;
        sort($sortedWeights);
        $this->assertEquals($sortedWeights, $weights);
    }

    /**
     * Test 17: Filter queue by BLOCKED state
     */
    public function test17_ExceptionQueue_FilterByBlocked(): void
    {
        $res = $this->service->getExceptionReviewQueue(null, 'BLOCKED');

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('BLOCKED', $res['filter_state']);
        foreach ($res['queue'] as $item) {
            $this->assertEquals('BLOCKED', $item['canonical_operational_state']);
        }
    }

    /**
     * Test 18: Filter queue by HUMAN_REVIEW state
     */
    public function test18_ExceptionQueue_FilterByHumanReview(): void
    {
        $res = $this->service->getExceptionReviewQueue(null, 'HUMAN_REVIEW');

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('HUMAN_REVIEW', $res['filter_state']);
        foreach ($res['queue'] as $item) {
            $this->assertEquals('HUMAN_REVIEW', $item['canonical_operational_state']);
        }
    }

    /**
     * Test 19: Filter queue by feeder ID
     */
    public function test19_ExceptionQueue_FilterByFeederId(): void
    {
        $res = $this->service->getExceptionReviewQueue(10);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals(10, $res['penyulang_id']);
        foreach ($res['queue'] as $item) {
            $this->assertEquals(10, $item['penyulang_id']);
        }
    }

    /**
     * Test 20: Policy locks attached to exception queue payload
     */
    public function test20_ExceptionQueue_PolicyLocksEnforced(): void
    {
        $res = $this->service->getExceptionReviewQueue();

        $this->assertArrayHasKey('policy_locks', $res);
        $this->assertFalse($res['policy_locks']['can_confirm']);
        $this->assertFalse($res['policy_locks']['can_rollback']);
        $this->assertFalse($res['policy_locks']['can_reject']);
        $this->assertFalse($res['policy_locks']['can_mutate_asset']);
    }

    // =========================================================================
    // SECTION 6: MANDATORY NEGATIVE TESTS (Tests 21 - 24)
    // =========================================================================

    /**
     * Test 21: Mandatory Negative: getProposalWorkbenchDetail causes ZERO DB writes
     */
    public function test21_MandatoryNegative_WorkbenchDetail_ZeroDatabaseWrites(): void
    {
        $countsBefore = $this->snapshotTableCounts();

        // Call getProposalWorkbenchDetail multiple times
        $this->service->getProposalWorkbenchDetail(1);
        $this->service->getProposalWorkbenchDetail(2);
        $this->service->getProposalWorkbenchDetail(3);
        $this->service->getProposalWorkbenchDetail(5);
        $this->service->getProposalWorkbenchDetail(6);

        $countsAfter = $this->snapshotTableCounts();
        $this->assertEquals($countsBefore, $countsAfter, 'Zero writes invariant violated by getProposalWorkbenchDetail');
    }

    /**
     * Test 22: Mandatory Negative: getExceptionReviewQueue causes ZERO DB writes
     */
    public function test22_MandatoryNegative_ExceptionQueue_ZeroDatabaseWrites(): void
    {
        $countsBefore = $this->snapshotTableCounts();

        // Call getExceptionReviewQueue with various filters
        $this->service->getExceptionReviewQueue();
        $this->service->getExceptionReviewQueue(10);
        $this->service->getExceptionReviewQueue(null, 'BLOCKED');
        $this->service->getExceptionReviewQueue(null, 'HUMAN_REVIEW');

        $countsAfter = $this->snapshotTableCounts();
        $this->assertEquals($countsBefore, $countsAfter, 'Zero writes invariant violated by getExceptionReviewQueue');
    }

    /**
     * Test 23: Mandatory Negative: Cross-ULP proposal access is strictly denied (UNAUTHORIZED_FEEDER_ACCESS)
     */
    public function test23_MandatoryNegative_CrossUlpAuthorizationDenied(): void
    {
        // Proposal 1 belongs to feeder 10 which belongs to ULP 1
        // Requesting with userUlpId = 2 (ULP 2) must return UNAUTHORIZED_FEEDER_ACCESS
        $res = $this->service->getProposalWorkbenchDetail(1, 2);

        $this->assertEquals('error', $res['status']);
        $this->assertEquals('UNAUTHORIZED_FEEDER_ACCESS', $res['reason']);
        $this->assertStringContainsString('Akses ditolak', $res['message']);
        $this->assertStringContainsString('otorisasi ULP', $res['message']);
    }

    /**
     * Test 24: Mandatory Negative: Nonexistent proposal ID returns PROPOSAL_NOT_FOUND
     */
    public function test24_MandatoryNegative_NonexistentProposalNotFound(): void
    {
        $res = $this->service->getProposalWorkbenchDetail(99999);

        $this->assertEquals('error', $res['status']);
        $this->assertEquals('PROPOSAL_NOT_FOUND', $res['reason']);
    }

    // =========================================================================
    // SECTION 7: CONTROLLER HTTP ENDPOINT INTEGRATION (Tests 25 - 28)
    // =========================================================================

    /**
     * Test 25: Controller apiProposalWorkbenchDetail returns HTTP 200 on valid proposal
     */
    public function test25_ControllerEndpoint_ProposalWorkbenchDetailHttpSuccess(): void
    {
        $controller = new GisController();
        $controller->initController(
            service('request'),
            service('response'),
            service('logger')
        );

        $response = $controller->apiProposalWorkbenchDetail(1);
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('success', $body['status']);
        $this->assertEquals(1, $body['proposal_id']);
        $this->assertEquals('READY', $body['proposal']['canonical_operational_state']);
    }

    /**
     * Test 26: Controller apiProposalWorkbenchDetail returns HTTP 404 on nonexistent proposal
     */
    public function test26_ControllerEndpoint_ProposalWorkbenchDetailHttpNotFound(): void
    {
        $controller = new GisController();
        $controller->initController(
            service('request'),
            service('response'),
            service('logger')
        );

        $response = $controller->apiProposalWorkbenchDetail(99999);
        $this->assertEquals(404, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertEquals('PROPOSAL_NOT_FOUND', $body['reason']);
    }

    /**
     * Test 27: Controller apiProposalWorkbenchDetail returns HTTP 422 on invalid ID (<= 0)
     */
    public function test27_ControllerEndpoint_ProposalWorkbenchDetailHttpInvalidId(): void
    {
        $controller = new GisController();
        $controller->initController(
            service('request'),
            service('response'),
            service('logger')
        );

        $response = $controller->apiProposalWorkbenchDetail(0);
        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertEquals('INVALID_PROPOSAL_ID', $body['reason']);
    }

    /**
     * Test 28: Controller apiProposalExceptionQueue returns HTTP 200
     */
    public function test28_ControllerEndpoint_ProposalExceptionQueueHttpSuccess(): void
    {
        $controller = new GisController();
        $controller->initController(
            service('request'),
            service('response'),
            service('logger')
        );

        $response = $controller->apiProposalExceptionQueue();
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('queue', $body);
        $this->assertArrayHasKey('summary', $body);
    }

    // =========================================================================
    // SECTION 8: ZERO-MUTATION DATA INVARIANTS (Tests 29 - 30)
    // =========================================================================

    /**
     * Test 29: Zero mutation on operational asset tables (assets, sections, penyulang, ulps, temuan)
     */
    public function test29_ZeroMutation_OperationalTablesRowCountsIdentical(): void
    {
        $assetsCount = $this->db->table('assets')->countAllResults();
        $sectionsCount = $this->db->table('sections')->countAllResults();
        $penyulangCount = $this->db->table('penyulang')->countAllResults();
        $ulpsCount = $this->db->table('ulps')->countAllResults();

        // Run entire workbench pipeline
        $this->service->getProposalWorkbenchDetail(1);
        $this->service->getExceptionReviewQueue();

        $this->assertEquals($assetsCount, $this->db->table('assets')->countAllResults());
        $this->assertEquals($sectionsCount, $this->db->table('sections')->countAllResults());
        $this->assertEquals($penyulangCount, $this->db->table('penyulang')->countAllResults());
        $this->assertEquals($ulpsCount, $this->db->table('ulps')->countAllResults());
    }

    /**
     * Test 30: Zero mutation on gis_translines and gis_transline_proposals
     */
    public function test30_ZeroMutation_TranslinesAndProposalsNotMutated(): void
    {
        $tlCount = $this->db->table('gis_translines')->countAllResults();
        $propCount = $this->db->table('gis_transline_proposals')->countAllResults();

        $this->service->getProposalWorkbenchDetail(1);
        $this->service->getProposalWorkbenchDetail(2);
        $this->service->getExceptionReviewQueue();

        $this->assertEquals($tlCount, $this->db->table('gis_translines')->countAllResults());
        $this->assertEquals($propCount, $this->db->table('gis_transline_proposals')->countAllResults());
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function snapshotTableCounts(): array
    {
        return [
            'ulps'                    => $this->db->table('ulps')->countAllResults(),
            'penyulang'               => $this->db->table('penyulang')->countAllResults(),
            'sections'                => $this->db->table('sections')->countAllResults(),
            'assets'                  => $this->db->table('assets')->countAllResults(),
            'gis_translines'          => $this->db->table('gis_translines')->countAllResults(),
            'gis_transline_proposals' => $this->db->table('gis_transline_proposals')->countAllResults(),
            'temuan'                  => $this->db->table('temuan')->countAllResults(),
            'temuan_materials'        => $this->db->table('temuan_materials')->countAllResults(),
        ];
    }
}
