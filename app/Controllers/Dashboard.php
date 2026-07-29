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

        if ($showMobile) {
            return view('dashboard/mobile', [
                'userName'          => $session->get('user_name') ?: 'inspeksi',
                'userRole'          => $role,
                'canInput'          => $canInput,
                'canEdit'           => $canEdit,
                'canDelete'         => $canDelete,
                'canApprove'        => $canApprove,
                'canMonitoring'     => $canMonitoring,
                'topInputOfficers'  => $topInputOfficers,
                'topUpdateOfficers' => $topUpdateOfficers,
                'monthFilter'        => $monthFilter,
                'yearFilter'         => $yearFilter
            ]);
        }

        // Ambil Data Statistik Card
        $stats = $this->temuanRepository->getDashboardStats($ulpIdFilter, $role);

        // Ambil Data Grafik (Chart.js)
        $monthlyData = $this->temuanRepository->getMonthlyStats($ulpIdFilter);
        $ulpData = $this->temuanRepository->getUlpStats($ulpIdFilter);
        $penyulangData = $this->temuanRepository->getPenyulangStats($ulpIdFilter);
        $pelaksanaData = $this->temuanRepository->getPelaksanaStats($ulpIdFilter);
        $prioritasData = $this->temuanRepository->getPrioritasStats($ulpIdFilter);
        $potensiGangguanData = $this->temuanRepository->getPotensiGangguanStats($ulpIdFilter);

        // Ambil data pin peta (GIS)
        $mapPins = $this->temuanRepository->getMapPins($ulpIdFilter);

        return view('dashboard/index', [
            'userRole'            => $role,
            'canInput'            => $canInput,
            'canEdit'             => $canEdit,
            'canDelete'           => $canDelete,
            'canApprove'          => $canApprove,
            'canMonitoring'       => $canMonitoring,
            'stats'               => $stats,
            'monthlyData'         => $monthlyData,
            'ulpData'             => $ulpData,
            'penyulangData'       => $penyulangData,
            'pelaksanaData'       => $pelaksanaData,
            'prioritasData'       => $prioritasData,
            'potensiGangguanData' => $potensiGangguanData,
            'mapPins'             => $mapPins,
            'topInputOfficers'    => $topInputOfficers,
            'topUpdateOfficers'   => $topUpdateOfficers,
            'monthFilter'         => $monthFilter,
            'yearFilter'          => $yearFilter
        ]);
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
