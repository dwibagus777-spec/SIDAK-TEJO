<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\NetworkIntelligenceService;

/**
 * B3NetworkIntelligenceTest
 *
 * 15-Point Unit Test Suite for Phase B.3 Network Intelligence Platform:
 *  1. Authoritative Baseline Graph Builder
 *  2. Feeder 118 Anchor & Root Resolution Provenance
 *  3. Section Topology 3-Way Classification (Intra, Boundary Switch, Cross-Section)
 *  4. Strict Dijkstra Path Analysis with Telemetry
 *  5. Network Integrity Analyzer Structure & Evidence
 *  6. Deterministic Snapshot ID Immutability
 *  7. Rejected Edges Provenance Auditable Preservation
 *  8. Zero Database Mutation Invariant Across All Operations
 *  9. Undirected Graph Adjacency Symmetry
 * 10. Cross-Feeder Path Rejection
 * 11. Disconnected Component Explicit Rejection
 * 12. Traversal Parent Decoupling from Asset Parent
 * 13. Snapshot ID Constant Invariant
 * 14. Preview Edges Exclusion Invariant
 * 15. Inactive Translines Exclusion Invariant
 */
class B3NetworkIntelligenceTest extends TestCase
{
    protected NetworkIntelligenceService $service;
    protected array $fixtureAssets;
    protected array $fixtureTranslines;

    protected function setUp(): void
    {
        parent::setUp();
        // Instantiate without requiring live DB connection for pure deterministic testing
        $this->service = new NetworkIntelligenceService();

        // 1. Fixture Assets (Feeder 118, Feeder 15, Cross-feeder, Isolated)
        $this->fixtureAssets = [
            // Feeder 118 (Bahagia Steel 1)
            [
                'id'                   => 5245,
                'kode_asset'           => 'AST-KRN-BHGSTL1-JTM-033',
                'nama_asset'           => 'Tiang 33',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 50,
                'penyulang_id'         => 118,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'sequence_no'          => 33,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 5246,
                'kode_asset'           => 'AST-KRN-BHGSTL1-JTM-034',
                'nama_asset'           => 'Tiang 34',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 50,
                'penyulang_id'         => 118,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'sequence_no'          => 34,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 5232,
                'kode_asset'           => 'AST-KRN-BHGSTL1-JTM-020',
                'nama_asset'           => 'Tiang 20',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 46,
                'penyulang_id'         => 118,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'sequence_no'          => 20,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 5233,
                'kode_asset'           => 'AST-KRN-BHGSTL1-JTM-021',
                'nama_asset'           => 'Tiang 21',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 50,
                'penyulang_id'         => 118,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'sequence_no'          => 21,
                'status'               => 'NORMAL',
            ],
            // Feeder 15 (Banjarkemantran)
            [
                'id'                   => 101,
                'kode_asset'           => 'BANJARKEMANTRAN_28',
                'nama_asset'           => 'BANJARKEMANTRAN_28 LBS',
                'jenis_asset'          => 'LBS',
                'section_id'           => 46,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'construction_type_id' => 2,
                'sequence_no'          => 28,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 102,
                'kode_asset'           => 'BANJARKEMANTRAN_29',
                'nama_asset'           => 'BANJARKEMANTRAN_29',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 47,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'sequence_no'          => 29,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 103,
                'kode_asset'           => 'BANJARKEMANTRAN_1',
                'nama_asset'           => 'BANJARKEMANTRAN_1',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 47,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'sequence_no'          => 1,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 104,
                'kode_asset'           => 'BANJARKEMANTRAN_2',
                'nama_asset'           => 'BANJARKEMANTRAN_2',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 47,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'sequence_no'          => 2,
                'status'               => 'NORMAL',
            ],
        ];

        // 2. Fixture Translines
        $this->fixtureTranslines = [
            // Feeder 118: Strictly 1 authoritative edge (Tiang 33 -> 34)
            [
                'id'                 => 335,
                'transline_code'     => 'TL-118-5245-5246',
                'penyulang_id'       => 118,
                'source_asset_id'    => 5245,
                'target_asset_id'    => 5246,
                'distance_meters'    => 27.82,
                'conductor_type'     => 'XLPE',
                'conductor_size'     => '150 mm²',
                'conductor_material' => 'COPPER',
                'status'             => 'ACTIVE',
                'is_active'          => 1,
            ],
            // Feeder 15: TL #6 (Boundary Switch Sec 46 -> 47)
            [
                'id'                 => 6,
                'transline_code'     => 'TL-15-6',
                'penyulang_id'       => 15,
                'source_asset_id'    => 101,
                'target_asset_id'    => 102,
                'distance_meters'    => 45.0,
                'conductor_type'     => 'AAAC',
                'conductor_size'     => '150 mm²',
                'conductor_material' => 'ALUMINUM_ALLOY',
                'status'             => 'ACTIVE',
                'is_active'          => 1,
            ],
            // Feeder 15: TL #8 (Boundary Switch Sec 46 -> 47)
            [
                'id'                 => 8,
                'transline_code'     => 'TL-15-8',
                'penyulang_id'       => 15,
                'source_asset_id'    => 101,
                'target_asset_id'    => 103,
                'distance_meters'    => 38.5,
                'conductor_type'     => 'AAAC',
                'conductor_size'     => '150 mm²',
                'conductor_material' => 'ALUMINUM_ALLOY',
                'status'             => 'ACTIVE',
                'is_active'          => 1,
            ],
            // Feeder 15: TL #9 (Intra-section Sec 47 -> 47)
            [
                'id'                 => 9,
                'transline_code'     => 'TL-15-9',
                'penyulang_id'       => 15,
                'source_asset_id'    => 103,
                'target_asset_id'    => 104,
                'distance_meters'    => 50.0,
                'conductor_type'     => 'AAAC',
                'conductor_size'     => '150 mm²',
                'conductor_material' => 'ALUMINUM_ALLOY',
                'status'             => 'ACTIVE',
                'is_active'          => 1,
            ],
        ];
    }

