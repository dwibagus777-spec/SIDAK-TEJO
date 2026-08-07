<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionResultModel extends Model
{
    protected $table            = 'inspection_results';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'inspection_point_id',
        'template_item_id',
        'result_status',
        'measurement_value',
        'notes',
        'temuan_id',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
