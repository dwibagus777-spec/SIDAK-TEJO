<?php

namespace App\Models;

use CodeIgniter\Model;

class InspeksiKubikelHistoriModel extends Model
{
    protected $table            = 'inspeksi_kubikel_histori';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'inspeksi_kubikel_id',
        'jenis_perubahan',
        'nilai_lama',
        'nilai_baru',
        'user_id',
        'alasan',
        'ip_address',
        'user_agent',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'inspeksi_kubikel_id' => 'required|integer',
        'jenis_perubahan'     => 'in_list[STATUS,TEMUAN,REKOMENDASI,PENUTUPAN,KOREKSI,VERIFIKASI,PENGHAPUSAN,INFORMASI_UTAMA]',
        'user_id'             => 'required|integer',
    ];

    public function getByInspeksi(int $inspeksiId): array
    {
        return $this->where('inspeksi_kubikel_id', $inspeksiId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function logPerubahan(int $inspeksiId, string $jenis, ?string $lama, ?string $baru, int $userId, ?string $alasan = null): bool
    {
        return $this->insert([
            'inspeksi_kubikel_id' => $inspeksiId,
            'jenis_perubahan'     => $jenis,
            'nilai_lama'          => $lama,
            'nilai_baru'          => $baru,
            'user_id'             => $userId,
            'alasan'              => $alasan,
            'ip_address'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'          => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
}