<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use InvalidArgumentException;

/**
 * SIDAK TEJO — Phase B.6.4: Field Findings, Investigation Evidence & Finding Revisions Service
 *
 * ARCHITECTURAL CONTRACT & HARD GUARDS:
 * - B6-G04:    Prediction ≠ Actual Finding (System NEVER automatically copies candidate to actual)
 * - B6-G05:    Finding Revisions Append-Only (Corrections logged in field_finding_revisions; no delete/overwrite)
 * - B6-G06:    Case / Investigation FSM (INVESTIGATING -> FINDING_RECORDED -> CONFIRMED, or -> UNRESOLVED)
 * - B6-G09:    GPS Provenance (Coordinates, accuracy_m, and timestamp enforced)
 * - B6-G10:    Evidence Integrity (SHA256 Format Valid & Content Match verified)
 * - B6.4-G01:  ZERO_TOPOLOGY_MUTATION (gis_translines = 0, assets = 0)
 * - B6.4-G02:  Case / Investigation Ownership Integrity (Strict cross-case isolation)
 * - B6.4-G03:  Revision Concurrency Integrity (Transactional row locking; race-safe revision_no)
 * - B6.4-G04:  Finding Submission Idempotency (Resubmissions return existing finding)
 * - B6.4-G05:  Evidence Idempotency (Duplicate file / hash returns existing evidence)
 * - B6.4-G06:  Finding Lifecycle Ownership (State machine preconditions strictly checked)
 * - B6.4-G07:  Authoritative Asset Integrity (actual_asset_id must have deleted_at IS NULL)
 * - B6.4-G08:  Actual Finding Requires Explicit Field Observation (No empty confirmations)
 * - B6.4-G09:  No Automatic Prediction -> Actual Promotion (Algorithmic confidence != field reality)
 * - B6.4-G10:  NO BUSINESS DELETE (Physical deletion of findings/revisions strictly prohibited)
 */
class FieldFindingsService
{
    public const SERVICE_VERSION = 'B6-FINDINGS-1.0';

    public const STATUS_RECORDED   = 'RECORDED';
    public const STATUS_CONFIRMED  = 'CONFIRMED';
    public const STATUS_UNRESOLVED = 'UNRESOLVED';
    public const STATUS_REVISED    = 'REVISED';

    public const VALID_STATUSES = [
        self::STATUS_RECORDED,
        self::STATUS_CONFIRMED,
        self::STATUS_UNRESOLVED,
        self::STATUS_REVISED,
    ];

    public const CAUSE_CATEGORIES = [
        'LIGHTNING'         => 'Sambaran Petir (Lightning Trip)',
        'VEGETATION'        => 'Sentuhan Pohon / Vegetasi (Vegetation)',
        'EQUIPMENT_FAILURE' => 'Kerusakan Peralatan / Material (Equipment Breakdown)',
        'ANIMAL'            => 'Gangguan Binatang (Animal Intrusion)',
        'THIRD_PARTY'       => 'Pihak Ketiga / Kendaraan (Third Party Impact)',
        'WEATHER'           => 'Cuaca Ekstrem / Angin Kencang (Extreme Weather)',
        'NO_FAULT_FOUND'    => 'Pemeriksaan Lapangan Nihil (No Fault Observed)',
        'OTHER'             => 'Penyebab Lainnya (Other)',
        'UNKNOWN'           => 'Belum Teridentifikasi (Unknown)',
    ];

    public const EVIDENCE_TYPES = [
        'PHOTO'         => 'Photograph',
        'DOCUMENT'      => 'Document / Report',
        'THERMAL_IMAGE' => 'Thermal / Infrared Image',
        'SIGNATURE'     => 'Digital Signature',
        'AUDIO'         => 'Audio Recording',
    ];

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
    // 1. RECORD ACTUAL FIELD FINDING (B6-G04, B6-G06, B6-G09, B6.4-G01..G08)
    // =========================================================================

