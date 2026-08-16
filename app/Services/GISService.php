<?php

namespace App\Services;

use App\Repositories\AssetRepository;

class GISService
{
    private AssetRepository $assetRepository;

    public function __construct()
    {
        $this->assetRepository = new AssetRepository();
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
    public function getConstructionMarkerSpec(?string $jenisAsset, ?string $constructionType = null): array
    {
        $jenis = strtoupper(trim((string)$jenisAsset));
        $type  = strtoupper(trim((string)$constructionType));

        // Level 1 Priority: Primary Asset Category Family
        if ($jenis === 'TRAFO') {
            return [
                'shape'      => 'trafo',
                'icon_class' => 'fa-bolt',
                'color'      => '#d97706',
                'label'      => 'TRAFO'
            ];
        }
        if ($jenis === 'GARDU') {
            return [
                'shape'      => 'gardu',
                'icon_class' => 'fa-building-columns',
                'color'      => '#0284c7',
                'label'      => 'GARDU'
            ];
        }
        if ($jenis === 'KUBIKEL') {
            return [
                'shape'      => 'kubikel',
                'icon_class' => 'fa-box-archive',
                'color'      => '#475569',
                'label'      => 'KUBIKEL'
            ];
        }
        if ($jenis === 'LBS' || $jenis === 'LBSM' || $jenis === 'RECLOSER' || $jenis === 'SECTIONALIZER') {
            return [
                'shape'      => 'switch',
                'icon_class' => 'fa-toggle-on',
                'color'      => '#dc2626',
                'label'      => $jenis
            ];
        }

        // Level 2 Priority: JTM Construction Sub-variations
        if (str_contains($type, 'PMS') || str_contains($type, 'PEMISAH')) {
            return [
                'shape'      => 'pms',
                'icon_class' => 'fa-toggle-off',
                'color'      => '#dc2626',
                'label'      => 'JTM • PMS'
            ];
        }
        if (str_contains($type, 'PMT') || str_contains($type, 'PEMUTUS')) {
            return [
                'shape'      => 'pmt',
                'icon_class' => 'fa-toggle-on',
                'color'      => '#ea580c',
                'label'      => 'JTM • PMT'
            ];
        }
        if (str_contains($type, 'GTT')) {
            return [
                'shape'      => 'gtt',
                'icon_class' => 'fa-charging-station',
                'color'      => '#059669',
                'label'      => 'JTM • GTT'
            ];
        }
        if (str_contains($type, 'TMTP') || str_contains($type, 'PORTAL')) {
            return [
                'shape'      => 'tmtp',
                'icon_class' => 'fa-archway',
                'color'      => '#7c3aed',
                'label'      => 'JTM • TMTP'
            ];
        }

        return [
            'shape'      => 'jtm',
            'icon_class' => 'fa-square-poll-vertical',
            'color'      => '#2563eb',
            'label'      => $jenis ?: 'JTM'
        ];
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

        $assets = $this->assetRepository->getFilteredAssets(['penyulang_id' => $penyulangId]);

        $lineCoords = [];
        $features   = [];
        $stats      = [
            'total_assets' => count($assets),
            'jtm_count'    => 0,
            'gardu_count'  => 0,
            'trafo_count'  => 0,
            'switch_count' => 0,
        ];

        $minLat = 90.0; $maxLat = -90.0;
        $minLng = 180.0; $maxLng = -180.0;
        $validGisCount = 0;

        foreach ($assets as $asset) {
            $lat = (float)($asset['latitude'] ?? 0);
            $lng = (float)($asset['longitude'] ?? 0);
            if ($lat == 0 || $lng == 0) continue;

            $validGisCount++;
            $lineCoords[] = [$lng, $lat];

            if ($lat < $minLat) $minLat = $lat;
            if ($lat > $maxLat) $maxLat = $lat;
            if ($lng < $minLng) $minLng = $lng;
            if ($lng > $maxLng) $maxLng = $lng;

            $jenis = strtoupper(trim((string)($asset['jenis_asset'] ?? 'JTM')));
            $constrName = $asset['construction_name'] ?? $jenis; // NEVER fallback to nama_asset!

            if ($jenis === 'GARDU') $stats['gardu_count']++;
            elseif ($jenis === 'TRAFO') $stats['trafo_count']++;
            elseif (in_array($jenis, ['LBS', 'LBSM', 'RECLOSER', 'SECTIONALIZER']) || str_contains($constrName, 'PMS') || str_contains($constrName, 'PMT')) $stats['switch_count']++;
            else $stats['jtm_count']++;

            // Backend Level of Detail (LOD) Filtering Rules
            // Zoom < 13: 0 Point Markers returned (Polyline & Summary ONLY!)
            if ($zoom < 13) {
                continue;
            }

            // Zoom 13 - 16: Return GARDU, TRAFO, KUBIKEL, SWITCH Equipment Markers ONLY
            if ($zoom >= 13 && $zoom <= 16) {
                $isEquipment = in_array($jenis, ['GARDU', 'TRAFO', 'KUBIKEL', 'LBS', 'LBSM', 'RECLOSER', 'SECTIONALIZER']) 
                            || str_contains($constrName, 'PMS') || str_contains($constrName, 'PMT') || str_contains($constrName, 'GTT');
                if (!$isEquipment) {
                    continue; // Skip individual JTM poles at mid zoom
                }
            }

            // Zoom >= 17: Return ALL markers including individual JTM poles

            $spec = $this->getConstructionMarkerSpec($jenis, $constrName);

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
                    'construction_type' => $constrName,
                    'status'            => $asset['status'] ?? 'NORMAL',
                    'lokasi'            => $asset['lokasi'] ?? '',
                    'marker_spec'       => $spec
                ]
            ];
        }

        return [
            'summary' => $stats,
            'zoom'    => $zoom,
            'transline' => [
                'type' => 'Feature',
                'geometry' => [
                    'type'        => 'LineString',
                    'coordinates' => $lineCoords
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