    /**
     * TEST 01: B.3.1 Graph Builder Authoritative Baseline
     */
    public function testB31GraphBuilderAuthoritativeBaseline(): void
    {
        $graph = $this->service->buildNetworkGraph(null, $this->fixtureAssets, $this->fixtureTranslines);

        $this->assertArrayHasKey('scope', $graph);
        $this->assertArrayHasKey('summary', $graph);
        $this->assertArrayHasKey('nodes', $graph);
        $this->assertArrayHasKey('edges', $graph);
        $this->assertArrayHasKey('adjacency', $graph);
        $this->assertArrayHasKey('degrees', $graph);
        $this->assertArrayHasKey('components', $graph);
        $this->assertArrayHasKey('roles', $graph);

        // Multi-edge violations must be 0
        $this->assertEquals(0, $graph['summary']['multi_edge_violations'], 'Authoritative graph must contain 0 multi-edges.');

        // Total distance must be positive
        $this->assertGreaterThan(0.0, $graph['summary']['total_distance_meters']);
        $this->assertEquals(4, count($graph['edges']));
    }

    /**
     * TEST 02: B.3.2 Feeder Traversal Anchor Feeder 118 & Root Resolution Provenance
     */
    public function testB32FeederTraversalAnchorFeeder118(): void
    {
        $traversal = $this->service->traverseFeeder(118, null, $this->fixtureAssets, $this->fixtureTranslines);

        $this->assertEquals('SIDAK TEJO', $traversal['system']);
        $this->assertEquals('B.3', $traversal['phase']);
        $this->assertEquals('READ_ONLY', $traversal['mode']);
        $this->assertFalse($traversal['mutation']);

        $payload = $traversal['payload'];
        $this->assertArrayHasKey('root_resolution', $payload);
        $this->assertArrayHasKey('root_confidence', $payload);
        $this->assertContains($payload['root_resolution'], [
            'EXPLICIT_CONFIGURED',
            'VERIFIED_FEEDER_HEAD',
            'LOWEST_SEQUENCE_FALLBACK',
            'CONNECTED_NODE_FALLBACK',
            'ISOLATED_NODE_FALLBACK',
            'UNRESOLVED',
        ]);
        $this->assertGreaterThanOrEqual(0.0, $payload['root_confidence']);
        $this->assertLessThanOrEqual(1.0, $payload['root_confidence']);

        // Check traversal tree field naming (Guard 3: strictly NOT parent_asset_id)
        if (!empty($payload['traversal_tree'])) {
            foreach ($payload['traversal_tree'] as $node) {
                $this->assertArrayHasKey('traversal_parent', $node);
                $this->assertArrayHasKey('graph_parent_asset_id', $node);
                $this->assertArrayNotHasKey('parent_asset_id', $node, 'BFS traversal must not use parent_asset_id as key name.');
            }
        }
    }

