<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * CR-FL-01 Phase FL-01A: Read-Only Authoritative Topology Adapter
 *
 * Implements the authoritative topology graph read model for the Fault Locator Engine.
 * Strictly READ-ONLY: 0 INSERT, 0 UPDATE, 0 DELETE, 0 DDL.
 *
 * Enforces Mandatory Engineering Amendments:
 * 1. Root Resolution: Never infer root from geographic proximity or nearest terminal.
 *    Priority A: Explicit upstream anchor, Priority B: Explicit recloser anchor,
 *    Priority C: Existing authoritative GI connection. Otherwise ROOT_UNRESOLVED.
 * 2. Weighted Distance Traversal: BFS for connected components, Dijkstra for cumulative distance.
 * 3. Branching: Preserves distinct branch paths (component_id, path_id) and segment boundaries.
 * 4. Geometry: Validates WKT LineString geometry and endpoint-to-asset coordinate consistency.
 * 5. Topology Integrity Firewall: Validates endpoints, non-orphans, same feeder, non-duplicates.
 *    Does not auto-repair; returns honest failure states (TOPOLOGY_VALID, TOPOLOGY_PARTIAL, TOPOLOGY_INVALID).
 */
class FaultTopologyAdapterService
{
    protected BaseConnection $db;

    /**
     * Authoritative Substation (GI) Directory for ULP Sidoarjo Kota
     */
    protected array $substationMaster = [
        1  => ['id' => 1,  'kode' => 'GI-BDR',     'nama' => 'GI BUDURAN',     'latitude' => -7.42345280, 'longitude' => 112.72043070],
        2  => ['id' => 2,  'kode' => 'GI-SDO',     'nama' => 'GI SIDOARJO',    'latitude' => -7.49650560, 'longitude' => 112.70176410],
        5  => ['id' => 5,  'kode' => 'GI-SDT-001', 'nama' => 'GI SEDATI',      'latitude' => -7.39638180, 'longitude' => 112.76112440],
        6  => ['id' => 6,  'kode' => 'GI-BBD-001', 'nama' => 'GI BABADAN',     'latitude' => -7.38901380, 'longitude' => 112.67294940],
        7  => ['id' => 7,  'kode' => 'GI-BLN-001', 'nama' => 'GI BALONGBENDO', 'latitude' => -7.41321000, 'longitude' => 112.55843300],
        8  => ['id' => 8,  'kode' => 'GI-TRK-001', 'nama' => 'GI TARIK',       'latitude' => -7.45986131, 'longitude' => 112.49719344],
        9  => ['id' => 9,  'kode' => 'GI-DRY-001', 'nama' => 'GI DRIYOREJO',   'latitude' => -7.36900500, 'longitude' => 112.59981600],
        10 => ['id' => 10, 'kode' => 'GI-KRN-001', 'nama' => 'GI KRIAN',       'latitude' => -7.34611700, 'longitude' => 112.60333200],
        11 => ['id' => 11, 'kode' => 'GI-KAJ-001', 'nama' => 'GI KASIH JATIM', 'latitude' => -7.34809000, 'longitude' => 112.56793200],
    ];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Build the Authoritative Topology Read Model for a Feeder.
     *
     * @param int $penyulangId
     * @param array $options ['override_root_asset_id' => int|null, 'tolerance_meters' => float]
     * @return array
     */
    public function buildFeederTopologyModel(int $penyulangId, array $options = []): array
    {
        if ($penyulangId <= 0) {
            return $this->buildEmptyModel($penyulangId, 'INVALID_FEEDER_ID');
        }

        // 1. Load Feeder metadata
        $feeder = $this->loadFeeder($penyulangId);
        if (!$feeder) {
            return $this->buildEmptyModel($penyulangId, 'FEEDER_NOT_FOUND');
        }

        $sourceAnchor = $this->resolveSourceAnchor($feeder);

        // 2. Load Assets and Translines (Authoritative DB)
        $assets = $this->loadAssets($penyulangId);
        $translines = $this->loadTranslines($penyulangId);

        // 3. Topology Integrity Firewall
        $firewallResult = $this->runTopologyIntegrityFirewall($assets, $translines, $penyulangId, $options);

        $nodeMap = [];
        foreach ($assets as $a) {
            $nodeMap[(int)$a['id']] = $a;
        }

        $validEdges = $firewallResult['valid_edges'];
        $anomalies  = $firewallResult['anomalies'];

        // Build Adjacency and Degree Maps
        $adj = [];
        $degrees = [];
        foreach (array_keys($nodeMap) as $aId) {
            $adj[$aId] = [];
            $degrees[$aId] = 0;
        }

        foreach ($validEdges as $edge) {
            $sId = (int)$edge['source_asset_id'];
            $tId = (int)$edge['target_asset_id'];
            $adj[$sId][] = $tId;
            $adj[$tId][] = $sId;
            $degrees[$sId] = ($degrees[$sId] ?? 0) + 1;
            $degrees[$tId] = ($degrees[$tId] ?? 0) + 1;
        }

        // Deduplicate adjacency list
        foreach ($adj as $id => $neighbors) {
            $adj[$id] = array_values(array_unique($neighbors));
        }

        // 4. Partition Connected Components via BFS
        $components = $this->partitionConnectedComponents($nodeMap, $adj, $validEdges);

        // 5. Root Resolution & Weighted Distance Traversal (Dijkstra)
        $overrideRootId = $options['override_root_asset_id'] ?? null;
        $processedComponents = [];
        $allDistancePaths = [];
        $overallRootStatus = 'RESOLVED';

        foreach ($components as $idx => $comp) {
            $compAssetIds = $comp['asset_ids'];
            $compEdgeMap  = $comp['edge_map'];

            $rootRes = $this->resolveComponentRoot(
                $penyulangId,
                $compAssetIds,
                $nodeMap,
                $compEdgeMap,
                $sourceAnchor,
                $overrideRootId
            );

            if ($rootRes['status'] === 'ROOT_UNRESOLVED') {
                if ($comp['is_main_trunk']) {
                    $overallRootStatus = 'TOPOLOGY_ROOT_UNRESOLVED';
                }
            }

            $comp['root_resolution'] = $rootRes;

            // If root is resolved, calculate weighted distance chains via Dijkstra
            if ($rootRes['status'] === 'RESOLVED' && $rootRes['root_asset_id'] !== null) {
                $paths = $this->buildWeightedDistanceChains(
                    $comp['component_id'],
                    $rootRes['root_asset_id'],
                    $nodeMap,
                    $compEdgeMap,
                    $adj
                );
                $comp['distance_paths'] = $paths;
                foreach ($paths as $p) {
                    $allDistancePaths[] = $p;
                }
            } else {
                $comp['distance_paths'] = [];
            }

            $processedComponents[] = $comp;
        }

        // 6. Determine Final Model Status
        $finalStatus = 'TOPOLOGY_VALID';
        if (!empty($anomalies)) {
            $hasFatal = false;
            foreach ($anomalies as $anom) {
                if (in_array($anom['type'], ['ORPHAN_ENDPOINT', 'CROSS_FEEDER_EDGE', 'DUPLICATE_NATURAL_KEY'], true)) {
                    $hasFatal = true;
                    break;
                }
            }
            $finalStatus = $hasFatal ? 'TOPOLOGY_INVALID' : 'TOPOLOGY_PARTIAL';
        }

        if ($overallRootStatus === 'TOPOLOGY_ROOT_UNRESOLVED') {
            $finalStatus = 'TOPOLOGY_ROOT_UNRESOLVED';
        }

        // Enrich node degrees in asset records
        $enrichedAssets = [];
        foreach ($assets as $a) {
            $aId = (int)$a['id'];
            $deg = $degrees[$aId] ?? 0;
            $a['degree'] = $deg;
            $a['role'] = $this->classifyNodeRole($deg);
            $enrichedAssets[] = $a;
        }

        $model = [
            'adapter_version'  => 'FL-01A-1.0',
            'status'           => $finalStatus,
            'feeder_id'        => $penyulangId,
            'feeder_code'      => $feeder['kode_penyulang'] ?? "PYL-{$penyulangId}",
            'feeder_name'      => $feeder['nama_penyulang'] ?? "FEEDER #{$penyulangId}",
            'ulp_id'           => (int)($feeder['ulp_id'] ?? 0),
            'source_anchor'    => $sourceAnchor,
            'integrity_audit'  => [
                'is_valid'             => empty($anomalies),
                'total_edges_examined' => count($translines),
                'valid_edges_count'    => count($validEdges),
                'anomalies_count'      => count($anomalies),
                'anomalies'            => $anomalies,
            ],
            'summary' => [
                'total_assets'     => count($assets),
                'total_edges'      => count($validEdges),
                'components_count' => count($processedComponents),
                'isolated_assets'  => count(array_filter($enrichedAssets, fn($x) => $x['degree'] === 0)),
                'terminal_assets'  => count(array_filter($enrichedAssets, fn($x) => $x['degree'] === 1)),
                'branch_assets'    => count(array_filter($enrichedAssets, fn($x) => $x['degree'] >= 3)),
            ],
            'assets'           => $enrichedAssets,
            'edges'            => array_values($validEdges),
            'components'       => $processedComponents,
            'distance_paths'   => $allDistancePaths,
        ];

        $model['model_fingerprint'] = $this->calculateModelFingerprint($model);
        $model['generated_at']      = date('c');

        return $model;
    }

