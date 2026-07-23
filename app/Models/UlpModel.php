<?php

namespace App\Models;

use CodeIgniter\Model;

class UlpModel extends Model
{
    protected $table            = 'ulps';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['kode_ulp', 'nama_ulp', 'status'];
    
    // Timestamps
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
