<?php

namespace App\Repositories;

use App\Models\PenyulangModel;
use CodeIgniter\Database\BaseBuilder;

class PenyulangRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new PenyulangModel());
    }

    protected function getJoinedWithUlp(): BaseBuilder
    {
        return $this->getBuilder('penyulang')
            ->select('penyulang.*, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = penyulang.ulp_id');
    }

    public function getActivePenyulangsByUlp(int $ulpId): array
    {
        return cache()->remember("active_penyulangs_ulp_{$ulpId}", 3600, function() use ($ulpId) {
            return $this->model
                ->where('ulp_id', $ulpId)
                ->where('status', 'AKTIF')
                ->orderBy('nama_penyulang', 'ASC')
                ->findAll();
        });
    }

    public function getAllWithUlp(): array
    {
        return $this->getJoinedWithUlp()
            ->orderBy('penyulang.id', 'DESC')
            ->get()->getResultArray();
    }

    public function findWithUlp(int $id): ?array
    {
        return $this->getJoinedWithUlp()
            ->where('penyulang.id', $id)
            ->get()->getRowArray();
    }

    public function getActivePenyulangs(): array
    {
        return cache()->remember('active_penyulangs', 3600, function() {
            return $this->model->where('status', 'AKTIF')->orderBy('nama_penyulang', 'ASC')->findAll();
        });
    }
}
