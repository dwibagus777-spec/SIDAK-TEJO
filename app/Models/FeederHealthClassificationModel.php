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
        'health_classification', // SEMPURNA, SAKIT, KRONIS, KRITIS
        'interruption_count',
        'interruption_duration_minutes',
        'critical_findings_count',
        'recurring_findings_count',
        'bom_degradation_score',
        'overload_events_count',
        'explanation_json',
        'calculated_at',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = false;
}
