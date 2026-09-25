<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * SIDAK TEJO — Phase B.4.1: Network Context Engine
 *
 * UNIFIED 360° ASSET & FEEDER TELEMETRY CONSUMPTION LAYER
 *
 * ARCHITECTURAL CONTRACT:
 * - 100% READ-ONLY / Zero-Mutation Invariant (Strictly 0 writes to DB).
 * - Feeder Head vs Protective Device distinction (Guard 1).
 * - If no authoritative path to feeder head, returns distance = null (Guard 1).
 * - Conductor impedance availability flag (Guard 5).
 * - Dynamic versioned distance tolerance (Guard 7).
 * - Snapshot locked to TOPOLOGY-20260925-243-ad2c9fcb.
 */
class NetworkContextEngine
{
    public const DEFAULT_DISTANCE_TOLERANCE = 250.0; // meters (Guard 7)
    public const ANALYSIS_ENGINE_VERSION = 'FLI-1.0.0'; // Guard 3

    protected NetworkIntelligenceService $intelligenceService;
    protected BaseConnection $db;

    public function __construct(?NetworkIntelligenceService $intelligenceService = null, ?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->intelligenceService = $intelligenceService ?? new NetworkIntelligenceService($this->db);
    }

    /**
     * Get Snapshot ID from Intelligence Service
     */
    public function getTopologySnapshotId(): string
    {
        return $this->intelligenceService->getTopologySnapshotId();
    }

    /**
     * B.4.1 — Unified 360° Asset Context Telemetry
     *
     * @param int|string $assetIdentifier Asset primary key ID or kode_asset
     * @param array|null $customAssets Optional in-memory assets fixture for testing
     * @param array|null $customTranslines Optional in-memory translines fixture for testing
     * @return array
     */
    public function getAssetContext($assetIdentifier, ?array $customAssets = null, ?array $customTranslines = null): array
    {
        // 1. Resolve Asset Identity
        $asset = $this->resolveAsset($assetIdentifier, $customAssets);
        if (!$asset) {
            return $this->intelligenceService->createEnvelope([
                'status'     => 'ASSET_NOT_FOUND',
                'identifier' => $assetIdentifier,
            ]);
        }

        $assetId = (int)$asset['id'];
        $feederId = (int)$asset['penyulang_id'];

        // 2. Fetch Graph & Feeder Traversal
        $graph = $this->intelligenceService->buildNetworkGraph($feederId, $customAssets, $customTranslines);
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];
        $adj = $graph['adjacency'][$assetId] ?? [];
        $deg = $graph['degrees'][$assetId] ?? 0;
        $edgeLookup = $graph['edge_lookup'];

        $traversal = $this->intelligenceService->traverseFeeder($feederId, null, $customAssets, $customTranslines);
        $tPayload = $traversal['payload'] ?? [];
        $tree = $tPayload['traversal_tree'] ?? [];
        $rootId = $tPayload['root_asset_id'] ?? null;

        // 3. Resolve Feeder Head vs Protective Device (Guard 1)
        $feederHeadResolution = $tPayload['root_resolution'] ?? 'UNRESOLVED';
        $feederHeadConfidence = $tPayload['root_confidence'] ?? 0.0;

        $protectiveDevice = null;
        foreach ($nodes as $nid => $n) {
            $jenis = strtoupper($n['jenis_asset'] ?? '');
            $nama = strtoupper($n['nama_asset'] ?? '');
            if (str_contains($jenis, 'PMCB') || str_contains($nama, 'PMCB') || str_contains($jenis, 'RECLOSER')) {
                $protectiveDevice = [
                    'asset_id'    => $nid,
                    'kode_asset'  => $n['kode_asset'],
                    'device_type' => str_contains($jenis, 'PMCB') ? 'PMCB' : 'RECLOSER',
                ];
                break;
            }
        }

        // 4. Calculate Authoritative Upstream Path to Feeder Head (Guard 1)
        $distanceToFeederHeadM = null;
        $hopsToFeederHead = null;
        $upstreamPath = [];
        $upstreamReachable = false;
        $upstreamUnreachableReason = null;

