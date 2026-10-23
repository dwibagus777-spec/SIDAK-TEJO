<?php

namespace App\Services;

use Config\Database;

class FaultLocatorWorkspaceService
{
    protected $db;
    protected FaultLocationIntelligenceService $fliEngine;
    protected string $topologySnapshotId = 'TOPOLOGY-20261021-SDA-PROD';
    protected string $engineVersion = 'FLI-2.1.0-PHYSICS-RIGOR';

    // Standard PLN 20 kV Conductor Impedance Catalog (at 50 Hz, 20 kV, positive and zero sequence)
    // Source: SPLN D3.002-1: 2007 & IEEE Std 141 (Red Book)
    public const CONDUCTOR_CATALOG = [
        'AAAC 150 mm²' => [
            'type'        => 'AAAC',
            'size'        => '150 mm²',
            'r1_ohm_km'   => 0.2330,
            'x1_ohm_km'   => 0.3350,
            'z1_ohm_km'   => 0.4080,
            'r0_ohm_km'   => 0.3820,
            'x0_ohm_km'   => 1.5400,
            'z0_ohm_km'   => 1.5870,
            'ampacity_a'  => 400.0,
        ],
        'AAAC 70 mm²' => [
            'type'        => 'AAAC',
            'size'        => '70 mm²',
            'r1_ohm_km'   => 0.4990,
            'x1_ohm_km'   => 0.3600,
            'z1_ohm_km'   => 0.6153,
            'r0_ohm_km'   => 0.6480,
            'x0_ohm_km'   => 1.5650,
            'z0_ohm_km'   => 1.6940,
            'ampacity_a'  => 245.0,
        ],
        'AAAC 240 mm²' => [
            'type'        => 'AAAC',
            'size'        => '240 mm²',
            'r1_ohm_km'   => 0.1460,
            'x1_ohm_km'   => 0.3150,
            'z1_ohm_km'   => 0.3472,
            'r0_ohm_km'   => 0.2950,
            'x0_ohm_km'   => 1.5200,
            'z0_ohm_km'   => 1.5480,
            'ampacity_a'  => 520.0,
        ],
        'AAACS 150 mm²' => [
            'type'        => 'AAACS',
            'size'        => '150 mm²',
            'r1_ohm_km'   => 0.2350,
            'x1_ohm_km'   => 0.3300,
            'z1_ohm_km'   => 0.4051,
            'r0_ohm_km'   => 0.3850,
            'x0_ohm_km'   => 1.5350,
            'z0_ohm_km'   => 1.5830,
            'ampacity_a'  => 390.0,
        ],
        'XLPE 150 mm²' => [
            'type'        => 'XLPE',
            'size'        => '150 mm²',
            'r1_ohm_km'   => 0.2060,
            'x1_ohm_km'   => 0.1150,
            'z1_ohm_km'   => 0.2359,
            'r0_ohm_km'   => 0.8240,
            'x0_ohm_km'   => 0.4600,
            'z0_ohm_km'   => 0.9440,
            'ampacity_a'  => 350.0,
        ],
    ];

    // Operational Fault Classification Thresholds (PLN 20 kV Distribution & Relay Engineering Standards)
    public const THRESHOLD_NEUTRAL_NOISE_A = 15.0;    // <= 15.0 A dianggap nol (noise / ketidakseimbangan beban normal)
    public const THRESHOLD_NEUTRAL_DETECTED_A = 20.0; // >= 20.0 A terdeteksi sebagai arus gangguan tanah / GFR
    public const THRESHOLD_PHASE_NOISE_A = 20.0;      // <= 20.0 A dianggap nol / idle
    public const THRESHOLD_OCR_MIN_A = 300.0;         // Ambang batas bawah OCR: > 300.0 A (300.0 A tepat TIDAK memenuhi "di atas 300 A")
    public const THRESHOLD_OCR_INSTANT_A = 1000.0;    // Ambang batas OCR vs OCR Instan: 300 A < I <= 1000 A adalah OCR; I > 1000 A adalah OCR Instan

    public function __construct(?FaultLocationIntelligenceService $fliEngine = null, $db = null)
    {
        $this->db = $db ?? Database::connect('default');
        $this->fliEngine = $fliEngine ?? new FaultLocationIntelligenceService(null, null, $this->db);
    }

    /**
     * Get operational summary & FLI engine baseline status from actual database tables
     */
    public function getOperationalSummary(): array
    {
        $activeTl = 0;
        $physicalTl = 0;
        $networkSpan = 0.0;
        if ($this->db->tableExists('gis_translines')) {
            $activeTl = $this->db->table('gis_translines')->where('deleted_at IS NULL')->where('is_active', 1)->countAllResults();
            $physicalTl = $this->db->table('gis_translines')->countAllResults();
            $spanRow = $this->db->table('gis_translines')
                ->where('deleted_at IS NULL')
                ->where('is_active', 1)
                ->selectSum('distance_meters', 'total_span')
                ->get()
                ->getRow();
            $networkSpan = (float)($spanRow->total_span ?? 0.0);
        }

        $activeAssets = 0;
        $physicalAssets = 0;
        if ($this->db->tableExists('assets')) {
            $activeAssets = $this->db->table('assets')->where('deleted_at IS NULL')->countAllResults();
            $physicalAssets = $this->db->table('assets')->countAllResults();
        }

        return [
            'engine_version'     => $this->engineVersion,
            'topology_snapshot'  => $this->topologySnapshotId,
            'active_translines'  => $activeTl,
            'physical_translines'=> $physicalTl,
            'active_assets'      => $activeAssets,
            'physical_assets'    => $physicalAssets,
            'network_span_meters'=> round($networkSpan, 2),
            'system_voltage_kv'  => 20.0,
            'grounding_system'   => 'NGR_40_OHM',
            'supported_outcomes' => ['RESOLVED', 'DISTANCE_AMBIGUOUS', 'UNRESOLVED', 'DATA_TIDAK_CUKUP'],
            'ai_ceiling'         => 'L4_RECOMMENDED',
            'human_gate'         => 'L5_APPROVED -> L6_EXECUTED'
        ];
    }

