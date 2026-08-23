<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Inspection Interval Policy Seeder — Phase 7Y Dynamic Inspection Policy Layer (M-03)
 *
 * ⚠️  GOVERNANCE CONTRACT NOTICE:
 * Records in this seeder are SEED_SAMPLE_OR_TEMPLATE only.
 * Status = CONFIGURATION_REQUIRED by default.
 * These records are NOT official statutory or maintenance SOP schedules for PLN or any unit.
 *
 * Mandatory Invariants:
 * 1. SEEDED_TEMPLATE ≠ APPROVED_INSPECTION_POLICY_AUTHORITY
 * 2. CONFIGURED_POLICY ≠ OFFICIAL_INSPECTION_SCHEDULE
 * 3. NO_APPLICABLE_APPROVED_POLICY = POLICY_UNAVAILABLE_ADVISORY
 * 4. DEFAULT_INTERVAL_INVENTION = FORBIDDEN
 */
class InspectionIntervalPolicySeeder extends Seeder
{
    public function run(): void
    {
        $now       = date('Y-m-d H:i:s');
        $startYear = date('Y-01-01 00:00:00');

        $policies = [
            [
                'policy_code'                 => 'TMP-INSP-DEF-P1-CRIT-2026-V1',
                'policy_version'              => 1,
                'scope_type'                  => 'ENTERPRISE_DEFAULT',
                'scope_reference'             => null,
                'priority_tier'               => 'P1',
                'health_tier'                 => 'CRITICAL',
                'recommended_interval_days'   => 7,
                'recommended_inspection_type' => 'EMERGENCY_VISUAL_AND_THERMOVISION_INSPECTION',
                'recommended_window_label'    => 'WITHIN_7_DAYS',
                'effective_from'              => $startYear,
                'effective_until'             => null,
                'status'                      => 'CONFIGURATION_REQUIRED',
                'source_of_record'            => 'SEED_TEMPLATE_REQUIRING_MAINTENANCE_SOP_APPROVAL',
                'origin_class'                => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_at'                 => null,
                'approved_by_reference'       => null,
                'supersedes_policy_id'        => null,
                'notes'                       => 'P1 Critical Health Enterprise Default Template. CONFIGURATION_REQUIRED before operational use.',
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ],
            [
                'policy_code'                 => 'TMP-INSP-DEF-P2-POOR-2026-V1',
                'policy_version'              => 1,
                'scope_type'                  => 'ENTERPRISE_DEFAULT',
                'scope_reference'             => null,
                'priority_tier'               => 'P2',
                'health_tier'                 => 'POOR',
                'recommended_interval_days'   => 14,
                'recommended_inspection_type' => 'DETAILED_VISUAL_AND_ULTRASONIC_INSPECTION',
                'recommended_window_label'    => 'WITHIN_14_DAYS',
                'effective_from'              => $startYear,
                'effective_until'             => null,
                'status'                      => 'CONFIGURATION_REQUIRED',
                'source_of_record'            => 'SEED_TEMPLATE_REQUIRING_MAINTENANCE_SOP_APPROVAL',
                'origin_class'                => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_at'                 => null,
                'approved_by_reference'       => null,
                'supersedes_policy_id'        => null,
                'notes'                       => 'P2 Poor Health Enterprise Default Template. CONFIGURATION_REQUIRED before operational use.',
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ],
            [
                'policy_code'                 => 'TMP-INSP-DEF-P3-MOD-2026-V1',
                'policy_version'              => 1,
                'scope_type'                  => 'ENTERPRISE_DEFAULT',
                'scope_reference'             => null,
                'priority_tier'               => 'P3',
                'health_tier'                 => 'MODERATE',
                'recommended_interval_days'   => 30,
                'recommended_inspection_type' => 'DETAILED_VISUAL_INSPECTION',
                'recommended_window_label'    => 'WITHIN_30_DAYS',
                'effective_from'              => $startYear,
                'effective_until'             => null,
                'status'                      => 'CONFIGURATION_REQUIRED',
                'source_of_record'            => 'SEED_TEMPLATE_REQUIRING_MAINTENANCE_SOP_APPROVAL',
                'origin_class'                => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_at'                 => null,
                'approved_by_reference'       => null,
                'supersedes_policy_id'        => null,
                'notes'                       => 'P3 Moderate Health Enterprise Default Template. CONFIGURATION_REQUIRED before operational use.',
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ],
            [
                'policy_code'                 => 'TMP-INSP-DEF-P4-GOOD-2026-V1',
                'policy_version'              => 1,
                'scope_type'                  => 'ENTERPRISE_DEFAULT',
                'scope_reference'             => null,
                'priority_tier'               => 'P4',
                'health_tier'                 => 'GOOD',
                'recommended_interval_days'   => 90,
                'recommended_inspection_type' => 'ROUTINE_PREVENTIVE_INSPECTION',
                'recommended_window_label'    => 'WITHIN_90_DAYS',
                'effective_from'              => $startYear,
                'effective_until'             => null,
                'status'                      => 'CONFIGURATION_REQUIRED',
                'source_of_record'            => 'SEED_TEMPLATE_REQUIRING_MAINTENANCE_SOP_APPROVAL',
                'origin_class'                => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_at'                 => null,
                'approved_by_reference'       => null,
                'supersedes_policy_id'        => null,
                'notes'                       => 'P4 Good Health Enterprise Default Template. CONFIGURATION_REQUIRED before operational use.',
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ],
            [
                'policy_code'                 => 'TMP-INSP-DEF-P5-EXC-2026-V1',
                'policy_version'              => 1,
                'scope_type'                  => 'ENTERPRISE_DEFAULT',
                'scope_reference'             => null,
                'priority_tier'               => 'P5',
                'health_tier'                 => 'EXCELLENT',
                'recommended_interval_days'   => 180,
                'recommended_inspection_type' => 'ANNUAL_ROUTINE_PATROL',
                'recommended_window_label'    => 'WITHIN_180_DAYS',
                'effective_from'              => $startYear,
                'effective_until'             => null,
                'status'                      => 'CONFIGURATION_REQUIRED',
                'source_of_record'            => 'SEED_TEMPLATE_REQUIRING_MAINTENANCE_SOP_APPROVAL',
                'origin_class'                => 'SEED_SAMPLE_OR_TEMPLATE',
                'approved_at'                 => null,
                'approved_by_reference'       => null,
                'supersedes_policy_id'        => null,
                'notes'                       => 'P5 Excellent Health Enterprise Default Template. CONFIGURATION_REQUIRED before operational use.',
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ],
        ];

        $this->db->table('inspection_interval_policies')->insertBatch($policies);
    }
}
