<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SpatialSectionCandidateService;

/**
 * Unit Test Suite for AR-01 Phase 5G: Spatial Section Candidate Engine (Contract v1.0)
 * 
 * Verifies 11 Acceptance Criteria:
 * - AC-01: Zero Mutation (Strictly Read-Only)
 * - AC-02: Complete GPS Input Processing
 * - AC-03: Cross-Feeder Isolation (Zero contamination)
 * - AC-04: Multi-Criteria Evidence Breakdown
 * - AC-05: Top-3 Candidate Ranking
 * - AC-06: Confidence Stratification
 * - AC-07: Ambiguity Detection (Margin < 5%)
 * - AC-08: Deterministic Tie Handling
 * - AC-09: Missing GPS Graceful Handling
 * - AC-10: Evidence JSON Schema Compliance
 * - AC-11: Candidate != Assignment Invariant
 */
class SpatialSectionCandidateTest extends CIUnitTestCase
{
    protected $db;
    protected SpatialSectionCandidateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->service = new SpatialSectionCandidateService($this->db);

        $this->setupMockDatabaseSchema();
    }

    protected function setupMockDatabaseSchema(): void
    {
        // 1. Create or ensure penyulang
        if (!$this->db->tableExists('penyulang')) {
            $forge = \Config\Database::forge();
            $forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'kode_penyulang'  => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang'  => ['type' => 'VARCHAR', 'constraint' => 150],
                'is_active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        // 2. Create or ensure sections
        if (!$this->db->tableExists('sections')) {
            $forge = \Config\Database::forge();
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'penyulang_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'nama_section'   => ['type' => 'VARCHAR', 'constraint' => 150],
                'sequence_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'status'         => ['type' => 'ENUM', 'constraint' => ['AKTIF', 'NONAKTIF', 'ACTIVE'], 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        // 3. Create or ensure assets
        if (!$this->db->tableExists('assets')) {
            $forge = \Config\Database::forge();
            $forge->addField([
                'id'                         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'kode_asset'                 => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'                 => ['type' => 'VARCHAR', 'constraint' => 255],
                'jenis_asset'                => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'asset_type'                 => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'parent_asset_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'penyulang_id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'section_id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'latitude'                   => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'longitude'                  => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'field_sequence'             => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_resolution_method'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'UNRESOLVED'],
                'created_at'                 => ['type' => 'DATETIME', 'null' => true],
                'updated_at'                 => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'                 => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        } else {
            $forge = \Config\Database::forge();
            if (!$this->db->fieldExists('parent_asset_id', 'assets')) {
                try {
                    $forge->addColumn('assets', [
                        'parent_asset_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                    ]);
                } catch (\Throwable $e) {}
            }
            if (!$this->db->fieldExists('asset_type', 'assets')) {
                try {
                    $forge->addColumn('assets', [
                        'asset_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                    ]);
                } catch (\Throwable $e) {}
            }
            if (!$this->db->fieldExists('jenis_asset', 'assets')) {
                try {
                    $forge->addColumn('assets', [
                        'jenis_asset' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                    ]);
                } catch (\Throwable $e) {}
            }
        }
    }

    public function testHaversineDistanceCalculatesAccurately()
    {
        // Distance between two points in Sidoarjo (~1.1 km)
        $lat1 = -7.42539;
        $lon1 = 112.73040;
        $lat2 = -7.43500;
        $lon2 = 112.73040;

        $distance = $this->service->calculateHaversineDistance($lat1, $lon1, $lat2, $lon2);
        
        $this->assertGreaterThan(1000.0, $distance);
        $this->assertLessThan(1200.0, $distance);
    }

    public function testCrossFeederIsolationYieldsZeroScore()
    {
        $asset = [
            'id'           => 9001,
            'kode_asset'   => 'TEST_ASSET_FEEDER_1',
            'penyulang_id' => 1,
            'latitude'     => -7.42539,
            'longitude'    => 112.73040,
        ];

        $foreignSection = [
            'id'           => 101,
            'penyulang_id' => 2, // Different Feeder
            'nama_section' => 'FOREIGN SECTION',
        ];

        $scoreResult = $this->service->scoreCandidate($asset, $foreignSection, null, []);

        $this->assertEquals(0.0, $scoreResult['score']);
        $this->assertEquals('CROSS_FEEDER_CONTAMINATION_PREVENTED', $scoreResult['evidence']['error']);
    }

    public function testAmbiguityDetectionWhenMarginBelowThreshold()
    {
        $rawCandidates = [
            [
                'section_id'     => 14,
                'section_name'   => 'Section 14',
                'sequence_order' => 1,
                'score'          => 85.0,
                'evidence'       => [],
            ],
            [
                'section_id'     => 15,
                'section_name'   => 'Section 15',
                'sequence_order' => 2,
                'score'          => 83.5, // Margin = 1.76% (< 5.0%)
                'evidence'       => [],
            ],
        ];

        $ranked = $this->service->rankCandidates($rawCandidates);

        $this->assertTrue($ranked['is_ambiguous']);
        $this->assertLessThan(5.0, $ranked['margin_pct']);
        $this->assertEquals('AMBIGUOUS', $ranked['candidates'][0]['confidence']);
    }

    public function testHighConfidenceWhenMarginAboveThreshold()
    {
        $rawCandidates = [
            [
                'section_id'     => 14,
                'section_name'   => 'Section 14',
                'sequence_order' => 1,
                'score'          => 92.0,
                'evidence'       => [],
            ],
            [
                'section_id'     => 15,
                'section_name'   => 'Section 15',
                'sequence_order' => 2,
                'score'          => 70.0, // Margin = 23.91% (>= 10.0%)
                'evidence'       => [],
            ],
        ];

        $ranked = $this->service->rankCandidates($rawCandidates);

        $this->assertFalse($ranked['is_ambiguous']);
        $this->assertGreaterThanOrEqual(20.0, $ranked['margin_pct']);
        $this->assertEquals('HIGH', $ranked['candidates'][0]['confidence']);
    }

    public function testDeterministicTieHandling()
    {
        $rawCandidates = [
            [
                'section_id'     => 20,
                'section_name'   => 'Section 20',
                'sequence_order' => 2,
                'score'          => 80.0,
                'evidence'       => [],
            ],
            [
                'section_id'     => 10,
                'section_name'   => 'Section 10',
                'sequence_order' => 1, // Lower sequence order wins tie
                'score'          => 80.0,
                'evidence'       => [],
            ],
        ];

        $ranked1 = $this->service->rankCandidates($rawCandidates);
        $ranked2 = $this->service->rankCandidates(array_reverse($rawCandidates));

        $this->assertEquals($ranked1['candidates'][0]['section_id'], $ranked2['candidates'][0]['section_id']);
        $this->assertEquals(10, $ranked1['candidates'][0]['section_id']);
        $this->assertTrue($ranked1['is_ambiguous']);
    }

    public function testMissingGpsProducesUnresolvedDecision()
    {
        // Seed Feeder and Sections
        $feederId = 99;
        $this->db->table('penyulang')->insert([
            'id'             => $feederId,
            'kode_penyulang' => 'PYL-TEST-99',
            'nama_penyulang' => 'TEST FEEDER 99',
        ]);
        $this->db->table('sections')->insert([
            'id'             => 991,
            'penyulang_id'   => $feederId,
            'nama_section'   => 'GI - RECLOSER TEST',
            'sequence_order' => 1,
            'status'         => 'AKTIF',
        ]);

        // Seed Asset with NULL GPS
        $assetId = 8888;
        $this->db->table('assets')->insert([
            'id'           => $assetId,
            'kode_asset'   => 'ASSET_NO_GPS',
            'nama_asset'   => 'Tiang No GPS',
            'jenis_asset'  => 'JTM',
            'penyulang_id' => $feederId,
            'latitude'     => null,
            'longitude'    => null,
            'section_id'   => null,
        ]);

        $analysis = $this->service->analyzeAsset($assetId);

        $this->assertTrue($analysis['success']);
        $this->assertEquals('UNRESOLVED', $analysis['decision']['status']);
        $this->assertFalse($analysis['decision']['recommendation_allowed']);
        $this->assertFalse($analysis['recommendation_allowed']);
        $this->assertNull($analysis['recommended_section']);
        $this->assertNotNull($analysis['top_candidate']);
        $this->assertNull($analysis['asset_gps']['latitude']);
        $this->assertFalse($analysis['governance']['mutation_applied']);
    }

    public function testZeroMutationInvariantPreservesDatabaseIntegrity()
    {
        $feederId = 98;
        $this->db->table('penyulang')->insert([
            'id'             => $feederId,
            'kode_penyulang' => 'PYL-MUTATION-TEST',
            'nama_penyulang' => 'MUTATION TEST FEEDER',
        ]);
        $this->db->table('sections')->insert([
            'id'             => 981,
            'penyulang_id'   => $feederId,
            'nama_section'   => 'GI - UJUNG TEST',
            'sequence_order' => 1,
            'status'         => 'AKTIF',
        ]);

        $assetId = 7777;
        $this->db->table('assets')->insert([
            'id'           => $assetId,
            'kode_asset'   => 'TEST_MUTATION_ASSET',
            'nama_asset'   => 'Tiang Uji Zero Mutation',
            'jenis_asset'  => 'JTM',
            'penyulang_id' => $feederId,
            'latitude'     => -7.42539,
            'longitude'    => 112.73040,
            'section_id'   => null,
        ]);

        // Execute Analysis
        $analysis = $this->service->analyzeAsset($assetId);
        $batch = $this->service->analyzeFeeder($feederId);

        // Verify that assets.section_id is STILL NULL in database (Zero Mutation)
        $dbAsset = $this->db->table('assets')->where('id', $assetId)->get()->getRowArray();
        $this->assertNull($dbAsset['section_id']);
        $this->assertFalse($analysis['mutation_applied']);
        $this->assertFalse($batch['mutation_applied']);
    }

    public function testEvidenceJsonPayloadStructureCompliesWithContract()
    {
        $feederId = 97;
        $this->db->table('penyulang')->insert([
            'id'             => $feederId,
            'kode_penyulang' => 'PYL-JSON-97',
            'nama_penyulang' => 'JSON TEST FEEDER',
        ]);
        $this->db->table('sections')->insert([
            'id'             => 971,
            'penyulang_id'   => $feederId,
            'nama_section'   => 'GI - RECLOSER TEST',
            'sequence_order' => 1,
            'status'         => 'AKTIF',
        ]);

        $assetId = 6666;
        $this->db->table('assets')->insert([
            'id'           => $assetId,
            'kode_asset'   => 'ASSET_JSON_TEST',
            'nama_asset'   => 'Tiang JSON Test',
            'jenis_asset'  => 'JTM',
            'penyulang_id' => $feederId,
            'latitude'     => -7.42539,
            'longitude'    => 112.73040,
            'section_id'   => null,
        ]);

        $result = $this->service->analyzeAsset($assetId);

        $this->assertEquals('AR-01-SPATIAL-CANDIDATE', $result['engine']);
        $this->assertEquals('1.1', $result['contract_version']);
        $this->assertIsArray($result['section_candidates']);
        $this->assertNotEmpty($result['section_candidates']);
        $this->assertArrayHasKey('spatial', $result['section_candidates'][0]['evidence']);
        $this->assertArrayHasKey('boundary', $result['section_candidates'][0]['evidence']);
        $this->assertArrayHasKey('feeder', $result['section_candidates'][0]['evidence']);
        $this->assertArrayHasKey('continuity', $result['section_candidates'][0]['evidence']);
        $this->assertArrayHasKey('topology', $result['section_candidates'][0]['evidence']);
        $this->assertTrue($result['requires_human_verification']);
        $this->assertFalse($result['governance']['mutation_applied']);
    }

    public function testFeederIdResolutionResolvesExactPenyulang()
    {
        $feederId = 4;
        $this->db->table('penyulang')->insert([
            'id'             => $feederId,
            'kode_penyulang' => 'PYL-004',
            'nama_penyulang' => 'GEMURUNG',
        ]);

        $resolved = $this->service->resolveFeeder($feederId);

        $this->assertNotNull($resolved);
        $this->assertEquals(4, (int)$resolved['id']);
        $this->assertEquals('PYL-004', $resolved['kode_penyulang']);
        $this->assertEquals('GEMURUNG', $resolved['nama_penyulang']);
    }

    public function testNonexistentFeederReturnsNullWithoutSilentFallback()
    {
        $nonExistentId = 999999;
        $resolved = $this->service->resolveFeeder($nonExistentId);

        $this->assertNull($resolved, 'Resolving nonexistent feeder must return null and never fall back to feeder #1');
    }

    public function testAssetFeederIntegrityFilter()
    {
        $feederId = 4;
        $assetId = 3711;

        $this->db->table('assets')->insert([
            'id'           => $assetId,
            'kode_asset'   => 'GEMURUNG_16',
            'nama_asset'   => 'GEMURUNG_16',
            'jenis_asset'  => 'JTM',
            'penyulang_id' => $feederId,
            'latitude'     => -7.42539,
            'longitude'    => 112.73040,
            'section_id'   => null,
        ]);

        $this->db->table('sections')->insert([
            'id'             => 14,
            'penyulang_id'   => $feederId,
            'nama_section'   => 'GI - RECLOSER PULAU BATU',
            'sequence_order' => 1,
            'status'         => 'AKTIF',
        ]);

        $analysis = $this->service->analyzeAsset($assetId);

        $this->assertTrue($analysis['success']);
        $this->assertEquals(4, $analysis['feeder_id']);
        $this->assertEquals(3711, $analysis['asset_id']);
        $this->assertEquals('GEMURUNG_16', $analysis['kode_asset']);
    }

    public function testAc12BoundaryTokenizationAndAliasMatching()
    {
        $tok = $this->service->tokenizeLandmarkLabel('RECLOSER PULAU BATU');
        $this->assertEquals('RECLOSER', $tok['raw_device_type']);
        $this->assertEquals(['PULAU', 'BATU'], $tok['landmark_tokens']);
        $this->assertFalse($tok['is_endpoint']);

        $feederAssets = [
            [
                'id'         => 1001,
                'kode_asset' => 'REC_PULAU_BATU_01',
                'nama_asset' => 'REC PULAU BATU',
                'asset_type' => 'RECLOSER',
                'latitude'   => -7.42500,
                'longitude'  => 112.73000,
            ],
        ];

        $matched = $this->service->findMatchingDeviceAsset($tok, $feederAssets);
        $this->assertNotNull($matched);
        $this->assertEquals(1001, $matched['asset_id']);
        $this->assertEquals('RECLOSER', $matched['raw_device_type']);
        $this->assertEquals('RECLOSER', $matched['matched_device_type']);
        $this->assertEquals('EXACT_TOKEN', $matched['match_mode']);
    }

    public function testAc13MultiLandmarkSectionExtraction()
    {
        $feederId = 55;
        $sections = [
            [
                'id'           => 15,
                'penyulang_id' => $feederId,
                'nama_section' => 'RECLOSER PULAU BATU - LBSM TRI DASA WINDU - LBS COUPLE PERTIGAAN PRASUNG - LBSM BANJARSARI',
            ]
        ];

        $ac13Assets = [
            [
                'id'           => 2001,
                'kode_asset'   => 'REC_PB',
                'nama_asset'   => 'RECLOSER PULAU BATU',
                'jenis_asset'  => 'RECLOSER',
                'penyulang_id' => $feederId,
                'latitude'     => -7.42000,
                'longitude'    => 112.72000,
            ],
            [
                'id'           => 2002,
                'kode_asset'   => 'LBS_TDW',
                'nama_asset'   => 'LBSM TRI DASA WINDU',
                'jenis_asset'  => 'LBS',
                'penyulang_id' => $feederId,
                'latitude'     => -7.42200,
                'longitude'    => 112.72200,
            ],
            [
                'id'           => 2003,
                'kode_asset'   => 'LBS_PRASUNG',
                'nama_asset'   => 'LBS COUPLE PERTIGAAN PRASUNG',
                'jenis_asset'  => 'LBS',
                'penyulang_id' => $feederId,
                'latitude'     => -7.42400,
                'longitude'    => 112.72400,
            ],
            [
                'id'           => 2004,
                'kode_asset'   => 'LBS_BS',
                'nama_asset'   => 'LBSM BANJARSARI',
                'jenis_asset'  => 'LBS',
                'penyulang_id' => $feederId,
                'latitude'     => -7.42600,
                'longitude'    => 112.72600,
            ],
        ];

        foreach ($ac13Assets as $a) {
            $this->db->table('assets')->insert($a);
        }

        $boundaries = $this->service->resolveBoundaryDevices($sections, $feederId);
        $this->assertArrayHasKey(15, $boundaries);
        $secBound = $boundaries[15];

        $this->assertEquals('BOUNDARY_DEVICE_RESOLVED', $secBound['status']);
        $this->assertEquals(4, $secBound['resolved_devices_count']);
        $this->assertCount(4, $secBound['landmarks']);
        $this->assertEquals('START', $secBound['landmarks'][0]['role']);
        $this->assertEquals('INTERMEDIATE', $secBound['landmarks'][1]['role']);
        $this->assertEquals('INTERMEDIATE', $secBound['landmarks'][2]['role']);
        $this->assertEquals('END', $secBound['landmarks'][3]['role']);
    }

    public function testSyntheticControlledTopologyGraphFourStates()
    {
        $feederId = 77;
        $this->db->table('penyulang')->insert([
            'id'             => $feederId,
            'kode_penyulang' => 'PYL-SYNTH-77',
            'nama_penyulang' => 'SYNTHETIC TOPOLOGY TEST',
        ]);

        $this->db->table('sections')->insertBatch([
            [
                'id'             => 701,
                'penyulang_id'   => $feederId,
                'nama_section'   => 'GI - LBS TENGAH',
                'sequence_order' => 1,
                'status'         => 'AKTIF',
            ],
            [
                'id'             => 702,
                'penyulang_id'   => $feederId,
                'nama_section'   => 'LBS TENGAH - UJUNG',
                'sequence_order' => 2,
                'status'         => 'AKTIF',
            ],
        ]);

        // Topology:
        // GI -> Node A (7001, Lat -7.400) -> Node B (LBS TENGAH, 7002, Lat -7.410) -> Node C (7003, Lat -7.420) -> Node D (7004, Lat -7.430)
        // Asset X (7005, Lat -7.402, Parent=7001): In Section 701
        // Asset Y (7006, Lat -7.428, Parent=7003): In Section 702
        // Asset Z (7007, Lat -7.41005, Parent=7002): Equidistant boundary
        // Asset Q (7008, Lat NULL, Lon NULL): Missing GPS & topology
        $syntheticAssets = [
            [
                'id'               => 7001,
                'kode_asset'       => 'TIANG_A',
                'nama_asset'       => 'TIANG A',
                'jenis_asset'      => 'JTM',
                'penyulang_id'     => $feederId,
                'latitude'         => -7.40000,
                'longitude'        => 112.70000,
                'parent_asset_id'  => null,
                'section_id'       => null,
            ],
            [
                'id'               => 7002,
                'kode_asset'       => 'LBS_MID',
                'nama_asset'       => 'LBS TENGAH',
                'jenis_asset'      => 'LBS',
                'penyulang_id'     => $feederId,
                'latitude'         => -7.41000,
                'longitude'        => 112.70000,
                'parent_asset_id'  => 7001,
                'section_id'       => null,
            ],
            [
                'id'               => 7003,
                'kode_asset'       => 'TIANG_C',
                'nama_asset'       => 'TIANG C',
                'jenis_asset'      => 'JTM',
                'penyulang_id'     => $feederId,
                'latitude'         => -7.42000,
                'longitude'        => 112.70000,
                'parent_asset_id'  => 7002,
                'section_id'       => null,
            ],
            [
                'id'               => 7004,
                'kode_asset'       => 'TIANG_D',
                'nama_asset'       => 'TIANG D',
                'jenis_asset'      => 'JTM',
                'penyulang_id'     => $feederId,
                'latitude'         => -7.43000,
                'longitude'        => 112.70000,
                'parent_asset_id'  => 7003,
                'section_id'       => null,
            ],
            [
                'id'               => 7005,
                'kode_asset'       => 'ASSET_X',
                'nama_asset'       => 'ASSET X (SIDE A)',
                'jenis_asset'      => 'JTM',
                'penyulang_id'     => $feederId,
                'latitude'         => -7.40200,
                'longitude'        => 112.70000,
                'parent_asset_id'  => 7001,
                'section_id'       => null,
            ],
            [
                'id'               => 7006,
                'kode_asset'       => 'ASSET_Y',
                'nama_asset'       => 'ASSET Y (SIDE B)',
                'jenis_asset'      => 'JTM',
                'penyulang_id'     => $feederId,
                'latitude'         => -7.42800,
                'longitude'        => 112.70000,
                'parent_asset_id'  => 7003,
                'section_id'       => null,
            ],
            [
                'id'               => 7007,
                'kode_asset'       => 'ASSET_Z',
                'nama_asset'       => 'ASSET Z (BOUNDARY)',
                'jenis_asset'      => 'JTM',
                'penyulang_id'     => $feederId,
                'latitude'         => -7.41000,
                'longitude'        => 112.70000,
                'parent_asset_id'  => null,
                'section_id'       => null,
            ],
            [
                'id'               => 7008,
                'kode_asset'       => 'ASSET_Q',
                'nama_asset'       => 'ASSET Q (UNRESOLVED)',
                'jenis_asset'      => 'JTM',
                'penyulang_id'     => $feederId,
                'latitude'         => null,
                'longitude'        => null,
                'parent_asset_id'  => null,
                'section_id'       => null,
            ],
        ];

        foreach ($syntheticAssets as $sa) {
            $this->db->table('assets')->insert($sa);
        }

        // State 1: Asset X -> CLEAR Section 701 (Side A)
        $resX = $this->service->analyzeAsset(7005);
        $this->assertEquals(701, $resX['candidates'][0]['section_id']);
        $this->assertEquals('CLEAR', $resX['decision']['status']);
        $this->assertTrue($resX['decision']['recommendation_allowed']);
        $this->assertTrue($resX['recommendation_allowed']);
        $this->assertNotNull($resX['recommended_section']);
        $this->assertEquals(701, $resX['recommended_section']['section_id']);
        $this->assertFalse($resX['governance']['mutation_applied']);

        // State 2: Asset Y -> CLEAR Section 702 (Side B)
        $resY = $this->service->analyzeAsset(7006);
        $this->assertEquals(702, $resY['candidates'][0]['section_id']);
        $this->assertEquals('CLEAR', $resY['decision']['status']);
        $this->assertTrue($resY['decision']['recommendation_allowed']);
        $this->assertTrue($resY['recommendation_allowed']);
        $this->assertNotNull($resY['recommended_section']);
        $this->assertEquals(702, $resY['recommended_section']['section_id']);
        $this->assertFalse($resY['governance']['mutation_applied']);

        // State 3: Asset Z -> AMBIGUOUS (Equidistant directly on LBS TENGAH boundary)
        $resZ = $this->service->analyzeAsset(7007);
        $this->assertEquals('AMBIGUOUS', $resZ['decision']['status']);
        $this->assertFalse($resZ['decision']['recommendation_allowed']);
        $this->assertFalse($resZ['recommendation_allowed']);
        $this->assertNull($resZ['recommended_section']);
        $this->assertNotNull($resZ['top_candidate']);
        $this->assertLessThan(5.0, $resZ['decision']['margin_percent']);
        $this->assertFalse($resZ['governance']['mutation_applied']);

        // State 4: Asset Q -> UNRESOLVED (Missing GPS & Topology)
        $resQ = $this->service->analyzeAsset(7008);
        $this->assertEquals('UNRESOLVED', $resQ['decision']['status']);
        $this->assertFalse($resQ['decision']['recommendation_allowed']);
        $this->assertFalse($resQ['recommendation_allowed']);
        $this->assertNull($resQ['recommended_section']);
        $this->assertNotNull($resQ['top_candidate']);
        $this->assertFalse($resQ['governance']['mutation_applied']);
    }
}


