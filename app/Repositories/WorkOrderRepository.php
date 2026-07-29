<?php

namespace App\Repositories;

use App\Models\WorkOrderModel;
use CodeIgniter\Database\BaseResult;

class WorkOrderRepository
{
    private WorkOrderModel $model;

    public function __construct()
    {
        $this->model = new WorkOrderModel();
    }

    public function find(int $id): ?array
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('work_orders wo');
            $builder->select('wo.*, a.nama_asset, a.kode_asset, a.jenis_asset, a.status as status_asset, t.nomor_temuan, t.detail_temuan, u.nama_ulp, p.nama_penyulang');
            $builder->join('assets a', 'wo.asset_id = a.id', 'left');
            $builder->join('temuan t', 'wo.temuan_id = t.id', 'left');
            $builder->join('ulps u', 'a.ulp_id = u.id', 'left');
            $builder->join('penyulang p', 'a.penyulang_id = p.id', 'left');
            $builder->where('wo.id', $id);
            $builder->where('wo.deleted_at IS NULL');

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[WorkOrderRepository::find] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown') . ' | SQL: ' . (string)$db->getLastQuery());
                return null;
            }

            $wo = $query->getRowArray();
            if (!$wo) return null;

            // Fetch checklists
            $qChk = $db->table('wo_checklists')->where('wo_id', $id)->get();
            $wo['checklists'] = ($qChk && ($qChk instanceof BaseResult)) ? $qChk->getResultArray() : [];

            // Fetch materials
            $qMat = $db->table('wo_materials')->where('wo_id', $id)->get();
            $wo['materials'] = ($qMat && ($qMat instanceof BaseResult)) ? $qMat->getResultArray() : [];

            // Fetch histories
            $qHist = $db->table('wo_histories')->where('wo_id', $id)->orderBy('id', 'DESC')->get();
            $wo['histories'] = ($qHist && ($qHist instanceof BaseResult)) ? $qHist->getResultArray() : [];

            // Calculate checklist progress percentage
            $totalCheck = count($wo['checklists']);
            $completedCheck = 0;
            foreach ($wo['checklists'] as $chk) {
                if (!empty($chk['is_completed'])) $completedCheck++;
            }
            $wo['checklist_percentage'] = $totalCheck > 0 ? round(($completedCheck / $totalCheck) * 100) : 0;

            return $wo;
        } catch (\Throwable $e) {
            log_message('error', '[WorkOrderRepository::find] Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function getFilteredWorkOrders(array $filters = [], ?int $userUlpId = null): array
    {
        try {
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

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[WorkOrderRepository::getFilteredWorkOrders] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown') . ' | SQL: ' . (string)$db->getLastQuery());
                return [];
            }

            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[WorkOrderRepository::getFilteredWorkOrders] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function getWOStats(?int $userUlpId = null): array
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('work_orders wo');
            $builder->join('assets a', 'wo.asset_id = a.id', 'left');
            $builder->where('wo.deleted_at IS NULL');

            if (!empty($userUlpId)) {
                $builder->where('a.ulp_id', $userUlpId);
            }

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[WorkOrderRepository::getWOStats] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown') . ' | SQL: ' . (string)$db->getLastQuery());
                return ['total' => 0, 'aktif' => 0, 'selesai' => 0, 'overdue' => 0];
            }

            $all = $query->getResultArray();

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
        } catch (\Throwable $e) {
            log_message('error', '[WorkOrderRepository::getWOStats] Exception: ' . $e->getMessage());
            return ['total' => 0, 'aktif' => 0, 'selesai' => 0, 'overdue' => 0];
        }
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
        try {
            $db = \Config\Database::connect();
            $query = $db->table('wo_checklists')->where('id', $chkId)->get();
            if ($query === false || !($query instanceof BaseResult)) {
                log_message('error', '[WorkOrderRepository::toggleChecklist] Query gagal');
                return false;
            }
            $chk = $query->getRowArray();
            if (!$chk) return false;

            $newVal = $chk['is_completed'] ? 0 : 1;
            return $db->table('wo_checklists')->where('id', $chkId)->update([
                'is_completed' => $newVal,
                'completed_by' => $newVal ? $userName : null,
                'completed_at' => $newVal ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[WorkOrderRepository::toggleChecklist] Exception: ' . $e->getMessage());
            return false;
        }
    }

    public function addMaterial(array $data): bool
    {
        $db = \Config\Database::connect();
        return $db->table('wo_materials')->insert($data);
    }
}
