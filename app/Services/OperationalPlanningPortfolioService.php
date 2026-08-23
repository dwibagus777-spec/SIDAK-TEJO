<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Operational Planning Portfolio Service (Wave 2 Phase OP-03)
 *
 * Responsibilities:
 * - Portfolio Governance & Human Planning Prioritization Fabric.
 * - Enforces:
 *     PORTFOLIO_PRIORITY != EXECUTION_ORDER
 *     DUPLICATE_ACTIVE_PORTFOLIO_MEMBERSHIP = REJECTED
 *     PORTFOLIO_ITEM_SOURCE_REBINDING = FORBIDDEN
 *     UNEXPLAINED_PRIORITY_DECISION = REJECTED (Auditable Tier Decision Events)
 *     RATIFIED_PORTFOLIO_MUTATION = FORBIDDEN
 *     INCOMPLETE_PORTFOLIO_RATIFICATION = REJECTED
 *     MATERIAL_AGGREGATION_STATUS = INDICATIVE_PORTFOLIO_ESTIMATE_ONLY
 *     ZERO_AUTONOMOUS_EXECUTION = ENFORCED
 */
class OperationalPlanningPortfolioService
{
    public const ALLOWED_PORTFOLIO_TRANSITIONS = [
        'PORTFOLIO_DRAFT'        => ['UNDER_PORTFOLIO_REVIEW'],
        'UNDER_PORTFOLIO_REVIEW' => ['PORTFOLIO_RATIFIED'],
        'PORTFOLIO_RATIFIED'     => ['PORTFOLIO_ARCHIVED'],
        'PORTFOLIO_ARCHIVED'     => [], // Terminal
    ];

