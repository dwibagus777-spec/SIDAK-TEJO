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
        return view('inspections/start', [
            'types'     => $this->catalogService->getInspectionTypes(),
            'baselines' => $this->baselineService->getBaselines(),
        ]);
    }

    public function storeStart()
    {
        $typeId     = (int)$this->request->getPost('inspection_type_id');
        $baselineId = (int)$this->request->getPost('baseline_id');
        $userId     = (int)(session()->get('user_id') ?: 1);

        try {
            $run = $this->executionService->startInspection($typeId, $baselineId, $userId);
            return redirect()->to(site_url('inspections/guided/' . $run['inspection_id']))->with('success', 'Sesi inspeksi berhasil dimulai!');
        } catch (\Throwable $e) {
            return redirect()->to(site_url('inspections/start'))->with('error', $e->getMessage());
        }
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
            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
