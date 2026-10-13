<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\AssetIngestion;
use App\Services\AssetMassIngestionService;
use App\Services\AssetTopologyReconciliationService;
use App\Services\MassAssetCommitService;

/**
 * Class CrAsset01MassIngestionEngineTest
 *
 * Comprehensive unit test suite for CR-ASSET-01:
 * Mass Asset Data Ingestion, Reconciliation & Decoupled Topology Engine.
 */
class CrAsset01MassIngestionEngineTest extends CIUnitTestCase
{
    protected AssetIngestion $config;
    protected AssetMassIngestionService $ingestionService;
    protected AssetTopologyReconciliationService $topologyService;

    protected function setUp(): void
    {
        parent::setUp();
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();
        $this->ensureTablesExist($forge, $db);

        $this->config = new AssetIngestion();
        $this->ingestionService = new AssetMassIngestionService($db, $this->config);
        $this->topologyService = new AssetTopologyReconciliationService($db, $this->config);
    }

    protected function ensureTablesExist($forge, $db): void
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

        // penyulang
        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('penyulang', true);
            $feeders = [
                ['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI'],
                ['id' => 2, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-002', 'nama_penyulang' => 'ECCO'],
                ['id' => 3, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-003', 'nama_penyulang' => 'GADING KIRANA'],
                ['id' => 4, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-004', 'nama_penyulang' => 'GEMURUNG'],
                ['id' => 5, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-005', 'nama_penyulang' => 'GEDANGAN'],
            ];
            foreach ($feeders as $f) {
                $db->table('penyulang')->insert($f);
            }
        }

        // construction_types
        if (!$db->tableExists('construction_types')) {
            $forge->addField([
                'id'                => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'construction_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'construction_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('construction_types', true);
            $db->table('construction_types')->insert(['id' => 1, 'construction_code' => 'TM1', 'construction_name' => 'Tiang TM 1']);
            $db->table('construction_types')->insert(['id' => 2, 'construction_code' => 'GTT2', 'construction_name' => 'Gardu Trafo Tiang 2 Portal']);
        }

        // assets
        if (!$db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'JTM'],
                'ulp_id'               => ['type' => 'INTEGER', 'null' => true],
                'penyulang_id'         => ['type' => 'INTEGER', 'null' => true],
                'section_id'           => ['type' => 'INTEGER', 'null' => true],
                'construction_type_id' => ['type' => 'INTEGER', 'null' => true],
                'lokasi'               => ['type' => 'TEXT', 'null' => true],
                'latitude'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'longitude'            => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('assets', true);
        }

        // gis_translines
        if (!$db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INTEGER'],
                'source_asset_id'    => ['type' => 'INTEGER'],
                'target_asset_id'    => ['type' => 'INTEGER'],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'SYSTEM'],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'updated_at'         => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('gis_translines', true);
        }
    }

    /**
     * Test 1: Geodetic Bounding Box Policy Validation
     */
    public function testGeodeticValidationWithinAndOutsideBoundingBox()
    {
        // Valid Sidoarjo / East Java coordinate
        $valid = $this->ingestionService->validateGeodetic(-7.4242, 112.7270, 'EAST_JAVA');
        $this->assertTrue($valid['valid']);
        $this->assertEquals(-7.4242, $valid['latitude']);
        $this->assertEquals(112.7270, $valid['longitude']);

        // Out of bounds (Jakarta coordinates)
        $out = $this->ingestionService->validateGeodetic(-6.2088, 106.8456, 'EAST_JAVA');
        $this->assertFalse($out['valid']);
        $this->assertEquals('GEODETIC_OUT_OF_BOUNDS', $out['reason_code']);

        // Zero coordinate (0, 0)
        $zero = $this->ingestionService->validateGeodetic(0.0, 0.0, 'EAST_JAVA');
        $this->assertFalse($zero['valid']);
        $this->assertEquals('INVALID_GEODETIC_COORDINATES', $zero['reason_code']);

        // Non-numeric coordinate
        $nonNumeric = $this->ingestionService->validateGeodetic('INVALID_LAT', 112.7, 'EAST_JAVA');
        $this->assertFalse($nonNumeric['valid']);
        $this->assertEquals('INVALID_GEODETIC_COORDINATES', $nonNumeric['reason_code']);

        // Null / empty coordinate
        $empty = $this->ingestionService->validateGeodetic('', '', 'EAST_JAVA');
        $this->assertFalse($empty['valid']);
        $this->assertEquals('INVALID_GEODETIC_COORDINATES', $empty['reason_code']);
    }

    /**
     * Test 2: Construction Nomenclature Canonical Mapping
     */
    public function testConstructionNomenclatureCanonicalMapping()
    {
        $resTM1 = $this->ingestionService->resolveConstruction('TM1');
        $this->assertEquals('TM1', $resTM1['canonical_code']);

        $resGTT2T = $this->ingestionService->resolveConstruction('GTT2T');
        $this->assertEquals('GTT2', $resGTT2T['canonical_code']);

        $resEmpty = $this->ingestionService->resolveConstruction('0');
        $this->assertNull($resEmpty['canonical_code']);
        $this->assertNull($resEmpty['construction_type_id']);
    }

    /**
     * Test 3: 3-Tier Classification & Batch Duplicate Detection
     */
    public function testThreeTierClassificationAndBatchDuplicateDetection()
    {
        $batchSeenKeys = [];

        // Row 1: Valid NEW asset -> AUTO_ACCEPT
        $row1 = [
            'nama_asset'   => 'TEST_POLE_001',
            'penyulang'    => 'SIWALAN PANJI',
            'latitude'     => -7.4242,
            'longitude'    => 112.7270,
            'konstruksi'   => 'TM1',
            'jenis_asset'  => 'JTM',
            'lokasi'       => 'Jl. Raya Siwalan',
        ];

        $res1 = $this->ingestionService->processRow($row1, 2, $batchSeenKeys);
        $this->assertEquals('AUTO_ACCEPT', $res1['tier']);
        $this->assertEquals('INSERT', $res1['action']);
        $this->assertEquals('NEW_ASSET', $res1['reason_code']);

        // Row 2: Same key in the same CSV batch -> QUARANTINE (DUPLICATE_SOURCE_KEY)
        $res2 = $this->ingestionService->processRow($row1, 3, $batchSeenKeys);
        $this->assertEquals('QUARANTINE', $res2['tier']);
        $this->assertEquals('REJECT', $res2['action']);
        $this->assertEquals('DUPLICATE_SOURCE_KEY', $res2['reason_code']);

        // Row 3: Missing mandatory asset code -> QUARANTINE
        $row3 = [
            'nama_asset'   => '',
            'penyulang'    => 'SIWALAN PANJI',
            'latitude'     => -7.4242,
            'longitude'    => 112.7270,
        ];
        $res3 = $this->ingestionService->processRow($row3, 4, $batchSeenKeys);
        $this->assertEquals('QUARANTINE', $res3['tier']);
        $this->assertEquals('MISSING_MANDATORY_FIELDS', $res3['reason_code']);

        // Row 4: Invalid GPS -> QUARANTINE
        $row4 = [
            'nama_asset'   => 'TEST_POLE_002',
            'penyulang'    => 'SIWALAN PANJI',
            'latitude'     => -99.9999,
            'longitude'    => 112.7270,
        ];
        $res4 = $this->ingestionService->processRow($row4, 5, $batchSeenKeys);
        $this->assertEquals('QUARANTINE', $res4['tier']);
        $this->assertEquals('GEODETIC_OUT_OF_BOUNDS', $res4['reason_code']);
    }

    /**
     * Test 4: Decoupled Topology - Valid Assets Remain Isolated Nodes Without Synthetic Edges
     */
    public function testDecoupledTopologyAssetsRemainIsolatedNodesWithoutSyntheticEdges()
    {
        // 3 disconnected assets across different locations
        $assets = [
            [
                'payload' => [
                    'kode_asset'   => 'SPUR_ALPHA',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4200,
                    'longitude'    => 112.7200,
                ],
            ],
            [
                'payload' => [
                    'kode_asset'   => 'SPUR_BETA',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4300,
                    'longitude'    => 112.7300,
                ],
            ],
            [
                'payload' => [
                    'kode_asset'   => 'SPUR_GAMMA',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4400,
                    'longitude'    => 112.7400,
                ],
            ],
        ];

        $topologyResult = $this->topologyService->reconcileTopology($assets);

        // Invariant: ZERO synthetic edges created!
        $this->assertCount(0, $topologyResult['candidate_translines']);
        // All 3 assets remain valid ISOLATED_NODE
        $this->assertCount(3, $topologyResult['isolated_nodes']);
        $this->assertEquals(3, $topologyResult['summary']['isolated_assets']);
        $this->assertEquals(0, $topologyResult['summary']['candidate_translines_cnt']);
    }

    /**
     * Test 5: Topology Deterministic Sequential Chains (Level 2)
     */
    public function testTopologyDeterministicConsecutiveChains()
    {
        // Consecutive poles ~45 meters apart
        $assets = [
            [
                'payload' => [
                    'kode_asset'   => 'TESTFE_1',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4200000,
                    'longitude'    => 112.7200000,
                ],
            ],
            [
                'payload' => [
                    'kode_asset'   => 'TESTFE_2',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4203500, // ~40m away
                    'longitude'    => 112.7200000,
                ],
            ],
            [
                'payload' => [
                    'kode_asset'   => 'TESTFE_3',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4207000, // ~40m away
                    'longitude'    => 112.7200000,
                ],
            ],
        ];

        $topologyResult = $this->topologyService->reconcileTopology($assets);

        // 2 consecutive edges expected: 1->2 and 2->3
        $this->assertCount(2, $topologyResult['candidate_translines']);
        $this->assertEquals('TESTFE_1', $topologyResult['candidate_translines'][0]['source_asset_code']);
        $this->assertEquals('TESTFE_2', $topologyResult['candidate_translines'][0]['target_asset_code']);
        $this->assertEquals('TESTFE_2', $topologyResult['candidate_translines'][1]['source_asset_code']);
        $this->assertEquals('TESTFE_3', $topologyResult['candidate_translines'][1]['target_asset_code']);

        // All assets are connected, 0 isolated
        $this->assertCount(0, $topologyResult['isolated_nodes']);
    }

    /**
     * Test 6: Span Threshold Guardrails - Spans > 120m Held in Review
     */
    public function testTopologySpanGuardrailExceedsMaxDistance()
    {
        // Consecutive indices 1 and 2, but 300 meters apart!
        $assets = [
            [
                'payload' => [
                    'kode_asset'   => 'LONGSPAN_1',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4200000,
                    'longitude'    => 112.7200000,
                ],
            ],
            [
                'payload' => [
                    'kode_asset'   => 'LONGSPAN_2',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4230000, // ~330m away!
                    'longitude'    => 112.7200000,
                ],
            ],
        ];

        $topologyResult = $this->topologyService->reconcileTopology($assets);

        // Invariant: ZERO translines synthesized for excessive span
        $this->assertCount(0, $topologyResult['candidate_translines']);
        // Held in review
        $this->assertCount(1, $topologyResult['review_candidates']);
        $this->assertEquals('SPAN_EXCEEDS_MAX_METERS', $topologyResult['review_candidates'][0]['reason_code']);
        // Both remain isolated nodes
        $this->assertCount(2, $topologyResult['isolated_nodes']);
    }

    /**
     * Test 7: Sequence Gap Guardrail - Non-consecutive indices Held in Review
     */
    public function testTopologySequenceGapGuardrail()
    {
        // Gap in sequence: index 1 and index 5 (missing 2, 3, 4)
        $assets = [
            [
                'payload' => [
                    'kode_asset'   => 'GAPPOLE_1',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4200000,
                    'longitude'    => 112.7200000,
                ],
            ],
            [
                'payload' => [
                    'kode_asset'   => 'GAPPOLE_5',
                    'penyulang_id' => 1,
                    'latitude'     => -7.4203000,
                    'longitude'    => 112.7200000,
                ],
            ],
        ];

        $topologyResult = $this->topologyService->reconcileTopology($assets);

        // Invariant: Gap is NEVER bridged automatically
        $this->assertCount(0, $topologyResult['candidate_translines']);
        $this->assertCount(1, $topologyResult['review_candidates']);
        $this->assertEquals('SEQUENCE_GAP', $topologyResult['review_candidates'][0]['reason_code']);
        $this->assertCount(2, $topologyResult['isolated_nodes']);
    }

    /**
     * Test 8: Preflight Plan Generation and Cryptographic Integrity
     */
    public function testPlanGenerationAndTamperDetection()
    {
        $commitService = new MassAssetCommitService(null);

        $fakeBatch = [
            'file_name'          => 'unit_test_sample.csv',
            'total_rows_scanned' => 2,
            'auto_accept'        => [
                [
                    'tier'    => 'AUTO_ACCEPT',
                    'action'  => 'INSERT',
                    'payload' => [
                        'kode_asset'   => 'PLAN_TEST_1',
                        'nama_asset'   => 'PLAN_TEST_1',
                        'penyulang_id' => 1,
                        'latitude'     => -7.4200,
                        'longitude'    => 112.7200,
                    ],
                ],
            ],
            'auto_review' => [],
            'quarantine'  => [],
        ];

        $fakeTopology = [
            'candidate_translines' => [],
            'isolated_nodes'       => [
                ['asset_code' => 'PLAN_TEST_1', 'status' => 'ISOLATED_NODE'],
            ],
            'review_candidates'    => [],
        ];

        $plan = $commitService->generatePlan([$fakeBatch], $fakeTopology);

        $this->assertNotEmpty($plan['plan_id']);
        $this->assertNotEmpty($plan['fingerprint']);
        $this->assertFileExists($plan['plan_file']);

        // Verify SHA-256 fingerprint matches payload
        $content = json_decode(file_get_contents($plan['plan_file']), true);
        $this->assertEquals($plan['fingerprint'], $content['fingerprint']);

        // Tamper test: Alter file content without updating fingerprint
        $tampered = $content;
        $tampered['summary']['auto_accept_assets'] = 99999;
        $tamperedPath = $plan['plan_file'] . '.tampered.json';
        file_put_contents($tamperedPath, json_encode($tampered));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PLAN INTEGRITY COMPROMISED');
        $commitService->executePlan($tamperedPath);

        // Cleanup
        if (file_exists($tamperedPath)) unlink($tamperedPath);
        if (file_exists($plan['plan_file'])) unlink($plan['plan_file']);
    }

    /**
     * Test 9: Identity Conflict - Same asset code, coordinates differ > 1.0m -> AUTO_REVIEW (HOLD_IDENTITY_CONFLICT)
     */
    public function testIdentityConflictHoldWhenCoordinatesDiffer()
    {
        $db = \Config\Database::connect();
        // Insert existing asset in DB
        $db->table('assets')->where('kode_asset', 'CONFLICT_POLE')->delete();
        $db->table('assets')->insert([
            'kode_asset'   => 'CONFLICT_POLE',
            'nama_asset'   => 'CONFLICT_POLE',
            'penyulang_id' => 1,
            'latitude'     => '-7.4200000',
            'longitude'    => '112.7200000',
            'status'       => 'NORMAL',
        ]);

        $service = new AssetMassIngestionService($db, $this->config);
        $service->loadExistingAssetsForFeeders([1]);

        // Incoming row with same code but different coordinates (~15 meters away)
        $incoming = [
            'nama_asset' => 'CONFLICT_POLE',
            'penyulang'  => 'SIWALAN PANJI',
            'latitude'   => -7.4201300, // ~15m away
            'longitude'  => 112.7200000,
        ];

        $batchKeys = [];
        $result = $service->processRow($incoming, 2, $batchKeys);

        $this->assertEquals('AUTO_REVIEW', $result['tier']);
        $this->assertEquals('HOLD_IDENTITY_CONFLICT', $result['action']);
        $this->assertEquals('HOLD_IDENTITY_CONFLICT', $result['reason_code']);
        $this->assertGreaterThan(1.0, $result['delta_meters']);

        // Clean up
        $db->table('assets')->where('kode_asset', 'CONFLICT_POLE')->delete();
    }

    /**
     * Test 10: Identity Enrich - Same code & coordinates, missing construction in DB -> AUTO_ACCEPT (ENRICH)
     */
    public function testIdentityEnrichWhenMetadataMissingInDb()
    {
        $db = \Config\Database::connect();
        $db->table('assets')->where('kode_asset', 'ENRICH_POLE')->delete();
        $db->table('assets')->insert([
            'kode_asset'           => 'ENRICH_POLE',
            'nama_asset'           => 'ENRICH_POLE',
            'penyulang_id'         => 1,
            'construction_type_id' => null, // missing in DB!
            'lokasi'               => null,
            'latitude'             => '-7.4200000',
            'longitude'            => '112.7200000',
            'status'               => 'NORMAL',
        ]);

        $service = new AssetMassIngestionService($db, $this->config);
        $service->loadExistingAssetsForFeeders([1]);

        $incoming = [
            'nama_asset' => 'ENRICH_POLE',
            'penyulang'  => 'SIWALAN PANJI',
            'latitude'   => -7.4200000,
            'longitude'  => 112.7200000,
            'konstruksi' => 'TM1',
            'lokasi'     => 'Gardu Distribusi A',
        ];

        $batchKeys = [];
        $result = $service->processRow($incoming, 2, $batchKeys);

        $this->assertEquals('AUTO_ACCEPT', $result['tier']);
        $this->assertEquals('ENRICH', $result['action']);
        $this->assertEquals('ENRICH_EXISTING_ASSET', $result['reason_code']);
        $this->assertEquals(1, $result['payload']['enrich_fields']['construction_type_id']);

        // Clean up
        $db->table('assets')->where('kode_asset', 'ENRICH_POLE')->delete();
    }

    /**
     * Test 11: Spatial Collision - Different asset code at exact same coordinate on same feeder -> AUTO_REVIEW
     */
    public function testSpatialCollisionWithExistingAssetAtSameCoordinates()
    {
        $db = \Config\Database::connect();
        $db->table('assets')->where('kode_asset', 'ORIGINAL_POLE')->delete();
        $db->table('assets')->insert([
            'kode_asset'   => 'ORIGINAL_POLE',
            'nama_asset'   => 'ORIGINAL_POLE',
            'penyulang_id' => 1,
            'latitude'     => '-7.4250000',
            'longitude'    => '112.7250000',
            'status'       => 'NORMAL',
        ]);

        $service = new AssetMassIngestionService($db, $this->config);
        $service->loadExistingAssetsForFeeders([1]);

        $incoming = [
            'nama_asset' => 'COLLIDING_POLE_XYZ',
            'penyulang'  => 'SIWALAN PANJI',
            'latitude'   => -7.4250000, // exact same coordinate!
            'longitude'  => 112.7250000,
        ];

        $batchKeys = [];
        $result = $service->processRow($incoming, 2, $batchKeys);

        $this->assertEquals('AUTO_REVIEW', $result['tier']);
        $this->assertEquals('HOLD_SPATIAL_COLLISION', $result['action']);
        $this->assertEquals('HOLD_SPATIAL_COLLISION', $result['reason_code']);

        // Clean up
        $db->table('assets')->where('kode_asset', 'ORIGINAL_POLE')->delete();
    }

    /**
     * Test 12: Atomic Plan Execution and Persistence
     */
    public function testAtomicPlanExecutionAndPersistence()
    {
        $db = \Config\Database::connect();
        $commitService = new MassAssetCommitService($db);

        // Prepare test batch
        $testCode1 = 'ATOM_TEST_1';
        $testCode2 = 'ATOM_TEST_2';

        $db->table('assets')->whereIn('kode_asset', [$testCode1, $testCode2])->delete();

        $batch = [
            'file_name'          => 'atomic_test.csv',
            'total_rows_scanned' => 2,
            'auto_accept'        => [
                [
                    'tier'    => 'AUTO_ACCEPT',
                    'action'  => 'INSERT',
                    'payload' => [
                        'kode_asset'           => $testCode1,
                        'nama_asset'           => $testCode1,
                        'jenis_asset'          => 'JTM',
                        'penyulang_id'         => 1,
                        'ulp_id'               => 1,
                        'construction_type_id' => 1,
                        'lokasi'               => 'Test Loc 1',
                        'latitude'             => '-7.4200000',
                        'longitude'            => '112.7200000',
                    ],
                ],
                [
                    'tier'    => 'AUTO_ACCEPT',
                    'action'  => 'INSERT',
                    'payload' => [
                        'kode_asset'           => $testCode2,
                        'nama_asset'           => $testCode2,
                        'jenis_asset'          => 'JTM',
                        'penyulang_id'         => 1,
                        'ulp_id'               => 1,
                        'construction_type_id' => 1,
                        'lokasi'               => 'Test Loc 2',
                        'latitude'             => '-7.4203500',
                        'longitude'            => '112.7200000',
                    ],
                ],
            ],
            'auto_review' => [],
            'quarantine'  => [],
        ];

        $topology = [
            'candidate_translines' => [
                [
                    'transline_code'     => "TL-{$testCode1}-{$testCode2}",
                    'penyulang_id'       => 1,
                    'source_asset_code'  => $testCode1,
                    'target_asset_code'  => $testCode2,
                    'distance_meters'    => 39.0,
                    'conductor_type'     => 'AAAC',
                    'conductor_size'     => '150 mm²',
                    'geometry'           => 'LINESTRING (112.7200000 -7.4200000, 112.7200000 -7.4203500)',
                ],
            ],
            'isolated_nodes'    => [],
            'review_candidates' => [],
        ];

        $plan = $commitService->generatePlan([$batch], $topology);
        $this->assertFileExists($plan['plan_file']);

        // Execute Plan
        $receipt = $commitService->executePlan($plan['plan_file']);

        $this->assertEquals('COMMITTED', $receipt['status']);
        $this->assertEquals(2, $receipt['mutations']['assets_inserted']);
        $this->assertEquals(1, $receipt['mutations']['translines_inserted']);
        $this->assertFileExists($receipt['receipt_file']);

        // Verify rows in DB
        $a1 = $db->table('assets')->where('kode_asset', $testCode1)->get()->getRowArray();
        $a2 = $db->table('assets')->where('kode_asset', $testCode2)->get()->getRowArray();
        $this->assertNotNull($a1);
        $this->assertNotNull($a2);

        $tl = $db->table('gis_translines')->where('source_asset_id', $a1['id'])->where('target_asset_id', $a2['id'])->get()->getRowArray();
        $this->assertNotNull($tl);
        $this->assertEquals(1, $tl['is_active']);

        // Clean up test rows and receipt/plan files
        $db->table('gis_translines')->where('id', $tl['id'])->delete();
        $db->table('assets')->whereIn('kode_asset', [$testCode1, $testCode2])->delete();
        if (file_exists($plan['plan_file'])) unlink($plan['plan_file']);
        if (file_exists($receipt['receipt_file'])) unlink($receipt['receipt_file']);
    }

    /**
     * Test 13: CR-ASSET-01C Multi-Word Prefix & Feeder Context Sequence Reconciliation
     */
    public function testGadingKiranaMultiWordPrefixAndFeederContextSequenceParsing()
    {
        // 1. Direct parser verification with and without feeder context
        $p1 = $this->topologyService->parseAssetCodeSequence('GADING KIRANA_29', 'GADING KIRANA');
        $this->assertNotNull($p1);
        $this->assertEquals('GADINGKIRANA', $p1['prefix']);
        $this->assertEquals(29, $p1['index']);

        $p2 = $this->topologyService->parseAssetCodeSequence('GADING KIRANA_207');
        $this->assertNotNull($p2);
        $this->assertEquals('GADINGKIRANA', $p2['prefix']);
        $this->assertEquals(207, $p2['index']);

        // 2. Topology consecutive chain verification for multi-word feeder
        $assets = [
            [
                'payload' => [
                    'kode_asset'   => 'GADING KIRANA_1',
                    'penyulang_id' => 3, // GADING KIRANA
                    'latitude'     => -7.4200000,
                    'longitude'    => 112.7200000,
                ],
            ],
            [
                'payload' => [
                    'kode_asset'   => 'GADING KIRANA_2',
                    'penyulang_id' => 3,
                    'latitude'     => -7.4203500, // ~39m
                    'longitude'    => 112.7200000,
                ],
            ],
            [
                'payload' => [
                    'kode_asset'   => 'GADING KIRANA_3',
                    'penyulang_id' => 3,
                    'latitude'     => -7.4207000, // ~39m
                    'longitude'    => 112.7200000,
                ],
            ],
        ];

        $res = $this->topologyService->reconcileTopology($assets);

        $this->assertCount(2, $res['candidate_translines']);
        $this->assertEquals('GADING KIRANA_1', $res['candidate_translines'][0]['source_asset_code']);
        $this->assertEquals('GADING KIRANA_2', $res['candidate_translines'][0]['target_asset_code']);
        $this->assertEquals('GADING KIRANA_2', $res['candidate_translines'][1]['source_asset_code']);
        $this->assertEquals('GADING KIRANA_3', $res['candidate_translines'][1]['target_asset_code']);
        $this->assertCount(0, $res['isolated_nodes']);
    }
}
