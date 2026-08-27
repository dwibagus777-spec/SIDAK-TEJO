<?php

namespace App\Models;

use CodeIgniter\Model;

class NetworkSectionConductorModel extends Model
{
    protected $table            = 'network_section_conductors';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'network_section_configuration_id',
        'conductor_material_id',
        'sequence_order',
        'segment_label',
        'start_node_id',
        'end_node_id',
        'length_m',
        'verified',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
