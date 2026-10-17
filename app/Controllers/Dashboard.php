<?php

namespace App\Controllers;

use App\Repositories\TemuanRepository;
use App\Models\TemuanModel;

class Dashboard extends BaseController
{
    private TemuanRepository $temuanRepository;
    private TemuanModel $temuanModel;

    public function __construct()
    {
        $this->temuanRepository = new TemuanRepository();
        $this->temuanModel = new TemuanModel();
    }

    public function index()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));

        $ulpId = $session->get('user_ulp_id');

        // Check if user forced a specific view mode ('mobile' or 'desktop')
        $viewMode = $session->get('view_mode') ?: ($_COOKIE['view_mode'] ?? null);

        $agent = $this->request->getUserAgent();
        $isMobile = $agent->isMobile();

        // Determine whether to show the mobile layout
        $showMobile = false;
        if ($viewMode === 'mobile') {
            $showMobile = true;
        } elseif ($viewMode === 'desktop') {
            $showMobile = false;
        } else {
            // Auto detect
            $showMobile = $isMobile;
        }

        // Role-based data scoping
        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpId)) {
            $ulpIdFilter = (int)$ulpId;
        }

        // Admin/supervisor can override via ?ulp_id= URL param
        $urlUlpId = $this->request->getGet('ulp_id');
        if ($urlUlpId && in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'])) {
            $ulpIdFilter = (int)$urlUlpId;
        }

        // Month & Year Filter for Top 10 Leaderboard & Stats
        $monthFilter = $this->request->getGet('month') ? (int)$this->request->getGet('month') : date('n');
        $yearFilter = $this->request->getGet('year') ? (int)$this->request->getGet('year') : date('Y');

        // Ambil Top 10 Leaderboard
        $topInputOfficers = $this->temuanModel->getTopInputOfficers($monthFilter, $yearFilter, $ulpIdFilter);
        $topUpdateOfficers = $this->temuanModel->getTopUpdateOfficers($monthFilter, $yearFilter, $ulpIdFilter);

        // Permission Flags
        $canInput = check_role(['administrator', 'admin_ulp', 'inspeksi']);
        $canEdit = check_role(['administrator', 'admin_ulp', 'inspeksi', 'yantek', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane']);
        $canDelete = check_role(['administrator']);
        $canApprove = check_role(['administrator', 'supervisor_ulp', 'supervisor_up3']);
        $canMonitoring = check_role(['administrator', 'admin_pusat', 'supervisor_up3', 'manager']);

        // Fetch All Dashboard Analytics Data (System-scoped or ULP-scoped, preventing empty role stats)
        $stats               = $this->temuanRepository->getDashboardStats($ulpIdFilter, null);
        $monthlyData         = $this->temuanRepository->getMonthlyStats($ulpIdFilter);
        $ulpData             = $this->temuanRepository->getUlpStats($ulpIdFilter);
        $penyulangData       = $this->temuanRepository->getPenyulangStats($ulpIdFilter);
        $pelaksanaData       = $this->temuanRepository->getPelaksanaStats($ulpIdFilter);
        $prioritasData       = $this->temuanRepository->getPrioritasStats($ulpIdFilter);
        $potensiGangguanData = $this->temuanRepository->getPotensiGangguanStats($ulpIdFilter);
        $mapPins             = $this->temuanRepository->getMapPins($ulpIdFilter);

        // Distribution Asset Health Monitoring Calculations
        $db = \Config\Database::connect();
        $feederQuery = $db->table('penyulang p')
            ->select("p.id, p.nama_penyulang, u.nama_ulp,
                      COUNT(t.id) as total_temuan,
                      SUM(CASE WHEN t.prioritas = 'EMERGENCY' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as emg,
                      SUM(CASE WHEN t.prioritas = 'HIGH' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as high,
                      SUM(CASE WHEN t.prioritas = 'MEDIUM' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as med,
                      SUM(CASE WHEN t.status != 'SELESAI' THEN 1 ELSE 0 END) as belum_selesai")
            ->join('ulps u', 'p.ulp_id = u.id', 'left')
            ->join('temuan t', 't.penyulang_id = p.id AND t.deleted_at IS NULL', 'left');

        if ($ulpIdFilter !== null) {
            $feederQuery->where('p.ulp_id', $ulpIdFilter);
        }

        $allFeeders = $feederQuery->groupBy('p.id, p.nama_penyulang, u.nama_ulp')->get()->getResultArray();

        $sehatCount = 0;
        $kronisCount = 0;
        $sakitCount = 0;
        $kritisCount = 0;
        $totalHealthScore = 0;
        $rankedFeeders = [];

        foreach ($allFeeders as &$f) {
            $f['total_temuan'] = (int)($f['total_temuan'] ?? 0);
            $f['emg'] = (int)($f['emg'] ?? 0);
            $f['high'] = (int)($f['high'] ?? 0);
            $f['med'] = (int)($f['med'] ?? 0);
            $f['belum_selesai'] = (int)($f['belum_selesai'] ?? 0);

            $penalty = ($f['emg'] * 15) + ($f['high'] * 5) + ($f['med'] * 2);
            $score = max(20, min(100, 100 - $penalty));
            $f['health_score'] = $score;
            $totalHealthScore += $score;

            if ($score > 80) {
                $f['status'] = 'SEHAT';
                $sehatCount++;
            } elseif ($score >= 60) {
                $f['status'] = 'KRONIS';
                $kronisCount++;
            } elseif ($score >= 40) {
                $f['status'] = 'SAKIT';
                $sakitCount++;
            } else {
                $f['status'] = 'KRITIS';
                $kritisCount++;
            }
            $rankedFeeders[] = $f;
        }
        unset($f);

        $totalFeeders = count($allFeeders);
        $avgHealthIndex = $totalFeeders > 0 ? (int)round($totalHealthScore / $totalFeeders) : 89;

        // Sort by total findings DESC for Top 10
        usort($rankedFeeders, fn($a, $b) => $b['total_temuan'] <=> $a['total_temuan']);
        $top10Feeders = array_slice($rankedFeeders, 0, 10);

        // Finding types breakdown
        $jenisQuery = $db->table('temuan')
            ->select('jenis_temuan, COUNT(*) as cnt')
            ->where('deleted_at IS NULL');
        if ($ulpIdFilter !== null) {
            $jenisQuery->where('ulp_id', $ulpIdFilter);
        }
        $jenisTemuanStats = $jenisQuery->groupBy('jenis_temuan')->orderBy('cnt', 'DESC')->limit(6)->get()->getResultArray();

        // Recent inspections / activities
        $recentActQuery = $db->table('temuan t')
            ->select('t.id, t.nomor_temuan, t.jenis_temuan, t.prioritas, t.status, t.pelaksana, t.created_at, t.detail_temuan, u.nama_ulp, p.nama_penyulang')
            ->join('ulps u', 't.ulp_id = u.id', 'left')
            ->join('penyulang p', 't.penyulang_id = p.id', 'left')
            ->where('t.deleted_at IS NULL')
            ->orderBy('t.id', 'DESC')
            ->limit(5);
        if ($ulpIdFilter !== null) {
            $recentActQuery->where('t.ulp_id', $ulpIdFilter);
        }
        $recentActivities = $recentActQuery->get()->getResultArray();

        // ULP List for Filter Dropdown
        $ulpsList = $db->table('ulps')->select('id, nama_ulp')->orderBy('nama_ulp', 'ASC')->get()->getResultArray();

        $assetHealthData = [
            'total_penyulang'  => $totalFeeders,
            'avg_health_index' => $avgHealthIndex,
            'sehat_count'      => $sehatCount,
            'sehat_pct'        => $totalFeeders > 0 ? round(($sehatCount / $totalFeeders) * 100) : 55,
            'kronis_count'     => $kronisCount,
            'kronis_pct'       => $totalFeeders > 0 ? round(($kronisCount / $totalFeeders) * 100) : 10,
            'sakit_count'      => $sakitCount,
            'sakit_pct'        => $totalFeeders > 0 ? round(($sakitCount / $totalFeeders) * 100) : 6,
            'kritis_count'     => $kritisCount,
            'kritis_pct'       => $totalFeeders > 0 ? round(($kritisCount / $totalFeeders) * 100) : 2,
            'top10'            => $top10Feeders,
            'jenis_temuan'     => $jenisTemuanStats,
            'recent'           => $recentActivities,
        ];

        // Ambil Data Work Order & Asset Stats (Phase 31.1 Mission Control)
        $woRepo = new \App\Repositories\WorkOrderRepository();
        $assetRepo = new \App\Repositories\AssetRepository();
        $woStats = $woRepo->getWOStats($ulpIdFilter);
        $assetStats = $assetRepo->getAssetStats($ulpIdFilter);

        // Phase 2: Penyulang Emergency Ranking
        $penyulangRanking = $this->temuanRepository->getPenyulangEmergencyRanking($ulpIdFilter, 5);

        // Sidebar collapse preference: default TRUE (collapsed) unless user explicitly expanded
        $prefersCollapsed = $session->get('sidebar_collapsed') ?? true;

        $viewParams = [
            'userName'            => $session->get('user_name') ?: 'Petugas',
            'userRole'            => $role,
            'canInput'            => $canInput,
            'canEdit'             => $canEdit,
            'canDelete'           => $canDelete,
            'canApprove'          => $canApprove,
            'canMonitoring'       => $canMonitoring,
            'stats'               => $stats,
            'assetHealthData'     => $assetHealthData,
            'ulpsList'            => $ulpsList,
            'selectedUlpId'       => $ulpIdFilter,
            'woStats'             => $woStats,
            'assetStats'          => $assetStats,
            'monthlyData'         => $monthlyData,
            'ulpData'             => $ulpData,
            'penyulangData'       => $penyulangData,
            'penyulangRanking'    => $penyulangRanking,
            'pelaksanaData'       => $pelaksanaData,
            'prioritasData'       => $prioritasData,
            'potensiGangguanData' => $potensiGangguanData,
            'mapPins'             => $mapPins,
            'topInputOfficers'    => $topInputOfficers,
            'topUpdateOfficers'   => $topUpdateOfficers,
            'monthFilter'         => $monthFilter,
            'yearFilter'          => $yearFilter,
            'prefersCollapsed'    => $prefersCollapsed
        ];

        if ($showMobile) {
            return view('dashboard/mobile', $viewParams);
        }

        return view('dashboard/index', $viewParams);
    }

    /**
     * AJAX endpoint to save sidebar collapse preference to user session
     */
    public function sidebarPreference()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid request']);
        }

        $collapsed = (bool)$this->request->getPost('collapsed');
        $session = session();
        $session->set('sidebar_collapsed', $collapsed);

        return $this->response->setStatusCode(200)->setJSON(['success' => true, 'collapsed' => $collapsed]);
    }

    public function toggleView()
    {
        $session = session();
        $agent = $this->request->getUserAgent();
        $isMobile = $agent->isMobile();
        $currentMode = $session->get('view_mode') ?: ($_COOKIE['view_mode'] ?? null);

        if ($currentMode === 'desktop') {
            $newMode = 'mobile';
        } elseif ($currentMode === 'mobile') {
            $newMode = 'desktop';
        } else {
            $newMode = $isMobile ? 'desktop' : 'mobile';
        }

        $session->set('view_mode', $newMode);
        setcookie('view_mode', $newMode, time() + (86400 * 30), '/');

        return redirect()->to(site_url('dashboard'));
    }

    /**
     * Real-time AJAX endpoint for role-scoped dashboard charts & analytics
     */
    public function analyticsData()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $ulpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpId)) {
            $ulpIdFilter = (int)$ulpId;
        }

        $analytics = $this->temuanRepository->getComprehensiveAnalytics($role, $ulpIdFilter);

        return $this->response->setStatusCode(200)->setJSON([
            'success'   => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'data'      => $analytics
        ]);
    }

    /**
     * Phase 14 Executive Dashboard View
     */
    public function executive()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $ulpId = $session->get('user_ulp_id');

        $ulpModel = new \App\Models\UlpModel();
        $penyulangModel = new \App\Models\PenyulangModel();

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpId)) {
            $ulpIdFilter = (int)$ulpId;
        }

        $initialData = $this->temuanRepository->getExecutiveAnalyticsData([], $role, $ulpIdFilter);

        return view('dashboard/executive', [
            'initialData' => $initialData,
            'ulps'        => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs'  => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'userRole'    => $role,
            'userName'    => session()->get('user_name') ?: 'User'
        ]);
    }

    /**
     * Real-time AJAX endpoint for Executive Dashboard filters & 30s auto-refresh
     */
    public function executiveApi()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $ulpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpId)) {
            $ulpIdFilter = (int)$ulpId;
        }

        $filters = [
            'ulp_id'         => $this->request->getGet('ulp_id'),
            'penyulang_id'   => $this->request->getGet('penyulang_id'),
            'section_id'     => $this->request->getGet('section_id'),
            'jenis_temuan'   => $this->request->getGet('jenis_temuan'),
            'pelaksana'      => $this->request->getGet('pelaksana'),
            'prioritas'      => $this->request->getGet('prioritas'),
            'status'         => $this->request->getGet('status'),
            'tanggal_mulai'  => $this->request->getGet('tanggal_mulai'),
            'tanggal_selesai'=> $this->request->getGet('tanggal_selesai'),
        ];

        $analytics = $this->temuanRepository->getExecutiveAnalyticsData($filters, $role, $ulpIdFilter);

        return $this->response->setStatusCode(200)->setJSON([
            'success'   => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'data'      => $analytics
        ]);
    }
}
