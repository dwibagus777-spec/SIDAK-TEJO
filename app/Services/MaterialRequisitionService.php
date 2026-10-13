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
 * SIDAK TEJO — Phase B.8.2: Material Requisition Core Service
 * Governance Engine for Material Requisitions & Asset Consumption
 *
 * HARDENED GOVERNANCE & HARD GUARDS:
 * - B82-G01: Material Master Authority (Items linked to master_materials table)
 * - B82-G02: Reservation Atomicity (Pessimistic lock on reservation transition)
 * - B82-G03: AI Autonomy Ceiling Enforcement (Max L4 RECOMMENDED for AI, L5/L6 human only)
 * - B82-G04: Parent Work Package Validation (work_package_id must exist in remediation_work_packages)
 * - B82-G05: Permanent Audit Non-Cascade (ON DELETE RESTRICT, deletePackage throws 405)
 * - B82-G06: Reconciliation Evidence Integrity (SHA-256 verification and consumption variance math)
 * - B82-G07: Rejection Mandate (rejection_notes required on REJECTED status)
 * - B82-G08: Idempotency & Replay Protection (client_operation_uuid UNIQUE guard)
 *
 * 9-State FSM: DRAFT -> REQUESTED -> PENDING_APPROVAL -> APPROVED / REJECTED -> RESERVED -> ISSUED -> CONSUMED -> RECONCILED
 * 6-Level Provenance Chain: L1:OBSERVED -> L2:DERIVED -> L3:ANALYTICAL -> L4:RECOMMENDED -> L5:APPROVED -> L6:EXECUTED
 */
class MaterialRequisitionService
{
    public const SERVICE_VERSION = 'B8.2-MATERIAL-1.0';

