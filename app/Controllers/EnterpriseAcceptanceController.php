<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\ProductionAcceptanceChecklistService;
use App\Services\OperationalHandoverRunbookService;
use App\Services\HypercareMonitoringService;
use App\Services\FinalGoLiveCertificationService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseAcceptanceController extends BaseController
{
    protected ProductionAcceptanceChecklistService $checklistService;
    protected OperationalHandoverRunbookService $runbookService;
    protected HypercareMonitoringService $hypercareService;
    protected FinalGoLiveCertificationService $certService;

    public function __construct()
    {
        $this->checklistService = new ProductionAcceptanceChecklistService();
        $this->runbookService   = new OperationalHandoverRunbookService();
        $this->hypercareService = new HypercareMonitoringService();
        $this->certService      = new FinalGoLiveCertificationService();
    }

    /**
     * GET /acceptance/status
     * Enterprise Production Acceptance & Final Go-Live Certification View (Phase 6E)
     */
    public function index()
    {
        $checklist = $this->checklistService->evaluateAcceptanceChecklist();
        $runbook   = $this->runbookService->getOperationalHandoverStatus();
        $hypercare = $this->hypercareService->getHypercareStatus();
        $cert      = $this->certService->issueFinalGoLiveCertification();

        return view('enterprise_acceptance/index', [
            'title'     => 'SIDAK TEJO v3.0.0 — Enterprise Production Acceptance & Final Go-Live Certification',
            'checklist' => $checklist['acceptance_checklist'] ?? [],
            'runbook'   => $runbook['runbook_handover'] ?? [],
            'hypercare' => $hypercare['hypercare'] ?? [],
            'cert'      => $cert['certification'] ?? [],
        ]);
    }

    /**
     * POST /acceptance/sign-off
     * Sign-off Production Acceptance Checklist API (Phase 6E)
     */
    public function signOff(): ResponseInterface
    {
        $result = $this->checklistService->evaluateAcceptanceChecklist();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /acceptance/certificate/generate
     * Generate Final Go-Live Certificate API (Phase 6E)
     */
    public function generateCertificate(): ResponseInterface
    {
        $result = $this->certService->issueFinalGoLiveCertification();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
