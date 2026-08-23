<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\RiskCapitalAllocationService;
use App\Services\ResilienceInvestmentAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseRiskCapitalController extends BaseController
{
    protected RiskCapitalAllocationService $capitalService;
    protected ResilienceInvestmentAdvisoryService $investmentService;

    public function __construct()
    {
        $this->capitalService    = new RiskCapitalAllocationService();
        $this->investmentService = new ResilienceInvestmentAdvisoryService();
    }

    /**
     * GET /risk-capital/control-center
     * Enterprise Operational Risk Capital Control View (Phase 7S)
     */
    public function index()
    {
        $capRes = $this->capitalService->assessRiskCapitalAllocation(1);
        $invRes = $this->investmentService->recommendResilienceInvestment(1);

        return view('enterprise_risk_capital/index', [
            'title'                 => 'SIDAK TEJO v3.0.0 — Enterprise Operational Risk Capital & Investment Center',
            'riskCapitalAllocation' => $capRes['risk_capital_allocation'] ?? [],
            'investmentAdvisory'    => $invRes['investment_advisory'] ?? [],
        ]);
    }

    /**
     * GET /risk-capital/capital-snapshot
     * Risk Capital Allocation Snapshot API (Phase 7S)
     */
    public function capitalSnapshot(): ResponseInterface
    {
        $result = $this->capitalService->assessRiskCapitalAllocation(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /risk-capital/investment-advisory
     * Resilience Investment Advisory API (Phase 7S)
     */
    public function investmentAdvisory(): ResponseInterface
    {
        $result = $this->investmentService->recommendResilienceInvestment(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