    /**
     * Topology Integrity Firewall (Phase FL-01A mandatory check)
     */
    public function runTopologyIntegrityFirewall(array $assets, array $translines, int $penyulangId, array $options = []): array
    {
        $nodeMap = [];
        foreach ($assets as $a) {
            $nodeMap[(int)$a['id']] = $a;
        }

        $validEdges = [];
        $anomalies = [];
        $seenNaturalKeys = [];

        $toleranceMeters = (float)($options['tolerance_meters'] ?? 10.0);

        foreach ($translines as $tl) {
            $edgeId = (int)$tl['id'];
            $sId = (int)($tl['source_asset_id'] ?? 0);
            $tId = (int)($tl['target_asset_id'] ?? 0);
            $fId = (int)($tl['penyulang_id'] ?? 0);
            $dist = (float)($tl['distance_meters'] ?? 0.0);
            $isActive = (int)($tl['is_active'] ?? 0);
            $geom = $tl['geometry'] ?? null;

            // 1. Check Active Edge
            if ($isActive !== 1) {
                $anomalies[] = [
                    'edge_id'     => $edgeId,
                    'type'        => 'INACTIVE_EDGE',
                    'description' => "Edge #{$edgeId} is inactive (is_active != 1)",
                ];
                continue;
            }

            // 2. Check Positive Segment Length
            if ($dist <= 0.0) {
                $anomalies[] = [
                    'edge_id'     => $edgeId,
                    'type'        => 'NON_POSITIVE_LENGTH',
                    'description' => "Edge #{$edgeId} has non-positive distance ({$dist}m)",
                ];
                continue;
            }

            // 3. Check Endpoint Existence (Non-Orphan)
            if (!isset($nodeMap[$sId])) {
                $anomalies[] = [
                    'edge_id'     => $edgeId,
                    'type'        => 'ORPHAN_ENDPOINT',
                    'description' => "Edge #{$edgeId} source_asset_id #{$sId} does not exist in assets",
                ];
                continue;
            }

            if (!isset($nodeMap[$tId])) {
                $anomalies[] = [
                    'edge_id'     => $edgeId,
                    'type'        => 'ORPHAN_ENDPOINT',
                    'description' => "Edge #{$edgeId} target_asset_id #{$tId} does not exist in assets",
                ];
                continue;
            }

            if ($sId === $tId) {
                $anomalies[] = [
                    'edge_id'     => $edgeId,
                    'type'        => 'SELF_LOOP',
                    'description' => "Edge #{$edgeId} connects asset #{$sId} to itself",
                ];
                continue;
            }

            $sourceAsset = $nodeMap[$sId];
            $targetAsset = $nodeMap[$tId];

            // 4. Check Same Feeder & Cross-Feeder Contamination
            $sFeeder = (int)($sourceAsset['penyulang_id'] ?? 0);
            $tFeeder = (int)($targetAsset['penyulang_id'] ?? 0);

            if ($fId !== $penyulangId || $sFeeder !== $penyulangId || $tFeeder !== $penyulangId) {
                $anomalies[] = [
                    'edge_id'     => $edgeId,
                    'type'        => 'CROSS_FEEDER_EDGE',
                    'description' => "Edge #{$edgeId} cross-feeder contamination (Edge Feeder: {$fId}, Source: {$sFeeder}, Target: {$tFeeder})",
                ];
                continue;
            }

            // 5. Check Duplicate Natural Key
            $natKey = min($sId, $tId) . ':' . max($sId, $tId);
            if (isset($seenNaturalKeys[$natKey])) {
                $anomalies[] = [
                    'edge_id'     => $edgeId,
                    'type'        => 'DUPLICATE_NATURAL_KEY',
                    'description' => "Edge #{$edgeId} duplicate pair with Edge #{$seenNaturalKeys[$natKey]} ({$natKey})",
                ];
                continue;
            }
            $seenNaturalKeys[$natKey] = $edgeId;

            // 6. Geometry Validation (LineString WKT)
            $parsedGeom = $this->parseLineStringWkt($geom);
            if ($parsedGeom === null || count($parsedGeom) < 2) {
                $anomalies[] = [
                    'edge_id'     => $edgeId,
                    'type'        => 'INVALID_GEOMETRY',
                    'description' => "Edge #{$edgeId} has invalid or malformed WKT LineString geometry",
                ];
                continue;
            }

            // 7. Geometry Endpoint Consistency with Asset Coordinates
            $firstVertex = $parsedGeom[0];
            $lastVertex  = $parsedGeom[count($parsedGeom) - 1];

            $sLat = (float)($sourceAsset['latitude'] ?? 0);
            $sLon = (float)($sourceAsset['longitude'] ?? 0);
            $tLat = (float)($targetAsset['latitude'] ?? 0);
            $tLon = (float)($targetAsset['longitude'] ?? 0);

            $distDirect = $this->haversineMeters($firstVertex['lat'], $firstVertex['lon'], $sLat, $sLon)
                        + $this->haversineMeters($lastVertex['lat'], $lastVertex['lon'], $tLat, $tLon);

            $distReverse = $this->haversineMeters($firstVertex['lat'], $firstVertex['lon'], $tLat, $tLon)
                         + $this->haversineMeters($lastVertex['lat'], $lastVertex['lon'], $sLat, $sLon);

            $minMismatch = min($distDirect, $distReverse);
            if ($minMismatch > $toleranceMeters * 2) {
                $anomalies[] = [
                    'edge_id'     => $edgeId,
                    'type'        => 'GEOMETRY_COORDINATE_MISMATCH',
                    'description' => "Edge #{$edgeId} geometry endpoints deviate from asset coordinates (Mismatch: " . round($minMismatch, 2) . "m)",
                ];
            }

            $validEdges[$edgeId] = [
                'id'                 => $edgeId,
                'transline_code'     => $tl['transline_code'] ?? "TL-{$edgeId}",
                'source_asset_id'    => $sId,
                'target_asset_id'    => $tId,
                'source_asset_code'  => $sourceAsset['kode_asset'],
                'target_asset_code'  => $targetAsset['kode_asset'],
                'distance_meters'    => $dist,
                'conductor_type'     => $tl['conductor_type'] ?? 'AAAC',
                'conductor_size'     => $tl['conductor_size'] ?? '150 mm²',
                'geometry'           => $geom,
                'parsed_vertices'    => $parsedGeom,
            ];
        }

        return [
            'is_valid'    => empty($anomalies),
            'valid_edges' => $validEdges,
            'anomalies'   => $anomalies,
        ];
    }

