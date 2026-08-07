<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetTypeModel extends Model
{
    protected $table            = 'asset_types';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'code',
        'name',
        'network_type',
        'icon',
        'marker_shape',
        'marker_size',
        'default_color',
        'parent_type',
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
