<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionProgramModel extends Model
{
    protected $table            = 'inspection_programs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'program_code',
        'nama_pekerjaan',
        'asset_domain',
        'inspection_type',
        'executor_type',
        'inspection_category',
        'active',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
