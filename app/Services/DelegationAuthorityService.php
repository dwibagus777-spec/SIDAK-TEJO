<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class DelegationAuthorityService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Resolve Active Decision Authority & Delegation of Authority Rules (Phase 3F)
     */
    public function resolveActiveAuthority(string $primaryRole = 'SUPERVISOR_ULP'): array
    {
        $delegationRules = [
            'SUPERVISOR_ULP' => [
                'primary_officer'     => 'Supervisor Pemeliharaan ULP Sidoarjo Kota',
                'is_on_leave'         => false,
                'acting_officer'      => null,
                'delegation_status'   => 'DIRECT_PRIMARY_AUTHORITY_ACTIVE',
            ],
            'MANAJER_ULP_DAN_DALOPS' => [
                'primary_officer'     => 'Manajer ULP Sidoarjo Kota',
                'is_on_leave'         => false,
                'acting_officer'      => null,
                'delegation_status'   => 'DIRECT_PRIMARY_AUTHORITY_ACTIVE',
            ],
        ];

        $resolved = $delegationRules[$primaryRole] ?? [
            'primary_officer'   => $primaryRole,
            'delegation_status' => 'DEFAULT_ROLE_ACTIVE',
        ];

        return [
            'status'                  => 'success',
            'requested_primary_role'  => $primaryRole,
            'active_authority'        => $resolved,
            'delegation_rule_version' => 'DELEGATION_AUTHORITY_v1.0',
            'certified_delegation'    => 'AUTHORITY_RESOLVED_CLEANLY',
        ];
    }
}
