<?php

namespace App\Models;

use CodeIgniter\Model;

class MaterialAliasModel extends Model
{
    protected $table            = 'material_aliases';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'material_id',
        'alias_name',
        'normalized_alias',
        'alias_type',
        'source',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