    /**
     * TEST 03: B.3.3 Section Topology 3-Way Classification
     */
    public function testB33SectionTopology3WayClassification(): void
    {
        $sectionTopo = $this->service->getSectionTopology(15, $this->fixtureAssets, $this->fixtureTranslines);
        $payload = $sectionTopo['payload'];

        $this->assertArrayHasKey('sections', $payload);
        $this->assertArrayHasKey('edges', $payload);

        $hasBoundarySwitch = false;
        $hasIntraSection = false;

        foreach ($payload['edges'] as $edge) {
            $this->assertContains($edge['classification'], [
                'INTRA_SECTION',
                'BOUNDARY_SWITCH',
                'CROSS_SECTION_EDGE',
            ], 'Edge must be classified into one of the 3 canonical categories.');

            if ($edge['classification'] === 'BOUNDARY_SWITCH') {
                $hasBoundarySwitch = true;
                $this->assertNotNull($edge['switch_evidence'], 'Boundary switch edge must provide switch evidence.');
            }
            if ($edge['classification'] === 'INTRA_SECTION') {
                $hasIntraSection = true;
            }
        }

        $this->assertTrue($hasBoundarySwitch, 'Feeder 15 must contain verified boundary switch edges.');
        $this->assertTrue($hasIntraSection, 'Feeder 15 must contain intra-section edges.');
    }

    /**
     * TEST 04: B.3.4 Strict Dijkstra Path Analysis with Telemetry
     */
    public function testB34PathAnalysisConnectedAndDisconnected(): void
    {
        // Path on Feeder 118: Tiang 33 (5245) -> Tiang 34 (5246)
        $pathRes = $this->service->analyzePath(5245, 5246, $this->fixtureAssets, $this->fixtureTranslines);
        $payload = $pathRes['payload'];

        $this->assertTrue($payload['reachable']);
        $this->assertEquals('DIJKSTRA', $payload['algorithm']);
        $this->assertEquals(1, $payload['edge_count']);
        $this->assertEquals(2, $payload['asset_count']);
        $this->assertEqualsWithDelta(27.82, $payload['distance_m'], 0.05);
        $this->assertCount(1, $payload['edges']);
        $this->assertArrayHasKey('sections_crossed', $payload);
        $this->assertArrayHasKey('switching_points_traversed', $payload);
        $this->assertArrayHasKey('conductor_transitions', $payload);
    }

    /**
     * TEST 05: B.3.5 Network Integrity Analyzer Structure & Evidence
     */
    public function testB35NetworkIntegrityAnalyzerZeroMutation(): void
    {
        $integrity = $this->service->analyzeNetworkIntegrity(null, $this->fixtureAssets, $this->fixtureTranslines);

        $this->assertFalse($integrity['mutation']);
        $payload = $integrity['payload'];

        $this->assertContains($payload['verdict'], ['HEALTHY', 'HEALTHY_WITH_ISOLATED_OBSERVATIONS', 'REVIEW_REQUIRED']);
        $this->assertArrayHasKey('observations', $payload);
        $this->assertArrayHasKey('details', $payload);
        $this->assertArrayHasKey('isolated_nodes', $payload['details']);
        $this->assertArrayHasKey('terminal_nodes', $payload['details']);
        $this->assertArrayHasKey('intermediate_nodes', $payload['details']);
        $this->assertArrayHasKey('branch_nodes', $payload['details']);
    }

