<?php

namespace App\Services;

use App\Repositories\AssetRepository;

class GISService
{
    private AssetRepository $assetRepository;
    private AssetVisualRegistryService $visualRegistry;

    public function __construct()
    {
        $this->assetRepository = new AssetRepository();
        $this->visualRegistry  = new AssetVisualRegistryService();
    }

    /**
     * Map EAM Status to dynamic color string
     */
    public function getStatusColor(string $status): string
    {
        switch (strtoupper($status)) {
            case 'NORMAL':
                return '#059669'; // Green
            case 'BERMASALAH':
            case 'CRITICAL':
                return '#dc2626'; // Red
            case 'MAINTENANCE':
                return '#d97706'; // Orange
            default:
                return '#64748b'; // Grey
        }
    }

    /**
     * Map Construction Type & Asset Family to Leaflet Marker Specifications
     */
    public function getConstructionMarkerSpec(?string $jenisAsset, ?string $constructionType = null, ?string $kode = null): array
    {
        $jenis = strtoupper(trim((string)$jenisAsset));
        $type  = strtoupper(trim((string)$constructionType));
        $visual = $this->visualRegistry->resolveVisual($jenis, $type, $kode);

        // Level 1 Priority: Primary Asset Category Family
        if ($jenis === 'TRAFO') {
            $legacy = [
                'shape'      => 'trafo',
                'icon_class' => 'fa-bolt',
                'color'      => '#d97706',
                'label'      => 'TRAFO'
            ];
        } elseif ($jenis === 'GARDU') {
            $legacy = [
                'shape'      => 'gardu',
                'icon_class' => 'fa-building-columns',
                'color'      => '#0284c7',
                'label'      => 'GARDU'
            ];
        } elseif ($jenis === 'KUBIKEL') {
            $legacy = [
                'shape'      => 'kubikel',
                'icon_class' => 'fa-box-archive',
                'color'      => '#475569',
                'label'      => 'KUBIKEL'
            ];
        } elseif ($jenis === 'LBS' || $jenis === 'LBSM' || $jenis === 'RECLOSER' || $jenis === 'SECTIONALIZER') {
            $legacy = [
                'shape'      => 'switch',
                'icon_class' => 'fa-toggle-on',
                'color'      => '#dc2626',
                'label'      => $jenis
            ];
        } elseif (str_contains($type, 'PMS') || str_contains($type, 'PEMISAH')) {
            $legacy = [
                'shape'      => 'pms',
                'icon_class' => 'fa-toggle-off',
                'color'      => '#dc2626',
                'label'      => 'JTM • PMS'
            ];
        } elseif (str_contains($type, 'PMT') || str_contains($type, 'PEMUTUS')) {
            $legacy = [
                'shape'      => 'pmt',
                'icon_class' => 'fa-toggle-on',
                'color'      => '#ea580c',
                'label'      => 'JTM • PMT'
            ];
        } elseif (str_contains($type, 'GTT')) {
            $legacy = [
                'shape'      => 'gtt',
                'icon_class' => 'fa-charging-station',
                'color'      => '#059669',
                'label'      => 'JTM • GTT'
            ];
        } elseif (str_contains($type, 'TMTP') || str_contains($type, 'PORTAL')) {
            $legacy = [
                'shape'      => 'tmtp',
                'icon_class' => 'fa-archway',
                'color'      => '#7c3aed',
                'label'      => 'JTM • TMTP'
            ];
        } else {
            $legacy = [
                'shape'      => 'jtm',
                'icon_class' => 'fa-square-poll-vertical',
                'color'      => '#2563eb',
                'label'      => $jenis ?: 'JTM'
            ];
        }

        return array_merge($legacy, [
            'visual' => $visual,
        ]);
    }

