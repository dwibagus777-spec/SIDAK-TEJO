<?php

namespace App\Controllers;

use App\Services\InspectionAnalyticsService;
use App\Services\FindingIntelligenceService;
use App\Services\ThermovisionAnalyticsService;
use App\Services\AssetHealthScoringEngine;
use App\Models\AssetModel;

class ExecutiveAnalyticsController extends BaseController
{
    private InspectionAnalyticsService $inspectionAnalytics;
    private FindingIntelligenceService $findingIntelligence;
    private ThermovisionAnalyticsService $thermoAnalytics;
    private AssetHealthScoringEngine $healthEngine;
    private AssetModel $assetModel;

    public function __construct()
    {
        $this->inspectionAnalytics = new InspectionAnalyticsService();
        $this->findingIntelligence = new FindingIntelligenceService();
        $this->thermoAnalytics     = new ThermovisionAnalyticsService();
        $this->healthEngine        = new AssetHealthScoringEngine();
        $this->assetModel          = new AssetModel();
    }

    public function index()
    {
        $ulpId        = $this->request->getGet('ulp_id') ? (int)$this->request->getGet('ulp_id') : null;
        $penyulangId  = $this->request->getGet('penyulang_id') ? (int)$this->request->getGet('penyulang_id') : null;

        $kpis               = $this->inspectionAnalytics->getOverallKPIs($ulpId, $penyulangId);
        $typeBreakdown      = $this->inspectionAnalytics->getTypeBreakdown();
        $topAssets          = $this->findingIntelligence->getTopProblematicAssets(5);
        $severityBreakdown  = $this->findingIntelligence->getSeverityBreakdown();

        // Sample Health Score Calculation for Top Assets
        $healthList = [];
        foreach ($topAssets as $ta) {
            $healthList[] = $this->healthEngine->calculateAssetHealth((int)$ta['asset_id']);
        }

        return view('analytics/executive', [
            'kpis'              => $kpis,
            'typeBreakdown'     => $typeBreakdown,
            'topAssets'         => $topAssets,
            'severityBreakdown' => $severityBreakdown,
            'healthList'        => $healthList,
            'scoringVersion'    => \App\Services\AssetHealthScoringEngine::SCORING_VERSION,
        ]);
    }
}
