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
use App\Services\PreventiveRiskRadarService;
use App\Services\PriorityActionQueueService;
use App\Services\RecurringFindingIntelligenceService;
use App\Services\RiskExplainabilityService;
use App\Services\SpreadsheetGangguanDryRunService;
use App\Services\SpreadsheetGangguanImportPlanService;
use App\Services\SpreadsheetGangguanImportService;
use App\Services\GangguanImportReconciliationService;
use App\Services\Providers\DatabaseGangguanProvider;
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
    protected PreventiveRiskRadarService $radarService;
    protected PriorityActionQueueService $actionQueueService;
    protected RecurringFindingIntelligenceService $recurringService;
    protected RiskExplainabilityService $explainabilityService;
    protected SpreadsheetGangguanDryRunService $dryRunService;
    protected SpreadsheetGangguanImportPlanService $importPlanService;
    protected SpreadsheetGangguanImportService $importService;
    protected GangguanImportReconciliationService $reconciliationService;
    protected DatabaseGangguanProvider $dbGangguanProvider;

    public function __construct()
    {
        $this->opService             = new OperationalIntelligenceService();
        $this->topoService           = new NetworkTopologyService();
        $this->predictService        = new PredictiveRiskService();
        $this->prescriptiveService    = new PrescriptiveDecisionService();
        $this->orchestratorService   = new ExecutionOrchestrationService();
        $this->feedbackService       = new ExecutionFeedbackService();
        $this->hardeningService      = new ProductionHardeningService();
        $this->resilienceService     = new OperationalResilienceService();
        $this->observabilityService  = new SystemObservabilityService();
        $this->radarService          = new PreventiveRiskRadarService();
        $this->actionQueueService    = new PriorityActionQueueService();
        $this->recurringService      = new RecurringFindingIntelligenceService();
        $this->explainabilityService  = new RiskExplainabilityService();
        $this->dryRunService         = new SpreadsheetGangguanDryRunService();
        $this->importPlanService     = new SpreadsheetGangguanImportPlanService();
        $this->importService         = new SpreadsheetGangguanImportService();
        $this->reconciliationService = new GangguanImportReconciliationService();
        $this->dbGangguanProvider    = new DatabaseGangguanProvider();

        $this->radarService->setGangguanProvider($this->dbGangguanProvider);
        $this->explainabilityService->setGangguanProvider($this->dbGangguanProvider);
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

    /**
     * GET /command-center/api/summary
     * Additive Command Center Macro Risk Summary API (Step 5)
     */
    public function apiSummary(): ResponseInterface
    {
        $filters = $this->extractFilters();
        $radar = $this->radarService->getAggregatedRadar($filters);
        $recurring = $this->recurringService->getRecurringIntelligence($filters);
        $queue = $this->actionQueueService->getActionQueue($filters);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'radar_summary'       => $radar['summary'] ?? [],
                'recurring_summary'   => $recurring['summary'] ?? [],
                'queue_summary'       => $queue['summary'] ?? [],
                'interruption_source' => $radar['interruption_provider'] ?? [],
            ],
            'meta'   => [
                'generated_at'    => date('Y-m-d H:i:s'),
                'scoring_version' => PreventiveRiskRadarService::SCORING_VERSION,
            ]
        ]);
    }

    /**
     * GET /command-center/api/risk-radar
     * Additive Spatial & Tabular Preventive Risk Radar API (Step 5)
     */
    public function apiRiskRadar(): ResponseInterface
    {
        $filters = $this->extractFilters();
        $radar = $this->radarService->getAggregatedRadar($filters);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $radar,
            'meta'   => [
                'generated_at'    => date('Y-m-d H:i:s'),
                'scoring_version' => PreventiveRiskRadarService::SCORING_VERSION,
            ]
        ]);
    }

    /**
     * GET /command-center/api/priority-actions
     * Additive Ranked Priority Action Queue API (Step 5)
     */
    public function apiPriorityActions(): ResponseInterface
    {
        $filters = $this->extractFilters();
        $filters['limit'] = (int)($this->request->getGet('limit') ?? 25);
        $filters['offset'] = (int)($this->request->getGet('offset') ?? 0);

        $queue = $this->actionQueueService->getActionQueue($filters);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $queue,
            'meta'   => [
                'generated_at'    => date('Y-m-d H:i:s'),
                'scoring_version' => PreventiveRiskRadarService::SCORING_VERSION,
            ]
        ]);
    }

    /**
     * GET /command-center/api/recurring-intelligence
     * Additive Recurring Finding Intelligence API (Step 5)
     */
    public function apiRecurringIntelligence(): ResponseInterface
    {
        $filters = $this->extractFilters();
        $filters['min_recurrence'] = (int)($this->request->getGet('min_recurrence') ?? 1);

        $recurring = $this->recurringService->getRecurringIntelligence($filters);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $recurring,
            'meta'   => [
                'generated_at'    => date('Y-m-d H:i:s'),
                'scoring_version' => PreventiveRiskRadarService::SCORING_VERSION,
            ]
        ]);
    }

    /**
     * GET /command-center/api/explainability/(:num)
     * Additive "Why Prioritized?" Evidence Lineage API (Step 5)
     */
    public function apiExplainability($findingId = null): ResponseInterface
    {
        $fId = (int)($findingId ?? $this->request->getGet('finding_id') ?? 1);
        $explain = $this->explainabilityService->explainFindingRisk($fId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $explain,
            'meta'   => [
                'generated_at'    => date('Y-m-d H:i:s'),
                'scoring_version' => PreventiveRiskRadarService::SCORING_VERSION,
            ]
        ]);
    }

    /**
     * Extract multi-select query parameter filters supporting arrays.
     */
    protected function extractFilters(): array
    {
        $filters = [];

        $ulp = $this->request->getGet('ulp') ?? $this->request->getGet('ulp_id');
        if (!empty($ulp)) {
            $filters['ulp_id'] = is_array($ulp) ? $ulp : [$ulp];
        }

        $penyulang = $this->request->getGet('penyulang') ?? $this->request->getGet('penyulang_id');
        if (!empty($penyulang)) {
            $filters['penyulang_id'] = is_array($penyulang) ? $penyulang : [$penyulang];
        }

        $section = $this->request->getGet('section') ?? $this->request->getGet('section_id');
        if (!empty($section)) {
            $filters['section_id'] = is_array($section) ? $section : [$section];
        }

        $riskTier = $this->request->getGet('risk_tier');
        if (!empty($riskTier)) {
            $filters['risk_tier'] = is_array($riskTier) ? $riskTier : [$riskTier];
        }

        $category = $this->request->getGet('category');
        if (!empty($category)) {
            $filters['category'] = is_array($category) ? $category : [$category];
        }

        return $filters;
    }

    /**
     * POST /command-center/api/gangguan-import/dry-run
     * Execute pure in-memory simulation of spreadsheet ingestion.
     */
    public function apiGangguanDryRun(): ResponseInterface
    {
        $filePath = $this->resolveUploadedFilePath();
        if (!$filePath) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Berkas spreadsheet (file) atau file_path tidak ditemukan dalam request.',
            ]);
        }

        $sheetName = $this->request->getPost('sheet_name') ?? $this->request->getGet('sheet_name');
        $dryRun = $this->dryRunService->executeDryRun($filePath, $sheetName);

        // Cleanup temporary upload if it was an HTTP upload
        $this->cleanupTemporaryUpload($filePath);

        return $this->response->setJSON([
            'status' => $dryRun['success'] ? 'success' : 'error',
            'data'   => $dryRun,
        ]);
    }

    /**
     * POST /command-center/api/gangguan-import/plan
     * Generate an Import Plan with feeder resolution and duplicate dispositions.
     */
    public function apiGangguanPlan(): ResponseInterface
    {
        $filePath = $this->resolveUploadedFilePath();
        if (!$filePath) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Berkas spreadsheet (file) atau file_path tidak ditemukan dalam request.',
            ]);
        }

        $sheetName   = $this->request->getPost('sheet_name') ?? $this->request->getGet('sheet_name');
        $autoAccept  = (bool)($this->request->getPost('auto_accept_high_confidence') ?? false);
        $plan        = $this->importPlanService->generateImportPlan($filePath, $sheetName, $autoAccept);

        $this->cleanupTemporaryUpload($filePath);

        return $this->response->setJSON([
            'status' => $plan['success'] ? 'success' : 'error',
            'data'   => $plan,
        ]);
    }

    /**
     * POST /command-center/api/gangguan-import/commit
     * Commit an approved Import Plan with explicit confirmation.
     */
    public function apiGangguanCommit(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $importPlan = $json['import_plan'] ?? null;
        $token      = $json['confirmation_token'] ?? '';
        $confirm    = (bool)($json['confirm'] ?? false);

        if (!$importPlan || empty($token)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Payload import_plan dan confirmation_token wajib disertakan.',
            ]);
        }

        // 1. Commit with confirmation barrier
        $commitResult = $this->importService->commitImportPlan($importPlan, $token, $confirm);

        if (!$commitResult['success']) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'data'   => $commitResult,
            ]);
        }

        // 2. Run Reconciliation
        $reconciliation = $this->reconciliationService->reconcileImport($importPlan, $commitResult);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'commit'         => $commitResult,
                'reconciliation' => $reconciliation,
            ],
        ]);
    }

    /**
     * GET /command-center/api/gangguan-import/status/{planId}
     * Check status and current disturbance knowledge provider availability.
     */
    public function apiGangguanStatus(?string $planId = null): ResponseInterface
    {
        $meta = $this->dbGangguanProvider->getMetadata();
        $stats = $this->dbGangguanProvider->getInterruptionStats();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'plan_id'             => $planId,
                'provider_metadata'   => $meta,
                'interruption_stats'  => $stats,
                'scoring_version'     => 'PREVENTIVE_SCORING_v1.0',
            ],
        ]);
    }

    /**
     * Helper to resolve uploaded file path from HTTP multipart or JSON parameter.
     */
    protected function resolveUploadedFilePath(): ?string
    {
        $file = $this->request->getFile('file');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $tempName = 'upload_gdr_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $file->getClientExtension();
            $targetDir = WRITEPATH . 'temp';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $file->move($targetDir, $tempName);
            return $targetDir . DIRECTORY_SEPARATOR . $tempName;
        }

        $json = $this->request->getJSON(true);
        $explicitPath = $this->request->getPost('file_path') ?? ($json['file_path'] ?? null);
        if ($explicitPath && file_exists($explicitPath)) {
            return $explicitPath;
        }

        return null;
    }

    /**
     * Clean up temporary file created by HTTP upload.
     */
    protected function cleanupTemporaryUpload(?string $filePath): void
    {
        if ($filePath && str_contains($filePath, WRITEPATH . 'temp') && file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}

