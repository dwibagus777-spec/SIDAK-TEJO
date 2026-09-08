<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Service for Deterministic Transline Completion (TL-01 Phase 1)
 *
 * Core Principles:
 * - STRICT READ-ONLY: 0 INSERT, 0 UPDATE, 0 DELETE.
 * - DETERMINISTIC FIRST: Zero AI hallucination, mathematical classification.
 * - IDEMPOTENT: Identical input + database state => identical candidate output.
 * - GOVERNED STATUSES: AUTO_MATCH, NEEDS_REVIEW, MISSING, INVALID.
 */
class TranslineCompletionService
{
    public const STATUS_AUTO_MATCH   = 'AUTO_MATCH';
    public const STATUS_NEEDS_REVIEW = 'NEEDS_REVIEW';
    public const STATUS_MISSING      = 'MISSING';
    public const STATUS_INVALID      = 'INVALID';

    public const REASON_EXISTING_VALID_PAIR          = 'EXISTING_VALID_PAIR';
    public const REASON_DETERMINISTIC_MISSING_EDGE   = 'DETERMINISTIC_MISSING_EDGE';
    public const REASON_INVALID_STORED_DISTANCE      = 'INVALID_STORED_DISTANCE';
    public const REASON_INVALID_COORDINATE           = 'INVALID_COORDINATE';
    public const REASON_CONDUCTOR_SPEC_CONFLICT      = 'CONDUCTOR_SPEC_CONFLICT';
    public const REASON_BRANCHING_AMBIGUITY          = 'BRANCHING_AMBIGUITY';
    public const REASON_NO_DETERMINISTIC_ORDER       = 'NO_DETERMINISTIC_ORDER';
    public const REASON_DISTANCE_PLAUSIBILITY_WARN   = 'DISTANCE_PLAUSIBILITY_WARNING';
    public const REASON_CROSS_FEEDER_ILLEGAL         = 'CROSS_FEEDER_ILLEGAL_RELATIONSHIP';
    public const REASON_CROSS_SECTION_VIOLATION      = 'CROSS_SECTION_VIOLATION';
    public const REASON_EMPTY_SECTION                = 'EMPTY_SECTION';
    public const REASON_SINGLE_ASSET                 = 'SINGLE_ASSET_SECTION';

    protected BaseConnection $db;
    protected GisTranslineService $translineService;

    public function __construct(?BaseConnection $db = null, ?GisTranslineService $translineService = null)
    {
        $this->db = $db ?? Database::connect();
        $this->translineService = $translineService ?? new GisTranslineService($this->db);
    }

    /**
     * Compute Haversine distance in meters between two lat/long points
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

    /**
     * Generate canonical natural key for an undirected asset pair
     */
    public function buildNaturalKey(int $penyulangId, int $assetAId, int $assetBId): string
    {
        $min = min($assetAId, $assetBId);
        $max = max($assetAId, $assetBId);
        return "TL-NAT:{$penyulangId}:{$min}-{$max}";
    }

