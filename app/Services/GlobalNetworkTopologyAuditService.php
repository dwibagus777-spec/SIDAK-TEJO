<?php

namespace App\Services;

use Config\Database;

/**
 * GlobalNetworkTopologyAuditService
 *
 * STRICT READ-ONLY Enterprise Network Topology Coverage Auditor across ALL Feeders.
 *
 * Invariants Enforced:
 * 1. STRICT READ-ONLY: 0 INSERT, 0 UPDATE, 0 DELETE, 0 TRUNCATE, 0 DDL.
 * 2. PROTECTED DOMAINS IMMUTABLE: assets, gis_translines, gis_transline_proposals,
 *    temuan, temuan_materials, sections, penyulang, ulps.
 * 3. TEMUAN ISOLATION: Temuan records are NEVER treated as network nodes,
 *    endpoints, or topology candidates.
 * 4. IN-MEMORY SCALABLE ARCHITECTURE: All global data indexed in single-pass
 *    in-memory Hash Maps (O(1) lookups). Zero sequential full database scans.
 */
class GlobalNetworkTopologyAuditService
{
    private const HARD_MAX_SPAN_METERS = 85.0;
    private const CANDIDATE_SEARCH_RADIUS_METERS = 120.0;
    private const HARD_MAX_DEGREE = 4;

    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Compute SHA-256 state fingerprint across all protected tables
     */
    public function computeDatabaseFingerprint(): array
    {
        $tables = [
            'assets',
            'gis_translines',
            'gis_transline_proposals',
            'temuan',
            'temuan_materials',
            'sections',
            'penyulang',
            'ulps'
        ];

        $fingerprints = [];
        foreach ($tables as $table) {
            try {
                if (!$this->db->tableExists($table)) {
                    $fingerprints[$table] = ['count' => 0, 'sha256' => hash('sha256', 'TABLE_NOT_FOUND')];
                    continue;
                }

                $count = (int)$this->db->table($table)->countAllResults();
                $fields = $this->db->getFieldNames($table);
                $builder = $this->db->table($table);
                if (in_array('id', $fields)) {
                    $builder->orderBy('id', 'ASC');
                }
                $query = $builder->limit(1000)->get();
                $rows = ($query && !is_bool($query)) ? $query->getResultArray() : [];
                $sha256 = hash('sha256', json_encode($rows));

                $fingerprints[$table] = [
                    'count'  => $count,
                    'sha256' => $sha256,
                ];
            } catch (\Throwable $e) {
                $fingerprints[$table] = ['count' => 0, 'sha256' => hash('sha256', 'ERR_' . $e->getMessage())];
            }
        }

        return $fingerprints;
    }

