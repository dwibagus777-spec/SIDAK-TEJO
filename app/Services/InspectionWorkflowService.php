<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * InspectionWorkflowService
 * 
 * Governs GIS Inspection Lifecycle States:
 * - PLANNED_PENDING (🟠): Scheduled in planning, pending field inspection
 * - IN_PROGRESS (🔵): Inspector currently inspecting the asset
 * - INSPECTED (🟢): Inspection completed
 * - NOT_PLANNED (⚪): Outside operational planning
 * 
 * CORE INVARIANT:
 * INSPECTED != HAS_FINDING
 * An asset can be marked INSPECTED with finding_count = 0 (healthy asset with no defects).
 */
class InspectionWorkflowService
{
    public const STATE_PLANNED_PENDING = 'PLANNED_PENDING';
    public const STATE_IN_PROGRESS     = 'IN_PROGRESS';
    public const STATE_INSPECTED       = 'INSPECTED';
    public const STATE_NOT_PLANNED     = 'NOT_PLANNED';

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Get the authoritative inspection lifecycle state for an asset.
     */
    public function getLifecycleState(int $assetId, ?int $planningId = null): array
    {
        $planningId = $planningId ?? 0;

        if (!$this->db->tableExists('asset_inspection_states')) {
            return [
                'status'        => self::STATE_NOT_PLANNED,
                'finding_count' => 0,
                'inspected_at'  => null,
                'inspected_by'  => null,
                'color'         => '#94a3b8', // Gray
                'label'         => 'Tidak Masuk Planning',
            ];
        }

        $row = $this->db->table('asset_inspection_states')
            ->where('asset_id', $assetId)
            ->where('planning_id', $planningId)
            ->get()
            ->getRowArray();

        if ($row) {
            $status = (string)$row['status'];
            return [
                'status'        => $status,
                'finding_count' => (int)$row['finding_count'],
                'inspected_at'  => $row['inspected_at'],
                'inspected_by'  => $row['inspected_by'] ? (int)$row['inspected_by'] : null,
                'color'         => $this->getStatusColor($status),
                'label'         => $this->getStatusLabel($status),
            ];
        }

        // Check if asset is part of an active planning
        $isPlanned = false;
        if ($planningId > 0 && $this->db->tableExists('inspection_planning_assets')) {
            $planAsset = $this->db->table('inspection_planning_assets')
                ->where('planning_id', $planningId)
                ->where('asset_id', $assetId)
                ->get()
                ->getRowArray();
            $isPlanned = !empty($planAsset);
        }

        $defaultStatus = $isPlanned ? self::STATE_PLANNED_PENDING : self::STATE_NOT_PLANNED;

        return [
            'status'        => $defaultStatus,
            'finding_count' => 0,
            'inspected_at'  => null,
            'inspected_by'  => null,
            'color'         => $this->getStatusColor($defaultStatus),
            'label'         => $this->getStatusLabel($defaultStatus),
        ];
    }

    /**
     * Record an inspection event with a finding (increments finding count).
     */
    public function recordFindingInspection(int $assetId, ?int $planningId = null, ?int $inspectorId = null, ?string $notes = null): array
    {
        return $this->mutateInspectionState($assetId, $planningId, self::STATE_INSPECTED, 1, $inspectorId, $notes);
    }

    /**
     * Record a clean inspection event (no findings; finding_count unchanged or initialized to 0).
     * Enforces invariant: INSPECTED != HAS_FINDING.
     */
    public function recordCleanInspection(int $assetId, ?int $planningId = null, ?int $inspectorId = null, ?string $notes = null): array
    {
        return $this->mutateInspectionState($assetId, $planningId, self::STATE_INSPECTED, 0, $inspectorId, $notes);
    }

    /**
     * Mark an asset as in-progress (operator began inspecting).
     */
    public function markInProgress(int $assetId, ?int $planningId = null, ?int $inspectorId = null): array
    {
        return $this->mutateInspectionState($assetId, $planningId, self::STATE_IN_PROGRESS, 0, $inspectorId, null);
    }

    /**
     * Internal atomic state mutation.
     */
    protected function mutateInspectionState(
        int $assetId,
        ?int $planningId,
        string $status,
        int $findingCountDelta,
        ?int $inspectorId,
        ?string $notes
    ): array {
        $planningId = $planningId ?? 0;
        $now = date('Y-m-d H:i:s');

        if (!$this->db->tableExists('asset_inspection_states')) {
            return [
                'status'        => $status,
                'finding_count' => $findingCountDelta,
                'updated'       => false,
            ];
        }

        $existing = $this->db->table('asset_inspection_states')
            ->where('asset_id', $assetId)
            ->where('planning_id', $planningId)
            ->get()
            ->getRowArray();

        if ($existing) {
            $newFindingCount = (int)$existing['finding_count'] + $findingCountDelta;
            $updateData = [
                'status'        => $status,
                'finding_count' => $newFindingCount,
                'updated_at'    => $now,
            ];
            if ($status === self::STATE_INSPECTED) {
                $updateData['inspected_at'] = $now;
                if ($inspectorId) {
                    $updateData['inspected_by'] = $inspectorId;
                }
            }
            if ($notes !== null) {
                $updateData['notes'] = $notes;
            }

            $this->db->table('asset_inspection_states')
                ->where('id', (int)$existing['id'])
                ->update($updateData);

            return [
                'status'        => $status,
                'finding_count' => $newFindingCount,
                'updated'       => true,
            ];
        }

        $insertData = [
            'planning_id'   => $planningId,
            'asset_id'      => $assetId,
            'status'        => $status,
            'finding_count' => max(0, $findingCountDelta),
            'notes'         => $notes,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        if ($status === self::STATE_INSPECTED) {
            $insertData['inspected_at'] = $now;
            $insertData['inspected_by'] = $inspectorId;
        }

        $this->db->table('asset_inspection_states')->insert($insertData);

        return [
            'status'        => $status,
            'finding_count' => max(0, $findingCountDelta),
            'updated'       => true,
        ];
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            self::STATE_PLANNED_PENDING => '#f97316', // Orange 🟠
            self::STATE_IN_PROGRESS     => '#0284c7', // Blue 🔵
            self::STATE_INSPECTED       => '#16a34a', // Green 🟢
            default                     => '#94a3b8', // Gray ⚪
        };
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            self::STATE_PLANNED_PENDING => 'Belum Diinspeksi (Rencana)',
            self::STATE_IN_PROGRESS     => 'Sedang Diinspeksi',
            self::STATE_INSPECTED       => 'Sudah Diinspeksi',
            default                     => 'Tidak Masuk Planning',
        };
    }
}
