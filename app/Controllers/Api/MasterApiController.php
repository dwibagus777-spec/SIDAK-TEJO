<?php

namespace App\Controllers\Api;

use App\Repositories\UlpRepository;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;

class MasterApiController extends BaseApiController
{
    private UlpRepository $ulpRepository;
    private PenyulangRepository $penyulangRepository;
    private SectionRepository $sectionRepository;

    public function __construct()
    {
        $this->ulpRepository = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
        $this->sectionRepository = new SectionRepository();
    }

    /**
     * GET /api/v1/master/ulps
     */
    public function ulps()
    {
        $ulps = $this->ulpRepository->getActiveUlps();
        return $this->respondSuccess($ulps, 'Daftar master ULP aktif.');
    }

    /**
     * GET /api/v1/master/penyulangs
     */
    public function penyulangs()
    {
        $ulpId = $this->request->getGet('ulp_id');
        if (!empty($ulpId)) {
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp((int)$ulpId);
        } else {
            $penyulangs = $this->penyulangRepository->getActivePenyulangs();
        }

        return $this->respondSuccess($penyulangs, 'Daftar master Penyulang.');
    }

    /**
     * GET /api/v1/master/sections
     */
    public function sections()
    {
        $penyulangId = $this->request->getGet('penyulang_id');
        if (!empty($penyulangId)) {
            $sections = $this->sectionRepository->getActiveSectionsByPenyulang((int)$penyulangId);
        } else {
            $sections = $this->sectionRepository->findAll();
        }

        return $this->respondSuccess($sections, 'Daftar master Section.');
    }
}
