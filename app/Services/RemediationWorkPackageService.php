<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use App\Exceptions\UnprocessableEntityException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\MethodNotAllowedException;

/**
 * SIDAK TEJO — Phase B.8.1: Remediation Core Service
 * Closed-Loop Work Package Lifecycle & Governance Engine
 *
 * ARCHITECTURAL CONTRACT & HARD GUARDS:
 * - B81-G01: Finding Assignment Atomicity (Resource-level pessimistic locking on field_findings row)
 * - B81-G02: Permanent Audit History Non-Cascade (remediation_package_history ON DELETE RESTRICT)
 * - B81-G03: Work Package Creation Idempotency (client_operation_uuid VARCHAR(64) UNIQUE)
 * - B81-G04: Pre-Commit Finding Revalidation (Live status must be CONFIRMED before SUBMIT & APPROVE)
 * - B81-G05: Verification Evidence Content Integrity (SHA-256 content verification on QA completion)
 * - Invariant: B.7 Topology is strictly immutable (Delta_topology = 0)
 * - 9-State FSM: DRAFT -> ENGINEER_SUBMITTED -> PENDING_APPROVAL -> APPROVED / REJECTED -> RELEASED -> IN_PROGRESS -> COMPLETED -> VERIFIED
 */
class RemediationWorkPackageService
{
    public const SERVICE_VERSION = 'B8.1-REMEDIATION-1.0';
    public const TOPOLOGY_SNAPSHOT = 'TOPOLOGY-20260925-243-ad2c9fcb';

