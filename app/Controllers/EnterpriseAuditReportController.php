<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\RegulatoryReportingService;
use App\Services\AuditorExportBundleService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseAuditReportController extends BaseController
{
    protected RegulatoryReportingService $reportService;
    protected AuditorExportBundleService $bundleService;

    public function __construct()
    {
        $this->reportService = new RegulatoryReportingService();
        $this->bundleService = new AuditorExportBundleService();
    }

    /**
     * GET /audit/regulatory-report
     * Enterprise Regulatory Audit & Statutory Export View (Phase 7A)
     */
    public function index()
    {
        $report = $this->reportService->generateRegulatoryReport('ESDM_STATUTORY_COMPLIANCE');
        $bundle = $this->bundleService->createAuditorExportBundle($report['statutory_report']['report_code'] ?? 'RPT-STJ-20260822-001');

        return view('enterprise_audit/index', [
            'title'  => 'SIDAK TEJO v3.0.0 — Enterprise Regulatory Audit & Statutory Export Control',
            'report' => $report['statutory_report'] ?? [],
            'bundle' => $bundle['export_bundle'] ?? [],
        ]);
    }

    /**
     * POST /audit/report/generate
     * Generate Statutory Regulatory Report API (Phase 7A)
     */
    public function generateReport(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $type = $json['report_type'] ?? 'ESDM_STATUTORY_COMPLIANCE';

        $result = $this->reportService->generateRegulatoryReport($type);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /audit/bundle/export
     * Create Auditor Export Bundle API (Phase 7A)
     */
    public function exportBundle(): ResponseInterface
    {
        $json       = $this->request->getJSON(true) ?? [];
        $reportCode = $json['report_code'] ?? 'RPT-STJ-20260822-001';

        $result = $this->bundleService->createAuditorExportBundle($reportCode);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
