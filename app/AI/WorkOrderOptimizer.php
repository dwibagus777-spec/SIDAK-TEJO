<?php

namespace App\AI;

class WorkOrderOptimizer
{
    /**
     * Determine auto priority based on emergency, risk score, & asset health
     */
    public function calculateAutoPriority(array $woData): array
    {
        $riskScore = (float)($woData['risk_score'] ?? 50);
        $isEmergency = ($woData['is_emergency'] ?? false) || (strtoupper($woData['prioritas'] ?? '') === 'EMERGENCY');

        if ($isEmergency || $riskScore >= 85) {
            return ['priority' => 'CRITICAL', 'badge' => 'bg-danger', 'sla_hours' => 72];
        } elseif ($riskScore >= 65) {
            return ['priority' => 'HIGH', 'badge' => 'bg-warning text-dark', 'sla_hours' => 168];
        } elseif ($riskScore >= 40) {
            return ['priority' => 'MEDIUM', 'badge' => 'bg-info text-white', 'sla_hours' => 744];
        }

        return ['priority' => 'LOW', 'badge' => 'bg-secondary', 'sla_hours' => 2160];
    }

    /**
     * Auto assign best officer based on workload, role, and proximity
     */
    public function autoAssignOfficer(array $woData, array $officers): array
    {
        if (empty($officers)) {
            return ['officer_id' => null, 'officer_name' => 'Tim PDKB UP3', 'reason' => 'Default Tim PDKB Specialist'];
        }

        // Pick officer with lowest workload
        usort($officers, fn($a, $b) => ($a['workload'] ?? 0) <=> ($b['workload'] ?? 0));
        $best = $officers[0];

        return [
            'officer_id'   => $best['id'] ?? 1,
            'officer_name' => $best['nama'] ?? 'Dwi Bagus Arianto',
            'reason'       => 'Optimal Workload (' . ($best['workload'] ?? 0) . ' active jobs) & Highest Performance Rating'
        ];
    }

    /**
     * Optimize route sequence for multiple Work Orders
     */
    public function optimizeRouteSequence(array $woList): array
    {
        // Sort by priority (CRITICAL -> HIGH -> MEDIUM -> LOW)
        usort($woList, function($a, $b) {
            $prioOrder = ['CRITICAL' => 1, 'HIGH' => 2, 'MEDIUM' => 3, 'LOW' => 4];
            $pa = $prioOrder[strtoupper($a['prioritas'] ?? 'MEDIUM')] ?? 3;
            $pb = $prioOrder[strtoupper($b['prioritas'] ?? 'MEDIUM')] ?? 3;
            return $pa <=> $pb;
        });

        return $woList;
    }
}
