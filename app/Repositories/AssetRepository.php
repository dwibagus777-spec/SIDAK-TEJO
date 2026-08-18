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
        $res = $this->getFilteredAssetsPaginated($filters, $userUlpId, 1, 10000);
        return $res['data'] ?? [];
    }

    private function applyAssetFilters($builder, array $filters = [], ?int $userUlpId = null): void
    {
        $builder->where('a.deleted_at IS NULL');

        if (!empty($userUlpId)) {
            $builder->groupStart()
                ->where('a.ulp_id', $userUlpId)
                ->orWhere('a.ulp_id IS NULL')
                ->orWhere('a.ulp_id', 0)
            ->groupEnd();
        }
        if (!empty($filters['ulp_id']) && is_numeric($filters['ulp_id']) && (int)$filters['ulp_id'] > 0) {
            $uId = (int)$filters['ulp_id'];
            $builder->groupStart()
                ->where('a.ulp_id', $uId)
                ->orWhere('a.ulp_id IS NULL')
                ->orWhere('a.ulp_id', 0)
            ->groupEnd();
        }
        if (!empty($filters['penyulang_id']) && is_numeric($filters['penyulang_id']) && (int)$filters['penyulang_id'] > 0) {
            $pId = (int)$filters['penyulang_id'];
            $builder->groupStart()
                ->where('a.penyulang_id', $pId)
                ->orWhere('a.penyulang_id IS NULL')
                ->orWhere('a.penyulang_id', 0)
            ->groupEnd();
        }
        if (!empty($filters['section_id']) && is_numeric($filters['section_id']) && (int)$filters['section_id'] > 0) {
            $builder->where('a.section_id', (int)$filters['section_id']);
        }
        if (!empty($filters['jenis_asset']) && trim($filters['jenis_asset']) !== '') {
            $jVal = strtoupper(trim($filters['jenis_asset']));
            if ($jVal === 'JTM') {
                $builder->groupStart()
                    ->where('a.jenis_asset', 'JTM')
                    ->orLike('a.jenis_asset', 'TIANG')
                    ->orLike('a.jenis_asset', 'POLE')
                    ->orLike('a.jenis_asset', 'SUTM')
                    ->orLike('a.jenis_asset', 'SKTM')
                ->groupEnd();
            } elseif ($jVal === 'GARDU') {
                $builder->groupStart()
                    ->where('a.jenis_asset', 'GARDU')
                    ->orLike('a.jenis_asset', 'GH')
                    ->orLike('a.jenis_asset', 'GD')
                    ->orLike('a.jenis_asset', 'GI')
                ->groupEnd();
            } elseif ($jVal === 'TRAFO') {
                $builder->groupStart()
                    ->where('a.jenis_asset', 'TRAFO')
                    ->orLike('a.jenis_asset', 'TRANSFORMATOR')
                    ->orLike('a.jenis_asset', 'GTT')
                ->groupEnd();
            } elseif ($jVal === 'LBS') {
                $builder->groupStart()
                    ->where('a.jenis_asset', 'LBS')
                    ->orLike('a.jenis_asset', 'LBSM')
                    ->orLike('a.jenis_asset', 'SWITCH')
                ->groupEnd();
            } else {
                $builder->where('a.jenis_asset', $jVal);
            }
        }
        if (!empty($filters['status']) && trim($filters['status']) !== '') {
            $builder->where('a.status', strtoupper(trim($filters['status'])));
        }
        if (!empty($filters['search'])) {
            $s = trim((string)$filters['search']);
            if ($s !== '') {
                $builder->groupStart()
                    ->like('a.kode_asset', $s)
                    ->orLike('a.nama_asset', $s)
                    ->orLike('a.lokasi', $s)
                    ->orLike('a.merk', $s)
                    ->orLike('a.nomor_seri', $s)
                ->groupEnd();
            }
        }
    }

    /**
     * Optimized Paginated Query (Server-Side Pagination for Fast Rendering)
     */
    public function getFilteredAssetsPaginated(array $filters = [], ?int $userUlpId = null, int $page = 1, int $perPage = 50): array
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('assets')) {
                return ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'last_page' => 1];
            }

            // 1. Separate Clean Count Query
            $countBuilder = $db->table('assets a');
            $this->applyAssetFilters($countBuilder, $filters, $userUlpId);
            $total = $countBuilder->countAllResults();

            if ($total === 0) {
                return ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'last_page' => 1];
            }

            // 2. Separate Clean Data Query
            $dataBuilder = $db->table('assets a');
            $dataBuilder->select('a.id, a.kode_asset, a.nama_asset, a.jenis_asset, a.status, a.lokasi, a.latitude, a.longitude, a.import_batch_id, a.created_at, u.nama_ulp, p.nama_penyulang, s.nama_section');
            $dataBuilder->join('ulps u', 'a.ulp_id = u.id', 'left');
            $dataBuilder->join('penyulang p', 'a.penyulang_id = p.id', 'left');
            $dataBuilder->join('sections s', 'a.section_id = s.id', 'left');
            $this->applyAssetFilters($dataBuilder, $filters, $userUlpId);

            $page = max(1, $page);
            $offset = ($page - 1) * $perPage;
            $dataBuilder->orderBy('a.id', 'DESC');
            $dataBuilder->limit($perPage, $offset);

            $query = $dataBuilder->get();
            $data = ($query && $query instanceof BaseResult) ? $query->getResultArray() : [];

            return [
                'data'      => $data,
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => max(1, ceil($total / $perPage))
            ];
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::getFilteredAssetsPaginated] Exception: ' . $e->getMessage());
            return ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'last_page' => 1];
        }
    }

    /**
     * Bulk Soft-Delete by Feeder / Filter
     */
    public function bulkSoftDeleteByFilter(array $filters = [], ?int $userUlpId = null, int $userId = 0, string $reason = 'BULK_DELETE_FILTER'): int
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('assets')) return 0;

            $builder = $db->table('assets');
            $builder->where('deleted_at IS NULL');

            if (!empty($userUlpId)) {
                $builder->where('ulp_id', $userUlpId);
            }
            if (!empty($filters['ulp_id'])) {
                $builder->where('ulp_id', (int)$filters['ulp_id']);
            }
            if (!empty($filters['penyulang_id'])) {
                $builder->where('penyulang_id', (int)$filters['penyulang_id']);
            }
            if (!empty($filters['section_id'])) {
                $builder->where('section_id', (int)$filters['section_id']);
            }
            if (!empty($filters['jenis_asset'])) {
                $builder->where('jenis_asset', strtoupper($filters['jenis_asset']));
            }

            $updateData = [
                'deleted_at'     => date('Y-m-d H:i:s'),
                'deleted_by'     => $userId > 0 ? $userId : null,
                'deleted_reason' => $reason,
            ];

            $builder->update($updateData);
            return $db->affectedRows();
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::bulkSoftDeleteByFilter] Exception: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Bulk Soft-Delete by Asset IDs (ULP Security Scoped)
     */
    public function bulkSoftDeleteByIds(array $assetIds, ?int $userUlpId = null, int $userId = 0, string $reason = 'BULK_DELETE_SELECTED'): int
    {
        try {
            if (empty($assetIds)) return 0;
            $db = \Config\Database::connect();
            if (!$db->tableExists('assets')) return 0;

            $builder = $db->table('assets');
            $builder->whereIn('id', array_map('intval', $assetIds));
            $builder->where('deleted_at IS NULL');

            if (!empty($userUlpId)) {
                $builder->where('ulp_id', $userUlpId);
            }

            $updateData = [
                'deleted_at'     => date('Y-m-d H:i:s'),
                'deleted_by'     => $userId > 0 ? $userId : null,
                'deleted_reason' => $reason,
            ];

            $builder->update($updateData);
            return $db->affectedRows();
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::bulkSoftDeleteByIds] Exception: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Rollback Specific Import Batch (Transaction, Topology Clean Up & ULP Security Scoped)
     */
    public function rollbackImportBatch(int $batchId, int $userId = 0, ?int $userUlpId = null): array
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('assets') || !$db->tableExists('asset_import_batches')) {
                return ['success' => false, 'message' => 'Tabel assets atau import batch tidak ditemukan.'];
            }

            $batchQuery = $db->table('asset_import_batches')->where('id', $batchId);
            if (!empty($userUlpId)) {
                $batchQuery->where('ulp_id', $userUlpId);
            }
            $batch = $batchQuery->get()->getRowArray();
            if (!$batch) {
                return ['success' => false, 'message' => 'Import batch tidak ditemukan atau Anda tidak memiliki akses ke ULP ini.'];
            }
            if (($batch['status'] ?? 'ACTIVE') === 'ROLLED_BACK') {
                return ['success' => false, 'message' => 'Import batch ini sudah pernah di-rollback sebelumnya.'];
            }

            // Find all asset IDs attached to this import_batch_id
            $assetQuery = $db->table('assets')
                ->select('id')
                ->where('import_batch_id', $batchId)
                ->where('deleted_at IS NULL');
            if (!empty($userUlpId)) {
                $assetQuery->where('ulp_id', $userUlpId);
            }
            $assetRows = $assetQuery->get()->getResultArray();
            $deletedAssetIds = array_column($assetRows, 'id');

            $db->transBegin();

            // 1. Soft-delete assets attached to this import_batch_id
            $assetUpdate = $db->table('assets')
                ->where('import_batch_id', $batchId)
                ->where('deleted_at IS NULL');
            if (!empty($userUlpId)) {
                $assetUpdate->where('ulp_id', $userUlpId);
            }
            $assetUpdate->update([
                'deleted_at'     => date('Y-m-d H:i:s'),
                'deleted_by'     => $userId > 0 ? $userId : null,
                'deleted_reason' => 'ROLLBACK_IMPORT_BATCH_' . $batchId,
            ]);

            $affectedCount = $db->affectedRows();

            // 2. Clean up orphaned topology relationships in asset_relationships
            if (!empty($deletedAssetIds) && $db->tableExists('asset_relationships')) {
                $db->table('asset_relationships')
                    ->groupStart()
                        ->whereIn('parent_asset_id', $deletedAssetIds)
                        ->orWhereIn('child_asset_id', $deletedAssetIds)
                    ->groupEnd()
                    ->update([
                        'is_active'  => 0,
                        'status'     => 'REJECTED',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            // 3. Mark batch status as ROLLED_BACK
            $db->table('asset_import_batches')
                ->where('id', $batchId)
                ->update([
                    'status'     => 'ROLLED_BACK',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($db->transStatus() === false) {
                $db->transRollback();
                return ['success' => false, 'message' => 'Gagal melakukan rollback: DB Transaction error.'];
            }

            $db->transCommit();

            return [
                'success'        => true,
                'affected_count' => $affectedCount,
                'batch_code'     => $batch['batch_code'],
                'message'        => "Import Batch #{$batch['batch_code']} berhasil di-rollback. Total {$affectedCount} aset di-soft delete dan topologi terkait dibersihkan secara aman."
            ];
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::rollbackImportBatch] Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
        }
    }

    /**
     * Calculate exact Haversine distance in meters between two lat/lng coordinates
     */
    private function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Get Complete Feeder Topology Network MultiLineString Segments & Nodes
     * Priority 1: parent_asset_id Tree Edges
     * Priority 2: sequence_no ASC (500m max span)
     * Priority 3: Spatial Nearest-Neighbor Traversal with 350m Haversine Distance Guard
     */
    public function getFeederNetworkSegments(int $penyulangId, ?int $userUlpId = null): array
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('assets') || $penyulangId <= 0) {
                return ['type' => 'MultiLineString', 'coordinates' => [], 'nodes' => []];
            }

            $hasParentCol = $db->fieldExists('parent_asset_id', 'assets');
            $hasSeqCol    = $db->fieldExists('sequence_no', 'assets');

            $builder = $db->table('assets a');
            $builder->select('a.id, a.parent_asset_id, a.jenis_asset, a.latitude, a.longitude, a.sequence_no');
            $builder->where('a.penyulang_id', $penyulangId);
            $builder->where('a.deleted_at IS NULL');
            $builder->where('a.latitude !=', 0);
            $builder->where('a.longitude !=', 0);

            if (!empty($userUlpId)) {
                $builder->where('a.ulp_id', $userUlpId);
            }

            if ($hasSeqCol) {
                $builder->orderBy('a.sequence_no', 'ASC');
            } else {
                $builder->orderBy('a.id', 'ASC');
            }

            $nodes = $builder->get()->getResultArray();
            if (empty($nodes)) {
                return ['type' => 'MultiLineString', 'coordinates' => [], 'nodes' => []];
            }

            $nodeMap   = [];
            $allPoints = [];
            $unvisited = [];

            foreach ($nodes as $n) {
                $id     = (int)$n['id'];
                $lng    = (float)$n['longitude'];
                $lat    = (float)$n['latitude'];
                $seq    = (int)($n['sequence_no'] ?? 0);
                $parent = (int)($n['parent_asset_id'] ?? 0);
                $jenis  = strtoupper((string)($n['jenis_asset'] ?? 'JTM'));

                $nodeMap[$id] = [
                    'id'       => $id,
                    'parent'   => $parent,
                    'seq'      => $seq,
                    'jenis'    => $jenis,
                    'lat'      => $lat,
                    'lng'      => $lng,
                    'coord'    => [$lng, $lat]
                ];
                $allPoints[] = [$lng, $lat];
                $unvisited[$id] = $nodeMap[$id];
            }

            $multiLineCoords = [];
            $hasValidParents = false;

            // =========================================================================
            // PRIORITY 1: Explicit Topology Tree Edges (parent_asset_id)
            // =========================================================================
            if ($hasParentCol) {
                foreach ($nodeMap as $id => $n) {
                    $parentId = $n['parent'];
                    if ($parentId > 0 && isset($nodeMap[$parentId])) {
                        $parentCoord = $nodeMap[$parentId]['coord'];
                        $childCoord  = $n['coord'];
                        $multiLineCoords[] = [$parentCoord, $childCoord];
                        $hasValidParents = true;
                    }
                }
            }

            // =========================================================================
            // PRIORITY 2 & 3: Sequence / Spatial Nearest-Neighbor Reconstruction
            // =========================================================================
            if (!$hasValidParents) {
                $hasValidSequence = false;
                $seqNodes = [];
                foreach ($nodeMap as $n) {
                    if ($n['seq'] > 0) {
                        $seqNodes[] = $n;
                    }
                }

                if (count($seqNodes) > 1) {
                    usort($seqNodes, fn($a, $b) => $a['seq'] <=> $b['seq']);
                    $seqLine = [];
                    for ($i = 0; $i < count($seqNodes); $i++) {
                        if ($i === 0) {
                            $seqLine[] = $seqNodes[$i]['coord'];
                            continue;
                        }
                        $prev = $seqNodes[$i - 1];
                        $curr = $seqNodes[$i];
                        $dist = $this->haversineDistanceMeters($prev['lat'], $prev['lng'], $curr['lat'], $curr['lng']);

                        if ($dist <= 500.0) {
                            $seqLine[] = $curr['coord'];
                        } else {
                            if (count($seqLine) > 1) {
                                $multiLineCoords[] = $seqLine;
                            }
                            $seqLine = [$curr['coord']];
                        }
                    }
                    if (count($seqLine) > 1) {
                        $multiLineCoords[] = $seqLine;
                    }
                    $hasValidSequence = true;
                }

                // PRIORITY 3: Spatial Nearest-Neighbor Traversal with 350m Distance Guard
                if (!$hasValidSequence && count($nodes) > 1) {
                    $startId = null;
                    foreach ($unvisited as $id => $n) {
                        if (in_array($n['jenis'], ['GI', 'GARDU', 'SUBSTATION', 'GH'])) {
                            $startId = $id;
                            break;
                        }
                    }
                    if ($startId === null) {
                        usort($nodes, fn($a, $b) => $a['latitude'] <=> $b['latitude']);
                        $startId = (int)$nodes[0]['id'];
                    }

                    while (!empty($unvisited)) {
                        if (!isset($unvisited[$startId])) {
                            $keys = array_keys($unvisited);
                            $startId = $keys[0];
                        }

                        $current = $unvisited[$startId];
                        unset($unvisited[$startId]);

                        $segment = [$current['coord']];

                        while (true) {
                            $nearestId   = null;
                            $minDistance = 99999999.0;

                            foreach ($unvisited as $candidateId => $cand) {
                                $d = $this->haversineDistanceMeters($current['lat'], $current['lng'], $cand['lat'], $cand['lng']);
                                if ($d < $minDistance && $d <= 350.0) {
                                    $minDistance = $d;
                                    $nearestId   = $candidateId;
                                }
                            }

                            if ($nearestId !== null) {
                                $current = $unvisited[$nearestId];
                                unset($unvisited[$nearestId]);
                                $segment[] = $current['coord'];
                            } else {
                                break;
                            }
                        }

                        if (count($segment) > 1) {
                            $multiLineCoords[] = $segment;
                        }
                    }
                }
            }

            return [
                'type'        => 'MultiLineString',
                'coordinates' => $multiLineCoords,
                'nodes'       => $allPoints
            ];
        } catch (\Throwable $e) {
            log_message('error', '[AssetRepository::getFeederNetworkSegments] Exception: ' . $e->getMessage());
            return ['type' => 'MultiLineString', 'coordinates' => [], 'nodes' => []];
        }
    }

    public function getFeederPolylineCoords(int $penyulangId, ?int $userUlpId = null): array
    {
        $seg = $this->getFeederNetworkSegments($penyulangId, $userUlpId);
        return $seg['nodes'] ?? [];
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

            // Build Strict Layer Filter Mapping BEFORE Zoom LOD Evaluation
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

            // Patch 2: Empty Layer Guard (Evaluated BEFORE Zoom LOD Return!)
            if (empty($allowedJenisList) && !$includeGtt && !$includeSwitchEquipment) {
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
