<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Operational Scheduling Service (Wave 2 Phase OP-04)
 *
 * Responsibilities:
 * - Governed Scheduling & Resource Capacity Planning Bridge.
 * - Enforces:
 *     SCHEDULE_SCENARIO != CREW_DISPATCH
 *     CAPACITY_ALLOCATION != PERSONNEL_ASSIGNMENT
 *     OUTAGE_WINDOW_PROPOSAL != NETWORK_SWITCHING_AUTHORIZATION
 *     DUPLICATE_ACTIVE_SCENARIO_FOR_PORTFOLIO = REJECTED
 *     SCENARIO_SOURCE_REBINDING = FORBIDDEN
 *     UNEXPLAINED_SLOT_OVERRIDE = REJECTED (Append-only slot event logs)
 *     APPROVED_SCENARIO_MUTATION = FORBIDDEN (Frozen upon ratification)
 *     ZERO_AUTONOMOUS_EXECUTION = ENFORCED
 */
class OperationalSchedulingService
{
    public const ALLOWED_SCENARIO_TRANSITIONS = [
        'SCENARIO_DRAFT'         => ['UNDER_CAPACITY_REVIEW'],
        'UNDER_CAPACITY_REVIEW'  => ['SCENARIO_APPROVED', 'REVISION_REQUIRED'],
        'REVISION_REQUIRED'      => ['SCENARIO_DRAFT'],
        'SCENARIO_APPROVED'      => ['SCENARIO_SUPERSEDED'],
        'SCENARIO_SUPERSEDED'    => [], // Terminal
    ];

