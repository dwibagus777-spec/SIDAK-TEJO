<?php

namespace App\Services;

/**
 * CR-FL-01 Phase FL-01B: FTU-R200 Fault Parser Service
 *
 * Strictly READ-ONLY raw-text parser.
 * Converts FTU-R200 raw telemetry text into a canonical structured measurement payload.
 *
 * Scope & Guardrails:
 * - 0 DB mutations, 0 DB queries (pure in-memory deterministic text parsing).
 * - Does NOT calculate Z = V / I.
 * - Does NOT derive distance from impedance.
 * - Does NOT locate fault or infer feeder.
 * - Only parses syntax and extracts canonical measurement values.
 */
class FtuFaultParserService
{
    public const VERSION = 'FL-01B-1.0';

    /**
     * Core fields expected in a fully specified FTU-R200 fault report.
     */
    protected const CORE_FIELDS = [
        'fault_type',
        'ia',
        'ib',
        'ic',
        'voltage',
        'distance',
        'impedance',
        'fault_time',
    ];

    /**
     * Parse raw FTU-R200 telemetry text into canonical measurement payload.
     *
     * @param string $rawText
     * @return array
     */
    public function parse(string $rawText): array
    {
        $trimmed = trim($rawText);
        if ($trimmed === '') {
            return [
                'parser_version' => self::VERSION,
                'parse_status'   => 'EMPTY_PAYLOAD',
                'fault_type'     => null,
                'ia'             => null,
                'ib'             => null,
                'ic'             => null,
                'voltage'        => null,
                'distance'       => null,
                'distance_unit'  => null,
                'impedance'      => null,
                'impedance_unit' => null,
                'fault_time'     => null,
                'timezone'       => null,
                'date'           => null,
                'parsed_fields'  => [],
                'parse_errors'   => ['Payload is empty or contains only whitespace.'],
                'raw_payload'    => $rawText,
            ];
        }

        // Header pattern matching supported FTU fields
        $pattern = '/(?:^|[\r\n\s,;])(FAULT(?:\s+TYPE)?|TIPE\s+GANGGUAN|IA|I_A|IB|I_B|IC|I_C|VOLTAGE|VOLT|V|DISTANCE|DIST|JARAK|IMPEDANCE|IMPEDANSI|IMP|Z|TIME|TIMESTAMP|WAKTU|JAM)\b\s*[:=\s]\s*/i';

        if (!preg_match_all($pattern, $trimmed, $matches, PREG_OFFSET_CAPTURE) || empty($matches[0])) {
            return [
                'parser_version' => self::VERSION,
                'parse_status'   => 'UNRECOGNIZED_FORMAT',
                'fault_type'     => null,
                'ia'             => null,
                'ib'             => null,
                'ic'             => null,
                'voltage'        => null,
                'distance'       => null,
                'distance_unit'  => null,
                'impedance'      => null,
                'impedance_unit' => null,
                'fault_time'     => null,
                'timezone'       => null,
                'date'           => null,
                'parsed_fields'  => [],
                'parse_errors'   => ['No recognizable FTU fields found in raw text.'],
                'raw_payload'    => $rawText,
            ];
        }

        $rawFields = [];
        $errors = [];
        $count = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $header = $matches[1][$i][0];
            $key = $this->canonicalKey($header);
            $start = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $nextHeaderStart = ($i + 1 < $count) ? $matches[0][$i + 1][1] : strlen($trimmed);

            // Bounded by either next header or newline, preventing bleed into subsequent lines
            $newlinePos = strpos($trimmed, "\n", $start);
            if ($newlinePos === false) {
                $newlinePos = strlen($trimmed);
            }

            $end = min($nextHeaderStart, $newlinePos);
            $val = trim(substr($trimmed, $start, $end - $start), " \t\r\n,;");

            if (!isset($rawFields[$key])) {
                $rawFields[$key] = [];
            }
            $rawFields[$key][] = $val;
        }

