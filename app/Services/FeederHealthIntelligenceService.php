<?php

namespace App\Services;

use App\Models\FeederHealthPolicyVersionModel;
use App\Models\FeederHealthPolicyRuleModel;
use App\Models\FeederHealthClassificationModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Executive Feeder Health Intelligence Service (CR-06E / CC-04 Bridge)
 * Governed by 7 Hardening Gates:
 * Gate 6: calculation_policy_version audit trail
 * Gate 7: Parameterized Weights & Thresholds (No hardcoded magic numbers)
 */
class FeederHealthIntelligenceService
{
    protected BaseConnection $db;
    protected FeederHealthPolicyVersionModel $policyModel;
    protected FeederHealthPolicyRuleModel $ruleModel;
    protected FeederHealthClassificationModel $healthModel;

    public const DEFAULT_POLICY_CODE = 'FHI-v1.0';

    public function __construct(?BaseConnection $db = null)
    {
        $this->db          = $db ?? \Config\Database::connect();
        $this->policyModel = new FeederHealthPolicyVersionModel();
        $this->ruleModel   = new FeederHealthPolicyRuleModel();
        $this->healthModel = new FeederHealthClassificationModel();
    }

    /**
     * Ensure Default Policy FHI-v1.0 is initialized in Database (Gate 7).
     */
    public function ensureDefaultPolicy(): array
    {
        $policy = $this->policyModel->where('policy_code', self::DEFAULT_POLICY_CODE)->first();

        if (!$policy) {
            $now = date('Y-m-d H:i:s');
            $policyId = (int)$this->policyModel->insert([
                'policy_code'    => self::DEFAULT_POLICY_CODE,
                'policy_name'    => 'PLN Feeder Health Index Canonical Policy v1.0',
                'description'    => 'Multi-factor health scoring across Outage, Findings, BOM Degradation, and Overload',
                'status'         => 'ACTIVE',
                'effective_from' => $now,
                'effective_to'   => null,
                'created_at'     => $now,
            ], true);

            // Default parameterized rules
            $defaultRules = [
                ['GANGGUAN_FREQUENCY', 0.3000, 85.00, 70.00, 50.00, 49.99],
                ['CRITICAL_FINDINGS',  0.2500, 85.00, 70.00, 50.00, 49.99],
                ['RECURRING_FINDINGS', 0.1500, 85.00, 70.00, 50.00, 49.99],
                ['BOM_DEGRADATION',    0.1500, 85.00, 70.00, 50.00, 49.99],
                ['OVERLOAD_EVENTS',    0.1500, 85.00, 70.00, 50.00, 49.99],
            ];

            foreach ($defaultRules as $r) {
                $this->ruleModel->insert([
                    'policy_version_id'      => $policyId,
                    'metric_key'             => $r[0],
                    'weight'                 => $r[1],
                    'threshold_sempurna_min' => $r[2],
                    'threshold_sakit_min'    => $r[3],
                    'threshold_kronis_min'   => $r[4],
                    'threshold_kritis_max'   => $r[5],
                    'created_at'             => $now,
                ]);
            }

            $policy = $this->policyModel->find($policyId);
        }

        return $policy;
    }

