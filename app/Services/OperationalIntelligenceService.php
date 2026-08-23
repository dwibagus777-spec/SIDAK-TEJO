<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;

class OperationalIntelligenceService
{
    protected BaseConnection $db;

    /**
     * Revised SLA Hours Definition Matrix (Official Locked Contract v2.0 Refined)
     * P1: Emergency (72 hrs / 3 days), P2: Critical (72 hrs / 3 days), P3: High (168 hrs / 7 days), P4: Medium (720 hrs / 30 days), P5: Routine Monitoring (No Resolution Breach)
     */
    protected static array $slaMatrix = [
        'P1' => ['response_hours' => 2.0,  'resolution_hours' => 72,   'monitoring_interval_hours' => null],
        'P2' => ['response_hours' => 6.0,  'resolution_hours' => 72,   'monitoring_interval_hours' => null],
        'P3' => ['response_hours' => 12.0, 'resolution_hours' => 168,  'monitoring_interval_hours' => null],
        'P4' => ['response_hours' => 24.0, 'resolution_hours' => 720,  'monitoring_interval_hours' => null],
        'P5' => ['response_hours' => 72.0, 'resolution_hours' => null, 'monitoring_interval_hours' => 720], // Routine Monitoring
    ];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Resolve Composite Risk Priority based on Severity (Revised v2.0 Refined Contract)
     * EMERGENCY -> P1 (3 Days), CRITICAL -> P2 (3 Days), HIGH -> P3 (7 Days), MEDIUM -> P4 (30 Days), NORMAL -> P5 (Routine Monitoring)
     */
    public static function resolveRiskPriority(
        string $severity,
        string $assetCriticality = 'NORMAL',
        int $recurrenceCount = 0
    ): array {
        $cSev  = strtoupper(trim($severity));
        $cCrit = strtoupper(trim($assetCriticality));

        $priority = 'P5';

        if ($cSev === 'EMERGENCY') {
            $priority = 'P1';
        } elseif ($cSev === 'CRITICAL') {
            $priority = 'P2';
        } elseif ($cSev === 'HIGH') {
            $priority = 'P3';
        } elseif ($cSev === 'MEDIUM') {
            $priority = 'P4';
        } else {
            $priority = 'P5';
        }

        $slaConfig = self::$slaMatrix[$priority] ?? self::$slaMatrix['P5'];

        return [
            'priority_code'             => $priority,
            'response_sla_hrs'          => $slaConfig['response_hours'],
            'resolution_sla_hrs'        => $slaConfig['resolution_hours'],
            'monitoring_interval_hrs'   => $slaConfig['monitoring_interval_hours'],
            'rule_version'              => 'RISK_PRIORITY_v2.0_REFINED',
        ];
    }

    /**
     * Calculate SLA Compliance Status & Escalation Level
     * P5 Routine Monitoring (resolutionSlaHours === null) never triggers SLA Breach!
     */
    public static function calculateSlaStatus(string $openedAt, ?int $resolutionSlaHours): array
    {
        if ($resolutionSlaHours === null) {
            return [
                'sla_status'        => 'ON_TRACK',
                'pct_elapsed'       => 0.00,
                'elapsed_hours'     => 0.00,
                'sla_deadline'      => 'ROUTINE_MONITORING',
                'escalation_level'  => 'NONE',
                'escalation_target' => 'ROUTINE_MONITORING',
            ];
        }

        $openedTime = new \DateTime($openedAt);
        $currentTime = new \DateTime(Time::now('Asia/Jakarta')->toDateTimeString());

        $elapsedSeconds = max(0, $currentTime->getTimestamp() - $openedTime->getTimestamp());
        $slaSeconds     = max(1, $resolutionSlaHours * 3600);

        $pctElapsed = round(($elapsedSeconds / $slaSeconds) * 100.0, 2);

        $deadlineTime = clone $openedTime;
        $deadlineTime->modify("+{$resolutionSlaHours} hours");

        $status           = 'ON_TRACK';
        $escalationLevel  = 'NONE';
        $escalationTarget = 'FIELD_OFFICER';

        if ($pctElapsed > 100.0) {
            $status           = 'SLA_BREACH';
            $escalationLevel  = 'MANAGER_ESCALATION';
            $escalationTarget = 'MANAJER_ULP_DAN_DALOPS';
        } elseif ($pctElapsed >= 80.0) {
            $status           = 'SLA_WARNING';
            $escalationLevel  = 'SUPERVISOR_WARNING';
            $escalationTarget = 'SUPERVISOR_UNIT';
        }

        return [
            'sla_status'        => $status,
            'pct_elapsed'       => $pctElapsed,
            'elapsed_hours'     => round($elapsedSeconds / 3600, 2),
            'sla_deadline'      => $deadlineTime->format('Y-m-d H:i:s'),
            'escalation_level'  => $escalationLevel,
            'escalation_target' => $escalationTarget,
        ];
    }

    /**
     * Get Aggregated Operational Decision Intelligence Metrics
     */
    public function getOperationalDashboardMetrics(): array
    {
        $cases = $this->db->table('observation_action_cases')
            ->whereNotIn('status', ['VERIFIED', 'SUPERSEDED'])
            ->get()
            ->getResultArray();

        $metrics = [
            'total_active_action_cases' => count($cases),
            'by_priority' => [
                'P1' => 0,
                'P2' => 0,
                'P3' => 0,
                'P4' => 0,
                'P5' => 0,
            ],
            'by_sla_status' => [
                'ON_TRACK'    => 0,
                'SLA_WARNING' => 0,
                'SLA_BREACH'  => 0,
            ],
            'breached_case_ids' => [],
        ];

        foreach ($cases as $c) {
            $pCode = 'P' . ($c['priority'] ?? 5);
            if (isset($metrics['by_priority'][$pCode])) {
                $metrics['by_priority'][$pCode]++;
            }

            $slaShrs = self::$slaMatrix[$pCode]['resolution_hours'] ?? null;
            $slaInfo = self::calculateSlaStatus($c['opened_at'], $slaShrs);

            $sStatus = $slaInfo['sla_status'];
            if (isset($metrics['by_sla_status'][$sStatus])) {
                $metrics['by_sla_status'][$sStatus]++;
            }

            if ($sStatus === 'SLA_BREACH') {
                $metrics['breached_case_ids'][] = (int)$c['id'];
            }
        }

        return $metrics;
    }
}
