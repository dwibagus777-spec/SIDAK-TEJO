<?php

namespace App\Models;

use CodeIgniter\Model;

class NetworkBaselineModel extends Model
{
    protected $table            = 'network_baselines';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'network_type',
        'ulp_id',
        'penyulang_id',
        'gardu_id',
        'trafo_id',
        'version',
        'effective_date',
        'status',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
