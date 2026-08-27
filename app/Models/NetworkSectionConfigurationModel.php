<?php

namespace App\Models;

use CodeIgniter\Model;

class NetworkSectionConfigurationModel extends Model
{
    protected $table            = 'network_section_configurations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'section_id',
        'import_batch_id',
        'section_ref',
        'version_number',
        'effective_from',
        'effective_to',
        'verification_status', // DRAFT, SUBMITTED, VERIFIED, ACTIVE, SUPERSEDED
        'topology_connectivity_status', // VERIFIED, UNVERIFIED, DISCONTINUOUS
        'configuration_source',
        'inspection_id',
        'changed_by',
        'change_reason',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
