<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class AdaptiveOperationalExperienceService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Build Role-Aware Adaptive Operational Workspace (Phase 4C)
     */
    public function getRoleAdaptiveWorkspace(string $role = 'PETUGAS_LAPANGAN', int $assetId = 1): array
    {
        $db = $this->db;

        $actionService = new UnifiedOperationalWorkspaceService($db);
        $baseWorkspace = $actionService->getAssetActionWorkspace($assetId);

        $roleProfiles = [
            'PETUGAS_LAPANGAN' => [
                'role_name'            => 'Petugas Lapangan / Regu Pemeliharaan',
                'primary_focus'        => 'Eksekusi Lapangan, Bukti Foto Before/After & Material Log',
                'allowed_actions'      => ['CAPTURE_BEFORE_EVIDENCE', 'LOG_MATERIAL_MANHOURS', 'SUBMIT_REPAIR_COMPLETED'],
                'next_best_action'     => 'Ambil Bukti Foto Sebelum Pengerjaan & Verifikasi Koordinat Aset',
                'workspace_template'   => 'MOBILE_FIELD_EXECUTION_VIEW',
            ],
            'SUPERVISOR_ULP' => [
                'role_name'            => 'Supervisor ULP Sidoarjo Kota',
                'primary_focus'        => 'Review Decision Explainability, Persetujuan Preskripsi & Verifikasi Risik',
                'allowed_actions'      => ['APPROVE_RECOMMENDATION', 'OVERRIDE_DECISION', 'TRIGGER_ESCALATION', 'VERIFY_RISK_RECOVERY'],
                'next_best_action'     => 'Review Decision Explainability Panel & Berikan Otorisasi Persetujuan',
                'workspace_template'   => 'SUPERVISOR_GOVERNANCE_VIEW',
            ],
            'COMMAND_CENTER_OPERATOR' => [
                'role_name'            => 'Operator Command Center & Grid Dispatch',
                'primary_focus'        => 'Monitoring Real-time, What-If Simulation Studio & Dispatch Dispatcher',
                'allowed_actions'      => ['RUN_WHAT_IF_SIMULATION', 'DISPATCH_WORK_PACKAGE', 'BROADCAST_ALERT'],
                'next_best_action'     => 'Pantau Heatmap Risiko Penyulang & Jalankan Simulasi Intervensi',
                'workspace_template'   => 'COMMAND_CENTER_SITUATION_VIEW',
            ],
            'MANAJER_ULP_DAN_DALOPS' => [
                'role_name'            => 'Manajer ULP & Dalops Executive',
                'primary_focus'        => 'Executive Dashboard KPI, Risk Exposure & Emergency Override',
                'allowed_actions'      => ['EMERGENCY_OVERRIDE', 'APPROVE_VIP_MANEUVER', 'REVIEW_SRE_METRICS'],
                'next_best_action'     => 'Evaluasi Pemenuhan Target SLA & Risk Exposure Beban Penyulang',
                'workspace_template'   => 'EXECUTIVE_COMMAND_VIEW',
            ],
        ];

        $selectedProfile = $roleProfiles[$role] ?? $roleProfiles['PETUGAS_LAPANGAN'];

        return [
            'status'                     => 'success',
            'requested_role'             => $role,
            'role_profile'               => $selectedProfile,
            'asset_action_workspace'     => $baseWorkspace['action_workspace'],
            'adaptive_experience_version'=> 'ADAPTIVE_OPERATIONAL_EXPERIENCE_v1.0',
            'certified_experience_status'=> 'ADAPTIVE_WORKSPACE_ACTIVE',
        ];
    }
}
