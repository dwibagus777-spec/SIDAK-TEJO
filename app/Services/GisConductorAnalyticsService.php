<?php

namespace App\Services;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * GisConductorAnalyticsService
 *
 * Dedicated Read-Model Engine for Conductor Intelligence, Network Conductor Analytics,
 * and Authoritative Length Aggregations across GIS Translines.
 *
 * Architectural Invariants:
 * 1. STRICT AUTHORITATIVE SOURCE: Reads ONLY from `gis_translines` WHERE `is_active = 1`.
 *    Zero inclusion of spatial preview segments (Bahagia Steel 1/2 will return 0).
 * 2. PROVENANCE-AWARE LENGTH: Uses `distance_meters` if > 0; otherwise computes Haversine
 *    geodesic distance on-the-fly. Never writes back to DB during analytics.
 * 3. IDENTICAL READ-MODEL: Both Screen Analytics and Spreadsheet Export share the exact same query.
 * 4. FAULT LOCATOR READY: Extracts graph-ready edge features (feeder, section, conductor, length, endpoints).
 * 5. FEEDER SCOPE VS GLOBAL SEARCH: Explicitly isolates active feeder vs global multi-feeder queries.
 */
class GisConductorAnalyticsService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Canonical text parser for conductor search inputs
     * Normalizes e.g. "A3C 150", "A3C150", "A3C 150 mm2", "AAAC 70 mm²"
     *
     * @param string $query
     * @return array{type: string, size: string, raw: string}
     */
    public static function parseConductorQuery(string $query): array
    {
        $raw = trim($query);
        if ($raw === '') {
            return ['type' => '', 'size' => '', 'raw' => ''];
        }

        $clean = strtoupper($raw);
        $clean = str_replace(['MM2', 'MM²', 'SQMM', 'MILI', 'METER', 'KABEL', 'CONDUCTOR', 'KONDUKTOR'], '', $clean);
        $clean = trim($clean);

        $type = '';
        $size = '';

        // Known conductor types
        $knownTypes = ['A3C', 'AAAC', 'XLPE', 'ACSR', 'AAC', 'MVTIC', 'SKTM', 'TC'];
        foreach ($knownTypes as $kt) {
            if (strpos($clean, $kt) !== false) {
                $type = $kt;
                $clean = trim(str_replace($kt, '', $clean));
                break;
            }
        }

        // Look for numeric cross-section
        $cleanWithoutNum = $clean;
        if (preg_match('/(\d+(?:\.\d+)?)/', $clean, $matches)) {
            $num = $matches[1];
            $size = "{$num} mm²";
            $cleanWithoutNum = trim(preg_replace('/\b' . preg_quote($num, '/') . '\b/', '', $clean));
        }

        // If type wasn't found in known list, use alphabetic word if it remains
        if ($type === '' && preg_match('/^([A-Z][A-Z0-9]*)/', $cleanWithoutNum, $wMatches)) {
            $type = $wMatches[1];
        }

        return [
            'type' => $type,
            'size' => $size,
            'raw'  => $raw
        ];
    }

    /**
     * Haversine formula for calculating geodesic distance between two GPS coordinates in meters
     */
    public static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if ($lat1 === 0.0 || $lon1 === 0.0 || $lat2 === 0.0 || $lon2 === 0.0) {
            return 0.0;
        }

        $earthRadius = 6371000.0; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Retrieve Conductor Analytics dataset based on multi-dimensional filters
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getAnalytics(array $filters = []): array
    {
        if (!$this->db->tableExists('gis_translines')) {
            return $this->buildEmptyResult($filters, 'Table gis_translines does not exist.');
        }

        $feederId      = (int)($filters['penyulang_id'] ?? 0);
        $ulpId         = (int)($filters['ulp_id'] ?? 0);
        $sectionId     = (int)($filters['section_id'] ?? 0);
        $scope         = strtolower(trim((string)($filters['scope'] ?? 'feeder')));
        $conductorType = trim((string)($filters['conductor_type'] ?? ''));
        $conductorSize = trim((string)($filters['conductor_size'] ?? ''));
        $queryText     = trim((string)($filters['q'] ?? $filters['query'] ?? ''));

        // Parse free-form query if provided and explicit type/size not specified
        if ($queryText !== '') {
            $parsed = self::parseConductorQuery($queryText);
            if ($conductorType === '' && $parsed['type'] !== '') {
                $conductorType = $parsed['type'];
            }
            if ($conductorSize === '' && $parsed['size'] !== '') {
                $conductorSize = $parsed['size'];
            }
        }

        $hasMaterial     = $this->db->fieldExists('conductor_material', 'gis_translines');
        $hasInstallation = $this->db->fieldExists('installation_type', 'gis_translines');
        $hasCircuit      = $this->db->fieldExists('circuit_config', 'gis_translines');

        // Build Database Query with strict authoritative constraints
        $builder = $this->db->table('gis_translines gt');
        $builder->select([
            'gt.id',
            'gt.transline_code',
            'gt.penyulang_id',
            'gt.source_asset_id',
            'gt.target_asset_id',
            'gt.geometry',
            'gt.geometry_type',
            'gt.conductor_type',
            'gt.conductor_size',
            $hasMaterial ? 'gt.conductor_material' : "'ALUMINUM_ALLOY' AS conductor_material",
            $hasInstallation ? 'gt.installation_type' : "'OVERHEAD' AS installation_type",
            $hasCircuit ? 'gt.circuit_config' : "'3_PHASE' AS circuit_config",
            'gt.distance_meters',
            'gt.status',
            'gt.is_active',
            'gt.created_by',
            'p.nama_penyulang',
            'p.ulp_id',
            'u.nama_ulp',
            'sa.nama_asset AS sa_nama_asset',
            'sa.kode_asset AS sa_kode_asset',
            'sa.section_id AS sa_section_id',
            'sa.latitude AS sa_lat',
            'sa.longitude AS sa_lng',
            'ta.nama_asset AS ta_nama_asset',
            'ta.kode_asset AS ta_kode_asset',
            'ta.latitude AS ta_lat',
            'ta.longitude AS ta_lng',
            'sec.nama_section AS sec_nama_section'
        ]);

        // Joins with safeguards
        $builder->join('penyulang p', 'p.id = gt.penyulang_id', 'left');
        $builder->join('ulps u', 'u.id = p.ulp_id', 'left');
        $builder->join('assets sa', 'sa.id = gt.source_asset_id', 'left');
        $builder->join('assets ta', 'ta.id = gt.target_asset_id', 'left');
        $builder->join('sections sec', 'sec.id = sa.section_id', 'left');

        // INVARIANT 1: Authoritative & Active rows ONLY
        $builder->where('gt.is_active', 1);
        if ($this->db->fieldExists('deleted_at', 'gis_translines')) {
            $builder->where('gt.deleted_at IS NULL');
        }

        // Scope Filter: Feeder vs Global Search
        if ($scope === 'global') {
            // Global search searches across all feeders; if feederId > 0 is specifically passed as an explicit filter, apply it
            if ($feederId > 0 && !empty($filters['force_feeder_filter'])) {
                $builder->where('gt.penyulang_id', $feederId);
            }
        } else {
            // Feeder-scoped: default to given feederId
            if ($feederId > 0) {
                $builder->where('gt.penyulang_id', $feederId);
            }
        }

        // ULP Filter
        if ($ulpId > 0) {
            $builder->where('p.ulp_id', $ulpId);
        }

        // Section Filter
        if ($sectionId > 0) {
            $builder->groupStart()
                ->where('sa.section_id', $sectionId)
                ->orWhere('ta.section_id', $sectionId)
                ->groupEnd();
        }

        // Conductor Type Filter
        if ($conductorType !== '') {
            $builder->like('UPPER(gt.conductor_type)', strtoupper($conductorType));
        }

        // Conductor Size Filter
        if ($conductorSize !== '') {
            $cleanSize = trim(str_replace(['mm2', 'mm²', ' '], '', strtolower($conductorSize)));
            $builder->groupStart()
                ->like('gt.conductor_size', $conductorSize)
                ->orLike('gt.conductor_size', $cleanSize)
                ->groupEnd();
        }

        $rows = $builder->orderBy('gt.penyulang_id', 'ASC')
            ->orderBy('gt.id', 'ASC')
            ->get()
            ->getResultArray();

        // Process rows, calculate provenance length, and aggregate analytics
        $items = [];
        $totalLengthMeters = 0.0;
        $uniqueFeeders = [];
        $uniqueSections = [];
        $uniqueUlps = [];
        $uniqueAssets = [];

        foreach ($rows as $r) {
            $tId  = (int)$r['id'];
            $pId  = (int)$r['penyulang_id'];
            $uId  = (int)($r['ulp_id'] ?? 0);
            $sId  = (int)$r['source_asset_id'];
            $tTar = (int)$r['target_asset_id'];

            $uniqueAssets[$sId]  = true;
            $uniqueAssets[$tTar] = true;

            $pName   = $r['nama_penyulang'] ?? "Penyulang #{$pId}";
            $uName   = $r['nama_ulp'] ?? '-';
            $secName = $r['sec_nama_section'] ?? ($r['sa_section_id'] ? "Section #{$r['sa_section_id']}" : '-');

            if ($pId > 0) {
                if (!isset($uniqueFeeders[$pId])) {
                    $uniqueFeeders[$pId] = [
                        'id'        => $pId,
                        'name'      => $pName,
                        'ulp_name'  => $uName,
                        'count'     => 0,
                        'length_m'  => 0.0
                    ];
                }
                $uniqueFeeders[$pId]['count']++;
            }

            if (!empty($secName) && $secName !== '-') {
                if (!isset($uniqueSections[$secName])) {
                    $uniqueSections[$secName] = [
                        'name'        => $secName,
                        'feeder_name' => $pName,
                        'count'       => 0,
                        'length_m'    => 0.0
                    ];
                }
                $uniqueSections[$secName]['count']++;
            }

            if ($uId > 0 && !isset($uniqueUlps[$uId])) {
                $uniqueUlps[$uId] = [
                    'id'   => $uId,
                    'name' => $uName
                ];
            }

            // Length Provenance Calculation
            $storedDistance = (float)($r['distance_meters'] ?? 0);
            $lengthMeters = $storedDistance;
            $provenance = 'STORED_DISTANCE';

            $saLat = (float)($r['sa_lat'] ?? 0);
            $saLng = (float)($r['sa_lng'] ?? 0);
            $taLat = (float)($r['ta_lat'] ?? 0);
            $taLng = (float)($r['ta_lng'] ?? 0);

            if ($lengthMeters <= 0.0) {
                if ($saLat != 0.0 && $saLng != 0.0 && $taLat != 0.0 && $taLng != 0.0) {
                    $lengthMeters = self::haversineDistance($saLat, $saLng, $taLat, $taLng);
                    $provenance = 'CALCULATED_HAVERSINE';
                }
            }

            $lengthMeters = round($lengthMeters, 2);
            $totalLengthMeters += $lengthMeters;

            if ($pId > 0 && isset($uniqueFeeders[$pId])) {
                $uniqueFeeders[$pId]['length_m'] += $lengthMeters;
            }
            if (!empty($secName) && isset($uniqueSections[$secName])) {
                $uniqueSections[$secName]['length_m'] += $lengthMeters;
            }

            // Resolve line coordinates
            $coordinates = [];
            if ($saLat != 0.0 && $saLng != 0.0 && $taLat != 0.0 && $taLng != 0.0) {
                $coordinates = [[$saLat, $saLng], [$taLat, $taLng]];
            } elseif (!empty($r['geometry'])) {
                $rawGeom = json_decode($r['geometry'], true);
                if (is_array($rawGeom)) {
                    if (isset($rawGeom['coordinates']) && is_array($rawGeom['coordinates'])) {
                        $coordinates = $rawGeom['coordinates'];
                    } elseif (isset($rawGeom[0]) && is_array($rawGeom[0])) {
                        $coordinates = $rawGeom;
                    }
                }
            }

            $cType  = $r['conductor_type'] ?: 'AAAC';
            $cSize  = $r['conductor_size'] ?: '150 mm²';
            $cLabel = "{$cType} {$cSize}";

            $sourceName = $r['sa_nama_asset'] ?: ($r['sa_kode_asset'] ?: "Asset #{$sId}");
            $targetName = $r['ta_nama_asset'] ?: ($r['ta_kode_asset'] ?: "Asset #{$tTar}");

            $items[] = [
                'transline_id'       => $tId,
                'transline_code'     => $r['transline_code'] ?: "TL-{$pId}-{$sId}-{$tTar}",
                'penyulang_id'       => $pId,
                'nama_penyulang'     => $pName,
                'ulp_id'             => $uId,
                'nama_ulp'           => $uName,
                'section_id'         => (int)($r['sa_section_id'] ?? 0),
                'nama_section'       => $secName,
                'source_asset_id'    => $sId,
                'source_asset_name'  => $sourceName,
                'source_asset_code'  => $r['sa_kode_asset'] ?? '',
                'source_lat'         => $saLat,
                'source_lng'         => $saLng,
                'target_asset_id'    => $tTar,
                'target_asset_name'  => $targetName,
                'target_asset_code'  => $r['ta_kode_asset'] ?? '',
                'target_lat'         => $taLat,
                'target_lng'         => $taLng,
                'conductor_type'     => $cType,
                'conductor_size'     => $cSize,
                'conductor_label'    => $cLabel,
                'conductor_material' => $r['conductor_material'] ?: 'ALUMINUM_ALLOY',
                'installation_type'  => $r['installation_type'] ?: 'OVERHEAD',
                'circuit_config'     => $r['circuit_config'] ?: '3_PHASE',
                'length_meter'       => $lengthMeters,
                'length_provenance'  => $provenance,
                'coordinates'        => $coordinates,
                'status'             => 'ACTIVE',
                'is_authoritative'   => true,
                'created_by'         => $r['created_by'] ?? 'SYSTEM',
            ];
        }

        // Format summary breakdown lists
        foreach ($uniqueFeeders as $k => $v) {
            $uniqueFeeders[$k]['length_km'] = round($v['length_m'] / 1000.0, 3);
            $uniqueFeeders[$k]['length_m']  = round($v['length_m'], 1);
        }
        foreach ($uniqueSections as $k => $v) {
            $uniqueSections[$k]['length_km'] = round($v['length_m'] / 1000.0, 3);
            $uniqueSections[$k]['length_m']  = round($v['length_m'], 1);
        }

        $totalLengthKm = round($totalLengthMeters / 1000.0, 3);

        return [
            'status' => 'success',
            'summary' => [
                'transline_resmi_count' => count($items),
                'total_panjang_meter'   => round($totalLengthMeters, 2),
                'total_panjang_km'      => $totalLengthKm,
                'penyulang_count'       => count($uniqueFeeders),
                'section_count'         => count($uniqueSections),
                'ulp_count'             => count($uniqueUlps),
                'distinct_assets_count' => count($uniqueAssets),
                'query_conductor_type'  => $conductorType,
                'query_conductor_size'  => $conductorSize,
                'scope'                 => $scope,
                'is_authoritative_only' => true,
                'preview_included'      => false,
            ],
            'breakdown' => [
                'feeders'  => array_values($uniqueFeeders),
                'sections' => array_values($uniqueSections),
                'ulps'     => array_values($uniqueUlps),
            ],
            'items' => $items
        ];
    }

    /**
     * Generate UTF-8 tab-delimited Excel (.xls) file stream from the exact same analytics model
     */
    public function generateSpreadsheetContent(array $filters = []): string
    {
        $result = $this->getAnalytics($filters);
        $summary = $result['summary'];
        $items = $result['items'];

        $out = "\xEF\xBB\xBF"; // UTF-8 BOM

        // Header Metadata
        $out .= "SIDAK TEJO - REKAPITULASI ANALISIS KONDUKTOR TRANSLINE RESMI\n";
        $out .= "Filter Konduktor\t: " . ($summary['query_conductor_type'] ?: 'Semua') . " " . ($summary['query_conductor_size'] ?: '') . "\n";
        $out .= "Scope Pencarian\t: " . strtoupper($summary['scope']) . "\n";
        $out .= "Total Transline Resmi\t: " . $summary['transline_resmi_count'] . "\n";
        $out .= "Total Panjang\t: " . $summary['total_panjang_km'] . " km (" . number_format($summary['total_panjang_meter'], 2, ',', '.') . " m)\n";
        $out .= "Jumlah Penyulang\t: " . $summary['penyulang_count'] . "\n";
        $out .= "Jumlah Section\t: " . $summary['section_count'] . "\n";
        $out .= "Jumlah Aset Terkait\t: " . $summary['distinct_assets_count'] . "\n";
        $out .= "Status Data\t: AUTHORITATIVE DATABASE ONLY (Preview Spasial Mutlak 0)\n";
        $out .= "Tanggal Ekspor\t: " . date('Y-m-d H:i:s') . "\n\n";

        // Table Columns
        $columns = [
            'No',
            'Kode Transline',
            'ULP',
            'Penyulang',
            'Section',
            'Titik A (Dari)',
            'Titik B (Ke)',
            'Konduktor',
            'Penampang',
            'Panjang (Meter)',
            'Provenance Panjang',
            'Status'
        ];
        $out .= implode("\t", $columns) . "\n";

        $no = 1;
        foreach ($items as $row) {
            $line = [
                $no++,
                $row['transline_code'],
                $row['nama_ulp'],
                $row['nama_penyulang'],
                $row['nama_section'],
                $row['source_asset_name'],
                $row['target_asset_name'],
                $row['conductor_type'],
                $row['conductor_size'],
                str_replace('.', ',', (string)$row['length_meter']),
                $row['length_provenance'],
                $row['status'] . ' (OTORITATIF)'
            ];
            $out .= implode("\t", $line) . "\n";
        }

        return $out;
    }

    /**
     * Build standard empty result
     */
    protected function buildEmptyResult(array $filters, string $message): array
    {
        return [
            'status'  => 'success',
            'message' => $message,
            'summary' => [
                'transline_resmi_count' => 0,
                'total_panjang_meter'   => 0.0,
                'total_panjang_km'      => 0.0,
                'penyulang_count'       => 0,
                'section_count'         => 0,
                'ulp_count'             => 0,
                'distinct_assets_count' => 0,
                'query_conductor_type'  => $filters['conductor_type'] ?? '',
                'query_conductor_size'  => $filters['conductor_size'] ?? '',
                'scope'                 => $filters['scope'] ?? 'feeder',
                'is_authoritative_only' => true,
                'preview_included'      => false,
            ],
            'breakdown' => [
                'feeders'  => [],
                'sections' => [],
                'ulps'     => [],
            ],
            'items' => []
        ];
    }
}
