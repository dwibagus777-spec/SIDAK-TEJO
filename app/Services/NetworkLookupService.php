<?php

namespace App\Services;

use App\Repositories\UlpRepository;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;
use App\Repositories\AssetRepository;
use App\Models\UidModel;
use App\Models\Up3Model;
use App\Models\GarduIndukModel;

/**
 * MNF-01: Master Network Fabric Domain Service
 * Canonical hierarchy service: UID -> UP3 -> GARDU INDUK -> PENYULANG -> SECTION -> ASSET
 * Also supports administrative hierarchy: UID -> UP3 -> ULP -> PENYULANG -> SECTION -> ASSET
 */
class NetworkLookupService
{
    protected UlpRepository $ulpRepository;
    protected PenyulangRepository $penyulangRepository;
    protected SectionRepository $sectionRepository;
    protected AssetRepository $assetRepository;
    protected ?UidModel $uidModel = null;
    protected ?Up3Model $up3Model = null;
    protected ?GarduIndukModel $garduIndukModel = null;

    public function __construct()
    {
        $this->ulpRepository       = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
        $this->sectionRepository   = new SectionRepository();
        $this->assetRepository     = new AssetRepository();
    }

    /**
     * Level 0a: Get active UIDs (Induk)
     */
    public function getUids(): array
    {
        if ($this->uidModel === null) {
            $this->uidModel = new UidModel();
        }
        return $this->uidModel->getActiveUids();
    }

    /**
     * Level 0b: Get active UP3s by UID ID
     */
    public function getUp3s(int $uidId = 0): array
    {
        if ($this->up3Model === null) {
            $this->up3Model = new Up3Model();
        }
        return $this->up3Model->getActiveUp3sByUid($uidId);
    }

    /**
     * Level 0c: Get active Gardu Induk by UP3 ID
     */
    public function getGarduInduk(int $up3Id = 0): array
    {
        if ($this->garduIndukModel === null) {
            $this->garduIndukModel = new GarduIndukModel();
        }
        $builder = $this->garduIndukModel->where('status', 'AKTIF')->orWhere('status', 'ACTIVE');
        if ($up3Id > 0) {
            $builder->where('up3_id', $up3Id);
        }
        return $builder->orderBy('nama_gi', 'ASC')->findAll();
    }

    /**
     * Level 1: Get all active ULPs (optionally filtered by UP3)
     */
    public function getUlps(int $up3Id = 0): array
    {
        if ($up3Id > 0) {
            return $this->ulpRepository->where('up3_id', $up3Id)->where('status', 'AKTIF')->findAll();
        }
        return $this->ulpRepository->getActiveUlps();
    }

    /**
     * Level 2a: Get active Penyulangs by Gardu Induk (GI) ID
     */
    public function getPenyulangsByGi(int $giId): array
    {
        if ($giId <= 0) {
            return [];
        }
        return $this->penyulangRepository->getActivePenyulangsByGi($giId);
    }

    /**
     * Level 2b: Get active Penyulangs by ULP ID
     */
    public function getPenyulangsByUlp(int $ulpId): array
    {
        if ($ulpId <= 0) {
            return [];
        }
        return $this->penyulangRepository->getActivePenyulangsByUlp($ulpId);
    }

    /**
     * Level 3: Get active Sections by Penyulang ID
     */
    public function getSectionsByPenyulang(int $penyulangId): array
    {
        if ($penyulangId <= 0) {
            return [];
        }
        return $this->sectionRepository->getActiveSectionsByPenyulang($penyulangId);
    }

    /**
     * Level 4: Get Assets with filter by section, penyulang, ulp, type, or status
     */
    public function getAssets(array $filters = []): array
    {
        $cleanFilters = [];

        if (!empty($filters['section_id']) && (int)$filters['section_id'] > 0) {
            $cleanFilters['section_id'] = (int)$filters['section_id'];
        }
        if (!empty($filters['penyulang_id']) && (int)$filters['penyulang_id'] > 0) {
            $cleanFilters['penyulang_id'] = (int)$filters['penyulang_id'];
        }
        if (!empty($filters['ulp_id']) && (int)$filters['ulp_id'] > 0) {
            $cleanFilters['ulp_id'] = (int)$filters['ulp_id'];
        }
        if (!empty($filters['type']) || !empty($filters['asset_type']) || !empty($filters['jenis_asset'])) {
            $cleanFilters['jenis_asset'] = (string)($filters['type'] ?? $filters['asset_type'] ?? $filters['jenis_asset']);
        }
        if (!empty($filters['status']) || !empty($filters['condition'])) {
            $cleanFilters['status'] = (string)($filters['status'] ?? $filters['condition']);
        }
        if (!empty($filters['search'])) {
            $cleanFilters['search'] = (string)$filters['search'];
        }

        return $this->assetRepository->getFilteredAssets($cleanFilters);
    }

    /**
     * Single Asset by ID
     */
    public function getAssetById(int $assetId): ?array
    {
        if ($assetId <= 0) {
            return null;
        }
        return $this->assetRepository->find($assetId);
    }
}
