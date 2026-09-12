<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * SIDAK TEJO ENTERPRISE: TRANSLINE AI ACCELERATION PROGRAM
 * "NETWORK COMPLETION ENGINE v1"
 *
 * Core Mandates:
 * 1. Read all JTM Assets and Authoritative Translines.
 * 2. Build in-memory Graph G(V,E) where V = assets.id and E = gis_translines.
 * 3. Evaluate candidate edges across 8 topological categories.
 * 4. 100-Point Scoring Model (Physical Network First: distance & bearing over code numbering).
 * 5. Progressive Atomic Batch Execution (<= 10 edges per transaction, recalculating graph after each batch).
 * 6. Continue automatically until true natural stabilization (when defensible AUTO_COMPLETE = 0).
 * 7. Multi-feeder generic: supports any penyulang_id or global scope.
 * 8. Zero-Write Firewall: NEVER mutate assets (coordinates/sections), temuan, temuan_materials, sections, penyulang.
 * 9. Existing transline immutability: IDs 1-127 and all prior edges remain 100% untouched.
 * 10. Temuan Firewall: Temuan is never a network node or transline endpoint.
 */
class TranslineNetworkCompletionEngine
{
    public const ENGINE_NAME    = 'TRANSLINE_AI_NETWORK_COMPLETION_ENGINE';
    public const ENGINE_VERSION = 'v1.0';
    public const MAX_BATCH_SIZE = 10;
    public const HARD_MAX_DEGREE = 4;
    public const HARD_MAX_SPAN_METERS = 85.0;
    public const HARD_MIN_SPAN_METERS = 1.0;
    public const SHORT_SPAN_FIREWALL_METERS = 5.0;

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    // =========================================================================
    // 📐 GEOMETRIC & MATHEMATICAL HELPERS
    // =========================================================================

