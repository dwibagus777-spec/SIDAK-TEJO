<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionMeasurementPointModel extends Model
{
    protected $table            = 'inspection_measurement_points';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'template_id',
        'point_code',
        'point_name',
        'phase',
        'line',
        'measurement_type',
        'unit',
        'sequence_order',
        'mandatory',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
