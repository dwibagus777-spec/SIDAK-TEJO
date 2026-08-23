<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\ContinuousReliabilityAssuranceService;
use App\Services\ReliabilityImprovementAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseReliabilityAssuranceController extends BaseController
{
    protected ContinuousReliabilityAssuranceService $assuranceService;
    protected ReliabilityImprovementAdvisoryService $improvementService;

    public function __construct()
    {
        $this->assuranceService   = new ContinuousReliabilityAssuranceService();
        $this->improvementService = new ReliabilityImprovementAdvisoryService();
    }

    /**
     * GET /reliability-assurance/control-center
     * Enterprise Grid Continuous Reliability Assurance Control View (Phase 7V)
     */
    public function index()
    {
        $assRes = $this->assuranceService->auditReliabilityAssurance(1);
        $impRes = $this->improvementService->recommendReliabilityImprovement(1);

        return view('enterprise_reliability_assurance/index', [
            'title'                => 'SIDAK TEJO v3.0.0 — Enterprise Grid Reliability Assurance Center',
            'reliabilityAssurance' => $assRes['reliability_assurance'] ?? [],
            'improvementAdvisory'  => $impRes['improvement_advisory'] ?? [],
        ]);
    }

    /**
     * GET /reliability-assurance/reliability-snapshot
     * Reliability Assurance Snapshot API (Phase 7V)
     */
    public function reliabilitySnapshot(): ResponseInterface
    {
        $result = $this->assuranceService->auditReliabilityAssurance(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /reliability-assurance/improvement-advisory
     * Reliability Improvement Advisory API (Phase 7V)
     */
    public function improvementAdvisory(): ResponseInterface
    {
        $result = $this->improvementService->recommendReliabilityImprovement(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