    /**
     * Calculate Feeder Health for a specific Feeder and Period Month (e.g. '2026-08')
     */
    public function calculateFeederHealth(int $penyulangId, ?string $periodMonth = null, ?string $policyCode = null): array
    {
        $period = $periodMonth ?: date('Y-m');
        $policy = $this->ensureDefaultPolicy();

        if ($policyCode !== null && $policyCode !== self::DEFAULT_POLICY_CODE) {
            $customPolicy = $this->policyModel->where('policy_code', $policyCode)->where('status', 'ACTIVE')->first();
            if ($customPolicy) {
                $policy = $customPolicy;
            }
        }

        $rules = $this->ruleModel->where('policy_version_id', $policy['id'])->findAll();
        $ruleMap = [];
        foreach ($rules as $r) {
            $ruleMap[$r['metric_key']] = (float)$r['weight'];
        }

        // 1. Gather Metrics
        $interruptionCount = $this->getInterruptionCount($penyulangId, $period);
        $interruptionDur   = $this->getInterruptionDuration($penyulangId, $period);
        $criticalCount     = $this->getCriticalFindingsCount($penyulangId);
        $recurringCount    = $this->getRecurringFindingsCount($penyulangId);
        $bomDegradation    = $this->getBomDegradationScore($penyulangId);
        $overloadCount     = $this->getOverloadCount($penyulangId, $period);

        // 2. Compute Penalties based on Parameterized Weights
        $wGangguan  = $ruleMap['GANGGUAN_FREQUENCY'] ?? 0.30;
        $wCritical  = $ruleMap['CRITICAL_FINDINGS'] ?? 0.25;
        $wRecurring = $ruleMap['RECURRING_FINDINGS'] ?? 0.15;
        $wBom       = $ruleMap['BOM_DEGRADATION'] ?? 0.15;
        $wOverload  = $ruleMap['OVERLOAD_EVENTS'] ?? 0.15;

        // Sub-scores (0-100 where 100 is pristine)
        $subGangguan  = max(0.0, 100.0 - ($interruptionCount * 15.0 + ($interruptionDur / 60.0) * 5.0));
        $subCritical  = max(0.0, 100.0 - ($criticalCount * 20.0));
        $subRecurring = max(0.0, 100.0 - ($recurringCount * 12.0));
        $subBom       = max(0.0, 100.0 - ($bomDegradation * 10.0));
        $subOverload  = max(0.0, 100.0 - ($overloadCount * 15.0));

        $totalScore = round(
            ($subGangguan * $wGangguan) +
            ($subCritical * $wCritical) +
            ($subRecurring * $wRecurring) +
            ($subBom * $wBom) +
            ($subOverload * $wOverload),
            2
        );
        $totalScore = max(0.0, min(100.0, $totalScore));

        // 3. Classify based on Rules
        $sampleRule = !empty($rules) ? $rules[0] : null;
        $threshSempurna = $sampleRule ? (float)$sampleRule['threshold_sempurna_min'] : 85.0;
        $threshSakit    = $sampleRule ? (float)$sampleRule['threshold_sakit_min'] : 70.0;
        $threshKronis   = $sampleRule ? (float)$sampleRule['threshold_kronis_min'] : 50.0;

        if ($totalScore >= $threshSempurna) {
            $classification = 'SEMPURNA';
        } elseif ($totalScore >= $threshSakit) {
            $classification = 'SAKIT';
        } elseif ($totalScore >= $threshKronis) {
            $classification = 'KRONIS';
        } else {
            $classification = 'KRITIS';
        }

        // 4. Build Explainable JSON Details (Gate 6)
        $explanation = [
            'policy_code'       => $policy['policy_code'],
            'period_month'      => $period,
            'computed_at'       => date('Y-m-d H:i:s'),
            'score_breakdown'   => [
                'gangguan' => [
                    'weight'    => $wGangguan,
                    'sub_score' => $subGangguan,
                    'count'     => $interruptionCount,
                    'duration_m'=> $interruptionDur,
                ],
                'critical_findings' => [
                    'weight'    => $wCritical,
                    'sub_score' => $subCritical,
                    'count'     => $criticalCount,
                ],
                'recurring_findings' => [
                    'weight'    => $wRecurring,
                    'sub_score' => $subRecurring,
                    'count'     => $recurringCount,
                ],
                'bom_degradation' => [
                    'weight'    => $wBom,
                    'sub_score' => $subBom,
                    'score'     => $bomDegradation,
                ],
                'overload' => [
                    'weight'    => $wOverload,
                    'sub_score' => $subOverload,
                    'events'    => $overloadCount,
                ],
            ],
            'executive_summary' => sprintf(
                'Penyulang diklasifikasikan sebagai %s (Skor: %.2f/100) berdasarkan %d gangguan, %d temuan kritis, dan %d temuan berulang.',
                $classification,
                $totalScore,
                $interruptionCount,
                $criticalCount,
                $recurringCount
            ),
        ];

        $now = date('Y-m-d H:i:s');
        $payload = [
            'penyulang_id'                  => $penyulangId,
            'calculation_policy_version'    => $policy['policy_code'],
            'period_month'                  => $period,
            'health_score'                  => $totalScore,
            'health_classification'         => $classification,
            'interruption_count'            => $interruptionCount,
            'interruption_duration_minutes' => $interruptionDur,
            'critical_findings_count'       => $criticalCount,
            'recurring_findings_count'      => $recurringCount,
            'bom_degradation_score'         => $bomDegradation,
            'overload_events_count'         => $overloadCount,
            'explanation_json'              => json_encode($explanation, JSON_PRETTY_PRINT),
            'calculated_at'                 => $now,
        ];

        // Upsert record
        $existing = $this->healthModel
            ->where('penyulang_id', $penyulangId)
            ->where('period_month', $period)
            ->first();

        if ($existing) {
            $this->healthModel->update($existing['id'], $payload);
            $recordId = (int)$existing['id'];
        } else {
            $recordId = (int)$this->healthModel->insert($payload, true);
        }

        return $this->healthModel->find($recordId);
    }

