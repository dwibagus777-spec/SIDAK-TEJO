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

        // 5. Construct Power Grid Topology Polylines (GI -> Penyulang -> Section -> LBS -> Recloser -> Trafo -> Tiang)
        $gridTopology = [
            'nodes' => [
                ['id' => 'GI-SDJ', 'name' => 'GI Sidoarjo', 'type' => 'GI', 'lat' => -7.4450, 'lng' => 112.7150, 'status' => 'NORMAL', 'color' => '#10b981'],
                ['id' => 'PYL-KLR', 'name' => 'Penyulang Klurak', 'type' => 'PENYULANG', 'lat' => -7.4478, 'lng' => 112.7183, 'status' => 'WARNING', 'color' => '#f59e0b'],
                ['id' => 'SEC-SDJ01', 'name' => 'Section SDJ-01', 'type' => 'SECTION', 'lat' => -7.4520, 'lng' => 112.7210, 'status' => 'NORMAL', 'color' => '#10b981'],
                ['id' => 'LBS-KLR', 'name' => 'LBS Klurak', 'type' => 'LBS', 'lat' => -7.4550, 'lng' => 112.7240, 'status' => 'EMERGENCY', 'color' => '#ef4444'],
                ['id' => 'TRF-SDJ14', 'name' => 'Trafo SDJ-14', 'type' => 'TRAFO', 'lat' => -7.4580, 'lng' => 112.7270, 'status' => 'NORMAL', 'color' => '#10b981'],
                ['id' => 'TNG-T102', 'name' => 'Tiang T-102', 'type' => 'TIANG', 'lat' => -7.4610, 'lng' => 112.7300, 'status' => 'NORMAL', 'color' => '#10b981'],
            ],
            'lines' => [
                ['from' => [-7.4450, 112.7150], 'to' => [-7.4478, 112.7183], 'status' => 'NORMAL', 'color' => '#10b981', 'label' => 'Feeder 20KV GI Sidoarjo'],
                ['from' => [-7.4478, 112.7183], 'to' => [-7.4520, 112.7210], 'status' => 'WARNING', 'color' => '#f59e0b', 'label' => 'Section Main SDJ-01'],
                ['from' => [-7.4520, 112.7210], 'to' => [-7.4550, 112.7240], 'status' => 'EMERGENCY', 'color' => '#ef4444', 'label' => 'Jalur LBS Klurak'],
                ['from' => [-7.4550, 112.7240], 'to' => [-7.4580, 112.7270], 'status' => 'NORMAL', 'color' => '#10b981', 'label' => 'Jalur Trafo SDJ-14'],
                ['from' => [-7.4580, 112.7270], 'to' => [-7.4610, 112.7300], 'status' => 'NORMAL', 'color' => '#10b981', 'label' => 'Jalur Tiang T-102'],
            ]
        ];

        // 6. Live Officer Positions
        $liveOfficers = [
            ['id' => 1, 'nama' => 'Dwi Bagus Arianto', 'role' => 'Administrator', 'status' => 'Bekerja', 'lat' => -7.4485, 'lng' => 112.7190, 'icon' => 'user-shield'],
            ['id' => 2, 'nama' => 'Tim PDKB UP3', 'role' => 'PDKB Specialist', 'status' => 'Inspeksi Hotline', 'lat' => -7.4530, 'lng' => 112.7225, 'icon' => 'bolt'],
            ['id' => 3, 'nama' => 'Tim HAR Gardu', 'role' => 'HAR Gardu', 'status' => 'Update Eviden', 'lat' => -7.4560, 'lng' => 112.7255, 'icon' => 'screwdriver-wrench'],
        ];

        // 7. Outage Impact Estimation
        $outageImpact = [
            ['affected_penyulang' => 'Penyulang Klurak', 'affected_sections' => 2, 'affected_trafos' => 8, 'est_customers' => 1250, 'risk' => 'HIGH'],
            ['affected_penyulang' => 'Penyulang Krian 04', 'affected_sections' => 1, 'affected_trafos' => 5, 'est_customers' => 840, 'risk' => 'CRITICAL'],
        ];

        return $this->response->setStatusCode(200)->setJSON([
            'success'      => true,
            'timestamp'    => date('Y-m-d H:i:s'),
            'temuanPins'   => $temuanPins,
            'assetPins'    => $assetPins,
            'woPins'       => $workOrders,
            'heatmapData'  => $heatmapData,
            'gridTopology' => $gridTopology,
            'liveOfficers' => $liveOfficers,
            'outageImpact' => $outageImpact,
            'stats'        => [
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
