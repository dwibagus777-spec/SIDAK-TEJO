<?php

namespace App\Controllers;

use App\Services\InspectionCatalogService;
use App\Services\InspectionExecutionService;
use App\Services\BaselineService;
use App\Models\InspectionModel;
use App\Models\InspectionPointModel;

class InspectionController extends BaseController
{
    private InspectionCatalogService $catalogService;
    private InspectionExecutionService $executionService;
    private BaselineService $baselineService;
    private InspectionModel $inspectionModel;
    private InspectionPointModel $pointModel;

    public function __construct()
    {
        $this->catalogService   = new InspectionCatalogService();
        $this->executionService = new InspectionExecutionService();
        $this->baselineService  = new BaselineService();
        $this->inspectionModel  = new InspectionModel();
        $this->pointModel       = new InspectionPointModel();
    }

    public function index()
    {
        $inspections = $this->inspectionModel
            ->select('inspections.*, inspection_types.name as type_name, network_baselines.name as baseline_name')
            ->join('inspection_types', 'inspection_types.id = inspections.inspection_type_id', 'left')
            ->join('network_baselines', 'network_baselines.id = inspections.baseline_id', 'left')
            ->orderBy('inspections.id', 'DESC')
            ->findAll();

        return view('inspections/index', [
            'inspections' => $inspections,
        ]);
    }

    public function start()
    {
        $giModel = new \App\Models\GarduIndukModel();
        $ulpRepo = new \App\Repositories\UlpRepository();
        $penyulangRepo = new \App\Repositories\PenyulangRepository();

        return view('inspections/start', [
            'types'      => $this->catalogService->getInspectionTypes(),
            'garduInduk' => $giModel->getActiveGi(),
            'ulps'       => $ulpRepo->getActiveUlps(),
            'penyulangs' => $penyulangRepo->getModel()->where('status', 'AKTIF')->findAll(),
            'baselines'  => $this->baselineService->getBaselines(),
        ]);
    }

    public function storeStart()
    {
        $typeId      = (int)$this->request->getPost('inspection_type_id');
        $baselineId  = (int)$this->request->getPost('baseline_id');
        $penyulangId = (int)$this->request->getPost('penyulang_id');
        $planningId  = (int)$this->request->getPost('planning_id');
        $objectType  = $this->request->getPost('object_type') ?: 'SEMUA';
        $userId      = (int)(session()->get('user_id') ?: 1);

        try {
            if ($planningId > 0) {
                try {
                    $planningModel = new \App\Models\InspectionPlanningModel();
                    $planning = $planningModel->find($planningId);
                    $updateData = ['status' => 'IN_PROGRESS'];
                    if ($planning && empty($planning['assigned_inspector_id'])) {
                        $updateData['assigned_inspector_id'] = $userId;
                    }
                    $planningModel->update($planningId, $updateData);
                } catch (\Throwable $exP) {}
            }

            if ($baselineId <= 0 && $penyulangId > 0) {
                $resolved = $this->baselineService->resolveBaselineForPenyulang($penyulangId, $objectType);
                if ($resolved) {
                    $baselineId = (int)$resolved['id'];
                }
            }

            if ($baselineId <= 0 && $planningId <= 0) {
                $allBaselines = $this->baselineService->getBaselines();
                if (!empty($allBaselines)) {
                    $baselineId = (int)$allBaselines[0]['id'];
                }
            }

            if ($baselineId <= 0 && $planningId <= 0) {
                return redirect()->to(site_url('inspections/start'))->with('error', 'Belum ada rute baseline yang tersedia untuk kombinasi penyulang ini.');
            }

            $run = $this->executionService->startInspection($typeId, $baselineId, $userId, $objectType, $planningId);
            return redirect()->to(site_url('inspections/guided/' . $run['inspection_id']))->with('success', 'Sesi Guided Inspection berhasil dimulai!');
        } catch (\Throwable $e) {
            return redirect()->to(site_url('inspections/start'))->with('error', $e->getMessage());
        }
    }

