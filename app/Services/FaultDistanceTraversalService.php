<?php

namespace App\Services;

/**
 * CR-FL-01 Phase FL-01D: FTU Distance Traversal Engine
 *
 * Deterministic, strictly READ-ONLY topology graph distance traversal engine.
 * Maps FTU-reported distance (from FL-01C) onto FL-01A authoritative cumulative distance paths.
 *
 * Invariants & Strict Boundaries:
 * 1. 100% READ-ONLY: 0 query mutations, 0 INSERT, 0 UPDATE, 0 DELETE, 0 DDL.
 * 2. Does NOT access or create fault_records table.
 * 3. Does NOT calculate geographic coordinates (latitude, longitude, or fault coordinates).
 * 4. Does NOT choose an asset or pole as the fault point (fault is on a segment interval).
 * 5. Does NOT use nearest-neighbor, straight-line GPS, or synthetic translines.
 * 6. Root Resolution: If FL-01A root is ROOT_UNRESOLVED, returns ROOT_UNRESOLVED (0 guessing).
 * 7. Boundary Convention: Half-open [start, end) for non-terminal edges, closed [start, end] for terminal edge.
 * 8. Branch Ambiguity: Preserves all hypotheses when D_FTU falls after a fork into >= 2 distinct branch segments.
 * 9. Distance Out of Range: If D_FTU > max(path), returns DISTANCE_OUT_OF_RANGE (no clamping, no snapping).
 * 10. Deterministic SHA-256 fingerprint reproducible across repeated executions.
 */
class FaultDistanceTraversalService
{
    public const VERSION = 'FL-01D-1.0';

    protected ?FaultTopologyAdapterService $topologyAdapter;

    public function __construct(?FaultTopologyAdapterService $topologyAdapter = null)
    {
        $this->topologyAdapter = $topologyAdapter;
    }

