<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\ContractorPerformanceAuditService;
use App\Services\VendorSlaGovernanceService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseVendorGovernanceController extends BaseController
{
    protected ContractorPerformanceAuditService $auditService;
    protected VendorSlaGovernanceService $govService;

    public function __construct()
    {
        $this->auditService = new ContractorPerformanceAuditService();
        $this->govService   = new VendorSlaGovernanceService();
    }

    /**
     * GET /vendor-governance/control-center
     * Enterprise Vendor Governance Control View (Phase 7L)
     */
    public function index()
    {
        $auditRes = $this->auditService->auditContractorPerformance(1);
        $govRes   = $this->govService->governVendorSla(1);

        return view('enterprise_vendor_governance/index', [
            'title'           => 'SIDAK TEJO v3.0.0 — Enterprise Contractor Performance & Vendor Control Center',
            'contractorAudit' => $auditRes['contractor_audit'] ?? [],
            'ratingAdvisory'  => $govRes['rating_advisory'] ?? [],
        ]);
    }

    /**
     * GET /vendor-governance/contractor-snapshot
     * Contractor Audit Snapshot API (Phase 7L)
     */
    public function contractorSnapshot(): ResponseInterface
    {
        $result = $this->auditService->auditContractorPerformance(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /vendor-governance/vendor-rating-advisory
     * Vendor Rating Advisory API (Phase 7L)
     */
    public function vendorRatingAdvisory(): ResponseInterface
    {
        $result = $this->govService->governVendorSla(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
