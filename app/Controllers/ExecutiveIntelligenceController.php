<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\ExecutiveDecisionAnalyticsService;

/**
 * Executive Intelligence Controller (Phase CC-04)
 *
 * Responsibilities:
 * - Enterprise Read-Model Dashboard & Decision Analytics.
 * - Invariant: MANAGEMENT_READ_MODEL_ONLY_HUMAN_OVERSIGHT.
 */
class ExecutiveIntelligenceController extends ResourceController
{
    protected $format = 'json';
    protected ExecutiveDecisionAnalyticsService $analyticsService;

    public function __construct()
    {
        $this->analyticsService = new ExecutiveDecisionAnalyticsService();
    }

    /**
     * GET /executive-intelligence
     * Web Dashboard for Executive Decision Analytics
     */
    public function index()
    {
        $summary = $this->analyticsService->generateExecutiveSummary();

        $data = [
            'title'   => 'Executive Intelligence & Decision Analytics',
            'summary' => $summary,
        ];

        return view('preventive_intelligence/executive', $data);
    }

    /**
     * GET /executive-intelligence/api/summary
     */
    public function apiSummary()
    {
        $summary = $this->analyticsService->generateExecutiveSummary();
        return $this->respond($summary);
    }

    /**
     * GET /executive-intelligence/export
     */
    public function exportSummary()
    {
        $summary = $this->analyticsService->generateExecutiveSummary();
        return $this->respond([
            'status'         => 'success',
            'export_bundle'  => 'EXEC-RPT-STJ-' . date('YmdHis'),
            'summary'        => $summary,
            'certified_by'   => 'SIDAK_TEJO_ENTERPRISE_ANALYTICS_FABRIC',
        ]);
    }
}