    /**
     * Partition Graph into Connected Components using BFS
     */
    protected function partitionConnectedComponents(array $nodeMap, array $adj, array $validEdges): array
    {
        $visited = [];
        $components = [];
        $compIndex = 1;

        $edgePairMap = [];
        foreach ($validEdges as $e) {
            $key = min($e['source_asset_id'], $e['target_asset_id']) . ':' . max($e['source_asset_id'], $e['target_asset_id']);
            $edgePairMap[$key] = $e;
        }

        foreach (array_keys($nodeMap) as $assetId) {
            if (isset($visited[$assetId])) continue;

            $queue = [$assetId];
            $visited[$assetId] = true;
            $compMembers = [];
            $compEdges = [];

            while (!empty($queue)) {
                $curr = array_shift($queue);
                $compMembers[] = $curr;

                if (isset($adj[$curr])) {
                    foreach ($adj[$curr] as $neighbor) {
                        $pairKey = min($curr, $neighbor) . ':' . max($curr, $neighbor);
                        if (isset($edgePairMap[$pairKey])) {
                            $compEdges[$pairKey] = $edgePairMap[$pairKey];
                        }

                        if (!isset($visited[$neighbor])) {
                            $visited[$neighbor] = true;
                            $queue[] = $neighbor;
                        }
                    }
                }
            }

            sort($compMembers);
            $totalLengthMeters = array_sum(array_column($compEdges, 'distance_meters'));

            $components[] = [
                'component_id'        => 0, // Assigned sequentially after deterministic sort
                'asset_ids'           => $compMembers,
                'asset_count'         => count($compMembers),
                'edge_count'          => count($compEdges),
                'total_length_meters' => round($totalLengthMeters, 2),
                'edge_map'            => $compEdges,
                'is_main_trunk'       => false,
            ];
        }

        usort($components, function($a, $b) {
            if ($b['asset_count'] !== $a['asset_count']) {
                return $b['asset_count'] <=> $a['asset_count'];
            }
            $minA = !empty($a['asset_ids']) ? min($a['asset_ids']) : 0;
            $minB = !empty($b['asset_ids']) ? min($b['asset_ids']) : 0;
            return $minA <=> $minB;
        });

        foreach ($components as $idx => &$comp) {
            $comp['component_id'] = $idx + 1;
        }
        unset($comp);

        if (!empty($components)) {
            $components[0]['is_main_trunk'] = true;
        }

        return $components;
    }

