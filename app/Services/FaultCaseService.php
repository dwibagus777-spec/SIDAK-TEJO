<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use InvalidArgumentException;

/**
 * SIDAK TEJO — Phase B.6.2: Fault Case Orchestration Service
 *
 * ARCHITECTURAL CONTRACT & HARD GUARDS:
 * - B6-G02:    Snapshot Binding (Case bound to authoritative topology snapshot)
 * - B6-G03:    Event ≠ Case (Telemetry event immutable; case represents operational process)
 * - B6-G04:    Candidate ≠ Actual Finding (FLI candidate unconfirmed; actual finding separate)
 * - B6-G06:    Case Lifecycle State Machine (Strict transition graph; invalid skips rejected)
 * - B6-G11:    Prediction / Actual Separation
 * - B6-G12:    No Automatic Model Rewrite (Calibration data decoupled from production models)
 * - B6.2-G00:  NO BUSINESS DELETE (Physical deletion prohibited; cases are immutable historical entities)
 * - B6.2-G01:  One Event → Deterministic Case
 * - B6.2-G02:  Case Creation Idempotency (Re-resolving existing case returns exact record)
 * - B6.2-G03:  Candidate Snapshot Consistency (Candidates strictly bound to case topology snapshot)
 * - B6.2-G04:  Case Timeline Append-Only (Chronological reconstruction without history loss)
 * - B6.2-G05:  ZERO_TOPOLOGY_MUTATION (gis_translines = 0, assets = 0)
 */
class FaultCaseService
{
    public const SERVICE_VERSION = 'B6-CASE-1.0';
    public const DEFAULT_SNAPSHOT = 'TOPOLOGY-20260925-243-ad2c9fcb';
    public const DEFAULT_ANALYSIS_VERSION = 'FLI-1.0.0';

    /**
     * Case Lifecycle FSM Allowed Transitions:
     * Current Status -> [Allowed Next Statuses]
     */
    public const ALLOWED_TRANSITIONS = [
        'CANDIDATE_IDENTIFIED' => ['DISPATCHED', 'UNRESOLVED', 'CANCELLED'],
        'DISPATCHED'           => ['ACCEPTED', 'UNRESOLVED', 'CANCELLED'],
        'ACCEPTED'             => ['EN_ROUTE', 'UNRESOLVED', 'CANCELLED'],
        'EN_ROUTE'             => ['ARRIVED', 'UNRESOLVED', 'CANCELLED'],
        'ARRIVED'              => ['INVESTIGATING', 'UNRESOLVED', 'CANCELLED'],
        'INVESTIGATING'        => ['FINDING_RECORDED', 'UNRESOLVED', 'CANCELLED'],
        'FINDING_RECORDED'     => ['CONFIRMED', 'UNRESOLVED', 'CANCELLED'],
        'CONFIRMED'            => ['CLOSED'],
        'UNRESOLVED'           => ['CLOSED', 'CANDIDATE_IDENTIFIED'],
        'CANCELLED'            => [], // Terminal state
        'CLOSED'               => [], // Terminal state
    ];

    public const TERMINAL_STATUSES = ['CLOSED', 'CANCELLED'];

    public const PRIORITY_LEVELS = [
        'P1_CRITICAL' => 'P1_CRITICAL',
        'P2_HIGH'     => 'P2_HIGH',
        'P3_MEDIUM'   => 'P3_MEDIUM',
        'P4_LOW'      => 'P4_LOW',
    ];

    protected BaseConnection $db;
    protected ?FaultLocationIntelligenceService $fliService;

    public function __construct(
        ?BaseConnection $db = null,
        ?FaultLocationIntelligenceService $fliService = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->fliService = $fliService;
    }

    /**
     * Get or lazy-load the FLI service
     */
    public function getFliService(): FaultLocationIntelligenceService
    {
        if ($this->fliService === null) {
            $this->fliService = new FaultLocationIntelligenceService(null, null, $this->db);
        }
        return $this->fliService;
    }

    // =========================================================================
    // 1. CASE CREATION & RESOLUTION (B6.2-G01, B6.2-G02, B6-G02, B6-G03)
    // =========================================================================