    public const VALID_PRIORITY_TIERS = [
        'TIER_1_IMMEDIATE_SCHEDULING',
        'TIER_2_PLANNED_WINDOW',
        'TIER_3_DEFERRED_MAINTENANCE',
    ];

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Human Assembly of a Planning Portfolio from Approved Plans.
     *
     * @param string $title
     * @param int $year
     * @param int $week
     * @param array $planIds
     * @param array|null $actor
     * @return array
     */
    public function assemblePortfolio(
        string $title,
        int $year,
        int $week,
        array $planIds,
        ?array $actor = null
    ): array {
        if (empty($planIds)) {
            return [
                'status'  => 'error',
                'message' => 'At least one approved plan is required to assemble a portfolio.',
                'code'    => 'EMPTY_PORTFOLIO_PLANS',
            ];
        }

        // 1. Exclusivity & Eligibility Check for all planIds
        $plans = $this->db->table('operational_plans')
                          ->whereIn('id', $planIds)
                          ->get()
                          ->getResultArray();

        if (count($plans) !== count($planIds)) {
            return [
                'status'  => 'error',
                'message' => 'One or more selected plans could not be found.',
                'code'    => 'PLAN_NOT_FOUND',
            ];
        }

        foreach ($plans as $p) {
            if ($p['plan_status'] !== 'APPROVED_FOR_PORTFOLIO') {
                return [
                    'status'  => 'error',
                    'message' => "Plan {$p['plan_code']} is '{$p['plan_status']}', but must be 'APPROVED_FOR_PORTFOLIO' to join portfolio.",
                    'code'    => 'PLAN_NOT_ELIGIBLE_FOR_PORTFOLIO',
                ];
            }

            // Check active portfolio membership exclusivity
            $existingMembership = $this->db->table('operational_portfolio_items as opi')
                                           ->join('operational_planning_portfolios as opp', 'opp.id = opi.portfolio_id')
                                           ->where('opi.plan_id', $p['id'])
                                           ->whereIn('opp.portfolio_status', ['PORTFOLIO_DRAFT', 'UNDER_PORTFOLIO_REVIEW', 'PORTFOLIO_RATIFIED'])
                                           ->get()
                                           ->getRowArray();

            if ($existingMembership) {
                return [
                    'status'  => 'error',
                    'message' => "Plan {$p['plan_code']} already belongs to active portfolio {$existingMembership['portfolio_code']} ({$existingMembership['portfolio_status']}).",
                    'code'    => 'DUPLICATE_ACTIVE_PORTFOLIO_MEMBERSHIP',
                ];
            }
        }

        // 2. Aggregate Material Demands and Risk Summaries
        $materialMap = [];
        $outageCount = 0;
        $feederDist  = [];

        foreach ($plans as $p) {
            if (!empty($p['outage_required'])) {
                $outageCount++;
            }
            $feederName = $p['feeder_name'] ?? 'BALUNG';
            $feederDist[$feederName] = ($feederDist[$feederName] ?? 0) + 1;

            if (!empty($p['indicative_materials_json'])) {
                $items = json_decode($p['indicative_materials_json'], true);
                if (is_array($items)) {
                    foreach ($items as $m) {
                        $mName = $m['material_name'] ?? 'Material';
                        $unit  = $m['unit'] ?? 'buah';
                        $qty   = (float)($m['quantity'] ?? 1);
                        $key   = $mName . '|' . $unit;

                        if (!isset($materialMap[$key])) {
                            $materialMap[$key] = [
                                'material_name' => $mName,
                                'total_quantity'=> 0,
                                'unit'          => $unit,
                            ];
                        }
                        $materialMap[$key]['total_quantity'] += $qty;
                    }
                }
            }
        }

        $aggregatedMaterials = array_values($materialMap);

        $riskSummary = [
            'total_plans'        => count($plans),
            'outage_plans'       => $outageCount,
            'pdkb_plans'         => count($plans) - $outageCount,
            'feeder_distribution'=> $feederDist,
            'governance_model'   => 'HUMAN_PORTFOLIO_PRIORITIZATION',
        ];

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'HUMAN_PORTFOLIO_MANAGER';
        $portfolioCode = 'PORTFOLIO-STJ-' . $year . '-W' . str_pad((string)$week, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

        // 3. Persist Portfolio Master Record
        $portfolioData = [
            'portfolio_code'               => $portfolioCode,
            'portfolio_title'              => trim($title),
            'period_year'                  => $year,
            'period_week'                  => $week,
            'portfolio_status'             => 'PORTFOLIO_DRAFT',
            'total_plans_count'            => count($plans),
            'total_outage_plans_count'     => $outageCount,
            'tier_1_plans_count'           => 0,
            'tier_2_plans_count'           => 0,
            'tier_3_plans_count'           => 0,
            'portfolio_risk_summary_json'  => json_encode($riskSummary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'material_demand_summary_json' => json_encode($aggregatedMaterials, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'material_aggregation_status'  => 'INDICATIVE_PORTFOLIO_ESTIMATE_ONLY',
            'created_by_actor_name'        => $actorName,
            'created_at'                   => $now,
            'updated_at'                   => $now,
        ];

        $this->db->table('operational_planning_portfolios')->insert($portfolioData);
        $portfolioId = (int)$this->db->insertID();

        // 4. Persist Portfolio Items with Immutable Lineage
        foreach ($plans as $p) {
            $itemData = [
                'portfolio_id'         => $portfolioId,
                'portfolio_code'       => $portfolioCode,
                'plan_id'              => $p['id'],
                'plan_code'            => $p['plan_code'],
                'candidate_id'         => $p['candidate_id'],
                'candidate_code'       => $p['candidate_code'],
                'snapshot_id'          => $p['snapshot_id'],
                'snapshot_code'        => $p['snapshot_code'],
                'penyulang_id'         => $p['penyulang_id'],
                'feeder_name'          => $p['feeder_name'],
                'section_id'           => $p['section_id'],
                'section_name'         => $p['section_name'],
                'work_category'        => $p['work_category'],
                'outage_required'      => $p['outage_required'],
                'priority_tier'        => 'UNASSIGNED',
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
            $this->db->table('operational_portfolio_items')->insert($itemData);
        }

        return [
            'status'             => 'success',
            'portfolio_id'       => $portfolioId,
            'portfolio_code'     => $portfolioCode,
            'total_plans'        => count($plans),
            'portfolio_status'   => 'PORTFOLIO_DRAFT',
            'material_status'    => 'INDICATIVE_PORTFOLIO_ESTIMATE_ONLY',
            'governance_verdict' => 'PORTFOLIO_ASSEMBLED_EXCLUSIVITY_VERIFIED',
        ];
    }

    /**
     * Human Assignment / Override of Priority Tier for a Portfolio Item with Auditable Event Log.
     *
     * @param int $portfolioItemId
     * @param string $tier
     * @param string $rationale Mandatory human rationale
     * @param array|null $actor
     * @return array
     */
    public function assignItemPriorityTier(
        int $portfolioItemId,
        string $tier,
        string $rationale,
        ?array $actor = null
    ): array {
        $item = $this->db->table('operational_portfolio_items')
                         ->where('id', $portfolioItemId)
                         ->get()
                         ->getRowArray();

        if (!$item) {
            return [
                'status'  => 'error',
                'message' => "Portfolio Item #{$portfolioItemId} not found.",
                'code'    => 'PORTFOLIO_ITEM_NOT_FOUND',
            ];
        }

        $portfolio = $this->db->table('operational_planning_portfolios')
                              ->where('id', $item['portfolio_id'])
                              ->get()
                              ->getRowArray();

        // 1. Ratification Freeze Check
        if (in_array($portfolio['portfolio_status'], ['PORTFOLIO_RATIFIED', 'PORTFOLIO_ARCHIVED'], true)) {
            return [
                'status'  => 'error',
                'message' => "Portfolio {$portfolio['portfolio_code']} is {$portfolio['portfolio_status']}. Mutating item priority is forbidden.",
                'code'    => 'RATIFIED_PORTFOLIO_MUTATION_FORBIDDEN',
            ];
        }

        // 2. Mandatory Rationale Check
        $cleanRationale = trim($rationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Priority decision rationale is mandatory for every human tier assignment.',
                'code'    => 'UNEXPLAINED_PRIORITY_DECISION_REJECTED',
            ];
        }

        $cleanTier = strtoupper(trim($tier));
        if (!in_array($cleanTier, self::VALID_PRIORITY_TIERS, true)) {
            return [
                'status'  => 'error',
                'message' => "Invalid priority tier: {$cleanTier}. Allowed: " . implode(', ', self::VALID_PRIORITY_TIERS),
                'code'    => 'INVALID_PRIORITY_TIER',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'HUMAN_PORTFOLIO_MANAGER';
        $prevTier  = $item['priority_tier'];

        // 3. Update Item Priority
        $this->db->table('operational_portfolio_items')
                 ->where('id', $portfolioItemId)
                 ->update([
                     'priority_tier'        => $cleanTier,
                     'priority_assigned_by' => $actorName,
                     'priority_assigned_at' => $now,
                     'priority_rationale'   => $cleanRationale,
                     'updated_at'           => $now,
                 ]);

        // 4. Append-Only Audit Event
        $this->db->table('operational_portfolio_tier_events')->insert([
            'portfolio_id'      => $item['portfolio_id'],
            'portfolio_item_id' => $portfolioItemId,
            'plan_code'         => $item['plan_code'],
            'previous_tier'     => $prevTier,
            'new_tier'          => $cleanTier,
            'decision_rationale'=> $cleanRationale,
            'decided_by'        => $actorName,
            'decided_at'        => $now,
        ]);

        // 5. Recalculate Portfolio Tier Counts
        $t1 = $this->db->table('operational_portfolio_items')->where('portfolio_id', $item['portfolio_id'])->where('priority_tier', 'TIER_1_IMMEDIATE_SCHEDULING')->countAllResults();
        $t2 = $this->db->table('operational_portfolio_items')->where('portfolio_id', $item['portfolio_id'])->where('priority_tier', 'TIER_2_PLANNED_WINDOW')->countAllResults();
        $t3 = $this->db->table('operational_portfolio_items')->where('portfolio_id', $item['portfolio_id'])->where('priority_tier', 'TIER_3_DEFERRED_MAINTENANCE')->countAllResults();

        $this->db->table('operational_planning_portfolios')
                 ->where('id', $item['portfolio_id'])
                 ->update([
                     'tier_1_plans_count' => $t1,
                     'tier_2_plans_count' => $t2,
                     'tier_3_plans_count' => $t3,
                     'updated_at'         => $now,
                 ]);

        return [
            'status'             => 'success',
            'portfolio_item_id'  => $portfolioItemId,
            'plan_code'          => $item['plan_code'],
            'previous_tier'      => $prevTier,
            'new_tier'           => $cleanTier,
            'decided_by'         => $actorName,
            'decision_rationale' => $cleanRationale,
            'governance_verdict' => 'AUDITABLE_HUMAN_TIER_DECISION_RECORDED',
        ];
    }

    /**
     * State Machine Transition for Portfolio (Draft -> Review -> Ratified).
     *
     * @param int $portfolioId
     * @param string $toStatus
     * @param string $rationale Mandatory review/ratification rationale
     * @param array|null $actor
     * @return array
     */
    public function transitionPortfolioStatus(
        int $portfolioId,
        string $toStatus,
        string $rationale,
        ?array $actor = null
    ): array {
        $portfolio = $this->db->table('operational_planning_portfolios')
                              ->where('id', $portfolioId)
                              ->get()
                              ->getRowArray();

        if (!$portfolio) {
            return [
                'status'  => 'error',
                'message' => "Portfolio #{$portfolioId} not found.",
                'code'    => 'PORTFOLIO_NOT_FOUND',
            ];
        }

        $fromStatus = $portfolio['portfolio_status'];
        $targetStatus = strtoupper(trim($toStatus));
        $cleanRationale = trim($rationale);

        // Check allowed state transitions
        $allowedNext = self::ALLOWED_PORTFOLIO_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($targetStatus, $allowedNext, true)) {
            return [
                'status'  => 'error',
                'message' => "Invalid transition from {$fromStatus} to {$targetStatus}. Allowed: " . implode(', ', $allowedNext ?: ['NONE (Terminal State)']),
                'code'    => 'INVALID_PORTFOLIO_TRANSITION',
            ];
        }

        // Completeness validation when transitioning to PORTFOLIO_RATIFIED
        if ($targetStatus === 'PORTFOLIO_RATIFIED') {
            if ($cleanRationale === '') {
                return [
                    'status'  => 'error',
                    'message' => 'Ratification rationale is mandatory for ratifying a portfolio.',
                    'code'    => 'MANDATORY_RATIFICATION_RATIONALE_REQUIRED',
                ];
            }

            // Ensure no items have UNASSIGNED priority tier
            $unassignedCount = $this->db->table('operational_portfolio_items')
                                        ->where('portfolio_id', $portfolioId)
                                        ->where('priority_tier', 'UNASSIGNED')
                                        ->countAllResults();

            if ($unassignedCount > 0) {
                return [
                    'status'  => 'error',
                    'message' => "Cannot ratify portfolio: {$unassignedCount} items still have UNASSIGNED priority tier.",
                    'code'    => 'INCOMPLETE_PORTFOLIO_RATIFICATION_UNASSIGNED_ITEMS',
                ];
            }
        }

        $now = date('Y-m-d H:i:s');
        $managerName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'MANAJER_BAGIAN_JARINGAN';
        $managerRole = $actor['role'] ?? 'MANAJER_JARINGAN';

        $updateData = [
            'portfolio_status' => $targetStatus,
            'updated_at'       => $now,
        ];

        if ($targetStatus === 'PORTFOLIO_RATIFIED') {
            $updateData['governing_manager_name'] = $managerName;
            $updateData['governing_manager_role'] = $managerRole;
            $updateData['ratification_rationale'] = $cleanRationale;
            $updateData['ratified_at']            = $now;
        }

        $this->db->table('operational_planning_portfolios')
                 ->where('id', $portfolioId)
                 ->update($updateData);

        return [
            'status'             => 'success',
            'portfolio_id'       => $portfolioId,
            'portfolio_code'     => $portfolio['portfolio_code'],
            'from_status'        => $fromStatus,
            'to_status'          => $targetStatus,
            'ratified_by'        => $managerName,
            'governance_verdict' => 'PORTFOLIO_STATE_TRANSITION_VERIFIED',
        ];
    }

    /**
     * Get list of portfolios with optional filters.
     */
    public function getPortfolios(array $filters = []): array
    {
        if (!$this->db->tableExists('operational_planning_portfolios')) {
            return [];
        }

        $builder = $this->db->table('operational_planning_portfolios');

        if (!empty($filters['status'])) {
            $builder->where('portfolio_status', $filters['status']);
        }
        if (!empty($filters['year'])) {
            $builder->where('period_year', (int)$filters['year']);
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * Get approved plans ready for portfolio assembly (not already in active portfolio).
     */
    public function getUnassignedApprovedPlans(): array
    {
        if (!$this->db->tableExists('operational_plans')) {
            return [];
        }

        $plans = $this->db->table('operational_plans')
                          ->where('plan_status', 'APPROVED_FOR_PORTFOLIO')
                          ->get()
                          ->getResultArray();

        $ready = [];
        foreach ($plans as $p) {
            $activeMembership = $this->db->table('operational_portfolio_items as opi')
                                         ->join('operational_planning_portfolios as opp', 'opp.id = opi.portfolio_id')
                                         ->where('opi.plan_id', $p['id'])
                                         ->whereIn('opp.portfolio_status', ['PORTFOLIO_DRAFT', 'UNDER_PORTFOLIO_REVIEW', 'PORTFOLIO_RATIFIED'])
                                         ->countAllResults();
            if ($activeMembership === 0) {
                $ready[] = $p;
            }
        }

        return $ready;
    }

    /**
     * Get Portfolio Detail with items, aggregated materials, and tier events.
     */
    public function getPortfolioDetail(int $portfolioId): array
    {
        $portfolio = $this->db->table('operational_planning_portfolios')
                              ->where('id', $portfolioId)
                              ->get()
                              ->getRowArray();

        if (!$portfolio) {
            return [];
        }

        $items = $this->db->table('operational_portfolio_items')
                          ->where('portfolio_id', $portfolioId)
                          ->orderBy('id', 'ASC')
                          ->get()
                          ->getResultArray();

        $tierEvents = $this->db->table('operational_portfolio_tier_events')
                               ->where('portfolio_id', $portfolioId)
                               ->orderBy('id', 'DESC')
                               ->get()
                               ->getResultArray();

        $materials = !empty($portfolio['material_demand_summary_json'])
            ? json_decode($portfolio['material_demand_summary_json'], true)
            : [];

        $riskSummary = !empty($portfolio['portfolio_risk_summary_json'])
            ? json_decode($portfolio['portfolio_risk_summary_json'], true)
            : [];

        return [
            'portfolio'    => $portfolio,
            'items'        => $items,
            'materials'    => $materials,
            'risk_summary' => $riskSummary,
            'tier_events'  => $tierEvents,
            'invariants'   => [
                'portfolio_item_source_rebinding_locked' => true,
                'material_aggregation_status'            => $portfolio['material_aggregation_status'],
                'work_order_created'                     => false,
                'crew_dispatched'                        => false,
                'network_switching_triggered'            => false,
            ],
        ];
    }
}
