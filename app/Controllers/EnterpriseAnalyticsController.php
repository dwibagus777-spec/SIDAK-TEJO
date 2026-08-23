<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OperationalAnalyticsService;
use App\Services\ExecutiveBiReportingService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseAnalyticsController extends BaseController
{
    protected OperationalAnalyticsService $analyticsService;
    protected ExecutiveBiReportingService $biService;

    public function __construct()
    {
        $this->analyticsService = new OperationalAnalyticsService();
        $this->biService        = new ExecutiveBiReportingService();
    }

    /**
     * GET /analytics/executive-bi
     * Executive BI & Operational Analytics Control View (Phase 7C)
     */
    public function index()
    {
        $biSnapshotData = $this->biService->getExecutiveBiSnapshot();
        $drillDownData  = $this->analyticsService->getBoundedDrillDownData(1, 10);

        return view('enterprise_analytics/index', [
            'title'      => 'SIDAK TEJO v3.0.0 — Executive Business Intelligence & Operational Analytics',
            'biSnapshot' => $biSnapshotData['bi_snapshot'] ?? [],
            'drillDown'  => $drillDownData['drill_down_payload'] ?? [],
        ]);
    }

    /**
     * GET /analytics/snapshot
     * Executive BI Snapshot API (Phase 7C)
     */
    public function snapshot(): ResponseInterface
    {
        $result = $this->biService->getExecutiveBiSnapshot();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /analytics/drill-down
     * Bounded Paginated Drill-Down Data API (Phase 7C)
     */
    public function drillDown(): ResponseInterface
    {
        $page    = (int)($this->request->getGet('page') ?? 1);
        $perPage = (int)($this->request->getGet('per_page') ?? 10);

        $result = $this->analyticsService->getBoundedDrillDownData($page, $perPage);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
