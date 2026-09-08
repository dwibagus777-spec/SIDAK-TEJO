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
     * Resolve a bidirectional transline segment between two assets (A -> B or B -> A)
     *
     * @param int $penyulangId
     * @param int $assetAId
     * @param int $assetBId
     * @return array<string, mixed>|null
     */
    public function resolveTranslinePair(int $penyulangId, int $assetAId, int $assetBId): ?array
    {
        if ($assetAId <= 0 || $assetBId <= 0 || $assetAId === $assetBId) {
            return null;
        }

        $builder = $this->db->table('gis_translines')
            ->where('is_active', 1)
            ->groupStart()
                ->groupStart()
                    ->where('source_asset_id', $assetAId)->where('target_asset_id', $assetBId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('source_asset_id', $assetBId)->where('target_asset_id', $assetAId)
                ->groupEnd()
            ->groupEnd();

        if ($penyulangId > 0) {
            $builder->where('penyulang_id', $penyulangId);
        }

        return $builder->get()->getRowArray();
    }

    /**
     * Delete an active transline segment identified by pair (A -> B or B -> A)
     *
     * @param int $penyulangId
     * @param int $assetAId
     * @param int $assetBId
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function deleteTranslineByPair(int $penyulangId, int $assetAId, int $assetBId, ?array $actor = null): array
    {
        $row = $this->resolveTranslinePair($penyulangId, $assetAId, $assetBId);
        if (!$row) {
            return [
                'status'  => 'error',
                'message' => 'Tidak ditemukan sambungan jalur aktif antara kedua aset tersebut.'
            ];
        }

        return $this->deleteTransline((int)$row['id'], $actor);
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

    /**
     * TL-01 Visual Realization: Authoritative Read-Only Transline Query Service
     *
     * Pure SELECT query with single controlled JOIN between gis_translines,
     * source assets, and target assets (zero N+1 queries).
     *
     * HARD SAFETY INVARIANTS:
     * - Zero writes (INSERT=0, UPDATE=0, DELETE=0, DDL=0).
     * - Endpoints resolve strictly to assets.id.
     * - Temuan is strictly forbidden as an endpoint (domain-typed rejection).
     * - Coordinates originate strictly from assets.latitude and assets.longitude.
     * - NO synthetic/canonical fallback in production service. Returns empty state if DB table has 0 rows.
     * - Scoped by ULP, Penyulang, and Section with cross-scope rejection and anomaly diagnostics.
     *
     * @param array<string, mixed> $scope ['penyulang_id' => int, 'section_id' => ?int, 'ulp_id' => ?int, 'options' => ?array]
     * @param int|null $userUlpId
     * @return array<string, mixed>
     */
    public function getAuthoritativeTranslines(array $scope = [], ?int $userUlpId = null): array
    {
        $penyulangId = (int)($scope['penyulang_id'] ?? 0);
        $sectionId   = isset($scope['section_id']) && $scope['section_id'] !== '' ? (int)$scope['section_id'] : 0;
        $ulpId       = isset($scope['ulp_id']) && $scope['ulp_id'] !== '' ? (int)$scope['ulp_id'] : 0;
        $options     = (array)($scope['options'] ?? []);

        // 1. Domain-Typed Temuan Firewall Check
        $sourceType = strtoupper(trim((string)($options['source_type'] ?? 'ASSET')));
        $targetType = strtoupper(trim((string)($options['target_type'] ?? 'ASSET')));
        if ($sourceType === 'TEMUAN' || $sourceType === 'FINDING' || $targetType === 'TEMUAN' || $targetType === 'FINDING') {
            return [
                'success'     => false,
                'status'      => 'error',
                'error_code'  => 'TEMUAN_ENDPOINT_FORBIDDEN',
                'message'     => 'Titik temuan/finding tidak boleh menjadi endpoint transline JTM. Topologi jaringan hanya menghubungkan antar aset JTM.',
                'data'        => [],
                'diagnostics' => [],
            ];
        }

        // 2. Scope Validation: Scope must not be empty
        if ($penyulangId <= 0 && $ulpId <= 0) {
            return [
                'success'     => true,
                'status'      => 'empty',
                'message'     => 'Penyulang atau ULP wajib dipilih untuk memuat data transline.',
                'scope'       => [
                    'ulp_id'       => null,
                    'penyulang_id' => null,
                    'section_id'   => null,
                ],
                'total'       => 0,
                'data'        => [],
                'diagnostics' => [],
            ];
        }

        // 3. Feeder & ULP Authorization Scope Firewall
        $feederUlpId = null;
        if ($penyulangId > 0) {
            if ($this->db->tableExists('penyulang')) {
                $feeder = $this->db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
                if (!$feeder) {
                    return [
                        'success'     => true,
                        'status'      => 'success',
                        'message'     => "Penyulang ID {$penyulangId} tidak ditemukan atau belum memiliki transline.",
                        'scope'       => [
                            'ulp_id'       => null,
                            'penyulang_id' => $penyulangId,
                            'section_id'   => $sectionId > 0 ? $sectionId : null,
                        ],
                        'total'       => 0,
                        'data'        => [],
                        'translines'  => [],
                        'diagnostics' => [],
                    ];
                }
                $feederUlpId = (int)($feeder['ulp_id'] ?? 0);

                // Check authorization boundary against session userUlpId
                if ($userUlpId !== null && $userUlpId > 0 && $feederUlpId > 0 && $userUlpId !== $feederUlpId) {
                    return [
                        'success'     => false,
                        'status'      => 'error',
                        'error_code'  => 'UNAUTHORIZED_FEEDER_ACCESS',
                        'message'     => 'Akses ditolak: Penyulang berada di luar batas otorisasi ULP Anda.',
                        'data'        => [],
                        'diagnostics' => [],
                    ];
                }

                // Check cross-scope if request explicitly passed an ulp_id that differs from feeder's ULP
                if ($ulpId > 0 && $feederUlpId > 0 && $ulpId !== $feederUlpId) {
                    return [
                        'success'     => false,
                        'status'      => 'error',
                        'error_code'  => 'CROSS_SCOPE_REQUEST',
                        'message'     => "Penyulang #{$penyulangId} tidak berada dalam ULP #{$ulpId}.",
                        'data'        => [],
                        'diagnostics' => [],
                    ];
                }
            }

            // 4. Section Scope Validation
            if ($sectionId > 0 && $this->db->tableExists('sections')) {
                $section = $this->db->table('sections')->where('id', $sectionId)->get()->getRowArray();
                if ($section && isset($section['penyulang_id']) && (int)$section['penyulang_id'] !== $penyulangId) {
                    return [
                        'success'     => false,
                        'status'      => 'error',
                        'error_code'  => 'CROSS_SCOPE_REQUEST',
                        'message'     => "Seksi #{$sectionId} tidak berada dalam penyulang #{$penyulangId}.",
                        'data'        => [],
                        'diagnostics' => [],
                    ];
                }
            }
        } elseif ($ulpId > 0) {
            // ULP level authorization check
            if ($userUlpId !== null && $userUlpId > 0 && $ulpId !== $userUlpId) {
                return [
                    'success'     => false,
                    'status'      => 'error',
                    'error_code'  => 'UNAUTHORIZED_FEEDER_ACCESS',
                    'message'     => 'Akses ditolak: ULP yang diminta berada di luar batas otorisasi Anda.',
                    'data'        => [],
                    'diagnostics' => [],
                ];
            }
            $feederUlpId = $ulpId;
        }

        // 5. Query Authoritative Translines (Single controlled JOIN, zero N+1)
        if (!$this->db->tableExists('gis_translines') || !$this->db->tableExists('assets')) {
            return [
                'success'     => true,
                'status'      => 'success',
                'message'     => 'Tabel gis_translines atau assets belum tersedia.',
                'scope'       => [
                    'ulp_id'       => $feederUlpId,
                    'penyulang_id' => $penyulangId > 0 ? $penyulangId : null,
                    'section_id'   => $sectionId > 0 ? $sectionId : null,
                ],
                'total'       => 0,
                'data'        => [],
                'diagnostics' => [],
            ];
        }

        $hasSectionCol = $this->db->fieldExists('section_id', 'gis_translines');
        $sectionSelect = $hasSectionCol ? 't.section_id,' : '';

        $builder = $this->db->table('gis_translines t')
            ->select('
                t.id, t.transline_code, t.penyulang_id, ' . $sectionSelect . '
                t.source_asset_id, t.target_asset_id,
                t.conductor_type, t.conductor_size, t.distance_meters,
                t.status, t.is_active,
                sa.id AS sa_id, sa.kode_asset AS sa_code, sa.nama_asset AS sa_name,
                sa.latitude AS sa_lat, sa.longitude AS sa_lng,
                sa.penyulang_id AS sa_penyulang_id, sa.section_id AS sa_section_id,
                ta.id AS ta_id, ta.kode_asset AS ta_code, ta.nama_asset AS ta_name,
                ta.latitude AS ta_lat, ta.longitude AS ta_lng,
                ta.penyulang_id AS ta_penyulang_id, ta.section_id AS ta_section_id
            ')
            ->join('assets sa', 'sa.id = t.source_asset_id', 'left')
            ->join('assets ta', 'ta.id = t.target_asset_id', 'left');

        if ($penyulangId > 0) {
            $builder->where('t.penyulang_id', $penyulangId);
        } elseif ($ulpId > 0 && $this->db->tableExists('penyulang')) {
            $builder->join('penyulang p', 'p.id = t.penyulang_id', 'inner')
                    ->where('p.ulp_id', $ulpId);
        }

        if ($sectionId > 0) {
            if ($hasSectionCol) {
                $builder->where('t.section_id', $sectionId);
            } else {
                $builder->groupStart()
                        ->where('sa.section_id', $sectionId)
                        ->orWhere('ta.section_id', $sectionId)
                        ->groupEnd();
            }
        }

        // Active filters
        if ($this->db->fieldExists('is_active', 'gis_translines')) {
            $builder->where('t.is_active', 1);
        }
        if ($this->db->fieldExists('status', 'gis_translines')) {
            $builder->where('t.status', 'ACTIVE');
        }
        if ($this->db->fieldExists('deleted_at', 'gis_translines')) {
            $builder->where('t.deleted_at IS NULL');
        }

        $rows = $builder->orderBy('t.id', 'ASC')->get()->getResultArray();

        // 6. Integrity Verification & Diagnostics Loop (Pure Read-Only: No Mutation)
        $data = [];
        $diagnostics = [];

        foreach ($rows as $r) {
            $tId = (int)$r['id'];
            $sourceId = $r['sa_id'] !== null ? (int)$r['sa_id'] : null;
            $targetId = $r['ta_id'] !== null ? (int)$r['ta_id'] : null;

            // Check orphan source or target
            if ($sourceId === null || $targetId === null) {
                $diagnostics[] = [
                    'transline_id' => $tId,
                    'code'         => 'ORPHAN_ENDPOINT',
                    'type'         => 'ORPHAN_ENDPOINT',
                    'message'      => "Transline #{$tId} memiliki endpoint yang tidak ditemukan pada tabel assets (source: " . ($r['source_asset_id'] ?? 'null') . ", target: " . ($r['target_asset_id'] ?? 'null') . ").",
                ];
                continue;
            }

            // Check self-loop
            if ($sourceId === $targetId) {
                $diagnostics[] = [
                    'transline_id' => $tId,
                    'code'         => 'IDENTICAL_ENDPOINTS',
                    'type'         => 'IDENTICAL_ENDPOINTS',
                    'message'      => "Transline #{$tId} memiliki source dan target identik (aset #{$sourceId}).",
                ];
                continue;
            }

            // Check coordinates
            $saLat = $r['sa_lat'] !== null ? (float)$r['sa_lat'] : null;
            $saLng = $r['sa_lng'] !== null ? (float)$r['sa_lng'] : null;
            $taLat = $r['ta_lat'] !== null ? (float)$r['ta_lat'] : null;
            $taLng = $r['ta_lng'] !== null ? (float)$r['ta_lng'] : null;

            $hasValidCoords = ($saLat !== null && $saLng !== null && $taLat !== null && $taLng !== null &&
                               !($saLat == 0.0 && $saLng == 0.0) && !($taLat == 0.0 && $taLng == 0.0));

            if (!$hasValidCoords) {
                $diagnostics[] = [
                    'transline_id' => $tId,
                    'code'         => 'MISSING_COORDINATE',
                    'type'         => 'MISSING_COORDINATE',
                    'message'      => "Transline #{$tId} memiliki koordinat aset yang hilang atau bernilai 0.",
                ];
                continue;
            }

            // Check scope consistency (Domain & scope integrity)
            $tFeeder = (int)$r['penyulang_id'];
            $saFeeder = (int)($r['sa_penyulang_id'] ?? 0);
            $taFeeder = (int)($r['ta_penyulang_id'] ?? 0);

            if (($saFeeder > 0 && $saFeeder !== $tFeeder) || ($taFeeder > 0 && $taFeeder !== $tFeeder)) {
                $diagnostics[] = [
                    'transline_id' => $tId,
                    'code'         => 'CROSS_SCOPE_ENDPOINT',
                    'type'         => 'CROSS_SCOPE_ENDPOINT',
                    'message'      => "Transline #{$tId} pada penyulang #{$tFeeder} menghubungkan aset dari penyulang berbeda (source: #{$saFeeder}, target: #{$taFeeder}).",
                ];
                continue;
            }

            $lengthM = (float)($r['distance_meters'] ?? 0.0);
            if ($lengthM <= 0.0) {
                $lengthM = round($this->haversineDistance($saLat, $saLng, $taLat, $taLng), 2);
            }

            $conductorLabel = trim(($r['conductor_type'] ?? 'AAAC') . ' ' . ($r['conductor_size'] ?? '150 mm²'));
            $translineCode  = $r['transline_code'] ?: ('TL-' . $tFeeder . '-' . $tId);

            $data[] = [
                'id'                 => $tId,
                'transline_id'       => $tId,
                'code'               => $translineCode,
                'transline_code'     => $translineCode,
                'penyulang_id'       => $tFeeder,
                'section_id'         => !empty($r['section_id']) ? (int)$r['section_id'] : null,
                'source_asset_id'    => $sourceId,
                'target_asset_id'    => $targetId,
                'from_asset_id'      => $sourceId,
                'to_asset_id'        => $targetId,
                'source_asset'       => [
                    'id'         => $sourceId,
                    'code'       => $r['sa_code'] ?: ('AST-' . $sourceId),
                    'kode_asset' => $r['sa_code'] ?: ('AST-' . $sourceId),
                    'name'       => $r['sa_name'] ?: ('Tiang #' . $sourceId),
                    'nama_asset' => $r['sa_name'] ?: ('Tiang #' . $sourceId),
                    'latitude'   => $saLat,
                    'longitude'  => $saLng,
                ],
                'target_asset'       => [
                    'id'         => $targetId,
                    'code'       => $r['ta_code'] ?: ('AST-' . $targetId),
                    'kode_asset' => $r['ta_code'] ?: ('AST-' . $targetId),
                    'name'       => $r['ta_name'] ?: ('Tiang #' . $targetId),
                    'nama_asset' => $r['ta_name'] ?: ('Tiang #' . $targetId),
                    'latitude'   => $taLat,
                    'longitude'  => $taLng,
                ],
                'coordinates'        => [
                    [$saLng, $saLat],
                    [$taLng, $taLat],
                ],
                'length_m'           => $lengthM,
                'length_meter'       => $lengthM,
                'distance_meters'    => $lengthM,
                'conductor'          => $conductorLabel,
                'conductor_label'    => $conductorLabel,
                'conductor_type'     => $r['conductor_type'] ?? 'AAAC',
                'conductor_size'     => $r['conductor_size'] ?? '150 mm²',
                'conductor_material' => $r['conductor_material'] ?? 'ALUMINUM_ALLOY',
                'status'             => $r['status'] ?? 'ACTIVE',
                'is_active'          => 1,
            ];
        }

        return [
            'success'     => true,
            'status'      => 'success',
            'scope'       => [
                'ulp_id'       => $feederUlpId,
                'penyulang_id' => $penyulangId > 0 ? $penyulangId : null,
                'section_id'   => $sectionId > 0 ? $sectionId : null,
            ],
            'total'       => count($data),
            'data'        => $data,
            'translines'  => $data,
            'diagnostics' => $diagnostics,
        ];
    }
}