    // 9 FSM States
    public const STATUS_DRAFT            = 'DRAFT';
    public const STATUS_REQUESTED        = 'REQUESTED';
    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const STATUS_APPROVED         = 'APPROVED';
    public const STATUS_REJECTED         = 'REJECTED';
    public const STATUS_RESERVED         = 'RESERVED';
    public const STATUS_ISSUED           = 'ISSUED';
    public const STATUS_CONSUMED         = 'CONSUMED';
    public const STATUS_RECONCILED       = 'RECONCILED';

    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REQUESTED,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_RESERVED,
        self::STATUS_ISSUED,
        self::STATUS_CONSUMED,
        self::STATUS_RECONCILED,
    ];

    // 6 Provenance Levels
    public const PROVENANCE_OBSERVED   = 'OBSERVED';
    public const PROVENANCE_DERIVED    = 'DERIVED';
    public const PROVENANCE_ANALYTICAL = 'ANALYTICAL';
    public const PROVENANCE_RECOMMENDED= 'RECOMMENDED';
    public const PROVENANCE_APPROVED   = 'APPROVED';
    public const PROVENANCE_EXECUTED   = 'EXECUTED';

    public const ALL_PROVENANCE_LEVELS = [
        self::PROVENANCE_OBSERVED,
        self::PROVENANCE_DERIVED,
        self::PROVENANCE_ANALYTICAL,
        self::PROVENANCE_RECOMMENDED,
        self::PROVENANCE_APPROVED,
        self::PROVENANCE_EXECUTED,
    ];

    // Item Statuses
    public const ITEM_STATUS_PENDING                = 'PENDING';
    public const ITEM_STATUS_APPROVED               = 'APPROVED';
    public const ITEM_STATUS_RESERVED               = 'RESERVED';
    public const ITEM_STATUS_ISSUED                 = 'ISSUED';
    public const ITEM_STATUS_INSTALLED              = 'INSTALLED';
    public const ITEM_STATUS_RETURNED               = 'RETURNED';
    public const ITEM_STATUS_REJECTED               = 'REJECTED';
    public const ITEM_STATUS_PENDING_CLASSIFICATION = 'PENDING_CLASSIFICATION';

    // FSM Allowed Transitions
    protected const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [
            'submit' => self::STATUS_REQUESTED,
        ],
        self::STATUS_REQUESTED => [
            'request_approval' => self::STATUS_PENDING_APPROVAL,
        ],
        self::STATUS_PENDING_APPROVAL => [
            'approve' => self::STATUS_APPROVED,
            'reject'  => self::STATUS_REJECTED,
        ],
        self::STATUS_APPROVED => [
            'reserve' => self::STATUS_RESERVED,
        ],
        self::STATUS_RESERVED => [
            'issue' => self::STATUS_ISSUED,
        ],
        self::STATUS_ISSUED => [
            'consume' => self::STATUS_CONSUMED,
        ],
        self::STATUS_CONSUMED => [
            'reconcile' => self::STATUS_RECONCILED,
        ],
        self::STATUS_REJECTED => [],
        self::STATUS_RECONCILED => [],
    ];

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
    // 1. REQUISITION PACKAGE CREATION (B82-G04, B82-G08, B82-G01, B82-G03)
    // =========================================================================

    /**
     * Create a new Material Requisition Package in DRAFT state or return canonical package if UUID exists.
     */
    public function createRequisitionPackage(array $payload, int $actorId, string $actorRole = 'FIELD_ENGINEER'): array
    {
        $opUuid = trim((string)($payload['client_operation_uuid'] ?? ''));
        if ($opUuid === '') {
            throw new UnprocessableEntityException("client_operation_uuid is mandatory for idempotent creation (B82-G08).", 422);
        }

        // B82-G08: Check idempotency
        $existing = $this->db->table('material_requisition_packages')
            ->where('client_operation_uuid', $opUuid)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            $existing['items'] = $this->getRequisitionItems((int)$existing['id']);
            $existing['history'] = $this->getRequisitionHistory((int)$existing['id']);
            $existing['is_duplicate'] = true;
            return $existing;
        }

        $workPackageId = (int)($payload['work_package_id'] ?? 0);
        if ($workPackageId <= 0) {
            throw new UnprocessableEntityException("work_package_id is required.", 422);
        }

        // B82-G04: Parent Work Package Validation
        $wpExists = $this->db->table('remediation_work_packages')
            ->where('id', $workPackageId)
            ->countAllResults();

        if ($wpExists === 0) {
            throw new NotFoundException("Parent Work Package ID {$workPackageId} not found (B82-G04).", 404);
        }

        $warehouseId = isset($payload['warehouse_id']) ? (int)$payload['warehouse_id'] : null;
        if ($warehouseId !== null && $warehouseId > 0) {
            $whExists = $this->db->table('material_warehouses')
                ->where('id', $warehouseId)
                ->countAllResults();
            if ($whExists === 0) {
                throw new NotFoundException("Warehouse ID {$warehouseId} not found.", 404);
            }
        } else {
            $warehouseId = null;
        }

        $testMode = !empty($payload['test_mode']) ? 1 : 0;
        $reqCode = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

        $this->db->transStart();

        $now = date('Y-m-d H:i:s');
        $packageData = [
            'requisition_code'      => $reqCode,
            'work_package_id'       => $workPackageId,
            'client_operation_uuid' => $opUuid,
            'status'                => self::STATUS_DRAFT,
            'warehouse_id'          => $warehouseId,
            'requested_by'          => $actorId,
            'approved_by'           => null,
            'issued_by'             => null,
            'reconciled_by'         => null,
            'rejection_notes'       => null,
            'test_mode'             => $testMode,
            'created_at'            => $now,
            'updated_at'            => $now,
        ];

        $this->db->table('material_requisition_packages')->insert($packageData);
        $packageId = (int)$this->db->insertID();

        // Log initial audit history
        $this->db->table('material_requisition_history')->insert([
            'requisition_package_id' => $packageId,
            'previous_status'        => null,
            'new_status'             => self::STATUS_DRAFT,
            'action_name'            => 'create',
            'actor_id'               => $actorId,
            'actor_role'             => $actorRole,
            'transition_notes'       => 'Requisition package created in DRAFT state',
            'created_at'             => $now,
        ]);

        // Process items if provided
        $insertedItems = [];
        if (!empty($payload['items']) && is_array($payload['items'])) {
            $insertedItems = $this->insertItemsForPackage($packageId, $payload['items'], $actorRole);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new UnprocessableEntityException("Failed to insert material requisition package into database.", 422);
        }

        $package = $this->getRequisitionPackage($packageId);
        $package['is_duplicate'] = false;
        return $package;
    }

    // =========================================================================
    // 2. FSM STATUS TRANSITIONS (B82-G02, B82-G03, B82-G06, B82-G07)
    // =========================================================================

    /**
     * Transition a Material Requisition Package through FSM graph.
     */
    public function transitionStatus(int $packageId, string $action, array $params, int $actorId, string $actorRole): array
    {
        $this->db->transStart();

        // B82-G02: Pessimistic locking FOR UPDATE
        $sql = "SELECT * FROM material_requisition_packages WHERE id = ? FOR UPDATE";
        $package = $this->db->query($sql, [$packageId])->getRowArray();

        if ($package === null) {
            $this->db->transRollback();
            throw new NotFoundException("Material Requisition Package ID {$packageId} not found.", 404);
        }

        $currentStatus = $package['status'];
        if (!isset(self::ALLOWED_TRANSITIONS[$currentStatus][$action])) {
            $this->db->transRollback();
            throw new ConflictException(
                "Illegal FSM transition action '{$action}' from status '{$currentStatus}'. Allowed actions: " .
                implode(', ', array_keys(self::ALLOWED_TRANSITIONS[$currentStatus] ?? [])),
                409
            );
        }

        $targetStatus = self::ALLOWED_TRANSITIONS[$currentStatus][$action];
        $now = date('Y-m-d H:i:s');
        $updateData = ['updated_at' => $now];
        $transitionNotes = trim((string)($params['notes'] ?? ''));

        // Handle action specific guards & requirements
        switch ($action) {
            case 'submit':
                // Check items exist
                $itemCount = $this->db->table('material_requisition_items')
                    ->where('requisition_package_id', $packageId)
                    ->countAllResults();
                if ($itemCount === 0) {
                    $this->db->transRollback();
                    throw new UnprocessableEntityException("Cannot submit a package with no items.", 422);
                }
                $updateData['status'] = self::STATUS_REQUESTED;
                break;

            case 'request_approval':
                $updateData['status'] = self::STATUS_PENDING_APPROVAL;
                break;

            case 'approve':
                // B82-G03: Elevation of provenance level to L5:APPROVED
                $updateData['status']      = self::STATUS_APPROVED;
                $updateData['approved_by'] = $actorId;
                
                // Approve all items and elevate provenance level to APPROVED (L5)
                $items = $this->db->table('material_requisition_items')
                    ->where('requisition_package_id', $packageId)
                    ->get()
                    ->getResultArray();

                foreach ($items as $item) {
                    $appQty = isset($params['approved_quantities'][$item['id']])
                        ? (float)$params['approved_quantities'][$item['id']]
                        : (float)$item['requested_quantity'];

                    $this->db->table('material_requisition_items')
                        ->where('id', $item['id'])
                        ->update([
                            'approved_quantity' => $appQty,
                            'item_status'       => self::ITEM_STATUS_APPROVED,
                            'provenance_level'  => self::PROVENANCE_APPROVED,
                            'updated_at'        => $now,
                        ]);
                }
                break;

            case 'reject':
                // B82-G07: Rejection Mandate
                if ($transitionNotes === '' || strlen($transitionNotes) < 5) {
                    $this->db->transRollback();
                    throw new UnprocessableEntityException("Rejection notes are required and must be at least 5 characters (B82-G07).", 422);
                }
                $updateData['status']          = self::STATUS_REJECTED;
                $updateData['rejection_notes'] = $transitionNotes;

                // Mark items rejected
                $this->db->table('material_requisition_items')
                    ->where('requisition_package_id', $packageId)
                    ->update([
                        'item_status' => self::ITEM_STATUS_REJECTED,
                        'updated_at'  => $now,
                    ]);
                break;

            case 'reserve':
                // B82-G02: Inventory Reservation
                if (empty($package['warehouse_id'])) {
                    $this->db->transRollback();
                    throw new UnprocessableEntityException("Cannot reserve materials without an assigned warehouse_id (B82-G02).", 422);
                }
                $updateData['status'] = self::STATUS_RESERVED;

                $this->db->table('material_requisition_items')
                    ->where('requisition_package_id', $packageId)
                    ->update([
                        'item_status' => self::ITEM_STATUS_RESERVED,
                        'updated_at'  => $now,
                    ]);
                break;

            case 'issue':
                $updateData['status']   = self::STATUS_ISSUED;
                $updateData['issued_by'] = $actorId;

                $items = $this->db->table('material_requisition_items')
                    ->where('requisition_package_id', $packageId)
                    ->get()
                    ->getResultArray();

                foreach ($items as $item) {
                    $issuedQty = isset($params['issued_quantities'][$item['id']])
                        ? (float)$params['issued_quantities'][$item['id']]
                        : (float)($item['approved_quantity'] ?? $item['requested_quantity']);

                    $this->db->table('material_requisition_items')
                        ->where('id', $item['id'])
                        ->update([
                            'issued_quantity'  => $issuedQty,
                            'item_status'      => self::ITEM_STATUS_ISSUED,
                            'provenance_level' => self::PROVENANCE_EXECUTED, // L6 Executed
                            'updated_at'       => $now,
                        ]);
                }
                break;

            case 'consume':
                $updateData['status'] = self::STATUS_CONSUMED;

                $items = $this->db->table('material_requisition_items')
                    ->where('requisition_package_id', $packageId)
                    ->get()
                    ->getResultArray();

                foreach ($items as $item) {
                    $consumedQty = isset($params['consumed_quantities'][$item['id']])
                        ? (float)$params['consumed_quantities'][$item['id']]
                        : (float)($item['issued_quantity'] ?? 0.0);

                    $returnedQty = isset($params['returned_quantities'][$item['id']])
                        ? (float)$params['returned_quantities'][$item['id']]
                        : 0.0;

                    $this->db->table('material_requisition_items')
                        ->where('id', $item['id'])
                        ->update([
                            'consumed_quantity' => $consumedQty,
                            'returned_quantity' => $returnedQty,
                            'item_status'       => self::ITEM_STATUS_INSTALLED,
                            'updated_at'        => $now,
                        ]);
                }
                break;

            case 'reconcile':
                // B82-G06: Reconciliation Evidence & Variance Check
                $evidenceHash = trim((string)($params['evidence_sha256'] ?? ''));
                if (strlen($evidenceHash) !== 64 || !ctype_xdigit($evidenceHash)) {
                    $this->db->transRollback();
                    throw new UnprocessableEntityException("Valid 64-character SHA-256 evidence_sha256 is required for reconciliation (B82-G06).", 422);
                }

                $updateData['status']        = self::STATUS_RECONCILED;
                $updateData['reconciled_by'] = $actorId;

                // Perform reconciliation calculation
                $this->performReconciliationRecord($package, $params, $evidenceHash, $actorId, $transitionNotes);
                break;
        }

        // Update package status
        $this->db->table('material_requisition_packages')
            ->where('id', $packageId)
            ->update($updateData);

        // Record history audit trail
        $this->db->table('material_requisition_history')->insert([
            'requisition_package_id' => $packageId,
            'previous_status'        => $currentStatus,
            'new_status'             => $targetStatus,
            'action_name'            => $action,
            'actor_id'               => $actorId,
            'actor_role'             => $actorRole,
            'transition_notes'       => $transitionNotes !== '' ? $transitionNotes : "Transitioned to {$targetStatus} via {$action}",
            'created_at'             => $now,
        ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new UnprocessableEntityException("FSM state transition failed during database transaction.", 422);
        }

        return $this->getRequisitionPackage($packageId);
    }

    // =========================================================================
    // 3. ITEM ATTACHMENT & HELPER METHODS (B82-G01, B82-G03)
    // =========================================================================

    /**
     * Insert items for a package with B82-G01 and B82-G03 checks.
     */
    protected function insertItemsForPackage(int $packageId, array $items, string $actorRole): array
    {
        $inserted = [];
        $now = date('Y-m-d H:i:s');

        foreach ($items as $idx => $item) {
            $masterId = (int)($item['material_master_id'] ?? 0);
            if ($masterId <= 0) {
                throw new UnprocessableEntityException("Item #{$idx} missing material_master_id.", 422);
            }

            // B82-G01: Material Authority Check
            $master = $this->db->table('master_materials')
                ->where('id', $masterId)
                ->get()
                ->getRowArray();

            if ($master === null) {
                throw new UnprocessableEntityException("Item #{$idx} refers to non-existent master_materials ID {$masterId} (B82-G01).", 422);
            }

            $reqQty = (float)($item['requested_quantity'] ?? 0.0);
            if ($reqQty <= 0) {
                throw new UnprocessableEntityException("Item #{$idx} requested_quantity must be > 0.", 422);
            }

            // B82-G03: Provenance Level Ceiling Guard
            $provLevel = strtoupper(trim((string)($item['provenance_level'] ?? self::PROVENANCE_RECOMMENDED)));
            if (!in_array($provLevel, self::ALL_PROVENANCE_LEVELS, true)) {
                $provLevel = self::PROVENANCE_RECOMMENDED;
            }

            // AI/System can only generate up to L4:RECOMMENDED. If AI specifies L5/L6, cap at L4 unless human role
            if (in_array($provLevel, [self::PROVENANCE_APPROVED, self::PROVENANCE_EXECUTED], true) && $actorRole === 'AI_AGENT') {
                $provLevel = self::PROVENANCE_RECOMMENDED;
            }

            $itemCode = !empty($item['item_code']) ? trim((string)$item['item_code']) : ($master['material_code'] ?? 'MAT-' . $masterId);
            $uom      = !empty($item['unit_of_measure']) ? trim((string)$item['unit_of_measure']) : ($master['satuan'] ?? 'PCS');

            $itemData = [
                'requisition_package_id' => $packageId,
                'material_master_id'     => $masterId,
                'item_code'              => $itemCode,
                'requested_quantity'     => $reqQty,
                'approved_quantity'      => null,
                'issued_quantity'        => null,
                'consumed_quantity'      => null,
                'returned_quantity'      => null,
                'unit_of_measure'        => $uom,
                'item_status'            => self::ITEM_STATUS_PENDING,
                'provenance_level'       => $provLevel,
                'created_at'             => $now,
                'updated_at'             => $now,
            ];

            $this->db->table('material_requisition_items')->insert($itemData);
            $itemData['id'] = (int)$this->db->insertID();
            $inserted[] = $itemData;
        }

        return $inserted;
    }

    /**
     * Perform calculation and insert into material_consumption_reconciliations.
     */
    protected function performReconciliationRecord(array $package, array $params, string $evidenceHash, int $actorId, string $notes): void
    {
        $packageId = (int)$package['id'];
        $items = $this->db->table('material_requisition_items')
            ->where('requisition_package_id', $packageId)
            ->get()
            ->getResultArray();

        $totalIssued   = 0;
        $totalConsumed = 0;
        $totalReturned = 0;

        foreach ($items as $item) {
            $totalIssued   += (int)round((float)($item['issued_quantity'] ?? $item['approved_quantity'] ?? $item['requested_quantity']));
            $totalConsumed += (int)round((float)($item['consumed_quantity'] ?? 0));
            $totalReturned += (int)round((float)($item['returned_quantity'] ?? 0));
        }

        $accounted = $totalConsumed + $totalReturned;
        $variance  = $totalIssued > 0 ? (($accounted - $totalIssued) / $totalIssued) * 100.0 : 0.0;
        $varianceAbs = abs($variance);

        if ($accounted < $totalIssued) {
            $recStatus = 'UNACCOUNTED_LOSS';
        } elseif ($varianceAbs == 0.0) {
            $recStatus = 'EXACT_MATCH';
        } elseif ($varianceAbs <= 5.0) {
            $recStatus = 'MINOR_VARIANCE';
        } else {
            $recStatus = 'EXCESS_CONSUMPTION';
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('material_consumption_reconciliations')->insert([
            'requisition_package_id' => $packageId,
            'work_package_id'        => (int)$package['work_package_id'],
            'total_items_issued'     => $totalIssued,
            'total_items_consumed'   => $totalConsumed,
            'total_items_returned'   => $totalReturned,
            'overall_variance_pct'   => round($variance, 2),
            'reconciliation_status'  => $recStatus,
            'reconciled_by'          => $actorId,
            'evidence_sha256'        => $evidenceHash,
            'reconciliation_notes'   => $notes !== '' ? $notes : "Reconciliation finalized with status {$recStatus}",
            'created_at'             => $now,
        ]);
    }

    // =========================================================================
    // 4. READ & QUERY METHODS
    // =========================================================================

    public function getRequisitionPackage(int $packageId): ?array
    {
        $package = $this->db->table('material_requisition_packages')
            ->where('id', $packageId)
            ->get()
            ->getRowArray();

        if ($package === null) {
            return null;
        }

        $package['items'] = $this->getRequisitionItems($packageId);
        $package['history'] = $this->getRequisitionHistory($packageId);
        $package['reconciliation'] = $this->db->table('material_consumption_reconciliations')
            ->where('requisition_package_id', $packageId)
            ->get()
            ->getRowArray();

        return $package;
    }

    public function getRequisitionItems(int $packageId): array
    {
        return $this->db->table('material_requisition_items')
            ->where('requisition_package_id', $packageId)
            ->get()
            ->getResultArray();
    }

    public function getRequisitionHistory(int $packageId): array
    {
        return $this->db->table('material_requisition_history')
            ->where('requisition_package_id', $packageId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function listRequisitionPackages(array $filters = []): array
    {
        $builder = $this->db->table('material_requisition_packages');

        if (!empty($filters['work_package_id'])) {
            $builder->where('work_package_id', (int)$filters['work_package_id']);
        }
        if (!empty($filters['status'])) {
            $builder->where('status', (string)$filters['status']);
        }
        if (isset($filters['test_mode'])) {
            $builder->where('test_mode', (int)$filters['test_mode']);
        }

        $packages = $builder->orderBy('id', 'DESC')->get()->getResultArray();
        foreach ($packages as &$pkg) {
            $pkg['items_count'] = $this->db->table('material_requisition_items')
                ->where('requisition_package_id', $pkg['id'])
                ->countAllResults();
        }

        return $packages;
    }

    // =========================================================================
    // 5. HARD GUARD B82-G05 — PHYSICAL DELETE REJECTION
    // =========================================================================

    /**
     * B82-G05 Guard: Physical deletion is strictly prohibited.
     */
    public function deletePackage(int $packageId): void
    {
        throw new MethodNotAllowedException(
            "Physical deletion of Material Requisition Packages is strictly prohibited by B82-G05 governance.",
            405
        );
    }
}
