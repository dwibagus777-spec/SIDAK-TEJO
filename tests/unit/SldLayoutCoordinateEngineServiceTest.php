<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SldTopologyReadModelService;
use App\Services\SldSemanticClassificationService;
use App\Services\SldLayoutCoordinateEngineService;

/**
 * Class SldLayoutCoordinateEngineServiceTest
 *
 * Enforces all 31 Acceptance Test Scenarios for SLD-04:
 *
 * Core:
 * 1. F15 layout builds successfully
 * 2. 205 nodes preserved
 * 3. 197 edges preserved
 * 4. 6 components preserved
 * 5. Node #3231 remains root
 * 6. Parent-child identical to SLD-03
 * 7. No topology invention
 *
 * Geometry:
 * 8. Deterministic coordinates
 * 9. No node collision (collisions = 0)
 * 10. No duplicate coordinate
 * 11. Rooted tree crossing target = 0
 * 12. Depth monotonicity in main network
 * 13. Stable sibling ordering
 * 14. Stable line-section mapping (60 sections)
 *
 * Isolation:
 * 15. C15-02..06 remain unrooted in UNCONNECTED_COMPONENTS
 * 16. Isolated #3203/#3263 remain in ISOLATED_ASSETS
 * 17. No artificial GI connection
 *
 * Geographic:
 * 18. Lat/lon preserved
 * 19. Schematic coordinates do not overwrite geographic
 * 20. GPS not used as topology authority
 *
 * GTT / Devices:
 * 21. 23 GTT preserved
 * 22. 4 switch candidates preserved
 * 23. UNKNOWN_OPERATIONAL_STATE preserved
 * 24. device_role unchanged
 * 25. topology_role unchanged (205 partition)
 *
 * Safety:
 * 26. Zero DB writes (Delta = 0)
 * 27. Zero renderer code
 * 28. Zero SVG
 * 29. Zero Canvas
 * 30. Zero CSS
 * 31. Deterministic SHA-256
 */
