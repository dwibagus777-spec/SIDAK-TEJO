<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * MAP-03: Read-Only Location Context Assistant Service
 *
 * Deterministically resolves nearby network context (ULP -> Feeder -> Section)
 * and optional nearest asset suggestion based on operator GPS coordinates.
 *
 * Guaranteed Invariants:
 * - STRICT READ-ONLY: 0 INSERT, 0 UPDATE, 0 DELETE.
 * - NO SILENT BINDING: Asset context is returned as suggestion only, NEVER persisted to temuan.asset_id.
 * - BOUNDED SPATIAL SEARCH: Capped at <= 1,000 meters (1 km).
 * - SCOPED AUTHORIZATION: Enforces server-side user ULP boundaries.
 */
class LocationContextService
{
    public const MAX_SEARCH_RADIUS_METERS = 1000.0;
    public const ACCURACY_HIGH_QUALITY    = 20.0;
    public const ACCURACY_ACCEPTABLE      = 50.0;

    public const STATUS_READY               = 'READY';
    public const STATUS_LOW_ACCURACY        = 'LOW_ACCURACY';
    public const STATUS_NO_CONTEXT          = 'NO_CONTEXT';
    public const STATUS_INVALID_COORDINATES = 'INVALID_COORDINATES';
    public const STATUS_GPS_UNAVAILABLE     = 'GPS_UNAVAILABLE';
    public const STATUS_FORBIDDEN           = 'FORBIDDEN';

    public const QUALITY_HIGH_QUALITY = 'HIGH_QUALITY';
    public const QUALITY_ACCEPTABLE   = 'ACCEPTABLE';
    public const QUALITY_LOW_ACCURACY = 'LOW_ACCURACY';
    public const QUALITY_UNKNOWN      = 'UNKNOWN';

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Compute Haversine distance in meters between two lat/long points
     */
    public function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (abs($lat1) < 0.00001 && abs($lon1) < 0.00001) return 0.0;
        if (abs($lat2) < 0.00001 && abs($lon2) < 0.00001) return 0.0;

        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;

