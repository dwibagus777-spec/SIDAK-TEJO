<?php

namespace App\Services;

/**
 * Class SldFindingAnnotationLayoutEngineService
 *
 * SLD-05T: Technical Finding Annotation Layout Engine
 *
 * Implements deterministic runtime layout for printable PLN engineering drawing callouts:
 * 1. Resolves anchor coordinates (ASSET_LINKED to asset node, LOCATION_LINKED to GPS position).
 *    Refinement 1: LOCATION_LINKED NEVER binds to nearest asset and NEVER creates topology nodes/edges.
 * 2. Resolves sheet containment (Sheet Firewall: each finding strictly assigned to authoritative sheet_id).
 * 3. Deterministic collision avoidance using AABB staggering ladder (same input -> same layout).
 * 4. Technical leader line router with elbow paths and arrow pointers.
 * 5. Formatting for PLN engineering drawings (high-contrast text, explicit priority text HIGH/MED/LOW).
 * 6. Pure runtime read model: Delta_db = 0, Delta_nodes = 0, Delta_edges = 0.
 */
class SldFindingAnnotationLayoutEngineService
{
    /**
     * Build technical annotation model for findings on a feeder diagram.
     *
     * @param int $penyulangId
     * @param array $findings Array of raw finding items
     * @param array $nodes Array of layout nodes with render coordinates
     * @param array $sheets Array of composed sheets from SldSheetComposerService
     * @param array $canvasMeta Canvas scaling and offset parameters
     * @return array Annotated findings
     */
    public function buildAnnotationModel(
        int $penyulangId,
        array $findings,
        array $nodes,
        array $sheets,
        array $canvasMeta = []
    ): array {
        if (empty($findings)) {
            return [];
        }

        // Index nodes by asset_id for fast O(1) lookup
        $nodeMap = [];
        foreach ($nodes as $n) {
            $aId = (int)($n['asset_id'] ?? 0);
            if ($aId > 0) {
                $nodeMap[$aId] = $n;
            }
        }

        // Index sheets by sheet_index
        $sheetMap = [];
        foreach ($sheets as $s) {
            $sIdx = (int)($s['sheet_index'] ?? 0);
            if ($sIdx > 0) {
                $sheetMap[$sIdx] = $s;
            }
        }

        // Step 1: Resolve Anchor and Authoritative Sheet for each finding
        $resolvedFindings = [];
        foreach ($findings as $f) {
            $anchor = $this->resolveAnchor($f, $nodeMap, $canvasMeta);
            $sheetId = $this->resolveSheetId($f, $anchor, $sheetMap, $nodeMap);
            $textInfo = $this->formatFindingText($f, $anchor['target_node'] ?? null);

            $resolvedFindings[] = [
                'raw_finding' => $f,
                'anchor'      => $anchor,
                'sheet_id'    => $sheetId,
                'text_info'   => $textInfo,
            ];
        }

        // Step 2: Group findings by sheet_id for isolated per-sheet collision avoidance
        $bySheet = [];
        foreach ($resolvedFindings as $item) {
            $sId = $item['sheet_id'];
            $bySheet[$sId][] = $item;
        }

        // Step 3: Compute deterministic layout per sheet
        $annotatedFindings = [];
        foreach ($bySheet as $sId => $items) {
            $sheet = $sheetMap[$sId] ?? null;
            $sheetVb = $sheet['view_box'] ?? [
                'x' => 0,
                'y' => 0,
                'w' => 8000,
                'h' => 3000,
            ];

            $sheetAnnotations = $this->layoutSheetAnnotations($items, $sheetVb);
            foreach ($sheetAnnotations as $ann) {
                $annotatedFindings[] = $ann;
            }
        }

        // Step 4: Sort deterministically by id asc
        usort($annotatedFindings, function ($a, $b) {
            return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
        });

        return $annotatedFindings;
    }

