<?php

namespace App\Repositories;

use App\Models\TindakLanjutModel;

class TindakLanjutRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new TindakLanjutModel());
    }

    public function getHistoryByTemuan(int $temuanId): array
    {
        return $this->model
            ->where('temuan_id', $temuanId)
            ->orderBy('tanggal', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }
}
