<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class OrganizationalAuthorityService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Get Organizational Hierarchy & Decision Authority Matrix (Phase 3F)
     */
    public function getOrganizationalStructureAndAuthorityMatrix(): array
    {
        $orgHierarchy = [
            'unit_induk' => 'PLN_UID_JAWA_TIMUR',
            'unit_pelaksana' => 'PLN_UP3_SIDOARJO',
            'unit_layanan'   => 'PLN_ULP_SIDOARJO_KOTA',
        ];

        $authorityMatrix = [
            'PETUGAS_LAPANGAN' => [
                'operational_scope'     => 'Field Inspection & Physical Trimming/Maintenance',
                'decision_authority'    => 'Submit Evidence & Update Work Status',
                'approval_threshold'    => 'P5 Routine Monitoring Only',
            ],
            'SUPERVISOR_ULP' => [
                'operational_scope'     => 'ULP Distribution Maintenance & Resource Allocation',
                'decision_authority'    => 'Approve Prescriptive Action & Verify Risk Recovery',
                'approval_threshold'    => 'P2-P4 Cases (Resolution SLA <= 720h)',
            ],
            'MANAJER_ULP_DAN_DALOPS' => [
                'operational_scope'     => 'ULP Executive Management & Grid Dispatch Operations',
                'decision_authority'    => 'Emergency Work Package Override & VIP Outage Mitigation',
                'approval_threshold'    => 'P1 Emergency Cases & Cross-Feeder Load Maneuver',
            ],
        ];

        return [
            'status'                     => 'success',
            'organization_hierarchy'     => $orgHierarchy,
            'role_authority_matrix'      => $authorityMatrix,
            'authority_fabric_version'   => 'ORGANIZATION_AUTHORITY_v1.0',
            'certified_authority_status' => 'ENTERPRISE_AUTHORITY_MATRIX_ACTIVE',
        ];
    }
}
