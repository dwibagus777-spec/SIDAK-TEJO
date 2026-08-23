<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\OperationalPlanningCandidateService;

/**
 * Operational Planning Controller (Wave 2 Phase OP-01)
 *
 * Responsibilities:
 * - Governed Planning Candidate Bridge UI & REST API.
 * - Invariant: PLANNING_CANDIDATE != WORK_ORDER, ZERO_AUTO_EXECUTION.
 */
class OperationalPlanningController extends ResourceController
{
    protected $format = 'json';
    protected OperationalPlanningCandidateService $planningService;

    public function __construct()
    {
        $this->planningService = new OperationalPlanningCandidateService();
    }

    /**
     * GET /operational-planning/candidates
     * Planning Candidate Queue & Promotion Hub
     */
    public function candidates()
    {
        $candidates = $this->planningService->getCandidates();
        $eligibleAdvisories = $this->planningService->getEligibleAdvisories();

        $data = [
            'title'              => 'Governed Planning Candidate Queue',
            'candidates'         => $candidates,
            'eligibleAdvisories' => $eligibleAdvisories,
        ];

        return view('operational_planning/candidates', $data);
    }

    /**
     * POST /operational-planning/candidates/promote
     */
    public function promote()
    {
        $snapshotId = (int)($this->request->getPost('snapshot_id') ?? 0);
        $title      = (string)($this->request->getPost('proposed_work_title') ?? '');
        $scope      = (string)($this->request->getPost('proposed_work_scope') ?? '');
        $days       = (int)($this->request->getPost('target_completion_days') ?? 7);
        $rationale  = (string)($this->request->getPost('promotion_rationale') ?? '');

        $workData = [
            'proposed_work_title'    => $title,
            'proposed_work_scope'    => $scope,
            'target_completion_days' => $days,
        ];

        $result = $this->planningService->promoteAdvisoryToCandidate($snapshotId, $workData, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Advisory berhasil dipromosikan menjadi Kandidat Perencanaan {$result['candidate_code']}.");
        } else {
            session()->setFlashdata('error', "Gagal promosi: " . ($result['message'] ?? 'Advisory tidak memenuhi syarat.'));
        }

        return redirect()->to(base_url('operational-planning/candidates'));
    }

    /**
     * GET /operational-planning/candidates/(:num)
     */
    public function detail($candidateId = null)
    {
        $cId = (int)($candidateId ?? 1);
        $data = $this->planningService->getCandidateDetail($cId);

        if (empty($data['candidate'])) {
            session()->setFlashdata('error', "Kandidat Perencanaan #{$cId} tidak ditemukan.");
            return redirect()->to(base_url('operational-planning/candidates'));
        }

        $data['title'] = "Planning Candidate Detail — {$data['candidate']['candidate_code']}";

        return view('operational_planning/candidate_detail', $data);
    }

    /**
     * POST /operational-planning/candidates/(:num)/transition
     */
    public function transition($candidateId = null)
    {
        $cId = (int)($candidateId ?? 1);
        $toStatus  = (string)($this->request->getPost('to_status') ?? '');
        $rationale = (string)($this->request->getPost('decision_rationale') ?? '');
        $notes     = (string)($this->request->getPost('decision_notes') ?? '');

        $result = $this->planningService->transitionCandidateStatus($cId, $toStatus, $rationale, $notes);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Status kandidat berhasil diubah menjadi: {$toStatus}");
        } else {
            session()->setFlashdata('error', "Gagal mengubah status: " . ($result['message'] ?? 'Alasan tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/candidates/' . $cId));
    }

    /**
     * GET /operational-planning/api/candidates
     */
    public function apiCandidates()
    {
        $candidates = $this->planningService->getCandidates();
        return $this->respond([
            'status'     => 'success',
            'candidates' => $candidates,
            'governance' => 'PLANNING_CANDIDATE_READ_MODEL',
        ]);
    }
}