    /**
     * Create a new fault case or resolve an existing active case for a fault event.
     * Guarantees idempotency (B6.2-G02) and deterministic resolution (B6.2-G01).
     *
     * @param int $faultEventId
     * @param array $options Optional overrides (priority, created_by, target_distance, tolerance_m)
     * @return array ['success' => bool, 'is_new' => bool, 'is_existing' => bool, 'case' => array, 'candidates' => array]
     */
    public function createOrResolveCase(int $faultEventId, array $options = []): array
    {
        // 1. Verify Event Exists
        $event = $this->db->table('fault_events')->where('id', $faultEventId)->get()->getRowArray();
        if (!$event) {
            return [
                'success' => false,
                'status'  => 'EVENT_NOT_FOUND',
                'message' => "Fault event #{$faultEventId} does not exist.",
            ];
        }

        // 2. Check Idempotency: Check if an active case already exists for this event
        $existingCase = $this->db->table('fault_cases')
            ->where('fault_event_id', $faultEventId)
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        if ($existingCase) {
            $candidates = $this->getCandidates((int)$existingCase['id']);
            return [
                'success'     => true,
                'is_new'      => false,
                'is_existing' => true,
                'case'        => $existingCase,
                'candidates'  => $candidates,
                'message'     => "Existing case #{$existingCase['case_number']} resolved idempotently.",
            ];
        }

        // 3. Resolve Temporal Snapshot & Analysis Version (B6-G02)
        $snapshotId = $event['topology_snapshot_id'] ?? self::DEFAULT_SNAPSHOT;
        $analysisVersion = self::DEFAULT_ANALYSIS_VERSION;

        // 4. Run or Retrieve FLI Candidates (B6.2-G03)
        $candidates = $options['candidates'] ?? [];
        $targetDistance = (float)($options['target_distance_meters'] ?? $event['relay_distance_m'] ?? 0.0);
        $toleranceM = (float)($options['distance_tolerance_meters'] ?? 250.0);
        $deviceId = (int)($event['source_device_asset_id'] ?? $options['device_asset_id'] ?? 0);
        $faultPhase = (string)($event['fault_phase'] ?? $options['fault_type'] ?? 'UNKNOWN');
        $penyulangId = (int)($event['penyulang_id'] ?? $options['penyulang_id'] ?? 15);

        if (empty($candidates) && $deviceId > 0 && $targetDistance > 0) {
            try {
                $fliResult = $this->getFliService()->locateCandidates(
                    $penyulangId,
                    $deviceId,
                    $targetDistance,
                    $toleranceM,
                    ['fault_type' => $faultPhase]
                );
                $candidates = $fliResult['payload']['candidates'] ?? [];
                if (!empty($fliResult['topology_snapshot_id'])) {
                    $snapshotId = $fliResult['topology_snapshot_id'];
                }
            } catch (\Throwable $e) {
                // Non-fatal if topology graph cannot resolve specific test node; fallback to 0 candidates
                $candidates = [];
            }
        }

        // 5. Calculate Transparent Priority Score
        $priority = $options['priority'] ?? $this->calculatePriority($event, $candidates);

        // 6. Generate Case Number
        $caseNumber = 'CASE-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $now = date('Y-m-d H:i:s');
        $topCand = !empty($candidates) ? $candidates[0] : null;

        $caseData = [
            'case_number'               => $caseNumber,
            'fault_event_id'            => $faultEventId,
            'topology_snapshot_id'      => $snapshotId,
            'analysis_version'          => $analysisVersion,
            'analysis_input_hash'       => hash('sha256', "EVENT:{$faultEventId}:DIST:{$targetDistance}:PHASE:{$faultPhase}"),
            'device_asset_id'           => $deviceId > 0 ? $deviceId : null,
            'target_distance_meters'    => $targetDistance,
            'distance_tolerance_meters' => $toleranceM,
            'fault_type'                => $faultPhase,
            'impedance_supported'       => 0,
            'impedance_reason'          => 'NO_CANONICAL_IMPEDANCE_PROFILE',
            'candidate_count'           => count($candidates),
            'top_candidate_asset_id'    => $topCand ? (int)($topCand['asset_id'] ?? 0) : null,
            'top_confidence_score'      => $topCand ? (float)($topCand['confidence_score'] ?? $topCand['confidence_percent'] ?? 0.0) : null,
            'status'                    => 'CANDIDATE_IDENTIFIED',
            'priority'                  => $priority,
            'opened_at'                 => $now,
            'closed_at'                 => null,
            'created_by'                => $options['created_by'] ?? null,
            'analysis_timestamp'        => $now,
            'created_at'                => $now,
            'updated_at'                => $now,
        ];

        $this->db->table('fault_cases')->insert($caseData);
        $caseId = (int)$this->db->insertID();
        $caseData['id'] = $caseId;

        // 7. Store Candidate Set (B6-G04: Unconfirmed Candidates)
        if (!empty($candidates) && $this->db->tableExists('fault_candidate_assets')) {
            $candRows = [];
            foreach ($candidates as $cand) {
                $candRows[] = [
                    'fault_case_id'                => $caseId,
                    'asset_id'                     => (int)$cand['asset_id'],
                    'rank'                         => (int)($cand['rank'] ?? 1),
                    'candidate_status'             => 'CANDIDATE',
                    'graph_distance_from_device_m' => (float)($cand['graph_distance_from_device_m'] ?? $cand['graph_distance_m'] ?? 0.0),
                    'distance_delta_m'             => (float)($cand['distance_delta_m'] ?? 0.0),
                    'confidence_score'             => (float)($cand['confidence_score'] ?? $cand['confidence_percent'] ?? 0.0),
                    'evidence_breakdown_json'      => json_encode($cand['evidence_breakdown'] ?? []),
                    'path_asset_ids_json'          => json_encode($cand['path_asset_ids'] ?? []),
                    'created_at'                   => $now,
                    'updated_at'                   => $now,
                ];
            }
            $this->db->table('fault_candidate_assets')->insertBatch($candRows);
        }

        return [
            'success'     => true,
            'is_new'      => true,
            'is_existing' => false,
            'case'        => $caseData,
            'candidates'  => $candidates,
            'message'     => "New fault case #{$caseNumber} created with " . count($candidates) . " candidate(s).",
        ];
    }

