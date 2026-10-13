<?php

namespace Tests\Unit\Fault;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FaultGeometryProjectionService;
use App\Services\FaultTopologyAdapterService;
use Config\Database;

/**
 * CR-FL-01 Phase FL-01E: Fault Geometry Projection Engine Test Suite
 *
 * 34 Mandatory Unit and Integration Tests (Final Execution Contract):
 * 1.  Direct 2-vertex simple segment
 * 2.  Reversed 2-vertex simple segment (in-memory reversal)
 * 3.  Multi-vertex first subsegment
 * 4.  Multi-vertex second subsegment
 * 5.  5-vertex winding polyline
 * 6.  Offset 0 boundary
 * 7.  Terminal offset boundary
 * 8.  Intermediate vertex exact boundary
 * 9.  Branch ambiguity multiple hypotheses (independent projection, 0 selection)
 * 10. Empty LineString geometry firewall (GEOMETRY_MISSING)
 * 11. Malformed WKT geometry firewall (GEOMETRY_MALFORMED)
 * 12. Fewer than 2 vertices firewall (GEOMETRY_INVALID)
 * 13. Zero-length complete geometry firewall (GEOMETRY_ZERO_LENGTH)
 * 14. Invalid coordinates firewall (COORDINATES_INVALID)
 * 15. Endpoint mismatch firewall (GEOMETRY_ENDPOINT_MISMATCH)
 * 16. Traversal unresolved passthrough (TRAVERSAL_UNRESOLVED)
 * 17. Missing edge firewall (EDGE_NOT_FOUND)
 * 18. Precision and canonical WKT format POINT(lon lat)
 * 19. Fingerprint reproducibility
 * 20. Live Feeder 1 end-to-end integration test
 * 21. Live Feeder 4 end-to-end integration test
 * 22. Database immutability invariant (Delta = 0)
 * 23. Distance domain separation: edge length != geometry length
 * 24. Ratio-to-geometry-offset conversion: segment_ratio * geometry_length_m
 * 25. Reversed multi-vertex LineString traversal
 * 26. Repeated vertex with valid total geometry
 * 27. Zero-length subsegment handling in traverseMultiVertexLineString
 * 28. Missing source coordinate context (GEOMETRY_ENDPOINT_CONTEXT_MISSING)
 * 29. Missing target coordinate context (GEOMETRY_ENDPOINT_CONTEXT_MISSING)
 * 30. Orientation ambiguity firewall (GEOMETRY_ORIENTATION_AMBIGUOUS)
 * 31. Endpoint tolerance boundary check
 * 32. Branch identity preservation (path_id, component_id, edge_id)
 * 33. Repeated deterministic run (10 cycles)
 * 34. Zero topology/geometry mutation
 */
