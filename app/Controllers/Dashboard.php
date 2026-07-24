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

        if ($showMobile) {
            return view('dashboard/mobile', [
                'userName' => $session->get('user_name') ?: 'inspeksi',
                'topInputOfficers' => $topInputOfficers,
                'topUpdateOfficers' => $topUpdateOfficers,
                'monthFilter' => $monthFilter,
                'yearFilter' => $yearFilter
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
            'stats' => $stats,
            'monthlyData' => $monthlyData,
            'ulpData' => $ulpData,
            'penyulangData' => $penyulangData,
            'pelaksanaData' => $pelaksanaData,
            'prioritasData' => $prioritasData,
            'potensiGangguanData' => $potensiGangguanData,
            'mapPins' => $mapPins,
            'topInputOfficers' => $topInputOfficers,
            'topUpdateOfficers' => $topUpdateOfficers,
            'monthFilter' => $monthFilter,
            'yearFilter' => $yearFilter
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
}
