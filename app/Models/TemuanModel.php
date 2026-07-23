<?php

namespace App\Models;

use CodeIgniter\Model;

class TemuanModel extends Model
{
    protected $table            = 'temuan';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    
    // Soft Delete
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'deleted_at';

    protected $allowedFields    = [
        'nomor_temuan', 'ulp_id', 'penyulang_id', 'section_id', 
        'jenis_temuan', 'pelaksana', 'prioritas', 'potensi_gangguan', 
        'konduktor', 'noga', 'material', 'detail_temuan', 'alamat', 
        'latitude', 'longitude', 'tanggal_temuan', 'tanggal_selesai', 
        'foto', 'status', 'created_by', 'created_by_name', 'created_by_nip',
        'updated_by', 'updated_by_name', 'updated_by_nip',
        'foto_path', 'tindak_lanjut', 'catatan_tindak_lanjut'
    ];

    // Timestamps
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Dapatkan Top 10 Petugas yang paling banyak meng-input temuan
     */
    public function getTopInputOfficers($month = null, $year = null, $ulpId = null)
    {
        $builder = $this->db->table($this->table);
        $builder->select('created_by, created_by_name, created_by_nip, COUNT(id) as total_input');
        $builder->where('deleted_at IS NULL');
        $builder->where('created_by_name IS NOT NULL');
        $builder->where('created_by_name !=', '');

        if ($month) {
            $builder->where('MONTH(tanggal_temuan)', $month);
        }
        if ($year) {
            $builder->where('YEAR(tanggal_temuan)', $year);
        }
        if ($ulpId) {
            $builder->where('ulp_id', $ulpId);
        }

        $builder->groupBy(['created_by_name', 'created_by_nip']);
        $builder->orderBy('total_input', 'DESC');
        $builder->limit(10);

        return $builder->get()->getResultArray();
    }

    /**
     * Dapatkan Top 10 Petugas yang paling banyak melakukan update/penyelesaian temuan
     */
    public function getTopUpdateOfficers($month = null, $year = null, $ulpId = null)
    {
        $builder = $this->db->table($this->table);
        $builder->select('updated_by, updated_by_name, updated_by_nip, COUNT(id) as total_update');
        $builder->where('deleted_at IS NULL');
        $builder->where('updated_by_name IS NOT NULL');
        $builder->where('updated_by_name !=', '');
        $builder->where('status', 'SELESAI');

        if ($month) {
            $builder->where('MONTH(updated_at)', $month);
        }
        if ($year) {
            $builder->where('YEAR(updated_at)', $year);
        }
        if ($ulpId) {
            $builder->where('ulp_id', $ulpId);
        }

        $builder->groupBy(['updated_by_name', 'updated_by_nip']);
        $builder->orderBy('total_update', 'DESC');
        $builder->limit(10);

        return $builder->get()->getResultArray();
    }
}
