<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;
use RuntimeException;
use App\Services\HealthIndexEngine;

class ObservationActionLifecycleService
{
    protected BaseConnection $db;
    protected HealthIndexEngine $hiEngine;

    /**
     * Severity to Operational Priority Mapping Matrix
     * 1: Emergency (<=2 Hours), 2: Top (<=24 Hours), 3: High (<=3 Days), 4: Medium (<=7 Days), 5: Normal (Routine)
     */
    protected static array $priorityMap = [
        'EMERGENCY' => 1,
        'CRITICAL'  => 2,
        'HIGH'      => 3,
        'MEDIUM'    => 4,
        'LOW'       => 5,
        'NORMAL'    => 5,
    ];

    /**
     * State Transition Validation Matrix
     */
    protected static array $allowedTransitions = [
        'OPEN' => [
            'ACKNOWLEDGED',
            'EMERGENCY_ACTION_TRIGGERED',
            'SUPERSEDED'
        ],
        'EMERGENCY_ACTION_TRIGGERED' => [
            'IN_PROGRESS',
            'SUPERSEDED'
        ],
        'ACKNOWLEDGED' => [
            'ACTION_PLANNED',
            'SUPERSEDED'
        ],
        'ACTION_PLANNED' => [
            'IN_PROGRESS',
            'SUPERSEDED'
        ],
        'IN_PROGRESS' => [
            'RESOLVED',
            'SUPERSEDED'
        ],
        'RESOLVED' => [
            'VERIFIED',
            'IN_PROGRESS', // Rework / Verification Failed
            'SUPERSEDED'
        ],
        'VERIFIED'   => ['SUPERSEDED'],
        'SUPERSEDED' => [],
    ];

    public function __construct(?BaseConnection $db = null, ?HealthIndexEngine $hiEngine = null)
    {
        $this->db       = $db ?? \Config\Database::connect();
        $this->hiEngine = $hiEngine ?? new HealthIndexEngine();
    }

