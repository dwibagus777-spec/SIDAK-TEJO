<?php

namespace App\Models;

use CodeIgniter\Model;

class InspeksiKubikelModel extends Model
{
    protected $table            = 'inspeksi_kubikel';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nomor_inspeksi',
        'inspection_type_id',
        'ulp_id',
        'penyulang_id',
        'section_id',
        'gardu_induk_id',
        'asset_id',
        'id_pel',
        'inspector_user_id',
        'tgl_inspeksi',
        'waktu_mulai',
        'waktu_selesai',
        'status',
        'status_temuan',
        'catatan_umum',
        'alasan_penutupan',
        'supervisor_approval_user_id',
        'supervisor_approval_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'tgl_inspeksi'     => 'required|valid_date',
        'inspector_user_id' => 'required|integer',
        'status'           => 'in_list[DRAFT,IN_PROGRESS,SELESAI,DITUTUP,DIBATALKAN]',
        'status_temuan'    => 'in_list[ADA,TIDAK_ADA,MENUNGGU_VERIFIKASI]',
    ];

    protected $validationMessages = [
        'tgl_inspeksi' => [
            'required'   => 'Tanggal inspeksi wajib diisi.',
            'valid_date' => 'Format tanggal inspeksi tidak valid.',
        ],
    ];

    public function getInspeksiByUser(int $userId, string $status = null): array
    {
        $builder = $this->where('inspector_user_id', $userId);
        if ($status) {
            $builder->where('status', $status);
        }
        return $builder->orderBy('tgl_inspeksi', 'DESC')->orderBy('id', 'DESC')->findAll();
    }

    public function getInspeksiByPenyulang(int $penyulangId, string $status = null): array
    {
        $builder = $this->where('penyulang_id', $penyulangId);
        if ($status) {
            $builder->where('status', $status);
        }
        return $builder->orderBy('tgl_inspeksi', 'DESC')->findAll();
    }

    public function getInspeksiByRole(string $role, int $userId, int $ulpId = null): array
    {
        $builder = $this->select('inspeksi_kubikel.*, users.nama_pegawai as inspector_nama');

        switch (strtolower($role)) {
            case 'beyond_kwh':
                // Beyond KWH hanya melihat inspeksi yang punya temuan untuk mereka
                $builder->join('inspeksi_kubikel_temuan', 'inspeksi_kubikel_temuan.inspeksi_kubikel_id = inspeksi_kubikel.id')
                    ->join('inspeksi_kubikel_rekomendasi', 'inspeksi_kubikel_rekomendasi.temuan_id = inspeksi_kubikel_temuan.id')
                    ->where('inspeksi_kubikel_rekomendasi.penerima', 'BEYOND_KWH')
                    ->where('inspeksi_kubikel_rekomendasi.penerima_user_id', $userId)
                    ->groupBy('inspeksi_kubikel.id');
                break;
            case 'administrator':
            case 'admin':
            case 'admin_pusat':
                // Admin melihat semua
                break;
            default:
                // ULP/HAR GTT melihat inspeksi di wilayah mereka
                if ($ulpId) {
                    $builder->where('ulp_id', $ulpId);
                } else {
                    $builder->where('inspector_user_id', $userId);
                }
        }

        return $builder->orderBy('tgl_inspeksi', 'DESC')->orderBy('id', 'DESC')->findAll();
    }

    public function generateNomorInspeksi(): string
    {
        $date = date('Ymd');
        $last = $this->where('nomor_inspeksi LIKE', "INK-KUB-$date-%")
            ->orderBy('id', 'DESC')
            ->first();

        $sequence = 1;
        if ($last && preg_match('/INK-KUB-' . $date . '-(\d+)/', $last['nomor_inspeksi'], $matches)) {
            $sequence = (int)$matches[1] + 1;
        }

        return sprintf('INK-KUB-%s-%04d', $date, $sequence);
    }
}