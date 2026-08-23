<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OrganizationalAuthorityService;
use App\Services\DelegationAuthorityService;
use App\Services\OnCallRosterService;
use App\Services\EscalationPolicyService;
use CodeIgniter\HTTP\ResponseInterface;

class OperationalAuthorityController extends BaseController
{
    protected OrganizationalAuthorityService $orgAuthorityService;
    protected DelegationAuthorityService $delegationService;
    protected OnCallRosterService $rosterService;
    protected EscalationPolicyService $escalationService;

    public function __construct()
    {
        $this->orgAuthorityService = new OrganizationalAuthorityService();
        $this->delegationService   = new DelegationAuthorityService();
        $this->rosterService       = new OnCallRosterService();
        $this->escalationService   = new EscalationPolicyService();
    }

    /**
     * GET /authority/matrix-status
     * Organizational Structure & Role Decision Authority Matrix API (Phase 3F)
     */
    public function matrixStatus(): ResponseInterface
    {
        $matrix = $this->orgAuthorityService->getOrganizationalStructureAndAuthorityMatrix();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $matrix,
        ]);
    }

    /**
     * GET /authority/delegation-rule/(:segment)
     * Resolve Active Authority & Delegation Rule API (Phase 3F)
     */
    public function delegationRule(string $roleName = 'SUPERVISOR_ULP'): ResponseInterface
    {
        $delegation = $this->delegationService->resolveActiveAuthority($roleName);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $delegation,
        ]);
    }

    /**
     * GET /authority/shift-roster
     * Active 24/7 Shift & On-Call Roster Schedule API (Phase 3F)
     */
    public function shiftRoster(): ResponseInterface
    {
        $roster = $this->rosterService->getActiveShiftRoster();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $roster,
        ]);
    }

    /**
     * GET /authority/escalate-alert/(:num)
     * Evaluate Alert Escalation Policy API for specific Case (Phase 3F)
     */
    public function escalateAlert(int $caseId): ResponseInterface
    {
        $elapsedHours = (int)($this->request->getGet('elapsed_hours') ?? 5);
        $escalation   = $this->escalationService->evaluateAlertEscalationPolicy($caseId, $elapsedHours);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $escalation,
        ]);
    }
}
