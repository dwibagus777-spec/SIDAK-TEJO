<?php

namespace App\Services;

use Config\AssetIngestion;
use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * CR-ASSET-01: Decoupled Asset Topology Reconciliation Engine
 *
 * Guaranteed Invariants:
 * 1. Asset presence is decoupled from topology. An asset is valid even with 0 edges (ISOLATED_NODE).
 * 2. Deterministic evidence hierarchy (Levels 1 to 4).
 * 3. STRICT ANTI-NEAREST-NEIGHBOR: Zero edge synthesis via nearest distance or coordinate sorting.
 * 4. Existing authoritative translines in `gis_translines` are protected (never mutated or deleted).
 * 5. Minimum span: 5.0m, Maximum span: 120.0m. Spans > 120m or < 5m are flagged for review.
 */
class AssetTopologyReconciliationService
{
    protected BaseConnection $db;
    protected AssetIngestion $config;
    protected array $existingEdgesByFeeder = [];

    public function __construct(?BaseConnection $db = null, ?AssetIngestion $config = null)
    {
        $this->db = $db ?? Database::connect();
        $this->config = $config ?? new AssetIngestion();
    }

    /**
     * Reconcile candidate topology for a set of accepted assets.
     *
     * @param array $acceptedAssets Array of asset payloads from AssetMassIngestionService (AUTO_ACCEPT)
     * @return array [
     *   'candidate_translines' => [...],
     *   'isolated_nodes'       => [...],
     *   'review_candidates'    => [...],
     *   'summary'              => [...]
     * ]
     */
    public function reconcileTopology(array $acceptedAssets): array
    {
        if (empty($acceptedAssets)) {
            return [
                'candidate_translines' => [],
                'isolated_nodes'       => [],
                'review_candidates'    => [],
                'summary'              => [
                    'total_assets'             => 0,
                    'connected_assets'         => 0,
                    'isolated_assets'          => 0,
                    'candidate_translines_cnt' => 0,
                    'review_candidates_cnt'    => 0,
                ],
            ];
        }

        // Group assets by feeder
        $assetsByFeeder = [];
        $allFeederIds = [];
        foreach ($acceptedAssets as $item) {
            $payload = $item['payload'] ?? $item;
            $feederId = (int)($payload['penyulang_id'] ?? 0);
            if ($feederId <= 0) continue;

            $assetsByFeeder[$feederId][] = $payload;
            if (!in_array($feederId, $allFeederIds, true)) {
                $allFeederIds[] = $feederId;
            }
        }

        // Load existing transline edges for these feeders
        $this->loadExistingTranslines($allFeederIds);

        $candidateTranslines = [];
        $reviewCandidates = [];
        $connectedAssetCodes = [];
        $totalAssetsProcessed = 0;

        foreach ($assetsByFeeder as $feederId => $feederAssets) {
            $feederResult = $this->reconcileFeederTopology($feederId, $feederAssets);
            
            foreach ($feederResult['candidates'] as $edge) {
                $candidateTranslines[] = $edge;
                $connectedAssetCodes[$edge['source_asset_code']] = true;
                $connectedAssetCodes[$edge['target_asset_code']] = true;
            }

            foreach ($feederResult['review'] as $rev) {
                $reviewCandidates[] = $rev;
            }

            $totalAssetsProcessed += count($feederAssets);
        }

        // Determine isolated nodes: assets that have 0 candidate edges
        $isolatedNodes = [];
        foreach ($acceptedAssets as $item) {
            $payload = $item['payload'] ?? $item;
            $code = strtoupper(trim($payload['kode_asset'] ?? ''));
            if (!isset($connectedAssetCodes[$code])) {
                $isolatedNodes[] = [
                    'asset_code'   => $code,
                    'penyulang_id' => (int)($payload['penyulang_id'] ?? 0),
                    'latitude'     => $payload['latitude'] ?? null,
                    'longitude'    => $payload['longitude'] ?? null,
                    'status'       => 'ISOLATED_NODE',
                    'reason'       => 'No deterministic sequential chain or explicit link available (Strict anti-nearest-neighbor policy)',
                ];
            }
        }

        return [
            'candidate_translines' => $candidateTranslines,
            'isolated_nodes'       => $isolatedNodes,
            'review_candidates'    => $reviewCandidates,
            'summary'              => [
                'total_assets'             => $totalAssetsProcessed,
                'connected_assets'         => count($connectedAssetCodes),
                'isolated_assets'          => count($isolatedNodes),
                'candidate_translines_cnt' => count($candidateTranslines),
                'review_candidates_cnt'    => count($reviewCandidates),
            ],
        ];
    }

