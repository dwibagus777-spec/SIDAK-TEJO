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

    /**
     * System Normalizer: Auto-generate unique kode_gi based on nama_gi
     * Example: GI BUDURAN -> GI-BDR-001
     */
    public function generateKodeGi(string $namaGi): string
    {
        $clean = trim(preg_replace('/^GI\s+/i', '', $namaGi));
        $words = preg_split('/\s+/', $clean);

        if (count($words) >= 2) {
            $prefix = strtoupper(substr($words[0], 0, 2) . substr($words[1], 0, 1));
        } else {
            $consonants = preg_replace('/[AEIOU\s]/i', '', $clean);
            if (strlen($consonants) >= 3) {
                $prefix = strtoupper(substr($consonants, 0, 3));
            } else {
                $prefix = strtoupper(substr($clean, 0, 3));
            }
        }

        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }

        $baseCode = 'GI-' . $prefix;
        
        $db = \Config\Database::connect();
        $existing = $db->query("SELECT kode_gi FROM gardu_induk WHERE kode_gi LIKE '{$baseCode}%'")->getResultArray();
        $count = count($existing);
        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return $baseCode . '-' . $sequence;
    }
}
