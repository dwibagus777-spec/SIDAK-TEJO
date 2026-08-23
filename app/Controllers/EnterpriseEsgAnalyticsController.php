<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\EsgCarbonFootprintAuditService;
use App\Services\DecarbonizationAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseEsgAnalyticsController extends BaseController
{
    protected EsgCarbonFootprintAuditService $esgService;
    protected DecarbonizationAdvisoryService $decarbService;

    public function __construct()
    {
        $this->esgService    = new EsgCarbonFootprintAuditService();
        $this->decarbService = new DecarbonizationAdvisoryService();
    }

    /**
     * GET /esg-analytics/control-center
     * Enterprise ESG Analytics Control View (Phase 7M)
     */
    public function index()
    {
        $esgRes    = $this->esgService->auditCarbonFootprint(1);
        $decarbRes = $this->decarbService->recommendDecarbonization(1);

        return view('enterprise_esg_analytics/index', [
            'title'                   => 'SIDAK TEJO v3.0.0 — Enterprise ESG & Carbon Footprint Control Center',
            'esgAudit'                => $esgRes['esg_audit'] ?? [],
            'decarbonizationAdvisory' => $decarbRes['decarbonization_advisory'] ?? [],
        ]);
    }

    /**
     * GET /esg-analytics/esg-snapshot
     * ESG Carbon Footprint Audit Snapshot API (Phase 7M)
     */
    public function esgSnapshot(): ResponseInterface
    {
        $result = $this->esgService->auditCarbonFootprint(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /esg-analytics/decarbonization-advisory
     * Decarbonization Advisory API (Phase 7M)
     */
    public function decarbonizationAdvisory(): ResponseInterface
    {
        $result = $this->decarbService->recommendDecarbonization(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
