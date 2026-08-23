<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\IncidentCommandService;
use App\Services\MajorEventCoordinationService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseIncidentCommandController extends BaseController
{
    protected IncidentCommandService $incidentService;
    protected MajorEventCoordinationService $crisisService;

    public function __construct()
    {
        $this->incidentService = new IncidentCommandService();
        $this->crisisService   = new MajorEventCoordinationService();
    }

    /**
     * GET /incident/command-center
     * Enterprise Incident Command Control View (Phase 7H)
     */
    public function index()
    {
        $incidentRes = $this->incidentService->declareMajorIncident([]);
        $crisisRes   = $this->crisisService->coordinateCrisisResources();

        return view('enterprise_incident_command/index', [
            'title'               => 'SIDAK TEJO v3.0.0 — Enterprise Incident Command & Crisis Control Center',
            'incidentDeclaration' => $incidentRes['incident_declaration'] ?? [],
            'crisisCoordination'  => $crisisRes['crisis_coordination'] ?? [],
        ]);
    }

    /**
     * POST /incident/declare
     * Declare Major Incident API (Phase 7H)
     */
    public function declareIncident(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $result  = $this->incidentService->declareMajorIncident($payload);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /incident/situation-board
     * Situation Board & Crisis Coordination API (Phase 7H)
     */
    public function situationBoard(): ResponseInterface
    {
        $result = $this->crisisService->coordinateCrisisResources();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
