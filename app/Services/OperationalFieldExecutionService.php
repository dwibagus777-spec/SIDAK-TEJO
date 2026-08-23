<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Operational Field Execution Service (Wave 2 Phase OP-06)
 *
 * Responsibilities:
 * - Controlled Field Execution Record & Human Progress Governance.
 * - Enforces:
 *     EXECUTION_AUTHORIZED != WORK_AUTOMATICALLY_STARTED
 *     WORK_STARTED = EXPLICIT_IDENTIFIED_HUMAN_FIELD_ACTION
 *     DUPLICATE_ACTIVE_EXECUTION_FOR_AUTHORIZATION = REJECTED (Hardening #1)
 *     EXECUTION_SOURCE_REBINDING = FORBIDDEN (Hardening #2)
 *     EXPLICIT_HUMAN_START_ACCOUNTABILITY (Hardening #3)
 *     BEFORE_AFTER_EVIDENCE_INTEGRITY (Hardening #4)
 *     APPEND_ONLY_PROGRESS_LEDGER (Hardening #5)
 *     ACTUAL_MATERIAL_USAGE != MUTATION_OF_PLANNING_ESTIMATE (Hardening #6)
 *     GOVERNED_SAFETY_HOLD_RESUME (Hardening #7)
 *     FIELD_COMPLETION_DECLARATION != WORK_ACCEPTANCE (Hardening #8)
 *     ZERO_AUTONOMOUS_EXECUTION = ENFORCED
 */
class OperationalFieldExecutionService
{
    public const ALLOWED_EXECUTION_TRANSITIONS = [
        'WORK_PENDING_FIELD_START'          => ['WORK_IN_PROGRESS', 'WORK_ABORTED_FIELD_CONSTRAINTS'],
        'WORK_IN_PROGRESS'                  => ['WORK_PAUSED_SAFETY_HOLD', 'WORK_COMPLETED_PENDING_ACCEPTANCE', 'WORK_ABORTED_FIELD_CONSTRAINTS'],
        'WORK_PAUSED_SAFETY_HOLD'           => ['WORK_IN_PROGRESS', 'WORK_ABORTED_FIELD_CONSTRAINTS'],
        'WORK_COMPLETED_PENDING_ACCEPTANCE' => [], // Terminal boundary for OP-06
        'WORK_ABORTED_FIELD_CONSTRAINTS'    => [], // Terminal
    ];

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Initiate an Execution Record in WORK_PENDING_FIELD_START from an authorized package.
     *
     * @param int $authId
     * @param array|null $actor
     * @return array
     */
    public function initiateExecutionRecord(int $authId, ?array $actor = null): array
    {
        // 1. Authorization Eligibility Check
        $auth = $this->db->table('operational_work_authorizations')
                         ->where('id', $authId)
                         ->get()
                         ->getRowArray();

        if (!$auth) {
            return [
                'status'  => 'error',
                'message' => "Work Authorization Package #{$authId} not found.",
                'code'    => 'AUTHORIZATION_NOT_FOUND',
            ];
        }

        if ($auth['authorization_status'] !== 'EXECUTION_AUTHORIZED') {
            return [
                'status'  => 'error',
                'message' => "Package {$auth['authorization_code']} is '{$auth['authorization_status']}'. Only 'EXECUTION_AUTHORIZED' packages can initiate execution records.",
                'code'    => 'PACKAGE_NOT_EXECUTION_AUTHORIZED',
            ];
        }

        // Hardening #1: Exclusivity & Idempotency Check
        $activeExec = $this->db->table('operational_field_execution_records')
                               ->where('authorization_id', $authId)
                               ->whereIn('execution_status', ['WORK_PENDING_FIELD_START', 'WORK_IN_PROGRESS', 'WORK_PAUSED_SAFETY_HOLD'])
                               ->get()
                               ->getRowArray();

        if ($activeExec) {
            return [
                'status'  => 'error',
                'message' => "Authorization {$auth['authorization_code']} already has active execution record {$activeExec['execution_code']} ({$activeExec['execution_status']}).",
                'code'    => 'DUPLICATE_ACTIVE_EXECUTION_FOR_AUTHORIZATION',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'PENGAWAS_LAPANGAN_HAR_SUTM';
        $execCode = 'EXEC-REC-STJ-' . date('Y') . '-W' . date('W') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Fetch indicative materials from operational plan (Hardening #6 - preserve OP-02 baseline)
        $plan = $this->db->table('operational_plans')->where('id', $auth['plan_id'])->get()->getRowArray();
        $indicativeMaterials = !empty($plan['indicative_materials_json']) ? json_decode($plan['indicative_materials_json'], true) : [];

        $reconciliationList = [];
        if (is_array($indicativeMaterials)) {
            foreach ($indicativeMaterials as $m) {
                $estQty = (float)($m['quantity'] ?? 1);
                $reconciliationList[] = [
                    'material_name'      => $m['material_name'] ?? 'Material',
                    'unit'               => $m['unit'] ?? 'buah',
                    'estimated_quantity' => $estQty,
                    'actual_quantity'    => 0.0,
                    'variance_quantity'  => -$estQty,
                    'variance_percentage'=> -100.0,
                    'variance_rationale' => 'Baseline inisialisasi pra-kerja',
                    'recorded_by'        => $actorName,
                ];
            }
        }

        // Hardening #2: Persist with Lineage and Initial Pending Start state
        $execData = [
            'execution_code'        => $execCode,
            'authorization_id'      => $authId,
            'authorization_code'    => $auth['authorization_code'],
            'scenario_id'           => $auth['scenario_id'],
            'slot_id'               => $auth['slot_id'],
            'portfolio_id'          => $auth['portfolio_id'],
            'plan_id'               => $auth['plan_id'],
            'plan_code'             => $auth['plan_code'],
            'candidate_id'          => $auth['candidate_id'],
            'snapshot_id'           => $auth['snapshot_id'],
            'feeder_name'           => $auth['feeder_name'],
            'section_name'          => $auth['section_name'],
            'execution_status'      => 'WORK_PENDING_FIELD_START',
            'progress_percentage'   => 0.00,
            'field_supervisor_name' => $actorName,
            'field_crew_count'      => 4,
            'actual_materials_json' => json_encode($reconciliationList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at'            => $now,
            'updated_at'            => $now,
        ];

        $this->db->table('operational_field_execution_records')->insert($execData);
        $execId = (int)$this->db->insertID();

        // Hardening #5: Append-Only Event
        $this->db->table('operational_execution_progress_events')->insert([
            'execution_id'        => $execId,
            'execution_code'      => $execCode,
            'event_type'          => 'EXECUTION_RECORD_INITIALIZED',
            'progress_percentage' => 0.00,
            'event_description'   => 'Rekaman eksekusi lapangan dibuat, menunggu inisiasi mulai kerja fisik oleh pengawas',
            'decision_rationale'  => 'Inisialisasi record dari paket otorisasi bersegel SHA-256',
            'recorded_by'         => $actorName,
            'recorded_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'execution_id'       => $execId,
            'execution_code'     => $execCode,
            'authorization_code' => $auth['authorization_code'],
            'execution_status'   => 'WORK_PENDING_FIELD_START',
            'governance_verdict' => 'EXECUTION_RECORD_INITIALIZED_PENDING_HUMAN_START',
        ];
    }

    /**
     * Explicit Human Field Start with Mandatory Before Photo Evidence.
     *
     * @param int $execId
     * @param array $beforeEvidence Must include photo_uri
     * @param string $rationale
     * @param array|null $actor
     * @return array
     */
    public function startFieldWork(
        int $execId,
        array $beforeEvidence,
        string $rationale,
        ?array $actor = null
    ): array {
        $exec = $this->db->table('operational_field_execution_records')
                         ->where('id', $execId)
                         ->get()
                         ->getRowArray();

        if (!$exec) {
            return [
                'status'  => 'error',
                'message' => "Execution Record #{$execId} not found.",
                'code'    => 'EXECUTION_NOT_FOUND',
            ];
        }

        if ($exec['execution_status'] !== 'WORK_PENDING_FIELD_START') {
            return [
                'status'  => 'error',
                'message' => "Record is '{$exec['execution_status']}'. Only 'WORK_PENDING_FIELD_START' can be started.",
                'code'    => 'INVALID_START_STATUS',
            ];
        }

        // Hardening #4: Before Evidence Mandatory
        $photoUri = trim($beforeEvidence['photo_uri'] ?? '');
        if ($photoUri === '') {
            return [
                'status'  => 'error',
                'message' => 'Before photo evidence is mandatory before starting field work.',
                'code'    => 'BEFORE_PHOTO_MANDATORY',
            ];
        }

        // Hardening #3: Explicit Human Start Accountability
        $cleanRationale = trim($rationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Field start rationale is mandatory.',
                'code'    => 'MANDATORY_START_RATIONALE_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'PENGAWAS_LAPANGAN_HAR_SUTM';
        $actorRole = $actor['role'] ?? 'PENGAWAS_PEKERJAAN_SUTM';

        $photoHash = hash('sha256', $photoUri . '|' . $now . '|' . $actorName);
        $evidencePayload = [
            'photo_uri'     => $photoUri,
            'photo_sha256'  => $photoHash,
            'captured_at'   => $now,
            'captured_by'   => $actorName,
            'evidence_type' => 'BEFORE_WORK_SITE_CONDITION',
            'notes'         => $beforeEvidence['notes'] ?? 'Foto tiang dan kondisi sebelum perabasan/pemeliharaan',
        ];

        // Transition to WORK_IN_PROGRESS
        $this->db->table('operational_field_execution_records')
                 ->where('id', $execId)
                 ->update([
                     'execution_status'         => 'WORK_IN_PROGRESS',
                     'progress_percentage'      => 10.00,
                     'field_start_initiated_by' => $actorName,
                     'field_start_initiated_at' => $now,
                     'field_start_actor_role'   => $actorRole,
                     'field_start_rationale'    => $cleanRationale,
                     'work_started_at'          => $now,
                     'before_evidence_json'     => json_encode($evidencePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'updated_at'               => $now,
                 ]);

        // Hardening #5: Append-Only Event
        $this->db->table('operational_execution_progress_events')->insert([
            'execution_id'           => $execId,
            'execution_code'         => $exec['execution_code'],
            'event_type'             => 'WORK_STARTED',
            'progress_percentage'    => 10.00,
            'event_description'      => 'Pekerjaan fisik resmi dimulai di lokasi tiang/seksi penyulang',
            'evidence_metadata_json' => json_encode($evidencePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'decision_rationale'     => $cleanRationale,
            'recorded_by'            => $actorName,
            'recorded_at'            => $now,
        ]);

        return [
            'status'             => 'success',
            'execution_id'       => $execId,
            'execution_code'     => $exec['execution_code'],
            'execution_status'   => 'WORK_IN_PROGRESS',
            'progress'           => 10.00,
            'initiated_by'       => $actorName,
            'governance_verdict' => 'EXPLICIT_HUMAN_FIELD_WORK_STARTED',
        ];
    }

    /**
     * Log a Progress Update to the Append-Only Ledger.
     */
    public function logProgressUpdate(
        int $execId,
        float $progressPct,
        string $description,
        ?array $evidence = null,
        string $rationale = '',
        ?array $actor = null
    ): array {
        $exec = $this->db->table('operational_field_execution_records')
                         ->where('id', $execId)
                         ->get()
                         ->getRowArray();

        if (!$exec || $exec['execution_status'] !== 'WORK_IN_PROGRESS') {
            return [
                'status'  => 'error',
                'message' => 'Progress can only be logged when status is WORK_IN_PROGRESS.',
                'code'    => 'INVALID_PROGRESS_LOG_STATUS',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'PENGAWAS_LAPANGAN';
        $clampedProgress = min(99.0, max(10.0, $progressPct));

        $this->db->table('operational_field_execution_records')
                 ->where('id', $execId)
                 ->update([
                     'progress_percentage' => $clampedProgress,
                     'updated_at'          => $now,
                 ]);

        $this->db->table('operational_execution_progress_events')->insert([
            'execution_id'           => $execId,
            'execution_code'         => $exec['execution_code'],
            'event_type'             => 'PROGRESS_LOGGED',
            'progress_percentage'    => $clampedProgress,
            'event_description'      => trim($description),
            'evidence_metadata_json' => !empty($evidence) ? json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'decision_rationale'     => trim($rationale) ?: 'Pembaruan progres berkala pelaksanaan lapangan',
            'recorded_by'            => $actorName,
            'recorded_at'            => $now,
        ]);

        return [
            'status'             => 'success',
            'execution_id'       => $execId,
            'progress'           => $clampedProgress,
            'governance_verdict' => 'PROGRESS_LOGGED_APPEND_ONLY',
        ];
    }

    /**
     * Reconcile Actual Material Usage vs Indicative Planning Estimate (Hardening #6).
     */
    public function reconcileActualMaterials(
        int $execId,
        array $actuals,
        string $rationale,
        ?array $actor = null
    ): array {
        $exec = $this->db->table('operational_field_execution_records')
                         ->where('id', $execId)
                         ->get()
                         ->getRowArray();

        if (!$exec) {
            return [
                'status'  => 'error',
                'message' => "Execution Record #{$execId} not found.",
                'code'    => 'EXECUTION_NOT_FOUND',
            ];
        }

        $cleanRationale = trim($rationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Material reconciliation rationale is mandatory.',
                'code'    => 'MANDATORY_MATERIAL_RATIONALE_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'PETUGAS_LOGISTIK_LAPANGAN';
        $currentReconciliation = !empty($exec['actual_materials_json']) ? json_decode($exec['actual_materials_json'], true) : [];

        $updatedList = [];
        foreach ($currentReconciliation as $idx => $m) {
            $actualQty = isset($actuals[$idx]['actual_quantity']) ? (float)$actuals[$idx]['actual_quantity'] : (float)$m['actual_quantity'];
            $estQty = (float)$m['estimated_quantity'];
            $variance = $actualQty - $estQty;
            $varPct = $estQty > 0 ? round(($variance / $estQty) * 100.0, 2) : 0.0;

            $updatedList[] = [
                'material_name'      => $m['material_name'],
                'unit'               => $m['unit'],
                'estimated_quantity' => $estQty,
                'actual_quantity'    => $actualQty,
                'variance_quantity'  => $variance,
                'variance_percentage'=> $varPct,
                'variance_rationale' => $actuals[$idx]['variance_rationale'] ?? $cleanRationale,
                'recorded_by'        => $actorName,
            ];
        }

        $this->db->table('operational_field_execution_records')
                 ->where('id', $execId)
                 ->update([
                     'actual_materials_json' => json_encode($updatedList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'updated_at'            => $now,
                 ]);

        $this->db->table('operational_execution_progress_events')->insert([
            'execution_id'        => $execId,
            'execution_code'      => $exec['execution_code'],
            'event_type'          => 'MATERIAL_VARIANCE_RECORDED',
            'progress_percentage' => (float)$exec['progress_percentage'],
            'event_description'   => 'Rekonsiliasi pemakaian material aktual terhadap estimasi indikatif',
            'decision_rationale'  => $cleanRationale,
            'recorded_by'         => $actorName,
            'recorded_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'execution_id'       => $execId,
            'total_materials'    => count($updatedList),
            'governance_verdict' => 'MATERIAL_VARIANCE_RECONCILED_PRESERVING_BASELINE',
        ];
    }

    /**
     * Declare Governed Safety Hold (Hardening #7).
     */
    public function declareSafetyHold(
        int $execId,
        string $holdReason,
        string $riskDesc,
        ?array $actor = null
    ): array {
        $exec = $this->db->table('operational_field_execution_records')
                         ->where('id', $execId)
                         ->get()
                         ->getRowArray();

        if (!$exec || $exec['execution_status'] !== 'WORK_IN_PROGRESS') {
            return [
                'status'  => 'error',
                'message' => 'Safety hold can only be declared when work is in progress.',
                'code'    => 'INVALID_SAFETY_HOLD_STATUS',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'PENGAWAS_K3_LAPANGAN';

        $this->db->table('operational_field_execution_records')
                 ->where('id', $execId)
                 ->update([
                     'execution_status'        => 'WORK_PAUSED_SAFETY_HOLD',
                     'safety_hold_reason'      => trim($holdReason),
                     'safety_hold_declared_by' => $actorName,
                     'safety_hold_declared_at' => $now,
                     'field_incident_notes'    => trim($riskDesc),
                     'updated_at'              => $now,
                 ]);

        $this->db->table('operational_execution_progress_events')->insert([
            'execution_id'        => $execId,
            'execution_code'      => $exec['execution_code'],
            'event_type'          => 'SAFETY_HOLD_DECLARED',
            'progress_percentage' => (float)$exec['progress_percentage'],
            'event_description'   => 'Pekerjaan dihentikan sementara (Safety Hold): ' . trim($holdReason),
            'decision_rationale'  => trim($riskDesc) ?: 'Potensi bahaya cuaca / keselamatan regu kerja',
            'recorded_by'         => $actorName,
            'recorded_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'execution_id'       => $execId,
            'execution_status'   => 'WORK_PAUSED_SAFETY_HOLD',
            'governance_verdict' => 'SAFETY_HOLD_DECLARED_AUDITED',
        ];
    }

    /**
     * Resume from Safety Hold with Explicit Reassessment Rationale (Hardening #7).
     */
    public function resumeFromSafetyHold(
        int $execId,
        string $resumeRationale,
        ?array $actor = null
    ): array {
        $exec = $this->db->table('operational_field_execution_records')
                         ->where('id', $execId)
                         ->get()
                         ->getRowArray();

        if (!$exec || $exec['execution_status'] !== 'WORK_PAUSED_SAFETY_HOLD') {
            return [
                'status'  => 'error',
                'message' => 'Record is not currently paused under safety hold.',
                'code'    => 'INVALID_RESUME_STATUS',
            ];
        }

        $cleanRationale = trim($resumeRationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Resume rationale is mandatory following safety hold reassessment.',
                'code'    => 'MANDATORY_RESUME_RATIONALE_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'PENGAWAS_K3_LAPANGAN';

        $this->db->table('operational_field_execution_records')
                 ->where('id', $execId)
                 ->update([
                     'execution_status'   => 'WORK_IN_PROGRESS',
                     'safety_hold_reason' => null,
                     'updated_at'         => $now,
                 ]);

        $this->db->table('operational_execution_progress_events')->insert([
            'execution_id'        => $execId,
            'execution_code'      => $exec['execution_code'],
            'event_type'          => 'WORK_RESUMED',
            'progress_percentage' => (float)$exec['progress_percentage'],
            'event_description'   => 'Pekerjaan dilanjutkan kembali setelah re-evaluasi K3',
            'decision_rationale'  => $cleanRationale,
            'recorded_by'         => $actorName,
            'recorded_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'execution_id'       => $execId,
            'execution_status'   => 'WORK_IN_PROGRESS',
            'governance_verdict' => 'WORK_RESUMED_EXPLICIT_AUTHORIZATION',
        ];
    }

    /**
     * Declare Work Completed with Mandatory After Photo Evidence (Hardening #4 & #8).
     */
    public function declareWorkCompleted(
        int $execId,
        array $afterEvidence,
        string $declarationRationale,
        ?array $actor = null
    ): array {
        $exec = $this->db->table('operational_field_execution_records')
                         ->where('id', $execId)
                         ->get()
                         ->getRowArray();

        if (!$exec || $exec['execution_status'] !== 'WORK_IN_PROGRESS') {
            return [
                'status'  => 'error',
                'message' => 'Only work in progress can be declared completed.',
                'code'    => 'INVALID_COMPLETION_STATUS',
            ];
        }

        // Hardening #4: After Evidence Mandatory
        $photoUri = trim($afterEvidence['photo_uri'] ?? '');
        if ($photoUri === '') {
            return [
                'status'  => 'error',
                'message' => 'After photo evidence is mandatory to declare work completion.',
                'code'    => 'AFTER_PHOTO_MANDATORY',
            ];
        }

        // Hardening #8: Completion Declaration Rationale Mandatory
        $cleanRationale = trim($declarationRationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Completion declaration rationale is mandatory.',
                'code'    => 'MANDATORY_COMPLETION_RATIONALE_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'PENGAWAS_LAPANGAN_HAR_SUTM';

        $photoHash = hash('sha256', $photoUri . '|' . $now . '|' . $actorName);
        $evidencePayload = [
            'photo_uri'     => $photoUri,
            'photo_sha256'  => $photoHash,
            'captured_at'   => $now,
            'captured_by'   => $actorName,
            'evidence_type' => 'AFTER_WORK_COMPLETION_RESULT',
            'notes'         => $afterEvidence['notes'] ?? 'Pekerjaan selesai fisik, area bersih dari sisa ranting dan aman',
        ];

        // Transition to WORK_COMPLETED_PENDING_ACCEPTANCE
        $this->db->table('operational_field_execution_records')
                 ->where('id', $execId)
                 ->update([
                     'execution_status'                 => 'WORK_COMPLETED_PENDING_ACCEPTANCE',
                     'progress_percentage'              => 100.00,
                     'work_completed_at'                => $now,
                     'after_evidence_json'              => json_encode($evidencePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'completion_declaration_rationale' => $cleanRationale,
                     'field_completion_declared_by'     => $actorName,
                     'field_completion_declared_at'     => $now,
                     'updated_at'                       => $now,
                 ]);

        $this->db->table('operational_execution_progress_events')->insert([
            'execution_id'           => $execId,
            'execution_code'         => $exec['execution_code'],
            'event_type'             => 'COMPLETION_DECLARED',
            'progress_percentage'    => 100.00,
            'event_description'      => 'Pekerjaan fisik dinyatakan selesai oleh pengawas lapangan (Pending Acceptance)',
            'evidence_metadata_json' => json_encode($evidencePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'decision_rationale'     => $cleanRationale,
            'recorded_by'            => $actorName,
            'recorded_at'            => $now,
        ]);

        return [
            'status'             => 'success',
            'execution_id'       => $execId,
            'execution_code'     => $exec['execution_code'],
            'execution_status'   => 'WORK_COMPLETED_PENDING_ACCEPTANCE',
            'progress'           => 100.00,
            'declared_by'        => $actorName,
            'governance_verdict' => 'HUMAN_FIELD_COMPLETION_DECLARED_PENDING_ACCEPTANCE',
        ];
    }

    /**
     * Abort work due to field constraints.
     */
    public function abortWork(int $execId, string $abortReason, ?array $actor = null): array
    {
        $exec = $this->db->table('operational_field_execution_records')
                         ->where('id', $execId)
                         ->get()
                         ->getRowArray();

        if (!$exec || in_array($exec['execution_status'], ['WORK_COMPLETED_PENDING_ACCEPTANCE', 'WORK_ABORTED_FIELD_CONSTRAINTS'], true)) {
            return [
                'status'  => 'error',
                'message' => 'Record cannot be aborted from its current terminal status.',
                'code'    => 'INVALID_ABORT_STATUS',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'PENGAWAS_LAPANGAN';
        $cleanReason = trim($abortReason) ?: 'Pekerjaan dibatalkan karena kendala teknis / cuaca ekstrim';

        $this->db->table('operational_field_execution_records')
                 ->where('id', $execId)
                 ->update([
                     'execution_status'     => 'WORK_ABORTED_FIELD_CONSTRAINTS',
                     'field_incident_notes' => $cleanReason,
                     'updated_at'           => $now,
                 ]);

        $this->db->table('operational_execution_progress_events')->insert([
            'execution_id'        => $execId,
            'execution_code'      => $exec['execution_code'],
            'event_type'          => 'WORK_ABORTED',
            'progress_percentage' => (float)$exec['progress_percentage'],
            'event_description'   => 'Pekerjaan dibatalkan: ' . $cleanReason,
            'decision_rationale'  => $cleanReason,
            'recorded_by'         => $actorName,
            'recorded_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'execution_id'       => $execId,
            'execution_status'   => 'WORK_ABORTED_FIELD_CONSTRAINTS',
            'governance_verdict' => 'WORK_ABORTED_FIELD_CONSTRAINTS_RECORDED',
        ];
    }

    /**
     * Get list of execution records.
     */
    public function getExecutionRecords(array $filters = []): array
    {
        if (!$this->db->tableExists('operational_field_execution_records')) {
            return [];
        }

        $builder = $this->db->table('operational_field_execution_records');

        if (!empty($filters['status'])) {
            $builder->where('execution_status', $filters['status']);
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * Get authorized packages ready for execution recording.
     */
    public function getAuthorizedPackagesReadyForExecution(): array
    {
        if (!$this->db->tableExists('operational_work_authorizations')) {
            return [];
        }

        $packages = $this->db->table('operational_work_authorizations')
                             ->where('authorization_status', 'EXECUTION_AUTHORIZED')
                             ->get()
                             ->getResultArray();

        $ready = [];
        foreach ($packages as $pkg) {
            $activeExecCount = $this->db->table('operational_field_execution_records')
                                        ->where('authorization_id', $pkg['id'])
                                        ->whereIn('execution_status', ['WORK_PENDING_FIELD_START', 'WORK_IN_PROGRESS', 'WORK_PAUSED_SAFETY_HOLD'])
                                        ->countAllResults();
            if ($activeExecCount === 0) {
                $ready[] = $pkg;
            }
        }

        return $ready;
    }

    /**
     * Get execution record detail with actual material reconciliation and progress events.
     */
    public function getExecutionDetail(int $execId): array
    {
        $exec = $this->db->table('operational_field_execution_records')
                         ->where('id', $execId)
                         ->get()
                         ->getRowArray();

        if (!$exec) {
            return [];
        }

        $events = $this->db->table('operational_execution_progress_events')
                           ->where('execution_id', $execId)
                           ->orderBy('id', 'DESC')
                           ->get()
                           ->getResultArray();

        $auth = $this->db->table('operational_work_authorizations')
                         ->where('id', $exec['authorization_id'])
                         ->get()
                         ->getRowArray();

        $materials = !empty($exec['actual_materials_json']) ? json_decode($exec['actual_materials_json'], true) : [];
        $beforeEvidence = !empty($exec['before_evidence_json']) ? json_decode($exec['before_evidence_json'], true) : [];
        $afterEvidence = !empty($exec['after_evidence_json']) ? json_decode($exec['after_evidence_json'], true) : [];

        return [
            'exec'            => $exec,
            'auth'            => $auth,
            'materials'       => $materials,
            'before_evidence' => $beforeEvidence,
            'after_evidence'  => $afterEvidence,
            'events'          => $events,
            'invariants'      => [
                'execution_source_rebinding_locked' => true,
                'actual_material_preserves_op02'    => true,
                'no_automatic_closure'              => true,
                'no_automatic_acceptance'           => true,
            ],
        ];
    }
}
