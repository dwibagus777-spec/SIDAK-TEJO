<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * MR-01 Phase 3B: Finding Material Transaction Model
 * Represents the immutable material request lines attached to a temuan.
 */
class TemuanMaterialModel extends Model
{
    protected $table            = 'temuan_materials';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'temuan_id',
        'asset_id',
        'construction_type_id',
        'material_id',
        'canonical_code_snapshot',
        'canonical_name_snapshot',
        'unit_snapshot',
        'quantity',
        'justification_note',
        'source_mode',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
