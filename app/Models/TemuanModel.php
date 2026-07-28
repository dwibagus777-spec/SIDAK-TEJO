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
     * Dapatkan Top 10 Petugas yang paling banyak meng-input temuan (SARGable Date Filter)
     */
    public function getTopInputOfficers($month = null, $year = null, $ulpId = null)
    {
        $builder = $this->db->table($this->table);
        $builder->select('created_by, created_by_name, created_by_nip, COUNT(id) as total_input');
        $builder->where('deleted_at IS NULL');
        $builder->where('created_by_name IS NOT NULL');
        $builder->where('created_by_name !=', '');

        if ($month && $year) {
            $startDate = sprintf('%04d-%02d-01', (int)$year, (int)$month);
            $endDate   = date('Y-m-t', strtotime($startDate));
            $builder->where('tanggal_temuan >=', $startDate);
            $builder->where('tanggal_temuan <=', $endDate);
        } elseif ($year) {
            $builder->where('tanggal_temuan >=', sprintf('%04d-01-01', (int)$year));
            $builder->where('tanggal_temuan <=', sprintf('%04d-12-31', (int)$year));
        }

        if ($ulpId) {
            $builder->where('ulp_id', (int)$ulpId);
        }

        $builder->groupBy(['created_by', 'created_by_name', 'created_by_nip']);
        $builder->orderBy('total_input', 'DESC');
        $builder->limit(10);

        return $builder->get()->getResultArray();
    }

    /**
     * Dapatkan Top 10 Petugas yang paling banyak melakukan update/penyelesaian temuan (SARGable Date Filter)
     */
    public function getTopUpdateOfficers($month = null, $year = null, $ulpId = null)
    {
        $builder = $this->db->table($this->table);
        $builder->select('updated_by, updated_by_name, updated_by_nip, COUNT(id) as total_update');
        $builder->where('deleted_at IS NULL');
        $builder->where('updated_by_name IS NOT NULL');
        $builder->where('updated_by_name !=', '');
        $builder->where('status', 'SELESAI');

        if ($month && $year) {
            $startDate = sprintf('%04d-%02d-01 00:00:00', (int)$year, (int)$month);
            $endDate   = date('Y-m-t 23:59:59', strtotime($startDate));
            $builder->where('updated_at >=', $startDate);
            $builder->where('updated_at <=', $endDate);
        } elseif ($year) {
            $builder->where('updated_at >=', sprintf('%04d-01-01 00:00:00', (int)$year));
            $builder->where('updated_at <=', sprintf('%04d-12-31 23:59:59', (int)$year));
        }

        if ($ulpId) {
            $builder->where('ulp_id', (int)$ulpId);
        }

        $builder->groupBy(['updated_by', 'updated_by_name', 'updated_by_nip']);
        $builder->orderBy('total_update', 'DESC');
        $builder->limit(10);

        return $builder->get()->getResultArray();
    }
}
