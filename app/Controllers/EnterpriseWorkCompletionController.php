<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\WorkCompletionAssuranceService;
use App\Services\WorkQualityAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseWorkCompletionController extends BaseController
{
    protected WorkCompletionAssuranceService $assuranceService;
    protected WorkQualityAdvisoryService $advisoryService;

    public function __construct()
    {
        $this->assuranceService = new WorkCompletionAssuranceService();
        $this->advisoryService  = new WorkQualityAdvisoryService();
    }

    /**
     * GET /work-completion/control-center
     * Enterprise Work Completion Assurance Center View (Phase 7X)
     */
    public function index()
    {
        $assuranceRes = $this->assuranceService->auditWorkCompletion(1);
        $advisoryRes  = $this->advisoryService->recommendWorkQualityAdvisory(1);

        return view('enterprise_work_completion/index', [
            'title'               => 'SIDAK TEJO v3.0.0 — Enterprise Work Completion Assurance Center',
            'workCompletionAudit' => $assuranceRes['work_completion_audit'] ?? [],
            'workQualityAdvisory' => $advisoryRes['work_quality_advisory'] ?? [],
        ]);
    }

    /**
     * GET /work-completion/completion-snapshot
     * Work Completion Evidence Snapshot API (Phase 7X)
     */
    public function completionSnapshot(): ResponseInterface
    {
        $result = $this->assuranceService->auditWorkCompletion(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /work-completion/quality-advisory
     * Work Quality & Evidence Advisory API (Phase 7X)
     */
    public function qualityAdvisory(): ResponseInterface
    {
        $result = $this->advisoryService->recommendWorkQualityAdvisory(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
