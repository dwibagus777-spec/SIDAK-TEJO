<?php

namespace App\Services;

use App\Models\GisTranslineModel;
use App\Models\AssetRelationshipModel;
use App\Repositories\AssetRepository;
use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * Service for Authoritative GIS Transline CRUD & Multi-Segment Topology Reconciliation
 */
class GisTranslineService
{
    protected BaseConnection $db;
    protected GisTranslineModel $translineModel;
    protected AssetRelationshipModel $relModel;
    protected AssetRepository $assetRepository;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->translineModel = new GisTranslineModel();
        $this->relModel = new AssetRelationshipModel();
        $this->assetRepository = new AssetRepository();
    }

    public function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
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
     * Get all active transline segments for a feeder
     *
     * @param int $feederId
     * @return array<int, array<string, mixed>>
     */
    public function getFeederTranslines(int $feederId): array
    {
        if ($feederId <= 0) return [];
        return $this->translineModel->getActiveTranslinesByFeeder($feederId);
    }

    /**
     * Create or update an individual transline segment (Atomic CRUD)
     *
     * @param array $data
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function saveTransline(array $data, ?array $actor = null): array
    {
        $sourceId = (int)($data['source_asset_id'] ?? 0);
        $targetId = (int)($data['target_asset_id'] ?? 0);
        $penyulangId = (int)($data['penyulang_id'] ?? 0);

        if ($sourceId <= 0 || $targetId <= 0) {
            return [
                'status'  => 'error',
                'message' => 'Kedua ID aset tiang awal dan tiang akhir wajib ditentukan.'
            ];
        }

        $sourceAsset = $this->db->table('assets')->select('id, nama_asset, kode_asset, latitude, longitude, penyulang_id')->where('id', $sourceId)->get()->getRowArray();
        $targetAsset = $this->db->table('assets')->select('id, nama_asset, kode_asset, latitude, longitude, penyulang_id')->where('id', $targetId)->get()->getRowArray();

        if (!$sourceAsset || !$targetAsset) {
            return [
                'status'  => 'error',
                'message' => 'Aset sumber atau target tidak ditemukan.'
            ];
        }

        if ($penyulangId <= 0) {
            $penyulangId = (int)($targetAsset['penyulang_id'] ?? $sourceAsset['penyulang_id'] ?? 0);
        }

        $conductorType     = (string)($data['conductor_type'] ?? 'AAAC');
        $conductorSize     = (string)($data['conductor_size'] ?? '150 mm²');
        $conductorMaterial = (string)($data['conductor_material'] ?? 'ALUMINUM_ALLOY');
        $installationType  = (string)($data['installation_type'] ?? 'OVERHEAD');
        $circuitConfig     = (string)($data['circuit_config'] ?? '3_PHASE');
        $actorName         = $actor['name'] ?? 'SYSTEM';
        $actorId           = $actor['id'] ?? null;

        $distance = (float)($data['distance_meters'] ?? 0);
        if ($distance <= 0 && !empty($sourceAsset['latitude']) && !empty($targetAsset['latitude'])) {
            $distance = round($this->haversineDistance(
                (float)$sourceAsset['latitude'], (float)$sourceAsset['longitude'],
                (float)$targetAsset['latitude'], (float)$targetAsset['longitude']
            ), 2);
        }

        // Geometry: default to straight LineString if not provided
        $geometry = $data['geometry'] ?? null;
        if (empty($geometry)) {
            $coords = [
                [(float)$sourceAsset['longitude'], (float)$sourceAsset['latitude']],
                [(float)$targetAsset['longitude'], (float)$targetAsset['latitude']],
            ];
            $geometry = json_encode($coords);
        } elseif (is_array($geometry)) {
            $coords = $geometry['coordinates'] ?? $geometry;
            $geometry = json_encode($coords);
        }

        $translineCode = $data['transline_code'] ?? ('TL-' . $penyulangId . '-' . $sourceId . '-' . $targetId);
        $mode          = strtoupper((string)($data['connection_mode'] ?? 'ADD'));

        $this->db->transBegin();
        try {
            if ($mode === 'REPLACE') {
                // Deactivate previous connections originating from source
                $this->db->table('gis_translines')
                    ->where('penyulang_id', $penyulangId)
                    ->where('source_asset_id', $sourceId)
                    ->where('target_asset_id !=', $targetId)
                    ->update([
                        'is_active'  => 0,
                        'status'     => 'DELETED',
                        'deleted_at' => date('Y-m-d H:i:s'),
                    ]);

                if ($this->db->fieldExists('penyulang_id', 'asset_relationships')) {
                    $this->db->table('asset_relationships')
                        ->where('penyulang_id', $penyulangId)
                        ->where('source_asset_id', $sourceId)
                        ->where('target_asset_id !=', $targetId)
                        ->delete();
                } else {
                    $this->db->table('asset_relationships')
                        ->where('source_asset_id', $sourceId)
                        ->where('target_asset_id !=', $targetId)
                        ->delete();
                }
            }

            // 1. Check existing row in gis_translines
            $existing = $this->db->table('gis_translines')
                ->groupStart()
                    ->where('source_asset_id', $sourceId)->where('target_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('source_asset_id', $targetId)->where('target_asset_id', $sourceId)
                ->groupEnd()
                ->where('penyulang_id', $penyulangId)
                ->get()
                ->getRowArray();

            $translineId = null;
            $payload = [
                'transline_code'     => $translineCode,
                'penyulang_id'       => $penyulangId,
                'source_asset_id'    => $sourceId,
                'target_asset_id'    => $targetId,
                'geometry'           => $geometry,
                'geometry_type'      => 'LineString',
                'conductor_type'     => $conductorType,
                'conductor_size'     => $conductorSize,
                'conductor_material' => $conductorMaterial,
                'installation_type'  => $installationType,
                'circuit_config'     => $circuitConfig,
                'distance_meters'    => $distance,
                'status'             => 'ACTIVE',
                'is_active'          => 1,
                'updated_by'         => $actorName,
                'updated_at'         => date('Y-m-d H:i:s'),
                'deleted_at'         => null,
            ];

            if ($existing) {
                $translineId = (int)$existing['id'];
                $this->db->table('gis_translines')->where('id', $translineId)->update($payload);
            } else {
                $payload['created_by'] = $actorName;
                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('gis_translines')->insert($payload);
                $translineId = (int)$this->db->insertID();
            }

            // 2. Synchronize asset_relationships
            $relExisting = $this->db->table('asset_relationships')
                ->groupStart()
                    ->where('parent_asset_id', $sourceId)->where('child_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('parent_asset_id', $targetId)->where('child_asset_id', $sourceId)
                ->groupEnd()
                ->get()
                ->getRowArray();

            $relData = [
                'parent_asset_id'    => $sourceId,
                'child_asset_id'     => $targetId,
                'source_asset_id'    => $sourceId,
                'target_asset_id'    => $targetId,
                'penyulang_id'       => $penyulangId,
                'relationship_type'  => 'NETWORK',
                'conductor_type'     => $conductorType,
                'conductor_size'     => $conductorSize,
                'conductor_material' => $conductorMaterial,
                'installation_type'  => $installationType,
                'circuit_config'     => $circuitConfig,
                'distance_meters'    => $distance,
                'source'             => 'GIS_TRANSLINE_CRUD',
                'status'             => 'VERIFIED',
                'verified_by'        => $actorId,
                'verified_at'        => date('Y-m-d H:i:s'),
                'is_active'          => 1,
                'updated_at'         => date('Y-m-d H:i:s'),
            ];

            $existingRelFields = $this->db->getFieldNames('asset_relationships');
            $filteredRelData = array_intersect_key($relData, array_flip($existingRelFields));

            if ($relExisting) {
                $this->db->table('asset_relationships')->where('id', $relExisting['id'])->update($filteredRelData);
            } else {
                $filteredRelData['created_by'] = $actorName;
                $filteredRelData['created_at'] = date('Y-m-d H:i:s');
                $filteredRelData = array_intersect_key($filteredRelData, array_flip($existingRelFields));
                $this->db->table('asset_relationships')->insert($filteredRelData);
            }

            // 3. Update assets.parent_asset_id
            $this->db->table('assets')->where('id', $targetId)->update(['parent_asset_id' => $sourceId]);

            // 4. Rebuild full derived multi-segment topology snapshot from ALL active gis_translines
            $freshTopology = $this->rebuildFeederTopologySnapshot($penyulangId, $actorName);

            $this->db->transCommit();

            return [
                'status'          => 'success',
                'is_direct_commit'=> true,
                'message'         => "Transline #{$translineId} ({$sourceAsset['nama_asset']} ➔ {$targetAsset['nama_asset']}) berhasil disimpan.",
                'transline_id'    => $translineId,
                'translines'      => $this->getFeederTranslines($penyulangId),
                'topology'        => $freshTopology,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'message' => 'Exception in saveTransline: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete an individual transline segment by ID (Atomic CRUD)
     *
     * @param int $translineId
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function deleteTransline(int $translineId, ?array $actor = null): array
    {
        if ($translineId <= 0) {
            return ['status' => 'error', 'message' => 'ID Transline tidak valid.'];
        }

        $row = $this->translineModel->find($translineId);
        if (!$row) {
            return ['status' => 'error', 'message' => "Transline #{$translineId} tidak ditemukan."];
        }

        $penyulangId = (int)$row['penyulang_id'];
        $sourceId    = (int)$row['source_asset_id'];
        $targetId    = (int)$row['target_asset_id'];
        $actorName   = $actor['name'] ?? 'SYSTEM';

        $this->db->transBegin();
        try {
            // 1. Soft-delete row in gis_translines
            $this->db->table('gis_translines')->where('id', $translineId)->update([
                'is_active'  => 0,
                'status'     => 'DELETED',
                'updated_by' => $actorName,
                'deleted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // 2. Remove / deactivate relationship
            $this->db->table('asset_relationships')
                ->groupStart()
                    ->where('parent_asset_id', $sourceId)->where('child_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('parent_asset_id', $targetId)->where('child_asset_id', $sourceId)
                ->groupEnd()
                ->delete();

            // 3. Clear parent_asset_id
            $this->db->table('assets')->where('id', $targetId)->where('parent_asset_id', $sourceId)->update(['parent_asset_id' => null]);
            $this->db->table('assets')->where('id', $sourceId)->where('parent_asset_id', $targetId)->update(['parent_asset_id' => null]);

            // 4. Rebuild full derived topology snapshot from remaining active gis_translines
            $freshTopology = $this->rebuildFeederTopologySnapshot($penyulangId, $actorName);

            $this->db->transCommit();

            return [
                'status'          => 'success',
                'is_direct_commit'=> true,
                'message'         => "Transline #{$translineId} berhasil dihapus.",
                'translines'      => $this->getFeederTranslines($penyulangId),
                'topology'        => $freshTopology,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['status' => 'error', 'message' => 'Gagal menghapus transline: ' . $e->getMessage()];
        }
    }

    /**
     * Update custom polyline geometry vertices for a specific segment
     *
     * @param int $penyulangId
     * @param int $sourceId
     * @param int $targetId
     * @param array $geoJsonGeometry
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function updateSegmentGeometry(int $penyulangId, int $sourceId, int $targetId, array $geoJsonGeometry, ?array $actor = null): array
    {
        $coords = $geoJsonGeometry['coordinates'] ?? [];
        if (empty($coords)) {
            return ['status' => 'error', 'message' => 'Koordinat segmen tidak boleh kosong.'];
        }

        $actorName = $actor['name'] ?? 'SYSTEM';

        $this->db->transBegin();
        try {
            // Locate existing transline row
            $row = $this->db->table('gis_translines')
                ->groupStart()
                    ->where('source_asset_id', $sourceId)->where('target_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('source_asset_id', $targetId)->where('target_asset_id', $sourceId)
                ->groupEnd()
                ->where('penyulang_id', $penyulangId)
                ->where('is_active', 1)
                ->get()
                ->getRowArray();

            if ($row) {
                $this->db->table('gis_translines')->where('id', $row['id'])->update([
                    'geometry'   => json_encode($coords),
                    'updated_by' => $actorName,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $translineId = (int)$row['id'];
            } else {
                // Create new record for this custom segment
                $this->db->table('gis_translines')->insert([
                    'transline_code'  => "TL-{$penyulangId}-{$sourceId}-{$targetId}",
                    'penyulang_id'    => $penyulangId,
                    'source_asset_id' => $sourceId,
                    'target_asset_id' => $targetId,
                    'geometry'        => json_encode($coords),
                    'geometry_type'   => 'LineString',
                    'status'          => 'ACTIVE',
                    'is_active'       => 1,
                    'created_by'      => $actorName,
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
                $translineId = (int)$this->db->insertID();
            }

            // Rebuild full derived topology snapshot
            $freshTopology = $this->rebuildFeederTopologySnapshot($penyulangId, $actorName);

            $this->db->transCommit();

            return [
                'status'       => 'success',
                'message'      => "Bentuk geometri transline #{$translineId} berhasil diperbarui.",
                'transline_id' => $translineId,
                'translines'   => $this->getFeederTranslines($penyulangId),
                'topology'     => $freshTopology,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['status' => 'error', 'message' => 'Gagal memperbarui bentuk segmen: ' . $e->getMessage()];
        }
    }

    /**
     * Rebuild the full derived MultiLineString topology snapshot from ALL active gis_translines
     *
     * @param int $penyulangId
     * @param string $actorName
     * @return array<string, mixed>
     */
    public function rebuildFeederTopologySnapshot(int $penyulangId, string $actorName): array
    {
        if ($penyulangId <= 0) {
            return ['type' => 'MultiLineString', 'coordinates' => [], 'nodes' => [], 'edges' => []];
        }

        // Fetch all active translines for this feeder
        $activeRows = $this->translineModel->getActiveTranslinesByFeeder($penyulangId);

        $multiLineCoords = [];
        $allNodes = [];
        $edges = [];

        foreach ($activeRows as $r) {
            $geomStr = $r['geometry'] ?? '';
            $segCoords = !empty($geomStr) ? json_decode($geomStr, true) : null;

            if (!empty($segCoords) && is_array($segCoords) && count($segCoords) >= 2) {
                $multiLineCoords[] = $segCoords;
                foreach ($segCoords as $pt) {
                    $allNodes[] = $pt;
                }

                $edges[] = [
                    'transline_id'       => (int)$r['id'],
                    'edge_id'            => (int)$r['id'],
                    'from_asset_id'      => (int)$r['source_asset_id'],
                    'to_asset_id'        => (int)$r['target_asset_id'],
                    'conductor_type'     => $r['conductor_type'] ?? 'AAAC',
                    'conductor_size'     => $r['conductor_size'] ?? '150 mm²',
                    'conductor_label'    => "{$r['conductor_type']} {$r['conductor_size']}",
                    'conductor_material' => $r['conductor_material'] ?? 'ALUMINUM_ALLOY',
                    'installation_type'  => $r['installation_type'] ?? 'OVERHEAD',
                    'circuit_config'     => $r['circuit_config'] ?? '3_PHASE',
                    'length_meter'       => (float)($r['distance_meters'] ?? 0),
                    'coordinates'        => $segCoords,
                    'status'             => 'ACTIVE',
                ];
            }
        }

        // Fallback: If gis_translines is empty, fallback to asset_relationships & assets
        if (empty($multiLineCoords)) {
            $fallback = $this->assetRepository->getFeederNetworkSegments($penyulangId);
            $multiLineCoords = $fallback['coordinates'] ?? [];
            $allNodes = $fallback['nodes'] ?? [];
            $edges = $fallback['edges'] ?? [];
        }

        $freshTopology = [
            'type'        => 'MultiLineString',
            'coordinates' => $multiLineCoords,
            'nodes'       => $allNodes,
            'edges'       => $edges,
        ];

        // Persist to network_topology_versions as derived snapshot
        if ($this->db->tableExists('network_topology_versions') && !empty($multiLineCoords)) {
            $ntvFields = $this->db->getFieldNames('network_topology_versions');

            // Supersede old active versions
            $supPayload = ['is_active' => 0];
            if (in_array('version_status', $ntvFields, true)) {
                $supPayload['version_status'] = 'SUPERSEDED';
            }
            if (in_array('superseded_at', $ntvFields, true)) {
                $supPayload['superseded_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('updated_at', $ntvFields, true)) {
                $supPayload['updated_at'] = date('Y-m-d H:i:s');
            }

            $this->db->table('network_topology_versions')
                     ->where('penyulang_id', $penyulangId)
                     ->where('is_active', 1)
                     ->update($supPayload);

            $maxVerRow = $this->db->table('network_topology_versions')
                                    ->where('penyulang_id', $penyulangId)
                                    ->selectMax('version_no')
                                    ->get()
                                    ->getRowArray();
            $maxVer = (int)($maxVerRow['version_no'] ?? 0);
            $newVer = $maxVer + 1;

            $insertPayload = [
                'penyulang_id'     => $penyulangId,
                'version_no'       => $newVer,
                'geojson_topology' => json_encode($freshTopology),
                'nodes_count'      => count($allNodes),
                'segments_count'   => count($multiLineCoords),
                'is_active'        => 1,
                'version_status'   => 'ACTIVE',
                'created_by'       => $actorName,
                'created_at'       => date('Y-m-d H:i:s'),
            ];
            $filteredInsert = array_intersect_key($insertPayload, array_flip($ntvFields));

            $this->db->table('network_topology_versions')->insert($filteredInsert);
        }

        return $freshTopology;
    }
}
