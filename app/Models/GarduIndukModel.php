<?php

namespace App\Models;

use CodeIgniter\Model;

class GarduIndukModel extends Model
{
    protected $table            = 'gardu_induk';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'kode_gi',
        'nama_gi',
        'lokasi',
        'latitude',
        'longitude',
        'status',
    ];

    // Timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get active Gardu Induk list
     */
    public function getActiveGi(): array
    {
        return $this->where('status', 'AKTIF')
            ->orWhere('status', 'ACTIVE')
            ->orderBy('nama_gi', 'ASC')
            ->findAll();
    }
}
