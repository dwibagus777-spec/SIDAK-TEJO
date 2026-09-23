<?php

namespace App\Services;

/**
 * CR-FL-01 Phase FL-01C: FTU-R200 Measurement & Physical Range Validator Service
 *
 * Deterministic, strictly READ-ONLY engineering measurement validator.
 * Bridges FL-01B (raw parser) to downstream engines (FL-01D/E, currently locked).
 *
 * Invariants:
 * 1. ZERO business database access (0 SELECT, 0 INSERT, 0 UPDATE, 0 DELETE).
 * 2. Does NOT create or reference fault_records table.
 * 3. Does NOT access or mutate assets, gis_translines, penyulang, sections, temuan.
 * 4. FTU-reported impedance Z is strictly authoritative (NEVER replaced by Z = V / I).
 * 5. Does NOT calculate distance along topology or pinpoint network coordinates.
 * 6. Does NOT hard-code universal 300A / 30A protection pickup (marks PROTECTION_PICKUP_UNKNOWN if unspecified).
 * 7. Strictly separates 5 statuses: VALID, PARTIAL, INVALID, DIAGNOSTIC_ONLY, UNRESOLVED.
 * 8. Returns independent explicit flags: is_fault_event, is_locatable, is_engineering_plausible, is_complete.
 * 9. Deterministic SHA-256 fingerprint reproducible across repeated runs.
 */
class FtuMeasurementValidatorService
{
    public const VERSION = 'FL-01C-1.0';

    // Engineering Plausibility Bounds (PLN MV 20 kV Distribution Standard)
    public const VOLTAGE_TYPICAL_MAX_KV = 24.0;
    public const VOLTAGE_PHYSICAL_CUTOFF_KV = 30.0;

    public const CURRENT_TYPICAL_MAX_KA = 12.5;
    public const CURRENT_PHYSICAL_CUTOFF_KA = 25.0;

    public const IMPEDANCE_TYPICAL_MAX_OHM = 100.0;
    public const IMPEDANCE_PHYSICAL_CUTOFF_OHM = 250.0;

    public const DISTANCE_TYPICAL_MAX_KM = 35.0;
    public const DISTANCE_PHYSICAL_CUTOFF_KM = 100.0;

    protected FtuFaultParserService $parser;

    public function __construct(?FtuFaultParserService $parser = null)
    {
        $this->parser = $parser ?? new FtuFaultParserService();
    }

    /**
     * Validate an FTU-R200 measurement payload.
     *
     * @param array|string $input Raw telemetry text or parsed telemetry array from FL-01B
     * @param array $context Optional protection settings e.g. ['ocr_pickup_amps' => ..., 'gfr_pickup_amps' => ...]
     * @return array Canonical validation report
     */
    public function validate(array|string $input, array $context = []): array
    {
        // 1. Parser Integration Firewall
        if (is_string($input)) {
            $parsed = $this->parser->parse($input);
        } else {
            $parsed = $input;
        }

        // 2. Syntax & Parser Error Resolution Firewall
        $parseStatus = $parsed['parse_status'] ?? '';
        $parseErrors = $parsed['parse_errors'] ?? [];

        if (
            empty($parsed) ||
            $parseStatus === 'EMPTY_PAYLOAD' ||
            $parseStatus === 'UNRECOGNIZED_FORMAT' ||
            $parseStatus === 'MALFORMED_SYNTAX' ||
            $this->hasConflictingField($parseErrors)
        ) {
            return $this->buildUnresolvedResult(
                $parsed,
                ['Parser rejected or payload corrupted (unrecognized/empty/conflicting syntax)']
            );
        }

        // 3. Extract and Normalize Engineering Metrics
        $metrics = $this->extractAndNormalizeMetrics($parsed);

        // 4. Check for Diagnostic Telemetry (Non-fault Events)
        if ($this->isDiagnosticEvent($parsed, $metrics)) {
            return $this->buildDiagnosticResult($parsed, $metrics);
        }

        // 5. Check if all current channels are missing
        if ($metrics['ia'] === null && $metrics['ib'] === null && $metrics['ic'] === null) {
            return $this->buildUnresolvedResult(
                $parsed,
                ['Missing all current channels (Ia, Ib, Ic); insufficient evidence to establish fault event']
            );
        }

        // 6. Evaluate Physical Bounds & Diagnostic Bands
        $bands = $this->evaluateDiagnosticBands($metrics);

        // 7. Evaluate Fault Type & Current Coherence
        $coherence = $this->evaluateCoherence($metrics['fault_type'], $metrics);

        // 8. Determine Final Status, Flags, and Rejection Codes
        return $this->determineStatusAndAssembleResult($parsed, $metrics, $bands, $coherence, $context);
    }

