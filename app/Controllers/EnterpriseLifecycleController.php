<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\AssetLifecycleDecisionService;
use App\Services\CapexPrioritizationService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseLifecycleController extends BaseController
{
    protected AssetLifecycleDecisionService $lifecycleService;
    protected CapexPrioritizationService $capexService;

    public function __construct()
    {
        $this->lifecycleService = new AssetLifecycleDecisionService();
        $this->capexService     = new CapexPrioritizationService();
    }

    /**
     * GET /lifecycle/capex-decision
     * Enterprise Asset Lifecycle & CAPEX Decision Control View (Phase 7D)
     */
    public function index()
    {
        $eval  = $this->lifecycleService->evaluateAssetLifecycle(1);
        $capex = $this->capexService->prioritizeCapexPortfolio();

        return view('enterprise_lifecycle/index', [
            'title'     => 'SIDAK TEJO v3.0.0 — Enterprise Asset Lifecycle & CAPEX Decision Center',
            'lifecycle' => $eval['lifecycle_evaluation'] ?? [],
            'capex'     => $capex['capex_prioritization'] ?? [],
        ]);
    }

    /**
     * GET /lifecycle/decision-snapshot
     * Lifecycle Decision Snapshot API (Phase 7D)
     */
    public function decisionSnapshot(): ResponseInterface
    {
        $result = $this->lifecycleService->evaluateAssetLifecycle(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /lifecycle/capex-matrix
     * CAPEX Prioritization Matrix API (Phase 7D)
     */
    public function capexMatrix(): ResponseInterface
    {
        $result = $this->capexService->prioritizeCapexPortfolio();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
