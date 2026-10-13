<?php

namespace Tests\Unit\Fault;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FaultDistanceTraversalService;
use App\Services\FaultTopologyAdapterService;

/**
 * Class FaultDistanceTraversalServiceTest
 *
 * CR-FL-01 Phase FL-01D: FTU Distance Traversal Engine Test Suite
 * 30-Test Acceptance Suite enforcing:
 * 1. Linear feeder Dijkstra cumulative distance intervals (40m + 50m + 60m = 150m)
 * 2. Shared-boundary rule: [start, end) for non-terminal, [start, end] for terminal
 * 3. Distance out of range (D > max path distance, no clamping, no snapping)
 * 4. Non-positive and zero distances rejected cleanly
 * 5. Root unresolved: zero fallback root guessing
 * 6. Isolated root: returns NO_PATH
 * 7. Branch before requested distance: SINGLE_PATH_MATCH (no false ambiguity)
 * 8. True BRANCH_AMBIGUITY when distance falls onto >= 2 distinct branch segments
 * 9. Preservation of all branch candidate hypotheses
 * 10. Branch where only one path reaches the distance (SINGLE_PATH_MATCH)
 * 11. Component isolation and topology gap handling
 * 12. Deterministic repeated execution (SHA-256 fingerprint reproducibility)
 * 13. Zero nearest-neighbor, zero GPS straight-line, zero sequence distance
 * 14. Zero synthetic translines
 * 15. Zero database mutation firewall
 * 16. Production topology tests across Gading Kirana, Siwalan Panji, Gemurung, Gedangan, Ecco
 */