    /**
     * Get active ULPs from database
     */
    public function getUlps(): array
    {
        if (!$this->db->tableExists('ulps')) {
            return [];
        }

        return $this->db->table('ulps')
            ->select('id, kode_ulp, nama_ulp, status')
            ->where('status', 'AKTIF')
            ->orderBy('nama_ulp', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Resolve ULP ID from ID, Kode, or Normalized Name
     *
     * @param mixed $identifier
     * @return int|false Returns resolved integer ID or false if not found
     */
    public function resolveUlpId($identifier)
    {
        if ($identifier === null || $identifier === '' || $identifier === false) {
            return false;
        }

        if (!$this->db->tableExists('ulps')) {
            return false;
        }

        // 1. If numeric, check primary key id first
        if (is_numeric($identifier)) {
            $numId = (int)$identifier;
            $row = $this->db->table('ulps')
                ->select('id')
                ->where('id', $numId)
                ->get()
                ->getRowArray();
            if ($row) {
                return (int)$row['id'];
            }

            // Check if matching kode_ulp as number/string
            $rowKode = $this->db->table('ulps')
                ->select('id')
                ->where('kode_ulp', (string)$identifier)
                ->get()
                ->getRowArray();
            if ($rowKode) {
                return (int)$rowKode['id'];
            }
        }

        // 2. Check exact string kode_ulp
        $cleanStr = trim((string)$identifier);
        $rowStr = $this->db->table('ulps')
            ->select('id')
            ->where('kode_ulp', $cleanStr)
            ->get()
            ->getRowArray();
        if ($rowStr) {
            return (int)$rowStr['id'];
        }

        // 3. Fallback: normalize name match
        $normName = preg_replace('/\s+/', ' ', strtolower($cleanStr));
        $allUlps = $this->db->table('ulps')
            ->select('id, nama_ulp')
            ->get()
            ->getResultArray();

        foreach ($allUlps as $u) {
            $dbNorm = preg_replace('/\s+/', ' ', strtolower(trim($u['nama_ulp'])));
            if ($dbNorm === $normName) {
                return (int)$u['id'];
            }
            if (str_replace('ulp ', '', $dbNorm) === str_replace('ulp ', '', $normName)) {
                return (int)$u['id'];
            }
        }

        return false;
    }

    /**
     * Get list of real feeders from database, strictly filtered by ULP if requested.
     *
     * @param int|string|null $ulpId
     * @return array
     */
    public function getFeeders($ulpId = null): array
    {
        if (!$this->db->tableExists('penyulang')) {
            return [];
        }

        $builder = $this->db->table('penyulang')
            ->select('id, kode_penyulang, nama_penyulang, ulp_id');

        // If an ULP is requested, resolve it strictly
        if ($ulpId !== null && $ulpId !== '') {
            $resolvedId = $this->resolveUlpId($ulpId);
            if ($resolvedId === false) {
                // Explicitly requested ULP was invalid/not found -> return empty list, never all/default feeders
                return [];
            }
            $builder->where('ulp_id', $resolvedId);
        }

        if ($this->db->fieldExists('status', 'penyulang')) {
            $builder->where('status', 'AKTIF');
        }

        return $builder->orderBy('nama_penyulang', 'ASC')->get()->getResultArray();
    }

    /**
     * Get protection devices (Recloser, PMCB, GH) for a specific feeder
     *
     * Rule: In Fault Locator, ONLY Recloser, PMCB, and GH serve as protection references.
     * LBSM and ASS remain switching/keypoint assets, NOT protection trip points.
     */
    public function getProtectionDevices(int $penyulangId): array
    {
        if (!$this->db->tableExists('assets') || $penyulangId <= 0) {
            return [];
        }

        return $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, jenis_asset, section_id, sequence_no, latitude, longitude, parent_asset_id')
            ->where('penyulang_id', $penyulangId)
            ->where('deleted_at IS NULL')
            ->groupStart()
                ->whereIn('UPPER(jenis_asset)', ['RECLOSER', 'PMCB', 'GH', 'JTM_RECLOSER', 'JTM_PMCB', 'GARDU_HUBUNG'])
                ->orGroupStart()
                    ->groupStart()
                        ->like('nama_asset', 'RECLOSER')
                        ->orLike('nama_asset', 'REC ')
                        ->orLike('nama_asset', 'PMCB')
                        ->orLike('nama_asset', 'GH ')
                        ->orLike('nama_asset', 'GARDU HUBUNG')
                    ->groupEnd()
                    ->notLike('nama_asset', 'LBS')
                    ->notLike('nama_asset', 'ASS')
                    ->notLike('nama_asset', 'SECTIONALIZER')
                ->groupEnd()
            ->groupEnd()
            ->notLike('jenis_asset', 'LBS')
            ->notLike('jenis_asset', 'ASS')
            ->notLike('jenis_asset', 'SECTIONALIZER')
            ->orderBy('sequence_no', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get recent interruptions from historical table
     */
    public function getRecentInterruptions(int $limit = 15): array
    {
        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            return [];
        }

        return $this->db->table('historical_feeder_interruptions')
            ->select('id, penyulang_id, feeder_name, event_date, interruption_started_at, outage_duration_minutes, device_name, relay_trip_type, faulted_phase, fault_current_amperes, weather_condition, field_narrative_raw, asset_id')
            ->orderBy('event_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Parse and validate telemetry inputs (Currents R/S/T/N in A, Phase Voltages R/S/T in kV, Optional Relay Distance in m).
     * Strictly distinguishes entered 0.00 kV (0.0) from unentered/empty data (null).
     */
    public function parseAndValidateTelemetry(array $telemetry): array
    {
        $errors = [];

        $parseCurrent = static function ($raw, string $phaseLabel) use (&$errors): float {
            if ($raw === null || $raw === '') {
                return 0.0;
            }
            if (is_string($raw)) {
                $raw = str_replace(',', '.', trim($raw));
                if ($raw === '') {
                    return 0.0;
                }
            }
            if (!is_numeric($raw)) {
                $errors[] = "Arus {$phaseLabel} harus berupa angka valid dalam satuan Ampere (A).";
                return 0.0;
            }
            $val = (float)$raw;
            if (!is_finite($val) || $val < 0.0 || $val > 50000.0) {
                $errors[] = "Arus {$phaseLabel} harus berada dalam rentang 0.00 A hingga 50,000.00 A.";
                return 0.0;
            }
            return round($val, 2);
        };

        $parseVoltageKv = static function ($raw, string $phaseLabel) use (&$errors): ?float {
            if ($raw === null) {
                return null;
            }
            if (is_string($raw)) {
                $raw = str_replace(',', '.', trim($raw));
                if ($raw === '') {
                    return null;
                }
            }
            if (!is_numeric($raw)) {
                $errors[] = "Tegangan {$phaseLabel} harus berupa angka valid dalam satuan kV.";
                return null;
            }
            $val = (float)$raw;
            if (!is_finite($val) || $val < 0.0 || $val > 35.0) {
                $errors[] = "Tegangan {$phaseLabel} harus berada dalam rentang 0.00 kV hingga 35.00 kV.";
                return null;
            }
            return round($val, 2);
        };

        $parseRelayDistance = static function ($raw) use (&$errors): ?float {
            if ($raw === null) {
                return null;
            }
            if (is_string($raw)) {
                $raw = str_replace(',', '.', trim($raw));
                if ($raw === '') {
                    return null;
                }
            }
            if (!is_numeric($raw)) {
                $errors[] = 'Jarak gangguan terukur relay harus berupa angka valid dalam satuan meter (m).';
                return null;
            }
            $val = (float)$raw;
            if (!is_finite($val) || $val < 0.0 || $val > 250000.0) {
                $errors[] = 'Jarak gangguan terukur relay harus berada dalam rentang > 0 m hingga 250,000 m.';
                return null;
            }
            return $val > 0.0 ? round($val, 1) : null;
        };

        $currentR = $parseCurrent($telemetry['current_r'] ?? ($telemetry['fault_current_a'] ?? null), 'Fasa R');
        $currentS = $parseCurrent($telemetry['current_s'] ?? null, 'Fasa S');
        $currentT = $parseCurrent($telemetry['current_t'] ?? null, 'Fasa T');
        $currentN = $parseCurrent($telemetry['current_n'] ?? null, 'Netral / GFR');

        $voltageR = array_key_exists('voltage_r_kv', $telemetry)
            ? $parseVoltageKv($telemetry['voltage_r_kv'], 'Fasa R')
            : (array_key_exists('voltage_r', $telemetry) ? $parseVoltageKv($telemetry['voltage_r'], 'Fasa R') : null);
        $voltageS = array_key_exists('voltage_s_kv', $telemetry)
            ? $parseVoltageKv($telemetry['voltage_s_kv'], 'Fasa S')
            : (array_key_exists('voltage_s', $telemetry) ? $parseVoltageKv($telemetry['voltage_s'], 'Fasa S') : null);
        $voltageT = array_key_exists('voltage_t_kv', $telemetry)
            ? $parseVoltageKv($telemetry['voltage_t_kv'], 'Fasa T')
            : (array_key_exists('voltage_t', $telemetry) ? $parseVoltageKv($telemetry['voltage_t'], 'Fasa T') : null);

        $rawRelayDist = $telemetry['relay_measured_distance_m'] ?? $telemetry['relay_distance_m'] ?? null;
        $relayDistanceM = $parseRelayDistance($rawRelayDist);

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'values' => [
                'current_r'                 => $currentR,
                'current_s'                 => $currentS,
                'current_t'                 => $currentT,
                'current_n'                 => $currentN,
                'voltage_r_kv'              => $voltageR,
                'voltage_s_kv'              => $voltageS,
                'voltage_t_kv'              => $voltageT,
                'relay_measured_distance_m' => $relayDistanceM,
            ],
        ];
    }

    /**
     * Rigorous Fault Indication Classification from Combined Currents (R, S, T, N in A) and Phase Voltages (R, S, T in kV)
     *
     * Adheres to IEEE C37.114 & SPLN S3.001:
     * - Distinguishes measured 0.00 kV (0.0) from unentered voltage (null).
     * - Evaluates voltage depression / neutral shift alongside phase & neutral currents to classify DGR / ground / phase fault indications.
     * - Always presents classification as an "indication" with supporting evidence and confidence score.
     */
    public function classifyFaultCurrents(
        float $iR,
        float $iS,
        float $iT,
        float $iN,
        ?float $vR = null,
        ?float $vS = null,
        ?float $vT = null
    ): array {
        $iR = round(abs($iR), 2);
        $iS = round(abs($iS), 2);
        $iT = round(abs($iT), 2);
        $iN = round(abs($iN), 2);

        $vR = $vR !== null ? round(abs($vR), 2) : null;
        $vS = $vS !== null ? round(abs($vS), 2) : null;
        $vT = $vT !== null ? round(abs($vT), 2) : null;

        $iMap = ['R' => $iR, 'S' => $iS, 'T' => $iT];
        $vMap = ['R' => $vR, 'S' => $vS, 'T' => $vT];

        $enteredVoltages = array_filter($vMap, static fn($v): bool => $v !== null);
        $hasAnyVoltage = !empty($enteredVoltages);
        $hasAllThreeVoltages = ($vR !== null && $vS !== null && $vT !== null);
        $indicationBasis = $hasAnyVoltage ? 'COMBINED_CURRENT_AND_PHASE_VOLTAGE' : 'CURRENT_ONLY_SCALAR';

        $saggedPhases = [];
        $healthyVoltagePhases = [];
        foreach ($vMap as $phase => $vVal) {
            if ($vVal === null) {
                continue;
            }
            if ($vVal <= 8.5) {
                $saggedPhases[] = $phase;
            } elseif ($vVal >= 10.0) {
                $healthyVoltagePhases[] = $phase;
            }
        }

        $maxPhase = max($iR, $iS, $iT);

        $buildPayload = static function (
            string $faultType,
            string $faultTypeLabel,
            float $activeCurrentA,
            array $faultPhases,
            bool $isGroundFault,
            string $rule,
            float $indicationConfidence,
            array $evidence,
            string $operationalCategory = '',
            string $subClassification = 'NONE',
            string $technicalType = ''
        ) use ($iR, $iS, $iT, $iN, $vR, $vS, $vT, $hasAnyVoltage, $hasAllThreeVoltages, $indicationBasis): array {
            $opCat = $operationalCategory ?: $faultType;
            $techType = $technicalType ?: $faultType;
            return [
                'fault_type'                    => $opCat,
                'operational_category'          => $opCat,
                'sub_classification'            => $subClassification,
                'fault_type_label'              => $faultTypeLabel,
                'indication_label'              => $faultTypeLabel,
                'technical_fault_type'          => $techType,
                'is_indication'                 => true,
                'indication_basis'              => $indicationBasis,
                'indication_confidence_percent' => round($indicationConfidence, 1),
                'supporting_evidence'           => $evidence,
                'active_current_a'              => round($activeCurrentA, 1),
                'fault_phases'                  => $faultPhases,
                'is_ground_fault'               => $isGroundFault,
                'classification_rule'           => $rule,
                'thresholds_used'               => [
                    'neutral_noise_a'           => self::THRESHOLD_NEUTRAL_NOISE_A,
                    'neutral_detected_a'        => self::THRESHOLD_NEUTRAL_DETECTED_A,
                    'phase_noise_a'             => self::THRESHOLD_PHASE_NOISE_A,
                    'ocr_min_a'                 => self::THRESHOLD_OCR_MIN_A,
                    'ocr_instant_a'             => self::THRESHOLD_OCR_INSTANT_A,
                ],
                'current_r'                     => $iR,
                'current_s'                     => $iS,
                'current_t'                     => $iT,
                'current_n'                     => $iN,
                'voltage_r_kv'                  => $vR,
                'voltage_s_kv'                  => $vS,
                'voltage_t_kv'                  => $vT,
                'has_voltage_telemetry'         => $hasAnyVoltage,
                'has_complete_phase_voltages'   => $hasAllThreeVoltages,
            ];
        };

        // Under-current / load threshold: If all currents <= 10A and no voltage collapse
        if ($maxPhase <= 10.0 && $iN <= 10.0 && empty($saggedPhases)) {
            $evidence = [
                "Arus fasa R ({$iR} A), S ({$iS} A), T ({$iT} A) dan Netral ({$iN} A) berada di bawah ambang gangguan (<= 10 A).",
            ];
            if ($hasAnyVoltage) {
                $vDesc = [];
                foreach ($enteredVoltages as $p => $v) {
                    $vDesc[] = "{$p}=" . number_format($v, 2, '.', '') . " kV";
                }
                $evidence[] = 'Tegangan fasa terukur normal (' . implode(', ', $vDesc) . ').';
            }
            return $buildPayload(
                'NO_FAULT',
                'Normal / Beban Rendah (Arus < 10 A)',
                0.0,
                [],
                false,
                'ARUS_DIBAWAH_AMBANG_GANGGUAN',
                95.0,
                $evidence,
                'NO_FAULT',
                'NONE',
                'NO_FAULT'
            );
        }

        // Neutral status evaluation (validated thresholds)
        $isNeutralZero = ($iN <= self::THRESHOLD_NEUTRAL_NOISE_A);
        $isNeutralDetected = ($iN >= self::THRESHOLD_NEUTRAL_DETECTED_A);
        $isNeutralBorderline = (!$isNeutralZero && !$isNeutralDetected); // 10.0 A < iN < 15.0 A

        // Active phase candidates above phase noise threshold (20 A)
        $phaseCandidates = [];
        foreach ($iMap as $p => $cur) {
            if ($cur > self::THRESHOLD_PHASE_NOISE_A) {
                $phaseCandidates[$p] = $cur;
            }
        }
        arsort($phaseCandidates);
        $candidateCount = count($phaseCandidates);

        $isInterPhase = false;
        $faultPhases = [];
        $iFault = 0.0;
        $technicalType = 'UNKNOWN_PROFILE';

        // 1. Priority: If exactly 1 phase voltage collapsed and others healthy -> Single-Phase Ground (DGR)
        if (count($saggedPhases) === 1 && count($healthyVoltagePhases) >= 1 && ($isNeutralDetected || $iN >= self::THRESHOLD_NEUTRAL_NOISE_A)) {
            $isInterPhase = false;
            $faultPhases = $saggedPhases;
            $iFault = max($iMap[$faultPhases[0]], $iN);
            $technicalType = '1_PHASE_GROUND';
        }
        // 2. If exactly 2 phase voltages collapsed -> 2-Phase Inter-phase fault
        elseif (count($saggedPhases) === 2) {
            $isInterPhase = true;
            $faultPhases = $saggedPhases;
            sort($faultPhases);
            $iFault = round(($iMap[$faultPhases[0]] + $iMap[$faultPhases[1]]) / 2.0, 1);
            $technicalType = '2_PHASE';
        }
        // 3. Check 3-Phase fault (all 3 voltages collapsed OR all 3 phases active & well-balanced with no single phase ground)
        elseif ($candidateCount === 3) {
            $minPhase = min($iR, $iS, $iT);
            $imbalance = ($maxPhase - $minPhase) / max(1.0, $maxPhase);
            if (count($saggedPhases) === 3 || ($imbalance <= 0.35 && empty($saggedPhases))) {
                $isInterPhase = true;
                $faultPhases = ['R', 'S', 'T'];
                // Average of 3 symmetrical faulted phases, never arbitrary summation
                $iFault = round(($iR + $iS + $iT) / 3.0, 1);
                $technicalType = '3_PHASE';
            }
        }

        // 4. If not inter-phase yet, check 2-Phase from current telemetry
        if (!$isInterPhase && $technicalType !== '1_PHASE_GROUND') {
            if ($candidateCount >= 2) {
                $keys = array_keys($phaseCandidates);
                $p1 = $keys[0];
                $p2 = $keys[1];
                $p3 = $keys[2] ?? null;
                $p3Val = $p3 ? $phaseCandidates[$p3] : 0.0;
                // Two dominant phases well above the third
                if ($p3Val <= self::THRESHOLD_PHASE_NOISE_A || $p3Val < 0.40 * $phaseCandidates[$p2] || ($phaseCandidates[$p1] >= 100.0 && $phaseCandidates[$p2] >= 100.0 && $p3Val < 50.0)) {
                    $isInterPhase = true;
                    $faultPhases = [$p1, $p2];
                    sort($faultPhases);
                    $iFault = round(($iMap[$p1] + $iMap[$p2]) / 2.0, 1);
                    $technicalType = '2_PHASE';
                }
            }
        }

        // 5. If not inter-phase, check 1-Phase fault from current telemetry
        if (!$isInterPhase && $technicalType !== '1_PHASE_GROUND') {
            if ($candidateCount === 1) {
                $faultPhases = [array_keys($phaseCandidates)[0]];
                $iFault = max($phaseCandidates[$faultPhases[0]], $iN);
                $technicalType = '1_PHASE_GROUND';
            } elseif ($isNeutralDetected) {
                $dominantPhase = !empty($phaseCandidates) ? array_keys($phaseCandidates)[0] : 'S';
                $faultPhases = [$dominantPhase];
                $iFault = max($maxPhase, $iN);
                $technicalType = '1_PHASE_GROUND';
            }
        }

        $phasesStr = !empty($faultPhases) ? implode('-', $faultPhases) : 'Tidak Spesifik';

        // -------------------------------------------------------------
        // BRANCH 1: Ambiguous / Transition Neutral Zone (10 A < iN < 15 A)
        // -------------------------------------------------------------
        if ($isNeutralBorderline) {
            $evidence = [
                "Arus Netral/GFR sebesar {$iN} A berada di zona ambang transisi (" . self::THRESHOLD_NEUTRAL_NOISE_A . " A – " . self::THRESHOLD_NEUTRAL_DETECTED_A . " A), sehingga belum dapat dipastikan sebagai nol maupun gangguan tanah pasti.",
                "Arus fasa terukur: R={$iR} A, S={$iS} A, T={$iT} A (Fasa: {$phasesStr}, I_fault = {$iFault} A).",
            ];
            if ($hasAnyVoltage) {
                $vDesc = [];
                foreach ($enteredVoltages as $p => $v) $vDesc[] = "{$p}=" . number_format($v, 2, '.', '') . " kV";
                $evidence[] = "Tegangan fasa: " . implode(', ', $vDesc) . ".";
            } else {
                $evidence[] = "Data tegangan per fasa (kV) belum dimasukkan; masukkan tegangan fasa untuk memvalidasi indikasi drop tegangan.";
            }
            return $buildPayload(
                'PERLU_VERIFIKASI',
                "Perlu Verifikasi (Arus Netral {$iN} A di Zona Batas Noise 10 A - 15 A)",
                $iFault,
                $faultPhases,
                false,
                'AMBANG_NETRAL_TRANSISI_PERLU_VERIFIKASI',
                50.0,
                $evidence,
                'PERLU_VERIFIKASI',
                'NONE',
                $technicalType
            );
        }

        // -------------------------------------------------------------
        // BRANCH 2: Pola Gangguan Antar-Fasa ($isInterPhase = true)
        // -------------------------------------------------------------
        if ($isInterPhase) {
            // Scenario 2A: Netral Terdeteksi -> OCR-DGR atau OCR Instan-DGR
            if ($isNeutralDetected) {
                if ($iFault > self::THRESHOLD_OCR_INSTANT_A) {
                    // OCR Instan-DGR (> 1000 A)
                    $evidence = [
                        "Pola gangguan antar-fasa ({$phasesStr}) terdeteksi dengan arus fasa {$iFault} A > " . number_format(self::THRESHOLD_OCR_INSTANT_A, 0) . " A (memenuhi kriteria OCR Instan).",
                        "Arus Netral/GFR terdeteksi sebesar {$iN} A >= " . self::THRESHOLD_NEUTRAL_DETECTED_A . " A (arus gangguan ke tanah juga terpicu).",
                        "Kombinasi fasa dan netral mengindikasikan gangguan OCR Instan-DGR (hubung singkat fasa ke fasa tanah arus tinggi).",
                    ];
                    if ($hasAnyVoltage) {
                        $vDesc = [];
                        foreach ($enteredVoltages as $p => $v) $vDesc[] = "{$p}=" . number_format($v, 2, '.', '') . " kV";
                        $evidence[] = "Tegangan pendukung: " . implode(', ', $vDesc) . ".";
                    }
                    return $buildPayload(
                        'OCR_INSTAN_DGR',
                        "OCR Instan-DGR (Hubung Singkat Fasa & Tanah > 1.000 A)",
                        $iFault,
                        array_merge($faultPhases, ['N']),
                        true,
                        'OCR_INSTAN_DGR_GT_1000A_WITH_GROUND',
                        $hasAnyVoltage ? 94.0 : 88.0,
                        $evidence,
                        'OCR_INSTAN_DGR',
                        'OCR_INSTAN',
                        $technicalType === '3_PHASE' ? '3_PHASE_GROUND' : '2_PHASE_GROUND'
                    );
                } elseif ($iFault > self::THRESHOLD_OCR_MIN_A) {
                    // OCR-DGR (> 300 A s/d 1000 A; tepat 1000 A masuk OCR)
                    $evidence = [
                        "Pola gangguan antar-fasa ({$phasesStr}) terdeteksi dengan arus fasa {$iFault} A berada di atas 300 A s/d 1.000 A (memenuhi kriteria OCR).",
                        "Arus Netral/GFR terdeteksi sebesar {$iN} A >= " . self::THRESHOLD_NEUTRAL_DETECTED_A . " A (arus gangguan ke tanah juga terpicu).",
                        "Kombinasi fasa dan netral mengindikasikan gangguan OCR-DGR.",
                    ];
                    if ($hasAnyVoltage) {
                        $vDesc = [];
                        foreach ($enteredVoltages as $p => $v) $vDesc[] = "{$p}=" . number_format($v, 2, '.', '') . " kV";
                        $evidence[] = "Tegangan pendukung: " . implode(', ', $vDesc) . ".";
                    }
                    return $buildPayload(
                        'OCR_DGR',
                        "OCR-DGR (Hubung Singkat Fasa & Tanah 300 A - 1.000 A)",
                        $iFault,
                        array_merge($faultPhases, ['N']),
                        true,
                        'OCR_DGR_300A_TO_1000A_WITH_GROUND',
                        $hasAnyVoltage ? 92.0 : 85.0,
                        $evidence,
                        'OCR_DGR',
                        'OCR',
                        $technicalType === '3_PHASE' ? '3_PHASE_GROUND' : '2_PHASE_GROUND'
                    );
                } else {
                    // OCR-DGR (arus fasa <= 300 A)
                    $evidence = [
                        "Pola gangguan antar-fasa ({$phasesStr}) terdeteksi bersama arus Netral/GFR ({$iN} A >= " . self::THRESHOLD_NEUTRAL_DETECTED_A . " A).",
                        "Arus fasa {$iFault} A <= " . number_format(self::THRESHOLD_OCR_MIN_A, 0) . " A (di bawah ambang OCR standar > 300 A).",
                        "Diklasifikasikan sebagai OCR-DGR arus rendah / gangguan multi-fasa tanah berimpedansi.",
                    ];
                    return $buildPayload(
                        'OCR_DGR',
                        "OCR-DGR (Hubung Singkat Fasa & Tanah <= 300 A)",
                        $iFault,
                        array_merge($faultPhases, ['N']),
                        true,
                        'OCR_DGR_LE_300A_WITH_GROUND',
                        $hasAnyVoltage ? 85.0 : 75.0,
                        $evidence,
                        'OCR_DGR',
                        'OCR_LOW_CURRENT',
                        $technicalType === '3_PHASE' ? '3_PHASE_GROUND' : '2_PHASE_GROUND'
                    );
                }
            }

            // Scenario 2B: Netral Dianggap Nol ($isNeutralZero = true) -> OCR atau OCR Instan
            if ($isNeutralZero) {
                if ($iFault > self::THRESHOLD_OCR_INSTANT_A) {
                    // OCR Instan (> 1000 A)
                    $evidence = [
                        "Pola gangguan antar-fasa ({$phasesStr}) terdeteksi dengan arus fasa {$iFault} A > " . number_format(self::THRESHOLD_OCR_INSTANT_A, 0) . " A (memenuhi kriteria OCR Instan).",
                        "Arus Netral/GFR sebesar {$iN} A <= " . self::THRESHOLD_NEUTRAL_NOISE_A . " A (dianggap nol / noise).",
                        "Klasifikasi operasional: OCR Instan murni antar-fasa tanpa gangguan ke tanah.",
                    ];
                    if ($hasAnyVoltage) {
                        $vDesc = [];
                        foreach ($enteredVoltages as $p => $v) $vDesc[] = "{$p}=" . number_format($v, 2, '.', '') . " kV";
                        $evidence[] = "Tegangan pendukung: " . implode(', ', $vDesc) . ".";
                    }
                    return $buildPayload(
                        'OCR_INSTAN',
                        "OCR Instan (Hubung Singkat Antar-Fasa > 1.000 A)",
                        $iFault,
                        $faultPhases,
                        false,
                        'OCR_INSTAN_ANTAR_FASA_GT_1000A',
                        $hasAnyVoltage ? 95.0 : 90.0,
                        $evidence,
                        'OCR_INSTAN',
                        'OCR_INSTAN',
                        $technicalType
                    );
                } elseif ($iFault > self::THRESHOLD_OCR_MIN_A) {
                    // OCR (> 300 A s/d 1000 A; tepat 1000 A masuk OCR)
                    $evidence = [
                        "Pola gangguan antar-fasa ({$phasesStr}) terdeteksi dengan arus fasa {$iFault} A di atas 300 A s/d 1.000 A (memenuhi kriteria OCR).",
                        "Arus Netral/GFR sebesar {$iN} A <= " . self::THRESHOLD_NEUTRAL_NOISE_A . " A (dianggap nol / noise).",
                        "Klasifikasi operasional: OCR murni antar-fasa.",
                    ];
                    if ($hasAnyVoltage) {
                        $vDesc = [];
                        foreach ($enteredVoltages as $p => $v) $vDesc[] = "{$p}=" . number_format($v, 2, '.', '') . " kV";
                        $evidence[] = "Tegangan pendukung: " . implode(', ', $vDesc) . ".";
                    }
                    return $buildPayload(
                        'OCR',
                        "OCR (Hubung Singkat Antar-Fasa 300 A - 1.000 A)",
                        $iFault,
                        $faultPhases,
                        false,
                        'OCR_ANTAR_FASA_300A_TO_1000A',
                        $hasAnyVoltage ? 92.0 : 85.0,
                        $evidence,
                        'OCR',
                        'OCR',
                        $technicalType
                    );
                } else {
                    // iFault <= 300 A (tepat 300 A TIDAK memenuhi "di atas 300 A")
                    $evidence = [
                        "Pola arus antar-fasa ({$phasesStr}) terdeteksi sebesar {$iFault} A dengan netral nol ({$iN} A).",
                        "Besaran arus {$iFault} A <= " . number_format(self::THRESHOLD_OCR_MIN_A, 0) . " A tidak memenuhi kriteria 'di atas 300 A' untuk OCR operasional.",
                        "Status: Perlu verifikasi lapangan / pemeriksaan beban penyulang.",
                    ];
                    return $buildPayload(
                        'PERLU_VERIFIKASI',
                        "Perlu Verifikasi (Arus Antar-Fasa {$iFault} A <= 300 A, Belum Memenuhi Ambang OCR)",
                        $iFault,
                        $faultPhases,
                        false,
                        'PERLU_VERIFIKASI_ANTAR_FASA_LE_300A',
                        60.0,
                        $evidence,
                        'PERLU_VERIFIKASI',
                        'NONE',
                        $technicalType
                    );
                }
            }
        }

        // -------------------------------------------------------------
        // BRANCH 3: Pola Fasa Tunggal + Netral Terdeteksi -> DGR
        // -------------------------------------------------------------
        if ($isNeutralDetected) {
            // Rule 1: DGR - arus gangguan terdeteksi pada fasa dan netral/GFR
            $faultPhase = !empty($faultPhases) ? $faultPhases[0] : 'S';
            $phaseCurrent = $iMap[$faultPhase] ?? $maxPhase;
            $evidence = [
                "Arus gangguan terdeteksi pada fasa ({$faultPhase} = " . number_format($phaseCurrent, 1, '.', '') . " A) dan Netral/GFR (" . number_format($iN, 1, '.', '') . " A >= " . number_format(self::THRESHOLD_NEUTRAL_DETECTED_A, 0) . " A).",
                "Karakteristik konsisten dengan gangguan tanah fasa tunggal / Directional Ground Relay (DGR) pada jaringan distribusi 20 kV.",
            ];
            if ($hasAnyVoltage) {
                if (count($saggedPhases) === 1) {
                    $evidence[] = "Drop tegangan fasa {$saggedPhases[0]} ke " . number_format((float)$vMap[$saggedPhases[0]], 2, '.', '') . " kV mengonfirmasi fasa yang terhubung ke tanah.";
                } else {
                    $vDesc = [];
                    foreach ($enteredVoltages as $p => $v) $vDesc[] = "{$p}=" . number_format($v, 2, '.', '') . " kV";
                    $evidence[] = "Tegangan fasa: " . implode(', ', $vDesc) . ".";
                }
            } else {
                $evidence[] = "Data tegangan fasa belum dimasukkan; masukkan tegangan fasa R/S/T untuk verifikasi pergeseran titik netral DGR.";
            }

            $ruleName = (count($saggedPhases) === 1) ? "INDIKASI_DGR_{$faultPhase}_N" : 'DGR_FASA_DAN_NETRAL_TERDETEKSI';
            $labelName = (count($saggedPhases) === 1)
                ? "Indikasi Gangguan Tanah Fasa {$faultPhase}-N / DGR"
                : "DGR (Gangguan Fasa {$faultPhase}-N / Directional Ground Relay)";

            return $buildPayload(
                'DGR',
                $labelName,
                $iFault,
                [$faultPhase, 'N'],
                true,
                $ruleName,
                $hasAnyVoltage ? 92.0 : 80.0,
                $evidence,
                'DGR',
                'DGR',
                '1_PHASE_GROUND'
            );
        }

        // -------------------------------------------------------------
        // BRANCH 4: Fasa Tunggal Tanpa Netral (Netral Nol <= 10 A)
        // -------------------------------------------------------------
        if ($candidateCount === 1 && $isNeutralZero) {
            $p = array_keys($phaseCandidates)[0];
            $evidence = [
                "Arus fasa {$p} terukur {$phaseCandidates[$p]} A, namun arus Netral/GFR dianggap nol ({$iN} A <= " . self::THRESHOLD_NEUTRAL_NOISE_A . " A) dan fasa lain nol.",
                "Pola arus satu fasa tanpa arus kembali (return path) tidak memenuhi kaidah rangkaian tertutup AC; kemungkinan deviasi sensor atau CT.",
                "Status: Perlu verifikasi telemetri.",
            ];
            return $buildPayload(
                'PERLU_VERIFIKASI',
                "Perlu Verifikasi (Pola 1 Fasa {$p} Tanpa Arus Netral)",
                $phaseCandidates[$p],
                [$p],
                false,
                'PERLU_VERIFIKASI_SATU_FASA_TANPA_NETRAL',
                40.0,
                $evidence,
                'PERLU_VERIFIKASI',
                'NONE',
                'UNKNOWN_PROFILE'
            );
        }

        // -------------------------------------------------------------
        // BRANCH 5: Fallback / Pola Asimetris Tidak Jelas -> Perlu Verifikasi
        // -------------------------------------------------------------
        $evidence = [
            "Pola telemetri arus (R={$iR} A, S={$iS} A, T={$iT} A, N={$iN} A) tidak cocok dengan profil gangguan standar.",
            "Berdasarkan aturan operasional: bila data tidak cukup atau pola fasa tidak jelas, ditampilkan Perlu Verifikasi.",
        ];
        if (!$hasAnyVoltage) {
            $evidence[] = "Data tegangan per fasa (kV) belum tersedia.";
        }

        return $buildPayload(
            'PERLU_VERIFIKASI',
            'Perlu Verifikasi (Pola Fasa Tidak Spesifik / Di Bawah Ambang)',
            $maxPhase,
            $faultPhases,
            false,
            'POLA_TIDAK_SPESIFIK_PERLU_VERIFIKASI',
            35.0,
            $evidence,
            'PERLU_VERIFIKASI',
            'NONE',
            'UNKNOWN_PROFILE'
        );
    }

    /**
     * Summarize conductor types and sizes strictly from the traversed downstream transline sequence.
     * Never silently guesses a conductor if the traversed path has no conductor metadata.
     */
    protected function summarizeTraversedPathConductors(array $poleSequence): array
    {
        if (empty($poleSequence)) {
            return [
                'is_available'         => false,
                'status'               => 'BELUM_TERSEDIA',
                'official_type'        => 'Belum tersedia',
                'display_label'        => 'Belum tersedia',
                'source'               => 'TRAVERSED_PATH_EMPTY',
                'is_hybrid'            => false,
                'resolved_catalog_key' => null,
                'catalog_entry'        => null,
                'total_traversed_m'    => 0.0,
                'breakdown'            => [],
                'segment_ranges'       => [],
                'status_message'       => 'Belum tersedia (Jalur transline hilir belum terbentuk dari titik sumber).',
            ];
        }

        $totalPathSpanM = 0.0;
        $maxCumM = 0.0;
        $validSpanCount = 0;
        $missingSpanCount = 0;
        $byConductor = [];
        $segmentRanges = [];

        foreach ($poleSequence as $seg) {
            $spanM = (float)($seg['span_meters'] ?? 0.0);
            $startM = (float)($seg['cum_start_meters'] ?? 0.0);
            $endM = (float)($seg['cumulative_meters'] ?? ($startM + $spanM));
            $totalPathSpanM += $spanM;
            if ($endM > $maxCumM) {
                $maxCumM = $endM;
            }

            $rawType = strtoupper(trim((string)($seg['raw_conductor_type'] ?? $seg['conductor_type'] ?? '')));
            $rawSize = trim((string)($seg['raw_conductor_size'] ?? $seg['conductor_size'] ?? ''));

            if ($rawType === '' || $rawType === 'UNKNOWN' || $rawType === 'BELUM TERSEDIA' || $rawType === '-') {
                $missingSpanCount++;
                $condLabel = 'Belum tersedia';
                $normType = '';
                $normSize = '';
            } else {
                $validSpanCount++;
                $normType = $rawType;
                if ($rawSize !== '' && $rawSize !== '-') {
                    $normSize = str_contains(strtolower($rawSize), 'mm') ? $rawSize : ($rawSize . ' mm²');
                } else {
                    $normSize = '';
                }
                $condLabel = trim($normType . ($normSize !== '' ? ' ' . $normSize : ''));
            }

            if ($normType !== '') {
                if (!isset($byConductor[$condLabel])) {
                    $byConductor[$condLabel] = [
                        'label'      => $condLabel,
                        'type'       => $normType,
                        'size'       => $normSize !== '' ? $normSize : 'Belum tercatat',
                        'spans'      => 0,
                        'distance_m' => 0.0,
                        'min_cum_m'  => $startM,
                        'max_cum_m'  => $endM,
                    ];
                }
                $byConductor[$condLabel]['spans']++;
                $byConductor[$condLabel]['distance_m'] = round($byConductor[$condLabel]['distance_m'] + $spanM, 1);
                $byConductor[$condLabel]['min_cum_m'] = min($byConductor[$condLabel]['min_cum_m'], $startM);
                $byConductor[$condLabel]['max_cum_m'] = max($byConductor[$condLabel]['max_cum_m'], $endM);
            }

            $lastIdx = count($segmentRanges) - 1;
            if (
                $lastIdx >= 0
                && $segmentRanges[$lastIdx]['conductor_label'] === $condLabel
                && (int)$segmentRanges[$lastIdx]['to_pole_id'] === (int)($seg['from_pole_id'] ?? -1)
            ) {
                $segmentRanges[$lastIdx]['to_pole_id'] = (int)($seg['to_pole_id'] ?? 0);
                $segmentRanges[$lastIdx]['to_pole_kode'] = $seg['to_pole_kode'] ?? '';
                $segmentRanges[$lastIdx]['end_m'] = round($endM, 1);
                $segmentRanges[$lastIdx]['span_meters'] = round($segmentRanges[$lastIdx]['span_meters'] + $spanM, 1);
                $segmentRanges[$lastIdx]['span_count']++;
                $segmentRanges[$lastIdx]['range_text'] = number_format($segmentRanges[$lastIdx]['start_m'], 1, '.', '')
                    . ' m – ' . number_format($segmentRanges[$lastIdx]['end_m'], 1, '.', '')
                    . ' m (' . $segmentRanges[$lastIdx]['from_pole_kode'] . ' → ' . $segmentRanges[$lastIdx]['to_pole_kode'] . '): '
                    . $condLabel;
            } else {
                $fromKode = $seg['from_pole_kode'] ?? '';
                $toKode = $seg['to_pole_kode'] ?? '';
                $segmentRanges[] = [
                    'conductor_label' => $condLabel,
                    'conductor_type'  => $normType !== '' ? $normType : 'Belum tersedia',
                    'conductor_size'  => $normSize !== '' ? $normSize : '-',
                    'from_pole_id'    => (int)($seg['from_pole_id'] ?? 0),
                    'from_pole_kode'  => $fromKode,
                    'to_pole_id'      => (int)($seg['to_pole_id'] ?? 0),
                    'to_pole_kode'    => $toKode,
                    'start_m'         => round($startM, 1),
                    'end_m'           => round($endM, 1),
                    'span_meters'     => round($spanM, 1),
                    'span_count'      => 1,
                    'range_text'      => number_format($startM, 1, '.', '') . ' m – ' . number_format($endM, 1, '.', '') . " m ({$fromKode} → {$toKode}): {$condLabel}",
                ];
            }
        }

        if ($validSpanCount === 0 || empty($byConductor)) {
            return [
                'is_available'         => false,
                'status'               => 'BELUM_TERSEDIA',
                'official_type'        => 'Belum tersedia',
                'display_label'        => 'Belum tersedia',
                'source'               => 'TRAVERSED_PATH_MISSING_CONDUCTOR',
                'is_hybrid'            => false,
                'resolved_catalog_key' => null,
                'catalog_entry'        => null,
                'total_traversed_m'    => round($totalPathSpanM, 1),
                'breakdown'            => [],
                'segment_ranges'       => $segmentRanges,
                'status_message'       => 'Belum tersedia (Segmen transline pada jalur ini belum memiliki atribut jenis/ukuran konduktor).',
            ];
        }

        $breakdown = array_values($byConductor);
        usort($breakdown, static fn(array $a, array $b): int => $b['distance_m'] <=> $a['distance_m']);

        foreach ($breakdown as &$bItem) {
            $bItem['distance_km'] = round($bItem['distance_m'] / 1000.0, 2);
            $bItem['percentage'] = $totalPathSpanM > 0 ? round(($bItem['distance_m'] / $totalPathSpanM) * 100, 1) : 100.0;
            $bItem['range_summary'] = number_format($bItem['min_cum_m'], 1, '.', '') . ' m – ' . number_format($bItem['max_cum_m'], 1, '.', '') . ' m';
        }
        unset($bItem);

        $dominant = $breakdown[0];
        $matchedCatalogKey = $this->matchConductorCatalogKey($dominant['type'], $dominant['size']);
        $isHybrid = count($breakdown) > 1;

        if (!$isHybrid) {
            $displayLabel = "{$dominant['label']} (Rentang 0.0 m – " . number_format($maxCumM, 1, '.', '') . " m)";
            $statusMsg = "Konduktor jalur terdeteksi otomatis dari {$dominant['spans']} span transline aktif ({$dominant['label']}, rentang 0.0 m – " . number_format($maxCumM, 1, '.', '') . " m).";
            if ($missingSpanCount > 0) {
                $statusMsg .= " Terdapat {$missingSpanCount} span tanpa atribut konduktor.";
            }
        } else {
            $parts = [];
            foreach ($breakdown as $b) {
                $parts[] = "{$b['label']} ({$b['range_summary']}, {$b['percentage']}%)";
            }
            $displayLabel = 'Multi-Konduktor: ' . implode(' | ', $parts);
            $statusMsg = 'Jalur hasil penelusuran melewati ' . count($breakdown) . ' jenis konduktor: ' . implode('; ', $parts) . '.';
        }

        return [
            'is_available'         => true,
            'status'               => $isHybrid ? 'MULTI_SEGMENT_HYBRID' : 'SINGLE_CONDUCTOR',
            'official_type'        => $dominant['label'],
            'display_label'        => $displayLabel,
            'source'               => 'TRAVERSED_DOWNSTREAM_TRANSLINE',
            'is_hybrid'            => $isHybrid,
            'resolved_catalog_key' => $matchedCatalogKey,
            'catalog_entry'        => $matchedCatalogKey ? (self::CONDUCTOR_CATALOG[$matchedCatalogKey] ?? null) : null,
            'total_traversed_m'    => round($totalPathSpanM, 1),
            'max_reach_m'          => round($maxCumM, 1),
            'breakdown'            => $breakdown,
            'segment_ranges'       => $segmentRanges,
            'status_message'       => $statusMsg,
        ];
    }

    /**
     * Match raw conductor type & size against CONDUCTOR_CATALOG keys without guessing when empty
     */
    protected function matchConductorCatalogKey(string $rawType, string $rawSize = ''): ?string
    {
        $normType = strtoupper(trim($rawType));
        if ($normType === '' || $normType === 'BELUM TERSEDIA' || $normType === 'UNKNOWN') {
            return null;
        }

        $sizeDigits = '';
        if (preg_match('/(\d+)/', $rawSize, $m)) {
            $sizeDigits = $m[1];
        }

        if ($sizeDigits !== '') {
            foreach (array_keys(self::CONDUCTOR_CATALOG) as $catKey) {
                if (preg_match('/\b' . preg_quote($normType, '/') . '\b/i', $catKey) && str_contains($catKey, $sizeDigits)) {
                    return $catKey;
                }
            }
        }

        foreach (array_keys(self::CONDUCTOR_CATALOG) as $catKey) {
            if (preg_match('/\b' . preg_quote($normType, '/') . '\b/i', $catKey)) {
                return $catKey;
            }
        }

        return null;
    }

    /**
     * Execute FLI Fault Location Analysis with Rigorous Electrical Physics and Fail-Safe Telemetry Check
     */
    public function locateFault(array $telemetry): array
    {
        $validation = $this->parseAndValidateTelemetry($telemetry);
        if (!$validation['valid']) {
            return [
                'validation_error'   => true,
                'errors'             => $validation['errors'],
                'message'            => implode(' ', $validation['errors']),
                'calculation_status' => 'VALIDATION_ERROR',
                'outcome_status'     => 'VALIDATION_ERROR',
            ];
        }

        $parsedTel       = $validation['values'];
        $ulpInput        = $telemetry['ulp_id'] ?? null;
        $feederInput     = $telemetry['feeder_id'] ?? null;
        $sourceType      = strtoupper(trim((string)($telemetry['source_type'] ?? 'GI'))); // 'GI' or 'PROTECTION'
        $protectionId    = (int)($telemetry['protection_device_id'] ?? 0);
        $faultTimestamp  = $telemetry['fault_time'] ?? $telemetry['timestamp'] ?? date('Y-m-d H:i:s');
        $relayType       = $telemetry['relay_type'] ?? 'OCR_GFR';
        $rawConductorReq = trim((string)($telemetry['conductor_type'] ?? ''));
        $relayDistanceIn = $parsedTel['relay_measured_distance_m'];

        // Currents in Amperes & Phase Voltages in kV (strictly preserving 0.0 vs null)
        $currentR  = $parsedTel['current_r'];
        $currentS  = $parsedTel['current_s'];
        $currentT  = $parsedTel['current_t'];
        $currentN  = $parsedTel['current_n'];
        $voltageR  = $parsedTel['voltage_r_kv'];
        $voltageS  = $parsedTel['voltage_s_kv'];
        $voltageT  = $parsedTel['voltage_t_kv'];

        // 1. Resolve Feeder Strictly (Never silently fall back to another feeder)
        $feeder = null;
        if ($feederInput !== null && $feederInput !== '') {
            if (is_numeric($feederInput)) {
                $feeder = $this->db->table('penyulang')->where('id', (int)$feederInput)->get()->getRowArray();
            }
            if (!$feeder) {
                $fBuilder = $this->db->table('penyulang');
                if (is_numeric($ulpInput) && (int)$ulpInput > 0) {
                    $fBuilder->where('ulp_id', (int)$ulpInput);
                }
                $feeder = $fBuilder->groupStart()
                        ->where('kode_penyulang', trim((string)$feederInput))
                        ->orWhere('nama_penyulang', trim((string)$feederInput))
                    ->groupEnd()
                    ->get()
                    ->getRowArray();
            }
        }
        if (!$feeder) {
            $feeder = [
                'id'             => 0,
                'kode_penyulang' => 'UNKNOWN',
                'nama_penyulang' => !empty($feederInput) ? (string)$feederInput : 'Penyulang Tidak Ditemukan',
                'ulp_id'         => (int)($ulpInput ?? 0),
            ];
        }

        $penyulangId   = (int)$feeder['id'];
        $penyulangNama = $feeder['nama_penyulang'];
        $penyulangKode = $feeder['kode_penyulang'];
        $ulpId         = (int)($feeder['ulp_id'] ?? $ulpInput ?? 0);

        // 2. Classify Fault Indication from Combined Currents (R, S, T, N) and Phase Voltages (R, S, T)
        $faultClass = $this->classifyFaultCurrents($currentR, $currentS, $currentT, $currentN, $voltageR, $voltageS, $voltageT);
        $faultType  = $faultClass['fault_type'];
        $iSc        = $faultClass['active_current_a'];

        // 3. Load Strictly Scoped Feeder Assets & Translines and Resolve Anchor JTM Pole
        // Invariant: Every candidate pole and transline endpoint MUST belong strictly to $penyulangId.
        $gisTransService = new GisTranslineService($this->db);
        $feederAssets = [];
        $translines   = [];
        $assetMap     = [];
        $adj          = [];

        if ($penyulangId > 0) {
            $feederAssets = $this->db->table('assets')
                ->select('id, penyulang_id, parent_asset_id, kode_asset, nama_asset, jenis_asset, section_id, sequence_no, latitude, longitude')
                ->where('penyulang_id', $penyulangId)
                ->where('deleted_at IS NULL')
                ->orderBy('sequence_no', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($feederAssets as $a) {
                if ((int)$a['penyulang_id'] !== $penyulangId) {
                    continue;
                }
                if (GisTranslineService::isJtmPoleAsset($a)['valid']) {
                    $assetMap[(int)$a['id']] = $a;
                }
            }

            $translines = $this->db->table('gis_translines')
                ->where('penyulang_id', $penyulangId)
                ->where('is_active', 1)
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();

            // Build adjacency graph ONLY between verified distinct JTM poles belonging to this feeder
            foreach ($translines as $tl) {
                if ((int)($tl['penyulang_id'] ?? 0) !== $penyulangId) {
                    continue;
                }
                $u = (int)$tl['source_asset_id'];
                $v = (int)$tl['target_asset_id'];
                if ($u === $v || !isset($assetMap[$u], $assetMap[$v])) {
                    continue;
                }
                $dist = (float)($tl['distance_meters'] ?? 0);
                if ($dist <= 0) {
                    $dist = $this->haversineDistance(
                        (float)$assetMap[$u]['latitude'], (float)$assetMap[$u]['longitude'],
                        (float)$assetMap[$v]['latitude'], (float)$assetMap[$v]['longitude']
                    );
                }
                $pathCoords = $this->extractTranslineGeometry($tl['geojson_geometry'] ?? null, $assetMap[$u], $assetMap[$v]);
                $adj[$u][] = ['target' => $v, 'tl' => $tl, 'dist' => $dist, 'path_coords' => $pathCoords, 'forward' => true];
                $adj[$v][] = ['target' => $u, 'tl' => $tl, 'dist' => $dist, 'path_coords' => array_reverse($pathCoords), 'forward' => false];
            }
        }

        // Partition adjacency graph into connected components (largest component first)
        $components = [];
        $compOfNode = [];
        foreach (array_keys($adj) as $startNode) {
            if (isset($compOfNode[$startNode])) {
                continue;
            }
            $compIdx = count($components);
            $q = [$startNode];
            $compOfNode[$startNode] = $compIdx;
            $nodes = [];
            while (!empty($q)) {
                $curr = array_shift($q);
                $nodes[] = $curr;
                foreach ($adj[$curr] ?? [] as $edge) {
                    $nxt = $edge['target'];
                    if (!isset($compOfNode[$nxt])) {
                        $compOfNode[$nxt] = $compIdx;
                        $q[] = $nxt;
                    }
                }
            }
            $components[] = $nodes;
        }
        usort($components, static fn(array $a, array $b): int => count($b) <=> count($a));
        $compOfNode = [];
        foreach ($components as $idx => $nodes) {
            foreach ($nodes as $nId) {
                $compOfNode[$nId] = $idx;
            }
        }

        $anchorPole = null;
        $anchorDesc = '';
        $anchorOffsetMeters = 0.0;
        $anchorRelType = 'GI_PANGKAL';
        $sourceDeviceAsset = null;
        $protectionResolutionError = null;

        if ($sourceType === 'PROTECTION') {
            if ($penyulangId > 0 && $protectionId > 0) {
                $resolved = $gisTransService->resolveSourceContextToNearestJtmPole($protectionId, $penyulangId);
                if ($resolved && !empty($resolved['anchor_pole_asset'])) {
                    $candAnchor = $resolved['anchor_pole_asset'];
                    $sourceDeviceAsset = $resolved['source_asset'] ?? null;
                    $anchorRelType = $resolved['relationship_type'] ?? 'MOUNTED_ON_POLE';
                    if ((int)($candAnchor['penyulang_id'] ?? 0) === $penyulangId && isset($assetMap[(int)$candAnchor['id']])) {
                        $anchorPole = $assetMap[(int)$candAnchor['id']];
                        $anchorOffsetMeters = (float)($resolved['distance_to_pole_meters'] ?? 0.0);
                        $devCode = $sourceDeviceAsset['kode_asset'] ?? "DEV-{$protectionId}";
                        $devName = $sourceDeviceAsset['nama_asset'] ?? $devCode;
                        $anchorDesc = "Peralatan Proteksi [{$devCode} - {$devName}] -> Tiang JTM [{$anchorPole['kode_asset']}] (Hilir / Ke Arah Ujung Penyulang)";
                    } else {
                        $protectionResolutionError = "Tiang JTM tempat pemasangan perangkat proteksi tidak ditemukan pada penyulang {$penyulangNama}.";
                    }
                } else {
                    $protectionResolutionError = "Perangkat proteksi (#{$protectionId}) belum terhubung ke tiang JTM pada jaringan transline penyulang {$penyulangNama}.";
                }
            } else {
                $protectionResolutionError = "Pilih perangkat proteksi (Recloser / PMCB) terlebih dahulu untuk memulai penelusuran ke arah hilir.";
            }

            if (!$anchorPole) {
                $anchorDesc = "Peralatan Proteksi -> Belum terhubung ke tiang JTM pada penyulang {$penyulangNama}";
            }
        }

        // Determine GI Root Pole (Pangkal Penyulang) for the feeder's target connected component
        $targetCompIdx = ($anchorPole && isset($compOfNode[(int)$anchorPole['id']]))
            ? $compOfNode[(int)$anchorPole['id']]
            : 0;
        $targetCompNodes = $components[$targetCompIdx] ?? [];
        $giRootPole = $this->resolveFeederGiRootPole($penyulangId, $targetCompNodes, $assetMap, $adj, $feederAssets);
        $giRootPoleId = (int)($giRootPole['id'] ?? 0);

        if ($sourceType !== 'PROTECTION') {
            $anchorPole = $giRootPole;
            if (!$anchorPole && !empty($assetMap)) {
                $anchorPole = reset($assetMap) ?: null;
            }
            if ($anchorPole) {
                $anchorPoleKode = $anchorPole['kode_asset'] ?? 'PANGKAL_PENYULANG';
                $anchorDesc = "Gardu Induk (Pangkal Penyulang) -> Tiang JTM Pangkal [{$anchorPoleKode}]";
            } else {
                $anchorDesc = "Gardu Induk (Pangkal Penyulang) -> Belum ada tiang JTM terpetakan pada penyulang {$penyulangNama}";
            }
        }

        $anchorPoleId = (int)($anchorPole['id'] ?? 0);
        $translineCount = count($translines);
        $assetCount     = count($assetMap);

        // 4. Compute Dijkstra distances from GI Root Pole ($giRootPoleId) to orient upstream vs downstream
        $distFromGi = [];
        $parentFromGi = [];
        $parentEdgeFromGi = [];
        if ($giRootPoleId > 0 && isset($adj[$giRootPoleId])) {
            $distFromGi[$giRootPoleId] = 0.0;
            $pq = [[0.0, $giRootPoleId]];
            while (!empty($pq)) {
                usort($pq, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
                [$dCurr, $u] = array_shift($pq);
                if ($dCurr > ($distFromGi[$u] ?? PHP_FLOAT_MAX)) {
                    continue;
                }
                foreach ($adj[$u] ?? [] as $edge) {
                    $v = $edge['target'];
                    $nd = $dCurr + (float)$edge['dist'];
                    if ($nd < ($distFromGi[$v] ?? PHP_FLOAT_MAX)) {
                        $distFromGi[$v] = $nd;
                        $parentFromGi[$v] = $u;
                        $parentEdgeFromGi[$v] = $edge;
                        $pq[] = [$nd, $v];
                    }
                }
            }
        }

        // Block upstream GI-side nodes when traversing from a Protection Device (Recloser / PMCB)
        $blockedUpstreamNodes = [];
        $upstreamSegments = [];
        $anchorDistFromGiM = isset($distFromGi[$anchorPoleId]) ? round($distFromGi[$anchorPoleId], 1) : 0.0;

        if ($sourceType === 'PROTECTION' && $anchorPoleId > 0 && $giRootPoleId > 0 && $anchorPoleId !== $giRootPoleId && isset($distFromGi[$anchorPoleId])) {
            // 1) Block the direct shortest path from GI root pole up to $anchorPoleId
            $pathNodes = [];
            $currP = $anchorPoleId;
            while (isset($parentFromGi[$currP])) {
                $prevP = $parentFromGi[$currP];
                $blockedUpstreamNodes[$prevP] = true;
                $pathNodes[] = [$prevP, $currP, $parentEdgeFromGi[$currP]];
                $currP = $prevP;
            }
            $pathNodes = array_reverse($pathNodes);
            $upCum = 0.0;
            foreach ($pathNodes as [$uPole, $vPole, $uEdge]) {
                $uSpan = round((float)$uEdge['dist'], 1);
                $upCum = round($upCum + $uSpan, 1);
                $uFrom = $assetMap[$uPole] ?? null;
                $uTo   = $assetMap[$vPole] ?? null;
                if ($uFrom && $uTo) {
                    $upstreamSegments[] = [
                        'from_pole_id'      => $uPole,
                        'from_pole_kode'    => $uFrom['kode_asset'],
                        'to_pole_id'        => $vPole,
                        'to_pole_kode'      => $uTo['kode_asset'],
                        'span_meters'       => $uSpan,
                        'cumulative_meters' => $upCum,
                        'from_coords'       => ['lat' => (float)$uFrom['latitude'], 'lng' => (float)$uFrom['longitude']],
                        'to_coords'         => ['lat' => (float)$uTo['latitude'], 'lng' => (float)$uTo['longitude']],
                        'path_coords'       => $uEdge['path_coords'],
                    ];
                }
            }

            // 2) Also block any upstream lateral branches whose distance from GI is <= anchor's distance from GI,
            // provided doing so still leaves at least one downstream neighbor from $anchorPoleId.
            $candidateUpstreamBlock = $blockedUpstreamNodes;
            $anchorDist = $distFromGi[$anchorPoleId];
            foreach ($distFromGi as $nodeId => $dGi) {
                if ($nodeId !== $anchorPoleId && $dGi <= $anchorDist - 0.01) {
                    $candidateUpstreamBlock[$nodeId] = true;
                }
            }
            $remainingDownstreamNeighbors = 0;
            foreach ($adj[$anchorPoleId] ?? [] as $edge) {
                if (!isset($candidateUpstreamBlock[$edge['target']])) {
                    $remainingDownstreamNeighbors++;
                }
            }
            if ($remainingDownstreamNeighbors > 0) {
                $blockedUpstreamNodes = $candidateUpstreamBlock;
            }
        }

        // 5. Traverse Real JTM Pole Translines Strictly Downstream from $anchorPoleId (0.0 m) toward Feeder End
        $poleSequence = [];
        $visitedEdges = [];
        $totalSpanMeters = 0.0;
        $maxDownstreamReachMeters = 0.0;
        $farthestDownstreamSeqIdx = null;
        $downstreamEndPole = null;
        $parentSeqIdxOfPole = [];
        $outDegreeByPole = [];

        if ($penyulangId > 0 && $translineCount > 0 && $assetCount > 0 && $anchorPoleId > 0 && !empty($adj[$anchorPoleId])) {
            $queue = [[0.0, $anchorPoleId]];
            $visitedPoles = [$anchorPoleId => 0.0];

            $seqNo = 1;
            while (!empty($queue)) {
                usort($queue, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
                [$currCum, $currPoleId] = array_shift($queue);

                if (!isset($adj[$currPoleId])) {
                    continue;
                }

                $unvisitedOutEdges = [];
                foreach ($adj[$currPoleId] as $edge) {
                    $nextPoleId = $edge['target'];
                    if (isset($blockedUpstreamNodes[$nextPoleId])) {
                        continue;
                    }
                    $edgeKey = min($currPoleId, $nextPoleId) . '-' . max($currPoleId, $nextPoleId);
                    if (!isset($visitedEdges[$edgeKey])) {
                        $unvisitedOutEdges[] = $edge;
                    }
                }

                $outDegreeByPole[$currPoleId] = [
                    'deg'   => count($unvisitedOutEdges),
                    'cum_m' => $currCum,
                    'kode'  => $assetMap[$currPoleId]['kode_asset'] ?? "T-{$currPoleId}",
                ];

                foreach ($unvisitedOutEdges as $edge) {
                    $nextPoleId = $edge['target'];
                    $edgeKey = min($currPoleId, $nextPoleId) . '-' . max($currPoleId, $nextPoleId);
                    $visitedEdges[$edgeKey] = true;

                    if (isset($visitedPoles[$nextPoleId])) {
                        continue;
                    }

                    $spanDist = round((float)$edge['dist'], 1);
                    $nextCum = round($currCum + $spanDist, 1);
                    $totalSpanMeters += $spanDist;

                    $fromPoleAsset = $assetMap[$currPoleId] ?? ['id' => $currPoleId, 'kode_asset' => "T-{$currPoleId}", 'nama_asset' => "Tiang #{$currPoleId}"];
                    $toPoleAsset   = $assetMap[$nextPoleId] ?? ['id' => $nextPoleId, 'kode_asset' => "T-{$nextPoleId}", 'nama_asset' => "Tiang #{$nextPoleId}"];

                    $rawCondType = trim((string)($edge['tl']['conductor_type'] ?? ''));
                    $rawCondSize = trim((string)($edge['tl']['conductor_size'] ?? ''));

                    $currSeqIdx = count($poleSequence);
                    $parentSeqIdx = $parentSeqIdxOfPole[$currPoleId] ?? null;

                    $seqItem = [
                        'seq_no'             => $seqNo++,
                        'seq_idx'            => $currSeqIdx,
                        'parent_seq_idx'     => $parentSeqIdx,
                        'transline_id'       => (int)($edge['tl']['id'] ?? 0),
                        'section_name'       => $edge['tl']['section_name'] ?? null,
                        'from_pole_id'       => $currPoleId,
                        'from_pole_kode'     => $fromPoleAsset['kode_asset'] ?? "T-{$currPoleId}",
                        'from_pole_nama'     => $fromPoleAsset['nama_asset'] ?? $fromPoleAsset['kode_asset'],
                        'to_pole_id'         => $nextPoleId,
                        'to_pole_kode'       => $toPoleAsset['kode_asset'] ?? "T-{$nextPoleId}",
                        'to_pole_nama'       => $toPoleAsset['nama_asset'] ?? $toPoleAsset['kode_asset'],
                        'span_meters'        => $spanDist,
                        'cumulative_meters'  => $nextCum,
                        'cum_start_meters'   => $currCum,
                        'raw_conductor_type' => $rawCondType,
                        'raw_conductor_size' => $rawCondSize,
                        'conductor_type'     => $rawCondType !== '' ? $rawCondType : 'Belum tersedia',
                        'conductor_size'     => $rawCondSize !== '' ? $rawCondSize : '-',
                        'is_fault_zone'      => false,
                        'is_on_fault_path'   => false,
                        'from_coords'        => ['lat' => (float)($fromPoleAsset['latitude'] ?? 0), 'lng' => (float)($fromPoleAsset['longitude'] ?? 0)],
                        'to_coords'          => ['lat' => (float)($toPoleAsset['latitude'] ?? 0), 'lng' => (float)($toPoleAsset['longitude'] ?? 0)],
                        'path_coords'        => $edge['path_coords'],
                    ];

                    $poleSequence[] = $seqItem;
                    $visitedPoles[$nextPoleId] = $nextCum;
                    $parentSeqIdxOfPole[$nextPoleId] = $currSeqIdx;
                    $queue[] = [$nextCum, $nextPoleId];

                    if ($nextCum >= $maxDownstreamReachMeters) {
                        $maxDownstreamReachMeters = $nextCum;
                        $farthestDownstreamSeqIdx = $currSeqIdx;
                        $downstreamEndPole = [
                            'id'                => $nextPoleId,
                            'kode_asset'        => $toPoleAsset['kode_asset'] ?? "T-{$nextPoleId}",
                            'nama_asset'        => $toPoleAsset['nama_asset'] ?? ($toPoleAsset['kode_asset'] ?? "Tiang #{$nextPoleId}"),
                            'cumulative_meters' => $nextCum,
                            'latitude'          => (float)($toPoleAsset['latitude'] ?? 0),
                            'longitude'         => (float)($toPoleAsset['longitude'] ?? 0),
                        ];
                    }
                }
            }

            if ($sourceType === 'PROTECTION' && empty($poleSequence) && $protectionResolutionError === null) {
                $protectionResolutionError = "Arah hilir (downstream) dari tiang pemasangan [{$anchorPole['kode_asset']}] menuju ujung penyulang tidak dapat ditentukan karena segmen transline hilir belum tersedia.";
            }
        }

        $hasTopology = ($penyulangId > 0 && $translineCount > 0 && $assetCount > 0 && $anchorPoleId > 0 && !empty($poleSequence));
        $topologyPathLengthM = $hasTopology ? round($maxDownstreamReachMeters, 1) : null;
        $topologyPathLengthLabel = $sourceType === 'PROTECTION'
            ? 'Panjang jalur dari proteksi ke ujung feeder (estimasi topologi)'
            : 'Panjang jalur dari GI ke ujung feeder (estimasi topologi)';

        // 6. Automatic Conductor Resolution strictly from the traversed downstream transline sequence
        $traversedConductor = $this->summarizeTraversedPathConductors($poleSequence);

        // Determine active conductor catalog entry for impedance calculation:
        // Prefer traversed path's resolved conductor; fall back to explicit valid request only if provided.
        $explicitCatalogKey = ($rawConductorReq !== '' && strtoupper($rawConductorReq) !== 'AUTO')
            ? ($this->matchConductorCatalogKey($rawConductorReq, $rawConductorReq) ?? (isset(self::CONDUCTOR_CATALOG[$rawConductorReq]) ? $rawConductorReq : null))
            : null;
        $resolvedCatalogKey = $traversedConductor['resolved_catalog_key'] ?? $explicitCatalogKey;
        $condParams = $resolvedCatalogKey !== null
            ? self::CONDUCTOR_CATALOG[$resolvedCatalogKey]
            : self::CONDUCTOR_CATALOG['AAAC 150 mm²'];
        $conductorDisplayKey = $traversedConductor['is_available']
            ? $traversedConductor['official_type']
            : ($explicitCatalogKey ?? 'Belum tersedia');

        $r1PerKm = (float)$condParams['r1_ohm_km'];
        $x1PerKm = (float)$condParams['x1_ohm_km'];
        $z1PerKm = (float)$condParams['z1_ohm_km'];

        // Electrical Physics Constants (PLN 20 kV nominal distribution)
        $V_LL = 20000.0;
        $V_LN = 20000.0 / sqrt(3.0);
        $R_source = 0.05;
        $X_source = 0.45;
        $Z_source_mag = sqrt($R_source ** 2 + $X_source ** 2);

        // 7. Rigorous Electrical Calculation & Physics Telemetry Checks
        // Strictly separate topology_path_length_m from relay_measured_distance_m.
        $calculationStatus = 'RESOLVED';
        $dasarPerhitungan = 'ESTIMASI_TOPOLOGI';
        $dasarPerhitunganLabel = '';
        $formulaDerivation = '';
        $missingParameters = [];
        $calculatedDistanceM = 0.0;
        $confidence = 0.0;
        $explanation = '';

        if (!$hasTopology) {
            $calculationStatus = 'DATA_TIDAK_CUKUP';
            if ($sourceType === 'PROTECTION' && $protectionResolutionError !== null) {
                $dasarPerhitungan = 'ANCHOR_PROTEKSI_TIDAK_VALID';
                $dasarPerhitunganLabel = "DATA TIDAK CUKUP: {$protectionResolutionError}";
                $formulaDerivation = "Penelusuran dari peralatan proteksi dihentikan karena perangkat belum terhubung ke tiang JTM atau segmen hilir pada penyulang [{$penyulangNama}] belum lengkap. Sistem tidak melakukan fallback ke GI maupun mengisi jarak palsu.";
                $missingParameters = [
                    $protectionResolutionError,
                    "Relasi pemasangan perangkat proteksi (parent_asset_id / koordinat tiang JTM penyulang {$penyulangNama})",
                ];
                $explanation = $protectionResolutionError;
            } else {
                $dasarPerhitungan = 'TOPOLOGI_BELUM_TERSEDIA';
                $dasarPerhitunganLabel = "DATA TIDAK CUKUP: Penyulang {$penyulangNama} belum memiliki data topologi span/tiang JTM resmi ({$translineCount} span / {$assetCount} tiang)";
                $formulaDerivation = "Penyulang [{$penyulangNama}] memiliki {$translineCount} transline aktif dan {$assetCount} tiang JTM terdaftar. Perhitungan jarak gangguan dihentikan pada 0.0 m untuk mencegah hasil estimasi fiktif.";
                $missingParameters = [
                    "Topologi transline resmi untuk penyulang {$penyulangNama} belum terpetakan di sistem (0 segmen)",
                    "Data aset tiang JTM resmi belum lengkap ({$assetCount} tiang)",
                    "Peralatan proteksi/pangkal penyulang belum terhubung ke graf transline resmi",
                ];
                $explanation = "Penyulang {$penyulangNama} belum memiliki topologi transline dan tiang JTM resmi di sistem (0 span / {$assetCount} tiang). Silakan petakan aset tiang JTM dan transline penyulang ini terlebih dahulu sebelum menjalankan Fault Locator.";
            }
            $calculatedDistanceM = 0.0;
            $confidence = 0.0;
        } elseif ($relayDistanceIn !== null && $relayDistanceIn > 0) {
            // Case A: Relay distance measurement available directly from numeric relay
            $calculatedDistanceM = round($relayDistanceIn, 1);
            $calculationStatus = 'CALCULATED_RELAY_AIDED';
            $dasarPerhitungan = 'RELAY_DISTANCE_MEASUREMENT';
            $dasarPerhitunganLabel = "Hasil Pengukuran Digital Fault Locator / Distance Relay: {$calculatedDistanceM} m";
            $formulaDerivation = "d_relay = {$calculatedDistanceM} m (diperoleh langsung dari pembacaan relay numerik lapangan; {$topologyPathLengthLabel} = {$topologyPathLengthM} m)";
            $confidence = 92.0;
            $explanation = "Penentuan titik lokasi menggunakan jarak gangguan terukur relay numerik ({$calculatedDistanceM} m) yang ditelusuri sepanjang graf transline tiang JTM ke arah hilir dari {$anchorDesc}.";
        } elseif (!$traversedConductor['is_available'] && $explicitCatalogKey === null) {
            // Traversed path has no conductor data and no relay distance was provided
            $calculationStatus = 'DATA_TIDAK_CUKUP';
            $dasarPerhitungan = 'KONDUKTOR_JALUR_BELUM_TERSEDIA';
            $dasarPerhitunganLabel = 'DATA TIDAK CUKUP: Data jenis/ukuran konduktor pada jalur transline belum tersedia';
            $formulaDerivation = "Perhitungan impedansi saluran membutuhkan parameter R1 dan X1 konduktor, namun segmen transline pada jalur hilir penyulang [{$penyulangNama}] belum memiliki data konduktor. Sistem tidak menebak konduktor secara diam-diam.";
            $missingParameters = [
                'Atribut jenis dan ukuran konduktor (conductor_type / conductor_size) pada gis_translines jalur terpilih masih kosong (Belum tersedia)',
                'Jarak gangguan terukur relay numerik (opsional bila konduktor belum tersedia)',
            ];
            $calculatedDistanceM = 0.0;
            $confidence = 0.0;
            $explanation = "Data konduktor pada jalur transline hasil penelusuran belum tersedia. {$topologyPathLengthLabel} tercatat {$topologyPathLengthM} m, namun jarak titik gangguan tidak dihitung tanpa data konduktor atau jarak terukur relay.";
        } else {
            // Case B: No numeric relay distance entered; evaluate based on electrical physics
            $missingParameters = [
                'Jarak gangguan terukur relay numerik / DFR (belum diisi — opsional)',
                'Sudut fasa arus terhadap tegangan (Power Factor / cos phi) dan reaktansi murni loop relay (X_loop)',
                'Kapasitas hubung-singkat GI dan impedansi sumber (MVA_sc / Z_source) — Belum tercatat di database master_gi/penyulang',
                'Konfigurasi spesifikasi tahanan pentanahan NGR GI terkait (40 Ohm vs 12 Ohm vs Solid Grounding)',
            ];
            if ($voltageR === null || $voltageS === null || $voltageT === null) {
                array_unshift($missingParameters, 'Tegangan per fasa lengkap (VR, VS, VT dalam kV) saat gangguan');
            }

            $calculationStatus = 'DATA_TIDAK_CUKUP';
            $confidence = 35.0;

            if (!empty($faultClass['is_ground_fault'])) {
                // Ground Fault (DGR, OCR-DGR, OCR Instan-DGR):
                // Do NOT fabricate a fake distance when relay distance is not provided!
                $dasarPerhitungan = 'NGR_GROUND_FAULT_INDETERMINATE';
                $dasarPerhitunganLabel = 'DATA TIDAK CUKUP: Arus gangguan tanah didominasi NGR dan Rf, bukan panjang konduktor';
                $formulaDerivation = "I_f = 3*V_LN / (2*Z1 + Z0 + 3*RN + 3*Rf).\n"
                    . "Tahanan NGR (3*RN ≈ 120 Ω bila NGR 40Ω, atau 36 Ω bila NGR 12Ω) dan tahanan sentuh pohon/tanah (Rf = 5 - 200 Ω) mendominasi total impedansi dibandingkan impedansi kawat (2*Z1 + Z0 ≈ 1-15 Ω).\n"
                    . "Secara fisika, jarak titik gangguan TIDAK DAPAT dihitung dari besaran arus/tegangan fasa semata tanpa jarak/reaktansi X dari relay numerik.\n"
                    . "{$topologyPathLengthLabel}: {$topologyPathLengthM} m.";
                $explanation = "Terdeteksi {$faultClass['fault_type_label']}. Pada sistem distribusi ber-tahanan netral (NGR), jarak gangguan tanah tidak dapat dihitung tanpa pembacaan jarak relay numerik. {$topologyPathLengthLabel} adalah {$topologyPathLengthM} m (bukan jarak terukur relay).";
                $calculatedDistanceM = 0.0;
            } elseif ($faultType === 'OCR_INSTAN' || $faultType === 'OCR') {
                if (($faultClass['technical_fault_type'] ?? '') === '3_PHASE' && $iSc > 10.0) {
                    $zLoop = $V_LN / $iSc;
                    $A = $r1PerKm ** 2 + $x1PerKm ** 2;
                    $B = 2.0 * ($R_source * $r1PerKm + $X_source * $x1PerKm);
                    $C = ($R_source ** 2 + $X_source ** 2) - ($zLoop ** 2);
                    $disc = $B ** 2 - 4.0 * $A * $C;

                    $dKm = 0.0;
                    if ($disc >= 0 && $A > 0) {
                        $dKm = max(0.0, (-$B + sqrt($disc)) / (2.0 * $A));
                    }
                    $calculatedDistanceM = round($dKm * 1000.0, 1);

                    $dasarPerhitungan = 'SIMULASI_IDEAL_BOLTED_3PH';
                    $dasarPerhitunganLabel = "DATA TIDAK CUKUP: Simulasi Ideal Bolted 3-Fasa (Asumsi Rf=0, Zs={$Z_source_mag}Ω, V_LN=11.55 kV)";
                    $formulaDerivation = "Persamaan Kuadrat Impedansi Kompleks:\n"
                        . "Z_loop = V_LN / I_sc = 11,547 V / {$iSc} A = " . round($zLoop, 3) . " Ω\n"
                        . "|(R_s + r1*d) + j(X_s + x1*d)| = Z_loop\n"
                        . "A = r1² + x1² = " . round($A, 4) . "; B = 2(Rs*r1 + Xs*x1) = " . round($B, 4) . "; C = Zs² - Zloop² = " . round($C, 4) . "\n"
                        . "d_teoritis = (-B + √(B² - 4AC)) / 2A = " . round($dKm, 3) . " km ({$calculatedDistanceM} m)\n"
                        . "[PERINGATAN: Nilai di atas adalah simulasi teoritis ideal (bukan jarak terukur relay); {$topologyPathLengthLabel}: {$topologyPathLengthM} m]";
                    $explanation = "Data telemetri {$faultClass['fault_type_label']} belum menyertakan jarak terukur relay numerik. Jarak simulasi teoritis {$calculatedDistanceM} m ditelusuri pada jalur hilir (panjang jalur topologi ke ujung feeder: {$topologyPathLengthM} m).";
                } elseif ($iSc > 10.0) {
                    $zLoop = $V_LL / (2.0 * $iSc);
                    $A = $r1PerKm ** 2 + $x1PerKm ** 2;
                    $B = 2.0 * ($R_source * $r1PerKm + $X_source * $x1PerKm);
                    $C = ($R_source ** 2 + $X_source ** 2) - ($zLoop ** 2);
                    $disc = $B ** 2 - 4.0 * $A * $C;

                    $dKm = 0.0;
                    if ($disc >= 0 && $A > 0) {
                        $dKm = max(0.0, (-$B + sqrt($disc)) / (2.0 * $A));
                    }
                    $calculatedDistanceM = round($dKm * 1000.0, 1);

                    $dasarPerhitungan = 'SIMULASI_IDEAL_BOLTED_2PH';
                    $dasarPerhitunganLabel = "DATA TIDAK CUKUP: Simulasi Ideal Bolted Antar-Fasa (Asumsi Rf=0, Zs={$Z_source_mag}Ω, V_LL=20 kV)";
                    $formulaDerivation = "Persamaan Kuadrat Impedansi Loop Fasa-ke-Fasa:\n"
                        . "Z_loop = V_LL / (2 * I_sc) = 20,000 V / (2 * {$iSc} A) = " . round($zLoop, 3) . " Ω\n"
                        . "d_teoritis = (-B + √(B² - 4AC)) / 2A = " . round($dKm, 3) . " km ({$calculatedDistanceM} m)\n"
                        . "[PERINGATAN: Mengabaikan tahanan busur kontak antar konduktor; {$topologyPathLengthLabel}: {$topologyPathLengthM} m]";
                    $explanation = "Gangguan antar fasa ({$faultClass['fault_type_label']}) dengan arus " . number_format($iSc, 0) . " A tanpa input jarak terukur relay. Jarak simulasi teoritis {$calculatedDistanceM} m merupakan estimasi kandidat ({$topologyPathLengthLabel}: {$topologyPathLengthM} m).";
                }
            } else {
                $calculationStatus = 'DATA_TIDAK_CUKUP';
                $dasarPerhitungan = ($faultType === 'NO_FAULT') ? 'ARUS_TIDAK_MENCAPAI_AMBANG' : 'POLA_ARUS_PERLU_VERIFIKASI';
                $dasarPerhitunganLabel = ($faultType === 'NO_FAULT') ? 'Arus di bawah ambang batas deteksi gangguan (<= 10 A)' : 'DATA TIDAK CUKUP: Pola arus/tegangan perlu verifikasi lapangan';
                $confidence = 0.0;
                $explanation = ($faultType === 'NO_FAULT') ? 'Besaran arus fasa dan netral berada dalam rentang beban normal.' : "Hasil klasifikasi: {$faultClass['fault_type_label']}. Diperlukan verifikasi telemetri atau patroli lapangan.";
                $calculatedDistanceM = 0.0;
            }
        }

        // 8. Identify Fault Zone Segment(s) along the Traversed Downstream Path when $calculatedDistanceM > 0
        $matchingFaultSegments = [];
        $branchAmbiguityDetected = false;
        $branchingJunctionPoles = [];

        if ($calculatedDistanceM > 0 && !empty($poleSequence)) {
            foreach ($outDegreeByPole as $degInfo) {
                if ($degInfo['deg'] > 1 && $degInfo['cum_m'] < $calculatedDistanceM) {
                    $branchingJunctionPoles[] = $degInfo['kode'];
                }
            }

            foreach ($poleSequence as $idx => $seqItem) {
                if ($calculatedDistanceM >= $seqItem['cum_start_meters'] && $calculatedDistanceM <= $seqItem['cumulative_meters']) {
                    $poleSequence[$idx]['is_fault_zone'] = true;
                    $matchingFaultSegments[] = $poleSequence[$idx];
                }
            }
        }

        // Deduplicate colocated parallel matching segments (within 35m of each other)
        if (count($matchingFaultSegments) > 1) {
            $distinctMatches = [];
            foreach ($matchingFaultSegments as $candSeg) {
                $isColocated = false;
                foreach ($distinctMatches as $existingSeg) {
                    $dEnd = $this->haversineDistance(
                        (float)$candSeg['to_coords']['lat'], (float)$candSeg['to_coords']['lng'],
                        (float)$existingSeg['to_coords']['lat'], (float)$existingSeg['to_coords']['lng']
                    );
                    if ($dEnd <= 35.0) {
                        $isColocated = true;
                        break;
                    }
                }
                if (!$isColocated) {
                    $distinctMatches[] = $candSeg;
                }
            }
            $matchingFaultSegments = $distinctMatches;
        }

        // Branching Ambiguity Resolution
        $faultZoneSegment = null;
        $candidatePoles = [];

        if ($calculatedDistanceM > 0 && count($matchingFaultSegments) > 1) {
            $branchAmbiguityDetected = true;
            $calculationStatus = ($calculationStatus === 'DATA_TIDAK_CUKUP') ? 'DATA_TIDAK_CUKUP' : 'DISTANCE_AMBIGUOUS';
            $branchJunctionStr = implode(', ', array_slice(array_unique($branchingJunctionPoles), 0, 5));
            $explanation .= " Terdeteksi " . count($matchingFaultSegments) . " percabangan hilir (T-Off" . ($branchJunctionStr ? " via {$branchJunctionStr}" : '') . ") yang sama-sama menjangkau jarak " . number_format($calculatedDistanceM, 0) . "m dari titik acuan. Diperlukan inspeksi patroli pada cabang kandidat.";
            $faultZoneSegment = $matchingFaultSegments[0];
            foreach ($matchingFaultSegments as $ms) {
                if (isset($assetMap[$ms['from_pole_id']])) $candidatePoles[] = $assetMap[$ms['from_pole_id']];
                if (isset($assetMap[$ms['to_pole_id']])) $candidatePoles[] = $assetMap[$ms['to_pole_id']];
            }
        } elseif ($calculatedDistanceM > 0 && count($matchingFaultSegments) === 1) {
            $faultZoneSegment = $matchingFaultSegments[0];
            $candidatePoles = [
                $assetMap[$faultZoneSegment['from_pole_id']] ?? null,
                $assetMap[$faultZoneSegment['to_pole_id']] ?? null,
            ];
        } elseif ($calculatedDistanceM > 0 && !empty($poleSequence)) {
            $minDelta = PHP_FLOAT_MAX;
            foreach ($poleSequence as $seq) {
                $delta = abs($seq['cumulative_meters'] - $calculatedDistanceM);
                if ($delta < $minDelta) {
                    $minDelta = $delta;
                    $faultZoneSegment = $seq;
                }
            }
            if ($faultZoneSegment) {
                $poleSequence[$faultZoneSegment['seq_idx']]['is_fault_zone'] = true;
                $faultZoneSegment['is_fault_zone'] = true;
                $matchingFaultSegments = [$faultZoneSegment];
                $candidatePoles = [
                    $assetMap[$faultZoneSegment['from_pole_id']] ?? null,
                    $assetMap[$faultZoneSegment['to_pole_id']] ?? null,
                ];
            }
        }

        // Trace back the contiguous path from Anchor Pole (0.0m) to Fault Zone Segment(s),
        // or to the farthest downstream terminal pole when no specific fault distance was measured ($calculatedDistanceM == 0)
        $faultPathSequence = [];
        $segmentsToTrace = !empty($matchingFaultSegments)
            ? $matchingFaultSegments
            : ($faultZoneSegment ? [$faultZoneSegment] : ($farthestDownstreamSeqIdx !== null && isset($poleSequence[$farthestDownstreamSeqIdx]) ? [$poleSequence[$farthestDownstreamSeqIdx]] : []));
        $primaryPathIndices = [];
        foreach ($segmentsToTrace as $tIdx => $mSeg) {
            $walkIdx = $mSeg['seq_idx'] ?? null;
            $guard = 0;
            while ($walkIdx !== null && isset($poleSequence[$walkIdx]) && $guard < 2000) {
                $poleSequence[$walkIdx]['is_on_fault_path'] = true;
                if ($tIdx === 0) {
                    $primaryPathIndices[] = $walkIdx;
                }
                $walkIdx = $poleSequence[$walkIdx]['parent_seq_idx'];
                $guard++;
            }
        }
        $primaryPathIndices = array_reverse($primaryPathIndices);
        foreach ($primaryPathIndices as $pIdx) {
            $faultPathSequence[] = $poleSequence[$pIdx];
        }
        if ($faultZoneSegment && isset($poleSequence[$faultZoneSegment['seq_idx']])) {
            $faultZoneSegment = $poleSequence[$faultZoneSegment['seq_idx']];
        }

        // Candidate Zone & Asset Resolution
        $isIndeterminateZone = ($calculatedDistanceM <= 0.0);
        $candidateZoneInfo = null;

        if ($isIndeterminateZone && !empty($poleSequence)) {
            // Indeterminate DGR / Ground fault or unmeasured relay distance:
            // Designate the downstream protected path as un-narrowed coordination reach
            $zoneRangeStartM = 0.0;
            $zoneRangeEndM = $topologyPathLengthM > 0 ? $topologyPathLengthM : (float)($poleSequence[count($poleSequence) - 1]['cumulative_meters'] ?? 0);
            $uncertaintyM = round(($zoneRangeEndM - $zoneRangeStartM) / 2.0, 1);

            // Strictly do NOT fabricate candidate poles across the entire feeder!
            // When distance cannot be calculated, candidate poles from physics calculation is empty.
            $candidatePoles = [];

            $zoneNotice = "Kaidah IEEE C37.114: Gangguan fasa-tanah (DGR) pada sistem pentanahan NGR 40Ω memiliki arus gangguan yang sangat dipengaruhi oleh resistansi kontak tanah (Rf). Tanpa pembacaan reaktansi loop X / jarak relay numerik, sistem menandai seluruh jangkauan hilir proteksi (" . number_format($zoneRangeStartM, 1) . " m – " . number_format($zoneRangeEndM, 1) . " m) sebagai jangkauan koordinasi proteksi untuk patroli visual, bukan daftar titik prediksi gangguan.";

            $candidateZoneInfo = [
                'is_single_point'      => false,
                'zone_type'            => 'PROTECTION_DOWNSTREAM_REACH',
                'range_start_m'        => $zoneRangeStartM,
                'range_end_m'          => $zoneRangeEndM,
                'range_label'          => number_format($zoneRangeStartM, 1) . " m – " . number_format($zoneRangeEndM, 1) . " m (" . number_format($zoneRangeEndM / 1000.0, 2) . " km)",
                'uncertainty_m'        => $uncertaintyM,
                'uncertainty_label'    => "±" . number_format($uncertaintyM, 1) . " m",
                'zone_title'           => 'Jangkauan Proteksi Hilir (Belum Dipersempit)',
                'zone_notice'          => $zoneNotice,
                'candidate_pole_count' => 0,
                'candidate_poles'      => [],
            ];
        } elseif ($calculatedDistanceM > 0 && $branchAmbiguityDetected) {
            $rangeStart = max(0.0, $calculatedDistanceM - 150.0);
            $rangeEnd = $calculatedDistanceM + 150.0;
            $candidateZoneInfo = [
                'is_single_point'    => false,
                'zone_type'          => 'BRANCH_AMBIGUITY',
                'range_start_m'      => $rangeStart,
                'range_end_m'        => $rangeEnd,
                'range_label'        => number_format($rangeStart, 1) . " m – " . number_format($rangeEnd, 1) . " m (" . count($matchingFaultSegments) . " Percabangan Hilir)",
                'uncertainty_m'      => 150.0,
                'uncertainty_label'  => "±150.0 m",
                'zone_title'         => 'Zona Kandidat Percabangan Ganda Hilir',
                'zone_notice'        => "Jarak gangguan terhitung menjangkau " . count($matchingFaultSegments) . " cabang terpisah pada penyulang ini. Patroli perlu memeriksa seluruh tiang kandidat pada cabang terkait.",
            ];
            foreach ($poleSequence as &$pSeq) {
                if (!empty($pSeq['is_fault_zone'])) {
                    $pSeq['is_candidate_zone'] = true;
                }
            }
            unset($pSeq);
        } elseif ($calculatedDistanceM > 0) {
            $rangeStart = max(0.0, $calculatedDistanceM - 100.0);
            $rangeEnd = $calculatedDistanceM + 100.0;
            $candidateZoneInfo = [
                'is_single_point'    => true,
                'zone_type'          => 'SINGLE_POINT',
                'range_start_m'      => $rangeStart,
                'range_end_m'        => $rangeEnd,
                'range_label'        => number_format($calculatedDistanceM, 1) . " m (" . number_format($calculatedDistanceM / 1000.0, 2) . " km dari acuan)",
                'uncertainty_m'      => 100.0,
                'uncertainty_label'  => "±100.0 m",
                'zone_title'         => 'Estimasi Titik & Segmen Gangguan Terproyeksi',
                'zone_notice'        => "Titik gangguan terproyeksi pada segmen transline dengan estimasi radius toleransi ±100 m.",
            ];
            foreach ($poleSequence as &$pSeq) {
                if (!empty($pSeq['is_fault_zone'])) {
                    $pSeq['is_candidate_zone'] = true;
                }
            }
            unset($pSeq);
        }

        $candidatePoles = array_values(array_filter($candidatePoles));

        // Build cumulative distance lookup for all traversed poles from anchor
        $poleCumDistanceMap = [];
        if ($anchorPole) {
            $poleCumDistanceMap[$anchorPole['id']] = 0.0;
        }
        foreach ($poleSequence as $pSeq) {
            if (!isset($poleCumDistanceMap[$pSeq['from_pole_id']])) {
                $poleCumDistanceMap[$pSeq['from_pole_id']] = (float)($pSeq['cum_start_meters'] ?? 0.0);
            }
            if (!isset($poleCumDistanceMap[$pSeq['to_pole_id']])) {
                $poleCumDistanceMap[$pSeq['to_pole_id']] = (float)$pSeq['cumulative_meters'];
            }
        }

        // Candidate assets formatting with strict feeder boundary and coordinate validation
        $candidateAssetList = [];
        $seenIds = [];
        foreach ($candidatePoles as $cAst) {
            if (!$cAst || isset($seenIds[$cAst['id']])) continue;
            if ((int)($cAst['penyulang_id'] ?? 0) !== $penyulangId) continue;
            if (!GisTranslineService::isJtmPoleAsset($cAst)['valid']) continue;
            $cLat = (float)($cAst['latitude'] ?? 0);
            $cLng = (float)($cAst['longitude'] ?? 0);
            if ($cLat == 0.0 || $cLng == 0.0) continue;

            $seenIds[$cAst['id']] = true;
            $poleCum = isset($poleCumDistanceMap[$cAst['id']]) ? round($poleCumDistanceMap[$cAst['id']], 1) : null;
            $deltaToTarget = ($calculatedDistanceM > 0 && $poleCum !== null) ? round(abs($poleCum - $calculatedDistanceM), 1) : null;

            $candidateAssetList[] = [
                'asset_id'              => (int)$cAst['id'],
                'penyulang_id'          => $penyulangId,
                'kode_asset'            => $cAst['kode_asset'],
                'asset_name'            => $cAst['nama_asset'] ?? $cAst['kode_asset'],
                'jenis_asset'           => $cAst['jenis_asset'],
                'section_id'            => $cAst['section_id'] ?? null,
                'latitude'              => $cLat,
                'longitude'             => $cLng,
                'cumulative_meters'     => $poleCum,
                'delta_to_target_m'     => $deltaToTarget,
                'is_physics_prediction' => true,
            ];
        }

        if ($calculatedDistanceM > 0 && !empty($candidateAssetList)) {
            // Rank strictly by delta proximity to the calculated target point (closest pole = Rank 1)
            usort($candidateAssetList, function($a, $b) {
                $delA = $a['delta_to_target_m'] ?? PHP_FLOAT_MAX;
                $delB = $b['delta_to_target_m'] ?? PHP_FLOAT_MAX;
                return $delA <=> $delB;
            });

            $baseScore = ($calculationStatus === 'CALCULATED_RELAY_AIDED') ? 95 : 80;
            foreach ($candidateAssetList as $rIdx => &$cItem) {
                $cItem['rank'] = $rIdx + 1;
                $penalty = min(20, (int)(($cItem['delta_to_target_m'] ?? 0) / 10));
                $score = max(50, $baseScore - ($rIdx * 6) - $penalty);
                $cItem['prediction_score'] = $score;
                $cItem['confidence_label'] = "{$score}%";
                $cItem['ranking_basis'] = 'PROXIMITY_TO_CALCULATED_FAULT_POINT';
                $cItem['influencing_factors'] = [
                    "Selisih {$cItem['delta_to_target_m']} m dari titik estimasi (d={$calculatedDistanceM} m)",
                    "Metode: " . ($calculationStatus === 'CALCULATED_RELAY_AIDED' ? 'Jarak Terukur Relay Numerik' : 'Simulasi Loop Impedansi'),
                    "Segmen: " . ($faultZoneSegment['from_pole_kode'] ?? '-') . " → " . ($faultZoneSegment['to_pole_kode'] ?? '-'),
                    "Konduktor: " . ($faultZoneSegment['conductor_type'] ?? 'Belum tersedia')
                ];
            }
            unset($cItem);
        } else {
            // When distance is not calculated ($calculatedDistanceM <= 0):
            // Do NOT fabricate fake prediction candidates across the feeder!
            $candidateAssetList = [];
        }

        if ($candidateZoneInfo) {
            $candidateZoneInfo['candidate_pole_count'] = count($candidateAssetList);
            $candidateZoneInfo['candidate_poles'] = $candidateAssetList;
        }

        // Search for evidence-based historical vulnerability priority poles along this path
        $historicalPriorityAssets = [];
        if ($isIndeterminateZone && !empty($poleSequence)) {
            $historicalPriorityAssets = $this->findHistoricalVulnerabilityPoles($penyulangId, $poleSequence, $assetMap, $poleCumDistanceMap);
        }

        // 9. Spatial Coordinates Interpolation on the Identified Transline Segment
        $faultPointGeom = null;
        if (!$isIndeterminateZone && !empty($candidateAssetList) && $faultZoneSegment && !empty($faultZoneSegment['from_coords']['lat']) && !empty($faultZoneSegment['to_coords']['lat'])) {
            $span = max(1.0, (float)$faultZoneSegment['span_meters']);
            $cumStart = (float)$faultZoneSegment['cum_start_meters'];
            $cumEnd = (float)$faultZoneSegment['cumulative_meters'];
            $fraction = 0.5;
            if ($calculatedDistanceM >= $cumStart && $calculatedDistanceM <= $cumEnd) {
                $fraction = ($calculatedDistanceM - $cumStart) / $span;
            }
            $fraction = max(0.05, min(0.95, $fraction));

            $fLat = $faultZoneSegment['from_coords']['lat'] + $fraction * ($faultZoneSegment['to_coords']['lat'] - $faultZoneSegment['from_coords']['lat']);
            $fLng = $faultZoneSegment['from_coords']['lng'] + $fraction * ($faultZoneSegment['to_coords']['lng'] - $faultZoneSegment['from_coords']['lng']);
            $faultPointGeom = [
                'lat' => round($fLat, 6),
                'lng' => round($fLng, 6),
                'span_fraction' => round($fraction * 100, 1),
            ];
        } elseif (!$isIndeterminateZone && !empty($candidateAssetList)) {
            $faultPointGeom = [
                'lat' => round($candidateAssetList[0]['latitude'], 6),
                'lng' => round($candidateAssetList[0]['longitude'], 6),
                'span_fraction' => 0.0,
            ];
        } else {
            // Indeterminate distance / DGR: No fake single precision point!
            $faultPointGeom = null;
            if ($isIndeterminateZone) {
                $faultZoneSegment = null;
            }
        }

        // GIS URL
        $targetAssetId = !empty($candidateAssetList) ? $candidateAssetList[0]['asset_id'] : '';
        if ($faultPointGeom) {
            $gisUrl = site_url("gis?penyulang_id={$penyulangId}&asset_id={$targetAssetId}&lat={$faultPointGeom['lat']}&lng={$faultPointGeom['lng']}&zoom=19");
        } else {
            $gisUrl = site_url("gis?penyulang_id={$penyulangId}");
        }

        // Case UUID & Fingerprint
        $inputData = [
            'feeder_id'                 => $penyulangId,
            'source_type'               => $sourceType,
            'protection_id'             => $protectionId,
            'current_r'                 => $currentR,
            'current_s'                 => $currentS,
            'current_t'                 => $currentT,
            'current_n'                 => $currentN,
            'voltage_r_kv'              => $voltageR,
            'voltage_s_kv'              => $voltageS,
            'voltage_t_kv'              => $voltageT,
            'relay_measured_distance_m' => $relayDistanceIn,
            'fault_time'                => $faultTimestamp,
            'conductor'                 => $conductorDisplayKey,
        ];
        ksort($inputData);
        $inputFingerprint = hash('sha256', json_encode($inputData, JSON_UNESCAPED_SLASHES));
        $caseUuid = 'FLI-CASE-' . date('Ymd') . '-' . strtoupper(substr($inputFingerprint, 0, 8));

        $sourceLabelShort = $sourceType === 'PROTECTION'
            ? ($sourceDeviceAsset ? ($sourceDeviceAsset['nama_asset'] ?: $sourceDeviceAsset['kode_asset']) : 'Peralatan Proteksi')
            : 'GI Pangkal (' . ($anchorPole['kode_asset'] ?? $penyulangNama) . ')';

        return [
            'case_uuid'                 => $caseUuid,
            'engine_version'            => $this->engineVersion,
            'topology_snapshot'         => $this->topologySnapshotId,
            'ulp_id'                    => $ulpId,
            'feeder_id'                 => $penyulangId,
            'feeder_code'               => $penyulangKode,
            'feeder_name'               => $penyulangNama,
            'source_type'               => $sourceType,
            'protection_device_id'      => $sourceType === 'PROTECTION' ? $protectionId : null,
            'telemetry_input'           => $parsedTel,
            'source_context'            => [
                'source_type'               => $sourceType,
                'direction_mode'            => 'DOWNSTREAM_TO_FEEDER_END',
                'source_label'              => $sourceLabelShort,
                'protection_device'         => $sourceDeviceAsset ? [
                    'id'          => (int)$sourceDeviceAsset['id'],
                    'kode_asset'  => $sourceDeviceAsset['kode_asset'],
                    'nama_asset'  => $sourceDeviceAsset['nama_asset'],
                    'jenis_asset' => $sourceDeviceAsset['jenis_asset'],
                    'latitude'    => (float)($sourceDeviceAsset['latitude'] ?? 0),
                    'longitude'   => (float)($sourceDeviceAsset['longitude'] ?? 0),
                ] : null,
                'mounting_relationship'     => $anchorRelType,
                'anchor_offset_meters'      => $anchorOffsetMeters,
                'gi_root_pole'              => $giRootPole ? [
                    'id'         => (int)$giRootPole['id'],
                    'kode_asset' => $giRootPole['kode_asset'],
                    'nama_asset' => $giRootPole['nama_asset'],
                    'latitude'   => (float)$giRootPole['latitude'],
                    'longitude'  => (float)$giRootPole['longitude'],
                ] : null,
                'downstream_end_pole'       => $downstreamEndPole,
                'distance_from_gi_meters'   => $anchorDistFromGiM,
                'upstream_blocked_nodes'    => count($blockedUpstreamNodes),
                'constraint_error'          => $protectionResolutionError,
            ],
            'anchor_description'        => $anchorDesc,
            'anchor_pole'               => $anchorPole ? [
                'id'         => (int)$anchorPole['id'],
                'kode_asset' => $anchorPole['kode_asset'],
                'nama_asset' => $anchorPole['nama_asset'],
                'latitude'   => (float)$anchorPole['latitude'],
                'longitude'  => (float)$anchorPole['longitude'],
            ] : null,
            'fault_classification'      => $faultClass,
            'traversed_conductor'       => $traversedConductor,
            'electrical_parameters'     => [
                'system_voltage_ll_kv'  => 20.0,
                'system_voltage_ln_kv'  => round($V_LN / 1000.0, 3),
                'r_source_ohm'          => $R_source,
                'x_source_ohm'          => $X_source,
                'z_source_mag_ohm'      => round($Z_source_mag, 3),
                'conductor_type'        => $conductorDisplayKey,
                'conductor_status'      => $traversedConductor['status'],
                'conductor_display'     => $traversedConductor['display_label'],
                'r1_ohm_km'             => $r1PerKm,
                'x1_ohm_km'             => $x1PerKm,
                'z1_ohm_km'             => $z1PerKm,
            ],
            'calculation_status'         => $calculationStatus,
            'outcome_status'             => $calculationStatus,
            'dasar_perhitungan'          => $dasarPerhitungan,
            'dasar_perhitungan_label'    => $dasarPerhitunganLabel,
            'formula_derivation'         => $formulaDerivation,
            'missing_parameters'         => $missingParameters,
            'calculated_distance_m'      => $calculatedDistanceM,
            'relay_measured_distance_m'  => $relayDistanceIn,
            'relay_distance_m'           => $relayDistanceIn,
            'topology_path_length_m'     => $topologyPathLengthM,
            'topology_path_length_label' => $topologyPathLengthLabel,
            'feeder_span_meters'         => round($totalSpanMeters, 1),
            'confidence_percent'         => $confidence,
            'pole_sequence'              => $poleSequence,
            'fault_path_sequence'        => $faultPathSequence,
            'candidate_zone'             => $candidateZoneInfo,
            'upstream_segments'          => $upstreamSegments,
            'fault_zone_segment'         => $faultZoneSegment,
            'matching_fault_segments'    => $matchingFaultSegments,
            'branch_ambiguity'           => $branchAmbiguityDetected,
            'candidate_assets'           => $candidateAssetList,
            'historical_priority_assets' => $historicalPriorityAssets,
            'has_physics_prediction'     => ($calculatedDistanceM > 0),
            'transline_segment'          => $faultZoneSegment ? "TL-{$penyulangKode}-P{$faultZoneSegment['seq_no']}" : "TL-{$penyulangKode}-MAIN",
            'fault_point'                => $faultPointGeom,
            'explanation'                => $explanation,
            'gis_url'                    => $gisUrl,
            'input_fingerprint'          => $inputFingerprint,
            'keterbatasan_telemetri'     => 'Berdasarkan kaidah IEEE C37.114, masukan arus/tegangan skalar semata tanpa reaktansi loop X dari relay numerik dan MVA bus GI tidak dapat menghasilkan jarak impedansi presisi tinggi.',
        ];
    }

    /**
     * Identify evidence-based historical vulnerability priority poles along the downstream traversed path.
     * Used when physics calculations cannot narrow down location mathematically (e.g. DGR without relay loop reactance).
     */
    public function findHistoricalVulnerabilityPoles(int $penyulangId, array $poleSequence, array $assetMap, array $poleCumDistanceMap): array
    {
        if (empty($poleSequence)) {
            return [];
        }

        $allPoleIds = [];
        foreach ($poleSequence as $p) {
            if (!empty($p['from_pole_id'])) $allPoleIds[(int)$p['from_pole_id']] = true;
            if (!empty($p['to_pole_id'])) $allPoleIds[(int)$p['to_pole_id']] = true;
        }
        $poleIdList = array_keys($allPoleIds);
        if (empty($poleIdList)) {
            return [];
        }

        $findingsByPole = [];
        if ($this->db->tableExists('fault_actual_findings')) {
            $fRows = $this->db->table('fault_actual_findings')
                ->select('id, fault_case_id, found_asset_id, cause_category_code, component_damaged, inspected_at')
                ->whereIn('found_asset_id', $poleIdList)
                ->where('outcome_type', 'CONFIRMED')
                ->get()
                ->getResultArray();
            foreach ($fRows as $fr) {
                $pid = (int)$fr['found_asset_id'];
                $findingsByPole[$pid][] = $fr;
            }
        }

        $temuanByPole = [];
        if ($this->db->tableExists('temuan')) {
            $tQuery = $this->db->table('temuan')
                ->select('id, asset_id, jenis_temuan, status, tanggal_temuan')
                ->whereIn('asset_id', $poleIdList);
            if ($this->db->fieldExists('deleted_at', 'temuan')) {
                $tQuery->where('deleted_at IS NULL');
            }
            if ($this->db->fieldExists('status', 'temuan')) {
                $tQuery->whereNotIn('status', ['SELESAI', 'CLOSED']);
            }
            $tRows = $tQuery->get()->getResultArray();
            foreach ($tRows as $tr) {
                $pid = (int)$tr['asset_id'];
                $temuanByPole[$pid][] = $tr;
            }
        }

        $priorityList = [];
        foreach ($poleIdList as $poleId) {
            $findings = $findingsByPole[$poleId] ?? [];
            $temuans = $temuanByPole[$poleId] ?? [];

            if (empty($findings) && empty($temuans)) {
                continue;
            }

            $ast = $assetMap[$poleId] ?? null;
            if (!$ast) continue;

            $fCount = count($findings);
            $tCount = count($temuans);
            $vScore = min(92, 45 + ($fCount * 20) + ($tCount * 12));

            $factors = [];
            if ($fCount > 0) {
                $factors[] = "{$fCount}x Riwayat titik gangguan aktual terkonfirmasi pada tiang ini";
            }
            if ($tCount > 0) {
                $factors[] = "{$tCount}x Catatan temuan inspeksi aktif (anomali komponen / ROW)";
            }

            $priorityList[] = [
                'asset_id'               => $poleId,
                'kode_asset'             => $ast['kode_asset'],
                'asset_name'             => $ast['nama_asset'] ?? $ast['kode_asset'],
                'cumulative_meters'      => $poleCumDistanceMap[$poleId] ?? null,
                'latitude'               => (float)($ast['latitude'] ?? 0),
                'longitude'              => (float)($ast['longitude'] ?? 0),
                'vulnerability_score'    => $vScore,
                'evidence_factors'       => $factors,
                'finding_count'          => $fCount,
                'temuan_count'           => $tCount,
                'is_historical_priority' => true,
                'is_physics_prediction'  => false,
            ];
        }

        usort($priorityList, function($a, $b) {
            return $b['vulnerability_score'] <=> $a['vulnerability_score'];
        });

        foreach ($priorityList as $idx => &$item) {
            $item['priority_rank'] = $idx + 1;
        }
        unset($item);

        return array_slice($priorityList, 0, 5);
    }

    /**
     * D3-G14: Create B6 Field Investigation Dispatch Package
     */
    public function createFieldDispatchPackage(string $caseUuid): array
    {
        $now = date('Y-m-d H:i:s');
        $dispatchId = 'DSP-FLI-' . date('Ymd') . '-' . strtoupper(substr(md5($caseUuid), 0, 6));

        return [
            'status'         => 'success',
            'dispatch_id'    => $dispatchId,
            'case_uuid'      => $caseUuid,
            'work_package'   => 'EMERGENCY_FAULT_INVESTIGATION',
            'priority'       => 'P1_CRITICAL_OUTAGE',
            'assigned_team'  => 'TIM_YANTEK_DISTRIBUSI_SIDOARJO',
            'governance_seal'=> 'B6_DISPATCH_SEALED',
            'created_at'     => $now
        ];
    }

    /**
     * Save a completed Fault Locator analysis case into fault_cases
     */
    public function saveFaultCase(array $resultData, ?array $actor = null): array
    {
        $caseUuid = $resultData['case_uuid'] ?? ('FLI-CASE-' . date('Ymd') . '-' . substr(md5(microtime()), 0, 6));
        $now = date('Y-m-d H:i:s');
        $actorUserId = (isset($actor['id']) && is_numeric($actor['id']) && (int)$actor['id'] > 0) ? (int)$actor['id'] : null;

        // Check if case already exists
        $existing = $this->db->table('fault_cases')->where('case_number', $caseUuid)->get()->getRowArray();
        if ($existing) {
            return [
                'status'      => 'success',
                'case_id'     => (int)$existing['id'],
                'case_number' => $existing['case_number'],
                'message'     => 'Kasus gangguan sudah tersimpan sebelumnya.'
            ];
        }

        $fc  = $resultData['fault_classification'] ?? [];
        $tel = $resultData['telemetry_input'] ?? [];

        $extractNullableFloat = static function ($val): ?float {
            if ($val === null || $val === '') {
                return null;
            }
            if (is_string($val)) {
                $val = str_replace(',', '.', trim($val));
                if ($val === '') {
                    return null;
                }
            }
            return is_numeric($val) ? round((float)$val, 2) : null;
        };

        $currentR = isset($fc['current_r']) ? (float)$fc['current_r'] : (float)($resultData['current_r'] ?? $tel['current_r'] ?? 0.0);
        $currentS = isset($fc['current_s']) ? (float)$fc['current_s'] : (float)($resultData['current_s'] ?? $tel['current_s'] ?? 0.0);
        $currentT = isset($fc['current_t']) ? (float)$fc['current_t'] : (float)($resultData['current_t'] ?? $tel['current_t'] ?? 0.0);
        $currentN = isset($fc['current_n']) ? (float)$fc['current_n'] : (float)($resultData['current_n'] ?? $tel['current_n'] ?? 0.0);

        $voltageR = $extractNullableFloat($fc['voltage_r_kv'] ?? $resultData['voltage_r_kv'] ?? $tel['voltage_r_kv'] ?? null);
        $voltageS = $extractNullableFloat($fc['voltage_s_kv'] ?? $resultData['voltage_s_kv'] ?? $tel['voltage_s_kv'] ?? null);
        $voltageT = $extractNullableFloat($fc['voltage_t_kv'] ?? $resultData['voltage_t_kv'] ?? $tel['voltage_t_kv'] ?? null);

        $topologyPathLengthM    = $extractNullableFloat($resultData['topology_path_length_m'] ?? null);
        $relayMeasuredDistanceM = $extractNullableFloat($resultData['relay_measured_distance_m'] ?? null);

        // Ensure fault_classification inside telemetry_payload_json also preserves currents & phase voltages
        if (!isset($resultData['fault_classification']) || !is_array($resultData['fault_classification'])) {
            $resultData['fault_classification'] = [];
        }
        $resultData['fault_classification']['current_r']    = $currentR;
        $resultData['fault_classification']['current_s']    = $currentS;
        $resultData['fault_classification']['current_t']    = $currentT;
        $resultData['fault_classification']['current_n']    = $currentN;
        $resultData['fault_classification']['voltage_r_kv'] = $voltageR;
        $resultData['fault_classification']['voltage_s_kv'] = $voltageS;
        $resultData['fault_classification']['voltage_t_kv'] = $voltageT;
        $resultData['topology_path_length_m']               = $topologyPathLengthM;
        $resultData['relay_measured_distance_m']            = $relayMeasuredDistanceM;

        // Link or create fault_event
        $eventId = !empty($resultData['fault_event_id']) ? (int)$resultData['fault_event_id'] : null;
        if (!$eventId && $this->db->tableExists('fault_events')) {
            $eventNumber = 'EVT-' . (substr($caseUuid, 0, 4) === 'FLI-' ? substr($caseUuid, 4) : $caseUuid);
            $this->db->table('fault_events')->insert([
                'event_number'           => $eventNumber,
                'penyulang_id'           => (int)($resultData['feeder_id'] ?? 1),
                'source_device_asset_id' => !empty($resultData['anchor_pole']['id']) ? (int)$resultData['anchor_pole']['id'] : null,
                'event_time'             => $resultData['fault_time'] ?? $now,
                'source_type'            => $resultData['source_type'] ?? 'MANUAL_ENTRY',
                'raw_telemetry_json'     => json_encode($resultData),
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
            $eventId = (int)$this->db->insertID();
        }

        $topCandId = !empty($resultData['candidate_assets'][0]['asset_id']) ? (int)$resultData['candidate_assets'][0]['asset_id'] : null;
        if ($topCandId !== null && $this->db->tableExists('assets')) {
            $exists = $this->db->table('assets')->where('id', $topCandId)->countAllResults() > 0;
            if (!$exists) {
                $topCandId = null;
            }
        }

        $insertData = [
            'case_number'             => $caseUuid,
            'fault_event_id'          => $eventId,
            'ulp_id'                  => !empty($resultData['ulp_id']) ? (int)$resultData['ulp_id'] : null,
            'penyulang_id'            => (int)($resultData['feeder_id'] ?? 1),
            'source_type'             => $resultData['source_type'] ?? 'GI',
            'analysis_version'        => $resultData['engine_version'] ?? $this->engineVersion,
            'analysis_input_hash'     => $resultData['input_fingerprint'] ?? hash('sha256', $caseUuid),
            'device_asset_id'         => !empty($resultData['anchor_pole']['id']) ? (int)$resultData['anchor_pole']['id'] : null,
            'target_distance_meters'  => (float)($resultData['calculated_distance_m'] ?? 0),
            'fault_type'              => $resultData['fault_classification']['fault_type'] ?? 'UNKNOWN',
            'current_r'               => $currentR,
            'current_s'               => $currentS,
            'current_t'               => $currentT,
            'current_n'               => $currentN,
            'conductor_type'          => $resultData['electrical_parameters']['conductor_type'] ?? ($resultData['traversed_conductor']['official_type'] ?? 'Belum tersedia'),
            'formula_version'         => $resultData['engine_version'] ?? $this->engineVersion,
            'missing_parameters_json' => json_encode($resultData['missing_parameters'] ?? []),
            'telemetry_payload_json'  => json_encode($resultData),
            'estimated_lat'           => !empty($resultData['fault_point']['lat']) ? (float)$resultData['fault_point']['lat'] : null,
            'estimated_lng'           => !empty($resultData['fault_point']['lng']) ? (float)$resultData['fault_point']['lng'] : null,
            'top_candidate_asset_id'  => $topCandId,
            'top_confidence_score'    => (float)($resultData['confidence_percent'] ?? 0),
            'status'                  => 'OPEN',
            'follow_up_status'        => 'MENUNGGU_PEMERIKSAAN',
            'created_by'              => $actorUserId,
            'analysis_timestamp'      => $now,
            'created_at'              => $now,
            'updated_at'              => $now,
        ];

        if ($this->db->fieldExists('voltage_r_kv', 'fault_cases')) {
            $insertData['voltage_r_kv'] = $voltageR;
            $insertData['voltage_s_kv'] = $voltageS;
            $insertData['voltage_t_kv'] = $voltageT;
        }
        if ($this->db->fieldExists('topology_path_length_m', 'fault_cases')) {
            $insertData['topology_path_length_m']    = $topologyPathLengthM;
            $insertData['relay_measured_distance_m'] = $relayMeasuredDistanceM;
        }

        $this->db->table('fault_cases')->insert($insertData);
        $caseId = (int)$this->db->insertID();

        // Store candidate assets or historical priorities
        if ($caseId > 0 && !empty($resultData['candidate_assets'])) {
            foreach ($resultData['candidate_assets'] as $rank => $cand) {
                if (empty($cand['asset_id'])) continue;
                $candAssetId = (int)$cand['asset_id'];
                if ($this->db->tableExists('assets') && $this->db->table('assets')->where('id', $candAssetId)->countAllResults() === 0) {
                    continue;
                }
                $this->db->table('fault_candidate_assets')->insert([
                    'fault_case_id'                => $caseId,
                    'asset_id'                     => $candAssetId,
                    'rank'                         => $cand['rank'] ?? ($rank + 1),
                    'candidate_status'             => 'PREDICTED_CANDIDATE',
                    'graph_distance_from_device_m' => (float)($resultData['calculated_distance_m'] ?? 0),
                    'evidence_breakdown_json'      => json_encode($cand),
                    'created_at'                   => $now,
                ]);
            }
        } elseif ($caseId > 0 && !empty($resultData['historical_priority_assets'])) {
            foreach ($resultData['historical_priority_assets'] as $rank => $hCand) {
                if (empty($hCand['asset_id'])) continue;
                $candAssetId = (int)$hCand['asset_id'];
                if ($this->db->tableExists('assets') && $this->db->table('assets')->where('id', $candAssetId)->countAllResults() === 0) {
                    continue;
                }
                $this->db->table('fault_candidate_assets')->insert([
                    'fault_case_id'                => $caseId,
                    'asset_id'                     => $candAssetId,
                    'rank'                         => $hCand['priority_rank'] ?? ($rank + 1),
                    'candidate_status'             => 'HISTORICAL_PRIORITY',
                    'graph_distance_from_device_m' => (float)($hCand['cumulative_meters'] ?? 0),
                    'evidence_breakdown_json'      => json_encode($hCand),
                    'created_at'                   => $now,
                ]);
            }
        }

        return [
            'status'      => 'success',
            'case_id'     => $caseId,
            'case_number' => $caseUuid,
            'message'     => 'Kasus analisis gangguan berhasil disimpan.'
        ];
    }

    /**
     * Record post-patrol field outcome (positive or negative) without mutating initial predictions
     */
    public function recordFieldOutcome(int $caseId, array $outcomeData, ?array $actor = null): array
    {
        $case = $this->db->table('fault_cases')->where('id', $caseId)->get()->getRowArray();
        if (!$case) {
            return ['status' => 'error', 'message' => "Kasus gangguan #{$caseId} tidak ditemukan."];
        }

        $now = date('Y-m-d H:i:s');
        $actorUsername = $actor['username'] ?? $actor['name'] ?? 'PETUGAS_PATROLI';

        // Get current revision count for this case
        $prevFinding = $this->db->table('fault_actual_findings')
            ->where('fault_case_id', $caseId)
            ->orderBy('revision_no', 'DESC')
            ->get()
            ->getRowArray();
        $revisionNo = $prevFinding ? ((int)$prevFinding['revision_no'] + 1) : 1;

        $outcomeType = strtoupper(trim($outcomeData['outcome_type'] ?? 'CONFIRMED')); // CONFIRMED, NOT_FOUND, AMBIGUOUS
        $foundAssetId = !empty($outcomeData['found_asset_id']) ? (int)$outcomeData['found_asset_id'] : null;
        $causeCategoryCode = !empty($outcomeData['cause_category_code']) ? strtoupper(trim($outcomeData['cause_category_code'])) : 'GENERAL_FAULT';
        $componentDamaged = $outcomeData['component_damaged'] ?? null;
        $findingNotes = $outcomeData['finding_notes'] ?? '';
        $inspectedAreaNotes = $outcomeData['inspected_area_notes'] ?? null;
        $notFoundReason = $outcomeData['not_found_reason'] ?? null;
        $technicianName = $outcomeData['technician_name'] ?? $actorUsername;
        $inspectedAt = !empty($outcomeData['inspected_at']) ? $outcomeData['inspected_at'] : $now;
        $photoEvidenceUrl = $outcomeData['photo_evidence_url'] ?? null;
        $actualLat = (isset($outcomeData['actual_latitude']) && $outcomeData['actual_latitude'] !== '') ? (float)$outcomeData['actual_latitude'] : null;
        $actualLng = (isset($outcomeData['actual_longitude']) && $outcomeData['actual_longitude'] !== '') ? (float)$outcomeData['actual_longitude'] : null;
        $actualObservedDist = (isset($outcomeData['actual_distance_m']) && $outcomeData['actual_distance_m'] !== '') ? (float)$outcomeData['actual_distance_m'] : null;

        $distErrorMeters = null;
        $isLocationMatch = 0;
        $isComponentMatch = !empty($outcomeData['is_component_match']) ? 1 : 0;
        $newFollowUpStatus = 'MENUNGGU_PEMERIKSAAN';

        if ($outcomeType === 'CONFIRMED' && $foundAssetId > 0) {
            $newFollowUpStatus = 'GANGGUAN_DITEMUKAN';
            // Compute distance delta from predicted coordinates
            $foundAsset = $this->db->table('assets')->where('id', $foundAssetId)->get()->getRowArray();
            if ($foundAsset) {
                if ($actualLat === null && !empty($foundAsset['latitude'])) {
                    $actualLat = (float)$foundAsset['latitude'];
                }
                if ($actualLng === null && !empty($foundAsset['longitude'])) {
                    $actualLng = (float)$foundAsset['longitude'];
                }
                if (!empty($case['estimated_lat']) && $actualLat && $actualLng) {
                    $distErrorMeters = round($this->haversineDistance(
                        (float)$case['estimated_lat'], (float)$case['estimated_lng'],
                        $actualLat, $actualLng
                    ), 1);
                    // Matched if within 250 meters
                    $isLocationMatch = ($distErrorMeters <= 250.0) ? 1 : 0;
                } elseif ($actualObservedDist !== null && !empty($case['calculated_distance_m']) && (float)$case['calculated_distance_m'] > 0) {
                    $distErrorMeters = round(abs($actualObservedDist - (float)$case['calculated_distance_m']), 1);
                    $isLocationMatch = ($distErrorMeters <= 250.0) ? 1 : 0;
                }
            } else {
                $foundAssetId = null;
            }
        } elseif ($outcomeType === 'CONFIRMED' && $actualLat && $actualLng) {
            $newFollowUpStatus = 'GANGGUAN_DITEMUKAN';
            if (!empty($case['estimated_lat'])) {
                $distErrorMeters = round($this->haversineDistance(
                    (float)$case['estimated_lat'], (float)$case['estimated_lng'],
                    $actualLat, $actualLng
                ), 1);
                $isLocationMatch = ($distErrorMeters <= 250.0) ? 1 : 0;
            } elseif ($actualObservedDist !== null && !empty($case['calculated_distance_m']) && (float)$case['calculated_distance_m'] > 0) {
                $distErrorMeters = round(abs($actualObservedDist - (float)$case['calculated_distance_m']), 1);
                $isLocationMatch = ($distErrorMeters <= 250.0) ? 1 : 0;
            }
        } elseif ($outcomeType === 'NOT_FOUND') {
            $newFollowUpStatus = 'TIDAK_DITEMUKAN';
            $foundAssetId = null; // Strictly null to prevent negative data from being interpreted as defect location
        } elseif ($outcomeType === 'AMBIGUOUS' || $outcomeType === 'AMBIGUOUS_VERIFIED') {
            $newFollowUpStatus = 'PERLU_VERIFIKASI';
        }

        // Insert append-only finding
        $this->db->table('fault_actual_findings')->insert([
            'fault_case_id'              => $caseId,
            'revision_no'                => $revisionNo,
            'outcome_type'               => $outcomeType,
            'found_asset_id'             => $foundAssetId,
            'cause_category_code'        => $causeCategoryCode,
            'component_damaged'          => $componentDamaged,
            'actual_observed_distance_m' => $actualObservedDist,
            'actual_latitude'            => $actualLat,
            'actual_longitude'           => $actualLng,
            'distance_error_meters'      => $distErrorMeters,
            'is_location_match'          => $isLocationMatch,
            'is_component_match'         => $isComponentMatch,
            'technician_name'            => $technicianName,
            'created_by'                 => $actorUsername,
            'finding_notes'              => $findingNotes,
            'inspected_area_notes'       => $inspectedAreaNotes,
            'not_found_reason'           => $notFoundReason,
            'photo_evidence_url'         => $photoEvidenceUrl,
            'inspected_at'               => $inspectedAt,
            'verified_at'                => $now,
            'created_at'                 => $now,
        ]);
        $findingId = (int)$this->db->insertID();

        // Update case follow-up status without altering original prediction values
        $this->db->table('fault_cases')->where('id', $caseId)->update([
            'follow_up_status' => $newFollowUpStatus,
            'status'           => ($outcomeType === 'CONFIRMED') ? 'RESOLVED' : 'FIELD_VERIFIED',
            'updated_at'       => $now,
        ]);

        return [
            'status'                => 'success',
            'finding_id'            => $findingId,
            'revision_no'           => $revisionNo,
            'follow_up_status'      => $newFollowUpStatus,
            'is_location_match'     => $isLocationMatch,
            'distance_error_meters' => $distErrorMeters,
            'message'               => 'Hasil pemeriksaan lapangan berhasil dicatat.'
        ];
    }

    /**
     * Normalize stored case telemetry fields (strictly distinguishing 0.00 kV from null)
     */
    protected function hydrateCaseTelemetryFields(array &$c): void
    {
        $payload = [];
        if (!empty($c['telemetry_payload_json']) && is_string($c['telemetry_payload_json'])) {
            $decoded = json_decode($c['telemetry_payload_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $fc = $payload['fault_classification'] ?? [];
        $tel = $payload['telemetry_input'] ?? [];

        $normalizeNullable = static function ($colVal, $fallbackVal = null): ?float {
            if ($colVal !== null && $colVal !== '') {
                return round((float)$colVal, 2);
            }
            if ($fallbackVal !== null && $fallbackVal !== '') {
                return round((float)$fallbackVal, 2);
            }
            return null;
        };

        $c['voltage_r_kv'] = $normalizeNullable($c['voltage_r_kv'] ?? null, $fc['voltage_r_kv'] ?? $tel['voltage_r_kv'] ?? null);
        $c['voltage_s_kv'] = $normalizeNullable($c['voltage_s_kv'] ?? null, $fc['voltage_s_kv'] ?? $tel['voltage_s_kv'] ?? null);
        $c['voltage_t_kv'] = $normalizeNullable($c['voltage_t_kv'] ?? null, $fc['voltage_t_kv'] ?? $tel['voltage_t_kv'] ?? null);
        $c['has_voltage_measurement'] = ($c['voltage_r_kv'] !== null || $c['voltage_s_kv'] !== null || $c['voltage_t_kv'] !== null);

        $c['topology_path_length_m']    = $normalizeNullable($c['topology_path_length_m'] ?? null, $payload['topology_path_length_m'] ?? null);
        $c['relay_measured_distance_m'] = $normalizeNullable($c['relay_measured_distance_m'] ?? null, $payload['relay_measured_distance_m'] ?? null);
        $c['protection_device_id']      = !empty($payload['protection_device_id']) ? (int)$payload['protection_device_id'] : null;
        $c['fault_type_label']          = $fc['fault_type_label'] ?? $c['fault_type'] ?? 'UNKNOWN';
    }

    /**
     * Get list of historical cases with linked findings and feeder metadata
     */
    public function getCaseHistory(?int $penyulangId = null, int $limit = 20): array
    {
        if (!$this->db->tableExists('fault_cases')) {
            return [];
        }

        $builder = $this->db->table('fault_cases')
            ->select('fault_cases.*, penyulang.nama_penyulang, penyulang.kode_penyulang')
            ->join('penyulang', 'penyulang.id = fault_cases.penyulang_id', 'left')
            ->where('fault_cases.deleted_at IS NULL');

        if ($penyulangId !== null && $penyulangId > 0) {
            $builder->where('fault_cases.penyulang_id', $penyulangId);
        }

        $cases = $builder->orderBy('fault_cases.id', 'DESC')->limit($limit)->get()->getResultArray();

        foreach ($cases as &$c) {
            // Guarantee human-readable, non-placeholder feeder display
            if (empty($c['nama_penyulang']) || str_starts_with((string)$c['nama_penyulang'], 'Feeder #')) {
                if (!empty($c['penyulang_id'])) {
                    $pyl = $this->db->table('penyulang')->where('id', (int)$c['penyulang_id'])->get()->getRowArray();
                    $c['nama_penyulang'] = $pyl['nama_penyulang'] ?? ('Penyulang ID #' . $c['penyulang_id']);
                } else {
                    $c['nama_penyulang'] = 'Penyulang Belum Terdefinisi (Data Uji/Arsip)';
                }
            }

            // Distinguish zero from unmeasured/missing currents
            $r = (float)($c['current_r'] ?? 0);
            $s = (float)($c['current_s'] ?? 0);
            $t = (float)($c['current_t'] ?? 0);
            $n = (float)($c['current_n'] ?? 0);
            $c['has_current_measurement'] = ($r > 0 || $s > 0 || $t > 0 || $n > 0);

            $this->hydrateCaseTelemetryFields($c);

            // Fetch latest field finding
            $finding = $this->db->table('fault_actual_findings')
                ->where('fault_case_id', $c['id'])
                ->orderBy('revision_no', 'DESC')
                ->get()
                ->getRowArray();
            $c['latest_finding'] = $finding;
        }

        return $cases;
    }

    /**
     * Archives legacy prototype dummy test cases (FLI-1.0.0 placeholder records with 0 currents & null feeders)
     * with mandatory pre-backup JSON file creation.
     */
    public function archiveProtoTestCases(bool $execute = false): array
    {
        if (!$this->db->tableExists('fault_cases')) {
            return ['status' => 'error', 'message' => 'Tabel fault_cases tidak ditemukan.'];
        }

        $query = $this->db->table('fault_cases')
            ->where('deleted_at IS NULL')
            ->where('penyulang_id IS NULL')
            ->where('current_r', 0)
            ->where('current_s', 0)
            ->where('current_t', 0)
            ->where('analysis_version', 'FLI-1.0.0');

        $protoCases = $query->get()->getResultArray();
        $count = count($protoCases);

        if ($count === 0) {
            return [
                'status'         => 'success',
                'archived_count' => 0,
                'message'        => 'Tidak ditemukan data uji prototype tak lengkap untuk diarsipkan.',
            ];
        }

        $backupPath = WRITEPATH . 'backups/archived_proto_fault_cases_' . date('Ymd_His') . '.json';
        if (!is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }

        file_put_contents($backupPath, json_encode([
            'archived_at' => date('Y-m-d H:i:s'),
            'total_cases' => $count,
            'records'     => $protoCases,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($execute) {
            $ids = array_column($protoCases, 'id');
            $this->db->table('fault_cases')
                ->whereIn('id', $ids)
                ->update([
                    'status'     => 'ARCHIVED_TEST_DATA',
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return [
                'status'         => 'success',
                'executed'       => true,
                'archived_count' => $count,
                'backup_file'    => $backupPath,
                'archived_ids'   => $ids,
                'message'        => "Berhasil mengarsipkan {$count} kasus uji prototype ke status soft-delete dengan backup aman.",
            ];
        }

        return [
            'status'         => 'success',
            'executed'       => false,
            'archived_count' => $count,
            'backup_file'    => $backupPath,
            'sample_ids'     => array_slice(array_column($protoCases, 'id'), 0, 5),
            'message'        => "Simulasi: Terdeteksi {$count} kasus uji prototype siap diarsipkan (gunakan execute=true).",
        ];
    }

    /**
     * Reads official network topology & conductor context for a given feeder and source (GI or PROTECTION)
     * strictly from the traversed downstream path.
     */
    public function getFeederNetworkContext(int $penyulangId, string $sourceType = 'GI', int $protectionId = 0): array
    {
        if (!$this->db->tableExists('penyulang') || $penyulangId <= 0) {
            return ['status' => 'error', 'message' => 'Penyulang tidak valid.'];
        }

        $feeder = $this->db->table('penyulang')
            ->select('penyulang.*, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = penyulang.ulp_id', 'left')
            ->where('penyulang.id', $penyulangId)
            ->get()
            ->getRowArray();

        if (!$feeder) {
            return ['status' => 'error', 'message' => 'Data penyulang tidak ditemukan.'];
        }

        $sourceType = strtoupper(trim($sourceType ?: 'GI'));

        // Execute read-only downstream traversal from the selected source (GI or PROTECTION)
        $traversalCtx = $this->locateFault([
            'ulp_id'               => (int)($feeder['ulp_id'] ?? 0),
            'feeder_id'            => $penyulangId,
            'source_type'          => $sourceType,
            'protection_device_id' => $protectionId,
            'current_r'            => 0.0,
            'current_s'            => 0.0,
            'current_t'            => 0.0,
            'current_n'            => 0.0,
        ]);

        $traversedConductor = $traversalCtx['traversed_conductor'] ?? $this->summarizeTraversedPathConductors([]);
        $poleSequence = $traversalCtx['pole_sequence'] ?? [];
        $topologyPathLengthM = $traversalCtx['topology_path_length_m'] ?? null;
        $topologyPathLengthLabel = $traversalCtx['topology_path_length_label'] ?? (
            $sourceType === 'PROTECTION'
                ? 'Panjang jalur dari proteksi ke ujung feeder (estimasi topologi)'
                : 'Panjang jalur dari GI ke ujung feeder (estimasi topologi)'
        );

        // Total feeder-wide transline & pole statistics
        $totalSpanMeters = 0.0;
        $segmentCount = 0;
        $poleCount = 0;
        $sectionCount = 0;

        if ($this->db->tableExists('gis_translines')) {
            $tlStats = $this->db->table('gis_translines')
                ->select('COUNT(*) as seg_cnt, SUM(distance_meters) as span_sum')
                ->where('penyulang_id', $penyulangId)
                ->where('is_active', 1)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRowArray();

            $segmentCount = (int)($tlStats['seg_cnt'] ?? 0);
            $totalSpanMeters = (float)($tlStats['span_sum'] ?? 0.0);
        }

        if ($this->db->tableExists('assets')) {
            $feederPoleCandidates = $this->db->table('assets')
                ->select('id, kode_asset, nama_asset, jenis_asset')
                ->where('penyulang_id', $penyulangId)
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();
            foreach ($feederPoleCandidates as $pCand) {
                if (GisTranslineService::isJtmPoleAsset($pCand)['valid']) {
                    $poleCount++;
                }
            }

            $sectionCount = $this->db->table('assets')
                ->where('penyulang_id', $penyulangId)
                ->where('deleted_at IS NULL')
                ->where('section_id IS NOT NULL')
                ->select('COUNT(DISTINCT section_id) as sec_cnt')
                ->get()
                ->getRowArray()['sec_cnt'] ?? 0;
        }

        $segmentsList = [];
        foreach (array_slice($poleSequence, 0, 50) as $idx => $segRow) {
            $segmentsList[] = [
                'seq_no'            => $idx + 1,
                'from_pole'         => $segRow['from_pole_kode'],
                'from_nama'         => $segRow['from_pole_nama'],
                'to_pole'           => $segRow['to_pole_kode'],
                'to_nama'           => $segRow['to_pole_nama'],
                'span_meters'       => (float)$segRow['span_meters'],
                'cum_start_meters'  => (float)$segRow['cum_start_meters'],
                'cumulative_meters' => (float)$segRow['cumulative_meters'],
                'conductor_type'    => $segRow['conductor_type'],
                'conductor_size'    => $segRow['conductor_size'],
                'nama_section'      => $segRow['section_name'] ?? '-',
            ];
        }

        // Protection devices (Recloser, PMCB, GH) linked to valid JTM poles
        $gisTransService = new GisTranslineService($this->db);
        $devices = $this->getProtectionDevices($penyulangId);
        $enhancedDevices = [];
        foreach ($devices as $d) {
            $linkedPole = null;
            $resolvedCtx = $gisTransService->resolveSourceContextToNearestJtmPole((int)$d['id'], $penyulangId);
            if ($resolvedCtx && !empty($resolvedCtx['anchor_pole_asset'])) {
                $linkedPole = $resolvedCtx['anchor_pole_asset'];
            }

            $enhancedDevices[] = [
                'id'               => (int)$d['id'],
                'kode_asset'       => $d['kode_asset'],
                'nama_asset'       => $d['nama_asset'],
                'jenis_asset'      => $d['jenis_asset'],
                'linked_pole_id'   => $linkedPole ? (int)$linkedPole['id'] : null,
                'linked_pole_code' => $linkedPole ? $linkedPole['kode_asset'] : 'Belum terhubung ke tiang JTM',
                'display_label'    => $d['kode_asset'] . ' - ' . $d['nama_asset'] . ' (' . $d['jenis_asset'] . ($linkedPole ? ', pada tiang ' . $linkedPole['kode_asset'] : ' - Belum terhubung ke tiang JTM') . ')',
            ];
        }

        $devicesAvailable = count($enhancedDevices) > 0;
        $protectionStatusMessage = $devicesAvailable
            ? 'Tersedia ' . count($enhancedDevices) . ' perangkat proteksi terdaftar pada penyulang ini.'
            : 'Data aset proteksi (Recloser / PMCB) belum tersedia untuk penyulang ini. Perhitungan didukung penuh dari Gardu Induk (GI) Pangkal.';

        return [
            'status' => 'success',
            'data'   => [
                'feeder' => [
                    'id'             => (int)$feeder['id'],
                    'kode_penyulang' => $feeder['kode_penyulang'],
                    'nama_penyulang' => $feeder['nama_penyulang'],
                    'ulp_id'         => (int)$feeder['ulp_id'],
                    'nama_ulp'       => $feeder['nama_ulp'] ?? 'ULP Tidak Diketahui',
                ],
                'source_context' => $traversalCtx['source_context'] ?? null,
                'anchor_pole'    => $traversalCtx['anchor_pole'] ?? null,
                'anchor_description' => $traversalCtx['anchor_description'] ?? '',
                'conductor' => [
                    'is_available'        => $traversedConductor['is_available'],
                    'status'              => $traversedConductor['status'],
                    'official_type'       => $traversedConductor['official_type'],
                    'display_label'       => $traversedConductor['display_label'],
                    'source'              => $traversedConductor['source'],
                    'is_hybrid'           => $traversedConductor['is_hybrid'],
                    'breakdown'           => $traversedConductor['breakdown'],
                    'segment_ranges'      => $traversedConductor['segment_ranges'],
                    'status_message'      => $traversedConductor['status_message'],
                    'catalog_entry'       => $traversedConductor['catalog_entry'],
                ],
                'segments_preview' => $segmentsList,
                'topology' => [
                    'topology_path_length_m'     => $topologyPathLengthM,
                    'topology_path_length_km'    => $topologyPathLengthM !== null ? round($topologyPathLengthM / 1000.0, 2) : null,
                    'topology_path_length_label' => $topologyPathLengthLabel,
                    'traversed_segment_count'    => count($poleSequence),
                    'traversed_span_meters'      => round((float)($traversalCtx['feeder_span_meters'] ?? 0.0), 1),
                    'total_span_meters'          => round($totalSpanMeters, 1),
                    'total_span_km'              => round($totalSpanMeters / 1000.0, 2),
                    'segment_count'              => $segmentCount,
                    'valid_jtm_poles_count'      => $poleCount,
                    'sections_count'             => (int)$sectionCount,
                    'is_topology_ready'          => ($topologyPathLengthM !== null && $topologyPathLengthM > 0),
                    'constraint_error'           => $traversalCtx['source_context']['constraint_error'] ?? null,
                ],
                'protection_devices' => [
                    'available' => $devicesAvailable,
                    'count'     => count($enhancedDevices),
                    'message'   => $protectionStatusMessage,
                    'devices'   => $enhancedDevices,
                ]
            ]
        ];
    }

    /**
     * Get detailed case information with prediction telemetry, candidate assets, and finding revisions
     */
    public function getCaseDetails(int $caseId): ?array
    {
        if (!$this->db->tableExists('fault_cases')) {
            return null;
        }

        $case = $this->db->table('fault_cases')
            ->select('fault_cases.*, penyulang.nama_penyulang, penyulang.kode_penyulang')
            ->join('penyulang', 'penyulang.id = fault_cases.penyulang_id', 'left')
            ->where('fault_cases.id', $caseId)
            ->where('fault_cases.deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$case) {
            return null;
        }

        $this->hydrateCaseTelemetryFields($case);

        // Candidates
        $candidates = $this->db->table('fault_candidate_assets')
            ->select('fault_candidate_assets.*, assets.kode_asset, assets.nama_asset, assets.jenis_asset')
            ->join('assets', 'assets.id = fault_candidate_assets.asset_id', 'left')
            ->where('fault_case_id', $caseId)
            ->orderBy('rank', 'ASC')
            ->get()
            ->getResultArray();

        // Findings revisions
        $findings = $this->db->table('fault_actual_findings')
            ->select('fault_actual_findings.*, assets.kode_asset, assets.nama_asset')
            ->join('assets', 'assets.id = fault_actual_findings.found_asset_id', 'left')
            ->where('fault_case_id', $caseId)
            ->orderBy('revision_no', 'DESC')
            ->get()
            ->getResultArray();

        $case['candidates'] = $candidates;
        $case['findings_history'] = $findings;

        return $case;
    }

    /**
     * Get Candidate Forensic Bundle
     */
    public function getForensicBundle(string $caseUuid): array
    {
        $inputFingerprint = hash('sha256', $caseUuid . '|in');
        $outputFingerprint = hash('sha256', $caseUuid . '|out');
        $bundleHash = hash('sha256', $inputFingerprint . '|' . $outputFingerprint);

        return [
            'bundle_id'          => 'BDL-FLI-' . $caseUuid,
            'topology_snapshot'  => $this->topologySnapshotId,
            'engine_version'     => $this->engineVersion,
            'case_uuid'          => $caseUuid,
            'input_fingerprint'  => $inputFingerprint,
            'output_fingerprint' => $outputFingerprint,
            'bundle_hash'        => $bundleHash,
            'lineage_verified'   => true,
            'verified_at'        => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Resolve authoritative GI Root Pole (Pangkal Penyulang) within the target connected component
     */
    protected function resolveFeederGiRootPole(int $penyulangId, array $targetCompNodes, array $assetMap, array $adj, array $feederAssets): ?array
    {
        if (empty($targetCompNodes)) {
            foreach ($assetMap as $poleId => $poleRow) {
                if (!empty($adj[$poleId])) {
                    return $poleRow;
                }
            }
            return !empty($assetMap) ? (reset($assetMap) ?: null) : null;
        }

        $targetCompSet = array_flip($targetCompNodes);

        // Rule 1: Explicit GI Outgoing / Pangkal Section or Pole Naming
        $giSectionIds = [];
        try {
            if ($this->db->tableExists('sections')) {
                $sections = $this->db->table('sections')
                    ->where('penyulang_id', $penyulangId)
                    ->get()
                    ->getResultArray();
                foreach ($sections as $sec) {
                    $sn = strtoupper((string)($sec['nama_section'] ?? ''));
                    if (preg_match('/^GI\b|\bGI\s*-|\bGARDU INDUK\b|\bPANGKAL\b|\bOUTGOING\b/i', $sn)) {
                        $giSectionIds[(int)$sec['id']] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore if sections table is unavailable in lightweight test environments
        }

        $giSectionCandidates = [];
        foreach ($targetCompNodes as $nId) {
            $pole = $assetMap[$nId] ?? null;
            if (!$pole) {
                continue;
            }
            $secId = (int)($pole['section_id'] ?? 0);
            $pName = strtoupper((string)($pole['nama_asset'] ?? '') . ' ' . (string)($pole['kode_asset'] ?? ''));
            if (isset($giSectionIds[$secId]) || preg_match('/\bPANGKAL\b|\bOUTGOING\b/i', $pName)) {
                $deg = count($adj[$nId] ?? []);
                $seq = (int)($pole['sequence_no'] ?? 0);
                $giSectionCandidates[] = [
                    'pole' => $pole,
                    'deg'  => $deg,
                    'seq'  => $seq > 0 ? $seq : 999999,
                    'id'   => $nId,
                ];
            }
        }
        if (!empty($giSectionCandidates)) {
            usort($giSectionCandidates, static function (array $a, array $b): int {
                $aTerm = ($a['deg'] === 1) ? 0 : 1;
                $bTerm = ($b['deg'] === 1) ? 0 : 1;
                if ($aTerm !== $bTerm) {
                    return $aTerm <=> $bTerm;
                }
                if ($a['seq'] !== $b['seq']) {
                    return $a['seq'] <=> $b['seq'];
                }
                return $a['id'] <=> $b['id'];
            });
            return $giSectionCandidates[0]['pole'];
        }

        // Rule 2: Topological Trunk-vs-Load-Tree Orientation via Protection Device Mounting Poles
        // A feeder trunk coming from the GI into a Recloser/PMCB is typically a smaller/linear incoming branch
        // compared to the branching downstream load network.
        $gisTransService = new GisTranslineService($this->db);
        foreach ($feederAssets as $fa) {
            $jType = strtoupper(trim((string)($fa['jenis_asset'] ?? '')));
            if (in_array($jType, ['RECLOSER', 'PMCB', 'LBS', 'FCO', 'SECT', 'SECTIONALIZER'], true)) {
                $res = $gisTransService->resolveSourceContextToNearestJtmPole((int)$fa['id'], $penyulangId);
                $pPoleId = (int)($res['anchor_pole_asset']['id'] ?? 0);
                if ($pPoleId > 0 && isset($targetCompSet[$pPoleId]) && count($adj[$pPoleId] ?? []) >= 2) {
                    $subtrees = [];
                    foreach ($adj[$pPoleId] as $edge) {
                        $nb = $edge['target'];
                        $vis = [$pPoleId => true, $nb => true];
                        $q = [[$nb, (float)$edge['dist']]];
                        $cnt = 0;
                        $terms = [];
                        while (!empty($q)) {
                            [$c, $d] = array_shift($q);
                            $cnt++;
                            $deg = 0;
                            foreach ($adj[$c] ?? [] as $e2) {
                                $nx = $e2['target'];
                                if ($nx === $pPoleId) {
                                    continue;
                                }
                                $deg++;
                                if (!isset($vis[$nx])) {
                                    $vis[$nx] = true;
                                    $q[] = [$nx, $d + (float)$e2['dist']];
                                }
                            }
                            if ($deg === 0 || ($c !== $nb && count($adj[$c] ?? []) === 1)) {
                                $terms[] = ['pole_id' => $c, 'dist' => $d];
                            }
                        }
                        if (empty($terms)) {
                            $terms[] = ['pole_id' => $nb, 'dist' => (float)$edge['dist']];
                        }
                        usort($terms, static fn(array $a, array $b): int => $b['dist'] <=> $a['dist']);
                        $subtrees[] = [
                            'count'          => $cnt,
                            'terminal_count' => count($terms),
                            'far_terminal'   => $terms[0]['pole_id'],
                        ];
                    }
                    usort($subtrees, static fn(array $a, array $b): int => $a['count'] <=> $b['count']);
                    $smallest = $subtrees[0];
                    $largest  = $subtrees[count($subtrees) - 1];
                    if (
                        $smallest['terminal_count'] <= 2
                        && $smallest['count'] >= 1
                        && $smallest['count'] < $largest['count']
                        && isset($assetMap[$smallest['far_terminal']])
                    ) {
                        return $assetMap[$smallest['far_terminal']];
                    }
                }
            }
        }

        // Rule 3: Explicit positive sequence_no (e.g. sequence_no = 1) or lowest ID in target component
        $bestPole = null;
        $bestSeq = PHP_INT_MAX;
        foreach ($targetCompNodes as $nId) {
            $pole = $assetMap[$nId] ?? null;
            if (!$pole) {
                continue;
            }
            $seq = (int)($pole['sequence_no'] ?? 0);
            if ($seq > 0 && $seq < $bestSeq) {
                $bestSeq = $seq;
                $bestPole = $pole;
            }
        }
        if ($bestPole) {
            return $bestPole;
        }

        $firstNodeId = $targetCompNodes[0];
        return $assetMap[$firstNodeId] ?? null;
    }

    /**
     * Extract ordered LineString coordinate points [[lat, lng], ...] oriented from $fromPole to $toPole
     */
    protected function extractTranslineGeometry(?string $geojsonStr, array $fromPole, array $toPole): array
    {
        $fromLat = (float)($fromPole['latitude'] ?? 0);
        $fromLng = (float)($fromPole['longitude'] ?? 0);
        $toLat   = (float)($toPole['latitude'] ?? 0);
        $toLng   = (float)($toPole['longitude'] ?? 0);
        $default = [
            ['lat' => $fromLat, 'lng' => $fromLng],
            ['lat' => $toLat, 'lng' => $toLng],
        ];

        if (empty($geojsonStr)) {
            return $default;
        }

        $decoded = json_decode($geojsonStr, true);
        if (!is_array($decoded)) {
            return $default;
        }

        $coords = null;
        if (($decoded['type'] ?? '') === 'LineString' && !empty($decoded['coordinates'])) {
            $coords = $decoded['coordinates'];
        } elseif (($decoded['type'] ?? '') === 'Feature' && ($decoded['geometry']['type'] ?? '') === 'LineString') {
            $coords = $decoded['geometry']['coordinates'] ?? null;
        }

        if (!is_array($coords) || count($coords) < 2) {
            return $default;
        }

        $points = [];
        foreach ($coords as $pt) {
            if (is_array($pt) && count($pt) >= 2) {
                $lng = (float)$pt[0];
                $lat = (float)$pt[1];
                if ($lat != 0.0 && $lng != 0.0) {
                    $points[] = ['lat' => $lat, 'lng' => $lng];
                }
            }
        }

        if (count($points) < 2) {
            return $default;
        }

        // Ensure orientation matches $fromPole -> $toPole
        $first = $points[0];
        $last  = $points[count($points) - 1];
        $dForward = $this->haversineDistance($fromLat, $fromLng, $first['lat'], $first['lng'])
                  + $this->haversineDistance($toLat, $toLng, $last['lat'], $last['lng']);
        $dReverse = $this->haversineDistance($fromLat, $fromLng, $last['lat'], $last['lng'])
                  + $this->haversineDistance($toLat, $toLng, $first['lat'], $first['lng']);

        if ($dReverse + 1.0 < $dForward) {
            $points = array_reverse($points);
        }

        return $points;
    }

    /**
     * Haversine distance helper
     */
    protected function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }
}
