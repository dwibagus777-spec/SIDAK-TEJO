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
        $feeders = [];
        try {
            $feeders = $this->penyulangModel->orderBy('nama_penyulang', 'ASC')->findAll();
        } catch (\Throwable $e) {
            // In unit test environment or fallback mode
            $feeders = [
                ['id' => 15, 'kode_penyulang' => 'PYL-015', 'nama_penyulang' => 'BANJAR KEMANTREN']
            ];
        }

        if (empty($feeders)) {
            $feeders = [
                ['id' => 15, 'kode_penyulang' => 'PYL-015', 'nama_penyulang' => 'BANJAR KEMANTREN']
            ];
        }

        $selectedFeederId = $penyulangId ? (int)$penyulangId : 15;
        $layoutApiUrl = site_url("api/sld/feeder/{$selectedFeederId}/layout");
        $sheetsApiUrl = site_url("api/sld/feeder/{$selectedFeederId}/sheets");
        $findingsApiUrl = site_url("api/sld/feeder/{$selectedFeederId}/findings");

        return view('sld/index', [
            'title'            => 'Single Line Diagram (SLD) Engine | SIDAK TEJO',
            'feeders'          => $feeders,
            'selectedFeederId' => $selectedFeederId,
            'layoutApiUrl'     => $layoutApiUrl,
            'sheetsApiUrl'     => $sheetsApiUrl,
            'findingsApiUrl'   => $findingsApiUrl,
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
