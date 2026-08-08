<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionPlanningModel extends Model
{
    protected $table            = 'inspection_plannings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'nomor_planning',
        'title',
        'inspection_type_id',
        'gi_id',
        'ulp_id',
        'penyulang_id',
        'jenis_asset',
        'assigned_inspector_id',
        'created_by_user_id',
        'scheduled_date',
        'published_at',
        'completed_at',
        'total_assets',
        'status',
    ];

    // Timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Generate unique nomor_planning (e.g. PLN-20260808-001)
     */
    public function generateNomorPlanning(): string
    {
        $prefix = 'PLN-' . date('Ymd');
        $db = \Config\Database::connect();
        $existing = $db->query("SELECT id FROM inspection_plannings WHERE nomor_planning LIKE '{$prefix}%'")->getResultArray();
        $sequence = str_pad(count($existing) + 1, 3, '0', STR_PAD_LEFT);

        return $prefix . '-' . $sequence;
    }
}
