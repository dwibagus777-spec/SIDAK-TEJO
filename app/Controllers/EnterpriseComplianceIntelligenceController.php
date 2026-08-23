<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\RegulatoryObligationRegistryService;
use App\Services\ComplianceGapAssessmentService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseComplianceIntelligenceController extends BaseController
{
    protected RegulatoryObligationRegistryService $regService;
    protected ComplianceGapAssessmentService $gapService;

    public function __construct()
    {
        $this->regService = new RegulatoryObligationRegistryService();
        $this->gapService = new ComplianceGapAssessmentService();
    }

    /**
     * GET /compliance-intelligence/control-center
     * Enterprise Compliance Intelligence Control View (Phase 7K)
     */
    public function index()
    {
        $regRes = $this->regService->registerRegulatoryObligations(1);
        $gapRes = $this->gapService->assessComplianceGaps(1);

        return view('enterprise_compliance_intelligence/index', [
            'title'              => 'SIDAK TEJO v3.0.0 — Enterprise Regulatory Compliance Intelligence & Obligation Control Center',
            'obligationRegistry' => $regRes['obligation_registry'] ?? [],
            'gapAssessment'      => $gapRes['gap_assessment'] ?? [],
        ]);
    }

    /**
     * GET /compliance-intelligence/obligation-snapshot
     * Regulatory Obligation Snapshot API (Phase 7K)
     */
    public function obligationSnapshot(): ResponseInterface
    {
        $result = $this->regService->registerRegulatoryObligations(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /compliance-intelligence/readiness-bundle
     * Submission Readiness Bundle API (Phase 7K)
     */
    public function readinessBundle(): ResponseInterface
    {
        $result = $this->gapService->assessComplianceGaps(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
