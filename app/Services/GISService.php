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
                return '#d97706'; // Orange / Yellow
            case 'MENUNGGU_VERIFIKASI':
                return '#ea580c'; // Dark Orange
            default:
                return '#64748b'; // Grey for Non-aktif / Retired
        }
    }

    /**
     * Map Asset Type code to Leaflet marker shape
     */
    public function getMarkerShape(?string $jenisAsset): string
    {
        $jenis = strtoupper((string)$jenisAsset);

        if (str_contains($jenis, 'TRAFO')) {
            return 'diamond';
        }
        if (str_contains($jenis, 'GARDU')) {
            return 'square';
        }
        if (str_contains($jenis, 'LBS')) {
            return 'pentagon';
        }
        if (str_contains($jenis, 'RECLOSER')) {
            return 'hexagon';
        }
        if (str_contains($jenis, 'TIANG')) {
            return 'circle';
        }

        return 'circle';
    }

    /**
     * Generate GeoJSON FeatureCollection for Leaflet GIS Map
     */
    public function getGeoJsonCollection(array $filters = [], ?int $userUlpId = null): array
    {
        $assets = $this->assetRepository->getFilteredAssets($filters, $userUlpId);
        $features = [];

        foreach ($assets as $a) {
            $lat = (float)($a['latitude'] ?? 0);
            $lng = (float)($a['longitude'] ?? 0);

            if ($lat == 0 || $lng == 0) {
                continue; // Skip assets without coordinates
            }

            $color = $this->getStatusColor($a['status'] ?? 'NORMAL');
            $shape = $this->getMarkerShape($a['jenis_asset'] ?? '');

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat]
                ],
                'properties' => [
                    'id'           => (int)$a['id'],
                    'kode_asset'   => $a['kode_asset'],
                    'nama_asset'   => $a['nama_asset'],
                    'jenis_asset'  => $a['jenis_asset'] ?: 'Gardu',
                    'status'       => $a['status'],
                    'merk'         => $a['merk'] ?: '-',
                    'nomor_seri'   => $a['nomor_seri'] ?: '-',
                    'lokasi'       => $a['lokasi'] ?: '-',
                    'nama_ulp'     => $a['nama_ulp'] ?: '-',
                    'marker_color' => $color,
                    'marker_shape' => $shape,
                    'detail_url'   => site_url('assets/detail/' . $a['id']),
                    'twin_url'     => site_url('assets/digital-twin/' . $a['id']),
                ]
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }
}
