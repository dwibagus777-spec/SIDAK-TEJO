<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\EvChargingGridImpactService;
use App\Services\DemandSideFlexibilityAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseEvGridResilienceController extends BaseController
{
    protected EvChargingGridImpactService $evImpactService;
    protected DemandSideFlexibilityAdvisoryService $flexibilityService;

    public function __construct()
    {
        $this->evImpactService    = new EvChargingGridImpactService();
        $this->flexibilityService = new DemandSideFlexibilityAdvisoryService();
    }

    /**
     * GET /ev-grid-resilience/control-center
     * Enterprise EV Grid Resilience Control View (Phase 7P)
     */
    public function index()
    {
        $evRes   = $this->evImpactService->assessEvGridImpact(1);
        $flexRes = $this->flexibilityService->recommendDemandFlexibility(1);

        return view('enterprise_ev_grid_resilience/index', [
            'title'               => 'SIDAK TEJO v3.0.0 — Enterprise EV Charging & Demand-Side Intelligence Center',
            'evGridImpact'        => $evRes['ev_grid_impact'] ?? [],
            'flexibilityAdvisory' => $flexRes['flexibility_advisory'] ?? [],
        ]);
    }

    /**
     * GET /ev-grid-resilience/ev-impact-snapshot
     * EV Charging Demand Impact Snapshot API (Phase 7P)
     */
    public function evImpactSnapshot(): ResponseInterface
    {
        $result = $this->evImpactService->assessEvGridImpact(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /ev-grid-resilience/flexibility-advisory
     * Demand Flexibility Advisory API (Phase 7P)
     */
    public function flexibilityAdvisory(): ResponseInterface
    {
        $result = $this->flexibilityService->recommendDemandFlexibility(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
