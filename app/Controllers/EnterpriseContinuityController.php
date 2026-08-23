<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\BackupRecoveryOrchestrationService;
use App\Services\DisasterRecoveryReadinessService;
use App\Services\RecoveryIntegrityVerificationService;
use App\Services\BusinessContinuityService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseContinuityController extends BaseController
{
    protected BackupRecoveryOrchestrationService $backupService;
    protected DisasterRecoveryReadinessService $drService;
    protected RecoveryIntegrityVerificationService $integrityService;
    protected BusinessContinuityService $continuityService;

    public function __construct()
    {
        $this->backupService   = new BackupRecoveryOrchestrationService();
        $this->drService       = new DisasterRecoveryReadinessService();
        $this->integrityService= new RecoveryIntegrityVerificationService();
        $this->continuityService= new BusinessContinuityService();
    }

    /**
     * GET /continuity/dr-status
     * Enterprise Disaster Recovery & Business Continuity Dashboard View (Phase 5C)
     */
    public function index()
    {
        $drReadiness = $this->drService->getDisasterRecoveryReadinessScore();
        $continuity  = $this->continuityService->getOperationalContinuityMode();

        return view('enterprise_continuity/index', [
            'title'       => 'SIDAK TEJO v3.0.0 — Enterprise Disaster Recovery & Business Continuity',
            'drReadiness' => $drReadiness['dr_readiness'] ?? [],
            'continuity'  => $continuity['continuity_mode'] ?? [],
        ]);
    }

    /**
     * POST /continuity/create-recovery-point
     * Create Recovery Point API (Phase 5C)
     */
    public function createRecoveryPoint(): ResponseInterface
    {
        $json  = $this->request->getJSON(true) ?? [];
        $label = $json['label'] ?? 'MANUAL_EXECUTIVE_BACKUP';

        $result = $this->backupService->createRecoveryPoint($label);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /continuity/verify-integrity
     * Post-Restore Integrity Verification API (Phase 5C)
     */
    public function verifyIntegrity(): ResponseInterface
    {
        $result = $this->integrityService->verifyPostRestoreIntegrity();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
