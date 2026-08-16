<?php

namespace App\Services;

use App\Models\AssetModel;
use App\Models\AssetRelationshipModel;

class AssetTopologyService
{
    private AssetModel $assetModel;
    private AssetRelationshipModel $relationshipModel;

    private const TOPOLOGY_RULES = [
        'GI' => ['JTM', 'PMT', 'RECLOSER', 'LBS', 'GARDU'],
        'JTM' => ['JTM', 'GARDU', 'TRAFO', 'LBS', 'LBSM', 'RECLOSER', 'SECTIONALIZER'],
        'GARDU' => ['TRAFO', 'JTM', 'KUBIKEL'],
        'KUBIKEL' => ['TRAFO', 'JTM'],
        'LBS' => ['JTM', 'GARDU'],
        'LBSM' => ['JTM', 'GARDU'],
        'RECLOSER' => ['JTM', 'GARDU'],
        'SECTIONALIZER' => ['JTM', 'GARDU'],
    ];

    public function __construct()
    {
        $this->assetModel = new AssetModel();
        $this->relationshipModel = new AssetRelationshipModel();
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function calculateHierarchyScore(string $parentJenis, string $childJenis): float
    {
        $p = strtoupper(trim($parentJenis));
        $c = strtoupper(trim($childJenis));

        if (isset(self::TOPOLOGY_RULES[$p]) && in_array($c, self::TOPOLOGY_RULES[$p])) {
            return 100.0;
        }
        if ($p === $c) {
            return 90.0;
        }
        if ($p === 'TRAFO' && in_array($c, ['JTM', 'GI'])) {
            return 0.0;
        }
        return 50.0;
    }

    private function calculateDistanceScore(float $distanceMeters): float
    {
        if ($distanceMeters <= 50.0) return 100.0;
        if ($distanceMeters <= 150.0) return 85.0;
        if ($distanceMeters <= 350.0) return 60.0;
        if ($distanceMeters <= 500.0) return 30.0;
        return 0.0;
    }

    /**
     * Circular Topology Guard: Cek apakah penambahan relasi sourceId -> targetId menyebabkan cycle (A -> B -> C -> A)
     */
    public function wouldCreateCycle(int $sourceId, int $targetId): bool
    {
        if ($sourceId === $targetId) {
            return true;
        }

        $visited = [];
        $queue = [$targetId];

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            if ($currentId === $sourceId) {
                return true;
            }

            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            $outgoingRels = $this->relationshipModel
                ->where('parent_asset_id', $currentId)
                ->where('is_active', 1)
                ->findAll();

            foreach ($outgoingRels as $rel) {
                $nextId = (int)($rel['child_asset_id'] ?? $rel['target_asset_id'] ?? 0);
                if ($nextId > 0 && !isset($visited[$nextId])) {
                    $queue[] = $nextId;
                }
            }

            $asset = $this->assetModel->find($currentId);
            if ($asset && !empty($asset['parent_asset_id'])) {
                $parentId = (int)$asset['parent_asset_id'];
                if (!isset($visited[$parentId])) {
                    $queue[] = $parentId;
                }
            }
        }

        return false;
    }

