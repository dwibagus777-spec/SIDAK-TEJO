<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\TranslineProposalReviewService;
use App\Controllers\GisController;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\UserAgent;

/**
 * TL-01 Sub-Gate D3: Proposal Lifecycle Integrity & Operational Hardening Test Suite
 *
 * Verifies all mandatory requirements:
 * 1. Pure State Machine Transition Validator (validateStateTransition)
 * 2. Canonical Operational State Mapping (resolveCanonicalOperationalState)
 * 3. Integrity Scanner:
 *    - GOVERNANCE_ANOMALY detection
 *    - ORPHAN_CONFIRMATION_REF detection
 *    - BROKEN_TRANSLINE_FK detection
 *    - FEEDER_PROVENANCE_MISMATCH detection
 *    - SOURCE_ASSET_PROVENANCE_MISMATCH & TARGET_ASSET_PROVENANCE_MISMATCH detection
 *    - DUPLICATE_NATURAL_KEY_ANOMALY detection
 *    - UNLINKED_ACTIVE_TRANSLINE detection for proposal-governed domain
 *    - LEGACY_AUTHORITATIVE_TRANSLINE false-positive protection for 42 baseline translines
 * 4. Stale Coordinates Diagnostic (STALE_COORDINATES_ANOMALY) is DIAGNOSTIC-only (mutation = NONE)
 * 5. Deterministic Audit Receipts (generateAuditReceipt & generateBatchAuditReceipt) with SHA-256 (0 DB writes)
 * 6. Dashboard Operational Summary (getProposalDashboardSummary)
 * 7. Controller Read-Only Endpoints (apiProposalIntegrityScan & apiProposalDashboardSummary)
 * 8. Zero-Mutation Invariants on Master Assets, Sections, Penyulang, Temuan
 */
