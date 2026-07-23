<?php

namespace App\Repositories;

use App\Models\SectionModel;

class SectionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new SectionModel());
    }

    public function getActiveSectionsByPenyulang(int $penyulangId): array
    {
        return cache()->remember("active_sections_penyulang_{$penyulangId}", 3600, function() use ($penyulangId) {
            return $this->model
                ->where('penyulang_id', $penyulangId)
                ->where('status', 'AKTIF')
                ->orderBy('nama_section', 'ASC')
                ->findAll();
        });
    }

    public function getAllWithPenyulangAndUlp(): array
    {
        return $this->model
            ->select('sections.*, penyulang.nama_penyulang, penyulang.ulp_id, ulps.nama_ulp')
            ->join('penyulang', 'penyulang.id = sections.penyulang_id')
            ->join('ulps', 'ulps.id = penyulang.ulp_id')
            ->orderBy('sections.id', 'DESC')
            ->findAll();
    }

    public function findWithPenyulangAndUlp(int $id): ?array
    {
        return $this->model
            ->select('sections.*, penyulang.nama_penyulang, penyulang.ulp_id, ulps.nama_ulp')
            ->join('penyulang', 'penyulang.id = sections.penyulang_id')
            ->join('ulps', 'ulps.id = penyulang.ulp_id')
            ->where('sections.id', $id)
            ->first();
    }
}
