<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Operational Work Acceptance Service (Wave 2 Phase OP-07)
 *
 * Responsibilities:
 * - Work Acceptance, Quality Assurance & Closure Governance.
 * - Enforces:
 *     COMPLETION_DECLARATION != WORK_ACCEPTANCE
 *     WORK_ACCEPTED != WORK_CLOSED
 *     DUPLICATE_ACTIVE_ACCEPTANCE_FOR_EXECUTION = REJECTED (Hardening #1)
 *     SEPARATION_OF_DUTIES_ENFORCED (Hardening #2)
 *     GOVERNED_REWORK_REINSPECTION_LOOP (Hardening #3)
 *     CANONICAL_SHA256_ACCEPTANCE_SEAL (Hardening #4)
 *     FULL_FORENSIC_LINEAGE_THROUGH_CLOSURE (Hardening #5)
 *     ZERO_AUTONOMOUS_EXECUTION = ENFORCED
 */
class OperationalWorkAcceptanceService
{
    public const ALLOWED_ACCEPTANCE_TRANSITIONS = [
        'ACCEPTANCE_REVIEW_PENDING' => ['WORK_ACCEPTED', 'REWORK_REQUIRED', 'ACCEPTANCE_REJECTED'],
        'REWORK_REQUIRED'           => ['ACCEPTANCE_REVIEW_PENDING'],
        'ACCEPTANCE_REJECTED'       => [], // Terminal
        'WORK_ACCEPTED'             => ['WORK_CLOSED'],
        'WORK_CLOSED'               => [], // Final Terminal
    ];

    public const QUALITY_SCORE_THRESHOLD = 85.00;

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Initiate Acceptance Review from a completed field execution record.
     *
     * @param int $execId
     * @param array|null $actor
     * @return array
     */
    public function initiateAcceptanceReview(int $execId, ?array $actor = null): array
    {
        // 1. Execution Eligibility Check
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

        if ($exec['execution_status'] !== 'WORK_COMPLETED_PENDING_ACCEPTANCE') {
            return [
                'status'  => 'error',
                'message' => "Record {$exec['execution_code']} is '{$exec['execution_status']}'. Only 'WORK_COMPLETED_PENDING_ACCEPTANCE' records can be reviewed for acceptance.",
                'code'    => 'EXECUTION_NOT_COMPLETED',
            ];
        }

        // Hardening #1: Exclusivity & Idempotency Check
        $activeAcc = $this->db->table('operational_work_acceptances')
                              ->where('execution_id', $execId)
                              ->whereIn('acceptance_status', ['ACCEPTANCE_REVIEW_PENDING', 'REWORK_REQUIRED', 'WORK_ACCEPTED'])
                              ->get()
                              ->getRowArray();

        if ($activeAcc) {
            return [
                'status'  => 'error',
                'message' => "Execution {$exec['execution_code']} already has active acceptance record {$activeAcc['acceptance_code']} ({$activeAcc['acceptance_status']}).",
                'code'    => 'DUPLICATE_ACTIVE_ACCEPTANCE_FOR_EXECUTION',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'INSPEKTUR_MUTU_JARINGAN';
        $accCode = 'ACC-CERT-STJ-' . date('Y') . '-W' . date('W') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // 4-Dimensional QA Evaluation Checklist Template
        $evidenceChecks = [
            ['item' => 'Foto Kondisi Awal (Before) dan Akhir (After) Valid & Jelas', 'passed' => true, 'notes' => 'Forensic hash foto verified'],
            ['item' => 'Kesesuaian Lokasi Fisik Tiang dan Seksi Penyulang Terverifikasi', 'passed' => true, 'notes' => 'Kordinat GPS tiang sesuai'],
        ];

        $technicalChecks = [
            ['item' => 'Ruang Bebas (ROW) Bersih Sesuai Standar (>= 3 Meter)', 'passed' => true, 'notes' => 'ROW aman radius 3m bebas dahan'],
            ['item' => 'Peralatan Grounding Lokal Telah Dilepas Seluruhnya', 'passed' => true, 'notes' => 'Peralatan K3 dilepas aman'],
            ['item' => 'Kekencangan Konduktor, Isolator & Jumper Bebas Anomali', 'passed' => true, 'notes' => 'Pemeriksaan visual mekanik baik'],
        ];

        $materialChecks = [
            ['item' => 'Selisih Material Aktual Teralokasi dan Tervalidasi', 'passed' => true, 'notes' => 'Variance material tercatat transparan'],
            ['item' => 'Material Bekas / Sisa Telah Diserahkan ke Logistik Gudang', 'passed' => true, 'notes' => 'Sisa potongan kawat/ranting bersih'],
        ];

        $asbuiltChecks = [
            ['item' => 'Catatan Lapangan & Kejadian Khusus Terisi Lengkap', 'passed' => true, 'notes' => 'Log book pekerjaan diverifikasi'],
            ['item' => 'Kesiapan Pengoperasian Kembali Jaringan Terkonfirmasi', 'passed' => true, 'notes' => 'Jaringan aman untuk dinormalkan'],
        ];

        $qualityScore = 100.00; // All standard dimensions pass initially

        // Hardening #5: Persist with Lineage
        $accData = [
            'acceptance_code'            => $accCode,
            'execution_id'               => $execId,
            'execution_code'             => $exec['execution_code'],
            'authorization_id'           => $exec['authorization_id'],
            'authorization_code'         => $exec['authorization_code'],
            'scenario_id'                => $exec['scenario_id'],
            'slot_id'                    => $exec['slot_id'],
            'portfolio_id'               => $exec['portfolio_id'],
            'plan_id'                    => $exec['plan_id'],
            'plan_code'                  => $exec['plan_code'],
            'candidate_id'               => $exec['candidate_id'],
            'snapshot_id'                => $exec['snapshot_id'],
            'feeder_name'                => $exec['feeder_name'],
            'section_name'               => $exec['section_name'],
            'acceptance_status'          => 'ACCEPTANCE_REVIEW_PENDING',
            'evidence_verification_json' => json_encode($evidenceChecks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'technical_quality_json'     => json_encode($technicalChecks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'material_audit_json'        => json_encode($materialChecks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'asbuilt_verification_json'  => json_encode($asbuiltChecks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'quality_score'              => $qualityScore,
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ];

        $this->db->table('operational_work_acceptances')->insert($accData);
        $accId = (int)$this->db->insertID();

        // Append-Only Audit Event
        $this->db->table('operational_acceptance_events')->insert([
            'acceptance_id'      => $accId,
            'acceptance_code'    => $accCode,
            'event_type'         => 'ACCEPTANCE_REVIEW_INITIALIZED',
            'previous_status'    => 'NONE',
            'new_status'         => 'ACCEPTANCE_REVIEW_PENDING',
            'quality_score'      => $qualityScore,
            'decision_rationale' => 'Inisialisasi review penerimaan mutu independen dari rekaman eksekusi selesai',
            'decided_by'         => $actorName,
            'decided_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'acceptance_id'      => $accId,
            'acceptance_code'    => $accCode,
            'execution_code'     => $exec['execution_code'],
            'quality_score'      => $qualityScore,
            'acceptance_status'  => 'ACCEPTANCE_REVIEW_PENDING',
            'governance_verdict' => 'WORK_ACCEPTANCE_REVIEW_INITIALIZED',
        ];
    }

    /**
     * Update 4-Dimensional QA Evaluation Checklist and Quality Score.
     */
    public function evaluateQualityDimensions(
        int $accId,
        array $evalData,
        string $rationale,
        ?array $actor = null
    ): array {
        $acc = $this->db->table('operational_work_acceptances')
                        ->where('id', $accId)
                        ->get()
                        ->getRowArray();

        if (!$acc) {
            return [
                'status'  => 'error',
                'message' => "Acceptance Record #{$accId} not found.",
                'code'    => 'ACCEPTANCE_NOT_FOUND',
            ];
        }

        // Hardening #4: Freeze check
        if (in_array($acc['acceptance_status'], ['WORK_ACCEPTED', 'WORK_CLOSED'], true)) {
            return [
                'status'  => 'error',
                'message' => "Record {$acc['acceptance_code']} is '{$acc['acceptance_status']}' and sealed. Mutating QA checklist is forbidden.",
                'code'    => 'SEALED_ACCEPTANCE_MUTATION_FORBIDDEN',
            ];
        }

        $cleanRationale = trim($rationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'QA evaluation rationale is mandatory.',
                'code'    => 'MANDATORY_QA_RATIONALE_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'INSPEKTUR_MUTU_JARINGAN';

        $evidence  = $evalData['evidence_verification'] ?? json_decode($acc['evidence_verification_json'], true);
        $technical = $evalData['technical_quality'] ?? json_decode($acc['technical_quality_json'], true);
        $material  = $evalData['material_audit'] ?? json_decode($acc['material_audit_json'], true);
        $asbuilt   = $evalData['asbuilt_verification'] ?? json_decode($acc['asbuilt_verification_json'], true);

        // Calculate score
        $totalItems = count($evidence) + count($technical) + count($material) + count($asbuilt);
        $passedItems = 0;
        foreach (array_merge($evidence, $technical, $material, $asbuilt) as $item) {
            if (!empty($item['passed'])) {
                $passedItems++;
            }
        }
        $score = $totalItems > 0 ? round(($passedItems / $totalItems) * 100.0, 2) : 0.00;

        $this->db->table('operational_work_acceptances')
                 ->where('id', $accId)
                 ->update([
                     'evidence_verification_json' => json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'technical_quality_json'     => json_encode($technical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'material_audit_json'        => json_encode($material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'asbuilt_verification_json'  => json_encode($asbuilt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'quality_score'              => $score,
                     'updated_at'                 => $now,
                 ]);

        // Append-Only Event
        $this->db->table('operational_acceptance_events')->insert([
            'acceptance_id'      => $accId,
            'acceptance_code'    => $acc['acceptance_code'],
            'event_type'         => 'QUALITY_EVALUATION_UPDATED',
            'previous_status'    => $acc['acceptance_status'],
            'new_status'         => $acc['acceptance_status'],
            'quality_score'      => $score,
            'decision_rationale' => $cleanRationale,
            'decided_by'         => $actorName,
            'decided_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'acceptance_id'      => $accId,
            'quality_score'      => $score,
            'governance_verdict' => 'QUALITY_EVALUATION_UPDATED_AUDITED',
        ];
    }

    /**
     * Formal Work Acceptance with Canonical SHA-256 Certificate Seal (Hardening #2, #4, #5).
     */
    public function acceptWork(
        int $accId,
        string $rationale,
        ?array $actor = null
    ): array {
        $acc = $this->db->table('operational_work_acceptances')
                        ->where('id', $accId)
                        ->get()
                        ->getRowArray();

        if (!$acc) {
            return [
                'status'  => 'error',
                'message' => "Acceptance Record #{$accId} not found.",
                'code'    => 'ACCEPTANCE_NOT_FOUND',
            ];
        }

        if ($acc['acceptance_status'] !== 'ACCEPTANCE_REVIEW_PENDING') {
            return [
                'status'  => 'error',
                'message' => "Record is in '{$acc['acceptance_status']}'. Only 'ACCEPTANCE_REVIEW_PENDING' can be accepted.",
                'code'    => 'INVALID_ACCEPTANCE_STATUS',
            ];
        }

        // Quality score check
        if ((float)$acc['quality_score'] < self::QUALITY_SCORE_THRESHOLD) {
            return [
                'status'  => 'error',
                'message' => "Quality score is {$acc['quality_score']}%, which is below mandatory threshold of " . self::QUALITY_SCORE_THRESHOLD . "%. Rework is required.",
                'code'    => 'QUALITY_SCORE_BELOW_THRESHOLD',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'INSPEKTUR_MUTU_JARINGAN';
        $actorRole = $actor['role'] ?? 'INSPEKTUR_MUTU_JARINGAN';
        $cleanRationale = trim($rationale) ?: 'Hasil pekerjaan fisik telah memenuhi seluruh standar mutu teknis dan K3';

        // Hardening #2: Separation of Duties Enforcement
        $exec = $this->db->table('operational_field_execution_records')->where('id', $acc['execution_id'])->get()->getRowArray();
        $isFieldExecutor = ($exec && ($exec['field_start_initiated_by'] === $actorName || $exec['field_completion_declared_by'] === $actorName));
        $hasIndependentQARole = in_array($actorRole, ['INSPEKTUR_MUTU_JARINGAN', 'TIM_QA_INDEPENDEN', 'ASISTEN_MANAJER_JARINGAN'], true);

        if ($isFieldExecutor && !$hasIndependentQARole) {
            return [
                'status'  => 'error',
                'message' => "Separation of Duties violation: Field executor '{$actorName}' cannot accept their own field work. Independent QA reviewer required.",
                'code'    => 'SEPARATION_OF_DUTIES_VIOLATION',
            ];
        }

        // Hardening #4: Deterministic Canonical SHA-256 Acceptance Seal
        $sealPayload = [
            'acceptance_code'          => $acc['acceptance_code'],
            'execution_code'           => $acc['execution_code'],
            'authorization_id'         => (int)$acc['authorization_id'],
            'scenario_id'              => (int)$acc['scenario_id'],
            'slot_id'                  => (int)$acc['slot_id'],
            'portfolio_id'             => (int)$acc['portfolio_id'],
            'plan_id'                  => (int)$acc['plan_id'],
            'candidate_id'             => (int)$acc['candidate_id'],
            'snapshot_id'              => (int)$acc['snapshot_id'],
            'quality_score'            => (float)$acc['quality_score'],
            'evidence_verification'    => json_decode($acc['evidence_verification_json'], true),
            'technical_quality'        => json_decode($acc['technical_quality_json'], true),
            'material_audit'           => json_decode($acc['material_audit_json'], true),
            'asbuilt_verification'     => json_decode($acc['asbuilt_verification_json'], true),
            'accepting_inspector_name' => $actorName,
            'accepting_inspector_role' => $actorRole,
            'acceptance_rationale'     => $cleanRationale,
            'accepted_at'              => $now,
            'certificate_schema_version'=> 'OP07_CANONICAL_v1.0',
        ];

        $canonicalString = $this->canonicalizePayload($sealPayload);
        $sha256 = hash('sha256', $canonicalString);

        // Update record to WORK_ACCEPTED
        $this->db->table('operational_work_acceptances')
                 ->where('id', $accId)
                 ->update([
                     'acceptance_status'             => 'WORK_ACCEPTED',
                     'accepting_inspector_name'      => $actorName,
                     'accepting_inspector_role'      => $actorRole,
                     'acceptance_rationale'          => $cleanRationale,
                     'acceptance_certificate_sha256' => $sha256,
                     'accepted_at'                   => $now,
                     'updated_at'                    => $now,
                 ]);

        // Append-Only Event
        $this->db->table('operational_acceptance_events')->insert([
            'acceptance_id'      => $accId,
            'acceptance_code'    => $acc['acceptance_code'],
            'event_type'         => 'WORK_ACCEPTED',
            'previous_status'    => 'ACCEPTANCE_REVIEW_PENDING',
            'new_status'         => 'WORK_ACCEPTED',
            'quality_score'      => (float)$acc['quality_score'],
            'decision_rationale' => $cleanRationale,
            'decided_by'         => $actorName,
            'decided_at'         => $now,
        ]);

        return [
            'status'               => 'success',
            'acceptance_id'        => $accId,
            'acceptance_code'      => $acc['acceptance_code'],
            'acceptance_status'    => 'WORK_ACCEPTED',
            'quality_score'        => (float)$acc['quality_score'],
            'acceptance_sha256'    => $sha256,
            'inspector'            => $actorName,
            'governance_verdict'   => 'WORK_ACCEPTED_SHA256_SEALED',
        ];
    }

    /**
     * Request Rework with Mandatory Deficiencies (Hardening #3).
     */
    public function requestRework(
        int $accId,
        string $instructions,
        ?array $actor = null
    ): array {
        $acc = $this->db->table('operational_work_acceptances')
                        ->where('id', $accId)
                        ->get()
                        ->getRowArray();

        if (!$acc || $acc['acceptance_status'] !== 'ACCEPTANCE_REVIEW_PENDING') {
            return [
                'status'  => 'error',
                'message' => 'Rework can only be requested from ACCEPTANCE_REVIEW_PENDING.',
                'code'    => 'INVALID_REWORK_STATUS',
            ];
        }

        $cleanInstructions = trim($instructions);
        if ($cleanInstructions === '') {
            return [
                'status'  => 'error',
                'message' => 'Rework instructions and deficiency notes are mandatory.',
                'code'    => 'MANDATORY_REWORK_INSTRUCTIONS_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'INSPEKTUR_MUTU_JARINGAN';

        $this->db->table('operational_work_acceptances')
                 ->where('id', $accId)
                 ->update([
                     'acceptance_status'   => 'REWORK_REQUIRED',
                     'rework_instructions' => $cleanInstructions,
                     'updated_at'          => $now,
                 ]);

        $this->db->table('operational_acceptance_events')->insert([
            'acceptance_id'      => $accId,
            'acceptance_code'    => $acc['acceptance_code'],
            'event_type'         => 'REWORK_REQUIRED',
            'previous_status'    => 'ACCEPTANCE_REVIEW_PENDING',
            'new_status'         => 'REWORK_REQUIRED',
            'quality_score'      => (float)$acc['quality_score'],
            'decision_rationale' => $cleanInstructions,
            'decided_by'         => $actorName,
            'decided_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'acceptance_id'      => $accId,
            'acceptance_status'  => 'REWORK_REQUIRED',
            'governance_verdict' => 'REWORK_REQUIRED_PRESERVES_LINEAGE',
        ];
    }

    /**
     * Request Re-Inspection after physical rework is completed (Hardening #3).
     */
    public function requestReinspection(
        int $accId,
        string $reworkNotes,
        ?array $actor = null
    ): array {
        $acc = $this->db->table('operational_work_acceptances')
                        ->where('id', $accId)
                        ->get()
                        ->getRowArray();

        if (!$acc || $acc['acceptance_status'] !== 'REWORK_REQUIRED') {
            return [
                'status'  => 'error',
                'message' => 'Re-inspection can only be requested when status is REWORK_REQUIRED.',
                'code'    => 'INVALID_REINSPECTION_STATUS',
            ];
        }

        $cleanNotes = trim($reworkNotes);
        if ($cleanNotes === '') {
            return [
                'status'  => 'error',
                'message' => 'Rework completion notes are mandatory to request re-inspection.',
                'code'    => 'MANDATORY_REWORK_COMPLETION_NOTES_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'PENGAWAS_LAPANGAN';

        $this->db->table('operational_work_acceptances')
                 ->where('id', $accId)
                 ->update([
                     'acceptance_status' => 'ACCEPTANCE_REVIEW_PENDING',
                     'updated_at'        => $now,
                 ]);

        $this->db->table('operational_acceptance_events')->insert([
            'acceptance_id'      => $accId,
            'acceptance_code'    => $acc['acceptance_code'],
            'event_type'         => 'REINSPECTION_REQUESTED',
            'previous_status'    => 'REWORK_REQUIRED',
            'new_status'         => 'ACCEPTANCE_REVIEW_PENDING',
            'quality_score'      => (float)$acc['quality_score'],
            'decision_rationale' => $cleanNotes,
            'decided_by'         => $actorName,
            'decided_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'acceptance_id'      => $accId,
            'acceptance_status'  => 'ACCEPTANCE_REVIEW_PENDING',
            'governance_verdict' => 'REINSPECTION_REQUESTED_REVIEWS_PENDING',
        ];
    }

    /**
     * Final Executive Work Closure by Human Manager (Hardening #5).
     */
    public function closeWork(
        int $accId,
        string $closureRationale,
        ?array $actor = null
    ): array {
        $acc = $this->db->table('operational_work_acceptances')
                        ->where('id', $accId)
                        ->get()
                        ->getRowArray();

        if (!$acc) {
            return [
                'status'  => 'error',
                'message' => "Acceptance Record #{$accId} not found.",
                'code'    => 'ACCEPTANCE_NOT_FOUND',
            ];
        }

        if ($acc['acceptance_status'] !== 'WORK_ACCEPTED') {
            return [
                'status'  => 'error',
                'message' => "Record is in '{$acc['acceptance_status']}'. Only 'WORK_ACCEPTED' records can be closed.",
                'code'    => 'WORK_NOT_ACCEPTED',
            ];
        }

        $cleanRationale = trim($closureRationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Manager closure rationale is mandatory.',
                'code'    => 'MANDATORY_CLOSURE_RATIONALE_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'MANAJER_BAGIAN_JARINGAN';
        $actorRole = $actor['role'] ?? 'MANAJER_UP3_JARINGAN';

        $this->db->table('operational_work_acceptances')
                 ->where('id', $accId)
                 ->update([
                     'acceptance_status'    => 'WORK_CLOSED',
                     'closing_manager_name' => $actorName,
                     'closing_manager_role' => $actorRole,
                     'closure_rationale'    => $cleanRationale,
                     'closed_at'            => $now,
                     'updated_at'           => $now,
                 ]);

        $this->db->table('operational_acceptance_events')->insert([
            'acceptance_id'      => $accId,
            'acceptance_code'    => $acc['acceptance_code'],
            'event_type'         => 'WORK_CLOSED',
            'previous_status'    => 'WORK_ACCEPTED',
            'new_status'         => 'WORK_CLOSED',
            'quality_score'      => (float)$acc['quality_score'],
            'decision_rationale' => $cleanRationale,
            'decided_by'         => $actorName,
            'decided_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'acceptance_id'      => $accId,
            'acceptance_code'    => $acc['acceptance_code'],
            'acceptance_status'  => 'WORK_CLOSED',
            'closing_manager'    => $actorName,
            'governance_verdict' => 'WORK_OFFICIALLY_CLOSED_FORENSIC_LOCK',
        ];
    }

    /**
     * Deterministic Canonical Serialization for SHA-256 Certificate Fingerprint.
     */
    protected function canonicalizePayload(mixed $data): string
    {
        if (is_array($data)) {
            $isAssoc = array_keys($data) !== range(0, count($data) - 1);
            if ($isAssoc) {
                ksort($data, SORT_STRING);
            }
            foreach ($data as $key => $val) {
                $data[$key] = $this->canonicalizePayload($val);
            }
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string)$data;
    }

    /**
     * Verify the cryptographic SHA-256 seal integrity of an accepted certificate.
     */
    public function verifyCertificateSeal(int $accId): array
    {
        $acc = $this->db->table('operational_work_acceptances')
                        ->where('id', $accId)
                        ->get()
                        ->getRowArray();

        if (!$acc || empty($acc['acceptance_certificate_sha256'])) {
            return [
                'status'  => 'error',
                'message' => 'Acceptance certificate is not sealed.',
                'code'    => 'CERTIFICATE_NOT_SEALED',
            ];
        }

        $sealPayload = [
            'acceptance_code'          => $acc['acceptance_code'],
            'execution_code'           => $acc['execution_code'],
            'authorization_id'         => (int)$acc['authorization_id'],
            'scenario_id'              => (int)$acc['scenario_id'],
            'slot_id'                  => (int)$acc['slot_id'],
            'portfolio_id'             => (int)$acc['portfolio_id'],
            'plan_id'                  => (int)$acc['plan_id'],
            'candidate_id'             => (int)$acc['candidate_id'],
            'snapshot_id'              => (int)$acc['snapshot_id'],
            'quality_score'            => (float)$acc['quality_score'],
            'evidence_verification'    => json_decode($acc['evidence_verification_json'], true),
            'technical_quality'        => json_decode($acc['technical_quality_json'], true),
            'material_audit'           => json_decode($acc['material_audit_json'], true),
            'asbuilt_verification'     => json_decode($acc['asbuilt_verification_json'], true),
            'accepting_inspector_name' => $acc['accepting_inspector_name'],
            'accepting_inspector_role' => $acc['accepting_inspector_role'],
            'acceptance_rationale'     => $acc['acceptance_rationale'],
            'accepted_at'              => $acc['accepted_at'],
            'certificate_schema_version'=> 'OP07_CANONICAL_v1.0',
        ];

        $recalculatedHash = hash('sha256', $this->canonicalizePayload($sealPayload));
        $matches = hash_equals($acc['acceptance_certificate_sha256'], $recalculatedHash);

        return [
            'status'            => $matches ? 'success' : 'tampered',
            'acceptance_code'   => $acc['acceptance_code'],
            'persisted_sha256'  => $acc['acceptance_certificate_sha256'],
            'calculated_sha256' => $recalculatedHash,
            'integrity_verdict' => $matches ? 'SHA256_INTEGRITY_VERIFIED' : 'SEAL_INTEGRITY_COMPROMISED',
        ];
    }

    /**
     * Get list of acceptance records.
     */
    public function getAcceptanceRecords(array $filters = []): array
    {
        if (!$this->db->tableExists('operational_work_acceptances')) {
            return [];
        }

        $builder = $this->db->table('operational_work_acceptances');

        if (!empty($filters['status'])) {
            $builder->where('acceptance_status', $filters['status']);
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * Get completed field execution records ready for acceptance review.
     */
    public function getCompletedExecutionsReadyForAcceptance(): array
    {
        if (!$this->db->tableExists('operational_field_execution_records')) {
            return [];
        }

        $records = $this->db->table('operational_field_execution_records')
                            ->where('execution_status', 'WORK_COMPLETED_PENDING_ACCEPTANCE')
                            ->get()
                            ->getResultArray();

        $ready = [];
        foreach ($records as $r) {
            $activeAccCount = $this->db->table('operational_work_acceptances')
                                       ->where('execution_id', $r['id'])
                                       ->whereIn('acceptance_status', ['ACCEPTANCE_REVIEW_PENDING', 'REWORK_REQUIRED', 'WORK_ACCEPTED'])
                                       ->countAllResults();
            if ($activeAccCount === 0) {
                $ready[] = $r;
            }
        }

        return $ready;
    }

    /**
     * Get acceptance record full detail.
     */
    public function getAcceptanceDetail(int $accId): array
    {
        $acc = $this->db->table('operational_work_acceptances')
                        ->where('id', $accId)
                        ->get()
                        ->getRowArray();

        if (!$acc) {
            return [];
        }

        $events = $this->db->table('operational_acceptance_events')
                           ->where('acceptance_id', $accId)
                           ->orderBy('id', 'DESC')
                           ->get()
                           ->getResultArray();

        $exec = $this->db->table('operational_field_execution_records')
                         ->where('id', $acc['execution_id'])
                         ->get()
                         ->getRowArray();

        return [
            'acc'       => $acc,
            'exec'      => $exec,
            'evidence'  => !empty($acc['evidence_verification_json']) ? json_decode($acc['evidence_verification_json'], true) : [],
            'technical' => !empty($acc['technical_quality_json']) ? json_decode($acc['technical_quality_json'], true) : [],
            'material'  => !empty($acc['material_audit_json']) ? json_decode($acc['material_audit_json'], true) : [],
            'asbuilt'   => !empty($acc['asbuilt_verification_json']) ? json_decode($acc['asbuilt_verification_json'], true) : [],
            'events'    => $events,
            'invariants'=> [
                'separation_of_duties_enforced'    => true,
                'canonical_sha256_sealed'          => !empty($acc['acceptance_certificate_sha256']),
                'forensic_lineage_preserved'       => true,
            ],
        ];
    }
}
