<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Inspection Interval Policy Resolver Service (Phase 7Y Maintenance M-03)
 *
 * Responsibilities:
 * - Cascade resolution of Risk-Based Inspection Interval Policies
 * - Applicability matching: SCOPE + PRIORITY TIER + HEALTH TIER + STATUS + EFFECTIVE DATING
 * - Resolution precedence:
 *     1. Feeder-Specific Active Policy (scope_type = 'FEEDER')
 *     2. Substation-Specific Active Policy (scope_type = 'SUBSTATION')
 *     3. Unit-Specific (UP3/ULP) Active Policy (scope_type = 'UP3' or 'ULP')
 *     4. Enterprise Default Active Policy (scope_type = 'ENTERPRISE_DEFAULT')
 * - Mandatory Refinements & Governance:
 *     - Explicit Match vs Explicit Policy Wildcard (MATCH_TYPE = 'EXACT' | 'EXPLICIT_POLICY_WILDCARD')
 *     - Explicit Evaluation Timestamp (AS_OF_TIMESTAMP_EVALUATION)
 *     - Zero-Invention Fail-Safe: NO_APPLICABLE_APPROVED_POLICY = POLICY_UNAVAILABLE_ADVISORY
 *     - Rich Immutable Version Pinning
 *     - Strict Advisory Boundary: RECOMMENDED_INTERVAL != MANDATORY_REGULATORY_INTERVAL
 */
