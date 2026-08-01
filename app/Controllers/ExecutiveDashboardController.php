<?php

namespace App\Controllers;

use App\Repositories\DashboardRepository;
use App\Models\UlpModel;
use App\Models\TemuanModel;

class ExecutiveDashboardController extends BaseController
{
    private DashboardRepository $dashboardRepository;
    private UlpModel $ulpModel;
    private TemuanModel $temuanModel;

    public function __construct()
    {
        $this->dashboardRepository = new DashboardRepository();
        $this->ulpModel           = new UlpModel();
        $this->temuanModel        = new TemuanModel();
    }

    /**
     * Executive Dashboard View
     */
    public function index()
    {
        $session = session();
        $role    = strtolower((string)$session->get('user_role'));
        $ulpId   = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpId)) {
            $ulpIdFilter = (int)$ulpId;
        }

        $bulanSel = $this->request->getGet('bulan') ? (int)$this->request->getGet('bulan') : (int)date('n');
        $tahunSel = $this->request->getGet('tahun') ? (int)$this->request->getGet('tahun') : (int)date('Y');
        $ulpSel   = $this->request->getGet('ulp_id') ? (int)$this->request->getGet('ulp_id') : $ulpIdFilter;

        $ulps = $this->ulpModel->where('status', 'AKTIF')->findAll();

        $kpi       = $this->dashboardRepository->getDashboardKpi($ulpSel, $bulanSel, $tahunSel);
        $lineData  = $this->dashboardRepository->getLineChartData($ulpSel, $bulanSel, $tahunSel);
        $ulpBar    = $this->dashboardRepository->getUlpBarChartData($bulanSel, $tahunSel);
        $statusDonut = $this->dashboardRepository->getStatusDonutData($ulpSel, $bulanSel, $tahunSel);
        $jenisPie  = $this->dashboardRepository->getJenisPieData($ulpSel, $bulanSel, $tahunSel);
        $prioPie   = $this->dashboardRepository->getPrioritasPieData($ulpSel, $bulanSel, $tahunSel);

        return view('dashboard/executive_enterprise', [
            'title'        => 'Executive Dashboard Enterprise - SIDAK TEJO',
            'userRole'     => $role,
            'userName'     => session()->get('user_name') ?: 'Executive',
            'ulps'         => $ulps,
            'bulanSel'     => $bulanSel,
            'tahunSel'     => $tahunSel,
            'ulpSel'       => $ulpSel,
            'kpi'          => $kpi,
            'lineData'     => $lineData,
            'ulpBar'       => $ulpBar,
            'statusDonut'  => $statusDonut,
            'jenisPie'     => $jenisPie,
            'prioPie'      => $prioPie,
        ]);
    }

    /**
     * Realtime AJAX Endpoint for Global Dashboard Filter Refresh
     */
    public function getChartData(): \CodeIgniter\HTTP\ResponseInterface
    {
        $session = session();
        $role    = strtolower((string)$session->get('user_role'));
        $userUlp = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlp)) {
            $ulpIdFilter = (int)$userUlp;
        }

        $ulpParam   = $this->request->getGet('ulp_id');
        $ulpId      = $ulpParam !== null && $ulpParam !== '' ? (int)$ulpParam : $ulpIdFilter;
        $bulan      = $this->request->getGet('bulan') !== null && $this->request->getGet('bulan') !== '' ? (int)$this->request->getGet('bulan') : (int)date('n');
        $tahun      = $this->request->getGet('tahun') !== null && $this->request->getGet('tahun') !== '' ? (int)$this->request->getGet('tahun') : (int)date('Y');

        $kpi         = $this->dashboardRepository->getDashboardKpi($ulpId, $bulan, $tahun);
        $lineData    = $this->dashboardRepository->getLineChartData($ulpId, $bulan, $tahun);
        $ulpBar      = $this->dashboardRepository->getUlpBarChartData($bulan, $tahun);
        $statusDonut = $this->dashboardRepository->getStatusDonutData($ulpId, $bulan, $tahun);
        $jenisPie    = $this->dashboardRepository->getJenisPieData($ulpId, $bulan, $tahun);
        $prioPie     = $this->dashboardRepository->getPrioritasPieData($ulpId, $bulan, $tahun);

        return $this->response->setStatusCode(200)->setJSON([
            'success'     => true,
            'timestamp'   => date('Y-m-d H:i:s'),
            'filters'     => [
                'ulp_id' => $ulpId,
                'bulan'  => $bulan,
                'tahun'  => $tahun,
            ],
            'kpi'         => $kpi,
            'line_chart'  => $lineData,
            'ulp_bar'     => $ulpBar,
            'status_donut'=> $statusDonut,
            'jenis_pie'   => $jenisPie,
            'prio_pie'    => $prioPie,
        ]);
    }

    /**
     * Dashboard Summary JSON
     */
    public function getSummary(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->getChartData();
    }

    /**
     * Dashboard KPI JSON
     */
    public function getKpiData(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->getChartData();
    }
}
