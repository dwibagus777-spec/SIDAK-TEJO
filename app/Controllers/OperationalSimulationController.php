<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OperationalDigitalTwinService;
use App\Services\ScenarioSimulationService;
use App\Services\InterventionImpactSimulationService;
use CodeIgniter\HTTP\ResponseInterface;

class OperationalSimulationController extends BaseController
{
    protected OperationalDigitalTwinService $twinService;
    protected ScenarioSimulationService $simService;
    protected InterventionImpactSimulationService $impactService;

    public function __construct()
    {
        $this->twinService   = new OperationalDigitalTwinService();
        $this->simService    = new ScenarioSimulationService();
        $this->impactService = new InterventionImpactSimulationService();
    }

    /**
     * GET /simulation/digital-twin/(:num)
     * Operational Digital Twin Model API (Phase 3H)
     */
    public function digitalTwin(int $assetId): ResponseInterface
    {
        $twin = $this->twinService->getDigitalTwinState($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $twin,
        ]);
    }

    /**
     * POST /simulation/run-what-if
     * Run What-If Scenario Simulation API (Phase 3H)
     */
    public function runWhatIf(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $assetId      = (int)($json['asset_id'] ?? 1);
        $scenarioType = $json['scenario_type'] ?? 'WHAT_IF_REPLACE_NOW';

        $simulation = $this->simService->runWhatIfSimulation($assetId, $scenarioType);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $simulation,
        ]);
    }

    /**
     * GET /simulation/compare-scenarios/(:num)
     * Comparative Intervention Scenario Analysis API (Phase 3H)
     */
    public function compareScenarios(int $assetId): ResponseInterface
    {
        $comparison = $this->impactService->compareInterventionScenarios($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $comparison,
        ]);
    }
}
