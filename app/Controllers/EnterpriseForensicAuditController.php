<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OperationalForensicAuditService;
use App\Services\AuditorForensicBundleService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseForensicAuditController extends BaseController
{
    protected OperationalForensicAuditService $forensicService;
    protected AuditorForensicBundleService $bundleService;

    public function __construct()
    {
        $this->forensicService = new OperationalForensicAuditService();
        $this->bundleService   = new AuditorForensicBundleService();
    }

    /**
     * GET /forensic-audit/control-center
     * Enterprise Operational Forensic Audit Control View (Phase 7Q)
     */
    public function index()
    {
        $auditRes  = $this->forensicService->auditDecisionProvenance(1);
        $bundleRes = $this->bundleService->generateForensicBundle(1);

        return view('enterprise_forensic_audit/index', [
            'title'          => 'SIDAK TEJO v3.0.0 — Enterprise Operational Forensic Audit & Lineage Center',
            'forensicAudit'  => $auditRes['forensic_audit'] ?? [],
            'forensicBundle' => $bundleRes['forensic_bundle'] ?? [],
        ]);
    }

    /**
     * GET /forensic-audit/provenance-snapshot
     * Provenance Lineage Snapshot API (Phase 7Q)
     */
    public function provenanceSnapshot(): ResponseInterface
    {
        $result = $this->forensicService->auditDecisionProvenance(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /forensic-audit/forensic-bundle
     * Auditor Forensic Bundle API (Phase 7Q)
     */
    public function forensicBundle(): ResponseInterface
    {
        $result = $this->bundleService->generateForensicBundle(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
