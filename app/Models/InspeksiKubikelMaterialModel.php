<?php

namespace App\Models;

use CodeIgniter\Model;

class InspeksiKubikelMaterialModel extends Model
{
    protected $table            = 'inspeksi_kubikel_material';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'inspeksi_kubikel_id',
        'temuan_id',
        'kode_material',
        'nama_material',
        'satuan',
        'jumlah_rencana',
        'jumlah_digunakan',
        'sisa',
        'status',
        'catatan',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'inspeksi_kubikel_id' => 'required|integer',
        'nama_material'       => 'required|max_length[255]',
        'status'              => 'in_list[DIRNCANA,DIPESAN,DITERIMA,DIGUNAKAN,SELESAI]',
    ];

    public function getByInspeksi(int $inspeksiId): array
    {
        return $this->where('inspeksi_kubikel_id', $inspeksiId)
            ->orderBy('status', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    public function getByTemuan(int $temuanId): array
    {
        return $this->where('temuan_id', $temuanId)
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    public function getRencanaByInspeksi(int $inspeksiId): array
    {
        return $this->where('inspeksi_kubikel_id', $inspeksiId)
            ->where('temuan_id IS NULL')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    public function updateSisa(int $id): bool
    {
        $item = $this->find($id);
        if (!$item) return false;

        $rencana = $item['jumlah_rencana'] ?? 0;
        $digunakan = $item['jumlah_digunakan'] ?? 0;
        $sisa = max(0, $rencana - $digunakan);

        return $this->update($id, ['sisa' => $sisa]);
    }
}