        // Duplicate and conflict detection
        $dedupedFields = [];
        foreach ($rawFields as $key => $values) {
            $first = $values[0];
            $hasConflict = false;
            for ($k = 1; $k < count($values); $k++) {
                if ($this->normalizeComparisonString($values[$k]) !== $this->normalizeComparisonString($first)) {
                    $errors[] = "CONFLICTING_FIELD: Conflicting duplicate values for '{$key}' ('{$first}' vs '{$values[$k]}')";
                    $hasConflict = true;
                    break;
                }
            }
            if (!$hasConflict) {
                $dedupedFields[$key] = $first;
            }
        }

        $parsedFields = [];
        $faultType = null;
        $ia = null;
        $ib = null;
        $ic = null;
        $voltage = null;
        $distance = null;
        $distanceUnit = null;
        $impedance = null;
        $impedanceUnit = null;
        $faultTime = null;
        $timezone = null;
        $date = null;

        // 1. FAULT TYPE
        if (isset($dedupedFields['fault_type'])) {
            $res = $this->parseFaultType($dedupedFields['fault_type']);
            if (isset($res['error'])) {
                $errors[] = $res['error'];
            } else {
                $faultType = $res['fault_type'];
                $parsedFields[] = 'fault_type';
            }
        } else {
            $errors[] = "MISSING_FIELD: fault_type is absent";
        }

        // 2. Currents (Ia, Ib, Ic)
        foreach (['ia', 'ib', 'ic'] as $cKey) {
            if (isset($dedupedFields[$cKey])) {
                $res = $this->parseCurrent($dedupedFields[$cKey], $cKey);
                if (isset($res['error'])) {
                    $errors[] = $res['error'];
                } else {
                    $$cKey = $res['value'];
                    $parsedFields[] = $cKey;
                }
            } else {
                $errors[] = "MISSING_FIELD: {$cKey} is absent";
            }
        }

        // 3. Voltage (V)
        if (isset($dedupedFields['voltage'])) {
            $res = $this->parseVoltage($dedupedFields['voltage']);
            if (isset($res['error'])) {
                $errors[] = $res['error'];
            } else {
                $voltage = $res['value'];
                $parsedFields[] = 'voltage';
            }
        } else {
            $errors[] = "MISSING_FIELD: voltage is absent";
        }

        // 4. Distance
        if (isset($dedupedFields['distance'])) {
            $res = $this->parseDistance($dedupedFields['distance']);
            if (isset($res['error'])) {
                $errors[] = $res['error'];
            } else {
                $distance = $res['distance'];
                $distanceUnit = $res['distance_unit'];
                $parsedFields[] = 'distance';
            }
        } else {
            $errors[] = "MISSING_FIELD: distance is absent";
        }

        // 5. Impedance
        if (isset($dedupedFields['impedance'])) {
            $res = $this->parseImpedance($dedupedFields['impedance']);
            if (isset($res['error'])) {
                $errors[] = $res['error'];
            } else {
                $impedance = $res['impedance'];
                $impedanceUnit = $res['impedance_unit'];
                $parsedFields[] = 'impedance';
            }
        } else {
            $errors[] = "MISSING_FIELD: impedance is absent";
        }

        // 6. Fault Time
        if (isset($dedupedFields['fault_time'])) {
            $res = $this->parseTime($dedupedFields['fault_time']);
            if (isset($res['error'])) {
                $errors[] = $res['error'];
            } else {
                $faultTime = $res['fault_time'];
                $timezone = $res['timezone'];
                $date = $res['date'];
                $parsedFields[] = 'fault_time';
            }
        } else {
            $errors[] = "MISSING_FIELD: fault_time is absent";
        }

        // Determine Final Status
        $hasSyntaxErrors = false;
        foreach ($errors as $err) {
            if (!str_starts_with($err, 'MISSING_FIELD')) {
                $hasSyntaxErrors = true;
                break;
            }
        }

        if ($hasSyntaxErrors) {
            $parseStatus = 'MALFORMED_SYNTAX';
        } elseif (count($parsedFields) === count(self::CORE_FIELDS)) {
            $parseStatus = 'SUCCESS';
            $errors = []; // Clear missing field notices on complete success
        } else {
            $parseStatus = 'PARTIAL';
        }

