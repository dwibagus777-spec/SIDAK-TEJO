<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\AssetCorpusCompletionService;
use CodeIgniter\HTTP\ResponseInterface;

class AssetCorpusCompletionController extends BaseController
{
    protected $service;

    public function __construct()
    {
        $this->service = new AssetCorpusCompletionService();
    }

    /**
     * Render Asset Corpus Reconciliation Workspace UI
     */
    public function workspace()
    {
        $summary = $this->service->getOperationalSummary();
        $audit   = $this->service->runCanonicalReconciliationAudit();

        $data = [
            'title'   => 'Asset Corpus Reconciliation & Completion Workspace (D1)',
            'summary' => $summary,
            'audit'   => $audit
        ];

        return view('asset_corpus/workspace', $data);
    }

    /**
     * API: Get Top Summary & Baseline KPIs
     */
    public function summary(): ResponseInterface
    {
        $summary = $this->service->getOperationalSummary();
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $summary
        ]);
    }

    /**
     * API: Run Canonical Reconciliation Audit
     */
    public function reconcileAudit(): ResponseInterface
    {
        $audit = $this->service->runCanonicalReconciliationAudit();
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $audit
        ]);
    }

    /**
     * API: Execute Governed Asset Commit
     */
    public function commitGoverned(): ResponseInterface
    {
        $audit = $this->service->runCanonicalReconciliationAudit();
        $result = $this->service->commitGovernedReconciliation($audit);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result
        ]);
    }

    /**
     * API: Get Forensic Bundle
     */
    public function forensicBundle(string $id): ResponseInterface
    {
        $bundle = $this->service->getAssetForensicBundle($id);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $bundle
        ]);
    }
}
