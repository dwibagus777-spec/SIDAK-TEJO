<?php

namespace App\Controllers;

use App\Services\ConstructionIntelligenceService;
use App\Services\NetworkConfigurationService;
use App\Services\DynamicSldEngineService;
use App\Services\FeederHealthIntelligenceService;
use App\Services\InspectionMeasurementService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controller for Construction, Material & Network Configuration Intelligence (CR-06)
 * Governed by 7 Hardening Gates
 */
class ConstructionIntelligenceController extends BaseController
{
    protected ConstructionIntelligenceService $ciService;
    protected NetworkConfigurationService $ncService;
    protected DynamicSldEngineService $sldService;
    protected FeederHealthIntelligenceService $fhiService;
    protected InspectionMeasurementService $inspService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->ciService   = new ConstructionIntelligenceService();
        $this->ncService   = new NetworkConfigurationService();
        $this->sldService  = new DynamicSldEngineService(null, $this->ncService);
        $this->fhiService  = new FeederHealthIntelligenceService();
        $this->inspService = new InspectionMeasurementService();
    }

    /**
     * Render Construction Intelligence Console Dashboard.
     */
    public function index()
    {
        $report = $this->ciService->getDataQualityReport();
        $policy = $this->fhiService->ensureDefaultPolicy();

        return view('construction_intelligence/index', [
            'title'       => 'Construction, Material & Network Configuration Intelligence | SIDAK TEJO',
            'report'      => $report,
            'policy'      => $policy,
            'active_menu' => 'construction_intelligence',
        ]);
    }

    /**
     * API: Get Data Quality & Unresolved Materials Report
     * GET /api/construction-intelligence/summary
     */
    public function apiDataQuality(): ResponseInterface
    {
        $report = $this->ciService->getDataQualityReport();
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $report,
        ]);
    }

    /**
     * API: Get Dynamic SLD Payload for a Section (Gate 5)
     * GET /api/sld/section/:sectionId
     */
    public function apiSld(int $sectionId): ResponseInterface
    {
        $asOfDate = $this->request->getGet('as_of');
        $sld = $this->sldService->renderSectionSld($sectionId, $asOfDate);
        return $this->response->setJSON($sld);
    }

    /**
     * API: Get Executive Feeder Health Score & Classification (Gate 6 & 7 / CC-04)
     * GET /api/feeder-health/:penyulangId
     */
    public function apiFeederHealth(int $penyulangId): ResponseInterface
    {
        $periodMonth = $this->request->getGet('period') ?: date('Y-m');
        $policyCode  = $this->request->getGet('policy') ?: 'FHI-v1.0';

        $health = $this->fhiService->calculateFeederHealth($penyulangId, $periodMonth, $policyCode);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $health,
        ]);
    }
}