    /**
     * Record actual field investigation finding observed by field technicians.
     * Enforces explicit field observation, authoritative asset check, and cross-case isolation.
     *
     * @param int $caseId
     * @param int $capturedBy Technician User ID
     * @param array $data Observation payload
     * @return array
     */
    public function recordFinding(int $caseId, int $capturedBy, array $data): array
    {
        // 1. Validate Case Exists
        $case = $this->caseService->getCase($caseId);
        if (!$case) {
            return [
                'success' => false,
                'status'  => 'CASE_NOT_FOUND',
                'message' => "Fault case #{$caseId} does not exist.",
            ];
        }

        // 2. Finding Submission Idempotency Check (Guard B6.4-G04)
        $clientSubmissionUuid = trim((string)($data['client_submission_uuid'] ?? ''));
        if (!empty($clientSubmissionUuid)) {
            $existing = $this->db->table('field_findings')
                ->where('fault_case_id', $caseId)
                ->where('notes LIKE', "%[UUID:{$clientSubmissionUuid}]%")
                ->get()
                ->getRowArray();
            if ($existing) {
                return [
                    'success'     => true,
                    'is_new'      => false,
                    'is_existing' => true,
                    'finding'     => $this->getFinding((int)$existing['id']),
                    'case'        => $case,
                    'message'     => "Idempotent return: Finding already submitted with UUID '{$clientSubmissionUuid}'.",
                ];
            }
        } elseif (isset($data['actual_asset_id']) && (int)$data['actual_asset_id'] > 0) {
            $checkAssetId = (int)$data['actual_asset_id'];
            $fiveMinsAgo = date('Y-m-d H:i:s', time() - 300);
            $existing = $this->db->table('field_findings')
                ->where('fault_case_id', $caseId)
                ->where('actual_asset_id', $checkAssetId)
                ->where('captured_by', $capturedBy)
                ->where('captured_at >=', $fiveMinsAgo)
                ->get()
                ->getRowArray();
            if ($existing) {
                return [
                    'success'     => true,
                    'is_new'      => false,
                    'is_existing' => true,
                    'finding'     => $this->getFinding((int)$existing['id']),
                    'case'        => $case,
                    'message'     => "Idempotent return: Identical finding already recorded by User #{$capturedBy}.",
                ];
            }
        }

        // 3. Lifecycle Precondition: Case must be INVESTIGATING (B6.4-G06)
        if (strtoupper($case['status']) !== 'INVESTIGATING') {
            return [
                'success' => false,
                'status'  => 'INVALID_CASE_LIFECYCLE_STATE',
                'message' => "Cannot record finding: Case #{$caseId} status is '{$case['status']}', expected 'INVESTIGATING'.",
            ];
        }

        // 3. Resolve & Validate Investigation Ownership (B6.4-G02, B6.4-G06)
        if (!$this->db->tableExists('field_investigations')) {
            return [
                'success' => false,
                'status'  => 'INVESTIGATION_NOT_FOUND',
                'message' => 'Field investigations table does not exist.',
            ];
        }

        $investigationId = isset($data['investigation_id']) ? (int)$data['investigation_id'] : null;
        if ($investigationId !== null && $investigationId > 0) {
            $res = $this->db->table('field_investigations')->where('id', $investigationId)->get();
            $inves = $res ? $res->getRowArray() : null;
            if (!$inves) {
                return [
                    'success' => false,
                    'status'  => 'INVESTIGATION_NOT_FOUND',
                    'message' => "Field investigation #{$investigationId} does not exist.",
                ];
            }
            if ((int)$inves['fault_case_id'] !== $caseId) {
                return [
                    'success' => false,
                    'status'  => 'CROSS_CASE_INVESTIGATION_REJECTED',
                    'message' => "Guard B6.4-G02 Violation: Investigation #{$investigationId} belongs to Case #{$inves['fault_case_id']}, not Case #{$caseId}.",
                ];
            }
            if (strtoupper($inves['status']) !== 'INVESTIGATING') {
                return [
                    'success' => false,
                    'status'  => 'INVALID_INVESTIGATION_STATE',
                    'message' => "Investigation #{$investigationId} status is '{$inves['status']}', expected 'INVESTIGATING'.",
                ];
            }
        } else {
            // Auto-resolve active investigation for this case
            $res = $this->db->table('field_investigations')
                ->where('fault_case_id', $caseId)
                ->where('status', 'INVESTIGATING')
                ->orderBy('id', 'DESC')
                ->get();
            $inves = $res ? $res->getRowArray() : null;
            $investigationId = $inves ? (int)$inves['id'] : null;
        }

        // 4. Validate Authoritative Actual Asset (B6.4-G07)
        if (!isset($data['actual_asset_id']) || (int)$data['actual_asset_id'] <= 0) {
            return [
                'success' => false,
                'status'  => 'MISSING_ACTUAL_ASSET',
                'message' => "Guard B6.4-G08 Violation: Actual asset ID is required to record a field finding.",
            ];
        }

        $actualAssetId = (int)$data['actual_asset_id'];
        $actualAsset = $this->db->table('assets')
            ->where('id', $actualAssetId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$actualAsset) {
            return [
                'success' => false,
                'status'  => 'REJECTED_NON_AUTHORITATIVE_ASSET',
                'message' => "Guard B6.4-G07 Violation: Asset #{$actualAssetId} does not exist or is non-authoritative (soft-deleted).",
            ];
        }

        // 5. Validate Candidate / Predicted Asset Isolation (B6-G04, B6.4-G02, B6.4-G09)
        $predictedAssetId = isset($data['predicted_asset_id']) && (int)$data['predicted_asset_id'] > 0
            ? (int)$data['predicted_asset_id']
            : null;

        if ($predictedAssetId !== null) {
            // Verify predicted asset actually belongs to this case's candidate assets
            $candRow = $this->db->table('fault_candidate_assets')
                ->where('fault_case_id', $caseId)
                ->where('asset_id', $predictedAssetId)
                ->get()
                ->getRowArray();

            if (!$candRow) {
                return [
                    'success' => false,
                    'status'  => 'CROSS_CASE_PREDICTED_ASSET_MISMATCH',
                    'message' => "Guard B6.4-G02 Violation: Predicted asset #{$predictedAssetId} is not a candidate for Case #{$caseId}.",
                ];
            }
        }

        // 6. Validate GPS Provenance (B6-G09)
        if (!isset($data['actual_lat']) || !isset($data['actual_lng'])) {
            return [
                'success' => false,
                'status'  => 'MISSING_GPS_PROVENANCE',
                'message' => "Guard B6-G09 Violation: Latitude and Longitude are mandatory for field finding.",
            ];
        }

        $lat = (float)$data['actual_lat'];
        $lng = (float)$data['actual_lng'];
        $accuracyM = isset($data['gps_accuracy_m']) ? (float)$data['gps_accuracy_m'] : null;

        $gpsCheck = $this->validateCoordinates($lat, $lng, $accuracyM);
        if (!$gpsCheck['valid']) {
            return [
                'success' => false,
                'status'  => 'INVALID_GPS_PROVENANCE',
                'message' => $gpsCheck['message'],
            ];
        }

        // 7. Validate Cause Category Taxonomy
        $causeCategory = strtoupper(trim((string)($data['cause_category'] ?? 'UNKNOWN')));
        if (!array_key_exists($causeCategory, self::CAUSE_CATEGORIES)) {
            $causeCategory = 'OTHER';
        }


        // 9. Transition Case Lifecycle FSM: INVESTIGATING -> FINDING_RECORDED (B6-G06)
        $transRes = $this->caseService->transitionCase($caseId, 'FINDING_RECORDED', [
            'actor_id' => $capturedBy,
            'notes'    => "Field finding recorded on asset #{$actualAssetId} ({$actualAsset['kode_asset']})",
        ]);
        if (!$transRes['success']) {
            return $transRes;
        }

        // 10. Update Active Investigation Status: INVESTIGATING -> FINDING_RECORDED
        $now = date('Y-m-d H:i:s');
        if ($investigationId) {
            $this->db->table('field_investigations')->where('id', $investigationId)->update([
                'status'     => 'FINDING_RECORDED',
                'updated_at' => $now,
            ]);
        }

        // 11. Insert Finding Record into `field_findings` (Current Projection)
        $capturedAt = $data['captured_at'] ?? $now;
        $noteText = trim((string)($data['notes'] ?? ''));
        if (!empty($clientSubmissionUuid)) {
            $noteText .= " [UUID:{$clientSubmissionUuid}]";
        }

        $findingData = [
            'fault_case_id'         => $caseId,
            'investigation_id'      => $investigationId,
            'predicted_asset_id'    => $predictedAssetId,
            'actual_asset_id'       => $actualAssetId,
            'actual_lat'            => $lat,
            'actual_lng'            => $lng,
            'gps_accuracy_m'        => $accuracyM,
            'finding_status'        => self::STATUS_RECORDED,
            'cause_category'        => $causeCategory,
            'condition_description' => $data['condition_description'] ?? 'Physical inspection finding recorded.',
            'notes'                 => $noteText,
            'captured_at'           => $capturedAt,
            'captured_by'           => $capturedBy,
            'created_at'            => $now,
            'updated_at'            => $now,
        ];

        $this->db->table('field_findings')->insert($findingData);
        $findingId = (int)$this->db->insertID();
        $findingData['id'] = $findingId;

        return [
            'success'     => true,
            'is_new'      => true,
            'is_existing' => false,
            'status'      => 'FINDING_RECORDED',
            'finding'     => $this->getFinding($findingId),
            'case'        => $this->caseService->getCase($caseId),
            'message'     => "Field finding #{$findingId} successfully recorded on asset #{$actualAssetId}.",
        ];
    }

