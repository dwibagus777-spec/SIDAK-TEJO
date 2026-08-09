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

        $builder = $db->table('network_baselines nb');
        $builder->select('nb.*, nb.network_type as type');
        
        if ($db->tableExists('ulps')) {
            $builder->select('u.nama_ulp');
            $builder->join('ulps u', 'nb.ulp_id = u.id', 'left');
        }
        if ($db->tableExists('penyulang')) {
            $builder->select('p.nama_penyulang');
            $builder->join('penyulang p', 'nb.penyulang_id = p.id', 'left');
        }
        if ($db->tableExists('baseline_assets')) {
            $builder->select('COUNT(ba.id) as total_assets');
            $builder->join('baseline_assets ba', 'nb.id = ba.baseline_id', 'left');
        }
        
        $builder->groupBy('nb.id');
        $builder->orderBy('nb.name', 'ASC');

        $result = $builder->get()->getResultArray();
        if (!empty($result)) {
            return $result;
        }

        return $this->baselineModel->findAll();
    }

    public function getBaselineDetail(int $baselineId, ?string $objectType = null): ?array
    {
        $baseline = $this->baselineModel->find($baselineId);
        if (!$baseline) {
            return null;
        }

        $assets = $this->getOrderedBaselineAssets($baselineId);

        if (!empty($objectType) && strtoupper($objectType) !== 'SEMUA') {
            $objUpper = strtoupper($objectType);
            $assets = array_values(array_filter($assets, function ($ast) use ($objUpper) {
                return strtoupper($ast['jenis_asset'] ?? '') === $objUpper;
            }));
        }

        $baseline['assets'] = $assets;
        return $baseline;
    }

    public function resolveBaselineForPenyulang(int $penyulangId, ?string $objectType = null): ?array
    {
        $existing = $this->baselineModel->where('penyulang_id', $penyulangId)->first();
        if ($existing) {
            return $this->getBaselineDetail((int)$existing['id'], $objectType);
        }

        $all = $this->getBaselines();
        foreach ($all as $b) {
            if ((int)($b['penyulang_id'] ?? 0) === $penyulangId) {
                return $this->getBaselineDetail((int)$b['id'], $objectType);
            }
        }

        if (!empty($all)) {
            return $this->getBaselineDetail((int)$all[0]['id'], $objectType);
        }

        return null;
    }

    public function resolveBaselineForPlanning(int $planningId): ?array
    {
        $db = \Config\Database::connect();
        $planning = $db->table('inspection_plannings')->where('id', $planningId)->get()->getRowArray();
        if (!$planning) {
            return null;
        }

        $builder = $db->table('inspection_planning_assets pa');
        $builder->select('pa.sequence_no, 0.0 as distance_from_previous, NULL as section_name, a.id as asset_id, a.kode_asset, a.nama_asset, a.jenis_asset, a.status, a.latitude, a.longitude, ct.code as construction_code, ct.name as construction_name', false);
        $builder->join('assets a', 'pa.asset_id = a.id');
        $builder->join('construction_types ct', 'a.construction_type_id = ct.id', 'left');
        $builder->where('pa.planning_id', $planningId);
        $builder->where('a.deleted_at IS NULL');
        $builder->orderBy('pa.sequence_no', 'ASC');

        $assets = $builder->get()->getResultArray();

        return [
            'id'           => 0,
            'name'         => $planning['title'],
            'ulp_id'       => $planning['ulp_id'],
            'penyulang_id' => $planning['penyulang_id'],
            'assets'       => $assets,
        ];
    }
}
