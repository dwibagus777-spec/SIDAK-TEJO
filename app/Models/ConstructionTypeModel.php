<?php

namespace App\Models;

use CodeIgniter\Model;

class ConstructionTypeModel extends Model
{
    protected $table            = 'construction_types';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'code',
        'name',
        'network_type',
        'asset_category',
        'construction_group',
        'voltage_level',
        'description',
        'standard_reference',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