    /**
     * Traverse FTU distance against authoritative feeder topology.
     *
     * @param array|float $measurementInput FL-01C validation result, parsed payload, or numeric distance
     * @param array|int $topologyInput FL-01A topology model array or feeder ID integer
     * @param array $options Optional configuration
     * @return array Canonical traversal report
     */
    public function traverse(
        array|float $measurementInput,
        array|int $topologyInput,
        array $options = []
    ): array {
        // 1. Resolve & Validate Measurement Distance Input
        $distRes = $this->resolveMeasurementDistance($measurementInput, $options);
        if ($distRes['status'] !== 'OK') {
            return $this->buildFailureResult($distRes['status'], $distRes['diagnostics'], $distRes);
        }

        $distanceM = $distRes['normalized_distance_m'];
        $rawDistance = $distRes['source_distance'];
        $rawUnit = $distRes['source_distance_unit'];

        // 2. Physical Non-Positive Distance Firewall
        if ($distanceM <= 0.0) {
            return $this->buildFailureResult(
                'DISTANCE_NON_POSITIVE',
                "Reported distance {$distanceM}m is non-positive; cannot traverse topology.",
                [
                    'source_distance'       => $rawDistance,
                    'source_distance_unit'  => $rawUnit,
                    'normalized_distance_m' => $distanceM,
                ]
            );
        }

        // 3. Resolve Topology Read Model (FL-01A)
        $topology = $this->resolveTopologyModel($topologyInput, $options);
        if (!$topology || !is_array($topology)) {
            return $this->buildFailureResult(
                'TOPOLOGY_UNAVAILABLE',
                'Authoritative topology read model could not be resolved.',
                ['normalized_distance_m' => $distanceM]
            );
        }

        // 4. Root Resolution Firewall
        $topStatus = $topology['status'] ?? '';
        if ($topStatus === 'TOPOLOGY_ROOT_UNRESOLVED' || ($topology['summary']['total_edges'] ?? 0) === 0) {
            $rootRes = $topology['components'][0]['root_resolution'] ?? [];
            if (($rootRes['status'] ?? '') === 'ROOT_UNRESOLVED' || $topStatus === 'TOPOLOGY_ROOT_UNRESOLVED') {
                return $this->buildFailureResult(
                    'ROOT_UNRESOLVED',
                    'Topology root is unresolved. Cannot determine cumulative distance origin without authoritative anchor.',
                    [
                        'source_distance'       => $rawDistance,
                        'source_distance_unit'  => $rawUnit,
                        'normalized_distance_m' => $distanceM,
                        'feeder_id'             => $topology['feeder_id'] ?? null,
                    ]
                );
            }
        }

        // 5. Component & Path Isolation
        $rootComp = $this->findRootComponent($topology);
        if (!$rootComp) {
            return $this->buildFailureResult(
                'NO_PATH',
                'No authoritative component with a resolved root exists in the feeder.',
                ['normalized_distance_m' => $distanceM]
            );
        }

        $rootAssetId = $rootComp['root_resolution']['root_asset_id'] ?? null;
        $componentId = (int)$rootComp['component_id'];

        // Check if root component is an isolated node (degree 0)
        if (empty($rootComp['edge_map']) || empty($rootComp['distance_paths'])) {
            return $this->buildFailureResult(
                'NO_PATH',
                "Root asset #{$rootAssetId} is physically isolated (degree 0) with no continuous transline edges.",
                [
                    'source_distance'       => $rawDistance,
                    'source_distance_unit'  => $rawUnit,
                    'normalized_distance_m' => $distanceM,
                    'root_asset_id'         => $rootAssetId,
                    'component_id'          => $componentId,
                ]
            );
        }

        $distancePaths = $rootComp['distance_paths'];

        // 6. Find Maximum Reachable Distance in Root Component
        $maxPathDistanceM = 0.0;
        foreach ($distancePaths as $p) {
            $pDist = (float)($p['total_distance_m'] ?? 0.0);
            if ($pDist > $maxPathDistanceM) {
                $maxPathDistanceM = $pDist;
            }
        }

        // Check Distance Out of Range
        if ($distanceM > $maxPathDistanceM) {
            $res = [
                'engine_version'         => self::VERSION,
                'status'                 => 'DISTANCE_OUT_OF_RANGE',
                'source_distance'        => $rawDistance,
                'source_distance_unit'   => $rawUnit,
                'normalized_distance_m'  => $distanceM,
                'root_asset_id'          => $rootAssetId,
                'component_id'           => $componentId,
                'path_id'                => null,
                'matched_edge_id'        => null,
                'distance_start_m'       => null,
                'distance_end_m'         => null,
                'traversal_distance_m'   => $distanceM,
                'candidate_paths'        => [],
                'max_path_distance_m'    => $maxPathDistanceM,
                'diagnostics'            => "Requested distance {$distanceM}m exceeds maximum cumulative path distance ({$maxPathDistanceM}m) in component {$componentId}.",
            ];
            $res['fingerprint'] = $this->calculateTraversalFingerprint($res);
            return $res;
        }

        // 7. Interval Matching across All Candidate Paths
        // Boundary rule: [start, end) for non-terminal edges, [start, end] for terminal edge
        $matchingHypotheses = [];
        $uniqueEdgeIds = [];

        foreach ($distancePaths as $path) {
            $segments = $path['segments'] ?? [];
            $segCount = count($segments);
            if ($segCount === 0) continue;

            foreach ($segments as $idx => $seg) {
                $startM = (float)$seg['start_distance_m'];
                $endM   = (float)$seg['end_distance_m'];
                $lenM   = (float)$seg['segment_length_m'];
                $isTerminalSeg = ($idx === $segCount - 1);

                // Deterministic shared boundary check
                $matches = false;
                if ($isTerminalSeg) {
                    $matches = ($distanceM >= $startM && $distanceM <= $endM);
                } else {
                    $matches = ($distanceM >= $startM && $distanceM < $endM);
                }

                if ($matches) {
                    $offsetM = round($distanceM - $startM, 2);
                    $ratio = ($lenM > 0.0) ? round($offsetM / $lenM, 4) : 0.0;

                    $hyp = [
                        'path_id'             => $path['path_id'],
                        'component_id'        => $componentId,
                        'root_asset_id'       => $rootAssetId,
                        'leaf_asset_id'       => $path['leaf_asset_id'],
                        'edge_id'             => (int)$seg['edge_id'],
                        'transline_code'      => $seg['transline_code'],
                        'upstream_asset_id'   => $seg['upstream_asset_id'],
                        'downstream_asset_id' => $seg['downstream_asset_id'],
                        'upstream_code'       => $seg['upstream_code'],
                        'downstream_code'     => $seg['downstream_code'],
                        'distance_start_m'    => $startM,
                        'distance_end_m'      => $endM,
                        'segment_length_m'    => $lenM,
                        'offset_in_segment_m' => $offsetM,
                        'segment_ratio'       => $ratio,
                    ];

                    $matchingHypotheses[] = $hyp;
                    $uniqueEdgeIds[(int)$seg['edge_id']] = true;
                    break; // Each path can have at most one matching segment
                }
            }
        }

        // 8. Classify Traversal Outcome (Single Match vs Branch Ambiguity vs Discontinuous)
        $matchCount = count($matchingHypotheses);
        $distinctEdgeCount = count($uniqueEdgeIds);

        if ($matchCount === 0) {
            // Distance is within maxPathDistanceM but not covered by any continuous segment (gap)
            $res = [
                'engine_version'         => self::VERSION,
                'status'                 => 'PATH_DISCONTINUOUS',
                'source_distance'        => $rawDistance,
                'source_distance_unit'   => $rawUnit,
                'normalized_distance_m'  => $distanceM,
                'root_asset_id'          => $rootAssetId,
                'component_id'           => $componentId,
                'path_id'                => null,
                'matched_edge_id'        => null,
                'distance_start_m'       => null,
                'distance_end_m'         => null,
                'traversal_distance_m'   => $distanceM,
                'candidate_paths'        => [],
                'max_path_distance_m'    => $maxPathDistanceM,
                'diagnostics'            => "Distance {$distanceM}m falls within topology reach but encounters an unlinked gap in authoritative paths.",
            ];
            $res['fingerprint'] = $this->calculateTraversalFingerprint($res);
            return $res;
        }

        // Case A: All matching hypotheses refer to the EXACT SAME physical edge (e.g. main trunk before fork)
        if ($distinctEdgeCount === 1) {
            $primaryHyp = $matchingHypotheses[0];
            $res = [
                'engine_version'         => self::VERSION,
                'status'                 => 'SINGLE_PATH_MATCH',
                'source_distance'        => $rawDistance,
                'source_distance_unit'   => $rawUnit,
                'normalized_distance_m'  => $distanceM,
                'root_asset_id'          => $rootAssetId,
                'component_id'           => $componentId,
                'path_id'                => $primaryHyp['path_id'],
                'matched_edge_id'        => $primaryHyp['edge_id'],
                'distance_start_m'       => $primaryHyp['distance_start_m'],
                'distance_end_m'         => $primaryHyp['distance_end_m'],
                'traversal_distance_m'   => $distanceM,
                'candidate_paths'        => [$primaryHyp],
                'max_path_distance_m'    => $maxPathDistanceM,
                'diagnostics'            => ($matchCount > 1)
                    ? "Distance {$distanceM}m matched on shared trunk segment (Edge #{$primaryHyp['edge_id']}) before downstream branch point; no spatial ambiguity."
                    : "Distance {$distanceM}m unambiguously matched on Edge #{$primaryHyp['edge_id']} ({$primaryHyp['path_id']}).",
            ];
            $res['fingerprint'] = $this->calculateTraversalFingerprint($res);
            return $res;
        }

        // Case B: Multiple distinct edges matched after a fork point -> True BRANCH_AMBIGUITY
        // Sort hypotheses deterministically by path_id ASC
        usort($matchingHypotheses, fn($a, $b) => strcmp($a['path_id'], $b['path_id']));

        $res = [
            'engine_version'         => self::VERSION,
            'status'                 => 'BRANCH_AMBIGUITY',
            'source_distance'        => $rawDistance,
            'source_distance_unit'   => $rawUnit,
            'normalized_distance_m'  => $distanceM,
            'root_asset_id'          => $rootAssetId,
            'component_id'           => $componentId,
            'path_id'                => null, // Ambiguous: multiple paths
            'matched_edge_id'        => null, // Ambiguous: multiple edges
            'distance_start_m'       => null,
            'distance_end_m'         => null,
            'traversal_distance_m'   => $distanceM,
            'candidate_paths'        => $matchingHypotheses,
            'max_path_distance_m'    => $maxPathDistanceM,
            'diagnostics'            => "Distance {$distanceM}m lies after a fork point and matches {$distinctEdgeCount} distinct downstream branch edges across " . count($matchingHypotheses) . " paths. Preserving all hypotheses.",
        ];
        $res['fingerprint'] = $this->calculateTraversalFingerprint($res);
        return $res;
    }

