<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\VirtualGridStressSimulationService;
use App\Services\PreventiveGridMitigationAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseGridStressSimulationController extends BaseController
{
    protected VirtualGridStressSimulationService $simulationService;
    protected PreventiveGridMitigationAdvisoryService $mitigationService;

    public function __construct()
    {
        $this->simulationService = new VirtualGridStressSimulationService();
        $this->mitigationService = new PreventiveGridMitigationAdvisoryService();
    }

    /**
     * GET /grid-stress-simulation/control-center
     * Enterprise Virtual Grid Stress Testing Control View (Phase 7R)
     */
    public function index()
    {
        $simRes  = $this->simulationService->runGridStressSimulation(1);
        $mitRes  = $this->mitigationService->recommendPreventiveMitigation(1);

        return view('enterprise_grid_stress_simulation/index', [
            'title'                => 'SIDAK TEJO v3.0.0 — Enterprise Virtual Grid Stress Testing Center',
            'gridStressSimulation' => $simRes['grid_stress_simulation'] ?? [],
            'mitigationAdvisory'   => $mitRes['mitigation_advisory'] ?? [],
        ]);
    }

    /**
     * GET /grid-stress-simulation/simulation-snapshot
     * Virtual Grid Stress Simulation Snapshot API (Phase 7R)
     */
    public function simulationSnapshot(): ResponseInterface
    {
        $result = $this->simulationService->runGridStressSimulation(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /grid-stress-simulation/mitigation-advisory
     * Preventive Grid Mitigation Advisory API (Phase 7R)
     */
    public function mitigationAdvisory(): ResponseInterface
    {
        $result = $this->mitigationService->recommendPreventiveMitigation(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
