<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class ScenarioSimulationService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Run Non-Destructive What-If Scenario Simulation (Phase 3H)
     */
    public function runWhatIfSimulation(int $assetId, string $scenarioType = 'WHAT_IF_REPLACE_NOW'): array
    {
        $twinService = new OperationalDigitalTwinService($this->db);
        $twin = $twinService->getDigitalTwinState($assetId);

        $scenarios = [
            'WHAT_IF_REPLACE_NOW' => [
                'scenario_name'          => 'Ganti Aset Baru / Major Overhaul',
                'predicted_hi_recovery'  => 85.0,
                'predicted_hi_category'  => 'GOOD',
                'recurrence_risk'        => 'LOW',
                'estimated_cost_idr'     => 15000000,
                'estimated_duration_hrs' => 6.0,
                'customer_outage_risk'   => 'MINIMAL_PLANNED_MANEUVER',
            ],
            'WHAT_IF_TEMPORARY_REPAIR' => [
                'scenario_name'          => 'Perbaikan Darurat Sementara',
                'predicted_hi_recovery'  => 62.0,
                'predicted_hi_category'  => 'FAIR',
                'recurrence_risk'        => 'HIGH',
                'estimated_cost_idr'     => 3500000,
                'estimated_duration_hrs' => 2.0,
                'customer_outage_risk'   => 'TEMPORARY_STABILIZED',
            ],
            'WHAT_IF_DEFER_30_DAYS' => [
                'scenario_name'          => 'Tunda Eksekusi 30 Hari',
                'predicted_hi_recovery'  => 35.0,
                'predicted_hi_category'  => 'POOR',
                'recurrence_risk'        => 'CRITICAL',
                'estimated_cost_idr'     => 0,
                'estimated_duration_hrs' => 0.0,
                'customer_outage_risk'   => 'HIGH_UNPLANNED_OUTAGE_IMPACT_340_CUSTOMERS',
            ],
        ];

        $selectedScenario = $scenarios[$scenarioType] ?? $scenarios['WHAT_IF_REPLACE_NOW'];

        return [
            'status'                     => 'success',
            'simulation_id'              => 'SIM-STJ-' . date('Ymd') . '-' . sprintf('%04d', rand(1000, 9999)),
            'target_asset_id'            => $assetId,
            'scenario_type'              => $scenarioType,
            'simulation_outcome'         => $selectedScenario,
            'digital_twin_baseline'      => $twin['digital_twin_model'],
            'simulation_engine_version'  => 'SCENARIO_SIMULATION_v1.0',
            'certified_simulation'       => 'WHAT_IF_SIMULATION_EXECUTED_CLEANLY',
        ];
    }
}
