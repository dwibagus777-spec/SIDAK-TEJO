<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * SIDAK TEJO — Phase B.5: Fault Event Ingestion Service
 *
 * ARCHITECTURAL CONTRACT & HARD GUARDS:
 * - B5.2-G01: Canonical Normalization (Multi-source normalization layer)
 * - B5.2-G02: Source Provenance Enforcement (Explicit source_type & source_reference)
 * - B5.2-G03: Deterministic SHA-256 Fingerprint (Sorted canonical JSON payload)
 * - B5.2-G04: DB-Level Idempotency & Concurrency Safeguard (DB UNIQUE constraint)
 * - B5.2-G05: Temporal Topology Snapshot Binding (Resolves snapshot at event timestamp)
 * - B5.2-G06: Legacy Event Isolation (fingerprint = NULL + LEGACY_UNFINGERPRINTED preserved)
 * - B5.2-G07: Lifecycle Transition Enforcement (Strict finite state machine)
 * - B5.2-G08: Append-Only Revision Boundary (fault_event_revisions append-only)
 * - B5.2-G09: FLI-1.0.0 Bridge Isolation (Decoupled ingestion vs candidate inference)
 * - B5.2-G10: ZERO_TOPOLOGY_MUTATION (gis_translines = 0, assets = 0)
 * - B5.2-G11: INPUT_PROVENANCE_COMPLETENESS (Reject invalid/missing provenance)
 */
class FaultEventIngestionService
{
    public const INGESTION_VERSION = 'B5-INGEST-1.0';
    public const DEFAULT_TOPOLOGY_SNAPSHOT = 'TOPOLOGY-20260925-243-ad2c9fcb';

    public const ALLOWED_SOURCES = [
        'SCADA',
        'PMCB_RELAY',
        'RECLOSER',
        'MANUAL_ENTRY',
        'IMPORT',
        'API',
    ];

    public const ALLOWED_LIFECYCLE_STATUSES = [
        'INGESTED',
        'ANALYZING',
        'CANDIDATE_IDENTIFIED',
        'DISPATCHED',
        'INVESTIGATING',
        'CONFIRMED',
        'UNRESOLVED',
        'CLOSED',
    ];

