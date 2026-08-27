<?php

namespace App\Models;

use CodeIgniter\Model;

class FeederHealthPolicyRuleModel extends Model
{
    protected $table            = 'feeder_health_policy_rules';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'policy_version_id',
        'metric_key',
        'weight',
        'threshold_sempurna_min',
        'threshold_sakit_min',
        'threshold_kronis_min',
        'threshold_kritis_max',
        'rule_params_json',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
