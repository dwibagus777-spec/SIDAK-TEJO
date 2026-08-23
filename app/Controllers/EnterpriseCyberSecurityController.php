<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\CyberPhysicalTelemetryIntegrityService;
use App\Services\CyberPhysicalSecurityAdvisoryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseCyberSecurityController extends BaseController
{
    protected CyberPhysicalTelemetryIntegrityService $integrityService;
    protected CyberPhysicalSecurityAdvisoryService $securityService;

    public function __construct()
    {
        $this->integrityService = new CyberPhysicalTelemetryIntegrityService();
        $this->securityService  = new CyberPhysicalSecurityAdvisoryService();
    }

    /**
     * GET /cyber-security/control-center
     * Enterprise Grid Cyber-Physical Immunity Control View (Phase 7T)
     */
    public function index()
    {
        $intRes = $this->integrityService->auditTelemetryIntegrity(1);
        $secRes = $this->securityService->recommendCyberSecurityAdvisory(1);

        return view('enterprise_cyber_security/index', [
            'title'              => 'SIDAK TEJO v3.0.0 — Enterprise Grid Cyber-Physical Immunity Center',
            'telemetryIntegrity' => $intRes['telemetry_integrity'] ?? [],
            'securityAdvisory'   => $secRes['security_advisory'] ?? [],
        ]);
    }

    /**
     * GET /cyber-security/telemetry-snapshot
     * Telemetry Integrity Snapshot API (Phase 7T)
     */
    public function telemetrySnapshot(): ResponseInterface
    {
        $result = $this->integrityService->auditTelemetryIntegrity(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /cyber-security/security-advisory
     * Cyber Security Advisory API (Phase 7T)
     */
    public function securityAdvisory(): ResponseInterface
    {
        $result = $this->securityService->recommendCyberSecurityAdvisory(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
