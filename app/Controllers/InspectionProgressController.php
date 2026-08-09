<?php

namespace App\Controllers;

use App\Models\InspectionModel;
use App\Models\InspectionPointModel;
use Config\Database;

class InspectionProgressController extends BaseController
{
    private InspectionModel $inspectionModel;
    private InspectionPointModel $pointModel;

    public function __construct()
    {
        $this->inspectionModel = new InspectionModel();
        $this->pointModel      = new InspectionPointModel();
    }

    /**
     * GET /inspection-progress (Admin/SPV Live Progress Monitoring)
     */
    public function index()
    {
        if (!check_role(['administrator', 'admin', 'admin_ulp', 'spv', 'supervisor'])) {
            return redirect()->to(site_url('my-progress'));
        }

        $db = Database::connect();
        $builder = $db->table('inspections i');
        $builder->select('i.*, t.name as type_name, u.nama_ulp, p.nama_penyulang, COALESCE(usr.nama, usr.username) as inspector_name');
        $builder->join('inspection_types t', 'i.inspection_type_id = t.id', 'left');
        $builder->join('ulps u', 'i.ulp_id = u.id', 'left');
        $builder->join('penyulang p', 'i.penyulang_id = p.id', 'left');
        $builder->join('users usr', 'i.inspector_user_id = usr.id', 'left');
        $builder->orderBy('i.id', 'DESC');

        $inspections = $builder->get()->getResultArray();

        // Calculate progress percentage for each run
        foreach ($inspections as &$run) {
            $total = (int)($run['total_points'] ?? 0);
            $passed = (int)($run['passed_points'] ?? 0);
            $failed = (int)($run['failed_points'] ?? 0);
            $completed = $passed + $failed;
            $percent = ($total > 0) ? round(($completed / $total) * 100) : 0;

            $run['completed_count'] = $completed;
            $run['progress_percent'] = $percent;
        }

        return view('inspection_progress/index', [
            'inspections' => $inspections,
        ]);
    }

    /**
     * GET /inspection-progress/detail/(:num) (SPV/Admin Progress Detail Drill-down)
     */
    public function detail(int $id)
    {
        $inspection = $this->inspectionModel->find($id);
        if (!$inspection) {
            return redirect()->to(site_url('inspection-progress'))->with('error', 'Data inspeksi tidak ditemukan.');
        }

        $db = Database::connect();
        $builder = $db->table('inspection_points ip');
        $builder->select('ip.*, a.kode_asset, a.nama_asset, a.jenis_asset, a.status as asset_status, a.lokasi');
        $builder->join('assets a', 'ip.asset_id = a.id');
        $builder->where('ip.inspection_id', $id);
        $builder->where('a.deleted_at IS NULL');
        $builder->orderBy('ip.sequence_no', 'ASC');

        $points = $builder->get()->getResultArray();

        return view('inspection_progress/detail', [
            'inspection' => $inspection,
            'points'     => $points,
        ]);
    }

    /**
     * GET /my-progress (Inspector Active In-Progress Tasks)
     */
    public function myProgress()
    {
        $userId = (int)(session()->get('user_id') ?: 1);

        $db = Database::connect();
        $builder = $db->table('inspections i');
        $builder->select('i.*, t.name as type_name, u.nama_ulp, p.nama_penyulang');
        $builder->join('inspection_types t', 'i.inspection_type_id = t.id', 'left');
        $builder->join('ulps u', 'i.ulp_id = u.id', 'left');
        $builder->join('penyulang p', 'i.penyulang_id = p.id', 'left');
        $builder->where('i.inspector_user_id', $userId);
        $builder->where("i.status IN ('IN_PROGRESS', 'PAUSED')");
        $builder->orderBy('i.id', 'DESC');

        $activeRuns = $builder->get()->getResultArray();

        foreach ($activeRuns as &$run) {
            $total = (int)($run['total_points'] ?? 0);
            $completed = (int)($run['passed_points'] ?? 0) + (int)($run['failed_points'] ?? 0);
            $run['progress_percent'] = ($total > 0) ? round(($completed / $total) * 100) : 0;
        }

        return view('inspection_progress/my_progress', [
            'runs' => $activeRuns,
        ]);
    }

    /**
     * GET /my-history (Inspector Completed Inspections History)
     */
    public function myHistory()
    {
        $userId = (int)(session()->get('user_id') ?: 1);

        $db = Database::connect();
        $builder = $db->table('inspections i');
        $builder->select('i.*, t.name as type_name, u.nama_ulp, p.nama_penyulang');
        $builder->join('inspection_types t', 'i.inspection_type_id = t.id', 'left');
        $builder->join('ulps u', 'i.ulp_id = u.id', 'left');
        $builder->join('penyulang p', 'i.penyulang_id = p.id', 'left');
        $builder->where('i.inspector_user_id', $userId);
        $builder->where('i.status', 'COMPLETED');
        $builder->orderBy('i.id', 'DESC');

        $historyRuns = $builder->get()->getResultArray();

        return view('inspection_progress/my_history', [
            'runs' => $historyRuns,
        ]);
    }
}
