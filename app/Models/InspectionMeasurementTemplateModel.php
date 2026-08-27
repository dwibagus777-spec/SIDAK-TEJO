<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionMeasurementTemplateModel extends Model
{
    protected $table            = 'inspection_measurement_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'inspection_program_id',
        'template_code',
        'template_name',
        'asset_domain',
        'active',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
