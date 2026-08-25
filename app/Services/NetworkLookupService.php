<?php

namespace App\Services;

use App\Repositories\UlpRepository;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;
use App\Repositories\AssetRepository;

/**
 * MNF-01: Master Network Fabric Domain Service
 * Canonical 4-level network hierarchy service: ULP -> PENYULANG -> SECTION -> ASSET
 */
class NetworkLookupService
{
    protected UlpRepository $ulpRepository;
    protected PenyulangRepository $penyulangRepository;
    protected SectionRepository $sectionRepository;
    protected AssetRepository $assetRepository;

    public function __construct()
    {
        $this->ulpRepository       = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
        $this->sectionRepository   = new SectionRepository();
        $this->assetRepository     = new AssetRepository();
    }

    /**
     * Level 1: Get all active ULPs
     */
    public function getUlps(): array
    {
        return $this->ulpRepository->getActiveUlps();
    }

    /**
     * Level 2: Get active Penyulangs by ULP ID
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