    /**
     * Release C: Server-Side Validation & Idempotent Start/Claim from Map Marker
     * GET /inspections/start-by-asset?asset_id=X
     */
    public function startByAsset()
    {
        $assetId = (int)($this->request->getGet('asset_id') ?? 0);
        $userId  = (int)(session()->get('user_id') ?: 1);

        if ($assetId <= 0) {
            return redirect()->to(site_url('gis'))->with('error', 'ASSET_NOT_FOUND: Parameter asset_id tidak valid.');
        }

        $db = \Config\Database::connect();

        // 1. Server-Side Validation Contract: Verify asset exists, is ACTIVE, and deleted_at IS NULL
        $asset = $db->table('assets')
            ->where('id', $assetId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$asset) {
            return redirect()->to(site_url('gis'))->with('error', 'ASSET_SOFT_DELETED: Aset tidak ditemukan atau telah dihapus.');
        }

        if (strtoupper($asset['status'] ?? '') !== 'AKTIF') {
            return redirect()->to(site_url('gis'))->with('error', 'ASSET_INACTIVE: Aset berstatus nonaktif.');
        }

        // 2. Check Feeder Planning Target Contract (with server-side validation of planning_id candidate)
        $candidatePlanningId = (int)($this->request->getGet('planning_id') ?? 0);
        $penyulangId = (int)($asset['penyulang_id'] ?? 0);
        $planning = null;

        if ($candidatePlanningId > 0 && $db->tableExists('inspection_plannings')) {
            $planning = $db->table('inspection_plannings')
                ->where('id', $candidatePlanningId)
                ->where('penyulang_id', $penyulangId)
                ->whereIn('status', ['PUBLISHED', 'IN_PROGRESS'])
                ->get()
                ->getRowArray();
        }

        if (!$planning && $penyulangId > 0 && $db->tableExists('inspection_plannings')) {
            $planning = $db->table('inspection_plannings')
                ->where('penyulang_id', $penyulangId)
                ->whereIn('status', ['PUBLISHED', 'IN_PROGRESS'])
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
        }

        $planningId = $planning ? (int)$planning['id'] : null;

        // 3. Idempotent Check: Check if inspector already has an active inspection session for this feeder/planning
        $existingRun = null;
        if ($planningId > 0) {
            $existingRun = $this->inspectionModel
                ->where('planning_id', $planningId)
                ->where('inspector_user_id', $userId)
                ->where('status', 'IN_PROGRESS')
                ->first();
        }

        if (!$existingRun && $penyulangId > 0) {
            $existingRun = $this->inspectionModel
                ->where('penyulang_id', $penyulangId)
                ->where('inspector_user_id', $userId)
                ->where('status', 'IN_PROGRESS')
                ->first();
        }

        if ($existingRun) {
            // Re-use active session idempotently without creating duplicate inspection runs
            return redirect()->to(site_url('inspections/guided/' . $existingRun['id']))->with('info', 'Melanjutkan sesi inspeksi aktif untuk penyulang ini.');
        }

        // 4. Claim & Start New Session
        $typeId = 1;
        $allTypes = $this->catalogService->getInspectionTypes();
        if (!empty($allTypes)) {
            $typeId = (int)$allTypes[0]['id'];
        }

        $resolved = $this->baselineService->resolveBaselineForPenyulang($penyulangId, 'SEMUA');
        $baselineId = $resolved ? (int)$resolved['id'] : 0;

        if ($baselineId <= 0 && $planningId > 0) {
            $bRes = $this->baselineService->resolveBaselineForPlanning($planningId);
            if ($bRes) $baselineId = (int)($bRes['id'] ?? 0);
        }

        if ($baselineId <= 0) {
            $allBaselines = $this->baselineService->getBaselines();
            if (!empty($allBaselines)) {
                $baselineId = (int)$allBaselines[0]['id'];
            }
        }

        if ($baselineId <= 0) {
            return redirect()->to(site_url('gis'))->with('error', 'PLANNING_NOT_ELIGIBLE: Belum ada rute baseline untuk penyulang aset ini.');
        }

        if ($planningId > 0 && $db->tableExists('inspection_plannings')) {
            try {
                $pModel = new \App\Models\InspectionPlanningModel();
                $pModel->update($planningId, [
                    'status'                 => 'IN_PROGRESS',
                    'assigned_inspector_id' => $userId
                ]);
            } catch (\Throwable $exP) {}
        }

        $run = $this->executionService->startInspection($typeId, $baselineId, $userId, 'SEMUA', $planningId);
        return redirect()->to(site_url('inspections/guided/' . $run['inspection_id']))->with('success', 'Sesi Guided Inspection berhasil dimulai dari marker GIS!');
    }

    public function guided(int $id)
    {
        $inspection = $this->inspectionModel
            ->select('inspections.*, inspection_types.name as type_name, network_baselines.name as baseline_name')
            ->join('inspection_types', 'inspection_types.id = inspections.inspection_type_id', 'left')
            ->join('network_baselines', 'network_baselines.id = inspections.baseline_id', 'left')
            ->where('inspections.id', $id)
            ->first();

        if (!$inspection) {
            return redirect()->to(site_url('inspections'))->with('error', 'Sesi inspeksi tidak ditemukan.');
        }

        $points = $this->pointModel
            ->select('inspection_points.*, assets.kode_asset, assets.nama_asset, assets.jenis_asset, assets.lokasi, assets.latitude, assets.longitude')
            ->join('assets', 'assets.id = inspection_points.asset_id')
            ->where('inspection_points.inspection_id', $id)
            ->where('assets.deleted_at IS NULL')
            ->orderBy('inspection_points.sequence_no', 'ASC')
            ->findAll();

        $templateItems = $this->catalogService->getTemplateItemsForType((int)$inspection['inspection_type_id']);

        return view('inspections/guided', [
            'inspection'    => $inspection,
            'points'        => $points,
            'templateItems' => $templateItems,
        ]);
    }

    public function submitPoint(int $pointId)
    {
        $json = $this->request->getJSON(true);
        if (!$json) {
            return $this->response->setJSON(['success' => false, 'message' => 'Payload JSON tidak valid.']);
        }

        try {
            $itemsData  = $json['items'] ?? [];
            $photosData = $json['photos'] ?? [];
            $notes      = $json['notes'] ?? null;

            $result = $this->executionService->savePointResult($pointId, $itemsData, $photosData, $notes);
            $result['csrf_token'] = csrf_token();
            $result['csrf_hash']  = csrf_hash();
            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
