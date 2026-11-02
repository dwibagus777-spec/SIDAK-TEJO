<?php

namespace App\Models;

use CodeIgniter\Model;

class UidModel extends Model
{
    protected $table            = 'uids';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['kode_uid', 'nama_uid', 'status'];

    // Timestamps
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getActiveUids(): array
    {
        return $this->where('status', 'AKTIF')
            ->orderBy('nama_uid', 'ASC')
            ->findAll();
    }
}
