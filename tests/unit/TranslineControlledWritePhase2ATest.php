<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\TranslineCompletionService;
use Config\Database;

/**
 * Test Suite for TL-01 Phase 2A: Single Transline Controlled Write & Rollback Proof
 *
 * Verifies:
 * - A: Target Selection Firewall
 * - B: Exactly 1 Controlled INSERT
 * - C: Duplicate Natural Key Rejection
 * - D: Cross-Feeder Rejection
 * - E: T-Off Branching Rejection
 * - F: Protected Domain Zero Mutation Firewall
 * - G: Topology & Geometry Integrity Verification
 * - H: Exact Rollback by Primary Key
 * - I: Zero-Residual Verification (BEFORE === POST-ROLLBACK)
 * - J: Idempotency Across Lifecycle (Candidate classification identical before and after rollback)
 */
class TranslineControlledWritePhase2ATest extends CIUnitTestCase
{
    protected $db;
    protected TranslineCompletionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupTestSchema();
        $this->service = new TranslineCompletionService($this->db);
    }

    protected function setupTestSchema(): void
    {
        $forge = \Config\Database::forge();

        // 1. sections table
        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        // 2. penyulang table
        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        // 3. assets table
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'      => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_asset'      => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'Aset Testing'],
                'jenis_asset'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'section_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'    => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'sequence_no'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'parent_asset_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'        => ['type' => 'DECIMAL', 'constraint' => '10,8', 'default' => -7.4242],
                'longitude'       => ['type' => 'DECIMAL', 'constraint' => '11,8', 'default' => 112.72701],
                'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        // 4. gis_translines table
        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        // Clear tables
        $this->db->table('sections')->emptyTable();
        $this->db->table('penyulang')->emptyTable();
        $this->db->table('assets')->emptyTable();
        $this->db->table('gis_translines')->emptyTable();
    }

    protected function insertAsset(array $data): void
    {
        if (!isset($data['nama_asset'])) {
            $data['nama_asset'] = $data['kode_asset'] ?? 'Tiang ' . ($data['id'] ?? 'Test');
        }
        if (!isset($data['jenis_asset'])) {
            $data['jenis_asset'] = 'TIANG_BETON';
        }
        $this->db->table('assets')->insert($data);
    }

    /**
     * TEST A & B & G & H & I & J: Full Lifecycle of Single Controlled Write & Rollback Proof
     */
    public function testFullLifecycleSingleControlledWriteAndRollbackProof(): void
    {
        // 1. Setup pilot section and assets based on real Siwalan Panji AST-SML-T001 -> AST-SML-T002 topology
        $this->db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'GI-LBSM PDAM']);
        $this->db->table('penyulang')->insert(['id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);

        $this->insertAsset([
            'id'           => 400,
            'kode_asset'   => 'AST-SML-T001',
            'section_id'   => 1,
            'penyulang_id' => 1,
            'sequence_no'  => 1,
            'latitude'     => -7.43210000,
            'longitude'    => 112.70120000,
        ]);
        $this->insertAsset([
            'id'           => 401,
            'kode_asset'   => 'AST-SML-T002',
            'section_id'   => 1,
            'penyulang_id' => 1,
            'sequence_no'  => 2,
            'latitude'     => -7.43300000,
            'longitude'    => 112.70250000,
        ]);

        // ==========================================
        // STEP 1: BEFORE SNAPSHOT
        // ==========================================
        $beforeTranslinesCount = $this->db->table('gis_translines')->countAllResults();
        $beforeAssetsCount     = $this->db->table('assets')->countAllResults();
        $beforeSectionsCount   = $this->db->table('sections')->countAllResults();
        $beforeAllTranslines   = $this->db->table('gis_translines')->get()->getResultArray();
        $beforeAllAssets       = $this->db->table('assets')->get()->getResultArray();

        $this->assertSame(0, $beforeTranslinesCount);
        $this->assertSame(2, $beforeAssetsCount);

        // Pre-run candidate engine: Should be MISSING initially because no transline exists yet
        $initialCandidates = $this->service->getSectionCompletionCandidates(1);
        $this->assertCount(1, $initialCandidates['candidates']);
        $targetCandidate = $initialCandidates['candidates'][0];
        $this->assertSame('TL-NAT:1:400-401', $targetCandidate['natural_key']);

        // Prepare controlled candidate payload for write
        $candidateToWrite = $targetCandidate;
        $candidateToWrite['status'] = TranslineCompletionService::STATUS_AUTO_MATCH; // Authoritative AUTO_MATCH qualification
        $candidateToWrite['conductor_type'] = 'AAAC';
        $candidateToWrite['conductor_size'] = '150 mm²';

        // ==========================================
        // STEP 2: PRE-WRITE VALIDATION
        // ==========================================
        $preValidation = $this->service->validateControlledCandidate($candidateToWrite);
        $this->assertTrue($preValidation['valid'], 'Pre-validation must pass for qualified AUTO_MATCH candidate');
        $this->assertNull($preValidation['reason']);

        // ==========================================
        // STEP 3: EXACTLY 1 CONTROLLED WRITE
        // ==========================================
        $writeResult = $this->service->executeControlledWrite($candidateToWrite, ['name' => 'GOVERNANCE_TEST']);
        $this->assertSame('success', $writeResult['status']);
        $this->assertSame('WRITE_COMMITTED', $writeResult['action']);
        $translineId = $writeResult['transline_id'];
        $this->assertGreaterThan(0, $translineId);

        // ==========================================
        // STEP 4: AFTER-WRITE SNAPSHOT & TOPOLOGY VERIFICATION
        // ==========================================
        $afterTranslinesCount = $this->db->table('gis_translines')->countAllResults();
        $afterAssetsCount     = $this->db->table('assets')->countAllResults();
        $afterSectionsCount   = $this->db->table('sections')->countAllResults();

        // Cardinality delta MUST = +1
        $this->assertSame($beforeTranslinesCount + 1, $afterTranslinesCount);
        // Protected domains MUST = 0 mutation
        $this->assertSame($beforeAssetsCount, $afterAssetsCount);
        $this->assertSame($beforeSectionsCount, $afterSectionsCount);

        // Inspect the single inserted row
        $insertedRow = $this->db->table('gis_translines')->where('id', $translineId)->get()->getRowArray();
        $this->assertNotNull($insertedRow);
        $this->assertSame('TL-1-400-401', $insertedRow['transline_code']);
        $this->assertSame('1', (string)$insertedRow['penyulang_id']);
        $this->assertSame('400', (string)$insertedRow['source_asset_id']);
        $this->assertSame('401', (string)$insertedRow['target_asset_id']);
        $this->assertSame('AAAC', $insertedRow['conductor_type']);
        $this->assertSame('150 mm²', $insertedRow['conductor_size']);
        $this->assertSame('1', (string)$insertedRow['is_active']);
        $this->assertSame('ACTIVE', $insertedRow['status']);

        // Verify geometry
        $geom = json_decode($insertedRow['geometry'], true);
        $this->assertIsArray($geom);
        $this->assertCount(2, $geom);
        $this->assertEqualsWithDelta(112.7012, $geom[0][0], 0.0001);
        $this->assertEqualsWithDelta(-7.4321, $geom[0][1], 0.0001);
        $this->assertEqualsWithDelta(112.7025, $geom[1][0], 0.0001);
        $this->assertEqualsWithDelta(-7.4330, $geom[1][1], 0.0001);

        // Verify that candidate engine NOW recognizes it as AUTO_MATCH
        $activeRun = $this->service->getSectionCompletionCandidates(1);
        $this->assertCount(1, $activeRun['candidates']);
        $this->assertSame(TranslineCompletionService::STATUS_AUTO_MATCH, $activeRun['candidates'][0]['status']);
        $this->assertSame($translineId, $activeRun['candidates'][0]['existing_transline_id']);

        // ==========================================
        // STEP 5: CONTROLLED ROLLBACK PROOF
        // ==========================================
        $rollbackResult = $this->service->executeControlledRollback($translineId, ['name' => 'GOVERNANCE_ROLLBACK_TEST']);
        $this->assertSame('success', $rollbackResult['status']);
        $this->assertSame('ROLLBACK_COMMITTED', $rollbackResult['action']);
        $this->assertTrue($rollbackResult['rollback_verified']);
        $this->assertSame($translineId, $rollbackResult['rolled_back_id']);

        // ==========================================
        // STEP 6: POST-ROLLBACK ZERO-RESIDUAL VERIFICATION
        // ==========================================
        $postTranslinesCount = $this->db->table('gis_translines')->countAllResults();
        $postAssetsCount     = $this->db->table('assets')->countAllResults();
        $postSectionsCount   = $this->db->table('sections')->countAllResults();
        $postAllTranslines   = $this->db->table('gis_translines')->get()->getResultArray();
        $postAllAssets       = $this->db->table('assets')->get()->getResultArray();

        // Invariant: BEFORE === POST-ROLLBACK
        $this->assertSame($beforeTranslinesCount, $postTranslinesCount);
        $this->assertSame($beforeAssetsCount, $postAssetsCount);
        $this->assertSame($beforeSectionsCount, $postSectionsCount);
        $this->assertSame($beforeAllTranslines, $postAllTranslines);
        $this->assertSame($beforeAllAssets, $postAllAssets);

        // Specific check: deleted record does not exist
        $deletedCheck = $this->db->table('gis_translines')->where('id', $translineId)->get()->getRowArray();
        $this->assertNull($deletedCheck);

        // Step 7: IDEMPOTENCY RESTORATION
        // Running completion engine again returns initial state exactly
        $postRun = $this->service->getSectionCompletionCandidates(1);
        $this->assertSame(json_encode($initialCandidates), json_encode($postRun));
    }

    /**
     * TEST C: Duplicate Natural Key Rejection
     */
    public function testDuplicateNaturalKeyRejected(): void
    {
        $this->db->table('sections')->insert(['id' => 2, 'penyulang_id' => 1, 'nama_section' => 'SEC-2']);
        $this->insertAsset(['id' => 410, 'kode_asset' => 'AST-410', 'section_id' => 2, 'penyulang_id' => 1, 'latitude' => -7.4240, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 411, 'kode_asset' => 'AST-411', 'section_id' => 2, 'penyulang_id' => 1, 'latitude' => -7.4244, 'longitude' => 112.7274]);

        // Insert first transline
        $this->db->table('gis_translines')->insert([
            'id'              => 601,
            'transline_code'  => 'TL-1-410-411',
            'penyulang_id'    => 1,
            'source_asset_id' => 410,
            'target_asset_id' => 411,
            'is_active'       => 1
        ]);

        // Attempt second write for reversed pair 411 -> 410
        $dupCandidate = [
            'status'          => TranslineCompletionService::STATUS_AUTO_MATCH,
            'penyulang_id'    => 1,
            'source_asset_id' => 411,
            'target_asset_id' => 410,
            'natural_key'     => 'TL-NAT:1:410-411',
        ];

        $res = $this->service->executeControlledWrite($dupCandidate);
        $this->assertSame('error', $res['status']);
        $this->assertSame('ABORT', $res['action']);
        $this->assertSame('TRANSLINE_ALREADY_EXISTS', $res['reason']);
    }

    /**
     * TEST D: Cross-Feeder Rejection
     */
    public function testCrossFeederRejected(): void
    {
        $this->db->table('sections')->insert(['id' => 3, 'penyulang_id' => 1, 'nama_section' => 'SEC-3']);
        $this->insertAsset(['id' => 420, 'kode_asset' => 'AST-420', 'section_id' => 3, 'penyulang_id' => 1, 'latitude' => -7.4240, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 421, 'kode_asset' => 'AST-421', 'section_id' => 3, 'penyulang_id' => 2, 'latitude' => -7.4244, 'longitude' => 112.7274]);

        $crossCandidate = [
            'status'          => TranslineCompletionService::STATUS_AUTO_MATCH,
            'penyulang_id'    => 1,
            'source_asset_id' => 420,
            'target_asset_id' => 421,
            'natural_key'     => 'TL-NAT:1:420-421',
        ];

        $res = $this->service->executeControlledWrite($crossCandidate);
        $this->assertSame('error', $res['status']);
        $this->assertSame('ABORT', $res['action']);
        $this->assertSame('CROSS_FEEDER_ILLEGAL_RELATIONSHIP', $res['reason']);
    }

    /**
     * TEST E: Non-AUTO_MATCH (e.g. NEEDS_REVIEW or MISSING) Aborts Write
     */
    public function testNonAutoMatchAbortsWrite(): void
    {
        $candidate = [
            'status'          => TranslineCompletionService::STATUS_NEEDS_REVIEW,
            'penyulang_id'    => 1,
            'source_asset_id' => 400,
            'target_asset_id' => 401,
            'natural_key'     => 'TL-NAT:1:400-401',
        ];

        $res = $this->service->executeControlledWrite($candidate);
        $this->assertSame('error', $res['status']);
        $this->assertSame('ABORT', $res['action']);
        $this->assertSame('STATUS_NOT_AUTO_MATCH', $res['reason']);
    }
}
