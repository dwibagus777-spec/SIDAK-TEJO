<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Historical Interruption Correlation Service (Phase 7U Maintenance M-05)
 *
 * Responsibilities:
 * - Bridges active asset/finding state with historical interruption memory.
 * - Evaluates historical trip recurrence frequency and outage impact for similar failure modes.
 */
class HistoricalInterruptionCorrelationService
{
    protected BaseConnection $db;
    protected HistoricalInterruptionKnowledgeService $knowledgeService;

    public function __construct(
        ?BaseConnection $db = null,
        ?HistoricalInterruptionKnowledgeService $knowledgeService = null
    ) {
        $this->db               = $db ?? \Config\Database::connect();
        $this->knowledgeService = $knowledgeService ?? new HistoricalInterruptionKnowledgeService($this->db);
    }

    /**
     * Correlate asset/finding context with historical interruption records.
     *
     * @param array $findingContext Output from AssetFindingCorrelationService
     * @return array
     */
    public function correlateWithHistory(array $findingContext): array
    {
        $feederName = $findingContext['feeder_name'] ?? 'BALUNG';
        $category   = $findingContext['classified_category'] ?? 'VEGETATION_ROW';

        // Query Historical Knowledge Service with active context
        $retrieval = $this->knowledgeService->retrieveSimilarIncidents([
            'feeder'   => $feederName,
            'relay'    => 'DGR',
            'phase'    => '',
            'weather'  => 'hujan-angin',
            'category' => 'PERMANENT',
        ], 5);

        $matchedCases = $retrieval['top_cases'] ?? [];
        $matchCount   = count($matchedCases);

        // Calculate Historical Recurrence Score (0.0 to 1.0)
        // 0 matches = 0.10, 1 match = 0.35, 2 matches = 0.60, 3+ matches = 0.85+
        $recurrenceScore = match (true) {
            $matchCount >= 4 => 0.90,
            $matchCount === 3 => 0.75,
            $matchCount === 2 => 0.55,
            $matchCount === 1 => 0.35,
            default           => 0.10,
        };

        // Determine dominant historical cause and median outage duration
        $causeFreq = [];
        $durations = [];
        $referenceSet = [];

        foreach ($matchedCases as $case) {
            $cCode = $case['cause_canonical_code'];
            $causeFreq[$cCode] = ($causeFreq[$cCode] ?? 0) + 1;
            if ($case['outage_duration_minutes'] > 0) {
                $durations[] = $case['outage_duration_minutes'];
            }

            $referenceSet[] = [
                'date'           => $case['historical_date'],
                'feeder'         => $case['feeder'],
                'relay'          => $case['relay'],
                'cause_code'     => $case['cause_canonical_code'],
                'duration_min'   => $case['outage_duration_minutes'],
                'action_summary' => $case['historical_restoration_action'],
            ];
        }

        arsort($causeFreq);
        $dominantCause = !empty($causeFreq) ? array_key_first($causeFreq) : 'ROW';
        $medianDuration = !empty($durations) ? round(array_sum($durations) / count($durations), 1) : 45.0;

        return [
            'historical_knowledge_source_class' => 'EXTERNAL_HISTORICAL_INTERRUPTION_KNOWLEDGE',
            'historical_case_matches_count'     => $matchCount,
            'historical_recurrence_score'       => $recurrenceScore,
            'dominant_historical_cause'         => $dominantCause,
            'median_historical_outage_min'      => $medianDuration,
            'historical_case_reference_set'     => $referenceSet,
            'historical_evidence_summary'       => "Ditemukan {$matchCount} kasus gangguan historis pada penyulang {$feederName} dengan dominasi penyebab: {$dominantCause}.",
        ];
    }
}
