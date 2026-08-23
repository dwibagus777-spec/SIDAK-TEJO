<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Vendor SLA Policy Resolver Service (Phase 7L Enhancement)
 *
 * Responsibilities:
 * - Cascade resolution of SLA policies from database
 * - Resolution order:
 *     1. Contract-Specific Active Policy
 *     2. Vendor-Specific Active Policy
 *     3. Enterprise Default Active Policy
 * - Enforce Governance:
 *     - SLA_POLICY_LOOKUP_FAILURE ≠ DEFAULT_SLA_INVENTION
 *     - NO_APPLICABLE_POLICY = POLICY_UNAVAILABLE_ADVISORY
 *     - RETROACTIVE_POLICY_SUBSTITUTION = FORBIDDEN
 *     - EXPLICIT_RE_EVALUATION = HUMAN_AUTHORITY_REQUIRED
 */
class VendorSlaPolicyResolverService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Resolve active SLA policy for a given vendor, priority, work type, or contract.
     *
     * @param int|null $vendorId
     * @param string $priority P1..P5
     * @param int|null $contractId
     * @param string|null $workType
     * @param string|null $asOfDate Reference date (defaults to today)
     * @return array Resolved policy snapshot or unavailable advisory
     */
    public function resolvePolicy(
        ?int $vendorId = null,
        string $priority = 'P1',
        ?int $contractId = null,
        ?string $workType = null,
        ?string $asOfDate = null
    ): array {
        $referenceDate = $asOfDate ?? date('Y-m-d');

        // Check if table exists
        if (!$this->db->tableExists('vendor_sla_policies')) {
            return $this->buildUnavailableResponse('DATABASE_TABLE_NOT_FOUND', $priority, $referenceDate);
        }

        // 1. Contract-Specific Policy
        if ($contractId !== null) {
            $policy = $this->queryPolicy([
                'contract_id' => $contractId,
                'priority'    => $priority,
            ], $referenceDate);

            if ($policy) {
                return $this->formatResolvedPolicy($policy, 'CONTRACT_SPECIFIC');
            }
        }

        // 2. Vendor-Specific Policy
        if ($vendorId !== null) {
            $policy = $this->queryPolicy([
                'vendor_id' => $vendorId,
                'priority'  => $priority,
            ], $referenceDate);

            if ($policy) {
                return $this->formatResolvedPolicy($policy, 'VENDOR_SPECIFIC');
            }
        }

        // 3. Enterprise Default Policy (vendor_id IS NULL)
        $policy = $this->queryPolicy([
            'vendor_id' => null,
            'priority'  => $priority,
        ], $referenceDate);

        if ($policy) {
            return $this->formatResolvedPolicy($policy, 'ENTERPRISE_DEFAULT');
        }

        // 4. Lookup Failure: NO_APPLICABLE_POLICY = POLICY_UNAVAILABLE_ADVISORY (Never invent hardcoded default)
        return $this->buildUnavailableResponse('NO_APPLICABLE_APPROVED_POLICY', $priority, $referenceDate);
    }

    /**
     * Helper to query policy from database with effective dating & status
     */
    protected function queryPolicy(array $conditions, string $referenceDate): ?array
    {
        $builder = $this->db->table('vendor_sla_policies');

        foreach ($conditions as $field => $val) {
            if ($val === null) {
                $builder->where("{$field} IS NULL");
            } else {
                $builder->where($field, $val);
            }
        }

        // Only ACTIVE policies within effective date window
        $builder->where('status', 'ACTIVE')
                ->where('effective_from <=', $referenceDate)
                ->groupStart()
                    ->where('effective_until IS NULL')
                    ->orWhere('effective_until >=', $referenceDate)
                ->groupEnd()
                ->orderBy('version', 'DESC')
                ->limit(1);

        $row = $builder->get()->getRowArray();
        return $row ?: null;
    }

    /**
     * Format a successfully resolved policy with version pinning
     */
    protected function formatResolvedPolicy(array $policy, string $resolutionClass): array
    {
        return [
            'status'                          => 'RESOLVED',
            'sla_policy_id'                   => (int)$policy['id'],
            'policy_version'                  => (int)$policy['version'],
            'policy_origin'                   => $policy['policy_origin'] ?? 'UNKNOWN',
            'policy_effective_from'           => $policy['effective_from'],
            'policy_effective_until'          => $policy['effective_until'],
            'sla_response_minutes'            => (int)$policy['sla_response_minutes'],
            'sla_resolution_minutes'          => (int)$policy['sla_resolution_minutes'],
            'resolution_class'                => $resolutionClass,
            'source_reference'                => $policy['source_reference'] ?? null,
            'approved_by'                     => $policy['approved_by'] ?? null,
            'governance_rule'                 => 'POLICY_VERSION_IMMUTABLY_PINNED',
            'evaluation_class'                => 'ADVISORY_ONLY',
        ];
    }

    /**
     * Format an unavailable response when lookup fails (Never invent an SLA)
     */
    protected function buildUnavailableResponse(string $reason, string $priority, string $referenceDate): array
    {
        return [
            'status'                          => 'POLICY_UNAVAILABLE_ADVISORY',
            'reason'                          => $reason,
            'priority'                        => $priority,
            'as_of_date'                      => $referenceDate,
            'sla_policy_id'                   => null,
            'policy_version'                  => null,
            'sla_response_minutes'            => null,
            'sla_resolution_minutes'          => null,
            'resolution_class'                => 'NO_APPLICABLE_POLICY',
            'governance_rule'                 => 'SLA_POLICY_LOOKUP_FAILURE_NOT_DEFAULT_SLA_INVENTION',
            'evaluation_class'                => 'ADVISORY_ONLY',
            'advisory_notice'                 => 'SLA policy is not configured or approved. Official human contract review required before evaluating vendor SLA performance.',
        ];
    }
}
