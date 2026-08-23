<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\EnterpriseSecretManagementService;
use App\Services\SessionTrustFabricService;
use App\Services\StepUpGrantLifecycleService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseSecurityHardeningController extends BaseController
{
    protected EnterpriseSecretManagementService $secretService;
    protected SessionTrustFabricService $sessionService;
    protected StepUpGrantLifecycleService $stepUpLifecycleService;

    public function __construct()
    {
        $this->secretService          = new EnterpriseSecretManagementService();
        $this->sessionService         = new SessionTrustFabricService();
        $this->stepUpLifecycleService = new StepUpGrantLifecycleService();
    }

    /**
     * GET /security/hardening-status
     * Security Hardening & Secret Management View (Phase 5B)
     */
    public function index()
    {
        $secretHealth  = $this->secretService->getSecretBoundaryHealth();
        $sessionTrust  = $this->sessionService->evaluateSessionTrust('SESS-SDA-20260822-001');

        return view('enterprise_security/hardening', [
            'title'        => 'SIDAK TEJO v3.0.0 — Security Hardening & Secret Management',
            'secretHealth' => $secretHealth,
            'sessionTrust' => $sessionTrust['session_trust'] ?? [],
        ]);
    }

    /**
     * POST /security/secret-audit
     * Audit Secret Boundary API (Phase 5B)
     */
    public function secretAudit(): ResponseInterface
    {
        $health = $this->secretService->getSecretBoundaryHealth();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $health,
        ]);
    }

    /**
     * POST /security/revoke-session
     * Instant Session Revocation API (Phase 5B)
     */
    public function revokeSession(): ResponseInterface
    {
        $json      = $this->request->getJSON(true) ?? [];
        $sessionId = $json['session_id'] ?? 'SESS-SDA-20260822-001';
        $reason    = $json['reason'] ?? 'Suspicious concurrent login anomaly detected.';

        $result = $this->sessionService->revokeSession($sessionId, $reason);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
