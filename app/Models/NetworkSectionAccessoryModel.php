<?php

namespace App\Models;

use CodeIgniter\Model;

class NetworkSectionAccessoryModel extends Model
{
    protected $table            = 'network_section_accessories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'network_section_configuration_id',
        'accessory_material_id',
        'accessory_type',
        'quantity',
        'location_reference',
        'condition_status',
        'verified',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
