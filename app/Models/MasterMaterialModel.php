<?php

namespace App\Models;

use CodeIgniter\Model;

class MasterMaterialModel extends Model
{
    protected $table            = 'master_materials';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'material_code',
        'nama_material',
        'nama_lapangan',
        'satuan',
        'material_domain',
        'material_category',
        'specification',
        'source_workbook',
        'source_sheet',
        'source_row',
        'status',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
}