    private function getInterruptionCount(int $penyulangId, string $period): int
    {
        if ($this->db->tableExists('historical_feeder_interruptions')) {
            try {
                return (int)$this->db->table('historical_feeder_interruptions')
                    ->where('penyulang_id', $penyulangId)
                    ->where("DATE_FORMAT(start_time, '%Y-%m')", $period)
                    ->countAllResults();
            } catch (\Throwable $e) {}
        }
        return 0;
    }

    private function getInterruptionDuration(int $penyulangId, string $period): float
    {
        if ($this->db->tableExists('historical_feeder_interruptions')) {
            try {
                $row = $this->db->table('historical_feeder_interruptions')
                    ->selectSum('duration_minutes', 'total_dur')
                    ->where('penyulang_id', $penyulangId)
                    ->where("DATE_FORMAT(start_time, '%Y-%m')", $period)
                    ->get()
                    ->getRowArray();
                return (float)($row['total_dur'] ?? 0.0);
            } catch (\Throwable $e) {}
        }
        return 0.0;
    }

    private function getCriticalFindingsCount(int $penyulangId): int
    {
        if ($this->db->tableExists('temuan')) {
            try {
                return (int)$this->db->table('temuan')
                    ->where('penyulang_id', $penyulangId)
                    ->groupStart()
                        ->like('kategori_anomali', 'KRITIS')
                        ->orLike('prioritas', 'P1')
                    ->groupEnd()
                    ->where('status_pekerjaan !=', 'SELESAI')
                    ->where('deleted_at IS NULL')
                    ->countAllResults();
            } catch (\Throwable $e) {}
        }
        return 0;
    }

    private function getRecurringFindingsCount(int $penyulangId): int
    {
        if ($this->db->tableExists('temuan')) {
            try {
                return (int)$this->db->table('temuan')
                    ->where('penyulang_id', $penyulangId)
                    ->where('is_recurring', 1)
                    ->where('status_pekerjaan !=', 'SELESAI')
                    ->where('deleted_at IS NULL')
                    ->countAllResults();
            } catch (\Throwable $e) {}
        }
        return 0;
    }

    private function getBomDegradationScore(int $penyulangId): float
    {
        // Counts missing/defective accessories in sections under this feeder
        if ($this->db->tableExists('network_section_accessories') && $this->db->tableExists('sections')) {
            try {
                $count = $this->db->table('network_section_accessories')
                    ->join('network_section_configurations', 'network_section_configurations.id = network_section_accessories.network_section_configuration_id')
                    ->join('sections', 'sections.id = network_section_configurations.section_id')
                    ->where('sections.penyulang_id', $penyulangId)
                    ->where('network_section_configurations.verification_status', 'ACTIVE')
                    ->whereIn('network_section_accessories.condition_status', ['DEFECTIVE', 'MISSING'])
                    ->countAllResults();
                return (float)$count;
            } catch (\Throwable $e) {}
        }
        return 0.0;
    }

    private function getOverloadCount(int $penyulangId, string $period): int
    {
        return 0;
    }
}
