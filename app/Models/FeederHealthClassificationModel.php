<?php

namespace App\Models;

use CodeIgniter\Model;

class FeederHealthClassificationModel extends Model
{
    protected $table            = 'feeder_health_classifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'penyulang_id',
        'calculation_policy_version',
        'period_month',
        'health_score',
        'health_classification', // SEMPURNA, WASPADA, PERHATIAN, KRITIS, UNRESOLVED
        'fhi_status',            // RESOLVED, PARTIAL, UNRESOLVED
        'data_completeness_ratio',
        'physical_coverage_ratio',
        'asset_health_score',
        'finding_severity_score',
        'reliability_score',
        'recurrence_score',
        'primary_driver',
        'primary_driver_score',
        'assigned_unit',
        'priority_level',
        'interruption_count',
        'interruption_duration_minutes',
        'critical_findings_count',
        'recurring_findings_count',
        'bom_degradation_score',
        'overload_events_count',
        'fingerprint_json',
        'explanation_json',
        'advisory_narrative',
        'calculated_at',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