    /**
     * Create Master Action Case from Field Observation Evidence
     */
    public function createActionCase(
        int $assetId,
        string $obsType,
        int $obsId,
        string $severity,
        ?int $userId = null
    ): array {
        $cType = strtoupper(trim($obsType));
        if (!in_array($cType, ['VEGETATION', 'THERMOVISION'], true)) {
            throw new InvalidArgumentException("Tipe observasi '{$obsType}' tidak valid.");
        }

        $cSev     = strtoupper(trim($severity));
        $priority = self::$priorityMap[$cSev] ?? 5;
        $nowStr   = Time::now('Asia/Jakarta')->toDateTimeString();

        // Fast-track initial status for EMERGENCY
        $initialStatus = ($cSev === 'EMERGENCY') ? 'EMERGENCY_ACTION_TRIGGERED' : 'OPEN';

        $this->db->transStart();

        // 1. Insert Master Action Case
        $this->db->table('observation_action_cases')->insert([
            'asset_id'                => $assetId,
            'source_observation_type' => $cType,
            'source_observation_id'   => $obsId,
            'severity_at_open'        => $cSev,
            'priority'                => $priority,
            'status'                  => $initialStatus,
            'opened_at'               => $nowStr,
            'opened_by'               => $userId,
            'created_at'              => $nowStr,
            'updated_at'              => $nowStr,
        ]);
        $caseId = $this->db->insertID();

        // 2. Record Append-Only Initial Lifecycle Event
        $this->db->table('observation_action_events')->insert([
            'action_case_id' => $caseId,
            'from_status'    => null,
            'to_status'      => $initialStatus,
            'event_type'     => 'ACTION_CASE_OPENED',
            'notes'          => "Action Case #{$caseId} dibuka otomatis dari Observasi {$cType} #{$obsId} (Severity: {$cSev}, Priority: {$priority}).",
            'performed_by'   => $userId,
            'performed_at'   => $nowStr,
            'created_at'     => $nowStr,
        ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException("Gagal membina Action Case untuk observasi #{$obsId}.");
        }

        return [
            'action_case_id' => (int)$caseId,
            'status'         => $initialStatus,
            'priority'       => $priority,
            'opened_at'      => $nowStr,
        ];
    }

    /**
     * Transition Lifecycle State with Strict Guard Validation
     */
    public function transitionStatus(
        int $caseId,
        string $toStatus,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        $cToStatus = strtoupper(trim($toStatus));

        $case = $this->db->table('observation_action_cases')->where('id', $caseId)->get()->getRowArray();
        if (!$case) {
            throw new InvalidArgumentException("Action Case #{$caseId} tidak ditemukan.");
        }

        $fromStatus = $case['status'];

        // Validate Transition Graph
        $allowed = self::$allowedTransitions[$fromStatus] ?? [];
        if (!in_array($cToStatus, $allowed, true)) {
            throw new InvalidArgumentException("Transisi status dari '{$fromStatus}' ke '{$cToStatus}' dilarang oleh aturan lifecycle.");
        }

        $nowStr = Time::now('Asia/Jakarta')->toDateTimeString();
        $updateFields = [
            'status'     => $cToStatus,
            'updated_at' => $nowStr,
        ];

        if ($cToStatus === 'ACKNOWLEDGED') {
            $updateFields['acknowledged_at'] = $nowStr;
            $updateFields['acknowledged_by'] = $userId;
        } elseif ($cToStatus === 'ACTION_PLANNED') {
            $updateFields['planned_at'] = $nowStr;
        } elseif ($cToStatus === 'IN_PROGRESS') {
            $updateFields['started_at'] = $nowStr;
        } elseif ($cToStatus === 'RESOLVED') {
            $updateFields['resolved_at'] = $nowStr;
            $updateFields['resolved_by'] = $userId;
        }

        $useLocalTrans = ($this->db->transDepth === 0);
        if ($useLocalTrans) {
            $this->db->transStart();
        }

        $this->db->table('observation_action_cases')->where('id', $caseId)->update($updateFields);

        $this->db->table('observation_action_events')->insert([
            'action_case_id' => $caseId,
            'from_status'    => $fromStatus,
            'to_status'      => $cToStatus,
            'event_type'     => 'STATUS_TRANSITION',
            'notes'          => $notes ?: "Perubahan status dari {$fromStatus} ke {$cToStatus}.",
            'performed_by'   => $userId,
            'performed_at'   => $nowStr,
            'created_at'     => $nowStr,
        ]);

        if ($useLocalTrans) {
            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                throw new RuntimeException("Gagal mengubah status Action Case #{$caseId}.");
            }
        }

        return [
            'action_case_id' => (int)$caseId,
            'from_status'    => $fromStatus,
            'to_status'      => $cToStatus,
            'updated_at'     => $nowStr,
        ];
    }

    /**
     * Phase 2H.4: Issue Work Order for Action Case
     */
    public function issueWorkOrder(
        int $caseId,
        string $woType = 'CORRECTIVE_MAINTENANCE',
        ?string $scheduledAt = null,
        ?int $userId = null
    ): array {
        $case = $this->db->table('observation_action_cases')->where('id', $caseId)->get()->getRowArray();
        if (!$case) {
            throw new InvalidArgumentException("Action Case #{$caseId} tidak ditemukan.");
        }

        $woNumber = 'WO-STJ-' . date('YmdHis') . '-' . rand(100, 999);
        $nowStr   = Time::now('Asia/Jakarta')->toDateTimeString();

        // 1. Insert Work Order
        $this->db->table('observation_action_work_orders')->insert([
            'action_case_id'    => $caseId,
            'work_order_number' => $woNumber,
            'work_order_type'   => $woType,
            'status'            => 'ISSUED',
            'issued_at'         => $nowStr,
            'scheduled_at'      => $scheduledAt,
            'created_by'        => $userId,
            'created_at'        => $nowStr,
            'updated_at'        => $nowStr,
        ]);
        $woId = $this->db->insertID();

        // 2. Automatically transition Action Case status based on Emergency Fast-Track or Normal Planning
        if ($case['status'] === 'EMERGENCY_ACTION_TRIGGERED') {
            $this->transitionStatus($caseId, 'IN_PROGRESS', "Emergency Work Order #{$woNumber} diterbitkan & diprioritaskan eksekusi langsung.", $userId);
        } elseif (in_array($case['status'], ['OPEN', 'ACKNOWLEDGED'], true)) {
            $this->transitionStatus($caseId, 'ACTION_PLANNED', "Work Order #{$woNumber} telah diterbitkan.", $userId);
        }

        return [
            'work_order_id'     => (int)$woId,
            'work_order_number' => $woNumber,
            'status'            => 'ISSUED',
        ];
    }

