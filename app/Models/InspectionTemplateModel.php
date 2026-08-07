<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionTemplateModel extends Model
{
    protected $table            = 'inspection_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'inspection_type_id',
        'title',
        'asset_category',
        'construction_type_id',
        'version',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