    /**
     * Extract and normalize metrics to standard engineering units:
     * Current in kA, Voltage in kV, Impedance in Ohm, Distance in km.
     */
    protected function extractAndNormalizeMetrics(array $parsed): array
    {
        $faultType = isset($parsed['fault_type']) ? strtoupper(trim((string)$parsed['fault_type'])) : null;

        // Current normalization (to kA)
        $ia = $this->normalizeCurrentValue($parsed['ia'] ?? null);
        $ib = $this->normalizeCurrentValue($parsed['ib'] ?? null);
        $ic = $this->normalizeCurrentValue($parsed['ic'] ?? null);

        // Voltage normalization (to kV)
        $voltage = $this->normalizeVoltageValue($parsed['voltage'] ?? null);

        // Distance normalization (to km)
        $distance = null;
        if (isset($parsed['distance']) && $parsed['distance'] !== null && is_numeric($parsed['distance'])) {
            $d = (float)$parsed['distance'];
            $unit = strtoupper(trim((string)($parsed['distance_unit'] ?? 'KM')));
            if ($unit === 'M' || ($d > 1000.0 && $unit !== 'KM')) {
                $distance = round($d / 1000.0, 4);
            } else {
                $distance = $d;
            }
        }

        // Impedance normalization (to Ohm) - Authoritative FTU value
        $impedance = null;
        if (isset($parsed['impedance']) && $parsed['impedance'] !== null && is_numeric($parsed['impedance'])) {
            $impedance = (float)$parsed['impedance'];
        }

        return [
            'fault_type'      => $faultType,
            'ia'              => $ia,
            'ib'              => $ib,
            'ic'              => $ic,
            'voltage'         => $voltage,
            'distance'        => $distance,
            'impedance'       => $impedance,
            'fault_time'      => $parsed['fault_time'] ?? null,
            'timezone'        => $parsed['timezone'] ?? null,
            'date'            => $parsed['date'] ?? null,
        ];
    }

    /**
     * Normalize current to kA.
     */
    protected function normalizeCurrentValue($val): ?float
    {
        if ($val === null || !is_numeric($val)) {
            return null;
        }
        $v = (float)$val;
        // If current is > 100.0, it is almost certainly supplied in Amperes (e.g. 500A = 0.5kA, 26000A = 26kA)
        if ($v > 100.0) {
            return round($v / 1000.0, 4);
        }
        return $v;
    }

    /**
     * Normalize voltage to kV.
     */
    protected function normalizeVoltageValue($val): ?float
    {
        if ($val === null || !is_numeric($val)) {
            return null;
        }
        $v = (float)$val;
        // If voltage is > 100.0, it is supplied in Volts (e.g. 20000V = 20kV, 31000V = 31kV)
        if ($v > 100.0) {
            return round($v / 1000.0, 4);
        }
        return $v;
    }