    /**
     * Normalize conductor size string for deterministic comparison (e.g. "150 mm²" or "150 mm2" => "150")
     */
    public function normalizeConductorSize(?string $size): string
    {
        if ($size === null || trim($size) === '') {
            return '';
        }
        if (preg_match('/(\d+)/', trim($size), $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Normalize conductor type string (e.g. "a3cs" => "A3CS", "aaac" => "AAAC")
     */
    public function normalizeConductorType(?string $type): string
    {
        if ($type === null) return '';
        return strtoupper(trim($type));
    }

    /**
     * Get deterministic Transline completion candidates for a given section
     *
     * @param int $sectionId
     * @param array $options
     * @return array<string, mixed>
     */
    public function getSectionCompletionCandidates(int $sectionId, array $options = []): array
    {
        if ($sectionId <= 0) {
            return [
                'scope'      => ['type' => 'section', 'id' => $sectionId, 'name' => 'INVALID_SCOPE'],
                'summary'    => ['total_candidates' => 0, 'auto_match_count' => 0, 'needs_review_count' => 0, 'missing_count' => 0, 'invalid_count' => 0],
                'candidates' => [],
            ];
        }

        // 1. Fetch Section details
        $section = null;
        if ($this->db->tableExists('sections')) {
            $section = $this->db->table('sections')->where('id', $sectionId)->get()->getRowArray();
        }

        $sectionName = $section['nama_section'] ?? "Section #{$sectionId}";
        $penyulangId = (int)($section['penyulang_id'] ?? 0);

        // Fetch Feeder details if penyulang exists
        $feederName = "Penyulang #{$penyulangId}";
        if ($penyulangId > 0 && $this->db->tableExists('penyulang')) {
            $pRow = $this->db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
            if ($pRow) {
                $feederName = $pRow['nama_penyulang'] ?? $feederName;
            }
        }

        // 2. Fetch Assets in Section
        $assets = [];
        if ($this->db->tableExists('assets')) {
            $builder = $this->db->table('assets')
                ->where('section_id', $sectionId);

            if ($this->db->fieldExists('deleted_at', 'assets')) {
                $builder->where('deleted_at IS NULL');
            }

            // Deterministic base query
            $assets = $builder->get()->getResultArray();
        }

        $assetCount = count($assets);

        // Edge case: empty section
        if ($assetCount === 0) {
            return [
                'scope' => [
                    'type'        => 'section',
                    'id'          => $sectionId,
                    'name'        => $sectionName,
                    'feeder_id'   => $penyulangId,
                    'feeder_name' => $feederName,
                ],
                'summary' => [
                    'total_candidates'  => 0,
                    'auto_match_count'  => 0,
                    'needs_review_count'=> 0,
                    'missing_count'     => 0,
                    'invalid_count'     => 0,
                    'note'              => self::REASON_EMPTY_SECTION,
                ],
                'candidates' => [],
            ];
        }

        // 3. Asset Map & Ordering Determination
        $assetMap = [];
        $hasValidSequence = true;
        $seenSequences = [];
        $distinctSequencesCount = 0;

        foreach ($assets as $a) {
            $id = (int)$a['id'];
            $assetMap[$id] = $a;
            $seq = isset($a['sequence_no']) ? (int)$a['sequence_no'] : 0;
            if ($seq <= 0 || isset($seenSequences[$seq])) {
                $hasValidSequence = false;
            } else {
                $seenSequences[$seq] = $id;
                $distinctSequencesCount++;
            }
        }

        // Check if topology tree (parent_asset_id) provides ordering if sequence_no is missing
        $hasParentPointers = false;
        foreach ($assets as $a) {
            if (!empty($a['parent_asset_id']) && (int)$a['parent_asset_id'] > 0) {
                $hasParentPointers = true;
                break;
            }
        }

        // 4. Fetch All Active GIS Translines in Scope & Boundary Neighbors
        $existingTranslines = [];
        if ($penyulangId > 0 && $this->db->tableExists('gis_translines')) {
            $rows = $this->db->table('gis_translines')
                ->where('penyulang_id', $penyulangId)
                ->where('is_active', 1)
                ->get()
                ->getResultArray();

            $neighborAssetIds = [];
            foreach ($rows as $r) {
                $sId = (int)$r['source_asset_id'];
                $tId = (int)$r['target_asset_id'];
                $pairKey = min($sId, $tId) . '_' . max($sId, $tId);
                $existingTranslines[$pairKey][] = $r;

                if (isset($assetMap[$sId]) && !isset($assetMap[$tId])) {
                    $neighborAssetIds[$tId] = $tId;
                } elseif (isset($assetMap[$tId]) && !isset($assetMap[$sId])) {
                    $neighborAssetIds[$sId] = $sId;
                }
            }

            if (!empty($neighborAssetIds)) {
                $neighbors = $this->db->table('assets')
                    ->whereIn('id', array_values($neighborAssetIds))
                    ->get()
                    ->getResultArray();
                foreach ($neighbors as $nb) {
                    $assetMap[(int)$nb['id']] = $nb;
                }
            }
        }

        // Edge case: single asset in section AND no boundary translines connected
        if ($assetCount === 1 && count($assetMap) === 1) {
            return [
                'scope' => [
                    'type'        => 'section',
                    'id'          => $sectionId,
                    'name'        => $sectionName,
                    'feeder_id'   => $penyulangId,
                    'feeder_name' => $feederName,
                ],
                'summary' => [
                    'total_candidates'  => 0,
                    'auto_match_count'  => 0,
                    'needs_review_count'=> 0,
                    'missing_count'     => 0,
                    'invalid_count'     => 0,
                    'note'              => self::REASON_SINGLE_ASSET,
                ],
                'candidates' => [],
            ];
        }

        // Pre-calculate parent child counts for branching detection
        $parentChildCounts = [];
        foreach ($assetMap as $child) {
            $pId = (int)($child['parent_asset_id'] ?? 0);
            if ($pId > 0) {
                $parentChildCounts[$pId] = ($parentChildCounts[$pId] ?? 0) + 1;
            }
        }

        // 5. Build Candidate Adjacencies
        $evaluatedPairs = [];

        // STRATEGY 1: If valid 1..N continuous sequence exists, order by sequence_no
        if ($hasValidSequence && $distinctSequencesCount === $assetCount) {
            usort($assets, fn($a, $b) => (int)$a['sequence_no'] <=> (int)$b['sequence_no']);

            // Consecutive pairs
            for ($i = 0; $i < $assetCount - 1; $i++) {
                $nodeA = $assets[$i];
                $nodeB = $assets[$i + 1];
                $uKey = min((int)$nodeA['id'], (int)$nodeB['id']) . '_' . max((int)$nodeA['id'], (int)$nodeB['id']);

                $evaluatedPairs[$uKey] = [
                    'source' => $nodeA,
                    'target' => $nodeB,
                    'order'  => (int)$nodeA['sequence_no'],
                ];
            }
        } elseif ($hasParentPointers) {
            // STRATEGY 2: Topological Parent-Child tree
            foreach ($assets as $child) {
                $pId = (int)($child['parent_asset_id'] ?? 0);
                if ($pId > 0 && isset($assetMap[$pId])) {
                    $parent = $assetMap[$pId];
                    $uKey = min($pId, (int)$child['id']) . '_' . max($pId, (int)$child['id']);

                    $evaluatedPairs[$uKey] = [
                        'source' => $parent,
                        'target' => $child,
                        'order'  => (int)($child['sequence_no'] ?? (int)$child['id']),
                        'is_branch' => (($parentChildCounts[$pId] ?? 0) > 1),
                    ];
                }
            }
        } else {
            // STRATEGY 3: Check if existing gis_translines link the assets
            $foundAnyExisting = false;
            foreach ($existingTranslines as $pairKey => $tRows) {
                [$sId, $tId] = explode('_', $pairKey);
                $sId = (int)$sId;
                $tId = (int)$tId;

                if (isset($assetMap[$sId]) && isset($assetMap[$tId])) {
                    $foundAnyExisting = true;
                    $evaluatedPairs[$pairKey] = [
                        'source' => $assetMap[$sId],
                        'target' => $assetMap[$tId],
                        'order'  => (int)($assetMap[$sId]['sequence_no'] ?? $sId),
                    ];
                }
            }

            // If no ordering and no existing translines => cannot determine order deterministically
            if (!$foundAnyExisting) {
                return [
                    'scope' => [
                        'type'        => 'section',
                        'id'          => $sectionId,
                        'name'        => $sectionName,
                        'feeder_id'   => $penyulangId,
                        'feeder_name' => $feederName,
                    ],
                    'summary' => [
                        'total_candidates'  => 0,
                        'auto_match_count'  => 0,
                        'needs_review_count'=> 0,
                        'missing_count'     => 0,
                        'invalid_count'     => 0,
                        'note'              => self::REASON_NO_DETERMINISTIC_ORDER,
                    ],
                    'candidates' => [],
                ];
            }
        }

        // Also incorporate any active existing translines within this section that weren't captured by consecutive order
        foreach ($existingTranslines as $pairKey => $tRows) {
            [$sId, $tId] = explode('_', $pairKey);
            $sId = (int)$sId;
            $tId = (int)$tId;

            if (isset($assetMap[$sId]) && isset($assetMap[$tId]) && !isset($evaluatedPairs[$pairKey])) {
                $evaluatedPairs[$pairKey] = [
                    'source' => $assetMap[$sId],
                    'target' => $assetMap[$tId],
                    'order'  => (int)($assetMap[$sId]['sequence_no'] ?? $sId),
                ];
            }
        }

        // Count occurrences of each node in evaluated pairs to detect branching
        $nodeDegree = [];
        foreach ($evaluatedPairs as $pKey => $pData) {
            $sId = (int)$pData['source']['id'];
            $tId = (int)$pData['target']['id'];
            $nodeDegree[$sId] = ($nodeDegree[$sId] ?? 0) + 1;
            $nodeDegree[$tId] = ($nodeDegree[$tId] ?? 0) + 1;
        }

        // 6. Evaluate and Classify Each Pair
        $candidates       = [];
        $autoMatchCount   = 0;
        $needsReviewCount = 0;
        $missingCount     = 0;
        $invalidCount     = 0;

        foreach ($evaluatedPairs as $pairKey => $pairData) {
            $source = $pairData['source'];
            $target = $pairData['target'];
            $sId    = (int)$source['id'];
            $tId    = (int)$target['id'];

            $latA = (float)($source['latitude'] ?? 0);
            $lonA = (float)($source['longitude'] ?? 0);
            $latB = (float)($target['latitude'] ?? 0);
            $lonB = (float)($target['longitude'] ?? 0);

            $naturalKey = $this->buildNaturalKey($penyulangId, $sId, $tId);
            $expectedDist = $this->haversineDistanceMeters($latA, $lonA, $latB, $lonB);

            $status     = self::STATUS_MISSING;
            $reasonCode = self::REASON_DETERMINISTIC_MISSING_EDGE;
            $warnings   = [];

            // A. Check Cross-Feeder Violation
            $sFeeder = (int)($source['penyulang_id'] ?? 0);
            $tFeeder = (int)($target['penyulang_id'] ?? 0);
            if ($sFeeder > 0 && $tFeeder > 0 && $sFeeder !== $tFeeder) {
                $status     = self::STATUS_INVALID;
                $reasonCode = self::REASON_CROSS_FEEDER_ILLEGAL;
                $warnings[] = "Source feeder ({$sFeeder}) berbeda dengan target feeder ({$tFeeder}).";
                $invalidCount++;

                $candidates[] = $this->buildCandidatePayload(
                    $naturalKey, $status, $reasonCode, $penyulangId, $sectionId,
                    $source, $target, null, $expectedDist, $warnings, $pairData['order'] ?? 0
                );
                continue;
            }

            // B. Check Cross-Section Violation
            $sSec = (int)($source['section_id'] ?? 0);
            $tSec = (int)($target['section_id'] ?? 0);
            if ($sSec > 0 && $tSec > 0 && $sSec !== $tSec) {
                $warnings[] = "Boundary span: melintasi Section {$sSec} dan {$tSec}.";
            }

            // C. Check Coordinates Validity
            $coordsInvalid = false;
            if ((abs($latA) < 0.0001 && abs($lonA) < 0.0001) || (abs($latB) < 0.0001 && abs($lonB) < 0.0001)) {
                $coordsInvalid = true;
                $warnings[] = "Koordinat GPS salah satu tiang 0.00 (tidak valid).";
            }

            // D. Check Existing Transline in gis_translines
            $matchingRows = $existingTranslines[$pairKey] ?? [];

            if (!empty($matchingRows)) {
                // If multiple duplicate rows exist in gis_translines for same pair
                if (count($matchingRows) > 1) {
                    $status     = self::STATUS_NEEDS_REVIEW;
                    $reasonCode = self::REASON_BRANCHING_AMBIGUITY;
                    $warnings[] = "Ditemukan " . count($matchingRows) . " record transline ganda untuk pasangan aset ini.";
                } else {
                    $transline = $matchingRows[0];
                    $storedDist = (float)($transline['distance_meters'] ?? 0);
                    $cType = $this->normalizeConductorType($transline['conductor_type'] ?? 'AAAC');

                    // Check Distance Firewall: distance <= 0 is NOT allowed
                    if ($storedDist <= 0.0) {
                        $status     = self::STATUS_NEEDS_REVIEW;
                        $reasonCode = self::REASON_INVALID_STORED_DISTANCE;
                        $warnings[] = "Jarak transline tersimpan bernilai 0.00 m (Perlu dihitung ulang).";
                    } elseif ($coordsInvalid) {
                        $status     = self::STATUS_NEEDS_REVIEW;
                        $reasonCode = self::REASON_INVALID_COORDINATE;
                    } elseif (($nodeDegree[$sId] > 2 || $nodeDegree[$tId] > 2) && !empty($pairData['is_branch'])) {
                        // T-Off Branching Firewall
                        $status     = self::STATUS_NEEDS_REVIEW;
                        $reasonCode = self::REASON_BRANCHING_AMBIGUITY;
                        $warnings[] = "Terdeteksi percabangan (T-Off/Salaman): tiang memiliki lebih dari 2 koneksi cabang.";
                    } else {
                        // Conductor comparison against canonical defaults if available
                        $conductorConflict = false;
                        if (isset($options['survey_conductor_type'])) {
                            $surveyType = $this->normalizeConductorType($options['survey_conductor_type']);
                            if ($surveyType !== '' && $surveyType !== $cType) {
                                $conductorConflict = true;
                                $status     = self::STATUS_NEEDS_REVIEW;
                                $reasonCode = self::REASON_CONDUCTOR_SPEC_CONFLICT;
                                $warnings[] = "Konflik spesifikasi: survey [{$surveyType}] vs GIS [{$cType}].";
                            }
                        }

                        if (!$conductorConflict) {
                            // GPS Plausibility diagnostic check (10m <= dist <= 150m heuristic)
                            if ($storedDist > 0 && ($storedDist < 10.0 || $storedDist > 150.0)) {
                                $warnings[] = "Peringatan heuristik: Jarak {$storedDist}m di luar rentang umum tiang (10m-150m).";
                            }

                            $status     = self::STATUS_AUTO_MATCH;
                            $reasonCode = self::REASON_EXISTING_VALID_PAIR;
                        }
                    }

                    if ($status === self::STATUS_AUTO_MATCH) {
                        $autoMatchCount++;
                    } else {
                        $needsReviewCount++;
                    }

                    $candidates[] = $this->buildCandidatePayload(
                        $naturalKey, $status, $reasonCode, $penyulangId, $sectionId,
                        $source, $target, $transline, $expectedDist, $warnings, $pairData['order'] ?? 0
                    );
                    continue;
                }
            }

            // E. No Existing Transline Found => MISSING or NEEDS_REVIEW
            if ($coordsInvalid) {
                $status     = self::STATUS_NEEDS_REVIEW;
                $reasonCode = self::REASON_INVALID_COORDINATE;
                $needsReviewCount++;
            } elseif (!empty($pairData['is_branch']) || ($parentChildCounts[$sId] ?? 0) > 1 || ($parentChildCounts[$tId] ?? 0) > 1 || ($nodeDegree[$sId] ?? 0) > 2 || ($nodeDegree[$tId] ?? 0) > 2) {
                // T-Off branching without existing transline
                $status     = self::STATUS_NEEDS_REVIEW;
                $reasonCode = self::REASON_BRANCHING_AMBIGUITY;
                $warnings[] = "Percabangan T-Off terdeteksi tanpa segmen transline eksisting.";
                $needsReviewCount++;
            } else {
                $status     = self::STATUS_MISSING;
                $reasonCode = self::REASON_DETERMINISTIC_MISSING_EDGE;
                $warnings[] = "Segmen fisik belum terdaftar di gis_translines.";
                $missingCount++;
            }

            $candidates[] = $this->buildCandidatePayload(
                $naturalKey, $status, $reasonCode, $penyulangId, $sectionId,
                $source, $target, null, $expectedDist, $warnings, $pairData['order'] ?? 0
            );
        }

        // 7. Deterministic Sorting: Primary by sequence order, Secondary by min_id, Tertiary by max_id
        usort($candidates, function ($a, $b) {
            $ord = ($a['_sort_order'] ?? 0) <=> ($b['_sort_order'] ?? 0);
            if ($ord !== 0) return $ord;

            $minA = min($a['source_asset_id'], $a['target_asset_id']);
            $minB = min($b['source_asset_id'], $b['target_asset_id']);
            if ($minA !== $minB) return $minA <=> $minB;

            $maxA = max($a['source_asset_id'], $a['target_asset_id']);
            $maxB = max($b['source_asset_id'], $b['target_asset_id']);
            return $maxA <=> $maxB;
        });

        // Strip internal sorting field
        foreach ($candidates as &$c) {
            unset($c['_sort_order']);
        }
        unset($c);

        return [
            'scope' => [
                'type'        => 'section',
                'id'          => $sectionId,
                'name'        => $sectionName,
                'feeder_id'   => $penyulangId,
                'feeder_name' => $feederName,
            ],
            'summary' => [
                'total_candidates'   => count($candidates),
                'auto_match_count'   => $autoMatchCount,
                'needs_review_count' => $needsReviewCount,
                'missing_count'      => $missingCount,
                'invalid_count'      => $invalidCount,
            ],
            'candidates' => $candidates,
        ];
    }

    /**
     * Resolve Visual Style Token and Pattern based on GIS-SPEC-VISUAL-01
     *
     * @param string $conductorType
     * @param string $conductorSize
     * @return array{token: string, pattern: string}
     */
    public function resolveVisualStyleToken(string $conductorType, string $conductorSize): array
    {
        $type = strtoupper(trim($conductorType));
        $size = trim($conductorSize);

        if (str_contains($type, 'MVTIC')) {
            return ['token' => 'MVTIC', 'pattern' => 'TWISTED_CHAIN'];
        }
        if (str_contains($type, 'A3CS') || str_contains($type, 'AAACS')) {
            if (str_contains($size, '240')) {
                return ['token' => 'A3CS_240', 'pattern' => 'DOUBLE_STRIPED'];
            }
            return ['token' => 'A3CS_150', 'pattern' => 'PROTECTED_SOLID'];
        }
        if (str_contains($type, 'A3C')) {
            if (str_contains($size, '70')) {
                return ['token' => 'A3C_70', 'pattern' => 'DASH_DOT'];
            }
            return ['token' => 'A3C_150', 'pattern' => 'HEAVY_SOLID'];
        }
        if (str_contains($type, 'AAAC')) {
            if (str_contains($size, '70')) {
                return ['token' => 'AAAC_70', 'pattern' => 'DASHED'];
            }
            return ['token' => 'AAAC_150', 'pattern' => 'HEAVY_SOLID'];
        }

        return ['token' => 'AAAC_150', 'pattern' => 'HEAVY_SOLID'];
    }

    /**
     * Build standard candidate payload
     */
    protected function buildCandidatePayload(
        string $naturalKey,
        string $status,
        string $reasonCode,
        int $penyulangId,
        int $sectionId,
        array $source,
        array $target,
        ?array $transline,
        float $expectedDist,
        array $warnings,
        int $sortOrder
    ): array {
        $cType = $transline['conductor_type'] ?? 'AAAC';
        $cSize = $transline['conductor_size'] ?? '150 mm²';
        $style = $this->resolveVisualStyleToken($cType, $cSize);

        $confidence = 0.0;
        if ($status === self::STATUS_AUTO_MATCH) {
            $confidence = 1.0;
        } elseif ($status === self::STATUS_NEEDS_REVIEW) {
            $confidence = 0.65;
        } elseif ($status === self::STATUS_MISSING) {
            $confidence = 0.50;
        }

        return [
            'natural_key'              => $naturalKey,
            'status'                   => $status,
            'classification'           => $status,
            'reason_code'              => $reasonCode,
            'confidence_score'         => $confidence,
            'penyulang_id'             => $penyulangId,
            'section_id'               => $sectionId,
            'source_asset_id'          => (int)$source['id'],
            'target_asset_id'          => (int)$target['id'],
            'source_asset_code'        => $source['kode_asset'] ?? $source['nama_asset'] ?? "AST-{$source['id']}",
            'target_asset_code'        => $target['kode_asset'] ?? $target['nama_asset'] ?? "AST-{$target['id']}",
            'existing_transline_id'    => $transline ? (int)$transline['id'] : null,
            'existing_transline_code'  => $transline['transline_code'] ?? null,
            'conductor_type'           => $cType,
            'conductor_size'           => $cSize,
            'visual_style_token'       => $style['token'],
            'visual_pattern'           => $style['pattern'],
            'distance_meters'          => $transline ? (float)($transline['distance_meters'] ?? 0) : null,
            'expected_distance_meters' => $expectedDist,
            'source_coordinates'       => [
                'lat' => (float)($source['latitude'] ?? 0),
                'lng' => (float)($source['longitude'] ?? 0),
            ],
            'target_coordinates'       => [
                'lat' => (float)($target['latitude'] ?? 0),
                'lng' => (float)($target['longitude'] ?? 0),
            ],
            'evidence'                 => $warnings,
            'warnings'                 => $warnings,
            '_sort_order'              => $sortOrder,
        ];
    }

    /**
     * TL-01 Stage D0: Comprehensive Feeder-level candidate scan
     *
     * @param int $penyulangId
     * @param array $options
     * @return array<string, mixed>
     */
    public function getPenyulangCompletionCandidates(int $penyulangId, array $options = []): array
    {
        if ($penyulangId <= 0) {
            return [
                'scope'      => ['type' => 'penyulang', 'id' => $penyulangId, 'name' => 'INVALID_SCOPE'],
                'summary'    => ['total_candidates' => 0, 'auto_match_count' => 0, 'needs_review_count' => 0, 'missing_count' => 0, 'invalid_count' => 0],
                'candidates' => [],
            ];
        }

        $feeder = null;
        if ($this->db->tableExists('penyulang')) {
            $feeder = $this->db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
        }
        $feederName = $feeder['nama_penyulang'] ?? "Penyulang #{$penyulangId}";

        // 1. Fetch all sections for this feeder
        $sections = [];
        if ($this->db->tableExists('sections')) {
            $sections = $this->db->table('sections')->where('penyulang_id', $penyulangId)->get()->getResultArray();
        }

        // 2. Fetch all assets belonging to feeder
        $assets = [];
        if ($this->db->tableExists('assets')) {
            $builder = $this->db->table('assets')->where('penyulang_id', $penyulangId);
            if ($this->db->fieldExists('deleted_at', 'assets')) {
                $builder->where('deleted_at IS NULL');
            }
            $assets = $builder->get()->getResultArray();
        }

        $totalAssets = count($assets);

        // 3. Run analysis across sections and consolidate
        $allCandidatesMap = [];
        $autoMatch = 0;
        $needsReview = 0;
        $invalid = 0;
        $missing = 0;

        foreach ($sections as $sec) {
            $secId = (int)$sec['id'];
            $secResult = $this->getSectionCompletionCandidates($secId, $options);
            foreach ($secResult['candidates'] as $c) {
                $natKey = $c['natural_key'];
                if (!isset($allCandidatesMap[$natKey])) {
                    $allCandidatesMap[$natKey] = $c;
                }
            }
        }

        // 4. Also check any existing translines for this feeder to ensure 100% topology coverage
        if ($this->db->tableExists('gis_translines')) {
            $tlBuilder = $this->db->table('gis_translines')->where('penyulang_id', $penyulangId);
            if ($this->db->fieldExists('is_active', 'gis_translines')) {
                $tlBuilder->where('is_active', 1);
            }
            $activeTls = $tlBuilder->get()->getResultArray();

            $assetMap = [];
            foreach ($assets as $a) {
                $assetMap[(int)$a['id']] = $a;
            }

            foreach ($activeTls as $tl) {
                $sId = (int)($tl['source_asset_id'] ?? 0);
                $tId = (int)($tl['target_asset_id'] ?? 0);
                if ($sId <= 0 || $tId <= 0) continue;

                $natKey = $this->buildNaturalKey($penyulangId, $sId, $tId);
                if (!isset($allCandidatesMap[$natKey])) {
                    $source = $assetMap[$sId] ?? null;
                    $target = $assetMap[$tId] ?? null;
                    if ($source && $target) {
                        $latA = (float)($source['latitude'] ?? 0);
                        $lonA = (float)($source['longitude'] ?? 0);
                        $latB = (float)($target['latitude'] ?? 0);
                        $lonB = (float)($target['longitude'] ?? 0);
                        $expDist = $this->haversineDistanceMeters($latA, $lonA, $latB, $lonB);
                        $storedDist = (float)($tl['distance_meters'] ?? 0);

                        $status = self::STATUS_AUTO_MATCH;
                        $reason = self::REASON_EXISTING_VALID_PAIR;
                        $warn = [];

                        if ($storedDist <= 0.0) {
                            $status = self::STATUS_NEEDS_REVIEW;
                            $reason = self::REASON_INVALID_STORED_DISTANCE;
                            $warn[] = "Jarak transline tersimpan bernilai 0.00 m.";
                        } elseif ((abs($latA) < 0.0001 && abs($lonA) < 0.0001) || (abs($latB) < 0.0001 && abs($lonB) < 0.0001)) {
                            $status = self::STATUS_NEEDS_REVIEW;
                            $reason = self::REASON_INVALID_COORDINATE;
                            $warn[] = "Koordinat tiang tidak valid.";
                        }

                        $allCandidatesMap[$natKey] = $this->buildCandidatePayload(
                            $natKey, $status, $reason, $penyulangId, (int)($source['section_id'] ?? 0),
                            $source, $target, $tl, $expDist, $warn, 0
                        );
                    }
                }
            }
        }

        // 5. Final deterministic sorting by natural_key
        $candidates = array_values($allCandidatesMap);
        usort($candidates, function($a, $b) {
            $cmpSec = ($a['section_id'] ?? 0) <=> ($b['section_id'] ?? 0);
            if ($cmpSec !== 0) return $cmpSec;
            return strcmp($a['natural_key'], $b['natural_key']);
        });

        // Compute counts
        foreach ($candidates as $c) {
            if ($c['status'] === self::STATUS_AUTO_MATCH) $autoMatch++;
            elseif ($c['status'] === self::STATUS_NEEDS_REVIEW) $needsReview++;
            elseif ($c['status'] === self::STATUS_INVALID) $invalid++;
            elseif ($c['status'] === self::STATUS_MISSING) $missing++;
        }

        return [
            'scope' => [
                'type'             => 'penyulang',
                'id'               => $penyulangId,
                'name'             => $feederName,
                'section_count'    => count($sections),
                'asset_count'      => $totalAssets,
            ],
            'summary' => [
                'total_candidates'   => count($candidates),
                'auto_match_count'   => $autoMatch,
                'needs_review_count' => $needsReview,
                'missing_count'      => $missing,
                'invalid_count'      => $invalid,
            ],
            'candidates' => $candidates,
        ];
    }

    /**
     * Revalidate a single candidate before controlled write (TL-01 Phase 2A)
     *
     * @param array $candidate
     * @return array{valid: bool, reason: string|null, details: array}
     */
    public function validateControlledCandidate(array $candidate): array
    {
        $penyulangId = (int)($candidate['penyulang_id'] ?? 0);
        $sourceId    = (int)($candidate['source_asset_id'] ?? 0);
        $targetId    = (int)($candidate['target_asset_id'] ?? 0);

        if ($penyulangId <= 0 || $sourceId <= 0 || $targetId <= 0) {
            return ['valid' => false, 'reason' => 'INVALID_IDENTIFIERS', 'details' => ['candidate' => $candidate]];
        }

        // Must be AUTO_MATCH
        if (($candidate['status'] ?? '') !== self::STATUS_AUTO_MATCH) {
            return ['valid' => false, 'reason' => 'STATUS_NOT_AUTO_MATCH', 'details' => ['status' => $candidate['status'] ?? 'UNKNOWN']];
        }

        // Check source & target asset existence in database
        $source = $this->db->table('assets')->where('id', $sourceId)->get()->getRowArray();
        $target = $this->db->table('assets')->where('id', $targetId)->get()->getRowArray();

        if (!$source || !$target) {
            return ['valid' => false, 'reason' => 'ASSET_NOT_FOUND', 'details' => ['source' => (bool)$source, 'target' => (bool)$target]];
        }

        // Same penyulang
        if ((int)($source['penyulang_id'] ?? 0) !== $penyulangId || (int)($target['penyulang_id'] ?? 0) !== $penyulangId) {
            return ['valid' => false, 'reason' => 'CROSS_FEEDER_ILLEGAL_RELATIONSHIP', 'details' => []];
        }

        // Coordinates check
        $latA = (float)($source['latitude'] ?? 0);
        $lonA = (float)($source['longitude'] ?? 0);
        $latB = (float)($target['latitude'] ?? 0);
        $lonB = (float)($target['longitude'] ?? 0);

        if ((abs($latA) < 0.0001 && abs($lonA) < 0.0001) || (abs($latB) < 0.0001 && abs($lonB) < 0.0001)) {
            return ['valid' => false, 'reason' => 'INVALID_COORDINATE', 'details' => []];
        }

        // Natural key uniqueness check
        $naturalKey = $this->buildNaturalKey($penyulangId, $sourceId, $targetId);
        if (($candidate['natural_key'] ?? '') !== $naturalKey) {
            return ['valid' => false, 'reason' => 'NATURAL_KEY_MISMATCH', 'details' => ['expected' => $naturalKey, 'got' => $candidate['natural_key'] ?? '']];
        }

        // Check if an ACTIVE record already exists in gis_translines for this natural key
        if ($this->db->tableExists('gis_translines')) {
            $existing = $this->db->table('gis_translines')
                ->where('penyulang_id', $penyulangId)
                ->groupStart()
                    ->where('source_asset_id', $sourceId)->where('target_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('source_asset_id', $targetId)->where('target_asset_id', $sourceId)
                ->groupEnd()
                ->where('is_active', 1)
                ->get()
                ->getRowArray();

            if ($existing) {
                return ['valid' => false, 'reason' => 'TRANSLINE_ALREADY_EXISTS', 'details' => ['existing_id' => $existing['id']]];
            }
        }

        return ['valid' => true, 'reason' => null, 'details' => ['source' => $source, 'target' => $target, 'natural_key' => $naturalKey]];
    }

    /**
     * Execute exactly one controlled Transline INSERT inside a transaction (TL-01 Phase 2A)
     *
     * @param array $candidate
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function executeControlledWrite(array $candidate, ?array $actor = null): array
    {
        $validation = $this->validateControlledCandidate($candidate);
        if (!$validation['valid']) {
            return [
                'status'  => 'error',
                'action'  => 'ABORT',
                'reason'  => $validation['reason'],
                'details' => $validation['details'],
            ];
        }

        $source = $validation['details']['source'];
        $target = $validation['details']['target'];
        $penyulangId = (int)$candidate['penyulang_id'];
        $sourceId    = (int)$candidate['source_asset_id'];
        $targetId    = (int)$candidate['target_asset_id'];
        $actorName   = $actor['name'] ?? 'HUMAN_GOVERNANCE_PHASE2A';

        $latA = (float)($source['latitude'] ?? 0);
        $lonA = (float)($source['longitude'] ?? 0);
        $latB = (float)($target['latitude'] ?? 0);
        $lonB = (float)($target['longitude'] ?? 0);
        $distance = (float)($candidate['distance_meters'] ?? $candidate['expected_distance_meters'] ?? $this->haversineDistanceMeters($latA, $lonA, $latB, $lonB));

        $geometry = json_encode([
            [$lonA, $latA],
            [$lonB, $latB],
        ]);

        $payload = [
            'transline_code'     => "TL-{$penyulangId}-{$sourceId}-{$targetId}",
            'penyulang_id'       => $penyulangId,
            'source_asset_id'    => $sourceId,
            'target_asset_id'    => $targetId,
            'geometry'           => $geometry,
            'conductor_type'     => $candidate['conductor_type'] ?? 'AAAC',
            'conductor_size'     => $candidate['conductor_size'] ?? '150 mm²',
            'conductor_material' => 'ALUMINUM_ALLOY',
            'distance_meters'    => $distance,
            'status'             => 'ACTIVE',
            'is_active'          => 1,
            'created_by'         => $actorName,
        ];

        if ($this->db->fieldExists('geometry_type', 'gis_translines')) {
            $payload['geometry_type'] = 'LineString';
        }
        if ($this->db->fieldExists('installation_type', 'gis_translines')) {
            $payload['installation_type'] = 'OVERHEAD';
        }
        if ($this->db->fieldExists('circuit_config', 'gis_translines')) {
            $payload['circuit_config'] = '3_PHASE';
        }
        if ($this->db->fieldExists('created_at', 'gis_translines')) {
            $payload['created_at'] = date('Y-m-d H:i:s');
        }

        $this->db->transBegin();
        try {
            $countBefore = $this->db->table('gis_translines')->countAllResults();

            $this->db->table('gis_translines')->insert($payload);
            $insertedId = (int)$this->db->insertID();

            $countAfter = $this->db->table('gis_translines')->countAllResults();

            // After-write verification
            if ($countAfter !== $countBefore + 1) {
                throw new \RuntimeException("Cardinality mismatch: expected 1 insert, delta is " . ($countAfter - $countBefore));
            }

            $insertedRow = $this->db->table('gis_translines')->where('id', $insertedId)->get()->getRowArray();
            if (!$insertedRow) {
                throw new \RuntimeException("Inserted row #{$insertedId} could not be retrieved.");
            }

            $naturalKey = $this->buildNaturalKey($penyulangId, (int)$insertedRow['source_asset_id'], (int)$insertedRow['target_asset_id']);
            if ($naturalKey !== $candidate['natural_key']) {
                throw new \RuntimeException("Natural key mismatch on inserted row: {$naturalKey} vs {$candidate['natural_key']}");
            }

            $this->db->transCommit();

            return [
                'status'         => 'success',
                'action'         => 'WRITE_COMMITTED',
                'transline_id'   => $insertedId,
                'transline_code' => $payload['transline_code'],
                'natural_key'    => $naturalKey,
                'distance'       => $distance,
                'row'            => $insertedRow,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'action'  => 'ROLLBACK',
                'reason'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute controlled rollback of the exact transline record by primary key (TL-01 Phase 2A)
     *
     * @param int $translineId
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function executeControlledRollback(int $translineId, ?array $actor = null): array
    {
        if ($translineId <= 0) {
            return ['status' => 'error', 'reason' => 'INVALID_TRANSLINE_ID'];
        }

        $this->db->transBegin();
        try {
            $row = $this->db->table('gis_translines')->where('id', $translineId)->get()->getRowArray();
            if (!$row) {
                throw new \RuntimeException("Transline #{$translineId} does not exist for rollback.");
            }

            $countBefore = $this->db->table('gis_translines')->countAllResults();

            // Exact deletion of experiment artifact
            $this->db->table('gis_translines')->where('id', $translineId)->delete();

            $countAfter = $this->db->table('gis_translines')->countAllResults();
            if ($countAfter !== $countBefore - 1) {
                throw new \RuntimeException("Rollback cardinality mismatch: expected 1 deleted, delta is " . ($countBefore - $countAfter));
            }

            $this->db->transCommit();

            return [
                'status'            => 'success',
                'action'            => 'ROLLBACK_COMMITTED',
                'rolled_back_id'    => $translineId,
                'transline_code'    => $row['transline_code'] ?? null,
                'rollback_verified' => true,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'action'  => 'ROLLBACK_FAILED',
                'reason'  => $e->getMessage(),
            ];
        }
    }

    /**
     * TL-01 Sub-Gate D1: Controlled Proposal Persistence
     *
     * Persists candidate proposals into `gis_transline_proposals` table with
     * strict idempotency (anti-duplication) and zero-mutation firewall on `gis_translines`.
     *
     * @param array $candidates Array of candidate dictionaries
     * @param array $options Execution options: ['dry_run' => bool, 'engine_version' => string]
     * @return array<string, mixed> Execution summary and audit details
     */
    public function persistProposalBatch(array $candidates, array $options = []): array
    {
        $engineVersion = $options['engine_version'] ?? 'TL-01-V2.0';
        $dryRun = $options['dry_run'] ?? false;

        $validClassifications = [
            self::STATUS_AUTO_MATCH,
            self::STATUS_NEEDS_REVIEW,
            self::STATUS_INVALID,
            self::STATUS_MISSING,
        ];

        if (empty($candidates)) {
            return [
                'status'  => 'error',
                'action'  => 'ABORT',
                'reason'  => 'EMPTY_CANDIDATE_BATCH',
                'details' => ['count' => 0],
            ];
        }

        // 1. Pre-validation of all candidates in batch
        foreach ($candidates as $idx => $c) {
            $class = $c['classification'] ?? $c['status'] ?? null;
            if (!in_array($class, $validClassifications, true)) {
                return [
                    'status'  => 'error',
                    'action'  => 'ABORT',
                    'reason'  => 'ILLEGAL_CLASSIFICATION',
                    'details' => ['index' => $idx, 'classification' => $class],
                ];
            }

            $natKey = $c['natural_key'] ?? '';
            if (empty($natKey) || !str_starts_with($natKey, 'TL-NAT:')) {
                return [
                    'status'  => 'error',
                    'action'  => 'ABORT',
                    'reason'  => 'INVALID_NATURAL_KEY',
                    'details' => ['index' => $idx, 'natural_key' => $natKey],
                ];
            }
        }

        // 2. Fetch all existing active proposals for this batch's natural keys (Application-level deduplication)
        $naturalKeys = array_column($candidates, 'natural_key');
        $existingRows = [];
        if ($this->db->tableExists('gis_transline_proposals') && !empty($naturalKeys)) {
            $existingRows = $this->db->table('gis_transline_proposals')
                ->whereIn('natural_key', $naturalKeys)
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();
        }

        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[$row['natural_key']] = $row;
        }

        $toInsert = [];
        $skippedExisting = [];

        foreach ($candidates as $c) {
            $natKey = $c['natural_key'];
            if (isset($existingMap[$natKey])) {
                $skippedExisting[] = [
                    'natural_key' => $natKey,
                    'existing_id' => (int)$existingMap[$natKey]['id'],
                    'status'      => $existingMap[$natKey]['status'],
                ];
            } else {
                $sCoord = $c['source_coordinates'] ?? null;
                $tCoord = $c['target_coordinates'] ?? null;
                $geom = null;
                if ($sCoord && $tCoord && !empty($sCoord['lat']) && !empty($tCoord['lat'])) {
                    $geom = json_encode([
                        [(float)$sCoord['lng'], (float)$sCoord['lat']],
                        [(float)$tCoord['lng'], (float)$tCoord['lat']],
                    ]);
                }

                $evidence = [
                    'reason_code'        => $c['reason_code'] ?? null,
                    'warnings'           => $c['warnings'] ?? [],
                    'visual_style_token' => $c['visual_style_token'] ?? null,
                    'visual_pattern'     => $c['visual_pattern'] ?? null,
                    'distance_meters'    => $c['distance_meters'] ?? $c['expected_distance_meters'] ?? null,
                ];

                $toInsert[] = [
                    'penyulang_id'            => (int)($c['penyulang_id'] ?? 0),
                    'section_id'              => !empty($c['section_id']) ? (int)$c['section_id'] : null,
                    'source_asset_id'         => (int)($c['source_asset_id'] ?? 0),
                    'target_asset_id'         => (int)($c['target_asset_id'] ?? 0),
                    'natural_key'             => $natKey,
                    'proposed_conductor_type' => $c['conductor_type'] ?? 'AAAC',
                    'proposed_conductor_size' => $c['conductor_size'] ?? '150 mm²',
                    'proposed_distance'       => (float)($c['distance_meters'] ?? $c['expected_distance_meters'] ?? 0),
                    'proposed_geometry'       => $geom,
                    'classification'          => $c['classification'] ?? $c['status'],
                    'confidence_score'        => (float)($c['confidence_score'] ?? 1.0),
                    'evidence_json'           => json_encode($evidence),
                    'proposal_source'         => 'DETERMINISTIC_ENGINE',
                    'engine_version'          => $engineVersion,
                    'status'                  => 'PENDING_REVIEW',
                    'created_at'              => date('Y-m-d H:i:s'),
                    'updated_at'              => date('Y-m-d H:i:s'),
                ];
            }
        }

        if ($dryRun) {
            return [
                'status'                 => 'dry_run_success',
                'action'                 => 'DRY_RUN',
                'candidates_count'       => count($candidates),
                'to_insert_count'        => count($toInsert),
                'skipped_existing_count' => count($skippedExisting),
                'skipped_keys'           => array_column($skippedExisting, 'natural_key'),
            ];
        }

        // 3. Atomic Transactional Insert
        $this->db->transBegin();
        try {
            $insertedRecords = [];
            foreach ($toInsert as $payload) {
                $this->db->table('gis_transline_proposals')->insert($payload);
                $newId = (int)$this->db->insertID();
                $insertedRecords[] = [
                    'id'          => $newId,
                    'natural_key' => $payload['natural_key'],
                    'status'      => $payload['status'],
                ];
            }

            $this->db->transCommit();

            return [
                'status'                 => 'success',
                'action'                 => 'PROPOSALS_COMMITTED',
                'total_candidates'       => count($candidates),
                'inserted_count'         => count($insertedRecords),
                'skipped_existing_count' => count($skippedExisting),
                'inserted_records'       => $insertedRecords,
                'skipped_existing'       => $skippedExisting,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'action'  => 'ROLLBACK',
                'reason'  => 'TRANSACTION_EXCEPTION',
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * TL-01 Hard Invariant: Candidate Engine Domain Firewall
     *
     * Validates that candidate endpoints strictly resolve to assets.id.
     * Guaranteed: 0 UNION temuan, 0 JOIN temuan, 0 nearest finding binding.
     *
     * @param int $sourceAssetId
     * @param int $targetAssetId
     * @return bool
     */
    public function validateCandidateEndpoints(int $sourceAssetId, int $targetAssetId): bool
    {
        if ($sourceAssetId <= 0 || $targetAssetId <= 0 || $sourceAssetId === $targetAssetId) {
            return false;
        }

        if (!$this->db->tableExists('assets')) {
            return false;
        }

        $sCount = $this->db->table('assets')->where('id', $sourceAssetId)->countAllResults();
        $tCount = $this->db->table('assets')->where('id', $targetAssetId)->countAllResults();

        return ($sCount === 1 && $tCount === 1);
    }
}

