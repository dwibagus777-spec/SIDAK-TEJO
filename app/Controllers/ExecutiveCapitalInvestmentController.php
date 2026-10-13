<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\ExecutiveCapitalInvestmentService;

/**
 * Executive Reliability Capital Allocation & Investment Controller (B8.10 Web & API Surface)
 */
class ExecutiveCapitalInvestmentController extends ResourceController
{
    protected $format = 'json';
    protected ExecutiveCapitalInvestmentService $investmentService;

    public function __construct()
    {
        $this->investmentService = new ExecutiveCapitalInvestmentService();
    }

    /**
     * GET /capital-investment/workspace
     */
    public function workspace()
    {
        $summary = $this->investmentService->getOperationalSummary();

        $data = [
            'title'   => 'B8.10 Executive Reliability Capital Allocation & Investment Optimization Center',
            'summary' => $summary,
        ];

        return view('capital_investment/workspace', $data);
    }

    /**
     * GET /api/capital-investment/summary
     */
    public function apiSummary()
    {
        $summary = $this->investmentService->getOperationalSummary();
        return $this->respond($summary);
    }

    /**
     * POST /api/capital-investment/assemble
     */
    public function apiAssemble()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $actor = ['name' => session()->get('nama_lengkap') ?? 'SENIOR_ENGINEER_PERENCANAAN_UP3'];

        $result = $this->investmentService->assembleCapitalPortfolio($json, $actor);

        if ($result['status'] === 'success') {
            return $this->respondCreated($result);
        }

        return $this->fail($result['message'] ?? 'Gagal merakit draf portofolio investasi.', 400);
    }

    /**
     * POST /api/capital-investment/simulate-saidi
     */
    public function apiSimulateSaidi()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $pId  = (int)($json['portfolio_id'] ?? 1);

        $result = $this->investmentService->simulateSaidiSaifiReduction($pId);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal simulasi reduksi SAIDI/SAIFI.', 400);
    }

    /**
     * POST /api/capital-investment/ratify-executive
     */
    public function apiRatifyExecutive()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $pId       = (int)($json['portfolio_id'] ?? 0);
        $rationale = (string)($json['rationale'] ?? '');
        $signoff   = (string)($json['executive_signoff'] ?? 'APPROVED');
        $actor     = ['name' => session()->get('nama_lengkap') ?? 'SENIOR_MANAGER_UP3'];

        $result = $this->investmentService->ratifyExecutivePortfolio($pId, $rationale, $signoff, $actor);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal ratifikasi eksekutif portofolio.', 400);
    }
}
