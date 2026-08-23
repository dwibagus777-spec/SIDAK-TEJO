<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\EnterpriseIdentitySecurityService;
use App\Services\ZeroTrustAccessService;
use App\Services\StepUpAuthorizationService;
use App\Services\SecurityAuditFabricService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseSecurityController extends BaseController
{
    protected EnterpriseIdentitySecurityService $identityService;
    protected ZeroTrustAccessService $zeroTrustService;
    protected StepUpAuthorizationService $stepUpService;
    protected SecurityAuditFabricService $auditService;

    public function __construct()
    {
        $this->identityService  = new EnterpriseIdentitySecurityService();
        $this->zeroTrustService  = new ZeroTrustAccessService();
        $this->stepUpService    = new StepUpAuthorizationService();
        $this->auditService     = new SecurityAuditFabricService();
    }

    /**
     * GET /security/zero-trust-status
     * Enterprise Zero-Trust Access & Security Fabric View (Phase 5A)
     */
    public function index()
    {
        $identity  = $this->identityService->getIdentitySecurityContext(1);
        $access    = $this->zeroTrustService->evaluateAccess('SUPERVISOR_ULP', 'APPROVE_RECOMMENDATION', 1);
        $audit     = $this->auditService->recordSecurityAudit('ZERO_TRUST_EVAL', 'supervisor.sidoarjokota@pln.co.id', 'ALLOW');

        return view('enterprise_security/index', [
            'title'    => 'SIDAK TEJO v3.0.0 — Enterprise Security & Zero-Trust Access Fabric',
            'identity' => $identity['identity_context'] ?? [],
            'access'   => $access['access_evaluation'] ?? [],
            'audit'    => $audit['security_audit_record'] ?? [],
        ]);
    }

    /**
     * POST /security/evaluate-access
     * Access Evaluation API (Phase 5A)
     */
    public function evaluateAccess(): ResponseInterface
    {
        $json   = $this->request->getJSON(true) ?? [];
        $role   = $json['role'] ?? 'SUPERVISOR_ULP';
        $action = $json['requested_action'] ?? 'APPROVE_RECOMMENDATION';

        $result = $this->zeroTrustService->evaluateAccess($role, $action, 1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /security/step-up
     * Step-Up Re-Authorization API (Phase 5A)
     */
    public function stepUp(): ResponseInterface
    {
        $json   = $this->request->getJSON(true) ?? [];
        $action = $json['target_action'] ?? 'EMERGENCY_OVERRIDE';

        $result = $this->stepUpService->requestStepUpChallenge($action, 1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
