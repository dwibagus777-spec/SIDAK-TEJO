<?php

namespace App\Services;

/**
 * SLD-05S: Location Context Service
 *
 * Provides isolated geographic and road context (ROAD_CONTEXT) for the Single Line Diagram.
 * Governed by the Strict Isolation Invariant (Amendment #5):
 * - Road and locality information is purely visual metadata (ROAD_CONTEXT).
 * - NEVER serves as evidence for topology graph construction or edge connectivity.
 * - Zero dependency on external blocking APIs (Amendment #6).
 * - Operates in pure SELECT / in-memory mode: Delta = 0.
 */
class SldLocationContextService
{
    protected ?object $db = null;

    /**
     * Local Sidoarjo geographic dictionary for offline locality resolution.
     */
    protected array $localityDictionary = [
        'BANJAR KEMANTRAN' => [
            'road_name'    => 'Jl. Raya Banjar Kemantren',
            'locality'     => 'Desa Banjar Kemantren, Kec. Buduran',
            'default_sub'  => 'Buduran',
        ],
        'BANJARKEMANTRAN'  => [
            'road_name'    => 'Jl. Raya Banjar Kemantren',
            'locality'     => 'Desa Banjar Kemantren, Kec. Buduran',
            'default_sub'  => 'Buduran',
        ],
        'KEBOAN ANOM'      => [
            'road_name'    => 'Jl. Raya Keboan Anom',
            'locality'     => 'Desa Keboan Anom, Kec. Gedangan',
            'default_sub'  => 'Gedangan',
        ],
        'KEBOANANOM'       => [
            'road_name'    => 'Jl. Raya Keboan Anom',
            'locality'     => 'Desa Keboan Anom, Kec. Gedangan',
            'default_sub'  => 'Gedangan',
        ],
        'KEBOAN SIKEP'     => [
            'road_name'    => 'Jl. Keboan Sikep',
            'locality'     => 'Desa Keboan Sikep, Kec. Gedangan',
            'default_sub'  => 'Gedangan',
        ],
        'JAMBANGAN'        => [
            'road_name'    => 'Jl. Ds. Jambangan',
            'locality'     => 'Desa Jambangan, Kec. Candi',
            'default_sub'  => 'Candi',
        ],
        'BUDURAN'          => [
            'road_name'    => 'Jl. Raya Buduran',
            'locality'     => 'Kecamatan Buduran, Sidoarjo',
            'default_sub'  => 'Buduran',
        ],
        'SENTOSA MANDIRI'  => [
            'road_name'    => 'Jl. Perum Sentosa Mandiri',
            'locality'     => 'Perum Sentosa Mandiri, Kec. Candi',
            'default_sub'  => 'Candi',
        ],
    ];

    public function __construct(?object $db = null)
    {
        $this->db = $db;
    }