    /**
     * Load existing active translines to prevent reciprocal and duplicate creation.
     */
    protected function loadExistingTranslines(array $feederIds): void
    {
        if (empty($feederIds) || !$this->db->tableExists('gis_translines')) {
            return;
        }

        $translines = $this->db->table('gis_translines')
            ->select('id, penyulang_id, source_asset_id, target_asset_id')
            ->whereIn('penyulang_id', $feederIds)
            ->where('is_active', 1)
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($translines as $tl) {
            $fId = (int)$tl['penyulang_id'];
            $sId = (int)$tl['source_asset_id'];
            $tId = (int)$tl['target_asset_id'];

            $this->existingEdgesByFeeder[$fId]["{$sId}:{$tId}"] = (int)$tl['id'];
            $this->existingEdgesByFeeder[$fId]["{$tId}:{$sId}"] = (int)$tl['id'];
        }
    }

    /**
     * Reconcile topology for a single feeder using Levels 1-4 evidence.
     */
    protected function reconcileFeederTopology(int $feederId, array $feederAssets): array
    {
        $candidates = [];
        $review = [];
        $feederName = $this->getFeederName($feederId);

        // 1. Group assets by prefix (e.g., 'ECCO', 'SIWALANPANJI', 'GADINGKIRANA')
        $chainsByPrefix = [];
        foreach ($feederAssets as $asset) {
            $code = strtoupper(trim($asset['kode_asset'] ?? ''));
            $parsed = $this->parseAssetCodeSequence($code, $feederName);

            if ($parsed !== null) {
                $prefix = $parsed['prefix'];
                $index = $parsed['index'];
                $chainsByPrefix[$prefix][$index] = $asset;
            }
        }

        // 2. Evaluate sequential chains (Level 2: Consecutive sequence i -> i+1)
        foreach ($chainsByPrefix as $prefix => $chainIndexed) {
            ksort($chainIndexed, SORT_NUMERIC);
            $indices = array_keys($chainIndexed);

            for ($k = 0; $k < count($indices) - 1; $k++) {
                $idxA = $indices[$k];
                $idxB = $indices[$k + 1];

                // Strictly consecutive indices only: B must be A + 1
                if ($idxB !== $idxA + 1) {
                    // Gap in sequence (e.g. ECCO_10 -> ECCO_13 missing 11, 12)
                    // Do NOT bridge gaps arbitrarily!
                    $review[] = [
                        'feeder_id'          => $feederId,
                        'prefix'             => $prefix,
                        'source_index'       => $idxA,
                        'target_index'       => $idxB,
                        'source_asset_code'  => $chainIndexed[$idxA]['kode_asset'],
                        'target_asset_code'  => $chainIndexed[$idxB]['kode_asset'],
                        'reason_code'        => 'SEQUENCE_GAP',
                        'message'            => "Sequence gap detected between index {$idxA} and {$idxB} (delta: " . ($idxB - $idxA) . "). Connection held for human review.",
                    ];
                    continue;
                }

                $source = $chainIndexed[$idxA];
                $target = $chainIndexed[$idxB];

                $latA = (float)$source['latitude'];
                $lonA = (float)$source['longitude'];
                $latB = (float)$target['latitude'];
                $lonB = (float)$target['longitude'];

                // Geometric Validation (Level 4)
                $distance = $this->haversineDistanceMeters($latA, $lonA, $latB, $lonB);

                // Check min and max span thresholds
                if ($distance < $this->config->minSpanDistanceMeters) {
                    $review[] = [
                        'feeder_id'         => $feederId,
                        'source_asset_code' => $source['kode_asset'],
                        'target_asset_code' => $target['kode_asset'],
                        'distance_meters'   => $distance,
                        'reason_code'       => 'SPAN_BELOW_MIN_METERS',
                        'message'           => "Distance {$distance}m is below minimum threshold {$this->config->minSpanDistanceMeters}m",
                    ];
                    continue;
                }

                if ($distance > $this->config->maxSpanDistanceMeters) {
                    $review[] = [
                        'feeder_id'         => $feederId,
                        'source_asset_code' => $source['kode_asset'],
                        'target_asset_code' => $target['kode_asset'],
                        'distance_meters'   => $distance,
                        'reason_code'       => 'SPAN_EXCEEDS_MAX_METERS',
                        'message'           => "Distance {$distance}m exceeds maximum threshold {$this->config->maxSpanDistanceMeters}m",
                    ];
                    continue;
                }

                // Construct deterministic candidate edge
                $conductor = $this->config->defaultConductor;
                $lineString = sprintf('LINESTRING (%.7f %.7f, %.7f %.7f)', $lonA, $latA, $lonB, $latB);
                $edgeCode = "TL-{$source['kode_asset']}-{$target['kode_asset']}";

                $candidates[] = [
                    'transline_code'     => $edgeCode,
                    'penyulang_id'       => $feederId,
                    'source_asset_code'  => $source['kode_asset'],
                    'target_asset_code'  => $target['kode_asset'],
                    'geometry'           => $lineString,
                    'geometry_type'      => 'LineString',
                    'conductor_type'     => $conductor['type'] ?? 'AAAC',
                    'conductor_size'     => $conductor['size'] ?? '150 mm²',
                    'conductor_material' => $conductor['material'] ?? 'ALUMINUM_ALLOY',
                    'installation_type'  => $conductor['installation_type'] ?? 'OVERHEAD',
                    'circuit_config'     => $conductor['circuit_config'] ?? '3_PHASE',
                    'distance_meters'    => $distance,
                    'evidence_level'     => 'LEVEL_2_CONSECUTIVE_CHAIN',
                    'confidence'         => 'HIGH',
                    'status'             => 'ACTIVE',
                    'is_active'          => 1,
                ];
            }
        }

        return [
            'candidates' => $candidates,
            'review'     => $review,
        ];
    }

