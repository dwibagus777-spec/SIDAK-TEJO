<?php

namespace App\Services;

use App\Models\InspectionModel;
use App\Models\InspectionPointModel;
use App\Models\InspectionResultModel;

class InspectionAnalyticsService
{
    private InspectionModel $inspectionModel;
    private InspectionPointModel $pointModel;
    private InspectionResultModel $resultModel;

    public function __construct()
    {
        $this->inspectionModel = new InspectionModel();
        $this->pointModel      = new InspectionPointModel();
        $this->resultModel     = new InspectionResultModel();
    }

    /**
     * Get Overall Inspection Execution KPIs (Completion Rate, Point Pass Rate, Point Failure Rate)
     */
    public function getOverallKPIs(?int $ulpId = null, ?int $penyulangId = null): array
    {
        $builder = $this->inspectionModel->builder();
        if ($ulpId) {
            $builder->where('ulp_id', $ulpId);
        }
        if ($penyulangId) {
            $builder->where('penyulang_id', $penyulangId);
        }

        $totalInspections     = (int)$builder->countAllResults(false);
        $completedInspections = (int)(clone $builder)->where('status', 'COMPLETED')->countAllResults();
        $inProgressInspections= (int)(clone $builder)->where('status', 'IN_PROGRESS')->countAllResults();

        $completionRate = $totalInspections > 0 ? round(($completedInspections / $totalInspections) * 100, 1) : 0.0;

        // Point Pass & Failure Rate Calculations
        $pointBuilder = $this->pointModel->builder();
        if ($ulpId || $penyulangId) {
            $pointBuilder->join('inspections', 'inspections.id = inspection_points.inspection_id');
            if ($ulpId) $pointBuilder->where('inspections.ulp_id', $ulpId);
            if ($penyulangId) $pointBuilder->where('inspections.penyulang_id', $penyulangId);
        }

        $totalInspectedPoints = (int)(clone $pointBuilder)->whereIn('inspection_points.status', ['PASSED', 'FAILED'])->countAllResults();
        $passedPoints         = (int)(clone $pointBuilder)->where('inspection_points.status', 'PASSED')->countAllResults();
        $failedPoints         = (int)(clone $pointBuilder)->where('inspection_points.status', 'FAILED')->countAllResults();

        $pointPassRate    = $totalInspectedPoints > 0 ? round(($passedPoints / $totalInspectedPoints) * 100, 1) : 100.0;
        $pointFailureRate = $totalInspectedPoints > 0 ? round(($failedPoints / $totalInspectedPoints) * 100, 1) : 0.0;

        return [
            'total_inspections'        => $totalInspections,
            'completed_inspections'    => $completedInspections,
            'in_progress_inspections'  => $inProgressInspections,
            'inspection_completion_rate' => $completionRate,
            'total_inspected_points'   => $totalInspectedPoints,
            'passed_points'            => $passedPoints,
            'failed_points'            => $failedPoints,
            'point_pass_rate'          => $pointPassRate,
            'point_failure_rate'       => $pointFailureRate,
        ];
    }

    /**
     * Get Inspection Breakdown by Inspection Type
     */
    public function getTypeBreakdown(): array
    {
        return $this->inspectionModel->builder()
            ->select('inspection_types.name as type_name, inspection_types.code as type_code, COUNT(inspections.id) as total_runs, SUM(inspections.passed_points) as total_passed, SUM(inspections.failed_points) as total_failed')
            ->join('inspection_types', 'inspection_types.id = inspections.inspection_type_id', 'left')
            ->groupBy('inspections.inspection_type_id')
            ->get()
            ->getResultArray();
    }
}
