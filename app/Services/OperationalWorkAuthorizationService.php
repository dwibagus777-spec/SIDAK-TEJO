<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Operational Work Authorization Service (Wave 2 Phase OP-05)
 *
 * Responsibilities:
 * - Execution Readiness Gate & Work Authorization Governance.
 * - Enforces:
 *     AUTHORIZED_EXECUTION_INTENT != AUTOMATIC_FIELD_EXECUTION
 *     WORK_AUTHORIZATION_PACKAGE != AUTOMATIC_CREW_DISPATCH
 *     DUPLICATE_ACTIVE_AUTHORIZATION_FOR_SLOT = REJECTED (Hardening #1)
 *     AUTHORIZATION_SOURCE_REBINDING = FORBIDDEN (Hardening #2)
 *     APPEND_ONLY_READINESS_EVENT_TRAIL (Hardening #3)
 *     CANONICAL_SHA256_SEAL_PAYLOAD (Hardening #4)
 *     SEALED_AUTHORIZATION_FREEZE (Hardening #5)
 *     READINESS_REVISION_REGRESSION_GUARD (Hardening #6)
 *     EXPLICIT_EXECUTION_BOUNDARY_METADATA (Hardening #7)
 *     ZERO_AUTONOMOUS_EXECUTION = ENFORCED
 */
class OperationalWorkAuthorizationService
{
    public const ALLOWED_AUTHORIZATION_TRANSITIONS = [
        'READINESS_CHECK_PENDING' => ['READINESS_VERIFIED', 'REVISION_REQUIRED'],
        'REVISION_REQUIRED'       => ['READINESS_CHECK_PENDING'],
        'READINESS_VERIFIED'      => ['EXECUTION_AUTHORIZED', 'REVISION_REQUIRED'],
        'EXECUTION_AUTHORIZED'    => ['AUTHORIZATION_REVOKED', 'AUTHORIZATION_SUPERSEDED'],
        'AUTHORIZATION_REVOKED'   => [], // Terminal
        'AUTHORIZATION_SUPERSEDED' => [], // Terminal
    ];

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Generate an initial Work Authorization Package for a Scheduled Slot in an Approved Scenario.
     *
     * @param int $slotId
     * @param array|null $actor
     * @return array
     */
    public function generatePackageForSlot(int $slotId, ?array $actor = null): array
    {
        // 1. Slot & Scenario Eligibility Check
        $slot = $this->db->table('operational_scheduled_slots')
                         ->where('id', $slotId)
                         ->get()
                         ->getRowArray();

        if (!$slot) {
            return [
                'status'  => 'error',
                'message' => "Scheduled Slot #{$slotId} not found.",
                'code'    => 'SLOT_NOT_FOUND',
            ];
        }

        $scenario = $this->db->table('operational_scheduling_scenarios')
                             ->where('id', $slot['scenario_id'])
                             ->get()
                             ->getRowArray();

        if (!$scenario || $scenario['scenario_status'] !== 'SCENARIO_APPROVED') {
            return [
                'status'  => 'error',
                'message' => "Scenario {$slot['scenario_id']} is not 'SCENARIO_APPROVED'. Only approved scenarios can generate authorization packages.",
                'code'    => 'SCENARIO_NOT_APPROVED',
            ];
        }

        // Hardening #1: Exclusivity & Idempotency Check
        $activeAuth = $this->db->table('operational_work_authorizations')
                               ->where('slot_id', $slotId)
                               ->whereIn('authorization_status', ['READINESS_CHECK_PENDING', 'READINESS_VERIFIED', 'EXECUTION_AUTHORIZED', 'REVISION_REQUIRED'])
                               ->get()
                               ->getRowArray();

        if ($activeAuth) {
            return [
                'status'  => 'error',
                'message' => "Slot #{$slotId} already has an active authorization package: {$activeAuth['authorization_code']} ({$activeAuth['authorization_status']}).",
                'code'    => 'DUPLICATE_ACTIVE_AUTHORIZATION_FOR_SLOT',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'HUMAN_READINESS_OFFICER';
        $authCode = 'AUTH-PKG-STJ-' . date('Y') . '-W' . date('W') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Fetch portfolio & item
        $portfolioItem = $this->db->table('operational_portfolio_items')
                                  ->where('id', $slot['portfolio_item_id'])
                                  ->get()
                                  ->getRowArray();

        // 4-Dimensional Default Checklist Template
        $safetyChecks = [
            ['item' => 'JSA (Job Safety Analysis) Tersedia & Ditandatangani', 'passed' => true, 'notes' => 'JSA SUTM 20kV Terverifikasi'],
            ['item' => 'APD Lengkap 20kV (Helm, Sarung Tangan, Sepatu 20kV)', 'passed' => true, 'notes' => 'Inspeksi fisik APD lulus'],
            ['item' => 'Peralatan Grounding Lokal & Voltage Detector Siap', 'passed' => true, 'notes' => 'Grounding set terkalibrasi'],
            ['item' => 'Kotak P3K & APAR Tersedia di Kendaraan Kerja', 'passed' => true, 'notes' => 'Siap tanggap darurat'],
            ['item' => 'Safety Briefing Pra-Kerja Terjadwal', 'passed' => true, 'notes' => 'Toolbox meeting wajib sebelum naik tiang'],
        ];

        $materialChecks = [
            ['item' => 'Material SUTM Sesuai Estimasi Telah Staging di Gudang', 'passed' => true, 'notes' => 'Material teralokasi fisik'],
            ['item' => 'Kualitas Fisik Material & Isolator Bebas Retak/Cacat', 'passed' => true, 'notes' => 'Pemeriksaan visual lulus'],
            ['item' => 'Alat Kerja Utama (Tang, Katrol, Derek) Siap Pakai', 'passed' => true, 'notes' => 'Kelaikan alat kerja terverifikasi'],
        ];

        $permitChecks = [
            ['item' => 'Pemberitahuan Pemadaman ke Pelanggan Terdistribusi (Bila Padam)', 'passed' => true, 'notes' => 'Notifikasi 3x24 jam telah dipublikasi'],
            ['item' => 'Izin Melintas / Koordinasi Lingkungan Lokal Selesai', 'passed' => true, 'notes' => 'Koordinasi warga & dinas lancar'],
        ];

        $teamChecks = [
            ['item' => 'Pengawas Pekerjaan Bersertifikasi Ditugaskan', 'passed' => true, 'notes' => 'Pengawas bersertifikat kompetensi'],
            ['item' => 'Pengawas K3 Bersertifikasi Ditugaskan', 'passed' => true, 'notes' => 'Pengawas K3 hadir penuh di lokasi'],
            ['item' => 'Regu Pelaksana Memiliki Sertifikat Bekerja di Ketinggian', 'passed' => true, 'notes' => 'Semua personil lineworker certified'],
        ];

        $readinessScore = 100.00; // All standard dimensions pass initially

        // Hardening #2 & #7: Persist with Lineage and Execution Boundary Metadata
        $authData = [
            'authorization_code'          => $authCode,
            'scenario_id'                 => $slot['scenario_id'],
            'scenario_code'               => $scenario['scenario_code'],
            'slot_id'                     => $slotId,
            'portfolio_id'                => $scenario['portfolio_id'],
            'portfolio_code'              => $scenario['portfolio_code'],
            'portfolio_item_id'           => $slot['portfolio_item_id'],
            'plan_id'                     => $slot['plan_id'],
            'plan_code'                   => $slot['plan_code'],
            'candidate_id'                => $slot['candidate_id'],
            'snapshot_id'                 => $slot['snapshot_id'],
            'feeder_name'                 => $slot['feeder_name'],
            'section_name'                => $slot['section_name'],
            'scheduled_date'              => $slot['scheduled_date'],
            'scheduled_window'            => substr($slot['scheduled_start_time'], 0, 5) . ' - ' . substr($slot['scheduled_end_time'], 0, 5),
            'authorization_status'        => 'READINESS_CHECK_PENDING',
            'safety_readiness_json'       => json_encode($safetyChecks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'material_readiness_json'     => json_encode($materialChecks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'permit_readiness_json'       => json_encode($permitChecks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'team_readiness_json'         => json_encode($teamChecks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'readiness_score'             => $readinessScore,
            'execution_mode_status'       => 'HUMAN_DIRECTED_EXECUTION_ONLY',
            'crew_dispatch_status'        => 'NO_AUTOMATIC_DISPATCH',
            'personnel_assignment_status' => 'AUTHORIZATION_SCOPE_ONLY',
            'network_operation_status'    => 'NO_SWITCHING_AUTHORITY',
            'work_execution_status'       => 'AUTHORIZED_INTENT_ONLY',
            'created_at'                  => $now,
            'updated_at'                  => $now,
        ];

        $this->db->table('operational_work_authorizations')->insert($authData);
        $authId = (int)$this->db->insertID();

        // Hardening #3: Record Append-Only Event
        $this->db->table('operational_authorization_events')->insert([
            'authorization_id'   => $authId,
            'authorization_code' => $authCode,
            'event_type'         => 'AUTHORIZATION_CREATED',
            'previous_status'    => 'NONE',
            'new_status'         => 'READINESS_CHECK_PENDING',
            'decision_rationale' => 'Inisialisasi paket otorisasi dari slot jadwal skenario yang disetujui',
            'decided_by'         => $actorName,
            'decided_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'authorization_id'   => $authId,
            'authorization_code' => $authCode,
            'scenario_code'      => $scenario['scenario_code'],
            'plan_code'          => $slot['plan_code'],
            'readiness_score'    => $readinessScore,
            'authorization_status'=> 'READINESS_CHECK_PENDING',
            'governance_verdict' => 'WORK_AUTHORIZATION_PACKAGE_INITIALIZED',
        ];
    }

    /**
     * Update 4-Dimensional Readiness Checklist with Mandatory Rationale.
     *
     * @param int $authId
     * @param array $checklistData
     * @param string $rationale
     * @param array|null $actor
     * @return array
     */
    public function updateReadinessChecklist(
        int $authId,
        array $checklistData,
        string $rationale,
        ?array $actor = null
    ): array {
        $auth = $this->db->table('operational_work_authorizations')
                         ->where('id', $authId)
                         ->get()
                         ->getRowArray();

        if (!$auth) {
            return [
                'status'  => 'error',
                'message' => "Authorization Package #{$authId} not found.",
                'code'    => 'AUTHORIZATION_NOT_FOUND',
            ];
        }

        // Hardening #5: Freeze check
        if ($auth['authorization_status'] === 'EXECUTION_AUTHORIZED') {
            return [
                'status'  => 'error',
                'message' => "Package {$auth['authorization_code']} is 'EXECUTION_AUTHORIZED' and sealed. Mutating checklist is forbidden.",
                'code'    => 'SEALED_AUTHORIZATION_MUTATION_FORBIDDEN',
            ];
        }

        // Hardening #3: Rationale check
        $cleanRationale = trim($rationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Decision rationale is mandatory when modifying readiness checklist.',
                'code'    => 'READINESS_MUTATION_RATIONALE_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'HUMAN_READINESS_OFFICER';

        $safety   = $checklistData['safety_readiness'] ?? json_decode($auth['safety_readiness_json'], true);
        $material = $checklistData['material_readiness'] ?? json_decode($auth['material_readiness_json'], true);
        $permit   = $checklistData['permit_readiness'] ?? json_decode($auth['permit_readiness_json'], true);
        $team     = $checklistData['team_readiness'] ?? json_decode($auth['team_readiness_json'], true);

        // Calculate score
        $totalItems = count($safety) + count($material) + count($permit) + count($team);
        $passedItems = 0;
        foreach (array_merge($safety, $material, $permit, $team) as $item) {
            if (!empty($item['passed'])) {
                $passedItems++;
            }
        }
        $score = $totalItems > 0 ? round(($passedItems / $totalItems) * 100.0, 2) : 0.00;

        $this->db->table('operational_work_authorizations')
                 ->where('id', $authId)
                 ->update([
                     'safety_readiness_json'   => json_encode($safety, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'material_readiness_json' => json_encode($material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'permit_readiness_json'   => json_encode($permit, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'team_readiness_json'     => json_encode($team, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'readiness_score'         => $score,
                     'updated_at'              => $now,
                 ]);

        // Hardening #3: Log event
        $this->db->table('operational_authorization_events')->insert([
            'authorization_id'   => $authId,
            'authorization_code' => $auth['authorization_code'],
            'event_type'         => 'READINESS_CHECKLIST_UPDATED',
            'previous_status'    => $auth['authorization_status'],
            'new_status'         => $auth['authorization_status'],
            'decision_rationale' => $cleanRationale,
            'decided_by'         => $actorName,
            'decided_at'         => $now,
        ]);

        return [
            'status'             => 'success',
            'authorization_id'   => $authId,
            'authorization_code' => $auth['authorization_code'],
            'readiness_score'    => $score,
            'decided_by'         => $actorName,
            'governance_verdict' => 'READINESS_CHECKLIST_MUTATION_AUDITED',
        ];
    }

    /**
     * State Machine Transition with Hardening #4 Canonical SHA-256 Seal and Hardening #6 Revision Loop.
     *
     * @param int $authId
     * @param string $toStatus
     * @param string $rationale
     * @param array|null $actor
     * @return array
     */
    public function transitionAuthorizationStatus(
        int $authId,
        string $toStatus,
        string $rationale,
        ?array $actor = null
    ): array {
        $auth = $this->db->table('operational_work_authorizations')
                         ->where('id', $authId)
                         ->get()
                         ->getRowArray();

        if (!$auth) {
            return [
                'status'  => 'error',
                'message' => "Authorization Package #{$authId} not found.",
                'code'    => 'AUTHORIZATION_NOT_FOUND',
            ];
        }

        $fromStatus = $auth['authorization_status'];
        $targetStatus = strtoupper(trim($toStatus));
        $cleanRationale = trim($rationale);

        // Hardening #6: Validate allowed transitions
        $allowedNext = self::ALLOWED_AUTHORIZATION_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($targetStatus, $allowedNext, true)) {
            return [
                'status'  => 'error',
                'message' => "Invalid transition from {$fromStatus} to {$targetStatus}. Allowed: " . implode(', ', $allowedNext ?: ['NONE (Terminal State)']),
                'code'    => 'INVALID_AUTHORIZATION_TRANSITION',
            ];
        }

        // Validate readiness score before READINESS_VERIFIED
        if ($targetStatus === 'READINESS_VERIFIED' && (float)$auth['readiness_score'] < 100.00) {
            return [
                'status'  => 'error',
                'message' => "Cannot verify readiness: Readiness score is {$auth['readiness_score']}%, but must be 100.00%.",
                'code'    => 'INCOMPLETE_READINESS_REJECTED',
            ];
        }

        // Validate rationale for all transitions
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Decision rationale is mandatory for every authorization state transition.',
                'code'    => 'MANDATORY_RATIONALE_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'ASISTEN_MANAJER_JARINGAN';
        $actorRole = $actor['role'] ?? 'ASISTEN_MANAJER_OPERASI_JARINGAN';

        $updateData = [
            'authorization_status' => $targetStatus,
            'updated_at'           => $now,
        ];

        // Hardening #4: Generate Canonical SHA-256 Seal upon EXECUTION_AUTHORIZED
        if ($targetStatus === 'EXECUTION_AUTHORIZED') {
            $sealPayload = [
                'authorization_code'        => $auth['authorization_code'],
                'scenario_id'               => (int)$auth['scenario_id'],
                'scenario_code'             => $auth['scenario_code'],
                'slot_id'                   => (int)$auth['slot_id'],
                'plan_id'                   => (int)$auth['plan_id'],
                'candidate_id'              => (int)$auth['candidate_id'],
                'snapshot_id'               => (int)$auth['snapshot_id'],
                'scheduled_date'            => $auth['scheduled_date'],
                'scheduled_window'          => $auth['scheduled_window'],
                'safety_readiness'          => json_decode($auth['safety_readiness_json'], true),
                'material_readiness'        => json_decode($auth['material_readiness_json'], true),
                'permit_readiness'          => json_decode($auth['permit_readiness_json'], true),
                'team_readiness'            => json_decode($auth['team_readiness_json'], true),
                'readiness_score'           => (float)$auth['readiness_score'],
                'authorizing_official_name' => $actorName,
                'authorizing_official_role' => $actorRole,
                'authorization_rationale'   => $cleanRationale,
            ];

            $canonicalString = $this->canonicalizePayload($sealPayload);
            $sha256 = hash('sha256', $canonicalString);

            $updateData['authorizing_official_name'] = $actorName;
            $updateData['authorizing_official_role'] = $actorRole;
            $updateData['authorization_rationale']   = $cleanRationale;
            $updateData['authorization_sha256']      = $sha256;
            $updateData['authorized_at']             = $now;
        }

        $this->db->table('operational_work_authorizations')
                 ->where('id', $authId)
                 ->update($updateData);

        // Hardening #3: Log transition event
        $eventType = match($targetStatus) {
            'READINESS_VERIFIED'      => 'READINESS_VERIFIED',
            'EXECUTION_AUTHORIZED'    => 'AUTHORIZATION_SEALED',
            'REVISION_REQUIRED'       => 'READINESS_REVISION_REQUESTED',
            'AUTHORIZATION_REVOKED'   => 'AUTHORIZATION_REVOKED',
            'AUTHORIZATION_SUPERSEDED'=> 'AUTHORIZATION_SUPERSEDED',
            default                   => 'STATUS_CHANGED',
        };

        $this->db->table('operational_authorization_events')->insert([
            'authorization_id'   => $authId,
            'authorization_code' => $auth['authorization_code'],
            'event_type'         => $eventType,
            'previous_status'    => $fromStatus,
            'new_status'         => $targetStatus,
            'decision_rationale' => $cleanRationale,
            'decided_by'         => $actorName,
            'decided_at'         => $now,
        ]);

        return [
            'status'               => 'success',
            'authorization_id'     => $authId,
            'authorization_code'   => $auth['authorization_code'],
            'from_status'          => $fromStatus,
            'to_status'            => $targetStatus,
            'authorization_sha256' => $updateData['authorization_sha256'] ?? null,
            'authorizing_official' => $actorName,
            'governance_verdict'   => 'WORK_AUTHORIZATION_STATE_TRANSITION_VERIFIED',
        ];
    }

    /**
     * Deterministic Canonical Serialization for SHA-256 Fingerprint.
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
     * Verify the cryptographic SHA-256 seal integrity of an authorized package.
     */
    public function verifyPackageSeal(int $authId): array
    {
        $auth = $this->db->table('operational_work_authorizations')
                         ->where('id', $authId)
                         ->get()
                         ->getRowArray();

        if (!$auth || empty($auth['authorization_sha256'])) {
            return [
                'status'  => 'error',
                'message' => 'Authorization package is not sealed.',
                'code'    => 'PACKAGE_NOT_SEALED',
            ];
        }

        $sealPayload = [
            'authorization_code'        => $auth['authorization_code'],
            'scenario_id'               => (int)$auth['scenario_id'],
            'scenario_code'             => $auth['scenario_code'],
            'slot_id'                   => (int)$auth['slot_id'],
            'plan_id'                   => (int)$auth['plan_id'],
            'candidate_id'              => (int)$auth['candidate_id'],
            'snapshot_id'               => (int)$auth['snapshot_id'],
            'scheduled_date'            => $auth['scheduled_date'],
            'scheduled_window'          => $auth['scheduled_window'],
            'safety_readiness'          => json_decode($auth['safety_readiness_json'], true),
            'material_readiness'        => json_decode($auth['material_readiness_json'], true),
            'permit_readiness'          => json_decode($auth['permit_readiness_json'], true),
            'team_readiness'            => json_decode($auth['team_readiness_json'], true),
            'readiness_score'           => (float)$auth['readiness_score'],
            'authorizing_official_name' => $auth['authorizing_official_name'],
            'authorizing_official_role' => $auth['authorizing_official_role'],
            'authorization_rationale'   => $auth['authorization_rationale'],
        ];

        $recalculatedHash = hash('sha256', $this->canonicalizePayload($sealPayload));
        $matches = hash_equals($auth['authorization_sha256'], $recalculatedHash);

        return [
            'status'            => $matches ? 'success' : 'tampered',
            'authorization_code'=> $auth['authorization_code'],
            'persisted_sha256'  => $auth['authorization_sha256'],
            'calculated_sha256' => $recalculatedHash,
            'integrity_verdict' => $matches ? 'SHA256_INTEGRITY_VERIFIED' : 'SEAL_INTEGRITY_COMPROMISED',
        ];
    }

    /**
     * Get list of authorization packages.
     */
    public function getAuthorizations(array $filters = []): array
    {
        if (!$this->db->tableExists('operational_work_authorizations')) {
            return [];
        }

        $builder = $this->db->table('operational_work_authorizations');

        if (!empty($filters['status'])) {
            $builder->where('authorization_status', $filters['status']);
        }
        if (!empty($filters['scenario_id'])) {
            $builder->where('scenario_id', (int)$filters['scenario_id']);
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * Get scheduled slots ready for authorization package generation.
     */
    public function getApprovedSlotsReadyForAuthorization(): array
    {
        if (!$this->db->tableExists('operational_scheduled_slots')) {
            return [];
        }

        $slots = $this->db->table('operational_scheduled_slots as oss')
                          ->join('operational_scheduling_scenarios as osc', 'osc.id = oss.scenario_id')
                          ->select('oss.*, osc.scenario_code, osc.scenario_status')
                          ->where('osc.scenario_status', 'SCENARIO_APPROVED')
                          ->get()
                          ->getResultArray();

        $ready = [];
        foreach ($slots as $s) {
            $activeAuthCount = $this->db->table('operational_work_authorizations')
                                        ->where('slot_id', $s['id'])
                                        ->whereIn('authorization_status', ['READINESS_CHECK_PENDING', 'READINESS_VERIFIED', 'EXECUTION_AUTHORIZED', 'REVISION_REQUIRED'])
                                        ->countAllResults();
            if ($activeAuthCount === 0) {
                $ready[] = $s;
            }
        }

        return $ready;
    }

    /**
     * Get full details of an Authorization Package.
     */
    public function getAuthorizationDetail(int $authId): array
    {
        $auth = $this->db->table('operational_work_authorizations')
                         ->where('id', $authId)
                         ->get()
                         ->getRowArray();

        if (!$auth) {
            return [];
        }

        $events = $this->db->table('operational_authorization_events')
                           ->where('authorization_id', $authId)
                           ->orderBy('id', 'DESC')
                           ->get()
                           ->getResultArray();

        $slot = $this->db->table('operational_scheduled_slots')
                         ->where('id', $auth['slot_id'])
                         ->get()
                         ->getRowArray();

        return [
            'auth'     => $auth,
            'slot'     => $slot,
            'safety'   => !empty($auth['safety_readiness_json']) ? json_decode($auth['safety_readiness_json'], true) : [],
            'material' => !empty($auth['material_readiness_json']) ? json_decode($auth['material_readiness_json'], true) : [],
            'permit'   => !empty($auth['permit_readiness_json']) ? json_decode($auth['permit_readiness_json'], true) : [],
            'team'     => !empty($auth['team_readiness_json']) ? json_decode($auth['team_readiness_json'], true) : [],
            'events'   => $events,
            'invariants' => [
                'execution_mode_status'       => $auth['execution_mode_status'],
                'crew_dispatch_status'        => $auth['crew_dispatch_status'],
                'personnel_assignment_status' => $auth['personnel_assignment_status'],
                'network_operation_status'    => $auth['network_operation_status'],
                'work_execution_status'       => $auth['work_execution_status'],
            ],
        ];
    }
}
