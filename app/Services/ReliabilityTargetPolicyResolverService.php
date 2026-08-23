<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Reliability Target Policy Resolver Service (Phase 7V Maintenance M-02)
 *
 * Responsibilities:
 * - Cascade resolution of SAIDI/SAIFI reliability target policies
 * - Resolution precedence:
 *     1. Feeder-Specific Active Policy (scope_type = 'FEEDER')
 *     2. Substation-Specific Active Policy (scope_type = 'SUBSTATION')
 *     3. Unit-Specific (UP3/ULP) Active Policy (scope_type = 'UP3' or 'ULP')
 *     4. Enterprise Default Active Policy (scope_type = 'ENTERPRISE_DEFAULT')
 * - Mandatory Governance & Refinements:
 *     - Strict Applicability: status IN ('ACTIVE', 'APPROVED'), effective_from <= evaluation_timestamp <= effective_until
 *     - Explicit Evaluation Timestamp: AS_OF_TIMESTAMP resolution (prevents retroactive reinterpretation)
 *     - Zero-Invention Fail-Safe: NO_APPLICABLE_APPROVED_POLICY = POLICY_UNAVAILABLE_ADVISORY (Never invent default 0.0)
 *     - Partial Target Detection: Missing SAIDI or SAIFI = PARTIAL_POLICY_UNAVAILABLE_ADVISORY
 *     - Comprehensive Version Pinning: Preserves policy_id, policy_code, version, targets, effective window, and source
 */
