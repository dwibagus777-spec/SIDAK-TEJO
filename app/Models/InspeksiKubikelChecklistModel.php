<?php

namespace App\Models;

use CodeIgniter\Model;

class InspeksiKubikelChecklistModel extends Model
{
    protected $table            = 'inspeksi_kubikel_checklist';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'inspeksi_kubikel_id',
        'komponen',
        'fasa',
        'posisi',
        'item_pemeriksaan',
        'kondisi',
        'temuan',
        'urgensi',
        'rekomendasi',
        'tindak_lanjut',
        'material_digunakan',
        'status_pekerjaan',
        'urutan',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'inspeksi_kubikel_id' => 'required|integer',
        'komponen'            => 'required|max_length[100]',
        'fasa'                => 'in_list[R,S,T,N,TIDAK_BERFASA]',
        'item_pemeriksaan'    => 'required|max_length[255]',
        'kondisi'             => 'in_list[BAIK,RUSAK_RINGAN,RUSAK_SEDANG,RUSAK_BERAT,TIDAK_DITEMUKAN,TIDAK_APLIKABEL]',
        'urgensi'             => 'in_list[RENDAH,SEDANG,TINGGI,KRITIS]',
        'status_pekerjaan'    => 'in_list[BELUM,PROSES,SELESAI,DITANGGUHKAN]',
    ];

    public function getByInspeksi(int $inspeksiId): array
    {
        return $this->where('inspeksi_kubikel_id', $inspeksiId)
            ->orderBy('urutan', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getByKomponen(int $inspeksiId, string $komponen): array
    {
        return $this->where('inspeksi_kubikel_id', $inspeksiId)
            ->where('komponen', $komponen)
            ->orderBy('urutan', 'ASC')
            ->findAll();
    }

    public function getTemuanItems(int $inspeksiId): array
    {
        return $this->where('inspeksi_kubikel_id', $inspeksiId)
            ->where('temuan IS NOT NULL')
            ->where('temuan !=', '')
            ->orderBy('urutan', 'ASC')
            ->findAll();
    }
}