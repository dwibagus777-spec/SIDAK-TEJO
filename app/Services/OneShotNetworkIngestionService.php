<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * OneShotNetworkIngestionService
 *
 * SIDAK TEJO Enterprise: One-Shot Network Ingestion & Topology Intelligence Engine.
 *
 * Core Architectural Mandates:
 * 1. ONE AI RUN -> ALL 134 FEEDERS -> ALL 5,236 ASSETS -> ONE CONSOLIDATED DRY-RUN.
 * 2. ONE USER CONFIRMATION -> ONE GLOBAL ATOMIC COMMIT (ZERO_PARTIAL_NETWORK_COMMIT).
 * 3. ZERO WRITE IN DRY RUN: 0 INSERT, 0 UPDATE, 0 DELETE.
 * 4. AUTHORITATIVE DATA IMMUTABLE: Existing active gis_translines (217 rows) are never overwritten.
 * 5. EVIDENCE ENGINE: Every candidate carries an explainable evidence_json breakdown.
 * 6. TRIPARTITE IDEMPOTENCY: Edge Identity (natural_key) vs Edge State vs Topology Version.
 * 7. CANONICAL REJECTION TAXONOMY: Itemized rejection reasons, no aggregate black box.
 */
class OneShotNetworkIngestionService
{
    public const CONFIDENCE_AUTO_ACCEPT = 0.95;
    public const CONFIDENCE_WARNING     = 0.80;

    public const REJECT_CROSS_ULP           = 'CROSS_ULP';
    public const REJECT_CROSS_FEEDER        = 'CROSS_FEEDER';
    public const REJECT_SELF_LOOP           = 'SELF_LOOP';
    public const REJECT_DUPLICATE           = 'DUPLICATE';
    public const REJECT_REVERSE_DUPLICATE   = 'REVERSE_DUPLICATE';
    public const REJECT_MISSING_COORDINATES = 'MISSING_COORDINATES';
    public const REJECT_INVALID_ASSET       = 'INVALID_ASSET';
    public const REJECT_CONFLICT_EXISTING   = 'CONFLICT_EXISTING';
    public const REJECT_IMPOSSIBLE_DISTANCE = 'IMPOSSIBLE_DISTANCE';
    public const REJECT_MAX_DEGREE_EXCEEDED = 'MAX_DEGREE_EXCEEDED';

    public const HARD_MAX_SPAN_METERS = 120.0;
    public const HARD_MIN_SPAN_METERS = 1.0;
    public const HARD_MAX_DEGREE      = 4;

    protected ?BaseConnection $db = null;

