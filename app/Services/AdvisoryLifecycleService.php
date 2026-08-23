<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Advisory Lifecycle Service (Phase CC-03)
 *
 * Responsibilities:
 * - Strict State Machine transition enforcement:
 *     ADVISORY_PROPOSED   -> [SUPERVISOR_REVIEWED]
 *     SUPERVISOR_REVIEWED -> [MITIGATION_PLANNED, ARCHIVED]
 *     MITIGATION_PLANNED  -> [ARCHIVED]
 *     ARCHIVED            -> [] (Terminal)
 * - Append-Only Immutable Audit Event persistence.
 * - Mandatory Supervisor decision rationale.
 * - Invariant: DIRECT_STATUS_MUTATION = FORBIDDEN, LIFECYCLE_EVENT_UPDATE = FORBIDDEN.
 */
class AdvisoryLifecycleService
{
    public const ALLOWED_TRANSITIONS = [
        'ADVISORY_PROPOSED'   => ['SUPERVISOR_REVIEWED'],
        'SUPERVISOR_REVIEWED' => ['MITIGATION_PLANNED', 'ARCHIVED'],
        'MITIGATION_PLANNED'  => ['ARCHIVED'],
        'ARCHIVED'            => [],
    ];

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Transition a snapshot's lifecycle state with strict matrix validation and append-only audit logging.
     *
     * @param int $snapshotId
     * @param string $toStatus
     * @param string $rationale Mandatory decision rationale
     * @param string|null $notes Optional operational notes
     * @param array|null $actor [ 'id' => ..., 'name' => ..., 'role' => ... ]
     * @return array
     */
    public function transitionState(
        int $snapshotId,
        string $toStatus,
        string $rationale,
        ?string $notes = null,
        ?array $actor = null
    ): array {
        $snapshot = $this->db->table('preventive_risk_advisory_snapshots')
                             ->where('id', $snapshotId)
                             ->get()
                             ->getRowArray();

        if (!$snapshot) {
            return [
                'status'  => 'error',
                'message' => "Preventive Risk Advisory Snapshot #{$snapshotId} not found.",
            ];
        }

        $fromStatus = $snapshot['governance_status'] ?? 'ADVISORY_PROPOSED';
        $targetStatus = strtoupper(trim($toStatus));
        $cleanedRationale = trim($rationale);

        // 1. Mandatory Rationale Validation
        if ($cleanedRationale === '') {
            return [
                'status'  => 'error',
                'message' => "Decision rationale is mandatory for transitioning to {$targetStatus}.",
                'code'    => 'MANDATORY_RATIONALE_REQUIRED',
            ];
        }

        // 2. Strict State Transition Matrix Validation
        $allowedNextStates = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($targetStatus, $allowedNextStates, true)) {
            return [
                'status'  => 'error',
                'message' => "Invalid state transition from {$fromStatus} to {$targetStatus}. Allowed transitions: " . implode(', ', $allowedNextStates ?: ['NONE (Terminal State)']),
                'code'    => 'INVALID_LIFECYCLE_TRANSITION',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorId   = $actor['id'] ?? (function_exists('session') ? session()->get('user_id') : null);
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'HUMAN_SUPERVISOR';
        $actorRole = $actor['role'] ?? (function_exists('session') ? session()->get('role') : null) ?? 'SUPERVISOR_DISTRIBUSI';

        // 3. Append-Only Audit Event Logging
        $this->db->table('advisory_lifecycle_events')->insert([
            'snapshot_id'         => $snapshotId,
            'snapshot_code'       => $snapshot['snapshot_code'],
            'from_status'         => $fromStatus,
            'to_status'           => $targetStatus,
            'actor_id'            => $actorId,
            'actor_name_snapshot' => $actorName,
            'actor_role_snapshot' => $actorRole,
            'decision_rationale'  => $cleanedRationale,
            'decision_notes'      => $notes ? trim($notes) : null,
            'event_timestamp'     => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $eventId = (int)$this->db->insertID();

        // 4. Update Snapshot State (Preserving original evidence and scoring weights)
        $this->db->table('preventive_risk_advisory_snapshots')
                 ->where('id', $snapshotId)
                 ->update([
                     'governance_status' => $targetStatus,
                     'updated_at'        => $now,
                 ]);

        return [
            'status'             => 'success',
            'event_id'           => $eventId,
            'snapshot_id'        => $snapshotId,
            'snapshot_code'      => $snapshot['snapshot_code'],
            'from_status'        => $fromStatus,
            'to_status'          => $targetStatus,
            'actor_name'         => $actorName,
            'actor_role'         => $actorRole,
            'decision_rationale' => $cleanedRationale,
            'transition_time'    => $now,
            'governance_rule'    => 'LIFECYCLE_EVENT_APPEND_ONLY_IMMUTABLE',
        ];
    }

    /**
     * Get chronological timeline of lifecycle events for a snapshot.
     */
    public function getTimeline(int $snapshotId): array
    {
        if (!$this->db->tableExists('advisory_lifecycle_events')) {
            return [];
        }

        return $this->db->table('advisory_lifecycle_events')
                        ->where('snapshot_id', $snapshotId)
                        ->orderBy('id', 'ASC')
                        ->get()
                        ->getResultArray();
    }

    /**
     * Get review queue with optional filters (feeder, status, tier).
     */
    public function getReviewQueue(array $filters = []): array
    {
        if (!$this->db->tableExists('preventive_risk_advisory_snapshots')) {
            return [];
        }

        $builder = $this->db->table('preventive_risk_advisory_snapshots');

        if (!empty($filters['status'])) {
            $builder->where('governance_status', $filters['status']);
        }
        if (!empty($filters['penyulang_id'])) {
            $builder->where('penyulang_id', (int)$filters['penyulang_id']);
        }
        if (!empty($filters['risk_tier'])) {
            $builder->where('preventive_risk_tier', $filters['risk_tier']);
        }

        return $builder->orderBy('id', 'DESC')->limit(50)->get()->getResultArray();
    }
}