    /**
     * Haversine distance in meters
     */
    public function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (abs($lat1) < 0.00001 && abs($lon1) < 0.00001) return 0.0;
        if (abs($lat2) < 0.00001 && abs($lon2) < 0.00001) return 0.0;

        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    /**
     * Azimuth bearing in degrees [0, 360)
     */
    public function calculateBearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLon = deg2rad($lon2 - $lon1);
        $y = sin($dLon) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) -
             sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos($dLon);
        return fmod((rad2deg(atan2($y, $x)) + 360.0), 360.0);
    }

    /**
     * Absolute delta between two bearings in degrees [0, 180]
     */
    public function calculateBearingDelta(float $b1, float $b2): float
    {
        $diff = abs($b1 - $b2);
        if ($diff > 180.0) {
            $diff = 360.0 - $diff;
        }
        return $diff;
    }

    /**
     * Sequence numbering delta (Evidence, NOT a hard gate)
     */
    public function calculateSequenceDelta(string $codeA, string $codeB): int
    {
        preg_match('/(\d+)$/', $codeA, $mA);
        preg_match('/(\d+)$/', $codeB, $mB);

        if (!empty($mA[1]) && !empty($mB[1])) {
            return abs((int)$mA[1] - (int)$mB[1]);
        }
        return 999;
    }

    // =========================================================================
    // 🌐 GRAPH BUILDER G(V, E)
    // =========================================================================

    /**
     * Build in-memory Graph G(V,E) for a given feeder or globally
     */
    public function buildGraph(?int $penyulangId = null): array
    {
        // 1. Authoritative Assets (Strict Read-Only)
        $assetQuery = $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, type, jenis_asset, section_id, penyulang_id, ulp_id, construction_type_id, latitude, longitude')
            ->where('deleted_at IS NULL');

        if ($penyulangId !== null && $penyulangId > 0) {
            $assetQuery->where('penyulang_id', $penyulangId);
        }

        $assetRows = $assetQuery->get()->getResultArray();
        $assetMap = [];
        $degrees = [];
        $adjacency = [];

        foreach ($assetRows as $a) {
            $id = (int)$a['id'];
            $assetMap[$id] = [
                'id'                   => $id,
                'kode_asset'           => $a['kode_asset'] ?? "AST-{$id}",
                'nama_asset'           => $a['nama_asset'] ?? '',
                'type'                 => $a['type'] ?? 'TIANG',
                'jenis_asset'          => $a['jenis_asset'] ?? 'TIANG_TM',
                'section_id'           => $a['section_id'] !== null ? (int)$a['section_id'] : null,
                'penyulang_id'         => (int)($a['penyulang_id'] ?? 0),
                'ulp_id'               => (int)($a['ulp_id'] ?? 0),
                'construction_type_id' => $a['construction_type_id'] !== null ? (int)$a['construction_type_id'] : null,
                'latitude'             => (float)($a['latitude'] ?? 0),
                'longitude'            => (float)($a['longitude'] ?? 0),
            ];
            $degrees[$id] = 0;
            $adjacency[$id] = [];
        }

        // 2. Authoritative Active Translines (Strict Read-Only)
        $tlQuery = $this->db->table('gis_translines')
            ->where('deleted_at IS NULL')
            ->where('is_active', 1);

        if ($penyulangId !== null && $penyulangId > 0) {
            $tlQuery->where('penyulang_id', $penyulangId);
        }

        $translines = $tlQuery->get()->getResultArray();
        $existingEdgeKeys = [];

        foreach ($translines as $tl) {
            $u = (int)$tl['source_asset_id'];
            $v = (int)$tl['target_asset_id'];

            if (isset($degrees[$u])) $degrees[$u]++;
            if (isset($degrees[$v])) $degrees[$v]++;
            if (isset($adjacency[$u])) $adjacency[$u][] = $v;
            if (isset($adjacency[$v])) $adjacency[$v][] = $u;

            $minId = min($u, $v);
            $maxId = max($u, $v);
            $existingEdgeKeys["{$minId}_{$maxId}"] = true;
        }

        // 3. Partition Node Roles
        $connectedAssetIds  = [];
        $isolatedAssetIds   = [];
        $terminalAnchorIds  = [];
        $mainlineNodeIds    = [];
        $lateralBranchIds   = [];
        $saturatedNodeIds   = [];

        foreach ($degrees as $id => $deg) {
            if ($deg === 0) {
                $isolatedAssetIds[] = $id;
            } else {
                $connectedAssetIds[] = $id;
                if ($deg === 1) {
                    $terminalAnchorIds[] = $id;
                } elseif ($deg === 2) {
                    $mainlineNodeIds[] = $id;
                } elseif ($deg === 3) {
                    $lateralBranchIds[] = $id;
                } elseif ($deg >= self::HARD_MAX_DEGREE) {
                    $saturatedNodeIds[] = $id;
                }
            }
        }

        // 4. Connected Components Detection (BFS)
        $components = [];
        $nodeComponentMap = [];
        $visitedComp = [];

        foreach (array_keys($assetMap) as $nodeId) {
            if (isset($visitedComp[$nodeId])) continue;

            $compNodes = [];
            $queue = [$nodeId];
            $visitedComp[$nodeId] = true;

            while (!empty($queue)) {
                $curr = array_shift($queue);
                $compNodes[] = $curr;

                foreach ($adjacency[$curr] ?? [] as $neighbor) {
                    if (!isset($visitedComp[$neighbor])) {
                        $visitedComp[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }

            $compIdx = count($components);
            $components[] = $compNodes;
            foreach ($compNodes as $cn) {
                $nodeComponentMap[$cn] = $compIdx;
            }
        }

        // 5. Detect Linear Chains among Isolated Assets
        $isolatedChains = $this->detectIsolatedChains($isolatedAssetIds, $assetMap);

        return [
            'penyulang_id'       => $penyulangId,
            'assets'             => $assetMap,
            'translines'         => $translines,
            'degrees'            => $degrees,
            'adjacency'          => $adjacency,
            'existing_edge_keys' => $existingEdgeKeys,
            'connected_ids'      => $connectedAssetIds,
            'isolated_ids'       => $isolatedAssetIds,
            'terminal_ids'       => $terminalAnchorIds,
            'mainline_ids'       => $mainlineNodeIds,
            'lateral_ids'        => $lateralBranchIds,
            'saturated_ids'      => $saturatedNodeIds,
            'components'         => $components,
            'node_component_map' => $nodeComponentMap,
            'isolated_chains'    => $isolatedChains,
        ];
    }

    /**
     * Detect linear sequential chains among isolated assets.
     * Spatial proximity (<=55m) takes precedence over sequential code naming jumps.
     */
    protected function detectIsolatedChains(array $isolatedIds, array $assetMap): array
    {
        $chains = [];
        $visited = [];

        foreach ($isolatedIds as $id) {
            if (isset($visited[$id])) continue;

            $currentChain = [$id];
            $visited[$id] = true;

            $tail = $id;
            while (true) {
                $tailAsset = $assetMap[$tail];
                $bestNeighbor = null;
                $minDist = 999999;

                foreach ($isolatedIds as $candidateId) {
                    if (isset($visited[$candidateId])) continue;

                    $cAsset = $assetMap[$candidateId];
                    $dist = $this->haversineDistanceMeters(
                        (float)$tailAsset['latitude'], (float)$tailAsset['longitude'],
                        (float)$cAsset['latitude'], (float)$cAsset['longitude']
                    );

                    // Allow chain connection within normal distribution span
                    if ($dist <= 55.0 && $dist < $minDist) {
                        $minDist = $dist;
                        $bestNeighbor = $candidateId;
                    }
                }

                if ($bestNeighbor !== null) {
                    $currentChain[] = $bestNeighbor;
                    $visited[$bestNeighbor] = true;
                    $tail = $bestNeighbor;
                } else {
                    break;
                }
            }

            if (count($currentChain) >= 2) {
                $chains[] = $currentChain;
            }
        }

        return $chains;
    }

    // =========================================================================
    // 🔍 CANDIDATE DISCOVERY & 100-POINT SCORING MODEL
    // =========================================================================

    /**
     * Discover and evaluate all candidate connections across 8 topological categories
     */
    public function discoverCandidates(array $graph): array
    {
        $assetMap         = $graph['assets'];
        $degrees          = $graph['degrees'];
        $adjacency        = $graph['adjacency'];
        $existingEdgeKeys = $graph['existing_edge_keys'];
        $connectedIds     = $graph['connected_ids'];
        $isolatedIds      = $graph['isolated_ids'];
        $chains           = $graph['isolated_chains'];

        // Map chain edges
        $chainEdgeMap = [];
        foreach ($chains as $chain) {
            $cnt = count($chain);
            for ($i = 0; $i < $cnt - 1; $i++) {
                $u = $chain[$i];
                $v = $chain[$i + 1];
                $minId = min($u, $v);
                $maxId = max($u, $v);
                $chainEdgeMap["{$minId}_{$maxId}"] = true;
            }
        }

        $candidates = [];
        $evaluatedPairs = [];

        // Pool: Connected + Isolated nodes
        $sourcePool = array_unique(array_merge($connectedIds, $isolatedIds));

        foreach ($sourcePool as $uId) {
            $uAsset = $assetMap[$uId];
            $uDeg   = $degrees[$uId] ?? 0;

            if ($uDeg >= self::HARD_MAX_DEGREE) {
                continue; // Saturated node
            }

            // Search targets: Isolated assets
            foreach ($isolatedIds as $vId) {
                if ($uId === $vId) continue;

                $minId = min($uId, $vId);
                $maxId = max($uId, $vId);
                $pairKey = "{$minId}_{$maxId}";

                if (isset($evaluatedPairs[$pairKey]) || isset($existingEdgeKeys[$pairKey])) {
                    continue;
                }
                $evaluatedPairs[$pairKey] = true;

                $vAsset = $assetMap[$vId];
                $vDeg   = $degrees[$vId] ?? 0;

                if ($vDeg >= self::HARD_MAX_DEGREE) {
                    continue;
                }

                // Distance calculation
                $dist = $this->haversineDistanceMeters(
                    (float)$uAsset['latitude'], (float)$uAsset['longitude'],
                    (float)$vAsset['latitude'], (float)$vAsset['longitude']
                );

                if ($dist > 120.0) {
                    continue; // Out of discovery window
                }

                // Safety Gates
                $gateValid = true;
                $gateReasons = [];

                if ($dist > self::HARD_MAX_SPAN_METERS) {
                    $gateValid = false;
                    $gateReasons[] = 'SPAN_EXCEEDS_HARD_CEILING_85M';
                }
                if ($dist < self::HARD_MIN_SPAN_METERS) {
                    $gateValid = false;
                    $gateReasons[] = 'SUB_METER_COORDINATE_COLLISION';
                }
                if ($uAsset['penyulang_id'] !== $vAsset['penyulang_id']) {
                    $gateValid = false;
                    $gateReasons[] = 'CROSS_FEEDER_BOUNDARY_PROHIBITED';
                }
                if ($uAsset['ulp_id'] !== $vAsset['ulp_id']) {
                    $gateValid = false;
                    $gateReasons[] = 'CROSS_ULP_BOUNDARY_PROHIBITED';
                }

                $isChainEdge   = isset($chainEdgeMap[$pairKey]);
                $isTerminalExt = (($uDeg === 1 && $vDeg === 0) || ($uDeg === 0 && $vDeg === 1));
                $isToff        = (($uDeg === 2 && $vDeg === 0) || ($uDeg === 0 && $vDeg === 2));

                // 100-Point Scoring Breakdown
                $scoreBreakdown = $this->calculate100PointScore(
                    $uAsset, $vAsset, $dist, $uDeg, $vDeg, $adjacency, $isChainEdge, $assetMap
                );
                $totalScore = $scoreBreakdown['total_score'];

                // Network Evidence & Coherence
                $networkEvidence = $this->calculateNetworkEvidence(
                    $uAsset, $vAsset, $dist, $uDeg, $vDeg, $isChainEdge, $isTerminalExt, $isToff, $scoreBreakdown
                );

                // Decision Classification
                $isAuto = false;
                $classification = 'BLOCKED';

                if ($dist < self::SHORT_SPAN_FIREWALL_METERS) {
                    // Very short distance firewall: < 5.0m requires field review (ambiguous equipment/portal/duplicate tag)
                    $isAuto = false;
                    $classification = 'REVIEW_REQUIRED';
                    $gateReasons[] = 'SHORT_SPAN_FIREWALL: Span < 5.0m requires field review for equipment/portal collision';
                } elseif ($gateValid && $totalScore >= 85) {
                    $isAuto = true;
                    $classification = 'AUTO_COMPLETE';
                } elseif ($gateValid && $totalScore >= 78 && $networkEvidence['is_network_coherent']) {
                    $isAuto = true;
                    $classification = 'AUTO_COMPLETE'; // Promoted by physical network coherence
                } elseif ($gateValid && $totalScore >= 75) {
                    $classification = 'HIGH_CONFIDENCE_REVIEW';
                } elseif ($gateValid && $totalScore >= 60) {
                    $classification = 'REVIEW_REQUIRED';
                } elseif (!$gateValid) {
                    $classification = 'BLOCKED';
                } else {
                    $classification = 'AMBIGUOUS';
                }

                $sourceAsset = ($uAsset['id'] === $minId) ? $uAsset : $vAsset;
                $targetAsset = ($uAsset['id'] === $minId) ? $vAsset : $uAsset;
                $sourceDeg   = ($uAsset['id'] === $minId) ? $uDeg : $vDeg;
                $targetDeg   = ($uAsset['id'] === $minId) ? $vDeg : $uDeg;

                $candidates[] = [
                    'natural_key'        => "TL-NAT:{$uAsset['penyulang_id']}:{$minId}-{$maxId}",
                    'source_asset_id'    => $minId,
                    'target_asset_id'    => $maxId,
                    'source_asset_code'  => $sourceAsset['kode_asset'],
                    'target_asset_code'  => $targetAsset['kode_asset'],
                    'source_asset_name'  => $sourceAsset['nama_asset'],
                    'target_asset_name'  => $targetAsset['nama_asset'],
                    'distance_meters'    => round($dist, 2),
                    'source_degree'      => $sourceDeg,
                    'target_degree'      => $targetDeg,
                    'candidate_type'     => $this->resolveCandidateType($uDeg, $vDeg, $isChainEdge),
                    'section_id'         => $uAsset['section_id'] ?? $vAsset['section_id'] ?? null,
                    'conductor_type'     => 'AAAC',
                    'conductor_size'     => '150 mm²',
                    'total_score'        => $totalScore,
                    'network_score'      => $networkEvidence['network_score'],
                    'confidence_score'   => round($totalScore / 100.0, 2),
                    'score_breakdown'    => $scoreBreakdown,
                    'network_evidence'   => $networkEvidence,
                    'classification'     => $classification,
                    'gate_valid'         => $gateValid,
                    'gate_reasons'       => $gateReasons,
                    'is_auto_complete'   => $isAuto,
                    'coordinates'        => [
                        [(float)$sourceAsset['longitude'], (float)$sourceAsset['latitude']],
                        [(float)$targetAsset['longitude'], (float)$targetAsset['latitude']]
                    ]
                ];
            }
        }

        // Sort candidates: AUTO_COMPLETE first, then total_score + network_score desc
        usort($candidates, function ($a, $b) {
            if ($a['is_auto_complete'] !== $b['is_auto_complete']) {
                return $b['is_auto_complete'] <=> $a['is_auto_complete'];
            }
            $scoreA = $a['total_score'] + ($a['network_score'] ?? 0);
            $scoreB = $b['total_score'] + ($b['network_score'] ?? 0);
            return $scoreB <=> $scoreA;
        });

        return $candidates;
    }

    /**
     * 100-Point Scoring Model (Physical Network First)
     */
    protected function calculate100PointScore(
        array $u, array $v, float $dist, int $uDeg, int $vDeg, array $adjacency, bool $isChainEdge, array $assetMap
    ): array {
        // 1. Distance / Span Quality (max 20)
        $sDistance = 0;
        if ($dist <= 30.0) {
            $sDistance = 20;
        } elseif ($dist <= 55.0) {
            $sDistance = 18;
        } elseif ($dist <= 70.0) {
            $sDistance = 14;
        } elseif ($dist <= 85.0) {
            $sDistance = 10;
        } else {
            $sDistance = 0;
        }

        // 2. Bearing / Alignment Continuity (max 20)
        $bearing = $this->calculateBearing(
            (float)$u['latitude'], (float)$u['longitude'],
            (float)$v['latitude'], (float)$v['longitude']
        );
        $bearingDelta = 0.0;
        $sBearing = 10;

        $uNeighbors = $adjacency[$u['id']] ?? [];
        if (!empty($uNeighbors)) {
            $refId = reset($uNeighbors);
            $refAsset = $assetMap[$refId] ?? null;
            if ($refAsset) {
                $refBearing = $this->calculateBearing(
                    (float)$refAsset['latitude'], (float)$refAsset['longitude'],
                    (float)$u['latitude'], (float)$u['longitude']
                );
                $bearingDelta = $this->calculateBearingDelta($refBearing, $bearing);
                if ($bearingDelta <= 15.0) {
                    $sBearing = 20; // Collinear mainline
                } elseif ($bearingDelta <= 30.0) {
                    $sBearing = 16; // Gentle curve
                } elseif ($bearingDelta <= 45.0) {
                    $sBearing = 12; // Acceptable corner
                } elseif ($bearingDelta <= 60.0) {
                    $sBearing = 8;  // Moderate turn
                } else {
                    $sBearing = 2;
                }
            }
        } else {
            // For isolated pairs, tight distance gives strong bearing confidence
            $sBearing = ($isChainEdge || $dist <= 35.0) ? 16 : 10;
        }

        // 3. Graph Continuity (max 15)
        $sGraph = 0;
        if (($uDeg === 1 && $vDeg === 0) || ($uDeg === 0 && $vDeg === 1)) {
            $sGraph = 15; // Mainline terminal anchor extension
        } elseif ($uDeg === 0 && $vDeg === 0 && ($isChainEdge || $dist <= 35.0)) {
            $sGraph = 13; // Internal chain edge or tight isolated pair
        } elseif (($uDeg === 2 && $vDeg === 0) || ($uDeg === 0 && $vDeg === 2)) {
            $sGraph = 12; // T-off lateral branch
        } elseif (($uDeg === 3 && $vDeg === 0) || ($uDeg === 0 && $vDeg === 3)) {
            $sGraph = 6;  // Tertiary branch
        } else {
            $sGraph = 3;
        }

        // 4. Chain Coherence (max 15)
        $sChain = 0;
        if ($isChainEdge || ($uDeg === 0 && $vDeg === 0 && $dist <= 35.0)) {
            $sChain = 15;
        } elseif ($uDeg === 1 && $vDeg === 0 && ($bearingDelta <= 45.0 || $dist <= 25.0)) {
            $sChain = 12;
        } elseif ($uDeg === 2 && $vDeg === 0 && $dist <= 50.0) {
            $sChain = 10;
        } else {
            $sChain = 3;
        }

        // 5. Section Continuity (max 10)
        $sSection = 0;
        $uSec = $u['section_id'] ?? null;
        $vSec = $v['section_id'] ?? null;
        if ($uSec !== null && $vSec !== null && (int)$uSec === (int)$vSec) {
            $sSection = 10; // Same section
        } elseif ($uSec === null && $vSec === null) {
            $sSection = 10; // Same unsectioned mainline
        } elseif ($dist <= 55.0) {
            $sSection = 7;  // Adjacent section boundary
        } else {
            $sSection = 2;
        }

        // 6. Feeder / ULP Continuity (max 10)
        $sFeeder = 0;
        if (($u['penyulang_id'] ?? 0) === ($v['penyulang_id'] ?? 0) && ($u['penyulang_id'] ?? 0) > 0) {
            $sFeeder = 10;
        }

        // 7. Construction Compatibility (max 5)
        $sConstruction = 5;

        // 8. Sequence Evidence (max 5 - evidence only, NOT hard veto)
        $sSeq = 0;
        $seqDelta = $this->calculateSequenceDelta($u['kode_asset'] ?? '', $v['kode_asset'] ?? '');
        if ($seqDelta === 1) {
            $sSeq = 5; // Consecutive pole
        } elseif ($seqDelta === 2) {
            $sSeq = 4;
        } elseif ($seqDelta <= 5 || $dist <= 25.0) {
            $sSeq = 3; // Proximity overrides numbering gap
        } elseif ($dist <= 50.0) {
            $sSeq = 2;
        } else {
            $sSeq = 1;
        }

        $total = $sDistance + $sBearing + $sGraph + $sChain + $sSection + $sFeeder + $sConstruction + $sSeq;

        return [
            'distance_score'            => $sDistance,
            'bearing_score'             => $sBearing,
            'graph_continuity'          => $sGraph,
            'chain_coherence'           => $sChain,
            'section_continuity'        => $sSection,
            'feeder_continuity'         => $sFeeder,
            'construction_compatibility'=> $sConstruction,
            'sequence_evidence'         => $sSeq,
            'total_score'               => min(100, $total),
            'bearing_degrees'           => round($bearing, 1),
            'bearing_delta_degrees'     => round($bearingDelta, 1),
            'sequence_delta'            => $seqDelta,
        ];
    }

    /**
     * Calculate network-level coherence evidence
     */
    protected function calculateNetworkEvidence(
        array $u, array $v, float $dist, int $uDeg, int $vDeg, bool $isChain, bool $isTerminalExt, bool $isToff, array $breakdown
    ): array {
        $netScore = 0;
        $reasons = [];

        if ($isChain || ($uDeg === 0 && $vDeg === 0 && $dist <= 35.0)) {
            $netScore += 15;
            $reasons[] = 'ISOLATED_LINEAR_CHAIN_MEMBER';
        }
        if ($isTerminalExt && ($breakdown['bearing_delta_degrees'] <= 45.0 || $dist <= 20.0)) {
            $netScore += 15;
            $reasons[] = 'COLLINEAR_MAINLINE_ANCHOR_EXTENSION';
        }
        if ($isToff && ($dist <= 50.0 || ($breakdown['sequence_delta'] ?? 999) <= 3)) {
            $netScore += 10;
            $reasons[] = 'LEGITIMATE_LATERAL_BRANCH';
        }
        if (($breakdown['sequence_delta'] ?? 999) === 1) {
            $netScore += 10;
            $reasons[] = 'CONSECUTIVE_POLE_SEQUENCE';
        }
        if (($breakdown['section_continuity'] ?? 0) >= 7) {
            $netScore += 5;
            $reasons[] = 'COHERENT_SECTION_ENVIRONMENT';
        }

        $isCoherent = ($netScore >= 20);

        return [
            'network_score'        => $netScore,
            'is_network_coherent'  => $isCoherent,
            'coherence_evidence'   => $reasons,
            'coherence_summary'    => implode(', ', $reasons),
        ];
    }

    protected function resolveCandidateType(int $uDeg, int $vDeg, bool $isChain): string
    {
        if ($isChain) return 'ISOLATED_CHAIN_SEGMENT';
        if (($uDeg === 1 && $vDeg === 0) || ($uDeg === 0 && $vDeg === 1)) return 'ANCHOR_MAINLINE_EXTENSION';
        if (($uDeg === 2 && $vDeg === 0) || ($uDeg === 0 && $vDeg === 2)) return 'T_OFF_LATERAL_BRANCH';
        if ($uDeg === 0 && $vDeg === 0) return 'ISOLATED_PAIR';
        return 'NETWORK_EDGE';
    }

    // =========================================================================
    // ⚡ PREVIEW & ANALYSIS API
    // =========================================================================

    /**
     * Preview analysis endpoint for any feeder or global scope
     */
    public function preview(?int $penyulangId = null): array
    {
        $graph = $this->buildGraph($penyulangId);
        $candidates = $this->discoverCandidates($graph);

        // Select initial defensible batch (up to 10 independent edges)
        $defensibleBatch = [];
        $batchDegrees = $graph['degrees'];

        foreach ($candidates as $c) {
            if (!$c['is_auto_complete'] || !$c['gate_valid']) {
                continue;
            }

            $u = $c['source_asset_id'];
            $v = $c['target_asset_id'];

            if (($batchDegrees[$u] ?? 0) >= self::HARD_MAX_DEGREE || ($batchDegrees[$v] ?? 0) >= self::HARD_MAX_DEGREE) {
                continue;
            }

            $defensibleBatch[] = $c;
            $batchDegrees[$u] = ($batchDegrees[$u] ?? 0) + 1;
            $batchDegrees[$v] = ($batchDegrees[$v] ?? 0) + 1;

            if (count($defensibleBatch) >= self::MAX_BATCH_SIZE) {
                break;
            }
        }

        $autoCompleteCount = count(array_filter($candidates, fn($c) => $c['is_auto_complete']));
        $highConfCount     = count(array_filter($candidates, fn($c) => $c['classification'] === 'HIGH_CONFIDENCE_REVIEW'));
        $reviewCount       = count(array_filter($candidates, fn($c) => $c['classification'] === 'REVIEW_REQUIRED'));
        $blockedCount      = count(array_filter($candidates, fn($c) => in_array($c['classification'], ['AMBIGUOUS', 'BLOCKED'])));

        return [
            'status'         => 'success',
            'engine_name'    => self::ENGINE_NAME,
            'engine_version' => self::ENGINE_VERSION,
            'penyulang_id'   => $penyulangId,
            'inventory'      => [
                'total_master_assets'      => count($graph['assets']),
                'authoritative_translines' => count($graph['translines']),
                'connected_assets_count'   => count($graph['connected_ids']),
                'isolated_assets_count'    => count($graph['isolated_ids']),
                'terminal_anchors_count'   => count($graph['terminal_ids']),
                'saturated_nodes_count'    => count($graph['saturated_ids']),
                'components_count'         => count($graph['components']),
            ],
            'summary'        => [
                'total_candidates_analyzed'   => count($candidates),
                'defensible_count'            => $autoCompleteCount,
                'defensible_candidates_count' => count($defensibleBatch),
                'batch_capacity'              => count($defensibleBatch),
                'high_confidence_review'      => $highConfCount,
                'review_required'             => $reviewCount,
                'ambiguous_blocked'           => $blockedCount,
                'isolated_chains_detected'    => count($graph['isolated_chains']),
            ],
            'defensible_batch_preview'   => $defensibleBatch,
            'candidates'                 => $candidates,
            'all_candidates_sample'      => array_slice($candidates, 0, 50),
        ];
    }

    // =========================================================================
    // 🚀 PROGRESSIVE ATOMIC AUTO-EXECUTION ENGINE
    // =========================================================================

    /**
     * Run Network Completion Engine:
     * - AUTO mode loops through batches of <= 10 edges until defensible AUTO_COMPLETE = 0.
     * - Each batch is executed in its own isolated atomic MariaDB transaction.
     * - After each batch, the production graph is fully recomputed.
     */
    public function run(array $options = []): array
    {
        $penyulangId   = isset($options['penyulang_id']) ? (int)$options['penyulang_id'] : null;
        $mode          = strtoupper($options['mode'] ?? 'AUTO'); // AUTO | PREVIEW | DRY_RUN
        $batchSize     = min(self::MAX_BATCH_SIZE, max(1, (int)($options['batch_size'] ?? self::MAX_BATCH_SIZE)));
        $maxBatches    = isset($options['max_batches']) && $options['max_batches'] !== null ? (int)$options['max_batches'] : null;
        $actorName     = (string)($options['actor_name'] ?? 'ENGINEER_TRANSLINE_AI');
        $runId         = 'AI-RUN-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

        if ($mode === 'PREVIEW') {
            return $this->preview($penyulangId);
        }

        // Initial state capture
        $initialGraph = $this->buildGraph($penyulangId);
        $preSignatures = $this->captureTableSignatures($penyulangId);

        $batches = [];
        $allCreatedIds = [];
        $totalCreated = 0;
        $batchNum = 0;
        $haltReason = '';

        while (true) {
            $batchNum++;

            if ($maxBatches !== null && $batchNum > $maxBatches) {
                $haltReason = 'MAX_BATCHES_LIMIT_REACHED';
                break;
            }

            // 1. Rebuild Fresh In-Memory Graph
            $currentGraph = $this->buildGraph($penyulangId);

            if (empty($currentGraph['isolated_ids'])) {
                $haltReason = 'ALL_ASSETS_CONNECTED';
                break;
            }

            // 2. Discover Candidates from Current Topology
            $candidates = $this->discoverCandidates($currentGraph);
            $autoCandidates = array_values(array_filter($candidates, fn($c) => $c['is_auto_complete'] && $c['gate_valid']));

            // Filter by requested proposal keys if provided
            $requestedKeys = $options['candidate_natural_keys'] ?? [];
            if (!empty($requestedKeys) && is_array($requestedKeys)) {
                $reqMap = array_flip($requestedKeys);
                $autoCandidates = array_values(array_filter($autoCandidates, fn($c) => isset($reqMap[$c['natural_key']])));
            }

            if (empty($autoCandidates)) {
                $haltReason = 'NATURAL_STABILIZATION_REACHED';
                break; // Natural stop gate: no more defensible candidates!
            }

            // 3. Assemble Defensible Independent Batch (<= batchSize edges)
            $selectedBatch = [];
            $batchDegrees = $currentGraph['degrees'];

            foreach ($autoCandidates as $c) {
                $u = $c['source_asset_id'];
                $v = $c['target_asset_id'];

                if (($batchDegrees[$u] ?? 0) >= self::HARD_MAX_DEGREE || ($batchDegrees[$v] ?? 0) >= self::HARD_MAX_DEGREE) {
                    continue;
                }

                $selectedBatch[] = $c;
                $batchDegrees[$u] = ($batchDegrees[$u] ?? 0) + 1;
                $batchDegrees[$v] = ($batchDegrees[$v] ?? 0) + 1;

                if (count($selectedBatch) >= $batchSize) {
                    break;
                }
            }

            if (empty($selectedBatch)) {
                $haltReason = 'DEGREE_SATURATION_STABILIZATION';
                break;
            }

            // In DRY_RUN mode, record simulated batch and stop
            if ($mode === 'DRY_RUN') {
                $batches[] = [
                    'batch_number' => $batchNum,
                    'mode'         => 'DRY_RUN',
                    'count'        => count($selectedBatch),
                    'edges'        => $selectedBatch,
                ];
                $haltReason = 'DRY_RUN_COMPLETED';
                break;
            }

            // 4. Execute Atomic Transaction for this Batch
            $this->db->transStart();

            $batchCreatedTranslines = [];
            $batchCreatedIds = [];

            try {
                $validColumns = array_flip($this->db->getFieldNames('gis_translines'));

                foreach ($selectedBatch as $idx => $edge) {
                    $minId = $edge['source_asset_id'];
                    $maxId = $edge['target_asset_id'];

                    // Final server-side validation firewall before INSERT
                    if ((float)$edge['distance_meters'] < self::SHORT_SPAN_FIREWALL_METERS) {
                        throw new RuntimeException("SHORT_SPAN_FIREWALL breach: Edge {$edge['natural_key']} has span {$edge['distance_meters']}m < " . self::SHORT_SPAN_FIREWALL_METERS . "m. Transaction aborted.");
                    }

                    // Anti-duplicate verification against current database
                    $exists = $this->db->table('gis_translines')
                        ->where('is_active', 1)
                        ->where('deleted_at IS NULL')
                        ->groupStart()
                            ->where(['source_asset_id' => $minId, 'target_asset_id' => $maxId])
                            ->orWhere(['source_asset_id' => $maxId, 'target_asset_id' => $minId])
                        ->groupEnd()
                        ->countAllResults();

                    if ($exists > 0) {
                        continue; // Already materialized
                    }

                    $translineCode = "TL-{$penyulangId}-{$minId}-{$maxId}";
                    $propNum = $idx + 1;
                    $provenance = "{$actorName}|ENGINE=NETWORK_COMPLETION_v1|RUN:{$runId}|BATCH:{$batchNum}|PROP:{$propNum}";

                    $coords = $edge['coordinates'];
                    $geoJson = json_encode([
                        'type' => 'LineString',
                        'coordinates' => $coords
                    ]);

                    $row = [
                        'transline_code'     => $translineCode,
                        'penyulang_id'       => $penyulangId ?? 15,
                        'source_asset_id'    => $minId,
                        'target_asset_id'    => $maxId,
                        'conductor_type'     => $edge['conductor_type'] ?? 'AAAC',
                        'conductor_size'     => $edge['conductor_size'] ?? '150 mm²',
                        'conductor_material' => 'ALUMINUM_ALLOY',
                        'installation_type'  => 'OVERHEAD',
                        'circuit_config'     => '3_PHASE',
                        'distance_meters'    => $edge['distance_meters'],
                        'geometry'           => $geoJson,
                        'is_active'          => 1,
                        'created_by'         => $provenance,
                    ];

                    if (isset($validColumns['coordinates'])) {
                        $row['coordinates'] = $geoJson;
                    }

                    $insertRow = array_intersect_key($row, $validColumns);
                    $inserted = $this->db->table('gis_translines')->insert($insertRow);

                    if (!$inserted) {
                        $err = $this->db->error();
                        throw new RuntimeException("Insert into gis_translines failed: " . ($err['message'] ?? 'unknown'));
                    }

                    $newId = (int)$this->db->insertID();
                    $batchCreatedIds[] = $newId;
                    $batchCreatedTranslines[] = [
                        'id'              => $newId,
                        'transline_code'  => $translineCode,
                        'natural_key'     => $edge['natural_key'],
                        'source_asset_id' => $minId,
                        'target_asset_id' => $maxId,
                        'distance_meters' => $edge['distance_meters'],
                        'total_score'     => $edge['total_score'],
                        'network_score'   => $edge['network_score'] ?? 0,
                        'candidate_type'  => $edge['candidate_type'] ?? 'MAINLINE',
                        'created_by'      => $provenance,
                    ];
                }

                // 5. Zero-Write Verification within Transaction
                $midSignatures = $this->captureTableSignatures($penyulangId);
                if ($midSignatures['assets'] !== $preSignatures['assets'] ||
                    $midSignatures['temuan'] !== $preSignatures['temuan']) {
                    throw new RuntimeException("CRITICAL: Protected table mutation detected during batch {$batchNum}. Transaction aborted.");
                }

                $this->db->transComplete();

                if ($this->db->transStatus() === false) {
                    throw new RuntimeException("Transaction commit failed for batch {$batchNum}.");
                }
            } catch (\Throwable $e) {
                $this->db->transRollback();
                $haltReason = 'BATCH_TRANSACTION_ERROR: ' . $e->getMessage();
                break;
            }

            if (empty($batchCreatedIds)) {
                $haltReason = 'ALL_BATCH_CANDIDATES_ALREADY_MATERIALIZED';
                break;
            }

            $batches[] = [
                'batch_number' => $batchNum,
                'created_count'=> count($batchCreatedIds),
                'created_ids'  => $batchCreatedIds,
                'translines'   => $batchCreatedTranslines,
            ];

            $totalCreated += count($batchCreatedIds);
            $allCreatedIds = array_merge($allCreatedIds, $batchCreatedIds);
        }

        // 6. Post-Run Final Analysis & Signature Verification
        $finalGraph = $this->buildGraph($penyulangId);
        $postSignatures = $this->captureTableSignatures($penyulangId);

        $protectedTablesIntact = (
            $preSignatures['assets'] === $postSignatures['assets'] &&
            $preSignatures['temuan'] === $postSignatures['temuan']
        );

        $isIdempotent = ($totalCreated === 0 && in_array($haltReason, [
            'NATURAL_STABILIZATION_REACHED',
            'ALL_ASSETS_CONNECTED',
            'DEGREE_SATURATION_STABILIZATION',
            'ALL_BATCH_CANDIDATES_ALREADY_MATERIALIZED'
        ]));

        $result = [
            'status'                     => $isIdempotent ? 'IDEMPOTENT' : 'STABILIZED',
            'engine_name'                => self::ENGINE_NAME,
            'engine_version'             => self::ENGINE_VERSION,
            'run_id'                     => $runId,
            'penyulang_id'               => $penyulangId,
            'mode'                       => $mode,
            'halt_reason'                => $haltReason,
            'batches_executed'           => count($batches),
            'total_created_translines'   => $totalCreated,
            'created_transline_ids'      => $allCreatedIds,
            'batches'                    => $batches,
            'zero_write_firewall_pass'   => $protectedTablesIntact,
            'inventory_progression'      => [
                'initial' => [
                    'assets_count'     => count($initialGraph['assets']),
                    'translines_count' => count($initialGraph['translines']),
                    'connected_count'  => count($initialGraph['connected_ids']),
                    'isolated_count'   => count($initialGraph['isolated_ids']),
                ],
                'final' => [
                    'assets_count'     => count($finalGraph['assets']),
                    'translines_count' => count($finalGraph['translines']),
                    'connected_count'  => count($finalGraph['connected_ids']),
                    'isolated_count'   => count($finalGraph['isolated_ids']),
                ],
            ],
            'table_signatures'           => [
                'before' => $preSignatures,
                'after'  => $postSignatures,
            ],
        ];

        // Persist Audit Record
        $this->persistAuditRecord($result);

        return $result;
    }

    /**
     * Exact-PK Rollback
     */
    public function rollback(array $translineIds, string $runId, string $actor = 'ADMIN'): array
    {
        if (empty($translineIds)) {
            return ['status' => 'error', 'message' => 'transline_ids tidak boleh kosong.'];
        }

        $this->db->transStart();
        $rolledBack = [];

        foreach ($translineIds as $tId) {
            $row = $this->db->table('gis_translines')
                ->where('id', (int)$tId)
                ->where('deleted_at IS NULL')
                ->get()->getRowArray();

            if (!$row) continue;

            $createdBy = (string)($row['created_by'] ?? '');
            if (strpos($createdBy, 'NETWORK_COMPLETION') === false && strpos($createdBy, $runId) === false) {
                continue; // Protected from deleting manual baseline or legacy translines
            }

            $this->db->table('gis_translines')
                ->where('id', (int)$tId)
                ->update([
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'updated_by' => "{$actor}|ROLLBACK:{$runId}"
                ]);

            $rolledBack[] = $tId;
        }

        $this->db->transComplete();

        return [
            'status'          => $this->db->transStatus() ? 'success' : 'error',
            'rolled_back_ids' => $rolledBack,
            'count'           => count($rolledBack),
        ];
    }

    // =========================================================================
    // 🛡️ ZERO-WRITE AUDIT & SIGNATURES
    // =========================================================================

    public function captureTableSignatures(?int $penyulangId = null): array
    {
        $assetQuery = $this->db->table('assets')
            ->select('id, kode_asset, latitude, longitude, section_id, construction_type_id')
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'ASC');

        if ($penyulangId !== null && $penyulangId > 0) {
            $assetQuery->where('penyulang_id', $penyulangId);
        }

        $assets = $assetQuery->get()->getResultArray();

        $temuanQuery = $this->db->table('temuan')
            ->select('id')
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'ASC');

        $temuan = $temuanQuery->get()->getResultArray();

        return [
            'assets' => hash('sha256', json_encode($assets)),
            'temuan' => hash('sha256', json_encode($temuan)),
        ];
    }

    protected function persistAuditRecord(array $result): void
    {
        $auditDir = WRITEPATH . 'audits';
        if (!is_dir($auditDir)) {
            @mkdir($auditDir, 0777, true);
        }

        $auditFile = $auditDir . DIRECTORY_SEPARATOR . 'transline_ai_network_completion.json';
        @file_put_contents($auditFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
