<?php

namespace App\Controllers;

use App\Models\InspectionPlanningModel;
use App\Models\InspectionPlanningAssetModel;
use App\Models\GarduIndukModel;
use App\Repositories\UlpRepository;
use App\Repositories\PenyulangRepository;
use App\Services\InspectionCatalogService;
use App\Services\BaselineService;
use App\Services\InspectionExecutionService;
use App\Models\UserModel;
use Config\Database;

class InspectionPlanningController extends BaseController
{
    private InspectionPlanningModel $planningModel;
    private InspectionPlanningAssetModel $planningAssetModel;
    private GarduIndukModel $giModel;
    private UlpRepository $ulpRepo;
    private PenyulangRepository $penyulangRepo;
    private InspectionCatalogService $catalogService;
    private BaselineService $baselineService;
    private InspectionExecutionService $executionService;

    public function __construct()
    {
        $this->planningModel      = new InspectionPlanningModel();
        $this->planningAssetModel = new InspectionPlanningAssetModel();
        $this->giModel            = new GarduIndukModel();
        $this->ulpRepo            = new UlpRepository();
        $this->penyulangRepo      = new PenyulangRepository();
        $this->catalogService     = new InspectionCatalogService();
        $this->baselineService    = new BaselineService();
        $this->executionService   = new InspectionExecutionService();
    }

    /**
     * GET /planning (Admin/SPV Planning List)
     */
    public function index()
    {
        if (!check_role(['administrator', 'admin', 'admin_ulp', 'spv', 'supervisor'])) {
            return redirect()->to(site_url('my-inspections'));
        }

        $db = Database::connect();
        $builder = $db->table('inspection_plannings p');
        $builder->select('p.*, t.name as type_name, gi.nama_gi, u.nama_ulp, peny.nama_penyulang, usr.nama_lengkap as inspector_name');
        $builder->join('inspection_types t', 'p.inspection_type_id = t.id', 'left');
        $builder->join('gardu_induk gi', 'p.gi_id = gi.id', 'left');
        $builder->join('ulps u', 'p.ulp_id = u.id', 'left');
        $builder->join('penyulang peny', 'p.penyulang_id = peny.id', 'left');
        $builder->join('users usr', 'p.assigned_inspector_id = usr.id', 'left');
        $builder->orderBy('p.id', 'DESC');

        $plannings = $builder->get()->getResultArray();

        return view('planning/index', [
            'plannings' => $plannings,
        ]);
    }

