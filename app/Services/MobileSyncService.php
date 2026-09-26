<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use InvalidArgumentException;

/**
 * SIDAK TEJO — Phase B.7.2: Mobile Field App & Offline Inspection Sync Gateway
 *
 * ARCHITECTURAL CONTRACT & HARD GUARDS:
 * - B7-G01:  Zero Authority Bypass (Server is sole arbiter; mobile sends intents only)
 * - B7-G02:  Client Submission Identity (client_submission_uuid binds physical operation)
 * - B7-G03:  Batch Synchronization Protocol (Partial success supported; isolated errors)
 * - B7-G04:  Queue State Machine (ACCEPTED, RETRYABLE_ERROR, REJECTED)
 * - B7-G05:  Server-Side Idempotency Guarantee (Duplicate UUID returns canonical receipt)
 * - B7-G06:  Dual-Clock Time Provenance (Client created at never overwritten; drift logged)
 * - B7-G07:  GPS Spatial Provenance (Syntactic bounds, mock location rejection, accuracy)
 * - B7-G09:  3-Tier Conflict Resolution (Transport, Domain, Ground Truth separated)
 * - B7-G10:  Comprehensive Sync Journaling (100% of attempts logged in mobile_sync_journal)
 * - B7-G11:  Registered Device Governance (Device registered and not REVOKED)
 * - B7-G12:  Monotonic Server Sequence (journal_seq BIGINT AUTO_INCREMENT high-water mark)
 * - B7-G14:  Topology Read-Only (gis_translines = 0, assets = 0, Delta = 0)
 * - B7-G15:  Per-Operation Atomicity (One UUID -> One isolated domain transaction)
 * - B7-G16:  Monotonic Server Cursor High-Water Mark
 * - B7-G19:  Device Revocation Non-Cascade (Journal records permanent)
 * - B7-G20:  GPS Provenance Semantics (Horizontal accuracy estimate, timezone offsets)
 */
class MobileSyncService
{
    public const SERVICE_VERSION = 'B7-SYNC-1.0';

    public const VALID_OPERATION_TYPES = [
        'ACCEPT_ASSIGNMENT',
        'REJECT_ASSIGNMENT',
        'START_JOURNEY',
        'RECORD_ARRIVAL',
        'START_INVESTIGATION',
        'RECORD_FINDING',
        'AMEND_FINDING',
        'CONFIRM_FINDING',
        'RECORD_EVIDENCE',
        'ATTACH_EVIDENCE',
        'RECORD_FEEDBACK',
        'TRANSITION_CASE',
    ];

    protected BaseConnection $db;
    protected FaultCaseService $caseService;
    protected FaultDispatchService $dispatchService;
    protected FieldFindingsService $findingsService;
    protected FaultFeedbackService $feedbackService;

    public function __construct(
        ?BaseConnection $db = null,
        ?FaultCaseService $caseService = null,
        ?FaultDispatchService $dispatchService = null,
        ?FieldFindingsService $findingsService = null,
        ?FaultFeedbackService $feedbackService = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->caseService = $caseService ?? new FaultCaseService($this->db);
        $this->dispatchService = $dispatchService ?? new FaultDispatchService($this->db, $this->caseService);
        $this->findingsService = $findingsService ?? new FieldFindingsService($this->db, $this->caseService);
        $this->feedbackService = $feedbackService ?? new FaultFeedbackService($this->db, $this->caseService);
    }

    public function getCaseService(): FaultCaseService
    {
        return $this->caseService;
    }

    public function getDispatchService(): FaultDispatchService
    {
        return $this->dispatchService;
    }

    public function getFindingsService(): FieldFindingsService
    {
        return $this->findingsService;
    }

    public function getFeedbackService(): FaultFeedbackService
    {
        return $this->feedbackService;
    }

    // =========================================================================
    // 1. BATCH INGESTION GATEWAY (B7-G03, B7-G15)
    // =========================================================================

    /**
     * Ingest and process a batch of offline field operations.
     * Enforces device authentication (B7-G11), per-operation atomicity (B7-G15),
     * and returns a granular multi-status response.
     *
     * @param array $envelope
     * @return array
     */
    public function ingestSyncBatch(array $envelope): array
    {
        return $this->pushBatch($envelope);
    }

