<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\CriticalInfrastructureResilienceService;
use App\Services\CriticalInfrastructureAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseCriticalInfrastructureController extends BaseController
{
    protected CriticalInfrastructureResilienceService $resilienceService;
    protected CriticalInfrastructureAdvisoryService $advisoryService;

    public function __construct()
    {
        $this->resilienceService = new CriticalInfrastructureResilienceService();
        $this->advisoryService   = new CriticalInfrastructureAdvisoryService();
    }

    /**
     * GET /critical-infrastructure/control-center
     * Enterprise Grid Critical Infrastructure Resilience Control View (Phase 7W)
     */
    public function index()
    {
        $resRes = $this->resilienceService->auditCriticalInfrastructureResilience(1);
        $advRes = $this->advisoryService->recommendCriticalInfrastructureAdvisory(1);

        return view('enterprise_critical_infrastructure/index', [
            'title'                            => 'SIDAK TEJO v3.0.0 — Enterprise Grid Critical Infrastructure Center',
            'criticalInfrastructureResilience' => $resRes['critical_infrastructure_resilience'] ?? [],
            'criticalAdvisory'                 => $advRes['critical_advisory'] ?? [],
        ]);
    }

    /**
     * GET /critical-infrastructure/resilience-snapshot
     * Critical Infrastructure Resilience Snapshot API (Phase 7W)
     */
    public function resilienceSnapshot(): ResponseInterface
    {
        $result = $this->resilienceService->auditCriticalInfrastructureResilience(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /critical-infrastructure/critical-advisory
     * Critical Infrastructure Restoration Advisory API (Phase 7W)
     */
    public function criticalAdvisory(): ResponseInterface
    {
        $result = $this->advisoryService->recommendCriticalInfrastructureAdvisory(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
