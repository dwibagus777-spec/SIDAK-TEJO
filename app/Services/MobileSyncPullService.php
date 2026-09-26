<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use InvalidArgumentException;

/**
 * SIDAK TEJO — Phase B.7.4: Downstream Delta Pull Engine
 *
 * ARCHITECTURAL CONTRACT & HARD GUARDS:
 * - B7-G12:  Monotonic Server Sequence Cursor (journal_seq BIGINT AUTO_INCREMENT)
 * - B7-G13:  Push / Pull Architectural Separation (Pull is strictly isolated from Push)
 * - B7-G14:  Topology Read-Only Read Model (gis_translines = 0, assets = 0, Delta = 0)
 * - B7-G16:  Monotonic Server Cursor High-Water Mark (Replication high-water mark derived from journal_seq)
 * - B7-G17:  Cursor Pagination Safety (next_cursor is strictly the journal_seq of the last record in the fetched window)
 * - B7-G19:  Device Revocation Non-Cascade (Revoked devices blocked from pulling)
 */
class MobileSyncPullService
{
    public const SERVICE_VERSION = 'B7-PULL-1.0';
    public const DEFAULT_PULL_LIMIT = 50;
    public const MAX_PULL_LIMIT = 200;

    protected BaseConnection $db;
    protected FaultCaseService $caseService;

    public function __construct(
        ?BaseConnection $db = null,
        ?FaultCaseService $caseService = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->caseService = $caseService ?? new FaultCaseService($this->db);
    }

    public function getCaseService(): FaultCaseService
    {
        return $this->caseService;
    }

    // =========================================================================
    // 1. DELTA PULL ENGINE (B7-G12, B7-G16, B7-G17)
    // =========================================================================

    /**
     * Pull incremental mutations and domain state changes since the given cursor.
     * Enforces device authentication (B7-G11, B7-G19), bounded pagination (B7-G17),
     * and strictly read-only topology models (B7-G14).
     *
     * @param int $cursor High-water mark journal_seq (default: 0)
     * @param int $limit Maximum records to return in this page (default: 50, max: 200)
     * @param string $deviceId Hardware device ID
     * @param int $userId Requesting user ID
     * @param array $options ['include_topology' => bool, 'user_scope_only' => bool]
     * @return array
     */
    public function pullDelta(
        int $cursor = 0,
        int $limit = self::DEFAULT_PULL_LIMIT,
        string $deviceId = '',
        int $userId = 0,
        array $options = []
    ): array {
        $serverTime = date('Y-m-d H:i:s');

        // 1. Device Authentication & Governance (B7-G11, B7-G19)
        $devCheck = $this->verifyActiveDevice($deviceId);
        if (!$devCheck['valid']) {
            return [
                'status'      => $devCheck['status'],
                'http_code'   => 403,
                'cursor_in'   => $cursor,
                'next_cursor' => $cursor,
                'has_more'    => false,
                'count'       => 0,
                'server_time' => $serverTime,
                'message'     => $devCheck['message'],
            ];
        }

        // Touch last_seen_at on device
        $this->db->table('mobile_devices')->where('device_id', $deviceId)->update([
            'last_seen_at' => $serverTime,
            'updated_at'   => $serverTime,
        ]);

        // 2. Cursor & Limit Sanitization (Guard B7-G12, B7-G16, B7-G17)
        if ($cursor < 0) {
            return [
                'status'      => 'INVALID_CURSOR',
                'http_code'   => 422,
                'cursor_in'   => $cursor,
                'next_cursor' => 0,
                'has_more'    => false,
                'count'       => 0,
                'server_time' => $serverTime,
                'message'     => 'Cursor sequence cannot be negative. Must be a non-negative integer (Guard B7-G16).',
            ];
        }

        if ($limit <= 0) {
            $limit = self::DEFAULT_PULL_LIMIT;
        } elseif ($limit > self::MAX_PULL_LIMIT) {
            $limit = self::MAX_PULL_LIMIT;
        }

        // 3. Query Delta Window from mobile_sync_journal (B7-G16, B7-G17)
        // Orders strictly by journal_seq ASC with bounded LIMIT
        $journalBuilder = $this->db->table('mobile_sync_journal')
            ->where('journal_seq >', $cursor)
            ->where('sync_status', 'ACCEPTED') // Deliver accepted operations
            ->orderBy('journal_seq', 'ASC')
            ->limit($limit);

        // Optional User Scoping
        if (!empty($options['user_scope_only']) && $userId > 0) {
            $journalBuilder->where('user_id', $userId);
        }

        $journalRecords = $journalBuilder->get()->getResultArray();
        foreach ($journalRecords as &$jr) {
            $jr['journal_seq'] = (int)$jr['journal_seq'];
            $jr['user_id'] = (int)$jr['user_id'];
            if (!empty($jr['entity_id'])) {
                $jr['entity_id'] = (int)$jr['entity_id'];
            }
        }
        unset($jr);

        $recordCount = count($journalRecords);

        // 4. Calculate Pagination-Safe Next Cursor (Guard B7-G17)
        // CRITICAL: next_cursor MUST be the journal_seq of the last record actually delivered,
        // NEVER the global latest high-water mark if more records remain to be fetched.
        if ($recordCount === 0) {
            $nextCursor = $cursor;
            $hasMore = false;
        } else {
            $lastRecord = end($journalRecords);
            $nextCursor = (int)$lastRecord['journal_seq'];

            // Check if more records exist beyond the current window
            $remainingBuilder = $this->db->table('mobile_sync_journal')
                ->where('journal_seq >', $nextCursor)
                ->where('sync_status', 'ACCEPTED');

            if (!empty($options['user_scope_only']) && $userId > 0) {
                $remainingBuilder->where('user_id', $userId);
            }

            $remainingCount = $remainingBuilder->countAllResults();
            $hasMore = ($remainingCount > 0);
        }

        // 5. Hydrate Domain Entities Referenced in Delta Records
        $deltas = $this->hydrateDeltas($journalRecords, $userId, $options);

        // 6. Include Read-Only Topology Model (Guard B7-G14)
        $topologyReadModel = null;
        if (!empty($options['include_topology']) || $cursor === 0) {
            $topologyReadModel = $this->getReadOnlyTopologyModel();
        }

        return [
            'status'              => 'SUCCESS',
            'http_code'           => 200,
            'cursor_in'           => $cursor,
            'next_cursor'         => $nextCursor,
            'has_more'            => $hasMore,
            'count'               => $recordCount,
            'server_time'         => $serverTime,
            'deltas'              => $deltas,
            'topology_read_model' => $topologyReadModel,
        ];
    }