    /**
     * Authoritative Feeder Root Resolution
     */
    public function resolveComponentRoot(
        int $penyulangId,
        array $compAssetIds,
        array $nodeMap,
        array $compEdgeMap,
        ?array $sourceAnchor,
        ?int $overrideRootId = null
    ): array {
        if ($overrideRootId !== null && in_array($overrideRootId, $compAssetIds, true)) {
            return [
                'status'        => 'RESOLVED',
                'method'        => 'MANUAL_ENGINEERING_OVERRIDE',
                'root_asset_id' => $overrideRootId,
                'root_asset_code' => $nodeMap[$overrideRootId]['kode_asset'] ?? "AST-{$overrideRootId}",
                'evidence'      => "Explicit root override specified by caller: Asset #{$overrideRootId}",
                'is_authoritative' => true,
            ];
        }

        // Priority A: Explicit authoritative upstream anchor in sections
        if ($this->db->tableExists('sections')) {
            $sections = $this->db->table('sections')
                ->where('penyulang_id', $penyulangId)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            if (!empty($sections)) {
                $headSection = $sections[0];
                $secName = strtoupper($headSection['nama_section'] ?? '');
                if (str_contains($secName, 'GI') || str_contains($secName, 'GARDU INDUK') || str_contains($secName, 'BAY')) {
                    foreach ($compAssetIds as $aId) {
                        $asset = $nodeMap[$aId];
                        if (isset($asset['section_id']) && (int)$asset['section_id'] === (int)$headSection['id']) {
                            return [
                                'status'        => 'RESOLVED',
                                'method'        => 'EXPLICIT_UPSTREAM_SECTION_ANCHOR',
                                'root_asset_id' => $aId,
                                'root_asset_code' => $asset['kode_asset'],
                                'evidence'      => "Asset #{$aId} ({$asset['kode_asset']}) is assigned to head section #{$headSection['id']} ({$headSection['nama_section']})",
                                'is_authoritative' => true,
                            ];
                        }
                    }
                }
            }
        }

        // Priority B: Explicit feeder switch/recloser anchor
        foreach ($compAssetIds as $aId) {
            $asset = $nodeMap[$aId];
            $j = strtoupper($asset['jenis_asset'] ?? '');
            $code = strtoupper($asset['kode_asset'] ?? '');
            $name = strtoupper($asset['nama_asset'] ?? '');

            if (str_contains($code, 'RECLOSER') || str_contains($name, 'RECLOSER') ||
                str_contains($code, 'LBS') || str_contains($name, 'LBS') ||
                str_contains($j, 'RECLOSER') || str_contains($j, 'PMT')) {
                return [
                    'status'        => 'RESOLVED',
                    'method'        => 'EXPLICIT_RECLOSER_SWITCH_ANCHOR',
                    'root_asset_id' => $aId,
                    'root_asset_code' => $asset['kode_asset'],
                    'evidence'      => "Switch/Recloser asset #{$aId} ({$asset['kode_asset']}) serves as authoritative boundary anchor",
                    'is_authoritative' => true,
                ];
            }
        }

        // Priority C: Existing authoritative GI connection
        if ($sourceAnchor) {
            $giCode = strtoupper($sourceAnchor['kode'] ?? '');
            foreach ($compAssetIds as $aId) {
                $asset = $nodeMap[$aId];
                $code = strtoupper($asset['kode_asset'] ?? '');
                $name = strtoupper($asset['nama_asset'] ?? '');
                if (($giCode !== '' && (str_contains($code, $giCode) || str_contains($name, $giCode))) ||
                    str_contains($code, 'GI_') || str_contains($name, 'GARDU INDUK')) {
                    return [
                        'status'        => 'RESOLVED',
                        'method'        => 'AUTHORITATIVE_GI_CONNECTION',
                        'root_asset_id' => $aId,
                        'root_asset_code' => $asset['kode_asset'],
                        'evidence'      => "Asset #{$aId} ({$asset['kode_asset']}) explicitly connects to substation {$sourceAnchor['nama']}",
                        'is_authoritative' => true,
                    ];
                }
            }
        }

        // Diagnostic Hint (Lowest sequence index) - NOT AUTHORITATIVE
        $minSeq = PHP_INT_MAX;
        $candidateSeqId = null;
        foreach ($compAssetIds as $aId) {
            $asset = $nodeMap[$aId];
            if (preg_match('/[_\-\s]+(\d+)$/', $asset['kode_asset'], $m)) {
                $seq = (int)$m[1];
                if ($seq < $minSeq) {
                    $minSeq = $seq;
                    $candidateSeqId = $aId;
                }
            }
        }

        return [
            'status'             => 'ROOT_UNRESOLVED',
            'method'             => 'NONE',
            'root_asset_id'      => null,
            'root_asset_code'    => null,
            'diagnostic_hint'    => $candidateSeqId ? "Lowest index pole detected: #{$candidateSeqId} ({$nodeMap[$candidateSeqId]['kode_asset']}), but lacks explicit upstream anchor evidence" : null,
            'evidence'           => 'No authoritative upstream takeoff, recloser anchor, or substation connection found in database for this component.',
            'is_authoritative'   => false,
        ];
    }

