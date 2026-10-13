<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\TranslineConnectivityCompletionService;
use CodeIgniter\HTTP\ResponseInterface;

class TranslineConnectivityCompletionController extends BaseController
{
    protected $service;

    public function __construct()
    {
        $this->service = new TranslineConnectivityCompletionService();
    }

    /**
     * Render Track D2 Network Connectivity Workspace UI
     */
    public function workspace()
    {
        $summary   = $this->service->getOperationalSummary();
        $scan      = $this->service->scanConnectivityCandidates();
        $proposals = $this->service->listProposals(10);

        $data = [
            'title'     => 'Transline Network Connectivity Completion Workspace (D2)',
            'summary'   => $summary,
            'scan'      => $scan,
            'proposals' => $proposals
        ];

        return view('transline_completion/workspace', $data);
    }

    /**
     * API: Get Operational Summary & Baseline KPIs
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
     * API: Scan Connectivity Candidates
     */
    public function scanCandidates(): ResponseInterface
    {
        $scan = $this->service->scanConnectivityCandidates();
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $scan
        ]);
    }

    /**
     * API: Promote Candidate to Authoritative
     */
    public function promoteCandidate(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $uuid = $json['proposal_uuid'] ?? '';
        $approver = $json['promoted_by'] ?? 'SUPERVISOR_MANAGER';

        $result = $this->service->promoteCandidateToAuthoritative($uuid, $approver);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result
        ]);
    }

    /**
     * API: Get Forensic Bundle
     */
    public function forensicBundle(string $uuid): ResponseInterface
    {
        $bundle = $this->service->getCandidateForensicBundle($uuid);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $bundle
        ]);
    }
}
