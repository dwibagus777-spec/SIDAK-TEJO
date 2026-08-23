<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Reliability Target Policy Seeder — Phase 7V Dynamic Reliability Target Layer
 *
 * ⚠️  GOVERNANCE CONTRACT NOTICE:
 * Records in this seeder are SEED_SAMPLE_OR_TEMPLATE only.
 * Status = CONFIGURATION_REQUIRED by default.
 * These records are NOT official statutory or corporate targets for PLN or any unit.
 *
 * Applicable Resolver Invariants:
 * 1. SEEDED_TEMPLATE ≠ APPROVED_RELIABILITY_TARGET_AUTHORITY
 * 2. CONFIGURED_POLICY ≠ OFFICIAL_STATUTORY_TARGET
 * 3. NO_APPLICABLE_APPROVED_POLICY = POLICY_UNAVAILABLE_ADVISORY
 * 4. DEFAULT_TARGET_INVENTION = FORBIDDEN
 */
class ReliabilityTargetPolicySeeder extends Seeder
{
    public function run(): void
    {
        $now       = date('Y-m-d H:i:s');
        $startYear = date('Y-01-01 00:00:00');

        $policies = [
            [
                'policy_code'             => 'TMP-REL-ENTERPRISE-DEF-2026-V1',
                'policy_version'          => 1,
                'scope_type'              => 'ENTERPRISE_DEFAULT',
                'scope_reference'         => null,
                'target_saidi_min_cust'   => 120.00,
                'target_saifi_times_cust' => 3.2000,
                'target_ens_mwh'          => 250.000,
                'effective_from'          => $startYear,
                'effective_until'         => null,
                'status'                  => 'CONFIGURATION_REQUIRED',
                'source_of_record'        => 'SEED_TEMPLATE_REQUIRING_CORPORATE_APPROVAL',
                'origin_class'            => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_at'             => null,
                'approved_by_reference'   => null,
                'supersedes_policy_id'    => null,
                'notes'                   => 'Enterprise default reliability target template. CONFIGURATION_REQUIRED before operational evaluation.',
                'created_at'              => $now,
                'updated_at'              => $now,
            ],
            [
                'policy_code'             => 'TMP-REL-UP3-JBR-2026-V1',
                'policy_version'          => 1,
                'scope_type'              => 'UP3',
                'scope_reference'         => 'UP3_JEMBER',
                'target_saidi_min_cust'   => 95.00,
                'target_saifi_times_cust' => 2.8000,
                'target_ens_mwh'          => 180.000,
                'effective_from'          => $startYear,
                'effective_until'         => null,
                'status'                  => 'CONFIGURATION_REQUIRED',
                'source_of_record'        => 'SEED_TEMPLATE_REQUIRING_UP3_PERFORMANCE_CONTRACT',
                'origin_class'            => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_at'             => null,
                'approved_by_reference'   => null,
                'supersedes_policy_id'    => null,
                'notes'                   => 'UP3 Jember reliability target template. CONFIGURATION_REQUIRED before operational evaluation.',
                'created_at'              => $now,
                'updated_at'              => $now,
            ],
            [
                'policy_code'             => 'TMP-REL-FDR-BALUNG-2026-V1',
                'policy_version'          => 1,
                'scope_type'              => 'FEEDER',
                'scope_reference'         => 'FDR_BALUNG',
                'target_saidi_min_cust'   => 45.00,
                'target_saifi_times_cust' => 1.5000,
                'target_ens_mwh'          => 50.000,
                'effective_from'          => $startYear,
                'effective_until'         => null,
                'status'                  => 'CONFIGURATION_REQUIRED',
                'source_of_record'        => 'SEED_TEMPLATE_REQUIRING_FEEDER_ANNUAL_TARGET_APPROVAL',
                'origin_class'            => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_at'             => null,
                'approved_by_reference'   => null,
                'supersedes_policy_id'    => null,
                'notes'                   => 'Feeder Balung reliability target template. CONFIGURATION_REQUIRED before operational evaluation.',
                'created_at'              => $now,
                'updated_at'              => $now,
            ],
        ];

        $this->db->table('reliability_target_policies')->insertBatch($policies);
    }
}