    /**
     * Resolve location context for a single asset.
     *
     * Hierarchy (Amendment #6):
     * 1. assets.lokasi (if populated in DB)
     * 2. sections.nama_section (if linked)
     * 3. Asset name patterns (e.g. BANJARKEMANTRAN_99 -> Ds. Banjar Kemantren)
     * 4. Offline Sidoarjo locality dictionary
     * 5. Fallback: Feeder metadata / ULP territory
     *
     * @param array $asset
     * @param array $feeder
     * @return array
     */
    public function resolveAssetLocationContext(array $asset, array $feeder = []): array
    {
        $assetName = strtoupper($asset['nama_asset'] ?? $asset['name'] ?? '');
        $lokasiRaw = trim($asset['lokasi'] ?? '');
        $lat = (float)($asset['latitude'] ?? ($asset['geo']['latitude'] ?? 0));
        $lng = (float)($asset['longitude'] ?? ($asset['geo']['longitude'] ?? 0));

        // 1. Direct assets.lokasi
        if (!empty($lokasiRaw)) {
            return [
                'context_type' => 'ROAD_CONTEXT',
                'road_name'    => $lokasiRaw,
                'locality'     => $feeder['ulp_name'] ?? 'ULP Sidoarjo Kota',
                'latitude'     => $lat !== 0.0 ? $lat : null,
                'longitude'    => $lng !== 0.0 ? $lng : null,
                'source'       => 'ASSET_DATABASE_FIELD',
                'confidence'   => 'HIGH',
            ];
        }

        // 2. Asset Name Pattern Matching via Locality Dictionary
        foreach ($this->localityDictionary as $key => $meta) {
            if (str_contains($assetName, $key)) {
                return [
                    'context_type' => 'ROAD_CONTEXT',
                    'road_name'    => $meta['road_name'],
                    'locality'     => $meta['locality'],
                    'latitude'     => $lat !== 0.0 ? $lat : null,
                    'longitude'    => $lng !== 0.0 ? $lng : null,
                    'source'       => 'LOCALITY_DICTIONARY_PATTERN',
                    'confidence'   => 'HIGH',
                ];
            }
        }

        // 3. Section context fallback
        $sectionName = trim($asset['nama_section'] ?? '');
        if (!empty($sectionName)) {
            return [
                'context_type' => 'ROAD_CONTEXT',
                'road_name'    => "Koridor " . $sectionName,
                'locality'     => $feeder['ulp_name'] ?? 'ULP Sidoarjo Kota',
                'latitude'     => $lat !== 0.0 ? $lat : null,
                'longitude'    => $lng !== 0.0 ? $lng : null,
                'source'       => 'SECTION_METADATA',
                'confidence'   => 'MEDIUM',
            ];
        }

        // 4. Default Feeder / ULP Fallback
        $feederName = $feeder['name'] ?? $feeder['nama_penyulang'] ?? 'FEEDER';
        return [
            'context_type' => 'ROAD_CONTEXT',
            'road_name'    => "Wilayah " . $feederName,
            'locality'     => $feeder['ulp_name'] ?? 'ULP Sidoarjo Kota',
            'latitude'     => $lat !== 0.0 ? $lat : null,
            'longitude'    => $lng !== 0.0 ? $lng : null,
            'source'       => 'FEEDER_DEFAULT',
            'confidence'   => 'LOW',
        ];
    }

    /**
     * Build named Road Corridors for Hybrid View (Mode C).
     *
     * Groups sequential nodes along the schematic into named geographic corridors.
     * Road corridors provide the visual double-line road boundaries and street labels.
     *
     * @param int $penyulangId
     * @param array $nodes
     * @param array $edges
     * @param array $feeder
     * @return array
     */
    public function buildRoadCorridors(int $penyulangId, array $nodes, array $edges, array $feeder = []): array
    {
        $corridors = [];
        $nodeMap = [];
        foreach ($nodes as $n) {
            $nodeMap[$n['asset_id']] = $n;
        }

        // Group nodes by their detected locality
        $localityBuckets = [];
        foreach ($nodes as $n) {
            $loc = $this->resolveAssetLocationContext($n, $feeder);
            $road = $loc['road_name'];
            if (!isset($localityBuckets[$road])) {
                $localityBuckets[$road] = [
                    'road_name' => $road,
                    'locality'  => $loc['locality'],
                    'nodes'     => [],
                    'min_x'     => PHP_INT_MAX,
                    'max_x'     => -PHP_INT_MAX,
                    'min_y'     => PHP_INT_MAX,
                    'max_y'     => -PHP_INT_MAX,
                ];
            }
            $localityBuckets[$road]['nodes'][] = $n['asset_id'];
            $gx = $n['schematic']['grid_x'] ?? 0;
            $gy = $n['schematic']['grid_y'] ?? 0;
            if ($gx < $localityBuckets[$road]['min_x']) $localityBuckets[$road]['min_x'] = $gx;
            if ($gx > $localityBuckets[$road]['max_x']) $localityBuckets[$road]['max_x'] = $gx;
            if ($gy < $localityBuckets[$road]['min_y']) $localityBuckets[$road]['min_y'] = $gy;
            if ($gy > $localityBuckets[$road]['max_y']) $localityBuckets[$road]['max_y'] = $gy;
        }

        $corrIdx = 1;
        foreach ($localityBuckets as $roadName => $b) {
            if (count($b['nodes']) < 2) continue; // Skip single isolated nodes
            $corridors[] = [
                'corridor_id' => sprintf("CORR-%02d", $corrIdx++),
                'road_name'   => $roadName,
                'locality'    => $b['locality'],
                'node_count'  => count($b['nodes']),
                'node_ids'    => $b['nodes'],
                'bounds'      => [
                    'min_grid_x' => $b['min_x'],
                    'max_grid_x' => $b['max_x'],
                    'min_grid_y' => $b['min_y'],
                    'max_grid_y' => $b['max_y'],
                ],
            ];
        }

        return $corridors;
    }
}
