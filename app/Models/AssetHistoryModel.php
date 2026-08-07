<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetHistoryModel extends Model
{
    protected $table            = 'asset_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'asset_id', 'tanggal', 'jenis_event', 'status_lama', 'status_baru',
        'referensi', 'deskripsi', 'user_id', 'approved_by',
        'foto_sebelum', 'foto_sesudah', 'ip_address', 'user_agent', 'device',
        'created_at', 'updated_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get timeline history for a specific asset
     */
    public function getTimelineByAssetId(int $assetId, int $limit = 50): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('asset_history ah');
        $builder->select('ah.*, u.nama_lengkap as nama_user, app.nama_lengkap as nama_approver');
        $builder->join('users u', 'ah.user_id = u.id', 'left');
        $builder->join('users app', 'ah.approved_by = app.id', 'left');
        $builder->where('ah.asset_id', $assetId);
        $builder->orderBy('ah.id', 'DESC');
        $builder->limit($limit);

        $query = $builder->get();
        return $query ? $query->getResultArray() : [];
    }
}
