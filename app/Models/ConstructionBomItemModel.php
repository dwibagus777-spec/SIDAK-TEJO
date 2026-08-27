<?php

namespace App\Models;

use CodeIgniter\Model;

class ConstructionBomItemModel extends Model
{
    protected $table            = 'construction_bom_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'construction_type_id',
        'material_id',
        'raw_material_name',
        'material_alias',
        'quantity',
        'quantity_status', // KNOWN, UNKNOWN, NOT_APPLICABLE (Gate 3)
        'unit',
        'mandatory',
        'component_category',
        'source_sheet',
        'source_row',
        'mapping_status', // RESOLVED, UNRESOLVED, MANUAL_REVIEW_REQUIRED
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