    /**
     * Weighted Graph Traversal using Dijkstra's Algorithm
     */
    public function buildWeightedDistanceChains(
        int $componentId,
        int $rootAssetId,
        array $nodeMap,
        array $compEdgeMap,
        array $adj
    ): array {
        $dist = [];
        $prevNode = [];
        $prevEdge = [];
        $visited = [];

        foreach (array_keys($nodeMap) as $id) {
            $dist[$id] = INF;
            $prevNode[$id] = null;
            $prevEdge[$id] = null;
        }

        $dist[$rootAssetId] = 0.0;
        $priorityQueue = new \SplPriorityQueue();
        $priorityQueue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        $priorityQueue->insert($rootAssetId, 0.0);

        $edgeLookup = [];
        foreach ($compEdgeMap as $e) {
            $key = min($e['source_asset_id'], $e['target_asset_id']) . ':' . max($e['source_asset_id'], $e['target_asset_id']);
            $edgeLookup[$key] = $e;
        }

        while (!$priorityQueue->isEmpty()) {
            $extracted = $priorityQueue->extract();
            $u = $extracted['data'];

            if (isset($visited[$u])) continue;
            $visited[$u] = true;

            if (!isset($adj[$u])) continue;

            foreach ($adj[$u] as $v) {
                $pairKey = min($u, $v) . ':' . max($u, $v);
                if (!isset($edgeLookup[$pairKey])) continue;

                $edge = $edgeLookup[$pairKey];
                $weight = (float)$edge['distance_meters'];

                if ($dist[$u] + $weight < $dist[$v]) {
                    $dist[$v] = $dist[$u] + $weight;
                    $prevNode[$v] = $u;
                    $prevEdge[$v] = $edge;
                    $priorityQueue->insert($v, -$dist[$v]);
                }
            }
        }

        $isParentInTree = [];
        $reachableNodes = [];
        foreach ($dist as $nodeId => $d) {
            if ($d !== INF) {
                $reachableNodes[$nodeId] = true;
                if ($prevNode[$nodeId] !== null) {
                    $isParentInTree[$prevNode[$nodeId]] = true;
                }
            }
        }

        $leafNodes = [];
        foreach (array_keys($reachableNodes) as $nodeId) {
            if (!isset($isParentInTree[$nodeId])) {
                $leafNodes[] = $nodeId;
            }
        }

        // Canonical deterministic sorting of leaf nodes by code ASC then id ASC
        usort($leafNodes, function($a, $b) use ($nodeMap) {
            $codeA = $nodeMap[$a]['kode_asset'] ?? '';
            $codeB = $nodeMap[$b]['kode_asset'] ?? '';
            $cmp = strcmp($codeA, $codeB);
            return $cmp !== 0 ? $cmp : ($a <=> $b);
        });

        $paths = [];

        foreach ($leafNodes as $leafId) {
            $curr = $leafId;
            $chainEdges = [];

            while ($curr !== null && $curr !== $rootAssetId) {
                $p = $prevNode[$curr];
                $e = $prevEdge[$curr];
                if ($e !== null) {
                    $chainEdges[] = [
                        'from_node' => $p,
                        'to_node'   => $curr,
                        'edge'      => $e,
                    ];
                }
                $curr = $p;
            }

            $chainEdges = array_reverse($chainEdges);
            if (empty($chainEdges)) continue;

            $segments = [];
            foreach ($chainEdges as $item) {
                $uNode = $item['from_node'];
                $vNode = $item['to_node'];
                $edge = $item['edge'];

                $startDist = round((float)$dist[$uNode], 2);
                $endDist   = round((float)$dist[$vNode], 2);
                $segLen    = round((float)$edge['distance_meters'], 2);

                $segments[] = [
                    'edge_id'             => (int)$edge['id'],
                    'transline_code'      => $edge['transline_code'],
                    'upstream_asset_id'   => $uNode,
                    'downstream_asset_id' => $vNode,
                    'upstream_code'       => $nodeMap[$uNode]['kode_asset'] ?? "AST-{$uNode}",
                    'downstream_code'     => $nodeMap[$vNode]['kode_asset'] ?? "AST-{$vNode}",
                    'segment_length_m'    => $segLen,
                    'start_distance_m'    => $startDist,
                    'end_distance_m'      => $endDist,
                    'cumulative_km_start' => round($startDist / 1000.0, 4),
                    'cumulative_km_end'   => round($endDist / 1000.0, 4),
                    'geometry'            => $edge['geometry'] ?? null,
                    'parsed_vertices'     => $edge['parsed_vertices'] ?? [],
                ];
            }

            $leafCode = $nodeMap[$leafId]['kode_asset'] ?? "AST-{$leafId}";
            $rootCode = $nodeMap[$rootAssetId]['kode_asset'] ?? "AST-{$rootAssetId}";
            $pathId = "PATH_C{$componentId}_R{$rootAssetId}_T{$leafId}";

            $paths[] = [
                'path_id'             => $pathId,
                'component_id'        => $componentId,
                'root_asset_id'       => $rootAssetId,
                'leaf_asset_id'       => $leafId,
                'root_asset_code'     => $rootCode,
                'leaf_asset_code'     => $leafCode,
                'total_distance_m'    => round($dist[$leafId], 2),
                'total_distance_km'   => round($dist[$leafId] / 1000.0, 4),
                'segments_count'      => count($segments),
                'segments'            => $segments,
            ];
        }

        // Canonical deterministic sort of paths by leaf_asset_code ASC then path_id ASC
        usort($paths, function($a, $b) {
            $cmp = strcmp($a['leaf_asset_code'], $b['leaf_asset_code']);
            return $cmp !== 0 ? $cmp : strcmp($a['path_id'], $b['path_id']);
        });

        return $paths;
    }

