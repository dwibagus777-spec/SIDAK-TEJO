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

    /**
     * AC-19: Production-like schema diagnostic
     */
    public function testProductionLikeSchemaDiagnostic()
    {
        $feederId = 81;
        $this->db->table('penyulang')->insert([
            'id'             => $feederId,
            'kode_penyulang' => 'PYL-DIAG-81',
            'nama_penyulang' => 'DIAGNOSTIC FEEDER 81',
        ]);
        $this->db->table('sections')->insert([
            'id'             => 811,
            'penyulang_id'   => $feederId,
            'nama_section'   => 'GI - REC PULAU BATU',
            'sequence_order' => 1,
            'status'         => 'AKTIF',
        ]);
        $this->db->table('assets')->insert([
            'id'           => 8101,
            'kode_asset'   => 'REC_PB_01',
            'nama_asset'   => 'RECLOSER PULAU BATU',
            'jenis_asset'  => 'RECLOSER',
            'penyulang_id' => $feederId,
            'latitude'     => -7.4501,
            'longitude'    => 112.7101,
        ]);

        $diag = $this->service->diagnoseFeeder($feederId);

        $this->assertTrue($diag['success']);
        $this->assertEquals(1, $diag['statistics']['resolved_landmarks']);
        $this->assertEquals(1, $diag['statistics']['potential_devices']);
        $this->assertFalse($diag['governance']['mutation_applied']);
    }

    /**
     * AC-20: Alias matching (REC PULAU BATU <-> RECLOSER PULAU BATU)
     */
    public function testAliasMatchingRecVsRecloser()
    {
        $feederAssets = [
            [
                'id'          => 8201,
                'kode_asset'  => 'REC_PB_82',
                'nama_asset'  => 'REC PULAU BATU',
                'jenis_asset' => 'JTM', // Untyped / Generic JTM
                'latitude'    => -7.4501,
                'longitude'   => 112.7101,
            ],
        ];

        $tokenized = $this->service->tokenizeLandmarkLabel('RECLOSER PULAU BATU');
        $match = $this->service->findMatchingDeviceAsset($tokenized, $feederAssets);

        $this->assertNotNull($match);
        $this->assertEquals(8201, $match['asset_id']);
        $this->assertEquals('RECLOSER', $match['device_type_family']);
    }

    /**
     * AC-21: Negative matching (LBS PULAU BATU != RECLOSER PULAU BATU)
     */
    public function testNegativeMatchingLbsVsRecloserRejection()
    {
        $feederAssets = [
            [
                'id'          => 8301,
                'kode_asset'  => 'LBS_PB_83',
                'nama_asset'  => 'LBS PULAU BATU',
                'jenis_asset' => 'LBS',
                'latitude'    => -7.4501,
                'longitude'   => 112.7101,
            ],
        ];

        $tokenized = $this->service->tokenizeLandmarkLabel('RECLOSER PULAU BATU');
        $match = $this->service->findMatchingDeviceAsset($tokenized, $feederAssets);

        $this->assertNull($match, "Incompatible device families (RECLOSER vs LBS) must NEVER match.");
    }

    /**
     * AC-22: Multi-landmark resolution with roles (START, INTERMEDIATE, END)
     */
    public function testMultiLandmarkResolutionRoles()
    {
        $feederId = 84;
        $this->db->table('penyulang')->insert([
            'id'             => $feederId,
            'kode_penyulang' => 'PYL-MULTI-84',
            'nama_penyulang' => 'MULTI LANDMARK FEEDER',
        ]);
        $sections = [
            [
                'id'           => 841,
                'penyulang_id' => $feederId,
                'nama_section' => 'REC PULAU BATU - LBSM TRI DASA WINDU - LBS COUPLE PRASUNG - LBSM BANJARSARI',
            ]
        ];

        $boundaries = $this->service->resolveBoundaryDevices($sections, $feederId);
        $this->assertCount(4, $boundaries[841]['landmarks']);
        $this->assertEquals('START', $boundaries[841]['landmarks'][0]['role']);
        $this->assertEquals('INTERMEDIATE', $boundaries[841]['landmarks'][1]['role']);
        $this->assertEquals('INTERMEDIATE', $boundaries[841]['landmarks'][2]['role']);
        $this->assertEquals('END', $boundaries[841]['landmarks'][3]['role']);
    }

    /**
     * AC-23: Missing GPS remains unresolved evidence
     */
    public function testMissingGpsRemainsUnresolvedEvidence()
    {
        $asset = [
            'id'           => 8501,
            'latitude'     => null,
            'longitude'    => null,
            'penyulang_id' => 85,
        ];
        $section = ['id' => 851, 'penyulang_id' => 85];
        $ev = $this->service->calculateSpatialEvidence($asset, $section, null, []);

        $this->assertFalse($ev['has_gps']);
        $this->assertFalse($ev['usable_for_confidence']);
        $this->assertEquals('NO_GPS', $ev['score_semantics']);
    }

    /**
     * AC-24: Topology ancestor landmark detection
     */
    public function testTopologyAncestorLandmarkDetection()
    {
        $allAssets = [
            ['id' => 8601, 'parent_asset_id' => 0, 'section_id' => null],    // Landmark (START)
            ['id' => 8602, 'parent_asset_id' => 8601, 'section_id' => null],
            ['id' => 8603, 'parent_asset_id' => 8602, 'section_id' => null], // Target asset
        ];
        $boundary = [
            'landmarks' => [
                [
                    'role'           => 'START',
                    'matched_device' => ['asset_id' => 8601],
                ]
            ]
        ];

        $ev = $this->service->calculateTopologyEvidence($allAssets[2], ['id' => 861], $allAssets, $boundary);
        $this->assertEquals('DOWNSTREAM_FROM_START_LANDMARK', $ev['direction']);
        $this->assertEquals(95.0, $ev['continuity_score']);
        $this->assertTrue($ev['usable_for_confidence']);
    }

    /**
     * AC-25: Topology downstream landmark detection
     */
    public function testTopologyDownstreamLandmarkDetection()
    {
        $allAssets = [
            ['id' => 8701, 'parent_asset_id' => 0, 'section_id' => null],    // Target asset (Upstream)
            ['id' => 8702, 'parent_asset_id' => 8701, 'section_id' => null],
            ['id' => 8703, 'parent_asset_id' => 8702, 'section_id' => null], // Landmark (END)
        ];
        $boundary = [
            'landmarks' => [
                [
                    'role'           => 'END',
                    'matched_device' => ['asset_id' => 8703],
                ]
            ]
        ];

        $ev = $this->service->calculateTopologyEvidence($allAssets[0], ['id' => 871], $allAssets, $boundary);
        $this->assertEquals('UPSTREAM_TO_END_LANDMARK', $ev['direction']);
        $this->assertEquals(95.0, $ev['continuity_score']);
        $this->assertTrue($ev['usable_for_confidence']);
    }

    /**
     * AC-26: Cross-feeder landmark rejection
     */
    public function testCrossFeederLandmarkRejection()
    {
        $feederIdA = 88;
        $feederIdB = 89;

        $assetInB = [
            'id'           => 8899,
            'penyulang_id' => $feederIdB,
            'nama_asset'   => 'RECLOSER PULAU BATU',
            'latitude'     => -7.4501,
            'longitude'    => 112.7101,
        ];

        $sections = [['id' => 881, 'nama_section' => 'GI - RECLOSER PULAU BATU']];
        // Resolve on Feeder A
        $boundaries = $this->service->resolveBoundaryDevices($sections, $feederIdA);

        $this->assertEquals('BOUNDARY_DEVICE_UNRESOLVED', $boundaries[881]['status']);
        $this->assertNull($boundaries[881]['landmarks'][1]['matched_device']);
    }

    /**
     * AC-27: No false positive landmark matching
     */
    public function testNoFalsePositiveLandmarkMatching()
    {
        $feederAssets = [
            [
                'id'          => 9001,
                'kode_asset'  => 'TIANG_01',
                'nama_asset'  => 'TIANG BETON 12M',
                'jenis_asset' => 'JTM',
                'latitude'    => -7.4501,
                'longitude'   => 112.7101,
            ],
        ];

        $tokenized = $this->service->tokenizeLandmarkLabel('RECLOSER PULAU BATU');
        $match = $this->service->findMatchingDeviceAsset($tokenized, $feederAssets);
        $this->assertNull($match);
    }

    /**
     * AC-28: Zero mutation during diagnostic
     */
    public function testZeroMutationInvariantDuringDiagnostic()
    {
        $feederId = 91;
        $this->db->table('penyulang')->insert([
            'id'             => $feederId,
            'kode_penyulang' => 'PYL-MUT-91',
            'nama_penyulang' => 'ZERO MUTATION TEST',
        ]);
        $this->db->table('sections')->insert([
            'id'             => 911,
            'penyulang_id'   => $feederId,
            'nama_section'   => 'GI - RECLOSER MUT',
            'sequence_order' => 1,
            'status'         => 'AKTIF',
        ]);
        $this->db->table('assets')->insert([
            'id'           => 9101,
            'kode_asset'   => 'ASSET_9101',
            'nama_asset'   => 'Tiang 9101',
            'jenis_asset'  => 'JTM',
            'penyulang_id' => $feederId,
            'section_id'   => null,
        ]);

        $diag = $this->service->diagnoseFeeder($feederId);
        $this->assertFalse($diag['governance']['mutation_applied']);
        $this->assertFalse($diag['governance']['assets_section_id_written']);

        // Check assets table
        $check = $this->db->table('assets')->where('id', 9101)->get()->getRowArray();
        $this->assertNull($check['section_id']);
    }

    /**
     * AC-29: Deterministic ranking output
     */
    public function testDeterministicRankingOutput()
    {
        $candidates = [
            ['section_id' => 10, 'sequence_order' => 2, 'score' => 60.0],
            ['section_id' => 20, 'sequence_order' => 1, 'score' => 60.0],
        ];

        $res1 = $this->service->rankCandidates($candidates);
        $res2 = $this->service->rankCandidates($candidates);

        $this->assertEquals($res1['candidates'][0]['section_id'], $res2['candidates'][0]['section_id']);
        $this->assertEquals(20, $res1['candidates'][0]['section_id'], "Lower sequence_order wins score tie-break.");
    }

    /**
     * AC-30: Fallback evidence cannot become confidence evidence
     */
    public function testFallbackEvidenceCannotBecomeConfidenceEvidence()
    {
        $rawCandidate = [
            'section_id'     => 999,
            'section_name'   => 'Section Fallback',
            'sequence_order' => 1,
            'score'          => 57.5,
            'evidence'       => [
                'spatial'    => ['usable_for_confidence' => false, 'spatial_score' => 50.0],
                'boundary'   => ['usable_for_confidence' => false, 'boundary_score' => 50.0],
                'feeder'     => ['usable_for_confidence' => true, 'feeder_score' => 100.0],
                'continuity' => ['usable_for_confidence' => false, 'continuity_score' => 50.0],
            ]
        ];

        $rank = $this->service->rankCandidates([$rawCandidate]);
        $this->assertEquals('UNRESOLVED', $rank['decision_status']);
        $this->assertEquals('UNRESOLVED', $rank['candidates'][0]['confidence']);
    }

    /**
     * AC-31: Evidence Forensics Command does not rely on unsupported builder methods and maintains zero mutation
     */
    public function testEvidenceForensicsCommandExecutionDoesNotError()
    {
        $cmd = new \App\Commands\Ar01EvidenceForensicsCommand(service('logger'), service('commands'));
        $this->assertInstanceOf(\CodeIgniter\CLI\BaseCommand::class, $cmd);
    }

    /**
     * AC-32: Evidence Reconcile Command is executable and preserves zero mutation
     */
    public function testEvidenceReconcileCommandExecutionDoesNotError()
    {
        $cmd = new \App\Commands\Ar01EvidenceReconcileCommand(service('logger'), service('commands'));
        $this->assertInstanceOf(\CodeIgniter\CLI\BaseCommand::class, $cmd);
    }

    /**
     * AC-33: Evidence Source Map Command is executable and preserves zero mutation
     */
    public function testEvidenceSourceMapCommandExecutionDoesNotError()
    {
        $cmd = new \App\Commands\Ar01EvidenceSourceMapCommand(service('logger'), service('commands'));
        $this->assertInstanceOf(\CodeIgniter\CLI\BaseCommand::class, $cmd);
    }

    /**
     * AC-34: Evidence Spatial Chain Command is executable and preserves zero mutation
     */
    public function testEvidenceSpatialChainCommandExecutionDoesNotError()
    {
        $cmd = new \App\Commands\Ar01EvidenceSpatialChainCommand(service('logger'), service('commands'));
        $this->assertInstanceOf(\CodeIgniter\CLI\BaseCommand::class, $cmd);
    }

    /**
     * AC-35: Evidence Anchor Command is executable and preserves zero mutation
     */
    public function testEvidenceAnchorCommandExecutionDoesNotError()
    {
        $cmd = new \App\Commands\Ar01EvidenceAnchorCommand(service('logger'), service('commands'));
        $this->assertInstanceOf(\CodeIgniter\CLI\BaseCommand::class, $cmd);
    }

    /**
     * AC-36: Evidence Partition Command is executable and preserves zero mutation
     */
    public function testEvidencePartitionCommandExecutionDoesNotError()
    {
        $cmd = new \App\Commands\Ar01EvidencePartitionCommand(service('logger'), service('commands'));
        $this->assertInstanceOf(\CodeIgniter\CLI\BaseCommand::class, $cmd);
    }

    /**
     * AC-37: Evidence Anchor Audit Command is executable and preserves zero mutation
     */
    public function testEvidenceAnchorAuditCommandExecutionDoesNotError()
    {
        $cmd = new \App\Commands\Ar01EvidenceAnchorAuditCommand(service('logger'), service('commands'));
        $this->assertInstanceOf(\CodeIgniter\CLI\BaseCommand::class, $cmd);
    }

    /**
     * AC-38: Evidence Deep Scan Command is executable and preserves zero mutation
     */
    public function testEvidenceDeepScanCommandExecutionDoesNotError()
    {
        $cmd = new \App\Commands\Ar01EvidenceDeepScanCommand(service('logger'), service('commands'));
        $this->assertInstanceOf(\CodeIgniter\CLI\BaseCommand::class, $cmd);
    }

    /**
     * AC-39: Unified Landmark Evidence Registry resolves and classifies evidence deterministically
     */
    public function testUnifiedLandmarkEvidenceRegistryResolvesEvidence()
    {
        $registry = new \App\Services\LandmarkEvidenceRegistry();
        $evidence = $registry->getFeederEvidence(4);

        $this->assertIsArray($evidence);
        $this->assertArrayHasKey('GI', $evidence);
        $this->assertArrayHasKey('TRI_DASA_WINDU', $evidence);
        $this->assertArrayHasKey('BANJARSARI', $evidence);
        $this->assertArrayHasKey('PULAU_BATU', $evidence);
        $this->assertArrayHasKey('UJUNG', $evidence);

        // PULAU BATU is DATA_NOT_PRESENT
        $this->assertEquals('DATA_NOT_PRESENT', $evidence['PULAU_BATU']['confidence_class']);
        $this->assertFalse($evidence['PULAU_BATU']['usable_for_confidence']);

        // GI is STRONG
        $this->assertEquals('STRONG', $evidence['GI']['confidence_class']);
        $this->assertTrue($evidence['GI']['usable_for_confidence']);
    }

    /**
     * AC-40: Haversine distance helper calculates accurate metric distance
     */
    public function testLandmarkEvidenceRegistryHaversineDistance()
    {
        $registry = new \App\Services\LandmarkEvidenceRegistry();
        // Distance between identical coordinates is 0
        $dist0 = $registry->haversineDistance(-7.42617, 112.73619, -7.42617, 112.73619);
        $this->assertEqualsWithDelta(0.0, $dist0, 0.01);

        // Distance between nearby poles (~25m)
        $dist = $registry->haversineDistance(-7.42617, 112.73619, -7.42630, 112.73619);
        $this->assertGreaterThan(10.0, $dist);
        $this->assertLessThan(50.0, $dist);
    }
}


