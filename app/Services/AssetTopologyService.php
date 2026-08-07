<?php

namespace App\Services;

use App\Models\AssetModel;
use App\Models\AssetRelationshipModel;

class AssetTopologyService
{
    private AssetModel $assetModel;
    private AssetRelationshipModel $relationshipModel;

    public function __construct()
    {
        $this->assetModel = new AssetModel();
        $this->relationshipModel = new AssetRelationshipModel();
    }

    /**
     * Circular Topology Guard: Cek apakah penambahan relasi sourceId -> targetId menyebabkan cycle (A -> B -> C -> A)
     */
    public function wouldCreateCycle(int $sourceId, int $targetId): bool
    {
        if ($sourceId === $targetId) {
            return true; // Self-loop
        }

        // BFS traversal dari targetId untuk mengecek apakah sourceId dapat dicapai
        $visited = [];
        $queue = [$targetId];

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            if ($currentId === $sourceId) {
                return true; // Cycle terdeteksi!
            }

            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            // Cari relasi di mana currentId menjadi source_asset_id
            $outgoingRels = $this->relationshipModel
                ->where('source_asset_id', $currentId)
                ->where('is_active', 1)
                ->findAll();

            foreach ($outgoingRels as $rel) {
                $nextId = (int)$rel['target_asset_id'];
                if (!isset($visited[$nextId])) {
                    $queue[] = $nextId;
                }
            }

            // Juga cek parent_asset_id pada tabel assets
            $asset = $this->assetModel->find($currentId);
            if ($asset && !empty($asset['parent_asset_id'])) {
                $parentId = (int)$asset['parent_asset_id'];
                if (!isset($visited[$parentId])) {
                    $queue[] = $parentId;
                }
            }
        }

        return false; // Aman, tidak ada cycle
    }

    /**
     * Tambah relasi topologi dengan validasi anti-cycle
     */
    public function addRelationship(int $sourceId, int $targetId, string $type = 'CONNECTED_TO', int $sequenceNo = 0): array
    {
        if ($this->wouldCreateCycle($sourceId, $targetId)) {
            return [
                'success' => false,
                'message' => 'Penambahan relasi ditolak: Terdeteksi circular topology (loop tertutup) pada jaringan listrik!',
            ];
        }

        // Cek jika relasi sudah ada
        $existing = $this->relationshipModel
            ->where('source_asset_id', $sourceId)
            ->where('target_asset_id', $targetId)
            ->where('relationship_type', $type)
            ->first();

        if ($existing) {
            $this->relationshipModel->update($existing['id'], [
                'sequence_no' => $sequenceNo,
                'is_active'   => 1,
            ]);
            return ['success' => true, 'id' => $existing['id'], 'message' => 'Relasi diperbarui.'];
        }

        $id = $this->relationshipModel->insert([
            'source_asset_id'   => $sourceId,
            'target_asset_id'   => $targetId,
            'relationship_type' => $type,
            'sequence_no'       => $sequenceNo,
            'is_active'         => 1,
            'effective_date'    => date('Y-m-d'),
        ]);

        return ['success' => true, 'id' => $id, 'message' => 'Relasi topologi berhasil ditambahkan.'];
    }

    /**
     * Dapatkan hirarki topologi (Upstream & Downstream) dalam bentuk Tree
     */
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

        // Cari anak direct via parent_asset_id
        $children = $this->assetModel->where('parent_asset_id', $assetId)->findAll();
        foreach ($children as $child) {
            $childTree = $this->buildNodeTree((int)$child['id'], $currentDepth + 1, $maxDepth, $visited);
            if (!empty($childTree)) {
                $node['downstream'][] = $childTree;
            }
        }

        // Cari anak via asset_relationships (DOWNSTREAM / SUPPLIES / CONNECTED_TO)
        $rels = $this->relationshipModel
            ->where('source_asset_id', $assetId)
            ->where('is_active', 1)
            ->findAll();

        foreach ($rels as $rel) {
            $targetId = (int)$rel['target_asset_id'];
            if (!isset($visited[$targetId])) {
                $targetTree = $this->buildNodeTree($targetId, $currentDepth + 1, $maxDepth, $visited);
                if (!empty($targetTree)) {
                    $node['downstream'][] = $targetTree;
                }
            }
        }

        return $node;
    }
}