    /**
     * Get On-Demand Network Data Segmented by Zoom LOD & Layer Filters
     */
    public function getNetworkData(array $filters = [], ?int $userUlpId = null): array
    {
        $penyulangId = (int)($filters['penyulang_id'] ?? 0);
        $zoom        = (int)($filters['zoom'] ?? 14);
        $layerFilter = $filters['layers'] ?? ['JTM', 'GARDU', 'TRAFO', 'SWITCH'];

        if ($userUlpId !== null && $userUlpId > 0) {
            $filters['ulp_id'] = $userUlpId;
        }

        // 1. Fetch Independent Feeder Network Topology Segments (MultiLineString Edge Tree)
        $segmentData = $this->assetRepository->getFeederNetworkSegments($penyulangId, $userUlpId);
        $nodes       = $segmentData['nodes'] ?? [];
        
        $minLat = 90.0; $maxLat = -90.0;
        $minLng = 180.0; $maxLng = -180.0;
        $validGisCount = 0;

        foreach ($nodes as $pNode) {
            $pLng = (float)($pNode[0] ?? 0);
            $pLat = (float)($pNode[1] ?? 0);
            if ($pLat != 0 && $pLng != 0) {
                $validGisCount++;
                if ($pLat < $minLat) $minLat = $pLat;
                if ($pLat > $maxLat) $maxLat = $pLat;
                if ($pLng < $minLng) $minLng = $pLng;
                if ($pLng > $maxLng) $maxLng = $pLng;
            }
        }

        // 2. Fetch Marker Features Filtered Strictly by Zoom LOD & Layer Selection
        $assets = $this->assetRepository->getGisNetworkAssets($filters, $userUlpId);

        $features = [];
        $stats    = [
            'total_assets' => 0,
            'jtm_count'    => 0,
            'gardu_count'  => 0,
            'trafo_count'  => 0,
            'switch_count' => 0,
            'rejected_cross_feeder' => 0,
        ];

        foreach ($assets as $asset) {
            $lat = (float)($asset['latitude'] ?? 0);
            $lng = (float)($asset['longitude'] ?? 0);
            if ($lat == 0 || $lng == 0) continue;

            $assetPenyulangId = (int)($asset['penyulang_id'] ?? 0);

            // STRICT DATA INTEGRITY GUARD:
            // Reject any asset where penyulang_id does not strictly match the requested feeder
            if ($penyulangId > 0 && $assetPenyulangId > 0 && $assetPenyulangId !== $penyulangId) {
                $stats['rejected_cross_feeder']++;
                continue;
            }

            $stats['total_assets']++;
            $jenis = strtoupper(trim((string)($asset['jenis_asset'] ?? 'JTM')));
            $constrName = $asset['construction_code'] ?? $asset['type'] ?? $asset['construction_name'] ?? $jenis;

            if ($jenis === 'GARDU' || str_contains($constrName, 'TM-8') || str_contains($constrName, 'TM-9') || str_contains($constrName, 'GTT') || str_contains($constrName, 'GARDU')) {
                $stats['gardu_count']++;
            } elseif ($jenis === 'TRAFO' || str_contains($constrName, 'DISTRIBUSI') || str_contains($constrName, 'TRAFO')) {
                $stats['trafo_count']++;
            } elseif (in_array($jenis, ['SWITCH', 'LBS', 'LBSM', 'RECLOSER', 'SECTIONALIZER']) || str_contains($constrName, 'PMS') || str_contains($constrName, 'PMT') || str_contains($constrName, 'LBS') || str_contains($constrName, 'REC')) {
                $stats['switch_count']++;
            } else {
                $stats['jtm_count']++;
            }

            $spec = $this->getConstructionMarkerSpec($jenis, $constrName, $asset['kode_asset'] ?? null);
            $visual = $this->visualRegistry->resolveVisual($jenis, $constrName, $asset['kode_asset'] ?? null);
            $conditionStatus = $asset['status'] ?? 'NORMAL';
            $conditionOverlay = $this->visualRegistry->getConditionOverlay($conditionStatus);

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type'        => 'Point',
                    'coordinates' => [$lng, $lat]
                ],
                'properties' => [
                    'id'                => (int)$asset['id'],
                    'kode_asset'        => $asset['kode_asset'],
                    'nama_asset'        => $asset['nama_asset'],
                    'jenis_asset'       => $jenis,
                    'penyulang_id'      => $assetPenyulangId,
                    'construction_type' => $constrName,
                    'status'            => $conditionStatus,
                    'lokasi'            => $asset['lokasi'] ?? '',
                    'latitude'          => $lat,
                    'longitude'         => $lng,
                    'marker_spec'       => $spec,
                    'visual'            => $visual,
                    'condition_overlay' => $conditionOverlay,
                    'provenance'        => [
                        'source'                          => 'assets',
                        'asset_id'                        => (int)$asset['id'],
                        'asset_penyulang_id'              => $assetPenyulangId,
                        'selected_penyulang_id'           => $penyulangId,
                        'is_valid_for_selected_penyulang' => true,
                        'data_origin'                     => 'MASTER_ASSET_REGISTER'
                    ]
                ]
            ];
        }

        return [
            'summary' => $stats,
            'zoom'    => $zoom,
            'legend'  => $this->visualRegistry->getLegendItems(),
            'transline' => [
                'type' => 'Feature',
                'geometry' => [
                    'type'        => $segmentData['type'] ?? 'MultiLineString',
                    'coordinates' => $segmentData['coordinates'] ?? []
                ],
                'properties' => [
                    'edges'          => $segmentData['edges'] ?? [],
                    'version_no'     => $segmentData['version_no'] ?? 1,
                    'version_status' => $segmentData['version_status'] ?? 'ACTIVE'
                ]
            ],
            'bbox' => $validGisCount > 0 ? [
                'min_lat' => $minLat,
                'max_lat' => $maxLat,
                'min_lng' => $minLng,
                'max_lng' => $maxLng
            ] : null,
            'features' => $features
        ];
    }

    public function getGeoJsonCollection(array $filters = [], ?int $userUlpId = null): array
    {
        $res = $this->getNetworkData($filters, $userUlpId);
        return [
            'type'     => 'FeatureCollection',
            'features' => $res['features'] ?? []
        ];
    }
}
