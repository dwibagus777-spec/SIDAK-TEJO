<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class DataAnomalyDetectionService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Audit Data Anomalies & Measurement Outlier Detection (Phase 3I)
     */
    public function auditDataAnomalies(int $assetId): array
    {
        $db = $this->db;

        $vegObs = $db->table('vegetation_observations')->where('asset_id', $assetId)->orderBy('id', 'DESC')->get()->getResultArray();
        $thermoObs = $db->table('thermovision_observations')->where('asset_id', $assetId)->orderBy('id', 'DESC')->get()->getResultArray();

        $anomaliesFound = [];
        // Check for non-physical negative values or extreme jumps
        foreach ($vegObs as $vo) {
            if ($vo['distance_meters'] < 0.0) {
                $anomaliesFound[] = [
                    'type'         => 'VEGETATION_NEGATIVE_DISTANCE',
                    'observation_id' => $vo['id'],
                    'severity'     => 'HIGH',
                ];
            }
        }

        foreach ($thermoObs as $to) {
            if ($to['measured_temperature_c'] > 300.0) {
                $anomaliesFound[] = [
                    'type'         => 'THERMOVISION_SENSOR_SPIKE',
                    'observation_id' => $to['id'],
                    'severity'     => 'HIGH',
                ];
            }
        }

        return [
            'status'                  => 'success',
            'asset_id'                => $assetId,
            'anomalies_detected_cnt'  => count($anomaliesFound),
            'anomalies_log'           => $anomaliesFound,
            'anomaly_engine_version'  => 'DATA_ANOMALY_DETECTION_v1.0',
            'certified_anomaly_status'=> count($anomaliesFound) === 0 ? 'NO_DATA_ANOMALIES_DETECTED' : 'ANOMALIES_FLAGGED_FOR_AUDIT',
        ];
    }
}
