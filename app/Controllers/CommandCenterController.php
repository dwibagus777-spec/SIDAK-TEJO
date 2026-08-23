<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OperationalIntelligenceService;
use App\Services\NetworkTopologyService;
use App\Services\PredictiveRiskService;
use App\Services\PrescriptiveDecisionService;
use App\Services\ExecutionOrchestrationService;
use App\Services\ExecutionFeedbackService;
use App\Services\ProductionHardeningService;
use App\Services\OperationalResilienceService;
use App\Services\SystemObservabilityService;
use CodeIgniter\HTTP\ResponseInterface;

class CommandCenterController extends BaseController
{
    protected OperationalIntelligenceService $opService;
    protected NetworkTopologyService $topoService;
    protected PredictiveRiskService $predictService;
    protected PrescriptiveDecisionService $prescriptiveService;
    protected ExecutionOrchestrationService $orchestratorService;
    protected ExecutionFeedbackService $feedbackService;
    protected ProductionHardeningService $hardeningService;
    protected OperationalResilienceService $resilienceService;
    protected SystemObservabilityService $observabilityService;

    public function __construct()
    {
        $this->opService            = new OperationalIntelligenceService();
        $this->topoService          = new NetworkTopologyService();
        $this->predictService       = new PredictiveRiskService();
        $this->prescriptiveService   = new PrescriptiveDecisionService();
        $this->orchestratorService  = new ExecutionOrchestrationService();
        $this->feedbackService      = new ExecutionFeedbackService();
        $this->hardeningService     = new ProductionHardeningService();
        $this->resilienceService    = new OperationalResilienceService();
        $this->observabilityService = new SystemObservabilityService();
    }

    /**
     * GET /command-center
     * Renders Operational Intelligence Command Center Dashboard View
     */
    public function index(): string
    {
        $db = \Config\Database::connect();

        // 1. Fetch Aggregated Metrics
        $metrics = $this->opService->getOperationalDashboardMetrics();

        // 2. Fetch Active Action Cases with Asset Information
        $activeCases = $db->table('observation_action_cases c')
            ->select('c.*, a.nama_asset, a.kode_asset, a.health_score, a.health_category')
            ->join('assets a', 'a.id = c.asset_id', 'inner')
            ->whereNotIn('c.status', ['VERIFIED', 'SUPERSEDED'])
            ->orderBy('c.priority', 'ASC')
            ->orderBy('c.opened_at', 'ASC')
            ->get()
            ->getResultArray();

        // Enrich Action Cases with SLA Calculations
        foreach ($activeCases as &$c) {
            $pCode = 'P' . ($c['priority'] ?? 5);
            $slaHours = ($pCode === 'P5') ? null : (int)OperationalIntelligenceService::resolveRiskPriority($c['severity_at_open'])['resolution_sla_hrs'];
            $c['sla_info'] = OperationalIntelligenceService::calculateSlaStatus($c['opened_at'], $slaHours);
        }
        unset($c);

        $data = [
            'title'       => 'Operational Intelligence Command Center',
            'metrics'     => $metrics,
            'activeCases' => $activeCases,
        ];

        return view('command_center/index', $data);
    }

