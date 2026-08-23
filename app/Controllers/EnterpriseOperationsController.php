<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\PostDeploymentVerificationService;
use App\Services\ReleaseHealthService;
use App\Services\ProductionCanaryService;
use App\Services\RegressionDetectionService;
use App\Services\OperationalIncidentService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseOperationsController extends BaseController
{
    protected PostDeploymentVerificationService $postVerifyService;
    protected ReleaseHealthService $releaseHealthService;
    protected ProductionCanaryService $canaryService;
    protected RegressionDetectionService $regressionService;
    protected OperationalIncidentService $incidentService;

    public function __construct()
    {
        $this->postVerifyService   = new PostDeploymentVerificationService();
        $this->releaseHealthService= new ReleaseHealthService();
        $this->canaryService       = new ProductionCanaryService();
        $this->regressionService   = new RegressionDetectionService();
        $this->incidentService     = new OperationalIncidentService();
    }

    /**
     * GET /operations/live-status
     * Enterprise Live Operations & Release Assurance View (Phase 6B)
     */
    public function index()
    {
        $postVerify = $this->postVerifyService->verifyLiveDeployment();
        $health     = $this->releaseHealthService->getReleaseHealthScore();
        $canary     = $this->canaryService->evaluateCanaryObservation();

        return view('enterprise_operations/index', [
            'title'      => 'SIDAK TEJO v3.0.0 — Enterprise Live Operations & Post-Deployment Assurance',
            'postVerify' => $postVerify['live_verification'] ?? [],
            'health'     => $health['release_health'] ?? [],
            'canary'     => $canary['canary_status'] ?? [],
        ]);
    }

    /**
     * POST /operations/verify-release
     * Post-Deployment Verification API (Phase 6B)
     */
    public function verifyRelease(): ResponseInterface
    {
        $result = $this->postVerifyService->verifyLiveDeployment();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /operations/canary-check
     * Canary Observation API (Phase 6B)
     */
    public function canaryCheck(): ResponseInterface
    {
        $result = $this->canaryService->evaluateCanaryObservation();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
