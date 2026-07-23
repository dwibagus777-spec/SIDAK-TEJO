<?php

namespace App\Repositories;

use App\Models\UlpModel;

class UlpRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new UlpModel());
    }

    public function getActiveUlps(): array
    {
        return cache()->remember('active_ulps', 3600, function() {
            return $this->model->where('status', 'AKTIF')->orderBy('nama_ulp', 'ASC')->findAll();
        });
    }

    public function findByKode(string $kode): ?array
    {
        return $this->model->where('kode_ulp', $kode)->first();
    }
}
