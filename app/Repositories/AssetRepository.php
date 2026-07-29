<?php

namespace App\Repositories;

use App\Models\AssetModel;
use CodeIgniter\Database\BaseResult;

class AssetRepository
{
    private AssetModel $model;

    public function __construct()
    {
        $this->model = new AssetModel();
    }

    public function find(int $id): ?array
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('assets a');
            $builder->select('a.*, u.nama_ulp, p.nama_penyulang, s.nama_section');
            $builder->join('ulps u', 'a.ulp_id = u.id', 'left');
            $builder->join('penyulang p', 'a.penyulang_id = p.id', 'left');
            $builder->join('sections s', 'a.section_id = s.id', 'left');
            $builder->where('a.id', $id);
            $builder->where('a.deleted_at IS NULL');

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[AssetRepository::find] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown') . ' | SQL: ' . (string)$db->getLastQuery());
                return null;
            }

            return $query->getRowArray() ?: null;
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::find] Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function findByKode(string $kode): ?array
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('assets a');
            $builder->select('a.*, u.nama_ulp, p.nama_penyulang, s.nama_section');
            $builder->join('ulps u', 'a.ulp_id = u.id', 'left');
            $builder->join('penyulang p', 'a.penyulang_id = p.id', 'left');
            $builder->join('sections s', 'a.section_id = s.id', 'left');
            $builder->where('a.kode_asset', $kode);
            $builder->where('a.deleted_at IS NULL');

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[AssetRepository::findByKode] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown') . ' | SQL: ' . (string)$db->getLastQuery());
                return null;
            }

            return $query->getRowArray() ?: null;
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::findByKode] Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function getFilteredAssets(array $filters = [], ?int $userUlpId = null): array
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('assets a');
            $builder->select('a.*, u.nama_ulp, p.nama_penyulang, s.nama_section');
            $builder->join('ulps u', 'a.ulp_id = u.id', 'left');
            $builder->join('penyulang p', 'a.penyulang_id = p.id', 'left');
            $builder->join('sections s', 'a.section_id = s.id', 'left');
            $builder->where('a.deleted_at IS NULL');

            if (!empty($userUlpId)) {
                $builder->where('a.ulp_id', $userUlpId);
            }

            if (!empty($filters['ulp_id'])) {
                $builder->where('a.ulp_id', (int)$filters['ulp_id']);
            }
            if (!empty($filters['penyulang_id'])) {
                $builder->where('a.penyulang_id', (int)$filters['penyulang_id']);
            }
            if (!empty($filters['section_id'])) {
                $builder->where('a.section_id', (int)$filters['section_id']);
            }
            if (!empty($filters['jenis_asset'])) {
                $builder->where('a.jenis_asset', $filters['jenis_asset']);
            }
            if (!empty($filters['status'])) {
                $builder->where('a.status', strtoupper($filters['status']));
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $builder->groupStart()
                    ->like('a.kode_asset', $s)
                    ->orLike('a.nama_asset', $s)
                    ->orLike('a.lokasi', $s)
                    ->orLike('a.merk', $s)
                    ->orLike('a.nomor_seri', $s)
                ->groupEnd();
            }

            $builder->orderBy('a.id', 'DESC');

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[AssetRepository::getFilteredAssets] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown') . ' | SQL: ' . (string)$db->getLastQuery());
                return [];
            }

            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::getFilteredAssets] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function getAssetStats(?int $userUlpId = null): array
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('assets');
            $builder->where('deleted_at IS NULL');

            if (!empty($userUlpId)) {
                $builder->where('ulp_id', $userUlpId);
            }

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[AssetRepository::getAssetStats] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown') . ' | SQL: ' . (string)$db->getLastQuery());
                return ['total' => 0, 'normal' => 0, 'bermasalah' => 0, 'critical' => 0];
            }

            $all = $query->getResultArray();

            $total = count($all);
            $normal = 0;
            $bermasalah = 0;
            $critical = 0;

            foreach ($all as $row) {
                $st = strtoupper($row['status'] ?? 'NORMAL');
                if ($st === 'BERMASALAH') {
                    $bermasalah++;
                } elseif ($st === 'CRITICAL') {
                    $critical++;
                } else {
                    $normal++;
                }
            }

            return [
                'total'      => $total,
                'normal'     => $normal,
                'bermasalah' => $bermasalah,
                'critical'   => $critical,
            ];
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::getAssetStats] Exception: ' . $e->getMessage());
            return ['total' => 0, 'normal' => 0, 'bermasalah' => 0, 'critical' => 0];
        }
    }

    public function insert(array $data): int
    {
        return (int)$this->model->insert($data, true);
    }

    public function update(int $id, array $data): bool
    {
        return (bool)$this->model->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return (bool)$this->model->delete($id);
    }
}