    /**
     * Allowed State Transitions: Current -> [Allowed Next]
     */
    public const LIFECYCLE_TRANSITIONS = [
        'INGESTED'             => ['ANALYZING'],
        'ANALYZING'            => ['CANDIDATE_IDENTIFIED', 'UNRESOLVED'],
        'CANDIDATE_IDENTIFIED' => ['DISPATCHED', 'ANALYZING'],
        'DISPATCHED'           => ['INVESTIGATING'],
        'INVESTIGATING'        => ['CONFIRMED', 'UNRESOLVED'],
        'CONFIRMED'            => ['CLOSED'],
        'UNRESOLVED'           => ['CLOSED', 'ANALYZING'],
        'CLOSED'               => [], // Terminal state: no transition allowed
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
    // GUARD 1 & 11: NORMALIZATION & PROVENANCE VALIDATION
    // =========================================================================

    /**
     * Normalize raw telemetry input from various protocols into a canonical schema.
     * Handles SCADA, PMCB_RELAY, RECLOSER, MANUAL_ENTRY, IMPORT, and API formats.
     */
    public function normalizeTelemetry(array $raw): array
    {
        // 1. Source Provenance
        $sourceType = strtoupper(trim((string)($raw['source_type'] ?? $raw['source'] ?? 'API')));
        $sourceRef = trim((string)($raw['source_reference'] ?? $raw['source_ref'] ?? $raw['ticket_no'] ?? $raw['record_id'] ?? $raw['event_id'] ?? ''));

        // 2. Device & Feeder Identifiers
        $penyulangId = isset($raw['penyulang_id']) ? (int)$raw['penyulang_id'] : (isset($raw['feeder_id']) ? (int)$raw['feeder_id'] : null);
        $deviceId = isset($raw['source_device_asset_id']) ? (int)$raw['source_device_asset_id'] : (isset($raw['device_id']) ? (int)$raw['device_id'] : null);
        if ($deviceId !== null && $deviceId <= 0) {
            $deviceId = null;
        }

        // 3. Event Timestamp
        $rawTime = $raw['event_time'] ?? $raw['timestamp'] ?? $raw['fault_time'] ?? null;
        $eventTime = null;
        if (!empty($rawTime)) {
            $ts = strtotime((string)$rawTime);
            if ($ts !== false) {
                $eventTime = date('Y-m-d H:i:s', $ts);
            }
        }

        // 4. Fault Phase Normalization
        $rawPhase = strtoupper(trim((string)($raw['fault_phase'] ?? $raw['phase'] ?? $raw['fasa'] ?? 'UNKNOWN')));
        $faultPhase = $this->canonicalizePhase($rawPhase);

        // 5. Fault Current Normalization
        $faultCurrent = null;
        if (isset($raw['fault_current_a'])) {
            $faultCurrent = round((float)$raw['fault_current_a'], 2);
        } elseif (isset($raw['current'])) {
            $faultCurrent = round((float)$raw['current'], 2);
        } elseif (isset($raw['arus_gangguan'])) {
            $faultCurrent = round((float)$raw['arus_gangguan'], 2);
        }

        // 6. Phase Currents (Ir, Is, It, In / Ia, Ib, Ic, I0)
        $phaseCurrents = $this->canonicalizePhaseCurrents($raw);

        // 7. Protection Elements
        $protectionElements = trim((string)($raw['protection_elements'] ?? $raw['relay_element'] ?? $raw['protection'] ?? ''));
        if ($protectionElements === '' && isset($raw['elements']) && is_array($raw['elements'])) {
            $protectionElements = implode('+', array_map('strtoupper', $raw['elements']));
        }

        // 8. Trip Sequence
        $tripSequence = strtoupper(trim((string)($raw['trip_sequence'] ?? $raw['sequence'] ?? '')));
        if ($tripSequence === '') {
            $tripSequence = 'TRIP_1';
        }

        // 9. Relay Distance Estimate
        $relayDistance = null;
        if (isset($raw['relay_distance_m'])) {
            $relayDistance = round((float)$raw['relay_distance_m'], 2);
        } elseif (isset($raw['distance_m'])) {
            $relayDistance = round((float)$raw['distance_m'], 2);
        } elseif (isset($raw['distance_km'])) {
            $relayDistance = round((float)$raw['distance_km'] * 1000.0, 2);
        } elseif (isset($raw['distance'])) {
            $relayDistance = round((float)$raw['distance'], 2);
        }

        // 10. Canonical Telemetry Bag (filtered of top-level standard fields)
        $telemetryBag = $raw['telemetry'] ?? $raw['extra_telemetry'] ?? [];
        if (!is_array($telemetryBag)) {
            $telemetryBag = [];
        }
        // Retain any protocol-specific fields
        $knownKeys = [
            'source_type', 'source', 'source_reference', 'source_ref', 'ticket_no', 'record_id', 'event_id',
            'penyulang_id', 'feeder_id', 'source_device_asset_id', 'device_id',
            'event_time', 'timestamp', 'fault_time', 'fault_phase', 'phase', 'fasa',
            'fault_current_a', 'current', 'arus_gangguan', 'phase_currents', 'phase_currents_json',
            'protection_elements', 'relay_element', 'protection', 'trip_sequence', 'sequence',
            'relay_distance_m', 'distance_m', 'distance_km', 'distance', 'telemetry', 'extra_telemetry'
        ];
        foreach ($raw as $k => $v) {
            if (!in_array($k, $knownKeys, true) && !is_object($v)) {
                $telemetryBag[$k] = $v;
            }
        }

        return [
            'source_type'            => $sourceType,
            'source_reference'        => $sourceRef,
            'penyulang_id'            => $penyulangId,
            'source_device_asset_id'  => $deviceId,
            'event_time'              => $eventTime,
            'fault_phase'             => $faultPhase,
            'fault_current_a'         => $faultCurrent,
            'phase_currents'          => $phaseCurrents,
            'phase_currents_json'     => !empty($phaseCurrents) ? json_encode($phaseCurrents) : null,
            'protection_elements'     => $protectionElements !== '' ? $protectionElements : null,
            'trip_sequence'           => $tripSequence,
            'relay_distance_m'        => $relayDistance,
            'canonical_telemetry'     => $telemetryBag,
            'raw_telemetry_json'      => json_encode($raw),
        ];
    }

    /**
     * Validate Provenance Completeness (B5.2-G02 & B5.2-G11)
     * Rejects input if minimum provenance information is missing.
     */
    public function validateProvenance(array $normalized): array
    {
        // 1. Source Type
        if (empty($normalized['source_type']) || !in_array($normalized['source_type'], self::ALLOWED_SOURCES, true)) {
            return [
                'valid'   => false,
                'code'    => 'REJECTED_INVALID_PROVENANCE',
                'field'   => 'source_type',
                'message' => 'Source type tidak valid atau tidak didukung: ' . ($normalized['source_type'] ?? 'EMPTY') .
                             '. Harus salah satu dari: ' . implode(', ', self::ALLOWED_SOURCES),
            ];
        }

        // 2. Source Reference
        if (empty($normalized['source_reference']) || trim((string)$normalized['source_reference']) === '') {
            return [
                'valid'   => false,
                'code'    => 'REJECTED_INVALID_PROVENANCE',
                'field'   => 'source_reference',
                'message' => 'Source reference (nomor tiket/ID rekaman relay/SCADA ticket) wajib disertakan.',
            ];
        }

        // 3. Event Time
        if (empty($normalized['event_time'])) {
            return [
                'valid'   => false,
                'code'    => 'REJECTED_INVALID_PROVENANCE',
                'field'   => 'event_time',
                'message' => 'Event time (waktu kejadian trip/telemetri) wajib memiliki format tanggal dan waktu yang valid.',
            ];
        }

        // 4. Penyulang ID
        if (empty($normalized['penyulang_id']) || (int)$normalized['penyulang_id'] <= 0) {
            return [
                'valid'   => false,
                'code'    => 'REJECTED_INVALID_PROVENANCE',
                'field'   => 'penyulang_id',
                'message' => 'Penyulang ID (feeder_id) wajib bernilai integer positif > 0.',
            ];
        }

        return ['valid' => true];
    }

    // =========================================================================
    // GUARD 3: DETERMINISTIC SHA-256 FINGERPRINT
    // =========================================================================

    /**
     * Compute Deterministic Event Fingerprint (B5.2-G03)
     * Hashes canonical telemetry so differences in JSON key ordering or whitespace
     * yield the exact same fingerprint.
     */
    public function computeEventFingerprint(array $canonical): string
    {
        $telemetry = $canonical['canonical_telemetry'] ?? [];
        if (!is_array($telemetry)) {
            $telemetry = [];
        }
        $telemetry = $this->sortArrayRecursive($telemetry);

        $phaseCurrents = $canonical['phase_currents'] ?? [];
        if (is_array($phaseCurrents)) {
            $phaseCurrents = $this->sortArrayRecursive($phaseCurrents);
        }

        $canonicalRepresentation = [
            'source_type'         => (string)$canonical['source_type'],
            'source_reference'    => (string)$canonical['source_reference'],
            'device_id'           => isset($canonical['source_device_asset_id']) ? (int)$canonical['source_device_asset_id'] : 0,
            'event_time'          => (string)$canonical['event_time'],
            'penyulang_id'        => (int)$canonical['penyulang_id'],
            'fault_phase'         => (string)($canonical['fault_phase'] ?? 'UNKNOWN'),
            'fault_current_a'     => isset($canonical['fault_current_a']) ? number_format((float)$canonical['fault_current_a'], 2, '.', '') : '0.00',
            'protection_elements' => (string)($canonical['protection_elements'] ?? ''),
            'trip_sequence'       => (string)($canonical['trip_sequence'] ?? 'TRIP_1'),
            'relay_distance_m'    => isset($canonical['relay_distance_m']) ? number_format((float)$canonical['relay_distance_m'], 2, '.', '') : '0.00',
            'phase_currents'      => $phaseCurrents,
            'telemetry'           => $telemetry,
        ];

        // Sort top-level keys
        ksort($canonicalRepresentation);

        $canonicalJson = json_encode($canonicalRepresentation, JSON_UNESCAPED_SLASHES);
        return hash('sha256', $canonicalJson);
    }

    // =========================================================================
    // GUARD 5: TEMPORAL TOPOLOGY SNAPSHOT BINDING
    // =========================================================================

    /**
     * Resolve Topology Snapshot Valid at Event Timestamp (B5.2-G05)
     * Binds the event to the network topology state in effect at $eventTime.
     */
    public function resolveTopologySnapshot(?string $eventTime): string
    {
        if (empty($eventTime)) {
            return self::DEFAULT_TOPOLOGY_SNAPSHOT;
        }

        // Query network_topology_versions if available
        if ($this->db->tableExists('network_topology_versions')) {
            $row = $this->db->table('network_topology_versions')
                ->where('created_at <=', $eventTime)
                ->orderBy('created_at', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();

            if (!empty($row['snapshot_id'])) {
                return $row['snapshot_id'];
            }
            if (!empty($row['version_code'])) {
                return $row['version_code'];
            }
        }

        // For current baseline (effective from 2026-09-25 onwards)
        return self::DEFAULT_TOPOLOGY_SNAPSHOT;
    }

    // =========================================================================
    // GUARD 4 & 10: INGESTION PIPELINE & DB-LEVEL IDEMPOTENCY
    // =========================================================================

    /**
     * Ingest a Single Fault Event (Guards G01 - G05, G10, G11)
     * Performs normalization, provenance validation, fingerprinting, snapshot binding,
     * duplicate detection, and transactional storage.
     */
    public function ingestEvent(array $rawInput): array
    {
        // Pre-ingestion topology invariant snapshot
        $tlBefore = $this->db->tableExists('gis_translines') ? $this->db->table('gis_translines')->countAllResults() : 0;
        $assetBefore = $this->db->tableExists('assets') ? $this->db->table('assets')->countAllResults() : 0;

        // 1. Normalization (B5.2-G01)
        $norm = $this->normalizeTelemetry($rawInput);

        // 2. Provenance Completeness Validation (B5.2-G02 & B5.2-G11)
        $validation = $this->validateProvenance($norm);
        if (!$validation['valid']) {
            return [
                'status'       => $validation['code'],
                'success'      => false,
                'field'        => $validation['field'] ?? null,
                'message'      => $validation['message'],
                'raw_payload'  => $rawInput,
            ];
        }

        // 3. Deterministic SHA-256 Fingerprint (B5.2-G03)
        $fingerprint = $this->computeEventFingerprint($norm);

        // 4. Pre-check Existing Fingerprint (B5.2-G04 Idempotency)
        if ($this->db->tableExists('fault_events')) {
            $existing = $this->db->table('fault_events')
                ->where('event_fingerprint', $fingerprint)
                ->get()
                ->getRowArray();

            if (!empty($existing)) {
                return [
                    'status'           => 'IDEMPOTENT_DUPLICATE',
                    'success'          => true,
                    'is_duplicate'     => true,
                    'event_id'         => (int)$existing['id'],
                    'event_number'     => $existing['event_number'],
                    'event_fingerprint'=> $existing['event_fingerprint'],
                    'fingerprint_status'=> $existing['fingerprint_status'] ?? 'CALCULATED',
                    'lifecycle_status' => $existing['lifecycle_status'] ?? 'INGESTED',
                    'message'          => 'Telemetry identical to existing event. Replayed idempotently without duplicate record.',
                    'event'            => $existing,
                ];
            }
        }

        // 5. Temporal Snapshot Resolution (B5.2-G05)
        $snapshotId = $this->resolveTopologySnapshot($norm['event_time']);

        // 6. Generate Event Number
        $timeSlug = date('Ymd-His', strtotime($norm['event_time']));
        $hashSlug = strtoupper(substr($fingerprint, 0, 8));
        $eventNumber = "EVT-{$timeSlug}-{$hashSlug}";

        // 7. Transactional Storage with DB UNIQUE safeguard
        $this->db->transStart();
        try {
            $insertData = [
                'event_number'           => $eventNumber,
                'event_fingerprint'      => $fingerprint,
                'fingerprint_status'     => 'CALCULATED',
                'penyulang_id'           => $norm['penyulang_id'],
                'source_device_asset_id' => $norm['source_device_asset_id'],
                'event_time'             => $norm['event_time'],
                'topology_snapshot_id'   => $snapshotId,
                'source_type'            => $norm['source_type'],
                'source_reference'       => $norm['source_reference'],
                'fault_phase'            => $norm['fault_phase'],
                'fault_current_a'        => $norm['fault_current_a'],
                'phase_currents_json'    => $norm['phase_currents_json'],
                'protection_elements'    => $norm['protection_elements'],
                'trip_sequence'          => $norm['trip_sequence'],
                'relay_distance_m'       => $norm['relay_distance_m'],
                'raw_telemetry_json'     => $norm['raw_telemetry_json'],
                'status'                 => 'OPEN',
                'lifecycle_status'       => 'INGESTED',
                'created_at'             => date('Y-m-d H:i:s'),
                'updated_at'             => date('Y-m-d H:i:s'),
            ];

            $this->db->table('fault_events')->insert($insertData);
            $insertedId = (int)$this->db->insertID();

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new RuntimeException("Database transaction failed during event insertion.");
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();

            // Check if failure was caused by concurrency race on UNIQUE constraint
            $existingRace = $this->db->table('fault_events')
                ->where('event_fingerprint', $fingerprint)
                ->get()
                ->getRowArray();

            if (!empty($existingRace)) {
                return [
                    'status'           => 'IDEMPOTENT_DUPLICATE',
                    'success'          => true,
                    'is_duplicate'     => true,
                    'event_id'         => (int)$existingRace['id'],
                    'event_number'     => $existingRace['event_number'],
                    'event_fingerprint'=> $existingRace['event_fingerprint'],
                    'fingerprint_status'=> $existingRace['fingerprint_status'] ?? 'CALCULATED',
                    'lifecycle_status' => $existingRace['lifecycle_status'] ?? 'INGESTED',
                    'message'          => 'Duplicate caught by DB UNIQUE constraint. Replayed idempotently.',
                    'event'            => $existingRace,
                ];
            }

            throw $e;
        }

        // Post-ingestion topology invariant verification (B5.2-G10)
        $tlAfter = $this->db->tableExists('gis_translines') ? $this->db->table('gis_translines')->countAllResults() : 0;
        $assetAfter = $this->db->tableExists('assets') ? $this->db->table('assets')->countAllResults() : 0;
        if ($tlBefore !== $tlAfter || $assetBefore !== $assetAfter) {
            throw new RuntimeException(
                "CRITICAL INVARIANT VIOLATION: Topology was mutated during event ingestion! " .
                "gis_translines: {$tlBefore}->{$tlAfter}, assets: {$assetBefore}->{$assetAfter}"
            );
        }

        return [
            'status'             => 'INGESTED',
            'success'            => true,
            'is_duplicate'       => false,
            'event_id'           => $insertedId,
            'event_number'       => $eventNumber,
            'event_fingerprint'  => $fingerprint,
            'fingerprint_status' => 'CALCULATED',
            'topology_snapshot_id' => $snapshotId,
            'lifecycle_status'   => 'INGESTED',
            'message'            => 'Fault event ingested and normalized successfully.',
            'normalized_payload' => $norm,
        ];
    }

    // =========================================================================
    // GUARD 7: LIFECYCLE FINITE STATE MACHINE
    // =========================================================================

    /**
     * Transition Event Lifecycle Status (B5.2-G07)
     * Enforces the valid transition graph. Rejects shortcuts or invalid progressions.
     */
    public function transitionLifecycle(int $eventId, string $targetStatus, array $context = []): array
    {
        $targetStatus = strtoupper(trim($targetStatus));

        if (!in_array($targetStatus, self::ALLOWED_LIFECYCLE_STATUSES, true)) {
            return [
                'status'  => 'REJECTED_UNKNOWN_STATUS',
                'success' => false,
                'message' => "Target lifecycle status '{$targetStatus}' tidak dikenal.",
                'allowed' => self::ALLOWED_LIFECYCLE_STATUSES,
            ];
        }

        $event = $this->db->table('fault_events')->where('id', $eventId)->get()->getRowArray();
        if (empty($event)) {
            return [
                'status'  => 'NOT_FOUND',
                'success' => false,
                'message' => "Fault event #{$eventId} tidak ditemukan.",
            ];
        }

        $currentStatus = strtoupper(trim((string)($event['lifecycle_status'] ?? 'INGESTED')));

        // If target is same as current, return idempotent OK
        if ($currentStatus === $targetStatus) {
            return [
                'status'         => 'NOOP_SAME_STATUS',
                'success'        => true,
                'event_id'       => $eventId,
                'current_status' => $currentStatus,
                'message'        => "Event #{$eventId} sudah berada pada lifecycle status '{$currentStatus}'.",
            ];
        }

        // Verify transition validity
        $allowedNext = self::LIFECYCLE_TRANSITIONS[$currentStatus] ?? [];
        if (!in_array($targetStatus, $allowedNext, true)) {
            return [
                'status'         => 'REJECTED_INVALID_TRANSITION',
                'success'        => false,
                'event_id'       => $eventId,
                'current_status' => $currentStatus,
                'target_status'  => $targetStatus,
                'allowed_next'   => $allowedNext,
                'message'        => "Transisi tidak diizinkan: dari '{$currentStatus}' ke '{$targetStatus}'. " .
                                    (empty($allowedNext) ? "Status '{$currentStatus}' adalah terminal." : "Transisi yang sah: " . implode(', ', $allowedNext)),
            ];
        }

        // Execute transition
        $updateData = [
            'lifecycle_status' => $targetStatus,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        // Keep legacy status column roughly synchronized for backward UI compatibility
        if ($targetStatus === 'CANDIDATE_IDENTIFIED') {
            $updateData['status'] = 'ANALYZED';
        } elseif ($targetStatus === 'CONFIRMED') {
            $updateData['status'] = 'RESOLVED';
        } elseif ($targetStatus === 'CLOSED') {
            $updateData['status'] = 'CLOSED';
        }

        $this->db->table('fault_events')->where('id', $eventId)->update($updateData);

        return [
            'status'          => 'TRANSITION_SUCCESS',
            'success'         => true,
            'event_id'        => $eventId,
            'previous_status' => $currentStatus,
            'new_status'      => $targetStatus,
            'message'         => "Event #{$eventId} berhasil transisi dari '{$currentStatus}' ke '{$targetStatus}'.",
        ];
    }

    // =========================================================================
    // GUARD 8 & 6: APPEND-ONLY REVISION BOUNDARY & LEGACY ISOLATION
    // =========================================================================

    /**
     * Amend Event Telemetry (B5.2-G08 Append-Only Revision Boundary & B5.2-G06 Legacy Isolation)
     * Preserves original raw telemetry; records amendment in fault_event_revisions.
     */
    public function amendEvent(int $eventId, array $amendedFields, string $reason, string $amendedBy): array
    {
        $event = $this->db->table('fault_events')->where('id', $eventId)->get()->getRowArray();
        if (empty($event)) {
            return [
                'status'  => 'NOT_FOUND',
                'success' => false,
                'message' => "Fault event #{$eventId} tidak ditemukan.",
            ];
        }

        if (trim($reason) === '') {
            return [
                'status'  => 'REJECTED_MISSING_REASON',
                'success' => false,
                'message' => 'Alasan amandemen (amendment_reason) wajib diisi untuk audit trail.',
            ];
        }

        if (trim($amendedBy) === '') {
            return [
                'status'  => 'REJECTED_MISSING_AMENDER',
                'success' => false,
                'message' => 'Identitas pengubah (amended_by) wajib diisi untuk audit trail.',
            ];
        }

        // Get current max revision number for this event
        $lastRev = $this->db->table('fault_event_revisions')
            ->where('fault_event_id', $eventId)
            ->selectMax('revision_no')
            ->get()
            ->getRowArray();
        $nextRevNo = ($lastRev['revision_no'] ?? 0) + 1;

        // Record previous values for amended keys
        $previousValues = [];
        foreach ($amendedFields as $k => $v) {
            $previousValues[$k] = $event[$k] ?? null;
        }

        // Insert append-only revision record
        $this->db->table('fault_event_revisions')->insert([
            'fault_event_id'       => $eventId,
            'revision_no'          => $nextRevNo,
            'amended_by'           => $amendedBy,
            'amendment_reason'     => $reason,
            'previous_values_json' => json_encode($previousValues),
            'amended_fields_json'  => json_encode($amendedFields),
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        $revId = (int)$this->db->insertID();

        // Update safe descriptive/operational fields on fault_events, but NEVER mutate event_fingerprint
        // or synthesize a fingerprint for legacy records (B5.2-G06)
        $safeUpdate = [];
        $disallowedInDirectUpdate = ['id', 'event_fingerprint', 'fingerprint_status', 'created_at'];
        foreach ($amendedFields as $k => $v) {
            if (!in_array($k, $disallowedInDirectUpdate, true) && array_key_exists($k, $event)) {
                $safeUpdate[$k] = $v;
            }
        }
        if (!empty($safeUpdate)) {
            $safeUpdate['updated_at'] = date('Y-m-d H:i:s');
            $this->db->table('fault_events')->where('id', $eventId)->update($safeUpdate);
        }

        return [
            'status'         => 'REVISION_APPLIED',
            'success'        => true,
            'event_id'       => $eventId,
            'revision_id'    => $revId,
            'revision_no'    => $nextRevNo,
            'is_legacy'      => ($event['fingerprint_status'] ?? '') === 'LEGACY_UNFINGERPRINTED',
            'amended_fields' => array_keys($amendedFields),
            'message'        => "Revisi #{$nextRevNo} berhasil dicatat secara append-only untuk event #{$eventId}.",
        ];
    }

    // =========================================================================
    // GUARD 9: FLI-1.0.0 BRIDGE ISOLATION
    // =========================================================================

    /**
     * Trigger FLI Analysis Bridge (B5.2-G09)
     * Bridges an ingested event to FLI-1.0.0 candidate ranking without mutating topology.
     */
    public function triggerAnalysis(int $eventId, ?float $toleranceM = null): array
    {
        $event = $this->db->table('fault_events')->where('id', $eventId)->get()->getRowArray();
        if (empty($event)) {
            return [
                'status'  => 'NOT_FOUND',
                'success' => false,
                'message' => "Fault event #{$eventId} tidak ditemukan.",
            ];
        }

        // Step 1: Transition lifecycle INGESTED -> ANALYZING (or allow re-analysis from CANDIDATE_IDENTIFIED/UNRESOLVED)
        $currentLife = strtoupper(trim((string)($event['lifecycle_status'] ?? 'INGESTED')));
        if ($currentLife === 'INGESTED') {
            $this->transitionLifecycle($eventId, 'ANALYZING');
        } elseif (in_array($currentLife, ['CANDIDATE_IDENTIFIED', 'UNRESOLVED'], true)) {
            $this->transitionLifecycle($eventId, 'ANALYZING');
        } elseif ($currentLife !== 'ANALYZING') {
            return [
                'status'  => 'REJECTED_LIFECYCLE_STATE',
                'success' => false,
                'message' => "Tidak dapat menjalankan analisis FLI pada event dengan status '{$currentLife}'.",
            ];
        }

        // Step 2: Extract analysis parameters
        $penyulangId = (int)$event['penyulang_id'];
        $deviceId = !empty($event['source_device_asset_id']) ? (int)$event['source_device_asset_id'] : null;
        $targetDistance = !empty($event['relay_distance_m']) ? (float)$event['relay_distance_m'] : 0.0;
        $faultPhase = !empty($event['fault_phase']) ? $event['fault_phase'] : 'UNKNOWN';

        if ($deviceId === null || $deviceId <= 0) {
            // Cannot run FLI without anchor device
            $this->transitionLifecycle($eventId, 'UNRESOLVED');
            return [
                'status'       => 'UNRESOLVED',
                'success'      => false,
                'event_id'     => $eventId,
                'message'      => 'Analisis FLI tidak dapat dijalankan: source_device_asset_id (anchor PMCB/Recloser) tidak terdefinisi.',
                'candidates'   => [],
            ];
        }

        // Step 3: Call FLI Engine (Strict Read-Only Analysis)
        $fli = $this->getFliService();
        $options = [
            'fault_type'       => $faultPhase,
            'fault_event_id'   => $eventId,
            'event_number'     => $event['event_number'],
            'fault_current_a'  => $event['fault_current_a'] ?? null,
            'trip_sequence'    => $event['trip_sequence'] ?? null,
        ];

        $analysisResult = $fli->locateCandidates(
            $penyulangId,
            $deviceId,
            $targetDistance,
            $toleranceM,
            $options
        );

        $candidates = $analysisResult['payload']['candidates'] ?? [];

        // Step 4: Create Fault Case and store candidates (if DB writable)
        $caseRecord = null;
        if ($this->db->tableExists('fault_cases')) {
            $caseNumber = 'CASE-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $topCand = !empty($candidates) ? $candidates[0] : null;

            $caseData = [
                'case_number'               => $caseNumber,
                'fault_event_id'            => $eventId,
                'topology_snapshot_id'      => $analysisResult['topology_snapshot_id'] ?? FaultLocationIntelligenceService::TOPOLOGY_SNAPSHOT_ID,
                'analysis_version'          => $analysisResult['analysis_version'] ?? FaultLocationIntelligenceService::ANALYSIS_VERSION,
                'analysis_input_hash'       => $analysisResult['payload']['analysis_input_hash'] ?? hash('sha256', (string)$eventId),
                'device_asset_id'           => $deviceId,
                'target_distance_meters'    => $targetDistance,
                'distance_tolerance_meters' => $toleranceM ?? FaultLocationIntelligenceService::DEFAULT_DISTANCE_TOLERANCE,
                'fault_type'                => $faultPhase,
                'impedance_supported'       => 0,
                'impedance_reason'          => 'NO_CANONICAL_IMPEDANCE_PROFILE',
                'candidate_count'           => count($candidates),
                'top_candidate_asset_id'    => $topCand ? (int)$topCand['asset_id'] : null,
                'top_confidence_score'      => $topCand ? (float)$topCand['confidence_score'] : null,
                'status'                    => !empty($candidates) ? 'CANDIDATE_IDENTIFIED' : 'OPEN',
                'analysis_timestamp'        => date('Y-m-d H:i:s'),
                'created_at'                => date('Y-m-d H:i:s'),
                'updated_at'                => date('Y-m-d H:i:s'),
            ];

            $this->db->table('fault_cases')->insert($caseData);
            $caseId = (int)$this->db->insertID();
            $caseRecord = array_merge($caseData, ['id' => $caseId]);

            // Insert candidate records
            if (!empty($candidates) && $this->db->tableExists('fault_candidate_assets')) {
                $candRows = [];
                foreach ($candidates as $cand) {
                    $candRows[] = [
                        'fault_case_id'                => $caseId,
                        'asset_id'                     => (int)$cand['asset_id'],
                        'rank'                         => (int)$cand['rank'],
                        'candidate_status'             => 'CANDIDATE',
                        'graph_distance_from_device_m' => (float)$cand['graph_distance_m'],
                        'distance_delta_m'             => (float)$cand['distance_delta_m'],
                        'confidence_score'             => (float)$cand['confidence_score'],
                        'evidence_breakdown_json'      => json_encode($cand['evidence_breakdown'] ?? []),
                        'path_asset_ids_json'          => json_encode($cand['path_asset_ids'] ?? []),
                        'created_at'                   => date('Y-m-d H:i:s'),
                        'updated_at'                   => date('Y-m-d H:i:s'),
                    ];
                }
                $this->db->table('fault_candidate_assets')->insertBatch($candRows);
            }
        }

        // Step 5: Transition lifecycle based on candidates
        if (!empty($candidates)) {
            $this->transitionLifecycle($eventId, 'CANDIDATE_IDENTIFIED');
            $finalLifecycle = 'CANDIDATE_IDENTIFIED';
        } else {
            $this->transitionLifecycle($eventId, 'UNRESOLVED');
            $finalLifecycle = 'UNRESOLVED';
        }

        return [
            'status'            => 'ANALYSIS_COMPLETE',
            'success'           => true,
            'event_id'          => $eventId,
            'lifecycle_status'  => $finalLifecycle,
            'engine_version'    => FaultLocationIntelligenceService::ANALYSIS_VERSION,
            'candidates_count'  => count($candidates),
            'top_candidate'     => !empty($candidates) ? $candidates[0] : null,
            'case'              => $caseRecord,
            'analysis_payload'  => $analysisResult['payload'] ?? [],
        ];
    }

    // =========================================================================
    // BATCH INGESTION & IDEMPOTENCY
    // =========================================================================

    /**
     * Ingest a Batch of Fault Events (Idempotent batch processing)
     */
    public function ingestBatch(array $records, string $sourceType, array $batchMetadata = []): array
    {
        $sourceType = strtoupper(trim($sourceType));
        $batchUuid = 'BATCH-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $batchHash = hash('sha256', $sourceType . ':' . json_encode($records));

        $totalRecords = count($records);
        $accepted = 0;
        $duplicates = 0;
        $rejected = 0;
        $results = [];

        foreach ($records as $index => $rec) {
            if (!isset($rec['source_type'])) {
                $rec['source_type'] = $sourceType;
            }

            try {
                $res = $this->ingestEvent($rec);
                $results[] = [
                    'index'   => $index,
                    'status'  => $res['status'],
                    'success' => $res['success'],
                    'event_id'=> $res['event_id'] ?? null,
                    'is_dup'  => $res['is_duplicate'] ?? false,
                    'message' => $res['message'],
                ];

                if (!empty($res['is_duplicate'])) {
                    $duplicates++;
                } elseif (!empty($res['success'])) {
                    $accepted++;
                } else {
                    $rejected++;
                }
            } catch (\Throwable $e) {
                $rejected++;
                $results[] = [
                    'index'   => $index,
                    'status'  => 'ERROR',
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        // Record batch audit row
        if ($this->db->tableExists('fault_ingestion_batches')) {
            $this->db->table('fault_ingestion_batches')->insert([
                'batch_uuid'        => $batchUuid,
                'source_type'       => $sourceType,
                'batch_hash'        => $batchHash,
                'total_records'     => $totalRecords,
                'accepted_records'  => $accepted,
                'duplicate_records' => $duplicates,
                'status'            => 'COMPLETED',
                'metadata_json'     => json_encode($batchMetadata),
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'batch_uuid'         => $batchUuid,
            'source_type'        => $sourceType,
            'batch_hash'         => $batchHash,
            'total_records'      => $totalRecords,
            'accepted_records'   => $accepted,
            'duplicate_records'  => $duplicates,
            'rejected_records'   => $rejected,
            'results'            => $results,
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Standardize fault phase codes
     */
    protected function canonicalizePhase(string $phase): string
    {
        $phase = strtoupper(str_replace([' ', '_'], '', $phase));
        $validMap = [
            'R'     => 'R',
            'S'     => 'S',
            'T'     => 'T',
            'RN'    => 'RN',
            'SN'    => 'SN',
            'TN'    => 'TN',
            'RS'    => 'RS',
            'ST'    => 'ST',
            'TR'    => 'TR',
            'RSG'   => 'RS-G',
            'RS-G'  => 'RS-G',
            'STG'   => 'ST-G',
            'ST-G'  => 'ST-G',
            'TRG'   => 'TR-G',
            'TR-G'  => 'TR-G',
            'RST'   => 'RST',
            'RSTG'  => 'RST-G',
            'RST-G' => 'RST-G',
            'A'     => 'R',
            'B'     => 'S',
            'C'     => 'T',
            'AG'    => 'RN',
            'BG'    => 'SN',
            'CG'    => 'TN',
            'AB'    => 'RS',
            'BC'    => 'ST',
            'CA'    => 'TR',
            'ABG'   => 'RS-G',
            'BCG'   => 'ST-G',
            'CAG'   => 'TR-G',
            'ABC'   => 'RST',
            'ABCG'  => 'RST-G',
        ];

        return $validMap[$phase] ?? 'UNKNOWN';
    }

    /**
     * Canonicalize individual phase currents
     */
    protected function canonicalizePhaseCurrents(array $raw): array
    {
        $currents = [];
        $sources = $raw['phase_currents'] ?? $raw;
        if (is_string($sources)) {
            $decoded = json_decode($sources, true);
            if (is_array($decoded)) {
                $sources = $decoded;
            }
        }

        if (is_array($sources)) {
            // R or A
            if (isset($sources['ir'])) $currents['ir'] = round((float)$sources['ir'], 2);
            elseif (isset($sources['ia'])) $currents['ir'] = round((float)$sources['ia'], 2);
            elseif (isset($sources['r'])) $currents['ir'] = round((float)$sources['r'], 2);

            // S or B
            if (isset($sources['is'])) $currents['is'] = round((float)$sources['is'], 2);
            elseif (isset($sources['ib'])) $currents['is'] = round((float)$sources['ib'], 2);
            elseif (isset($sources['s'])) $currents['is'] = round((float)$sources['s'], 2);

            // T or C
            if (isset($sources['it'])) $currents['it'] = round((float)$sources['it'], 2);
            elseif (isset($sources['ic'])) $currents['it'] = round((float)$sources['ic'], 2);
            elseif (isset($sources['t'])) $currents['it'] = round((float)$sources['t'], 2);

            // N or 0
            if (isset($sources['in'])) $currents['in'] = round((float)$sources['in'], 2);
            elseif (isset($sources['i0'])) $currents['in'] = round((float)$sources['i0'], 2);
            elseif (isset($sources['n'])) $currents['in'] = round((float)$sources['n'], 2);
        }

        return $currents;
    }

    /**
     * Recursively sort array keys for deterministic hashing
     */
    protected function sortArrayRecursive(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortArrayRecursive($value);
            }
        }
        ksort($array);
        return $array;
    }
}
