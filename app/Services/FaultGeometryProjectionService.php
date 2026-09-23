<?php

namespace App\Services;

/**
 * CR-FL-01 Phase FL-01E: Fault Geometry Projection Engine
 *
 * Deterministic, strictly READ-ONLY spatial projection engine.
 * Translates relative segment positions from FL-01D topological traversal
 * onto authoritative LineString geometry to compute physical fault points.
 *
 * Invariants & Strict Boundaries (Approved Final Execution Contract):
 * 1. 100% READ-ONLY: 0 query mutations, 0 INSERT, 0 UPDATE, 0 DELETE, 0 DDL.
 * 2. Does NOT access, alter, or create fault_records table.
 * 3. Distance Domain Separation:
 *    - FL-01D provides edge_length_m, offset_in_segment_m, segment_ratio.
 *    - FL-01E independently calculates geometry_length_m and geometry_offset_m.
 *    - FL-01E NEVER replaces FL-01D edge distance with Haversine geometry length.
 *    - segment_ratio is the invariant bridge between topology and geometry domains.
 * 4. Fault Point != Asset: Emits physical POINT(lon lat), never coerces to pole/asset.
 *    estimated_asset_id is strictly omitted from projection schema.
 * 5. No Branch Selection: Preserves and projects all candidate hypotheses in BRANCH_AMBIGUITY.
 *    Each hypothesis maintains its path_id, component_id, and edge_id.
 * 6. No Synthetic Geometry / Zero Repair: Rejects invalid, empty, malformed, zero-length,
 *    or mismatched geometry with explicit diagnostics (GEOMETRY_MISSING, GEOMETRY_MALFORMED,
 *    GEOMETRY_INVALID, GEOMETRY_ZERO_LENGTH, COORDINATES_INVALID, GEOMETRY_ENDPOINT_MISMATCH,
 *    GEOMETRY_ORIENTATION_AMBIGUOUS, GEOMETRY_ENDPOINT_CONTEXT_MISSING).
 * 7. Orientation Validation: In-memory reversal if LineString flow is REVERSED.
 *    Zero database mutations.
 * 8. Intermediate Vertex Boundary: Half-open [start, end) for non-terminal subsegments,
 *    closed [start, end] for terminal subsegment, guaranteeing exactly one subsegment match.
 * 9. Repeated Vertex: Zero-length subsegments are skipped for interval traversal.
 * 10. Deterministic SHA-256 fingerprint across all projections and diagnostics.
 */
class FaultGeometryProjectionService
{
    public const VERSION = 'FL-01E-1.0';

    protected ?FaultTopologyAdapterService $topologyAdapter;

    public function __construct(?FaultTopologyAdapterService $topologyAdapter = null)
    {
        $this->topologyAdapter = $topologyAdapter;
    }