class FaultGeometryProjectionServiceTest extends CIUnitTestCase
{
    protected FaultGeometryProjectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'url']);
        $this->service = new FaultGeometryProjectionService();
    }

    /**
     * Helper to construct a synthetic topology read model for unit tests.
     */
    protected function createMockTopology(array $edges, array $assets, int $feederId = 1): array
    {
        return [
            'adapter_version'  => 'FL-01A-1.0',
            'status'           => 'OK',
            'feeder_id'        => $feederId,
            'feeder_code'      => 'PYL-001',
            'feeder_name'      => 'TEST FEEDER',
            'ulp_id'           => 1,
            'source_anchor'    => ['root_asset_id' => 100],
            'integrity_audit'  => ['is_valid' => true, 'anomalies_count' => 0],
            'summary'          => ['total_assets' => count($assets), 'total_edges' => count($edges)],
            'assets'           => $assets,
            'edges'            => $edges,
            'components'       => [],
            'distance_paths'   => [],
        ];
    }

    /**
     * Test 1: Direct Orientation 2-Vertex Simple Segment
     */
    public function test01_DirectOrientationTwoVertex(): void
    {
        $assets = [
            ['id' => 100, 'kode_asset' => 'POLE-100', 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 101, 'kode_asset' => 'POLE-101', 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            [
                'id'              => 501,
                'transline_code'  => 'TL-501',
                'source_asset_id' => 100,
                'target_asset_id' => 101,
                'distance_meters' => 110.0,
                'geometry'        => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000)',
            ],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'               => 'SINGLE_PATH_MATCH',
            'traversal_distance_m' => 55.0,
            'root_asset_id'        => 100,
            'component_id'         => 1,
            'candidate_paths'      => [
                [
                    'path_id'             => 'PATH-01',
                    'edge_id'             => 501,
                    'transline_code'      => 'TL-501',
                    'upstream_asset_id'   => 100,
                    'downstream_asset_id' => 101,
                    'upstream_code'       => 'POLE-100',
                    'downstream_code'     => 'POLE-101',
                    'segment_length_m'    => 110.0,
                    'offset_in_segment_m' => 55.0,
                    'segment_ratio'       => 0.5,
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);

        $this->assertSame('PROJECTED', $res['status']);
        $this->assertSame(1, $res['hypotheses_count']);
        $proj = $res['projections'][0];
        $this->assertSame('DIRECT', $proj['orientation']);
        $this->assertSame('PASS', $proj['endpoint_validation']);
        $this->assertSame(0, $proj['subsegment_index']);
        $this->assertEqualsWithDelta(-7.4000, $proj['latitude'], 0.0001);
        $this->assertEqualsWithDelta(112.7005, $proj['longitude'], 0.0001);
        $this->assertStringStartsWith('POINT(112.7005', $proj['wkt_point']);
    }

    /**
     * Test 2: Reversed Orientation 2-Vertex (in-memory reversal)
     */
    public function test02_ReversedOrientationTwoVertex(): void
    {
        $assets = [
            ['id' => 100, 'kode_asset' => 'POLE-100', 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 101, 'kode_asset' => 'POLE-101', 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            [
                'id'              => 502,
                'transline_code'  => 'TL-502',
                'source_asset_id' => 101,
                'target_asset_id' => 100,
                'distance_meters' => 110.0,
                'geometry'        => 'LINESTRING (112.7010 -7.4000, 112.7000 -7.4000)',
            ],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'               => 'SINGLE_PATH_MATCH',
            'traversal_distance_m' => 27.5,
            'root_asset_id'        => 100,
            'component_id'         => 1,
            'candidate_paths'      => [
                [
                    'path_id'             => 'PATH-01',
                    'edge_id'             => 502,
                    'upstream_asset_id'   => 100,
                    'downstream_asset_id' => 101,
                    'segment_length_m'    => 110.0,
                    'offset_in_segment_m' => 27.5,
                    'segment_ratio'       => 0.25,
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);

        $this->assertSame('PROJECTED', $res['status']);
        $proj = $res['projections'][0];
        $this->assertSame('REVERSED', $proj['orientation']);
        $this->assertSame('PASS', $proj['endpoint_validation']);
        $this->assertEqualsWithDelta(112.70025, $proj['longitude'], 0.0001);
    }

    /**
     * Test 3: Multi-Vertex First Subsegment
     */
    public function test03_MultiVertexFirstSubsegment(): void
    {
        $assets = [
            ['id' => 200, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 201, 'latitude' => -7.4010, 'longitude' => 112.7010],
        ];
        $edges = [
            [
                'id'              => 601,
                'distance_meters' => 200.0,
                'geometry'        => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000, 112.7010 -7.4010)',
            ],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                [
                    'path_id'             => 'PATH-01',
                    'edge_id'             => 601,
                    'upstream_asset_id'   => 200,
                    'downstream_asset_id' => 201,
                    'segment_length_m'    => 200.0,
                    'offset_in_segment_m' => 50.0,
                    'segment_ratio'       => 0.25,
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $proj = $res['projections'][0];

        $this->assertSame(0, $proj['subsegment_index']);
        $this->assertEqualsWithDelta(-7.4000, $proj['latitude'], 0.0001);
        $this->assertGreaterThan(112.7000, $proj['longitude']);
        $this->assertLessThan(112.7010, $proj['longitude']);
    }

    /**
     * Test 4: Multi-Vertex Second Subsegment
     */
    public function test04_MultiVertexSecondSubsegment(): void
    {
        $assets = [
            ['id' => 200, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 201, 'latitude' => -7.4010, 'longitude' => 112.7010],
        ];
        $edges = [
            [
                'id'              => 601,
                'distance_meters' => 200.0,
                'geometry'        => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000, 112.7010 -7.4010)',
            ],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                [
                    'path_id'             => 'PATH-01',
                    'edge_id'             => 601,
                    'upstream_asset_id'   => 200,
                    'downstream_asset_id' => 201,
                    'segment_length_m'    => 200.0,
                    'offset_in_segment_m' => 150.0,
                    'segment_ratio'       => 0.75,
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $proj = $res['projections'][0];

        $this->assertSame(1, $proj['subsegment_index']);
        $this->assertEqualsWithDelta(112.7010, $proj['longitude'], 0.0001);
        $this->assertLessThan(-7.4000, $proj['latitude']);
        $this->assertGreaterThan(-7.4010, $proj['latitude']);
    }

    /**
     * Test 5: 5-Vertex Winding Polyline
     */
    public function test05_FiveVertexWindingPolyline(): void
    {
        $assets = [
            ['id' => 300, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 301, 'latitude' => -7.4040, 'longitude' => 112.7000],
        ];
        $edges = [
            [
                'id'              => 701,
                'distance_meters' => 400.0,
                'geometry'        => 'LINESTRING (112.7000 -7.4000, 112.7000 -7.4010, 112.7000 -7.4020, 112.7000 -7.4030, 112.7000 -7.4040)',
            ],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                [
                    'edge_id'             => 701,
                    'upstream_asset_id'   => 300,
                    'downstream_asset_id' => 301,
                    'segment_length_m'    => 400.0,
                    'offset_in_segment_m' => 240.0, // 60%
                    'segment_ratio'       => 0.6,
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $proj = $res['projections'][0];

        $this->assertSame(5, $proj['total_vertices']);
        $this->assertSame(2, $proj['subsegment_index']);
        $this->assertEqualsWithDelta(-7.4024, $proj['latitude'], 0.0002);
    }

    /**
     * Test 6: Offset 0 Boundary (exactly at upstream vertex)
     */
    public function test06_BoundaryOffsetZero(): void
    {
        $assets = [
            ['id' => 100, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 101, 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            ['id' => 501, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 501, 'upstream_asset_id' => 100, 'downstream_asset_id' => 101, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 0.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $proj = $res['projections'][0];

        $this->assertSame(0.0, $proj['subsegment_ratio']);
        $this->assertEqualsWithDelta(-7.4000, $proj['latitude'], 0.00001);
        $this->assertEqualsWithDelta(112.7000, $proj['longitude'], 0.00001);
    }

    /**
     * Test 7: Terminal Offset Boundary (exactly at downstream vertex)
     */
    public function test07_BoundaryOffsetTerminal(): void
    {
        $assets = [
            ['id' => 100, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 101, 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            ['id' => 501, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 501, 'upstream_asset_id' => 100, 'downstream_asset_id' => 101, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 100.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $proj = $res['projections'][0];

        $this->assertSame(1.0, $proj['subsegment_ratio']);
        $this->assertEqualsWithDelta(-7.4000, $proj['latitude'], 0.00001);
        $this->assertEqualsWithDelta(112.7010, $proj['longitude'], 0.00001);
    }

    /**
     * Test 8: Intermediate Vertex Exact Boundary (single subsegment match)
     */
    public function test08_IntermediateVertexExactBoundary(): void
    {
        $assets = [
            ['id' => 100, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 102, 'latitude' => -7.4000, 'longitude' => 112.7020],
        ];
        $edges = [
            ['id' => 801, 'distance_meters' => 200.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000, 112.7020 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 801, 'upstream_asset_id' => 100, 'downstream_asset_id' => 102, 'segment_length_m' => 200.0, 'offset_in_segment_m' => 100.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('PROJECTED', $res['status']);
        $this->assertSame(1, count($res['projections']));
        $this->assertEqualsWithDelta(112.7010, $res['projections'][0]['longitude'], 0.00001);
    }

    /**
     * Test 9: Branch Ambiguity Multiple Hypotheses (independent projection, 0 selection)
     */
    public function test09_BranchAmbiguityMultipleHypotheses(): void
    {
        $assets = [
            ['id' => 10, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 11, 'latitude' => -7.4000, 'longitude' => 112.7010],
            ['id' => 12, 'latitude' => -7.4010, 'longitude' => 112.7000],
        ];
        $edges = [
            ['id' => 901, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000)'],
            ['id' => 902, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7000 -7.4010)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'BRANCH_AMBIGUITY',
            'candidate_paths' => [
                ['path_id' => 'PATH-A', 'edge_id' => 901, 'upstream_asset_id' => 10, 'downstream_asset_id' => 11, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 40.0],
                ['path_id' => 'PATH-B', 'edge_id' => 902, 'upstream_asset_id' => 10, 'downstream_asset_id' => 12, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 40.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);

        $this->assertSame('BRANCH_AMBIGUITY_PROJECTED', $res['status']);
        $this->assertSame(2, $res['hypotheses_count']);
        $this->assertArrayNotHasKey('confidence', $res['projections'][0]);
        $this->assertArrayNotHasKey('confidence', $res['projections'][1]);
    }

    /**
     * Test 10: Empty LineString Geometry Firewall (GEOMETRY_MISSING)
     */
    public function test10_Firewall_EmptyLineString(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4, 'longitude' => 112.7],
            ['id' => 2, 'latitude' => -7.4, 'longitude' => 112.8],
        ];
        $edges = [
            ['id' => 10, 'distance_meters' => 100.0, 'geometry' => ''],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 10, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('GEOMETRY_MISSING', $res['status']);
        $this->assertNull($res['projections'][0]['wkt_point']);
    }

    /**
     * Test 11: Malformed WKT Geometry Firewall (GEOMETRY_MALFORMED)
     */
    public function test11_Firewall_MalformedWkt(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4, 'longitude' => 112.7],
            ['id' => 2, 'latitude' => -7.4, 'longitude' => 112.8],
        ];
        $edges = [
            ['id' => 10, 'distance_meters' => 100.0, 'geometry' => 'NOT_A_LINESTRING (112.7 -7.4)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 10, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('GEOMETRY_MALFORMED', $res['status']);
    }

    /**
     * Test 12: Fewer Than 2 Vertices Firewall (GEOMETRY_INVALID)
     */
    public function test12_Firewall_FewerThanTwoVertices(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4, 'longitude' => 112.7],
            ['id' => 2, 'latitude' => -7.4, 'longitude' => 112.8],
        ];
        $edges = [
            ['id' => 10, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 10, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('GEOMETRY_INVALID', $res['status']);
    }

    /**
     * Test 13: Zero-Length Complete Geometry Firewall (GEOMETRY_ZERO_LENGTH)
     */
    public function test13_Firewall_ZeroLengthCompleteGeometry(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 2, 'latitude' => -7.4000, 'longitude' => 112.7000],
        ];
        $edges = [
            ['id' => 10, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7000 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 10, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('GEOMETRY_ZERO_LENGTH', $res['status']);
    }

    /**
     * Test 14: Invalid Coordinates Firewall (COORDINATES_INVALID)
     */
    public function test14_Firewall_CoordinatesInvalid(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4, 'longitude' => 112.7],
            ['id' => 2, 'latitude' => -7.4, 'longitude' => 112.8],
        ];
        $edges = [
            ['id' => 10, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (999.0000 -7.4000, 112.8000 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 10, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('COORDINATES_INVALID', $res['status']);
    }

    /**
     * Test 15: Endpoint Mismatch Firewall (GEOMETRY_ENDPOINT_MISMATCH)
     */
    public function test15_Firewall_GeometryEndpointMismatch(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 2, 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            ['id' => 10, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7100 -7.4000, 112.7110 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 10, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('GEOMETRY_ENDPOINT_MISMATCH', $res['status']);
    }

    /**
     * Test 16: Traversal Unresolved Passthrough (TRAVERSAL_UNRESOLVED)
     */
    public function test16_Firewall_TraversalUnresolvedPassthrough(): void
    {
        $traversal = [
            'status'               => 'DISTANCE_OUT_OF_RANGE',
            'traversal_distance_m' => 12500.0,
            'diagnostics'          => 'Distance exceeds maximum path length.',
        ];

        $res = $this->service->project($traversal, []);
        $this->assertSame('TRAVERSAL_UNRESOLVED', $res['status']);
        $this->assertSame(0, $res['hypotheses_count']);
    }

    /**
     * Test 17: Missing Edge Firewall (EDGE_NOT_FOUND)
     */
    public function test17_Firewall_EdgeNotFound(): void
    {
        $topo = $this->createMockTopology([], []);
        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 9999, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('EDGE_NOT_FOUND', $res['status']);
    }

    /**
     * Test 18: Precision and Canonical WKT Format POINT(lon lat)
     */
    public function test18_PrecisionAndCanonicalWktFormat(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4235100, 'longitude' => 112.7263500],
            ['id' => 2, 'latitude' => -7.4238100, 'longitude' => 112.7262200],
        ];
        $edges = [
            ['id' => 1, 'distance_meters' => 36.31, 'geometry' => 'LINESTRING (112.7263500 -7.4235100, 112.7262200 -7.4238100)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 1, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 36.31, 'offset_in_segment_m' => 18.15],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $proj = $res['projections'][0];

        // Format POINT(lon lat) without space between POINT and parenthesis
        $this->assertMatchesRegularExpression('/^POINT\(\d+\.\d{7} -\d+\.\d{7}\)$/', $proj['wkt_point']);
        $this->assertArrayNotHasKey('estimated_asset_id', $proj);
    }

    /**
     * Test 19: Fingerprint Reproducibility
     */
    public function test19_FingerprintReproducibility(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 2, 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            ['id' => 1, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 1, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 33.33],
            ],
        ];

        $res1 = $this->service->project($traversal, $topo);
        $res2 = $this->service->project($traversal, $topo);

        $this->assertSame($res1['fingerprint'], $res2['fingerprint']);
        $this->assertSame(64, strlen($res1['fingerprint']));
    }

    /**
     * Test 20: Live Feeder 1 End-to-End Integration Test
     */
    public function test20_LiveFeeder1EndToEnd(): void
    {
        try {
            $db = Database::connect('default');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL default connection not available.');
        }

        if (!$db || !$db->tableExists('assets') || !$db->tableExists('gis_translines')) {
            $this->markTestSkipped('Live database tables not available.');
        }

        $adapter = new FaultTopologyAdapterService($db);
        $topo = $adapter->buildFeederTopologyModel(1);
        if (empty($topo['edges'])) {
            $this->markTestSkipped('Feeder 1 topology edges not available.');
        }

        // Project on first edge of Feeder 1
        $firstEdge = $topo['edges'][0];
        $traversal = [
            'status'               => 'SINGLE_PATH_MATCH',
            'traversal_distance_m' => round($firstEdge['distance_meters'] * 0.5, 2),
            'root_asset_id'        => $topo['source_anchor']['root_asset_id'] ?? null,
            'component_id'         => 1,
            'candidate_paths'      => [
                [
                    'path_id'             => 'LIVE-F1-01',
                    'edge_id'             => $firstEdge['id'],
                    'upstream_asset_id'   => $firstEdge['source_asset_id'],
                    'downstream_asset_id' => $firstEdge['target_asset_id'],
                    'segment_length_m'    => $firstEdge['distance_meters'],
                    'offset_in_segment_m' => round($firstEdge['distance_meters'] * 0.5, 2),
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('PROJECTED', $res['status']);
        $this->assertNotEmpty($res['projections'][0]['wkt_point']);
    }

    /**
     * Test 21: Live Feeder 4 End-to-End Integration Test
     */
    public function test21_LiveFeeder4EndToEnd(): void
    {
        try {
            $db = Database::connect('default');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL default connection not available.');
        }

        if (!$db || !$db->tableExists('assets') || !$db->tableExists('gis_translines')) {
            $this->markTestSkipped('Live database tables not available.');
        }

        $adapter = new FaultTopologyAdapterService($db);
        $topo = $adapter->buildFeederTopologyModel(4);
        if (empty($topo['edges'])) {
            $this->markTestSkipped('Feeder 4 topology edges not available.');
        }

        $firstEdge = $topo['edges'][0];
        $traversal = [
            'status'               => 'SINGLE_PATH_MATCH',
            'traversal_distance_m' => round($firstEdge['distance_meters'] * 0.3, 2),
            'candidate_paths'      => [
                [
                    'path_id'             => 'LIVE-F4-01',
                    'edge_id'             => $firstEdge['id'],
                    'upstream_asset_id'   => $firstEdge['source_asset_id'],
                    'downstream_asset_id' => $firstEdge['target_asset_id'],
                    'segment_length_m'    => $firstEdge['distance_meters'],
                    'offset_in_segment_m' => round($firstEdge['distance_meters'] * 0.3, 2),
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('PROJECTED', $res['status']);
        $this->assertNotEmpty($res['projections'][0]['wkt_point']);
    }

    /**
     * Test 22: Database Immutability Invariant (Delta = 0)
     */
    public function test22_ZeroDatabaseMutationInvariant(): void
    {
        try {
            $db = Database::connect('default');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL default connection not available.');
        }

        if (!$db || !$db->tableExists('assets')) {
            $this->markTestSkipped('Authoritative assets table not accessible on test database connection.');
        }

        $assetsCount     = (int) $db->table('assets')->countAllResults();
        $translinesCount = (int) $db->table('gis_translines')->countAllResults();
        $penyulangCount  = (int) $db->table('penyulang')->countAllResults();
        $sectionsCount   = (int) $db->table('sections')->countAllResults();
        $temuanCount     = (int) $db->table('temuan')->countAllResults();
        $faultRecordsExists = $db->tableExists('fault_records');

        $this->assertSame(1476, $assetsCount, 'assets count must remain strictly invariant at 1476');
        $this->assertSame(1278, $translinesCount, 'gis_translines count must remain strictly invariant at 1278');
        $this->assertSame(134, $penyulangCount, 'penyulang count must remain strictly invariant at 134');
        $this->assertSame(508, $sectionsCount, 'sections count must remain strictly invariant at 508');
        $this->assertSame(441, $temuanCount, 'temuan count must remain strictly invariant at 441');
        $this->assertFalse($faultRecordsExists, 'fault_records table must NOT exist');
    }

    /**
     * Test 23: Distance Domain Separation - Edge Length != Geometry Length
     */
    public function test23_DistanceDomainSeparation_GeometryLengthDiffersFromEdgeLength(): void
    {
        $assets = [
            ['id' => 10, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 11, 'latitude' => -7.4000, 'longitude' => 112.7016445],
        ];
        $edges = [
            [
                'id'              => 55,
                'distance_meters' => 174.52, // FL-01D distance domain
                'geometry'        => 'LINESTRING (112.7000 -7.4000, 112.7016445 -7.4000)',
            ],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                [
                    'edge_id'             => 55,
                    'upstream_asset_id'   => 10,
                    'downstream_asset_id' => 11,
                    'segment_length_m'    => 174.52,
                    'offset_in_segment_m' => 87.26,
                    'segment_ratio'       => 0.5,
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $proj = $res['projections'][0];

        $this->assertSame(0.5, $proj['segment_ratio']);
        $this->assertNotEquals(174.52, $proj['geometry_length_m']);
        $this->assertEqualsWithDelta(181.34, $proj['geometry_length_m'], 0.1);
    }

    /**
     * Test 24: Distance Domain Separation - Ratio-to-Geometry-Offset Conversion
     */
    public function test24_DistanceDomainSeparation_RatioMappedToGeometryLength(): void
    {
        $assets = [
            ['id' => 10, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 11, 'latitude' => -7.4000, 'longitude' => 112.7016445],
        ];
        $edges = [
            [
                'id'              => 55,
                'distance_meters' => 174.52,
                'geometry'        => 'LINESTRING (112.7000 -7.4000, 112.7016445 -7.4000)',
            ],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                [
                    'edge_id'             => 55,
                    'upstream_asset_id'   => 10,
                    'downstream_asset_id' => 11,
                    'segment_length_m'    => 174.52,
                    'offset_in_segment_m' => 87.26, // ratio = 0.5
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $proj = $res['projections'][0];

        $geomLen = $proj['geometry_length_m'];
        $geomOffset = $proj['geometry_offset_m'];

        $this->assertEqualsWithDelta($geomLen * 0.5, $geomOffset, 0.05);
        $this->assertNotEquals(87.26, $geomOffset);
    }

    /**
     * Test 25: Reversed Multi-Vertex LineString Traversal
     */
    public function test25_ReversedMultiVertexLineString(): void
    {
        $assets = [
            ['id' => 50, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 51, 'latitude' => -7.4030, 'longitude' => 112.7000],
        ];
        $edges = [
            [
                'id'              => 88,
                'distance_meters' => 330.0,
                'geometry'        => 'LINESTRING (112.7000 -7.4030, 112.7000 -7.4020, 112.7000 -7.4010, 112.7000 -7.4000)',
            ],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                [
                    'edge_id'             => 88,
                    'upstream_asset_id'   => 50,
                    'downstream_asset_id' => 51,
                    'segment_length_m'    => 330.0,
                    'offset_in_segment_m' => 33.0, // 10%
                    'segment_ratio'       => 0.1,
                ],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $proj = $res['projections'][0];

        $this->assertSame('REVERSED', $proj['orientation']);
        $this->assertEqualsWithDelta(-7.4003, $proj['latitude'], 0.0002);
    }

    /**
     * Test 26: Repeated Vertex with Valid Total Geometry
     */
    public function test26_RepeatedVertexWithValidTotalGeometry(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 2, 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            [
                'id'              => 20,
                'distance_meters' => 100.0,
                'geometry'        => 'LINESTRING (112.7000 -7.4000, 112.7005 -7.4000, 112.7005 -7.4000, 112.7010 -7.4000)', // Repeated intermediate vertex
            ],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 20, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 75.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('PROJECTED', $res['status']);
        $this->assertEqualsWithDelta(112.70075, $res['projections'][0]['longitude'], 0.0001);
    }

    /**
     * Test 27: Zero-Length Subsegment Handling in traverseMultiVertexLineString
     */
    public function test27_ZeroLengthSubsegmentHandling(): void
    {
        $vertices = [
            ['lon' => 112.7000, 'lat' => -7.4000],
            ['lon' => 112.7000, 'lat' => -7.4000], // 0-length subsegment
            ['lon' => 112.7010, 'lat' => -7.4000],
        ];

        $walk = $this->service->traverseMultiVertexLineString($vertices, 50.0);
        $this->assertNotNull($walk);
        $this->assertGreaterThan(112.7000, $walk['longitude']);
    }

    /**
     * Test 28: Missing Source Coordinate Context (GEOMETRY_ENDPOINT_CONTEXT_MISSING)
     */
    public function test28_MissingSourceCoordinateContext(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => 0.0, 'longitude' => 0.0], // (0,0) sentinel
            ['id' => 2, 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            ['id' => 30, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 30, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('GEOMETRY_ENDPOINT_CONTEXT_MISSING', $res['status']);
        $this->assertSame('FAILED', $res['projections'][0]['endpoint_validation']);
    }

    /**
     * Test 29: Missing Target Coordinate Context (GEOMETRY_ENDPOINT_CONTEXT_MISSING)
     */
    public function test29_MissingTargetCoordinateContext(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 2, 'latitude' => null, 'longitude' => null],
        ];
        $edges = [
            ['id' => 30, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 30, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('GEOMETRY_ENDPOINT_CONTEXT_MISSING', $res['status']);
    }

    /**
     * Test 30: Orientation Ambiguity Firewall (GEOMETRY_ORIENTATION_AMBIGUOUS)
     */
    public function test30_OrientationAmbiguity(): void
    {
        // Upstream and downstream assets are practically on top of each other (< 1m),
        // making DIRECT and REVERSED distances identical within tolerance
        $assets = [
            ['id' => 1, 'latitude' => -7.4000000, 'longitude' => 112.7000000],
            ['id' => 2, 'latitude' => -7.4000000, 'longitude' => 112.7000000],
        ];
        $edges = [
            ['id' => 45, 'distance_meters' => 10.0, 'geometry' => 'LINESTRING (112.7000000 -7.4000000, 112.7000500 -7.4000000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 45, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 10.0, 'offset_in_segment_m' => 5.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('GEOMETRY_ORIENTATION_AMBIGUOUS', $res['status']);
        $this->assertSame('FAILED', $res['projections'][0]['endpoint_validation']);
        $this->assertNull($res['projections'][0]['orientation']);
    }

    /**
     * Test 31: Endpoint Tolerance Boundary Check
     */
    public function test31_EndpointToleranceBoundary(): void
    {
        // Tolerance = 10.0m -> allowed threshold = 20.0m
        $assets = [
            ['id' => 1, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 2, 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];

        // Endpoints deviate by ~15m (< 20m) -> should PASS
        $edgesPass = [
            ['id' => 1, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7001 -7.4000, 112.7010 -7.4000)'],
        ];
        $topoPass = $this->createMockTopology($edgesPass, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 1, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];

        $resPass = $this->service->project($traversal, $topoPass, ['tolerance_meters' => 10.0]);
        $this->assertSame('PROJECTED', $resPass['status']);

        // Endpoints deviate by ~50m (> 20m) -> should FAIL
        $edgesFail = [
            ['id' => 2, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7005 -7.4000, 112.7010 -7.4000)'],
        ];
        $topoFail = $this->createMockTopology($edgesFail, $assets);
        $traversalFail = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 2, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
            ],
        ];
        $resFail = $this->service->project($traversalFail, $topoFail, ['tolerance_meters' => 10.0]);
        $this->assertSame('GEOMETRY_ENDPOINT_MISMATCH', $resFail['status']);
    }

    /**
     * Test 32: Branch Identity Preservation (path_id, component_id, edge_id)
     */
    public function test32_BranchIdentityPreservation(): void
    {
        $assets = [
            ['id' => 10, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 11, 'latitude' => -7.4000, 'longitude' => 112.7010],
            ['id' => 12, 'latitude' => -7.4010, 'longitude' => 112.7000],
        ];
        $edges = [
            ['id' => 901, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000)'],
            ['id' => 902, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7000 -7.4010)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'BRANCH_AMBIGUITY',
            'component_id'    => 7,
            'candidate_paths' => [
                ['path_id' => 'PATH-BRANCH-A', 'component_id' => 7, 'edge_id' => 901, 'upstream_asset_id' => 10, 'downstream_asset_id' => 11, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 40.0],
                ['path_id' => 'PATH-BRANCH-B', 'component_id' => 7, 'edge_id' => 902, 'upstream_asset_id' => 10, 'downstream_asset_id' => 12, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 60.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $this->assertSame('BRANCH_AMBIGUITY_PROJECTED', $res['status']);

        $p0 = $res['projections'][0];
        $p1 = $res['projections'][1];

        $this->assertSame('PATH-BRANCH-A', $p0['path_id']);
        $this->assertSame(7, $p0['component_id']);
        $this->assertSame(901, $p0['edge_id']);

        $this->assertSame('PATH-BRANCH-B', $p1['path_id']);
        $this->assertSame(7, $p1['component_id']);
        $this->assertSame(902, $p1['edge_id']);
    }

    /**
     * Test 33: Repeated Deterministic Run (10 cycles)
     */
    public function test33_RepeatedDeterministicRun(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 2, 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            ['id' => 1, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7000 -7.4000, 112.7010 -7.4000)'],
        ];
        $topo = $this->createMockTopology($edges, $assets);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 1, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 45.67],
            ],
        ];

        $firstFingerprint = null;
        for ($i = 0; $i < 10; $i++) {
            $res = $this->service->project($traversal, $topo);
            if ($firstFingerprint === null) {
                $firstFingerprint = $res['fingerprint'];
            } else {
                $this->assertSame($firstFingerprint, $res['fingerprint'], "Cycle {$i} must match fingerprint");
            }
        }
    }

    /**
     * Test 34: Zero Topology / Geometry Mutation
     */
    public function test34_ZeroTopologyGeometryMutation(): void
    {
        $assets = [
            ['id' => 1, 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 2, 'latitude' => -7.4000, 'longitude' => 112.7010],
        ];
        $edges = [
            ['id' => 1, 'distance_meters' => 100.0, 'geometry' => 'LINESTRING (112.7010 -7.4000, 112.7000 -7.4000)'], // REVERSED
        ];
        $topo = $this->createMockTopology($edges, $assets);
        $topoJsonBefore = json_encode($topo);

        $traversal = [
            'status'          => 'SINGLE_PATH_MATCH',
            'candidate_paths' => [
                ['edge_id' => 1, 'upstream_asset_id' => 1, 'downstream_asset_id' => 2, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 30.0],
            ],
        ];

        $res = $this->service->project($traversal, $topo);
        $topoJsonAfter = json_encode($topo);

        // Input topology data array must not be mutated
        $this->assertSame($topoJsonBefore, $topoJsonAfter, 'Topology model must not be mutated by projection');
        $this->assertSame('REVERSED', $res['projections'][0]['orientation']);
    }
}
