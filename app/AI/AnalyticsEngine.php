<?php

namespace App\AI;

class AnalyticsEngine
{
    /**
     * Anomaly Detection (Repeated findings, recurring WOs, overloaded officers)
     */
    public function detectAnomalies(array $temuanList, array $woList): array
    {
        $anomalies = [];

        // 1. Repeated Findings on same section/penyulang
        $sectionCounts = [];
        foreach ($temuanList as $t) {
            $secName = $t['nama_section'] ?? 'Unassigned';
            $sectionCounts[$secName] = ($sectionCounts[$secName] ?? 0) + 1;
        }

        foreach ($sectionCounts as $sec => $count) {
            if ($count >= 5) {
                $anomalies[] = [
                    'type'     => 'REPEATED_SECTION_FINDINGS',
                    'title'    => 'Anomali Temuan Berulang di Section ' . $sec,
                    'detail'   => 'Terdeteksi ' . $count . ' temuan pada section yang sama.',
                    'severity' => 'HIGH',
                ];
            }
        }

        // 2. Overloaded Officer Detection
        $officerWoCounts = [];
        foreach ($woList as $w) {
            if (!empty($w['assigned_to']) && $w['status'] !== 'COMPLETED') {
                $off = $w['assigned_to'];
                $officerWoCounts[$off] = ($officerWoCounts[$off] ?? 0) + 1;
            }
        }

        foreach ($officerWoCounts as $officer => $count) {
            if ($count >= 4) {
                $anomalies[] = [
                    'type'     => 'OFFICER_OVERLOAD',
                    'title'    => 'Anomali Beban Kerja Petugas (' . $officer . ')',
                    'detail'   => 'Petugas menangani ' . $count . ' Work Order aktif bersamaan.',
                    'severity' => 'MEDIUM',
                ];
            }
        }

        return $anomalies;
    }
}