        return round(2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /**
     * Resolve network context from GPS coordinates
     *
     * @param float|null $lat Latitude
     * @param float|null $lng Longitude
     * @param float|null $accuracy GPS accuracy in meters
     * @param int|null $userUlpId User assigned ULP ID for authorization scoping
     * @param string|null $userRole User role
     * @return array Canonical context response
     */
    public function resolveContext(
        ?float $lat,
        ?float $lng,
        ?float $accuracy = null,
        ?int $userUlpId = null,
        ?string $userRole = null
    ): array {
        // 1. Validate Coordinates presence
        if ($lat === null || $lng === null) {
            return [
                'status'        => self::STATUS_GPS_UNAVAILABLE,
                'message'       => 'Koordinat GPS tidak tersedia.',
                'gps'           => null,
                'context'       => null,
                'nearest_asset' => null,
                'gps_quality'   => self::QUALITY_UNKNOWN,
                'diagnostic'    => 'COORDINATES_MISSING',
            ];
        }

        // 2. Validate Coordinate Boundaries & Null Island
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0 || (abs($lat) < 0.00001 && abs($lng) < 0.00001)) {
            return [
                'status'        => self::STATUS_INVALID_COORDINATES,
                'message'       => 'Koordinat GPS berada di luar batas valid.',
                'gps'           => ['lat' => $lat, 'lng' => $lng, 'accuracy_m' => $accuracy],
                'context'       => null,
                'nearest_asset' => null,
                'gps_quality'   => self::QUALITY_UNKNOWN,
                'diagnostic'    => 'COORDINATES_OUT_OF_BOUNDS',
            ];
        }

        // 3. Evaluate GPS Quality
        $gpsQuality = self::QUALITY_UNKNOWN;
        if ($accuracy !== null && $accuracy >= 0) {
            if ($accuracy <= self::ACCURACY_HIGH_QUALITY) {
                $gpsQuality = self::QUALITY_HIGH_QUALITY;
            } elseif ($accuracy <= self::ACCURACY_ACCEPTABLE) {
                $gpsQuality = self::QUALITY_ACCEPTABLE;
            } else {
                $gpsQuality = self::QUALITY_LOW_ACCURACY;
            }
        }

        // 4. Check if assets table exists
        if (!$this->db->tableExists('assets')) {
            return [
                'status'        => self::STATUS_NO_CONTEXT,
                'message'       => 'Tabel aset tidak tersedia pada sistem.',
                'gps'           => ['lat' => $lat, 'lng' => $lng, 'accuracy_m' => $accuracy],
                'context'       => null,
                'nearest_asset' => null,
                'gps_quality'   => $gpsQuality,
                'diagnostic'    => 'ASSETS_TABLE_UNAVAILABLE',
            ];
        }

        // 5. Bounded Spatial Query (1 km bounding box)
        // 1 degree lat approx 111 km => 1 km approx 0.00901 degrees (~0.01)
        $latDelta = self::MAX_SEARCH_RADIUS_METERS / 111000.0;
        $cosLat = cos(deg2rad($lat));
        $lngDelta = ($cosLat > 0.0001) ? (self::MAX_SEARCH_RADIUS_METERS / (111000.0 * $cosLat)) : $latDelta;

        $builder = $this->db->table('assets');
        if ($this->db->DBDriver === 'SQLite3') {
            $builder->where("CAST(latitude AS REAL) >=", $lat - $latDelta)
                    ->where("CAST(latitude AS REAL) <=", $lat + $latDelta)
                    ->where("CAST(longitude AS REAL) >=", $lng - $lngDelta)
                    ->where("CAST(longitude AS REAL) <=", $lng + $lngDelta);
        } else {
            $builder->where('latitude >=', $lat - $latDelta)
                    ->where('latitude <=', $lat + $latDelta)
                    ->where('longitude >=', $lng - $lngDelta)
                    ->where('longitude <=', $lng + $lngDelta);
        }

        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }

        $candidates = $builder->get()->getResultArray();

        // 6. Compute exact Haversine distances and filter by radius <= 1 km
        $validAssets = [];
        foreach ($candidates as $candidate) {
            $cLat = (float)($candidate['latitude'] ?? 0);
            $cLng = (float)($candidate['longitude'] ?? 0);
            if (abs($cLat) < 0.00001 && abs($cLng) < 0.00001) {
                continue;
            }

            $dist = $this->haversineDistanceMeters($lat, $lng, $cLat, $cLng);
            if ($dist <= self::MAX_SEARCH_RADIUS_METERS) {
                $validAssets[] = [
                    'asset'    => $candidate,
                    'distance' => $dist,
                ];
            }
        }

        // 7. Handle NO_CONTEXT when no assets within 1 km
        if (empty($validAssets)) {
            return [
                'status'        => self::STATUS_NO_CONTEXT,
                'message'       => 'Tidak ditemukan aset jaringan dalam radius 1 km.',
                'gps'           => ['lat' => $lat, 'lng' => $lng, 'accuracy_m' => $accuracy],
                'context'       => null,
                'nearest_asset' => null,
                'gps_quality'   => $gpsQuality,
                'diagnostic'    => 'NO_ASSETS_WITHIN_BOUND',
            ];
        }

        // 8. Sort by distance ascending
        usort($validAssets, static fn($a, $b) => $a['distance'] <=> $b['distance']);

        $nearest = $validAssets[0];
        $assetData = $nearest['asset'];
        $nearestDistance = $nearest['distance'];

        // 9. Resolve Network Hierarchy
        $sectionId   = !empty($assetData['section_id']) ? (int)$assetData['section_id'] : null;
        $penyulangId = !empty($assetData['penyulang_id']) ? (int)$assetData['penyulang_id'] : null;
        $ulpId       = !empty($assetData['ulp_id']) ? (int)$assetData['ulp_id'] : null;

