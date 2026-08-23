<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\AdaptiveOperationalExperienceService;
use App\Services\MobileFieldExecutionService;
use App\Services\OperationalHandoffService;
use CodeIgniter\HTTP\ResponseInterface;

class OperationalExperienceController extends BaseController
{
    protected AdaptiveOperationalExperienceService $experienceService;
    protected MobileFieldExecutionService $mobileService;
    protected OperationalHandoffService $handoffService;

    public function __construct()
    {
        $this->experienceService = new AdaptiveOperationalExperienceService();
        $this->mobileService     = new MobileFieldExecutionService();
        $this->handoffService    = new OperationalHandoffService();
    }

    /**
     * GET /experience/role-workspace/(:segment)
     * Role-Aware Adaptive Operational Workspace View (Phase 4C)
     */
    public function roleWorkspace(string $role = 'PETUGAS_LAPANGAN')
    {
        $assetId = (int)($this->request->getGet('asset_id') ?? 1);
        $roleData = $this->experienceService->getRoleAdaptiveWorkspace($role, $assetId);

        return view('operational_experience/index', [
            'title'    => 'SIDAK TEJO v3.0.0 — Adaptive Operational Experience Workspace',
            'roleData' => $roleData,
        ]);
    }

    /**
     * GET /experience/mobile-field/(:num)
     * Mobile Field Execution Pipeline API (Phase 4C)
     */
    public function mobileField(int $assetId): ResponseInterface
    {
        $mobile = $this->mobileService->getMobileExecutionPipeline($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $mobile,
        ]);
    }

    /**
     * POST /experience/handoff
     * Contextual Operational Handoff Continuity API (Phase 4C)
     */
    public function handoff(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $fromActor     = $json['from_actor'] ?? 'SUPERVISOR_ULP';
        $toActor       = $json['to_actor'] ?? 'PETUGAS_LAPANGAN';
        $assetId       = (int)($json['asset_id'] ?? 1);
        $handoffReason = $json['handoff_reason'] ?? 'Penugasan eksekusi pemangkasan ROW vegetasi ke regu lapangan.';

        $result = $this->handoffService->recordOperationalHandoff($fromActor, $toActor, $assetId, $handoffReason);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