class ReliabilityTargetPolicyResolverService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Resolve active reliability target policy for a given feeder, substation, or unit.
     *
     * @param string|null $feederCode
     * @param string|null $substationCode
     * @param string|null $unitCode
     * @param string|null $evaluationTimestamp Explicit timestamp (defaults to current time)
     * @return array Resolved policy snapshot or unavailable advisory
     */
    public function resolveTargetPolicy(
        ?string $feederCode = null,
        ?string $substationCode = null,
        ?string $unitCode = null,
        ?string $evaluationTimestamp = null
    ): array {
        $evalTime = $evaluationTimestamp ?? date('Y-m-d H:i:s');

        // Check if table exists
        if (!$this->db->tableExists('reliability_target_policies')) {
            return $this->buildUnavailableResponse('DATABASE_TABLE_NOT_FOUND', $evalTime);
        }

        // 1. Feeder-Specific Active Policy
        if ($feederCode !== null && $feederCode !== '') {
            $policy = $this->queryApplicablePolicy('FEEDER', $feederCode, $evalTime);
            if ($policy) {
                return $this->formatResolvedPolicy($policy, 'FEEDER_SPECIFIC', $evalTime);
            }
        }

        // 2. Substation-Specific Active Policy
        if ($substationCode !== null && $substationCode !== '') {
            $policy = $this->queryApplicablePolicy('SUBSTATION', $substationCode, $evalTime);
            if ($policy) {
                return $this->formatResolvedPolicy($policy, 'SUBSTATION_SPECIFIC', $evalTime);
            }
        }

        // 3. Unit-Specific (UP3/ULP) Active Policy
        if ($unitCode !== null && $unitCode !== '') {
            $policy = $this->queryApplicablePolicy(['UP3', 'ULP'], $unitCode, $evalTime);
            if ($policy) {
                return $this->formatResolvedPolicy($policy, 'UNIT_SPECIFIC', $evalTime);
            }
        }

        // 4. Enterprise Default Active Policy
        $policy = $this->queryApplicablePolicy('ENTERPRISE_DEFAULT', null, $evalTime);
        if ($policy) {
            return $this->formatResolvedPolicy($policy, 'ENTERPRISE_DEFAULT', $evalTime);
        }

        // 5. Lookup Failure Fail-Safe (Zero Invention)
        return $this->buildUnavailableResponse('NO_APPLICABLE_APPROVED_POLICY', $evalTime);
    }

    /**
     * Helper to query applicable policy with strict status and effective dating
     */
    protected function queryApplicablePolicy($scopeType, ?string $scopeReference, string $evalTime): ?array
    {
        $builder = $this->db->table('reliability_target_policies');

        if (is_array($scopeType)) {
            $builder->whereIn('scope_type', $scopeType);
        } else {
            $builder->where('scope_type', $scopeType);
        }

        if ($scopeReference === null) {
            $builder->where('scope_reference IS NULL');
        } else {
            $builder->where('scope_reference', $scopeReference);
        }

        // Strict applicability: Only ACTIVE or APPROVED
        $builder->whereIn('status', ['ACTIVE', 'APPROVED'])
                ->where('effective_from <=', $evalTime)
                ->groupStart()
                    ->where('effective_until IS NULL')
                    ->orWhere('effective_until >=', $evalTime)
                ->groupEnd()
                ->orderBy('policy_version', 'DESC')
                ->limit(1);

        $row = $builder->get()->getRowArray();
        return $row ?: null;
    }

    /**
     * Format a successfully resolved policy with full metadata version pinning
     */
    protected function formatResolvedPolicy(array $policy, string $resolutionClass, string $evalTime): array
    {
        $saidiTarget = $policy['target_saidi_min_cust'] !== null ? (float)$policy['target_saidi_min_cust'] : null;
        $saifiTarget = $policy['target_saifi_times_cust'] !== null ? (float)$policy['target_saifi_times_cust'] : null;

        // Check for partial availability
        $isPartial = ($saidiTarget === null || $saifiTarget === null);
        $status = $isPartial ? 'PARTIAL_POLICY_UNAVAILABLE_ADVISORY' : 'RESOLVED';

        return [
            'status'                      => $status,
            'reliability_target_policy_id'=> (int)$policy['id'],
            'policy_code'                 => $policy['policy_code'],
            'policy_version'              => (int)$policy['policy_version'],
            'saidi_target_at_evaluation'  => $saidiTarget,
            'saifi_target_at_evaluation'  => $saifiTarget,
            'target_ens_mwh'              => $policy['target_ens_mwh'] !== null ? (float)$policy['target_ens_mwh'] : null,
            'effective_from'              => $policy['effective_from'],
            'effective_until'             => $policy['effective_until'],
            'source_of_record'            => $policy['source_of_record'] ?? 'CORPORATE_RELIABILITY_TARGET_AUTHORITY',
            'resolved_scope_type'         => $policy['scope_type'],
            'resolved_scope_reference'    => $policy['scope_reference'],
            'resolution_class'            => $resolutionClass,
            'evaluation_timestamp'        => $evalTime,
            'governance_rule'             => 'POLICY_VERSION_IMMUTABLY_PINNED',
            'evaluation_class'            => 'ADVISORY_ONLY',
        ];
    }

    /**
     * Format an unavailable response when lookup fails (Strict Zero-Invention)
     */
    protected function buildUnavailableResponse(string $reason, string $evalTime): array
    {
        return [
            'status'                      => 'POLICY_UNAVAILABLE_ADVISORY',
            'reason'                      => $reason,
            'evaluation_timestamp'        => $evalTime,
            'reliability_target_policy_id'=> null,
            'policy_code'                 => null,
            'policy_version'              => null,
            'saidi_target_at_evaluation'  => null,
            'saifi_target_at_evaluation'  => null,
            'target_ens_mwh'              => null,
            'effective_from'              => null,
            'effective_until'             => null,
            'source_of_record'            => 'APPROVED_CORPORATE_RELIABILITY_TARGET_AUTHORITY',
            'resolved_scope_type'         => 'NO_APPLICABLE_SCOPE',
            'resolved_scope_reference'    => null,
            'resolution_class'            => 'NO_APPLICABLE_POLICY',
            'governance_rule'             => 'TARGET_POLICY_LOOKUP_FAILURE_NOT_DEFAULT_TARGET_INVENTION',
            'evaluation_class'            => 'ADVISORY_ONLY',
            'advisory_notice'             => 'No approved active reliability target policy found for this scope and timestamp. Official management review required before evaluating SAIDI/SAIFI target compliance.',
        ];
    }
}
