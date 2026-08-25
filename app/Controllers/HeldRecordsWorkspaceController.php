<?php

namespace App\Controllers;

use App\Services\HeldRecordsResolutionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Held Records Workspace Controller (CR-02 Phase 2)
 *
 * Responsibilities:
 * - Render Held Records Resolution Workspace UI.
 * - JSON API for held records and master feeders list.
 * - Dry-Run Resolution Plan generation with cryptographic confirmation token.
 */
class HeldRecordsWorkspaceController extends BaseController
{
    protected HeldRecordsResolutionService $resolutionService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->resolutionService = new HeldRecordsResolutionService();
    }

    /**
     * Render the Held Records Workspace UI.
     */
    public function index()
    {
        $data = $this->resolutionService->getHeldWorkspaceData();
        return view('held_records/workspace', [
            'title'         => 'Held Records Resolution Workspace | SIDAK TEJO v3.1.0',
            'workspaceData' => $data,
        ]);
    }

    /**
     * API Endpoint: Get JSON list of held records and categories.
     */
    public function apiList(): ResponseInterface
    {
        $data = $this->resolutionService->getHeldWorkspaceData();
        return $this->response->setJSON($data);
    }

    /**
     * API Endpoint: Pure in-memory Dry-Run Resolution Plan.
     */
    public function apiDryRun(): ResponseInterface
    {
        $jsonInput = $this->request->getJSON(true) ?? [];
        $decisions = $jsonInput['decisions'] ?? [];
        $actor     = $jsonInput['actor'] ?? null;

        $plan = $this->resolutionService->generateDryRunPlan($decisions, $actor);

        return $this->response->setJSON($plan);
    }
}
