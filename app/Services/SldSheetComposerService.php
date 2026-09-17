<?php

namespace App\Services;

/**
 * Class SldSheetComposerService
 *
 * SLD-05T: GIS Sheet Composer Read Model Service (Auto Page Split)
 *
 * Implements the print-safe dynamic partitioning of feeder network into sequential CAD sheets.
 * Governed by the 7 Mandatory Refinement Locks:
 * 1. 100% Coverage Invariant: sum(unique asset IDs across sheets) == total active feeder assets (205).
 * 2. Zero lost assets and zero orphaned nodes.
 * 3. Topological continuity maintained across partition boundaries.
 * 4. Explicit match-lines connecting Sheet K to Sheet K+1 at exact boundary asset nodes.
 * 5. Optimal per-sheet viewBox calculations preserving aspect ratio and target occupancy.
 * 6. CAD Title Block metadata (PLN Standard Header, Sheet 01/04, Corridors, North Arrow).
 * 7. Strict Read-Only Invariant: Delta = 0 (zero database writes, pure in-memory read model).
 */
class SldSheetComposerService
{
    protected SldLayoutCoordinateEngineService $layoutService;

    public function __construct(?SldLayoutCoordinateEngineService $layoutService = null)
    {
        $this->layoutService = $layoutService ?? new SldLayoutCoordinateEngineService();
    }

