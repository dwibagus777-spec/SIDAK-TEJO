<?php

namespace App\Services;

/**
 * Historical Incident Data Quality Service (Phase 7U Maintenance M-04)
 *
 * Responsibilities:
 * - Deterministic scoring of historical record completeness & hygiene.
 * - Flags unmapped causes, missing feeder references, or incomplete durations.
 */
class HistoricalIncidentDataQualityService
{
    /**
     * Compute data quality score for a single normalized interruption record.
     *
     * @param array $normalized
     * @param array $causeResolution
     * @return array [ 'score' => float, 'status' => 'VALID'|'FLAGGED', 'flags' => array ]
     */
    public function evaluateRecordQuality(array $normalized, array $causeResolution): array
    {
        $score = 0.0;
        $flags = [];

        // 1. Core Grid & Date (0.25)
        if (!empty($normalized['event_date']) && !empty($normalized['feeder_name']) && $normalized['feeder_name'] !== 'UNKNOWN_FEEDER') {
            $score += 0.25;
        } else {
            $flags[] = 'MISSING_FEEDER_OR_DATE';
        }

        // 2. Relay & Phase Protection Context (0.20)
        if (!empty($normalized['relay_trip_type'])) {
            $score += 0.10;
        }
        if (!empty($normalized['faulted_phase'])) {
            $score += 0.10;
        }

        // 3. Duration & Fault Magnitude (0.20)
        if (($normalized['outage_duration_minutes'] ?? 0) > 0) {
            $score += 0.10;
        }
        if (($normalized['fault_current_amperes'] ?? 0) > 0) {
            $score += 0.10;
        }

        // 4. Cause Resolution Status (0.20)
        if (($causeResolution['cause_mapping_status'] ?? '') === 'RESOLVED') {
            $score += 0.20;
        } elseif (($causeResolution['cause_mapping_status'] ?? '') === 'PARTIALLY_RESOLVED') {
            $score += 0.15;
            $flags[] = 'PARTIALLY_RESOLVED_CAUSE';
        } else {
            $flags[] = 'UNMAPPED_CAUSE_CODE';
        }

        // 5. Evidence & Action Narrative (0.15)
        if (!empty($normalized['field_narrative_raw']) && strlen($normalized['field_narrative_raw']) > 5) {
            $score += 0.10;
        }
        if (!empty($normalized['restoration_action_raw'])) {
            $score += 0.05;
        }

        $roundedScore = round($score, 2);
        $status = ($roundedScore >= 0.60 && empty(array_intersect($flags, ['MISSING_FEEDER_OR_DATE', 'UNMAPPED_CAUSE_CODE']))) ? 'VALID' : 'FLAGGED';

        return [
            'data_quality_score' => $roundedScore,
            'ingestion_status'   => $status,
            'flags'              => $flags,
        ];
    }
}