    /**
     * Resolve physical anchor coordinates on canvas.
     * Refinement 1:
     * - ASSET_LINKED binds directly to asset node.
     * - LOCATION_LINKED calculates spatial position from its own GPS coordinates,
     *   NEVER binds to nearest asset, and NEVER touches topology.
     */
    public function resolveAnchor(array $finding, array $nodeMap, array $canvasMeta = []): array
    {
        $assetId = isset($finding['asset_id']) && $finding['asset_id'] !== null ? (int)$finding['asset_id'] : null;
        $modelType = ($assetId !== null && isset($nodeMap[$assetId])) ? 'ASSET_LINKED' : 'LOCATION_LINKED';

        $scaleX = (float)($canvasMeta['scale_x'] ?? 75.0);
        $scaleY = (float)($canvasMeta['scale_y'] ?? 85.0);
        $offsetX = (float)($canvasMeta['offset_x'] ?? 160.0);
        $offsetY = (float)($canvasMeta['offset_y'] ?? 180.0);

        if ($modelType === 'ASSET_LINKED') {
            $node = $nodeMap[$assetId];
            $x = isset($node['render_x']) ? (float)$node['render_x'] : ((float)($node['schematic']['grid_x'] ?? 0) * $scaleX + $offsetX);
            $y = isset($node['render_y']) ? (float)$node['render_y'] : ((float)($node['schematic']['grid_y'] ?? 0) * $scaleY + $offsetY);

            return [
                'model_type'     => 'ASSET_LINKED',
                'anchor_x'       => round($x, 2),
                'anchor_y'       => round($y, 2),
                'target_node'    => $node,
                'is_bound_asset' => true,
            ];
        }

        // Refinement 1: LOCATION_LINKED anchor from its OWN GPS coordinates
        // Project onto canvas using geographic bounding box of feeder network
        $lat = (float)($finding['latitude'] ?? 0);
        $lng = (float)($finding['longitude'] ?? 0);

        $geoBounds = $this->calculateFeederGeoBounds($nodeMap);
        $x = $this->projectGeoToCanvasX($lng, $geoBounds, $canvasMeta);
        $y = $this->projectGeoToCanvasY($lat, $geoBounds, $canvasMeta);

        return [
            'model_type'     => 'LOCATION_LINKED',
            'anchor_x'       => round($x, 2),
            'anchor_y'       => round($y, 2),
            'target_node'    => null, // STRICT: No nearest asset binding
            'is_bound_asset' => false,
            'latitude'       => $lat,
            'longitude'      => $lng,
        ];
    }

    /**
     * Resolve authoritative sheet_id.
     * Refinement 4: Sheet boundary firewall.
     * - If ASSET_LINKED: sheet_id is strictly the sheet containing target_node.
     * - If LOCATION_LINKED: sheet_id is the sheet whose viewBox contains the anchor coordinate.
     */
    public function resolveSheetId(array $finding, array $anchor, array $sheetMap, array $nodeMap): int
    {
        // 1. Asset-linked: ownership follows asset node strictly
        if ($anchor['model_type'] === 'ASSET_LINKED' && !empty($anchor['target_node'])) {
            $assetId = (int)$anchor['target_node']['asset_id'];
            foreach ($sheetMap as $sIdx => $sheet) {
                $nodeIds = $sheet['node_ids'] ?? [];
                if (in_array($assetId, $nodeIds, true)) {
                    return (int)$sIdx;
                }
                // Check in nodes array
                if (!empty($sheet['nodes'])) {
                    foreach ($sheet['nodes'] as $sn) {
                        if ((int)($sn['asset_id'] ?? 0) === $assetId) {
                            return (int)$sIdx;
                        }
                    }
                }
            }
        }

        // 2. Location-linked or asset not matched in sheets: check geometric viewBox
        $ax = (float)$anchor['anchor_x'];
        $ay = (float)$anchor['anchor_y'];

        foreach ($sheetMap as $sIdx => $sheet) {
            $vb = $sheet['view_box'] ?? null;
            if ($vb) {
                $minX = (float)$vb['x'];
                $minY = (float)$vb['y'];
                $maxX = $minX + (float)$vb['w'];
                $maxY = $minY + (float)$vb['h'];

                if ($ax >= $minX && $ax <= $maxX && $ay >= $minY && $ay <= $maxY) {
                    return (int)$sIdx;
                }
            }
        }

        // Fallback: Default to Sheet 1
        return !empty($sheetMap) ? (int)array_key_first($sheetMap) : 1;
    }

