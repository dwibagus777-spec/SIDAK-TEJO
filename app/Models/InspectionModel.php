<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionModel extends Model
{
    protected $table            = 'inspections';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nomor_inspeksi',
        'inspection_type_id',
        'baseline_id',
        'ulp_id',
        'penyulang_id',
        'inspector_user_id',
        'start_time',
        'end_time',
        'status',
        'total_points',
        'passed_points',
        'failed_points',
        'notes',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