        return [
            'parser_version' => self::VERSION,
            'parse_status'   => $parseStatus,
            'fault_type'     => $faultType,
            'ia'             => $ia,
            'ib'             => $ib,
            'ic'             => $ic,
            'voltage'        => $voltage,
            'distance'       => $distance,
            'distance_unit'  => $distanceUnit,
            'impedance'      => $impedance,
            'impedance_unit' => $impedanceUnit,
            'fault_time'     => $faultTime,
            'timezone'       => $timezone,
            'date'           => $date,
            'parsed_fields'  => $parsedFields,
            'parse_errors'   => $errors,
            'raw_payload'    => $rawText,
        ];
    }

    /**
     * Map header variant to canonical field key.
     */
    protected function canonicalKey(string $header): string
    {
        $h = strtoupper(trim($header));
        if (in_array($h, ['FAULT', 'FAULT TYPE', 'FAULT_TYPE', 'TIPE GANGGUAN', 'GANGGUAN'], true)) return 'fault_type';
        if (in_array($h, ['IA', 'I_A', 'I A', 'ARUS A'], true)) return 'ia';
        if (in_array($h, ['IB', 'I_B', 'I B', 'ARUS B'], true)) return 'ib';
        if (in_array($h, ['IC', 'I_C', 'I C', 'ARUS C'], true)) return 'ic';
        if (in_array($h, ['V', 'VOLT', 'VOLTAGE', 'TEGANGAN'], true)) return 'voltage';
        if (in_array($h, ['DIST', 'DISTANCE', 'JARAK'], true)) return 'distance';
        if (in_array($h, ['Z', 'IMP', 'IMPEDANCE', 'IMPEDANSI'], true)) return 'impedance';
        if (in_array($h, ['TIME', 'TIMESTAMP', 'WAKTU', 'JAM'], true)) return 'fault_time';
        return strtolower($h);
    }

    /**
     * Normalize strings for comparison (whitespace and comma agnostic).
     */
    protected function normalizeComparisonString(string $val): string
    {
        return str_replace([' ', "\t", "\r", "\n", ','], ['', '', '', '', '.'], strtolower(trim($val)));
    }

    /**
     * Parse fault type faithfully.
     */
    protected function parseFaultType(string $val): array
    {
        $t = trim($val);
        if ($t === '') {
            return ['error' => 'MISSING_VALUE: Empty value for fault_type'];
        }
        if (!preg_match('/^[A-Za-z0-9\-\/]+$/', $t)) {
            return ['error' => "MALFORMED_FAULT_TYPE: Invalid characters in fault_type '{$val}'"];
        }
        return ['fault_type' => strtoupper($t)];
    }

    /**
     * Parse numeric string and attached/separated unit.
     */
    protected function parseNumericAndUnit(string $val): array
    {
        $trimmed = trim($val);
        if (preg_match('/^([-+]?[0-9]+(?:[.,][0-9]+)?)\s*([^\s0-9.,].*)?$/u', $trimmed, $m)) {
            $numStr = str_replace(',', '.', $m[1]);
            $unit = isset($m[2]) ? trim($m[2]) : null;
            return [
                'is_valid' => true,
                'number'   => (float)$numStr,
                'unit'     => $unit,
            ];
        }

        return [
            'is_valid' => false,
            'number'   => null,
            'unit'     => null,
            'raw'      => $trimmed,
        ];
    }

    /**
     * Parse current value (Ia, Ib, Ic) with unit normalization.
     */
    protected function parseCurrent(string $val, string $field): array
    {
        $p = $this->parseNumericAndUnit($val);
        if (!$p['is_valid']) {
            return ['error' => "MALFORMED_NUMERIC_VALUE: Invalid numeric format '{$val}' for field '{$field}'"];
        }

        $unit = $p['unit'];
        if ($unit === null || $unit === '') {
            return ['error' => "MISSING_UNIT: Current unit is required for field '{$field}' (e.g. kA or A)"];
        }

        $uUpper = strtoupper($unit);
        if ($uUpper === 'KA') {
            return ['value' => $p['number']];
        } elseif ($uUpper === 'A') {
            return ['value' => round($p['number'] / 1000.0, 4)];
        }

        return ['error' => "UNSUPPORTED_UNIT: Unit '{$unit}' is not supported for field '{$field}' (expected kA or A)"];
    }

    /**
     * Parse voltage value with unit normalization.
     */
    protected function parseVoltage(string $val): array
    {
        $p = $this->parseNumericAndUnit($val);
        if (!$p['is_valid']) {
            return ['error' => "MALFORMED_NUMERIC_VALUE: Invalid numeric format '{$val}' for field 'voltage'"];
        }

        $unit = $p['unit'];
        if ($unit === null || $unit === '') {
            return ['error' => "MISSING_UNIT: Voltage unit is required (e.g. kV or V)"];
        }

        $uUpper = strtoupper($unit);
        if ($uUpper === 'KV') {
            return ['value' => $p['number']];
        } elseif ($uUpper === 'V') {
            return ['value' => round($p['number'] / 1000.0, 4)];
        }

        return ['error' => "UNSUPPORTED_UNIT: Unit '{$unit}' is not supported for voltage (expected kV or V)"];
    }

    /**
     * Parse distance value with unit normalization.
     */
    protected function parseDistance(string $val): array
    {
        $p = $this->parseNumericAndUnit($val);
        if (!$p['is_valid']) {
            return ['error' => "MALFORMED_NUMERIC_VALUE: Invalid numeric format '{$val}' for field 'distance'"];
        }

        $unit = $p['unit'];
        if ($unit === null || $unit === '') {
            return ['error' => "MISSING_UNIT: Distance unit is required (e.g. km or m)"];
        }

        $uUpper = strtoupper($unit);
        if ($uUpper === 'KM') {
            return ['distance' => $p['number'], 'distance_unit' => 'KM'];
        } elseif ($uUpper === 'M') {
            return ['distance' => round($p['number'] / 1000.0, 4), 'distance_unit' => 'KM'];
        }

        return ['error' => "UNSUPPORTED_UNIT: Unit '{$unit}' is not supported for distance (expected km or m)"];
    }

    /**
     * Parse impedance value with unit normalization.
     */
    protected function parseImpedance(string $val): array
    {
        $p = $this->parseNumericAndUnit($val);
        if (!$p['is_valid']) {
            return ['error' => "MALFORMED_NUMERIC_VALUE: Invalid numeric format '{$val}' for field 'impedance'"];
        }

        $unit = $p['unit'];
        if ($unit === null || $unit === '') {
            return ['error' => "MISSING_UNIT: Impedance unit is required (e.g. Ω or ohm)"];
        }

        $uClean = strtolower($unit);
        if ($unit === 'Ω' || in_array($uClean, ['ohm', 'ohms'], true)) {
            return ['impedance' => $p['number'], 'impedance_unit' => 'OHM'];
        }

        return ['error' => "UNSUPPORTED_UNIT: Unit '{$unit}' is not supported for impedance (expected Ω or ohm)"];
    }

    /**
     * Parse time and optional timezone / date.
     */
    protected function parseTime(string $val): array
    {
        $t = trim($val);
        $date = null;

        // Date detection (YYYY-MM-DD or YYYY/MM/DD)
        if (preg_match('/^(\d{4}[-\/]\d{2}[-\/]\d{2})\s+(.+)$/', $t, $dm)) {
            $date = str_replace('/', '-', $dm[1]);
            $t = trim($dm[2]);
        }

        // Time pattern: HH:MM or HH:MM:SS with optional timezone
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\s+([A-Za-z0-9\+\:\-]+))?$/', $t, $m)) {
            $h = (int)$m[1];
            $min = (int)$m[2];
            $sec = isset($m[3]) && $m[3] !== '' ? (int)$m[3] : null;
            $tz = isset($m[4]) && $m[4] !== '' ? strtoupper(trim($m[4])) : null;

            if ($h < 0 || $h > 23 || $min < 0 || $min > 59 || ($sec !== null && ($sec < 0 || $sec > 59))) {
                return ['error' => "MALFORMED_TIME: Out-of-range time values in '{$val}'"];
            }

            $formatted = sprintf('%02d:%02d', $h, $min);
            if ($sec !== null) {
                $formatted .= sprintf(':%02d', $sec);
            }

            return [
                'fault_time' => $formatted,
                'timezone'   => $tz,
                'date'       => $date,
            ];
        }

        return ['error' => "MALFORMED_TIME: Cannot parse time from '{$val}'"];
    }
}
