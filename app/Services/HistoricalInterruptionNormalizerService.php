<?php

namespace App\Services;

/**
 * Historical Interruption Normalizer Service (Phase 7U Maintenance M-04)
 *
 * Responsibilities:
 * - Deterministic normalization of dates, times, durations, amperes, and kWh.
 * - Regex extraction of zone/section, distance, and asset references from field narratives.
 */
class HistoricalInterruptionNormalizerService
{
    /**
     * Normalize a raw CSV/Spreadsheet row into structured canonical attributes.
     *
     * @param array $rawRow Associative or indexed row array
     * @return array
     */
    public function normalizeRow(array $rawRow): array
    {
        // 1. Event Date Normalization
        $eventDate = $this->parseDate($rawRow['tanggal'] ?? $rawRow[1] ?? date('Y-m-d'));

        // 2. Start & End Times
        $jamLepas = $this->parseTime($rawRow['JAM PMT Lepas'] ?? $rawRow[12] ?? null);
        $jamMasuk = $this->parseTime($rawRow['JAM PMT Masuk'] ?? $rawRow[14] ?? null);

        $startedAt = $jamLepas ? "{$eventDate} {$jamLepas}" : "{$eventDate} 00:00:00";
        $endedAt   = $jamMasuk ? "{$eventDate} {$jamMasuk}" : null;

        // 3. Duration in Minutes
        $rawDuration = $rawRow['LAMA PADAM'] ?? $rawRow[16] ?? null;
        $durationMinutes = $this->parseDurationMinutes($rawDuration, $startedAt, $endedAt);

        // 4. Amperes & ENS kWh
        $amperes = $this->parseNumeric($rawRow['( AMPER )'] ?? $rawRow[17] ?? null);
        $ensKwh  = $this->parseKwh($rawRow['( Kwh )'] ?? $rawRow[18] ?? null);

        // 5. Category (Temporary vs Permanent)
        $rawCategory = strtoupper(trim((string)($rawRow['KATEGORI'] ?? $rawRow[19] ?? 'TEMPORER')));
        $category = (str_contains($rawCategory, 'PERMANEN')) ? 'PERMANENT' : 'TEMPORARY';

        // 6. Narrative and Action
        $narrative = trim((string)($rawRow['KETERANGAN'] ?? $rawRow[26] ?? ''));
        $action    = trim((string)($rawRow['TINDAK LANJUT'] ?? $rawRow[27] ?? ''));

        // 7. Regex Extraction of Zone/Section, Distance, and Asset References
        $extractedSection  = $this->extractSection($narrative);
        $extractedDistance = $this->extractDistanceKms($narrative);
        $extractedAsset    = $this->extractAssetRef($narrative);

        return [
            'event_date'              => $eventDate,
            'interruption_started_at' => $startedAt,
            'interruption_ended_at'   => $endedAt,
            'outage_duration_minutes' => $durationMinutes,
            'substation_name'         => strtoupper(trim((string)($rawRow['GARDU INDUK'] ?? $rawRow[5] ?? 'UNKNOWN_GI'))),
            'ulp_name'                => strtoupper(trim((string)($rawRow['ULP'] ?? $rawRow[6] ?? 'UNKNOWN_ULP'))),
            'feeder_name'             => strtoupper(trim((string)($rawRow['PENYULANG'] ?? $rawRow[8] ?? 'UNKNOWN_FEEDER'))),
            'switching_device_type'   => strtoupper(trim((string)($rawRow['PMT-RECL/PMCB'] ?? $rawRow[7] ?? 'RECL-PMCB'))),
            'device_name'             => trim((string)($rawRow['RECLOSER'] ?? $rawRow[10] ?? '')),
            'relay_trip_type'         => strtoupper(trim((string)($rawRow['RELE KERJA'] ?? $rawRow[20] ?? 'DGR'))),
            'faulted_phase'           => strtoupper(trim((string)($rawRow['fasa'] ?? $rawRow[21] ?? ''))),
            'weather_condition'       => strtolower(trim((string)($rawRow['cuaca'] ?? $rawRow[22] ?? 'cerah'))),
            'fault_current_amperes'   => $amperes,
            'energy_not_supplied_kwh' => $ensKwh,
            'interruption_category'   => $category,
            'interruption_group'      => trim((string)($rawRow['KELOMPOK GGN'] ?? $rawRow[24] ?? 'GGN_PENYULANG')),
            'field_narrative_raw'     => $narrative,
            'restoration_action_raw'  => $action,
            'extracted_zone_section'  => $extractedSection,
            'extracted_distance_kms'  => $extractedDistance,
            'asset_reference_raw'     => $extractedAsset,
        ];
    }

