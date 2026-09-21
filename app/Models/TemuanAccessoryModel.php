<?php

namespace App\Models;

use CodeIgniter\Model;

class TemuanAccessoryModel extends Model
{
    protected $table            = 'temuan_accessories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'temuan_id',
        'asset_id',
        'accessory_type_id',
        'accessory_name_snapshot',
        'status',
        'condition',
        'note',
        'photo_url',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get all accessories for a finding
     */
    public function getByTemuanId(int $temuanId): array
    {
        return $this->select('temuan_accessories.*, m.code as accessory_code, m.category as accessory_category')
            ->join('master_jtm_accessories m', 'm.id = temuan_accessories.accessory_type_id', 'left')
            ->where('temuan_accessories.temuan_id', $temuanId)
            ->orderBy('m.sort_order', 'ASC')
            ->orderBy('temuan_accessories.id', 'ASC')
            ->findAll();
    }

    /**
     * Get all accessories for an asset across findings
     */
    public function getByAssetId(int $assetId): array
    {
        return $this->select('temuan_accessories.*, m.code as accessory_code')
            ->join('master_jtm_accessories m', 'm.id = temuan_accessories.accessory_type_id', 'left')
            ->where('temuan_accessories.asset_id', $assetId)
            ->orderBy('temuan_accessories.created_at', 'DESC')
            ->findAll();
    }
}
