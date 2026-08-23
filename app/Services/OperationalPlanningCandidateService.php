<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Operational Planning Candidate Service (Wave 2 Phase OP-01)
 *
 * Responsibilities:
 * - Governed Planning Candidate Bridge.
 * - Enforces:
 *     ELIGIBILITY = MITIGATION_PLANNED_ONLY
 *     PROMOTION_IDEMPOTENCY = REQUIRED (DUPLICATE_ACTIVE_CANDIDATE_PROMOTION = REJECTED)
 *     PROMOTED_SOURCE_LINEAGE = IMMUTABLY_BOUND
 *     CANDIDATE_SOURCE_REBINDING = FORBIDDEN
 *     TERMINAL_DECISION_RATIONALE = MANDATORY
 *     PLANNING_CANDIDATE != WORK_ORDER
 */
class OperationalPlanningCandidateService
{
    public const ALLOWED_TRANSITIONS = [
        'CANDIDATE_CREATED'     => ['UNDER_PLANNING_REVIEW'],
        'UNDER_PLANNING_REVIEW' => ['ACCEPTED_AS_PLANNING_INTENT', 'DISCARDED'],
        'ACCEPTED_AS_PLANNING_INTENT' => [], // Terminal
        'DISCARDED'             => [],       // Terminal
    ];

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Human-Initiated Promotion of an Advisory to an Operational Planning Candidate.
     *
     * @param int $snapshotId
     * @param array $workData [ 'proposed_work_title' => ..., 'proposed_work_scope' => ..., 'target_completion_days' => ... ]
     * @param string $rationale Mandatory human promotion rationale
     * @param array|null $actor
     * @return array
     */
    public function promoteAdvisoryToCandidate(
        int $snapshotId,
        array $workData,
        string $rationale,
        ?array $actor = null
    ): array {
        $cleanRationale = trim($rationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Promotion rationale is mandatory for creating a planning candidate.',
                'code'    => 'MANDATORY_PROMOTION_RATIONALE_REQUIRED',
            ];
        }

        // 1. Eligibility Check (MITIGATION_PLANNED only)
        $snapshot = $this->db->table('preventive_risk_advisory_snapshots')
                             ->where('id', $snapshotId)
                             ->get()
                             ->getRowArray();

        if (!$snapshot) {
            return [
                'status'  => 'error',
                'message' => "Advisory Snapshot #{$snapshotId} not found.",
                'code'    => 'SNAPSHOT_NOT_FOUND',
            ];
        }

        $currentStatus = $snapshot['governance_status'] ?? 'ADVISORY_PROPOSED';
        if ($currentStatus !== 'MITIGATION_PLANNED') {
            return [
                'status'  => 'error',
                'message' => "Advisory #{$snapshotId} is not eligible for planning. Status is '{$currentStatus}', required: 'MITIGATION_PLANNED'.",
                'code'    => 'SNAPSHOT_NOT_ELIGIBLE_FOR_PLANNING',
            ];
        }

        // 2. Idempotency & Duplicate Active Candidate Check
        $activeCandidate = $this->db->table('operational_planning_candidates')
                                    ->where('snapshot_id', $snapshotId)
                                    ->whereIn('candidate_status', ['CANDIDATE_CREATED', 'UNDER_PLANNING_REVIEW', 'ACCEPTED_AS_PLANNING_INTENT'])
                                    ->get()
                                    ->getRowArray();

        if ($activeCandidate) {
            return [
                'status'  => 'error',
                'message' => "Advisory #{$snapshotId} already has an active planning candidate: {$activeCandidate['candidate_code']} ({$activeCandidate['candidate_status']}).",
                'code'    => 'DUPLICATE_ACTIVE_CANDIDATE_PROMOTION',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorId   = $actor['id'] ?? (function_exists('session') ? session()->get('user_id') : null);
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'HUMAN_PLANNER';
        $actorRole = $actor['role'] ?? (function_exists('session') ? session()->get('role') : null) ?? 'PERENCANA_PEMELIHARAAN';

        $candidateCode = 'PLAN-CND-STJ-' . date('Ymd') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $title = !empty($workData['proposed_work_title'])
            ? trim($workData['proposed_work_title'])
            : "Mitigasi {$snapshot['dominant_historical_cause']} pada Seksi {$snapshot['section_name']} ({$snapshot['feeder_name']})";

        $scope = !empty($workData['proposed_work_scope'])
            ? trim($workData['proposed_work_scope'])
            : $snapshot['recommended_review_focus'];

        $targetDays = (int)($workData['target_completion_days'] ?? 7);

        // 3. Persist Candidate Record with Immutable Source Lineage Binding
        $candidateData = [
            'candidate_code'                => $candidateCode,
            'snapshot_id'                   => $snapshotId,
            'snapshot_code'                 => $snapshot['snapshot_code'],
            'promoted_from_lifecycle_state' => 'MITIGATION_PLANNED',
            'finding_id'                    => $snapshot['temuan_id'] ?? ($snapshot['finding_id'] ?? null),
            'penyulang_id'                  => (int)($snapshot['penyulang_id'] ?? 1),
            'feeder_name'                   => $snapshot['feeder_name'] ?? 'BALUNG',
            'section_id'                    => (int)($snapshot['section_id'] ?? 1),
            'section_name'                  => $snapshot['section_name'] ?? 'BALUNG-03',
            'asset_id'                      => $snapshot['asset_id'] ?? null,
            'asset_code'                    => $snapshot['asset_code'] ?? 'ASET-JTM',
            'candidate_status'              => 'CANDIDATE_CREATED',
            'proposed_work_title'           => $title,
            'proposed_work_scope'           => $scope,
            'target_completion_days'        => $targetDays,
            'planner_actor_id'              => $actorId,
            'planner_actor_name'            => $actorName,
            'planner_actor_role'            => $actorRole,
            'promotion_rationale'           => $cleanRationale,
            'created_at'                    => $now,
            'updated_at'                    => $now,
        ];

        $this->db->table('operational_planning_candidates')->insert($candidateData);
        $candidateId = (int)$this->db->insertID();

        return [
            'status'             => 'success',
            'candidate_id'       => $candidateId,
            'candidate_code'     => $candidateCode,
            'snapshot_id'        => $snapshotId,
            'snapshot_code'      => $snapshot['snapshot_code'],
            'candidate_status'   => 'CANDIDATE_CREATED',
            'planner_name'       => $actorName,
            'promotion_rationale'=> $cleanRationale,
            'governance_rule'    => 'HUMAN_INITIATED_PLANNING_CANDIDATE_BOUND',
        ];
    }

    /**
     * State Machine Transition for Planning Candidate.
     *
     * @param int $candidateId
     * @param string $toStatus
     * @param string $rationale Mandatory rationale
     * @param string|null $notes
     * @param array|null $actor
     * @return array
     */
    public function transitionCandidateStatus(
        int $candidateId,
        string $toStatus,
        string $rationale,
        ?string $notes = null,
        ?array $actor = null
    ): array {
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

        $fromStatus = $candidate['candidate_status'];
        $targetStatus = strtoupper(trim($toStatus));
        $cleanRationale = trim($rationale);

        // Mandatory Rationale for Decisions
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => "Decision rationale is mandatory for transitioning candidate to {$targetStatus}.",
                'code'    => 'MANDATORY_DECISION_RATIONALE_REQUIRED',
            ];
        }

