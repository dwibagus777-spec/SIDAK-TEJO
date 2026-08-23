<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\OperationalPlanningWorkspaceService;
use App\Services\OperationalPlanningCandidateService;

/**
 * Operational Planning Workspace Controller (Wave 2 Phase OP-02)
 *
 * Responsibilities:
 * - Human Operational Planning Workspace & Scope Governance UI & API.
 * - Invariant: PLANNING_DRAFT != WORK_ORDER, ZERO_AUTO_EXECUTION.
 */
class OperationalPlanningWorkspaceController extends ResourceController
{
    protected $format = 'json';
    protected OperationalPlanningWorkspaceService $workspaceService;
    protected OperationalPlanningCandidateService $candidateService;

    public function __construct()
    {
        $this->workspaceService = new OperationalPlanningWorkspaceService();
        $this->candidateService = new OperationalPlanningCandidateService();
    }

    /**
     * GET /operational-planning/workspace
     * Workspace Dashboard & Drafts Queue
     */
    public function index()
    {
        $plans = $this->workspaceService->getPlans();
        $readyCandidates = $this->workspaceService->getAcceptedCandidatesReadyForPlan();

        $data = [
            'title'           => 'Human Operational Planning Workspace',
            'plans'           => $plans,
            'readyCandidates' => $readyCandidates,
        ];

        return view('operational_planning/workspace_list', $data);
    }

    /**
     * GET /operational-planning/workspace/create/(:num)
     */
    public function create($candidateId = null)
    {
        $cId = (int)($candidateId ?? 1);
        $candidateDetail = $this->candidateService->getCandidateDetail($cId);

        if (empty($candidateDetail['candidate'])) {
            session()->setFlashdata('error', "Kandidat Perencanaan #{$cId} tidak ditemukan.");
            return redirect()->to(base_url('operational-planning/workspace'));
        }

        $candidate = $candidateDetail['candidate'];
        if ($candidate['candidate_status'] !== 'ACCEPTED_AS_PLANNING_INTENT') {
            session()->setFlashdata('error', "Kandidat berstatus '{$candidate['candidate_status']}', hanya status 'ACCEPTED_AS_PLANNING_INTENT' yang dapat disusun rencananya.");
            return redirect()->to(base_url('operational-planning/workspace'));
        }

        $data = [
            'title'     => "Susun Draft Rencana Kerja — {$candidate['candidate_code']}",
            'candidate' => $candidate,
            'snapshot'  => $candidateDetail['source_snapshot'] ?? [],
        ];

        return view('operational_planning/workspace_form', $data);
    }

    /**
     * POST /operational-planning/workspace/store
     */
    public function store()
    {
        $candidateId = (int)($this->request->getPost('candidate_id') ?? 0);
        $category    = (string)($this->request->getPost('work_category') ?? 'ROW_CLEARANCE');
        $scope       = (string)($this->request->getPost('work_scope_narrative') ?? '');
        $safety      = (string)($this->request->getPost('safety_precautions') ?? '');
        $outage      = (int)($this->request->getPost('outage_required') ?? 0);
        $startWindow = (string)($this->request->getPost('proposed_execution_window_start') ?? '');
        $endWindow   = (string)($this->request->getPost('proposed_execution_window_end') ?? '');

        // Indicative materials parsing
        $materialNames = $this->request->getPost('material_name') ?? [];
        $materialQtys  = $this->request->getPost('material_qty') ?? [];
        $materialUnits = $this->request->getPost('material_unit') ?? [];

        $materials = [];
        if (is_array($materialNames)) {
            foreach ($materialNames as $idx => $name) {
                if (!empty(trim($name))) {
                    $materials[] = [
                        'material_name' => trim($name),
                        'quantity'      => (float)($materialQtys[$idx] ?? 1),
                        'unit'          => trim($materialUnits[$idx] ?? 'buah'),
                    ];
                }
            }
        }

        $planData = [
            'work_category'                   => $category,
            'work_scope_narrative'            => $scope,
            'safety_precautions'              => $safety,
            'outage_required'                 => $outage,
            'proposed_execution_window_start' => $startWindow ?: null,
            'proposed_execution_window_end'   => $endWindow ?: null,
            'indicative_materials'            => $materials,
        ];

        $result = $this->workspaceService->createPlanDraft($candidateId, $planData);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Draft Rencana Kerja {$result['plan_code']} berhasil disusun.");
            return redirect()->to(base_url('operational-planning/workspace/detail/' . $result['plan_id']));
        }

        session()->setFlashdata('error', "Gagal menyusun draft: " . ($result['message'] ?? 'Data tidak valid.'));
        return redirect()->to(base_url('operational-planning/workspace'));
    }

    /**
     * GET /operational-planning/workspace/detail/(:num)
     */
    public function detail($planId = null)
    {
        $pId = (int)($planId ?? 1);
        $data = $this->workspaceService->getPlanDetail($pId);

        if (empty($data['plan'])) {
            session()->setFlashdata('error', "Rencana Kerja #{$pId} tidak ditemukan.");
            return redirect()->to(base_url('operational-planning/workspace'));
        }

        $data['title'] = "Operational Plan Detail — {$data['plan']['plan_code']}";

        return view('operational_planning/plan_detail', $data);
    }

    /**
     * POST /operational-planning/workspace/transition/(:num)
     */
    public function transition($planId = null)
    {
        $pId = (int)($planId ?? 1);
        $toStatus  = (string)($this->request->getPost('to_status') ?? '');
        $rationale = (string)($this->request->getPost('review_rationale') ?? '');

        $result = $this->workspaceService->transitionPlanStatus($pId, $toStatus, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Status rencana berhasil diubah menjadi: {$toStatus}");
        } else {
            session()->setFlashdata('error', "Gagal mengubah status: " . ($result['message'] ?? 'Alasan tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/workspace/detail/' . $pId));
    }
}