    /**
     * GET /planning/create (Admin Planning Form)
     */
    public function create()
    {
        if (!check_role(['administrator', 'admin', 'admin_ulp', 'spv', 'supervisor'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $userModel = new UserModel();
        $inspectors = $userModel->where('status', 'AKTIF')->findAll();

        return view('planning/create', [
            'types'      => $this->catalogService->getInspectionTypes(),
            'garduInduk' => $this->giModel->getActiveGi(),
            'ulps'       => $this->ulpRepo->getActiveUlps(),
            'penyulangs' => $this->penyulangRepo->getModel()->where('status', 'AKTIF')->findAll(),
            'inspectors' => $inspectors,
        ]);
    }

    /**
     * POST /planning/store (Store Draft or Published Planning)
     */
    public function store()
    {
        if (!check_role(['administrator', 'admin', 'admin_ulp', 'spv', 'supervisor'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $action     = $this->request->getPost('action') ?: 'draft';
        $title      = trim($this->request->getPost('title') ?: '');
        $typeId     = (int)$this->request->getPost('inspection_type_id');
        $giId       = (int)$this->request->getPost('gi_id') ?: null;
        $ulpId      = (int)$this->request->getPost('ulp_id') ?: null;
        $penyulangId = (int)$this->request->getPost('penyulang_id') ?: null;
        $jenisAsset = $this->request->getPost('jenis_asset') ?: 'SEMUA';
        $inspectorId = (int)$this->request->getPost('assigned_inspector_id') ?: null;
        $scheduledDate = $this->request->getPost('scheduled_date') ?: date('Y-m-d');
        $selectedAssetIds = $this->request->getPost('asset_ids') ?: [];

        if ($title === '' || $typeId <= 0) {
            return redirect()->to(site_url('planning/create'))->with('error', 'Judul Planning dan Jenis Inspeksi wajib diisi.');
        }

        $status = ($action === 'publish') ? 'PUBLISHED' : 'DRAFT';
        $publishedAt = ($status === 'PUBLISHED') ? date('Y-m-d H:i:s') : null;
        $nomorPlanning = $this->planningModel->generateNomorPlanning();
        $currentUserId = (int)(session()->get('user_id') ?: 1);

        $planningId = $this->planningModel->insert([
            'nomor_planning'        => $nomorPlanning,
            'title'                 => $title,
            'inspection_type_id'    => $typeId,
            'gi_id'                 => $giId,
            'ulp_id'                => $ulpId,
            'penyulang_id'          => $penyulangId,
            'jenis_asset'           => $jenisAsset,
            'assigned_inspector_id' => $inspectorId,
            'created_by_user_id'    => $currentUserId,
            'scheduled_date'        => $scheduledDate,
            'published_at'          => $publishedAt,
            'total_assets'          => count($selectedAssetIds),
            'status'                => $status,
        ]);

        // Save Asset Snapshots sequentially
        $seq = 1;
        foreach ($selectedAssetIds as $astId) {
            $astIdInt = (int)$astId;
            if ($astIdInt > 0) {
                try {
                    $this->planningAssetModel->insert([
                        'planning_id' => $planningId,
                        'asset_id'    => $astIdInt,
                        'sequence_no' => $seq++,
                    ]);
                } catch (\Throwable $exSnap) {}
            }
        }

        log_activity('CREATE_PLANNING', "Membuat Planning Inspeksi {$nomorPlanning}: {$title}");

        $msg = ($status === 'PUBLISHED') 
            ? "Planning Inspeksi '{$title}' ({$nomorPlanning}) berhasil dipublikasikan & ditugaskan!" 
            : "Draft Planning Inspeksi '{$title}' ({$nomorPlanning}) berhasil disimpan.";

        return redirect()->to(site_url('planning'))->with('success', $msg);
    }

    /**
     * POST /planning/publish/(:num)
     */
    public function publish(int $id)
    {
        $planning = $this->planningModel->find($id);
        if (!$planning) {
            return redirect()->to(site_url('planning'))->with('error', 'Planning tidak ditemukan.');
        }

        $this->planningModel->update($id, [
            'status'       => 'PUBLISHED',
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(site_url('planning'))->with('success', "Planning '{$planning['nomor_planning']}' berhasil dipublikasikan!");
    }

    /**
     * GET /planning/detail/(:num)
     */
    public function detail(int $id)
    {
        $planning = $this->planningModel->find($id);
        if (!$planning) {
            return redirect()->to(site_url('planning'))->with('error', 'Planning tidak ditemukan.');
        }

        $db = Database::connect();
        $builder = $db->table('inspection_planning_assets pa');
        $builder->select('pa.sequence_no, a.id as asset_id, a.kode_asset, a.nama_asset, a.jenis_asset, a.status as asset_status, a.lokasi');
        $builder->join('assets a', 'pa.asset_id = a.id');
        $builder->where('pa.planning_id', $id);
        $builder->orderBy('pa.sequence_no', 'ASC');

        $assets = $builder->get()->getResultArray();

        return view('planning/detail', [
            'planning' => $planning,
            'assets'   => $assets,
        ]);
    }

    /**
     * GET /my-inspections (Inspector Mobile/Web Task Dashboard)
     */
    public function myInspections()
    {
        $userId = (int)(session()->get('user_id') ?: 1);

        $db = Database::connect();
        $builder = $db->table('inspection_plannings p');
        $builder->select('p.*, t.name as type_name, gi.nama_gi, u.nama_ulp, peny.nama_penyulang');
        $builder->join('inspection_types t', 'p.inspection_type_id = t.id', 'left');
        $builder->join('gardu_induk gi', 'p.gi_id = gi.id', 'left');
        $builder->join('ulps u', 'p.ulp_id = u.id', 'left');
        $builder->join('penyulang peny', 'p.penyulang_id = peny.id', 'left');
        
        // Inspector can see tasks assigned to them or unassigned published tasks
        $builder->where("p.status IN ('PUBLISHED', 'ASSIGNED', 'IN_PROGRESS')");
        $builder->groupStart();
        $builder->where('p.assigned_inspector_id', $userId);
        $builder->orWhere('p.assigned_inspector_id IS NULL');
        $builder->groupEnd();
        $builder->orderBy('p.scheduled_date', 'ASC');

        $myTasks = $builder->get()->getResultArray();

        return view('planning/my_inspections', [
            'tasks' => $myTasks,
        ]);
    }
}
