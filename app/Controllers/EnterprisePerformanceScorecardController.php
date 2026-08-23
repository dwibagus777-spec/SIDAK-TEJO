<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OperationalPerformanceScorecardService;
use App\Services\ContinuousImprovementAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterprisePerformanceScorecardController extends BaseController
{
    protected OperationalPerformanceScorecardService $scorecardService;
    protected ContinuousImprovementAdvisoryService $improvementService;

    public function __construct()
    {
        $this->scorecardService   = new OperationalPerformanceScorecardService();
        $this->improvementService = new ContinuousImprovementAdvisoryService();
    }

    /**
     * GET /performance-scorecard/control-center
     * Enterprise Multi-Dimensional Performance Scorecard Center View (Phase 7Z)
     */
    public function index()
    {
        $scorecardRes    = $this->scorecardService->auditOperationalPerformanceScorecard(1);
        $improvementRes  = $this->improvementService->recommendContinuousImprovement(1);

        return view('enterprise_performance_scorecard/index', [
            'title'                        => 'SIDAK TEJO v3.0.0 — Enterprise Operational Performance Scorecard Center',
            'performanceScorecard'         => $scorecardRes['operational_performance_scorecard'] ?? [],
            'continuousImprovementAdvisory'=> $improvementRes['improvement_advisory'] ?? [],
        ]);
    }

    /**
     * GET /performance-scorecard/scorecard-snapshot
     * Operational Performance Scorecard Snapshot API (Phase 7Z)
     */
    public function scorecardSnapshot(): ResponseInterface
    {
        $result = $this->scorecardService->auditOperationalPerformanceScorecard(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /performance-scorecard/improvement-advisory
     * Continuous Improvement Advisory API (Phase 7Z)
     */
    public function improvementAdvisory(): ResponseInterface
    {
        $result = $this->improvementService->recommendContinuousImprovement(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
