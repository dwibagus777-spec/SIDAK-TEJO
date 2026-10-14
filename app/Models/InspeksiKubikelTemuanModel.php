<?php

namespace App\Models;

use CodeIgniter\Model;

class InspeksiKubikelTemuanModel extends Model
{
    protected $table            = 'inspeksi_kubikel_temuan';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'inspeksi_kubikel_id',
        'checklist_id',
        'komponen',
        'fasa',
        'posisi',
        'tanggal_temuan',
        'deskripsi_temuan',
        'jenis_potensi',
        'nilai_potensi',
        'urgensi',
        'rekomendasi',
        'penerima_rekomendasi',
        'status_temuan',
        'assigned_to_user_id',
        'verifikasi_user_id',
        'verifikasi_at',
        'fingerprint',
        'idempotency_key',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'inspeksi_kubikel_id'    => 'required|integer',
        'komponen'               => 'required|max_length[100]',
        'fasa'                   => 'in_list[R,S,T,N,TIDAK_BERFASA]',
        'tanggal_temuan'         => 'permit_empty|valid_date',
        'deskripsi_temuan'       => 'required',
        'jenis_potensi'          => 'permit_empty|in_list[TREATMENT_TRAFO,CLEANING_KUBIKEL]',
        'nilai_potensi'          => 'permit_empty|numeric',
        'urgensi'                => 'in_list[RENDAH,SEDANG,TINGGI,KRITIS]',
        'penerima_rekomendasi'   => 'in_list[ULP,HAR_GTT,BEYOND_KWH,ADMINISTRATOR,LAINNYA]',
        'status_temuan'          => 'in_list[BARU,DITUGASKAN,DIPROSES,SELESAI,DIVERIFIKASI,DITOLAK]',
    ];

    public function getByInspeksi(int $inspeksiId): array
    {
        return $this->where('inspeksi_kubikel_id', $inspeksiId)
            ->orderBy('urgensi', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function getByPenerima(string $penerima, int $userId = null): array
    {
        $builder = $this->where('penerima_rekomendasi', $penerima);
        if ($userId) {
            $builder->where('assigned_to_user_id', $userId);
        }
        return $builder->orderBy('urgensi', 'DESC')->orderBy('created_at', 'DESC')->findAll();
    }

    public function getByAssignedUser(int $userId): array
    {
        return $this->where('assigned_to_user_id', $userId)
            ->orderBy('urgensi', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function checkDuplicate(string $fingerprint): ?array
    {
        return $this->where('fingerprint', $fingerprint)
            ->where('deleted_at IS NULL')
            ->first();
    }

    public function generateFingerprint(array $data): string
    {
        $fields = [
            $data['inspeksi_kubikel_id'] ?? '',
            $data['komponen'] ?? '',
            $data['fasa'] ?? '',
            $data['posisi'] ?? '',
            $data['deskripsi_temuan'] ?? '',
        ];
        return hash('sha256', implode('|', $fields));
    }
}