    /**
     * Format textual summary for PLN technical drawing callout.
     * Refinement 2: Clean, compact technical text, explicit priority text.
     */
    public function formatFindingText(array $finding, ?array $targetNode): array
    {
        $rawPrio = strtoupper(trim((string)($finding['prioritas'] ?? $finding['priority'] ?? 'LOW')));
        $prio = in_array($rawPrio, ['HIGH', 'KRITIS', 'DARURAT', 'EMERGENCY']) ? 'HIGH' : (in_array($rawPrio, ['MEDIUM', 'SEDANG']) ? 'MEDIUM' : 'LOW');

        $fId = (int)($finding['id'] ?? 0);
        $pId = (int)($finding['penyulang_id'] ?? 15);
        $displayCode = $finding['nomor_temuan'] ?? sprintf("TMN-%d-%04d", $pId, $fId);

        $rawTitle = $finding['jenis_temuan'] ?? $finding['detail_temuan'] ?? 'TEMUAN TEKNIS';
        $shortSubtitle = mb_strtoupper(trim((string)$rawTitle));
        if (mb_strlen($shortSubtitle) > 28) {
            $shortSubtitle = mb_substr($shortSubtitle, 0, 25) . '...';
        }

        if ($targetNode !== null) {
            $title = $targetNode['asset_code'] ?? sprintf("TIANG #%d", $targetNode['asset_id']);
            $calloutType = 'ASSET';
            $disclaimer = null;
        } else {
            $title = 'LOCATION FINDING';
            $calloutType = 'LOCATION_ROW';
            $disclaimer = 'NOT TOPOLOGY NODE';
        }

        return [
            'priority'     => $prio,
            'title'        => $title,
            'subtitle'     => $shortSubtitle,
            'display_code' => $displayCode,
            'callout_type' => $calloutType,
            'disclaimer'   => $disclaimer,
            'description'  => $finding['detail_temuan'] ?? $finding['detail'] ?? '',
        ];
    }