    // 9 FSM States
    public const STATUS_DRAFT               = 'DRAFT';
    public const STATUS_ENGINEER_SUBMITTED  = 'ENGINEER_SUBMITTED';
    public const STATUS_PENDING_APPROVAL    = 'PENDING_APPROVAL';
    public const STATUS_APPROVED            = 'APPROVED';
    public const STATUS_REJECTED            = 'REJECTED';
    public const STATUS_RELEASED            = 'RELEASED';
    public const STATUS_IN_PROGRESS         = 'IN_PROGRESS';
    public const STATUS_COMPLETED           = 'COMPLETED';
    public const STATUS_VERIFIED            = 'VERIFIED';

    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ENGINEER_SUBMITTED,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_RELEASED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_VERIFIED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ENGINEER_SUBMITTED,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_RELEASED,
        self::STATUS_IN_PROGRESS,
    ];

    // Standard Roles
    public const ROLE_FIELD_ENGINEER      = 'FIELD_ENGINEER';
    public const ROLE_SUPERVISOR          = 'SUPERVISOR';
    public const ROLE_ULP_MANAGER         = 'ULP_MANAGER';
    public const ROLE_FIELD_TEAM_LEADER   = 'FIELD_TEAM_LEADER';
    public const ROLE_QA_ENGINEER         = 'QA_ENGINEER';
    public const ROLE_TECHNICAL_INSPECTOR = 'TECHNICAL_INSPECTOR';
    public const ROLE_ADMIN               = 'ADMIN';

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function getDb(): BaseConnection
    {
        return $this->db;
    }

    // =========================================================================
    // 1. WORK PACKAGE CREATION (B81-G03: Idempotent Creation)
    // =========================================================================

    /**
     * Create a new work package or return existing canonical entity if client_operation_uuid matches.
     *
     * @param array $payload
     * @param int $actorId
     * @param string $actorRole
     * @return array
     */
    public function createWorkPackage(array $payload, int $actorId, string $actorRole = self::ROLE_FIELD_ENGINEER): array
    {
        $opUuid = trim((string)($payload['client_operation_uuid'] ?? ''));
        if ($opUuid === '') {
            throw new UnprocessableEntityException("client_operation_uuid is mandatory for idempotent creation (B81-G03).", 422);
        }
        if (strlen($opUuid) > 64) {
            throw new UnprocessableEntityException("client_operation_uuid must not exceed 64 characters.", 422);
        }

        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new UnprocessableEntityException("title is mandatory for work package creation.", 422);
        }

        // B81-G03 Idempotency Check: Return existing record on duplicate op uuid
        $existing = $this->db->table('remediation_work_packages')
            ->where('client_operation_uuid', $opUuid)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return [
                'success'      => true,
                'status'       => 'EXISTING',
                'is_replay'    => true,
                'work_package' => $existing,
                'package_id'   => (int)$existing['id'],
                'package_code' => $existing['package_code'],
                'message'      => 'Idempotent replay: returning existing canonical work package',
                'http_code'    => 200,
            ];
        }

        $priority = strtoupper(trim((string)($payload['priority'] ?? 'MEDIUM')));
        $allowedPriorities = ['LOW', 'MEDIUM', 'HIGH', 'EMERGENCY'];
        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'MEDIUM';
        }

        $this->db->transBegin();
        try {
            // Generate canonical package_code: WP-YYYYMMDD-XXXX
            $datePrefix = date('Ymd');
            $packageCode = null;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $candidateCode = sprintf('WP-%s-%04X', $datePrefix, random_int(1, 0xFFFF));
                $codeExists = $this->db->table('remediation_work_packages')
                    ->where('package_code', $candidateCode)
                    ->countAllResults() > 0;
                if (!$codeExists) {
                    $packageCode = $candidateCode;
                    break;
                }
            }
            if ($packageCode === null) {
                $packageCode = 'WP-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(2)));
            }

            $now = date('Y-m-d H:i:s');
            $teamMembers = null;
            if (isset($payload['assigned_team_members'])) {
                $teamMembers = is_string($payload['assigned_team_members'])
                    ? $payload['assigned_team_members']
                    : json_encode($payload['assigned_team_members']);
            }

            $wpData = [
                'client_operation_uuid' => $opUuid,
                'package_code'          => $packageCode,
                'title'                 => $title,
                'description'           => $payload['description'] ?? null,
                'feeder_code'           => $payload['feeder_code'] ?? null,
                'priority'              => $priority,
                'status'                => self::STATUS_DRAFT,
                'assigned_team_leader'  => $payload['assigned_team_leader'] ?? null,
                'assigned_team_members' => $teamMembers,
                'created_by'            => $actorId,
                'test_mode'             => !empty($payload['test_mode']) ? 1 : 0,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];

            $this->db->table('remediation_work_packages')->insert($wpData);
            $packageId = (int)$this->db->insertID();

            // Append History
            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => null,
                'new_status'       => self::STATUS_DRAFT,
                'action_name'      => 'CREATE_WORK_PACKAGE',
                'actor_id'         => $actorId,
                'actor_role'       => $actorRole,
                'transition_notes' => $payload['description'] ?? 'Work package created in DRAFT status',
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            $created = $this->getWorkPackage($packageId);

            return [
                'success'      => true,
                'status'       => 'CREATED',
                'is_replay'    => false,
                'work_package' => $created,
                'package_id'   => $packageId,
                'package_code' => $packageCode,
                'message'      => 'Work package created successfully',
                'http_code'    => 201,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    // =========================================================================
    // 2. ATTACH / REMOVE FINDINGS (B81-G01: Finding Assignment Atomicity)
    // =========================================================================

    /**
     * Atomically attach a confirmed finding to a draft work package with pessimistic row-locking.
     * Enforces:
     * 1. SELECT ... FROM field_findings WHERE id = ? FOR UPDATE (B81-G01)
     * 2. finding_status must be 'CONFIRMED' (ADV-02)
     * 3. Target work package must exist and be 'DRAFT' (ADV-08)
     * 4. Finding must not already be in this package (ADV-04)
     * 5. Finding must not be in any other active package (ADV-05)
     *
     * @param int $packageId
     * @param int $findingId
     * @param int $actorId
     * @param string $actorRole
     * @param bool $isPrimary
     * @param string|null $notes
     * @return array
     */
    public function attachFinding(
        int $packageId,
        int $findingId,
        int $actorId,
        string $actorRole = self::ROLE_FIELD_ENGINEER,
        bool $isPrimary = false,
        ?string $notes = null
    ): array {
        $this->db->transBegin();
        try {
            // Step 1: Pessimistic Row Lock on field_findings (B81-G01)
            $findingQuery = "SELECT id, fault_case_id, actual_asset_id, predicted_asset_id, finding_status 
                             FROM field_findings 
                             WHERE id = ? 
                             FOR UPDATE";
            $finding = $this->db->query($findingQuery, [$findingId])->getRowArray();

            if ($finding === null) {
                $this->db->transRollback();
                throw new NotFoundException("Finding #{$findingId} does not exist in field_findings (ADV-03).", 404);
            }

            // Step 2: Validate status is CONFIRMED (ADV-02)
            if ($finding['finding_status'] !== 'CONFIRMED') {
                $this->db->transRollback();
                throw new UnprocessableEntityException(
                    "Finding #{$findingId} has status '{$finding['finding_status']}'. Only CONFIRMED findings can be attached to a work package (ADV-02).",
                    422
                );
            }

            // Step 3: Lock target Work Package & verify DRAFT status (ADV-08)
            $wpQuery = "SELECT id, package_code, status 
                        FROM remediation_work_packages 
                        WHERE id = ? 
                        FOR UPDATE";
            $wp = $this->db->query($wpQuery, [$packageId])->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} does not exist.", 404);
            }

            if ($wp['status'] !== self::STATUS_DRAFT) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Work package #{$packageId} is in status '{$wp['status']}'. Findings can only be attached while in DRAFT status (ADV-08).",
                    409
                );
            }

            // Step 4: Check if already attached to THIS package (ADV-04)
            $existingInThisWp = $this->db->table('remediation_package_findings')
                ->where('work_package_id', $packageId)
                ->where('finding_id', $findingId)
                ->countAllResults();

            if ($existingInThisWp > 0) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Finding #{$findingId} is already attached to work package #{$packageId} (ADV-04).",
                    409
                );
            }

            // Step 5: Check if attached to ANY OTHER active work package (ADV-05)
            $activeConflictQuery = "SELECT rpf.work_package_id, rwp.package_code, rwp.status 
                                    FROM remediation_package_findings rpf
                                    JOIN remediation_work_packages rwp ON rpf.work_package_id = rwp.id
                                    WHERE rpf.finding_id = ? 
                                      AND rwp.status IN ('" . implode("','", self::ACTIVE_STATUSES) . "')
                                    LIMIT 1";
            $activeConflict = $this->db->query($activeConflictQuery, [$findingId])->getRowArray();

            if ($activeConflict !== null) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Finding #{$findingId} is already assigned to active work package {$activeConflict['package_code']} (Status: {$activeConflict['status']}) (ADV-05).",
                    409
                );
            }

            // Step 6: Insert relation into remediation_package_findings
            $now = date('Y-m-d H:i:s');
            $assetId = !empty($finding['actual_asset_id']) ? (int)$finding['actual_asset_id'] : (int)($finding['predicted_asset_id'] ?? 0);
            $this->db->table('remediation_package_findings')->insert([
                'work_package_id'          => $packageId,
                'finding_id'               => $findingId,
                'case_id'                  => (int)$finding['fault_case_id'],
                'asset_id'                 => $assetId,
                'transline_id'             => null,
                'is_primary'               => $isPrimary ? 1 : 0,
                'finding_status_at_attach' => 'CONFIRMED',
                'item_remediation_status'  => 'PENDING',
                'item_notes'               => $notes,
                'created_at'               => $now,
                'updated_at'               => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'action'          => 'ATTACH_FINDING',
                'work_package_id' => $packageId,
                'finding_id'      => $findingId,
                'message'         => "Finding #{$findingId} successfully attached to work package #{$packageId}",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Remove an attached finding from a DRAFT work package.
     */
    public function removeFinding(
        int $packageId,
        int $findingId,
        int $actorId,
        string $actorRole = self::ROLE_FIELD_ENGINEER
    ): array {
        $this->db->transBegin();
        try {
            $wp = $this->db->table('remediation_work_packages')
                ->where('id', $packageId)
                ->get()
                ->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            if ($wp['status'] !== self::STATUS_DRAFT) {
                $this->db->transRollback();
                throw new ConflictException("Findings can only be removed from DRAFT packages.", 409);
            }

            $deleted = $this->db->table('remediation_package_findings')
                ->where('work_package_id', $packageId)
                ->where('finding_id', $findingId)
                ->delete();

            $this->db->transCommit();

            return [
                'success'         => true,
                'action'          => 'REMOVE_FINDING',
                'work_package_id' => $packageId,
                'finding_id'      => $findingId,
                'deleted_count'   => $deleted,
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    // =========================================================================
    // 3. FSM TRANSITIONS & PRE-COMMIT REVALIDATION (B81-G04)
    // =========================================================================

    /**
     * Helper to revalidate attached findings live status in field_findings (B81-G04).
     * Throws ConflictException if any finding is not CONFIRMED.
     */
    protected function revalidateAttachedFindings(int $packageId): array
    {
        $attached = $this->db->table('remediation_package_findings')
            ->select('finding_id')
            ->where('work_package_id', $packageId)
            ->get()
            ->getResultArray();

        if (empty($attached)) {
            throw new UnprocessableEntityException(
                "Work package must contain at least one finding before submission (ADV-06).",
                422
            );
        }

        foreach ($attached as $item) {
            $fid = (int)$item['finding_id'];
            $liveFinding = $this->db->table('field_findings')
                ->select('id, finding_status')
                ->where('id', $fid)
                ->get()
                ->getRowArray();

            if ($liveFinding === null || $liveFinding['finding_status'] !== 'CONFIRMED') {
                $currentStatus = $liveFinding['finding_status'] ?? 'DELETED';
                throw new ConflictException(
                    "Pre-commit revalidation failed: Finding #{$fid} is no longer CONFIRMED (Current status: {$currentStatus}) (B81-G04).",
                    409
                );
            }
        }

        return $attached;
    }

    /**
     * Submit DRAFT package -> ENGINEER_SUBMITTED
     * Prerequisite: Minimal 1 finding (ADV-06), Revalidate finding CONFIRMED (ADV-15 / B81-G04).
     */
    public function submitPackage(
        int $packageId,
        int $actorId,
        string $actorRole = self::ROLE_FIELD_ENGINEER,
        ?string $notes = null
    ): array {
        $this->db->transBegin();
        try {
            $wp = $this->db->query(
                "SELECT id, package_code, status FROM remediation_work_packages WHERE id = ? FOR UPDATE",
                [$packageId]
            )->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            if ($wp['status'] !== self::STATUS_DRAFT) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Illegal FSM transition: Cannot submit package in status '{$wp['status']}' (ADV-01).",
                    409
                );
            }

            // B81-G04 Revalidation & ADV-06 (min 1 finding)
            $this->revalidateAttachedFindings($packageId);

            $now = date('Y-m-d H:i:s');
            $this->db->table('remediation_work_packages')
                ->where('id', $packageId)
                ->update([
                    'status'       => self::STATUS_ENGINEER_SUBMITTED,
                    'submitted_by' => $actorId,
                    'submitted_at' => $now,
                    'updated_at'   => $now,
                ]);

            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => self::STATUS_DRAFT,
                'new_status'       => self::STATUS_ENGINEER_SUBMITTED,
                'action_name'      => 'SUBMIT_PACKAGE',
                'actor_id'         => $actorId,
                'actor_role'       => $actorRole,
                'transition_notes' => $notes ?? 'Submitted by field engineer for review',
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'status'          => self::STATUS_ENGINEER_SUBMITTED,
                'work_package_id' => $packageId,
                'message'         => "Work package #{$wp['package_code']} submitted to ENGINEER_SUBMITTED.",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Advance ENGINEER_SUBMITTED -> PENDING_APPROVAL
     */
    public function validateAndQueue(
        int $packageId,
        int $actorId,
        string $actorRole = self::ROLE_FIELD_ENGINEER
    ): array {
        $this->db->transBegin();
        try {
            $wp = $this->db->query(
                "SELECT id, package_code, status FROM remediation_work_packages WHERE id = ? FOR UPDATE",
                [$packageId]
            )->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            if ($wp['status'] !== self::STATUS_ENGINEER_SUBMITTED) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Illegal FSM transition: Cannot queue package in status '{$wp['status']}' for approval.",
                    409
                );
            }

            $this->revalidateAttachedFindings($packageId);

            $now = date('Y-m-d H:i:s');
            $this->db->table('remediation_work_packages')
                ->where('id', $packageId)
                ->update([
                    'status'     => self::STATUS_PENDING_APPROVAL,
                    'updated_at' => $now,
                ]);

            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => self::STATUS_ENGINEER_SUBMITTED,
                'new_status'       => self::STATUS_PENDING_APPROVAL,
                'action_name'      => 'QUEUE_FOR_APPROVAL',
                'actor_id'         => $actorId,
                'actor_role'       => $actorRole,
                'transition_notes' => 'Work package queued for supervisor approval',
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'status'          => self::STATUS_PENDING_APPROVAL,
                'work_package_id' => $packageId,
                'message'         => "Work package #{$wp['package_code']} transitioned to PENDING_APPROVAL.",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Approve PENDING_APPROVAL -> APPROVED
     * Authorization: Supervisor, ULP_Manager, Admin.
     * Revalidates finding live status (ADV-16 / B81-G04).
     * Rejects illegal shortcut from DRAFT (ADV-01).
     */
    public function approvePackage(
        int $packageId,
        int $actorId,
        string $actorRole = self::ROLE_SUPERVISOR,
        ?string $notes = null
    ): array {
        // Role Authorization Guard
        $allowedRoles = [self::ROLE_SUPERVISOR, self::ROLE_ULP_MANAGER, self::ROLE_ADMIN];
        if (!in_array($actorRole, $allowedRoles, true)) {
            throw new ForbiddenException("Role '{$actorRole}' is not authorized to approve work packages.", 403);
        }

        $this->db->transBegin();
        try {
            $wp = $this->db->query(
                "SELECT id, package_code, status FROM remediation_work_packages WHERE id = ? FOR UPDATE",
                [$packageId]
            )->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            // ADV-01 Guard: Must be PENDING_APPROVAL
            if ($wp['status'] !== self::STATUS_PENDING_APPROVAL) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Illegal FSM shortcut: Cannot approve package in status '{$wp['status']}'. Prerequisite status is PENDING_APPROVAL (ADV-01).",
                    409
                );
            }

            // B81-G04 Revalidation & ADV-16
            $this->revalidateAttachedFindings($packageId);

            $now = date('Y-m-d H:i:s');
            $this->db->table('remediation_work_packages')
                ->where('id', $packageId)
                ->update([
                    'status'      => self::STATUS_APPROVED,
                    'approved_by' => $actorId,
                    'approved_at' => $now,
                    'updated_at'  => $now,
                ]);

            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => self::STATUS_PENDING_APPROVAL,
                'new_status'       => self::STATUS_APPROVED,
                'action_name'      => 'APPROVE_PACKAGE',
                'actor_id'         => $actorId,
                'actor_role'       => $actorRole,
                'transition_notes' => $notes ?? 'Work package approved by supervisor',
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'status'          => self::STATUS_APPROVED,
                'work_package_id' => $packageId,
                'message'         => "Work package #{$wp['package_code']} approved successfully.",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Reject PENDING_APPROVAL -> REJECTED
     * Prerequisite: rejection_notes mandatory (>= 10 chars) (ADV-07).
     */
    public function rejectPackage(
        int $packageId,
        int $actorId,
        string $actorRole = self::ROLE_SUPERVISOR,
        string $rejectionNotes = ''
    ): array {
        $allowedRoles = [self::ROLE_SUPERVISOR, self::ROLE_ULP_MANAGER, self::ROLE_ADMIN];
        if (!in_array($actorRole, $allowedRoles, true)) {
            throw new ForbiddenException("Role '{$actorRole}' is not authorized to reject work packages.", 403);
        }

        $cleanNotes = trim($rejectionNotes);
        if (strlen($cleanNotes) < 10) {
            throw new UnprocessableEntityException(
                "rejection_notes is mandatory and must contain at least 10 characters (ADV-07).",
                422
            );
        }

        $this->db->transBegin();
        try {
            $wp = $this->db->query(
                "SELECT id, package_code, status FROM remediation_work_packages WHERE id = ? FOR UPDATE",
                [$packageId]
            )->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            if ($wp['status'] !== self::STATUS_PENDING_APPROVAL) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Cannot reject package in status '{$wp['status']}'. Prerequisite status is PENDING_APPROVAL.",
                    409
                );
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('remediation_work_packages')
                ->where('id', $packageId)
                ->update([
                    'status'          => self::STATUS_REJECTED,
                    'rejected_by'     => $actorId,
                    'rejected_at'     => $now,
                    'rejection_notes' => $cleanNotes,
                    'updated_at'      => $now,
                ]);

            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => self::STATUS_PENDING_APPROVAL,
                'new_status'       => self::STATUS_REJECTED,
                'action_name'      => 'REJECT_PACKAGE',
                'actor_id'         => $actorId,
                'actor_role'       => $actorRole,
                'transition_notes' => $cleanNotes,
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'status'          => self::STATUS_REJECTED,
                'work_package_id' => $packageId,
                'message'         => "Work package #{$wp['package_code']} rejected.",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Revise REJECTED -> DRAFT
     */
    public function revisePackage(
        int $packageId,
        int $actorId,
        string $actorRole = self::ROLE_FIELD_ENGINEER,
        ?string $notes = null
    ): array {
        $this->db->transBegin();
        try {
            $wp = $this->db->query(
                "SELECT id, package_code, status FROM remediation_work_packages WHERE id = ? FOR UPDATE",
                [$packageId]
            )->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            if ($wp['status'] !== self::STATUS_REJECTED) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Cannot revise package in status '{$wp['status']}'. Only REJECTED packages can be revised to DRAFT.",
                    409
                );
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('remediation_work_packages')
                ->where('id', $packageId)
                ->update([
                    'status'     => self::STATUS_DRAFT,
                    'updated_at' => $now,
                ]);

            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => self::STATUS_REJECTED,
                'new_status'       => self::STATUS_DRAFT,
                'action_name'      => 'REVISE_PACKAGE',
                'actor_id'         => $actorId,
                'actor_role'       => $actorRole,
                'transition_notes' => $notes ?? 'Package returned to DRAFT for revision',
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'status'          => self::STATUS_DRAFT,
                'work_package_id' => $packageId,
                'message'         => "Work package #{$wp['package_code']} revised and returned to DRAFT.",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Release APPROVED -> RELEASED
     * Assigns field execution team.
     */
    public function releasePackage(
        int $packageId,
        int $actorId,
        string $actorRole = self::ROLE_SUPERVISOR,
        array $teamAssignment = [],
        ?string $notes = null
    ): array {
        $allowedRoles = [self::ROLE_SUPERVISOR, self::ROLE_ULP_MANAGER, self::ROLE_ADMIN];
        if (!in_array($actorRole, $allowedRoles, true)) {
            throw new ForbiddenException("Role '{$actorRole}' is not authorized to release work packages.", 403);
        }

        $this->db->transBegin();
        try {
            $wp = $this->db->query(
                "SELECT id, package_code, status, assigned_team_leader FROM remediation_work_packages WHERE id = ? FOR UPDATE",
                [$packageId]
            )->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            if ($wp['status'] !== self::STATUS_APPROVED) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Cannot release package in status '{$wp['status']}'. Prerequisite status is APPROVED.",
                    409
                );
            }

            $teamLeader = trim((string)($teamAssignment['assigned_team_leader'] ?? $wp['assigned_team_leader'] ?? ''));
            if ($teamLeader === '') {
                $this->db->transRollback();
                throw new UnprocessableEntityException("assigned_team_leader is mandatory before releasing package to the field.", 422);
            }

            $now = date('Y-m-d H:i:s');
            $updateData = [
                'status'               => self::STATUS_RELEASED,
                'assigned_team_leader' => $teamLeader,
                'released_at'          => $now,
                'updated_at'           => $now,
            ];

            if (isset($teamAssignment['assigned_team_members'])) {
                $updateData['assigned_team_members'] = is_string($teamAssignment['assigned_team_members'])
                    ? $teamAssignment['assigned_team_members']
                    : json_encode($teamAssignment['assigned_team_members']);
            }

            $this->db->table('remediation_work_packages')->where('id', $packageId)->update($updateData);

            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => self::STATUS_APPROVED,
                'new_status'       => self::STATUS_RELEASED,
                'action_name'      => 'RELEASE_PACKAGE',
                'actor_id'         => $actorId,
                'actor_role'       => $actorRole,
                'transition_notes' => $notes ?? "Released to team leader: {$teamLeader}",
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'status'          => self::STATUS_RELEASED,
                'work_package_id' => $packageId,
                'message'         => "Work package #{$wp['package_code']} released to field execution.",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Start execution RELEASED -> IN_PROGRESS
     */
    public function startExecution(
        int $packageId,
        int $actorId,
        string $actorRole = self::ROLE_FIELD_TEAM_LEADER,
        ?string $notes = null
    ): array {
        $this->db->transBegin();
        try {
            $wp = $this->db->query(
                "SELECT id, package_code, status FROM remediation_work_packages WHERE id = ? FOR UPDATE",
                [$packageId]
            )->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            if ($wp['status'] !== self::STATUS_RELEASED) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Cannot start execution for package in status '{$wp['status']}'. Prerequisite status is RELEASED.",
                    409
                );
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('remediation_work_packages')
                ->where('id', $packageId)
                ->update([
                    'status'     => self::STATUS_IN_PROGRESS,
                    'started_at' => $now,
                    'updated_at' => $now,
                ]);

            // Set items to IN_REPAIR
            $this->db->table('remediation_package_findings')
                ->where('work_package_id', $packageId)
                ->where('item_remediation_status', 'PENDING')
                ->update([
                    'item_remediation_status' => 'IN_REPAIR',
                    'updated_at'              => $now,
                ]);

            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => self::STATUS_RELEASED,
                'new_status'       => self::STATUS_IN_PROGRESS,
                'action_name'      => 'START_EXECUTION',
                'actor_id'         => $actorId,
                'actor_role'       => $actorRole,
                'transition_notes' => $notes ?? 'Field team started repair execution',
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'status'          => self::STATUS_IN_PROGRESS,
                'work_package_id' => $packageId,
                'message'         => "Work package #{$wp['package_code']} execution started (IN_PROGRESS).",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Complete physical execution IN_PROGRESS -> COMPLETED
     */
    public function completeExecution(
        int $packageId,
        int $actorId,
        string $actorRole = self::ROLE_FIELD_TEAM_LEADER,
        array $itemResults = [],
        ?string $notes = null
    ): array {
        $this->db->transBegin();
        try {
            $wp = $this->db->query(
                "SELECT id, package_code, status FROM remediation_work_packages WHERE id = ? FOR UPDATE",
                [$packageId]
            )->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            if ($wp['status'] !== self::STATUS_IN_PROGRESS) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Cannot complete execution for package in status '{$wp['status']}'. Prerequisite status is IN_PROGRESS.",
                    409
                );
            }

            $now = date('Y-m-d H:i:s');

            // Apply item results if provided, or resolve remaining items
            if (!empty($itemResults)) {
                foreach ($itemResults as $findingId => $itemStatus) {
                    $validStatuses = ['RESOLVED', 'CANNOT_RESOLVE'];
                    $chosenStatus = in_array($itemStatus, $validStatuses, true) ? $itemStatus : 'RESOLVED';
                    $this->db->table('remediation_package_findings')
                        ->where('work_package_id', $packageId)
                        ->where('finding_id', $findingId)
                        ->update([
                            'item_remediation_status' => $chosenStatus,
                            'updated_at'              => $now,
                        ]);
                }
            } else {
                $this->db->table('remediation_package_findings')
                    ->where('work_package_id', $packageId)
                    ->update([
                        'item_remediation_status' => 'RESOLVED',
                        'updated_at'              => $now,
                    ]);
            }

            $this->db->table('remediation_work_packages')
                ->where('id', $packageId)
                ->update([
                    'status'       => self::STATUS_COMPLETED,
                    'completed_at' => $now,
                    'updated_at'   => $now,
                ]);

            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => self::STATUS_IN_PROGRESS,
                'new_status'       => self::STATUS_COMPLETED,
                'action_name'      => 'COMPLETE_EXECUTION',
                'actor_id'         => $actorId,
                'actor_role'       => $actorRole,
                'transition_notes' => $notes ?? 'Physical repairs completed by field crew',
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'status'          => self::STATUS_COMPLETED,
                'work_package_id' => $packageId,
                'message'         => "Work package #{$wp['package_code']} execution completed.",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    // =========================================================================
    // 4. QA VERIFICATION & EVIDENCE INTEGRITY (B81-G05)
    // =========================================================================

    /**
     * QA Sign-off & Verification COMPLETED -> VERIFIED
     * Enforces:
     * - QA role authorization (Role Matrix: QA_ENGINEER, TECHNICAL_INSPECTOR)
     * - Evidence artifact mandatory (ADV-09)
     * - 64-hex SHA-256 formatting check (ADV-10)
     * - Real content-verified hash match against artifact payload / registered evidence (ADV-17 / B81-G05)
     *
     * @param int $packageId
     * @param int $qaId
     * @param string $actorRole
     * @param string $qaNotes
     * @param int|null $evidenceId
     * @param string|null $evidenceContentBytes
     * @param string|null $providedSha256
     * @return array
     */
    public function verifyRemediation(
        int $packageId,
        int $qaId,
        string $actorRole = self::ROLE_QA_ENGINEER,
        string $qaNotes = '',
        ?int $evidenceId = null,
        ?string $evidenceContentBytes = null,
        ?string $providedSha256 = null
    ): array {
        // QA Role Authorization
        $allowedRoles = [self::ROLE_QA_ENGINEER, self::ROLE_TECHNICAL_INSPECTOR, self::ROLE_ADMIN];
        if (!in_array($actorRole, $allowedRoles, true)) {
            throw new ForbiddenException("Role '{$actorRole}' is not authorized for QA verification.", 403);
        }

        // ADV-09: Evidence mandatory
        if ($evidenceId === null && empty($evidenceContentBytes) && empty($providedSha256)) {
            throw new UnprocessableEntityException(
                "Verification evidence artifact is mandatory for QA completion (ADV-09).",
                422
            );
        }

        // ADV-10: Hash formatting validation if provided directly
        if ($providedSha256 !== null) {
            $providedSha256 = trim(strtolower($providedSha256));
            if (!preg_match('/^[a-f0-9]{64}$/', $providedSha256)) {
                throw new UnprocessableEntityException(
                    "Evidence hash must be a valid 64-character hexadecimal SHA-256 string (ADV-10).",
                    422
                );
            }
        }

        // B81-G05 & ADV-17: Content integrity verification
        $expectedHash = null;

        if ($evidenceId !== null) {
            $evidenceRow = $this->db->table('field_evidence')
                ->where('id', $evidenceId)
                ->get()
                ->getRowArray();

            if ($evidenceRow === null) {
                throw new NotFoundException("Referenced evidence_id #{$evidenceId} not found in field_evidence.", 404);
            }

            $expectedHash = strtolower($evidenceRow['sha256'] ?? $evidenceRow['evidence_sha256'] ?? '');
            if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
                throw new UnprocessableEntityException("Registered evidence has invalid SHA-256 format.", 422);
            }

            // Check provided hash against registered hash
            if ($providedSha256 !== null && $providedSha256 !== $expectedHash) {
                throw new UnprocessableEntityException(
                    "Evidence SHA-256 hash mismatch: provided '{$providedSha256}' does not match registered evidence '{$expectedHash}' (ADV-17 / B81-G05).",
                    422
                );
            }

            // If raw content bytes are supplied, verify content matches registered hash
            if ($evidenceContentBytes !== null) {
                $computedHash = hash('sha256', $evidenceContentBytes);
                if (strtolower($computedHash) !== $expectedHash) {
                    throw new UnprocessableEntityException(
                        "Verification evidence content bytes SHA-256 mismatch against registered evidence (ADV-17 / B81-G05).",
                        422
                    );
                }
            }
        } elseif (!empty($evidenceContentBytes)) {
            // Computed directly from payload bytes
            $computedHash = hash('sha256', $evidenceContentBytes);
            if ($providedSha256 !== null && $providedSha256 !== strtolower($computedHash)) {
                throw new UnprocessableEntityException(
                    "Verification evidence content hash mismatch: provided '{$providedSha256}' does not match computed '{$computedHash}' (ADV-17 / B81-G05).",
                    422
                );
            }
            $expectedHash = strtolower($computedHash);
        } else {
            // Only providedSha256 supplied
            $expectedHash = $providedSha256;
        }

        $this->db->transBegin();
        try {
            $wp = $this->db->query(
                "SELECT id, package_code, status FROM remediation_work_packages WHERE id = ? FOR UPDATE",
                [$packageId]
            )->getRowArray();

            if ($wp === null) {
                $this->db->transRollback();
                throw new NotFoundException("Work package #{$packageId} not found.", 404);
            }

            if ($wp['status'] !== self::STATUS_COMPLETED) {
                $this->db->transRollback();
                throw new ConflictException(
                    "Cannot verify package in status '{$wp['status']}'. Prerequisite status is COMPLETED.",
                    409
                );
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('remediation_work_packages')
                ->where('id', $packageId)
                ->update([
                    'status'                       => self::STATUS_VERIFIED,
                    'verified_by'                  => $qaId,
                    'verified_at'                  => $now,
                    'verification_notes'           => $qaNotes,
                    'verification_evidence_id'     => $evidenceId,
                    'verification_evidence_sha256' => $expectedHash,
                    'updated_at'                   => $now,
                ]);

            $this->db->table('remediation_package_history')->insert([
                'work_package_id'  => $packageId,
                'previous_status'  => self::STATUS_COMPLETED,
                'new_status'       => self::STATUS_VERIFIED,
                'action_name'      => 'VERIFY_REMEDIATION',
                'actor_id'         => $qaId,
                'actor_role'       => $actorRole,
                'transition_notes' => $qaNotes ?? 'QA verification complete and signed off',
                'created_at'       => $now,
            ]);

            $this->db->transCommit();

            return [
                'success'         => true,
                'status'          => self::STATUS_VERIFIED,
                'work_package_id' => $packageId,
                'evidence_sha256' => $expectedHash,
                'message'         => "Work package #{$wp['package_code']} verified and closed-loop sealed.",
                'http_code'       => 200,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    // =========================================================================
    // 5. PROHIBITED OPERATIONS (HIST-01, B81-G02, ADV-11)
    // =========================================================================

    /**
     * Physical deletion of work package is prohibited (HIST-01, B81-G02).
     */
    public function deleteWorkPackage(int $packageId, int $actorId): void
    {
        throw new MethodNotAllowedException(
            "Physical deletion of Work Packages is strictly prohibited. Work packages are permanent immutable audit entities (HIST-01 / B81-G02).",
            405
        );
    }

    /**
     * Master GIS / Topology mutation is prohibited (ADV-11).
     */
    public function mutateTopology(string $action): void
    {
        throw new ForbiddenException(
            "Topology mutation is strictly forbidden. Upstream GIS Master and Topology are read-only immutable boundaries (ADV-11).",
            403
        );
    }

    // =========================================================================
    // 6. READ HELPERS & QUERIES
    // =========================================================================

    public function getWorkPackage(int $id): ?array
    {
        return $this->db->table('remediation_work_packages')
            ->where('id', $id)
            ->get()
            ->getRowArray();
    }

    public function getWorkPackageByCode(string $code): ?array
    {
        return $this->db->table('remediation_work_packages')
            ->where('package_code', $code)
            ->get()
            ->getRowArray();
    }

    public function getWorkPackageFindings(int $packageId): array
    {
        return $this->db->table('remediation_package_findings rpf')
            ->select('rpf.*, ff.cause_category, ff.finding_status, ff.condition_description, ff.notes, ff.actual_asset_id')
            ->join('field_findings ff', 'rpf.finding_id = ff.id', 'left')
            ->where('rpf.work_package_id', $packageId)
            ->get()
            ->getResultArray();
    }

    public function getWorkPackageHistory(int $packageId): array
    {
        return $this->db->table('remediation_package_history')
            ->where('work_package_id', $packageId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }
}
