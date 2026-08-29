<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\DynamicSldEngineService;
use App\Models\PenyulangModel;

/**
 * Controller for Dynamic Single Line Diagram (CR-06H)
 */
class DynamicSldController extends BaseController
{
    protected DynamicSldEngineService $sldService;
    protected PenyulangModel $penyulangModel;

    public function __construct()
    {
        $this->sldService     = new DynamicSldEngineService();
        $this->penyulangModel = new PenyulangModel();
    }

    /**
     * View Feeder SLD page
     */
    public function view($penyulangId = null)
    {
        $feeders = $this->penyulangModel->orderBy('nama_penyulang', 'ASC')->findAll();
        
        $selectedFeederId = $penyulangId ? (int)$penyulangId : (int)($feeders[0]['id'] ?? 1);
        $sldData = $this->sldService->renderFeederSld($selectedFeederId);

        return view('sld/index', [
            'title'            => 'Dynamic Single Line Diagram (SLD)',
            'feeders'          => $feeders,
            'selectedFeederId' => $selectedFeederId,
            'sldData'          => $sldData,
        ]);
    }

    /**
     * API: Feeder SLD Graph JSON
     */
    public function getFeederGraph($penyulangId)
    {
        $data = $this->sldService->renderFeederSld((int)$penyulangId);
        return $this->response->setJSON($data);
    }

    /**
     * API: Section Detail Drilldown JSON
     */
    public function getSectionDetail($sectionId)
    {
        $data = $this->sldService->getSectionDrilldownDetails((int)$sectionId);
        return $this->response->setJSON($data);
    }
}