    /**
     * Non-destructive Candidate Engine with Multi-Candidate Ranking & Gap Score
     */
    public function generateFeederTopologyCandidates(int $penyulangId, ?int $userUlpId = null): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('assets') || $penyulangId <= 0) {
            return ['status' => 'error', 'message' => 'Tabel assets atau ID penyulang tidak valid.'];
        }

        $builder = $db->table('assets a');
        $builder->select('a.id, a.parent_asset_id, a.jenis_asset, a.latitude, a.longitude, a.sequence_no');
        $builder->where('a.penyulang_id', $penyulangId);
        $builder->where('a.deleted_at IS NULL');
        $builder->where('a.latitude !=', 0);
        $builder->where('a.longitude !=', 0);

        if (!empty($userUlpId)) {
            $builder->where('a.ulp_id', $userUlpId);
        }

        $assets = $builder->get()->getResultArray();
        if (count($assets) < 2) {
            return ['status' => 'success', 'generated_count' => 0, 'candidates' => []];
        }

        $createdCandidates = [];
        $savedCount = 0;

        foreach ($assets as $child) {
            $childId    = (int)$child['id'];
            $childJenis = strtoupper((string)$child['jenis_asset']);
            $cLat       = (float)$child['latitude'];
            $cLng       = (float)$child['longitude'];

            if (!empty($child['parent_asset_id'])) {
                continue;
            }

            $candidatePool = [];

            foreach ($assets as $parent) {
                $parentId    = (int)$parent['id'];
                $parentJenis = strtoupper((string)$parent['jenis_asset']);
                $pLat        = (float)$parent['latitude'];
                $pLng        = (float)$parent['longitude'];

                if ($childId === $parentId) continue;
                if ($this->wouldCreateCycle($parentId, $childId)) continue;

                $distance = $this->haversineDistance($pLat, $pLng, $cLat, $cLng);
                if ($distance > 500.0) continue;

                $hScore = $this->calculateHierarchyScore($parentJenis, $childJenis);
                if ($hScore <= 0) continue;

                $dScore = $this->calculateDistanceScore($distance);
                $aScore = 80.0;

                $confidence = ($hScore * 0.40) + ($dScore * 0.40) + ($aScore * 0.20);

                $candidatePool[] = [
                    'parent_id'       => $parentId,
                    'child_id'        => $childId,
                    'distance_meters' => round($distance, 2),
                    'hierarchy_score' => $hScore,
                    'distance_score'  => $dScore,
                    'angle_score'      => $aScore,
                    'confidence'      => round($confidence, 2),
                ];
            }

            if (empty($candidatePool)) continue;

            usort($candidatePool, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

            $bestCandidate = $candidatePool[0];
            $secondScore   = isset($candidatePool[1]) ? $candidatePool[1]['confidence'] : 0.0;
            $gapScore      = $bestCandidate['confidence'] - $secondScore;

            $existing = $this->relationshipModel
                ->where('parent_asset_id', $bestCandidate['parent_id'])
                ->where('child_asset_id', $bestCandidate['child_id'])
                ->first();

            $recordData = [
                'parent_asset_id'   => $bestCandidate['parent_id'],
                'child_asset_id'    => $bestCandidate['child_id'],
                'source_asset_id'   => $bestCandidate['parent_id'],
                'target_asset_id'   => $bestCandidate['child_id'],
                'relationship_type' => 'NETWORK',
                'source'            => 'AUTO',
                'confidence_score'  => $bestCandidate['confidence'],
                'status'            => 'CANDIDATE',
                'distance_meters'   => $bestCandidate['distance_meters'],
                'hierarchy_score'   => $bestCandidate['hierarchy_score'],
                'distance_score'    => $bestCandidate['distance_score'],
                'angle_score'        => $bestCandidate['angle_score'],
                'is_active'         => 1,
            ];

            if ($existing) {
                if (($existing['status'] ?? 'CANDIDATE') !== 'VERIFIED') {
                    $this->relationshipModel->update($existing['id'], $recordData);
                }
            } else {
                $this->relationshipModel->insert($recordData);
            }

            $bestCandidate['gap_score'] = round($gapScore, 2);
            $createdCandidates[] = $bestCandidate;
            $savedCount++;
        }

        return [
            'status'          => 'success',
            'generated_count' => $savedCount,
            'candidates'      => $createdCandidates
        ];
    }

    public function getTopologyTree(int $rootAssetId, int $maxDepth = 5): array
    {
        $visited = [];
        return $this->buildNodeTree($rootAssetId, 0, $maxDepth, $visited);
    }

    private function buildNodeTree(int $assetId, int $currentDepth, int $maxDepth, array &$visited): array
    {
        if ($currentDepth >= $maxDepth || isset($visited[$assetId])) {
            return [];
        }
        $visited[$assetId] = true;

        $asset = $this->assetModel->find($assetId);
        if (!$asset) {
            return [];
        }

        $node = [
            'id'           => $asset['id'],
            'kode_asset'   => $asset['kode_asset'],
            'nama_asset'   => $asset['nama_asset'],
            'jenis_asset'  => $asset['jenis_asset'],
            'status'       => $asset['status'],
            'downstream'   => [],
        ];

        $children = $this->assetModel->where('parent_asset_id', $assetId)->findAll();
        foreach ($children as $child) {
            $childTree = $this->buildNodeTree((int)$child['id'], $currentDepth + 1, $maxDepth, $visited);
            if (!empty($childTree)) {
                $node['downstream'][] = $childTree;
            }
        }

        return $node;
    }
}