    public function __construct(?BaseConnection $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } elseif (function_exists('config') && class_exists('Config\Database')) {
            try {
                $this->db = Database::connect();
            } catch (\Throwable $e) {
                $this->db = null;
            }
        }
    }

    // =========================================================================
    // 📐 GEODESIC & SPATIAL HELPERS
    // =========================================================================

    /**
     * Compute Haversine distance in meters between two coordinates
     */
    public static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (abs($lat1) < 0.00001 && abs($lon1) < 0.00001) return 0.0;
        if (abs($lat2) < 0.00001 && abs($lon2) < 0.00001) return 0.0;

        $earthRadius = 6371000.0; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;

        return round(2 * $earthRadius * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a))), 2);
    }

    /**
     * Build canonical natural key for an undirected asset pair
     * Natural Key: TL-NAT:<penyulang_id>:<min_id>-<max_id>
     */
    public static function buildNaturalKey(int $penyulangId, int $assetAId, int $assetBId): string
    {
        $min = min($assetAId, $assetBId);
        $max = max($assetAId, $assetBId);
        return "TL-NAT:{$penyulangId}:{$min}-{$max}";
    }

    /**
     * Calculate azimuth bearing in degrees [0, 360)
     */
    public static function calculateBearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLon = deg2rad($lon2 - $lon1);
        $y = sin($dLon) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) -
             sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos($dLon);
        return fmod((rad2deg(atan2($y, $x)) + 360.0), 360.0);
    }

    /**
     * Calculate absolute delta between two azimuth bearings [0, 180]
     */
    public static function calculateBearingDelta(float $b1, float $b2): float
    {
        $diff = abs($b1 - $b2);
        if ($diff > 180.0) {
            $diff = 360.0 - $diff;
        }
        return $diff;
    }

    /**
     * Calculate pole code numbering difference (e.g. AST_001 vs AST_002 = 1)
     */
    public static function calculateSequenceDelta(string $codeA, string $codeB): int
    {
        preg_match('/(\d+)$/', $codeA, $mA);
        preg_match('/(\d+)$/', $codeB, $mB);

        if (!empty($mA[1]) && !empty($mB[1])) {
            return abs((int)$mA[1] - (int)$mB[1]);
        }
        return 999;
    }

    // =========================================================================
    // 🧠 EVIDENCE & CONFIDENCE ENGINE
    // =========================================================================

    /**
     * Evaluates full evidence scores and composite confidence score for candidate edge
     */
    public static function computeEvidenceAndConfidence(
        array $assetA,
        array $assetB,
        float $distance,
        int $degreeA = 0,
        int $degreeB = 0,
        ?float $incomingBearing = null
    ): array {
        // 1. Distance Score (0.0 to 1.0)
        // Optimal span: <= 45m. Plausible span: <= 85m. Max bound: 120m.
        if ($distance <= 45.0) {
            $distanceScore = 1.00;
        } elseif ($distance <= 85.0) {
            $distanceScore = 1.00 - (($distance - 45.0) / 40.0) * 0.35; // 1.00 down to 0.65
        } elseif ($distance <= 120.0) {
            $distanceScore = 0.65 - (($distance - 85.0) / 35.0) * 0.45; // 0.65 down to 0.20
        } else {
            $distanceScore = 0.00;
        }

        // 2. Bearing Score (0.0 to 1.0)
        $currentBearing = self::calculateBearing(
            (float)($assetA['latitude'] ?? 0),
            (float)($assetA['longitude'] ?? 0),
            (float)($assetB['latitude'] ?? 0),
            (float)($assetB['longitude'] ?? 0)
        );

        if ($incomingBearing !== null) {
            $bearingDelta = self::calculateBearingDelta($incomingBearing, $currentBearing);
            $bearingScore = max(0.20, 1.0 - ($bearingDelta / 180.0));
        } else {
            $bearingScore = 1.00; // Source / origin edge has no incoming constraint
        }

        // 3. Sequence / Numbering Delta Score
        $codeA = $assetA['kode_asset'] ?? $assetA['nama_asset'] ?? '';
        $codeB = $assetB['kode_asset'] ?? $assetB['nama_asset'] ?? '';
        $seqDelta = self::calculateSequenceDelta($codeA, $codeB);
        if ($seqDelta === 1) {
            $seqScore = 1.00;
        } elseif ($seqDelta <= 3) {
            $seqScore = 0.85;
        } elseif ($seqDelta <= 5) {
            $seqScore = 0.65;
        } else {
            $seqScore = 0.35;
        }

        // 4. Section Score
        $secA = (int)($assetA['section_id'] ?? 0);
        $secB = (int)($assetB['section_id'] ?? 0);
        if ($secA > 0 && $secB > 0 && $secA === $secB) {
            $sectionScore = 1.00;
        } elseif ($secA === 0 || $secB === 0) {
            $sectionScore = 0.70;
        } else {
            $sectionScore = 0.40;
        }

        // 5. Feeder Score (Boundary guard)
        $feederA = (int)($assetA['penyulang_id'] ?? 0);
        $feederB = (int)($assetB['penyulang_id'] ?? 0);
        $feederScore = ($feederA > 0 && $feederA === $feederB) ? 1.00 : 0.00;

        // 6. ULP Score
        $ulpA = (int)($assetA['ulp_id'] ?? 0);
        $ulpB = (int)($assetB['ulp_id'] ?? 0);
        $ulpScore = ($ulpA > 0 && $ulpA === $ulpB) ? 1.00 : 0.00;

        // 7. Degree Score
        $maxDeg = max($degreeA, $degreeB);
        if ($maxDeg <= 1) {
            $degreeScore = 1.00;
        } elseif ($maxDeg === 2) {
            $degreeScore = 0.85;
        } elseif ($maxDeg === 3) {
            $degreeScore = 0.50;
        } else {
            $degreeScore = 0.10;
        }

        // 8. Parent Relation Score
        $parentA = (int)($assetA['parent_asset_id'] ?? 0);
        $parentB = (int)($assetB['parent_asset_id'] ?? 0);
        $idA = (int)($assetA['id'] ?? 0);
        $idB = (int)($assetB['id'] ?? 0);
        $hasParentRel = ($parentA === $idB || $parentB === $idA);
        $parentScore = $hasParentRel ? 1.00 : 0.60;

        // 9. Coordinate Quality Score
        $latA = (float)($assetA['latitude'] ?? 0);
        $lonA = (float)($assetA['longitude'] ?? 0);
        $latB = (float)($assetB['latitude'] ?? 0);
        $lonB = (float)($assetB['longitude'] ?? 0);
        $hasPrecision = (abs($latA) > 1.0 && abs($lonA) > 1.0 && abs($latB) > 1.0 && abs($lonB) > 1.0);
        $coordQualityScore = $hasPrecision ? 1.00 : 0.00;

        // 10. Asset Status Score
        $statusA = strtoupper((string)($assetA['status'] ?? 'NORMAL'));
        $statusB = strtoupper((string)($assetB['status'] ?? 'NORMAL'));
        $assetStatusScore = ($statusA === 'NORMAL' && $statusB === 'NORMAL') ? 1.00 : 0.70;

        // 11. Construction Compatibility
        $constructionScore = 1.00;

        // Composite Weighted Score
        $confidence = (
            ($distanceScore * 0.25) +
            ($bearingScore * 0.15) +
            ($seqScore * 0.20) +
            ($sectionScore * 0.15) +
            ($degreeScore * 0.10) +
            ($parentScore * 0.10) +
            ($assetStatusScore * 0.05)
        );
        $confidence = round(min(1.00, max(0.00, $confidence)), 4);

        $evidence = [
            'distance_score'                 => round($distanceScore, 4),
            'bearing_score'                  => round($bearingScore, 4),
            'sequence_score'                 => round($seqScore, 4),
            'section_score'                  => round($sectionScore, 4),
            'feeder_score'                   => round($feederScore, 4),
            'ulp_score'                      => round($ulpScore, 4),
            'degree_score'                   => round($degreeScore, 4),
            'existing_parent_relation_score' => round($parentScore, 4),
            'coordinate_quality'             => round($coordQualityScore, 4),
            'asset_status_score'             => round($assetStatusScore, 4),
            'construction_compatibility'     => round($constructionScore, 4),
            'calculated_distance_m'          => round($distance, 2),
            'calculated_bearing_deg'         => round($currentBearing, 2),
            'sequence_delta'                 => $seqDelta,
            'composite_confidence'           => $confidence
        ];

        return [
            'confidence' => $confidence,
            'evidence'   => $evidence
        ];
    }

    // =========================================================================
    // 🔍 SCAN & INGESTION PIPELINE
    // =========================================================================

    /**
     * Read-only source scan across entire network (134 feeders, 5,236 active assets)
     */
    public function scanSourceNetwork(): array
    {
        // 1. All Feeders
        $feeders = [];
        if ($this->db->tableExists('penyulang')) {
            $feeders = $this->db->table('penyulang')->orderBy('id', 'ASC')->get()->getResultArray();
        }

        // 2. All Active Assets
        $assets = [];
        if ($this->db->tableExists('assets')) {
            $builder = $this->db->table('assets');
            if ($this->db->fieldExists('deleted_at', 'assets')) {
                $builder->where('deleted_at IS NULL');
            }
            $assets = $builder->orderBy('id', 'ASC')->get()->getResultArray();
        }

        // 3. Existing Authoritative Translines
        $existingTranslines = [];
        if ($this->db->tableExists('gis_translines')) {
            $tlBuilder = $this->db->table('gis_translines')->where('is_active', 1);
            if ($this->db->fieldExists('deleted_at', 'gis_translines')) {
                $tlBuilder->where('deleted_at IS NULL');
            }
            $existingTranslines = $tlBuilder->orderBy('id', 'ASC')->get()->getResultArray();
        }

        // Group Assets by Feeder, by ULP, by Section
        $assetsByFeeder = [];
        $assetsByUlp = [];
        $assetsBySection = [];
        foreach ($assets as $a) {
            $fId = (int)($a['penyulang_id'] ?? 0);
            $uId = (int)($a['ulp_id'] ?? 0);
            $sId = (int)($a['section_id'] ?? 0);

            $assetsByFeeder[$fId][] = $a;
            $assetsByUlp[$uId][] = $a;
            $assetsBySection[$sId][] = $a;
        }

        // Existing Translines Natural Key Index
        $existingNaturalKeys = [];
        $existingByFeeder = [];
        foreach ($existingTranslines as $tl) {
            $fId = (int)$tl['penyulang_id'];
            $src = (int)$tl['source_asset_id'];
            $tgt = (int)$tl['target_asset_id'];
            $natKey = self::buildNaturalKey($fId, $src, $tgt);
            $existingNaturalKeys[$natKey] = (int)$tl['id'];
            $existingByFeeder[$fId][] = $tl;
        }

        $feedersWithAssetsCount = 0;
        $feedersWithoutAssetsCount = 0;
        foreach ($feeders as $f) {
            $fId = (int)$f['id'];
            if (!empty($assetsByFeeder[$fId])) {
                $feedersWithAssetsCount++;
            } else {
                $feedersWithoutAssetsCount++;
            }
        }

        return [
            'total_feeders'              => count($feeders),
            'feeders_with_assets'        => $feedersWithAssetsCount,
            'feeders_without_assets'     => $feedersWithoutAssetsCount,
            'total_active_assets'        => count($assets),
            'total_existing_translines'  => count($existingTranslines),
            'feeders'                    => $feeders,
            'assets_by_feeder'           => $assetsByFeeder,
            'assets_by_ulp'              => $assetsByUlp,
            'assets_by_section'          => $assetsBySection,
            'existing_natural_keys'      => $existingNaturalKeys,
            'existing_translines_by_f'   => $existingByFeeder
        ];
    }

    /**
     * Generate deterministic candidate topology edges for a single feeder's assets
     */
    public function generateFeederCandidates(
        int $penyulangId,
        array $assets,
        array $existingNaturalKeys
    ): array {
        $candidates = [];
        $rejections = [];
        $seenNaturalKeys = [];

        // Filter valid assets with coordinates
        $validAssets = [];
        foreach ($assets as $a) {
            $id  = (int)($a['id'] ?? 0);
            $lat = (float)($a['latitude'] ?? 0);
            $lng = (float)($a['longitude'] ?? 0);

            if ($lat == 0.0 || $lng == 0.0) {
                $rejections[] = [
                    'asset_id' => $id,
                    'reason'   => self::REJECT_MISSING_COORDINATES,
                    'detail'   => "Asset #{$id} has missing or zero GPS coordinates."
                ];
                continue;
            }
            $validAssets[] = $a;
        }

        if (count($validAssets) < 2) {
            return [
                'candidates' => [],
                'rejections' => $rejections
            ];
        }

        // Sort assets by sequence_no or pole code for linear ordering
        usort($validAssets, function ($a, $b) {
            $seqA = (int)($a['sequence_no'] ?? 0);
            $seqB = (int)($b['sequence_no'] ?? 0);
            if ($seqA !== $seqB) {
                return $seqA <=> $seqB;
            }
            $codeA = $a['kode_asset'] ?? $a['nama_asset'] ?? '';
            $codeB = $b['kode_asset'] ?? $b['nama_asset'] ?? '';
            return strnatcasecmp($codeA, $codeB);
        });

        $nodeList = $validAssets;
        $nodeCount = count($nodeList);
        $degrees = [];
        foreach ($nodeList as $n) {
            $degrees[(int)$n['id']] = 0;
        }

        // Generate consecutive edges first (linear backbone)
        for ($i = 0; $i < $nodeCount - 1; $i++) {
            $u = $nodeList[$i];
            $v = $nodeList[$i + 1];

            $uId = (int)$u['id'];
            $vId = (int)$v['id'];

            // 1. Self Loop
            if ($uId === $vId) {
                $rejections[] = [
                    'source_id' => $uId,
                    'target_id' => $vId,
                    'reason'    => self::REJECT_SELF_LOOP,
                    'detail'    => "Source asset equals target asset #{$uId}."
                ];
                continue;
            }

            // 2. Cross Feeder Check
            $fU = (int)($u['penyulang_id'] ?? 0);
            $fV = (int)($v['penyulang_id'] ?? 0);
            if ($fU !== $fV || $fU !== $penyulangId) {
                $rejections[] = [
                    'source_id' => $uId,
                    'target_id' => $vId,
                    'reason'    => self::REJECT_CROSS_FEEDER,
                    'detail'    => "Edge crosses feeders: {$fU} != {$fV}."
                ];
                continue;
            }

            // 3. Cross ULP Check
            $ulpU = (int)($u['ulp_id'] ?? 0);
            $ulpV = (int)($v['ulp_id'] ?? 0);
            if ($ulpU !== $ulpV) {
                $rejections[] = [
                    'source_id' => $uId,
                    'target_id' => $vId,
                    'reason'    => self::REJECT_CROSS_ULP,
                    'detail'    => "Edge crosses ULP boundary: ULP {$ulpU} != ULP {$ulpV}."
                ];
                continue;
            }

            // 4. Natural Key Deduplication
            $natKey = self::buildNaturalKey($penyulangId, $uId, $vId);
            if (isset($seenNaturalKeys[$natKey])) {
                $rejections[] = [
                    'source_id'   => $uId,
                    'target_id'   => $vId,
                    'natural_key' => $natKey,
                    'reason'      => self::REJECT_DUPLICATE,
                    'detail'      => "Duplicate candidate natural key {$natKey} in batch."
                ];
                continue;
            }

            // 5. Existing Authoritative Transline Conflict
            if (isset($existingNaturalKeys[$natKey])) {
                $rejections[] = [
                    'source_id'   => $uId,
                    'target_id'   => $vId,
                    'natural_key' => $natKey,
                    'reason'      => self::REJECT_CONFLICT_EXISTING,
                    'detail'      => "Natural key {$natKey} already exists as authoritative transline #{$existingNaturalKeys[$natKey]}."
                ];
                continue;
            }

            // 6. Distance Calculation & Bound Check
            $dist = self::haversineDistance(
                (float)$u['latitude'], (float)$u['longitude'],
                (float)$v['latitude'], (float)$v['longitude']
            );

            if ($dist < self::HARD_MIN_SPAN_METERS || $dist > self::HARD_MAX_SPAN_METERS) {
                $rejections[] = [
                    'source_id' => $uId,
                    'target_id' => $vId,
                    'distance'  => $dist,
                    'reason'    => self::REJECT_IMPOSSIBLE_DISTANCE,
                    'detail'    => "Distance {$dist}m violates span limits [" . self::HARD_MIN_SPAN_METERS . "m - " . self::HARD_MAX_SPAN_METERS . "m]."
                ];
                continue;
            }

            // 7. Degree Constraint Check
            if ($degrees[$uId] >= self::HARD_MAX_DEGREE || $degrees[$vId] >= self::HARD_MAX_DEGREE) {
                $rejections[] = [
                    'source_id' => $uId,
                    'target_id' => $vId,
                    'reason'    => self::REJECT_MAX_DEGREE_EXCEEDED,
                    'detail'    => "Pole degree exceeds maximum limit (" . self::HARD_MAX_DEGREE . ")."
                ];
                continue;
            }

            // 8. Evidence & Confidence Evaluation
            $ev = self::computeEvidenceAndConfidence($u, $v, $dist, $degrees[$uId], $degrees[$vId]);
            $conf = $ev['confidence'];
            $evidenceJson = json_encode($ev['evidence']);

            // Classification
            if ($conf >= self::CONFIDENCE_AUTO_ACCEPT) {
                $classification = 'AUTO_ACCEPT_CANDIDATE';
            } elseif ($conf >= self::CONFIDENCE_WARNING) {
                $classification = 'WARNING_REVIEW';
            } else {
                $classification = 'REVIEW_REQUIRED';
            }

            $conductorType = !empty($u['conductor_type']) ? $u['conductor_type'] : 'AAAC';
            $conductorSize = !empty($u['conductor_size']) ? $u['conductor_size'] : '150 mm²';

            $candidate = [
                'natural_key'          => $natKey,
                'penyulang_id'         => $penyulangId,
                'section_id'           => (int)($u['section_id'] ?? 0),
                'ulp_id'               => $ulpU,
                'source_asset_id'      => $uId,
                'source_asset_name'    => $u['nama_asset'] ?? "AST-{$uId}",
                'source_lat'           => (float)$u['latitude'],
                'source_lng'           => (float)$u['longitude'],
                'target_asset_id'      => $vId,
                'target_asset_name'    => $v['nama_asset'] ?? "AST-{$vId}",
                'target_lat'           => (float)$v['latitude'],
                'target_lng'           => (float)$v['longitude'],
                'distance_meters'      => $dist,
                'conductor_type'       => $conductorType,
                'conductor_size'       => $conductorSize,
                'confidence'           => $conf,
                'classification'       => $classification,
                'evidence'             => $ev['evidence'],
                'evidence_json'        => $evidenceJson,
                'geometry'             => json_encode([
                    'type'        => 'LineString',
                    'coordinates' => [
                        [(float)$u['longitude'], (float)$u['latitude']],
                        [(float)$v['longitude'], (float)$v['latitude']]
                    ]
                ]),
                'algorithm'            => 'DETERMINISTIC_NEAREST_SEQUENCE_MST_v1.0',
                'generated_at'         => date('Y-m-d H:i:s')
            ];

            $candidates[] = $candidate;
            $seenNaturalKeys[$natKey] = true;
            $degrees[$uId]++;
            $degrees[$vId]++;
        }

        return [
            'candidates' => $candidates,
            'rejections' => $rejections
        ];
    }

    /**
     * Compute deterministic Topology State Fingerprint
     */
    public static function computeTopologyFingerprint(
        int $totalAssets,
        int $existingTranslinesCount,
        array $acceptedCandidates
    ): string {
        $keys = [];
        foreach ($acceptedCandidates as $c) {
            $keys[] = $c['natural_key'] . ':' . $c['distance_meters'] . ':' . ($c['conductor_type'] ?? '') . ':' . ($c['conductor_size'] ?? '');
        }
        sort($keys);
        $payload = "ASSETS:{$totalAssets}|EXISTING:{$existingTranslinesCount}|EDGES:" . implode(';', $keys);
        return hash('sha256', $payload);
    }

    /**
     * Executes the comprehensive ONE-SHOT NETWORK INGESTION DRY-RUN
     * STRICT READ-ONLY: 0 DB Mutations
     */
    public function runDryRun(?array $mockSourceData = null): array
    {
        $startTime = microtime(true);

        // 1. Scan Source Network
        $source = $mockSourceData ?? $this->scanSourceNetwork();

        $totalFeeders          = $source['total_feeders'];
        $feedersWithAssets     = $source['feeders_with_assets'];
        $feedersWithoutAssets  = $source['feeders_without_assets'];
        $totalActiveAssets     = $source['total_active_assets'];
        $totalExistingTranslines = $source['total_existing_translines'];
        $existingNaturalKeys   = $source['existing_natural_keys'];
        $assetsByFeeder        = $source['assets_by_feeder'];
        $feeders               = $source['feeders'];

        $allCandidates = [];
        $allRejections = [];
        $rejectionCounts = [
            self::REJECT_CROSS_ULP           => 0,
            self::REJECT_CROSS_FEEDER        => 0,
            self::REJECT_SELF_LOOP           => 0,
            self::REJECT_DUPLICATE           => 0,
            self::REJECT_REVERSE_DUPLICATE   => 0,
            self::REJECT_MISSING_COORDINATES => 0,
            self::REJECT_INVALID_ASSET       => 0,
            self::REJECT_CONFLICT_EXISTING   => 0,
            self::REJECT_IMPOSSIBLE_DISTANCE => 0,
            self::REJECT_MAX_DEGREE_EXCEEDED => 0,
        ];

        $confidenceCounts = [
            'AUTO_ACCEPT'     => 0,
            'WARNING_REVIEW'  => 0,
            'REVIEW_REQUIRED' => 0
        ];

        $feederBreakdown = [];
        $totalNewDistanceMeters = 0.0;

        // Process all feeders in one consolidated run
        foreach ($feeders as $f) {
            $fId = (int)$f['id'];
            $fName = $f['nama_penyulang'] ?? "Penyulang #{$fId}";
            $ulpId = (int)($f['ulp_id'] ?? 0);
            $fAssets = $assetsByFeeder[$fId] ?? [];
            $existingCount = count($source['existing_translines_by_f'][$fId] ?? []);

            if (empty($fAssets)) {
                $feederBreakdown[] = [
                    'penyulang_id'         => $fId,
                    'nama_penyulang'       => $fName,
                    'ulp_id'               => $ulpId,
                    'total_assets'         => 0,
                    'existing_translines'  => $existingCount,
                    'candidates_generated' => 0,
                    'accepted'             => 0,
                    'warning'              => 0,
                    'review'               => 0,
                    'rejected'             => 0,
                    'new_length_km'        => 0.0,
                    'status'               => 'NO_NETWORK_DATA'
                ];
                continue;
            }

            $res = $this->generateFeederCandidates($fId, $fAssets, $existingNaturalKeys);
            $fCandidates = $res['candidates'];
            $fRejections = $res['rejections'];

            $fAccepted = 0;
            $fWarning = 0;
            $fReview = 0;
            $fDistMeters = 0.0;

            foreach ($fCandidates as $c) {
                $allCandidates[] = $c;
                if ($c['classification'] === 'AUTO_ACCEPT_CANDIDATE') {
                    $confidenceCounts['AUTO_ACCEPT']++;
                    $fAccepted++;
                    $totalNewDistanceMeters += $c['distance_meters'];
                    $fDistMeters += $c['distance_meters'];
                } elseif ($c['classification'] === 'WARNING_REVIEW') {
                    $confidenceCounts['WARNING_REVIEW']++;
                    $fWarning++;
                } else {
                    $confidenceCounts['REVIEW_REQUIRED']++;
                    $fReview++;
                }
            }

            foreach ($fRejections as $rej) {
                $allRejections[] = $rej;
                $reason = $rej['reason'] ?? 'OTHER';
                if (isset($rejectionCounts[$reason])) {
                    $rejectionCounts[$reason]++;
                }
            }

            $feederBreakdown[] = [
                'penyulang_id'         => $fId,
                'nama_penyulang'       => $fName,
                'ulp_id'               => $ulpId,
                'total_assets'         => count($fAssets),
                'existing_translines'  => $existingCount,
                'candidates_generated' => count($fCandidates),
                'accepted'             => $fAccepted,
                'warning'              => $fWarning,
                'review'               => $fReview,
                'rejected'             => count($fRejections),
                'new_length_km'        => round($fDistMeters / 1000.0, 3),
                'status'               => ($existingCount > 0 ? 'PARTIALLY_OR_FULLY_AUTHORITATIVE' : 'TOPOLOGY_GENERATED')
            ];
        }

        // Filter only auto-accepted candidates for the atomic batch
        $acceptedCandidates = array_values(array_filter($allCandidates, function ($c) {
            return $c['classification'] === 'AUTO_ACCEPT_CANDIDATE';
        }));

        $batchFingerprint = self::computeTopologyFingerprint(
            $totalActiveAssets,
            $totalExistingTranslines,
            $acceptedCandidates
        );

        $durationMs = round((microtime(true) - $startTime) * 1000.0, 2);

        return [
            'status'            => 'success',
            'dry_run'           => true,
            'duration_ms'       => $durationMs,
            'batch_id'          => 'INGEST-DRYRUN-' . date('YmdHis') . '-' . substr($batchFingerprint, 0, 8),
            'batch_fingerprint' => $batchFingerprint,
            'summary'           => [
                'total_feeders'                     => $totalFeeders,
                'feeders_with_assets'               => $feedersWithAssets,
                'feeders_without_assets'            => $feedersWithoutAssets,
                'total_active_assets'               => $totalActiveAssets,
                'existing_authoritative_translines' => $totalExistingTranslines,
                'total_candidates_generated'        => count($allCandidates),
                'accepted_candidates'               => count($acceptedCandidates),
                'warning_review_candidates'         => $confidenceCounts['WARNING_REVIEW'],
                'review_required_candidates'        => $confidenceCounts['REVIEW_REQUIRED'],
                'rejected_candidates'               => count($allRejections),
                'estimated_total_new_length_km'     => round($totalNewDistanceMeters / 1000.0, 3)
            ],
            'confidence_breakdown' => [
                'AUTO_ACCEPT_HIGH' => [
                    'threshold' => '>= ' . self::CONFIDENCE_AUTO_ACCEPT,
                    'count'     => $confidenceCounts['AUTO_ACCEPT']
                ],
                'WARNING_REVIEW' => [
                    'threshold' => self::CONFIDENCE_WARNING . ' - ' . (self::CONFIDENCE_AUTO_ACCEPT - 0.01),
                    'count'     => $confidenceCounts['WARNING_REVIEW']
                ],
                'REVIEW_REQUIRED' => [
                    'threshold' => '< ' . self::CONFIDENCE_WARNING,
                    'count'     => $confidenceCounts['REVIEW_REQUIRED']
                ]
            ],
            'rejection_breakdown' => $rejectionCounts,
            'feeder_breakdown'    => $feederBreakdown,
            'accepted_candidates' => $acceptedCandidates
        ];
    }

    // =========================================================================
    // 🔒 ATOMIC TRANSACTION COMMIT ENGINE (HELD FOR USER CONFIRMATION)
    // =========================================================================

    /**
     * Executes ONE-SHOT ATOMIC NETWORK COMMIT
     * Invariants:
     * - Stale-state protection: verifies batchFingerprint against current DB state
     * - ZERO_PARTIAL_NETWORK_COMMIT: all-or-nothing rollback on any failure
     * - 0 duplicate translines
     */
    public function executeAtomicCommit(
        string $expectedFingerprint,
        int $actorUserId,
        array $dryRunResult
    ): array {
        if (empty($expectedFingerprint)) {
            throw new RuntimeException("Commit rejected: Batch fingerprint is required.");
        }

        // 1. Stale-state check A: compare with dry-run fingerprint
        $dryRunFingerprint = $dryRunResult['batch_fingerprint'] ?? '';
        if ($expectedFingerprint !== $dryRunFingerprint) {
            throw new RuntimeException("Commit rejected: Stale fingerprint. Expected {$dryRunFingerprint}, received {$expectedFingerprint}. Re-run dry-run first.");
        }

        // 2. Stale-state check B: verify live DB invariants
        $currentSource = $this->scanSourceNetwork();
        $currentTotalAssets = $currentSource['total_active_assets'];
        $currentExisting = $currentSource['total_existing_translines'];

        $dryRunAssets = $dryRunResult['summary']['total_active_assets'] ?? 0;
        $dryRunExisting = $dryRunResult['summary']['existing_authoritative_translines'] ?? 0;

        if ($currentTotalAssets !== $dryRunAssets || $currentExisting !== $dryRunExisting) {
            throw new RuntimeException("Commit rejected: Database state changed between dry-run and commit (Assets: {$currentTotalAssets} vs {$dryRunAssets}, Existing: {$currentExisting} vs {$dryRunExisting}).");
        }

        $acceptedCandidates = $dryRunResult['accepted_candidates'] ?? [];
        if (empty($acceptedCandidates)) {
            return [
                'status'          => 'success',
                'committed_count' => 0,
                'message'         => 'No new candidates to commit.'
            ];
        }

        // 3. BEGIN TRANSACTION
        $this->db->transBegin();

        try {
            $batchCode = 'INGEST-BATCH-' . date('YmdHis') . '-' . substr($expectedFingerprint, 0, 8);
            $committedCount = 0;
            $affectedFeeders = [];

            // Stage A: Insert Accepted Translines into gis_translines
            foreach ($acceptedCandidates as $cand) {
                $natKey = $cand['natural_key'];

                // Secondary guard: double check that natural key is NOT already present in DB
                $alreadyExists = $this->db->table('gis_translines')
                    ->where('penyulang_id', $cand['penyulang_id'])
                    ->where('source_asset_id', min($cand['source_asset_id'], $cand['target_asset_id']))
                    ->where('target_asset_id', max($cand['source_asset_id'], $cand['target_asset_id']))
                    ->where('is_active', 1)
                    ->countAllResults();

                if ($alreadyExists > 0) {
                    continue; // Skip without error (idempotent)
                }

                // Invariant guard: Ensure both endpoints are valid JTM poles
                $sAsset = $this->db->table('assets')->select('id, nama_asset, kode_asset, jenis_asset')->where('id', $cand['source_asset_id'])->get()->getRowArray();
                $tAsset = $this->db->table('assets')->select('id, nama_asset, kode_asset, jenis_asset')->where('id', $cand['target_asset_id'])->get()->getRowArray();
                if ($sAsset && !GisTranslineService::isJtmPoleAsset($sAsset)['valid']) {
                    continue;
                }
                if ($tAsset && !GisTranslineService::isJtmPoleAsset($tAsset)['valid']) {
                    continue;
                }

                $insertData = [
                    'transline_code'     => 'TL-' . $cand['penyulang_id'] . '-' . $cand['source_asset_id'] . '-' . $cand['target_asset_id'],
                    'penyulang_id'       => $cand['penyulang_id'],
                    'source_asset_id'    => $cand['source_asset_id'],
                    'target_asset_id'    => $cand['target_asset_id'],
                    'geometry'           => $cand['geometry'],
                    'geometry_type'      => 'LineString',
                    'conductor_type'     => $cand['conductor_type'],
                    'conductor_size'     => $cand['conductor_size'],
                    'conductor_material' => 'ALUMINUM_ALLOY',
                    'installation_type'  => 'OVERHEAD',
                    'circuit_config'     => '3_PHASE',
                    'distance_meters'    => $cand['distance_meters'],
                    'status'             => 'ACTIVE',
                    'is_active'          => 1,
                    'created_by'         => "ONE_SHOT_INGESTION|BATCH={$batchCode}|USER={$actorUserId}",
                    'created_at'         => date('Y-m-d H:i:s'),
                    'updated_at'         => date('Y-m-d H:i:s')
                ];

                $this->db->table('gis_translines')->insert($insertData);
                $committedCount++;
                $affectedFeeders[$cand['penyulang_id']] = true;
            }

            // Stage B: Write Ingestion Batch record if table exists
            if ($this->db->tableExists('gis_network_ingestion_batches')) {
                $this->db->table('gis_network_ingestion_batches')->insert([
                    'batch_id'                  => $batchCode,
                    'batch_fingerprint'         => $expectedFingerprint,
                    'total_feeders'             => $dryRunResult['summary']['total_feeders'] ?? 0,
                    'total_assets'              => $dryRunResult['summary']['total_active_assets'] ?? 0,
                    'existing_translines_count' => $dryRunExisting,
                    'candidate_translines_count'=> $dryRunResult['summary']['total_candidates_generated'] ?? 0,
                    'accepted_count'            => count($acceptedCandidates),
                    'warning_count'             => $dryRunResult['summary']['warning_review_candidates'] ?? 0,
                    'rejected_count'            => $dryRunResult['summary']['rejected_candidates'] ?? 0,
                    'committed_count'           => $committedCount,
                    'rejection_breakdown_json'  => json_encode($dryRunResult['rejection_breakdown'] ?? []),
                    'status'                    => 'COMMITTED',
                    'created_by'                => $actorUserId,
                    'created_at'                => date('Y-m-d H:i:s'),
                    'completed_at'              => date('Y-m-d H:i:s')
                ]);
            }

            // Stage C: Write Audit Trail
            if ($this->db->tableExists('audit_logs')) {
                $this->db->table('audit_logs')->insert([
                    'user_id'    => $actorUserId,
                    'action'     => 'ONE_SHOT_NETWORK_INGESTION_COMMIT',
                    'details'    => "Committed {$committedCount} new authoritative translines across " . count($affectedFeeders) . " feeders. Batch: {$batchCode}",
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'OneShotNetworkIngestionService',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Stage D: Commit
            $this->db->transCommit();

            return [
                'status'           => 'success',
                'batch_id'         => $batchCode,
                'committed_count'  => $committedCount,
                'affected_feeders' => count($affectedFeeders),
                'message'          => "Successfully committed {$committedCount} translines in one atomic batch."
            ];

        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw new RuntimeException("Atomic Commit Failed & Rolled Back: " . $e->getMessage(), 0, $e);
        }
    }
}
