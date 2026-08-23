<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\ChangeManagementService;
use App\Services\ChangeImpactAssessmentService;
use App\Services\ProductionChangeApprovalService;
use App\Services\ProductionChangeWindowService;
use App\Services\ReleaseGovernanceEvidenceService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseChangeController extends BaseController
{
    protected ChangeManagementService $changeService;
    protected ChangeImpactAssessmentService $impactService;
    protected ProductionChangeApprovalService $approvalService;
    protected ProductionChangeWindowService $windowService;
    protected ReleaseGovernanceEvidenceService $evidenceService;

    public function __construct()
    {
        $this->changeService   = new ChangeManagementService();
        $this->impactService   = new ChangeImpactAssessmentService();
        $this->approvalService = new ProductionChangeApprovalService();
        $this->windowService   = new ProductionChangeWindowService();
        $this->evidenceService = new ReleaseGovernanceEvidenceService();
    }

    /**
     * GET /change/governance-status
     * Enterprise Release Governance & Change Control View (Phase 6C)
     */
    public function index()
    {
        $change   = $this->changeService->createChangeRequest('Deploy SIDAK TEJO v3.0.0 Release', 'NORMAL_CHANGE');
        $impact   = $this->impactService->assessChangeImpact('CR-STJ-20260822-001');
        $approval = $this->approvalService->approveChangeRequest('CR-STJ-20260822-001');

        return view('enterprise_change/index', [
            'title'    => 'SIDAK TEJO v3.0.0 — Enterprise Release Governance & Production Change Control',
            'change'   => $change['change_request'] ?? [],
            'impact'   => $impact['impact_assessment'] ?? [],
            'approval' => $approval['approval_record'] ?? [],
        ]);
    }

    /**
     * POST /change/request/create
     * Create Change Request API (Phase 6C)
     */
    public function createRequest(): ResponseInterface
    {
        $json  = $this->request->getJSON(true) ?? [];
        $title = $json['title'] ?? 'Deploy SIDAK TEJO v3.0.0 Release';
        $type  = $json['type'] ?? 'NORMAL_CHANGE';

        $result = $this->changeService->createChangeRequest($title, $type);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /change/impact/assess
     * Assess Change Impact API (Phase 6C)
     */
    public function assessImpact(): ResponseInterface
    {
        $json   = $this->request->getJSON(true) ?? [];
        $crCode = $json['change_code'] ?? 'CR-STJ-20260822-001';

        $result = $this->impactService->assessChangeImpact($crCode);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /change/approve
     * Approve Change Request API (Phase 6C)
     */
    public function approveRequest(): ResponseInterface
    {
        $json   = $this->request->getJSON(true) ?? [];
        $crCode = $json['change_code'] ?? 'CR-STJ-20260822-001';

        $result = $this->approvalService->approveChangeRequest($crCode);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
