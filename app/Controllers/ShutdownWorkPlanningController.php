<?php

namespace App\Controllers;

use App\Services\ShutdownWorkPlanningService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Shutdown Scope, Inspection Taxonomy, SLD Work Planning & Material Allocation Evidence Controller (CC-06 Phase 2)
 */
class ShutdownWorkPlanningController extends BaseController
{
    protected ShutdownWorkPlanningService $planningService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->planningService = new ShutdownWorkPlanningService();
    }

    /**
     * Render 6-Integrated Workspaces View.
     */
    public function index()
    {
        $summary = $this->planningService->getPlanningSummary();
        $catalog = $this->planningService->getInspectionCatalog();

        return view('planning/shutdown_workspace', [
            'title'   => 'Evidence-Based Shutdown Work Planning & Material Traceability Suite | SIDAK TEJO',
            'summary' => $summary,
            'catalog' => $catalog,
        ]);
    }

    /**
     * API: Get Inspection Work Catalog.
     * GET /api/planning/inspection-catalog
     */
    public function apiInspectionCatalog(): ResponseInterface
    {
        return $this->response->setJSON($this->planningService->getInspectionCatalog());
    }

    /**
     * API: Get Sections for a Feeder with asset and finding stats.
     * GET /api/planning/feeder-sections/(:num)
     */
    public function apiFeederSections(int $feederId): ResponseInterface
    {
        $result = $this->planningService->getFeederSections($feederId);
        if (!$result['success']) {
            return $this->response->setStatusCode(404)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Compose Work Plan Scope & Material Evidence Chain.
     * POST /api/planning/compose-scope
     */
    public function apiComposeScope(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $feederId        = (int)($json['penyulang_id'] ?? 15);
        $sectionIds      = array_map('intval', $json['section_ids'] ?? [48, 49]);
        $scopes          = $json['scopes'] ?? ['TO_TEMUAN', 'TO_GTT_GARDU'];
        $inspectionCodes = $json['inspection_work_codes'] ?? ['INSP-JTM-VL1', 'INSP-JTM-THERMO'];
        $workMode        = $json['work_mode'] ?? 'OUTAGE_ISOLATED';
        $actor           = $json['actor'] ?? [
            'actor_id'   => 1,
            'actor_name' => 'SUPERVISOR_PEMELIHARAAN_JARINGAN',
            'actor_nip'  => '198403152008121003',
            'actor_role' => 'MAINTENANCE_SUPERVISOR',
        ];

        $result = $this->planningService->composeWorkPlanScope($feederId, $sectionIds, $scopes, $inspectionCodes, $workMode, $actor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Get Details of a Plan.
     * GET /api/planning/plan/(:segment)
     */
    public function apiPlanDetail(string $planId): ResponseInterface
    {
        $result = $this->planningService->getWorkPlanDetail($planId);
        if (!$result['success']) {
            return $this->response->setStatusCode(404)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Get Planning Summary.
     * GET /api/planning/summary
     */
    public function apiPlanningSummary(): ResponseInterface
    {
        return $this->response->setJSON($this->planningService->getPlanningSummary());
    }
}