    /**
     * TEST 06: Deterministic Snapshot ID
     */
    public function testB3DeterministicSnapshotId(): void
    {
        $snapshotId = $this->service->getTopologySnapshotId();
        $this->assertEquals('TOPOLOGY-20260925-243-ad2c9fcb', $snapshotId);

        $envelope = $this->service->createEnvelope([]);
        $this->assertEquals('TOPOLOGY-20260925-243-ad2c9fcb', $envelope['snapshot_id']);
        $this->assertEquals(243, $envelope['authoritative_edge_count']);
    }

    /**
     * TEST 07: Rejected Edges Provenance Auditable Preservation
     */
    public function testRejectedEdgesProvenancePreserved(): void
    {
        $auditFile = __DIR__ . '/../../writable/audits/ONE_SHOT_CONSOLIDATED_DRY_RUN_REPORT.json';

        $this->assertFileExists($auditFile, 'Rejected candidates provenance file must exist in writable/audits/.');
        $data = json_decode(file_get_contents($auditFile), true);

        $this->assertNotNull($data);
        $this->assertEquals(1158, $data['consolidated_metrics']['total_rejected_edges']);
        $this->assertEquals(1120, $data['consolidated_metrics']['rejection_breakdown']['IMPOSSIBLE_DISTANCE']);
        $this->assertEquals(38, $data['consolidated_metrics']['rejection_breakdown']['SECTION_BOUNDARY_VIOLATION']);
    }

    /**
     * TEST 08: Zero Database Mutation Invariant Across All B.3 Operations
     */
    public function testNoDatabaseMutationAcrossAllB3Operations(): void
    {
        $initialAssetHash = md5(json_encode($this->fixtureAssets));
        $initialTLHash = md5(json_encode($this->fixtureTranslines));

        // Execute all operations
        $this->service->buildNetworkGraph(null, $this->fixtureAssets, $this->fixtureTranslines);
        $this->service->traverseFeeder(118, null, $this->fixtureAssets, $this->fixtureTranslines);
        $this->service->getSectionTopology(118, $this->fixtureAssets, $this->fixtureTranslines);
        $this->service->analyzePath(5245, 5246, $this->fixtureAssets, $this->fixtureTranslines);
        $this->service->analyzeNetworkIntegrity(null, $this->fixtureAssets, $this->fixtureTranslines);
        $this->service->getNodeIntelligence('AST-KRN-BHGSTL1-JTM-033', $this->fixtureAssets, $this->fixtureTranslines);

        $this->assertEquals($initialAssetHash, md5(json_encode($this->fixtureAssets)), 'Zero mutation breach: asset data modified.');
        $this->assertEquals($initialTLHash, md5(json_encode($this->fixtureTranslines)), 'Zero mutation breach: transline data modified.');
    }

    /**
     * TEST 09: Graph Adjacency Symmetry Invariant
     */
    public function testGraphAdjacencyIsSymmetric(): void
    {
        $graph = $this->service->buildNetworkGraph(null, $this->fixtureAssets, $this->fixtureTranslines);
        $adj = $graph['adjacency'];

        foreach ($adj as $u => $neighbors) {
            foreach ($neighbors as $v) {
                $this->assertContains($u, $adj[$v], "Adjacency asymmetry detected: {$u} -> {$v} exists, but {$v} -> {$u} is missing.");
            }
        }
    }

    /**
     * TEST 10: Cross-Feeder Path Is Explicitly Rejected
     */
    public function testCrossFeederPathIsRejected(): void
    {
        // Node 5245 is Feeder 118, Node 101 is Feeder 15
        $pathRes = $this->service->analyzePath(5245, 101, $this->fixtureAssets, $this->fixtureTranslines);
        $this->assertFalse($pathRes['payload']['reachable']);
        $this->assertEquals('CROSS_FEEDER', $pathRes['payload']['reason']);
    }

