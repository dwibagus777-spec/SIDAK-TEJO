<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetRelationshipModel extends Model
{
    protected $table            = 'asset_relationships';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'source_asset_id',
        'target_asset_id',
        'relationship_type',
        'sequence_no',
        'effective_date',
        'is_active',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