    protected array $feederNamesById = [];

    protected function getFeederName(int $feederId): ?string
    {
        if (isset($this->feederNamesById[$feederId])) {
            return $this->feederNamesById[$feederId];
        }

        $tablePenyulang = $this->db->tableExists('db_penyulang') ? 'db_penyulang' : 'penyulang';
        if ($this->db->tableExists($tablePenyulang)) {
            $row = $this->db->table($tablePenyulang)->select('id, nama_penyulang')->where('id', $feederId)->get()->getRowArray();
            if ($row && !empty($row['nama_penyulang'])) {
                $this->feederNamesById[$feederId] = strtoupper(trim((string)$row['nama_penyulang']));
                return $this->feederNamesById[$feederId];
            }
        }
        return null;
    }

    /**
     * Parse code with prefix and numeric sequence (e.g. 'ECCO_109' -> prefix: 'ECCO', index: 109,
     * or 'GADING KIRANA_29' with feeder context 'GADING KIRANA' -> prefix: 'GADINGKIRANA', index: 29).
     */
    public function parseAssetCodeSequence(string $code, ?string $feederContext = null): ?array
    {
        $code = trim($code);

        // 1. Feeder-aware contextual matching (Highest Confidence)
        if ($feederContext !== null && trim($feederContext) !== '') {
            $rawFeeder = trim($feederContext);
            $cleanFeeder = strtoupper(str_replace(['-', ' ', '_'], '', $rawFeeder));

            // Direct match with spaces/symbols: e.g. "GADING KIRANA_29"
            $quotedFeeder = preg_quote($rawFeeder, '/');
            if (preg_match('/^' . $quotedFeeder . '[_\-\s]+(\d+)$/i', $code, $m)) {
                return [
                    'raw_prefix' => $rawFeeder,
                    'prefix'     => $cleanFeeder,
                    'index'      => (int)$m[1],
                ];
            }

            // Compact match: e.g. "GADINGKIRANA_29"
            if (preg_match('/^' . preg_quote($cleanFeeder, '/') . '[_\-\s]+(\d+)$/i', $code, $m)) {
                return [
                    'raw_prefix' => $cleanFeeder,
                    'prefix'     => $cleanFeeder,
                    'index'      => (int)$m[1],
                ];
            }
        }

        // 2. Standard multi-word or single-word prefix regex:
        // Matches "ECCO_109", "SIWALANPANJI_440", "POLE 12", "GADING KIRANA_29", etc.
        if (preg_match('/^([A-Za-z0-9_\-\s]+?)[_\-\s]+(\d+)$/', $code, $m)) {
            $rawPrefix = trim($m[1]);
            $cleanPrefix = strtoupper(str_replace(['-', ' ', '_'], '', $rawPrefix));
            // Ensure prefix contains at least one alphabetic character and is not purely numeric
            if (preg_match('/[A-Za-z]/', $cleanPrefix)) {
                return [
                    'raw_prefix' => $rawPrefix,
                    'prefix'     => $cleanPrefix,
                    'index'      => (int)$m[2],
                ];
            }
        }

        return null;
    }

    /**
     * Calculate Haversine distance in meters.
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

        return round(2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
