<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\OperationalPlanningPortfolioService;
use App\Services\OperationalPlanningWorkspaceService;

/**
 * Operational Planning Portfolio Controller (Wave 2 Phase OP-03)
 *
 * Responsibilities:
 * - Portfolio Governance & Human Planning Prioritization UI & API.
 * - Invariant: PORTFOLIO_RATIFIED != WORK_ORDER, ZERO_AUTO_EXECUTION.
 */
class OperationalPlanningPortfolioController extends ResourceController
{
    protected $format = 'json';
    protected OperationalPlanningPortfolioService $portfolioService;
    protected OperationalPlanningWorkspaceService $workspaceService;

    public function __construct()
    {
        $this->portfolioService = new OperationalPlanningPortfolioService();
        $this->workspaceService = new OperationalPlanningWorkspaceService();
    }

    /**
     * GET /operational-planning/portfolios
     * Portfolio Dashboard & Ready Plans Queue
     */
    public function index()
    {
        $portfolios = $this->portfolioService->getPortfolios();
        $unassignedPlans = $this->portfolioService->getUnassignedApprovedPlans();

        $data = [
            'title'           => 'Portfolio Governance & Human Planning Prioritization',
            'portfolios'      => $portfolios,
            'unassignedPlans' => $unassignedPlans,
        ];

        return view('operational_planning/portfolio_list', $data);
    }

    /**
     * GET /operational-planning/portfolios/create
     */
    public function create()
    {
        $unassignedPlans = $this->portfolioService->getUnassignedApprovedPlans();

        $data = [
            'title'           => 'Rakit Portofolio Perencanaan Baru',
            'unassignedPlans' => $unassignedPlans,
            'currentYear'     => (int)date('Y'),
            'currentWeek'     => (int)date('W'),
        ];

        return view('operational_planning/portfolio_form', $data);
    }

    /**
     * POST /operational-planning/portfolios/store
     */
    public function store()
    {
        $title   = (string)($this->request->getPost('portfolio_title') ?? 'Portofolio Mitigasi Keandalan');
        $year    = (int)($this->request->getPost('period_year') ?? date('Y'));
        $week    = (int)($this->request->getPost('period_week') ?? date('W'));
        $planIds = $this->request->getPost('plan_ids') ?? [];

        if (!is_array($planIds) || empty($planIds)) {
            session()->setFlashdata('error', 'Pilih minimal satu rencana kerja yang disetujui untuk merakit portofolio.');
            return redirect()->to(base_url('operational-planning/portfolios/create'));
        }

        $result = $this->portfolioService->assemblePortfolio($title, $year, $week, array_map('intval', $planIds));

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Portofolio {$result['portfolio_code']} berhasil dirakit dengan {$result['total_plans']} rencana kerja.");
            return redirect()->to(base_url('operational-planning/portfolios/detail/' . $result['portfolio_id']));
        }

        session()->setFlashdata('error', "Gagal merakit portofolio: " . ($result['message'] ?? 'Data tidak valid.'));
        return redirect()->to(base_url('operational-planning/portfolios'));
    }

    /**
     * GET /operational-planning/portfolios/detail/(:num)
     */
    public function detail($portfolioId = null)
    {
        $pId = (int)($portfolioId ?? 1);
        $data = $this->portfolioService->getPortfolioDetail($pId);

        if (empty($data['portfolio'])) {
            session()->setFlashdata('error', "Portofolio #{$pId} tidak ditemukan.");
            return redirect()->to(base_url('operational-planning/portfolios'));
        }

        $data['title'] = "Portfolio Detail — {$data['portfolio']['portfolio_code']}";

        return view('operational_planning/portfolio_detail', $data);
    }

    /**
     * POST /operational-planning/portfolios/set-item-tier/(:num)
     */
    public function setItemTier($itemId = null)
    {
        $itId      = (int)($itemId ?? 1);
        $tier      = (string)($this->request->getPost('priority_tier') ?? '');
        $rationale = (string)($this->request->getPost('priority_rationale') ?? '');
        $portId    = (int)($this->request->getPost('portfolio_id') ?? 1);

        $result = $this->portfolioService->assignItemPriorityTier($itId, $tier, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Prioritas tier untuk {$result['plan_code']} berhasil ditetapkan: {$tier}");
        } else {
            session()->setFlashdata('error', "Gagal menetapkan tier: " . ($result['message'] ?? 'Alasan tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/portfolios/detail/' . $portId));
    }

    /**
     * POST /operational-planning/portfolios/transition/(:num)
     */
    public function transition($portfolioId = null)
    {
        $pId       = (int)($portfolioId ?? 1);
        $toStatus  = (string)($this->request->getPost('to_status') ?? '');
        $rationale = (string)($this->request->getPost('ratification_rationale') ?? '');

        $result = $this->portfolioService->transitionPortfolioStatus($pId, $toStatus, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Status portofolio berhasil diubah menjadi: {$toStatus}");
        } else {
            session()->setFlashdata('error', "Gagal mengubah status: " . ($result['message'] ?? 'Kelengkapan belum terpenuhi.'));
        }

        return redirect()->to(base_url('operational-planning/portfolios/detail/' . $pId));
    }
}
