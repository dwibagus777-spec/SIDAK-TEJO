<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Operational Planning Workspace Service (Wave 2 Phase OP-02)
 *
 * Responsibilities:
 * - Human Operational Planning Workspace & Scope Governance.
 * - Enforces:
 *     PLANNING_INTENT != WORK_ORDER
 *     DUPLICATE_ACTIVE_PLAN_CREATION = REJECTED
 *     PLAN_SOURCE_REBINDING = FORBIDDEN
 *     TERMINAL_OR_REVIEW_DECISION_WITHOUT_RATIONALE = REJECTED
 *     MATERIAL_STATUS = INDICATIVE_ESTIMATE_ONLY
 *     SCHEDULE_STATUS = PROPOSED_WINDOW_ONLY
 *     ZERO_AUTONOMOUS_EXECUTION = ENFORCED
 */
class OperationalPlanningWorkspaceService
{
    public const ALLOWED_PLAN_TRANSITIONS = [
        'PLAN_DRAFT'            => ['UNDER_PLANNING_REVIEW'],
        'UNDER_PLANNING_REVIEW' => ['APPROVED_FOR_PORTFOLIO', 'REVISION_REQUIRED'],
        'REVISION_REQUIRED'     => ['PLAN_DRAFT'],
        'APPROVED_FOR_PORTFOLIO'=> [], // Terminal for OP-02 (Ready for OP-03/OP-04)
    ];

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Human-Initiated Creation of an Operational Plan Draft.
     *
     * @param int $candidateId
     * @param array $planData
     * @param array|null $actor
     * @return array
     */
    public function createPlanDraft(int $candidateId, array $planData, ?array $actor = null): array
    {
        // 1. Candidate Eligibility Check
        $candidate = $this->db->table('operational_planning_candidates')
                              ->where('id', $candidateId)
                              ->get()
                              ->getRowArray();

        if (!$candidate) {
            return [
                'status'  => 'error',
                'message' => "Planning Candidate #{$candidateId} not found.",
                'code'    => 'CANDIDATE_NOT_FOUND',
            ];
        }

        if ($candidate['candidate_status'] !== 'ACCEPTED_AS_PLANNING_INTENT') {
            return [
                'status'  => 'error',
                'message' => "Candidate #{$candidateId} is '{$candidate['candidate_status']}', but must be 'ACCEPTED_AS_PLANNING_INTENT' to create a plan.",
                'code'    => 'CANDIDATE_NOT_ELIGIBLE_FOR_PLANNING',
            ];
        }

        // 2. Candidate Exclusivity / Idempotency Check
        $activePlan = $this->db->table('operational_plans')
                               ->where('candidate_id', $candidateId)
                               ->whereIn('plan_status', ['PLAN_DRAFT', 'UNDER_PLANNING_REVIEW', 'APPROVED_FOR_PORTFOLIO', 'REVISION_REQUIRED'])
                               ->get()
                               ->getRowArray();

        if ($activePlan) {
            return [
                'status'  => 'error',
                'message' => "Candidate #{$candidateId} already has an active operational plan: {$activePlan['plan_code']} ({$activePlan['plan_status']}).",
                'code'    => 'DUPLICATE_ACTIVE_PLAN_CREATION',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorId   = $actor['id'] ?? (function_exists('session') ? session()->get('user_id') : null);
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'HUMAN_PLANNER';
        $actorRole = $actor['role'] ?? (function_exists('session') ? session()->get('role') : null) ?? 'PERENCANA_PEMELIHARAAN';

        $planCode = 'PLAN-DOC-STJ-' . date('Ymd') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $category = $planData['work_category'] ?? 'ROW_CLEARANCE';
        $scopeNarrative = !empty($planData['work_scope_narrative'])
            ? trim($planData['work_scope_narrative'])
            : $candidate['proposed_work_scope'];

        $safety = !empty($planData['safety_precautions'])
            ? trim($planData['safety_precautions'])
            : "Wajib menggunakan APD Lengkap (Helm, Rompi, Sarung Tangan 20kV, Safety Shoes). Lakukan grounding lokal sebelum pekerjaan dimulai.";

        $outage = !empty($planData['outage_required']) ? 1 : 0;
        $startWindow = !empty($planData['proposed_execution_window_start']) ? $planData['proposed_execution_window_start'] : date('Y-m-d 08:00:00', strtotime('+3 days'));
        $endWindow   = !empty($planData['proposed_execution_window_end']) ? $planData['proposed_execution_window_end'] : date('Y-m-d 16:00:00', strtotime('+3 days'));

        $materials = $planData['indicative_materials'] ?? [
            ['material_name' => 'Kabel SUTM / A3C', 'quantity' => 10, 'unit' => 'meter'],
            ['material_name' => 'Isolator Tumpu 20kV', 'quantity' => 2, 'unit' => 'buah'],
        ];

        // 3. Persist Immutable Lineage Operational Plan Record
        $planRecord = [
            'plan_code'                       => $planCode,
            'candidate_id'                    => $candidateId,
            'candidate_code'                  => $candidate['candidate_code'],
            'snapshot_id'                     => $candidate['snapshot_id'],
            'snapshot_code'                   => $candidate['snapshot_code'],
            'source_planning_intent_status'   => 'ACCEPTED_AS_PLANNING_INTENT',
            'penyulang_id'                    => $candidate['penyulang_id'],
            'feeder_name'                     => $candidate['feeder_name'],
            'section_id'                      => $candidate['section_id'],
            'section_name'                    => $candidate['section_name'],
            'plan_status'                     => 'PLAN_DRAFT',
            'work_category'                   => $category,
            'work_scope_narrative'            => $scopeNarrative,
            'safety_precautions'              => $safety,
            'outage_required'                 => $outage,
            'proposed_execution_window_start' => $startWindow,
            'proposed_execution_window_end'   => $endWindow,
            'indicative_materials_json'       => json_encode($materials, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'material_status'                 => 'INDICATIVE_ESTIMATE_ONLY',
            'schedule_status'                 => 'PROPOSED_WINDOW_ONLY',
            'planner_actor_id'                => $actorId,
            'planner_actor_name'              => $actorName,
            'planner_actor_role'              => $actorRole,
            'created_at'                      => $now,
            'updated_at'                      => $now,
        ];

        $this->db->table('operational_plans')->insert($planRecord);
        $planId = (int)$this->db->insertID();

        return [
            'status'             => 'success',
            'plan_id'            => $planId,
            'plan_code'          => $planCode,
            'candidate_id'       => $candidateId,
            'candidate_code'     => $candidate['candidate_code'],
            'plan_status'        => 'PLAN_DRAFT',
            'material_status'    => 'INDICATIVE_ESTIMATE_ONLY',
            'schedule_status'    => 'PROPOSED_WINDOW_ONLY',
            'governance_verdict' => 'HUMAN_PLAN_DRAFT_CREATED_LINEAGE_BOUND',
        ];
    }

    /**
     * Governed State Machine Transition for Operational Plan.
     *
     * @param int $planId
     * @param string $toStatus
     * @param string $rationale Mandatory review rationale
     * @param array|null $actor
     * @return array
     */
    public function transitionPlanStatus(
        int $planId,
        string $toStatus,
        string $rationale,
        ?array $actor = null
    ): array {
        $plan = $this->db->table('operational_plans')
                         ->where('id', $planId)
                         ->get()
                         ->getRowArray();

        if (!$plan) {
            return [
                'status'  => 'error',
                'message' => "Operational Plan #{$planId} not found.",
                'code'    => 'PLAN_NOT_FOUND',
            ];
        }

        $fromStatus = $plan['plan_status'];
        $targetStatus = strtoupper(trim($toStatus));
        $cleanRationale = trim($rationale);

        // Mandatory rationale check for reviews
        if ($cleanRationale === '' && in_array($targetStatus, ['APPROVED_FOR_PORTFOLIO', 'REVISION_REQUIRED'], true)) {
            return [
                'status'  => 'error',
                'message' => "Review rationale is mandatory when transitioning plan to {$targetStatus}.",
                'code'    => 'MANDATORY_REVIEW_RATIONALE_REQUIRED',
            ];
        }

        // Validate allowed transitions
        $allowedNext = self::ALLOWED_PLAN_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($targetStatus, $allowedNext, true)) {
            return [
                'status'  => 'error',
                'message' => "Invalid transition from {$fromStatus} to {$targetStatus}. Allowed: " . implode(', ', $allowedNext ?: ['NONE (Terminal State)']),
                'code'    => 'INVALID_PLAN_TRANSITION',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $reviewerName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'LEAD_PLANNER_REVIEWER';

        $updateData = [
            'plan_status' => $targetStatus,
            'updated_at'  => $now,
        ];

        if ($targetStatus === 'APPROVED_FOR_PORTFOLIO') {
            $updateData['reviewer_actor_name'] = $reviewerName;
            $updateData['review_rationale']    = $cleanRationale;
        } elseif ($targetStatus === 'REVISION_REQUIRED') {
            $updateData['revision_reason']        = $cleanRationale;
            $updateData['revision_requested_by']  = $reviewerName;
            $updateData['revision_requested_at']  = $now;
        }

        $this->db->table('operational_plans')
                 ->where('id', $planId)
                 ->update($updateData);

        return [
            'status'             => 'success',
            'plan_id'            => $planId,
            'plan_code'          => $plan['plan_code'],
            'from_status'        => $fromStatus,
            'to_status'          => $targetStatus,
            'reviewer_name'      => $reviewerName,
            'review_rationale'   => $cleanRationale,
            'governance_verdict' => 'GOVERNED_PLAN_REVIEW_TRANSITION_VERIFIED',
        ];
    }

    /**
     * Get list of plans with optional filters.
     */
    public function getPlans(array $filters = []): array
    {
        if (!$this->db->tableExists('operational_plans')) {
            return [];
        }

        $builder = $this->db->table('operational_plans');

        if (!empty($filters['status'])) {
            $builder->where('plan_status', $filters['status']);
        }
        if (!empty($filters['penyulang_id'])) {
            $builder->where('penyulang_id', (int)$filters['penyulang_id']);
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * Get accepted candidates ready for planning draft creation.
     */
    public function getAcceptedCandidatesReadyForPlan(): array
    {
        if (!$this->db->tableExists('operational_planning_candidates')) {
            return [];
        }

        $candidates = $this->db->table('operational_planning_candidates')
                               ->where('candidate_status', 'ACCEPTED_AS_PLANNING_INTENT')
                               ->get()
                               ->getResultArray();

        $ready = [];
        foreach ($candidates as $cnd) {
            $planCount = $this->db->table('operational_plans')
                                  ->where('candidate_id', $cnd['id'])
                                  ->whereIn('plan_status', ['PLAN_DRAFT', 'UNDER_PLANNING_REVIEW', 'APPROVED_FOR_PORTFOLIO', 'REVISION_REQUIRED'])
                                  ->countAllResults();
            if ($planCount === 0) {
                $ready[] = $cnd;
            }
        }

        return $ready;
    }

    /**
     * Get Plan Detail with full Wave 1 and OP-01 Lineage.
     */
    public function getPlanDetail(int $planId): array
    {
        $plan = $this->db->table('operational_plans')
                         ->where('id', $planId)
                         ->get()
                         ->getRowArray();

        if (!$plan) {
            return [];
        }

        $materials = !empty($plan['indicative_materials_json'])
            ? json_decode($plan['indicative_materials_json'], true)
            : [];

        $candidate = $this->db->table('operational_planning_candidates')
                              ->where('id', $plan['candidate_id'])
                              ->get()
                              ->getRowArray();

        $snapshot = $this->db->table('preventive_risk_advisory_snapshots')
                             ->where('id', $plan['snapshot_id'])
                             ->get()
                             ->getRowArray();

        return [
            'plan'               => $plan,
            'materials'          => $materials,
            'candidate'          => $candidate,
            'snapshot'           => $snapshot,
            'lineage_invariants' => [
                'plan_source_rebinding_locked' => true,
                'material_status'              => $plan['material_status'],
                'schedule_status'              => $plan['schedule_status'],
                'work_order_created'           => false,
                'crew_dispatched'              => false,
            ],
        ];
    }
}
