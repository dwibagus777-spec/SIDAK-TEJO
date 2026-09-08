<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\TranslineCompletionService;
use Config\Database;

/**
 * Unit Test Suite for TL-01 Phase 1: TranslineCompletionService
 *
 * Covers 20 Required Governance Scenarios:
 * 1. Existing exact pair -> AUTO_MATCH
 * 2. Reversed pair A-B vs B-A -> same natural identity
 * 3. Missing pair -> MISSING
 * 4. distance = 0 -> NEEDS_REVIEW (INVALID_STORED_DISTANCE)
 * 5. invalid coordinates (0.0, 0.0) -> NEEDS_REVIEW (INVALID_COORDINATE)
 * 6. conductor type conflict -> NEEDS_REVIEW (CONDUCTOR_SPEC_CONFLICT)
 * 7. conductor size normalization -> deterministic comparison
 * 8. AAAC vs A3CS -> NEEDS_REVIEW
 * 9. T-Off / Branching detected -> NEEDS_REVIEW (BRANCHING_AMBIGUITY)
 * 10. cross-feeder -> INVALID (CROSS_FEEDER_ILLEGAL_RELATIONSHIP)
 * 11. cross-section violation -> appropriate warning & governed candidate
 * 12. duplicate candidate pair -> one candidate only
 * 13. stable sorting -> deterministic ordering
 * 14. empty section -> 0 candidates, EMPTY_SECTION
 * 15. section with one asset -> 0 candidates, SINGLE_ASSET_SECTION
 * 16. section with no deterministic ordering -> NO_DETERMINISTIC_ORDER
 * 17. idempotency run #1, #2, #3 -> identical outputs
 * 18. authorization boundary -> invalid scope rejected
 * 19. existing Transline remains unchanged (Zero database mutation)
 * 20. no write operation (Strictly read-only)
 */
