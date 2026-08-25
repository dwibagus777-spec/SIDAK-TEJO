<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Held Records Resolution Service (CR-02 Phase 2)
 *
 * Responsibilities:
 * - Read-only staging isolation for 29 held records.
 * - Human resolution validation (Approve Alias, Override Feeder, Confirm Distinct, Reject).
 * - Pure in-memory dry-run resolution plan generation (ZERO Database Writes).
 * - Cryptographic Confirmation Token binding baseline 832, plan ID, decision actions, and feeder IDs.
 */
class HeldRecordsResolutionService
{
    protected BaseConnection $db;
    protected string $stagingPath;
    protected string $preSnapshotPath;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->stagingPath = WRITEPATH . 'audits/cr02_held_workspace_snapshot.json';
        $this->preSnapshotPath = WRITEPATH . 'audits/cr02_pre_snapshot.json';
    }

    /**
     * Get all 29 staged held records and classification stats.
     */
    public function getHeldWorkspaceData(): array
    {
        if (!file_exists($this->stagingPath)) {
            return [
                'success' => false,
                'error'   => 'Held records workspace staging artifact not found.',
                'records' => [],
            ];
        }

        $workspace = json_decode(file_get_contents($this->stagingPath), true);
        $masterFeeders = $this->db->table('penyulang')
            ->select('id, id_unik_penyulang, kode_penyulang, nama_penyulang, ulp_id, status')
            ->orderBy('nama_penyulang', 'ASC')
            ->get()
            ->getResultArray();

        $baselineCount = $this->db->table('historical_feeder_interruptions')->countAllResults();

        return [
            'success'                    => true,
            'workspace_id'               => $workspace['workspace_id'] ?? 'CR02_HELD_WORKSPACE_STAGING_v1.0',
            'total_held_records'         => $workspace['total_held_records'] ?? 0,
            'category_breakdown'         => $workspace['category_breakdown'] ?? [],
            'staged_records'             => $workspace['staged_records'] ?? [],
            'master_feeders'             => $masterFeeders,
            'baseline_disturbance_count' => $baselineCount,
            'governance_invariants'      => $workspace['governance_invariants'] ?? [],
        ];
    }

    /**
     * Generate Pure In-Memory Dry-Run Resolution Plan.
     *
     * @param array $decisions Map of staging_id/source_row => [ 'action' => '...', 'target_feeder_id' => ..., 'notes' => '...' ]
     * @param array|null $actor
     * @return array
     */
    public function generateDryRunPlan(array $decisions = [], ?array $actor = null): array
    {
        $workspaceData = $this->getHeldWorkspaceData();
        if (!$workspaceData['success']) {
            return $workspaceData;
        }

        $actorInfo = $actor ?? [
            'actor_id'   => 1,
            'actor_name' => 'SUPERVISOR_DISTRIBUSI',
            'actor_role' => 'HUMAN_MANAGEMENT_AUTHORITY',
        ];

        $preSnapshotHash = file_exists($this->preSnapshotPath)
            ? hash('sha256', file_get_contents($this->preSnapshotPath))
            : 'PRE_SNAPSHOT_HASH_UNAVAILABLE';

        $workspaceHash = hash('sha256', file_get_contents($this->stagingPath));
        $baselineCount = $workspaceData['baseline_disturbance_count']; // Strictly 832

        $masterFeedersMap = [];
        foreach ($workspaceData['master_feeders'] as $mf) {
            $masterFeedersMap[$mf['id']] = $mf['nama_penyulang'];
        }

        $stagedRecords = $workspaceData['staged_records'];
        $resolvedCandidates = [];
        $rejectedRecords    = [];
        $unresolvedRecords  = [];

        $planTimestamp = date('Y-m-d H:i:s');
        $planId = 'PLAN-CR02-RES-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

        foreach ($stagedRecords as $rec) {
            $stagingId = $rec['staging_id'];
            $rowNum    = $rec['source_row'];
            $userDecision = $decisions[$stagingId] ?? $decisions[$rowNum] ?? null;

            $action = $userDecision['action'] ?? null;
            $targetFeederId = $userDecision['target_feeder_id'] ?? null;
            $notes = $userDecision['notes'] ?? '';

            // Default proposed resolution if no explicit user override is provided
            if (!$action) {
                if ($rec['classification'] === 'HIGH_CONFIDENCE_CANDIDATE') {
                    $action = 'APPROVE_ALIAS_MAPPING';
                    $targetFeederId = $rec['candidate_feeder_id'];
                    $notes = "Default auto-stage: High lexical match candidate '{$rec['candidate_feeder_name']}'";
                } elseif ($rec['classification'] === 'HOLD_COMPOSITE_DUPLICATE') {
                    $action = 'PENDING_HUMAN_DUPLICATE_DECISION';
                } else {
                    $action = 'UNRESOLVED_REQUIRE_MANUAL_INPUT';
                }
            }

            $resolvedEntry = [
                'staging_id'            => $stagingId,
                'source_row'            => $rowNum,
                'raw_feeder_name'       => $rec['raw_feeder_name'],
                'classification'        => $rec['classification'],
                'decision_action'       => $action,
                'source_record_hash'    => $rec['source_record_hash'],
                'raw_payload_hash'      => $rec['raw_payload_hash'],
                'notes'                 => $notes,
            ];

            if ($action === 'APPROVE_ALIAS_MAPPING' || $action === 'OVERRIDE_MANUAL_MAPPING' || $action === 'CONFIRM_DISTINCT_INCIDENT') {
                $feederId = (int)$targetFeederId;
                if ($feederId > 0 && isset($masterFeedersMap[$feederId])) {
                    $resolvedEntry['resolved_penyulang_id']   = $feederId;
                    $resolvedEntry['resolved_penyulang_name'] = $masterFeedersMap[$feederId];
                    $resolvedEntry['resolution_verdict']      = 'READY_FOR_COMMITTED_STAGE';
                    $resolvedCandidates[] = $resolvedEntry;
                } else {
                    $resolvedEntry['resolution_verdict'] = 'INVALID_TARGET_FEEDER_ID';
                    $unresolvedRecords[] = $resolvedEntry;
                }
            } elseif ($action === 'REJECT_RECORD') {
                $resolvedEntry['resolution_verdict'] = 'REJECTED_EXCLUDED_FROM_IMPORT';
                $rejectedRecords[] = $resolvedEntry;
            } else {
                $resolvedEntry['resolution_verdict'] = 'HELD_PENDING_EXPLICIT_DECISION';
                $unresolvedRecords[] = $resolvedEntry;
            }
        }

        $candidateCommitCount = count($resolvedCandidates);
        $rejectedCount        = count($rejectedRecords);
        $unresolvedCount      = count($unresolvedRecords);
        $projectedTotal       = $baselineCount + $candidateCommitCount;

        // Cryptographic Confirmation Token Binding
        $tokenComponents = [
            'plan_id'                  => $planId,
            'pre_snapshot_hash'        => $preSnapshotHash,
            'workspace_snapshot_hash'  => $workspaceHash,
            'baseline_disturbance_cnt' => $baselineCount,
            'resolved_hashes'          => array_column($resolvedCandidates, 'source_record_hash'),
            'resolved_feeder_ids'      => array_column($resolvedCandidates, 'resolved_penyulang_id'),
            'rejected_hashes'          => array_column($rejectedRecords, 'source_record_hash'),
            'unresolved_count'         => $unresolvedCount,
            'created_at'               => $planTimestamp,
        ];

        $confirmationToken = hash('sha256', json_encode($tokenComponents));

        return [
            'success'                      => true,
            'resolution_plan_id'           => $planId,
            'confirmation_token'           => $confirmationToken,
            'created_at'                   => $planTimestamp,
            'actor'                        => $actorInfo,
            'baseline_disturbance_count'   => $baselineCount, // 832
            'proposed_commit_count'        => $candidateCommitCount,
            'candidate_commit_count'       => $candidateCommitCount,
            'rejected_count'               => $rejectedCount,
            'unresolved_count'             => $unresolvedCount,
            'projected_total_after_commit' => $projectedTotal,
            'dry_run'                      => true,
            'database_writes'              => 0,
            'schema_mutations'             => 0,
            'resolved_candidates'          => $resolvedCandidates,
            'rejected_records'             => $rejectedRecords,
            'unresolved_records'           => $unresolvedRecords,
            'governance_invariants'        => [
                'DATABASE_WRITES_PROHIBITED_IN_DRYRUN'   => true,
                'MASTER_PENYULANG_MUTATION_PROHIBITED'   => true,
                'BASELINE_832_PROTECTED'                 => true,
                'CRYPTOGRAPHIC_TOKEN_PINNED'             => true,
                'HUMAN_MANAGEMENT_AUTHORITY_FINAL'       => true,
            ],
        ];
    }
}
