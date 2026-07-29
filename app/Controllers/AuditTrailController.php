<?php

namespace App\Controllers;

use App\Services\AuditTrailService;
use App\Repositories\TemuanRepository;

class AuditTrailController extends BaseController
{
    private AuditTrailService $auditService;
    private TemuanRepository $temuanRepo;

    public function __construct()
    {
        $this->auditService = new AuditTrailService();
        $this->temuanRepo   = new TemuanRepository();
    }

    public function index()
    {
        $filters = [
            'username'  => $this->request->getGet('username'),
            'aktivitas' => $this->request->getGet('aktivitas'),
            'temuan_id' => $this->request->getGet('temuan_id'),
        ];

        $logs = $this->auditService->getFilteredLogs($filters, 100);

        return view('audit_trail/index', [
            'logs' => $logs,
            'filters' => $filters,
            'userRole' => session()->get('user_role'),
        ]);
    }

    public function evidence(int $id)
    {
        $temuan = $this->temuanRepo->find($id);
        if (!$temuan) {
            return redirect()->to(site_url('temuan'))->with('error', 'Temuan tidak ditemukan.');
        }

        $versions = $this->auditService->getTimeMachineVersions($id);

        return view('audit_trail/evidence', [
            'temuan' => $temuan,
            'versions' => $versions,
            'userRole' => session()->get('user_role'),
        ]);
    }

    public function timeMachine(int $id)
    {
        $versions = $this->auditService->getTimeMachineVersions($id);
        return $this->response->setStatusCode(200)->setJSON([
            'success' => true,
            'versions' => $versions
        ]);
    }
}
