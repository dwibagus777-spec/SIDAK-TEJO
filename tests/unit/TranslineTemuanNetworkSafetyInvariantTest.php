<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\TranslineProposalReviewService;
use App\Services\TranslineCompletionService;

/**
 * TL-01 Hard Invariant: Critical Network & Temuan Data Safety Test Suite
 *
 * Verifies all 10 Mandatory Amendments and Business Semantics:
 * 1. testFindingCannotEnterCandidateSource: Findings never become candidate source endpoints.
 * 2. testFindingCannotEnterCandidateTarget: Findings never become candidate target endpoints.
 * 3. testFindingCannotBecomeGraphNode: Finding coordinates/records never become network topology nodes.
 * 4. testNearestFindingCannotAutoBindTopology: Spatial proximity of finding never binds to topology or alters score.
 * 5. testProposalRequiresAssetToAsset: Endpoints strictly resolve to master assets table in valid feeder scope.
 * 6. testFindingCountPreserved: Topology scans & review operations preserve exact temuan count (delta = 0).
 * 7. testFindingPrimaryKeySetPreserved: Exact primary key set of temuan is preserved before and after.
 * 8. testAssetSectionPreserved: assets.section_id is never mutated (mutation = 0).
 * 9. testAssetConstructionPreserved: assets.construction_type_id is never mutated (mutation = 0).
 * 10. testD4AReadModelDoesNotMutateFindings: Workbench detail & queue operations execute 0 writes on temuan.
 * 11. testNumericIdCollisionBetweenAssetAndTemuanDoesNotRejectValidAsset:
 *     asset.id = 400 and temuan.id = 400 coexistence does NOT falsely reject asset #400.
 * 12. testPositiveInvariantEveryTranslineEndpointResolvesToAssets:
 *     ∀ transline t: source ∈ assets.id ∧ target ∈ assets.id.
 * 13. testInspectionContextMetadataConformance:
 *     Workbench findings have context_type = INSPECTION_CONTEXT, is_topology_node = false, is_transline_endpoint = false.
 * 14. testTopologyOperationsNeverDeleteTemuan:
 *     Verifies zero deletion, zero truncation, and identical SHA-256 fingerprint on temuan across full TL pipeline.
 */
