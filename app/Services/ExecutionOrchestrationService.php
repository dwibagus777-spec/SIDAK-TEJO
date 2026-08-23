<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class ExecutionOrchestrationService
{
    protected BaseConnection $db;
    protected PrescriptiveDecisionService $prescriptiveService;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db                  = $db ?? \Config\Database::connect();
        $this->prescriptiveService = new PrescriptiveDecisionService($this->db);
    }

    /**
     * Generate Work Package & Resource Allocation for specific Asset (Phase 2P)
     */
    public function generateWorkPackage(int $assetId): array
    {
        $asset = $this->db->table('assets')
            ->where('id', $assetId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$asset) {
            return [
                'status'  => 'error',
                'message' => "Asset #{$assetId} not found.",
            ];
        }

        // Fetch Prescriptive Decision Package (Phase 2O)
        $prescriptive = $this->prescriptiveService->generatePrescriptiveRecommendation($assetId);
        $rec          = $prescriptive['prescriptive_recommendation'] ?? [];
        $priorityCode = $prescriptive['priority_code'] ?? 'P5';

        // Work Package Details Calculation
        $wpCode   = 'WP-STJ-' . date('Ymd') . '-' . sprintf('%03d', $assetId);
        $scope    = $rec['recommended_action'] ?? 'Pemeliharaan preventif terpadu.';
        $window   = $rec['recommended_execution_window'] ?? 'Dalam 30 hari.';

        // Crew Allocation & Tool Checklist based on Domain/Priority
        $crewType  = 'REGU_HAR_DISTRIBUSI_ULP';
        $materials = ['APD K3 Lengkap (Helm, Sabuk Keselamatan, Sarung Tangan 20kV)', 'Multimeter / Tang Ampere Digital', 'Toolkit Pemeliharaan Gardu'];
        $manHours  = 3.5;

        if ($priorityCode === 'P1') {
            $crewType  = 'REGU_PDKB_20KV_SENTUH_LANGSUNG';
            $materials = array_merge($materials, ['Hotstick PDKB 20kV', 'Insulated Ladder / Mobil Crane Bucket', 'Gergaji Mesin Trim ROW High-Voltage']);
            $manHours  = 5.0;
        } elseif (str_contains(strtolower($scope), 'pemangkasan') || str_contains(strtolower($scope), 'row')) {
            $crewType  = 'REGU_RABAS_VEGETASI_ROW';
            $materials = array_merge($materials, ['Gergaji Mesin (Chainsaw)', 'Tali Pengaman Ketinggian', 'Truk Pengangkut Dahan']);
            $manHours  = 4.0;
        }

        $workPackage = [
            'work_package_code'            => $wpCode,
            'asset_id'                     => $assetId,
            'nama_asset'                   => $asset['nama_asset'],
            'priority_code'                => $priorityCode,
            'scope_of_work'                => $scope,
            'assigned_crew_type'           => $crewType,
            'required_materials_and_tools' => $materials,
            'estimated_man_hours'          => $manHours,
            'execution_window_target'      => $window,
            'isolation_maneuver_plan'      => $rec['recommended_isolation_plan'] ?? [],
            'dispatch_approval_status'     => 'APPROVED_FOR_DISPATCH',
            'authorized_by'                => 'MANAJER_ULP_DAN_DALOPS (Auto-Orchestrated)',
            'created_at'                   => date('Y-m-d H:i:s'),
        ];

        return [
            'status'                => 'success',
            'work_package'          => $workPackage,
            'orchestration_version' => 'EXECUTION_ORCHESTRATION_v1.0',
        ];
    }
}
