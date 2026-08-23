<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\DataRetentionPolicyService;
use App\Services\EnterpriseArchiveService;
use App\Services\LegalHoldComplianceService;
use App\Services\ComplianceEvidenceService;
use CodeIgniter\HTTP\ResponseInterface;

class DataLifecycleController extends BaseController
{
    protected DataRetentionPolicyService $policyService;
    protected EnterpriseArchiveService $archiveService;
    protected LegalHoldComplianceService $legalHoldService;
    protected ComplianceEvidenceService $evidenceService;

    public function __construct()
    {
        $this->policyService   = new DataRetentionPolicyService();
        $this->archiveService  = new EnterpriseArchiveService();
        $this->legalHoldService= new LegalHoldComplianceService();
        $this->evidenceService = new ComplianceEvidenceService();
    }

    /**
     * GET /compliance/retention-status
     * Enterprise Data Lifecycle & Compliance Dashboard View (Phase 5D)
     */
    public function index()
    {
        $policyStatus = $this->policyService->getRetentionPolicyStatus();
        $legalHold    = $this->legalHoldService->evaluateLegalHold('FINDINGS_MASTER');

        return view('enterprise_compliance/index', [
            'title'        => 'SIDAK TEJO v3.0.0 — Enterprise Data Retention & Compliance Center',
            'policyStatus' => $policyStatus['domain_policies'] ?? [],
            'legalHold'    => $legalHold['legal_hold_state'] ?? [],
        ]);
    }

    /**
     * POST /compliance/archive/run
     * Create Archive Batch API (Phase 5D)
     */
    public function runArchive(): ResponseInterface
    {
        $json   = $this->request->getJSON(true) ?? [];
        $domain = $json['domain'] ?? 'FINDINGS_HISTORICAL';

        $result = $this->archiveService->createArchiveBatch($domain);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /compliance/evidence/generate
     * Generate Compliance Evidence Bundle API (Phase 5D)
     */
    public function generateEvidence(): ResponseInterface
    {
        $json   = $this->request->getJSON(true) ?? [];
        $domain = $json['domain'] ?? 'SECURITY_AUDIT';

        $result = $this->evidenceService->generateEvidenceBundle($domain);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