    /**
     * Parse varied date formats e.g. "01-Jan-25", "02-Feb-26", "2025-01-01", "1 Jan 2025"
     */
    protected function parseDate(?string $rawDate): string
    {
        if (!$rawDate) {
            return date('Y-m-d');
        }

        $rawDate = trim($rawDate);
        $monthMap = [
            'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
            'may' => '05', 'mei' => '05', 'jun' => '06', 'jul' => '07',
            'aug' => '08', 'agt' => '08', 'sep' => '09', 'oct' => '10',
            'okt' => '10', 'nov' => '11', 'dec' => '12', 'des' => '12'
        ];

        // Format: "01-Jan-25" or "02-Feb-26"
        if (preg_match('/^(\d{1,2})[-\s]([a-zA-Z]{3})[-\s](\d{2,4})$/i', $rawDate, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $monKey = strtolower(substr($m[2], 0, 3));
            $month = $monthMap[$monKey] ?? '01';
            $year = (strlen($m[3]) === 2) ? '20' . $m[3] : $m[3];
            return "{$year}-{$month}-{$day}";
        }

        $ts = strtotime($rawDate);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    /**
     * Parse time strings e.g. "14.52", "14:19", "0.28", "00.04"
     */
    protected function parseTime(?string $rawTime): ?string
    {
        if (!$rawTime) {
            return null;
        }

        $clean = trim(str_replace('.', ':', $rawTime));
        if (preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', $clean, $m)) {
            $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $i = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $s = isset($m[4]) ? str_pad($m[4], 2, '0', STR_PAD_LEFT) : '00';
            return "{$h}:{$i}:{$s}";
        }

        return null;
    }

    /**
     * Parse duration string e.g. "0.04.00", "1.28.00", "0:02:00", "0:01:45"
     */
    protected function parseDurationMinutes(?string $rawDuration, string $start, ?string $end): float
    {
        if ($rawDuration) {
            $clean = trim(str_replace('.', ':', $rawDuration));
            $parts = explode(':', $clean);

            if (count($parts) >= 2) {
                $hours = (float)($parts[0] ?? 0);
                $mins  = (float)($parts[1] ?? 0);
                $secs  = (float)($parts[2] ?? 0);
                $total = ($hours * 60.0) + $mins + ($secs / 60.0);
                if ($total > 0) {
                    return round($total, 2);
                }
            }
        }

        if ($end && $start) {
            $diff = strtotime($end) - strtotime($start);
            if ($diff > 0) {
                return round($diff / 60.0, 2);
            }
        }

        return 2.0; // Fallback typical temporer duration
    }

    /**
     * Clean numeric amperes
     */
    protected function parseNumeric($val): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }
        $cleaned = str_replace([' ', ','], ['', '.'], (string)$val);
        return is_numeric($cleaned) ? (float)$cleaned : null;
    }

    /**
     * Clean kWh number (handles "304,264", "2763,891", "461.303", "1.987.528")
     */
    protected function parseKwh($val): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }
        $str = trim((string)$val, " \t\n\r\0\x0B\"'");
        
        // Check if thousand separator is dot and decimal is comma e.g. "1.987.528"
        if (substr_count($str, '.') >= 2) {
            $str = str_replace('.', '', $str);
        }
        // Replace Indonesian decimal comma with standard dot
        $str = str_replace(',', '.', $str);
        return is_numeric($str) ? round((float)$str, 3) : null;
    }

    /**
     * Extract Zona / Section from narrative (e.g. "Zona 1 Section 1", "zona 2 sec 3")
     */
    protected function extractSection(string $narrative): ?string
    {
        if (preg_match('/(zona\s*\d+\s*(?:section|sec|sect)?\s*\d+)/i', $narrative, $m)) {
            return strtoupper(trim($m[1]));
        }
        return null;
    }

    /**
     * Extract distance in km from narrative (e.g. "Jarak 3kms", "4.5 kms", "KMS 10")
     */
    protected function extractDistanceKms(string $narrative): ?float
    {
        if (preg_match('/(?:jarak|panjang)?\s*[:=]?\s*(\d+(?:[,\.]\d+)?)\s*kms?/i', $narrative, $m)) {
            return (float)str_replace(',', '.', $m[1]);
        }
        return null;
    }

    /**
     * Extract Asset Reference code from narrative (e.g. "PA 1003", "PB 76", "GTT PC 328", "LBSM Kalimas", "TM 10")
     */
    protected function extractAssetRef(string $narrative): ?string
    {
        if (preg_match('/\b(PA[\s\.\-]?\d+|PB[\s\.\-]?\d+|PC[\s\.\-]?\d+|GTT[\s\.\-]?\w+|LBSM?[\s\.\-]?\w+|TM[\s\.\-]?\d+)\b/i', $narrative, $m)) {
            return strtoupper(trim($m[1]));
        }
        return null;
    }
}