    public function parseLineStringWkt(?string $wkt): ?array
    {
        if (empty($wkt)) return null;

        if (!preg_match('/LINESTRING\s*\((.+)\)/i', trim($wkt), $m)) {
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

        return count($vertices) >= 2 ? $vertices : null;
    }

    public function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (abs($lat1) < 0.00001 && abs($lon1) < 0.00001) return 0.0;
        if (abs($lat2) < 0.00001 && abs($lon2) < 0.00001) return 0.0;

        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;

        return round(2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    protected function classifyNodeRole(int $degree): string
    {
        if ($degree === 0) return 'ISOLATED_NODE';
        if ($degree === 1) return 'TERMINAL_NODE';
        if ($degree === 2) return 'INTERMEDIATE_NODE';
        return 'BRANCH_NODE';
    }

    protected function loadFeeder(int $penyulangId): ?array
    {
        if (!$this->db->tableExists('penyulang')) return null;

        $builder = $this->db->table('penyulang')->where('id', $penyulangId);
        return $builder->get()->getRowArray();
    }

    protected function resolveSourceAnchor(array $feeder): ?array
    {
        $fName = strtoupper($feeder['nama_penyulang'] ?? '');
        $giId = (int)($feeder['gi_id'] ?? 0);

        if ($giId > 0 && isset($this->substationMaster[$giId])) {
            return $this->substationMaster[$giId];
        }

        if (str_contains($fName, 'BUDURAN') || str_contains($fName, 'SIWALAN') ||
            str_contains($fName, 'GADING') || str_contains($fName, 'GEDANGAN') ||
            str_contains($fName, 'GEMURUNG') || str_contains($fName, 'ECCO')) {
            return $this->substationMaster[1];
        }

        return $this->substationMaster[1] ?? null;
    }

    protected function loadAssets(int $penyulangId): array
    {
        if (!$this->db->tableExists('assets')) return [];

        $builder = $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, jenis_asset, ulp_id, penyulang_id, section_id, latitude, longitude, status')
            ->where('penyulang_id', $penyulangId)
            ->orderBy('id', 'ASC');

        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }

        return $builder->get()->getResultArray();
    }

    protected function loadTranslines(int $penyulangId): array
    {
        if (!$this->db->tableExists('gis_translines')) return [];

        $builder = $this->db->table('gis_translines')
            ->where('penyulang_id', $penyulangId)
            ->orderBy('id', 'ASC');

        if ($this->db->fieldExists('deleted_at', 'gis_translines')) {
            $builder->where('deleted_at IS NULL');
        }

        return $builder->get()->getResultArray();
    }

    protected function buildEmptyModel(int $penyulangId, string $status): array
    {
        $model = [
            'adapter_version'  => 'FL-01A-1.0',
            'status'           => $status,
            'feeder_id'        => $penyulangId,
            'feeder_code'      => '-',
            'feeder_name'      => '-',
            'ulp_id'           => 0,
            'source_anchor'    => null,
            'integrity_audit'  => [
                'is_valid'             => false,
                'total_edges_examined' => 0,
                'valid_edges_count'    => 0,
                'anomalies_count'      => 0,
                'anomalies'            => [],
            ],
            'summary' => [
                'total_assets'     => 0,
                'total_edges'      => 0,
                'components_count' => 0,
                'isolated_assets'  => 0,
                'terminal_assets'  => 0,
                'branch_assets'    => 0,
            ],
            'assets'           => [],
            'edges'            => [],
            'components'       => [],
            'distance_paths'   => [],
        ];

        $model['model_fingerprint'] = $this->calculateModelFingerprint($model);
        $model['generated_at']      = date('c');

        return $model;
    }

    /**
     * Compute Deterministic SHA-256 Fingerprint of Feeder Topology Read Model.
     * Strictly excludes non-deterministic metadata like generated_at.
     */
    public function calculateModelFingerprint(array $model): string
    {
        $canonical = [
            'adapter_version' => $model['adapter_version'] ?? 'FL-01A-1.0',
            'feeder_id'       => (int)($model['feeder_id'] ?? 0),
            'feeder_code'     => (string)($model['feeder_code'] ?? ''),
            'status'          => (string)($model['status'] ?? ''),
            'source_anchor'   => $model['source_anchor'] ?? null,
            'integrity_audit' => [
                'is_valid'             => (bool)($model['integrity_audit']['is_valid'] ?? false),
                'total_edges_examined' => (int)($model['integrity_audit']['total_edges_examined'] ?? 0),
                'valid_edges_count'    => (int)($model['integrity_audit']['valid_edges_count'] ?? 0),
                'anomalies_count'      => (int)($model['integrity_audit']['anomalies_count'] ?? 0),
                'anomalies'            => $model['integrity_audit']['anomalies'] ?? [],
            ],
            'summary'         => $model['summary'] ?? [],
            'assets_digest'   => array_map(function($a) {
                return [
                    'id'         => (int)$a['id'],
                    'kode_asset' => (string)($a['kode_asset'] ?? ''),
                    'degree'     => (int)($a['degree'] ?? 0),
                    'role'       => (string)($a['role'] ?? ''),
                ];
            }, $model['assets'] ?? []),
            'edges_digest'    => array_map(function($e) {
                return [
                    'id'              => (int)$e['id'],
                    'source_asset_id' => (int)$e['source_asset_id'],
                    'target_asset_id' => (int)$e['target_asset_id'],
                    'distance_meters' => (float)$e['distance_meters'],
                ];
            }, $model['edges'] ?? []),
            'components_digest' => array_map(function($c) {
                return [
                    'component_id'        => (int)$c['component_id'],
                    'asset_count'         => (int)$c['asset_count'],
                    'edge_count'          => (int)$c['edge_count'],
                    'total_length_meters' => (float)$c['total_length_meters'],
                    'is_main_trunk'       => (bool)$c['is_main_trunk'],
                    'root_resolution'     => [
                        'status'        => (string)($c['root_resolution']['status'] ?? ''),
                        'method'        => (string)($c['root_resolution']['method'] ?? ''),
                        'root_asset_id' => $c['root_resolution']['root_asset_id'] ?? null,
                    ],
                    'path_ids'            => array_column($c['distance_paths'] ?? [], 'path_id'),
                ];
            }, $model['components'] ?? []),
            'distance_paths_digest' => array_map(function($p) {
                return [
                    'path_id'          => (string)$p['path_id'],
                    'root_asset_id'    => (int)$p['root_asset_id'],
                    'leaf_asset_id'    => (int)$p['leaf_asset_id'],
                    'total_distance_m' => (float)$p['total_distance_m'],
                    'segments_count'   => (int)$p['segments_count'],
                ];
            }, $model['distance_paths'] ?? []),
        ];

        usort($canonical['assets_digest'], fn($a, $b) => $a['id'] <=> $b['id']);
        usort($canonical['edges_digest'], fn($a, $b) => $a['id'] <=> $b['id']);
        usort($canonical['components_digest'], fn($a, $b) => $a['component_id'] <=> $b['component_id']);
        usort($canonical['distance_paths_digest'], fn($a, $b) => strcmp($a['path_id'], $b['path_id']));

        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $json);
    }
}