        if ($rootId !== null && isset($tree[$assetId])) {
            $nodeInfo = $tree[$assetId];
            $distanceToFeederHeadM = (float)$nodeInfo['accumulated_dist_m'];
            $hopsToFeederHead = (int)$nodeInfo['depth_level'];
            $upstreamReachable = true;

            // Trace path backwards using traversal_parent (Guard 3)
            $curr = $assetId;
            while ($curr !== null) {
                array_unshift($upstreamPath, [
                    'asset_id'   => $curr,
                    'kode_asset' => $nodes[$curr]['kode_asset'],
                ]);
                $curr = $tree[$curr]['traversal_parent'] ?? null;
            }
        } else {
            $upstreamReachable = false;
            $upstreamUnreachableReason = 'NO_AUTHORITATIVE_PATH_TO_FEEDER_HEAD';
        }

        // Distance to protective device (if different from root)
        $distanceToProtectiveDeviceM = null;
        if ($protectiveDevice !== null && $protectiveDevice['asset_id'] !== $rootId) {
            $pathDev = $this->intelligenceService->analyzePath($protectiveDevice['asset_id'], $assetId, $customAssets, $customTranslines);
            if (!empty($pathDev['payload']['reachable'])) {
                $distanceToProtectiveDeviceM = (float)$pathDev['payload']['distance_m'];
            }
        } elseif ($protectiveDevice !== null && $protectiveDevice['asset_id'] === $rootId) {
            $distanceToProtectiveDeviceM = $distanceToFeederHeadM;
        }

        // 5. Detect Nearest Protective Switches (Upstream & Downstream, Guard 1)
        $switchKeywords = ['LBS', 'RECLOSER', 'REC', 'SECTIONALIZER', 'FCO', 'FUSE CUT OUT', 'SWITCH'];
        $nearestUpstreamSwitch = null;

        if ($upstreamReachable && count($upstreamPath) > 1) {
            // Traverse backwards from parent towards root
            for ($i = count($upstreamPath) - 2; $i >= 0; $i--) {
                $uId = $upstreamPath[$i]['asset_id'];
                $uNode = $nodes[$uId] ?? null;
                if ($uNode) {
                    $uJenis = strtoupper($uNode['jenis_asset'] ?? '');
                    $uNama = strtoupper($uNode['nama_asset'] ?? '');
                    foreach ($switchKeywords as $kw) {
                        if (str_contains($uJenis, $kw) || str_contains($uNama, $kw)) {
                            $nearestUpstreamSwitch = [
                                'asset_id'    => $uId,
                                'kode_asset'  => $uNode['kode_asset'],
                                'switch_type' => $kw,
                                'distance_m'  => round($distanceToFeederHeadM - ($tree[$uId]['accumulated_dist_m'] ?? 0.0), 2),
                            ];
                            break 2;
                        }
                    }
                }
            }
        }

        // 6. Downstream Subtree Telemetry
        $downstreamAssets = [];
        $downstreamBranches = 0;
        $totalDownstreamDistanceM = 0.0;
        $nearestDownstreamSwitch = null;

        if (isset($tree[$assetId])) {
            $queue = $tree[$assetId]['children'] ?? [];
            while (!empty($queue)) {
                $cId = array_shift($queue);
                $downstreamAssets[] = $cId;
                $cNode = $tree[$cId] ?? null;
                if ($cNode) {
                    if (count($cNode['children']) > 1) {
                        $downstreamBranches++;
                    }
                    // Check if downstream node is a switch
                    if ($nearestDownstreamSwitch === null && isset($nodes[$cId])) {
                        $cJenis = strtoupper($nodes[$cId]['jenis_asset'] ?? '');
                        $cNama = strtoupper($nodes[$cId]['nama_asset'] ?? '');
                        foreach ($switchKeywords as $kw) {
                            if (str_contains($cJenis, $kw) || str_contains($cNama, $kw)) {
                                $nearestDownstreamSwitch = [
                                    'asset_id'    => $cId,
                                    'kode_asset'  => $nodes[$cId]['kode_asset'],
                                    'switch_type' => $kw,
                                ];
                                break;
                            }
                        }
                    }
                    foreach ($cNode['children'] as $grandChild) {
                        $queue[] = $grandChild;
                    }
                }
            }

            if (!empty($downstreamAssets)) {
                $maxSubtreeDist = max(array_map(fn($id) => $tree[$id]['accumulated_dist_m'] ?? 0.0, $downstreamAssets));
                $totalDownstreamDistanceM = round($maxSubtreeDist - $distanceToFeederHeadM, 2);
            }
        }

