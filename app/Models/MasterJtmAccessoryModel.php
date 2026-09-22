<?php

namespace App\Models;

use CodeIgniter\Model;

class MasterJtmAccessoryModel extends Model
{
    protected $table            = 'master_jtm_accessories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'code',
        'name',
        'category',
        'sub_category',
        'description',
        'phase_applicable',
        'phase_mode',
        'default_qty',
        'unit',
        'canonical_material_code',
        'is_active',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get all active accessories sorted by sort_order
     */
    public function getActive(): array
    {
        return $this->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
