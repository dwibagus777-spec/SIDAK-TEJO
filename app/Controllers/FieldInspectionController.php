<?php

namespace App\Controllers;

use App\Services\FieldInspectionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Field Inspection Controller (CR-06 Phase 2)
 *
 * Responsibilities:
 * - Serve Field Inspection Workspace UI.
 * - Serve JSON APIs for inspection sessions, observations, and material usage.
 * - Handle governed session state transitions and observation recording.
 */
class FieldInspectionController extends BaseController
{
    protected FieldInspectionService $inspectionService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->inspectionService = new FieldInspectionService();
    }

    /**
     * Render Field Inspection Workspace Dashboard.
     */
    public function index()
    {
        $summary = $this->inspectionService->getInspectionSummary();
        $db = \Config\Database::connect();
        $assets = $db->table('assets')->orderBy('id', 'ASC')->get()->getResultArray();
        $feeders = $db->table('penyulang')->orderBy('nama_penyulang', 'ASC')->get()->getResultArray();

        return view('field_inspections/index', [
            'title'   => 'Field Inspection & Living Asset Condition | SIDAK TEJO',
            'summary' => $summary,
            'assets'  => $assets,
            'feeders' => $feeders,
        ]);
    }

    /**
     * API: Get Field Inspection Summary.
     * GET /api/inspections/summary
     */
    public function apiSummary(): ResponseInterface
    {
        $data = $this->inspectionService->getInspectionSummary();
        return $this->response->setJSON($data);
    }

    /**
     * API: Create Inspection Session.
     * POST /api/inspections/create-session
     */
    public function apiCreateSession(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $payload = $json['payload'] ?? $json;
        $actor   = $json['actor'] ?? [
            'actor_id'   => 1,
            'actor_name' => 'SUPERVISOR_INSPEKSI_JARINGAN',
            'actor_nip'  => '198607122010011002',
            'actor_role' => 'INSPECTION_SUPERVISOR',
        ];

        $result = $this->inspectionService->createInspectionSession($payload, $actor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Transition Inspection Session State.
     * POST /api/inspections/transition-session
     */
    public function apiTransitionSession(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $sessionId   = $json['session_id'] ?? '';
        $targetState = $json['target_state'] ?? '';
        $notes       = $json['notes'] ?? '';
        $actor       = $json['actor'] ?? [
            'actor_id'   => 2,
            'actor_name' => 'PETUGAS_INSPEKSI_LAPANGAN_1',
            'actor_nip'  => '199304192019021004',
            'actor_role' => 'FIELD_INSPECTOR',
        ];

        $result = $this->inspectionService->transitionSessionState($sessionId, $targetState, $actor, $notes);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Record Field Observation & Update Asset Living Health.
     * POST /api/inspections/record-observation
     */
    public function apiRecordObservation(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $sessionId = $json['session_id'] ?? '';
        $obsData   = $json['observation'] ?? [];
        $actor     = $json['actor'] ?? [
            'actor_id'   => 2,
            'actor_name' => 'PETUGAS_INSPEKSI_LAPANGAN_1',
            'actor_nip'  => '199304192019021004',
            'actor_role' => 'FIELD_INSPECTOR',
        ];

        $result = $this->inspectionService->recordObservation($sessionId, $obsData, $actor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Record Material Usage.
     * POST /api/inspections/record-material
     */
    public function apiRecordMaterial(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $usageData = $json['usage'] ?? [];
        $actor     = $json['actor'] ?? [
            'actor_id'   => 3,
            'actor_name' => 'PETUGAS_LOGISTIK_TEKNIK',
            'actor_nip'  => '199011282015031001',
            'actor_role' => 'MATERIAL_OFFICER',
        ];

        $result = $this->inspectionService->recordMaterialUsage($usageData, $actor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }
}