    // =========================================================================
    // 2. DOMAIN ENTITY HYDRATION
    // =========================================================================

    /**
     * Hydrate detailed domain records for cases, investigations, findings, and evidence.
     */
    protected function hydrateDeltas(array $journalRecords, int $userId, array $options): array
    {
        $caseIds = [];
        $investigationIds = [];
        $findingIds = [];
        $evidenceIds = [];

        foreach ($journalRecords as $jr) {
            $entityType = $jr['entity_type'];
            $entityId = !empty($jr['entity_id']) ? (int)$jr['entity_id'] : 0;

            if ($entityId <= 0) {
                continue;
            }

            switch ($entityType) {
                case 'fault_case':
                    $caseIds[] = $entityId;
                    break;
                case 'field_investigation':
                    $investigationIds[] = $entityId;
                    break;
                case 'field_finding':
                case 'field_finding_revision':
                    $findingIds[] = $entityId;
                    break;
                case 'field_evidence':
                    $evidenceIds[] = $entityId;
                    break;
            }
        }

        // Also fetch active cases assigned to this user
        if ($userId > 0) {
            $assignedCaseRows = $this->db->table('dispatch_assignments')
                ->select('fault_case_id')
                ->where('assigned_to', $userId)
                ->whereIn('status', ['ASSIGNED', 'ACCEPTED'])
                ->get()
                ->getResultArray();

            foreach ($assignedCaseRows as $ar) {
                $caseIds[] = (int)$ar['fault_case_id'];
            }
        }

        $caseIds = array_unique(array_filter($caseIds));
        $investigationIds = array_unique(array_filter($investigationIds));
        $findingIds = array_unique(array_filter($findingIds));
        $evidenceIds = array_unique(array_filter($evidenceIds));

        // Hydrate Cases
        $cases = [];
        if (!empty($caseIds)) {
            $caseRows = $this->db->table('fault_cases')
                ->whereIn('id', $caseIds)
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();

            foreach ($caseRows as $c) {
                $c['candidates'] = $this->caseService->getCandidates((int)$c['id']);
                $cases[] = $c;
            }
        }

        // Hydrate Investigations
        $investigations = [];
        if (!empty($investigationIds)) {
            $investigations = $this->db->table('field_investigations')
                ->whereIn('id', $investigationIds)
                ->get()
                ->getResultArray();
        }

        // Hydrate Findings
        $findings = [];
        if (!empty($findingIds)) {
            $findingRows = $this->db->table('field_findings')
                ->whereIn('id', $findingIds)
                ->get()
                ->getResultArray();

            foreach ($findingRows as $f) {
                // Fetch revisions
                $f['revisions'] = $this->db->table('field_finding_revisions')
                    ->where('field_finding_id', (int)$f['id'])
                    ->orderBy('revision_no', 'ASC')
                    ->get()
                    ->getResultArray();
                $findings[] = $f;
            }
        }

        // Hydrate Evidence
        $evidence = [];
        if (!empty($evidenceIds)) {
            $evidence = $this->db->table('field_evidence')
                ->whereIn('id', $evidenceIds)
                ->get()
                ->getResultArray();
        }

        return [
            'journal_records' => $journalRecords,
            'assigned_cases'  => $cases,
            'investigations'  => $investigations,
            'findings'        => $findings,
            'evidence'        => $evidence,
        ];
    }

