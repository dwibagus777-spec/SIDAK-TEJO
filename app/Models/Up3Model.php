<?php

namespace App\Models;

use CodeIgniter\Model;

class Up3Model extends Model
{
    protected $table            = 'up3s';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['uid_id', 'kode_up3', 'nama_up3', 'status'];

    // Timestamps
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getActiveUp3sByUid(int $uidId = 0): array
    {
        $builder = $this->where('status', 'AKTIF');
        if ($uidId > 0) {
            $builder->where('uid_id', $uidId);
        }
        return $builder->orderBy('nama_up3', 'ASC')->findAll();
    }
}