    /**
     * Layout callouts for a single sheet with deterministic collision avoidance.
     * Refinement 3: Pure deterministic staggering ladder.
     */
    protected function layoutSheetAnnotations(array $items, array $sheetVb): array
    {
        // Deterministic sort: anchor_x ASC, anchor_y ASC, id ASC
        usort($items, function ($a, $b) {
            $axA = $a['anchor']['anchor_x'];
            $axB = $b['anchor']['anchor_x'];
            if ($axA !== $axB) return $axA <=> $axB;

            $ayA = $a['anchor']['anchor_y'];
            $ayB = $b['anchor']['anchor_y'];
            if ($ayA !== $ayB) return $ayA <=> $ayB;

            return ((int)($a['raw_finding']['id'] ?? 0)) <=> ((int)($b['raw_finding']['id'] ?? 0));
        });

        $placedBoxes = [];
        $result = [];

        $vbMinX = (float)$sheetVb['x'];
        $vbMinY = (float)$sheetVb['y'];
        $vbMaxX = $vbMinX + (float)$sheetVb['w'];
        $vbMaxY = $vbMinY + (float)$sheetVb['h'];

        // Deterministic vertical shift ladder
        $shiftLadder = [0, -65, 65, -130, 130, -195, 195, -260, 260];
        $hShiftLadder = [0, 40, -40, 80, -80];

        foreach ($items as $item) {
            $f = $item['raw_finding'];
            $anchor = $item['anchor'];
            $textInfo = $item['text_info'];
            $sheetId = $item['sheet_id'];

            $isRow = ($textInfo['callout_type'] === 'LOCATION_ROW');
            $boxW = 168.0;
            $boxH = $isRow ? 64.0 : 54.0;

            $ax = (float)$anchor['anchor_x'];
            $ay = (float)$anchor['anchor_y'];

            // Initial candidate placement: above the anchor by default
            // If near top of sheet, place below
            $preferAbove = ($ay - $vbMinY > $vbMaxY - $ay);
            $baseOffsetY = $preferAbove ? -($boxH + 30.0) : 30.0;

            $chosenBox = null;
            foreach ($shiftLadder as $vShift) {
                foreach ($hShiftLadder as $hShift) {
                    $candidateX = $ax - ($boxW / 2.0) + $hShift;
                    $candidateY = $ay + $baseOffsetY + ($preferAbove ? -$vShift : $vShift);

                    // Sheet boundary containment (Refinement 4)
                    $candidateX = max($vbMinX + 15.0, min($candidateX, $vbMaxX - $boxW - 15.0));
                    $candidateY = max($vbMinY + 15.0, min($candidateY, $vbMaxY - $boxH - 15.0));

                    $candidateRect = [
                        'x1' => $candidateX,
                        'y1' => $candidateY,
                        'x2' => $candidateX + $boxW,
                        'y2' => $candidateY + $boxH,
                    ];

                    if (!$this->hasCollision($candidateRect, $placedBoxes)) {
                        $chosenBox = [
                            'x' => round($candidateX, 2),
                            'y' => round($candidateY, 2),
                            'w' => $boxW,
                            'h' => $boxH,
                        ];
                        $placedBoxes[] = $candidateRect;
                        break 2;
                    }
                }
            }

            // Fallback if packed
            if ($chosenBox === null) {
                $candidateX = max($vbMinX + 20.0, min($ax - ($boxW / 2.0), $vbMaxX - $boxW - 20.0));
                $candidateY = max($vbMinY + 20.0, min($ay + $baseOffsetY, $vbMaxY - $boxH - 20.0));
                $chosenBox = [
                    'x' => round($candidateX, 2),
                    'y' => round($candidateY, 2),
                    'w' => $boxW,
                    'h' => $boxH,
                ];
                $placedBoxes[] = [
                    'x1' => $candidateX,
                    'y1' => $candidateY,
                    'x2' => $candidateX + $boxW,
                    'y2' => $candidateY + $boxH,
                ];
            }

            // Leader Line Routing (Refinement 2)
            $leaderPoints = $this->routeLeaderLine($ax, $ay, $chosenBox);

            $annotation = [
                'anchor_x'      => $ax,
                'anchor_y'      => $ay,
                'box_x'         => $chosenBox['x'],
                'box_y'         => $chosenBox['y'],
                'box_width'     => $chosenBox['w'],
                'box_height'    => $chosenBox['h'],
                'leader_points' => $leaderPoints,
                'sheet_id'      => $sheetId,
                'priority'      => $textInfo['priority'],
                'title'         => $textInfo['title'],
                'subtitle'      => $textInfo['subtitle'],
                'description'   => $textInfo['description'],
                'display_code'  => $textInfo['display_code'],
                'model_type'    => $anchor['model_type'],
                'callout_type'  => $textInfo['callout_type'],
                'disclaimer'    => $textInfo['disclaimer'],
            ];

            // Merge with raw finding data
            $merged = array_merge($f, [
                'annotation'        => $annotation,
                'sheet_id'          => $sheetId,
                'is_topology_node'  => false,
            ]);

            $result[] = $merged;
        }

        return $result;
    }

    /**
     * Check if a candidate box collides with already placed boxes (with 10px buffer).
     */
    protected function hasCollision(array $rect, array $placed): bool
    {
        $buffer = 10.0;
        foreach ($placed as $p) {
            $overlapX = ($rect['x1'] - $buffer < $p['x2']) && ($rect['x2'] + $buffer > $p['x1']);
            $overlapY = ($rect['y1'] - $buffer < $p['y2']) && ($rect['y2'] + $buffer > $p['y1']);
            if ($overlapX && $overlapY) {
                return true;
            }
        }
        return false;
    }