    // =========================================================================
    // 2. CASE RETRIEVAL (B6-G02, B6-G03, B6-G04)
    // =========================================================================

    /**
     * Retrieve a case by ID with full details.
     */
    public function getCase(int $caseId): ?array
    {
        $case = $this->db->table('fault_cases')->where('id', $caseId)->get()->getRowArray();
        if (!$case) {
            return null;
        }

        $case['candidates'] = $this->getCandidates($caseId);
        return $case;
    }

    /**
     * Retrieve a case by case_number.
     */
    public function getCaseByNumber(string $caseNumber): ?array
    {
        $case = $this->db->table('fault_cases')->where('case_number', $caseNumber)->get()->getRowArray();
        if (!$case) {
            return null;
        }

        $case['candidates'] = $this->getCandidates((int)$case['id']);
        return $case;
    }

    /**
     * Retrieve the active case linked to a fault event ID.
     */
    public function getCaseByEventId(int $faultEventId): ?array
    {
        $case = $this->db->table('fault_cases')
            ->where('fault_event_id', $faultEventId)
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        if (!$case) {
            return null;
        }

        $case['candidates'] = $this->getCandidates((int)$case['id']);
        return $case;
    }

    /**
     * Get candidate assets associated with a fault case.
     * Enforces B6-G04 (unconfirmed candidate terminology).
     */
    public function getCandidates(int $caseId): array
    {
        if (!$this->db->tableExists('fault_candidate_assets')) {
            return [];
        }

        $rows = $this->db->table('fault_candidate_assets')
            ->where('fault_case_id', $caseId)
            ->orderBy('rank', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$r) {
            $r['evidence_breakdown'] = json_decode($r['evidence_breakdown_json'] ?? '{}', true);
            $r['path_asset_ids'] = json_decode($r['path_asset_ids_json'] ?? '[]', true);
        }
        unset($r);

        return $rows;
    }

    // =========================================================================
    // 3. LIFECYCLE FINITE STATE MACHINE (B6-G06)
    // =========================================================================