    // =========================================================================
    // 3. READ-ONLY TOPOLOGY MODEL (B7-G14)
    // =========================================================================

    /**
     * Package electrical network topology snapshot for offline inspection read model.
     * Guaranteed 100% read-only: possesses zero write or mutation paths.
     */
    public function getReadOnlyTopologyModel(?string $snapshotId = null): array
    {
        $targetSnapshot = $snapshotId ?? FaultCaseService::DEFAULT_SNAPSHOT;

        // Count authoritative topology metrics (Strict Read-Only)
        $activeTl = (int)$this->db->table('gis_translines')
            ->where('is_active', 1)
            ->where('deleted_at IS NULL')
            ->countAllResults();

        $totalTl = (int)$this->db->table('gis_translines')
            ->countAllResults();

        $activeAssets = (int)$this->db->table('assets')
            ->where('deleted_at IS NULL')
            ->countAllResults();

        $totalAssets = (int)$this->db->table('assets')
            ->countAllResults();

        return [
            'topology_snapshot_id' => $targetSnapshot,
            'read_only_invariant'  => 'DELTA_TOPOLOGY_ZERO',
            'active_translines'    => $activeTl,
            'total_translines'     => $totalTl,
            'active_assets'        => $activeAssets,
            'total_assets'         => $totalAssets,
            'authoritative_bound'  => true,
            'mutation_permitted'   => false,
        ];
    }

    // =========================================================================
    // 4. DEVICE VERIFICATION HELPER (B7-G11, B7-G19)
    // =========================================================================

    protected function verifyActiveDevice(string $deviceId): array
    {
        if (empty($deviceId)) {
            return [
                'valid'   => false,
                'status'  => 'DEVICE_NOT_REGISTERED',
                'message' => 'device_id header/parameter is required for pull synchronization.',
            ];
        }

        $device = $this->db->table('mobile_devices')->where('device_id', $deviceId)->get()->getRowArray();
        if (!$device) {
            return [
                'valid'   => false,
                'status'  => 'DEVICE_NOT_REGISTERED',
                'message' => "Device '{$deviceId}' is not registered in device registry (Guard B7-G11).",
            ];
        }

        if (strtoupper($device['status']) === 'REVOKED') {
            return [
                'valid'   => false,
                'status'  => 'DEVICE_REVOKED',
                'message' => "Device '{$deviceId}' is revoked. Pull access prohibited (Guard B7-G19).",
            ];
        }

        if (strtoupper($device['status']) !== 'ACTIVE') {
            return [
                'valid'   => false,
                'status'  => 'DEVICE_SUSPENDED',
                'message' => "Device '{$deviceId}' is suspended.",
            ];
        }

        return ['valid' => true];
    }
}
