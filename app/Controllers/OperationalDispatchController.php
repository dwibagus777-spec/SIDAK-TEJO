<?php

namespace App\Controllers;

use App\Services\OperationalDispatchWorkflowService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Operational Dispatch Controller (CR-04 Phase 2)
 *
 * Responsibilities:
 * - Render Governed Operational Dispatch Workspace UI.
 * - Serve read-only JSON API for dispatch queue and evidence.
 * - Handle human-initiated dispatch plan creation.
 * - Handle governed state transitions with confirmation tokens.
 */
class OperationalDispatchController extends BaseController
{
    protected OperationalDispatchWorkflowService $dispatchService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->dispatchService = new OperationalDispatchWorkflowService();
    }

    /**
     * Render the Operational Dispatch Workspace UI.
     */
    public function index()
    {
        $queueData = $this->dispatchService->getDispatchQueue();
        return view('operational_dispatch/index', [
            'title'     => 'Operational Dispatch & Action Governance | SIDAK TEJO',
            'queueData' => $queueData,
        ]);
    }

    /**
     * API Endpoint: Get Dispatch Queue & History.
     * GET /api/operational-dispatch/queue
     */
    public function apiQueue(): ResponseInterface
    {
        $data = $this->dispatchService->getDispatchQueue();
        return $this->response->setJSON($data);
    }

    /**
     * API Endpoint: Create Human-Initiated Dispatch Draft.
     * POST /api/operational-dispatch/create-draft
     */
    public function apiCreateDraft(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $payload = $json['payload'] ?? $json;
        $actor   = $json['actor'] ?? [
            'actor_id'   => 1,
            'actor_name' => 'SUPERVISOR_OPERASI',
            'actor_nip'  => '198905142014021001',
            'actor_role' => 'PLANNER',
        ];

        $result = $this->dispatchService->createDraft($payload, $actor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API Endpoint: Execute Governed State Transition.
     * POST /api/operational-dispatch/transition
     */
    public function apiTransition(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $dispatchId  = $json['dispatch_id'] ?? '';
        $targetState = $json['target_state'] ?? '';
        $notes       = $json['notes'] ?? '';
        $actor       = $json['actor'] ?? [
            'actor_id'   => 1,
            'actor_name' => 'SUPERVISOR_DISTRIBUSI',
            'actor_nip'  => '198503122009011002',
            'actor_role' => 'SUPERVISOR',
        ];

        $result = $this->dispatchService->transitionState($dispatchId, $targetState, $actor, $notes);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }
}