class InspectionIntervalPolicyResolverService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Resolve active inspection interval policy for a given asset context.
     *
     * @param string $priorityTier P1..P5
     * @param string $healthTier CRITICAL, POOR, MODERATE, GOOD, EXCELLENT
     * @param string|null $feederCode
     * @param string|null $substationCode
     * @param string|null $unitCode
     * @param string|null $evaluationTimestamp Explicit timestamp for evaluation (as-of dating)
     * @return array Resolved policy snapshot or unavailable advisory
     */
    public function resolveIntervalPolicy(
        string $priorityTier = 'P3',
        string $healthTier = 'MODERATE',
        ?string $feederCode = null,
        ?string $substationCode = null,
        ?string $unitCode = null,
        ?string $evaluationTimestamp = null
    ): array {
        $evalTime = $evaluationTimestamp ?? date('Y-m-d H:i:s');

        // Check if table exists
        if (!$this->db->tableExists('inspection_interval_policies')) {
            return $this->buildUnavailableResponse('DATABASE_TABLE_NOT_FOUND', $priorityTier, $healthTier, $evalTime);
        }

        // 1. Feeder-Specific Active Policy
        if ($feederCode !== null && $feederCode !== '') {
            $policyResult = $this->queryApplicablePolicyWithMatch('FEEDER', $feederCode, $priorityTier, $healthTier, $evalTime);
            if ($policyResult) {
                return $this->formatResolvedPolicy($policyResult['policy'], 'FEEDER_SPECIFIC', $policyResult['match_type'], $priorityTier, $healthTier, $evalTime);
            }
        }

        // 2. Substation-Specific Active Policy
        if ($substationCode !== null && $substationCode !== '') {
            $policyResult = $this->queryApplicablePolicyWithMatch('SUBSTATION', $substationCode, $priorityTier, $healthTier, $evalTime);
            if ($policyResult) {
                return $this->formatResolvedPolicy($policyResult['policy'], 'SUBSTATION_SPECIFIC', $policyResult['match_type'], $priorityTier, $healthTier, $evalTime);
            }
        }

        // 3. Unit-Specific (UP3/ULP) Active Policy
        if ($unitCode !== null && $unitCode !== '') {
            $policyResult = $this->queryApplicablePolicyWithMatch(['UP3', 'ULP'], $unitCode, $priorityTier, $healthTier, $evalTime);
            if ($policyResult) {
                return $this->formatResolvedPolicy($policyResult['policy'], 'UNIT_SPECIFIC', $policyResult['match_type'], $priorityTier, $healthTier, $evalTime);
            }
        }

        // 4. Enterprise Default Active Policy
        $policyResult = $this->queryApplicablePolicyWithMatch('ENTERPRISE_DEFAULT', null, $priorityTier, $healthTier, $evalTime);
        if ($policyResult) {
            return $this->formatResolvedPolicy($policyResult['policy'], 'ENTERPRISE_DEFAULT', $policyResult['match_type'], $priorityTier, $healthTier, $evalTime);
        }

        // 5. Fail-Safe: Zero-Invention (Never invent hardcoded intervals)
        return $this->buildUnavailableResponse('NO_APPLICABLE_APPROVED_POLICY', $priorityTier, $healthTier, $evalTime);
    }

    /**
     * Helper to query applicable policy trying Exact match first, then Explicit Policy Wildcard
     */
    protected function queryApplicablePolicyWithMatch($scopeType, ?string $scopeReference, string $priorityTier, string $healthTier, string $evalTime): ?array
    {
        // A. Exact Match (priority_tier = $priorityTier AND health_tier = $healthTier)
        $exactPolicy = $this->executePolicyQuery($scopeType, $scopeReference, [$priorityTier], [$healthTier], $evalTime);
        if ($exactPolicy) {
            return ['policy' => $exactPolicy, 'match_type' => 'EXACT'];
        }

        // B. Priority Exact + Health Tier Wildcard 'ALL'
        $wildcardHealth = $this->executePolicyQuery($scopeType, $scopeReference, [$priorityTier], ['ALL'], $evalTime);
        if ($wildcardHealth) {
            return ['policy' => $wildcardHealth, 'match_type' => 'EXPLICIT_POLICY_WILDCARD'];
        }

        // C. Priority Wildcard 'ALL' + Health Tier Exact
        $wildcardPriority = $this->executePolicyQuery($scopeType, $scopeReference, ['ALL'], [$healthTier], $evalTime);
        if ($wildcardPriority) {
            return ['policy' => $wildcardPriority, 'match_type' => 'EXPLICIT_POLICY_WILDCARD'];
        }

        // D. Full Wildcard 'ALL' + 'ALL'
        $fullWildcard = $this->executePolicyQuery($scopeType, $scopeReference, ['ALL'], ['ALL'], $evalTime);
        if ($fullWildcard) {
            return ['policy' => $fullWildcard, 'match_type' => 'EXPLICIT_POLICY_WILDCARD'];
        }

        return null;
    }

    /**
     * Execute SQL builder query with strict status and effective dating
     */
    protected function executePolicyQuery($scopeType, ?string $scopeReference, array $priorityTiers, array $healthTiers, string $evalTime): ?array
    {
        $builder = $this->db->table('inspection_interval_policies');

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

        $builder->whereIn('priority_tier', $priorityTiers)
                ->whereIn('health_tier', $healthTiers)
                ->whereIn('status', ['ACTIVE', 'APPROVED'])
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
     * Format resolved policy with complete version pinning metadata
     */
    protected function formatResolvedPolicy(array $policy, string $resolutionClass, string $matchType, string $priorityTier, string $healthTier, string $evalTime): array
    {
        return [
            'status'                                  => 'RESOLVED',
            'inspection_interval_policy_id'           => (int)$policy['id'],
            'policy_code'                             => $policy['policy_code'],
            'policy_version'                          => (int)$policy['policy_version'],
            'resolved_scope_type'                     => $policy['scope_type'],
            'resolved_scope_reference'                => $policy['scope_reference'],
            'priority_tier_at_evaluation'             => $priorityTier,
            'health_tier_at_evaluation'               => $healthTier,
            'match_type'                              => $matchType,
            'interval_days_at_evaluation'             => (int)$policy['recommended_interval_days'],
            'recommended_window_label_at_evaluation'  => $policy['recommended_window_label'],
            'recommended_inspection_type_at_evaluation'=> $policy['recommended_inspection_type'],
            'effective_from'                          => $policy['effective_from'],
            'effective_until'                         => $policy['effective_until'],
            'source_of_record'                        => $policy['source_of_record'] ?? 'APPROVED_MAINTENANCE_SOP_AUTHORITY',
            'resolution_class'                        => $resolutionClass,
            'evaluation_timestamp'                    => $evalTime,
            'governance_rule'                         => 'POLICY_VERSION_IMMUTABLY_PINNED',
            'evaluation_class'                        => 'ADVISORY_ONLY',
        ];
    }

    /**
     * Format unavailable response when lookup fails (Zero Invention)
     */
    protected function buildUnavailableResponse(string $reason, string $priorityTier, string $healthTier, string $evalTime): array
    {
        return [
            'status'                                  => 'POLICY_UNAVAILABLE_ADVISORY',
            'reason'                                  => $reason,
            'evaluation_timestamp'                    => $evalTime,
            'priority_tier_at_evaluation'             => $priorityTier,
            'health_tier_at_evaluation'               => $healthTier,
            'match_type'                              => 'NO_MATCH',
            'inspection_interval_policy_id'           => null,
            'policy_code'                             => null,
            'policy_version'                          => null,
            'interval_days_at_evaluation'             => null,
            'recommended_window_label_at_evaluation'  => 'INTERVAL_POLICY_UNAVAILABLE',
            'recommended_inspection_type_at_evaluation'=> 'EVALUATION_PENDING_APPROVED_SOP_POLICY',
            'effective_from'                          => null,
            'effective_until'                         => null,
            'source_of_record'                        => 'APPROVED_MAINTENANCE_SOP_AUTHORITY',
            'resolved_scope_type'                     => 'NO_APPLICABLE_SCOPE',
            'resolved_scope_reference'                => null,
            'resolution_class'                        => 'NO_APPLICABLE_POLICY',
            'governance_rule'                         => 'INSPECTION_POLICY_LOOKUP_FAILURE_NOT_DEFAULT_INTERVAL_INVENTION',
            'evaluation_class'                        => 'ADVISORY_ONLY',
            'advisory_notice'                         => 'No approved active inspection interval policy found matching this scope, priority tier, and health tier. Official supervisor review required before scheduling inspection cycles.',
        ];
    }
}