class TranslineProposalLifecycleD3Test extends CIUnitTestCase
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

        $this->db->table('ulps')->insert([
            'id'       => 1,
            'kode_ulp' => 'ULP01',
            'nama_ulp' => 'ULP SIDOARJO KOTA',
            'status'   => 'AKTIF',
        ]);

        $this->db->table('penyulang')->insert([
            'id'             => 10,
            'ulp_id'         => 1,
            'kode_penyulang' => 'BJK',
            'nama_penyulang' => 'BANJAR KEMANTREN',
            'status'         => 'AKTIF',
        ]);

        $this->db->table('sections')->insert([
            'id'           => 100,
            'penyulang_id' => 10,
            'nama_section' => 'SECTION UTAMA BJK',
        ]);

        $this->db->table('assets')->insertBatch([
            [
                'id'                   => 1001,
                'kode_asset'           => 'BANJARKEMANTRAN_01',
                'nama_asset'           => 'Tiang BJK 01',
                'jenis_asset'          => 'TIANG_BETON',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'latitude'             => -7.41600000,
                'longitude'            => 112.72300000,
            ],
            [
                'id'                   => 1002,
                'kode_asset'           => 'BANJARKEMANTRAN_02',
                'nama_asset'           => 'Tiang BJK 02',
                'jenis_asset'          => 'TIANG_BETON',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'latitude'             => -7.41630000,
                'longitude'            => 112.72310000,
            ],
            [
                'id'                   => 1003,
                'kode_asset'           => 'BANJARKEMANTRAN_03',
                'nama_asset'           => 'Tiang BJK 03',
                'jenis_asset'          => 'TIANG_BETON',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'construction_type_id' => 2,
                'latitude'             => -7.41660000,
                'longitude'            => 112.72330000,
            ],
        ]);

        // Seed 1 authoritative legacy transline (representing the 42 baseline)
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

        // Seed 1 canonical AUTO_MATCH proposal
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
            'confidence_score'        => 1.0000,
            'status'                  => 'PENDING_REVIEW',
        ]);
    }

    // =========================================================================
    // SECTION 1: PURE STATE MACHINE TRANSITION VALIDATOR (Scenarios 1 - 10)
    // =========================================================================

    /**
     * Scenario 1: AUTO_MATCH + PENDING_REVIEW -> CONFIRMED is ALLOWED
     */
    public function test01_StateMachine_AutoMatchPendingToConfirmed_Allowed(): void
    {
        $res = $this->service->validateStateTransition('PENDING_REVIEW', 'CONFIRMED', 'AUTO_MATCH');
        $this->assertTrue($res['allowed']);
        $this->assertEquals('TRANSITION_ALLOWED', $res['reason_code']);
    }

    /**
     * Scenario 2: NEEDS_REVIEW + PENDING_REVIEW -> CONFIRMED is DENIED
     */
    public function test02_StateMachine_NeedsReviewPendingToConfirmed_Denied(): void
    {
        $res = $this->service->validateStateTransition('PENDING_REVIEW', 'CONFIRMED', 'NEEDS_REVIEW');
        $this->assertFalse($res['allowed']);
        $this->assertEquals('CLASSIFICATION_NEEDS_REVIEW', $res['reason_code']);
    }

    /**
     * Scenario 3: INVALID + PENDING_REVIEW -> CONFIRMED is DENIED
     */
    public function test03_StateMachine_InvalidPendingToConfirmed_Denied(): void
    {
        $res = $this->service->validateStateTransition('PENDING_REVIEW', 'CONFIRMED', 'INVALID');
        $this->assertFalse($res['allowed']);
        $this->assertEquals('CLASSIFICATION_INVALID', $res['reason_code']);
    }

    /**
     * Scenario 4: MISSING + PENDING_REVIEW -> CONFIRMED is DENIED
     */
    public function test04_StateMachine_MissingPendingToConfirmed_Denied(): void
    {
        $res = $this->service->validateStateTransition('PENDING_REVIEW', 'CONFIRMED', 'MISSING');
        $this->assertFalse($res['allowed']);
        $this->assertEquals('CLASSIFICATION_MISSING', $res['reason_code']);
    }

    /**
     * Scenario 5: CONFIRMED -> CONFIRMED is DENIED (Idempotency & Re-confirmation guard)
     */
    public function test05_StateMachine_ConfirmedToConfirmed_Denied(): void
    {
        $res = $this->service->validateStateTransition('CONFIRMED', 'CONFIRMED', 'AUTO_MATCH');
        $this->assertFalse($res['allowed']);
        $this->assertEquals('ALREADY_CONFIRMED', $res['reason_code']);
    }

    /**
     * Scenario 6: CONFIRMED -> PENDING_REVIEW without rollback context is DENIED
     */
    public function test06_StateMachine_ConfirmedToPendingWithoutRollbackContext_Denied(): void
    {
        $res = $this->service->validateStateTransition('CONFIRMED', 'PENDING_REVIEW', 'AUTO_MATCH', []);
        $this->assertFalse($res['allowed']);
        $this->assertEquals('DIRECT_TRANSITION_DISALLOWED', $res['reason_code']);
    }

    /**
     * Scenario 7: CONFIRMED -> PENDING_REVIEW with rollback context but missing exact PK is DENIED
     */
    public function test07_StateMachine_ConfirmedToPendingRollbackMissingExactPk_Denied(): void
    {
        $context = [
            'is_rollback'      => true,
            'has_exact_pk'     => false,
            'provenance_valid' => true,
        ];
        $res = $this->service->validateStateTransition('CONFIRMED', 'PENDING_REVIEW', 'AUTO_MATCH', $context);
        $this->assertFalse($res['allowed']);
        $this->assertEquals('EXACT_PK_REQUIRED', $res['reason_code']);
    }

    /**
     * Scenario 8: CONFIRMED -> PENDING_REVIEW with rollback context and exact PK but invalid provenance is DENIED
     */
    public function test08_StateMachine_ConfirmedToPendingRollbackProvenanceMismatch_Denied(): void
    {
        $context = [
            'is_rollback'      => true,
            'has_exact_pk'     => true,
            'provenance_valid' => false,
        ];
        $res = $this->service->validateStateTransition('CONFIRMED', 'PENDING_REVIEW', 'AUTO_MATCH', $context);
        $this->assertFalse($res['allowed']);
        $this->assertEquals('PROVENANCE_MISMATCH', $res['reason_code']);
    }

    /**
     * Scenario 9: CONFIRMED -> PENDING_REVIEW with valid rollback context, exact PK, and valid provenance is ALLOWED
     */
    public function test09_StateMachine_ConfirmedToPendingRollbackValidContext_Allowed(): void
    {
        $context = [
            'is_rollback'      => true,
            'has_exact_pk'     => true,
            'provenance_valid' => true,
        ];
        $res = $this->service->validateStateTransition('CONFIRMED', 'PENDING_REVIEW', 'AUTO_MATCH', $context);
        $this->assertTrue($res['allowed']);
        $this->assertEquals('ROLLBACK_ALLOWED', $res['reason_code']);
    }

    /**
     * Scenario 10: Arbitrary illegal state transitions are DENIED
     */
    public function test10_StateMachine_ArbitraryIllegalTransition_Denied(): void
    {
        $res = $this->service->validateStateTransition('UNKNOWN_STATE', 'CONFIRMED', 'AUTO_MATCH');
        $this->assertFalse($res['allowed']);
        $this->assertEquals('ILLEGAL_STATE_TRANSITION', $res['reason_code']);
    }

    // =========================================================================
    // SECTION 2: CANONICAL OPERATIONAL STATE MAPPING (Scenarios 11 - 18)
    // =========================================================================

    /**
     * Scenario 11: AUTO_MATCH + PENDING_REVIEW maps strictly to READY
     */
    public function test11_CanonicalMapping_AutoMatchPending_MapsToReady(): void
    {
        $this->assertEquals('READY', $this->service->resolveCanonicalOperationalState('AUTO_MATCH', 'PENDING_REVIEW'));
    }

    /**
     * Scenario 12: NEEDS_REVIEW + PENDING_REVIEW maps strictly to HUMAN_REVIEW
     */
    public function test12_CanonicalMapping_NeedsReviewPending_MapsToHumanReview(): void
    {
        $this->assertEquals('HUMAN_REVIEW', $this->service->resolveCanonicalOperationalState('NEEDS_REVIEW', 'PENDING_REVIEW'));
    }

    /**
     * Scenario 13: INVALID + PENDING_REVIEW maps strictly to BLOCKED
     */
    public function test13_CanonicalMapping_InvalidPending_MapsToBlocked(): void
    {
        $this->assertEquals('BLOCKED', $this->service->resolveCanonicalOperationalState('INVALID', 'PENDING_REVIEW'));
    }

    /**
     * Scenario 14: MISSING + PENDING_REVIEW maps strictly to BLOCKED
     */
    public function test14_CanonicalMapping_MissingPending_MapsToBlocked(): void
    {
        $this->assertEquals('BLOCKED', $this->service->resolveCanonicalOperationalState('MISSING', 'PENDING_REVIEW'));
    }

    /**
     * Scenario 15: AUTO_MATCH + CONFIRMED maps strictly to ACTIVE
     */
    public function test15_CanonicalMapping_AutoMatchConfirmed_MapsToActive(): void
    {
        $this->assertEquals('ACTIVE', $this->service->resolveCanonicalOperationalState('AUTO_MATCH', 'CONFIRMED'));
    }

    /**
     * Scenario 16: NEEDS_REVIEW + CONFIRMED maps to GOVERNANCE_ANOMALY (NEVER forced to READY)
     */
    public function test16_CanonicalMapping_NeedsReviewConfirmed_MapsToGovernanceAnomaly(): void
    {
        $state = $this->service->resolveCanonicalOperationalState('NEEDS_REVIEW', 'CONFIRMED');
        $this->assertEquals('GOVERNANCE_ANOMALY', $state);
        $this->assertNotEquals('READY', $state);
    }

    /**
     * Scenario 17: INVALID + CONFIRMED maps to GOVERNANCE_ANOMALY
     */
    public function test17_CanonicalMapping_InvalidConfirmed_MapsToGovernanceAnomaly(): void
    {
        $this->assertEquals('GOVERNANCE_ANOMALY', $this->service->resolveCanonicalOperationalState('INVALID', 'CONFIRMED'));
    }

    /**
     * Scenario 18: Unrecognized or corrupted statuses map to GOVERNANCE_ANOMALY
     */
    public function test18_CanonicalMapping_CorruptedValues_MapToGovernanceAnomaly(): void
    {
        $this->assertEquals('GOVERNANCE_ANOMALY', $this->service->resolveCanonicalOperationalState('CORRUPTED_CLS', 'PENDING_REVIEW'));
        $this->assertEquals('GOVERNANCE_ANOMALY', $this->service->resolveCanonicalOperationalState('AUTO_MATCH', 'CORRUPTED_STATUS'));
    }

    // =========================================================================
    // SECTION 3: INTEGRITY SCANNER & PROVENANCE VERIFICATION (Scenarios 19 - 27)
    // =========================================================================

    /**
     * Scenario 19: Scanner detects GOVERNANCE_ANOMALY for invalid proposal state
     */
    public function test19_Scanner_DetectsGovernanceAnomaly(): void
    {
        $this->db->table('gis_transline_proposals')->insert([
            'id'             => 88,
            'penyulang_id'   => 10,
            'source_asset_id'=> 1001,
            'target_asset_id'=> 1002,
            'classification' => 'INVALID',
            'status'         => 'CONFIRMED', // Anomalous: INVALID confirmed!
            'natural_key'    => 'TL-NAT:10:88-ANOMALY',
        ]);

        $scan = $this->service->scanProposalIntegrity(10);
        $this->assertEquals('success', $scan['status']);
        $this->assertGreaterThanOrEqual(1, $scan['anomalies_found']);

        $types = array_column($scan['anomalies'], 'anomaly_type');
        $this->assertContains('GOVERNANCE_ANOMALY', $types);
    }

    /**
     * Scenario 20: Scanner detects ORPHAN_CONFIRMATION_REF (CONFIRMED with NULL PK)
     */
    public function test20_Scanner_DetectsOrphanConfirmationRef(): void
    {
        $this->db->table('gis_transline_proposals')->insert([
            'id'                     => 89,
            'penyulang_id'           => 10,
            'source_asset_id'        => 1001,
            'target_asset_id'        => 1002,
            'classification'         => 'AUTO_MATCH',
            'status'                 => 'CONFIRMED',
            'confirmed_transline_id' => null, // Anomaly: NULL PK!
            'natural_key'            => 'TL-NAT:10:89-ORPHAN',
        ]);

        $scan = $this->service->scanProposalIntegrity(10);
        $types = array_column($scan['anomalies'], 'anomaly_type');
        $this->assertContains('ORPHAN_CONFIRMATION_REF', $types);
    }

    /**
     * Scenario 21: Scanner detects BROKEN_TRANSLINE_FK (CONFIRMED with nonexistent PK)
     */
    public function test21_Scanner_DetectsBrokenTranslineFk(): void
    {
        $this->db->table('gis_transline_proposals')->insert([
            'id'                     => 90,
            'penyulang_id'           => 10,
            'source_asset_id'        => 1001,
            'target_asset_id'        => 1002,
            'classification'         => 'AUTO_MATCH',
            'status'                 => 'CONFIRMED',
            'confirmed_transline_id' => 99999, // Anomaly: non-existent ID!
            'natural_key'            => 'TL-NAT:10:90-BROKEN',
        ]);

        $scan = $this->service->scanProposalIntegrity(10);
        $types = array_column($scan['anomalies'], 'anomaly_type');
        $this->assertContains('BROKEN_TRANSLINE_FK', $types);
    }

    /**
     * Scenario 22: Scanner detects FEEDER_PROVENANCE_MISMATCH
     */
    public function test22_Scanner_DetectsFeederProvenanceMismatch(): void
    {
        // Insert a transline in feeder 99
        $this->db->table('gis_translines')->insert([
            'id'              => 601,
            'transline_code'  => 'TL-99-1001-1002',
            'penyulang_id'    => 99, // Different feeder!
            'source_asset_id' => 1001,
            'target_asset_id' => 1002,
            'status'          => 'ACTIVE',
            'is_active'       => 1,
            'source'          => 'PROPOSAL_CONFIRMATION',
            'created_by'      => 'OPERATOR_TEST',
        ]);

        // Insert proposal in feeder 10 pointing to transline 601
        $this->db->table('gis_transline_proposals')->insert([
            'id'                     => 91,
            'penyulang_id'           => 10,
            'source_asset_id'        => 1001,
            'target_asset_id'        => 1002,
            'classification'         => 'AUTO_MATCH',
            'status'                 => 'CONFIRMED',
            'confirmed_transline_id' => 601,
            'natural_key'            => 'TL-NAT:10:91-MISFEED',
        ]);

        $scan = $this->service->scanProposalIntegrity();
        $types = array_column($scan['anomalies'], 'anomaly_type');
        $this->assertContains('FEEDER_PROVENANCE_MISMATCH', $types);
    }

    /**
     * Scenario 23: Scanner detects SOURCE_ASSET_PROVENANCE_MISMATCH and TARGET_ASSET_PROVENANCE_MISMATCH
     */
    public function test23_Scanner_DetectsAssetPairProvenanceMismatch(): void
    {
        // Transline links 1001 <-> 1002
        $this->db->table('gis_translines')->insert([
            'id'              => 602,
            'transline_code'  => 'TL-10-1001-1002',
            'penyulang_id'    => 10,
            'source_asset_id' => 1001,
            'target_asset_id' => 1002,
            'status'          => 'ACTIVE',
            'is_active'       => 1,
            'source'          => 'PROPOSAL_CONFIRMATION',
            'created_by'      => 'OPERATOR_TEST',
        ]);

        // Proposal claims to link 1002 <-> 1003 but points to transline 602
        $this->db->table('gis_transline_proposals')->insert([
            'id'                     => 92,
            'penyulang_id'           => 10,
            'source_asset_id'        => 1002,
            'target_asset_id'        => 1003, // Wrong asset!
            'classification'         => 'AUTO_MATCH',
            'status'                 => 'CONFIRMED',
            'confirmed_transline_id' => 602,
            'natural_key'            => 'TL-NAT:10:92-MISASSET',
        ]);

        $scan = $this->service->scanProposalIntegrity(10);
        $types = array_column($scan['anomalies'], 'anomaly_type');
        $hasMismatch = in_array('SOURCE_ASSET_PROVENANCE_MISMATCH', $types) || in_array('TARGET_ASSET_PROVENANCE_MISMATCH', $types);
        $this->assertTrue($hasMismatch);
    }

    /**
     * Scenario 24: Scanner detects DUPLICATE_NATURAL_KEY_ANOMALY
     */
    public function test24_Scanner_DetectsDuplicateNaturalKeyAnomaly(): void
    {
        $this->db->table('gis_transline_proposals')->insert([
            'id'              => 93,
            'penyulang_id'    => 10,
            'source_asset_id' => 1001,
            'target_asset_id' => 1002,
            'classification'  => 'AUTO_MATCH',
            'status'          => 'PENDING_REVIEW',
            'natural_key'     => 'TL-NAT:10:1001-1002', // Duplicate of proposal #1!
        ]);

        $scan = $this->service->scanProposalIntegrity(10);
        $types = array_column($scan['anomalies'], 'anomaly_type');
        $this->assertContains('DUPLICATE_NATURAL_KEY_ANOMALY', $types);
    }

    /**
     * Scenario 25: Scanner detects UNLINKED_ACTIVE_TRANSLINE for proposal-governed domain
     */
    public function test25_Scanner_DetectsUnlinkedProposalGovernedTransline(): void
    {
        // Transline created by proposal confirmation actor but has no proposal record linking to it
        $this->db->table('gis_translines')->insert([
            'id'              => 701,
            'transline_code'  => 'TL-10-1001-1003',
            'penyulang_id'    => 10,
            'source_asset_id' => 1001,
            'target_asset_id' => 1003,
            'status'          => 'ACTIVE',
            'is_active'       => 1,
            'source'          => 'PROPOSAL_CONFIRMATION',
            'created_by'      => 'OPERATOR_TEST',
        ]);

        $scan = $this->service->scanProposalIntegrity(10);
        $types = array_column($scan['anomalies'], 'anomaly_type');
        $this->assertContains('UNLINKED_ACTIVE_TRANSLINE', $types);
    }

    /**
     * Scenario 26: Legacy Authoritative Baseline Translines are PROTECTED (ZERO false positives)
     */
    public function test26_Scanner_ProtectsLegacyBaselineTranslines(): void
    {
        // Initial state has 1 baseline transline (#501 with source=BASELINE_SEEDER, created_by=SYSTEM_SEEDER)
        $scan = $this->service->scanProposalIntegrity(10);

        // Verify that 501 is protected and NOT listed as an anomaly
        $this->assertGreaterThanOrEqual(1, $scan['summary']['legacy_translines_protected']);

        $types = array_column($scan['anomalies'], 'anomaly_type');
        $this->assertNotContains('UNLINKED_ACTIVE_TRANSLINE', $types);
    }

    /**
     * Scenario 27: Stale coordinates check is DIAGNOSTIC-ONLY and executes 0 mutations
     */
    public function test27_Scanner_StaleCoordinatesDiagnosticOnlyZeroMutation(): void
    {
        // Proposal with source asset missing coordinates
        $this->db->table('assets')->insert([
            'id'                   => 3001,
            'kode_asset'           => 'TIANG_NO_COORDS',
            'nama_asset'           => 'Tiang Tanpa Koordinat',
            'penyulang_id'         => 10,
            'section_id'           => 100,
            'construction_type_id' => 1,
            'latitude'             => 0.0, // Missing!
            'longitude'            => 0.0, // Missing!
        ]);

        $this->db->table('gis_transline_proposals')->insert([
            'id'              => 94,
            'penyulang_id'    => 10,
            'source_asset_id' => 3001,
            'target_asset_id' => 1001,
            'classification'  => 'AUTO_MATCH',
            'status'          => 'PENDING_REVIEW',
            'natural_key'     => 'TL-NAT:10:3001-1001',
        ]);

        $beforeAssetCount = $this->db->table('assets')->countAllResults();
        $beforePropCount  = $this->db->table('gis_transline_proposals')->countAllResults();

        $scan = $this->service->scanProposalIntegrity(10);

        // Assert diagnostic classification and mutation = NONE
        $diag = $scan['diagnostic_stale_coordinates'];
        $this->assertEquals('DIAGNOSTIC', $diag['classification']);
        $this->assertEquals('NONE', $diag['mutation']);
        $this->assertGreaterThan(0, $diag['stale_count']);

        // Zero mutation guarantee
        $afterAssetCount = $this->db->table('assets')->countAllResults();
        $afterPropCount  = $this->db->table('gis_transline_proposals')->countAllResults();
        $this->assertEquals($beforeAssetCount, $afterAssetCount);
        $this->assertEquals($beforePropCount, $afterPropCount);
    }

    // =========================================================================
    // SECTION 4: AUDIT RECEIPTS & DASHBOARD SUMMARY (Scenarios 28 - 32)
    // =========================================================================

    /**
     * Scenario 28: generateAuditReceipt creates deterministic receipt with SHA-256 (0 DB writes)
     */
    public function test28_AuditReceipt_DeterministicFingerprintZeroWrites(): void
    {
        $tablesBefore = $this->db->listTables();
        $propsBefore  = $this->db->table('gis_transline_proposals')->countAllResults();

        $receiptRes = $this->service->generateAuditReceipt(1);
        $this->assertEquals('success', $receiptRes['status']);

        $receipt = $receiptRes['receipt'];
        $this->assertEquals('RCPT-D3-1', $receipt['receipt_id']);
        $this->assertEquals(1, $receipt['proposal_id']);
        $this->assertEquals('READY', $receipt['canonical_operational_state']);
        $this->assertNotEmpty($receipt['sha256_fingerprint']);
        $this->assertEquals(64, strlen($receipt['sha256_fingerprint']));
        $this->assertTrue($receipt['invariants_enforced']['state_machine_pure']);

        // Ensure NO new table and NO row mutations
        $tablesAfter = $this->db->listTables();
        $propsAfter  = $this->db->table('gis_transline_proposals')->countAllResults();
        $this->assertEquals(count($tablesBefore), count($tablesAfter));
        $this->assertEquals($propsBefore, $propsAfter);
    }

    /**
     * Scenario 29: generateBatchAuditReceipt creates batch receipts with aggregate fingerprint
     */
    public function test29_BatchAuditReceipt_GeneratesAggregateFingerprint(): void
    {
        $batchRes = $this->service->generateBatchAuditReceipt([1]);
        $this->assertEquals('success', $batchRes['status']);
        $this->assertEquals(1, $batchRes['receipt_count']);
        $this->assertNotEmpty($batchRes['batch_fingerprint']);
        $this->assertEquals(64, strlen($batchRes['batch_fingerprint']));
    }

    /**
     * Scenario 30: getProposalDashboardSummary returns canonical state breakdown
     */
    public function test30_DashboardSummary_ReturnsCanonicalOperationalStateBreakdown(): void
    {
        $summaryRes = $this->service->getProposalDashboardSummary(10);
        $this->assertEquals('success', $summaryRes['status']);

        $summary = $summaryRes['summary'];
        $this->assertArrayHasKey('ready', $summary);
        $this->assertArrayHasKey('human_review', $summary);
        $this->assertArrayHasKey('blocked', $summary);
        $this->assertArrayHasKey('active', $summary);
        $this->assertArrayHasKey('governance_anomaly', $summary);

        // In test seed: Proposal #1 is AUTO_MATCH + PENDING_REVIEW -> ready = 1
        $this->assertEquals(1, $summary['ready']);
        $this->assertEquals(0, $summary['active']);
        $this->assertEquals(0, $summary['governance_anomaly']);
        $this->assertNotEmpty($summaryRes['fingerprint']);
    }

    /**
     * Scenario 31: GET /gis/api-proposal-integrity-scan endpoint responds HTTP 200
     */
    public function test31_Controller_ApiProposalIntegrityScanResponds200(): void
    {
        $controller = new GisController();
        $controller->initController(
            service('request'),
            service('response'),
            service('logger')
        );

        $response = $controller->apiProposalIntegrityScan();
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('anomalies', $body);
        $this->assertArrayHasKey('summary', $body);
        $this->assertArrayHasKey('fingerprint', $body);
    }

    /**
     * Scenario 32: GET /gis/api-proposal-dashboard-summary endpoint responds HTTP 200
     */
    public function test32_Controller_ApiProposalDashboardSummaryResponds200(): void
    {
        $controller = new GisController();
        $controller->initController(
            service('request'),
            service('response'),
            service('logger')
        );

        $response = $controller->apiProposalDashboardSummary();
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('summary', $body);
        $this->assertArrayHasKey('ready', $body['summary']);
    }

    // =========================================================================
    // SECTION 5: STRICT ZERO-MUTATION FIREWALL PROOF (Scenarios 33 - 35)
    // =========================================================================

    /**
     * Scenario 33: Master assets table is 100% read-only across all scanner and receipt operations
     */
    public function test33_ZeroMutation_MasterAssetsTableUntouched(): void
    {
        $assetsBefore = $this->db->table('assets')->get()->getResultArray();
        $assetsHashBefore = hash('sha256', json_encode($assetsBefore));

        // Run full scanner, summary, and receipt generation
        $this->service->scanProposalIntegrity();
        $this->service->getProposalDashboardSummary();
        $this->service->generateAuditReceipt(1);
        $this->service->generateBatchAuditReceipt([1]);

        $assetsAfter = $this->db->table('assets')->get()->getResultArray();
        $assetsHashAfter = hash('sha256', json_encode($assetsAfter));

        $this->assertEquals($assetsHashBefore, $assetsHashAfter);
        $this->assertEquals(count($assetsBefore), count($assetsAfter));
    }

    /**
     * Scenario 34: assets.section_id and assets.construction_type_id remain 100% unmutated
     */
    public function test34_ZeroMutation_AssetSectionAndConstructionUntouched(): void
    {
        $asset1001Before = $this->db->table('assets')->where('id', 1001)->get()->getRowArray();
        $this->assertEquals(100, (int)$asset1001Before['section_id']);
        $this->assertEquals(1, (int)$asset1001Before['construction_type_id']);

        // Run full suite of D3 operations
        $this->service->validateStateTransition('PENDING_REVIEW', 'CONFIRMED', 'AUTO_MATCH');
        $this->service->resolveCanonicalOperationalState('AUTO_MATCH', 'PENDING_REVIEW');
        $this->service->scanProposalIntegrity();
        $this->service->getProposalDashboardSummary();
        $this->service->generateAuditReceipt(1);

        $asset1001After = $this->db->table('assets')->where('id', 1001)->get()->getRowArray();
        $this->assertEquals((int)$asset1001Before['section_id'], (int)$asset1001After['section_id']);
        $this->assertEquals((int)$asset1001Before['construction_type_id'], (int)$asset1001After['construction_type_id']);
    }

    /**
     * Scenario 35: Protected operational tables (penyulang, sections, temuan, temuan_materials) 0 mutation
     */
    public function test35_ZeroMutation_ProtectedDomainsUntouched(): void
    {
        $countsBefore = [
            'penyulang'        => $this->db->table('penyulang')->countAllResults(),
            'sections'         => $this->db->table('sections')->countAllResults(),
            'temuan'           => $this->db->table('temuan')->countAllResults(),
            'temuan_materials' => $this->db->table('temuan_materials')->countAllResults(),
        ];

        // Execute scanner & receipts
        $this->service->scanProposalIntegrity();
        $this->service->getProposalDashboardSummary();
        $this->service->generateAuditReceipt(1);

        $countsAfter = [
            'penyulang'        => $this->db->table('penyulang')->countAllResults(),
            'sections'         => $this->db->table('sections')->countAllResults(),
            'temuan'           => $this->db->table('temuan')->countAllResults(),
            'temuan_materials' => $this->db->table('temuan_materials')->countAllResults(),
        ];

        $this->assertEquals($countsBefore, $countsAfter);
    }
}
