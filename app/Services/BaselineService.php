<?php

namespace App\Services;

use App\Models\BaselineAssetModel;
use App\Models\NetworkBaselineModel;
use App\Models\AssetModel;

class BaselineService
{
    private NetworkBaselineModel $baselineModel;
    private BaselineAssetModel $baselineAssetModel;
    private AssetModel $assetModel;

    public function __construct()
    {
        $this->baselineModel = new NetworkBaselineModel();
        $this->baselineAssetModel = new BaselineAssetModel();
        $this->assetModel = new AssetModel();
    }

    public function createBaseline(array $data): int
    {
        return $this->baselineModel->insert([
            'name'           => $data['name'],
            'network_type'   => $data['network_type'] ?? 'JTM',
            'ulp_id'         => $data['ulp_id'] ?? null,
            'penyulang_id'   => $data['penyulang_id'] ?? null,
            'gardu_id'       => $data['gardu_id'] ?? null,
            'trafo_id'       => $data['trafo_id'] ?? null,
            'version'        => $data['version'] ?? 'v1.0',
            'effective_date' => $data['effective_date'] ?? date('Y-m-d'),
            'status'         => 'ACTIVE',
        ]);
    }

    public function addAssetToBaseline(int $baselineId, int $assetId, int $sequenceNo, float $distance = 0.0, ?string $sectionName = null): bool
    {
        $existing = $this->baselineAssetModel
            ->where('baseline_id', $baselineId)
            ->where('asset_id', $assetId)
            ->first();

        if ($existing) {
            return $this->baselineAssetModel->update($existing['id'], [
                'sequence_no'            => $sequenceNo,
                'distance_from_previous' => $distance,
                'section_name'           => $sectionName,
            ]);
        }

        $id = $this->baselineAssetModel->insert([
            'baseline_id'            => $baselineId,
            'asset_id'               => $assetId,
            'sequence_no'            => $sequenceNo,
            'distance_from_previous' => $distance,
            'section_name'           => $sectionName,
        ]);

        return (bool)$id;
    }

    public function getOrderedBaselineAssets(int $baselineId): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('baseline_assets')) {
            return [];
        }

        $builder = $db->table('baseline_assets ba');
        $builder->select('ba.sequence_no, ba.distance_from_previous, ba.section_name, a.id as asset_id, a.kode_asset, a.nama_asset, a.jenis_asset, a.status, a.latitude, a.longitude, ct.code as construction_code, ct.name as construction_name');
        $builder->join('assets a', 'ba.asset_id = a.id');
        $builder->join('construction_types ct', 'a.construction_type_id = ct.id', 'left');
        $builder->where('ba.baseline_id', $baselineId);
        $builder->orderBy('ba.sequence_no', 'ASC');

        return $builder->get()->getResultArray();
    }

    public function getBaselines(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('network_baselines')) {
            return [];
        }
        return $this->baselineModel->orderBy('name', 'ASC')->findAll();
    }
}
