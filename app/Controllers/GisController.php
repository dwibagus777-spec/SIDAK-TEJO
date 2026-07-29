<?php

namespace App\Controllers;

use App\Repositories\TemuanRepository;
use App\Repositories\AssetRepository;
use App\Repositories\WorkOrderRepository;
use App\Models\UlpModel;
use App\Models\PenyulangModel;
use App\Models\SectionModel;

class GisController extends BaseController
{
    private TemuanRepository $temuanRepository;
    private AssetRepository $assetRepository;
    private WorkOrderRepository $woRepository;

    public function __construct()
    {
        $this->temuanRepository = new TemuanRepository();
        $this->assetRepository = new AssetRepository();
        $this->woRepository = new WorkOrderRepository();
    }

    public function index()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpModel = new UlpModel();
        $penyulangModel = new PenyulangModel();
        $sectionModel = new SectionModel();

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }

        return view('gis/index', [
            'ulps'       => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs' => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'sections'   => $sectionModel->findAll(),
            'userRole'   => $role,
            'userName'   => session()->get('user_name') ?: 'User',
            'userUlpId'  => $ulpIdFilter,
        ]);
    }

    /**
     * JSON API Endpoint for GIS Map Layer Data (Temuan, Assets, Work Orders & Heatmap)
     */
    public function apiData()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $filters = [
            'ulp_id'        => $this->request->getGet('ulp_id'),
            'penyulang_id'  => $this->request->getGet('penyulang_id'),
            'section_id'    => $this->request->getGet('section_id'),
            'jenis_temuan'  => $this->request->getGet('jenis_temuan'),
            'prioritas'     => $this->request->getGet('prioritas'),
            'pelaksana'     => $this->request->getGet('pelaksana'),
            'status'        => $this->request->getGet('status'),
            'search'        => $this->request->getGet('search'),
        ];

        // 1. Fetch Temuan Pins
        $db = \Config\Database::connect();
        $builder = $db->table('temuan t');
        $builder->select('t.id, t.nomor_temuan, t.jenis_temuan, t.pelaksana, t.prioritas, t.status, t.detail_temuan, t.latitude, t.longitude, t.created_at, t.foto, u.nama_ulp, p.nama_penyulang, s.nama_section');
        $builder->join('ulps u', 't.ulp_id = u.id', 'left');
        $builder->join('penyulang p', 't.penyulang_id = p.id', 'left');
        $builder->join('sections s', 't.section_id = s.id', 'left');
        $builder->where('t.deleted_at IS NULL');
        $builder->where('t.latitude IS NOT NULL');
        $builder->where('t.longitude IS NOT NULL');
        $builder->where("t.latitude != ''");
        $builder->where("t.longitude != ''");

        if (!empty($ulpIdFilter)) {
            $builder->where('t.ulp_id', $ulpIdFilter);
        }
        if (!empty($filters['ulp_id'])) {
            $builder->where('t.ulp_id', (int)$filters['ulp_id']);
        }
        if (!empty($filters['penyulang_id'])) {
            $builder->where('t.penyulang_id', (int)$filters['penyulang_id']);
        }
        if (!empty($filters['section_id'])) {
            $builder->where('t.section_id', (int)$filters['section_id']);
        }
        if (!empty($filters['jenis_temuan'])) {
            $builder->where('t.jenis_temuan', $filters['jenis_temuan']);
        }
        if (!empty($filters['prioritas'])) {
            $builder->where('t.prioritas', strtoupper($filters['prioritas']));
        }
        if (!empty($filters['pelaksana'])) {
            $builder->where('t.pelaksana', $filters['pelaksana']);
        }
        if (!empty($filters['status'])) {
            $builder->where('t.status', strtoupper($filters['status']));
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $builder->groupStart()
                ->like('t.nomor_temuan', $s)
                ->orLike('t.detail_temuan', $s)
                ->orLike('t.alamat', $s)
            ->groupEnd();
        }

        $temuanPins = $builder->get()->getResultArray();

        // Format dates and photos for GIS popups
        foreach ($temuanPins as &$t) {
            $t['created_at_formatted'] = indo_datetime($t['created_at']);
            $t['foto_url'] = get_photo_url($t['foto']);
        }
        unset($t);

        // 2. Fetch Asset Pins
        $assets = $this->assetRepository->getFilteredAssets($filters, $ulpIdFilter);
        $assetPins = [];
        foreach ($assets as $a) {
            if (!empty($a['latitude']) && !empty($a['longitude'])) {
                $a['foto_url'] = get_photo_url($a['foto']);
                $assetPins[] = $a;
            }
        }

        // 3. Fetch Work Order Pins
        $workOrders = $this->woRepository->getFilteredWorkOrders($filters, $ulpIdFilter);

        // 4. Construct Heatmap Data Points [lat, lng, intensity]
        $heatmapData = [];
        foreach ($temuanPins as $t) {
            $lat = (float)$t['latitude'];
            $lng = (float)$t['longitude'];
            $intensity = match(strtoupper($t['prioritas'])) {
                'EMERGENCY' => 1.0,
                'HIGH'      => 0.7,
                default     => 0.4
            };
            if ($t['status'] === 'SELESAI') $intensity = 0.2;
            $heatmapData[] = [$lat, $lng, $intensity];
        }

        return $this->response->setStatusCode(200)->setJSON([
            'success'     => true,
            'timestamp'   => date('Y-m-d H:i:s'),
            'temuanPins'  => $temuanPins,
            'assetPins'   => $assetPins,
            'woPins'      => $workOrders,
            'heatmapData' => $heatmapData,
            'stats'       => [
                'total_pins'   => count($temuanPins) + count($assetPins),
                'total_temuan' => count($temuanPins),
                'total_assets' => count($assetPins),
                'total_wo'     => count($workOrders),
            ]
        ]);
    }

    /**
     * Geofence Radius Verification Endpoint for Location Check-In
     */
    public function checkin()
    {
        $userLat  = (float)$this->request->getPost('latitude');
        $userLng  = (float)$this->request->getPost('longitude');
        $targetId = (int)$this->request->getPost('target_id');
        $radius   = (int)($this->request->getPost('radius') ?: 100); // 50m, 100m, 250m, 500m

        $db = \Config\Database::connect();
        $target = $db->table('temuan')->where('id', $targetId)->get()->getRowArray();

        if (!$target || empty($target['latitude']) || empty($target['longitude'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Koordinat temuan target tidak ditemukan.'
            ]);
        }

        $targetLat = (float)$target['latitude'];
        $targetLng = (float)$target['longitude'];

        // Haversine Distance Calculation in Meters
        $earthRadius = 6371000; // Earth radius in meters
        $dLat = deg2rad($targetLat - $userLat);
        $dLon = deg2rad($targetLng - $userLng);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($userLat)) * cos(deg2rad($targetLat)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceMeters = round($earthRadius * $c);

        $isWithinRadius = $distanceMeters <= $radius;

        if ($isWithinRadius) {
            log_activity('GEOFENCE_CHECKIN', 'Berhasil check-in di temuan #' . $target['nomor_temuan'] . ' (Jarak: ' . $distanceMeters . 'm)');
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Check-in Berhasil! Anda berada di dalam radius ' . $radius . 'm (' . $distanceMeters . 'm dari lokasi).',
                'distance' => $distanceMeters,
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Check-in Gagal! Anda berada ' . $distanceMeters . 'm dari lokasi temuan (Batas radius: ' . $radius . 'm).',
            'distance' => $distanceMeters,
        ]);
    }
}
