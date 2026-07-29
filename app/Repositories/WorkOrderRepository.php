<?php

namespace App\Repositories;

use App\Models\WorkOrderModel;

class WorkOrderRepository
{
    private WorkOrderModel $model;

    public function __construct()
    {
        $this->model = new WorkOrderModel();
    }

    public function find(int $id): ?array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('work_orders wo');
        $builder->select('wo.*, a.nama_asset, a.kode_asset, a.jenis_asset, a.status as status_asset, t.nomor_temuan, t.detail_temuan, u.nama_ulp, p.nama_penyulang');
        $builder->join('assets a', 'wo.asset_id = a.id', 'left');
        $builder->join('temuan t', 'wo.temuan_id = t.id', 'left');
        $builder->join('ulps u', 'a.ulp_id = u.id', 'left');
        $builder->join('penyulang p', 'a.penyulang_id = p.id', 'left');
        $builder->where('wo.id', $id);
        $builder->where('wo.deleted_at IS NULL');

        $wo = $builder->get()->getRowArray();
        if (!$wo) return null;

        // Fetch checklists
        $wo['checklists'] = $db->table('wo_checklists')->where('wo_id', $id)->get()->getResultArray();

        // Fetch materials
        $wo['materials'] = $db->table('wo_materials')->where('wo_id', $id)->get()->getResultArray();

        // Fetch histories
        $wo['histories'] = $db->table('wo_histories')->where('wo_id', $id)->orderBy('id', 'DESC')->get()->getResultArray();

        // Calculate checklist progress percentage
        $totalCheck = count($wo['checklists']);
        $completedCheck = 0;
        foreach ($wo['checklists'] as $chk) {
            if (!empty($chk['is_completed'])) $completedCheck++;
        }
        $wo['checklist_percentage'] = $totalCheck > 0 ? round(($completedCheck / $totalCheck) * 100) : 0;

        return $wo;
    }

    public function getFilteredWorkOrders(array $filters = [], ?int $userUlpId = null): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('work_orders wo');
        $builder->select('wo.*, a.nama_asset, a.kode_asset, a.jenis_asset, t.nomor_temuan, u.nama_ulp, p.nama_penyulang');
        $builder->join('assets a', 'wo.asset_id = a.id', 'left');
        $builder->join('temuan t', 'wo.temuan_id = t.id', 'left');
        $builder->join('ulps u', 'a.ulp_id = u.id', 'left');
        $builder->join('penyulang p', 'a.penyulang_id = p.id', 'left');
        $builder->where('wo.deleted_at IS NULL');

        if (!empty($userUlpId)) {
            $builder->where('a.ulp_id', $userUlpId);
        }

        if (!empty($filters['ulp_id'])) {
            $builder->where('a.ulp_id', (int)$filters['ulp_id']);
        }
        if (!empty($filters['status'])) {
            $builder->where('wo.status', strtoupper($filters['status']));
        }
        if (!empty($filters['prioritas'])) {
            $builder->where('wo.prioritas', strtoupper($filters['prioritas']));
        }
        if (!empty($filters['pelaksana'])) {
            $builder->where('wo.pelaksana', $filters['pelaksana']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $builder->groupStart()
                ->like('wo.nomor_wo', $s)
                ->orLike('wo.judul_wo', $s)
                ->orLike('wo.assigned_to', $s)
                ->orLike('a.nama_asset', $s)
            ->groupEnd();
        }

        $builder->orderBy('wo.id', 'DESC');
        return $builder->get()->getResultArray();
    }

    public function getWOStats(?int $userUlpId = null): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('work_orders wo');
        $builder->join('assets a', 'wo.asset_id = a.id', 'left');
        $builder->where('wo.deleted_at IS NULL');

        if (!empty($userUlpId)) {
            $builder->where('a.ulp_id', $userUlpId);
        }

        $all = $builder->get()->getResultArray();

        $total = count($all);
        $aktif = 0;
        $selesai = 0;
        $overdue = 0;

        $now = time();
        foreach ($all as $row) {
            $st = strtoupper($row['status'] ?? 'OPEN');
            if ($st === 'COMPLETED') {
                $selesai++;
            } elseif ($st !== 'CANCELLED') {
                $aktif++;
                if (!empty($row['target_selesai']) && strtotime($row['target_selesai']) < $now) {
                    $overdue++;
                }
            }
        }

        return [
            'total'   => $total,
            'aktif'   => $aktif,
            'selesai' => $selesai,
            'overdue' => $overdue,
        ];
    }

    public function insert(array $data): int
    {
        return (int)$this->model->insert($data, true);
    }

    public function update(int $id, array $data): bool
    {
        return (bool)$this->model->update($id, $data);
    }

    public function addHistory(array $data): bool
    {
        $db = \Config\Database::connect();
        return $db->table('wo_histories')->insert($data);
    }

    public function addChecklist(int $woId, string $itemText): bool
    {
        $db = \Config\Database::connect();
        return $db->table('wo_checklists')->insert([
            'wo_id'        => $woId,
            'item_text'    => $itemText,
            'is_completed' => 0,
        ]);
    }

    public function toggleChecklist(int $chkId, string $userName): bool
    {
        $db = \Config\Database::connect();
        $chk = $db->table('wo_checklists')->where('id', $chkId)->get()->getRowArray();
        if (!$chk) return false;

        $newVal = $chk['is_completed'] ? 0 : 1;
        return $db->table('wo_checklists')->where('id', $chkId)->update([
            'is_completed' => $newVal,
            'completed_by' => $newVal ? $userName : null,
            'completed_at' => $newVal ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function addMaterial(array $data): bool
    {
        $db = \Config\Database::connect();
        return $db->table('wo_materials')->insert($data);
    }
}
