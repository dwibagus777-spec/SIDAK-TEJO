<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\EnterpriseCommandCenterService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseCommandCenterController extends BaseController
{
    protected EnterpriseCommandCenterService $commandCenterService;

    public function __construct()
    {
        $this->commandCenterService = new EnterpriseCommandCenterService();
    }

    /**
     * GET /enterprise-command-center
     * Unified Enterprise Command Center Workspace View (Phase 4A)
     */
    public function index()
    {
        $workspaceData = $this->commandCenterService->getUnifiedEnterpriseOperationalWorkspace(1);

        return view('enterprise_command_center/index', [
            'title'         => 'SIDAK TEJO v3.0.0 — Enterprise Command Center & Digital Operational Experience',
            'workspaceData' => $workspaceData,
        ]);
    }

    /**
     * GET /enterprise-command-center/api-feed
     * Unified Enterprise Operational Workspace API Feed (Phase 4A)
     */
    public function apiFeed(): ResponseInterface
    {
        $assetId = (int)($this->request->getGet('asset_id') ?? 1);
        $workspaceData = $this->commandCenterService->getUnifiedEnterpriseOperationalWorkspace($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $workspaceData,
        ]);
    }
}