    /**
     * Route leader line with orthogonal elbow and arrow connection to box.
     */
    protected function routeLeaderLine(float $ax, float $ay, array $box): array
    {
        $bx = $box['x'];
        $by = $box['y'];
        $bw = $box['w'];
        $bh = $box['h'];

        // Determine box connection point
        if ($by + $bh <= $ay) {
            // Box is above anchor: connect to bottom edge
            $connX = $bx + ($bw / 2.0);
            $connY = $by + $bh;
            $elbowY = $ay - (($ay - $connY) * 0.5);
            return [
                ['x' => round($ax, 2), 'y' => round($ay, 2)],
                ['x' => round($ax, 2), 'y' => round($elbowY, 2)],
                ['x' => round($connX, 2), 'y' => round($elbowY, 2)],
                ['x' => round($connX, 2), 'y' => round($connY, 2)],
            ];
        } elseif ($by >= $ay) {
            // Box is below anchor: connect to top edge
            $connX = $bx + ($bw / 2.0);
            $connY = $by;
            $elbowY = $ay + (($connY - $ay) * 0.5);
            return [
                ['x' => round($ax, 2), 'y' => round($ay, 2)],
                ['x' => round($ax, 2), 'y' => round($elbowY, 2)],
                ['x' => round($connX, 2), 'y' => round($elbowY, 2)],
                ['x' => round($connX, 2), 'y' => round($connY, 2)],
            ];
        } else {
            // Box is horizontally aligned: connect to closest lateral side
            $connX = ($ax < $bx) ? $bx : ($bx + $bw);
            $connY = $by + ($bh / 2.0);
            return [
                ['x' => round($ax, 2), 'y' => round($ay, 2)],
                ['x' => round($connX, 2), 'y' => round($connY, 2)],
            ];
        }
    }

    /**
     * Calculate bounding box of feeder nodes in geographic coordinates.
     */
    protected function calculateFeederGeoBounds(array $nodeMap): array
    {
        $minLat = 999.0;
        $maxLat = -999.0;
        $minLng = 999.0;
        $maxLng = -999.0;

        $hasGeo = false;
        foreach ($nodeMap as $n) {
            $lat = (float)($n['geo']['latitude'] ?? $n['latitude'] ?? 0);
            $lng = (float)($n['geo']['longitude'] ?? $n['longitude'] ?? 0);
            if ($lat !== 0.0 && $lng !== 0.0) {
                $hasGeo = true;
                if ($lat < $minLat) $minLat = $lat;
                if ($lat > $maxLat) $maxLat = $lat;
                if ($lng < $minLng) $minLng = $lng;
                if ($lng > $maxLng) $maxLng = $lng;
            }
        }

        if (!$hasGeo) {
            return [
                'min_lat' => -7.45,
                'max_lat' => -7.41,
                'min_lng' => 112.70,
                'max_lng' => 112.75,
            ];
        }

        return [
            'min_lat' => $minLat,
            'max_lat' => $maxLat,
            'min_lng' => $minLng,
            'max_lng' => $maxLng,
        ];
    }

    protected function projectGeoToCanvasX(float $lng, array $bounds, array $canvasMeta): float
    {
        $minLng = $bounds['min_lng'];
        $maxLng = $bounds['max_lng'];
        $spanLng = max(0.001, $maxLng - $minLng);

        $normX = ($lng - $minLng) / $spanLng;
        $normX = max(0.05, min(0.95, $normX));

        $totalW = 7200.0;
        $offsetX = (float)($canvasMeta['offset_x'] ?? 160.0);
        return $offsetX + ($normX * $totalW);
    }

    protected function projectGeoToCanvasY(float $lat, array $bounds, array $canvasMeta): float
    {
        $minLat = $bounds['min_lat'];
        $maxLat = $bounds['max_lat'];
        $spanLat = max(0.001, $maxLat - $minLat);

        // Latitude is inverted in SVG canvas (higher latitude = northern = lower Y)
        $normY = ($maxLat - $lat) / $spanLat;
        $normY = max(0.1, min(0.85, $normY));

        $totalH = 1800.0;
        $offsetY = (float)($canvasMeta['offset_y'] ?? 180.0);
        return $offsetY + ($normY * $totalH);
    }
}