class FaultDistanceTraversalServiceTest extends CIUnitTestCase
{
    protected FaultDistanceTraversalService $traversalService;
    protected FaultTopologyAdapterService $adapterService;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect('default');
        $this->adapterService = new FaultTopologyAdapterService($this->db);
        $this->traversalService = new FaultDistanceTraversalService($this->adapterService);
    }

    /**
     * Helper to build a synthetic linear topology model:
     * Node 1 (Root) -> Edge 1 (40m) -> Node 2 -> Edge 2 (50m) -> Node 3 -> Edge 3 (60m) -> Node 4 (Terminal)
     * Total distance = 150m
     */
    protected function createLinearMockTopology(): array
    {
        return [
            'adapter_version' => 'FL-01A-1.0',
            'status'          => 'TOPOLOGY_VALID',
            'feeder_id'       => 991,
            'feeder_code'     => 'PYL-TEST-LINEAR',
            'summary'         => ['total_assets' => 4, 'total_edges' => 3],
            'components'      => [
                [
                    'component_id'    => 1,
                    'is_main_trunk'   => true,
                    'asset_ids'       => [1, 2, 3, 4],
                    'edge_map'        => ['1:2' => ['id' => 101], '2:3' => ['id' => 102], '3:4' => ['id' => 103]],
                    'root_resolution' => [
                        'status'        => 'RESOLVED',
                        'method'        => 'EXPLICIT_UPSTREAM_ANCHOR',
                        'root_asset_id' => 1,
                    ],
                    'distance_paths'  => [
                        [
                            'path_id'          => 'PATH_C1_R1_T4',
                            'component_id'     => 1,
                            'root_asset_id'    => 1,
                            'leaf_asset_id'    => 4,
                            'total_distance_m' => 150.0,
                            'segments_count'   => 3,
                            'segments'         => [
                                [
                                    'edge_id'             => 101,
                                    'transline_code'      => 'TL-1-2',
                                    'upstream_asset_id'   => 1,
                                    'downstream_asset_id' => 2,
                                    'upstream_code'       => 'POLE_1',
                                    'downstream_code'     => 'POLE_2',
                                    'segment_length_m'    => 40.0,
                                    'start_distance_m'    => 0.0,
                                    'end_distance_m'      => 40.0,
                                ],
                                [
                                    'edge_id'             => 102,
                                    'transline_code'      => 'TL-2-3',
                                    'upstream_asset_id'   => 2,
                                    'downstream_asset_id' => 3,
                                    'upstream_code'       => 'POLE_2',
                                    'downstream_code'     => 'POLE_3',
                                    'segment_length_m'    => 50.0,
                                    'start_distance_m'    => 40.0,
                                    'end_distance_m'      => 90.0,
                                ],
                                [
                                    'edge_id'             => 103,
                                    'transline_code'      => 'TL-3-4',
                                    'upstream_asset_id'   => 3,
                                    'downstream_asset_id' => 4,
                                    'upstream_code'       => 'POLE_3',
                                    'downstream_code'     => 'POLE_4',
                                    'segment_length_m'    => 60.0,
                                    'start_distance_m'    => 90.0,
                                    'end_distance_m'      => 150.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Helper to build a synthetic branched topology model:
     * Node 1 (Root) -> Edge 1 (80m) -> Node 2 (Fork Point)
     * Fork:
     *   - Path A: Node 2 -> Edge 2 (70m) -> Node 3 (Terminal A, total 150m)
     *   - Path B: Node 2 -> Edge 3 (70m) -> Node 4 (Terminal B, total 150m)
     */
    protected function createBranchedMockTopology(): array
    {
        return [
            'adapter_version' => 'FL-01A-1.0',
            'status'          => 'TOPOLOGY_VALID',
            'feeder_id'       => 992,
            'feeder_code'     => 'PYL-TEST-BRANCH',
            'summary'         => ['total_assets' => 4, 'total_edges' => 3],
            'components'      => [
                [
                    'component_id'    => 1,
                    'is_main_trunk'   => true,
                    'asset_ids'       => [1, 2, 3, 4],
                    'edge_map'        => ['1:2' => ['id' => 201], '2:3' => ['id' => 202], '2:4' => ['id' => 203]],
                    'root_resolution' => [
                        'status'        => 'RESOLVED',
                        'method'        => 'EXPLICIT_UPSTREAM_ANCHOR',
                        'root_asset_id' => 1,
                    ],
                    'distance_paths'  => [
                        [
                            'path_id'          => 'PATH_C1_R1_T3',
                            'component_id'     => 1,
                            'root_asset_id'    => 1,
                            'leaf_asset_id'    => 3,
                            'total_distance_m' => 150.0,
                            'segments_count'   => 2,
                            'segments'         => [
                                [
                                    'edge_id'             => 201,
                                    'transline_code'      => 'TL-1-2',
                                    'upstream_asset_id'   => 1,
                                    'downstream_asset_id' => 2,
                                    'upstream_code'       => 'POLE_1',
                                    'downstream_code'     => 'POLE_2',
                                    'segment_length_m'    => 80.0,
                                    'start_distance_m'    => 0.0,
                                    'end_distance_m'      => 80.0,
                                ],
                                [
                                    'edge_id'             => 202,
                                    'transline_code'      => 'TL-2-3',
                                    'upstream_asset_id'   => 2,
                                    'downstream_asset_id' => 3,
                                    'upstream_code'       => 'POLE_2',
                                    'downstream_code'     => 'POLE_3',
                                    'segment_length_m'    => 70.0,
                                    'start_distance_m'    => 80.0,
                                    'end_distance_m'      => 150.0,
                                ],
                            ],
                        ],
                        [
                            'path_id'          => 'PATH_C1_R1_T4',
                            'component_id'     => 1,
                            'root_asset_id'    => 1,
                            'leaf_asset_id'    => 4,
                            'total_distance_m' => 150.0,
                            'segments_count'   => 2,
                            'segments'         => [
                                [
                                    'edge_id'             => 201,
                                    'transline_code'      => 'TL-1-2',
                                    'upstream_asset_id'   => 1,
                                    'downstream_asset_id' => 2,
                                    'upstream_code'       => 'POLE_1',
                                    'downstream_code'     => 'POLE_2',
                                    'segment_length_m'    => 80.0,
                                    'start_distance_m'    => 0.0,
                                    'end_distance_m'      => 80.0,
                                ],
                                [
                                    'edge_id'             => 203,
                                    'transline_code'      => 'TL-2-4',
                                    'upstream_asset_id'   => 2,
                                    'downstream_asset_id' => 4,
                                    'upstream_code'       => 'POLE_2',
                                    'downstream_code'     => 'POLE_4',
                                    'segment_length_m'    => 70.0,
                                    'start_distance_m'    => 80.0,
                                    'end_distance_m'      => 150.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Test 1: Linear Feeder Cumulative Distance 40m + 50m + 60m = 150m
     */
    public function testLinearFeederCumulativeDistance40_50_60(): void
    {
        $topo = $this->createLinearMockTopology();
        $path = $topo['components'][0]['distance_paths'][0];

        $this->assertEquals(150.0, $path['total_distance_m']);
        $this->assertEquals(3, count($path['segments']));
        $this->assertEquals(40.0, $path['segments'][0]['end_distance_m']);
        $this->assertEquals(90.0, $path['segments'][1]['end_distance_m']);
        $this->assertEquals(150.0, $path['segments'][2]['end_distance_m']);
    }

    /**
     * Test 2: Distance D = 20m (Falls cleanly in first edge [0, 40m))
     */
    public function testDistance20mInFirstEdge(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(20.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('SINGLE_PATH_MATCH', $res['status']);
        $this->assertEquals(101, $res['matched_edge_id']);
        $this->assertEquals(0.0, $res['distance_start_m']);
        $this->assertEquals(40.0, $res['distance_end_m']);
        $this->assertEquals(20.0, $res['normalized_distance_m']);
        $this->assertCount(1, $res['candidate_paths']);
        $this->assertEquals(20.0, $res['candidate_paths'][0]['offset_in_segment_m']);
        $this->assertEquals(0.5, $res['candidate_paths'][0]['segment_ratio']);
    }

    /**
     * Test 3: Shared Boundary Convention (D = 40m)
     * Non-terminal [0, 40) vs downstream [40, 90) -> MUST match Edge 102 (downstream edge preferred)
     */
    public function testDistance40mSharedBoundary(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(40.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('SINGLE_PATH_MATCH', $res['status']);
        $this->assertEquals(102, $res['matched_edge_id'], 'Shared vertex at 40m must select downstream Edge #102, NOT Edge #101');
        $this->assertEquals(40.0, $res['distance_start_m']);
        $this->assertEquals(90.0, $res['distance_end_m']);
        $this->assertCount(1, $res['candidate_paths']);
        $this->assertEquals(0.0, $res['candidate_paths'][0]['offset_in_segment_m']);
    }

    /**
     * Test 4: Distance D = 50m (Falls cleanly in second edge [40, 90m))
     */
    public function testDistance50mInSecondEdge(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('SINGLE_PATH_MATCH', $res['status']);
        $this->assertEquals(102, $res['matched_edge_id']);
        $this->assertEquals(10.0, $res['candidate_paths'][0]['offset_in_segment_m']);
        $this->assertEquals(0.2, $res['candidate_paths'][0]['segment_ratio']);
    }

    /**
     * Test 5: Distance D = 150m (Terminal Boundary [90, 150m] closed interval)
     */
    public function testDistance150mTerminalBoundary(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(150.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('SINGLE_PATH_MATCH', $res['status']);
        $this->assertEquals(103, $res['matched_edge_id']);
        $this->assertEquals(90.0, $res['distance_start_m']);
        $this->assertEquals(150.0, $res['distance_end_m']);
        $this->assertEquals(60.0, $res['candidate_paths'][0]['offset_in_segment_m']);
        $this->assertEquals(1.0, $res['candidate_paths'][0]['segment_ratio']);
    }

    /**
     * Test 6: Distance Beyond Total Path (D = 160m > 150m)
     * MUST return DISTANCE_OUT_OF_RANGE without clamping or snapping
     */
    public function testDistanceBeyondTotalPath(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(160.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('DISTANCE_OUT_OF_RANGE', $res['status']);
        $this->assertNull($res['matched_edge_id']);
        $this->assertEmpty($res['candidate_paths']);
        $this->assertEquals(150.0, $res['max_path_distance_m']);
    }

    /**
     * Test 7: Distance D = 0m
     */
    public function testDistanceZero(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(0.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('DISTANCE_NON_POSITIVE', $res['status']);
        $this->assertNull($res['matched_edge_id']);
    }

    /**
     * Test 8: Negative Distance (D = -10m)
     */
    public function testNegativeDistance(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(-10.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('DISTANCE_NON_POSITIVE', $res['status']);
        $this->assertNull($res['matched_edge_id']);
    }

    /**
     * Test 9: ROOT_UNRESOLVED Handling (Zero guessing)
     */
    public function testRootUnresolved(): void
    {
        $topo = $this->createLinearMockTopology();
        $topo['status'] = 'TOPOLOGY_ROOT_UNRESOLVED';
        $topo['components'][0]['root_resolution']['status'] = 'ROOT_UNRESOLVED';

        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('ROOT_UNRESOLVED', $res['status']);
        $this->assertNull($res['root_asset_id']);
        $this->assertNull($res['matched_edge_id']);
        $this->assertEmpty($res['candidate_paths']);
    }

    /**
     * Test 10: Isolated Root Node (degree 0)
     */
    public function testIsolatedRoot(): void
    {
        $topo = [
            'status'     => 'TOPOLOGY_VALID',
            'components' => [
                [
                    'component_id'    => 1,
                    'asset_ids'       => [1],
                    'edge_map'        => [],
                    'root_resolution' => [
                        'status'        => 'RESOLVED',
                        'root_asset_id' => 1,
                    ],
                    'distance_paths'  => [],
                ],
            ],
        ];

        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('NO_PATH', $res['status']);
        $this->assertNull($res['matched_edge_id']);
    }

    /**
     * Test 11: No Path Available in Component
     */
    public function testNoPath(): void
    {
        $topo = [
            'status'     => 'TOPOLOGY_VALID',
            'components' => [],
        ];

        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('NO_PATH', $res['status']);
    }

    /**
     * Test 12: Continuous Path Unambiguous Match
     */
    public function testContinuousPath(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(100.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('SINGLE_PATH_MATCH', $res['status']);
        $this->assertEquals(103, $res['matched_edge_id']);
        $this->assertEquals('PATH_C1_R1_T4', $res['path_id']);
    }

    /**
     * Test 13: Branch Occurring AFTER Requested Distance (Fork at 80m, D = 50m)
     * MUST return SINGLE_PATH_MATCH on the shared trunk (no branch ambiguity)
     */
    public function testBranchBeforeRequestedDistance(): void
    {
        $topo = $this->createBranchedMockTopology();
        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('SINGLE_PATH_MATCH', $res['status'], 'Distance on shared trunk before fork must be SINGLE_PATH_MATCH');
        $this->assertEquals(201, $res['matched_edge_id']);
        $this->assertCount(1, $res['candidate_paths']);
    }

    /**
     * Test 14: Distance exactly at fork point (D = 80m)
     * Fork point node 2 is shared vertex between trunk [0, 80) and branches [80, 150]
     * Boundary rule prefers downstream edges [80, 150] -> BRANCH_AMBIGUITY across both branches
     */
    public function testBranchAfterRequestedDistance(): void
    {
        $topo = $this->createBranchedMockTopology();
        $res = $this->traversalService->traverse(80.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('BRANCH_AMBIGUITY', $res['status']);
        $this->assertCount(2, $res['candidate_paths']);
        $edgeIds = array_column($res['candidate_paths'], 'edge_id');
        $this->assertContains(202, $edgeIds);
        $this->assertContains(203, $edgeIds);
    }

    /**
     * Test 15: True Branch Ambiguity (Fork at 80m, D = 120m)
     * Matches Edge 202 on Path A and Edge 203 on Path B
     */
    public function testTrueBranchAmbiguity(): void
    {
        $topo = $this->createBranchedMockTopology();
        $res = $this->traversalService->traverse(120.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('BRANCH_AMBIGUITY', $res['status']);
        $this->assertNull($res['matched_edge_id'], 'Matched edge must be null when ambiguous');
        $this->assertCount(2, $res['candidate_paths']);
    }

    /**
     * Test 16: Multiple Branch Hypotheses Preserved with Full Metadata
     */
    public function testMultipleBranchHypothesesPreserved(): void
    {
        $topo = $this->createBranchedMockTopology();
        $res = $this->traversalService->traverse(120.0, $topo, ['distance_unit' => 'M']);

        $paths = $res['candidate_paths'];
        $this->assertCount(2, $paths);

        $hyp1 = $paths[0];
        $hyp2 = $paths[1];

        $this->assertEquals('PATH_C1_R1_T3', $hyp1['path_id']);
        $this->assertEquals(202, $hyp1['edge_id']);
        $this->assertEquals(40.0, $hyp1['offset_in_segment_m']); // 120 - 80 = 40m

        $this->assertEquals('PATH_C1_R1_T4', $hyp2['path_id']);
        $this->assertEquals(203, $hyp2['edge_id']);
        $this->assertEquals(40.0, $hyp2['offset_in_segment_m']);
    }

    /**
     * Test 17: Branch with Only One Reachable Matching Path
     * Branch A terminates at 100m, Branch B reaches 150m. At D = 120m, only Branch B matches.
     */
    public function testBranchWithOnlyOneReachableMatchingPath(): void
    {
        $topo = $this->createBranchedMockTopology();
        // Shorten Path A to 100m total
        $topo['components'][0]['distance_paths'][0]['total_distance_m'] = 100.0;
        $topo['components'][0]['distance_paths'][0]['segments'][1]['segment_length_m'] = 20.0;
        $topo['components'][0]['distance_paths'][0]['segments'][1]['end_distance_m'] = 100.0;

        $res = $this->traversalService->traverse(120.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('SINGLE_PATH_MATCH', $res['status']);
        $this->assertEquals(203, $res['matched_edge_id']);
        $this->assertEquals('PATH_C1_R1_T4', $res['path_id']);
        $this->assertCount(1, $res['candidate_paths']);
    }

    /**
     * Test 18: Component Isolation
     * Traversal occurs only in the root component, ignoring disconnected components.
     */
    public function testComponentIsolation(): void
    {
        $topo = $this->createLinearMockTopology();
        // Add a second isolated component with length 500m
        $topo['components'][] = [
            'component_id'    => 2,
            'is_main_trunk'   => false,
            'asset_ids'       => [5, 6],
            'edge_map'        => ['5:6' => ['id' => 301]],
            'root_resolution' => ['status' => 'ROOT_UNRESOLVED', 'root_asset_id' => null],
            'distance_paths'  => [],
        ];

        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('SINGLE_PATH_MATCH', $res['status']);
        $this->assertEquals(1, $res['component_id']);
    }

    /**
     * Test 19: Topology Gap (Unlinked branch gap)
     */
    public function testTopologyGap(): void
    {
        $topo = $this->createLinearMockTopology();
        // Intentionally create a gap: Segment 1 ends at 40m, Segment 2 starts at 60m (20m gap)
        $topo['components'][0]['distance_paths'][0]['segments'][1]['start_distance_m'] = 60.0;
        $topo['components'][0]['distance_paths'][0]['segments'][1]['end_distance_m'] = 110.0;

        // Query D = 50m which falls in the gap
        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals('PATH_DISCONTINUOUS', $res['status']);
        $this->assertNull($res['matched_edge_id']);
        $this->assertEmpty($res['candidate_paths']);
    }

    /**
     * Test 20: Deterministic Repeated Execution
     */
    public function testDeterministicRepeatedExecution(): void
    {
        $topo = $this->createBranchedMockTopology();
        $res1 = $this->traversalService->traverse(120.0, $topo, ['distance_unit' => 'M']);
        $res2 = $this->traversalService->traverse(120.0, $topo, ['distance_unit' => 'M']);

        $this->assertNotEmpty($res1['fingerprint']);
        $this->assertEquals($res1['fingerprint'], $res2['fingerprint']);
    }

    /**
     * Test 21: No Nearest-Neighbor Calculation
     * Result must never contain nearest_asset or estimated_asset_id
     */
    public function testNoNearestNeighbor(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertArrayNotHasKey('nearest_asset', $res);
        $this->assertArrayNotHasKey('estimated_asset_id', $res);
        $this->assertArrayNotHasKey('nearest_pole', $res);
    }

    /**
     * Test 22: No GPS Straight-Line Distance
     */
    public function testNoGpsDistance(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertArrayNotHasKey('gps_distance', $res);
        $this->assertArrayNotHasKey('euclidean_distance', $res);
    }

    /**
     * Test 23: No Sequence-Distance Calculation
     */
    public function testNoSequenceDistanceCalculation(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        $this->assertArrayNotHasKey('sequence_index_distance', $res);
    }

    /**
     * Test 24: No Synthetic Translines
     */
    public function testNoSyntheticTransline(): void
    {
        $topo = $this->createLinearMockTopology();
        $res = $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);

        foreach ($res['candidate_paths'] as $hyp) {
            $this->assertNotEquals('SYNTHETIC', $hyp['transline_code']);
        }
    }

    /**
     * Test 25: Zero Database Mutation Firewall
     */
    public function testZeroDbMutation(): void
    {
        $assetsBefore     = $this->db->table('assets')->countAllResults();
        $translinesBefore = $this->db->table('gis_translines')->countAllResults();
        $penyulangBefore  = $this->db->table('penyulang')->countAllResults();
        $sectionsBefore   = $this->db->table('sections')->countAllResults();
        $temuanBefore     = $this->db->table('temuan')->countAllResults();

        $topo = $this->createLinearMockTopology();
        $this->traversalService->traverse(50.0, $topo, ['distance_unit' => 'M']);
        $this->traversalService->traverse(120.0, $topo, ['distance_unit' => 'M']);

        $this->assertEquals($assetsBefore, $this->db->table('assets')->countAllResults());
        $this->assertEquals($translinesBefore, $this->db->table('gis_translines')->countAllResults());
        $this->assertEquals($penyulangBefore, $this->db->table('penyulang')->countAllResults());
        $this->assertEquals($sectionsBefore, $this->db->table('sections')->countAllResults());
        $this->assertEquals($temuanBefore, $this->db->table('temuan')->countAllResults());

        $this->assertFalse($this->db->tableExists('fault_records'));
    }

    /**
     * Test 26: Production Gading Kirana (Feeder 19)
     */
    public function testProductionLikeGadingTopology(): void
    {
        $model = $this->adapterService->buildFeederTopologyModel(19);

        // Without override root, Feeder 19 is ROOT_UNRESOLVED
        $res = $this->traversalService->traverse(500.0, $model, ['distance_unit' => 'M']);
        $this->assertEquals('ROOT_UNRESOLVED', $res['status']);

        // With manual engineering override root on main trunk
        $mainTrunk = $model['components'][0];
        $modelOverridden = $this->adapterService->buildFeederTopologyModel(19, [
            'override_root_asset_id' => $mainTrunk['asset_ids'][0],
        ]);

        $resOverridden = $this->traversalService->traverse(500.0, $modelOverridden, ['distance_unit' => 'M']);
        $this->assertContains($resOverridden['status'], ['SINGLE_PATH_MATCH', 'BRANCH_AMBIGUITY']);
        $this->assertNotEmpty($resOverridden['candidate_paths']);
    }

    /**
     * Test 27: Production Siwalan Panji (Feeder 1)
     */
    public function testProductionLikeSiwalanTopology(): void
    {
        $model = $this->adapterService->buildFeederTopologyModel(1);
        $res = $this->traversalService->traverse(500.0, $model, ['distance_unit' => 'M']);

        // Feeder 1 has an unverified root in DB, so it safely reports ROOT_UNRESOLVED
        $this->assertEquals('ROOT_UNRESOLVED', $res['status']);

        // With override root on main component
        $rootId = $model['components'][0]['asset_ids'][0];
        $modelOverridden = $this->adapterService->buildFeederTopologyModel(1, [
            'override_root_asset_id' => $rootId,
        ]);
        $resOverridden = $this->traversalService->traverse(300.0, $modelOverridden, ['distance_unit' => 'M']);
        $this->assertContains($resOverridden['status'], ['SINGLE_PATH_MATCH', 'BRANCH_AMBIGUITY']);
    }

    /**
     * Test 28: Production Gemurung (Feeder 4)
     */
    public function testProductionLikeGemurungTopology(): void
    {
        $model = $this->adapterService->buildFeederTopologyModel(4);
        $rootId = $model['components'][0]['asset_ids'][0];
        $modelOverridden = $this->adapterService->buildFeederTopologyModel(4, [
            'override_root_asset_id' => $rootId,
        ]);

        $res = $this->traversalService->traverse(200.0, $modelOverridden, ['distance_unit' => 'M']);
        $this->assertContains($res['status'], ['SINGLE_PATH_MATCH', 'BRANCH_AMBIGUITY']);
    }

    /**
     * Test 29: Production Gedangan (Feeder 23)
     */
    public function testProductionLikeGedanganTopology(): void
    {
        $model = $this->adapterService->buildFeederTopologyModel(23);
        $rootId = $model['components'][0]['asset_ids'][0];
        $modelOverridden = $this->adapterService->buildFeederTopologyModel(23, [
            'override_root_asset_id' => $rootId,
        ]);

        $res = $this->traversalService->traverse(250.0, $modelOverridden, ['distance_unit' => 'M']);
        $this->assertContains($res['status'], ['SINGLE_PATH_MATCH', 'BRANCH_AMBIGUITY']);
    }

    /**
     * Test 30: Production Ecco (Feeder 33)
     */
    public function testProductionLikeEccoTopology(): void
    {
        $model = $this->adapterService->buildFeederTopologyModel(33);
        $rootId = $model['components'][0]['asset_ids'][0];
        $modelOverridden = $this->adapterService->buildFeederTopologyModel(33, [
            'override_root_asset_id' => $rootId,
        ]);

        $res = $this->traversalService->traverse(150.0, $modelOverridden, ['distance_unit' => 'M']);
        $this->assertContains($res['status'], ['SINGLE_PATH_MATCH', 'BRANCH_AMBIGUITY']);
    }
}
