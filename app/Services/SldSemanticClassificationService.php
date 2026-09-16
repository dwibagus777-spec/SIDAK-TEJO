<?php

namespace App\Services;

/**
 * Class SldSemanticClassificationService
 *
 * SLD-03: Semantic Network Hierarchy & Device Classification Layer
 *
 * Governed by the 8 Mandatory Architectural Amendments:
 * 1. Never use NORMAL_CLOSED_CANDIDATE (Switches are UNKNOWN_OPERATIONAL_STATE, others NOT_APPLICABLE).
 * 2. LINE_SECTION aggregation preserves full pole granularity (member_node_ids, member_edge_ids).
 * 3. Two-dimensional role decoupling: topology_role vs device_role.
 * 4. TERMINAL_NODE is topology semantic (degree 1), equipment_type preserves physical construction.
 * 5. Authoritative GTT metadata: GTT1 (Cantol), GTT2 (Portal), or UNKNOWN (zero guessing).
 * 6. Unrooted components (C15-02..C15-06) retain rooted: false, source_reachable: false, no fake directionality.
 * 7. Strong graph preservation: node count == SLD-02, edge count == SLD-02, Delta = 0 invariant.
 * 8. Clean Controller layering: Controller -> Semantic Service -> Topology Read Model Service.
 */
class SldSemanticClassificationService
{
    protected SldTopologyReadModelService $topologyService;

    public function __construct(?SldTopologyReadModelService $topologyService = null)
    {
        $this->topologyService = $topologyService ?? new SldTopologyReadModelService();
    }