    /**
     * Resolve and normalize distance input from FL-01C or raw structure.
     */
    protected function resolveMeasurementDistance(array|float $input, array $options): array
    {
        if (is_numeric($input)) {
            $val = (float)$input;
            $unit = strtoupper(trim((string)($options['distance_unit'] ?? 'M')));
            $distM = ($unit === 'KM' || ($val < 50.0 && $unit !== 'M')) ? round($val * 1000.0, 2) : round($val, 2);
            return [
                'status'                => 'OK',
                'source_distance'       => $val,
                'source_distance_unit'  => ($distM !== $val) ? 'KM' : 'M',
                'normalized_distance_m' => $distM,
            ];
        }

        if (is_array($input)) {
            // Check FL-01C validation status
            $valStatus = $input['status'] ?? null;
            if ($valStatus === 'INVALID' || $valStatus === 'UNRESOLVED') {
                return [
                    'status'      => 'MEASUREMENT_NOT_USABLE',
                    'diagnostics' => "Measurement record is marked {$valStatus} by FL-01C and cannot be traversed.",
                ];
            }
            if ($valStatus === 'DIAGNOSTIC_ONLY') {
                return [
                    'status'      => 'DIAGNOSTIC_TELEMETRY',
                    'diagnostics' => "Telemetry is diagnostic-only ({$valStatus}); no fault event to traverse.",
                ];
            }

            // Extract distance from FL-01C normalized metrics or direct keys
            $norm = $input['normalized_metrics'] ?? [];
            $d = $norm['distance_km'] ?? $input['distance_km'] ?? $input['distance'] ?? null;

            if ($d === null || !is_numeric($d)) {
                return [
                    'status'      => 'NO_DISTANCE_INPUT',
                    'diagnostics' => 'Measurement record contains no authoritative distance metric.',
                ];
            }

            $distVal = (float)$d;
            $distUnit = strtoupper(trim((string)($input['distance_unit'] ?? (isset($norm['distance_km']) ? 'KM' : 'M'))));

            // Default to KM if from normalized_metrics or value < 100.0
            $distM = ($distUnit === 'KM' || ($distVal < 100.0 && $distUnit !== 'M'))
                ? round($distVal * 1000.0, 2)
                : round($distVal, 2);

            return [
                'status'                => 'OK',
                'source_distance'       => $distVal,
                'source_distance_unit'  => ($distM !== $distVal) ? 'KM' : 'M',
                'normalized_distance_m' => $distM,
            ];
        }

        return [
            'status'      => 'NO_DISTANCE_INPUT',
            'diagnostics' => 'Invalid distance input format.',
        ];
    }

