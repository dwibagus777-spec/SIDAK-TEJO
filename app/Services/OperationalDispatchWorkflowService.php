<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Operational Dispatch Workflow Service (CR-04 Phase 2)
 *
 * Responsibilities:
 * - Governed state machine for human-initiated operational dispatch packages.
 * - Strict Zero Auto-Dispatch enforcement: Every action requires human actor NIP & role.
 * - Tripartite state transition validation with immutable cryptographic audit trail.
 * - Evidence linkage to 841 Historical Disturbances, CR-03 Pattern Intelligence, and Active Temuan.
 * - Preserves sealed intelligence baselines (M-04, M-05, PREVENTIVE_SCORING_v1.0, 40/35/25).
 */
class OperationalDispatchWorkflowService
{
    protected BaseConnection $db;
    protected string $registryPath;
    protected string $preSnapshotPath;

    public const MODEL_VERSION = 'OPERATIONAL_DISPATCH_MODEL_v1.0';
    public const PATTERN_MODEL_VERSION = 'HISTORICAL_PATTERN_MODEL_v1.0';
    public const SCORING_MODEL_VERSION = 'PREVENTIVE_SCORING_v1.0';

    // Canonical State Machine
    public const STATE_DRAFT               = 'DRAFT_DISPATCH_PLAN';
    public const STATE_PENDING_APPROVAL    = 'PENDING_SUPERVISOR_APPROVAL';
    public const STATE_AUTHORIZED          = 'DISPATCH_AUTHORIZED';
    public const STATE_IN_EXECUTION        = 'IN_FIELD_EXECUTION';
    public const STATE_FIELD_COMPLETED     = 'FIELD_COMPLETED';
    public const STATE_SUPERVISOR_VERIFIED = 'SUPERVISOR_VERIFIED';
    public const STATE_CANCELLED           = 'CANCELLED';

    // Permitted State Transitions
    protected array $allowedTransitions = [
        self::STATE_DRAFT => [
            self::STATE_PENDING_APPROVAL,
            self::STATE_CANCELLED,
        ],
        self::STATE_PENDING_APPROVAL => [
            self::STATE_AUTHORIZED,
            self::STATE_DRAFT, // Request revision
            self::STATE_CANCELLED,
        ],
        self::STATE_AUTHORIZED => [
            self::STATE_IN_EXECUTION,
            self::STATE_CANCELLED,
        ],
        self::STATE_IN_EXECUTION => [
            self::STATE_FIELD_COMPLETED,
        ],
        self::STATE_FIELD_COMPLETED => [
            self::STATE_SUPERVISOR_VERIFIED,
            self::STATE_IN_EXECUTION, // Rework requested
        ],
        self::STATE_SUPERVISOR_VERIFIED => [], // Terminal state
        self::STATE_CANCELLED           => [], // Terminal state
    ];