        // Validate State Machine Transitions
        $allowedNext = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($targetStatus, $allowedNext, true)) {
            return [
                'status'  => 'error',
                'message' => "Invalid transition from {$fromStatus} to {$targetStatus}. Allowed: " . implode(', ', $allowedNext ?: ['NONE (Terminal State)']),
                'code'    => 'INVALID_PLANNING_TRANSITION',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('operational_planning_candidates')
                 ->where('id', $candidateId)
                 ->update([
                     'candidate_status'   => $targetStatus,
                     'decision_rationale' => $cleanRationale,
                     'decision_notes'     => $notes ? trim($notes) : null,
                     'updated_at'         => $now,
                 ]);

        return [
            'status'             => 'success',
            'candidate_id'       => $candidateId,
            'candidate_code'     => $candidate['candidate_code'],
            'from_status'        => $fromStatus,
            'to_status'          => $targetStatus,
            'decision_rationale' => $cleanRationale,
            'updated_at'         => $now,
            'governance_verdict' => 'GOVERNED_PLANNING_STATE_TRANSITION_VERIFIED',
        ];
    }

    /**
     * Get list of candidates with optional filters.
     */
    public function getCandidates(array $filters = []): array
    {
        if (!$this->db->tableExists('operational_planning_candidates')) {
            return [];
        }

        $builder = $this->db->table('operational_planning_candidates');

        if (!empty($filters['status'])) {
            $builder->where('candidate_status', $filters['status']);
        }
        if (!empty($filters['penyulang_id'])) {
            $builder->where('penyulang_id', (int)$filters['penyulang_id']);
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * Get advisories eligible for promotion (MITIGATION_PLANNED and not already actively promoted).
     */
    public function getEligibleAdvisories(): array
    {
        if (!$this->db->tableExists('preventive_risk_advisory_snapshots')) {
            return [];
        }

        $snapshots = $this->db->table('preventive_risk_advisory_snapshots')
                              ->where('governance_status', 'MITIGATION_PLANNED')
                              ->get()
                              ->getResultArray();

        $eligible = [];
        foreach ($snapshots as $snap) {
            $activeCount = $this->db->table('operational_planning_candidates')
                                    ->where('snapshot_id', $snap['id'])
                                    ->whereIn('candidate_status', ['CANDIDATE_CREATED', 'UNDER_PLANNING_REVIEW', 'ACCEPTED_AS_PLANNING_INTENT'])
                                    ->countAllResults();
            if ($activeCount === 0) {
                $eligible[] = $snap;
            }
        }

        return $eligible;
    }

    /**
     * Get Candidate Detail with Wave 1 Source Lineage.
     */
    public function getCandidateDetail(int $candidateId): array
    {
        $candidate = $this->db->table('operational_planning_candidates')
                              ->where('id', $candidateId)
                              ->get()
                              ->getRowArray();

        if (!$candidate) {
            return [];
        }

        $snapshot = $this->db->table('preventive_risk_advisory_snapshots')
                             ->where('id', $candidate['snapshot_id'])
                             ->get()
                             ->getRowArray();

        return [
            'candidate'       => $candidate,
            'source_snapshot' => $snapshot,
            'lineage_provenance' => [
                'wave_1_snapshot_code'    => $candidate['snapshot_code'],
                'promoted_from_state'     => $candidate['promoted_from_lifecycle_state'],
                'scoring_model_version'   => $snapshot['scoring_model_version'] ?? 'PREVENTIVE_SCORING_v1.0',
                'dominant_cause'          => $snapshot['dominant_historical_cause'] ?? 'ROW',
                'source_rebinding_locked' => true,
            ],
        ];
    }
}
