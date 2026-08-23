<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\InspectionSchedulingIntelligenceService;
use App\Services\InspectionPriorityAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseInspectionPlanningController extends BaseController
{
    protected InspectionSchedulingIntelligenceService $intelligenceService;
    protected InspectionPriorityAdvisoryService $advisoryService;

    public function __construct()
    {
        $this->intelligenceService = new InspectionSchedulingIntelligenceService();
        $this->advisoryService     = new InspectionPriorityAdvisoryService();
    }

    /**
     * GET /inspection-planning/control-center
     * Enterprise Inspection Planning & Risk-Based Cycle Intelligence View (Phase 7Y)
     */
    public function index()
    {
        $scheduleRes  = $this->intelligenceService->auditInspectionSchedule(1);
        $priorityRes  = $this->advisoryService->recommendInspectionPriority(1);

        return view('enterprise_inspection_planning/index', [
            'title'                   => 'SIDAK TEJO v3.0.0 — Enterprise Inspection Planning & Scheduling Intelligence Center',
            'inspectionScheduleAudit' => $scheduleRes['inspection_schedule_audit'] ?? [],
            'inspectionPriorityAdvisory' => $priorityRes['inspection_priority_advisory'] ?? [],
        ]);
    }

    /**
     * GET /inspection-planning/schedule-snapshot
     * Inspection Schedule Snapshot API (Phase 7Y)
     */
    public function scheduleSnapshot(): ResponseInterface
    {
        $result = $this->intelligenceService->auditInspectionSchedule(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /inspection-planning/priority-advisory
     * Inspection Priority Advisory API (Phase 7Y)
     */
    public function priorityAdvisory(): ResponseInterface
    {
        $result = $this->advisoryService->recommendInspectionPriority(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
