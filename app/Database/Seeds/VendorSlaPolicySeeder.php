<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Vendor SLA Policy Seeder — Phase 7L Dynamic SLA Policy Layer
 *
 * ⚠️  GOVERNANCE NOTICE:
 * Records in this seeder are SEED_SAMPLE_OR_TEMPLATE only.
 * Status = CONFIGURATION_REQUIRED by default.
 * These values are NOT official SLA targets for PLN or any vendor.
 * Official policies must be:
 *   1. Reviewed by Contract Authority
 *   2. Set to status = ACTIVE
 *   3. Assigned approved_by = <authorized officer>
 *   4. Linked to source_reference = <contract/SOP number>
 *
 * SEEDED_TEMPLATE ≠ APPROVED_SLA_AUTHORITY
 * CONFIGURED_POLICY ≠ OFFICIAL_CONTRACT_POLICY
 */
class VendorSlaPolicySeeder extends Seeder
{
    public function run(): void
    {
        $now    = date('Y-m-d H:i:s');
        $today  = date('Y-m-d');

        // Enterprise Default SLA Templates (vendor_id = NULL = all vendors)
        // Status = CONFIGURATION_REQUIRED — NOT officially active until contract authority approves
        $policies = [
            [
                'vendor_id'              => null,
                'contract_id'            => null,
                'work_type'              => null,
                'priority'               => 'P1',
                'sla_response_minutes'   => 60,
                'sla_resolution_minutes' => 240,
                'effective_from'         => $today,
                'effective_until'        => null,
                'status'                 => 'CONFIGURATION_REQUIRED',
                'version'                => 1,
                'policy_origin'          => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_by'            => null,
                'source_reference'       => 'TEMPLATE_ONLY_NOT_CONTRACT_BINDING',
                'notes'                  => 'P1 enterprise default template. CONFIGURATION_REQUIRED before operational use. Activate only after official contract authority review.',
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'vendor_id'              => null,
                'contract_id'            => null,
                'work_type'              => null,
                'priority'               => 'P2',
                'sla_response_minutes'   => 120,
                'sla_resolution_minutes' => 480,
                'effective_from'         => $today,
                'effective_until'        => null,
                'status'                 => 'CONFIGURATION_REQUIRED',
                'version'                => 1,
                'policy_origin'          => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_by'            => null,
                'source_reference'       => 'TEMPLATE_ONLY_NOT_CONTRACT_BINDING',
                'notes'                  => 'P2 enterprise default template. CONFIGURATION_REQUIRED before operational use.',
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'vendor_id'              => null,
                'contract_id'            => null,
                'work_type'              => null,
                'priority'               => 'P3',
                'sla_response_minutes'   => 240,
                'sla_resolution_minutes' => 1440,
                'effective_from'         => $today,
                'effective_until'        => null,
                'status'                 => 'CONFIGURATION_REQUIRED',
                'version'                => 1,
                'policy_origin'          => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_by'            => null,
                'source_reference'       => 'TEMPLATE_ONLY_NOT_CONTRACT_BINDING',
                'notes'                  => 'P3 enterprise default template. CONFIGURATION_REQUIRED before operational use.',
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'vendor_id'              => null,
                'contract_id'            => null,
                'work_type'              => null,
                'priority'               => 'P4',
                'sla_response_minutes'   => 480,
                'sla_resolution_minutes' => 2880,
                'effective_from'         => $today,
                'effective_until'        => null,
                'status'                 => 'CONFIGURATION_REQUIRED',
                'version'                => 1,
                'policy_origin'          => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_by'            => null,
                'source_reference'       => 'TEMPLATE_ONLY_NOT_CONTRACT_BINDING',
                'notes'                  => 'P4 enterprise default template. CONFIGURATION_REQUIRED before operational use.',
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'vendor_id'              => null,
                'contract_id'            => null,
                'work_type'              => null,
                'priority'               => 'P5',
                'sla_response_minutes'   => 1440,
                'sla_resolution_minutes' => 10080,
                'effective_from'         => $today,
                'effective_until'        => null,
                'status'                 => 'CONFIGURATION_REQUIRED',
                'version'                => 1,
                'policy_origin'          => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_by'            => null,
                'source_reference'       => 'TEMPLATE_ONLY_NOT_CONTRACT_BINDING',
                'notes'                  => 'P5 enterprise default template. CONFIGURATION_REQUIRED before operational use.',
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
        ];

        $this->db->table('vendor_sla_policies')->insertBatch($policies);
    }
}