    /**
     * Resolve topology model array.
     */
    protected function resolveTopologyModel(array|int $topologyInput, array $options): ?array
    {
        if (is_array($topologyInput)) {
            return $topologyInput;
        }

        if (is_int($topologyInput) && $topologyInput > 0) {
            $adapter = $this->topologyAdapter ?? new FaultTopologyAdapterService();
            return $adapter->buildFeederTopologyModel($topologyInput, $options);
        }

        return null;
    }

    /**
     * Find the component containing the resolved root.
     */
    protected function findRootComponent(array $topology): ?array
    {
        $components = $topology['components'] ?? [];
        foreach ($components as $comp) {
            if (($comp['root_resolution']['status'] ?? '') === 'RESOLVED') {
                return $comp;
            }
        }
        return null;
    }

    /**
     * Build structured failure result.
     */
    protected function buildFailureResult(string $status, string $diagnostics, array $context = []): array
    {
        $res = [
            'engine_version'         => self::VERSION,
            'status'                 => $status,
            'source_distance'        => $context['source_distance'] ?? null,
            'source_distance_unit'   => $context['source_distance_unit'] ?? null,
            'normalized_distance_m'  => $context['normalized_distance_m'] ?? null,
            'root_asset_id'          => $context['root_asset_id'] ?? null,
            'component_id'           => $context['component_id'] ?? null,
            'path_id'                => null,
            'matched_edge_id'        => null,
            'distance_start_m'       => null,
            'distance_end_m'         => null,
            'traversal_distance_m'   => $context['normalized_distance_m'] ?? null,
            'candidate_paths'        => [],
            'max_path_distance_m'    => null,
            'diagnostics'            => $diagnostics,
        ];
        $res['fingerprint'] = $this->calculateTraversalFingerprint($res);
        return $res;
    }

