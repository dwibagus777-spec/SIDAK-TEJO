<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Phase AR-01 Phase 5G: Spatial Section Candidate & Topology Evidence Engine
 * Contract v1.0 (Strictly Read-Only / Non-Destructive Advisory)
 *
 * Invariants:
 * - Candidate != Assignment (Zero writes to assets.section_id)
 * - Score != Truth (Advisory only for human engineering review)
 * - Zero Blind Assignment (Ambiguous margin < 5% flagged without forced picking)
 * - Cross-Feeder Isolation (Candidates strictly within asset's parent feeder)
 * - Boundary Integrity (No fabricated coordinates when device master is unresolved)
 */
class SpatialSectionCandidateService
{
    protected BaseConnection $db;

    /**
     * Configurable scoring weights for Multi-Criteria Ranking (v1.0 baseline)
     */
    protected array $weights = [
        'spatial'    => 0.35, // Distance proximity to section path / cluster
        'boundary'   => 0.30, // Distance & direction to verified boundary switching devices
        'feeder'     => 0.15, // Feeder integrity and hierarchy match
        'continuity' => 0.20, // Proximity to other assets assigned/inferred on same section
    ];

    /**
     * Ambiguity threshold percentage (Delta score < 5.0% => AMBIGUOUS)
     */
    protected float $ambiguityMarginThreshold = 5.0;

    public function __construct(?BaseConnection $db = null, array $weights = [])
    {
        $this->db = $db ?? \Config\Database::connect();
        if (!empty($weights)) {
            $this->weights = array_merge($this->weights, $weights);
        }
    }

    /**
     * Set scoring weights dynamically
     */
    public function setWeights(array $weights): self
    {
        $this->weights = array_merge($this->weights, $weights);
        return $this;
    }

    /**
     * Get active scoring weights
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    /**
     * Resolve Feeder metadata
     */
    public function resolveFeeder(int $feederId): ?array
    {
        $builder = $this->db->table('penyulang')->where('id', $feederId);
        $res = $builder->get();
        return $res ? $res->getRowArray() : null;
    }

    /**
     * Resolve all active sections belonging to feeder (Cross-Feeder Isolation)
     */
    public function resolveSections(int $feederId): array
    {
        $seqCol = $this->db->fieldExists('sequence_order', 'sections') ? 'sequence_order' : ($this->db->fieldExists('urutan', 'sections') ? 'urutan' : 'id');
        $builder = $this->db->table('sections')->where('penyulang_id', $feederId);
        if ($this->db->fieldExists('status', 'sections')) {
            $builder->whereIn('status', ['AKTIF', 'ACTIVE', '1']);
        }
        $res = $builder->orderBy($seqCol, 'ASC')->get();
        return $res ? $res->getResultArray() : [];
    }

    /**
     * Resolve Boundary Devices and text tokens from section labels
     */
    public function resolveBoundaryDevices(array $sections, int $feederId): array
    {
        $boundaries = [];

        // Fetch all potential switching devices / landmarks in this feeder
        $devBuilder = $this->db->table('assets')->where('penyulang_id', $feederId);
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $devBuilder->where('deleted_at IS NULL');
        }
        $getDev = $devBuilder->get();
        $feederAssets = $getDev ? $getDev->getResultArray() : [];

        foreach ($sections as $sec) {
            $secId = (int)$sec['id'];
            $secName = $sec['nama_section'] ?? $sec['nama_seksi'] ?? ('Seksi #' . $secId);

            // Parse tokens separated by '-' or ' - '
            $parts = array_map('trim', explode('-', $secName));
            $startLabel = $parts[0] ?? 'GI';
            $endLabel   = end($parts) ?: 'UJUNG';

            $startDevice = $this->findMatchingDeviceAsset($startLabel, $feederAssets);
            $endDevice   = $this->findMatchingDeviceAsset($endLabel, $feederAssets);

            $status = 'BOUNDARY_TEXT_RESOLVED';
            if ($startDevice && $endDevice) {
                $status = 'BOUNDARY_DEVICE_RESOLVED';
            } elseif ($startDevice || $endDevice) {
                $status = 'BOUNDARY_PARTIALLY_RESOLVED';
            } else {
                $status = 'BOUNDARY_DEVICE_UNRESOLVED';
            }

            $boundaries[$secId] = [
                'section_id'         => $secId,
                'section_name'       => $secName,
                'start_label'        => $startLabel,
                'end_label'          => $endLabel,
                'status'             => $status,
                'start_device'       => $startDevice,
                'end_device'         => $endDevice,
            ];
        }

        return $boundaries;
    }

    /**
     * Calculate Geodesic Haversine Distance in meters
     */
    public function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0; // Earth radius in meters

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo   = deg2rad($lat2);
        $lonTo   = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    /**
     * Calculate Spatial Evidence for an asset against a section
     */
    public function calculateSpatialEvidence(array $asset, array $section, ?array $boundary, array $sectionAssets): array
    {
        $assetLat = (float)($asset['latitude'] ?? 0);
        $assetLon = (float)($asset['longitude'] ?? 0);

        if (empty($assetLat) || empty($assetLon)) {
            return [
                'has_gps'              => false,
                'spatial_score'        => 0.0,
                'distance_to_boundary' => null,
                'centroid_distance_m'  => null,
            ];
        }

        $minDist = null;

        // 1. Distance to resolved boundary devices
        if ($boundary) {
            if (!empty($boundary['start_device']['latitude']) && !empty($boundary['start_device']['longitude'])) {
                $dStart = $this->calculateHaversineDistance($assetLat, $assetLon, (float)$boundary['start_device']['latitude'], (float)$boundary['start_device']['longitude']);
                $minDist = $minDist === null ? $dStart : min($minDist, $dStart);
            }
            if (!empty($boundary['end_device']['latitude']) && !empty($boundary['end_device']['longitude'])) {
                $dEnd = $this->calculateHaversineDistance($assetLat, $assetLon, (float)$boundary['end_device']['latitude'], (float)$boundary['end_device']['longitude']);
                $minDist = $minDist === null ? $dEnd : min($minDist, $dEnd);
            }
        }

        // 2. Distance to cluster centroid of section assets (if already verified or mapped)
        $centroidDist = null;
        if (!empty($sectionAssets)) {
            $sumLat = 0; $sumLon = 0; $validCount = 0;
            foreach ($sectionAssets as $sa) {
                if (!empty($sa['latitude']) && !empty($sa['longitude'])) {
                    $sumLat += (float)$sa['latitude'];
                    $sumLon += (float)$sa['longitude'];
                    $validCount++;
                }
            }
            if ($validCount > 0) {
                $cLat = $sumLat / $validCount;
                $cLon = $sumLon / $validCount;
                $centroidDist = $this->calculateHaversineDistance($assetLat, $assetLon, $cLat, $cLon);
            }
        }

        // Calculate spatial score (closer distance yields higher score 0..100)
        $effectiveDist = $centroidDist ?? $minDist ?? 1000.0;
        // Exponential decay: e.g. 500m -> ~50 pts, 100m -> ~80 pts, 20m -> ~95 pts
        $spatialScore = max(0.0, min(100.0, 100.0 * exp(-$effectiveDist / 1200.0)));

        return [
            'has_gps'              => true,
            'spatial_score'        => round($spatialScore, 2),
            'distance_to_boundary' => $minDist !== null ? round($minDist, 1) : null,
            'centroid_distance_m'  => $centroidDist !== null ? round($centroidDist, 1) : null,
        ];
    }

    /**
     * Calculate Boundary Evidence (Score 0..100)
     */
    public function calculateBoundaryEvidence(array $asset, ?array $boundary): array
    {
        if (!$boundary || $boundary['status'] === 'BOUNDARY_DEVICE_UNRESOLVED') {
            return [
                'boundary_status' => 'BOUNDARY_UNRESOLVED',
                'boundary_score'  => 50.0, // Neutral fallback when boundary devices are not yet GPS-pinned
                'start_label'     => $boundary['start_label'] ?? 'N/A',
                'end_label'       => $boundary['end_label'] ?? 'N/A',
            ];
        }

        $score = 70.0;
        if ($boundary['status'] === 'BOUNDARY_DEVICE_RESOLVED') {
            $score = 90.0;
        } elseif ($boundary['status'] === 'BOUNDARY_PARTIALLY_RESOLVED') {
            $score = 75.0;
        }

        return [
            'boundary_status' => $boundary['status'],
            'boundary_score'  => $score,
            'start_label'     => $boundary['start_label'],
            'end_label'       => $boundary['end_label'],
        ];
    }

    /**
     * Calculate Feeder Match Evidence (Score 0..100)
     */
    public function calculateFeederEvidence(array $asset, int $feederId): array
    {
        $match = ((int)($asset['penyulang_id'] ?? 0) === $feederId);
        return [
            'feeder_match' => $match,
            'feeder_score' => $match ? 100.0 : 0.0,
        ];
    }

    /**
     * Calculate Continuity Evidence (Score 0..100)
     */
    public function calculateContinuityEvidence(array $asset, array $section, array $allFeederAssets): array
    {
        $secId = (int)$section['id'];
        $linkedCount = 0;
        foreach ($allFeederAssets as $a) {
            if (!empty($a['section_id']) && (int)$a['section_id'] === $secId) {
                $linkedCount++;
            }
        }

        // If section already has verified assets, continuity score is higher
        $continuityScore = $linkedCount > 0 ? min(100.0, 60.0 + ($linkedCount * 2.0)) : 50.0;

        return [
            'linked_assets_count' => $linkedCount,
            'continuity_score'    => round($continuityScore, 2),
        ];
    }

    /**
     * Calculate Multi-Criteria Score for single asset and single section
     */
    public function scoreCandidate(array $asset, array $section, ?array $boundary, array $allFeederAssets): array
    {
        $secId = (int)$section['id'];
        $secAssets = array_filter($allFeederAssets, fn($a) => !empty($a['section_id']) && (int)$a['section_id'] === $secId);

        $feederEv = $this->calculateFeederEvidence($asset, (int)$section['penyulang_id']);
        if (!$feederEv['feeder_match']) {
            // Strict Cross-Feeder Isolation: 0 score if different feeder
            return [
                'section_id'   => $secId,
                'section_name' => $section['nama_section'] ?? $section['nama_seksi'] ?? "Seksi #{$secId}",
                'score'        => 0.0,
                'evidence'     => ['error' => 'CROSS_FEEDER_CONTAMINATION_PREVENTED'],
            ];
        }

        $spatialEv    = $this->calculateSpatialEvidence($asset, $section, $boundary, $secAssets);
        $boundaryEv   = $this->calculateBoundaryEvidence($asset, $boundary);
        $continuityEv = $this->calculateContinuityEvidence($asset, $section, $allFeederAssets);

        // Weighted Multi-Criteria Formulation
        $w = $this->weights;
        $totalWeight = array_sum($w);
        if ($totalWeight <= 0) $totalWeight = 1.0;

        $finalScore = (
            ($w['spatial'] * $spatialEv['spatial_score']) +
            ($w['boundary'] * $boundaryEv['boundary_score']) +
            ($w['feeder'] * $feederEv['feeder_score']) +
            ($w['continuity'] * $continuityEv['continuity_score'])
        ) / $totalWeight;

        $finalScore = round(max(0.0, min(100.0, $finalScore)), 2);

        return [
            'section_id'     => $secId,
            'section_name'   => $section['nama_section'] ?? $section['nama_seksi'] ?? "Seksi #{$secId}",
            'sequence_order' => (int)($section['sequence_order'] ?? $section['urutan'] ?? $secId),
            'score'          => $finalScore,
            'evidence'       => [
                'spatial'    => $spatialEv,
                'boundary'   => $boundaryEv,
                'feeder'     => $feederEv,
                'continuity' => $continuityEv,
            ],
        ];
    }

    /**
     * Rank candidates and evaluate confidence / ambiguity
     */
    public function rankCandidates(array $rawCandidates): array
    {
        // Stable deterministic sort: Score DESC, then sequence_order ASC, then section_id ASC
        usort($rawCandidates, function ($a, $b) {
            if (abs($a['score'] - $b['score']) > 0.001) {
                return ($a['score'] > $b['score']) ? -1 : 1;
            }
            $seqA = $a['sequence_order'] ?? 0;
            $seqB = $b['sequence_order'] ?? 0;
            if ($seqA !== $seqB) {
                return ($seqA < $seqB) ? -1 : 1;
            }
            return ($a['section_id'] < $b['section_id']) ? -1 : 1;
        });

        // Compute top-3 candidates with ranks
        $ranked = [];
        $topScore = $rawCandidates[0]['score'] ?? 0.0;
        $secondScore = $rawCandidates[1]['score'] ?? 0.0;
        $margin = max(0.0, $topScore - $secondScore);

        $isAmbiguous = (count($rawCandidates) >= 2 && $margin < $this->ambiguityMarginThreshold);

        foreach (array_slice($rawCandidates, 0, 3) as $idx => $c) {
            $rankNum = $idx + 1;
            $conf = $this->calculateConfidence($c['score'], $margin, $isAmbiguous, $rankNum);

            $ranked[] = [
                'rank'           => $rankNum,
                'section_id'     => $c['section_id'],
                'section_name'   => $c['section_name'],
                'sequence_order' => $c['sequence_order'],
                'score'          => $c['score'],
                'confidence'     => $conf,
                'evidence'       => $c['evidence'],
            ];
        }

        return [
            'candidates'    => $ranked,
            'margin_pct'    => round($margin, 2),
            'is_ambiguous'  => $isAmbiguous,
        ];
    }

    /**
     * Determine confidence level based on score, rank, and margin
     */
    protected function calculateConfidence(float $score, float $margin, bool $isAmbiguous, int $rank): string
    {
        if ($rank > 1) {
            return ($score >= 70.0) ? 'ALTERNATIVE' : 'LOW';
        }

        if ($score < 30.0) {
            return 'UNRESOLVED';
        }

        if ($isAmbiguous) {
            return 'AMBIGUOUS';
        }

        if ($score >= 80.0 && $margin >= 10.0) {
            return 'HIGH';
        }

        if ($score >= 60.0 && $margin >= 5.0) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    /**
     * Analyze a single Asset and return structured candidate recommendation payload
     */
    public function analyzeAsset(int $assetId): array
    {
        $builder = $this->db->table('assets')->where('id', $assetId);
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }
        $getAsset = $builder->get();
        $asset = $getAsset ? $getAsset->getRowArray() : null;

        if (!$asset) {
            return [
                'success' => false,
                'error'   => "Asset ID #{$assetId} tidak ditemukan.",
            ];
        }

        $feederId = (int)($asset['penyulang_id'] ?? 0);
        $feeder = $this->resolveFeeder($feederId);
        $sections = $this->resolveSections($feederId);

        if (empty($sections)) {
            return [
                'success' => false,
                'error'   => "Belum ada seksi CR-06F aktif untuk penyulang ID #{$feederId}.",
            ];
        }

        $boundaries = $this->resolveBoundaryDevices($sections, $feederId);

        // Fetch all assets in feeder for continuity calculations
        $fAssetsBuilder = $this->db->table('assets')->where('penyulang_id', $feederId);
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $fAssetsBuilder->where('deleted_at IS NULL');
        }
        $getFAssets = $fAssetsBuilder->get();
        $allFeederAssets = $getFAssets ? $getFAssets->getResultArray() : [];

        $rawCandidates = [];
        foreach ($sections as $sec) {
            $secId = (int)$sec['id'];
            $boundary = $boundaries[$secId] ?? null;
            $rawCandidates[] = $this->scoreCandidate($asset, $sec, $boundary, $allFeederAssets);
        }

        $rankingResult = $this->rankCandidates($rawCandidates);

        // Build canonical Evidence JSON payload
        $topCandidate = $rankingResult['candidates'][0] ?? null;
        $decision = 'ADVISORY_ONLY';
        if (empty($asset['latitude']) || empty($asset['longitude'])) {
            $decision = 'MISSING_GPS_COORDINATES';
        } elseif ($rankingResult['is_ambiguous']) {
            $decision = 'AMBIGUOUS_REQUIRES_HUMAN_SURVEY';
        } elseif ($topCandidate && $topCandidate['confidence'] === 'HIGH') {
            $decision = 'HIGH_CONFIDENCE_RECOMMENDATION';
        }

        return [
            'success'                     => true,
            'engine'                      => 'AR-01-SPATIAL-CANDIDATE',
            'contract_version'            => '1.0',
            'asset_id'                    => (int)$asset['id'],
            'kode_asset'                  => $asset['kode_asset'] ?? $asset['nama_asset'] ?? 'N/A',
            'feeder_id'                   => $feederId,
            'feeder_name'                 => $feeder['nama_penyulang'] ?? "Penyulang #{$feederId}",
            'asset_gps'                   => [
                'latitude'  => !empty($asset['latitude']) ? (float)$asset['latitude'] : null,
                'longitude' => !empty($asset['longitude']) ? (float)$asset['longitude'] : null,
            ],
            'section_candidates'          => $rankingResult['candidates'],
            'margin_percent'              => $rankingResult['margin_pct'],
            'is_ambiguous'                => $rankingResult['is_ambiguous'],
            'decision'                    => $decision,
            'requires_human_verification' => true,
            'mutation_applied'            => false, // Hard architectural guarantee
        ];
    }

    /**
     * Analyze all Unresolved Assets for a Feeder (Batch Mode)
     */
    public function analyzeFeeder(int $feederId, int $limit = 50): array
    {
        $feeder = $this->resolveFeeder($feederId);
        if (!$feeder) {
            return [
                'success' => false,
                'error'   => "Penyulang ID #{$feederId} tidak ditemukan.",
            ];
        }

        $sections = $this->resolveSections($feederId);
        if (empty($sections)) {
            return [
                'success' => false,
                'error'   => "Belum ada seksi CR-06F aktif untuk penyulang [{$feeder['kode_penyulang']}] {$feeder['nama_penyulang']}.",
            ];
        }

        $boundaries = $this->resolveBoundaryDevices($sections, $feederId);

        // Fetch unresolved assets
        $builder = $this->db->table('assets')
            ->where('penyulang_id', $feederId)
            ->groupStart()
                ->where('section_id IS NULL')
                ->orWhere('section_id', 0)
            ->groupEnd();
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }
        $totalUnresolved = $builder->countAllResults(false);
        $assets = $builder->orderBy('id', 'ASC')->limit($limit)->get()->getResultArray();

        // Fetch all assets in feeder for continuity calculations
        $fAssetsBuilder = $this->db->table('assets')->where('penyulang_id', $feederId);
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $fAssetsBuilder->where('deleted_at IS NULL');
        }
        $getFAssets = $fAssetsBuilder->get();
        $allFeederAssets = $getFAssets ? $getFAssets->getResultArray() : [];

        $results = [];
        $highCount = 0;
        $ambiguousCount = 0;
        $lowCount = 0;

        foreach ($assets as $a) {
            $rawCandidates = [];
            foreach ($sections as $sec) {
                $secId = (int)$sec['id'];
                $boundary = $boundaries[$secId] ?? null;
                $rawCandidates[] = $this->scoreCandidate($a, $sec, $boundary, $allFeederAssets);
            }

            $ranking = $this->rankCandidates($rawCandidates);
            $top = $ranking['candidates'][0] ?? null;

            if ($ranking['is_ambiguous']) {
                $ambiguousCount++;
            } elseif ($top && $top['confidence'] === 'HIGH') {
                $highCount++;
            } else {
                $lowCount++;
            }

            $results[] = [
                'asset_id'           => (int)$a['id'],
                'kode_asset'         => $a['kode_asset'] ?? $a['nama_asset'],
                'latitude'           => $a['latitude'] ? (float)$a['latitude'] : null,
                'longitude'          => $a['longitude'] ? (float)$a['longitude'] : null,
                'section_candidates' => $ranking['candidates'],
                'margin_percent'     => $ranking['margin_pct'],
                'is_ambiguous'       => $ranking['is_ambiguous'],
                'top_recommendation' => $top ? [
                    'section_id'   => $top['section_id'],
                    'section_name' => $top['section_name'],
                    'score'        => $top['score'],
                    'confidence'   => $top['confidence'],
                ] : null,
            ];
        }

        return [
            'success'          => true,
            'engine'           => 'AR-01-SPATIAL-CANDIDATE',
            'contract_version' => '1.0',
            'feeder_id'        => $feederId,
            'kode_penyulang'   => $feeder['kode_penyulang'] ?? 'N/A',
            'nama_penyulang'   => $feeder['nama_penyulang'] ?? 'N/A',
            'total_unresolved' => $totalUnresolved,
            'analyzed_count'   => count($results),
            'statistics'       => [
                'high_confidence' => $highCount,
                'ambiguous'       => $ambiguousCount,
                'low_unresolved'  => $lowCount,
            ],
            'sections'         => $sections,
            'boundaries'       => $boundaries,
            'assets'           => $results,
            'mutation_applied' => false,
        ];
    }

    /**
     * Helper to find matching device in feeder assets list
     */
    protected function findMatchingDeviceAsset(string $token, array $feederAssets): ?array
    {
        $clean = strtoupper(trim(preg_replace('/[^A-Za-z0-9]/', '', $token)));
        if (empty($clean) || in_array($clean, ['GI', 'UJUNG', 'UJU'], true)) {
            return null;
        }

        foreach ($feederAssets as $a) {
            $name = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $a['nama_asset'] ?? ''));
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $a['kode_asset'] ?? ''));

            if (str_contains($name, $clean) || str_contains($code, $clean)) {
                return [
                    'asset_id'   => (int)$a['id'],
                    'kode_asset' => $a['kode_asset'] ?? '',
                    'nama_asset' => $a['nama_asset'] ?? '',
                    'latitude'   => $a['latitude'] ?? null,
                    'longitude'  => $a['longitude'] ?? null,
                ];
            }
        }

        return null;
    }
}