        // 7. Connected Translines & Conductor Specs
        $connectedEdges = [];
        $conductorTypes = [];
        foreach ($adj as $nbrId) {
            $edge = $edgeLookup["{$assetId}:{$nbrId}"] ?? null;
            if ($edge) {
                $condStr = ($edge['conductor_type'] ?? '') . ' ' . ($edge['conductor_size'] ?? '');
                $connectedEdges[] = [
                    'transline_id'   => $edge['id'],
                    'transline_code' => $edge['transline_code'],
                    'neighbor_asset' => $nodes[$nbrId]['kode_asset'] ?? "AST-{$nbrId}",
                    'distance_m'     => $edge['distance_meters'],
                    'conductor'      => $condStr,
                ];
                if (!in_array($condStr, $conductorTypes, true)) {
                    $conductorTypes[] = $condStr;
                }
            }
        }

        // 8. Construct Final Unified 360° Envelope
        return $this->intelligenceService->createEnvelope([
            'asset' => [
                'id'                   => $assetId,
                'kode_asset'           => $asset['kode_asset'],
                'nama_asset'           => $asset['nama_asset'],
                'jenis_asset'          => $asset['jenis_asset'],
                'feeder_id'            => $feederId,
                'section_id'           => $asset['section_id'] ? (int)$asset['section_id'] : null,
                'ulp_id'               => (int)($asset['ulp_id'] ?? 0),
                'sequence_no'          => isset($asset['sequence_no']) ? (int)$asset['sequence_no'] : null,
                'coordinates'          => [(float)($asset['longitude'] ?? 0), (float)($asset['latitude'] ?? 0)],
            ],
            'topology_metrics' => [
                'degree'                  => $deg,
                'topological_role'        => $deg === 0 ? 'ISOLATED' : ($deg === 1 ? 'TERMINAL' : ($deg === 2 ? 'INTERMEDIATE' : 'BRANCH')),
                'component_id'            => isset($graph['node_to_component'][$assetId]) ? "COMP-{$graph['node_to_component'][$assetId]}" : 'UNCONNECTED',
                'connected_translines'    => $connectedEdges,
                'conductor_specs'         => implode(', ', $conductorTypes) ?: 'UNKNOWN',
                'impedance_supported'     => false, // Guard 5: strictly false until canonical impedance registry
                'impedance_status_reason' => 'NO_CANONICAL_IMPEDANCE_PROFILE',
            ],
            'upstream_lineage' => [
                'feeder_head' => [
                    'asset_id'   => $rootId,
                    'kode_asset' => $rootId && isset($nodes[$rootId]) ? $nodes[$rootId]['kode_asset'] : null,
                    'resolution' => $feederHeadResolution,
                    'confidence' => $feederHeadConfidence,
                ],
                'protective_head' => $protectiveDevice,
                'reachable'                   => $upstreamReachable,
                'unreachable_reason'          => $upstreamUnreachableReason,
                'distance_to_feeder_head_m'   => $distanceToFeederHeadM,
                'hops_to_feeder_head'         => $hopsToFeederHead,
                'distance_to_protective_device_m' => $distanceToProtectiveDeviceM,
                'nearest_upstream_switch'     => $nearestUpstreamSwitch,
                'upstream_path_node_ids'      => array_column($upstreamPath, 'asset_id'),
            ],
            'downstream_lineage' => [
                'downstream_asset_count'      => count($downstreamAssets),
                'downstream_branch_count'     => $downstreamBranches,
                'total_downstream_distance_m' => $totalDownstreamDistanceM,
                'nearest_downstream_switch'   => $nearestDownstreamSwitch,
            ],
        ]);
    }

    /**
     * B.4.1 & B.4.4 — Find Candidate Assets Along Feeder Tree Within Distance Tolerance (Guards 6 & 7)
     *
     * @param int $penyulangId Feeder primary key
     * @param float $targetDistanceMeters Target electrical distance (e.g. from relay)
     * @param float|null $toleranceMeters Optional tolerance (null = default 250m, Guard 7)
     * @param array|null $customAssets Optional in-memory assets fixture
     * @param array|null $customTranslines Optional in-memory translines fixture
     * @return array
     */
    public function findCandidateAssetsByDistance(
        int $penyulangId,
        float $targetDistanceMeters,
        ?float $toleranceMeters = null,
        ?array $customAssets = null,
        ?array $customTranslines = null
    ): array {
        $tol = $toleranceMeters !== null && $toleranceMeters > 0 ? $toleranceMeters : self::DEFAULT_DISTANCE_TOLERANCE;

        // Traverse feeder tree from root
        $traversal = $this->intelligenceService->traverseFeeder($penyulangId, null, $customAssets, $customTranslines);
        $tPayload = $traversal['payload'] ?? [];
        $tree = $tPayload['traversal_tree'] ?? [];
        $rootId = $tPayload['root_asset_id'] ?? null;

        if (empty($tree)) {
            return $this->intelligenceService->createEnvelope([
                'feeder_id'             => $penyulangId,
                'target_distance_m'     => $targetDistanceMeters,
                'tolerance_m'           => $tol,
                'candidates_count'      => 0,
                'candidates'            => [],
                'status'                => 'EMPTY_FEEDER_TREE',
            ]);
        }

        $candidates = [];
        foreach ($tree as $nid => $node) {
            $dist = (float)$node['accumulated_dist_m'];
            $dev = abs($dist - $targetDistanceMeters);

            if ($dev <= $tol) {
                // Distance fitness score [0.0 - 1.0]
                $fitness = round(1.0 - ($dev / $tol), 4);

                $candidates[] = [
                    'asset_id'              => $nid,
                    'kode_asset'            => $node['kode_asset'],
                    'nama_asset'            => $node['nama_asset'],
                    'section_id'            => $node['section_id'],
                    'electrical_distance_m' => $dist,
                    'distance_deviation_m'  => round($dev, 2),
                    'distance_fitness'      => $fitness,
                    'hop_count'             => $node['hop_count'],
                    'traversal_parent'      => $node['traversal_parent'],
                ];
            }
        }

        // Sort candidates by lowest distance deviation (ascending)
        usort($candidates, fn($a, $b) => $a['distance_deviation_m'] <=> $b['distance_deviation_m']);

        // Assign preliminary ranks
        foreach ($candidates as $idx => &$cand) {
            $cand['preliminary_rank'] = $idx + 1;
        }
        unset($cand);

        return $this->intelligenceService->createEnvelope([
            'feeder_id'             => $penyulangId,
            'root_asset_id'         => $rootId,
            'root_resolution'       => $tPayload['root_resolution'] ?? 'NONE',
            'target_distance_m'     => $targetDistanceMeters,
            'tolerance_m'           => $tol,
            'candidates_count'      => count($candidates),
            'candidates'            => $candidates,
        ]);
    }

    /**
     * Resolve asset row by ID or kode_asset
     */
    protected function resolveAsset($identifier, ?array $customAssets = null): ?array
    {
        if (empty($identifier)) {
            return null;
        }

        if ($customAssets !== null) {
            foreach ($customAssets as $a) {
                if (is_numeric($identifier) && (int)$a['id'] === (int)$identifier) {
                    return $a;
                }
                if ((string)$a['kode_asset'] === (string)$identifier || ($a['nama_asset'] ?? '') === (string)$identifier) {
                    return $a;
                }
            }
            return null;
        }

        $builder = $this->db->table('assets');
        if (is_numeric($identifier)) {
            $builder->where('id', (int)$identifier);
        } else {
            $builder->groupStart()
                ->where('kode_asset', (string)$identifier)
                ->orWhere('nama_asset', (string)$identifier)
                ->groupEnd();
        }

        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }

        return $builder->get()->getRowArray();
    }
}