    /**
     * Evaluate diagnostic bands and physical violations across all parameters.
     */
    protected function evaluateDiagnosticBands(array $metrics): array
    {
        $bands = [
            'voltage_band'       => 'NOT_REPORTED',
            'current_bands'      => [],
            'impedance_band'     => 'NOT_REPORTED',
            'distance_band'      => 'NOT_REPORTED',
            'physical_violations'=> [],
        ];

        // 1. Voltage Bands
        if ($metrics['voltage'] !== null) {
            $v = $metrics['voltage'];
            if ($v < 0.0) {
                $bands['voltage_band'] = 'INVALID';
                $bands['physical_violations'][] = 'VOLTAGE_NEGATIVE';
            } elseif ($v > self::VOLTAGE_PHYSICAL_CUTOFF_KV) {
                $bands['voltage_band'] = 'INVALID_PHYSICAL_BOUND';
                $bands['physical_violations'][] = 'VOLTAGE_EXCEEDS_MAX_MV';
            } elseif ($v > self::VOLTAGE_TYPICAL_MAX_KV) {
                $bands['voltage_band'] = 'OUTSIDE_TYPICAL / DIAGNOSTIC';
            } else {
                $bands['voltage_band'] = 'PLAUSIBLE / TYPICAL BAND';
            }
        }

        // 2. Current Bands (Ia, Ib, Ic)
        foreach (['ia', 'ib', 'ic'] as $ch) {
            $val = $metrics[$ch];
            if ($val !== null) {
                if ($val < 0.0) {
                    $bands['current_bands'][$ch] = 'INVALID';
                    $bands['physical_violations'][] = 'CURRENT_NEGATIVE';
                } elseif ($val > self::CURRENT_PHYSICAL_CUTOFF_KA) {
                    $bands['current_bands'][$ch] = 'INVALID_PHYSICAL_BOUND';
                    $bands['physical_violations'][] = 'CURRENT_EXCEEDS_RATING';
                } elseif ($val > self::CURRENT_TYPICAL_MAX_KA) {
                    $bands['current_bands'][$ch] = 'HIGH / OUTSIDE_TYPICAL';
                } else {
                    $bands['current_bands'][$ch] = 'PLAUSIBLE / TYPICAL FAULT REFERENCE';
                }
            } else {
                $bands['current_bands'][$ch] = 'NOT_REPORTED';
            }
        }

        // 3. Impedance Bands
        if ($metrics['impedance'] !== null) {
            $z = $metrics['impedance'];
            if ($z <= 0.0) {
                $bands['impedance_band'] = 'INVALID';
                $bands['physical_violations'][] = 'IMPEDANCE_NON_POSITIVE';
            } elseif ($z > self::IMPEDANCE_PHYSICAL_CUTOFF_OHM) {
                $bands['impedance_band'] = 'INVALID_PHYSICAL_BOUND';
                $bands['physical_violations'][] = 'IMPEDANCE_OUT_OF_BOUNDS';
            } elseif ($z > self::IMPEDANCE_TYPICAL_MAX_OHM) {
                $bands['impedance_band'] = 'HIGH / OUTSIDE_TYPICAL';
            } else {
                $bands['impedance_band'] = 'PLAUSIBLE REFERENCE';
            }
        }

        // 4. Distance Bands
        if ($metrics['distance'] !== null) {
            $d = $metrics['distance'];
            if ($d <= 0.0) {
                $bands['distance_band'] = 'INVALID';
                $bands['physical_violations'][] = 'DISTANCE_NON_POSITIVE';
            } elseif ($d > self::DISTANCE_PHYSICAL_CUTOFF_KM) {
                $bands['distance_band'] = 'INVALID_PHYSICAL_BOUND';
                $bands['physical_violations'][] = 'DISTANCE_EXCEEDS_SCALE';
            } elseif ($d > self::DISTANCE_TYPICAL_MAX_KM) {
                $bands['distance_band'] = 'OUTSIDE_TYPICAL / DIAGNOSTIC';
            } else {
                $bands['distance_band'] = 'TYPICAL REFERENCE BAND';
            }
        }

        $bands['physical_violations'] = array_values(array_unique($bands['physical_violations']));
        return $bands;
    }