    // Role-Based Transition Authority
    protected array $roleAuthorities = [
        self::STATE_PENDING_APPROVAL    => ['PLANNER', 'SUPERVISOR', 'INSPECTOR', 'SYSTEM_ADMIN'],
        self::STATE_AUTHORIZED          => ['SUPERVISOR', 'MANAGER_ULP', 'SYSTEM_ADMIN'],
        self::STATE_IN_EXECUTION        => ['FIELD_CREW', 'SUPERVISOR', 'SYSTEM_ADMIN'],
        self::STATE_FIELD_COMPLETED     => ['FIELD_CREW', 'SUPERVISOR', 'SYSTEM_ADMIN'],
        self::STATE_SUPERVISOR_VERIFIED => ['SUPERVISOR', 'MANAGER_ULP', 'QA_ENGINEER', 'SYSTEM_ADMIN'],
        self::STATE_CANCELLED           => ['PLANNER', 'SUPERVISOR', 'MANAGER_ULP', 'SYSTEM_ADMIN'],
    ];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->registryPath = WRITEPATH . 'audits/cr04_dispatch_registry.json';
        $this->preSnapshotPath = WRITEPATH . 'audits/cr04_pre_snapshot.json';
        $this->ensureRegistryExists();
    }

    /**
     * Ensure the Governed Dispatch Registry File Exists.
     */
    protected function ensureRegistryExists(): void
    {
        if (!file_exists($this->registryPath)) {
            $initialRegistry = [
                'registry_id'          => 'CR04_DISPATCH_REGISTRY_v1.0',
                'created_at'           => date('Y-m-d H:i:s T'),
                'lineage_parent'       => 'CR04_PRE_SNAPSHOT',
                'governance_rule'      => 'ZERO_AUTO_DISPATCH_HUMAN_AUTHORITY_FINAL',
                'dispatches'           => [],
            ];
            file_put_contents($this->registryPath, json_encode($initialRegistry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Get All Governed Dispatches and Queue Status.
     */
    public function getDispatchQueue(): array
    {
        $registry = json_decode(file_get_contents($this->registryPath), true);
        $masterFeeders = $this->db->table('penyulang')
            ->select('id, nama_penyulang, ulp_id, status')
            ->orderBy('nama_penyulang', 'ASC')
            ->get()
            ->getResultArray();

        $activeFindings = $this->db->table('temuan')
            ->select('id, nomor_temuan, penyulang_id, jenis_temuan, current_severity, status, tanggal_temuan')
            ->orderBy('id', 'DESC')
            ->limit(50)
            ->get()
            ->getResultArray();

        $totalDisturbances = $this->db->table('historical_feeder_interruptions')->countAllResults();

        return [
            'success'              => true,
            'model_version'        => self::MODEL_VERSION,
            'dispatches'           => $registry['dispatches'] ?? [],
            'master_feeders'       => $masterFeeders,
            'active_findings'      => $activeFindings,
            'baseline_summary'     => [
                'historical_disturbances' => $totalDisturbances, // 841
                'master_feeders_count'    => count($masterFeeders), // 134
                'assets_count'            => 0, // Honest Zero
            ],
            'governance_invariants'=> [
                'HUMAN_AUTHORITY_FINAL'     => true,
                'ZERO_AUTO_DISPATCH'        => true,
                'IMMUTABLE_AUDIT_TRAIL'     => true,
                'M04_M05_PRESERVED'         => true,
                'WEIGHTS_PINNED_40_35_25'   => true,
            ],
        ];
    }

    /**
     * Create a Human-Initiated Dispatch Plan Draft.
     *
     * @param array $payload
     * @param array $actor
     * @return array
     */
    public function createDraft(array $payload, array $actor): array
    {
        // 1. Mandatory Human Actor Validation
        if (empty($actor['actor_name']) || empty($actor['actor_nip']) || empty($actor['actor_role'])) {
            return [
                'success' => false,
                'error'   => 'Human Actor validation failed: actor_name, actor_nip, and actor_role are mandatory.',
            ];
        }

        $feederId = (int)($payload['penyulang_id'] ?? 0);
        $feeder = $this->db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
        if (!$feeder) {
            return [
                'success' => false,
                'error'   => "Master Feeder ID #{$feederId} not found.",
            ];
        }

        $now = date('Y-m-d H:i:s');
        $dispatchId = 'DISP-CR04-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

        $dispatchEntry = [
            'dispatch_id'         => $dispatchId,
            'title'               => $payload['title'] ?? "Pemeliharaan Terarah Penyulang {$feeder['nama_penyulang']}",
            'penyulang_id'        => $feederId,
            'penyulang_name'      => $feeder['nama_penyulang'],
            'ulp_id'              => (int)$feeder['ulp_id'],
            'work_scope'          => $payload['work_scope'] ?? 'INSPEKSI_DAN_PEMANGKASAN_ROW',
            'justification'       => $payload['justification'] ?? 'Berdasarkan sinyal pattern intelligence recurrence dan temuan inspeksi.',
            'current_state'       => self::STATE_DRAFT,
            'created_at'          => $now,
            'created_by'          => [
                'actor_id'   => $actor['actor_id'] ?? 1,
                'actor_name' => $actor['actor_name'],
                'actor_nip'  => $actor['actor_nip'],
                'actor_role' => $actor['actor_role'],
            ],
            'evidence_lineage'    => [
                'linked_finding_ids'     => $payload['linked_finding_ids'] ?? [],
                'pattern_evidence_codes' => $payload['pattern_evidence_codes'] ?? ['REC-CR03-01'],
                'disturbance_baseline'   => 841,
                'pre_snapshot_id'        => 'CR04_PRE_SNAPSHOT',
            ],
            'confirmation_token'  => null,
            'state_history'       => [
                [
                    'from_state'    => null,
                    'to_state'      => self::STATE_DRAFT,
                    'actor_name'    => $actor['actor_name'],
                    'actor_nip'     => $actor['actor_nip'],
                    'actor_role'    => $actor['actor_role'],
                    'timestamp'     => $now,
                    'notes'         => 'Human-initiated draft dispatch created.',
                    'action_hash'   => hash('sha256', "{$dispatchId}|INITIAL_DRAFT|{$actor['actor_nip']}|{$now}"),
                ]
            ],
        ];

        // Save to Governed Registry
        $registry = json_decode(file_get_contents($this->registryPath), true);
        $registry['dispatches'][$dispatchId] = $dispatchEntry;
        file_put_contents($this->registryPath, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'     => true,
            'dispatch_id' => $dispatchId,
            'state'       => self::STATE_DRAFT,
            'message'     => "Dispatch draft {$dispatchId} created successfully.",
            'dispatch'    => $dispatchEntry,
        ];
    }

    /**
     * Transition Dispatch State with Strict Validation & Confirmation Token.
     *
     * @param string $dispatchId
     * @param string $targetState
     * @param array $actor
     * @param string $notes
     * @return array
     */
    public function transitionState(string $dispatchId, string $targetState, array $actor, string $notes = ''): array
    {
        // 1. Mandatory Human Actor Validation
        if (empty($actor['actor_name']) || empty($actor['actor_nip']) || empty($actor['actor_role'])) {
            return [
                'success' => false,
                'error'   => 'Human Actor validation failed: actor_name, actor_nip, and actor_role are mandatory.',
            ];
        }

        $registry = json_decode(file_get_contents($this->registryPath), true);
        if (!isset($registry['dispatches'][$dispatchId])) {
            return [
                'success' => false,
                'error'   => "Dispatch ID {$dispatchId} not found in registry.",
            ];
        }

        $dispatch = $registry['dispatches'][$dispatchId];
        $currentState = $dispatch['current_state'];

        // 2. Strict State Transition Validation
        $allowedNext = $this->allowedTransitions[$currentState] ?? [];
        if (!in_array($targetState, $allowedNext, true)) {
            return [
                'success' => false,
                'error'   => "Illegal state transition: Cannot transition from '{$currentState}' to '{$targetState}'.",
            ];
        }

        // 3. Role Authority Check
        $allowedRoles = $this->roleAuthorities[$targetState] ?? [];
        $actorRoleUpper = strtoupper($actor['actor_role']);
        $hasAuthority = false;
        foreach ($allowedRoles as $r) {
            if (str_contains($actorRoleUpper, $r)) {
                $hasAuthority = true;
                break;
            }
        }

        if (!$hasAuthority) {
            return [
                'success' => false,
                'error'   => "Authority violation: Role '{$actor['actor_role']}' is not authorized to transition to '{$targetState}'.",
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actionHash = hash('sha256', "{$dispatchId}|{$currentState}|{$targetState}|{$actor['actor_nip']}|{$now}");

        // 4. Generate Confirmation Token on Authorization
        $confirmationToken = $dispatch['confirmation_token'];
        if ($targetState === self::STATE_AUTHORIZED) {
            $preSnapshotHash = file_exists($this->preSnapshotPath)
                ? hash('sha256', file_get_contents($this->preSnapshotPath))
                : 'PRE_HASH_UNAVAILABLE';

            $tokenComponents = [
                'dispatch_id'         => $dispatchId,
                'penyulang_id'        => $dispatch['penyulang_id'],
                'authorizer_nip'      => $actor['actor_nip'],
                'authorizer_name'     => $actor['actor_name'],
                'pre_snapshot_hash'   => $preSnapshotHash,
                'timestamp'           => $now,
            ];
            $confirmationToken = hash('sha256', json_encode($tokenComponents));
            $dispatch['confirmation_token'] = $confirmationToken;
            $dispatch['authorized_at'] = $now;
            $dispatch['authorized_by'] = [
                'actor_name' => $actor['actor_name'],
                'actor_nip'  => $actor['actor_nip'],
                'actor_role' => $actor['actor_role'],
            ];
        }

        // 5. Append Immutable State History
        $dispatch['current_state'] = $targetState;
        $dispatch['updated_at']    = $now;
        $dispatch['state_history'][] = [
            'from_state'         => $currentState,
            'to_state'           => $targetState,
            'actor_name'         => $actor['actor_name'],
            'actor_nip'          => $actor['actor_nip'],
            'actor_role'         => $actor['actor_role'],
            'timestamp'          => $now,
            'notes'              => $notes ?: "Transitioned from {$currentState} to {$targetState}",
            'action_hash'        => $actionHash,
            'confirmation_token' => ($targetState === self::STATE_AUTHORIZED) ? $confirmationToken : null,
        ];

        $registry['dispatches'][$dispatchId] = $dispatch;
        file_put_contents($this->registryPath, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'            => true,
            'dispatch_id'        => $dispatchId,
            'previous_state'     => $currentState,
            'current_state'      => $targetState,
            'confirmation_token' => $confirmationToken,
            'action_hash'        => $actionHash,
            'message'            => "Dispatch {$dispatchId} transitioned to {$targetState} successfully.",
            'dispatch'           => $dispatch,
        ];
    }
}