        $section = null;
        if ($sectionId && $this->db->tableExists('sections')) {
            $section = $this->db->table('sections')->where('id', $sectionId)->get()->getRowArray();
            if ($section && empty($penyulangId) && !empty($section['penyulang_id'])) {
                $penyulangId = (int)$section['penyulang_id'];
            }
        }

        $penyulang = null;
        if ($penyulangId && $this->db->tableExists('penyulang')) {
            $penyulang = $this->db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
            if ($penyulang && empty($ulpId) && !empty($penyulang['ulp_id'])) {
                $ulpId = (int)$penyulang['ulp_id'];
            }
        }

        $ulp = null;
        if ($ulpId && $this->db->tableExists('ulps')) {
            $ulp = $this->db->table('ulps')->where('id', $ulpId)->get()->getRowArray();
        }

        // 10. Authorization Boundary Check (Role-based scoping)
        if ($userRole === 'admin_ulp' && $userUlpId !== null && $ulpId !== null && $ulpId !== $userUlpId) {
            return [
                'status'        => self::STATUS_FORBIDDEN,
                'message'       => 'Aset jaringan terdekat berada di luar wilayah ULP Anda.',
                'gps'           => ['lat' => $lat, 'lng' => $lng, 'accuracy_m' => $accuracy],
                'context'       => null,
                'nearest_asset' => null,
                'gps_quality'   => $gpsQuality,
                'diagnostic'    => 'OUT_OF_SCOPE_ULP',
            ];
        }

        // 11. Diagnostic notes for ambiguity
        $diagnostic = 'EXACT_MATCH';
        if (count($validAssets) > 1) {
            $second = $validAssets[1];
            if (abs($second['distance'] - $nearestDistance) <= 10.0) {
                $secondPenyulang = $second['asset']['penyulang_id'] ?? null;
                if ($secondPenyulang && $penyulangId && (int)$secondPenyulang !== (int)$penyulangId) {
                    $diagnostic = 'AMBIGUOUS_FEEDER_BOUNDARY';
                }
            }
        }

        // Status Determination
        $status = ($gpsQuality === self::QUALITY_LOW_ACCURACY) ? self::STATUS_LOW_ACCURACY : self::STATUS_READY;

        return [
            'status'  => $status,
            'message' => ($status === self::STATUS_LOW_ACCURACY)
                ? 'Konteks terdeteksi dengan akurasi GPS rendah (> 50 m).'
                : 'Konteks jaringan berhasil terdeteksi.',
            'gps'     => [
                'lat'        => $lat,
                'lng'        => $lng,
                'accuracy_m' => $accuracy,
            ],
            'context' => [
                'ulp' => $ulp ? [
                    'id'       => (int)$ulp['id'],
                    'nama_ulp' => $ulp['nama_ulp'] ?? "ULP #{$ulpId}",
                ] : ($ulpId ? ['id' => $ulpId, 'nama_ulp' => "ULP #{$ulpId}"] : null),
                'penyulang' => $penyulang ? [
                    'id'             => (int)$penyulang['id'],
                    'kode_penyulang' => $penyulang['kode_penyulang'] ?? '',
                    'nama_penyulang' => $penyulang['nama_penyulang'] ?? "Penyulang #{$penyulangId}",
                ] : ($penyulangId ? ['id' => $penyulangId, 'kode_penyulang' => '', 'nama_penyulang' => "Penyulang #{$penyulangId}"] : null),
                'section' => $section ? [
                    'id'           => (int)$section['id'],
                    'nama_section' => $section['nama_section'] ?? "Section #{$sectionId}",
                ] : ($sectionId ? ['id' => $sectionId, 'nama_section' => "Section #{$sectionId}"] : null),
            ],
            'nearest_asset' => [
                'id'         => (int)$assetData['id'],
                'kode_asset' => $assetData['kode_asset'] ?? '',
                'nama_asset' => $assetData['nama_asset'] ?? '',
                'distance_m' => $nearestDistance,
            ],
            'gps_quality' => $gpsQuality,
            'diagnostic'  => $diagnostic,
        ];
    }
}
