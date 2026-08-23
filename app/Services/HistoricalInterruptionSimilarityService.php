<?php

namespace App\Services;

/**
 * Historical Interruption Similarity Service (Phase 7U Maintenance M-04)
 *
 * Responsibilities:
 * - Deterministic multi-dimensional similarity vector computation:
 *     Similarity = w_feeder * match_feeder
 *                + w_relay * match_relay
 *                + w_phase * match_phase
 *                + w_weather * match_weather
 *                + w_current * match_current
 *                + w_category * match_category
 * - Enforces Governed Invariant: SIMILARITY_SCORE != DIAGNOSTIC_CERTAINTY
 */
class HistoricalInterruptionSimilarityService
{
    protected array $weights = [
        'feeder'   => 0.35,
        'relay'    => 0.25,
        'phase'    => 0.15,
        'weather'  => 0.10,
        'current'  => 0.10,
        'category' => 0.05,
    ];

    /**
     * Compute similarity score between active incident context and a historical record.
     *
     * @param array $activeContext [ 'feeder' => ..., 'relay' => ..., 'phase' => ..., 'weather' => ..., 'current_amperes' => ..., 'category' => ... ]
     * @param array $historicalRow
     * @return float Score between 0.00 and 1.00
     */
    public function computeSimilarity(array $activeContext, array $historicalRow): float
    {
        $score = 0.0;

        // 1. Feeder Match (0.35)
        $actFeeder  = strtoupper(trim($activeContext['feeder'] ?? ''));
        $histFeeder = strtoupper(trim($historicalRow['feeder_name'] ?? $historicalRow['PENYULANG'] ?? ''));
        if ($actFeeder !== '' && $histFeeder !== '' && $actFeeder === $histFeeder) {
            $score += $this->weights['feeder'];
        }

        // 2. Relay Match (0.25)
        $actRelay  = strtoupper(trim($activeContext['relay'] ?? ''));
        $histRelay = strtoupper(trim($historicalRow['relay_trip_type'] ?? $historicalRow['RELE KERJA'] ?? ''));
        if ($actRelay !== '' && $histRelay !== '') {
            if ($actRelay === $histRelay) {
                $score += $this->weights['relay'];
            } elseif (str_contains($histRelay, $actRelay) || str_contains($actRelay, $histRelay)) {
                $score += ($this->weights['relay'] * 0.70);
            }
        }

        // 3. Phase Match (0.15)
        $actPhase  = strtoupper(trim($activeContext['phase'] ?? ''));
        $histPhase = strtoupper(trim($historicalRow['faulted_phase'] ?? $historicalRow['fasa'] ?? ''));
        if ($actPhase !== '' && $histPhase !== '') {
            if ($actPhase === $histPhase) {
                $score += $this->weights['phase'];
            } elseif (str_contains($histPhase, $actPhase) || str_contains($actPhase, $histPhase)) {
                $score += ($this->weights['phase'] * 0.60);
            }
        }

        // 4. Weather Match (0.10)
        $actWeather  = strtolower(trim($activeContext['weather'] ?? ''));
        $histWeather = strtolower(trim($historicalRow['weather_condition'] ?? $historicalRow['cuaca'] ?? ''));
        if ($actWeather !== '' && $histWeather !== '') {
            if ($actWeather === $histWeather) {
                $score += $this->weights['weather'];
            } elseif (str_contains($histWeather, $actWeather) || str_contains($actWeather, $histWeather)) {
                $score += ($this->weights['weather'] * 0.80);
            }
        }

        // 5. Fault Current Proximity (0.10)
        $actAmps  = (float)($activeContext['current_amperes'] ?? 0);
        $histAmps = (float)($historicalRow['fault_current_amperes'] ?? $historicalRow['( AMPER )'] ?? 0);
        if ($actAmps > 0 && $histAmps > 0) {
            $diffRatio = abs($actAmps - $histAmps) / max($actAmps, $histAmps);
            if ($diffRatio <= 0.20) {
                $score += $this->weights['current'];
            } elseif ($diffRatio <= 0.50) {
                $score += ($this->weights['current'] * 0.50);
            }
        }

        // 6. Category Match (0.05)
        $actCat  = strtoupper(trim($activeContext['category'] ?? ''));
        $histCat = strtoupper(trim($historicalRow['interruption_category'] ?? $historicalRow['KATEGORI'] ?? ''));
        if ($actCat !== '' && $histCat !== '') {
            if (str_contains($histCat, $actCat) || str_contains($actCat, $histCat)) {
                $score += $this->weights['category'];
            }
        }

        return round($score, 2);
    }
}
