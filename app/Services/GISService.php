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
     * Release D: Map Construction Type code/name to Leaflet marker shape & icon class
     */
    public function getConstructionMarkerSpec(?string $jenisAsset, ?string $constructionType = null): array
    {
        $jenis = strtoupper(trim((string)$jenisAsset));
        $type  = strtoupper(trim((string)$constructionType));

        // Priority Level 1: Primary Asset Category Family
        if ($jenis === 'TRAFO') {
            return [
                'shape'      => 'trafo',
                'icon_class' => 'fas fa-bolt',
                'label'      => 'TRAFO'
            ];
        }
        if ($jenis === 'GARDU') {
            return [
                'shape'      => 'gardu',
                'icon_class' => 'fas fa-building-columns',
                'label'      => 'GARDU'
            ];
        }
        if ($jenis === 'KUBIKEL') {
            return [
                'shape'      => 'kubikel',
                'icon_class' => 'fas fa-box-archive',
                'label'      => 'KUBIKEL'
            ];
        }
        if ($jenis === 'LBS' || $jenis === 'LBSM') {
            return [
                'shape'      => 'lbs',
                'icon_class' => 'fas fa-toggle-on',
                'label'      => $jenis
            ];
        }
        if ($jenis === 'RECLOSER') {
            return [
                'shape'      => 'recloser',
                'icon_class' => 'fas fa-rotate',
                'label'      => 'RECLOSER'
            ];
        }

        // Priority Level 2: JTM / Network Construction Sub-variations
        if (str_contains($type, 'PMS') || str_contains($type, 'PEMISAH')) {
            return [
                'shape'      => 'pms',
                'icon_class' => 'fas fa-toggle-off',
                'label'      => 'JTM • PMS'
            ];
        }
        if (str_contains($type, 'PMT') || str_contains($type, 'PEMUTUS')) {
            return [
                'shape'      => 'pmt',
                'icon_class' => 'fas fa-toggle-on',
                'label'      => 'JTM • PMT'
            ];
        }
        if (str_contains($type, 'GTT')) {
            return [
                'shape'      => 'gtt',
                'icon_class' => 'fas fa-charging-station',
                'label'      => 'JTM • GTT'
            ];
        }
        if (str_contains($type, 'TMTP') || str_contains($type, 'PORTAL')) {
            return [
                'shape'      => 'tmtp',
                'icon_class' => 'fas fa-archway',
                'label'      => 'JTM • TMTP'
            ];
        }

        return [
            'shape'      => 'jtm',
            'icon_class' => 'fas fa-square-poll-vertical',
            'label'      => $jenis ?: 'JTM'
        ];
    }

    /**
     * Release B - Step B2: Get Feeder Network Transline & Bounding Box
     */
    public function getFeederNetwork(int $penyulangId): array
    {
        $db = \Config\Database::connect();
        $penyulang = $db->table('penyulang p')
            ->select('p.id, p.kode_penyulang, p.nama_penyulang, p.gi_id, g.nama_gi')
            ->join('gardu_induk g', 'p.gi_id = g.id', 'left')
            ->where('p.id', $penyulangId)
            ->get()
            ->getRowArray();

        if (!$penyulang) {
            return ['status' => 'error', 'message' => 'Penyulang tidak ditemukan.'];
        }

        $planning = null;
        if ($db->tableExists('inspection_plannings')) {
            $planning = $db->table('inspection_plannings')
                ->where('penyulang_id', $penyulangId)
                ->whereIn('status', ['PUBLISHED', 'IN_PROGRESS'])
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
        }

        $assets = $this->assetRepository->getFilteredAssets(['penyulang_id' => $penyulangId]);
        
        $lineCoords = [];
        $minLat = 90.0; $maxLat = -90.0;
        $minLng = 180.0; $maxLng = -180.0;
        $validCount = 0;

        foreach ($assets as $a) {
            $lat = (float)($a['latitude'] ?? 0);
            $lng = (float)($a['longitude'] ?? 0);
            if ($lat == 0 || $lng == 0) continue;

            $lineCoords[] = [$lng, $lat];
            $validCount++;

            if ($lat < $minLat) $minLat = $lat;
            if ($lat > $maxLat) $maxLat = $lat;
            if ($lng < $minLng) $minLng = $lng;
            if ($lng > $maxLng) $maxLng = $lng;
        }

        return [
            'status'          => 'success',
            'penyulang'       => $penyulang,
            'planning'        => $planning ? [
                'id'             => (int)$planning['id'],
                'nomor_planning' => $planning['nomor_planning'],
                'title'          => $planning['title'],
                'status'         => $planning['status'],
            ] : null,
            'total_assets'    => count($assets),
            'valid_gis_count' => $validCount,
            'transline' => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $lineCoords
                ]
            ],
            'bbox' => $validCount > 0 ? [
                'min_lat' => $minLat,
                'max_lat' => $maxLat,
                'min_lng' => $minLng,
                'max_lng' => $maxLng
            ] : null
        ];
    }

    /**
     * Release D: Get Viewport Assets with Full Feeder Planning Context & Construction Shapes
     */
    public function getFeederViewportAssets(int $penyulangId, ?float $minLat = null, ?float $maxLat = null, ?float $minLng = null, ?float $maxLng = null, ?int $planningId = null): array
    {
        $db = \Config\Database::connect();
        $planning = null;

        if ($planningId > 0 && $db->tableExists('inspection_plannings')) {
            $planning = $db->table('inspection_plannings')
                ->where('id', $planningId)
                ->get()
                ->getRowArray();
        }

        if (!$planning && $penyulangId > 0 && $db->tableExists('inspection_plannings')) {
            $planning = $db->table('inspection_plannings')
                ->where('penyulang_id', $penyulangId)
                ->whereIn('status', ['PUBLISHED', 'IN_PROGRESS'])
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
        }

        $assets = [];
        $pointStatusMap = [];
        $currentTargetAssetId = null;

        if ($planning && $db->tableExists('inspection_planning_assets')) {
            $builder = $db->table('inspection_planning_assets pa');
            $builder->select('pa.sequence_no, a.*');
            $builder->join('assets a', 'pa.asset_id = a.id');
            $builder->where('pa.planning_id', (int)$planning['id']);
            $builder->where('a.deleted_at IS NULL');
            $builder->orderBy('pa.sequence_no', 'ASC');
            $assets = $builder->get()->getResultArray();

            if ($db->tableExists('inspections') && $db->tableExists('inspection_points')) {
                $hasPlanningCol = $db->fieldExists('planning_id', 'inspections');
                $runBuilder = $db->table('inspections');
                if ($hasPlanningCol) {
                    $runBuilder->where('planning_id', (int)$planning['id']);
                } else {
                    $runBuilder->where('penyulang_id', (int)$penyulangId);
                }
                $inspectionRun = $runBuilder
                    ->whereIn('status', ['IN_PROGRESS', 'COMPLETED'])
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->getRowArray();

                if ($inspectionRun) {
                    $points = $db->table('inspection_points')
                        ->where('inspection_id', (int)$inspectionRun['id'])
                        ->get()
                        ->getResultArray();

                    foreach ($points as $pt) {
                        $pointStatusMap[(int)$pt['asset_id']] = strtoupper((string)($pt['status'] ?? 'PENDING'));
                    }
                }
            }

            foreach ($assets as $astItem) {
                $astId = (int)$astItem['id'];
                $st = $pointStatusMap[$astId] ?? 'PENDING';
                if ($st === 'PENDING' || $st === 'DRAFT') {
                    $currentTargetAssetId = $astId;
                    break;
                }
            }
        }

        if (empty($assets)) {
            $assets = $this->assetRepository->getFilteredAssets(['penyulang_id' => $penyulangId]);
        }

        $markers = [];
        $totalPlanned = count($assets);
        $inspectedCount = 0;

        foreach ($assets as $idx => $a) {
            $lat = (float)($a['latitude'] ?? 0);
            $lng = (float)($a['longitude'] ?? 0);
            if ($lat == 0 || $lng == 0) continue;

            $astId = (int)$a['id'];
            $seqNo = (int)($a['sequence_no'] ?? ($idx + 1));
            $inspStatus = $pointStatusMap[$astId] ?? 'PENDING';

            if ($inspStatus === 'PASS' || $inspStatus === 'FAIL' || $inspStatus === 'COMPLETED') {
                $inspectedCount++;
            }

            if ($currentTargetAssetId !== null && $astId === $currentTargetAssetId && ($inspStatus === 'PENDING' || $inspStatus === 'DRAFT')) {
                $inspStatus = 'CURRENT_TARGET';
            }

            if ($minLat !== null && ($lat < $minLat || $lat > $maxLat || $lng < $minLng || $lng > $maxLng)) {
                continue;
            }

            $spec = $this->getConstructionMarkerSpec($a['jenis_asset'] ?? '', $a['nama_asset'] ?? null);

            $accentColor = '#0284c7';
            if ($inspStatus === 'PASS') {
                $accentColor = '#10b981';
            } elseif ($inspStatus === 'FAIL') {
                $accentColor = '#ef4444';
            } elseif ($inspStatus === 'CURRENT_TARGET') {
                $accentColor = '#f59e0b';
            }

            $markers[] = [
                'id'                => $astId,
                'planning_id'       => $planning ? (int)$planning['id'] : null,
                'sequence_no'       => $seqNo,
                'kode_asset'        => $a['kode_asset'],
                'nama_asset'        => $a['nama_asset'],
                'jenis'             => $a['jenis_asset'] ?: 'Gardu',
                'status'            => $a['status'],
                'inspection_status' => $inspStatus,
                'lat'               => $lat,
                'lng'               => $lng,
                'shape'             => $spec['shape'],
                'icon_class'        => $spec['icon_class'],
                'shape_label'       => $spec['label'],
                'color'             => $accentColor,
            ];
        }

        return [
            'status'          => 'success',
            'planning'        => $planning ? [
                'id'             => (int)$planning['id'],
                'nomor_planning' => $planning['nomor_planning'],
                'title'          => $planning['title'],
                'status'         => $planning['status'],
            ] : null,
            'total_planned'   => $totalPlanned,
            'inspected_count' => $inspectedCount,
            'count'           => count($markers),
            'markers'         => $markers
        ];
    }
}
