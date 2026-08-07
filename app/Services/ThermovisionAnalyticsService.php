<?php

namespace App\Services;

use Config\Database;

class ThermovisionAnalyticsService
{
    public const THERMOVISION_WARNING_DELTA  = 15.0;
    public const THERMOVISION_CRITICAL_DELTA = 40.0;

    /**
     * Calculate Authoritative Server-Side Delta T from raw temperature readings
     */
    public function calculateAuthoritativeDelta(float $phaseR, float $phaseS, float $phaseT, float $ambient): array
    {
        $deltaR   = max(0.0, $phaseR - $ambient);
        $deltaS   = max(0.0, $phaseS - $ambient);
        $deltaT   = max(0.0, $phaseT - $ambient);
        $maxDelta = max($deltaR, $deltaS, $deltaT);

        $severity = 'NORMAL';
        if ($maxDelta >= self::THERMOVISION_CRITICAL_DELTA) {
            $severity = 'CRITICAL_HOTSPOT';
        } elseif ($maxDelta >= self::THERMOVISION_WARNING_DELTA) {
            $severity = 'WARNING_HOTSPOT';
        }

        return [
            'phase_r'       => round($phaseR, 1),
            'phase_s'       => round($phaseS, 1),
            'phase_t'       => round($phaseT, 1),
            'ambient'       => round($ambient, 1),
            'delta_r'       => round($deltaR, 1),
            'delta_s'       => round($deltaS, 1),
            'delta_t'       => round($deltaT, 1),
            'max_delta'     => round($maxDelta, 1),
            'severity'      => $severity,
            'is_abnormal'   => ($maxDelta >= self::THERMOVISION_WARNING_DELTA),
        ];
    }

    /**
     * Get Thermovision Temperature History & Hotspot Trend for an Asset
     */
    public function getAssetThermovisionTrend(int $assetId, int $limit = 10): array
    {
        $db = Database::connect();

        $rows = $db->table('inspection_results')
            ->select('inspection_results.measurement_value, inspection_results.created_at, inspection_template_items.item_name, inspection_points.asset_id')
            ->join('inspection_points', 'inspection_points.id = inspection_results.inspection_point_id')
            ->join('inspection_template_items', 'inspection_template_items.id = inspection_results.template_item_id')
            ->where('inspection_points.asset_id', $assetId)
            ->where('inspection_template_items.item_type', 'NUMERIC_MEASUREMENT')
            ->orderBy('inspection_results.id', 'DESC')
            ->limit($limit * 4)
            ->get()
            ->getResultArray();

        // Group numerical telemetry readings by timestamp run
        $grouped = [];
        foreach ($rows as $row) {
            $ts = $row['created_at'] ?? date('Y-m-d H:i:s');
            if (!isset($grouped[$ts])) {
                $grouped[$ts] = ['phase_r' => 0.0, 'phase_s' => 0.0, 'phase_t' => 0.0, 'ambient' => 30.0, 'created_at' => $ts];
            }

            $val = (float)$row['measurement_value'];
            $name = strtolower($row['item_name']);

            if (str_contains($name, 'phase r') || str_contains($name, 'fase r')) {
                $grouped[$ts]['phase_r'] = $val;
            } elseif (str_contains($name, 'phase s') || str_contains($name, 'fase s')) {
                $grouped[$ts]['phase_s'] = $val;
            } elseif (str_contains($name, 'phase t') || str_contains($name, 'fase t')) {
                $grouped[$ts]['phase_t'] = $val;
            } elseif (str_contains($name, 'ambient')) {
                $grouped[$ts]['ambient'] = $val;
            }
        }

        $trend = [];
        foreach ($grouped as $ts => $data) {
            $calc = $this->calculateAuthoritativeDelta($data['phase_r'], $data['phase_s'], $data['phase_t'], $data['ambient']);
            $calc['created_at'] = $ts;
            $trend[] = $calc;
        }

        return array_slice($trend, 0, $limit);
    }
}