    /**
     * Evaluate coherence between reported fault type and phase currents.
     * Conservative rules: only flags strong contradictions as incoherent.
     */
    protected function evaluateCoherence(?string $faultType, array $metrics): array
    {
        if ($faultType === null || $faultType === '') {
            return [
                'is_coherent' => true,
                'note'        => 'Fault type not specified; coherence evaluation skipped.',
            ];
        }

        $ia = $metrics['ia'] ?? 0.0;
        $ib = $metrics['ib'] ?? 0.0;
        $ic = $metrics['ic'] ?? 0.0;

        // Check for strong contradictions on single line to ground faults
        if (in_array($faultType, ['A-G', 'L1-G', 'R-G'], true)) {
            // Strong contradiction: Phase A is near zero while B or C has massive fault current
            if ($ia !== null && $ia < 0.05 && (($ib !== null && $ib > 1.0) || ($ic !== null && $ic > 1.0))) {
                return [
                    'is_coherent'    => false,
                    'rejection_code' => 'FAULT_TYPE_CURRENT_INCOHERENT',
                    'note'           => "Fault type {$faultType} contradicts zero current on Phase A while Phase B/C exhibits fault current",
                ];
            }
        } elseif (in_array($faultType, ['B-G', 'L2-G', 'S-G'], true)) {
            if ($ib !== null && $ib < 0.05 && (($ia !== null && $ia > 1.0) || ($ic !== null && $ic > 1.0))) {
                return [
                    'is_coherent'    => false,
                    'rejection_code' => 'FAULT_TYPE_CURRENT_INCOHERENT',
                    'note'           => "Fault type {$faultType} contradicts zero current on Phase B while Phase A/C exhibits fault current",
                ];
            }
        } elseif (in_array($faultType, ['C-G', 'L3-G', 'T-G'], true)) {
            if ($ic !== null && $ic < 0.05 && (($ia !== null && $ia > 1.0) || ($ib !== null && $ib > 1.0))) {
                return [
                    'is_coherent'    => false,
                    'rejection_code' => 'FAULT_TYPE_CURRENT_INCOHERENT',
                    'note'           => "Fault type {$faultType} contradicts zero current on Phase C while Phase A/B exhibits fault current",
                ];
            }
        } elseif (in_array($faultType, ['A-B', 'L1-L2', 'R-S'], true)) {
            if ($ia !== null && $ib !== null && $ia < 0.05 && $ib < 0.05 && ($ic !== null && $ic > 1.0)) {
                return [
                    'is_coherent'    => false,
                    'rejection_code' => 'FAULT_TYPE_CURRENT_INCOHERENT',
                    'note'           => "Fault type {$faultType} contradicts zero current on Phases A and B while Phase C exhibits fault current",
                ];
            }
        }

        return [
            'is_coherent' => true,
            'note'        => 'Currents are coherent with reported fault type or insufficient evidence to contradict',
        ];
    }

    /**
     * Check if telemetry is diagnostic / non-fault event.
     */
    protected function isDiagnosticEvent(array $parsed, array $metrics): bool
    {
        $type = $metrics['fault_type'] ?? '';
        $diagnosticTypes = [
            'DIAGNOSTIC', 'HEARTBEAT', 'BATTERY_TEST', 'BATTERY-TEST', 'SELF_CHECK', 'SELF-CHECK',
            'COMM_TEST', 'COMM-TEST', 'TEST', 'BATTERY', 'KEEP_ALIVE', 'KEEP-ALIVE'
        ];

        if (in_array($type, $diagnosticTypes, true)) {
            return true;
        }

        // Check if explicitly marked as normal non-fault telemetry
        if ($type === 'NORMAL' || $type === 'NONE') {
            return true;
        }

        return false;
    }