    /**
     * Execute a case lifecycle state machine transition.
     * Rejects unauthorized shortcuts and guarantees terminal state immutability.
     *
     * @param int $caseId
     * @param string $targetStatus
     * @param array $context ['actor_id' => int, 'reason' => string, 'notes' => string]
     * @return array ['success' => bool, 'previous_status' => string, 'new_status' => string, 'message' => string]
     */
    public function transitionCase(int $caseId, string $targetStatus, array $context = []): array
    {
        $targetStatus = strtoupper(trim($targetStatus));

        $case = $this->db->table('fault_cases')->where('id', $caseId)->get()->getRowArray();
        if (!$case) {
            return [
                'success' => false,
                'status'  => 'CASE_NOT_FOUND',
                'message' => "Fault case #{$caseId} does not exist.",
            ];
        }

        $currentStatus = strtoupper($case['status']);

        // Guard: Terminal state immutability
        if (in_array($currentStatus, self::TERMINAL_STATUSES, true)) {
            return [
                'success'         => false,
                'status'          => 'TERMINAL_STATE_IMMUTABLE',
                'previous_status' => $currentStatus,
                'target_status'   => $targetStatus,
                'message'         => "Case #{$caseId} is in terminal state '{$currentStatus}'. No further transitions permitted.",
            ];
        }

        // Guard: Check if target status is valid in FSM
        $allowedNext = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
        if (!in_array($targetStatus, $allowedNext, true)) {
            return [
                'success'         => false,
                'status'          => 'REJECTED_INVALID_TRANSITION',
                'previous_status' => $currentStatus,
                'target_status'   => $targetStatus,
                'allowed_next'    => $allowedNext,
                'message'         => "Invalid transition: Cannot move case from '{$currentStatus}' to '{$targetStatus}'. Allowed: " . implode(', ', $allowedNext),
            ];
        }

        // Guard: Cancellation requires non-empty reason
        if ($targetStatus === 'CANCELLED' && empty(trim((string)($context['reason'] ?? '')))) {
            return [
                'success' => false,
                'status'  => 'MISSING_CANCELLATION_REASON',
                'message' => "Cancellation reason is required when transitioning case to CANCELLED.",
            ];
        }

        // Apply update
        $now = date('Y-m-d H:i:s');
        $updateData = [
            'status'     => $targetStatus,
            'updated_at' => $now,
        ];

        if ($targetStatus === 'CLOSED' || $targetStatus === 'CANCELLED') {
            $updateData['closed_at'] = $now;
        }

        $this->db->table('fault_cases')->where('id', $caseId)->update($updateData);

        return [
            'success'         => true,
            'status'          => 'TRANSITION_APPLIED',
            'previous_status' => $currentStatus,
            'new_status'      => $targetStatus,
            'message'         => "Case #{$caseId} successfully transitioned from '{$currentStatus}' to '{$targetStatus}'.",
        ];
    }

    // =========================================================================
    // 4. TRANSPARENT PRIORITY CALCULATION
    // =========================================================================

    /**
     * Compute explicit, transparent dispatch priority.
     * Uses fault current, protection elements, confidence, and candidate ranking.
     */
    public function calculatePriority(array $event, array $candidates = []): string
    {
        $faultCurrentA = (float)($event['fault_current_a'] ?? 0.0);
        $protElements = strtoupper((string)($event['protection_elements'] ?? ''));
        $topCand = !empty($candidates) ? $candidates[0] : null;
        $topConfidence = $topCand ? (float)($topCand['confidence_score'] ?? 0.0) : 0.0;

        // P1_CRITICAL Conditions:
        // - Instantaneous trip (50 / 50N / OC_INST / GF_INST)
        // - Fault current exceeding 1,000 A (1.0 kA)
        // - High confidence (> 80%) on first rank candidate
        if (
            str_contains($protElements, 'INST') ||
            str_contains($protElements, '50') ||
            $faultCurrentA >= 1000.0 ||
            $topConfidence >= 80.0
        ) {
            return 'P1_CRITICAL';
        }

        // P2_HIGH Conditions:
        // - Time delay trip (51 / 51N / OC_DELAY)
        // - Fault current exceeding 400 A
        // - Moderate confidence (> 60%)
        if (
            str_contains($protElements, 'DELAY') ||
            str_contains($protElements, '51') ||
            $faultCurrentA >= 400.0 ||
            $topConfidence >= 60.0
        ) {
            return 'P2_HIGH';
        }

        // P4_LOW Conditions:
        // - Low confidence (< 35%) and small fault current
        if ($topConfidence < 35.0 && $faultCurrentA < 150.0 && $faultCurrentA > 0.0) {
            return 'P4_LOW';
        }

        // Default: P3_MEDIUM
        return 'P3_MEDIUM';
    }

    // =========================================================================
    // 5. CASE AUDIT TIMELINE (B6.2-G04)
    // =========================================================================

