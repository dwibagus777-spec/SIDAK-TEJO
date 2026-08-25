<?php

namespace App\Controllers;

use App\Services\MaterialRequestGovernanceService;
use App\Services\ShutdownWorkPlanningService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Material Request Governance & Technical Approval Controller (MR-01 Phase 2)
 */
class MaterialRequestGovernanceController extends BaseController
{
    protected MaterialRequestGovernanceService $requestService;
    protected ShutdownWorkPlanningService $planningService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->requestService  = new MaterialRequestGovernanceService();
        $this->planningService = new ShutdownWorkPlanningService();
    }

    /**
     * Render Material Request Governance Workspace View.
     */
    public function index()
    {
        $govSummary  = $this->requestService->getGovernanceSummary();
        $packages    = $this->requestService->listAllPackages();
        $workPlans   = $this->planningService->getPlanningSummary();

        return view('planning/material_request_workspace', [
            'title'      => 'Material Request Governance & Official SPM Voucher Suite | SIDAK TEJO',
            'summary'    => $govSummary,
            'packages'   => $packages['packages'] ?? [],
            'work_plans' => $workPlans,
        ]);
    }

    /**
     * Render Printable Surat Permintaan Material (SPM) Voucher.
     */
    public function spmVoucher(string $requestNo)
    {
        $result = $this->requestService->getPackageDetail($requestNo);
        if (!$result['success']) {
            return redirect()->to('/planning/material-requests')->with('error', 'Paket pengajuan material tidak ditemukan.');
        }

        return view('planning/spm_printable_voucher', [
            'title'   => "Surat Permintaan Material (SPM) #{$requestNo} | SIDAK TEJO",
            'package' => $result['package'],
        ]);
    }

    /**
     * API: List all packages.
     * GET /api/material-requests/packages
     */
    public function apiListPackages(): ResponseInterface
    {
        return $this->response->setJSON($this->requestService->listAllPackages());
    }

    /**
     * API: Get package detail.
     * GET /api/material-requests/package/(:segment)
     */
    public function apiPackageDetail(string $requestNo): ResponseInterface
    {
        $result = $this->requestService->getPackageDetail($requestNo);
        if (!$result['success']) {
            return $this->response->setStatusCode(404)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Create Draft Package from Work Plan.
     * POST /api/material-requests/create-package
     */
    public function apiCreatePackage(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $planId = $json['plan_id'] ?? '';
        if (empty($planId)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Work Plan ID is required.']);
        }

        $initiator = $json['initiator'] ?? [
            'actor_id'   => 1,
            'actor_name' => 'SUPERVISOR_PEMELIHARAAN_JARINGAN',
            'actor_nip'  => '198403152008121003',
            'actor_role' => 'MAINTENANCE_SUPERVISOR',
        ];

        $result = $this->requestService->createMaterialRequestPackage($planId, $initiator);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Submit Supervisor Technical Review.
     * POST /api/material-requests/technical-review
     */
    public function apiTechnicalReview(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $requestNo        = $json['request_no'] ?? '';
        $reviewedItems    = $json['reviewed_items'] ?? [];
        $supervisorNotes  = $json['supervisor_notes'] ?? 'Diverifikasi sesuai kebutuhan temuan dan kondisi lapangan';
        $supervisor       = $json['supervisor'] ?? [
            'supervisor_name' => 'SUPERVISOR_PEMELIHARAAN_JARINGAN',
            'supervisor_nip'  => '198403152008121003',
            'supervisor_role' => 'MAINTENANCE_SUPERVISOR',
        ];

        if (empty($requestNo)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Request Number is required.']);
        }

        $result = $this->requestService->submitTechnicalReview($requestNo, $reviewedItems, $supervisorNotes, $supervisor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Management Official Approval.
     * POST /api/material-requests/management-approve
     */
    public function apiManagementApprove(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $requestNo       = $json['request_no'] ?? '';
        $decision        = $json['decision'] ?? 'APPROVED';
        $managementNotes = $json['management_notes'] ?? 'Disetujui untuk pemeliharaan preventif jaringan JTM';
        $approver        = $json['approver'] ?? [
            'approver_name' => 'ASMAN_JARINGAN_UP3_SIDOARJO',
            'approver_nip'  => '198205102006041002',
            'approver_role' => 'ASSISTANT_MANAGER_NETWORK',
        ];

        if (empty($requestNo)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Request Number is required.']);
        }

        $result = $this->requestService->approveMaterialRequest($requestNo, $decision, $managementNotes, $approver);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Governance Summary.
     * GET /api/material-requests/summary
     */
    public function apiGovernanceSummary(): ResponseInterface
    {
        return $this->response->setJSON($this->requestService->getGovernanceSummary());
    }
}