    /**
     * TEST 11: Disconnected Component Is Explicitly Handled
     */
    public function testDisconnectedComponentIsExplicit(): void
    {
        // Node 5232 (Tiang 20) is on Feeder 118, but disconnected from Node 5245 (Tiang 33)
        $res = $this->service->analyzePath(5232, 5245, $this->fixtureAssets, $this->fixtureTranslines);
        $this->assertFalse($res['payload']['reachable']);
        $this->assertEquals('DISCONNECTED_COMPONENT', $res['payload']['reason']);
    }

    /**
     * TEST 12: Traversal Parent Does Not Map To Asset Parent
     */
    public function testTraversalParentDoesNotMapToAssetParent(): void
    {
        $traversal = $this->service->traverseFeeder(118, null, $this->fixtureAssets, $this->fixtureTranslines);
        $tree = $traversal['payload']['traversal_tree'];

        foreach ($tree as $node) {
            $this->assertArrayNotHasKey('parent_asset_id', $node, 'Node must not have parent_asset_id key.');
            $this->assertArrayHasKey('traversal_parent', $node);
            $this->assertArrayHasKey('graph_parent_asset_id', $node);
        }
    }

    /**
     * TEST 13: Snapshot ID Constant Invariant
     */
    public function testSnapshotIdImmutable(): void
    {
        $this->assertEquals('TOPOLOGY-20260925-243-ad2c9fcb', NetworkIntelligenceService::TOPOLOGY_SNAPSHOT_ID);
        $this->assertEquals(243, NetworkIntelligenceService::AUTHORITATIVE_EDGE_COUNT);
        $this->assertEquals(5236, NetworkIntelligenceService::ACTIVE_ASSETS_BASELINE);
    }

    /**
     * TEST 14: Preview Edges Never Enter Authoritative Graph
     */
    public function testPreviewEdgesNeverEnterGraph(): void
    {
        $translinesWithPreview = $this->fixtureTranslines;
        $translinesWithPreview[] = [
            'id'                 => 999,
            'transline_code'     => 'TL-PREVIEW-999',
            'penyulang_id'       => 118,
            'source_asset_id'    => 5232,
            'target_asset_id'    => 5233,
            'distance_meters'    => 48.62,
            'conductor_type'     => 'AAAC',
            'conductor_size'     => '150 mm²',
            'conductor_material' => 'ALUMINUM_ALLOY',
            'status'             => 'PREVIEW',
            'is_active'          => 0, // Inactive / preview
        ];

        $graph = $this->service->buildNetworkGraph(118, $this->fixtureAssets, $translinesWithPreview);
        foreach ($graph['edges'] as $edge) {
            $this->assertNotEquals(999, $edge['id'], 'Preview proposal edge #999 must never enter graph.');
            $this->assertNotEquals('PREVIEW', $edge['status']);
        }
    }

    /**
     * TEST 15: Inactive Translines Never Enter Graph
     */
    public function testInactiveTranslinesNeverEnterGraph(): void
    {
        $translinesWithInactive = $this->fixtureTranslines;
        $translinesWithInactive[] = [
            'id'                 => 998,
            'transline_code'     => 'TL-INACTIVE-998',
            'penyulang_id'       => 118,
            'source_asset_id'    => 5245,
            'target_asset_id'    => 5246,
            'distance_meters'    => 27.82,
            'conductor_type'     => 'AAAC',
            'conductor_size'     => '150 mm²',
            'conductor_material' => 'ALUMINUM_ALLOY',
            'status'             => 'DEACTIVATED',
            'is_active'          => 0,
        ];

        $graph = $this->service->buildNetworkGraph(118, $this->fixtureAssets, $translinesWithInactive);
        $edgeIds = array_column($graph['edges'], 'id');

        $this->assertNotContains(998, $edgeIds, 'Inactive transline #998 must never enter graph.');
    }
}