    /**
     * Determine final status and assemble validation report.
     */
    protected function determineStatusAndAssembleResult(
        array $parsed,
        array $metrics,
        array $bands,
        array $coherence,
        array $context
    ): array {
        $rejectionCodes = $bands['physical_violations'];

        if (!$coherence['is_coherent'] && isset($coherence['rejection_code'])) {
            $rejectionCodes[] = $coherence['rejection_code'];
        }

        $rejectionCodes = array_values(array_unique($rejectionCodes));

        // Protection pickup knowledge
        $pickupStatus = 'PROTECTION_PICKUP_UNKNOWN';
        if (isset($context['ocr_pickup_amps']) || isset($context['gfr_pickup_amps'])) {
            $pickupStatus = 'PROTECTION_PICKUP_CONFIGURED';
        }

        // 1. INVALID: Any physical bound violation or strong incoherence
        if (!empty($rejectionCodes)) {
            $result = [
                'validator_version'        => self::VERSION,
                'status'                   => 'INVALID',
                'flags'                    => [
                    'is_fault_event'           => false,
                    'is_locatable'             => false,
                    'is_engineering_plausible' => false,
                    'is_complete'              => false,
                ],
                'normalized_metrics'       => $this->buildNormalizedMetricsPayload($metrics),
                'diagnostic_bands'         => $bands,
                'rejection_codes'          => $rejectionCodes,
                'protection_pickup_status' => $pickupStatus,
                'diagnostics'              => $this->buildDiagnosticsPayload($metrics),
                'validation_messages'      => array_merge($rejectionCodes, [$coherence['note'] ?? '']),
            ];
            $result['validation_fingerprint'] = $this->calculateValidationFingerprint($result);
            return $result;
        }

        // 2. Check Completeness for Locatability
        // Primary parameters: Currents present + Voltage present + (Distance or Impedance present)
        $hasCurrents = ($metrics['ia'] !== null || $metrics['ib'] !== null || $metrics['ic'] !== null);
        $hasVoltage = ($metrics['voltage'] !== null);
        $hasDistanceOrZ = ($metrics['distance'] !== null || $metrics['impedance'] !== null);

        $isComplete = ($hasCurrents && $hasVoltage && $hasDistanceOrZ);

        // 3. PARTIAL: Authentic fault event but secondary measurement missing
        if (!$isComplete) {
            $missingComponents = [];
            if (!$hasVoltage) $missingComponents[] = 'VOLTAGE_ABSENT';
            if (!$hasDistanceOrZ) $missingComponents[] = 'DISTANCE_AND_IMPEDANCE_ABSENT';

            $result = [
                'validator_version'        => self::VERSION,
                'status'                   => 'PARTIAL',
                'flags'                    => [
                    'is_fault_event'           => true,
                    'is_locatable'             => false, // Strictly false: downstream determines locatability
                    'is_engineering_plausible' => true,
                    'is_complete'              => false,
                ],
                'normalized_metrics'       => $this->buildNormalizedMetricsPayload($metrics),
                'diagnostic_bands'         => $bands,
                'rejection_codes'          => [],
                'protection_pickup_status' => $pickupStatus,
                'diagnostics'              => $this->buildDiagnosticsPayload($metrics),
                'validation_messages'      => array_merge(['Secondary measurements incomplete for distance locating'], $missingComponents),
            ];
            $result['validation_fingerprint'] = $this->calculateValidationFingerprint($result);
            return $result;
        }

        // 4. VALID: Complete, coherent, in physical bounds
        $result = [
            'validator_version'        => self::VERSION,
            'status'                   => 'VALID',
            'flags'                    => [
                'is_fault_event'           => true,
                'is_locatable'             => true, // Locatable candidate for FL-01D
                'is_engineering_plausible' => true,
                'is_complete'              => true,
            ],
            'normalized_metrics'       => $this->buildNormalizedMetricsPayload($metrics),
            'diagnostic_bands'         => $bands,
            'rejection_codes'          => [],
            'protection_pickup_status' => $pickupStatus,
            'diagnostics'              => $this->buildDiagnosticsPayload($metrics),
            'validation_messages'      => ['Measurement validation successful; passes all engineering plausibility checks'],
        ];
        $result['validation_fingerprint'] = $this->calculateValidationFingerprint($result);
        return $result;
    }

    /**
     * Build diagnostic result for keepalive/battery tests.
     */
    protected function buildDiagnosticResult(array $parsed, array $metrics): array
    {
        $bands = $this->evaluateDiagnosticBands($metrics);
        $result = [
            'validator_version'        => self::VERSION,
            'status'                   => 'DIAGNOSTIC_ONLY',
            'flags'                    => [
                'is_fault_event'           => false,
                'is_locatable'             => false,
                'is_engineering_plausible' => empty($bands['physical_violations']),
                'is_complete'              => false,
            ],
            'normalized_metrics'       => $this->buildNormalizedMetricsPayload($metrics),
            'diagnostic_bands'         => $bands,
            'rejection_codes'          => [],
            'protection_pickup_status' => 'NOT_APPLICABLE_DIAGNOSTIC',
            'diagnostics'              => $this->buildDiagnosticsPayload($metrics),
            'validation_messages'      => ['Non-fault routine diagnostic or heartbeat telemetry'],
        ];
        $result['validation_fingerprint'] = $this->calculateValidationFingerprint($result);
        return $result;
    }

