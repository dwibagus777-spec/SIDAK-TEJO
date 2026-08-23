<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OperationalFinancialAuditService;
use App\Services\OutageCostRecoveryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseFinancialAuditController extends BaseController
{
    protected OperationalFinancialAuditService $finService;
    protected OutageCostRecoveryService $recoveryService;

    public function __construct()
    {
        $this->finService      = new OperationalFinancialAuditService();
        $this->recoveryService = new OutageCostRecoveryService();
    }

    /**
     * GET /financial-audit/control-center
     * Enterprise Financial Audit Control View (Phase 7J)
     */
    public function index()
    {
        $finRes      = $this->finService->auditOperationalFinances(1);
        $recoveryRes = $this->recoveryService->composeOutageCostRecovery(1);

        return view('enterprise_financial_audit/index', [
            'title'            => 'SIDAK TEJO v3.0.0 — Enterprise Operational Financial Audit & Cost Recovery Center',
            'financialAudit'   => $finRes['financial_audit'] ?? [],
            'recoveryProposal' => $recoveryRes['recovery_proposal'] ?? [],
        ]);
    }

    /**
     * GET /financial-audit/financial-snapshot
     * Operational Financial Audit Snapshot API (Phase 7J)
     */
    public function financialSnapshot(): ResponseInterface
    {
        $result = $this->finService->auditOperationalFinances(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /financial-audit/recovery-proposal
     * Outage Cost Recovery Proposal API (Phase 7J)
     */
    public function recoveryProposal(): ResponseInterface
    {
        $result = $this->recoveryService->composeOutageCostRecovery(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