    /**
     * Build authoritative Semantic Network Hierarchy for a feeder.
     *
     * @param int $penyulangId
     * @param array $options ['include_gtt' => bool]
     * @return array
     */
    public function buildSemanticHierarchy(int $penyulangId, array $options = []): array
    {
        // Step 1: Ingest authoritative topology read model from SLD-02
        $rawGraph = $this->topologyService->buildFeederGraph($penyulangId, $options);

        if (($rawGraph['status'] ?? '') !== 'success') {
            return $rawGraph;
        }

        $takeoff = $rawGraph['source']['takeoff'] ?? null;
        $takeoffStatus = $takeoff['status'] ?? 'MISSING';
        $takeoffAssetId = ($takeoffStatus === 'AUTHORITATIVE') ? (int)($takeoff['asset_id'] ?? 0) : null;

        $rawNodes = $rawGraph['nodes'] ?? [];
        $rawEdges = $rawGraph['edges'] ?? [];
        $rawComponents = $rawGraph['components'] ?? [];

        // Build Adjacency & Edge Index
        $adj = [];
        $edgeLookup = [];
        $topologyEdgeIdSet = [];

        foreach ($rawEdges as $e) {
            $tlId = (int)$e['transline_id'];
            $u = (int)$e['source_asset_id'];
            $v = (int)$e['target_asset_id'];

            $adj[$u][] = $v;
            $adj[$v][] = $u;

            $k1 = "$u-$v";
            $k2 = "$v-$u";
            $edgeLookup[$k1] = $e;
            $edgeLookup[$k2] = $e;
            $topologyEdgeIdSet[$tlId] = true;
        }

        // Step 2: Component Classification & Rooting Analysis (Amendment 6)
        $semanticComponents = [];
        $rootedComponentMap = [];

        foreach ($rawComponents as $comp) {
            $compId = $comp['id'];
            $sourceReachable = (bool)($comp['source_reachable'] ?? false);
            $isRooted = ($sourceReachable && $takeoffAssetId !== null);

            $semanticComponents[] = [
                'component_id'     => $compId,
                'nodes_count'      => (int)$comp['node_count'],
                'edges_count'      => (int)$comp['edge_count'],
                'rooted'           => $isRooted,
                'source_reachable' => $sourceReachable,
                'root_asset_id'    => $isRooted ? $takeoffAssetId : null,
            ];

            $rootedComponentMap[$compId] = $isRooted;
        }

        // Step 3: Directed BFS Hierarchy on Rooted Component (C15-01)
        $parentMap = [];
        $childrenMap = [];
        $depthMap = [];

        if ($takeoffAssetId !== null && isset($adj[$takeoffAssetId])) {
            $queue = [$takeoffAssetId];
            $parentMap[$takeoffAssetId] = null;
            $depthMap[$takeoffAssetId] = 0;
            $visitedBfs = [$takeoffAssetId => true];

            while (!empty($queue)) {
                $curr = array_shift($queue);
                $currDepth = $depthMap[$curr];

                if (!isset($childrenMap[$curr])) {
                    $childrenMap[$curr] = [];
                }

                if (isset($adj[$curr])) {
                    foreach ($adj[$curr] as $nbr) {
                        if (!isset($visitedBfs[$nbr])) {
                            $visitedBfs[$nbr] = true;
                            $parentMap[$nbr] = $curr;
                            $childrenMap[$curr][] = $nbr;
                            $depthMap[$nbr] = $currDepth + 1;
                            $queue[] = $nbr;
                        }
                    }
                }
            }
        }

        // Step 4: Two-Dimensional Semantic Node Classification (Amendments 1, 3, 4, 5)
        $semanticNodes = [];
        $structuralNodeSet = [];
        $switchCandidatesCount = 0;
        $transformerNodesCount = 0;
        $branchNodesCount = 0;
        $terminalNodesCount = 0;
        $passThroughNodesCount = 0;
        $isolatedNodesCount = 0;

        foreach ($rawNodes as $node) {
            $assetId = (int)$node['asset_id'];
            $deg = (int)$node['degree'];
            $compId = $node['component_id'] ?? null;
            $isRootedComp = ($compId !== null && ($rootedComponentMap[$compId] ?? false));
            $isTakeoffNode = ($takeoffAssetId !== null && $assetId === $takeoffAssetId);

            // 1. Topology Role (Amendment 3 & 4)
            if ($isTakeoffNode) {
                $topologyRole = 'SOURCE_INCOMER';
            } elseif ($deg === 0) {
                $topologyRole = 'ISOLATED_NODE';
                $isolatedNodesCount++;
            } elseif ($deg === 1) {
                $topologyRole = 'TERMINAL_NODE';
                $terminalNodesCount++;
            } elseif ($deg === 2) {
                $topologyRole = 'PASS_THROUGH';
                $passThroughNodesCount++;
            } else {
                $topologyRole = 'BRANCH_NODE';
                $branchNodesCount++;
            }

            // 2. Device Role & Authoritative Equipment Metadata (Amendments 3, 4, 5)
            $isSwitch = (bool)($node['is_switch'] ?? false);
            $isGtt = (bool)($node['is_gtt'] ?? false);
            $rawCt = strtoupper(trim((string)($node['construction_type'] ?? '')));

            if ($isTakeoffNode) {
                $deviceRole = 'SOURCE_INCOMER';
                $equipmentType = $rawCt ?: 'SUBSTATION_INTERFACE';
                $operationalState = 'NOT_APPLICABLE';
            } elseif ($isSwitch) {
                $deviceRole = 'SWITCH_CANDIDATE';
                $switchCandidatesCount++;
                // Strictly UNKNOWN_OPERATIONAL_STATE per Amendment 1
                $operationalState = 'UNKNOWN_OPERATIONAL_STATE';
                $equipmentType = str_contains($rawCt, 'PMS') ? 'PMS' : (str_contains($rawCt, 'PMT') ? 'PMT' : 'SWITCH');
            } elseif ($isGtt) {
                $deviceRole = 'TRANSFORMER_NODE';
                $transformerNodesCount++;
                $operationalState = 'NOT_APPLICABLE';
                // Authoritative GTT Type Resolution (Amendment 5)
                if (str_contains($rawCt, 'GTT1') || str_contains($rawCt, 'CANTOL')) {
                    $equipmentType = 'GTT1_CANTOL';
                } elseif (str_contains($rawCt, 'GTT2') || str_contains($rawCt, 'PORTAL')) {
                    $equipmentType = 'GTT2_PORTAL';
                } else {
                    $equipmentType = 'UNKNOWN_GTT_SUBTYPE';
                }
            } else {
                $deviceRole = 'LINE_POLE';
                $operationalState = 'NOT_APPLICABLE';
                $equipmentType = $rawCt ?: 'TIANG_JTM';
            }

            // Mark structural node for Line Section segmentation
            if ($isTakeoffNode || $deg === 1 || $deg >= 3 || $isSwitch || $isGtt || $deg === 0) {
                $structuralNodeSet[$assetId] = true;
            }

            // Rooted hierarchy fields (Amendment 6: unrooted components have null/empty)
            $parentAssetId = $isRootedComp ? ($parentMap[$assetId] ?? null) : null;
            $childAssetIds = $isRootedComp ? ($childrenMap[$assetId] ?? []) : [];
            $depthFromSource = $isRootedComp ? ($depthMap[$assetId] ?? null) : null;

            $semanticNodes[] = [
                'asset_id'          => $assetId,
                'code'              => $node['code'],
                'name'              => $node['name'],
                'topology_role'     => $topologyRole,
                'device_role'       => $deviceRole,
                'equipment_type'    => $equipmentType,
                'operational_state' => $operationalState,
                'degree'            => $deg,
                'component_id'      => $compId,
                'source_reachable'  => (bool)($node['source_reachable'] ?? false),
                'parent_asset_id'   => $parentAssetId,
                'child_asset_ids'   => $childAssetIds,
                'depth_from_source' => $depthFromSource,
                'latitude'          => (float)($node['latitude'] ?? 0),
                'longitude'         => (float)($node['longitude'] ?? 0),
            ];
        }

        // Step 5: Synthesize LINE_SECTION Blocks (Amendment 2 & 7)
        // Aggregates continuous pass-through chains between structural nodes
        $visitedEdges = [];
        $lineSections = [];
        $secSeq = 1;

        // Trace from each structural node
        foreach (array_keys($structuralNodeSet) as $u) {
            if (!isset($adj[$u])) continue;

            foreach ($adj[$u] as $v) {
                $edgeKey = $u < $v ? "$u-$v" : "$v-$u";
                if (isset($visitedEdges[$edgeKey])) continue;

                $visitedEdges[$edgeKey] = true;
                $pathNodes = [$u, $v];
                $pathEdges = [(int)$edgeLookup[$edgeKey]['transline_id']];
                $prev = $u;
                $curr = $v;

                // Follow continuous pass-through path until next structural node
                while (!isset($structuralNodeSet[$curr])) {
                    $nexts = $adj[$curr] ?? [];
                    $next = ($nexts[0] == $prev) ? ($nexts[1] ?? null) : $nexts[0];
                    if ($next === null) break;

                    $nextEdgeKey = $curr < $next ? "$curr-$next" : "$next-$curr";
                    $visitedEdges[$nextEdgeKey] = true;
                    $pathNodes[] = $next;
                    $pathEdges[] = (int)$edgeLookup[$nextEdgeKey]['transline_id'];

                    $prev = $curr;
                    $curr = $next;
                }

                // Determine orientation for rooted component (upstream -> downstream)
                $sourceNodeId = $u;
                $targetNodeId = $curr;
                $compId = $nodeComponentMap[$u] ?? ($rawGraph['nodes'][0]['component_id'] ?? null);

                if (isset($depthMap[$u], $depthMap[$curr])) {
                    if ($depthMap[$u] > $depthMap[$curr]) {
                        // Reverse path so source is upstream
                        $sourceNodeId = $curr;
                        $targetNodeId = $u;
                        $pathNodes = array_reverse($pathNodes);
                        $pathEdges = array_reverse($pathEdges);
                    }
                }

                $firstEdge = $edgeLookup[$edgeKey] ?? [];
                $sectionCompId = $firstEdge['component_id'] ?? null;

                $lineSections[] = [
                    'id'                  => sprintf("SEC-%d-%03d", $penyulangId, $secSeq++),
                    'semantic_type'       => 'LINE_SECTION',
                    'component_id'        => $sectionCompId,
                    'source_node_id'      => $sourceNodeId,
                    'target_node_id'      => $targetNodeId,
                    'member_node_ids'     => $pathNodes,
                    'member_edge_ids'     => $pathEdges,
                    'span_count'          => count($pathEdges),
                ];
            }
        }

        // Calculate semantic summary metrics
        $rootedCompsCount = count(array_filter($semanticComponents, fn($c) => $c['rooted']));
        $unrootedCompsCount = count($semanticComponents) - $rootedCompsCount;
        $totalSectionSpans = array_sum(array_column($lineSections, 'span_count'));

        // Step 6: Assemble Final Authoritative Semantic Output Contract
        return [
            'status' => 'success',
            'data_source' => $rawGraph['data_source'] ?? [
                'mode'               => 'CANONICAL_FORENSIC_BASELINE',
                'is_production_live' => false,
            ],
            'scope'  => $rawGraph['scope'],
            'feeder' => $rawGraph['feeder'],
            'source' => $rawGraph['source'],
            'graph'  => [
                'nodes_count'          => count($semanticNodes),
                'edges_count'          => count($rawEdges),
                'direction'            => 'UNDIRECTED',
                'is_acyclic_forest'    => (bool)($rawGraph['topology']['is_acyclic_forest'] ?? true),
                'handshaking_verified' => (bool)($rawGraph['topology']['handshaking_verified'] ?? true),
                'sum_degrees'          => (int)($rawGraph['topology']['sum_degrees'] ?? 0),
            ],
            'semantic' => [
                'total_nodes'              => count($semanticNodes),
                'total_edges'              => count($rawEdges),
                'rooted_components'        => $rootedCompsCount,
                'unrooted_components'      => $unrootedCompsCount,
                'isolated_nodes'           => $isolatedNodesCount,
                'switch_candidates'        => $switchCandidatesCount,
                'transformer_nodes'        => $transformerNodesCount,
                'branch_nodes'             => $branchNodesCount,
                'terminal_nodes'           => $terminalNodesCount,
                'pass_through_nodes'       => $passThroughNodesCount,
                'line_sections_count'      => count($lineSections),
                'line_sections_span_count' => $totalSectionSpans,
            ],
            'components'    => $semanticComponents,
            'line_sections' => $lineSections,
            'nodes'         => $semanticNodes,
            'edges'         => $rawEdges,
            'gtt'           => $rawGraph['gtt'],
            'firewall'      => $rawGraph['firewall'],
        ];
    }
}