    public const VALID_STRATEGIES = [
        'BALANCED_PDKB_PREFERRED',
        'AGGRESSIVE_OUTAGE_WINDOW',
        'CONSERVATIVE_CAPACITY',
    ];

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Create a Scheduling Scenario Draft for a Ratified Portfolio.
     *
     * @param int $portfolioId
     * @param string $title
     * @param string $strategy
     * @param array|null $actor
     * @return array
     */
    public function createScenarioDraft(
        int $portfolioId,
        string $title,
        string $strategy,
        ?array $actor = null
    ): array {
        // 1. Eligibility Check: Portfolio must be PORTFOLIO_RATIFIED
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

        if ($portfolio['portfolio_status'] !== 'PORTFOLIO_RATIFIED') {
            return [
                'status'  => 'error',
                'message' => "Portfolio {$portfolio['portfolio_code']} is '{$portfolio['portfolio_status']}', but must be 'PORTFOLIO_RATIFIED' to generate scheduling scenarios.",
                'code'    => 'PORTFOLIO_NOT_RATIFIED',
            ];
        }

        // 2. Exclusivity & Idempotency Check: No active scenario already exists
        $activeScenario = $this->db->table('operational_scheduling_scenarios')
                                   ->where('portfolio_id', $portfolioId)
                                   ->whereIn('scenario_status', ['SCENARIO_DRAFT', 'UNDER_CAPACITY_REVIEW', 'SCENARIO_APPROVED', 'REVISION_REQUIRED'])
                                   ->get()
                                   ->getRowArray();

        if ($activeScenario) {
            return [
                'status'  => 'error',
                'message' => "Portfolio {$portfolio['portfolio_code']} already has an active scenario: {$activeScenario['scenario_code']} ({$activeScenario['scenario_status']}).",
                'code'    => 'DUPLICATE_ACTIVE_SCENARIO_FOR_PORTFOLIO',
            ];
        }

        $cleanStrategy = strtoupper(trim($strategy));
        if (!in_array($cleanStrategy, self::VALID_STRATEGIES, true)) {
            $cleanStrategy = 'BALANCED_PDKB_PREFERRED';
        }

        // Fetch portfolio items
        $items = $this->db->table('operational_portfolio_items')
                          ->where('portfolio_id', $portfolioId)
                          ->orderBy('priority_tier', 'ASC')
                          ->get()
                          ->getResultArray();

        if (empty($items)) {
            return [
                'status'  => 'error',
                'message' => 'Portfolio has no items to schedule.',
                'code'    => 'EMPTY_PORTFOLIO_ITEMS',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'HUMAN_SCHEDULING_PLANNER';
        $scenarioCode = 'SCHED-SCN-STJ-' . $portfolio['period_year'] . '-W' . str_pad((string)$portfolio['period_week'], 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT) . '-' . substr(md5(uniqid('', true)), 0, 3);

        // 3. Persist Scenario Master Record
        $scenarioData = [
            'scenario_code'               => $scenarioCode,
            'portfolio_id'                => $portfolioId,
            'portfolio_code'              => $portfolio['portfolio_code'],
            'scenario_title'              => trim($title),
            'scenario_strategy'           => $cleanStrategy,
            'scenario_status'             => 'SCENARIO_DRAFT',
            'total_scheduled_plans_count' => count($items),
            'total_estimated_man_days'    => round(count($items) * 1.5, 1),
            'peak_daily_outage_count'     => 1,
            'dispatch_status'             => 'NO_DISPATCH_AUTHORITY',
            'personnel_assignment_status' => 'CAPACITY_ESTIMATE_ONLY',
            'network_operation_status'    => 'NO_SWITCHING_AUTHORITY',
            'work_order_status'           => 'NOT_A_WORK_ORDER',
            'created_by_actor_name'       => $actorName,
            'created_at'                  => $now,
            'updated_at'                  => $now,
        ];

        $this->db->table('operational_scheduling_scenarios')->insert($scenarioData);
        $scenarioId = (int)$this->db->insertID();

        // 4. Initial Slot Allocation according to Strategy
        $baseDate = date('Y-m-d', strtotime("{$portfolio['period_year']}W" . str_pad((string)$portfolio['period_week'], 2, '0', STR_PAD_LEFT) . "1")); // Monday of week
        $dayOffset = 0;

        foreach ($items as $idx => $it) {
            $slotDate = date('Y-m-d', strtotime("{$baseDate} +{$dayOffset} days"));
            if ($dayOffset < 4) {
                $dayOffset++;
            } else {
                $dayOffset = 0;
            }

            $crewType = !empty($it['outage_required']) ? 'REGU_PEMELIHARAAN_SUTM_PADAM' : 'REGU_PDKB_BERTEGANGAN';
            $duration = !empty($it['outage_required']) ? 5.0 : 3.5;

            $slotRecord = [
                'scenario_id'               => $scenarioId,
                'portfolio_item_id'         => $it['id'],
                'plan_id'                   => $it['plan_id'],
                'plan_code'                 => $it['plan_code'],
                'candidate_id'              => $it['candidate_id'],
                'snapshot_id'               => $it['snapshot_id'],
                'feeder_name'               => $it['feeder_name'],
                'section_name'              => $it['section_name'],
                'priority_tier'             => $it['priority_tier'],
                'scheduled_date'            => $slotDate,
                'scheduled_start_time'      => '08:30:00',
                'scheduled_end_time'        => !empty($it['outage_required']) ? '13:30:00' : '12:00:00',
                'estimated_duration_hours'  => $duration,
                'estimated_crew_type'       => $crewType,
                'outage_required'           => $it['outage_required'],
                'capacity_override_applied' => 0,
                'scheduling_notes'          => "Alokasi awal strategi {$cleanStrategy}",
                'created_at'                => $now,
                'updated_at'                => $now,
            ];

            $this->db->table('operational_scheduled_slots')->insert($slotRecord);
            $slotId = (int)$this->db->insertID();

            // Record initial slot creation event
            $this->db->table('operational_scheduling_slot_events')->insert([
                'scenario_id'        => $scenarioId,
                'slot_id'            => $slotId,
                'plan_code'          => $it['plan_code'],
                'event_type'         => 'SLOT_CREATED',
                'new_payload_json'   => json_encode($slotRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'decision_rationale' => "Inisialisasi slot jadwal strategi {$cleanStrategy}",
                'decided_by'         => $actorName,
                'decided_at'         => $now,
            ]);
        }

        $this->recalculateScenarioCapacity($scenarioId);

        return [
            'status'             => 'success',
            'scenario_id'        => $scenarioId,
            'scenario_code'      => $scenarioCode,
            'portfolio_code'     => $portfolio['portfolio_code'],
            'total_slots'        => count($items),
            'strategy'           => $cleanStrategy,
            'governance_verdict' => 'SCHEDULING_SCENARIO_DRAFTED_IMMUTABLY_BOUND',
        ];
    }

    /**
     * Update / Reschedule a Slot with Mandatory Rationale & Append-Only Audit Logging.
     *
     * @param int $slotId
     * @param array $slotData
     * @param string $rationale Mandatory human rationale
     * @param array|null $actor
     * @return array
     */
    public function updateSlot(
        int $slotId,
        array $slotData,
        string $rationale,
        ?array $actor = null
    ): array {
        $slot = $this->db->table('operational_scheduled_slots')
                         ->where('id', $slotId)
                         ->get()
                         ->getRowArray();

        if (!$slot) {
            return [
                'status'  => 'error',
                'message' => "Scheduled Slot #{$slotId} not found.",
                'code'    => 'SLOT_NOT_FOUND',
            ];
        }

        $scenario = $this->db->table('operational_scheduling_scenarios')
                             ->where('id', $slot['scenario_id'])
                             ->get()
                             ->getRowArray();

        // 1. Scenario Freeze Check
        if (in_array($scenario['scenario_status'], ['SCENARIO_APPROVED', 'SCENARIO_SUPERSEDED'], true)) {
            return [
                'status'  => 'error',
                'message' => "Scenario {$scenario['scenario_code']} is {$scenario['scenario_status']}. Mutating slots is forbidden.",
                'code'    => 'APPROVED_SCENARIO_MUTATION_FORBIDDEN',
            ];
        }

        // 2. Mandatory Rationale Check
        $cleanRationale = trim($rationale);
        if ($cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Decision rationale is mandatory for every slot update / capacity override.',
                'code'    => 'UNEXPLAINED_SLOT_OVERRIDE_REJECTED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'HUMAN_SCHEDULING_PLANNER';

        $newDate      = $slotData['scheduled_date'] ?? $slot['scheduled_date'];
        $newStart     = $slotData['scheduled_start_time'] ?? $slot['scheduled_start_time'];
        $newEnd       = $slotData['scheduled_end_time'] ?? $slot['scheduled_end_time'];
        $newDuration  = (float)($slotData['estimated_duration_hours'] ?? $slot['estimated_duration_hours']);
        $newCrewType  = $slotData['estimated_crew_type'] ?? $slot['estimated_crew_type'];
        $newOutage    = isset($slotData['outage_required']) ? (int)$slotData['outage_required'] : (int)$slot['outage_required'];
        $isOverride   = !empty($slotData['capacity_override_applied']) ? 1 : 0;
        $notes        = $slotData['scheduling_notes'] ?? $slot['scheduling_notes'];

        $updatedFields = [
            'scheduled_date'            => $newDate,
            'scheduled_start_time'      => $newStart,
            'scheduled_end_time'        => $newEnd,
            'estimated_duration_hours'  => $newDuration,
            'estimated_crew_type'       => $newCrewType,
            'outage_required'           => $newOutage,
            'capacity_override_applied' => $isOverride,
            'scheduling_notes'          => $notes,
            'updated_at'                => $now,
        ];

        // 3. Update Slot
        $this->db->table('operational_scheduled_slots')
                 ->where('id', $slotId)
                 ->update($updatedFields);

        // 4. Record Append-Only Audit Event
        $eventType = ($newDate !== $slot['scheduled_date']) ? 'SLOT_RESCHEDULED' : ($isOverride ? 'CAPACITY_OVERRIDE_APPLIED' : 'SLOT_UPDATED');
        $this->db->table('operational_scheduling_slot_events')->insert([
            'scenario_id'           => $slot['scenario_id'],
            'slot_id'               => $slotId,
            'plan_code'             => $slot['plan_code'],
            'event_type'            => $eventType,
            'previous_payload_json' => json_encode($slot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'new_payload_json'      => json_encode($updatedFields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'decision_rationale'    => $cleanRationale,
            'decided_by'            => $actorName,
            'decided_at'            => $now,
        ]);

        $this->recalculateScenarioCapacity($slot['scenario_id']);

        return [
            'status'             => 'success',
            'slot_id'            => $slotId,
            'plan_code'          => $slot['plan_code'],
            'scheduled_date'     => $newDate,
            'decided_by'         => $actorName,
            'decision_rationale' => $cleanRationale,
            'governance_verdict' => 'SLOT_UPDATED_EVENT_LOGGED',
        ];
    }

    /**
     * Recalculate and update macro capacity metrics on scenario master.
     */
    protected function recalculateScenarioCapacity(int $scenarioId): void
    {
        $slots = $this->db->table('operational_scheduled_slots')
                          ->where('scenario_id', $scenarioId)
                          ->get()
                          ->getResultArray();

        $totalHours = 0.0;
        $dailyOutageMap = [];
        $dailyLoadMap   = [];

        foreach ($slots as $s) {
            $totalHours += (float)$s['estimated_duration_hours'];
            $d = $s['scheduled_date'];

            if (!empty($s['outage_required'])) {
                $dailyOutageMap[$d] = ($dailyOutageMap[$d] ?? 0) + 1;
            }
            $dailyLoadMap[$d] = ($dailyLoadMap[$d] ?? 0) + (float)$s['estimated_duration_hours'];
        }

        $peakDailyOutages = !empty($dailyOutageMap) ? max($dailyOutageMap) : 0;
        $estimatedManDays = round($totalHours / 7.0, 1);

        $capacityAssessment = [
            'total_estimated_work_hours' => $totalHours,
            'total_estimated_man_days'   => $estimatedManDays,
            'peak_daily_outages'         => $peakDailyOutages,
            'daily_workload_hours'       => $dailyLoadMap,
            'daily_outages_count'        => $dailyOutageMap,
            'capacity_status'            => ($peakDailyOutages > 2) ? 'CAPACITY_WARNING_HIGH_OUTAGE' : 'BALANCED_CAPACITY_OPTIMAL',
        ];

        $this->db->table('operational_scheduling_scenarios')
                 ->where('id', $scenarioId)
                 ->update([
                     'total_scheduled_plans_count' => count($slots),
                     'total_estimated_man_days'    => $estimatedManDays,
                     'peak_daily_outage_count'     => $peakDailyOutages,
                     'capacity_assessment_json'    => json_encode($capacityAssessment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                     'updated_at'                  => date('Y-m-d H:i:s'),
                 ]);
    }

    /**
     * State Machine Transition for Scheduling Scenario.
     *
     * @param int $scenarioId
     * @param string $toStatus
     * @param string $rationale Mandatory rationale
     * @param array|null $actor
     * @return array
     */
    public function transitionScenarioStatus(
        int $scenarioId,
        string $toStatus,
        string $rationale,
        ?array $actor = null
    ): array {
        $scenario = $this->db->table('operational_scheduling_scenarios')
                             ->where('id', $scenarioId)
                             ->get()
                             ->getRowArray();

        if (!$scenario) {
            return [
                'status'  => 'error',
                'message' => "Scenario #{$scenarioId} not found.",
                'code'    => 'SCENARIO_NOT_FOUND',
            ];
        }

        $fromStatus = $scenario['scenario_status'];
        $targetStatus = strtoupper(trim($toStatus));
        $cleanRationale = trim($rationale);

        // Validate allowed transitions
        $allowedNext = self::ALLOWED_SCENARIO_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($targetStatus, $allowedNext, true)) {
            return [
                'status'  => 'error',
                'message' => "Invalid transition from {$fromStatus} to {$targetStatus}. Allowed: " . implode(', ', $allowedNext ?: ['NONE (Terminal State)']),
                'code'    => 'INVALID_SCENARIO_TRANSITION',
            ];
        }

        // Completeness check for SCENARIO_APPROVED
        if ($targetStatus === 'SCENARIO_APPROVED') {
            if ($cleanRationale === '') {
                return [
                    'status'  => 'error',
                    'message' => 'Approval rationale is mandatory for ratifying a scheduling scenario.',
                    'code'    => 'MANDATORY_APPROVAL_RATIONALE_REQUIRED',
                ];
            }

            // Ensure all slots are valid
            $slotCount = $this->db->table('operational_scheduled_slots')
                                  ->where('scenario_id', $scenarioId)
                                  ->countAllResults();

            if ($slotCount === 0) {
                return [
                    'status'  => 'error',
                    'message' => 'Cannot approve scenario with zero scheduled slots.',
                    'code'    => 'INCOMPLETE_SCENARIO_NO_SLOTS',
                ];
            }
        }

        // Revision check for REVISION_REQUIRED
        if ($targetStatus === 'REVISION_REQUIRED' && $cleanRationale === '') {
            return [
                'status'  => 'error',
                'message' => 'Revision reason is mandatory when requesting scenario revision.',
                'code'    => 'MANDATORY_REVISION_REASON_REQUIRED',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $actorName = $actor['name'] ?? (function_exists('session') ? session()->get('nama_lengkap') : null) ?? 'KOORDINATOR_PEMELIHARAAN_JARINGAN';

        $updateData = [
            'scenario_status' => $targetStatus,
            'updated_at'      => $now,
        ];

        if ($targetStatus === 'SCENARIO_APPROVED') {
            $updateData['approver_actor_name'] = $actorName;
            $updateData['approval_rationale']  = $cleanRationale;
            $updateData['approved_at']         = $now;
        } elseif ($targetStatus === 'REVISION_REQUIRED') {
            $updateData['revision_reason']     = $cleanRationale;
        }

        $this->db->table('operational_scheduling_scenarios')
                 ->where('id', $scenarioId)
                 ->update($updateData);

        return [
            'status'             => 'success',
            'scenario_id'        => $scenarioId,
            'scenario_code'      => $scenario['scenario_code'],
            'from_status'        => $fromStatus,
            'to_status'          => $targetStatus,
            'approver_name'      => $actorName,
            'governance_verdict' => 'SCENARIO_STATE_TRANSITION_VERIFIED',
        ];
    }

    /**
     * Supersede an approved scenario so a new scenario can be drafted for the portfolio.
     */
    public function supersedeScenario(int $scenarioId, string $rationale, ?array $actor = null): array
    {
        return $this->transitionScenarioStatus($scenarioId, 'SCENARIO_SUPERSEDED', $rationale, $actor);
    }

    /**
     * Get list of scenarios with optional filters.
     */
    public function getScenarios(array $filters = []): array
    {
        if (!$this->db->tableExists('operational_scheduling_scenarios')) {
            return [];
        }

        $builder = $this->db->table('operational_scheduling_scenarios');

        if (!empty($filters['status'])) {
            $builder->where('scenario_status', $filters['status']);
        }
        if (!empty($filters['portfolio_id'])) {
            $builder->where('portfolio_id', (int)$filters['portfolio_id']);
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * Get Ratified Portfolios ready for scheduling scenario creation.
     */
    public function getRatifiedPortfoliosReadyForScheduling(): array
    {
        if (!$this->db->tableExists('operational_planning_portfolios')) {
            return [];
        }

        $portfolios = $this->db->table('operational_planning_portfolios')
                               ->where('portfolio_status', 'PORTFOLIO_RATIFIED')
                               ->get()
                               ->getResultArray();

        $ready = [];
        foreach ($portfolios as $p) {
            $activeScenarioCount = $this->db->table('operational_scheduling_scenarios')
                                            ->where('portfolio_id', $p['id'])
                                            ->whereIn('scenario_status', ['SCENARIO_DRAFT', 'UNDER_CAPACITY_REVIEW', 'SCENARIO_APPROVED', 'REVISION_REQUIRED'])
                                            ->countAllResults();
            if ($activeScenarioCount === 0) {
                $ready[] = $p;
            }
        }

        return $ready;
    }

    /**
     * Get Scenario Detail with slots, capacity assessment, and slot event audit logs.
     */
    public function getScenarioDetail(int $scenarioId): array
    {
        $scenario = $this->db->table('operational_scheduling_scenarios')
                             ->where('id', $scenarioId)
                             ->get()
                             ->getRowArray();

        if (!$scenario) {
            return [];
        }

        $slots = $this->db->table('operational_scheduled_slots')
                          ->where('scenario_id', $scenarioId)
                          ->orderBy('scheduled_date', 'ASC')
                          ->orderBy('scheduled_start_time', 'ASC')
                          ->get()
                          ->getResultArray();

        $slotEvents = $this->db->table('operational_scheduling_slot_events')
                               ->where('scenario_id', $scenarioId)
                               ->orderBy('id', 'DESC')
                               ->get()
                               ->getResultArray();

        $capacity = !empty($scenario['capacity_assessment_json'])
            ? json_decode($scenario['capacity_assessment_json'], true)
            : [];

        $portfolio = $this->db->table('operational_planning_portfolios')
                              ->where('id', $scenario['portfolio_id'])
                              ->get()
                              ->getRowArray();

        return [
            'scenario'   => $scenario,
            'portfolio'  => $portfolio,
            'slots'      => $slots,
            'capacity'   => $capacity,
            'events'     => $slotEvents,
            'invariants' => [
                'scenario_source_rebinding_locked' => true,
                'dispatch_status'                  => $scenario['dispatch_status'],
                'personnel_assignment_status'      => $scenario['personnel_assignment_status'],
                'network_operation_status'         => $scenario['network_operation_status'],
                'work_order_status'                => $scenario['work_order_status'],
            ],
        ];
    }
}