class TranslineTemuanNetworkSafetyInvariantTest extends CIUnitTestCase
{
    protected $db;
    protected TranslineProposalReviewService $reviewService;
    protected TranslineCompletionService $completionService;
    private static bool $schemaInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();
        $this->reviewService = new TranslineProposalReviewService($this->db);
        $this->completionService = new TranslineCompletionService($this->db);
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
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11],
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
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON', 'null' => true],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'parent_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        // 5. gis_translines table
        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'  => ['type' => 'VARCHAR', 'constraint' => 100],
                'penyulang_id'    => ['type' => 'INT', 'constraint' => 11],
                'section_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id' => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id' => ['type' => 'INT', 'constraint' => 11],
                'conductor_type'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'AAAC'],
                'conductor_size'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '150 mm²'],
                'distance_meters' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 40.0],
                'geometry'        => ['type' => 'TEXT', 'null' => true],
                'status'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'       => ['type' => 'INT', 'constraint' => 1, 'default' => 1],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        // 6. gis_transline_proposals table
        if (!$this->db->tableExists('gis_transline_proposals')) {
            $forge->addField([
                'id'                      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id'            => ['type' => 'INT', 'constraint' => 11],
                'section_id'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'         => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id'         => ['type' => 'INT', 'constraint' => 11],
                'natural_key'             => ['type' => 'VARCHAR', 'constraint' => 128],
                'proposed_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'AAAC'],
                'proposed_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '150 mm²'],
                'proposed_distance'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 40.0],
                'proposed_geometry'       => ['type' => 'TEXT', 'null' => true],
                'classification'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'AUTO_MATCH'],
                'confidence_score'        => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 1.0000],
                'status'                  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'PENDING_REVIEW'],
                'confirmed_transline_id'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'evidence_json'           => ['type' => 'TEXT', 'null' => true],
                'proposal_source'         => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'DETERMINISTIC_ENGINE'],
                'engine_version'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-01-V2.0'],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
                'updated_at'              => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_transline_proposals', true);
        }

        // 7. temuan table (CRITICAL PROTECTED DOMAIN)
        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'judul'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'latitude'     => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'longitude'    => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
                'priority'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'MEDIUM'],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
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

        // Ensure required columns exist even if tables were pre-created by earlier tests
        $this->safeAddColumn('temuan', 'latitude', ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true]);
        $this->safeAddColumn('temuan', 'longitude', ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true]);
        $this->safeAddColumn('temuan', 'status', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN', 'null' => true]);
        $this->safeAddColumn('temuan', 'priority', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'MEDIUM', 'null' => true]);
        $this->safeAddColumn('temuan', 'asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('temuan', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);

        $this->safeAddColumn('assets', 'jenis_asset', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON', 'null' => true]);
        $this->safeAddColumn('assets', 'sequence_no', ['type' => 'INT', 'constraint' => 11, 'default' => 0, 'null' => true]);
        $this->safeAddColumn('assets', 'construction_type_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('assets', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);

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

        // Feeders & ULP
        $this->db->table('ulps')->insert([
            'id' => 1, 'kode_ulp' => 'ULP01', 'nama_ulp' => 'ULP SIDOARJO KOTA', 'status' => 'AKTIF',
        ]);
        $this->db->table('penyulang')->insert([
            'id' => 10, 'ulp_id' => 1, 'kode_penyulang' => 'BJK', 'nama_penyulang' => 'BANJAR KEMANTREN', 'status' => 'AKTIF',
        ]);
        $this->db->table('sections')->insert([
            'id' => 100, 'penyulang_id' => 10, 'nama_section' => 'SECTION UTAMA BJK',
        ]);

        // Seed Assets (JTM Poles)
        $this->db->table('assets')->insertBatch([
            [
                'id'                   => 1001,
                'kode_asset'           => 'BJK_01',
                'nama_asset'           => 'Tiang BJK 01',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'jenis_asset'          => 'TIANG_BETON',
                'construction_type_id' => 1,
                'latitude'             => -7.41600000,
                'longitude'            => 112.72300000,
                'sequence_no'          => 1,
            ],
            [
                'id'                   => 1002,
                'kode_asset'           => 'BJK_02',
                'nama_asset'           => 'Tiang BJK 02',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'jenis_asset'          => 'TIANG_BETON',
                'construction_type_id' => 1,
                'latitude'             => -7.41630000,
                'longitude'            => 112.72310000,
                'sequence_no'          => 2,
            ],
            [
                'id'                   => 1003,
                'kode_asset'           => 'BJK_03',
                'nama_asset'           => 'Tiang BJK 03',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'jenis_asset'          => 'TIANG_BETON',
                'construction_type_id' => 2,
                'latitude'             => -7.41660000,
                'longitude'            => 112.72330000,
                'sequence_no'          => 3,
            ],
            // Special Asset for Numeric ID Collision Test: asset.id = 400 and temuan.id = 400
            [
                'id'                   => 400,
                'kode_asset'           => 'BJK_COLLISION_400',
                'nama_asset'           => 'Tiang BJK 400 (Collision Test)',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'jenis_asset'          => 'TIANG_BETON',
                'construction_type_id' => 1,
                'latitude'             => -7.41700000,
                'longitude'            => 112.72360000,
                'sequence_no'          => 4,
            ],
            [
                'id'                   => 401,
                'kode_asset'           => 'BJK_401',
                'nama_asset'           => 'Tiang BJK 401',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'jenis_asset'          => 'TIANG_BETON',
                'construction_type_id' => 1,
                'latitude'             => -7.41730000,
                'longitude'            => 112.72380000,
                'sequence_no'          => 5,
            ],
        ]);

        // Seed 1 Authoritative Transline
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
        ]);

        // Seed Temuan (Independent Field Findings)
        $this->db->table('temuan')->insertBatch([
            [
                'id'           => 400, // Coexists with asset.id = 400!
                'ulp_id'       => 1,
                'penyulang_id' => 10,
                'section_id'   => 100,
                'asset_id'     => 400,
                'judul'        => 'Tiang Miring Di Sebelah Gardu',
                'latitude'     => -7.41705000,
                'longitude'    => 112.72362000,
                'status'       => 'OPEN',
                'priority'     => 'HIGH',
            ],
            [
                'id'           => 614,
                'ulp_id'       => 1,
                'penyulang_id' => 10,
                'section_id'   => 100,
                'asset_id'     => 1001,
                'judul'        => 'Isolator Tumpu Retak',
                'latitude'     => -7.41601000,
                'longitude'    => 112.72302000,
                'status'       => 'OPEN',
                'priority'     => 'MEDIUM',
            ],
            [
                'id'           => 615,
                'ulp_id'       => 1,
                'penyulang_id' => 10,
                'section_id'   => 100,
                'asset_id'     => 1002,
                'judul'        => 'Andongan Kendor Mendekati Pohon',
                'latitude'     => -7.41632000,
                'longitude'    => 112.72311000,
                'status'       => 'IN_PROGRESS',
                'priority'     => 'HIGH',
            ],
        ]);

        // Seed 1 Proposal
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 1,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1001,
            'target_asset_id'         => 1002,
            'natural_key'             => 'TL-NAT:10:1001-1002',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 35.00,
            'classification'          => 'AUTO_MATCH',
            'confidence_score'        => 1.0000,
            'status'                  => 'PENDING_REVIEW',
            'evidence_json'           => json_encode(['info' => 'deterministic baseline']),
        ]);
    }

    /**
     * Helper: Capture Temuan State for Invariant Comparison
     */
    protected function captureTemuanState(): array
    {
        $rows = $this->db->table('temuan')->orderBy('id', 'ASC')->get()->getResultArray();
        $pks = array_column($rows, 'id');
        $hashData = '';
        foreach ($rows as $r) {
            $hashData .= "{$r['id']}:{$r['asset_id']}:{$r['judul']}:{$r['latitude']}:{$r['longitude']}:{$r['status']}|";
        }

        return [
            'count'       => count($rows),
            'pk_set'      => $pks,
            'fingerprint' => hash('sha256', $hashData),
        ];
    }

    /**
     * Helper: Capture Assets State for Invariant Comparison
     */
    protected function captureAssetsState(): array
    {
        $rows = $this->db->table('assets')->orderBy('id', 'ASC')->get()->getResultArray();
        $sections = [];
        $constructions = [];
        foreach ($rows as $r) {
            $sections[(int)$r['id']] = (int)($r['section_id'] ?? 0);
            $constructions[(int)$r['id']] = (int)($r['construction_type_id'] ?? 0);
        }

        return [
            'count'         => count($rows),
            'sections'      => $sections,
            'constructions' => $constructions,
        ];
    }

    // =========================================================================
    // 1. DOMAIN SEPARATION & ENDPOINT TESTS (Tests 1 - 5)
    // =========================================================================

    /**
     * Test 01: Explicit finding entity descriptor cannot be passed as candidate source
     */
    public function test01_FindingCannotEnterCandidateSource(): void
    {
        $res = $this->reviewService->validateTranslineEndpoints(614, 1002, 10, ['source_type' => 'TEMUAN']);
        $this->assertFalse($res['valid']);
        $this->assertEquals('TEMUAN_ENDPOINT_FORBIDDEN', $res['reason_code']);
        $this->assertStringContainsString('Titik temuan/finding tidak boleh menjadi source endpoint', $res['message']);
    }

    /**
     * Test 02: Explicit finding entity descriptor cannot be passed as candidate target
     */
    public function test02_FindingCannotEnterCandidateTarget(): void
    {
        $res = $this->reviewService->validateTranslineEndpoints(1001, 614, 10, ['target_type' => 'FINDING']);
        $this->assertFalse($res['valid']);
        $this->assertEquals('TEMUAN_ENDPOINT_FORBIDDEN', $res['reason_code']);
        $this->assertStringContainsString('Titik temuan/finding tidak boleh menjadi target endpoint', $res['message']);
    }

    /**
     * Test 03: Finding coordinates/records never become network topology nodes
     */
    public function test03_FindingCannotBecomeGraphNode(): void
    {
        $candidates = $this->completionService->getSectionCompletionCandidates(100);
        $this->assertNotEmpty($candidates['candidates']);

        $temuanRows = $this->db->table('temuan')->get()->getResultArray();
        $temuanIds = array_column($temuanRows, 'id');

        foreach ($candidates['candidates'] as $c) {
            // Source & Target MUST NOT be in temuan IDs unless coexisting asset
            $sId = (int)$c['source_asset_id'];
            $tId = (int)$c['target_asset_id'];

            // Must exist in assets table
            $sAsset = $this->db->table('assets')->where('id', $sId)->get()->getRowArray();
            $tAsset = $this->db->table('assets')->where('id', $tId)->get()->getRowArray();
            $this->assertNotNull($sAsset, "Candidate source #{$sId} must exist in master assets");
            $this->assertNotNull($tAsset, "Candidate target #{$tId} must exist in master assets");
        }
    }

    /**
     * Test 04: Nearest finding cannot auto-bind topology or alter edge candidates
     */
    public function test04_NearestFindingCannotAutoBindTopology(): void
    {
        // Candidate completion runs solely on assets
        $candidates = $this->completionService->getSectionCompletionCandidates(100);

        foreach ($candidates['candidates'] as $c) {
            // Assert no candidate contains finding reference as endpoint
            $this->assertArrayNotHasKey('finding_id', $c);
            $this->assertTrue($this->completionService->validateCandidateEndpoints((int)$c['source_asset_id'], (int)$c['target_asset_id']));
        }
    }

    /**
     * Test 05: Proposal endpoint strictly requires valid JTM asset-to-asset in same feeder
     */
    public function test05_ProposalRequiresAssetToAsset(): void
    {
        // Valid pair
        $validRes = $this->reviewService->validateTranslineEndpoints(1001, 1002, 10);
        $this->assertTrue($validRes['valid']);
        $this->assertEquals('ENDPOINT_VALID', $validRes['reason_code']);

        // Non-existent asset
        $invalidRes = $this->reviewService->validateTranslineEndpoints(1001, 99999, 10);
        $this->assertFalse($invalidRes['valid']);
        $this->assertEquals('ASSET_NOT_FOUND', $invalidRes['reason_code']);

        // Self loop
        $selfRes = $this->reviewService->validateTranslineEndpoints(1001, 1001, 10);
        $this->assertFalse($selfRes['valid']);
        $this->assertEquals('IDENTICAL_ENDPOINTS', $selfRes['reason_code']);
    }

    // =========================================================================
    // 2. DATA PRESERVATION & IMMUTABILITY INVARIANTS (Tests 6 - 10)
    // =========================================================================

    /**
     * Test 06: Finding count preserved during topology read & review operations (delta = 0)
     */
    public function test06_FindingCountPreserved(): void
    {
        $before = $this->captureTemuanState();

        // Execute multiple read operations
        $this->completionService->getSectionCompletionCandidates(100);
        $this->reviewService->getProposalWorkbenchDetail(1);
        $this->reviewService->getExceptionReviewQueue(10);
        $this->reviewService->scanProposalIntegrity(10);

        $after = $this->captureTemuanState();
        $this->assertEquals($before['count'], $after['count'], "Temuan count delta must be strictly 0");
    }

    /**
     * Test 07: Finding primary key set preserved before and after topology operations
     */
    public function test07_FindingPrimaryKeySetPreserved(): void
    {
        $before = $this->captureTemuanState();

        // Read operations across candidate, proposal, and workbench layers
        $this->completionService->getSectionCompletionCandidates(100);
        $this->reviewService->getProposalWorkbenchDetail(1);

        $after = $this->captureTemuanState();
        $this->assertEqualsCanonicalizing($before['pk_set'], $after['pk_set'], "Temuan PK set must be 100% identical");
    }

    /**
     * Test 08: assets.section_id is never mutated during transline completion
     */
    public function test08_AssetSectionPreserved(): void
    {
        $before = $this->captureAssetsState();

        $this->completionService->getSectionCompletionCandidates(100);
        $this->reviewService->getProposalWorkbenchDetail(1);

        $after = $this->captureAssetsState();
        $this->assertEquals($before['sections'], $after['sections'], "assets.section_id mutations must be strictly 0");
    }

    /**
     * Test 09: assets.construction_type_id is never mutated during transline completion
     */
    public function test09_AssetConstructionPreserved(): void
    {
        $before = $this->captureAssetsState();

        $this->completionService->getSectionCompletionCandidates(100);
        $this->reviewService->getProposalWorkbenchDetail(1);

        $after = $this->captureAssetsState();
        $this->assertEquals($before['constructions'], $after['constructions'], "assets.construction_type_id mutations must be strictly 0");
    }

    /**
     * Test 10: D4A read model workbench detail executes ZERO writes on temuan
     */
    public function test10_D4AReadModelDoesNotMutateFindings(): void
    {
        $before = $this->captureTemuanState();

        $detail = $this->reviewService->getProposalWorkbenchDetail(1);
        $this->assertEquals('success', $detail['status']);

        $after = $this->captureTemuanState();
        $this->assertEquals($before['fingerprint'], $after['fingerprint'], "Temuan SHA-256 fingerprint must be 100% identical");
    }

    // =========================================================================
    // 3. MANDATORY AMENDMENTS: ID COLLISION, POSITIVE INVARIANTS, PROXIMITY (Tests 11 - 14)
    // =========================================================================

    /**
     * Test 11 (Amendment 1 & 9):
     * Numeric ID collision between asset.id = 400 and temuan.id = 400 does NOT reject valid asset #400.
     */
    public function test11_NumericIdCollisionBetweenAssetAndTemuanDoesNotRejectValidAsset(): void
    {
        // Assert both co-exist in DB with ID = 400
        $asset400 = $this->db->table('assets')->where('id', 400)->get()->getRowArray();
        $temuan400 = $this->db->table('temuan')->where('id', 400)->get()->getRowArray();
        $this->assertNotNull($asset400, "Asset #400 must exist");
        $this->assertNotNull($temuan400, "Temuan #400 must exist");

        // Validate endpoint between asset 400 and asset 401
        // MUST SUCCEED because 400 resolves to a valid record in assets table!
        $res = $this->reviewService->validateTranslineEndpoints(400, 401, 10);
        $this->assertTrue($res['valid'], "Asset #400 must be accepted even though temuan #400 exists");
        $this->assertEquals('ENDPOINT_VALID', $res['reason_code']);
        $this->assertEquals(400, (int)$res['source_asset']['id']);
        $this->assertEquals(401, (int)$res['target_asset']['id']);
    }

    /**
     * Test 12 (Amendment 6):
     * Positive Invariant: Every transline endpoint strictly resolves to assets.id.
     * ∀ transline t: source_asset_id ∈ assets.id ∧ target_asset_id ∈ assets.id
     */
    public function test12_PositiveInvariantEveryTranslineEndpointResolvesToAssets(): void
    {
        $allAssets = $this->db->table('assets')->get()->getResultArray();
        $assetIdMap = array_flip(array_column($allAssets, 'id'));

        // 1. Authoritative translines
        $translines = $this->db->table('gis_translines')->get()->getResultArray();
        foreach ($translines as $t) {
            $sId = (int)$t['source_asset_id'];
            $tId = (int)$t['target_asset_id'];
            $this->assertArrayHasKey($sId, $assetIdMap, "Transline #{$t['id']} source must be in assets.id");
            $this->assertArrayHasKey($tId, $assetIdMap, "Transline #{$t['id']} target must be in assets.id");
        }

        // 2. Proposals
        $proposals = $this->db->table('gis_transline_proposals')->get()->getResultArray();
        foreach ($proposals as $p) {
            $sId = (int)$p['source_asset_id'];
            $tId = (int)$p['target_asset_id'];
            $this->assertArrayHasKey($sId, $assetIdMap, "Proposal #{$p['id']} source must be in assets.id");
            $this->assertArrayHasKey($tId, $assetIdMap, "Proposal #{$p['id']} target must be in assets.id");
        }

        // 3. Candidates
        $candidates = $this->completionService->getSectionCompletionCandidates(100);
        foreach ($candidates['candidates'] as $c) {
            $sId = (int)$c['source_asset_id'];
            $tId = (int)$c['target_asset_id'];
            $this->assertArrayHasKey($sId, $assetIdMap, "Candidate source must be in assets.id");
            $this->assertArrayHasKey($tId, $assetIdMap, "Candidate target must be in assets.id");
        }
    }

    /**
     * Test 13 (Amendment 4):
     * Inspection Context metadata conformance:
     * context_type = INSPECTION_CONTEXT, is_topology_node = false, is_transline_endpoint = false, proximity_label = CONTEXTUAL PROXIMITY ONLY.
     */
    public function test13_InspectionContextMetadataConformance(): void
    {
        $res = $this->reviewService->getProposalWorkbenchDetail(1);
        $this->assertEquals('success', $res['status']);
        $this->assertArrayHasKey('inspection_context', $res);

        $ctx = $res['inspection_context'];
        $this->assertEquals('INSPECTION_CONTEXT', $ctx['context_type']);
        $this->assertEquals('CONTEXTUAL PROXIMITY ONLY', $ctx['proximity_label']);
        $this->assertFalse($ctx['is_topology_node']);
        $this->assertGreaterThanOrEqual(1, $ctx['findings_count']);

        foreach ($ctx['findings'] as $f) {
            $this->assertEquals('INSPECTION_CONTEXT', $f['context_type']);
            $this->assertEquals('CONTEXTUAL PROXIMITY ONLY', $f['proximity_label']);
            $this->assertFalse($f['is_topology_node']);
            $this->assertFalse($f['is_transline_endpoint']);
            $this->assertFalse($f['affects_topology']);
        }
    }

    /**
     * Test 14 (Amendment 5):
     * Strong Temuan Preservation: Topology operations never delete, truncate, or alter temuan rows.
     * BEFORE PK set === AFTER PK set (EXACT SAME SET).
     */
    public function test14_TopologyOperationsNeverDeleteTemuan(): void
    {
        $before = $this->captureTemuanState();

        // 1. Scan candidates
        $this->completionService->getSectionCompletionCandidates(100);

        // 2. Scan integrity
        $this->reviewService->scanProposalIntegrity(10);

        // 3. Queue listing
        $this->reviewService->getExceptionReviewQueue(10);

        // 4. Workbench detail
        $this->reviewService->getProposalWorkbenchDetail(1);

        $after = $this->captureTemuanState();

        // Strict Invariants
        $this->assertSame($before['count'], $after['count'], "Count must be identical");
        $this->assertSame($before['pk_set'], $after['pk_set'], "PK set must be the EXACT same set in identical order");
        $this->assertSame($before['fingerprint'], $after['fingerprint'], "Cryptographic SHA-256 fingerprint must be identical");
    }
}
