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
            if (!$db->tableExists('assets')) {
                return null;
            }
            $builder = $db->table('assets a');
            $builder->select('a.*, u.nama_ulp, p.nama_penyulang, s.nama_section');
            $builder->join('ulps u', 'a.ulp_id = u.id', 'left');
            $builder->join('penyulang p', 'a.penyulang_id = p.id', 'left');
            $builder->join('sections s', 'a.section_id = s.id', 'left');
            $builder->where('a.id', $id);
            $builder->where('a.deleted_at IS NULL');

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
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
            if (!$db->tableExists('assets')) {
                return null;
            }
            $builder = $db->table('assets a');
            $builder->select('a.*, u.nama_ulp, p.nama_penyulang, s.nama_section');
            $builder->join('ulps u', 'a.ulp_id = u.id', 'left');
            $builder->join('penyulang p', 'a.penyulang_id = p.id', 'left');
            $builder->join('sections s', 'a.section_id = s.id', 'left');
            $builder->where('a.kode_asset', $kode);
            $builder->where('a.deleted_at IS NULL');

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
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
            if (!$db->tableExists('assets')) {
                return [];
            }
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
                $builder->where('a.jenis_asset', strtoupper($filters['jenis_asset']));
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

            return ($query && $query instanceof BaseResult) ? $query->getResultArray() : [];
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::getFilteredAssets] Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Complete Feeder Topology Network Polyline Coordinates (Topology Sequence Ordered)
     * Independent of Marker Layer Filters!
     */
    public function getFeederPolylineCoords(int $penyulangId): array
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('assets') || $penyulangId <= 0) {
                return [];
            }

            $hasSeqCol = $db->fieldExists('sequence_no', 'assets');

            $builder = $db->table('assets');
            $builder->select('longitude, latitude');
            $builder->where('penyulang_id', $penyulangId);
            $builder->where('deleted_at IS NULL');
            $builder->where('latitude !=', 0);
            $builder->where('longitude !=', 0);

            if ($hasSeqCol) {
                $builder->orderBy('sequence_no', 'ASC');
            } else {
                $builder->orderBy('id', 'ASC');
            }

            return $builder->get()->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::getFeederPolylineCoords] Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Optimized SQL-level GIS query engine with Strict Layer Mapping & Topology Ordering
     */
    public function getGisNetworkAssets(array $filters = [], ?int $userUlpId = null): array
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('assets')) {
                return [];
            }

            $penyulangId = (int)($filters['penyulang_id'] ?? 0);
            $zoom        = (int)($filters['zoom'] ?? 14);
            $layers      = $filters['layers'] ?? ['JTM', 'GARDU', 'TRAFO', 'SWITCH'];

            if ($penyulangId <= 0) {
                return [];
            }

            $hasSeqCol = $db->fieldExists('sequence_no', 'assets');

            $builder = $db->table('assets a');
            $selectFields = 'a.id, a.kode_asset, a.nama_asset, a.jenis_asset, a.status, a.latitude, a.longitude, a.lokasi, ct.name as construction_name, ct.code as construction_code';
            if ($hasSeqCol) {
                $selectFields .= ', a.sequence_no';
            }
            $builder->select($selectFields);
            $builder->join('construction_types ct', 'a.construction_type_id = ct.id', 'left');
            $builder->where('a.penyulang_id', $penyulangId);
            $builder->where('a.deleted_at IS NULL');
            $builder->where('a.latitude !=', 0);
            $builder->where('a.longitude !=', 0);

            if (!empty($userUlpId)) {
                $builder->where('a.ulp_id', $userUlpId);
            }

            // 1. Zoom < 13: SQL returns ONLY lat/lng for Polyline (0 point markers processed)
            if ($zoom < 13) {
                if ($hasSeqCol) {
                    $builder->select('a.latitude, a.longitude, a.jenis_asset, a.sequence_no');
                    $builder->orderBy('a.sequence_no', 'ASC');
                } else {
                    $builder->select('a.latitude, a.longitude, a.jenis_asset');
                    $builder->orderBy('a.id', 'ASC');
                }
                return $builder->get()->getResultArray();
            }

            // Build Strict Layer Filter Mapping
            $allowedJenisList = [];
            $includeGtt = false;
            $includeSwitchEquipment = false;

            if (in_array('JTM', $layers)) {
                $allowedJenisList[] = 'JTM';
                $includeGtt = true;
            }
            if (in_array('GARDU', $layers)) {
                $allowedJenisList[] = 'GARDU';
                $includeGtt = true;
            }
            if (in_array('TRAFO', $layers)) {
                $allowedJenisList[] = 'TRAFO';
            }
            if (in_array('KUBIKEL', $layers)) {
                $allowedJenisList[] = 'KUBIKEL';
            }
            if (in_array('SWITCH', $layers)) {
                $includeSwitchEquipment = true;
                $allowedJenisList = array_merge($allowedJenisList, ['LBS', 'LBSM', 'RECLOSER', 'SECTIONALIZER']);
            }

            // Hardening 1: Empty Layer Guard — If all layers unchecked, return [] immediately
            if (empty($allowedJenisList) && !$includeGtt && !$includeSwitchEquipment) {
                return [];
            }

            // 2. Zoom 13 - 16: SQL-level Equipment Filter (Strictly respecting active layers!)
            if ($zoom >= 13 && $zoom <= 16) {
                $equipTypes = ['GARDU', 'TRAFO', 'KUBIKEL', 'LBS', 'LBSM', 'RECLOSER', 'SECTIONALIZER'];
                if (!empty($allowedJenisList)) {
                    $equipTypes = array_intersect($equipTypes, $allowedJenisList);
                }

                $builder->groupStart();
                if (!empty($equipTypes)) {
                    $builder->whereIn('a.jenis_asset', $equipTypes);
                }
                if ($includeGtt) {
                    $builder->orLike('ct.code', 'GTT');
                }
                if ($includeSwitchEquipment) {
                    $builder->orLike('ct.code', 'PMS')->orLike('ct.code', 'PMT');
                }
                $builder->groupEnd();
            } else {
                // Zoom >= 17: Apply strict layer filters at SQL level
                if (!empty($allowedJenisList)) {
                    $builder->groupStart();
                    $builder->whereIn('a.jenis_asset', $allowedJenisList);
                    if ($includeSwitchEquipment) {
                        $builder->orLike('ct.code', 'PMS')->orLike('ct.code', 'PMT');
                    }
                    $builder->groupEnd();
                }
            }

            if ($hasSeqCol) {
                $builder->orderBy('a.sequence_no', 'ASC');
            } else {
                $builder->orderBy('a.id', 'ASC');
            }

            $query = $builder->get();
            return ($query && $query instanceof BaseResult) ? $query->getResultArray() : [];
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::getGisNetworkAssets] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function getAssetStats(?int $userUlpId = null): array
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('assets')) {
                return ['total' => 0, 'normal' => 0, 'bermasalah' => 0, 'critical' => 0];
            }
            $builder = $db->table('assets');
            $builder->where('deleted_at IS NULL');
            if (!empty($userUlpId)) {
                $builder->where('ulp_id', $userUlpId);
            }

            $total = $builder->countAllResults(false);

            $normal = (clone $builder)->where('status', 'NORMAL')->countAllResults();
            $bermasalah = (clone $builder)->where('status', 'BERMASALAH')->countAllResults();
            $critical = (clone $builder)->where('status', 'CRITICAL')->countAllResults();

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
}