class SldLayoutCoordinateEngineServiceTest extends CIUnitTestCase
{
    protected SldTopologyReadModelService $topoService;
    protected SldSemanticClassificationService $semanticService;
    protected SldLayoutCoordinateEngineService $layoutService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topoService = new SldTopologyReadModelService();
        $this->semanticService = new SldSemanticClassificationService($this->topoService);
        $this->layoutService = new SldLayoutCoordinateEngineService($this->semanticService);
    }

    // ==========================================
    // CORE TESTS (1 - 7)
    // ==========================================

    public function test1_F15LayoutBuildsSuccessfully(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertEquals('success', $res['status']);
        $this->assertEquals('FEEDER', $res['scope']['type']);
        $this->assertEquals(15, $res['scope']['feeder_id']);
    }

    public function test2_Feeder15NodesCountPreserved(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertCount(205, $res['nodes']);
        $this->assertEquals(205, $res['diagnostics']['total_nodes']);
    }

    public function test3_Feeder15EdgesCountPreserved(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertCount(197, $res['edges']);
        $this->assertEquals(197, $res['diagnostics']['total_edges']);
    }

    public function test4_Feeder15ComponentsCountPreserved(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertCount(6, $res['components']);
    }

    public function test5_Node3231RemainsRoot(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertEquals(3231, $res['source']['asset_id']);
        $this->assertEquals('AUTHORITATIVE', $res['source']['status']);

        $nodeMap = array_column($res['nodes'], null, 'asset_id');
        $root = $nodeMap[3231];

        $this->assertEquals(0, $root['schematic']['grid_x']);
        $this->assertEquals(0, $root['schematic']['grid_y']);
        $this->assertEquals(0, $root['depth_from_source']);
        $this->assertNull($root['parent_asset_id']);
        $this->assertEquals('MAIN_NETWORK', $root['zone']);
    }

    public function test6_ParentChildIdenticalToSld03(): void
    {
        $semRes = $this->semanticService->buildSemanticHierarchy(15);
        $layoutRes = $this->layoutService->buildFeederLayout(15);

        $semMap = array_column($semRes['nodes'], null, 'asset_id');
        $layoutMap = array_column($layoutRes['nodes'], null, 'asset_id');

        foreach ($semMap as $id => $sNode) {
            $lNode = $layoutMap[$id];
            $this->assertEquals($sNode['parent_asset_id'], $lNode['parent_asset_id'], "Parent of asset $id must be identical");
            $this->assertEquals($sNode['child_asset_ids'], $lNode['child_asset_ids'], "Children of asset $id must be identical");
        }
    }

    public function test7_NoTopologyInvention(): void
    {
        $semRes = $this->semanticService->buildSemanticHierarchy(15);
        $layoutRes = $this->layoutService->buildFeederLayout(15);

        $semEdgeIds = array_column($semRes['edges'], 'transline_id');
        $layoutEdgeIds = array_column($layoutRes['edges'], 'transline_id');

        $this->assertEqualsCanonicalizing($semEdgeIds, $layoutEdgeIds);
    }

    // ==========================================
    // GEOMETRY TESTS (8 - 14)
    // ==========================================

    public function test8_DeterministicCoordinates(): void
    {
        $res1 = $this->layoutService->buildFeederLayout(15);
        $res2 = $this->layoutService->buildFeederLayout(15);

        $coords1 = array_map(fn($n) => $n['schematic'], $res1['nodes']);
        $coords2 = array_map(fn($n) => $n['schematic'], $res2['nodes']);

        $this->assertSame($coords1, $coords2);
    }

    public function test9_NoNodeCollision(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertEquals(0, $res['diagnostics']['node_collisions']);
    }

    public function test10_NoDuplicateCoordinate(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $seenCoords = [];

        foreach ($res['nodes'] as $n) {
            $key = "{$n['schematic']['grid_x']},{$n['schematic']['grid_y']}";
            $this->assertArrayNotHasKey($key, $seenCoords, "Duplicate coordinate $key detected for asset {$n['asset_id']}");
            $seenCoords[$key] = $n['asset_id'];
        }

        $this->assertCount(205, $seenCoords);
    }

    public function test11_RootedTreeCrossingTargetZero(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertEquals(0, $res['diagnostics']['rooted_tree_edge_crossings']);
    }

    public function test12_DepthMonotonicityInMainNetwork(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $nodeMap = array_column($res['nodes'], null, 'asset_id');

        $mainNodes = array_filter($res['nodes'], fn($n) => $n['zone'] === 'MAIN_NETWORK');

        foreach ($mainNodes as $node) {
            $parentId = $node['parent_asset_id'];
            if ($parentId !== null && isset($nodeMap[$parentId])) {
                $parent = $nodeMap[$parentId];
                $this->assertGreaterThan(
                    $parent['schematic']['grid_x'],
                    $node['schematic']['grid_x'],
                    "Child {$node['asset_id']} must have grid_x strictly greater than parent $parentId"
                );
            }
        }
    }

    public function test13_StableSiblingOrdering(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $nodeMap = array_column($res['nodes'], null, 'asset_id');

        // Node #3188 has multiple children: 3187 and 3238
        $this->assertArrayHasKey(3188, $nodeMap);
        $n3188 = $nodeMap[3188];
        $children = $n3188['child_asset_ids'];
        $this->assertCount(2, $children);

        // Child 3238 has larger subtree than child 3187 (leaf GTT)
        // Primary trunk (3238) continues horizontally at same Y, 3187 branches
        $c0 = $nodeMap[$children[0]];
        $c1 = $nodeMap[$children[1]];

        $this->assertNotEquals($c0['schematic']['grid_y'], $c1['schematic']['grid_y']);
    }

    public function test14_StableLineSectionMapping(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertCount(60, $res['line_sections']);

        $totalSpans = 0;
        foreach ($res['line_sections'] as $sec) {
            $this->assertNotNull($sec['layout']['start']);
            $this->assertNotNull($sec['layout']['end']);
            $totalSpans += $sec['span_count'];
        }

        $this->assertEquals(197, $totalSpans);
    }

    // ==========================================
    // ISOLATION TESTS (15 - 17)
    // ==========================================

    public function test15_UnrootedComponentsRemainUnrootedInDedicatedZone(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $unconnectedNodes = array_filter($res['nodes'], fn($n) => $n['zone'] === 'UNCONNECTED_COMPONENTS');

        $this->assertCount(20, $unconnectedNodes);
        foreach ($unconnectedNodes as $n) {
            $this->assertNull($n['parent_asset_id']);
            $this->assertNull($n['depth_from_source']);
        }
    }

    public function test16_IsolatedNodesRemainIsolated(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $isoNodes = array_filter($res['nodes'], fn($n) => $n['zone'] === 'ISOLATED_ASSETS');

        $this->assertCount(2, $isoNodes);
        $isoIds = array_column($isoNodes, 'asset_id');
        $this->assertEqualsCanonicalizing([3203, 3263], $isoIds);

        foreach ($isoNodes as $iso) {
            $this->assertNull($iso['parent_asset_id']);
            $this->assertNull($iso['depth_from_source']);
        }
    }

    public function test17_NoArtificialGiConnection(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $rootId = $res['source']['asset_id'];

        foreach ($res['edges'] as $edge) {
            // Edges to/from root must only be with Node 3300 (actual physical span)
            if ($edge['source_asset_id'] === $rootId) {
                $this->assertEquals(3300, $edge['target_asset_id']);
            }
            if ($edge['target_asset_id'] === $rootId) {
                $this->assertEquals(3300, $edge['source_asset_id']);
            }
        }
    }

    // ==========================================
    // GEOGRAPHIC TESTS (18 - 20)
    // ==========================================

    public function test18_LatLonPreserved(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        foreach ($res['nodes'] as $n) {
            $this->assertArrayHasKey('latitude', $n['geo']);
            $this->assertArrayHasKey('longitude', $n['geo']);
            $this->assertNotEmpty($n['geo']['latitude']);
            $this->assertNotEmpty($n['geo']['longitude']);
        }
    }

    public function test19_SchematicDoesNotOverwriteGeographic(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        foreach ($res['nodes'] as $n) {
            $this->assertIsInt($n['schematic']['grid_x']);
            $this->assertIsInt($n['schematic']['grid_y']);
            $this->assertIsFloat($n['geo']['latitude']);
            $this->assertIsFloat($n['geo']['longitude']);
        }
    }

    public function test20_GpsNotUsedAsTopologyAuthority(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertEquals('SCHEMATIC_GRID', $res['coordinate_system']['type']);
        $this->assertEquals('GRID', $res['coordinate_system']['unit']);
    }

    // ==========================================
    // GTT / DEVICES TESTS (21 - 25)
    // ==========================================

    public function test21_23GttPreserved(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $gtts = array_filter($res['nodes'], fn($n) => $n['device_role'] === 'TRANSFORMER_NODE');
        $this->assertCount(23, $gtts);
    }

    public function test22_4SwitchCandidatesPreserved(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $switches = array_filter($res['nodes'], fn($n) => $n['device_role'] === 'SWITCH_CANDIDATE');
        $this->assertCount(4, $switches);
    }

    public function test23_UnknownOperationalStatePreserved(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $switches = array_filter($res['nodes'], fn($n) => $n['device_role'] === 'SWITCH_CANDIDATE');

        foreach ($switches as $sw) {
            $this->assertEquals('UNKNOWN_OPERATIONAL_STATE', $sw['operational_state']);
        }

        $jsonStr = json_encode($res);
        $this->assertStringNotContainsString('NORMAL_CLOSED_CANDIDATE', $jsonStr);
    }

    public function test24_DeviceRoleUnchanged(): void
    {
        $semRes = $this->semanticService->buildSemanticHierarchy(15);
        $layoutRes = $this->layoutService->buildFeederLayout(15);

        $semRoles = array_column($semRes['nodes'], 'device_role', 'asset_id');
        $layoutRoles = array_column($layoutRes['nodes'], 'device_role', 'asset_id');

        $this->assertEquals($semRoles, $layoutRoles);
    }

    public function test25_TopologyRoleUnchangedAnd205Partition(): void
    {
        $layoutRes = $this->layoutService->buildFeederLayout(15);
        $topoRoles = [];

        foreach ($layoutRes['nodes'] as $n) {
            $tr = $n['topology_role'];
            $topoRoles[$tr] = ($topoRoles[$tr] ?? 0) + 1;
        }

        $this->assertEquals(1, $topoRoles['SOURCE_INCOMER']);
        $this->assertEquals(30, $topoRoles['TERMINAL_NODE']);
        $this->assertEquals(153, $topoRoles['PASS_THROUGH']);
        $this->assertEquals(19, $topoRoles['BRANCH_NODE']);
        $this->assertEquals(2, $topoRoles['ISOLATED_NODE']);
        $this->assertEquals(205, array_sum($topoRoles));
    }

    // ==========================================
    // SAFETY & INVARIANT TESTS (26 - 31)
    // ==========================================

    public function test26_ZeroDatabaseWrites(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertTrue($res['diagnostics']['zero_writes_verified']);
    }

    public function test27_ZeroRendererCode(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $json = json_encode($res);

        $this->assertStringNotContainsString('<svg', $json);
        $this->assertStringNotContainsString('<path', $json);
        $this->assertStringNotContainsString('<circle', $json);
        $this->assertStringNotContainsString('<rect', $json);
    }

    public function test28_ZeroSvg(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertArrayNotHasKey('svg', $res);
    }

    public function test29_ZeroCanvasPixels(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertEquals('GRID', $res['coordinate_system']['unit']);
        $this->assertIsInt($res['canvas']['grid_width']);
        $this->assertIsInt($res['canvas']['grid_height']);
    }

    public function test30_ZeroCss(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $json = json_encode($res);

        $this->assertStringNotContainsString('style=', $json);
        $this->assertStringNotContainsString('.sld-', $json);
    }

    public function test31_DeterministicSha256(): void
    {
        $json1 = json_encode($this->layoutService->buildFeederLayout(15));
        $json2 = json_encode($this->layoutService->buildFeederLayout(15));

        $this->assertSame($json1, $json2);
        $this->assertSame(hash('sha256', $json1), hash('sha256', $json2));
    }
}