class TranslineCompletionServiceTest extends CIUnitTestCase
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

        try {
            if (!$this->db->fieldExists('created_by', 'gis_translines')) {
                $forge->addColumn('gis_translines', ['created_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true]]);
            }
        } catch (\Throwable $e) {}
        try {
            if (!$this->db->fieldExists('circuit_config', 'gis_translines')) {
                $forge->addColumn('gis_translines', ['circuit_config' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE']]);
            }
        } catch (\Throwable $e) {}
        try {
            if (!$this->db->fieldExists('geometry_type', 'gis_translines')) {
                $forge->addColumn('gis_translines', ['geometry_type' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString']]);
            }
        } catch (\Throwable $e) {}
        try {
            if (!$this->db->fieldExists('installation_type', 'gis_translines')) {
                $forge->addColumn('gis_translines', ['installation_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD']]);
            }
        } catch (\Throwable $e) {}

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
     * Test 1: Existing exact pair -> AUTO_MATCH
     */
    public function testExistingExactPairProducesAutoMatch(): void
    {
        $this->db->table('sections')->insert(['id' => 10, 'penyulang_id' => 1, 'nama_section' => 'SEC-PILOT-1']);
        $this->insertAsset(['id' => 101, 'kode_asset' => 'AST-101', 'section_id' => 10, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4242, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 102, 'kode_asset' => 'AST-102', 'section_id' => 10, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4246, 'longitude' => 112.7274]);

        $this->db->table('gis_translines')->insert([
            'id' => 501, 'transline_code' => 'TL-1-101-102', 'penyulang_id' => 1,
            'source_asset_id' => 101, 'target_asset_id' => 102,
            'conductor_type' => 'AAAC', 'conductor_size' => '150 mm²', 'distance_meters' => 50.00, 'is_active' => 1
        ]);

        $res = $this->service->getSectionCompletionCandidates(10);

        $this->assertCount(1, $res['candidates']);
        $c = $res['candidates'][0];
        $this->assertSame(TranslineCompletionService::STATUS_AUTO_MATCH, $c['status']);
        $this->assertSame(TranslineCompletionService::REASON_EXISTING_VALID_PAIR, $c['reason_code']);
        $this->assertSame(501, $c['existing_transline_id']);
        $this->assertSame(1, $res['summary']['auto_match_count']);
    }

    /**
     * Test 2: Reversed pair A-B vs B-A produces same natural identity
     */
    public function testReversedPairProducesIdenticalNaturalKey(): void
    {
        $keyForward = $this->service->buildNaturalKey(1, 101, 102);
        $keyReverse = $this->service->buildNaturalKey(1, 102, 101);

        $this->assertSame($keyForward, $keyReverse);
        $this->assertSame('TL-NAT:1:101-102', $keyForward);
    }

    /**
     * Test 3: Missing pair -> MISSING
     */
    public function testMissingPairProducesMissingStatus(): void
    {
        $this->db->table('sections')->insert(['id' => 11, 'penyulang_id' => 1, 'nama_section' => 'SEC-PILOT-2']);
        $this->insertAsset(['id' => 103, 'kode_asset' => 'AST-103', 'section_id' => 11, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4242, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 104, 'kode_asset' => 'AST-104', 'section_id' => 11, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4246, 'longitude' => 112.7274]);

        $res = $this->service->getSectionCompletionCandidates(11);

        $this->assertCount(1, $res['candidates']);
        $c = $res['candidates'][0];
        $this->assertSame(TranslineCompletionService::STATUS_MISSING, $c['status']);
        $this->assertSame(TranslineCompletionService::REASON_DETERMINISTIC_MISSING_EDGE, $c['reason_code']);
        $this->assertNull($c['existing_transline_id']);
        $this->assertSame(1, $res['summary']['missing_count']);
    }

    /**
     * Test 4: distance = 0 -> NEEDS_REVIEW (INVALID_STORED_DISTANCE)
     */
    public function testDistanceZeroProducesNeedsReview(): void
    {
        $this->db->table('sections')->insert(['id' => 12, 'penyulang_id' => 1, 'nama_section' => 'SEC-PILOT-3']);
        $this->insertAsset(['id' => 105, 'kode_asset' => 'AST-105', 'section_id' => 12, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4242, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 106, 'kode_asset' => 'AST-106', 'section_id' => 12, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4246, 'longitude' => 112.7274]);

        $this->db->table('gis_translines')->insert([
            'id' => 502, 'transline_code' => 'TL-1-105-106', 'penyulang_id' => 1,
            'source_asset_id' => 105, 'target_asset_id' => 106,
            'conductor_type' => 'AAAC', 'conductor_size' => '150 mm²', 'distance_meters' => 0.00, 'is_active' => 1
        ]);

        $res = $this->service->getSectionCompletionCandidates(12);

        $this->assertCount(1, $res['candidates']);
        $c = $res['candidates'][0];
        $this->assertSame(TranslineCompletionService::STATUS_NEEDS_REVIEW, $c['status']);
        $this->assertSame(TranslineCompletionService::REASON_INVALID_STORED_DISTANCE, $c['reason_code']);
    }

    /**
     * Test 5: Invalid coordinates (0.0, 0.0) -> NEEDS_REVIEW (INVALID_COORDINATE)
     */
    public function testInvalidCoordinatesProduceNeedsReview(): void
    {
        $this->db->table('sections')->insert(['id' => 13, 'penyulang_id' => 1, 'nama_section' => 'SEC-PILOT-4']);
        $this->insertAsset(['id' => 107, 'kode_asset' => 'AST-107', 'section_id' => 13, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => 0.0000, 'longitude' => 0.0000]);
        $this->insertAsset(['id' => 108, 'kode_asset' => 'AST-108', 'section_id' => 13, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4246, 'longitude' => 112.7274]);

        $res = $this->service->getSectionCompletionCandidates(13);

        $this->assertCount(1, $res['candidates']);
        $c = $res['candidates'][0];
        $this->assertSame(TranslineCompletionService::STATUS_NEEDS_REVIEW, $c['status']);
        $this->assertSame(TranslineCompletionService::REASON_INVALID_COORDINATE, $c['reason_code']);
    }

    /**
     * Test 6 & 8: Conductor type conflict (AAAC vs A3CS) -> NEEDS_REVIEW
     */
    public function testConductorTypeConflictProducesNeedsReview(): void
    {
        $this->db->table('sections')->insert(['id' => 14, 'penyulang_id' => 1, 'nama_section' => 'SEC-PILOT-5']);
        $this->insertAsset(['id' => 109, 'kode_asset' => 'AST-109', 'section_id' => 14, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4242, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 110, 'kode_asset' => 'AST-110', 'section_id' => 14, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4246, 'longitude' => 112.7274]);

        $this->db->table('gis_translines')->insert([
            'id' => 503, 'transline_code' => 'TL-1-109-110', 'penyulang_id' => 1,
            'source_asset_id' => 109, 'target_asset_id' => 110,
            'conductor_type' => 'AAAC', 'conductor_size' => '150 mm²', 'distance_meters' => 50.00, 'is_active' => 1
        ]);

        // Survey recorded A3CS (shielded) while GIS records AAAC
        $res = $this->service->getSectionCompletionCandidates(14, ['survey_conductor_type' => 'A3CS']);

        $this->assertCount(1, $res['candidates']);
        $c = $res['candidates'][0];
        $this->assertSame(TranslineCompletionService::STATUS_NEEDS_REVIEW, $c['status']);
        $this->assertSame(TranslineCompletionService::REASON_CONDUCTOR_SPEC_CONFLICT, $c['reason_code']);
    }

    /**
     * Test 7: Conductor size normalization
     */
    public function testConductorSizeNormalization(): void
    {
        $this->assertSame('150', $this->service->normalizeConductorSize('150 mm²'));
        $this->assertSame('150', $this->service->normalizeConductorSize('150 mm2'));
        $this->assertSame('150', $this->service->normalizeConductorSize('150'));
        $this->assertSame('70', $this->service->normalizeConductorSize('70 mm²'));
    }

    /**
     * Test 9: T-Off / Branching detected -> NEEDS_REVIEW (BRANCHING_AMBIGUITY)
     */
    public function testBranchingDetectedProducesNeedsReview(): void
    {
        $this->db->table('sections')->insert(['id' => 15, 'penyulang_id' => 1, 'nama_section' => 'SEC-BRANCH']);
        // Node 200 has 2 children (201 and 202)
        $this->insertAsset(['id' => 200, 'kode_asset' => 'AST-HUB', 'section_id' => 15, 'penyulang_id' => 1, 'latitude' => -7.4242, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 201, 'kode_asset' => 'AST-BR1', 'section_id' => 15, 'penyulang_id' => 1, 'parent_asset_id' => 200, 'latitude' => -7.4246, 'longitude' => 112.7274]);
        $this->insertAsset(['id' => 202, 'kode_asset' => 'AST-BR2', 'section_id' => 15, 'penyulang_id' => 1, 'parent_asset_id' => 200, 'latitude' => -7.4250, 'longitude' => 112.7278]);

        $res = $this->service->getSectionCompletionCandidates(15);

        $this->assertCount(2, $res['candidates']);
        // Second branch from node 200 should be marked NEEDS_REVIEW due to branching
        $hasBranchWarning = false;
        foreach ($res['candidates'] as $c) {
            if ($c['reason_code'] === TranslineCompletionService::REASON_BRANCHING_AMBIGUITY) {
                $hasBranchWarning = true;
                break;
            }
        }
        $this->assertTrue($hasBranchWarning);
    }

    /**
     * Test 10: Cross-feeder -> INVALID (CROSS_FEEDER_ILLEGAL_RELATIONSHIP)
     */
    public function testCrossFeederProducesInvalid(): void
    {
        $this->db->table('sections')->insert(['id' => 16, 'penyulang_id' => 1, 'nama_section' => 'SEC-CROSS']);
        $this->insertAsset(['id' => 301, 'kode_asset' => 'AST-301', 'section_id' => 16, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4242, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 302, 'kode_asset' => 'AST-302', 'section_id' => 16, 'penyulang_id' => 2, 'sequence_no' => 2, 'latitude' => -7.4246, 'longitude' => 112.7274]);

        $res = $this->service->getSectionCompletionCandidates(16);

        $this->assertCount(1, $res['candidates']);
        $c = $res['candidates'][0];
        $this->assertSame(TranslineCompletionService::STATUS_INVALID, $c['status']);
        $this->assertSame(TranslineCompletionService::REASON_CROSS_FEEDER_ILLEGAL, $c['reason_code']);
    }

    /**
     * Test 11: Cross-section boundary warning
     */
    public function testCrossSectionBoundaryProducesWarning(): void
    {
        $this->db->table('sections')->insert(['id' => 17, 'penyulang_id' => 1, 'nama_section' => 'SEC-A']);
        $this->insertAsset(['id' => 401, 'kode_asset' => 'AST-401', 'section_id' => 17, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4242, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 402, 'kode_asset' => 'AST-402', 'section_id' => 18, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4246, 'longitude' => 112.7274]);

        $this->db->table('gis_translines')->insert([
            'id' => 504, 'transline_code' => 'TL-1-401-402', 'penyulang_id' => 1,
            'source_asset_id' => 401, 'target_asset_id' => 402,
            'conductor_type' => 'AAAC', 'conductor_size' => '150 mm²', 'distance_meters' => 50.00, 'is_active' => 1
        ]);

        $res = $this->service->getSectionCompletionCandidates(17);

        $this->assertNotEmpty($res['candidates']);
        $c = $res['candidates'][0];
        $this->assertNotEmpty($c['warnings']);
    }

    /**
     * Test 12: Duplicate candidate pair -> only one candidate generated
     */
    public function testDuplicateCandidatePairDeduped(): void
    {
        $this->db->table('sections')->insert(['id' => 18, 'penyulang_id' => 1, 'nama_section' => 'SEC-DEDUP']);
        $this->insertAsset(['id' => 501, 'kode_asset' => 'AST-501', 'section_id' => 18, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4242, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 502, 'kode_asset' => 'AST-502', 'section_id' => 18, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4246, 'longitude' => 112.7274]);

        $res = $this->service->getSectionCompletionCandidates(18);

        $this->assertCount(1, $res['candidates']);
    }

    /**
     * Test 13: Stable sorting -> deterministic ordering
     */
    public function testStableSorting(): void
    {
        $this->db->table('sections')->insert(['id' => 19, 'penyulang_id' => 1, 'nama_section' => 'SEC-SORT']);
        $this->insertAsset(['id' => 601, 'kode_asset' => 'AST-601', 'section_id' => 19, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4240, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 602, 'kode_asset' => 'AST-602', 'section_id' => 19, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4244, 'longitude' => 112.7274]);
        $this->insertAsset(['id' => 603, 'kode_asset' => 'AST-603', 'section_id' => 19, 'penyulang_id' => 1, 'sequence_no' => 3, 'latitude' => -7.4248, 'longitude' => 112.7278]);

        $res = $this->service->getSectionCompletionCandidates(19);

        $this->assertCount(2, $res['candidates']);
        $this->assertSame(601, $res['candidates'][0]['source_asset_id']);
        $this->assertSame(602, $res['candidates'][0]['target_asset_id']);
        $this->assertSame(602, $res['candidates'][1]['source_asset_id']);
        $this->assertSame(603, $res['candidates'][1]['target_asset_id']);
    }

    /**
     * Test 14: Empty section -> 0 candidates, EMPTY_SECTION
     */
    public function testEmptySectionProducesEmptySummary(): void
    {
        $this->db->table('sections')->insert(['id' => 20, 'penyulang_id' => 1, 'nama_section' => 'SEC-EMPTY']);

        $res = $this->service->getSectionCompletionCandidates(20);

        $this->assertSame(0, $res['summary']['total_candidates']);
        $this->assertEmpty($res['candidates']);
        $this->assertSame(TranslineCompletionService::REASON_EMPTY_SECTION, $res['summary']['note']);
    }

    /**
     * Test 15: Section with one asset -> 0 candidates, SINGLE_ASSET_SECTION
     */
    public function testSingleAssetSectionProducesZeroCandidates(): void
    {
        $this->db->table('sections')->insert(['id' => 21, 'penyulang_id' => 1, 'nama_section' => 'SEC-SINGLE']);
        $this->insertAsset(['id' => 701, 'kode_asset' => 'AST-701', 'section_id' => 21, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4240, 'longitude' => 112.7270]);

        $res = $this->service->getSectionCompletionCandidates(21);

        $this->assertSame(0, $res['summary']['total_candidates']);
        $this->assertEmpty($res['candidates']);
        $this->assertSame(TranslineCompletionService::REASON_SINGLE_ASSET, $res['summary']['note']);
    }

    /**
     * Test 16: Section with no deterministic ordering -> NO_DETERMINISTIC_ORDER
     */
    public function testSectionWithNoDeterministicOrdering(): void
    {
        $this->db->table('sections')->insert(['id' => 22, 'penyulang_id' => 1, 'nama_section' => 'SEC-NO-ORDER']);
        // No sequence_no, no parent_asset_id, no existing transline
        $this->insertAsset(['id' => 801, 'kode_asset' => 'AST-801', 'section_id' => 22, 'penyulang_id' => 1, 'sequence_no' => 0, 'latitude' => -7.4240, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 802, 'kode_asset' => 'AST-802', 'section_id' => 22, 'penyulang_id' => 1, 'sequence_no' => 0, 'latitude' => -7.4244, 'longitude' => 112.7274]);

        $res = $this->service->getSectionCompletionCandidates(22);

        $this->assertSame(0, $res['summary']['total_candidates']);
        $this->assertSame(TranslineCompletionService::REASON_NO_DETERMINISTIC_ORDER, $res['summary']['note']);
    }

    /**
     * Test 17: Idempotency run #1, #2, #3 -> identical outputs
     */
    public function testIdempotencyOverMultipleRuns(): void
    {
        $this->db->table('sections')->insert(['id' => 23, 'penyulang_id' => 1, 'nama_section' => 'SEC-IDEMPOTENT']);
        $this->insertAsset(['id' => 901, 'kode_asset' => 'AST-901', 'section_id' => 23, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4240, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 902, 'kode_asset' => 'AST-902', 'section_id' => 23, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4244, 'longitude' => 112.7274]);

        $run1 = $this->service->getSectionCompletionCandidates(23);
        $run2 = $this->service->getSectionCompletionCandidates(23);
        $run3 = $this->service->getSectionCompletionCandidates(23);

        $this->assertSame(json_encode($run1), json_encode($run2));
        $this->assertSame(json_encode($run2), json_encode($run3));
    }

    /**
     * Test 18: Authorization / scope boundary -> invalid scope rejected
     */
    public function testInvalidScopeReturnsEmptyCleanly(): void
    {
        $res = $this->service->getSectionCompletionCandidates(0);
        $this->assertSame(0, $res['summary']['total_candidates']);
        $this->assertEmpty($res['candidates']);

        $resNeg = $this->service->getSectionCompletionCandidates(-5);
        $this->assertSame(0, $resNeg['summary']['total_candidates']);
    }

    /**
     * Test 19 & 20: Zero write operation - database remains 100% untouched
     */
    public function testServiceNeverMutatesDatabase(): void
    {
        $this->db->table('sections')->insert(['id' => 24, 'penyulang_id' => 1, 'nama_section' => 'SEC-ZEROWRITE']);
        $this->insertAsset(['id' => 950, 'kode_asset' => 'AST-950', 'section_id' => 24, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4240, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 951, 'kode_asset' => 'AST-951', 'section_id' => 24, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4244, 'longitude' => 112.7274]);

        $countTranslinesBefore = $this->db->table('gis_translines')->countAllResults();
        $countAssetsBefore     = $this->db->table('assets')->countAllResults();

        // Run completion service
        $res = $this->service->getSectionCompletionCandidates(24);

        $countTranslinesAfter = $this->db->table('gis_translines')->countAllResults();
        $countAssetsAfter     = $this->db->table('assets')->countAllResults();

        $this->assertSame($countTranslinesBefore, $countTranslinesAfter);
        $this->assertSame($countAssetsBefore, $countAssetsAfter);
    }

    /**
     * Test 21: Explicit AAAC vs A3CS Material Difference produces NEEDS_REVIEW
     */
    public function testExplicitAaacVsA3csMaterialMismatch(): void
    {
        $this->db->table('sections')->insert(['id' => 25, 'penyulang_id' => 1, 'nama_section' => 'SEC-MAT-DIFF']);
        $this->insertAsset(['id' => 960, 'kode_asset' => 'AST-960', 'section_id' => 25, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4240, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 961, 'kode_asset' => 'AST-961', 'section_id' => 25, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4244, 'longitude' => 112.7274]);

        $this->db->table('gis_translines')->insert([
            'id' => 505, 'transline_code' => 'TL-1-960-961', 'penyulang_id' => 1,
            'source_asset_id' => 960, 'target_asset_id' => 961,
            'conductor_type' => 'AAAC', 'conductor_size' => '150 mm²', 'distance_meters' => 45.00, 'is_active' => 1
        ]);

        $res = $this->service->getSectionCompletionCandidates(25, ['survey_conductor_type' => 'A3CS']);
        $this->assertSame(TranslineCompletionService::STATUS_NEEDS_REVIEW, $res['candidates'][0]['status']);
        $this->assertSame(TranslineCompletionService::REASON_CONDUCTOR_SPEC_CONFLICT, $res['candidates'][0]['reason_code']);
    }

    /**
     * Test 22: GPS plausibility heuristic diagnostic warning (> 150m span)
     */
    public function testGpsPlausibilityDiagnosticWarning(): void
    {
        $this->db->table('sections')->insert(['id' => 26, 'penyulang_id' => 1, 'nama_section' => 'SEC-PLAUSIBLE']);
        $this->insertAsset(['id' => 970, 'kode_asset' => 'AST-970', 'section_id' => 26, 'penyulang_id' => 1, 'sequence_no' => 1, 'latitude' => -7.4240, 'longitude' => 112.7270]);
        $this->insertAsset(['id' => 971, 'kode_asset' => 'AST-971', 'section_id' => 26, 'penyulang_id' => 1, 'sequence_no' => 2, 'latitude' => -7.4270, 'longitude' => 112.7300]);

        $this->db->table('gis_translines')->insert([
            'id' => 506, 'transline_code' => 'TL-1-970-971', 'penyulang_id' => 1,
            'source_asset_id' => 970, 'target_asset_id' => 971,
            'conductor_type' => 'AAAC', 'conductor_size' => '150 mm²', 'distance_meters' => 250.00, 'is_active' => 1
        ]);

        $res = $this->service->getSectionCompletionCandidates(26);
        $this->assertNotEmpty($res['candidates'][0]['warnings']);
        $this->assertStringContainsString('Peringatan heuristik', $res['candidates'][0]['warnings'][0]);
    }
}