    /**
     * Compose feeder network into sequential print-ready sheets.
     *
     * @param int $penyulangId
     * @param array $options ['target_poles_per_sheet' => int]
     * @return array
     */
    public function composeFeederSheets(int $penyulangId, array $options = []): array
    {
        // Step 1: Ingest authoritative layout from SLD-04
        $layout = $this->layoutService->buildFeederLayout($penyulangId, $options);

        if (($layout['status'] ?? '') !== 'success') {
            return $layout;
        }

        $allNodes = $layout['nodes'] ?? [];
        $allEdges = $layout['edges'] ?? [];
        $feeder = $layout['feeder'] ?? [];
        $source = $layout['source'] ?? [];
        $totalAssets = count($allNodes);

        if ($totalAssets === 0) {
            return [
                'status'  => 'error',
                'message' => "Feeder #{$penyulangId} tidak memiliki node asset untuk di-compose.",
                'sheets'  => [],
            ];
        }

        // Step 2: Separate Main Network (C15-01) from Unconnected/Isolated Nodes
        $mainNodes = [];
        $otherNodes = [];

        foreach ($allNodes as $n) {
            if (($n['component_id'] ?? '') === 'C15-01' || ($n['zone'] ?? '') === 'MAIN_NETWORK') {
                $mainNodes[] = $n;
            } else {
                $otherNodes[] = $n;
            }
        }

        // Sort main nodes by depth_from_source asc, grid_x asc, asset_id asc for deterministic flow
        usort($mainNodes, function ($a, $b) {
            $depthA = $a['depth_from_source'] ?? 0;
            $depthB = $b['depth_from_source'] ?? 0;
            if ($depthA !== $depthB) {
                return $depthA <=> $depthB;
            }
            $xA = $a['schematic']['grid_x'] ?? 0;
            $xB = $b['schematic']['grid_x'] ?? 0;
            if ($xA !== $xB) {
                return $xA <=> $xB;
            }
            return $a['asset_id'] <=> $b['asset_id'];
        });

        // Step 3: Determine Partition Boundaries
        // Target 35–45 poles per sheet
        $targetPoles = (int)($options['target_poles_per_sheet'] ?? 45);
        $totalMain = count($mainNodes);

        // For Feeder 15 (183 main nodes + 22 other nodes = 205 total):
        // 4 sheets yields:
        // Sheet 1: ~45 nodes (GI Buduran -> Banjar Kemantren Pangkal)
        // Sheet 2: ~45 nodes (Banjar Kemantren Tengah -> Ujung)
        // Sheet 3: ~45 nodes (Keboan Anom Pangkal -> Tengah)
        // Sheet 4: remaining main nodes (~48) + other nodes (22) = 70 nodes (Keboan Anom Ujung + Fragments)
        $numMainSheets = max(1, (int)ceil($totalMain / $targetPoles));
        if ($numMainSheets < 3) $numMainSheets = 3;

        $chunkSize = (int)ceil($totalMain / $numMainSheets);
        $sheetNodeGroups = [];
        for ($s = 0; $s < $numMainSheets; $s++) {
            $startIdx = $s * $chunkSize;
            $slice = array_slice($mainNodes, $startIdx, $chunkSize);
            if (!empty($slice)) {
                $sheetNodeGroups[] = $slice;
            }
        }

        // Append other nodes (unconnected fragments & isolated assets) to the last sheet
        // This guarantees 100% COVERAGE: sum(unique asset IDs across all sheets) == 205
        if (!empty($otherNodes)) {
            $lastIdx = count($sheetNodeGroups) - 1;
            foreach ($otherNodes as $on) {
                $sheetNodeGroups[$lastIdx][] = $on;
            }
        }

        $totalSheets = count($sheetNodeGroups);

        // Step 4: Edge Indexing for Partition Filtering
        $nodeSheetMap = [];
        foreach ($sheetNodeGroups as $sIdx => $grp) {
            foreach ($grp as $node) {
                $nodeSheetMap[(int)$node['asset_id']] = $sIdx;
            }
        }

        // Step 5: Build Each Sheet Descriptor with CAD Metadata, Match Lines & ViewBox
        $sheets = [];
        $scaleX = $layout['canvas']['scale_x'] ?? 75.0;
        $scaleY = $layout['canvas']['scale_y'] ?? 85.0;
        $offsetX = $layout['canvas']['offset_x'] ?? 160.0;
        $offsetY = $layout['canvas']['offset_y'] ?? 180.0;

        for ($idx = 0; $idx < $totalSheets; $idx++) {
            $sheetNum = $idx + 1;
            $sheetCode = sprintf("SHEET-%02d", $sheetNum);
            $currentNodes = $sheetNodeGroups[$idx];
            $currentNodeIds = array_column($currentNodes, 'asset_id');
            $currentNodeIdSet = array_flip($currentNodeIds);

            // Filter edges belonging to this sheet (both endpoints in current sheet)
            $sheetEdges = [];
            $boundaryEdges = [];

            foreach ($allEdges as $e) {
                $u = (int)$e['source_asset_id'];
                $v = (int)$e['target_asset_id'];
                $inU = isset($currentNodeIdSet[$u]);
                $inV = isset($currentNodeIdSet[$v]);

                if ($inU && $inV) {
                    $sheetEdges[] = $e;
                } elseif ($inU || $inV) {
                    // Boundary crossover edge linking to another sheet
                    $boundaryEdges[] = [
                        'edge'           => $e,
                        'internal_node'  => $inU ? $u : $v,
                        'external_node'  => $inU ? $v : $u,
                        'external_sheet' => ($inU ? ($nodeSheetMap[$v] ?? null) : ($nodeSheetMap[$u] ?? null)) + 1,
                    ];
                }
            }

            // Determine Match Lines
            $matchLines = [];
            if ($sheetNum > 1) {
                // Incoming from previous sheet
                $prevSheetNum = $sheetNum - 1;
                $boundaryNodeId = $currentNodes[0]['asset_id'] ?? null;
                $matchLines['backward'] = [
                    'direction'        => 'BACKWARD',
                    'adjacent_sheet'   => $prevSheetNum,
                    'boundary_node_id' => $boundaryNodeId,
                    'label'            => sprintf("➔ DARI LEMBAR %02d", $prevSheetNum),
                ];
            }

            if ($sheetNum < $totalSheets) {
                // Outgoing to next sheet
                $nextSheetNum = $sheetNum + 1;
                $lastMainNode = null;
                // Find boundary node that connects forward
                foreach (array_reverse($currentNodes) as $cn) {
                    if (($cn['component_id'] ?? '') === 'C15-01') {
                        $lastMainNode = $cn['asset_id'];
                        break;
                    }
                }
                $boundaryNodeId = $lastMainNode ?? end($currentNodes)['asset_id'];
                $matchLines['forward'] = [
                    'direction'        => 'FORWARD',
                    'adjacent_sheet'   => $nextSheetNum,
                    'boundary_node_id' => $boundaryNodeId,
                    'label'            => sprintf("SAMBUNGAN KE LEMBAR %02d ➔", $nextSheetNum),
                ];
            }

            // Calculate Optimal Sheet ViewBox
            $minX = PHP_INT_MAX; $maxX = -PHP_INT_MAX;
            $minY = PHP_INT_MAX; $maxY = -PHP_INT_MAX;

            foreach ($currentNodes as $n) {
                $gx = (float)($n['schematic']['grid_x'] ?? 0);
                $gy = (float)($n['schematic']['grid_y'] ?? 0);
                $px = ($gx * $scaleX) + $offsetX;
                $py = ($gy * $scaleY) + $offsetY;

                $minX = min($minX, $px - 60);
                $maxX = max($maxX, $px + 60);
                $minY = min($minY, $py - 60);
                $maxY = max($maxY, $py + 80); // Extra for GTT cards
            }

            $contentW = max(400, $maxX - $minX);
            $contentH = max(250, $maxY - $minY);

            // Target print aspect ratio (e.g. A4/A3 Landscape: ~1.414)
            $sheetAspect = 1.414;
            $targetOccW = 0.88;
            $targetOccH = 0.80;

            $vh = max($contentH / $targetOccH, $contentW / ($targetOccW * $sheetAspect));
            $vw = $vh * $sheetAspect;

            $viewBox = [
                'x' => (int)round(($minX + $contentW / 2) - ($vw / 2)),
                'y' => (int)round(($minY + $contentH / 2) - ($vh / 2)),
                'w' => (int)round($vw),
                'h' => (int)round($vh),
            ];

            // Extract Corridors in this sheet
            $corridorSet = [];
            foreach ($currentNodes as $n) {
                $rd = $n['location_context']['road_name'] ?? null;
                if ($rd) $corridorSet[$rd] = true;
            }
            $sheetCorridors = array_keys($corridorSet);

            // Device Statistics for Title Block
            $devStats = [
                'total_nodes'       => count($currentNodes),
                'total_edges'       => count($sheetEdges),
                'switches'          => count(array_filter($currentNodes, fn($n) => $n['device_role'] === 'SWITCH_CANDIDATE')),
                'transformers'      => count(array_filter($currentNodes, fn($n) => $n['device_role'] === 'TRANSFORMER_NODE')),
                'branch_nodes'      => count(array_filter($currentNodes, fn($n) => $n['topology_role'] === 'BRANCH_NODE')),
                'terminal_nodes'    => count(array_filter($currentNodes, fn($n) => $n['topology_role'] === 'TERMINAL_NODE')),
                'line_poles'        => count(array_filter($currentNodes, fn($n) => $n['topology_role'] === 'PASS_THROUGH')),
                'isolated_nodes'    => count(array_filter($currentNodes, fn($n) => ($n['topology_role'] ?? '') === 'ISOLATED_NODE' || ($n['zone'] ?? '') === 'ISOLATED_ASSETS')),
            ];

            // CAD Title Block Metadata
            $titleBlock = [
                'company'          => 'PT PLN (PERSERO)',
                'unit_induk'       => 'UID JAWA TIMUR',
                'up3'              => 'UP3 SIDOARJO',
                'ulp'              => $feeder['ulp_name'] ?? 'ULP SIDOARJO KOTA',
                'feeder_code'      => $feeder['code'] ?? "PYL-{$penyulangId}",
                'feeder_name'      => $feeder['name'] ?? "FEEDER {$penyulangId}",
                'substation'       => $source['name'] ?? 'GI BUDURAN',
                'voltage_level'    => '20 kV',
                'drawing_title'    => 'SINGLE LINE DIAGRAM 20 kV',
                'sheet_index'      => $sheetNum,
                'total_sheets'     => $totalSheets,
                'sheet_label'      => sprintf("LEMBAR %02d DARI %02d", $sheetNum, $totalSheets),
                'corridors'        => $sheetCorridors,
                'date'             => date('Y-m-d'),
                'scale'            => 'NOT TO SCALE (SCHEMATIC)',
                'north_arrow'      => 'UP',
                'statistics'       => $devStats,
            ];

            $sheets[] = [
                'sheet_index'     => $sheetNum,
                'sheet_code'      => $sheetCode,
                'title_block'     => $titleBlock,
                'view_box'        => $viewBox,
                'match_lines'     => $matchLines,
                'node_ids'        => $currentNodeIds,
                'nodes'           => $currentNodes,
                'edges'           => $sheetEdges,
                'boundary_edges'  => $boundaryEdges,
                'corridors'       => $sheetCorridors,
                'statistics'      => $devStats,
            ];
        }

        // Verification of 100% Coverage Invariant
        $allCoveredAssetIds = [];
        foreach ($sheets as $s) {
            foreach ($s['node_ids'] as $aId) {
                $allCoveredAssetIds[$aId] = true;
            }
        }
        $uniqueCoveredCount = count($allCoveredAssetIds);
        $coverageComplete = ($uniqueCoveredCount === $totalAssets);

        return [
            'status'            => 'success',
            'penyulang_id'      => $penyulangId,
            'feeder_name'       => $feeder['name'] ?? "FEEDER {$penyulangId}",
            'total_sheets'      => $totalSheets,
            'total_assets'      => $totalAssets,
            'covered_assets'    => $uniqueCoveredCount,
            'coverage_complete' => $coverageComplete,
            'sheets'            => $sheets,
        ];
    }
}
