<?php

namespace App\Models;

use CodeIgniter\Model;

class InspeksiKubikelRekomendasiModel extends Model
{
    protected $table            = 'inspeksi_kubikel_rekomendasi';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'temuan_id',
        'penerima',
        'penerima_user_id',
        'deskripsi',
        'status',
        'waktu_kirim',
        'waktu_baca',
        'waktu_selesai',
        'catatan_penerima',
        'notification_id',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'temuan_id'       => 'required|integer',
        'penerima'        => 'in_list[ULP,HAR_GTT,BEYOND_KWH,ADMINISTRATOR,LAINNYA]',
        'deskripsi'       => 'required',
        'status'          => 'in_list[TERKIRIM,DIBACA,DIPROSES,SELESAI,DITOLAK]',
    ];

    public function getByTemuan(int $temuanId): array
    {
        return $this->where('temuan_id', $temuanId)
            ->orderBy('waktu_kirim', 'DESC')
            ->findAll();
    }

    public function getByPenerimaUser(int $userId, string $status = null): array
    {
        $builder = $this
            ->select('inspeksi_kubikel_rekomendasi.*, inspeksi_kubikel_temuan.komponen AS temuan_komponen, inspeksi_kubikel_temuan.fasa AS temuan_fasa, inspeksi_kubikel_temuan.posisi AS temuan_posisi, inspeksi_kubikel_temuan.deskripsi_temuan, inspeksi_kubikel_temuan.urgensi AS temuan_urgensi, inspeksi_kubikel_temuan.status_temuan AS status_temuan, inspeksi_kubikel.nomor_inspeksi, inspeksi_kubikel.tgl_inspeksi')
            ->join('inspeksi_kubikel_temuan', 'inspeksi_kubikel_temuan.id = inspeksi_kubikel_rekomendasi.temuan_id')
            ->join('inspeksi_kubikel', 'inspeksi_kubikel.id = inspeksi_kubikel_temuan.inspeksi_kubikel_id')
            ->where('inspeksi_kubikel_rekomendasi.penerima', 'BEYOND_KWH')
            ->where('inspeksi_kubikel_rekomendasi.penerima_user_id', $userId)
            ->where('inspeksi_kubikel_temuan.deleted_at', null)
            ->where('inspeksi_kubikel.deleted_at', null);
        if ($status) {
            $builder->where('inspeksi_kubikel_rekomendasi.status', $status);
        }
        return $builder->orderBy('inspeksi_kubikel_rekomendasi.waktu_kirim', 'DESC')->findAll();
    }

    public function getUnreadByPenerima(int $userId): array
    {
        return $this->where('penerima_user_id', $userId)
            ->where('status', 'TERKIRIM')
            ->where('waktu_baca IS NULL')
            ->orderBy('waktu_kirim', 'DESC')
            ->findAll();
    }

    public function markAsRead(int $id, int $userId): bool
    {
        return $this->update($id, [
            'status'      => 'DIBACA',
            'waktu_baca'  => date('Y-m-d H:i:s'),
            'penerima_user_id' => $userId,
        ]);
    }
}