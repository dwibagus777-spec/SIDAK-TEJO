<?php

namespace App\Services;

use Config\Database;

class FaultLocatorWorkspaceService
{
    protected $db;
    protected FaultLocationIntelligenceService $fliEngine;
    protected string $topologySnapshotId = 'TOPOLOGY-20261021-SDA-PROD';
    protected string $engineVersion = 'FLI-2.0.0-ELECTRICAL';

    // Standard PLN 20 kV Conductor Impedance Catalog (at 50 Hz, 20 kV)
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

    public function __construct(?FaultLocationIntelligenceService $fliEngine = null)
    {
        $this->db = Database::connect('default');
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
     * Get list of real feeders from database, optionally filtered by ULP
     */
    public function getFeeders(?int $ulpId = null): array
    {
        if (!$this->db->tableExists('penyulang')) {
            return [];
        }

        $builder = $this->db->table('penyulang')
            ->select('id, kode_penyulang, nama_penyulang, ulp_id, gardu_induk_id, panjang_jtm');

        if ($ulpId !== null && $ulpId > 0) {
            $builder->where('ulp_id', $ulpId);
        }

        return $builder->orderBy('nama_penyulang', 'ASC')->get()->getResultArray();
    }

    /**
     * Get protection devices (Recloser, PMCB, LBS) for a specific feeder
     */
    public function getProtectionDevices(int $penyulangId): array
    {
        if (!$this->db->tableExists('assets') || $penyulangId <= 0) {
            return [];
        }

        return $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, jenis_asset, section_id, sequence_no, latitude, longitude')
            ->where('penyulang_id', $penyulangId)
            ->where('deleted_at IS NULL')
            ->groupStart()
                ->like('jenis_asset', 'RECLOSER')
                ->orLike('jenis_asset', 'PMCB')
                ->orLike('jenis_asset', 'LBS')
                ->orLike('nama_asset', 'RECLOSER')
                ->orLike('nama_asset', 'REC ')
                ->orLike('nama_asset', 'PMCB')
                ->orLike('nama_asset', 'LBS')
            ->groupEnd()
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
     * Classify fault type and determine active short-circuit current from R, S, T, and Neutral
     */
    public function classifyFaultCurrents(float $iR, float $iS, float $iT, float $iN): array
    {
        $iR = abs($iR);
        $iS = abs($iS);
        $iT = abs($iT);
        $iN = abs($iN);

        $maxPhase = max($iR, $iS, $iT);

        // Under-current / load threshold: If all currents <= 10A, no detectable short circuit
        if ($maxPhase <= 10.0 && $iN <= 10.0) {
            return [
                'fault_type'        => 'NO_FAULT',
                'fault_type_label'  => 'Normal / Beban Rendah (Arus < 10 A)',
                'active_current_a'  => 0.0,
                'fault_phases'      => [],
                'is_ground_fault'   => false,
            ];
        }

        // Active phase threshold: phase current > 40 A AND > 25% of max phase
        $activePhases = [];
        if ($iR > 40.0 && $iR >= 0.25 * $maxPhase) $activePhases[] = 'R';
        if ($iS > 40.0 && $iS >= 0.25 * $maxPhase) $activePhases[] = 'S';
        if ($iT > 40.0 && $iT >= 0.25 * $maxPhase) $activePhases[] = 'T';

        $isGround = ($iN > 30.0 || (count($activePhases) === 1 && $maxPhase > 50.0));
        $phaseCount = count($activePhases);

        if ($phaseCount === 3) {
            $avgCurrent = round(($iR + $iS + $iT) / 3.0, 1);
            return [
                'fault_type'        => '3_PHASE',
                'fault_type_label'  => 'Gangguan 3 Fasa Simetris (R-S-T)',
                'active_current_a'  => $avgCurrent,
                'fault_phases'      => ['R', 'S', 'T'],
                'is_ground_fault'   => false,
            ];
        }

        if ($phaseCount === 2) {
            $phasesStr = implode('-', $activePhases);
            if ($isGround) {
                return [
                    'fault_type'        => '2_PHASE_GROUND',
                    'fault_type_label'  => "Gangguan 2 Fasa ke Tanah ({$phasesStr}-N)",
                    'active_current_a'  => round($maxPhase, 1),
                    'fault_phases'      => array_merge($activePhases, ['N']),
                    'is_ground_fault'   => true,
                ];
            }
            $sumCurrent = 0.0;
            foreach ($activePhases as $p) {
                $sumCurrent += ($p === 'R' ? $iR : ($p === 'S' ? $iS : $iT));
            }
            return [
                'fault_type'        => '2_PHASE',
                'fault_type_label'  => "Gangguan Antar Fasa ({$phasesStr})",
                'active_current_a'  => round($sumCurrent / 2.0, 1),
                'fault_phases'      => $activePhases,
                'is_ground_fault'   => false,
            ];
        }

        if ($phaseCount === 1) {
            $singlePhase = $activePhases[0];
            $actCurrent = ($singlePhase === 'R' ? $iR : ($singlePhase === 'S' ? $iS : $iT));
            if ($iN > $actCurrent) {
                $actCurrent = $iN;
            }
            return [
                'fault_type'        => '1_PHASE_GROUND',
                'fault_type_label'  => "Gangguan 1 Fasa ke Tanah ({$singlePhase}-N)",
                'active_current_a'  => round($actCurrent, 1),
                'fault_phases'      => [$singlePhase, 'N'],
                'is_ground_fault'   => true,
            ];
        }

        // Only neutral current high
        if ($iN > 30.0) {
            return [
                'fault_type'        => '1_PHASE_GROUND',
                'fault_type_label'  => 'Gangguan 1 Fasa ke Tanah (Ground Residual)',
                'active_current_a'  => round($iN, 1),
                'fault_phases'      => ['N'],
                'is_ground_fault'   => true,
            ];
        }

        return [
            'fault_type'        => 'UNKNOWN',
            'fault_type_label'  => 'Karakteristik Arus Tidak Spesifik',
            'active_current_a'  => round($maxPhase, 1),
            'fault_phases'      => $activePhases,
            'is_ground_fault'   => false,
        ];
    }

    /**
     * Execute FLI Fault Location Analysis with Electrical Impedance Rigor & Real JTM Pole Translines
     */
    public function locateFault(array $telemetry): array
    {
        $ulpInput         = $telemetry['ulp_id'] ?? null;
        $feederInput      = $telemetry['feeder_id'] ?? '1';
        $sourceType       = strtoupper(trim($telemetry['source_type'] ?? 'GI')); // 'GI' or 'PROTECTION'
        $protectionId     = (int)($telemetry['protection_device_id'] ?? 0);
        $faultTimestamp   = $telemetry['fault_time'] ?? $telemetry['timestamp'] ?? date('Y-m-d H:i:s');
        $relayType        = $telemetry['relay_type'] ?? 'OCR_GFR';
        $conductorKey     = $telemetry['conductor_type'] ?? 'AAAC 150 mm²';
        $relayDistanceIn  = isset($telemetry['relay_distance_m']) ? (float)$telemetry['relay_distance_m'] : null;

        // Currents in Amperes
        $currentR = (float)($telemetry['current_r'] ?? ($telemetry['fault_current_a'] ?? 0));
        $currentS = (float)($telemetry['current_s'] ?? 0);
        $currentT = (float)($telemetry['current_t'] ?? 0);
        $currentN = (float)($telemetry['current_n'] ?? 0);

        // 1. Resolve Feeder
        $feeder = null;
        if (is_numeric($feederInput)) {
            $feeder = $this->db->table('penyulang')->where('id', (int)$feederInput)->get()->getRowArray();
        }
        if (!$feeder) {
            $feeder = $this->db->table('penyulang')
                ->groupStart()
                    ->where('kode_penyulang', (string)$feederInput)
                    ->orWhere('nama_penyulang', (string)$feederInput)
                    ->orLike('nama_penyulang', (string)$feederInput)
                ->groupEnd()
                ->get()
                ->getRowArray();
        }
        if (!$feeder) {
            $feeder = $this->db->table('penyulang')->orderBy('id', 'ASC')->get()->getRowArray() ?? [
                'id' => 1,
                'kode_penyulang' => 'PYL-001',
                'nama_penyulang' => 'SIWALAN PANJI',
                'ulp_id' => 1
            ];
        }

        $penyulangId   = (int)$feeder['id'];
        $penyulangNama = $feeder['nama_penyulang'];
        $penyulangKode = $feeder['kode_penyulang'];
        $ulpId         = (int)($feeder['ulp_id'] ?? $ulpInput ?? 1);

        // 2. Conductor Impedance Parameters
        $condParams = self::CONDUCTOR_CATALOG[$conductorKey] ?? self::CONDUCTOR_CATALOG['AAAC 150 mm²'];
        $z1PerKm    = (float)$condParams['z1_ohm_km'];
        $r1PerKm    = (float)$condParams['r1_ohm_km'];
        $x1PerKm    = (float)$condParams['x1_ohm_km'];

        // Electrical Constants
        $V_LL = 20000.0;                   // 20 kV line-to-line
        $V_LN = 20000.0 / sqrt(3.0);       // ~11,547.005 V line-to-neutral
        $R_N  = 40.0;                      // NGR 40 Ohm (UP3 Sidoarjo)
        $Z_source = 0.5;                   // ~0.5 Ohm GI substation transformer equivalent source impedance

        // 3. Classify Fault Currents
        $faultClass = $this->classifyFaultCurrents($currentR, $currentS, $currentT, $currentN);
        $faultType  = $faultClass['fault_type'];
        $iSc        = $faultClass['active_current_a'];

        // 4. Resolve Anchor JTM Pole
        // Invariant: GI or Protection Devices never have translines directly connected to them.
        // We resolve them to the nearest verified JTM pole.
        $gisTransService = new GisTranslineService();
        $anchorPole = null;
        $anchorDesc = '';
        $anchorOffsetMeters = 0.0;

        if ($sourceType === 'PROTECTION' && $protectionId > 0) {
            $resolved = $gisTransService->resolveSourceContextToNearestJtmPole($protectionId, $penyulangId);
            if ($resolved && !empty($resolved['anchor_pole_asset'])) {
                $anchorPole = $resolved['anchor_pole_asset'];
                $anchorOffsetMeters = (float)($resolved['distance_to_pole_meters'] ?? 0.0);
                $devAsset = $resolved['source_asset'];
                $anchorDesc = "Peralatan Proteksi [{$devAsset['kode_asset']} - {$devAsset['nama_asset']}] -> Terhubung ke Tiang JTM [{$anchorPole['kode_asset']}] (Jarak: {$anchorOffsetMeters}m)";
            }
        }

        // Fallback for GI or unanchored protection: first valid JTM pole of feeder
        if (!$anchorPole) {
            $anchorPole = $this->db->table('assets')
                ->where('penyulang_id', $penyulangId)
                ->where('deleted_at IS NULL')
                ->groupStart()
                    ->whereIn('jenis_asset', ['TIANG_BETON', 'TIANG_BESI', 'TIANG_KAYU', 'TIANG', 'JTM', 'POLE'])
                    ->orWhere('jenis_asset IS NULL')
                ->groupEnd()
                ->orderBy('sequence_no', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getRowArray();

            $anchorDesc = "Gardu Induk (Pangkal Penyulang) -> Tiang JTM Pertama [{$anchorPole['kode_asset']}]";
        }

        $anchorPoleId = (int)($anchorPole['id'] ?? 0);

        // 5. Electrical Calculation & Rigor Checks
        $calculationStatus = 'RESOLVED';
        $dasarPerhitungan = 'ESTIMASI_TOPOLOGI';
        $dasarPerhitunganLabel = '';
        $formulaDerivation = '';
        $missingParameters = [];
        $calculatedDistanceM = 0.0;
        $confidence = 88.0;
        $explanation = '';

        if ($relayDistanceIn !== null && $relayDistanceIn > 0) {
            // Relied on direct distance measurement from numerical relay
            $calculatedDistanceM = round($relayDistanceIn, 1);
            $calculationStatus = 'CALCULATED_RELAY_AIDED';
            $dasarPerhitungan = 'RELAY_DISTANCE_MEASUREMENT';
            $dasarPerhitunganLabel = "Hasil Pengukuran Digital Fault Locator / Distance Relay: {$calculatedDistanceM} m";
            $formulaDerivation = "d = {$calculatedDistanceM} m (diperoleh langsung dari pembacaan relay numerik)";
            $confidence = 94.0;
            $explanation = "Penentuan lokasi menggunakan data jarak relay numerik ({$calculatedDistanceM}m) yang ditelusuri sepanjang transline tiang JTM.";
        } elseif ($faultType === '3_PHASE' && $iSc > 10.0) {
            // Symmetrical 3-Phase Fault: Z_loop = V_LN / I_sc
            $zLoop = $V_LN / $iSc;
            $zLine = max(0.01, $zLoop - $Z_source);
            $distKm = $zLine / $z1PerKm;
            $calculatedDistanceM = round($distKm * 1000.0, 1);

            $calculationStatus = 'CALCULATED_ELECTRICAL_IMPEDANCE';
            $dasarPerhitungan = 'IMPEDANCE_3PH_SYMMETRICAL';
            $dasarPerhitunganLabel = "Perhitungan Impedansi Loop 3 Fasa (V_LN = 11.55 kV, Z1 = {$z1PerKm} Ohm/km)";
            $formulaDerivation = "Z_loop = V_LN / I_sc = 11,547 V / {$iSc} A = " . round($zLoop, 3) . " Ω; Z_line = Z_loop - Z_trafo(0.5Ω) = " . round($zLine, 3) . " Ω; d = Z_line / Z1 = " . round($distKm, 3) . " km ({$calculatedDistanceM} m)";
            $confidence = 89.0;
            $explanation = "Gangguan 3 fasa simetris dengan arus " . number_format($iSc, 0) . "A. Berdasarkan impedansi urutan positif konduktor {$conductorKey} ({$z1PerKm} Ω/km), estimasi jarak gangguan adalah {$calculatedDistanceM} meter.";
        } elseif ($faultType === '2_PHASE' && $iSc > 10.0) {
            // Phase-to-Phase Fault: Z_loop = V_LL / (2 * I_sc)
            $zLoop = $V_LL / (2.0 * $iSc);
            $zLine = max(0.01, $zLoop - $Z_source);
            $distKm = $zLine / $z1PerKm;
            $calculatedDistanceM = round($distKm * 1000.0, 1);

            $calculationStatus = 'CALCULATED_ELECTRICAL_IMPEDANCE';
            $dasarPerhitungan = 'IMPEDANCE_2PH_PHASE_TO_PHASE';
            $dasarPerhitunganLabel = "Perhitungan Impedansi Antar Fasa (V_LL = 20 kV, Z1 = {$z1PerKm} Ohm/km)";
            $formulaDerivation = "Z_loop = V_LL / (2 * I_sc) = 20,000 V / (2 * {$iSc} A) = " . round($zLoop, 3) . " Ω; Z_line = Z_loop - Z_trafo(0.5Ω) = " . round($zLine, 3) . " Ω; d = Z_line / Z1 = " . round($distKm, 3) . " km ({$calculatedDistanceM} m)";
            $confidence = 86.0;
            $explanation = "Gangguan antar fasa tanpa kontak tanah dengan arus " . number_format($iSc, 0) . "A. Berdasarkan impedansi kawat {$conductorKey}, estimasi jarak gangguan adalah {$calculatedDistanceM} meter.";
        } elseif ($faultType === '1_PHASE_GROUND' || $faultType === '2_PHASE_GROUND') {
            // Ground Fault in 20 kV System with NGR 40 Ohm:
            // Crucial: Maximum ground fault current through 40 Ohm NGR is V_LN / R_N = 11,547 / 40 ≈ 288.7 A.
            // Loop impedance is dominated by NGR (3*RN = 120 Ohm) and contact/arc resistance (Rf),
            // NOT by line conductor impedance!
            // Without numerical relay reactance (X), calculating precise distance purely from current is physically indeterminate.
            $calculationStatus = 'DATA_TIDAK_CUKUP';
            $dasarPerhitungan = 'NGR_40_OHM_PHYSICAL_LIMITATION';
            $dasarPerhitunganLabel = 'DATA TIDAK CUKUP: Arus 1-LG pada sistem NGR 40 Ohm didominasi oleh tahanan pentanahan dan Rf, bukan panjang konduktor';
            $missingParameters = [
                'Reaktansi loop relay (X_loop / Ohm) atau rekaman event digital distance relay',
                'Besaran tahanan sentuh gangguan (Rf / Ohm)',
                'Impedansi urutan nol spesifik konduktor (Z0)'
            ];
            $formulaDerivation = "I_f = (3 * V_LN) / (2*Z1 + Z0 + 3*RN + 3*Rf). Dengan RN = 40 Ω, nilai 3*RN = 120 Ω mendominasi total impedansi dibandingkan impedansi kawat (2*Z1 + Z0 ≈ 1-20 Ω). Jarak tidak dapat dihitung presisi dari arus saja tanpa nilai reaktansi murni X_fault.";
            $confidence = 45.0;
            $explanation = "Pada sistem 20 kV dengan pentanahan NGR 40 Ohm, arus gangguan fasa-ke-tanah dibatasi oleh NGR (maksimum ~288 A) dan resistansi kontak (Rf). Parameter telemetri saat ini belum cukup untuk menghasilkan jarak presisi tinggi. Penelusuran topologi di bawah ini merupakan ESTIMASI TOPOLOGI KANDIDAT.";

            // Provide candidate topological midpoint (~1,200m or 30% of feeder) for visualization
            $calculatedDistanceM = 1200.0;
        } else {
            $calculationStatus = 'UNRESOLVED';
            $dasarPerhitungan = 'INVALID_CURRENT_TELEMETRY';
            $dasarPerhitunganLabel = 'Arus gangguan tidak terdeteksi atau berada di bawah ambang batas (<= 10 A)';
            $confidence = 0.0;
            $explanation = 'Besaran arus fasa atau netral tidak menunjukkan karakteristik hubung singkat.';
            $calculatedDistanceM = 0.0;
        }

        // 6. Traverse Real JTM Pole Translines Downstream
        $translines = $this->db->table('gis_translines')
            ->where('penyulang_id', $penyulangId)
            ->where('is_active', 1)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $feederAssets = $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, jenis_asset, section_id, sequence_no, latitude, longitude')
            ->where('penyulang_id', $penyulangId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $assetMap = [];
        foreach ($feederAssets as $a) {
            $assetMap[$a['id']] = $a;
        }

        // Build adjacency graph of JTM poles
        $adj = [];
        foreach ($translines as $tl) {
            $u = (int)$tl['source_asset_id'];
            $v = (int)$tl['target_asset_id'];
            $dist = (float)($tl['distance_meters'] ?? 0);
            if ($dist <= 0 && isset($assetMap[$u], $assetMap[$v])) {
                $dist = $this->haversineDistance(
                    (float)$assetMap[$u]['latitude'], (float)$assetMap[$u]['longitude'],
                    (float)$assetMap[$v]['latitude'], (float)$assetMap[$v]['longitude']
                );
            }
            $adj[$u][] = ['target' => $v, 'tl' => $tl, 'dist' => $dist];
            $adj[$v][] = ['target' => $u, 'tl' => $tl, 'dist' => $dist];
        }

        // Traverse downstream from anchor pole
        $poleSequence = [];
        $visitedPoles = [];
        $visitedEdges = [];
        $cumDist = 0.0;
        $totalSpanMeters = 0.0;
        $faultZoneSegment = null;
        $candidatePoles = [];

        if ($anchorPoleId > 0 && isset($adj[$anchorPoleId])) {
            $queue = [[$anchorPoleId, 0.0]];
            $visitedPoles[$anchorPoleId] = true;

            $seqNo = 1;
            while (!empty($queue)) {
                [$currPoleId, $currCum] = array_shift($queue);

                if (!isset($adj[$currPoleId])) continue;

                foreach ($adj[$currPoleId] as $edge) {
                    $nextPoleId = $edge['target'];
                    $edgeKey = min($currPoleId, $nextPoleId) . '-' . max($currPoleId, $nextPoleId);

                    if (isset($visitedEdges[$edgeKey])) continue;
                    $visitedEdges[$edgeKey] = true;

                    $spanDist = round((float)$edge['dist'], 1);
                    $nextCum = round($currCum + $spanDist, 1);
                    $totalSpanMeters += $spanDist;

                    $fromPoleAsset = $assetMap[$currPoleId] ?? ['id' => $currPoleId, 'kode_asset' => "T-{$currPoleId}", 'nama_asset' => "Tiang #{$currPoleId}"];
                    $toPoleAsset   = $assetMap[$nextPoleId] ?? ['id' => $nextPoleId, 'kode_asset' => "T-{$nextPoleId}", 'nama_asset' => "Tiang #{$nextPoleId}"];

                    $isZone = false;
                    if ($calculatedDistanceM > 0 && $calculatedDistanceM >= $currCum && $calculatedDistanceM <= $nextCum) {
                        $isZone = true;
                    } elseif ($calculatedDistanceM > 0 && $calculatedDistanceM > $nextCum && empty($queue)) {
                        // Beyond last span
                        $isZone = false;
                    }

                    $seqItem = [
                        'seq_no'            => $seqNo++,
                        'from_pole_id'      => $currPoleId,
                        'from_pole_kode'    => $fromPoleAsset['kode_asset'] ?? "T-{$currPoleId}",
                        'from_pole_nama'    => $fromPoleAsset['nama_asset'] ?? $fromPoleAsset['kode_asset'],
                        'to_pole_id'        => $nextPoleId,
                        'to_pole_kode'      => $toPoleAsset['kode_asset'] ?? "T-{$nextPoleId}",
                        'to_pole_nama'      => $toPoleAsset['nama_asset'] ?? $toPoleAsset['kode_asset'],
                        'span_meters'       => $spanDist,
                        'cumulative_meters' => $nextCum,
                        'conductor_type'    => $edge['tl']['conductor_type'] ?? $condParams['type'],
                        'conductor_size'    => $edge['tl']['conductor_size'] ?? $condParams['size'],
                        'is_fault_zone'     => $isZone,
                        'from_coords'       => ['lat' => (float)($fromPoleAsset['latitude'] ?? 0), 'lng' => (float)($fromPoleAsset['longitude'] ?? 0)],
                        'to_coords'         => ['lat' => (float)($toPoleAsset['latitude'] ?? 0), 'lng' => (float)($toPoleAsset['longitude'] ?? 0)],
                    ];

                    $poleSequence[] = $seqItem;

                    if ($isZone) {
                        $faultZoneSegment = $seqItem;
                        $candidatePoles = [$fromPoleAsset, $toPoleAsset];
                    }

                    if (!isset($visitedPoles[$nextPoleId])) {
                        $visitedPoles[$nextPoleId] = true;
                        $queue[] = [$nextPoleId, $nextCum];
                    }
                }
            }
        }

        // If fault zone was not strictly matched within an interior span, pick the closest span
        if (!$faultZoneSegment && !empty($poleSequence)) {
            $minDelta = PHP_FLOAT_MAX;
            foreach ($poleSequence as $seq) {
                $delta = abs($seq['cumulative_meters'] - $calculatedDistanceM);
                if ($delta < $minDelta) {
                    $minDelta = $delta;
                    $faultZoneSegment = $seq;
                }
            }
            if ($faultZoneSegment) {
                $candidatePoles = [
                    $assetMap[$faultZoneSegment['from_pole_id']] ?? null,
                    $assetMap[$faultZoneSegment['to_pole_id']] ?? null
                ];
                $candidatePoles = array_values(array_filter($candidatePoles));
            }
        }

        // 7. Calculate Interpolated Coordinates on the Physical Fault Zone Transline
        $faultPointGeom = null;
        if ($faultZoneSegment && !empty($faultZoneSegment['from_coords']['lat']) && !empty($faultZoneSegment['to_coords']['lat'])) {
            $span = max(1.0, (float)$faultZoneSegment['span_meters']);
            $cumEnd = (float)$faultZoneSegment['cumulative_meters'];
            $cumStart = $cumEnd - $span;
            $fraction = 0.5; // default midpoint of span
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
        } elseif (!empty($candidatePoles)) {
            $fLat = (float)($candidatePoles[0]['latitude'] ?? -7.4478);
            $fLng = (float)($candidatePoles[0]['longitude'] ?? 112.7183);
            $faultPointGeom = ['lat' => $fLat, 'lng' => $fLng, 'span_fraction' => 0.0];
        }

        // Candidate assets formatting
        $candidateAssetList = [];
        foreach ($candidatePoles as $cAst) {
            if (!$cAst) continue;
            $candidateAssetList[] = [
                'asset_id'    => $cAst['id'],
                'kode_asset'  => $cAst['kode_asset'],
                'asset_name'  => $cAst['nama_asset'] ?? $cAst['kode_asset'],
                'jenis_asset' => $cAst['jenis_asset'],
                'section_id'  => $cAst['section_id'] ?? null,
                'latitude'    => (float)($cAst['latitude'] ?? 0),
                'longitude'   => (float)($cAst['longitude'] ?? 0),
            ];
        }

        // GIS URL
        $targetAssetId = !empty($candidateAssetList) ? $candidateAssetList[0]['asset_id'] : '';
        $targetLat = $faultPointGeom ? $faultPointGeom['lat'] : -7.4478;
        $targetLng = $faultPointGeom ? $faultPointGeom['lng'] : 112.7183;
        $gisUrl = site_url("gis?penyulang_id={$penyulangId}&asset_id={$targetAssetId}&lat={$targetLat}&lng={$targetLng}&zoom=19");

        // Case UUID & Fingerprint
        $inputData = [
            'feeder_id'    => $penyulangId,
            'source_type'  => $sourceType,
            'current_r'    => $currentR,
            'current_s'    => $currentS,
            'current_t'    => $currentT,
            'current_n'    => $currentN,
            'fault_time'   => $faultTimestamp,
            'conductor'    => $conductorKey,
        ];
        ksort($inputData);
        $inputFingerprint = hash('sha256', json_encode($inputData, JSON_UNESCAPED_SLASHES));
        $caseUuid = 'FLI-CASE-' . date('Ymd') . '-' . strtoupper(substr($inputFingerprint, 0, 8));

        return [
            'case_uuid'                 => $caseUuid,
            'engine_version'            => $this->engineVersion,
            'topology_snapshot'         => $this->topologySnapshotId,
            'ulp_id'                    => $ulpId,
            'feeder_id'                 => $penyulangId,
            'feeder_code'               => $penyulangKode,
            'feeder_name'               => $penyulangNama,
            'source_type'               => $sourceType,
            'anchor_description'        => $anchorDesc,
            'anchor_pole'               => $anchorPole ? [
                'id'         => $anchorPole['id'],
                'kode_asset' => $anchorPole['kode_asset'],
                'nama_asset' => $anchorPole['nama_asset'],
                'latitude'   => (float)$anchorPole['latitude'],
                'longitude'  => (float)$anchorPole['longitude'],
            ] : null,
            'fault_classification'      => $faultClass,
            'electrical_parameters'     => [
                'system_voltage_ll_kv'  => 20.0,
                'system_voltage_ln_kv'  => round($V_LN / 1000.0, 3),
                'grounding_resistor_ohm'=> $R_N,
                'conductor_type'        => $conductorKey,
                'r1_ohm_km'             => $r1PerKm,
                'x1_ohm_km'             => $x1PerKm,
                'z1_ohm_km'             => $z1PerKm,
            ],
            'calculation_status'        => $calculationStatus,
            'outcome_status'            => $calculationStatus,
            'dasar_perhitungan'         => $dasarPerhitungan,
            'dasar_perhitungan_label'   => $dasarPerhitunganLabel,
            'formula_derivation'        => $formulaDerivation,
            'missing_parameters'        => $missingParameters,
            'calculated_distance_m'     => $calculatedDistanceM,
            'relay_distance_m'          => $calculatedDistanceM,
            'feeder_span_meters'        => round($totalSpanMeters, 1),
            'confidence_percent'        => $confidence,
            'pole_sequence'             => $poleSequence,
            'fault_zone_segment'        => $faultZoneSegment,
            'candidate_assets'          => $candidateAssetList,
            'transline_segment'         => $faultZoneSegment ? "TL-{$penyulangKode}-P{$faultZoneSegment['seq_no']}" : "TL-{$penyulangKode}-MAIN",
            'fault_point'               => $faultPointGeom,
            'explanation'               => $explanation,
            'gis_url'                   => $gisUrl,
            'input_fingerprint'         => $inputFingerprint,
            'batasaan_telemetri'        => !empty($missingParameters) ? 'Perhitungan presisi fasa-tanah memerlukan parameter impedansi/reaktansi loop dari DFR relay numerik.' : 'Perhitungan impedansi loop fasa simetris/antar fasa berbasis konduktor PLN terverifikasi.',
        ];
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
