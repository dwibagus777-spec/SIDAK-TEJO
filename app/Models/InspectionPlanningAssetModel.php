<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionPlanningAssetModel extends Model
{
    protected $table            = 'inspection_planning_assets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'planning_id',
        'asset_id',
        'sequence_no',
    ];

    // Timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
