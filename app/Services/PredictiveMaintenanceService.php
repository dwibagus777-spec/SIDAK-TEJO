<?php

namespace App\Services;

use App\AI\RiskEngine;
use App\AI\RecommendationEngine;
use App\AI\PredictionEngine;
use App\AI\AnalyticsEngine;
use App\AI\DatasetBuilder;
use App\Repositories\AssetRepository;
use App\Repositories\TemuanRepository;
use App\Repositories\WorkOrderRepository;
use App\Models\AiDecisionLogModel;

class PredictiveMaintenanceService
{
    private RiskEngine $riskEngine;
    private RecommendationEngine $recommendationEngine;
    private PredictionEngine $predictionEngine;
    private AnalyticsEngine $analyticsEngine;
    private DatasetBuilder $datasetBuilder;

    private AssetRepository $assetRepo;
    private TemuanRepository $temuanRepo;
    private WorkOrderRepository $woRepo;
    private AiDecisionLogModel $logModel;

    public function __construct()
    {
        $this->riskEngine           = new RiskEngine();
        $this->recommendationEngine = new RecommendationEngine();
        $this->predictionEngine     = new PredictionEngine();
        $this->analyticsEngine      = new AnalyticsEngine();
        $this->datasetBuilder       = new DatasetBuilder();

        $this->assetRepo  = new AssetRepository();
        $this->temuanRepo = new TemuanRepository();
        $this->woRepo     = new WorkOrderRepository();
        $this->logModel   = new AiDecisionLogModel();
    }

    public function getPredictiveAnalyticsData(?int $userUlpId = null): array
    {
        $assets = $this->assetRepo->getFilteredAssets([], $userUlpId);
        $temuanList = $this->temuanRepo->getFilteredTemuan([], $userUlpId);
        $woList = $this->woRepo->getFilteredWorkOrders([], $userUlpId);

        // 1. Calculate Risk Scores for All Assets
        $riskAssets = [];
        foreach ($assets as $asset) {
            $assetId = $asset['id'];
            $tCount = 0; $emCount = 0; $hiCount = 0;
            foreach ($temuanList as $t) {
                if (($t['asset_id'] ?? null) == $assetId) {
                    $tCount++;
                    if (($t['prioritas'] ?? '') === 'EMERGENCY') $emCount++;
                    if (($t['prioritas'] ?? '') === 'HIGH') $hiCount++;
                }
            }
            $woFail = 0;
            foreach ($woList as $w) {
                if (($w['asset_id'] ?? null) == $assetId && ($w['status'] ?? '') !== 'COMPLETED') $woFail++;
            }

            $tahun = (int)($asset['tahun_instalasi'] ?: date('Y'));
            $umur = max(1, date('Y') - $tahun);

            $riskRes = $this->riskEngine->calculateRiskScore([
                'temuan_count'     => $tCount,
                'emergency_count'  => $emCount,
                'high_count'       => $hiCount,
                'asset_age_years'  => $umur,
                'wo_failure_count' => $woFail,
                'is_pending'       => $tCount > 0,
            ]);

            $assetData = array_merge($asset, [
                'risk_score'   => $riskRes['score'],
                'risk_category'=> $riskRes['category'],
                'badge_class'  => $riskRes['badge_class'],
                'total_temuan' => $tCount,
            ]);

            $riskAssets[] = $assetData;
        }

        // Sort Top Risk Assets
        usort($riskAssets, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        // 2. Predict Failure Windows (7, 30, 90 days)
        $predictions = $this->predictionEngine->predictAssetFailures($riskAssets);

        // 3. Detect Anomalies
        $anomalies = $this->analyticsEngine->detectAnomalies($temuanList, $woList);

        return [
            'top_risk_assets'   => array_slice($riskAssets, 0, 10),
            'predictions'       => $predictions,
            'anomalies'         => $anomalies,
            'total_assets'      => count($assets),
            'critical_count'    => count(array_filter($riskAssets, fn($a) => $a['risk_category'] === 'CRITICAL')),
            'high_risk_count'   => count(array_filter($riskAssets, fn($a) => $a['risk_category'] === 'HIGH')),
        ];
    }

    /**
     * Get Explainable AI recommendation for a specific temuan
     */
    public function getExplainableRecommendation(array $temuan): array
    {
        $riskRes = $this->riskEngine->calculateRiskScore([
            'temuan_count'     => 1,
            'emergency_count'  => ($temuan['prioritas'] === 'EMERGENCY') ? 1 : 0,
            'high_count'       => ($temuan['prioritas'] === 'HIGH') ? 1 : 0,
            'asset_age_years'  => 8,
            'wo_failure_count' => 0,
            'is_pending'       => ($temuan['status'] !== 'SELESAI'),
        ]);

        $recRes = $this->recommendationEngine->generateRecommendation([
            'risk_score'   => $riskRes['score'],
            'jenis_temuan' => $temuan['jenis_temuan'],
            'prioritas'    => $temuan['prioritas'],
            'status'       => $temuan['status'],
            'pelaksana'    => $temuan['pelaksana'],
            'temuan_count' => 1,
            'asset_age'    => 8,
        ]);

        $result = array_merge($riskRes, $recRes);

        // Log AI Decision
        try {
            $this->logModel->insert([
                'target_type'           => 'TEMUAN',
                'target_id'             => $temuan['id'],
                'engine_name'           => 'RecommendationEngine_XAI',
                'input_data'            => json_encode($temuan),
                'score'                 => $riskRes['score'],
                'output_recommendation' => $recRes['recommendation_text'],
                'explanation'           => implode(" | ", $recRes['reasons']),
                'created_at'            => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Ignore log exceptions
        }

        return $result;
    }

    public function exportMlDataset(string $format = 'csv'): string
    {
        $assets = $this->assetRepo->getFilteredAssets([], null);
        $temuanList = $this->temuanRepo->getFilteredTemuan([], null);
        $woList = $this->woRepo->getFilteredWorkOrders([], null);

        $dataset = $this->datasetBuilder->buildDataset($assets, $temuanList, $woList);

        if (strtolower($format) === 'json') {
            return json_encode($dataset, JSON_PRETTY_PRINT);
        }
        return $this->datasetBuilder->exportCsv($dataset);
    }
}