    /**
     * Build unresolved result for syntax corruption, conflicting duplicate fields, or empty payloads.
     */
    protected function buildUnresolvedResult(array $parsed, array $reasons): array
    {
        $result = [
            'validator_version'        => self::VERSION,
            'status'                   => 'UNRESOLVED',
            'flags'                    => [
                'is_fault_event'           => false,
                'is_locatable'             => false,
                'is_engineering_plausible' => false,
                'is_complete'              => false,
            ],
            'normalized_metrics'       => [
                'fault_type'      => $parsed['fault_type'] ?? null,
                'ia_ka'           => null,
                'ib_ka'           => null,
                'ic_ka'           => null,
                'voltage_kv'      => null,
                'distance_km'     => null,
                'impedance_ohms'  => null,
                'fault_time'      => null,
            ],
            'diagnostic_bands'         => [
                'voltage_band'       => 'NOT_EVALUATED',
                'current_bands'      => [],
                'impedance_band'     => 'NOT_EVALUATED',
                'distance_band'      => 'NOT_EVALUATED',
                'physical_violations'=> [],
            ],
            'rejection_codes'          => ['UNRESOLVED_PAYLOAD'],
            'protection_pickup_status' => 'NOT_EVALUATED',
            'diagnostics'              => [],
            'validation_messages'      => $reasons,
        ];
        $result['validation_fingerprint'] = $this->calculateValidationFingerprint($result);
        return $result;
    }

    /**
     * Build clean normalized metrics payload.
     */
    protected function buildNormalizedMetricsPayload(array $metrics): array
    {
        return [
            'fault_type'     => $metrics['fault_type'],
            'ia_ka'          => $metrics['ia'],
            'ib_ka'          => $metrics['ib'],
            'ic_ka'          => $metrics['ic'],
            'voltage_kv'     => $metrics['voltage'],
            'distance_km'    => $metrics['distance'],
            'impedance_ohms' => $metrics['impedance'], // Authoritative FTU impedance preserved
            'fault_time'     => $metrics['fault_time'],
            'timezone'       => $metrics['timezone'] ?? null,
            'date'           => $metrics['date'] ?? null,
        ];
    }

    /**
     * Build diagnostics payload including explicit labeling of any V/I ratio.
     * FTU Z is NEVER replaced!
     */
    protected function buildDiagnosticsPayload(array $metrics): array
    {
        $diag = [
            'authoritative_z_preserved' => true,
        ];

        // Diagnostic V/I ratio (ONLY for diagnostic insight, NEVER for location or overwriting Z)
        $vKv = $metrics['voltage'];
        $iMaxKa = max($metrics['ia'] ?? 0.0, $metrics['ib'] ?? 0.0, $metrics['ic'] ?? 0.0);

        if ($vKv !== null && $iMaxKa > 0.0) {
            $ratio = round(($vKv * 1000.0) / ($iMaxKa * 1000.0), 4);
            $diag['diagnostic_v_over_i'] = [
                'calculated_ratio_ohms' => $ratio,
                'label'                 => 'DIAGNOSTIC_ONLY',
                'note'                  => 'Reference ratio only. Authoritative FTU impedance is never overwritten.',
            ];
        }

        return $diag;
    }

    /**
     * Check if errors array contains conflicting field errors.
     */
    protected function hasConflictingField(array $errors): bool
    {
        foreach ($errors as $err) {
            if (str_contains($err, 'CONFLICTING_FIELD')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate deterministic SHA-256 fingerprint.
     * Excludes execution times, machine paths, and random IDs.
     */
    public function calculateValidationFingerprint(array $result): string
    {
        $canonical = [
            'validator_version'        => $result['validator_version'] ?? self::VERSION,
            'status'                   => $result['status'] ?? '',
            'flags'                    => [
                'is_fault_event'           => (bool)($result['flags']['is_fault_event'] ?? false),
                'is_locatable'             => (bool)($result['flags']['is_locatable'] ?? false),
                'is_engineering_plausible' => (bool)($result['flags']['is_engineering_plausible'] ?? false),
                'is_complete'              => (bool)($result['flags']['is_complete'] ?? false),
            ],
            'normalized_metrics'       => $result['normalized_metrics'] ?? [],
            'diagnostic_bands'         => $result['diagnostic_bands'] ?? [],
            'rejection_codes'          => $result['rejection_codes'] ?? [],
            'protection_pickup_status' => $result['protection_pickup_status'] ?? '',
        ];

        ksort($canonical);
        ksort($canonical['flags']);
        if (isset($canonical['normalized_metrics']) && is_array($canonical['normalized_metrics'])) {
            ksort($canonical['normalized_metrics']);
        }
        if (isset($canonical['diagnostic_bands']) && is_array($canonical['diagnostic_bands'])) {
            ksort($canonical['diagnostic_bands']);
        }

        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $json);
    }
}
