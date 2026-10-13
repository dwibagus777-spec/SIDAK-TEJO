<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FaultTopologyAdapterService;

/**
 * Class FaultTopologyAdapterServiceTest
 *
 * CR-FL-01 Phase FL-01A: Read-Only Authoritative Topology Adapter
 * 10-Test Acceptance Suite enforcing:
 * 1. Anomaly detection via Topology Integrity Firewall (0 auto-repair)
 * 2. Linear feeder Dijkstra weighted distance traversal & intervals
 * 3. Branched feeder preserving distinct paths & branch points
 * 4. Strict root resolution hierarchy (Never guess from lowest sequence or GPS)
 * 5. Deterministic path_id (PATH_C{comp}_R{root}_T{leaf}) & canonical serialization
 * 6. Model fingerprint reproducibility across runs & Zero DB Mutation Firewall
 * 7. Production Feeder 19 (Gading Kirana - multi-component & isolated nodes)
 * 8. Production Feeder 1 (Siwalan Panji - large connected & branched tree)
 * 9. Production Feeder 4 (Gemurung - connected single main trunk)
 * 10. Production Feeder 15 (Banjar Kemantren - baseline seed isolated nodes)
 */
class FaultTopologyAdapterServiceTest extends CIUnitTestCase
{
    protected FaultTopologyAdapterService $adapterService;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect('default');
        $this->adapterService = new FaultTopologyAdapterService($this->db);
    }

    /**
     * Test 1: Topology Integrity Firewall - Validates clean edges and rejects anomalies
     */
    public function testTopologyIntegrityFirewallDetectsAnomalies(): void
    {
        $assets = [
            ['id' => 101, 'kode_asset' => 'POLE_A', 'penyulang_id' => 1, 'latitude' => -7.4200000, 'longitude' => 112.7200000],
            ['id' => 102, 'kode_asset' => 'POLE_B', 'penyulang_id' => 1, 'latitude' => -7.4204000, 'longitude' => 112.7200000],
            ['id' => 103, 'kode_asset' => 'POLE_FOREIGN', 'penyulang_id' => 2, 'latitude' => -7.4208000, 'longitude' => 112.7200000],
            ['id' => 104, 'kode_asset' => 'POLE_D', 'penyulang_id' => 1, 'latitude' => -7.4212000, 'longitude' => 112.7200000],
            ['id' => 105, 'kode_asset' => 'POLE_E', 'penyulang_id' => 1, 'latitude' => -7.4216000, 'longitude' => 112.7200000],
        ];

        $translines = [
            // Edge 1: Clean valid edge
            [
                'id' => 1, 'penyulang_id' => 1, 'source_asset_id' => 101, 'target_asset_id' => 102,
                'distance_meters' => 44.5, 'is_active' => 1,
                'geometry' => 'LINESTRING (112.7200000 -7.4200000, 112.7200000 -7.4204000)',
            ],
            // Edge 2: Orphan endpoint (source 999 does not exist)
            [
                'id' => 2, 'penyulang_id' => 1, 'source_asset_id' => 999, 'target_asset_id' => 102,
                'distance_meters' => 50.0, 'is_active' => 1,
                'geometry' => 'LINESTRING (112.7200000 -7.4200000, 112.7200000 -7.4204000)',
            ],
            // Edge 3: Cross-feeder contamination (source Feeder 1 -> target Feeder 2)
            [
                'id' => 3, 'penyulang_id' => 1, 'source_asset_id' => 101, 'target_asset_id' => 103,
                'distance_meters' => 88.0, 'is_active' => 1,
                'geometry' => 'LINESTRING (112.7200000 -7.4200000, 112.7200000 -7.4208000)',
            ],
            // Edge 4: Duplicate natural key (101:102 duplicate of Edge 1)
            [
                'id' => 4, 'penyulang_id' => 1, 'source_asset_id' => 102, 'target_asset_id' => 101,
                'distance_meters' => 44.5, 'is_active' => 1,
                'geometry' => 'LINESTRING (112.7200000 -7.4204000, 112.7200000 -7.4200000)',
            ],
            // Edge 5: Non-positive distance (<= 0)
            [
                'id' => 5, 'penyulang_id' => 1, 'source_asset_id' => 101, 'target_asset_id' => 104,
                'distance_meters' => -10.0, 'is_active' => 1,
                'geometry' => 'LINESTRING (112.7200000 -7.4200000, 112.7200000 -7.4212000)',
            ],
            // Edge 6: Corrupted WKT LineString
            [
                'id' => 6, 'penyulang_id' => 1, 'source_asset_id' => 104, 'target_asset_id' => 105,
                'distance_meters' => 45.0, 'is_active' => 1,
                'geometry' => 'POINT (112.7200000 -7.4200000)',
            ],
        ];

        $audit = $this->adapterService->runTopologyIntegrityFirewall($assets, $translines, 1);

        $this->assertFalse($audit['is_valid']);
        $this->assertCount(1, $audit['valid_edges']); // Only Edge 1 is valid
        $this->assertEquals(1, $audit['valid_edges'][1]['id']);

        $anomalyTypes = array_column($audit['anomalies'], 'type');
        $this->assertContains('ORPHAN_ENDPOINT', $anomalyTypes);
        $this->assertContains('CROSS_FEEDER_EDGE', $anomalyTypes);
        $this->assertContains('DUPLICATE_NATURAL_KEY', $anomalyTypes);
        $this->assertContains('NON_POSITIVE_LENGTH', $anomalyTypes);
        $this->assertContains('INVALID_GEOMETRY', $anomalyTypes);
    }

    /**
     * Test 2: Linear Feeder Topology Read Model & Weighted Dijkstra Cumulative Distance
     */
    public function testLinearFeederTopologyReadModel(): void
    {
        $nodeMap = [
            1 => ['id' => 1, 'kode_asset' => 'POLE_1', 'latitude' => -7.4200, 'longitude' => 112.7200],
            2 => ['id' => 2, 'kode_asset' => 'POLE_2', 'latitude' => -7.4204, 'longitude' => 112.7200],
            3 => ['id' => 3, 'kode_asset' => 'POLE_3', 'latitude' => -7.4209, 'longitude' => 112.7200],
            4 => ['id' => 4, 'kode_asset' => 'POLE_4', 'latitude' => -7.4215, 'longitude' => 112.7200],
        ];

        $compEdgeMap = [
            '1:2' => [
                'id' => 10, 'transline_code' => 'TL-1-2', 'source_asset_id' => 1, 'target_asset_id' => 2,
                'distance_meters' => 40.0, 'geometry' => 'LINESTRING (112.7200 -7.4200, 112.7200 -7.4204)',
            ],
            '2:3' => [
                'id' => 11, 'transline_code' => 'TL-2-3', 'source_asset_id' => 2, 'target_asset_id' => 3,
                'distance_meters' => 50.0, 'geometry' => 'LINESTRING (112.7200 -7.4204, 112.7200 -7.4209)',
            ],
            '3:4' => [
                'id' => 12, 'transline_code' => 'TL-3-4', 'source_asset_id' => 3, 'target_asset_id' => 4,
                'distance_meters' => 60.0, 'geometry' => 'LINESTRING (112.7200 -7.4209, 112.7200 -7.4215)',
            ],
        ];

        $adj = [
            1 => [2],
            2 => [1, 3],
            3 => [2, 4],
            4 => [3],
        ];

        $paths = $this->adapterService->buildWeightedDistanceChains(1, 1, $nodeMap, $compEdgeMap, $adj);

        $this->assertCount(1, $paths);
        $path = $paths[0];

        $this->assertEquals('PATH_C1_R1_T4', $path['path_id']);
        $this->assertEquals(1, $path['root_asset_id']);
        $this->assertEquals(4, $path['leaf_asset_id']);
        $this->assertEquals(150.0, $path['total_distance_m']);
        $this->assertEquals(0.150, $path['total_distance_km']);
        $this->assertCount(3, $path['segments']);

        $this->assertEquals(0.0, $path['segments'][0]['start_distance_m']);
        $this->assertEquals(40.0, $path['segments'][0]['end_distance_m']);

        $this->assertEquals(40.0, $path['segments'][1]['start_distance_m']);
        $this->assertEquals(90.0, $path['segments'][1]['end_distance_m']);

        $this->assertEquals(90.0, $path['segments'][2]['start_distance_m']);
        $this->assertEquals(150.0, $path['segments'][2]['end_distance_m']);
    }

    /**
     * Test 3: Branched Feeder Preserves Distinct Branch Paths with Dijkstra
     */
    public function testBranchedFeederPreservesDistinctPaths(): void
    {
        $nodeMap = [
            1 => ['id' => 1, 'kode_asset' => 'ROOT_POLE'],
            2 => ['id' => 2, 'kode_asset' => 'TRUNK_2'],
            3 => ['id' => 3, 'kode_asset' => 'TEE_NODE'],
            4 => ['id' => 4, 'kode_asset' => 'BRANCH_A_LEAF'],
            5 => ['id' => 5, 'kode_asset' => 'BRANCH_B_LEAF'],
        ];

        $compEdgeMap = [
            '1:2' => ['id' => 21, 'transline_code' => 'TL-1-2', 'source_asset_id' => 1, 'target_asset_id' => 2, 'distance_meters' => 50.0],
            '2:3' => ['id' => 22, 'transline_code' => 'TL-2-3', 'source_asset_id' => 2, 'target_asset_id' => 3, 'distance_meters' => 50.0],
            '3:4' => ['id' => 23, 'transline_code' => 'TL-3-4', 'source_asset_id' => 3, 'target_asset_id' => 4, 'distance_meters' => 40.0],
            '3:5' => ['id' => 24, 'transline_code' => 'TL-3-5', 'source_asset_id' => 3, 'target_asset_id' => 5, 'distance_meters' => 70.0],
        ];

        $adj = [
            1 => [2],
            2 => [1, 3],
            3 => [2, 4, 5],
            4 => [3],
            5 => [3],
        ];

        $paths = $this->adapterService->buildWeightedDistanceChains(1, 1, $nodeMap, $compEdgeMap, $adj);

        $this->assertCount(2, $paths);

        $pathIds = array_column($paths, 'path_id');
        $this->assertContains('PATH_C1_R1_T4', $pathIds);
        $this->assertContains('PATH_C1_R1_T5', $pathIds);

        foreach ($paths as $p) {
            if ($p['leaf_asset_id'] === 4) {
                $this->assertEquals(140.0, $p['total_distance_m']);
                $this->assertCount(3, $p['segments']);
            } elseif ($p['leaf_asset_id'] === 5) {
                $this->assertEquals(170.0, $p['total_distance_m']);
                $this->assertCount(3, $p['segments']);
            }
        }
    }

    /**
     * Test 4: Root Resolution Priority Enforcement (Never guess from lowest sequence or GPS)
     */
    public function testFeederRootResolutionPriorities(): void
    {
        $nodeMap = [
            1 => ['id' => 1, 'kode_asset' => 'POLE_01', 'jenis_asset' => 'JTM', 'section_id' => 999],
            2 => ['id' => 2, 'kode_asset' => 'RECLOSER_PANJI', 'jenis_asset' => 'RECLOSER', 'section_id' => null],
            3 => ['id' => 3, 'kode_asset' => 'POLE_03', 'jenis_asset' => 'JTM', 'section_id' => null],
        ];

        // Scenario A: Switch/Recloser present -> Priority B (Explicit Recloser Switch Anchor)
        $resA = $this->adapterService->resolveComponentRoot(1, [1, 2, 3], $nodeMap, [], null);
        $this->assertEquals('RESOLVED', $resA['status']);
        $this->assertEquals('EXPLICIT_RECLOSER_SWITCH_ANCHOR', $resA['method']);
        $this->assertEquals(2, $resA['root_asset_id']);
        $this->assertTrue($resA['is_authoritative']);

        // Scenario B: No recloser, no section match -> ROOT_UNRESOLVED (Invar 1: Never guess from lowest sequence!)
        $nodeMapB = [
            10 => ['id' => 10, 'kode_asset' => 'GADING_01', 'jenis_asset' => 'JTM', 'section_id' => null],
            11 => ['id' => 11, 'kode_asset' => 'GADING_02', 'jenis_asset' => 'JTM', 'section_id' => null],
        ];

        $resB = $this->adapterService->resolveComponentRoot(99, [10, 11], $nodeMapB, [], null);
        $this->assertEquals('ROOT_UNRESOLVED', $resB['status']);
        $this->assertEquals('NONE', $resB['method']);
        $this->assertNull($resB['root_asset_id']);
        $this->assertFalse($resB['is_authoritative']);
        $this->assertNotNull($resB['diagnostic_hint']);

        // Scenario C: Manual override specified by caller
        $resC = $this->adapterService->resolveComponentRoot(99, [10, 11], $nodeMapB, [], null, 10);
        $this->assertEquals('RESOLVED', $resC['status']);
        $this->assertEquals('MANUAL_ENGINEERING_OVERRIDE', $resC['method']);
        $this->assertEquals(10, $resC['root_asset_id']);
        $this->assertTrue($resC['is_authoritative']);
    }

    /**
     * Test 5: Deterministic path_id and Canonical Component Ordering
     */
    public function testDeterministicPathIdAndComponentSerialization(): void
    {
        $nodeMap = [
            10 => ['id' => 10, 'kode_asset' => 'ROOT'],
            20 => ['id' => 20, 'kode_asset' => 'MID'],
            30 => ['id' => 30, 'kode_asset' => 'BRANCH_Z'],
            40 => ['id' => 40, 'kode_asset' => 'BRANCH_A'],
        ];

        $compEdgeMap = [
            '10:20' => ['id' => 1, 'transline_code' => 'TL-1', 'source_asset_id' => 10, 'target_asset_id' => 20, 'distance_meters' => 10.0],
            '20:30' => ['id' => 2, 'transline_code' => 'TL-2', 'source_asset_id' => 20, 'target_asset_id' => 30, 'distance_meters' => 20.0],
            '20:40' => ['id' => 3, 'transline_code' => 'TL-3', 'source_asset_id' => 20, 'target_asset_id' => 40, 'distance_meters' => 30.0],
        ];

        $adj = [
            10 => [20],
            20 => [10, 30, 40],
            30 => [20],
            40 => [20],
        ];

        $paths = $this->adapterService->buildWeightedDistanceChains(2, 10, $nodeMap, $compEdgeMap, $adj);

        $this->assertCount(2, $paths);
        // Canonical ordering ensures PATH_C2_R10_T40 (BRANCH_A) precedes PATH_C2_R10_T30 (BRANCH_Z)
        $this->assertEquals('PATH_C2_R10_T40', $paths[0]['path_id']);
        $this->assertEquals('PATH_C2_R10_T30', $paths[1]['path_id']);
    }

    /**
     * Test 6: Model Fingerprint Determinism Across Runs & Database Zero-Mutation Firewall
     */
    public function testModelFingerprintDeterminismAndDatabaseZeroMutation(): void
    {
        // Snapshot DB counts before execution
        $assetsBefore     = $this->db->table('assets')->countAllResults();
        $translinesBefore = $this->db->table('gis_translines')->countAllResults();
        $temuanBefore     = $this->db->table('temuan')->countAllResults();

        // Execution Run #1
        $model1 = $this->adapterService->buildFeederTopologyModel(19);

        // Simulate tiny delay to prove generated_at timestamp does not affect fingerprint
        usleep(5000);

        // Execution Run #2
        $model2 = $this->adapterService->buildFeederTopologyModel(19);

        // Invariant: generated_at may differ or be equal, but model_fingerprint MUST BE BIT-FOR-BIT IDENTICAL
        $this->assertNotEmpty($model1['model_fingerprint']);
        $this->assertNotEmpty($model2['model_fingerprint']);
        $this->assertEquals($model1['model_fingerprint'], $model2['model_fingerprint'], 'Fingerprint must be 100% deterministic across multiple runs!');
        $this->assertEquals('FL-01A-1.0', $model1['adapter_version']);
        $this->assertEquals('FL-01A-1.0', $model2['adapter_version']);

        // Snapshot DB counts after execution
        $assetsAfter     = $this->db->table('assets')->countAllResults();
        $translinesAfter = $this->db->table('gis_translines')->countAllResults();
        $temuanAfter     = $this->db->table('temuan')->countAllResults();

        // Invariant: ZERO DB MUTATIONS! (0 INSERT, 0 UPDATE, 0 DELETE)
        $this->assertEquals($assetsBefore, $assetsAfter, 'Delta assets must be exactly 0');
        $this->assertEquals($translinesBefore, $translinesAfter, 'Delta translines must be exactly 0');
        $this->assertEquals($temuanBefore, $temuanAfter, 'Delta temuan must be exactly 0');
    }

    /**
     * Test 7: Production Feeder 19 (Gading Kirana) - Multi-component & Isolated nodes
     */
    public function testProductionFeederGadingKirana(): void
    {
        $model = $this->adapterService->buildFeederTopologyModel(19);

        $this->assertEquals(19, $model['feeder_id']);
        $this->assertEquals('GADING KIRANA', $model['feeder_name']);
        $this->assertEquals(207, $model['summary']['total_assets']);
        $this->assertEquals(184, $model['summary']['total_edges']);
        $this->assertEquals(2, $model['summary']['isolated_assets']);
        $this->assertEquals(23, $model['summary']['components_count']);

        // Integrity audit clean
        $this->assertTrue($model['integrity_audit']['is_valid']);
        $this->assertEquals(0, $model['integrity_audit']['anomalies_count']);

        // Substation Anchor
        $this->assertNotNull($model['source_anchor']);
        $this->assertEquals('GI BUDURAN', $model['source_anchor']['nama']);

        // Without explicit recloser/section in DB, status is honestly TOPOLOGY_ROOT_UNRESOLVED
        $this->assertEquals('TOPOLOGY_ROOT_UNRESOLVED', $model['status']);

        // With manual engineering override root
        $mainTrunk = $model['components'][0];
        $overrideAssetId = $mainTrunk['asset_ids'][0];
        $modelWithOverride = $this->adapterService->buildFeederTopologyModel(19, [
            'override_root_asset_id' => $overrideAssetId,
        ]);

        $this->assertEquals('TOPOLOGY_VALID', $modelWithOverride['status']);
        $overriddenComp = $modelWithOverride['components'][0];
        $this->assertEquals('RESOLVED', $overriddenComp['root_resolution']['status']);
        $this->assertEquals('MANUAL_ENGINEERING_OVERRIDE', $overriddenComp['root_resolution']['method']);
        $this->assertNotEmpty($overriddenComp['distance_paths']);
    }

    /**
     * Test 8: Production Feeder 1 (Siwalan Panji) - Large connected tree with branches
     */
    public function testProductionFeederSiwalanPanji(): void
    {
        $model = $this->adapterService->buildFeederTopologyModel(1);

        $this->assertEquals(1, $model['feeder_id']);
        $this->assertEquals('SIWALAN PANJI', $model['feeder_name']);
        $this->assertEquals(660, $model['summary']['total_assets']);
        $this->assertEquals(565, $model['summary']['total_edges']);
        $this->assertTrue($model['integrity_audit']['is_valid']);
        $this->assertEquals(0, $model['integrity_audit']['anomalies_count']);

        // Largest component in Siwalan Panji contains 61 assets and 60 edges
        $mainTrunk = $model['components'][0];
        $this->assertGreaterThanOrEqual(60, $mainTrunk['asset_count']);
        $this->assertGreaterThanOrEqual(59, $mainTrunk['edge_count']);
        $this->assertGreaterThan(2000.0, $mainTrunk['total_length_meters']);

        // Substation Anchor
        $this->assertEquals('GI BUDURAN', $model['source_anchor']['nama']);
        $this->assertNotEmpty($model['model_fingerprint']);
    }

    /**
     * Test 9: Production Feeder 4 (Gemurung) - Connected single main trunk
     */
    public function testProductionFeederGemurung(): void
    {
        $model = $this->adapterService->buildFeederTopologyModel(4);

        $this->assertEquals(4, $model['feeder_id']);
        $this->assertEquals('GEMURUNG', $model['feeder_name']);
        $this->assertEquals(229, $model['summary']['total_assets']);
        $this->assertEquals(221, $model['summary']['total_edges']);
        $this->assertTrue($model['integrity_audit']['is_valid']);
        $this->assertEquals(0, $model['integrity_audit']['anomalies_count']);

        // Main trunk of Gemurung contains 77 assets and 76 edges (~2.2km)
        $mainTrunk = $model['components'][0];
        $this->assertEquals(77, $mainTrunk['asset_count']);
        $this->assertEquals(76, $mainTrunk['edge_count']);
        $this->assertGreaterThan(2000.0, $mainTrunk['total_length_meters']);
        $this->assertNotEmpty($model['model_fingerprint']);
    }

    /**
     * Test 10: Production Feeder 15 (Banjar Kemantren) - Baseline seed topology with 0 translines
     */
    public function testProductionFeederBanjarKemantren(): void
    {
        $model = $this->adapterService->buildFeederTopologyModel(15);

        $this->assertEquals(15, $model['feeder_id']);
        $this->assertEquals('BANJAR KEMANTREN', $model['feeder_name']);
        $this->assertEquals(6, $model['summary']['total_assets']);
        $this->assertEquals(0, $model['summary']['total_edges']);
        $this->assertEquals(6, $model['summary']['isolated_assets']);
        $this->assertEquals(6, $model['summary']['components_count']);
        $this->assertTrue($model['integrity_audit']['is_valid']);
        $this->assertEquals(0, $model['integrity_audit']['anomalies_count']);

        // All 6 assets must be classified as ISOLATED_NODE (degree 0)
        foreach ($model['assets'] as $asset) {
            $this->assertEquals(0, $asset['degree']);
            $this->assertEquals('ISOLATED_NODE', $asset['role']);
        }

        $this->assertEquals('TOPOLOGY_ROOT_UNRESOLVED', $model['status']);
        $this->assertNotEmpty($model['model_fingerprint']);
    }
}