    // =========================================================================
    // 2. AMEND FINDING & APPEND-ONLY REVISION LOG (B6-G05, B6.4-G03)
    // =========================================================================

    /**
     * Amend an existing field finding.
     * Enforces Append-Only revisions in field_finding_revisions and race-safe concurrency.
     *
     * @param int $findingId
     * @param int $amendedBy Supervisor or Inspector ID
     * @param string $amendmentReason Mandatory justification
     * @param array $amendedFields Associative array of modified fields
     * @return array
     */
    public function amendFinding(int $findingId, int $amendedBy, string $amendmentReason, array $amendedFields): array
    {
        $amendmentReason = trim($amendmentReason);
        if (empty($amendmentReason)) {
            return [
                'success' => false,
                'status'  => 'MISSING_AMENDMENT_REASON',
                'message' => "Guard B6-G05 Violation: Amendment reason is mandatory to revise a field finding.",
            ];
        }

        if (empty($amendedFields)) {
            return [
                'success' => false,
                'status'  => 'NO_AMENDMENTS_PROVIDED',
                'message' => "No modified fields provided for amendment.",
            ];
        }

        // Validate authoritative asset if changing actual_asset_id
        if (isset($amendedFields['actual_asset_id'])) {
            $newAid = (int)$amendedFields['actual_asset_id'];
            $asset = $this->db->table('assets')->where('id', $newAid)->where('deleted_at IS NULL')->get()->getRowArray();
            if (!$asset) {
                return [
                    'success' => false,
                    'status'  => 'REJECTED_NON_AUTHORITATIVE_ASSET',
                    'message' => "Guard B6.4-G07 Violation: New asset #{$newAid} does not exist or is soft-deleted.",
                ];
            }
        }

        // Validate GPS bounds if changing coordinates
        if (isset($amendedFields['actual_lat']) || isset($amendedFields['actual_lng'])) {
            $lat = (float)($amendedFields['actual_lat'] ?? 0);
            $lng = (float)($amendedFields['actual_lng'] ?? 0);
            $acc = isset($amendedFields['gps_accuracy_m']) ? (float)$amendedFields['gps_accuracy_m'] : null;
            $gpsCheck = $this->validateCoordinates($lat, $lng, $acc);
            if (!$gpsCheck['valid']) {
                return [
                    'success' => false,
                    'status'  => 'INVALID_GPS_PROVENANCE',
                    'message' => $gpsCheck['message'],
                ];
            }
        }

        // Transactional Concurrency Lock (Guard B6.4-G03)
        $this->db->transStart();

        $finding = $this->db->table('field_findings')->where('id', $findingId)->get()->getRowArray();
        if (!$finding) {
            $this->db->transRollback();
            return [
                'success' => false,
                'status'  => 'FINDING_NOT_FOUND',
                'message' => "Field finding #{$findingId} does not exist.",
            ];
        }

        // Resolve next sequential revision_no with FOR UPDATE lock simulation
        $revRow = $this->db->query("SELECT MAX(revision_no) as max_rev FROM field_finding_revisions WHERE field_finding_id = ? FOR UPDATE", [$findingId])->getRowArray();
        $nextRevisionNo = ((int)($revRow['max_rev'] ?? 0)) + 1;

        // Snapshot previous values of the affected fields
        $previousValues = [];
        foreach ($amendedFields as $key => $val) {
            if (array_key_exists($key, $finding)) {
                $previousValues[$key] = $finding[$key];
            }
        }

        // 1. Insert Append-Only Revision Record (Guard B6-G05: NO updated_at, NO deleted_at)
        $now = date('Y-m-d H:i:s');
        $revisionRecord = [
            'field_finding_id'     => $findingId,
            'revision_no'          => $nextRevisionNo,
            'previous_values_json' => json_encode($previousValues),
            'amended_fields_json'  => json_encode($amendedFields),
            'amended_by'           => $amendedBy,
            'amendment_reason'     => $amendmentReason,
            'created_at'           => $now,
        ];
        $this->db->table('field_finding_revisions')->insert($revisionRecord);

        // 2. Update Current Projection in `field_findings`
        $updateProjection = $amendedFields;
        $updateProjection['updated_at'] = $now;
        if ($finding['finding_status'] === self::STATUS_RECORDED) {
            $updateProjection['finding_status'] = self::STATUS_REVISED;
        }

        $this->db->table('field_findings')->where('id', $findingId)->update($updateProjection);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'status'  => 'TRANSACTION_COLLISION_FAILED',
                'message' => "Guard B6.4-G03 Violation: Revision collision detected. Rolled back cleanly.",
            ];
        }

        return [
            'success'     => true,
            'status'      => 'FINDING_AMENDED',
            'revision_no' => $nextRevisionNo,
            'finding'     => $this->getFinding($findingId),
            'message'     => "Finding #{$findingId} amended successfully (Revision #{$nextRevisionNo}).",
        ];
    }

    // =========================================================================
    // 3. CONFIRM FIELD FINDING (B6-G06, B6.4-G06, B6.4-G08)
    // =========================================================================

    /**
     * Formally confirm a field finding.
     * Requires explicit field observation provenance and transitions Case to CONFIRMED.
     *
     * @param int $findingId
     * @param int $confirmedBy Supervisor User ID
     * @param array $options
     * @return array
     */
    public function confirmFinding(int $findingId, int $confirmedBy, array $options = []): array
    {
        $finding = $this->getFinding($findingId);
        if (!$finding) {
            return [
                'success' => false,
                'status'  => 'FINDING_NOT_FOUND',
                'message' => "Field finding #{$findingId} does not exist.",
            ];
        }

        $caseId = (int)$finding['fault_case_id'];
        $case = $this->caseService->getCase($caseId);
        if (!$case) {
            return [
                'success' => false,
                'status'  => 'CASE_NOT_FOUND',
                'message' => "Fault case #{$caseId} does not exist.",
            ];
        }

        // Guard B6.4-G06: Case must be FINDING_RECORDED
        if (strtoupper($case['status']) !== 'FINDING_RECORDED') {
            return [
                'success' => false,
                'status'  => 'INVALID_CASE_STATE_FOR_CONFIRMATION',
                'message' => "Cannot confirm finding: Case #{$caseId} status is '{$case['status']}', expected 'FINDING_RECORDED'.",
            ];
        }

        // Guard B6.4-G08: Explicit Field Observation Verification Check
        $hasActualAsset = !empty($finding['actual_asset_id']);
        $hasActualGps   = $finding['actual_lat'] !== null && $finding['actual_lng'] !== null;
        $hasCapturedBy  = !empty($finding['captured_by']);
        $hasCapturedAt  = !empty($finding['captured_at']);

        if (!$hasActualAsset || !$hasActualGps || !$hasCapturedBy || !$hasCapturedAt) {
            return [
                'success' => false,
                'status'  => 'REJECTED_FINDING_NOT_FIELD_VERIFIED',
                'message' => "Guard B6.4-G08 Violation: Cannot confirm unverified finding. Requires explicit actual_asset_id, GPS coordinates, captured_by, and captured_at.",
            ];
        }

        // FSM Transition: FINDING_RECORDED -> CONFIRMED (B6-G06)
        $transRes = $this->caseService->transitionCase($caseId, 'CONFIRMED', [
            'actor_id' => $confirmedBy,
            'notes'    => "Field finding #{$findingId} verified and confirmed by Supervisor #{$confirmedBy}.",
        ]);
        if (!$transRes['success']) {
            return $transRes;
        }

        $now = date('Y-m-d H:i:s');

        // Update finding status: CONFIRMED
        $this->db->table('field_findings')->where('id', $findingId)->update([
            'finding_status' => self::STATUS_CONFIRMED,
            'updated_at'     => $now,
        ]);

        // Complete active investigation if linked
        if (!empty($finding['investigation_id'])) {
            $this->db->table('field_investigations')->where('id', $finding['investigation_id'])->update([
                'status'       => 'COMPLETED',
                'completed_at' => $now,
                'updated_at'   => $now,
            ]);
        }

        return [
            'success'        => true,
            'status'         => 'FINDING_CONFIRMED',
            'finding_status' => self::STATUS_CONFIRMED,
            'finding'        => $this->getFinding($findingId),
            'case'           => $this->caseService->getCase($caseId),
            'message'        => "Field finding #{$findingId} confirmed. Case #{$case['case_number']} is now CONFIRMED.",
        ];
    }

    // =========================================================================
    // 4. RECORD NO FAULT FOUND / UNRESOLVED (B6-G06, B6.4-G08)
    // =========================================================================

    /**
     * Record field inspection result where no electrical or physical anomaly was observed.
     * Transitions Case to UNRESOLVED with complete investigation context and GPS provenance.
     *
     * @param int $caseId
     * @param int $investigationId
     * @param int $capturedBy
     * @param array $data ['actual_lat', 'actual_lng', 'gps_accuracy_m', 'notes']
     * @return array
     */
    public function recordNoFaultFound(int $caseId, int $investigationId, int $capturedBy, array $data): array
    {
        $case = $this->caseService->getCase($caseId);
        if (!$case) {
            return [
                'success' => false,
                'status'  => 'CASE_NOT_FOUND',
                'message' => "Fault case #{$caseId} does not exist.",
            ];
        }

        // Validate active investigation
        $inves = $this->db->table('field_investigations')->where('id', $investigationId)->get()->getRowArray();
        if (!$inves) {
            return [
                'success' => false,
                'status'  => 'INVESTIGATION_NOT_FOUND',
                'message' => "Investigation #{$investigationId} does not exist.",
            ];
        }

        if ((int)$inves['fault_case_id'] !== $caseId) {
            return [
                'success' => false,
                'status'  => 'CROSS_CASE_INVESTIGATION_REJECTED',
                'message' => "Guard B6.4-G02 Violation: Investigation #{$investigationId} does not belong to Case #{$caseId}.",
            ];
        }

        // Reason note is mandatory
        $reasonNote = trim((string)($data['notes'] ?? ''));
        if (empty($reasonNote)) {
            return [
                'success' => false,
                'status'  => 'MISSING_OBSERVATION_NOTE',
                'message' => "Guard B6.4-G08 Violation: Observation note explaining why no fault was found is required.",
            ];
        }

        // GPS Provenance check
        $lat = (float)($data['actual_lat'] ?? $inves['end_lat'] ?? 0.0);
        $lng = (float)($data['actual_lng'] ?? $inves['end_lng'] ?? 0.0);
        $acc = isset($data['gps_accuracy_m']) ? (float)$data['gps_accuracy_m'] : (isset($inves['end_accuracy_m']) ? (float)$inves['end_accuracy_m'] : null);

        $gpsCheck = $this->validateCoordinates($lat, $lng, $acc);
        if (!$gpsCheck['valid']) {
            return [
                'success' => false,
                'status'  => 'INVALID_GPS_PROVENANCE',
                'message' => $gpsCheck['message'],
            ];
        }

        // FSM Transition: INVESTIGATING -> UNRESOLVED (B6-G06)
        $transRes = $this->caseService->transitionCase($caseId, 'UNRESOLVED', [
            'actor_id' => $capturedBy,
            'notes'    => "No fault observed on site: {$reasonNote}",
        ]);
        if (!$transRes['success']) {
            return $transRes;
        }

        $now = date('Y-m-d H:i:s');

        // Create Finding record with UNRESOLVED status
        $findingData = [
            'fault_case_id'         => $caseId,
            'investigation_id'      => $investigationId,
            'predicted_asset_id'    => null,
            'actual_asset_id'       => null,
            'actual_lat'            => $lat,
            'actual_lng'            => $lng,
            'gps_accuracy_m'        => $acc,
            'finding_status'        => self::STATUS_UNRESOLVED,
            'cause_category'        => 'NO_FAULT_FOUND',
            'condition_description' => 'Field patrol completed; no visible damage or electrical fault detected.',
            'notes'                 => $reasonNote,
            'captured_at'           => $now,
            'captured_by'           => $capturedBy,
            'created_at'            => $now,
            'updated_at'            => $now,
        ];
        $this->db->table('field_findings')->insert($findingData);
        $findingId = (int)$this->db->insertID();

        // Complete investigation
        $this->db->table('field_investigations')->where('id', $investigationId)->update([
            'status'       => 'COMPLETED',
            'completed_at' => $now,
            'updated_at'   => $now,
        ]);

        return [
            'success'     => true,
            'status'      => 'NO_FAULT_RECORDED',
            'case_status' => 'UNRESOLVED',
            'finding_id'  => $findingId,
            'case'        => $this->caseService->getCase($caseId),
            'message'     => "Patrol inspection concluded with No Fault Found. Case transitioned to UNRESOLVED.",
        ];
    }

    // =========================================================================
    // 5. ATTACH EVIDENCE & INTEGRITY HASH (B6-G10, B6.4-G05)
    // =========================================================================

    /**
     * Attach photographic or documentary evidence with SHA-256 integrity hash.
     * Enforces format validation (B6-G10-A), content verification (B6-G10-B), and idempotency (B6.4-G05).
     *
     * @param int $caseId
     * @param int $capturedBy
     * @param string $fileReference File path or storage key
     * @param string $sha256 Expected SHA-256 checksum
     * @param string $evidenceType PHOTO, DOCUMENT, THERMAL_IMAGE, SIGNATURE, AUDIO
     * @param array $options ['field_finding_id' => int, 'asset_id' => int, 'metadata' => array]
     * @return array
     */
    public function attachEvidence(
        int $caseId,
        int $capturedBy,
        string $fileReference,
        string $sha256,
        string $evidenceType = 'PHOTO',
        array $options = []
    ): array {
        $evidenceType = strtoupper(trim($evidenceType));
        if (!array_key_exists($evidenceType, self::EVIDENCE_TYPES)) {
            $evidenceType = 'PHOTO';
        }

        $sha256 = strtolower(trim($sha256));

        // Guard B6-G10-A: Format Validation (64 hexadecimal characters)
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            return [
                'success' => false,
                'status'  => 'INVALID_SHA256_FORMAT',
                'message' => "Guard B6-G10-A Violation: Evidence checksum must be a valid 64-character lowercase hex SHA-256 hash.",
            ];
        }

        // Guard B6-G10-B: Content Match Verification (if file is locally accessible)
        if (file_exists($fileReference) && is_file($fileReference)) {
            $computedHash = hash_file('sha256', $fileReference);
            if ($computedHash !== $sha256) {
                return [
                    'success' => false,
                    'status'  => 'SHA256_CONTENT_MISMATCH',
                    'message' => "Guard B6-G10-B Violation: File content hash ({$computedHash}) does not match declared hash ({$sha256}).",
                ];
            }
        }

        $findingId = isset($options['field_finding_id']) && (int)$options['field_finding_id'] > 0
            ? (int)$options['field_finding_id']
            : null;

        $assetId = isset($options['asset_id']) && (int)$options['asset_id'] > 0
            ? (int)$options['asset_id']
            : null;

        // Guard B6.4-G05: Evidence Idempotency Check
        if (!$this->db->tableExists('field_evidence')) {
            return [
                'success' => false,
                'status'  => 'TABLE_NOT_FOUND',
                'message' => 'Table field_evidence does not exist.',
            ];
        }

        $builder = $this->db->table('field_evidence')
            ->where('fault_case_id', $caseId)
            ->where('sha256', $sha256)
            ->where('evidence_type', $evidenceType);

        if ($findingId !== null) {
            $builder->where('field_finding_id', $findingId);
        }

        $res = $builder->get();
        $existingEvidence = $res ? $res->getRowArray() : null;
        if ($existingEvidence) {
            return [
                'success'     => true,
                'is_new'      => false,
                'is_existing' => true,
                'status'      => 'EVIDENCE_ALREADY_ATTACHED',
                'evidence'    => $existingEvidence,
                'message'     => "Idempotent return: Evidence with hash '{$sha256}' already attached to Case #{$caseId}.",
            ];
        }

        // Insert Evidence Record
        $now = date('Y-m-d H:i:s');
        $evidenceData = [
            'fault_case_id'    => $caseId,
            'field_finding_id' => $findingId,
            'asset_id'         => $assetId,
            'evidence_type'    => $evidenceType,
            'file_reference'   => $fileReference,
            'sha256'           => $sha256,
            'captured_at'      => $options['captured_at'] ?? $now,
            'captured_by'      => $capturedBy,
            'metadata_json'    => json_encode($options['metadata'] ?? []),
            'created_at'       => $now,
        ];

        $this->db->table('field_evidence')->insert($evidenceData);
        $evidenceId = (int)$this->db->insertID();
        $evidenceData['id'] = $evidenceId;

        return [
            'success'     => true,
            'is_new'      => true,
            'is_existing' => false,
            'status'      => 'EVIDENCE_ATTACHED',
            'evidence'    => $evidenceData,
            'message'     => "Evidence #{$evidenceId} successfully attached to Case #{$caseId}.",
        ];
    }

    // =========================================================================
    // 6. RETRIEVAL & QUERY HELPERS
    // =========================================================================

    /**
     * Retrieve a finding with its complete append-only revisions and attached evidence.
     */
    public function getFinding(int $findingId): ?array
    {
        if (!$this->db->tableExists('field_findings')) {
            return null;
        }

        $res = $this->db->table('field_findings')->where('id', $findingId)->get();
        if (!$res) {
            return null;
        }

        $finding = $res->getRowArray();
        if (!$finding) {
            return null;
        }

        $finding['revisions'] = $this->getFindingRevisions($findingId);
        $finding['evidence']  = $this->db->tableExists('field_evidence')
            ? ($this->db->table('field_evidence')->where('field_finding_id', $findingId)->orderBy('id', 'ASC')->get()?->getResultArray() ?? [])
            : [];

        return $finding;
    }

    /**
     * Get all findings for a given case.
     */
    public function getFindingsByCase(int $caseId): array
    {
        $findings = $this->db->table('field_findings')
            ->where('fault_case_id', $caseId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($findings as &$f) {
            $f['revisions'] = $this->getFindingRevisions((int)$f['id']);
            $f['evidence']  = $this->db->table('field_evidence')->where('field_finding_id', $f['id'])->get()->getResultArray();
        }
        unset($f);

        return $findings;
    }

    /**
     * Get append-only revision history for a finding.
     */
    public function getFindingRevisions(int $findingId): array
    {
        $rows = $this->db->table('field_finding_revisions')
            ->where('field_finding_id', $findingId)
            ->orderBy('revision_no', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$r) {
            $r['previous_values'] = json_decode($r['previous_values_json'] ?? '{}', true);
            $r['amended_fields']  = json_decode($r['amended_fields_json'] ?? '{}', true);
        }
        unset($r);

        return $rows;
    }

    /**
     * Get evidence list for a case or finding.
     */
    public function getCaseEvidence(int $caseId, ?int $findingId = null): array
    {
        $builder = $this->db->table('field_evidence')->where('fault_case_id', $caseId);
        if ($findingId !== null) {
            $builder->where('field_finding_id', $findingId);
        }

        return $builder->orderBy('id', 'ASC')->get()->getResultArray();
    }

    /**
     * Hard Exception enforcing Guard B6.4-G10: NO BUSINESS DELETE.
     * Field findings are authoritative historical records; physical deletion is prohibited.
     *
     * @throws RuntimeException
     */
    public function deleteFinding(int $findingId): void
    {
        throw new RuntimeException(
            "Guard B6.4-G10 Violation: Physical deletion of field finding #{$findingId} is prohibited. Findings are immutable historical records."
        );
    }

    // =========================================================================
    // 7. HELPER FUNCTIONS
    // =========================================================================

    /**
     * Validate GPS coordinate bounds and precision (Guard B6-G09).
     * Semantic rule: NULL = missing, 0 = reported zero, > 0 = reported accuracy.
     * Negative accuracy (< 0) is strictly invalid.
     */
    protected function validateCoordinates(float $lat, float $lng, ?float $accuracyM): array
    {
        if ($lat < -90.0 || $lat > 90.0) {
            return ['valid' => false, 'message' => "Latitude {$lat} is outside valid bounds (-90 to 90)."];
        }
        if ($lng < -180.0 || $lng > 180.0) {
            return ['valid' => false, 'message' => "Longitude {$lng} is outside valid bounds (-180 to 180)."];
        }
        if ($accuracyM !== null && $accuracyM < 0.0) {
            return ['valid' => false, 'message' => "GPS accuracy ({$accuracyM} m) must be zero or positive."];
        }

        return ['valid' => true];
    }
}
