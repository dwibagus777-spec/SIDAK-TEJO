<?php

namespace App\Repositories;

use App\Models\AuditLogModel;

class AuditLogRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new AuditLogModel());
    }

    public function getRecentLogs(int $limit = 100): array
    {
        return $this->model
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}