    /**
     * Phase 2H.3: Verification & Health Index Recovery Contract
     * Mandatory: Verified status requires new valid After Evidence observation
     */
    public function verifyAndRecoverRisk(
        int $caseId,
        array $afterObservationData,
        int $userId,
        ?string $verificationNotes = null
    ): array {
        $case = $this->db->table('observation_action_cases')->where('id', $caseId)->get()->getRowArray();
        if (!$case) {
            throw new InvalidArgumentException("Action Case #{$caseId} tidak ditemukan.");
        }

        if ($case['status'] !== 'RESOLVED') {
            throw new InvalidArgumentException("Verifikasi perbaikan hanya dapat dilakukan pada Action Case berstatus 'RESOLVED'. Status saat ini: '{$case['status']}'.");
        }

        $assetId   = (int)$case['asset_id'];
        $obsType   = $case['source_observation_type'];
        $oldObsId  = (int)$case['source_observation_id'];
        $nowStr    = Time::now('Asia/Jakarta')->toDateTimeString();

        $this->db->transStart();

        // 1. Invalidate Old Observation (Append-Only Supersedes Flow)
        $obsTable = ($obsType === 'VEGETATION') ? 'vegetation_observations' : 'thermovision_observations';
        $this->db->table($obsTable)->where('id', $oldObsId)->update([
            'is_valid'            => 0,
            'invalidated_at'      => $nowStr,
            'invalidated_by'      => $userId,
            'invalidation_reason' => 'SUPERSEDED_BY_VERIFIED_REPAIR_EVIDENCE',
        ]);

        // 2. Insert New Valid After Evidence Observation
        $newObsData = array_merge($afterObservationData, [
            'asset_id'                  => $assetId,
            'supersedes_observation_id' => $oldObsId,
            'is_valid'                  => 1,
            'observed_by'               => $userId,
            'observed_at'               => $nowStr,
            'created_at'                => $nowStr,
            'updated_at'                => $nowStr,
        ]);

        $this->db->table($obsTable)->insert($newObsData);
        $newObsId = $this->db->insertID();

        // 3. Update Action Case Status to VERIFIED
        $this->db->table('observation_action_cases')->where('id', $caseId)->update([
            'status'      => 'VERIFIED',
            'verified_at' => $nowStr,
            'verified_by' => $userId,
            'updated_at'  => $nowStr,
        ]);

        // 4. Record Append-Only Verification Event
        $this->db->table('observation_action_events')->insert([
            'action_case_id' => $caseId,
            'from_status'    => 'RESOLVED',
            'to_status'      => 'VERIFIED',
            'event_type'     => 'VERIFIED_WITH_RECOVERY_EVIDENCE',
            'notes'          => "Verifikasi perbaikan LULUS oleh Supervisor #{$userId}. Bukti perbaikan baru #{$newObsId} dicatat: " . ($verificationNotes ?: 'Kondisi fisik aset dinyatakan pulih.'),
            'performed_by'   => $userId,
            'performed_at'   => $nowStr,
            'created_at'     => $nowStr,
        ]);

        // 5. Trigger Authoritative HealthIndexEngine Recalculation & Atomic Persistence
        $hiResult = $this->hiEngine->persistHealthIndexCalculation($assetId, 'ACTION_VERIFIED_RECOVERY', $userId);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException("Gagal menyelesaikan verifikasi dan pemulihan Health Index untuk Case #{$caseId}.");
        }

        return [
            'action_case_id'     => (int)$caseId,
            'status'             => 'VERIFIED',
            'new_observation_id' => (int)$newObsId,
            'hi_final_score'     => $hiResult['final_score'],
            'hi_category'        => $hiResult['category'],
            'calculation_hash'   => $hiResult['calculation_hash'],
            'verified_at'        => $nowStr,
        ];
    }
}