    /**
     * Compute Deterministic SHA-256 Fingerprint for Traversal Result.
     */
    public function calculateTraversalFingerprint(array $result): string
    {
        $canonical = [
            'engine_version'        => $result['engine_version'] ?? self::VERSION,
            'status'                => $result['status'] ?? '',
            'source_distance'       => $result['source_distance'] ?? null,
            'source_distance_unit'  => $result['source_distance_unit'] ?? null,
            'normalized_distance_m' => $result['normalized_distance_m'] ?? null,
            'root_asset_id'         => $result['root_asset_id'] ?? null,
            'component_id'          => $result['component_id'] ?? null,
            'path_id'               => $result['path_id'] ?? null,
            'matched_edge_id'       => $result['matched_edge_id'] ?? null,
            'distance_start_m'      => $result['distance_start_m'] ?? null,
            'distance_end_m'        => $result['distance_end_m'] ?? null,
            'candidate_paths'       => array_map(function($c) {
                return [
                    'path_id'             => $c['path_id'] ?? '',
                    'component_id'        => $c['component_id'] ?? 0,
                    'edge_id'             => $c['edge_id'] ?? 0,
                    'upstream_asset_id'   => $c['upstream_asset_id'] ?? 0,
                    'downstream_asset_id' => $c['downstream_asset_id'] ?? 0,
                    'distance_start_m'    => $c['distance_start_m'] ?? 0.0,
                    'distance_end_m'      => $c['distance_end_m'] ?? 0.0,
                    'offset_in_segment_m' => $c['offset_in_segment_m'] ?? 0.0,
                ];
            }, $result['candidate_paths'] ?? []),
            'diagnostics'           => $result['diagnostics'] ?? '',
        ];

        ksort($canonical);
        usort($canonical['candidate_paths'], fn($a, $b) => strcmp($a['path_id'], $b['path_id']));

        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $json);
    }
}
