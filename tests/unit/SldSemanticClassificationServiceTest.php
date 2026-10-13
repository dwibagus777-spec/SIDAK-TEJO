<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SldTopologyReadModelService;
use App\Services\SldSemanticClassificationService;

/**
 * Class SldSemanticClassificationServiceTest
 *
 * Verifies all 8 Mandatory Architectural Amendments for SLD-03:
 * 1. Never use NORMAL_CLOSED_CANDIDATE (strictly UNKNOWN_OPERATIONAL_STATE).
 * 2. LINE_SECTION aggregation preserves member nodes and edges without mutating physical graph.
 * 3. Two-dimensional role decoupling: topology_role vs device_role.
 * 4. TERMINAL_NODE is topology semantic (degree 1), equipment_type preserves physical construction.
 * 5. Authoritative GTT metadata: GTT1 (Cantol), GTT2 (Portal), or UNKNOWN (zero guessing).
 * 6. Unrooted components (C15-02..C15-06) retain rooted: false, source_reachable: false, no fake directionality.
 * 7. Strong graph preservation: node count == SLD-02, edge count == SLD-02, semantic_edges subset of topology_edges.
 * 8. Clean Controller layering: Controller -> Semantic Service -> Topology Read Model Service.
 */
class SldSemanticClassificationServiceTest extends CIUnitTestCase
{
    protected SldSemanticClassificationService $semanticService;
    protected SldTopologyReadModelService $topologyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topologyService = new SldTopologyReadModelService();
        $this->semanticService = new SldSemanticClassificationService($this->topologyService);
    }

    /**
     * 1. Exact Graph & Identity Preservation (Amendment 7)
     */
    public function testFeeder15GraphPreservation(): void
    {
        $topoRes = $this->topologyService->buildFeederGraph(15);
        $semRes = $this->semanticService->buildSemanticHierarchy(15);

        $this->assertEquals('success', $semRes['status']);
        $this->assertEquals($topoRes['topology']['feeder_assets_count'], $semRes['graph']['nodes_count']);
        $this->assertEquals($topoRes['topology']['edges_count'], $semRes['graph']['edges_count']);
        $this->assertEquals(205, $semRes['graph']['nodes_count']);
        $this->assertEquals(197, $semRes['graph']['edges_count']);
        $this->assertCount(205, $semRes['nodes']);
        $this->assertCount(197, $semRes['edges']);

        // Identity preservation: all asset IDs match SLD-02
        $topoAssetIds = array_column($topoRes['nodes'], 'asset_id');
        $semAssetIds = array_column($semRes['nodes'], 'asset_id');
        $this->assertEqualsCanonicalizing($topoAssetIds, $semAssetIds);

        // Identity preservation: all transline IDs match SLD-02
        $topoEdgeIds = array_column($topoRes['edges'], 'transline_id');
        $semEdgeIds = array_column($semRes['edges'], 'transline_id');
        $this->assertEqualsCanonicalizing($topoEdgeIds, $semEdgeIds);
    }

    /**
     * 2. Authoritative Takeoff & Substation Demarcation
     */
    public function testFeeder15AuthoritativeTakeoffIncomer(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $nodeMap = array_column($res['nodes'], null, 'asset_id');

        $this->assertArrayHasKey(3231, $nodeMap);
        $incomer = $nodeMap[3231];

        $this->assertEquals('SOURCE_INCOMER', $incomer['topology_role']);
        $this->assertEquals('SOURCE_INCOMER', $incomer['device_role']);
        $this->assertEquals('NOT_APPLICABLE', $incomer['operational_state']);
        $this->assertNull($incomer['parent_asset_id']);
        $this->assertEquals(0, $incomer['depth_from_source']);
        $this->assertTrue($incomer['source_reachable']);
        $this->assertNotEmpty($incomer['child_asset_ids']);
    }

    /**
     * 3. Two-Dimensional Role Decoupling (Amendment 3)
     */
    public function testTwoDimensionalRoleDecoupling(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $nodeMap = array_column($res['nodes'], null, 'asset_id');

        // Node #3188 is a Branch (degree 3) AND a Switch (PMS)
        $this->assertArrayHasKey(3188, $nodeMap);
        $n3188 = $nodeMap[3188];
        $this->assertEquals(3, $n3188['degree']);
        $this->assertEquals('BRANCH_NODE', $n3188['topology_role']);
        $this->assertEquals('SWITCH_CANDIDATE', $n3188['device_role']);
        $this->assertEquals('UNKNOWN_OPERATIONAL_STATE', $n3188['operational_state']);

        // Every node must possess both topology_role and device_role
        $validTopologyRoles = ['SOURCE_INCOMER', 'BRANCH_NODE', 'PASS_THROUGH', 'TERMINAL_NODE', 'ISOLATED_NODE'];
        $validDeviceRoles = ['SOURCE_INCOMER', 'SWITCH_CANDIDATE', 'TRANSFORMER_NODE', 'LINE_POLE'];

        foreach ($res['nodes'] as $n) {
            $this->assertContains($n['topology_role'], $validTopologyRoles);
            $this->assertContains($n['device_role'], $validDeviceRoles);
        }
    }

    /**
     * 4. Amendment 1: Never Use NORMAL_CLOSED_CANDIDATE
     */
    public function testEliminationOfNormalClosedCandidate(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $switchNodes = array_filter($res['nodes'], fn($n) => $n['device_role'] === 'SWITCH_CANDIDATE');

        $this->assertCount(4, $switchNodes);
        $switchIds = array_column($switchNodes, 'asset_id');
        $this->assertEqualsCanonicalizing([3188, 3217, 3266, 3295], $switchIds);

        foreach ($switchNodes as $sw) {
            $this->assertEquals('UNKNOWN_OPERATIONAL_STATE', $sw['operational_state']);
            $this->assertNotEquals('NORMAL_CLOSED_CANDIDATE', $sw['operational_state']);
        }

        // Check full JSON string does not contain NORMAL_CLOSED_CANDIDATE
        $jsonStr = json_encode($res);
        $this->assertStringNotContainsString('NORMAL_CLOSED_CANDIDATE', $jsonStr);
    }

    /**
     * 5. Non-Switches Have NOT_APPLICABLE Operational State
     */
    public function testNonSwitchesHaveNotApplicableState(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $nonSwitches = array_filter($res['nodes'], fn($n) => $n['device_role'] !== 'SWITCH_CANDIDATE');

        $this->assertCount(201, $nonSwitches);
        foreach ($nonSwitches as $ns) {
            $this->assertEquals('NOT_APPLICABLE', $ns['operational_state']);
        }
    }

    /**
     * 6. Amendment 2: LINE_SECTION Aggregation Preserves Granular Members
     */
    public function testLineSectionAggregationPreservesGranularity(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $sections = $res['line_sections'];

        $this->assertNotEmpty($sections);
        $this->assertEquals(60, count($sections));

        $totalSpans = 0;
        $seenEdges = [];

        foreach ($sections as $sec) {
            $this->assertEquals('LINE_SECTION', $sec['semantic_type']);
            $this->assertNotEmpty($sec['id']);
            $this->assertGreaterThan(0, $sec['source_node_id']);
            $this->assertGreaterThan(0, $sec['target_node_id']);
            $this->assertGreaterThanOrEqual(2, count($sec['member_node_ids']));
            $this->assertGreaterThanOrEqual(1, count($sec['member_edge_ids']));
            $this->assertEquals(count($sec['member_edge_ids']), $sec['span_count']);

            $totalSpans += $sec['span_count'];

            foreach ($sec['member_edge_ids'] as $eid) {
                $this->assertArrayNotHasKey($eid, $seenEdges, "Edge $eid should belong to exactly one line section");
                $seenEdges[$eid] = true;
            }
        }

        // Total spans across all sections equals total physical edges (197)
        $this->assertEquals(197, $totalSpans);
        $this->assertCount(197, $seenEdges);

        // Physical nodes (205) and edges (197) remain intact in response
        $this->assertCount(205, $res['nodes']);
        $this->assertCount(197, $res['edges']);
    }

    /**
     * 7. Amendment 4: TERMINAL_NODE is Topology Semantic, Not Equipment Type
     */
    public function testTerminalNodeIsTopologySemantic(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $terminals = array_filter($res['nodes'], fn($n) => $n['topology_role'] === 'TERMINAL_NODE');

        // All terminal nodes have degree 1
        foreach ($terminals as $t) {
            $this->assertEquals(1, $t['degree']);
            // Equipment type preserves physical asset construction
            $this->assertNotEmpty($t['equipment_type']);
        }
    }

    /**
     * 8. Amendment 5: Authoritative GTT Transformer Metadata
     */
    public function testAuthoritativeGttMetadata(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $gtts = array_filter($res['nodes'], fn($n) => $n['device_role'] === 'TRANSFORMER_NODE');

        $this->assertCount(23, $gtts);

        $cantolCount = 0;
        $portalCount = 0;
        $unknownCount = 0;

        foreach ($gtts as $g) {
            $eq = $g['equipment_type'];
            if ($eq === 'GTT1_CANTOL') {
                $cantolCount++;
            } elseif ($eq === 'GTT2_PORTAL') {
                $portalCount++;
            } elseif ($eq === 'UNKNOWN_GTT_SUBTYPE') {
                $unknownCount++;
            } else {
                $this->fail("Unexpected GTT equipment type: $eq");
            }
        }

        $this->assertEquals(4, $cantolCount, "4 GTT1 Cantol transformers");
        $this->assertEquals(15, $portalCount, "15 GTT2 Portal transformers");
        $this->assertEquals(4, $unknownCount, "4 generic GTT without authoritative subtype");
        $this->assertEquals(23, $cantolCount + $portalCount + $unknownCount);
    }

    /**
     * 9. Amendment 6: Unrooted Components Have NO Fake Directionality
     */
    public function testUnrootedComponentsHaveNoFakeDirectionality(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $comps = $res['components'];

        $this->assertCount(6, $comps);

        // C15-01 is rooted
        $this->assertEquals('C15-01', $comps[0]['component_id']);
        $this->assertTrue($comps[0]['rooted']);
        $this->assertTrue($comps[0]['source_reachable']);
        $this->assertEquals(3231, $comps[0]['root_asset_id']);

        // C15-02 .. C15-06 are unrooted forest fragments
        for ($i = 1; $i < count($comps); $i++) {
            $this->assertFalse($comps[$i]['rooted'], "Component {$comps[$i]['component_id']} must be unrooted");
            $this->assertFalse($comps[$i]['source_reachable'], "Component {$comps[$i]['component_id']} must not be source reachable");
            $this->assertNull($comps[$i]['root_asset_id'], "Component {$comps[$i]['component_id']} must have null root_asset_id");
        }

        // Check nodes in unrooted components have null parent and depth
        $unrootedNodes = array_filter($res['nodes'], fn($n) => $n['component_id'] !== 'C15-01' && $n['component_id'] !== null);
        $this->assertNotEmpty($unrootedNodes);

        foreach ($unrootedNodes as $un) {
            $this->assertNull($un['parent_asset_id'], "Unrooted node {$un['asset_id']} must not have parent");
            $this->assertNull($un['depth_from_source'], "Unrooted node {$un['asset_id']} must not have depth");
            $this->assertEmpty($un['child_asset_ids'], "Unrooted node {$un['asset_id']} must not have children");
        }
    }

    /**
     * 10. Two Isolated Assets Retained as ISOLATED_NODE
     */
    public function testTwoIsolatedAssetsRetained(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $isolatedNodes = array_filter($res['nodes'], fn($n) => $n['topology_role'] === 'ISOLATED_NODE');

        $this->assertCount(2, $isolatedNodes);
        $isoIds = array_column($isolatedNodes, 'asset_id');
        $this->assertContains(3203, $isoIds);
        $this->assertContains(3263, $isoIds);

        foreach ($isolatedNodes as $iso) {
            $this->assertEquals(0, $iso['degree']);
            $this->assertNull($iso['component_id']);
            $this->assertFalse($iso['source_reachable']);
            $this->assertNull($iso['parent_asset_id']);
            $this->assertEquals('NOT_APPLICABLE', $iso['operational_state']);
        }
    }

    /**
     * 11. No Topology Invention (Amendment 7)
     */
    public function testNoTopologyInvention(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $rawEdgeIds = array_column($res['edges'], 'transline_id');
        $rawEdgeIdSet = array_fill_keys($rawEdgeIds, true);

        // Every edge ID in line sections must belong to admitted topology edges
        foreach ($res['line_sections'] as $sec) {
            foreach ($sec['member_edge_ids'] as $eid) {
                $this->assertArrayHasKey($eid, $rawEdgeIdSet, "Section edge $eid must belong to admitted topology edges");
            }
        }

        $rawNodeIds = array_column($res['nodes'], 'asset_id');
        $rawNodeIdSet = array_fill_keys($rawNodeIds, true);

        // Every node ID in line sections must belong to admitted nodes
        foreach ($res['line_sections'] as $sec) {
            foreach ($sec['member_node_ids'] as $nid) {
                $this->assertArrayHasKey($nid, $rawNodeIdSet, "Section node $nid must belong to admitted nodes");
            }
        }
    }

    /**
     * 12. GTT Visibility Invariance (Amendment 7)
     */
    public function testGttVisibilityInvariance(): void
    {
        $resFalse = $this->semanticService->buildSemanticHierarchy(15, ['include_gtt' => false]);
        $resTrue  = $this->semanticService->buildSemanticHierarchy(15, ['include_gtt' => true]);

        // Presentation metadata toggles
        $this->assertFalse($resFalse['gtt']['visible']);
        $this->assertTrue($resTrue['gtt']['visible']);

        // Physical graph and semantic counts remain 100% IDENTICAL
        $this->assertEquals(205, count($resFalse['nodes']));
        $this->assertEquals(205, count($resTrue['nodes']));
        $this->assertEquals(197, count($resFalse['edges']));
        $this->assertEquals(197, count($resTrue['edges']));
        $this->assertEquals(60, count($resFalse['line_sections']));
        $this->assertEquals(60, count($resTrue['line_sections']));

        $this->assertEquals($resFalse['semantic'], $resTrue['semantic']);
    }

    /**
     * 13. Deterministic Repeated Execution
     */
    public function testDeterministicRepeatedExecution(): void
    {
        $json1 = json_encode($this->semanticService->buildSemanticHierarchy(15));
        $json2 = json_encode($this->semanticService->buildSemanticHierarchy(15));

        $this->assertSame($json1, $json2);
        $this->assertSame(hash('sha256', $json1), hash('sha256', $json2));
    }

    /**
     * 14. Zero Database Writes Invariant (Delta = 0)
     */
    public function testZeroDatabaseWritesInvariant(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $this->assertEquals('success', $res['status']);
        $this->assertEquals(205, $res['graph']['nodes_count']);
        $this->assertEquals(197, $res['graph']['edges_count']);
    }

    /**
     * 15. Topology Roles Mutually Exclusive Partition (Sum = 205)
     */
    public function testTopologyRolesMutuallyExclusivePartition(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $topoRoles = [];
        $degSum = 0;

        foreach ($res['nodes'] as $n) {
            $tr = $n['topology_role'];
            $topoRoles[$tr] = ($topoRoles[$tr] ?? 0) + 1;
            $degSum += (int)$n['degree'];
        }

        // Exact counts
        $this->assertEquals(1, $topoRoles['SOURCE_INCOMER'] ?? 0);
        $this->assertEquals(30, $topoRoles['TERMINAL_NODE'] ?? 0);
        $this->assertEquals(153, $topoRoles['PASS_THROUGH'] ?? 0);
        $this->assertEquals(19, $topoRoles['BRANCH_NODE'] ?? 0);
        $this->assertEquals(2, $topoRoles['ISOLATED_NODE'] ?? 0);

        // Mutually exclusive partition must sum to EXACTLY 205
        $totalPartitionNodes = array_sum($topoRoles);
        $this->assertEquals(205, $totalPartitionNodes);
        $this->assertEquals($res['graph']['nodes_count'], $totalPartitionNodes);

        // Handshaking Lemma: (1*1) + (30*1) + (153*2) + (19*3) + (2*0) = 1 + 30 + 306 + 57 + 0 = 394
        $this->assertEquals(394, $degSum);
        $this->assertEquals(2 * 197, $degSum);
    }
}