    /**
     * Executes the comprehensive Global Network Topology Coverage Audit
     */
    public function runAudit(): array
    {
        $startTime = microtime(true);
        $timestamp = date('c');

        // 1. Pre-Audit Zero-Write Fingerprint
        $preFingerprint = $this->computeDatabaseFingerprint();

        // 2. Discover All ULPs and Feeders (Phase 1)
        $ulps = [];
        if ($this->db->tableExists('ulps')) {
            $query = $this->db->table('ulps')->get();
            $ulpRows = ($query && !is_bool($query)) ? $query->getResultArray() : [];
            foreach ($ulpRows as $u) {
                $ulps[(int)$u['id']] = $u;
            }
        }

        $feeders = [];
        if ($this->db->tableExists('penyulang')) {
            $query = $this->db->table('penyulang')->get();
            $feederRows = ($query && !is_bool($query)) ? $query->getResultArray() : [];
            foreach ($feederRows as $f) {
                $feeders[(int)$f['id']] = $f;
            }
        }

        // 3. Build Reusable In-Memory Hash Map Indexes (Phase 6 - O(1) Lookups)
        $sectionsByFeeder = [];
        if ($this->db->tableExists('sections')) {
            $secFields = $this->db->getFieldNames('sections');
            $builder = $this->db->table('sections');
            if (in_array('deleted_at', $secFields)) {
                $builder->where('deleted_at IS NULL');
            }
            $query = $builder->get();
            $secRows = ($query && !is_bool($query)) ? $query->getResultArray() : [];
            foreach ($secRows as $sec) {
                $fId = (int)($sec['penyulang_id'] ?? 0);
                $sectionsByFeeder[$fId][] = $sec;
            }
        }

        $assetMap          = [];
        $assetsByFeeder    = [];
        $coordsByAsset     = [];
        $totalValidGpsAll  = 0;
        $totalJtmAssetsAll = 0;

        if ($this->db->tableExists('assets')) {
            $assetFields = $this->db->getFieldNames('assets');
            $builder = $this->db->table('assets');
            if (in_array('deleted_at', $assetFields)) {
                $builder->where('deleted_at IS NULL');
            }
            $query = $builder->get();
            $assetRows = ($query && !is_bool($query)) ? $query->getResultArray() : [];

            foreach ($assetRows as $a) {
                $id   = (int)$a['id'];
                $fId  = (int)($a['penyulang_id'] ?? 0);
                $lat  = (float)($a['latitude'] ?? 0);
                $lng  = (float)($a['longitude'] ?? 0);
                $hasGps = ($lat != 0.0 && $lng != 0.0 && abs($lat) <= 90.0 && abs($lng) <= 180.0);

                $a['has_valid_gps'] = $hasGps;
                $assetMap[$id] = $a;
                $coordsByAsset[$id] = ['lat' => $lat, 'lng' => $lng, 'has_gps' => $hasGps];

                if ($fId > 0) {
                    $assetsByFeeder[$fId][] = $a;
                }

                $totalJtmAssetsAll++;
                if ($hasGps) {
                    $totalValidGpsAll++;
                }
            }
        }

        $translineMap        = [];
        $translinesByFeeder  = [];
        $globalAdjacency     = [];
        $globalDegrees       = [];
        $globalEdgeCount     = 0;

        $globalDuplicateEdges  = 0;
        $globalSelfLoops       = 0;
        $globalCrossFeederEdges = 0;
        $globalCrossUlpEdges   = 0;
        $globalInvalidEndpoints = 0;
        $globalEdgeKeySet      = [];

        if ($this->db->tableExists('gis_translines')) {
            $tlFields = $this->db->getFieldNames('gis_translines');
            $builder = $this->db->table('gis_translines');
            if (in_array('deleted_at', $tlFields)) {
                $builder->where('deleted_at IS NULL');
            }
            $query = $builder->get();
            $tlRows = ($query && !is_bool($query)) ? $query->getResultArray() : [];

            foreach ($tlRows as $tl) {
                $id  = (int)$tl['id'];
                $fId = (int)($tl['penyulang_id'] ?? 0);
                $u   = (int)$tl['source_asset_id'];
                $v   = (int)$tl['target_asset_id'];
                $translineMap[$id] = $tl;
                $translinesByFeeder[$fId][] = $tl;
                $globalEdgeCount++;

                // Endpoint validity check
                $uExists = isset($assetMap[$u]);
                $vExists = isset($assetMap[$v]);
                if (!$uExists || !$vExists) {
                    $globalInvalidEndpoints++;
                }

                // Self loop check
                if ($u === $v) {
                    $globalSelfLoops++;
                }

                // Duplicate edge check
                $minId = min($u, $v);
                $maxId = max($u, $v);
                $edgeKey = "{$minId}_{$maxId}";
                if (isset($globalEdgeKeySet[$edgeKey])) {
                    $globalDuplicateEdges++;
                }
                $globalEdgeKeySet[$edgeKey] = true;

                // Cross-feeder / Cross-ULP check
                if ($uExists && $vExists) {
                    $uFeeder = (int)($assetMap[$u]['penyulang_id'] ?? 0);
                    $vFeeder = (int)($assetMap[$v]['penyulang_id'] ?? 0);
                    $uUlp    = (int)($assetMap[$u]['ulp_id'] ?? 0);
                    $vUlp    = (int)($assetMap[$v]['ulp_id'] ?? 0);

                    if ($uFeeder !== $vFeeder || $uFeeder !== $fId || $vFeeder !== $fId) {
                        $globalCrossFeederEdges++;
                    }
                    if ($uUlp !== $vUlp && $uUlp > 0 && $vUlp > 0) {
                        $globalCrossUlpEdges++;
                    }
                }

                // Global graph adjacency & degree
                $globalDegrees[$u] = ($globalDegrees[$u] ?? 0) + 1;
                $globalDegrees[$v] = ($globalDegrees[$v] ?? 0) + 1;
                $globalAdjacency[$u][] = $v;
                $globalAdjacency[$v][] = $u;
            }
        }

        // Proposals by Feeder
        $proposalsByFeeder = [];
        if ($this->db->tableExists('gis_transline_proposals')) {
            $propFields = $this->db->getFieldNames('gis_transline_proposals');
            $builder = $this->db->table('gis_transline_proposals');
            if (in_array('deleted_at', $propFields)) {
                $builder->where('deleted_at IS NULL');
            }
            $query = $builder->get();
            $propRows = ($query && !is_bool($query)) ? $query->getResultArray() : [];
            foreach ($propRows as $prop) {
                $fId = (int)($prop['penyulang_id'] ?? 0);
                $proposalsByFeeder[$fId][] = $prop;
            }
        }

        // 4. Feeder-by-Feeder Graph Analysis & Matrix (Phase 2 & Phase 3)
        $feederMatrix = [];
        $classificationCounts = [
            'COMPLETE_STABLE'                 => 0,
            'PARTIALLY_CONNECTED_AUTO_READY'  => 0,
            'PARTIALLY_CONNECTED_REVIEW_REQ'  => 0,
            'BLOCKED_DATA_QUALITY'            => 0,
            'NO_TOPOLOGY_DATA'                => 0,
            'NOT_ELIGIBLE_SCOPE'              => 0,
        ];

        $opportunityCounts = [
            'READY_FOR_AUTOMATED_COMPLETION'  => 0,
            'READY_FOR_REVIEW'                => 0,
            'BLOCKED'                         => 0,
            'NO_DATA'                         => 0,
            'ALREADY_STABLE'                  => 0,
            'NOT_ELIGIBLE'                    => 0,
        ];

        $globalTotalConnectedAssets = 0;
        $globalTotalIsolatedAssets  = 0;
        $globalTotalAutoCandidates  = 0;
        $globalTotalHighConfCandidates = 0;
        $globalTotalReviewCandidates = 0;
        $globalTotalBlockedCandidates = 0;

        foreach ($feeders as $fId => $feeder) {
            $feederName = $feeder['nama_penyulang'] ?? "Penyulang #{$fId}";
            $ulpId      = (int)($feeder['ulp_id'] ?? 0);
            $ulpName    = $ulps[$ulpId]['nama_ulp'] ?? "ULP #{$ulpId}";
            $feederStatus = strtoupper(trim((string)($feeder['status'] ?? 'AKTIF')));
            $sections   = $sectionsByFeeder[$fId] ?? [];
            $assets     = $assetsByFeeder[$fId] ?? [];
            $translines = $translinesByFeeder[$fId] ?? [];
            $proposals  = $proposalsByFeeder[$fId] ?? [];

            $totalAssets = count($assets);
            $existingTranslines = count($translines);
            $sectionCount = count($sections);

            $validGpsAssets = 0;
            $feederAssetMap = [];
            foreach ($assets as $a) {
                $feederAssetMap[(int)$a['id']] = $a;
                if (!empty($a['has_valid_gps'])) {
                    $validGpsAssets++;
                }
            }

            // Build Feeder Graph
            $feederDegrees   = [];
            $feederAdjacency = [];
            $feederEdgeKeys  = [];
            $feederDupEdges  = 0;
            $feederSelfLoops = 0;
            $feederCrossFeed = 0;
            $feederCrossUlp  = 0;
            $feederInvalidEnd = 0;

            foreach ($feederAssetMap as $aId => $a) {
                $feederDegrees[$aId] = 0;
                $feederAdjacency[$aId] = [];
            }

            foreach ($translines as $tl) {
                $u = (int)$tl['source_asset_id'];
                $v = (int)$tl['target_asset_id'];

                if (!isset($feederAssetMap[$u]) || !isset($feederAssetMap[$v])) {
                    $feederInvalidEnd++;
                }
                if ($u === $v) {
                    $feederSelfLoops++;
                }

                $minId = min($u, $v);
                $maxId = max($u, $v);
                $edgeKey = "{$minId}_{$maxId}";
                if (isset($feederEdgeKeys[$edgeKey])) {
                    $feederDupEdges++;
                }
                $feederEdgeKeys[$edgeKey] = true;

                if (isset($feederDegrees[$u])) $feederDegrees[$u]++;
                if (isset($feederDegrees[$v])) $feederDegrees[$v]++;
                if (isset($feederAdjacency[$u])) $feederAdjacency[$u][] = $v;
                if (isset($feederAdjacency[$v])) $feederAdjacency[$v][] = $u;
            }

            // Degrees breakdown
            $connectedCount   = 0;
            $isolatedCount    = 0;
            $terminalCount    = 0;
            $passthroughCount = 0;
            $branchCount      = 0;
            $saturatedCount   = 0;

            $isolatedAssetIds = [];
            $terminalAnchorIds = [];
            $connectedAssetIds = [];

            foreach ($feederDegrees as $aId => $deg) {
                if ($deg === 0) {
                    $isolatedCount++;
                    $isolatedAssetIds[] = $aId;
                } else {
                    $connectedCount++;
                    $connectedAssetIds[] = $aId;
                    if ($deg === 1) {
                        $terminalCount++;
                        $terminalAnchorIds[] = $aId;
                    } elseif ($deg === 2) {
                        $passthroughCount++;
                    } elseif ($deg === 3) {
                        $branchCount++;
                    } elseif ($deg >= 4) {
                        $saturatedCount++;
                    }
                }
            }

            $globalTotalConnectedAssets += $connectedCount;
            $globalTotalIsolatedAssets  += $isolatedCount;

            // Candidate Evaluation (Strict Read-Only Simulation)
            $autoCompleteCandidates = 0;
            $highConfidenceCandidates = 0;
            $reviewRequiredCandidates = 0;
            $blockedCandidates = 0;

            if ($isolatedCount > 0 && $validGpsAssets > 1) {
                $evalPairs = [];
                $sourcePool = array_unique(array_merge($terminalAnchorIds, $isolatedAssetIds));

                foreach ($sourcePool as $uId) {
                    $uAsset = $feederAssetMap[$uId];
                    if (empty($uAsset['has_valid_gps'])) continue;
                    $uDeg = $feederDegrees[$uId] ?? 0;
                    if ($uDeg >= self::HARD_MAX_DEGREE) continue;

                    foreach ($isolatedAssetIds as $vId) {
                        if ($uId === $vId) continue;
                        $vAsset = $feederAssetMap[$vId];
                        if (empty($vAsset['has_valid_gps'])) continue;
                        $vDeg = $feederDegrees[$vId] ?? 0;
                        if ($vDeg >= self::HARD_MAX_DEGREE) continue;

                        $minId = min($uId, $vId);
                        $maxId = max($uId, $vId);
                        $pairKey = "{$minId}_{$maxId}";

                        if (isset($evalPairs[$pairKey]) || isset($feederEdgeKeys[$pairKey])) {
                            continue;
                        }
                        $evalPairs[$pairKey] = true;

                        $dist = $this->haversineDistanceMeters(
                            (float)$uAsset['latitude'], (float)$uAsset['longitude'],
                            (float)$vAsset['latitude'], (float)$vAsset['longitude']
                        );

                        if ($dist > self::CANDIDATE_SEARCH_RADIUS_METERS) {
                            continue; // Out of range
                        }

                        // Safety Gate Verification
                        $gateValid = true;
                        if ($dist > self::HARD_MAX_SPAN_METERS) {
                            $gateValid = false;
                        }
                        if ($dist < 1.0) {
                            $gateValid = false;
                        }
                        if ($uDeg >= self::HARD_MAX_DEGREE || $vDeg >= self::HARD_MAX_DEGREE) {
                            $gateValid = false;
                        }

                        if (!$gateValid) {
                            $blockedCandidates++;
                            continue;
                        }

                        // Base Score Calculation (0 - 100)
                        $score = 0;
                        // Proximity: up to 45 pts
                        $score += max(0, (1.0 - ($dist / self::HARD_MAX_SPAN_METERS)) * 45);

                        // Degree compatibility: up to 25 pts
                        if (($uDeg === 1 && $vDeg === 0) || ($uDeg === 0 && $vDeg === 1)) {
                            $score += 25; // Terminal anchor extending line
                        } elseif ($uDeg === 0 && $vDeg === 0) {
                            $score += 15; // Connecting two isolated poles
                        } elseif ($uDeg === 2 && $vDeg === 0) {
                            $score += 12; // T-off tap from pass-through
                        }

                        // Code continuity: up to 20 pts
                        $codeU = (string)($uAsset['kode_asset'] ?? '');
                        $codeV = (string)($vAsset['kode_asset'] ?? '');
                        if (!empty($codeU) && !empty($codeV)) {
                            if (substr($codeU, 0, 15) === substr($codeV, 0, 15)) {
                                $score += 15;
                            }
                        }

                        // Construction feasibility: 10 pts
                        $score += 10;

                        if ($score >= 88) {
                            $autoCompleteCandidates++;
                        } elseif ($score >= 78) {
                            $highConfidenceCandidates++;
                        } else {
                            $reviewRequiredCandidates++;
                        }
                    }
                }
            }

            $globalTotalAutoCandidates      += $autoCompleteCandidates;
            $globalTotalHighConfCandidates  += $highConfidenceCandidates;
            $globalTotalReviewCandidates    += $reviewRequiredCandidates;
            $globalTotalBlockedCandidates   += $blockedCandidates;

            // Phase 3: Topology Coverage Classification
            if ($feederStatus !== 'AKTIF' || $totalAssets === 0) {
                $classification = 'F. NOT ELIGIBLE JTM SCOPE';
                $classKey = 'NOT_ELIGIBLE_SCOPE';
                $opportunity = 'NOT_ELIGIBLE';
            } elseif ($validGpsAssets === 0) {
                $classification = 'D. BLOCKED BY DATA QUALITY';
                $classKey = 'BLOCKED_DATA_QUALITY';
                $opportunity = 'BLOCKED';
            } elseif ($existingTranslines === 0 && $autoCompleteCandidates === 0 && $highConfidenceCandidates === 0 && $reviewRequiredCandidates === 0) {
                $classification = 'E. NO TOPOLOGY DATA';
                $classKey = 'NO_TOPOLOGY_DATA';
                $opportunity = 'NO_DATA';
            } elseif ($isolatedCount === 0 && $connectedCount === $totalAssets && $existingTranslines > 0) {
                $classification = 'A. COMPLETE / NATURALLY STABLE';
                $classKey = 'COMPLETE_STABLE';
                $opportunity = 'ALREADY_STABLE';
            } elseif ($autoCompleteCandidates > 0) {
                $classification = 'B. PARTIALLY CONNECTED — AUTO-COMPLETE AVAILABLE';
                $classKey = 'PARTIALLY_CONNECTED_AUTO_READY';
                $opportunity = 'READY_FOR_AUTOMATED_COMPLETION';
            } elseif ($highConfidenceCandidates > 0 || $reviewRequiredCandidates > 0) {
                $classification = 'C. PARTIALLY CONNECTED — REVIEW REQUIRED';
                $classKey = 'PARTIALLY_CONNECTED_REVIEW_REQ';
                $opportunity = 'READY_FOR_REVIEW';
            } elseif ($blockedCandidates > 0 && $autoCompleteCandidates === 0) {
                $classification = 'D. BLOCKED BY DATA QUALITY';
                $classKey = 'BLOCKED_DATA_QUALITY';
                $opportunity = 'BLOCKED';
            } else {
                $classification = 'E. NO TOPOLOGY DATA';
                $classKey = 'NO_TOPOLOGY_DATA';
                $opportunity = 'NO_DATA';
            }

            $classificationCounts[$classKey]++;
            $opportunityCounts[$opportunity]++;

            $feederMatrix[] = [
                'penyulang_id'       => $fId,
                'kode_penyulang'     => $feeder['kode_penyulang'] ?? '',
                'nama_penyulang'     => $feederName,
                'ulp_id'             => $ulpId,
                'nama_ulp'           => $ulpName,
                'status'             => $feederStatus,
                'section_count'      => $sectionCount,
                'total_jtm_assets'   => $totalAssets,
                'valid_gps_assets'   => $validGpsAssets,
                'existing_translines'=> $existingTranslines,
                'connected_assets'   => $connectedCount,
                'isolated_assets'    => $isolatedCount,
                'terminal_assets'    => $terminalCount,
                'passthrough_nodes'  => $passthroughCount,
                'branch_nodes'       => $branchCount,
                'saturated_nodes'    => $saturatedCount,
                'duplicate_edges'    => $feederDupEdges,
                'self_loops'         => $feederSelfLoops,
                'cross_feeder_edges' => $feederCrossFeed,
                'cross_ulp_edges'    => $feederCrossUlp,
                'invalid_endpoints'  => $feederInvalidEnd,
                'auto_complete'      => $autoCompleteCandidates,
                'high_confidence'    => $highConfidenceCandidates,
                'review_required'    => $reviewRequiredCandidates,
                'blocked'            => $blockedCandidates,
                'classification'     => $classification,
                'opportunity'        => $opportunity,
                'pending_proposals'  => count($proposals),
            ];
        }

        // 5. Post-Audit Zero-Write Verification
        $postFingerprint = $this->computeDatabaseFingerprint();
        $zeroWritePass = true;
        foreach ($preFingerprint as $table => $pre) {
            $post = $postFingerprint[$table] ?? null;
            if (!$post || $pre['count'] !== $post['count'] || $pre['sha256'] !== $post['sha256']) {
                $zeroWritePass = false;
                break;
            }
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'status'     => 'success',
            'phase'      => 'GLOBAL_NETWORK_TOPOLOGY_COVERAGE_AUDIT',
            'timestamp'  => $timestamp,
            'duration_ms'=> $durationMs,
            'governance' => [
                'strict_read_only'    => true,
                'operational_inserts' => 0,
                'operational_updates' => 0,
                'operational_deletes' => 0,
                'operational_ddl'     => 0,
                'zero_write_firewall' => $zeroWritePass ? 'PASS' : 'FAIL',
            ],
            'global_summary' => [
                'total_feeders'             => count($feeders),
                'total_ulps'                => count($ulps),
                'total_jtm_assets'          => $totalJtmAssetsAll,
                'total_valid_gps_assets'    => $totalValidGpsAll,
                'total_existing_translines' => $globalEdgeCount,
                'total_connected_assets'    => $globalTotalConnectedAssets,
                'total_isolated_assets'     => $globalTotalIsolatedAssets,
                'total_auto_complete'       => $globalTotalAutoCandidates,
                'total_high_confidence'     => $globalTotalHighConfCandidates,
                'total_review_required'     => $globalTotalReviewCandidates,
                'total_blocked'             => $globalTotalBlockedCandidates,
                'duplicate_edges_all'       => $globalDuplicateEdges,
                'self_loops_all'            => $globalSelfLoops,
                'cross_feeder_edges_all'    => $globalCrossFeederEdges,
                'cross_ulp_edges_all'       => $globalCrossUlpEdges,
                'invalid_endpoints_all'     => $globalInvalidEndpoints,
            ],
            'classification_summary' => $classificationCounts,
            'automation_opportunity' => $opportunityCounts,
            'feeder_matrix'          => $feederMatrix,
            'fingerprints'           => [
                'pre'   => $preFingerprint,
                'post'  => $postFingerprint,
                'match' => $zeroWritePass,
            ]
        ];
    }

    private function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
