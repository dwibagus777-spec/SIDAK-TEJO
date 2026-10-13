<?php

namespace App\Models;

use CodeIgniter\Model;

class InspeksiKubikelFotoModel extends Model
{
    protected $table            = 'inspeksi_kubikel_foto';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'inspeksi_kubikel_id',
        'checklist_id',
        'kategori',
        'nama_file',
        'path_file',
        'mime_type',
        'ukuran',
        'deskripsi',
        'uploaded_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';

    protected $validationRules = [
        'inspeksi_kubikel_id' => 'required|integer',
        'kategori'            => 'in_list[SEBELUM,SESUDAH,TEMUAN,BUKTI,LAINNYA]',
        'nama_file'           => 'required|max_length[255]',
        'path_file'           => 'required|max_length[500]',
        'mime_type'           => 'required|max_length[100]',
    ];

    public function getByInspeksi(int $inspeksiId): array
    {
        return $this->where('inspeksi_kubikel_id', $inspeksiId)
            ->orderBy('kategori', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function getByChecklist(int $checklistId): array
    {
        return $this->where('checklist_id', $checklistId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function getByKategori(int $inspeksiId, string $kategori): array
    {
        return $this->where('inspeksi_kubikel_id', $inspeksiId)
            ->where('kategori', $kategori)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }
}