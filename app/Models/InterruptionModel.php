<?php

namespace App\Models;

use CodeIgniter\Model;

class InterruptionModel extends Model
{
    protected $table = 'historical_feeder_interruptions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'batch_id',
        'source_system',
        'source_sheet_name',
        'source_row_number',
        'source_record_hash',
        'raw_payload_json',
        'event_date',
        'interruption_started_at',
        'interruption_ended_at',
        'outage_duration_minutes',
        'substation_name',
        'ulp_name',
        'ulp_id',
        'feeder_name',
        'penyulang_id',
        'switching_device_type',
        'device_name',
        'relay_trip_type',
        'faulted_phase',
        'weather_condition',
        'fault_current_amperes',
        'energy_not_supplied_kwh',
        'interruption_category',
        'interruption_group',
        'cause_raw',
        'cause_canonical_code',
        'cause_category',
        'cause_mapping_status',
        'field_narrative_raw',
        'restoration_action_raw',
        'extracted_zone_section',
        'extracted_distance_kms',
        'asset_reference_raw',
        'asset_id',
        'section_id',
        'data_quality_score',
        'ingestion_status',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get distinct years available in interruption history
     */
    public function getAvailableYears(): array
    {
        $rows = $this->select("DISTINCT YEAR(event_date) as yr")
            ->where('event_date IS NOT NULL')
            ->orderBy('yr', 'DESC')
            ->findAll();

        return array_values(array_filter(array_map(fn($r) => (int)$r['yr'], $rows)));
    }
}
