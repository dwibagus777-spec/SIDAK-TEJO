<?php

namespace App\Models;

use CodeIgniter\Model;

class TindakLanjutModel extends Model
{
    protected $table            = 'riwayat_tindak_lanjut';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'temuan_id', 'tanggal', 'pelaksana', 'status_progress', 'komentar', 
        'foto_sebelum', 'foto_proses', 'foto_sesudah'
    ];

    // Timestamps
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
