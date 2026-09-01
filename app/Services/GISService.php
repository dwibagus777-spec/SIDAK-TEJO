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

        if (is_string($layerFilter)) {
            $layerFilter = array_map('trim', explode(',', $layerFilter));
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

        // 2. Fetch Master Asset Features (Strictly from assets table only)
        $db = \Config\Database::connect();
        $feederUlpId = (int)($filters['ulp_id'] ?? $userUlpId ?? 0);
        if ($penyulangId > 0 && $feederUlpId <= 0 && $db->tableExists('penyulang')) {
            $fRow = $db->table('penyulang')->select('ulp_id')->where('id', $penyulangId)->get()->getRowArray();
            if (!empty($fRow['ulp_id'])) {
                $feederUlpId = (int)$fRow['ulp_id'];
            }
        }

        $assets = $this->assetRepository->getGisNetworkAssets($filters, $userUlpId);

        $assetFeatures   = [];
        $findingFeatures = [];
        $stats    = [
            'feeder_asset_count'         => 0,
            'unassigned_ulp_asset_count' => 0,
            'total_assets'               => 0,
            'jtm_count'                  => 0,
            'gardu_count'                => 0,
            'trafo_count'                => 0,
            'switch_count'               => 0,
            'temuan_count'               => 0,
            'rejected_cross_feeder'      => 0,
            'rejected_cross_ulp'         => ($feederUlpId > 0 && $db->tableExists('assets'))
                ? $db->table('assets')->where('ulp_id !=', $feederUlpId)->where('deleted_at IS NULL')->countAllResults()
                : 0,
        ];

        foreach ($assets as $asset) {
            $lat = (float)($asset['latitude'] ?? 0);
            $lng = (float)($asset['longitude'] ?? 0);
            if ($lat == 0 || $lng == 0) continue;

            $assetPenyulangId = !empty($asset['penyulang_id']) ? (int)$asset['penyulang_id'] : null;
            $assetUlpId       = (int)($asset['ulp_id'] ?? 0);

            // Scope Classification
            if ($penyulangId > 0 && $assetPenyulangId === $penyulangId) {
                $assetScope = 'FEEDER';
                $isFeederAsset = true;
                $stats['feeder_asset_count']++;
            } elseif ($feederUlpId > 0 && $assetUlpId === $feederUlpId && ($assetPenyulangId === null || $assetPenyulangId === 0)) {
                $assetScope = 'ULP_UNASSIGNED';
                $isFeederAsset = false;
                $stats['unassigned_ulp_asset_count']++;
            } elseif ($penyulangId > 0 && $assetPenyulangId !== null && $assetPenyulangId > 0 && $assetPenyulangId !== $penyulangId) {
                $stats['rejected_cross_feeder']++;
                continue;
            } elseif ($feederUlpId > 0 && $assetUlpId > 0 && $assetUlpId !== $feederUlpId) {
                $stats['rejected_cross_ulp']++;
                continue;
            } else {
                $stats['rejected_cross_ulp']++;
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

            if ($assetScope === 'ULP_UNASSIGNED') {
                $conditionOverlay['ring_class'] = 'asset-ring-unassigned';
                $spec['scope_label'] = 'Belum Terhubung ke Penyulang';
                $spec['is_unassigned'] = true;
            }

            $assetFeatures[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type'        => 'Point',
                    'coordinates' => [$lng, $lat]
                ],
                'properties' => [
                    'entity_type'           => 'ASSET',
                    'asset_scope'           => $assetScope,
                    'is_feeder_asset'       => $isFeederAsset,
                    'source_table'          => 'assets',
                    'id'                    => (int)$asset['id'],
                    'asset_id'              => (int)$asset['id'],
                    'kode_asset'            => $asset['kode_asset'],
                    'nama_asset'            => $asset['nama_asset'],
                    'jenis_asset'           => $jenis,
                    'penyulang_id'          => $assetPenyulangId,
                    'ulp_id'                => $assetUlpId,
                    'selected_penyulang_id' => $penyulangId,
                    'record_penyulang_id'   => $assetPenyulangId,
                    'construction_type'     => $constrName,
                    'status'                => $conditionStatus,
                    'lokasi'                => $asset['lokasi'] ?? '',
                    'latitude'              => $lat,
                    'longitude'             => $lng,
                    'marker_spec'           => $spec,
                    'visual'                => $visual,
                    'condition_overlay'     => $conditionOverlay,
                    'provenance'            => [
                        'source'                          => 'assets',
                        'asset_id'                        => (int)$asset['id'],
                        'asset_scope'                     => $assetScope,
                        'asset_penyulang_id'              => $assetPenyulangId,
                        'asset_ulp_id'                    => $assetUlpId,
                        'selected_penyulang_id'           => $penyulangId,
                        'selected_ulp_id'                 => $feederUlpId,
                        'is_valid_for_selected_scope'     => true,
                        'data_origin'                     => ($assetScope === 'FEEDER') ? 'FEEDER_ASSIGNED_MASTER_ASSET' : 'ULP_UNASSIGNED_MASTER_ASSET'
                    ]
                ]
            ];
        }

        // 3. STRICT TEMUAN LAYER (Only queried & loaded if TEMUAN layer is explicitly selected)
        if (in_array('TEMUAN', $layerFilter) && $penyulangId > 0) {
            $db = \Config\Database::connect();
            if ($db->tableExists('temuan')) {
                $temuanRows = $db->table('temuan')
                    ->where('penyulang_id', $penyulangId)
                    ->where('deleted_at IS NULL')
                    ->where('latitude !=', 0)
                    ->where('longitude !=', 0)
                    ->where('latitude IS NOT NULL')
                    ->where('longitude IS NOT NULL')
                    ->get()
                    ->getResultArray();

                foreach ($temuanRows as $t) {
                    $tLat = (float)$t['latitude'];
                    $tLng = (float)$t['longitude'];
                    $stats['temuan_count']++;

                    $severityColor = '#eab308'; // Default NORMAL/MEDIUM
                    if (strtoupper((string)$t['prioritas']) === 'EMERGENCY') {
                        $severityColor = '#dc2626';
                    } elseif (strtoupper((string)$t['prioritas']) === 'HIGH') {
                        $severityColor = '#ea580c';
                    }

                    $findingFeatures[] = [
                        'type' => 'Feature',
                        'geometry' => [
                            'type'        => 'Point',
                            'coordinates' => [$tLng, $tLat]
                        ],
                        'properties' => [
                            'entity_type'           => 'TEMUAN',
                            'source_table'          => 'temuan',
                            'id'                    => 'TEMUAN-' . $t['id'],
                            'finding_id'            => (int)$t['id'],
                            'nomor_temuan'          => $t['nomor_temuan'] ?? 'TEMUAN-' . $t['id'],
                            'jenis_temuan'          => $t['jenis_temuan'] ?? 'TEMUAN',
                            'prioritas'             => $t['prioritas'] ?? 'NORMAL',
                            'status'                => $t['status'] ?? 'BELUM',
                            'detail_temuan'         => $t['detail_temuan'] ?? '',
                            'penyulang_id'          => (int)$t['penyulang_id'],
                            'selected_penyulang_id' => $penyulangId,
                            'record_penyulang_id'   => (int)$t['penyulang_id'],
                            'latitude'              => $tLat,
                            'longitude'             => $tLng,
                            'marker_spec'           => [
                                'shape'      => 'finding',
                                'icon_class' => 'fa-triangle-exclamation',
                                'color'      => $severityColor,
                                'label'      => 'TEMUAN • ' . ($t['jenis_temuan'] ?? 'ANOMALI')
                            ],
                            'provenance'            => [
                                'source'                          => 'temuan',
                                'finding_id'                      => (int)$t['id'],
                                'penyulang_id'                    => (int)$t['penyulang_id'],
                                'selected_penyulang_id'           => $penyulangId,
                                'is_valid_for_selected_penyulang' => true,
                                'data_origin'                     => 'OPERATIONAL_INSPECTION_FINDINGS'
                            ]
                        ]
                    ];
                }
            }
        }

        $allFeatures = array_merge($assetFeatures, $findingFeatures);
        $hasMasterAssets = ($stats['total_assets'] > 0);
        $hasFeederAssets = ($stats['feeder_asset_count'] > 0);
        $hasTopology     = !empty($segmentData['coordinates']);

        return [
            'summary'   => $stats,
            'zoom'      => $zoom,
            'legend'    => $this->visualRegistry->getLegendItems(),
            'meta'      => [
                'selected_penyulang_id'      => $penyulangId,
                'selected_ulp_id'            => $feederUlpId,
                'feeder_asset_count'         => $stats['feeder_asset_count'],
                'unassigned_ulp_asset_count' => $stats['unassigned_ulp_asset_count'],
                'total_assets'               => $stats['total_assets'],
                'topology_count'             => count($segmentData['edges'] ?? []),
                'temuan_count'               => $stats['temuan_count'],
                'has_master_assets'          => $hasMasterAssets,
                'has_feeder_assets'          => $hasFeederAssets,
                'has_topology'               => $hasTopology,
                'message'                    => $hasFeederAssets 
                    ? 'Data master aset feeder berhasil dimuat.' 
                    : (($stats['unassigned_ulp_asset_count'] > 0)
                        ? "Terdapat {$stats['unassigned_ulp_asset_count']} Master Asset ULP yang belum terhubung ke penyulang."
                        : 'Belum terdapat Master Asset terdaftar untuk penyulang ini.'),
            ],
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
            'translines' => $segmentData['translines'] ?? $segmentData['edges'] ?? [],
            'bbox' => $validGisCount > 0 ? [
                'min_lat' => $minLat,
                'max_lat' => $maxLat,
                'min_lng' => $minLng,
                'max_lng' => $maxLng
            ] : null,
            'features' => $allFeatures
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
