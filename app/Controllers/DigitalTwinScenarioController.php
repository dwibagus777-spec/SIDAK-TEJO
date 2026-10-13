<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\DigitalTwinScenarioService;
use CodeIgniter\HTTP\ResponseInterface;

class DigitalTwinScenarioController extends BaseController
{
    protected $service;

    public function __construct()
    {
        $this->service = new DigitalTwinScenarioService();
    }

    /**
     * Render Digital Twin Command Center Workspace
     */
    public function workspace()
    {
        $summary = $this->service->getOperationalSummary();
        $scenarios = $this->service->listScenarios(10);

        $data = [
            'title'     => 'Digital Twin & Scenario Simulation Command Center',
            'summary'   => $summary,
            'scenarios' => $scenarios
        ];

        return view('digital_twin/workspace', $data);
    }

    /**
     * API: Get Top Summary & Baseline KPIs
     */
    public function summary(): ResponseInterface
    {
        $summary = $this->service->getOperationalSummary();
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $summary
        ]);
    }

    /**
     * API: Execute What-If Scenario Simulation
     */
    public function runSimulation(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();

        if (empty($json['scenario_type'])) {
            $json['scenario_type'] = 'MAINTENANCE_SCENARIO';
        }

        $result = $this->service->runSimulation($json);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result
        ]);
    }

    /**
     * API: List Scenarios
     */
    public function scenarios(): ResponseInterface
    {
        $list = $this->service->listScenarios(50);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $list
        ]);
    }

    /**
     * API: Get Side-by-Side Comparison KPI for a scenario
     */
    public function compare(string $uuid): ResponseInterface
    {
        $scenario = $this->service->getScenarioByUuid($uuid);
        if (!$scenario) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Scenario not found'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'scenario_uuid'       => $scenario['scenario_uuid'],
                'scenario_name'       => $scenario['scenario_name'],
                'scenario_type'       => $scenario['scenario_type'],
                'status'              => $scenario['status'],
                'simulation_results'  => json_decode($scenario['simulation_results_json'], true),
                'comparison_kpi'      => json_decode($scenario['comparison_kpi_json'], true),
                'input_fingerprint'   => $scenario['input_fingerprint'],
                'output_fingerprint'  => $scenario['output_fingerprint'],
                'forensic_bundle_hash'=> $scenario['forensic_bundle_hash']
            ]
        ]);
    }

    /**
     * API: Get Forensic Bundle
     */
    public function forensicBundle(string $uuid): ResponseInterface
    {
        $bundle = $this->service->getForensicBundle($uuid);
        if (!$bundle) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Forensic bundle not found'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $bundle
        ]);
    }

    /**
     * API: Submit L4 Recommendation
     */
    public function submitRecommendation(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $uuid = $json['scenario_uuid'] ?? '';

        $result = $this->service->submitRecommendation($uuid);
        return $this->response->setJSON($result);
    }

    /**
     * API: L5 Human Approval
     */
    public function humanApprove(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $uuid = $json['scenario_uuid'] ?? '';
        $approver = $json['approved_by'] ?? 'EXECUTIVE_SUPERVISOR';

        $result = $this->service->humanApprove($uuid, $approver);
        return $this->response->setJSON($result);
    }
}