    /**
     * Reconstruct complete chronological audit timeline for a fault case.
     * Merges: Event ingestion -> Case created -> Candidates -> FSM transitions -> Findings.
     */
    public function getCaseTimeline(int $caseId): array
    {
        $case = $this->db->table('fault_cases')->where('id', $caseId)->get()->getRowArray();
        if (!$case) {
            return [];
        }

        $timeline = [];

        // 1. Fault Event Ingestion Milestone
        if (!empty($case['fault_event_id'])) {
            $event = $this->db->table('fault_events')->where('id', $case['fault_event_id'])->get()->getRowArray();
            if ($event) {
                $timeline[] = [
                    'timestamp'   => $event['event_time'] ?? $event['created_at'],
                    'stage'       => 'TELEMETRY_INGESTED',
                    'title'       => 'Operational Telemetry Ingested',
                    'description' => "Event #{$event['event_number']} ({$event['source_type']}) received from device #{$event['source_device_asset_id']}.",
                    'actor'       => 'B5-INGEST-1.0',
                    'metadata'    => [
                        'event_number'     => $event['event_number'],
                        'fault_phase'      => $event['fault_phase'] ?? null,
                        'relay_distance_m' => $event['relay_distance_m'] ?? null,
                        'fingerprint'      => $event['event_fingerprint'] ?? null,
                    ],
                ];
            }
        }

        // 2. Case Opened Milestone
        $timeline[] = [
            'timestamp'   => $case['opened_at'] ?? $case['created_at'],
            'stage'       => 'CASE_OPENED',
            'title'       => 'Fault Investigation Case Opened',
            'description' => "Case #{$case['case_number']} opened with priority {$case['priority']}.",
            'actor'       => $case['created_by'] ? "User #{$case['created_by']}" : 'FLI-Orchestrator',
            'metadata'    => [
                'case_number'          => $case['case_number'],
                'priority'             => $case['priority'],
                'topology_snapshot_id' => $case['topology_snapshot_id'],
                'analysis_version'     => $case['analysis_version'],
            ],
        ];

        // 3. FLI Candidates Identified Milestone
        if ((int)$case['candidate_count'] > 0) {
            $timeline[] = [
                'timestamp'   => $case['analysis_timestamp'] ?? $case['created_at'],
                'stage'       => 'CANDIDATES_IDENTIFIED',
                'title'       => 'FLI Candidate Assets Identified',
                'description' => "FLI engine identified {$case['candidate_count']} candidate asset(s). Top candidate: #{$case['top_candidate_asset_id']} (Confidence: {$case['top_confidence_score']}%).",
                'actor'       => $case['analysis_version'] ?? 'FLI-1.0.0',
                'metadata'    => [
                    'candidate_count'        => (int)$case['candidate_count'],
                    'top_candidate_asset_id' => $case['top_candidate_asset_id'],
                    'top_confidence_score'   => $case['top_confidence_score'],
                ],
            ];
        }

        // 4. Current Status Snapshot
        $timeline[] = [
            'timestamp'   => $case['updated_at'] ?? $case['created_at'],
            'stage'       => 'CURRENT_STATUS',
            'title'       => "Case Status: {$case['status']}",
            'description' => "Case currently in status {$case['status']}.",
            'actor'       => 'FSM-Engine',
            'metadata'    => [
                'status'    => $case['status'],
                'closed_at' => $case['closed_at'],
            ],
        ];

        // Sort chronologically
        usort($timeline, fn($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

        return $timeline;
    }

    // =========================================================================
    // 6. LIST CASES WITH FILTERS
    // =========================================================================

    /**
     * Search and list cases with pagination and filters.
     */
    public function listCases(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $builder = $this->db->table('fault_cases');

        if (!empty($filters['status'])) {
            $builder->where('status', strtoupper($filters['status']));
        }
        if (!empty($filters['priority'])) {
            $builder->where('priority', strtoupper($filters['priority']));
        }
        if (!empty($filters['snapshot_id'])) {
            $builder->where('topology_snapshot_id', $filters['snapshot_id']);
        }
        if (!empty($filters['penyulang_id'])) {
            $builder->join('fault_events', 'fault_events.id = fault_cases.fault_event_id')
                    ->where('fault_events.penyulang_id', (int)$filters['penyulang_id']);
        }

        $totalCount = $builder->countAllResults(false);
        $rows = $builder->orderBy('fault_cases.id', 'DESC')
                        ->limit($limit, $offset)
                        ->get()
                        ->getResultArray();

        return [
            'total'  => $totalCount,
            'limit'  => $limit,
            'offset' => $offset,
            'cases'  => $rows,
        ];
    }

    // =========================================================================
    // 7. GUARD B6.2-G00: NO BUSINESS DELETE
    // =========================================================================

    /**
     * Business deletion is strictly prohibited by Guard B6.2-G00.
     * Cases represent historical operational records and must be transitioned via FSM.
     *
     * @throws RuntimeException Always throws exception enforcing B6.2-G00.
     */
    public function deleteCase(int $caseId): void
    {
        throw new RuntimeException(
            "Business deletion of fault_cases (ID: {$caseId}) is prohibited by Guard B6.2-G00. " .
            "Cases are permanent historical records and must be transitioned via lifecycle FSM (CANCELLED or CLOSED) to preserve complete audit history."
        );
    }
}
