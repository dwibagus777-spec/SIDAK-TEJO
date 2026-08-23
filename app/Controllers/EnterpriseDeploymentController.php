<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\EnvironmentControlService;
use App\Services\ReleaseManifestService;
use App\Services\ProductionReadinessService;
use App\Services\DeploymentOrchestrationService;
use App\Services\ReleaseRollbackService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseDeploymentController extends BaseController
{
    protected EnvironmentControlService $envService;
    protected ReleaseManifestService $manifestService;
    protected ProductionReadinessService $readinessService;
    protected DeploymentOrchestrationService $deployService;
    protected ReleaseRollbackService $rollbackService;

    public function __construct()
    {
        $this->envService      = new EnvironmentControlService();
        $this->manifestService = new ReleaseManifestService();
        $this->readinessService= new ProductionReadinessService();
        $this->deployService   = new DeploymentOrchestrationService();
        $this->rollbackService = new ReleaseRollbackService();
    }

    /**
     * GET /deployment/release-status
     * Enterprise Production Deployment & Release View (Phase 6A)
     */
    public function index()
    {
        $envContext = $this->envService->getEnvironmentContext();
        $manifest   = $this->manifestService->createReleaseManifest('v3.0.0-PROD');
        $readiness  = $this->readinessService->evaluateProductionReadiness();

        return view('enterprise_deployment/index', [
            'title'      => 'SIDAK TEJO v3.0.0 — Enterprise Production Deployment & Environment Control',
            'envContext' => $envContext['environment_context'] ?? [],
            'manifest'   => $manifest['release_manifest'] ?? [],
            'readiness'  => $readiness['readiness_checks'] ?? [],
        ]);
    }

    /**
     * POST /deployment/manifest/create
     * Create Release Manifest API (Phase 6A)
     */
    public function createManifest(): ResponseInterface
    {
        $json    = $this->request->getJSON(true) ?? [];
        $version = $json['version'] ?? 'v3.0.0-PROD';

        $result = $this->manifestService->createReleaseManifest($version);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /deployment/readiness-check
     * Evaluate Production Readiness API (Phase 6A)
     */
    public function readinessCheck(): ResponseInterface
    {
        $result = $this->readinessService->evaluateProductionReadiness();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /deployment/execute
     * Execute Deployment Orchestration API (Phase 6A)
     */
    public function executeDeployment(): ResponseInterface
    {
        $json        = $this->request->getJSON(true) ?? [];
        $releaseCode = $json['release_code'] ?? 'RELEASE-STJ-v3.0.0-PROD-20260822';

        $result = $this->deployService->executeDeploymentOrchestration($releaseCode);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