    /**
     * GET /command-center/api-data
     * API Data Feed for Live Dashboard Updates
     */
    public function apiData(): ResponseInterface
    {
        $db = \Config\Database::connect();

        $metrics = $this->opService->getOperationalDashboardMetrics();
        $activeCasesCount = $db->table('observation_action_cases')
            ->whereNotIn('status', ['VERIFIED', 'SUPERSEDED'])
            ->countAllResults();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'total_active_cases' => $activeCasesCount,
                'metrics'            => $metrics,
                'timestamp'          => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * GET /command-center/geo-data
     * GeoSpatial API Feed for Command Center Map Visualizer (Phase 2K)
     */
    public function geoData(): ResponseInterface
    {
        $db = \Config\Database::connect();

        $assets = $db->table('assets a')
            ->select('a.id, a.nama_asset, a.kode_asset, a.latitude, a.longitude, a.health_score, a.health_category, a.lokasi')
            ->where('a.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $features = [];
        $baseLat  = -7.4478;
        $baseLng  = 112.7183;

        foreach ($assets as $idx => $asset) {
            $lat = !empty($asset['latitude']) ? (float)$asset['latitude'] : ($baseLat + (($idx % 5) * 0.008) - 0.015);
            $lng = !empty($asset['longitude']) ? (float)$asset['longitude'] : ($baseLng + (floor($idx / 5) * 0.008) - 0.015);

            $activeCase = $db->table('observation_action_cases')
                ->where('asset_id', $asset['id'])
                ->whereNotIn('status', ['VERIFIED', 'SUPERSEDED'])
                ->orderBy('priority', 'ASC')
                ->get()
                ->getRowArray();

            $priorityCode = $activeCase ? 'P' . ($activeCase['priority'] ?? 5) : 'P5';
            $slaHours     = ($priorityCode === 'P5') ? null : (int)OperationalIntelligenceService::resolveRiskPriority($activeCase['severity_at_open'] ?? 'NORMAL')['resolution_sla_hrs'];
            $slaInfo      = $activeCase ? OperationalIntelligenceService::calculateSlaStatus($activeCase['opened_at'], $slaHours) : ['sla_status' => 'ON_TRACK'];

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type'        => 'Point',
                    'coordinates' => [$lng, $lat],
                ],
                'properties' => [
                    'asset_id'        => (int)$asset['id'],
                    'nama_asset'      => $asset['nama_asset'],
                    'kode_asset'      => $asset['kode_asset'],
                    'lokasi'          => $asset['lokasi'] ?? 'Sidoarjo',
                    'health_score'    => (float)($asset['health_score'] ?? 100),
                    'health_category' => $asset['health_category'] ?? 'EXCELLENT',
                    'active_case_id'  => $activeCase ? (int)$activeCase['id'] : null,
                    'priority_code'   => $priorityCode,
                    'severity'        => $activeCase['severity_at_open'] ?? 'NORMAL',
                    'case_status'     => $activeCase['status'] ?? 'NORMAL',
                    'sla_status'      => $slaInfo['sla_status'],
                ],
            ];
        }

        return $this->response->setJSON([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * GET /command-center/asset-impact/(:num)
     * Network Impact Propagation Analysis API for specific Asset (Phase 2L/2M)
     */
    public function assetImpact(int $assetId): ResponseInterface
    {
        $analysis = $this->topoService->analyzeAssetImpact($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $analysis,
        ]);
    }

    /**
     * GET /command-center/feeder-nri/(:segment)
     * Feeder Network Risk Index API (Phase 2L/2M)
     */
    public function feederNri(string $feederCode): ResponseInterface
    {
        $analysis = $this->topoService->calculateFeederNetworkRiskIndex($feederCode);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $analysis,
        ]);
    }

    /**
     * GET /command-center/asset-forecast/(:num)
     * Predictive Risk & Health Index Forecasting API for specific Asset (Phase 2N)
     */
    public function assetForecast(int $assetId): ResponseInterface
    {
        $forecast = $this->predictService->predictAssetRiskForecast($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $forecast,
        ]);
    }

    /**
     * GET /command-center/feeder-forecast/(:segment)
     * Feeder Risk Concentration & 30-Day Degradation Forecast API (Phase 2N)
     */
    public function feederForecast(string $feederCode): ResponseInterface
    {
        $forecast = $this->predictService->predictFeederRiskConcentration($feederCode);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $forecast,
        ]);
    }

    /**
     * GET /command-center/asset-prescriptive/(:num)
     * Prescriptive Decision Recommendation API for specific Asset (Phase 2O)
     */
    public function assetPrescriptive(int $assetId): ResponseInterface
    {
        $recommendation = $this->prescriptiveService->generatePrescriptiveRecommendation($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $recommendation,
        ]);
    }

    /**
     * GET /command-center/asset-work-package/(:num)
     * Work Package & Resource Orchestration API for specific Asset (Phase 2P)
     */
    public function assetWorkPackage(int $assetId): ResponseInterface
    {
        $package = $this->orchestratorService->generateWorkPackage($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $package,
        ]);
    }

    /**
     * GET /command-center/execution-feedback/(:num)
     * Execution Feedback, Actualization & Learning Loop API for specific Asset (Phase 2Q)
     */
    public function executionFeedback(int $assetId): ResponseInterface
    {
        $feedback = $this->feedbackService->recordExecutionFeedback($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $feedback,
        ]);
    }

    /**
     * GET /command-center/production-hardening-status
     * System Production Hardening, Checksum Integrity & Governance Audit API (Phase 3A)
     */
    public function productionHardeningStatus(): ResponseInterface
    {
        $audit = $this->hardeningService->verifySystemHardeningAndGovernance();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $audit,
        ]);
    }

    /**
     * GET /command-center/operational-resilience-status
     * System Operational Resilience, Circuit Breaker & Continuity Audit API (Phase 3B)
     */
    public function operationalResilienceStatus(): ResponseInterface
    {
        $audit = $this->resilienceService->auditOperationalResilienceAndContinuity();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $audit,
        ]);
    }

    /**
     * GET /command-center/system-observability-status
     * System Observability, Correlation ID Trace & SRE Telemetry API (Phase 3C)
     */
    public function systemObservabilityStatus(): ResponseInterface
    {
        $telemetry = $this->observabilityService->getSystemObservabilityAndSreMetrics();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $telemetry,
        ]);
    }
}
