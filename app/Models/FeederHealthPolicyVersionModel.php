<?php

namespace App\Models;

use CodeIgniter\Model;

class FeederHealthPolicyVersionModel extends Model
{
    protected $table            = 'feeder_health_policy_versions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'policy_code',
        'policy_name',
        'description',
        'status', // DRAFT, ACTIVE, SUPERSEDED
        'effective_from',
        'effective_to',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
