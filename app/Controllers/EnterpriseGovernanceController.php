<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\MasterDataStewardshipService;
use App\Services\CrossSystemReconciliationService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseGovernanceController extends BaseController
{
    protected MasterDataStewardshipService $stewardshipService;
    protected CrossSystemReconciliationService $reconService;

    public function __construct()
    {
        $this->stewardshipService = new MasterDataStewardshipService();
        $this->reconService       = new CrossSystemReconciliationService();
    }

    /**
     * GET /governance/data-stewardship
     * Enterprise Data Governance & Reconciliation Control View (Phase 7G)
     */
    public function index()
    {
        $stewardRes = $this->stewardshipService->auditMasterDataStewardship(1);
        $reconRes   = $this->reconService->reconcileCrossSystemData();

        return view('enterprise_governance/index', [
            'title'            => 'SIDAK TEJO v3.0.0 — Enterprise Data Governance & Cross-System Reconciliation Center',
            'stewardshipAudit' => $stewardRes['stewardship_audit'] ?? [],
            'reconResult'      => $reconRes['reconciliation_result'] ?? [],
        ]);
    }

    /**
     * GET /governance/stewardship-snapshot
     * Master Data Stewardship Audit Snapshot API (Phase 7G)
     */
    public function stewardshipSnapshot(): ResponseInterface
    {
        $result = $this->stewardshipService->auditMasterDataStewardship(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /governance/reconciliation-result
     * Cross-System Reconciliation Result API (Phase 7G)
     */
    public function reconciliationResult(): ResponseInterface
    {
        $result = $this->reconService->reconcileCrossSystemData();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