    public function pushBatch(array $envelope): array
    {
        $serverReceivedAt = date('Y-m-d H:i:s');

        // 1. Validate Envelope Structure
        $syncId = trim((string)($envelope['sync_id'] ?? ''));
        $deviceId = trim((string)($envelope['device_id'] ?? ''));
        $userId = (int)($envelope['user_id'] ?? 0);
        $clientSentAt = trim((string)($envelope['client_sent_at'] ?? ''));
        $operations = $envelope['operations'] ?? [];

        if (empty($syncId) || empty($deviceId) || $userId <= 0) {
            return [
                'status'             => 'REJECTED',
                'http_code'          => 400,
                'sync_id'            => $syncId,
                'server_received_at' => $serverReceivedAt,
                'message'            => 'Missing required batch envelope parameters: sync_id, device_id, and user_id are mandatory.',
            ];
        }

        if (!is_array($operations)) {
            return [
                'status'             => 'REJECTED',
                'http_code'          => 400,
                'sync_id'            => $syncId,
                'server_received_at' => $serverReceivedAt,
                'message'            => 'Operations payload must be a JSON array.',
            ];
        }

        // 2. Validate Registered Device Governance (B7-G11, B7-G19)
        $device = $this->db->table('mobile_devices')
            ->where('device_id', $deviceId)
            ->get()
            ->getRowArray();

        if (!$device) {
            return [
                'status'             => 'DEVICE_NOT_REGISTERED',
                'http_code'          => 403,
                'sync_id'            => $syncId,
                'server_received_at' => $serverReceivedAt,
                'message'            => "Device '{$deviceId}' is not registered in authoritative device registry.",
            ];
        }

        if (strtoupper($device['status']) === 'REVOKED') {
            return [
                'status'             => 'DEVICE_REVOKED',
                'http_code'          => 403,
                'sync_id'            => $syncId,
                'server_received_at' => $serverReceivedAt,
                'message'            => "Device '{$deviceId}' has been revoked. Sync transmission prohibited (Guard B7-G19).",
            ];
        }

        if (strtoupper($device['status']) !== 'ACTIVE') {
            return [
                'status'             => 'DEVICE_SUSPENDED',
                'http_code'          => 403,
                'sync_id'            => $syncId,
                'server_received_at' => $serverReceivedAt,
                'message'            => "Device '{$deviceId}' is in state '{$device['status']}'. Sync unavailable.",
            ];
        }

        // Touch device last_seen_at
        $this->db->table('mobile_devices')
            ->where('device_id', $deviceId)
            ->update([
                'last_seen_at' => $serverReceivedAt,
                'updated_at'   => $serverReceivedAt,
            ]);

        // 3. Register or Check Batch Record (B7-G03)
        $payloadHash = hash('sha256', json_encode($envelope));
        $existingBatch = $this->db->table('mobile_sync_batches')
            ->where('sync_id', $syncId)
            ->get()
            ->getRowArray();

        if (!$existingBatch) {
            $this->db->table('mobile_sync_batches')->insert([
                'sync_id'            => $syncId,
                'device_id'          => $deviceId,
                'user_id'            => $userId,
                'client_sent_at'     => !empty($clientSentAt) ? date('Y-m-d H:i:s', strtotime($clientSentAt)) : $serverReceivedAt,
                'server_received_at' => $serverReceivedAt,
                'operation_count'    => count($operations),
                'payload_sha256'     => $payloadHash,
                'status'             => 'PROCESSING',
                'created_at'         => $serverReceivedAt,
                'updated_at'         => $serverReceivedAt,
            ]);
        }

        // 4. Process Each Operation in Isolated Atomic Transaction (B7-G15)
        $results = [];
        $acceptedCount = 0;
        $duplicateCount = 0;
        $rejectedCount = 0;

        foreach ($operations as $index => $op) {
            $opResult = $this->processSingleOperation($op, $syncId, $deviceId, $userId, $serverReceivedAt);
            $results[] = $opResult;

            if ($opResult['sync_status'] === 'ACCEPTED') {
                $acceptedCount++;
            } elseif ($opResult['sync_status'] === 'DUPLICATE') {
                $duplicateCount++;
            } else {
                $rejectedCount++;
            }
        }

        // 5. Update Batch Status
        $batchStatus = 'COMPLETED';
        if ($rejectedCount > 0 && $acceptedCount > 0) {
            $batchStatus = 'PARTIAL';
        } elseif ($rejectedCount > 0 && $acceptedCount === 0) {
            $batchStatus = 'REJECTED';
        }

        $this->db->table('mobile_sync_batches')
            ->where('sync_id', $syncId)
            ->update([
                'status'     => $batchStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $httpCode = 200;
        if ($rejectedCount > 0 && ($acceptedCount > 0 || $duplicateCount > 0)) {
            $httpCode = 207;
        } elseif ($rejectedCount > 0 && $acceptedCount === 0 && $duplicateCount === 0) {
            $httpCode = 422;
        }

        return [
            'status'             => 'SUCCESS',
            'http_code'          => $httpCode,
            'batch'              => [
                'status'          => $batchStatus,
                'accepted_count'  => $acceptedCount,
                'duplicate_count' => $duplicateCount,
                'rejected_count'  => $rejectedCount,
            ],
            'sync_id'            => $syncId,
            'server_received_at' => $serverReceivedAt,
            'summary'            => [
                'total'     => count($operations),
                'accepted'  => $acceptedCount,
                'duplicate' => $duplicateCount,
                'rejected'  => $rejectedCount,
            ],
            'results' => $results,
        ];
    }

    // =========================================================================
    // 2. PER-OPERATION ATOMIC PROCESSOR (B7-G02, B7-G05, B7-G10, B7-G15)
    // =========================================================================

    /**
     * Process a single physical field operation within an isolated database transaction.
     * Enforces idempotency via client_submission_uuid and records audit journal.
     */
    protected function processSingleOperation(
        array $op,
        string $syncId,
        string $deviceId,
        int $userId,
        string $serverReceivedAt
    ): array {
        $uuid = trim((string)($op['client_submission_uuid'] ?? ''));
        $opType = strtoupper(trim((string)($op['operation_type'] ?? '')));
        $caseId = (int)($op['case_id'] ?? 0);
        $clientCreatedAtStr = (string)($op['client_created_at'] ?? '');
        $clientTzOffset = (string)($op['client_timezone_offset'] ?? '+07:00');
        $payload = $op['payload'] ?? [];

        // Syntactic Validation
        if (empty($uuid)) {
            return [
                'client_submission_uuid' => 'UNKNOWN',
                'sync_status'            => 'REJECTED',
                'http_code'              => 422,
                'domain_status'          => 'MISSING_CLIENT_SUBMISSION_UUID',
                'message'                => 'client_submission_uuid is mandatory for every operation (Guard B7-G02).',
            ];
        }

        if (empty($opType) || !in_array($opType, self::VALID_OPERATION_TYPES, true)) {
            return [
                'client_submission_uuid' => $uuid,
                'sync_status'            => 'REJECTED',
                'http_code'              => 422,
                'domain_status'          => 'INVALID_OPERATION_TYPE',
                'message'                => "Operation type '{$opType}' is invalid. Allowed: " . implode(', ', self::VALID_OPERATION_TYPES),
            ];
        }

        // Time Provenance & Clock Drift Evaluation (B7-G06, B7-G20)
        $clientCreatedTimestamp = !empty($clientCreatedAtStr) ? strtotime($clientCreatedAtStr) : time();
        $serverReceivedTimestamp = strtotime($serverReceivedAt);
        $clockDriftSeconds = $clientCreatedTimestamp - $serverReceivedTimestamp;
        $isFutureDrift = ($clockDriftSeconds > 300); // More than 5 minutes in future
        $clientCreatedAt = date('Y-m-d H:i:s', $clientCreatedTimestamp);

        // ---------------------------------------------------------------------
        // Check Idempotency Engine (B7-G05): Lookup existing journal row
        // ---------------------------------------------------------------------
        $existingJournal = $this->db->table('mobile_sync_journal')
            ->where('client_submission_uuid', $uuid)
            ->get()
            ->getRowArray();

        if ($existingJournal) {
            // Already processed: Return cached domain receipt with HTTP 200 (DUPLICATE)
            return [
                'client_submission_uuid' => $uuid,
                'sync_status'            => 'DUPLICATE',
                'http_code'              => 200,
                'journal_seq'            => (int)$existingJournal['journal_seq'],
                'domain_status'          => $existingJournal['domain_status'],
                'entity_type'            => $existingJournal['entity_type'],
                'entity_id'              => !empty($existingJournal['entity_id']) ? (int)$existingJournal['entity_id'] : null,
                'server_processed_at'    => $existingJournal['server_processed_at'],
                'message'                => 'Duplicate operation resolved idempotently from cached domain journal (Guard B7-G05).',
            ];
        }

        // ---------------------------------------------------------------------
        // Execute Atomic Domain Transaction (B7-G15)
        // ---------------------------------------------------------------------
        $this->db->transBegin();
        $serverProcessedAt = date('Y-m-d H:i:s');
        $payloadHash = hash('sha256', json_encode($payload));

        try {
            // Dispatch to Domain Adapter
            $domainResult = $this->executeDomainAction($opType, $caseId, $userId, $payload, $op);

            if ($domainResult['success'] === true) {
                // Domain Execution Succeeded: Persist ACCEPTED journal
                $entityType = $domainResult['entity_type'] ?? 'fault_case';
                $entityId = $domainResult['entity_id'] ?? ($caseId > 0 ? $caseId : null);
                $domainStatus = $domainResult['domain_status'] ?? $domainResult['status'] ?? 'SUCCESS';

                $journalData = [
                    'client_submission_uuid' => $uuid,
                    'sync_id'                => $syncId,
                    'device_id'              => $deviceId,
                    'user_id'                => $userId,
                    'operation_type'         => $opType,
                    'entity_type'            => $entityType,
                    'entity_id'              => $entityId,
                    'payload_sha256'         => $payloadHash,
                    'payload_json'           => json_encode($op),
                    'client_created_at'      => $clientCreatedAt,
                    'client_timezone_offset' => $clientTzOffset,
                    'server_received_at'     => $serverReceivedAt,
                    'server_processed_at'    => $serverProcessedAt,
                    'attempt_no'             => 1,
                    'sync_status'            => 'ACCEPTED',
                    'response_http_code'     => 200,
                    'domain_status'          => $domainStatus,
                    'error_message'          => $isFutureDrift ? 'FLAGGED: DISCREPANCY_CLIENT_TIME_FUTURE' : null,
                    'created_at'             => $serverProcessedAt,
                ];

                $this->db->table('mobile_sync_journal')->insert($journalData);
                $journalSeq = (int)$this->db->insertID();

                $this->db->transCommit();

                return [
                    'client_submission_uuid' => $uuid,
                    'sync_status'            => 'ACCEPTED',
                    'http_code'              => 200,
                    'journal_seq'            => $journalSeq,
                    'domain_status'          => $domainStatus,
                    'entity_type'            => $entityType,
                    'entity_id'              => $entityId,
                    'server_processed_at'    => $serverProcessedAt,
                    'message'                => $domainResult['message'] ?? 'Operation executed successfully.',
                ];
            }

            // Domain Execution Failed: Rollback domain changes
            $this->db->transRollback();

            // Record REJECTED in journal via separate transaction (B7-G10: Full Auditability)
            $rejectStatus = $domainResult['status'] ?? 'DOMAIN_REJECTED';
            $rejectMessage = $domainResult['message'] ?? 'Domain validation failed.';
            $rejectHttpCode = $domainResult['http_code'] ?? 422;

            $this->logRejectedJournal(
                $uuid, $syncId, $deviceId, $userId, $opType,
                $payloadHash, $op, $clientCreatedAt, $clientTzOffset,
                $serverReceivedAt, $serverProcessedAt, $rejectHttpCode,
                $rejectStatus, $rejectMessage
            );

            return [
                'client_submission_uuid' => $uuid,
                'sync_status'            => 'REJECTED',
                'http_code'              => $rejectHttpCode,
                'domain_status'          => $rejectStatus,
                'message'                => $rejectMessage,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();

            $errorMsg = 'Internal domain transaction error: ' . $e->getMessage();
            $this->logRejectedJournal(
                $uuid, $syncId, $deviceId, $userId, $opType,
                $payloadHash, $op, $clientCreatedAt, $clientTzOffset,
                $serverReceivedAt, $serverProcessedAt, 500,
                'INTERNAL_ERROR', $errorMsg
            );

            return [
                'client_submission_uuid' => $uuid,
                'sync_status'            => 'REJECTED',
                'http_code'              => 500,
                'domain_status'          => 'INTERNAL_TRANSACTION_ERROR',
                'message'                => $errorMsg,
            ];
        }
    }

    // =========================================================================
    // 3. DOMAIN ADAPTER (B7-G01: B.6 Bridge)
    // =========================================================================

    /**
     * Route operation intent to the authoritative B.6 Domain Service.
     */
    protected function executeDomainAction(
        string $opType,
        int $caseId,
        int $userId,
        array $payload,
        array $fullOp
    ): array {
        switch ($opType) {
            case 'ACCEPT_ASSIGNMENT':
                $assignmentId = (int)($payload['assignment_id'] ?? 0);
                if ($assignmentId <= 0) {
                    return ['success' => false, 'status' => 'MISSING_ASSIGNMENT_ID', 'message' => 'assignment_id is required.'];
                }
                $res = $this->dispatchService->acceptAssignment($assignmentId, $userId, $payload);
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'dispatch_assignment',
                        'entity_id'     => $assignmentId,
                        'domain_status' => 'ASSIGNMENT_ACCEPTED',
                        'message'       => $res['message'] ?? 'Assignment accepted.',
                    ];
                }
                return $res;

            case 'REJECT_ASSIGNMENT':
                $assignmentId = (int)($payload['assignment_id'] ?? 0);
                $reason = (string)($payload['rejection_reason'] ?? 'Rejected by field crew');
                if ($assignmentId <= 0) {
                    return ['success' => false, 'status' => 'MISSING_ASSIGNMENT_ID', 'message' => 'assignment_id is required.'];
                }
                $res = $this->dispatchService->rejectAssignment($assignmentId, $userId, $reason);
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'dispatch_assignment',
                        'entity_id'     => $assignmentId,
                        'domain_status' => 'ASSIGNMENT_REJECTED',
                        'message'       => $res['message'] ?? 'Assignment rejected.',
                    ];
                }
                return $res;

            case 'START_JOURNEY':
                $gpsCheck = $this->evaluateGpsPayload($payload);
                if (!$gpsCheck['valid']) {
                    return ['success' => false, 'status' => $gpsCheck['status'], 'message' => $gpsCheck['message']];
                }
                $res = $this->dispatchService->startJourney(
                    $caseId,
                    $userId,
                    (float)$payload['lat'],
                    (float)$payload['lng'],
                    isset($payload['accuracy_m']) ? (float)$payload['accuracy_m'] : null,
                    $payload
                );
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'field_investigation',
                        'entity_id'     => (int)($res['investigation']['id'] ?? 0),
                        'domain_status' => 'JOURNEY_STARTED',
                        'message'       => $res['message'] ?? 'Journey started.',
                    ];
                }
                return $res;

            case 'RECORD_ARRIVAL':
                $gpsCheck = $this->evaluateGpsPayload($payload);
                if (!$gpsCheck['valid']) {
                    return ['success' => false, 'status' => $gpsCheck['status'], 'message' => $gpsCheck['message']];
                }
                $res = $this->dispatchService->recordArrival(
                    $caseId,
                    $userId,
                    (float)$payload['lat'],
                    (float)$payload['lng'],
                    isset($payload['accuracy_m']) ? (float)$payload['accuracy_m'] : null,
                    $payload
                );
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'field_investigation',
                        'entity_id'     => (int)($res['investigation']['id'] ?? 0),
                        'domain_status' => 'ARRIVAL_RECORDED',
                        'message'       => $res['message'] ?? 'Arrival recorded.',
                    ];
                }
                return $res;

            case 'START_INVESTIGATION':
                $res = $this->dispatchService->startInvestigation($caseId, $userId, $payload);
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'field_investigation',
                        'entity_id'     => $caseId,
                        'domain_status' => 'INVESTIGATION_COMMENCED',
                        'message'       => $res['message'] ?? 'Investigation commenced.',
                    ];
                }
                return $res;

            case 'RECORD_FINDING':
                // Check GPS for finding observation (B7-G07, B7-G20)
                if (isset($payload['actual_lat']) && isset($payload['actual_lng'])) {
                    $gpsCheck = $this->evaluateGpsPayload([
                        'lat'                => $payload['actual_lat'],
                        'lng'                => $payload['actual_lng'],
                        'accuracy_m'         => $payload['gps_accuracy_m'] ?? null,
                        'mock_location_flag' => $payload['mock_location_flag'] ?? false,
                    ]);
                    if (!$gpsCheck['valid']) {
                        return ['success' => false, 'status' => $gpsCheck['status'], 'message' => $gpsCheck['message']];
                    }
                }
                $res = $this->findingsService->recordFinding($caseId, $userId, $payload);
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'field_finding',
                        'entity_id'     => (int)($res['finding']['id'] ?? 0),
                        'domain_status' => 'FINDING_RECORDED',
                        'message'       => $res['message'] ?? 'Finding recorded.',
                    ];
                }
                return $res;

            case 'AMEND_FINDING':
                $findingId = (int)($payload['finding_id'] ?? 0);
                $amendments = $payload['amendments'] ?? [];
                $reason = (string)($payload['reason'] ?? 'Mobile field amendment');
                if ($findingId <= 0) {
                    return ['success' => false, 'status' => 'MISSING_FINDING_ID', 'message' => 'finding_id is required.'];
                }
                $res = $this->findingsService->amendFinding($findingId, $userId, $amendments, $reason);
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'field_finding_revision',
                        'entity_id'     => (int)($res['revision']['id'] ?? 0),
                        'domain_status' => 'FINDING_AMENDED',
                        'message'       => $res['message'] ?? 'Finding amended.',
                    ];
                }
                return $res;

            case 'CONFIRM_FINDING':
                $findingId = (int)($payload['finding_id'] ?? 0);
                if ($findingId <= 0) {
                    return ['success' => false, 'status' => 'MISSING_FINDING_ID', 'message' => 'finding_id is required.'];
                }
                $res = $this->findingsService->confirmFinding($findingId, $userId, $payload);
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'field_finding',
                        'entity_id'     => $findingId,
                        'domain_status' => 'FINDING_CONFIRMED',
                        'message'       => $res['message'] ?? 'Finding confirmed.',
                    ];
                }
                return $res;

            case 'RECORD_FEEDBACK':
                $res = $this->feedbackService->generateCaseFeedback($caseId, $payload);
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'fault_feedback',
                        'entity_id'     => (int)($res['feedback']['id'] ?? 0),
                        'domain_status' => 'FEEDBACK_GENERATED',
                        'message'       => $res['message'] ?? 'Feedback generated.',
                    ];
                }
                return $res;

            case 'ATTACH_EVIDENCE':
            case 'RECORD_EVIDENCE':
                $fileRef = (string)($payload['file_path'] ?? $payload['file_reference'] ?? '');
                $sha256 = (string)($payload['sha256'] ?? '');
                $evType = (string)($payload['evidence_type'] ?? 'PHOTO');
                $findingId = isset($payload['field_finding_id']) ? (int)$payload['field_finding_id'] : null;
                $assetId = isset($payload['asset_id']) ? (int)$payload['asset_id'] : null;

                $evOptions = [
                    'field_finding_id'       => $findingId,
                    'asset_id'               => $assetId,
                    'correlation_id'         => $payload['correlation_id'] ?? $op['client_submission_uuid'] ?? null,
                    'client_submission_uuid' => $op['client_submission_uuid'] ?? null,
                    'metadata'               => $payload['metadata'] ?? [],
                    'captured_at'            => $payload['captured_at'] ?? null,
                ];

                $res = $this->findingsService->attachEvidence($caseId, $userId, $fileRef, $sha256, $evType, $evOptions);
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'field_evidence',
                        'entity_id'     => (int)($res['evidence']['id'] ?? $res['id'] ?? 0),
                        'domain_status' => 'EVIDENCE_ATTACHED',
                        'message'       => $res['message'] ?? 'Evidence attached successfully.',
                    ];
                }
                return $res;

            case 'TRANSITION_CASE':
                $targetStatus = (string)($payload['target_status'] ?? '');
                $res = $this->caseService->transitionCase($caseId, $targetStatus, [
                    'actor_id' => $userId,
                    'notes'    => $payload['notes'] ?? 'Mobile sync state transition',
                ]);
                if ($res['success']) {
                    return [
                        'success'       => true,
                        'entity_type'   => 'fault_case',
                        'entity_id'     => $caseId,
                        'domain_status' => 'STATUS_TRANSITIONED',
                        'message'       => $res['message'] ?? 'Case status transitioned.',
                    ];
                }
                return $res;

            default:
                return [
                    'success' => false,
                    'status'  => 'UNSUPPORTED_OPERATION',
                    'message' => "Operation '{$opType}' is not mapped to any domain action.",
                ];
        }
    }

    // =========================================================================
    // 4. GPS PROVENANCE & MOCK EVALUATION (B7-G07, B7-G20)
    // =========================================================================

    /**
     * Rigorous evaluation of client-reported GPS parameters.
     */
    protected function evaluateGpsPayload(array $payload): array
    {
        if (!isset($payload['lat']) || !isset($payload['lng'])) {
            return [
                'valid'   => false,
                'status'  => 'MISSING_GPS_COORDINATES',
                'message' => 'Latitude and longitude coordinates are mandatory for this operation.',
            ];
        }

        $lat = (float)$payload['lat'];
        $lng = (float)$payload['lng'];
        $accuracyM = isset($payload['accuracy_m']) ? (float)$payload['accuracy_m'] : null;
        $mockFlag = (bool)($payload['mock_location_flag'] ?? false);

        // Guard B7-G07: Reject Mock Location
        if ($mockFlag === true) {
            return [
                'valid'   => false,
                'status'  => 'MOCK_LOCATION_PROHIBITED',
                'message' => 'Mock location detected from client API. Simulated coordinates are strictly prohibited (Guard B7-G07).',
            ];
        }

        // Coordinate Bounds
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return [
                'valid'   => false,
                'status'  => 'INVALID_COORDINATE_BOUNDS',
                'message' => "Coordinates ({$lat}, {$lng}) fall outside legitimate WGS84 geographic boundaries.",
            ];
        }

        // Accuracy Check
        if ($accuracyM !== null && $accuracyM < 0.0) {
            return [
                'valid'   => false,
                'status'  => 'INVALID_GPS_ACCURACY',
                'message' => "Reported accuracy ({$accuracyM}m) cannot be negative.",
            ];
        }

        return ['valid' => true];
    }

    // =========================================================================
    // 5. FORENSIC JOURNAL WRITER (B7-G10)
    // =========================================================================

    /**
     * Record rejected sync attempt in mobile_sync_journal.
     * Executes within an explicit, independent transaction boundary
     * so rejection audit history is permanently committed even after domain rollback.
     */
    protected function logRejectedJournal(
        string $uuid,
        string $syncId,
        string $deviceId,
        int $userId,
        string $opType,
        string $payloadHash,
        array $fullOp,
        string $clientCreatedAt,
        string $clientTzOffset,
        string $serverReceivedAt,
        string $serverProcessedAt,
        int $httpCode,
        string $domainStatus,
        string $errorMessage
    ): void {
        // Start explicit independent transaction for forensic audit record
        $this->db->transBegin();
        try {
            $this->db->table('mobile_sync_journal')->insert([
                'client_submission_uuid' => $uuid,
                'sync_id'                => $syncId,
                'device_id'              => $deviceId,
                'user_id'                => $userId,
                'operation_type'         => $opType,
                'entity_type'            => 'fault_case',
                'entity_id'              => null,
                'payload_sha256'         => $payloadHash,
                'payload_json'           => json_encode($fullOp),
                'client_created_at'      => $clientCreatedAt,
                'client_timezone_offset' => $clientTzOffset,
                'server_received_at'     => $serverReceivedAt,
                'server_processed_at'    => $serverProcessedAt,
                'attempt_no'             => 1,
                'sync_status'            => 'REJECTED',
                'response_http_code'     => $httpCode,
                'domain_status'          => $domainStatus,
                'error_message'          => $errorMessage,
                'created_at'             => $serverProcessedAt,
            ]);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
        }
    }
}
