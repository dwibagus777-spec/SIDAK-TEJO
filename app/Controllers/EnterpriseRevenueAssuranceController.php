<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\RevenueAssuranceAuditService;
use App\Services\RevenueProtectionAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseRevenueAssuranceController extends BaseController
{
    protected RevenueAssuranceAuditService $assuranceService;
    protected RevenueProtectionAdvisoryService $protectionService;

    public function __construct()
    {
        $this->assuranceService  = new RevenueAssuranceAuditService();
        $this->protectionService = new RevenueProtectionAdvisoryService();
    }

    /**
     * GET /revenue-assurance/control-center
     * Enterprise Grid Revenue Assurance Control View (Phase 7U)
     */
    public function index()
    {
        $assRes = $this->assuranceService->auditRevenueAssurance(1);
        $proRes = $this->protectionService->recommendRevenueProtection(1);

        return view('enterprise_revenue_assurance/index', [
            'title'             => 'SIDAK TEJO v3.0.0 — Enterprise Grid Revenue Assurance Center',
            'revenueAssurance'  => $assRes['revenue_assurance'] ?? [],
            'protectionAdvisory'=> $proRes['protection_advisory'] ?? [],
        ]);
    }

    /**
     * GET /revenue-assurance/revenue-snapshot
     * Revenue Assurance Snapshot API (Phase 7U)
     */
    public function revenueSnapshot(): ResponseInterface
    {
        $result = $this->assuranceService->auditRevenueAssurance(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /revenue-assurance/protection-advisory
     * Revenue Protection Advisory API (Phase 7U)
     */
    public function protectionAdvisory(): ResponseInterface
    {
        $result = $this->protectionService->recommendRevenueProtection(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