    /**
     * Project FL-01D traversal result onto authoritative LineString geometry.
     *
     * @param array $traversalResult Output from FaultDistanceTraversalService::traverse()
     * @param array|int $topologyOrFeederId Authoritative FL-01A topology array or feeder ID
     * @param array $options Optional execution parameters
     * @return array Canonical projection report
     */
    public function project(
        array $traversalResult,
        array|int $topologyOrFeederId,
        array $options = []
    ): array {
        // 1. Validate Traversal Input Status
        $tStatus = $traversalResult['status'] ?? '';
        $tDistM  = (float)($traversalResult['traversal_distance_m'] ?? ($traversalResult['normalized_distance_m'] ?? 0.0));
        $rootAssetId = $traversalResult['root_asset_id'] ?? null;
        $componentId = $traversalResult['component_id'] ?? null;

        if ($tStatus !== 'SINGLE_PATH_MATCH' && $tStatus !== 'BRANCH_AMBIGUITY') {
            return $this->buildFailureResult(
                'TRAVERSAL_UNRESOLVED',
                "Traversal status '{$tStatus}' did not yield a valid edge match. Cannot project geometry.",
                [
                    'traversal_status'     => $tStatus,
                    'traversal_distance_m' => $tDistM,
                    'root_asset_id'        => $rootAssetId,
                    'component_id'         => $componentId,
                    'traversal_diagnostics'=> $traversalResult['diagnostics'] ?? '',
                ]
            );
        }

        $candidatePaths = $traversalResult['candidate_paths'] ?? [];
        if (empty($candidatePaths)) {
            return $this->buildFailureResult(
                'TRAVERSAL_UNRESOLVED',
                'Traversal result contains zero candidate paths for geometry projection.',
                [
                    'traversal_status'     => $tStatus,
                    'traversal_distance_m' => $tDistM,
                ]
            );
        }

        // 2. Resolve Authoritative Topology Read Model
        $topology = $this->resolveTopologyModel($topologyOrFeederId, $options);
        if (!$topology || !is_array($topology)) {
            return $this->buildFailureResult(
                'TOPOLOGY_UNAVAILABLE',
                'Authoritative topology read model could not be resolved for geometry lookup.',
                [
                    'traversal_status'     => $tStatus,
                    'traversal_distance_m' => $tDistM,
                ]
            );
        }

        $feederId = (int)($topology['feeder_id'] ?? 0);

        // 3. Index Edges and Assets for O(1) Lookup
        $edgeMap = [];
        foreach ($topology['edges'] ?? [] as $e) {
            $edgeMap[(int)$e['id']] = $e;
        }

        $assetMap = [];
        foreach ($topology['assets'] ?? [] as $a) {
            $assetMap[(int)$a['id']] = $a;
        }

        // 4. Project Each Candidate Hypothesis Independently
        $projections = [];
        $failedCount = 0;
        $primaryFailureStatus = null;
        $primaryFailureDiag = null;

        foreach ($candidatePaths as $hyp) {
            $hypProj = $this->projectHypothesis($hyp, $edgeMap, $assetMap, $options, $componentId);
            $projections[] = $hypProj;

            if ($hypProj['status'] !== 'PROJECTED') {
                $failedCount++;
                if ($primaryFailureStatus === null) {
                    $primaryFailureStatus = $hypProj['status'];
                    $primaryFailureDiag   = $hypProj['diagnostics'] ?? '';
                }
            }
        }

        // 5. Determine Subsystem Level Status
        $overallStatus = 'PROJECTED';
        $diagnostics = '';

        if ($tStatus === 'BRANCH_AMBIGUITY') {
            if ($failedCount === count($candidatePaths)) {
                $overallStatus = $primaryFailureStatus ?? 'GEOMETRY_INVALID';
                $diagnostics   = "All " . count($candidatePaths) . " branch hypotheses failed geometry projection. Primary: {$primaryFailureDiag}";
            } else {
                $overallStatus = 'BRANCH_AMBIGUITY_PROJECTED';
                $diagnostics   = "Successfully projected " . (count($candidatePaths) - $failedCount) . " of " . count($candidatePaths) . " candidate branch hypotheses across fork.";
            }
        } else {
            // SINGLE_PATH_MATCH
            if ($failedCount > 0) {
                $overallStatus = $primaryFailureStatus ?? 'GEOMETRY_INVALID';
                $diagnostics   = $primaryFailureDiag ?? 'Geometry projection failed on matched edge.';
            } else {
                $overallStatus = 'PROJECTED';
                $diagnostics   = "Unambiguously projected fault point on Edge #{$candidatePaths[0]['edge_id']}.";
            }
        }

        $res = [
            'engine_version'       => self::VERSION,
            'status'               => $overallStatus,
            'traversal_status'     => $tStatus,
            'traversal_distance_m' => $tDistM,
            'feeder_id'            => $feederId,
            'root_asset_id'        => $rootAssetId,
            'component_id'         => $componentId,
            'hypotheses_count'     => count($projections),
            'projections'          => $projections,
            'diagnostics'          => $diagnostics,
        ];

        $res['fingerprint'] = $this->calculateProjectionFingerprint($res);
        return $res;
    }

