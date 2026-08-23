<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class PrescriptiveDecisionService
{
    protected BaseConnection $db;
    protected PredictiveRiskService $predictService;
    protected NetworkTopologyService $topoService;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db             = $db ?? \Config\Database::connect();
        $this->predictService = new PredictiveRiskService($this->db);
        $this->topoService    = new NetworkTopologyService($this->db);
    }

    /**
     * Generate Prescriptive Decision Recommendation for a specific Asset (Phase 2O)
     */
    public function generatePrescriptiveRecommendation(int $assetId): array
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

        // Fetch Predictive Forecast (Phase 2N)
        $forecast = $this->predictService->predictAssetRiskForecast($assetId);
        
        // Fetch Network Impact & Isolation Scenario (Phase 2M)
        $impact = $this->topoService->analyzeAssetImpact($assetId);

        // Determine Prescriptive Action & Execution Window
        $pmpIndex       = (float)($forecast['predictive_maintenance_pmp'] ?? 0.0);
        $escalationProb = (float)($forecast['escalation_probability_pct'] ?? 0.0);
        $priorityCode   = $impact['active_case_priority'] ?? 'P5';

        $action = "Lakukan inspeksi dan pemeliharaan rutin terpandu.";
        $window = "Dalam 30 hari ke depan (Jadwal Pemeliharaan Berkala).";
        
        if ($priorityCode === 'P1') {
            $action = "EKSEKUSI PEMANGKASAN VEGETASI / REPARASI HOTSPOT DARURAT KATEGORI EMERGENCY.";
            $window = "SANGAT MENDESAK: Dalam 24-72 Jam (SLA P1 Emergency).";
        } elseif ($priorityCode === 'P2') {
            $action = "Lakukan tindakan perbaikan preventif prioritas tinggi pada komponen kritis.";
            $window = "MENDESAK: Dalam 72 Jam (SLA P2 Critical).";
        } elseif ($priorityCode === 'P3' || $pmpIndex >= 50.0) {
            $action = "Jadwalkan pemeliharaan preventif vegetasi / konektor isolator sebelum terjadi eskalasi.";
            $window = "OPTIMAL: Dalam 7-14 hari ke depan (Sebelum proyeksi HI 30-hari turun).";
        } elseif ($priorityCode === 'P4') {
            $action = "Jadwalkan perbaikan rutin pada siklus pemeliharaan bulanan.";
            $window = "TERJADWAL: Dalam 30 hari ke depan (SLA P4 Medium).";
        }

        // Expected Risk Reduction
        $currentScore = (float)($forecast['current_health_score'] ?? 100.0);
        $recoveredHi  = min(100.0, max($currentScore, 74.0));
        $expectedResult = [
            'expected_hi_recovery_score' => $recoveredHi,
            'expected_category'          => ($recoveredHi >= 70.0) ? 'GOOD' : 'FAIR',
            'risk_reduction_level'       => 'HIGH_RISK_REDUCTION',
            'sla_breach_exposure'        => 'AVOIDED',
        ];

        // Consequence of Deferral
        $vipCount   = count($impact['vip_customers_affected'] ?? []);
        $kvaImpact  = $impact['estimated_kva_impact'] ?? 0;
        $deferralRisk = [
            'escalation_probability'     => "Probabilitas eskalasi gangguan tetap {$escalationProb}%",
            'potential_load_interruption' => "Potensi gangguan pemadaman hingga {$kvaImpact} kVA di segmen hilir",
            'vip_customer_exposure'      => "Fasilitas kritis terdampak: {$vipCount} pelanggan VIP",
            'network_propagation_risk'   => $impact['propagation_risk_level'] ?? 'LOCALIZED_IMPACT',
        ];

        return [
            'status'                         => 'success',
            'asset_id'                       => $assetId,
            'nama_asset'                     => $asset['nama_asset'],
            'current_health_score'           => $currentScore,
            'projected_30d_hi'               => $forecast['projected_score_30d'] ?? $currentScore,
            'priority_code'                  => $priorityCode,
            'pmp_index'                      => $pmpIndex,
            'prescriptive_recommendation'    => [
                'recommended_action'           => $action,
                'recommended_execution_window' => $window,
                'recommended_isolation_plan'   => $impact['recommended_maneuver'] ?? [],
                'expected_result'              => $expectedResult,
                'consequence_of_deferral'      => $deferralRisk,
            ],
            'prescriptive_version'           => 'PRESCRIPTIVE_ENGINE_v1.0',
        ];
    }
}
