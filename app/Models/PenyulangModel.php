<?php

namespace App\Models;

use CodeIgniter\Model;

class PenyulangModel extends Model
{
    protected $table            = 'penyulang';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['id_unik_penyulang', 'kode_penyulang', 'nama_penyulang', 'ulp_id', 'status'];

    // Timestamps
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
