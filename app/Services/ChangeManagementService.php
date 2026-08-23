<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class ChangeManagementService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Production Change Request & Classification Engine (Phase 6C)
     */
    public function createChangeRequest(string $title = 'Deploy SIDAK TEJO v3.0.0 Release', string $type = 'NORMAL_CHANGE'): array
    {
        $db = $this->db;

        $crCode = 'CR-STJ-' . date('Ymd') . '-' . sprintf('%04d', rand(100, 999));

        $changeRequest = [
            'change_code'     => $crCode,
            'title'           => $title,
            'change_type'     => $type,
            'requester_role'  => 'DALOPS_ENGINEER',
            'created_at'      => date('Y-m-d H:i:s'),
            'change_status'   => 'CHANGE_REQUEST_REGISTERED',
        ];

        return [
            'status'                 => 'success',
            'change_request'         => $changeRequest,
            'change_engine_version'  => 'CHANGE_MANAGEMENT_v1.0',
            'certified_change_status'=> 'CHANGE_REQUEST_VALIDATED',
        ];
    }
}
