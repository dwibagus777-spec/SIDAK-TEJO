<?php

namespace App\Controllers;

use App\Services\ExecutiveReliabilityCommandService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Executive Command Center & Material Readiness Controller (CC-06 Phase 2)
 */
class ExecutiveReliabilityController extends BaseController
{
    protected ExecutiveReliabilityCommandService $commandService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->commandService = new ExecutiveReliabilityCommandService();
    }

    /**
     * Render Executive Command Center Dashboard.
     */
    public function index()
    {
        $summary = $this->commandService->getExecutiveSummary();

        return view('executive/command_center', [
            'title'   => 'Executive Command Center: Grid Reliability & Material Readiness | SIDAK TEJO',
            'summary' => $summary,
        ]);
    }

    /**
     * API: Executive Overview Summary.
     * GET /api/executive/summary
     */
    public function apiSummary(): ResponseInterface
    {
        return $this->response->setJSON($this->commandService->getExecutiveSummary());
    }

    /**
     * API: Feeder GIRI List.
     * GET /api/executive/giri-feeders
     */
    public function apiGiriFeeders(): ResponseInterface
    {
        return $this->response->setJSON($this->commandService->getFeederGiriList());
    }

    /**
     * API: Asset Degradation Radar.
     * GET /api/executive/asset-radar
     */
    public function apiAssetRadar(): ResponseInterface
    {
        return $this->response->setJSON($this->commandService->getAssetDegradationRadar());
    }

    /**
     * API: Material Readiness Gap Analysis.
     * GET /api/executive/material-gap
     */
    public function apiMaterialGap(): ResponseInterface
    {
        return $this->response->setJSON($this->commandService->getMaterialReadinessGap());
    }

    /**
     * API: Generate Predictive Budget Recommendation.
     * POST /api/executive/budget-estimation
     */
    public function apiBudgetEstimation(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $actor = $json['actor'] ?? [
            'actor_id'   => 99,
            'actor_name' => 'EXECUTIVE_MANAJER_UP3',
            'actor_nip'  => '197908122003121001',
            'actor_role' => 'SENIOR_MANAGER_UP3',
        ];

        $result = $this->commandService->generateExecutiveBudgetRecommendation($actor);
        return $this->response->setJSON($result);
    }
}
