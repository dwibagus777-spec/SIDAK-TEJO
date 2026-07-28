<?php

namespace App\Repositories;

use App\Models\SectionModel;
use CodeIgniter\Database\BaseBuilder;

class SectionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new SectionModel());
    }

    protected function getJoinedWithPenyulangAndUlp(): BaseBuilder
    {
        return $this->getBuilder('sections')
            ->select('sections.*, penyulang.nama_penyulang, penyulang.ulp_id, ulps.nama_ulp')
            ->join('penyulang', 'penyulang.id = sections.penyulang_id')
            ->join('ulps', 'ulps.id = penyulang.ulp_id');
    }

    public function getActiveSectionsByPenyulang(int $penyulangId): array
    {
        return cache()->remember("active_sections_penyulang_{$penyulangId}", 86400, function() use ($penyulangId) {
            return $this->model
                ->where('penyulang_id', $penyulangId)
                ->where('status', 'AKTIF')
                ->orderBy('nama_section', 'ASC')
                ->findAll();
        });
    }

    public function getAllWithPenyulangAndUlp(): array
    {
        return $this->getJoinedWithPenyulangAndUlp()
            ->orderBy('sections.id', 'DESC')
            ->get()->getResultArray();
    }

    public function findWithPenyulangAndUlp(int $id): ?array
    {
        return $this->getJoinedWithPenyulangAndUlp()
            ->where('sections.id', $id)
            ->get()->getRowArray();
    }
}