    /**
     * Project an individual candidate path hypothesis.
     *
     * @param array $hyp Traversal hypothesis from FL-01D
     * @param array $edgeMap Edge lookup map by edge ID
     * @param array $assetMap Asset lookup map by asset ID
     * @param array $options Configuration options
     * @param int|null $defaultComponentId Optional component ID fallback
     * @return array Projected hypothesis structure
     */
    public function projectHypothesis(
        array $hyp,
        array $edgeMap,
        array $assetMap,
        array $options = [],
        ?int $defaultComponentId = null
    ): array {
        $edgeId      = (int)($hyp['edge_id'] ?? 0);
        $pathId      = (string)($hyp['path_id'] ?? '');
        $componentId = $hyp['component_id'] ?? $defaultComponentId;

        // 1. Verify Edge Existence in Authoritative Topology
        if (!isset($edgeMap[$edgeId])) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'status'              => 'EDGE_NOT_FOUND',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Edge #{$edgeId} does not exist in authoritative feeder topology.",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        $edge = $edgeMap[$edgeId];
        $sourceAssetId = (int)($edge['source_asset_id'] ?? 0);
        $targetAssetId = (int)($edge['target_asset_id'] ?? 0);

        // 2. Validate FL-01D Topological Distance Inputs (Amendment #2: NO CLAMPING)
        $edgeLenM = (float)($hyp['segment_length_m'] ?? ($edge['distance_meters'] ?? 0.0));
        $offsetM  = (float)($hyp['offset_in_segment_m'] ?? 0.0);

        if ($edgeLenM <= 0.0) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'status'              => 'INVALID_TRAVERSAL_INPUT',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Edge #{$edgeId} has non-positive topological length ({$edgeLenM}m).",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        if ($offsetM < 0.0 || $offsetM > $edgeLenM) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'status'              => 'TRAVERSAL_OFFSET_OUT_OF_BOUNDS',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Offset {$offsetM}m is outside authoritative segment boundary [0, {$edgeLenM}m]. Clamping is strictly forbidden.",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        // Authoritative relative ratio: the bridge between topological distance and geometry length
        $ratio = round($offsetM / $edgeLenM, 6);
        if ($ratio < 0.0 || $ratio > 1.0) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'status'              => 'INVALID_TRAVERSAL_CONTRACT',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Segment ratio {$ratio} is outside contract range [0, 1].",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        // 3. Extract and Validate Authoritative LineString Geometry
        $rawGeom = $edge['geometry'] ?? null;
        if (empty($rawGeom)) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'status'              => 'GEOMETRY_MISSING',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Edge #{$edgeId} geometry is missing or empty in authoritative topology.",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        $parsedVertices = $this->parseLineStringWkt($rawGeom);
        if ($parsedVertices === null) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'status'              => 'GEOMETRY_MALFORMED',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Edge #{$edgeId} LineString geometry is malformed and could not be parsed.",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        if (count($parsedVertices) < 2) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'status'              => 'GEOMETRY_INVALID',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Edge #{$edgeId} LineString has fewer than 2 vertices (" . count($parsedVertices) . " found).",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        // 4. Validate Coordinates Bounds & Sentinel (0, 0) Check (Amendment #9)
        foreach ($parsedVertices as $vIdx => $v) {
            if (!$this->isValidCoordinate($v['lat'], $v['lon'])) {
                return [
                    'path_id'             => $pathId,
                    'component_id'        => $componentId,
                    'edge_id'             => $edgeId,
                    'source_asset_id'     => $sourceAssetId,
                    'target_asset_id'     => $targetAssetId,
                    'status'              => 'COORDINATES_INVALID',
                    'orientation'         => null,
                    'endpoint_validation' => 'FAILED',
                    'diagnostics'         => "Vertex #{$vIdx} of Edge #{$edgeId} has invalid or (0,0) sentinel coordinates ({$v['lat']}, {$v['lon']}).",
                    'latitude'            => null,
                    'longitude'           => null,
                    'wkt_point'           => null,
                ];
            }
        }

        // 5. Validate Endpoint Context (Upstream / Downstream Assets)
        $upAssetId   = (int)($hyp['upstream_asset_id'] ?? $sourceAssetId);
        $downAssetId = (int)($hyp['downstream_asset_id'] ?? $targetAssetId);

        $upAsset   = $assetMap[$upAssetId] ?? null;
        $downAsset = $assetMap[$downAssetId] ?? null;

        if (!$upAsset || !$downAsset) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'upstream_asset_id'   => $upAssetId,
                'downstream_asset_id' => $downAssetId,
                'status'              => 'GEOMETRY_ENDPOINT_CONTEXT_MISSING',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Upstream asset (#{$upAssetId}) or downstream asset (#{$downAssetId}) context is missing from topology.",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        $upLat   = (float)($upAsset['latitude'] ?? 0.0);
        $upLon   = (float)($upAsset['longitude'] ?? 0.0);
        $downLat = (float)($downAsset['latitude'] ?? 0.0);
        $downLon = (float)($downAsset['longitude'] ?? 0.0);

        if (!$this->isValidCoordinate($upLat, $upLon) || !$this->isValidCoordinate($downLat, $downLon)) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'upstream_asset_id'   => $upAssetId,
                'downstream_asset_id' => $downAssetId,
                'status'              => 'GEOMETRY_ENDPOINT_CONTEXT_MISSING',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Upstream (#{$upAssetId}) or downstream (#{$downAssetId}) asset has uninitialized/sentinel coordinate context.",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        // 6. Calculate Independent Geometric LineString Length (Amendment #3: sum of all subsegments)
        $geomLengthM = 0.0;
        $vertCount = count($parsedVertices);
        for ($i = 0; $i < $vertCount - 1; $i++) {
            $geomLengthM += $this->haversineMeters(
                $parsedVertices[$i]['lat'], $parsedVertices[$i]['lon'],
                $parsedVertices[$i + 1]['lat'], $parsedVertices[$i + 1]['lon']
            );
        }

        if ($geomLengthM <= 0.0) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'upstream_asset_id'   => $upAssetId,
                'downstream_asset_id' => $downAssetId,
                'status'              => 'GEOMETRY_ZERO_LENGTH',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Authoritative LineString has zero geometric length ({$geomLengthM}m) across all subsegments.",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        // 7. Orientation Validation & Tie-Break Firewall (Amendments #5 & #6)
        $toleranceM = (float)($options['tolerance_meters'] ?? 20.0);
        $vFirst = $parsedVertices[0];
        $vLast  = $parsedVertices[count($parsedVertices) - 1];

        $dDirect = $this->haversineMeters($vFirst['lat'], $vFirst['lon'], $upLat, $upLon)
                 + $this->haversineMeters($vLast['lat'], $vLast['lon'], $downLat, $downLon);

        $dReversed = $this->haversineMeters($vFirst['lat'], $vFirst['lon'], $downLat, $downLon)
                   + $this->haversineMeters($vLast['lat'], $vLast['lon'], $upLat, $upLon);

        $allowedThreshold = $toleranceM * 2.0;
        $directValid   = ($dDirect <= $allowedThreshold);
        $reversedValid = ($dReversed <= $allowedThreshold);

        if (!$directValid && !$reversedValid) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'upstream_asset_id'   => $upAssetId,
                'downstream_asset_id' => $downAssetId,
                'status'              => 'GEOMETRY_ENDPOINT_MISMATCH',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "LineString endpoints deviate from upstream/downstream assets (Direct: " . round($dDirect, 2) . "m, Reversed: " . round($dReversed, 2) . "m, Allowed: {$allowedThreshold}m). Zero repairs performed.",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        // Amendment #5: Orientation Ambiguity check
        if ($directValid && $reversedValid && abs($dDirect - $dReversed) < 0.001) {
            return [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'edge_id'             => $edgeId,
                'source_asset_id'     => $sourceAssetId,
                'target_asset_id'     => $targetAssetId,
                'upstream_asset_id'   => $upAssetId,
                'downstream_asset_id' => $downAssetId,
                'status'              => 'GEOMETRY_ORIENTATION_AMBIGUOUS',
                'orientation'         => null,
                'endpoint_validation' => 'FAILED',
                'diagnostics'         => "Both DIRECT (" . round($dDirect, 2) . "m) and REVERSED (" . round($dReversed, 2) . "m) satisfy endpoint tolerance equally. Orientation cannot be proven deterministically.",
                'latitude'            => null,
                'longitude'           => null,
                'wkt_point'           => null,
            ];
        }

        $orientation = ($dDirect <= $dReversed) ? 'DIRECT' : 'REVERSED';
        $orientedVertices = ($orientation === 'DIRECT') ? $parsedVertices : array_reverse($parsedVertices);

        // 8. Distance Domain Translation: ratio * geometry_length_m (Amendment #2)
        $geomOffsetM = round($ratio * $geomLengthM, 2);

        // 9. Polyline Walk & Interpolation (Amendment #1: traverseMultiVertexLineString signature)
        $walk = $this->traverseMultiVertexLineString($orientedVertices, $geomOffsetM);

        // 10. Canonical Output Schema (Amendment #7 & #8)
        $lon = $walk['longitude'];
        $lat = $walk['latitude'];

        return [
            'path_id'             => $pathId,
            'component_id'        => $componentId,
            'edge_id'             => $edgeId,
            'source_asset_id'     => $sourceAssetId,
            'target_asset_id'     => $targetAssetId,
            'upstream_asset_id'   => $upAssetId,
            'downstream_asset_id' => $downAssetId,
            'upstream_code'       => $hyp['upstream_code'] ?? ($upAsset['kode_asset'] ?? ''),
            'downstream_code'     => $hyp['downstream_code'] ?? ($downAsset['kode_asset'] ?? ''),
            'transline_code'      => $hyp['transline_code'] ?? ($edge['transline_code'] ?? "TL-{$edgeId}"),
            'segment_ratio'       => $ratio,
            'geometry_length_m'   => round($geomLengthM, 2),
            'geometry_offset_m'   => $geomOffsetM,
            'orientation'         => $orientation,
            'endpoint_validation' => 'PASS',
            'total_vertices'      => $vertCount,
            'subsegment_index'    => $walk['subsegment_index'],
            'subsegment_count'    => $vertCount - 1,
            'subsegment_start'    => $walk['subsegment_start'],
            'subsegment_end'      => $walk['subsegment_end'],
            'subsegment_length_m' => $walk['subsegment_length_m'],
            'subsegment_offset_m' => $walk['subsegment_offset_m'],
            'subsegment_ratio'    => $walk['subsegment_ratio'],
            'latitude'            => $lat,
            'longitude'           => $lon,
            'wkt_point'           => sprintf('POINT(%.7f %.7f)', $lon, $lat),
            'status'              => 'PROJECTED',
            'diagnostics'         => "Successfully projected at {$geomOffsetM}m / {$geomLengthM}m (ratio: {$ratio}) on Subsegment #{$walk['subsegment_index']}.",
        ];
    }

    /**
     * Walk multi-vertex oriented vertices to identify subsegment and linearly interpolate coordinates.
     *
     * Amendment #1: Semantics strictly decoupled from electrical distance domain.
     * Amendment #4: Zero-length subsegments (repeated vertices) are skipped for interval traversal.
     *
     * @param array $orientedVertices Ordered array of ['lon' => float, 'lat' => float]
     * @param float $geometryOffsetM Geometrical offset in meters along the LineString
     * @return array Subsegment and interpolated coordinate details
     */
    public function traverseMultiVertexLineString(
        array $orientedVertices,
        float $geometryOffsetM
    ): array {
        $vertCount = count($orientedVertices);
        $subsegmentCount = $vertCount - 1;

        // Calculate total geometry length
        $totalGeomLengthM = 0.0;
        $subLengths = [];
        for ($i = 0; $i < $subsegmentCount; $i++) {
            $len = $this->haversineMeters(
                $orientedVertices[$i]['lat'], $orientedVertices[$i]['lon'],
                $orientedVertices[$i + 1]['lat'], $orientedVertices[$i + 1]['lon']
            );
            $subLengths[$i] = $len;
            $totalGeomLengthM += $len;
        }

        // Boundary Condition 1: At or before start (geometryOffsetM <= 0)
        if ($geometryOffsetM <= 0.0) {
            $p0 = $orientedVertices[0];
            $p1 = $orientedVertices[1];
            $len0 = $subLengths[0] ?? 0.0;
            return [
                'subsegment_index'    => 0,
                'subsegment_start'    => $p0,
                'subsegment_end'      => $p1,
                'subsegment_length_m' => round($len0, 2),
                'subsegment_offset_m' => 0.0,
                'subsegment_ratio'    => 0.0,
                'latitude'            => round($p0['lat'], 7),
                'longitude'           => round($p0['lon'], 7),
            ];
        }

        // Boundary Condition 2: At or beyond end (geometryOffsetM >= totalGeomLengthM)
        if ($geometryOffsetM >= $totalGeomLengthM) {
            $lastIdx = $subsegmentCount - 1;
            $pn_1 = $orientedVertices[$lastIdx];
            $pn   = $orientedVertices[$subsegmentCount];
            $lenLast = $subLengths[$lastIdx] ?? 0.0;
            return [
                'subsegment_index'    => $lastIdx,
                'subsegment_start'    => $pn_1,
                'subsegment_end'      => $pn,
                'subsegment_length_m' => round($lenLast, 2),
                'subsegment_offset_m' => round($lenLast, 2),
                'subsegment_ratio'    => 1.0,
                'latitude'            => round($pn['lat'], 7),
                'longitude'           => round($pn['lon'], 7),
            ];
        }

        // General Walk: [start, end) for non-terminal, [start, end] for terminal
        $accumDist = 0.0;
        for ($i = 0; $i < $subsegmentCount; $i++) {
            $pStart = $orientedVertices[$i];
            $pEnd   = $orientedVertices[$i + 1];
            $subLen = $subLengths[$i];

            // Amendment #4: Skip zero-length subsegments (e.g. repeated vertices V1 == V1)
            // for interval matching, unless this is the final subsegment
            if ($subLen <= 0.000001 && $i < $subsegmentCount - 1) {
                continue;
            }

            $startM = $accumDist;
            $endM   = $accumDist + $subLen;
            $isTerminal = ($i === $subsegmentCount - 1);

            $inInterval = $isTerminal
                ? ($geometryOffsetM >= $startM && $geometryOffsetM <= $endM)
                : ($geometryOffsetM >= $startM && $geometryOffsetM < $endM);

            if ($inInterval) {
                $subOffset = round($geometryOffsetM - $startM, 4);
                $subRatio  = ($subLen > 0.0) ? ($subOffset / $subLen) : 0.0;
                $t = max(0.0, min(1.0, $subRatio));

                // Linear Interpolation in Geographic Coordinates
                $lat = $pStart['lat'] + $t * ($pEnd['lat'] - $pStart['lat']);
                $lon = $pStart['lon'] + $t * ($pEnd['lon'] - $pStart['lon']);

                return [
                    'subsegment_index'    => $i,
                    'subsegment_start'    => $pStart,
                    'subsegment_end'      => $pEnd,
                    'subsegment_length_m' => round($subLen, 2),
                    'subsegment_offset_m' => round($subOffset, 2),
                    'subsegment_ratio'    => round($t, 4),
                    'latitude'            => round($lat, 7),
                    'longitude'           => round($lon, 7),
                ];
            }

            $accumDist = $endM;
        }

        // Deterministic Terminal Fallback
        $lastIdx = $subsegmentCount - 1;
        $pn_1 = $orientedVertices[$lastIdx];
        $pn   = $orientedVertices[$subsegmentCount];
        return [
            'subsegment_index'    => $lastIdx,
            'subsegment_start'    => $pn_1,
            'subsegment_end'      => $pn,
            'subsegment_length_m' => round($subLengths[$lastIdx] ?? 0.0, 2),
            'subsegment_offset_m' => round($subLengths[$lastIdx] ?? 0.0, 2),
            'subsegment_ratio'    => 1.0,
            'latitude'            => round($pn['lat'], 7),
            'longitude'           => round($pn['lon'], 7),
        ];
    }

    /**
     * Parse WKT LineString into coordinate array.
     */
    public function parseLineStringWkt(?string $wkt): ?array
    {
        if (empty($wkt)) return null;

        if (!preg_match('/^\s*LINESTRING\s*\((.+)\)\s*$/i', trim($wkt), $m)) {
            return null;
        }

        $pairs = explode(',', trim($m[1]));
        $vertices = [];

        foreach ($pairs as $p) {
            $parts = preg_split('/\s+/', trim($p));
            if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $vertices[] = [
                    'lon' => (float)$parts[0],
                    'lat' => (float)$parts[1],
                ];
            }
        }

        return count($vertices) > 0 ? $vertices : null;
    }

    /**
     * Compute Haversine distance in meters between two lat/lon points.
     */
    public function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (abs($lat1 - $lat2) < 0.0000001 && abs($lon1 - $lon2) < 0.0000001) {
            return 0.0;
        }

        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;

        return round(2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a)), 4);
    }

    /**
     * Check if latitude and longitude are valid finite non-sentinel coordinates.
     */
    protected function isValidCoordinate(float $lat, float $lon): bool
    {
        // Business data-quality rule: (0, 0) is reserved as an uninitialized sentinel in GIS DB
        if (abs($lat) < 0.000001 && abs($lon) < 0.000001) {
            return false;
        }

        return is_finite($lat)
            && is_finite($lon)
            && $lat >= -90.0
            && $lat <= 90.0
            && $lon >= -180.0
            && $lon <= 180.0;
    }

    /**
     * Resolve topology model array from array or feeder ID.
     */
    protected function resolveTopologyModel(array|int $topologyOrFeederId, array $options): ?array
    {
        if (is_array($topologyOrFeederId)) {
            return $topologyOrFeederId;
        }

        if (is_int($topologyOrFeederId) && $topologyOrFeederId > 0) {
            if (!$this->topologyAdapter) {
                $this->topologyAdapter = new FaultTopologyAdapterService();
            }
            return $this->topologyAdapter->buildFeederTopologyModel($topologyOrFeederId, $options);
        }

        return null;
    }

    /**
     * Construct canonical failure report.
     */
    protected function buildFailureResult(string $status, string $diagnostics, array $extra = []): array
    {
        $res = array_merge([
            'engine_version'       => self::VERSION,
            'status'               => $status,
            'traversal_status'     => $extra['traversal_status'] ?? '',
            'traversal_distance_m' => (float)($extra['traversal_distance_m'] ?? 0.0),
            'feeder_id'            => (int)($extra['feeder_id'] ?? 0),
            'root_asset_id'        => $extra['root_asset_id'] ?? null,
            'component_id'         => $extra['component_id'] ?? null,
            'hypotheses_count'     => 0,
            'projections'          => [],
            'diagnostics'          => $diagnostics,
        ], $extra);

        $res['fingerprint'] = $this->calculateProjectionFingerprint($res);
        return $res;
    }

    /**
     * Calculate reproducible SHA-256 fingerprint of projection result.
     */
    public function calculateProjectionFingerprint(array $res): string
    {
        $canonical = [
            'engine_version'       => $res['engine_version'] ?? self::VERSION,
            'status'               => $res['status'] ?? '',
            'traversal_status'     => $res['traversal_status'] ?? '',
            'traversal_distance_m' => (float)($res['traversal_distance_m'] ?? 0.0),
            'feeder_id'            => (int)($res['feeder_id'] ?? 0),
            'hypotheses_count'     => (int)($res['hypotheses_count'] ?? 0),
            'projections_digest'   => array_map(function ($p) {
                return [
                    'edge_id'             => (int)($p['edge_id'] ?? 0),
                    'path_id'             => (string)($p['path_id'] ?? ''),
                    'status'              => (string)($p['status'] ?? ''),
                    'orientation'         => (string)($p['orientation'] ?? ''),
                    'subsegment_index'    => (int)($p['subsegment_index'] ?? 0),
                    'subsegment_ratio'    => (float)($p['subsegment_ratio'] ?? 0.0),
                    'wkt_point'           => (string)($p['wkt_point'] ?? ''),
                ];
            }, $res['projections'] ?? []),
        ];

        usort($canonical['projections_digest'], function ($a, $b) {
            $cmp = strcmp($a['path_id'], $b['path_id']);
            return $cmp !== 0 ? $cmp : ($a['edge_id'] <=> $b['edge_id']);
        });

        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $json);
    }
}
