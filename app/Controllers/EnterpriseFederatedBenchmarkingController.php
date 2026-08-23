<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\FederatedCrossUnitBenchmarkingService;
use App\Services\OperationalKnowledgeTransferService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseFederatedBenchmarkingController extends BaseController
{
    protected FederatedCrossUnitBenchmarkingService $benchmarkService;
    protected OperationalKnowledgeTransferService $knowledgeService;

    public function __construct()
    {
        $this->benchmarkService = new FederatedCrossUnitBenchmarkingService();
        $this->knowledgeService = new OperationalKnowledgeTransferService();
    }

    /**
     * GET /federated-benchmarking/control-center
     * Enterprise Federated Benchmarking Control View (Phase 7O)
     */
    public function index()
    {
        $benchRes     = $this->benchmarkService->benchmarkCrossUnitPerformance(1);
        $knowledgeRes = $this->knowledgeService->proposeKnowledgeTransfer(1);

        return view('enterprise_federated_benchmarking/index', [
            'title'              => 'SIDAK TEJO v3.0.0 — Enterprise Operational Twin Federation & Cross-Unit Benchmarking Center',
            'federatedBenchmark' => $benchRes['federated_benchmark'] ?? [],
            'knowledgeAdvisory'  => $knowledgeRes['knowledge_advisory'] ?? [],
        ]);
    }

    /**
     * GET /federated-benchmarking/benchmarking-snapshot
     * Cross-Unit Benchmarking Snapshot API (Phase 7O)
     */
    public function benchmarkingSnapshot(): ResponseInterface
    {
        $result = $this->benchmarkService->benchmarkCrossUnitPerformance(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /federated-benchmarking/knowledge-advisory
     * Knowledge Transfer Advisory API (Phase 7O)
     */
    public function knowledgeAdvisory(): ResponseInterface
    {
        $result = $this->knowledgeService->proposeKnowledgeTransfer(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
