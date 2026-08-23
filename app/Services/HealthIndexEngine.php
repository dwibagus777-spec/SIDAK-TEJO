<?php

namespace App\Services;

use App\Models\AssetModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;
use RuntimeException;

class HealthIndexEngine
{
    protected AssetModel $assetModel;
    protected BaseConnection $db;

    /**
     * Permanent Engine Version String
     */
    public const ENGINE_VERSION = '1.0';

    /**
     * Base Severity Deduction Points
     */
    protected static array $baseSeverityDeduction = [
        'LOW'      => 2.00,
        'MEDIUM'   => 5.00,
        'HIGH'     => 8.00,
        'CRITICAL' => 15.00,
    ];

    public function __construct(?AssetModel $assetModel = null, ?BaseConnection $db = null)
    {
        $this->assetModel = $assetModel ?? new AssetModel();
        $this->db         = $db ?? \Config\Database::connect();
    }

    /**
     * Helper Rekursif untuk Canonical Key Sorting Array Associative (Audit Hashing)
     */
    public static function canonicalizeArrayRecursive(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        // Cek apakah array bersifat asosiatif
        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        if ($isAssoc) {
            ksort($data);
        }

        foreach ($data as $key => &$val) {
            if (is_array($val)) {
                $val = self::canonicalizeArrayRecursive($val);
            }
        }
        unset($val);

        return $data;
    }

    /**
     * Hitung Model A Stepped Aging Deduction (>30 Hari Penundaan Eksekusi)
     */
    public static function calculateAgingDeduction(int $ageDays): float
    {
        if ($ageDays <= 30) {
            return 0.00;
        }
        if ($ageDays <= 37) {
            return 1.00;
        }
        if ($ageDays <= 44) {
            return 2.00;
        }
        if ($ageDays <= 51) {
            return 3.00;
        }
        if ($ageDays <= 58) {
            return 4.00;
        }
        return 5.00;
    }

    /**
     * Hitung Recurrence Deduction (2.00 Poin per Recurrence Count)
     */
    public static function calculateRecurrenceDeduction(int $recurrenceCount): float
    {
        if ($recurrenceCount <= 0) {
            return 0.00;
        }
        return min(10.00, $recurrenceCount * 2.00);
    }

    /**
     * Phase 2B: Thermovision Hotspot Rule Resolver (Dual-Domain: JTM/PDKB vs HAR GTT)
     */
    public static function resolveThermovisionDeduction(string $domain, float $temperatureC): array
    {
        $cDomain = strtoupper(trim($domain));
        
        if ($cDomain === 'HAR_GTT') {
            // Ladder HAR GTT: <60 NORMAL (0), 60-79.99 MEDIUM (-4), 80-99.99 HIGH (-8), 100-119.99 CRITICAL (-15), >=120 EMERGENCY (-20)
            if ($temperatureC < 60.00) {
                return ['status' => 'ACTIVE', 'severity' => 'NORMAL', 'operational_status' => 'NORMAL', 'deduction' => 0.00, 'rule_version' => 'THERMOVISION_HAR_GTT_v1.0'];
            }
            if ($temperatureC < 80.00) {
                return ['status' => 'ACTIVE', 'severity' => 'MEDIUM', 'operational_status' => 'MEDIUM', 'deduction' => 4.00, 'rule_version' => 'THERMOVISION_HAR_GTT_v1.0'];
            }
            if ($temperatureC < 100.00) {
                return ['status' => 'ACTIVE', 'severity' => 'HIGH', 'operational_status' => 'HIGH', 'deduction' => 8.00, 'rule_version' => 'THERMOVISION_HAR_GTT_v1.0'];
            }
            if ($temperatureC < 120.00) {
                return ['status' => 'ACTIVE', 'severity' => 'CRITICAL', 'operational_status' => 'CRITICAL', 'deduction' => 15.00, 'rule_version' => 'THERMOVISION_HAR_GTT_v1.0'];
            }
            return ['status' => 'ACTIVE', 'severity' => 'EMERGENCY', 'operational_status' => 'EMERGENCY', 'deduction' => 20.00, 'rule_version' => 'THERMOVISION_HAR_GTT_v1.0'];
        }

        // Default Domain: JTM_PDKB
        // Ladder JTM / PDKB: <50 NORMAL (0), 50-69.99 MEDIUM (-4), 70-84.99 HIGH (-8), 85-99.99 CRITICAL (-15), >=100 EMERGENCY (-20)
        if ($temperatureC < 50.00) {
            return ['status' => 'ACTIVE', 'severity' => 'NORMAL', 'operational_status' => 'NORMAL', 'deduction' => 0.00, 'rule_version' => 'THERMOVISION_JTM_PDKB_v1.0'];
        }
        if ($temperatureC < 70.00) {
            return ['status' => 'ACTIVE', 'severity' => 'MEDIUM', 'operational_status' => 'MEDIUM', 'deduction' => 4.00, 'rule_version' => 'THERMOVISION_JTM_PDKB_v1.0'];
        }
        if ($temperatureC < 85.00) {
            return ['status' => 'ACTIVE', 'severity' => 'HIGH', 'operational_status' => 'HIGH', 'deduction' => 8.00, 'rule_version' => 'THERMOVISION_JTM_PDKB_v1.0'];
        }
        if ($temperatureC < 100.00) {
            return ['status' => 'ACTIVE', 'severity' => 'CRITICAL', 'operational_status' => 'CRITICAL', 'deduction' => 15.00, 'rule_version' => 'THERMOVISION_JTM_PDKB_v1.0'];
        }
        return ['status' => 'ACTIVE', 'severity' => 'EMERGENCY', 'operational_status' => 'EMERGENCY', 'deduction' => 20.00, 'rule_version' => 'THERMOVISION_JTM_PDKB_v1.0'];
    }

    /**
     * Phase 2A: Vegetation / RoW Risk Rule Resolver (Distance Ladder & Wind Contact Emergency Override)
     */
    public static function resolveVegetationDeduction(float $distanceMeters, bool $windContact = false): array
    {
        if ($distanceMeters < 0) {
            throw new InvalidArgumentException('distanceMeters tidak boleh bernilai negatif.');
        }

        // Priority 1: Wind Contact Emergency Override
        if ($windContact === true) {
            return [
                'status'             => 'ACTIVE',
                'severity'           => 'EMERGENCY',
                'operational_status' => 'EMERGENCY',
                'deduction'          => 20.00,
                'reason_code'        => 'WIND_CONTACT_NETWORK',
                'rule_version'       => 'VEGETATION_ROW_v1.0',
            ];
        }

        // Priority 2: Distance Ladder Rules
        if ($distanceMeters <= 0.50) {
            return [
                'status'             => 'ACTIVE',
                'severity'           => 'EMERGENCY',
                'operational_status' => 'EMERGENCY',
                'deduction'          => 20.00,
                'reason_code'        => 'DISTANCE_LE_0_5M',
                'rule_version'       => 'VEGETATION_ROW_v1.0',
            ];
        }

        if ($distanceMeters < 1.00) {
            return [
                'status'             => 'ACTIVE',
                'severity'           => 'CRITICAL',
                'operational_status' => 'CRITICAL',
                'deduction'          => 15.00,
                'reason_code'        => 'DISTANCE_0_5_TO_1M',
                'rule_version'       => 'VEGETATION_ROW_v1.0',
            ];
        }

        if ($distanceMeters <= 2.00) {
            return [
                'status'             => 'ACTIVE',
                'severity'           => 'HIGH',
                'operational_status' => 'HIGH',
                'deduction'          => 8.00,
                'reason_code'        => 'DISTANCE_1_TO_2M',
                'rule_version'       => 'VEGETATION_ROW_v1.0',
            ];
        }

        if ($distanceMeters <= 3.00) {
            return [
                'status'             => 'ACTIVE',
                'severity'           => 'MEDIUM',
                'operational_status' => 'MEDIUM',
                'deduction'          => 4.00,
                'reason_code'        => 'DISTANCE_2_TO_3M',
                'rule_version'       => 'VEGETATION_ROW_v1.0',
            ];
        }

        return [
            'status'             => 'ACTIVE',
            'severity'           => 'NORMAL',
            'operational_status' => 'SAFE',
            'deduction'          => 0.00,
            'reason_code'        => 'DISTANCE_GT_3M',
            'rule_version'       => 'VEGETATION_ROW_v1.0',
        ];
    }

    /**
     * Klasifikasi Kategori Health Index Resmi
     */
    public static function classifyCategory(float $score): string
    {
        if ($score >= 90.00) {
            return 'VERY_GOOD';
        }
        if ($score >= 70.00) {
            return 'GOOD';
        }
        if ($score >= 50.00) {
            return 'FAIR';
        }
        if ($score >= 30.00) {
            return 'POOR';
        }
        return 'CRITICAL';
    }

    /**
     * Main Calculation Pipeline for a Single Asset (Read/Calculation Authority)
     */
    public function calculateAssetHealthIndex(int $assetId, string $triggerEvent = 'MANUAL'): array
    {
        if (empty($assetId)) {
            throw new InvalidArgumentException('asset_id wajib tersedia untuk kalkulasi Health Index.');
        }

        $now = Time::now('Asia/Jakarta')->toDateTimeString();

        // 1. Fetch Asset Record
        $asset = $this->assetModel->find($assetId);
        if (!$asset) {
            throw new InvalidArgumentException("Aset dengan ID {$assetId} tidak ditemukan.");
        }

        $baseScore = 100.00;

        // 2. Fetch Active Master Finding Cases with Explicit Deterministic Ordering
        $builder = $this->db->table('temuan');
        $builder->where('asset_id', $assetId);
        $builder->where('deleted_at IS NULL');
        $builder->whereIn('case_status', ['OPEN', 'IN_PROGRESS', 'WAITING_EXECUTION']);
        $builder->orderBy('first_detected_at', 'ASC');
        $builder->orderBy('id', 'ASC'); // Explicit Ordering for Hash Reproducibility
        $activeCases = $builder->get()->getResultArray();

        // 3. Compute ACTIVE_FINDINGS Deduction
        $findingsTotalDeduction = 0.00;
        $findingBreakdown = [];

        foreach ($activeCases as $case) {
            $severity = strtoupper((string)($case['current_severity'] ?: $case['prioritas'] ?: 'MEDIUM'));
            $baseSevDed = self::$baseSeverityDeduction[$severity] ?? 5.00;

            // Compute Age Days from first_detected_at or tanggal_temuan
            $firstDetected = $case['first_detected_at'] ?: $case['tanggal_temuan'] ?: $case['created_at'];
            $firstTime = new \DateTime($firstDetected);
            $currentTime = new \DateTime($now);
            $ageDays = (int)$firstTime->diff($currentTime)->format('%a');

            $agingDed = self::calculateAgingDeduction($ageDays);

            // Compute Recurrence Deduction
            $recCount = (int)($case['recurrence_count'] ?? 0);
            $recDed   = self::calculateRecurrenceDeduction($recCount);

            // Compute Escalation Deduction (Peak Severity > Current Severity)
            $escalationDed = 0.00;
            if (!empty($case['peak_severity']) && !empty($case['current_severity'])) {
                $peakRank = self::$baseSeverityDeduction[strtoupper($case['peak_severity'])] ?? 0;
                $currRank = self::$baseSeverityDeduction[strtoupper($case['current_severity'])] ?? 0;
                if ($peakRank > $currRank) {
                    $escalationDed = 5.00;
                }
            }

            $caseTotalDed = $baseSevDed + $agingDed + $recDed + $escalationDed;
            $findingsTotalDeduction += $caseTotalDed;

            $findingBreakdown[] = [
                'finding_id'         => (int)$case['id'],
                'nomor_temuan'       => $case['nomor_temuan'],
                'jenis_temuan'       => $case['jenis_temuan'],
                'severity'           => $severity,
                'days_open'          => $ageDays,
                'observation_count'  => (int)$case['observation_count'],
                'recurrence_count'   => $recCount,
                'base_severity_ded'  => $baseSevDed,
                'aging_ded'          => $agingDed,
                'recurrence_ded'     => $recDed,
                'escalation_ded'     => $escalationDed,
                'total_case_ded'     => $caseTotalDed,
            ];
        }

        // Cap ACTIVE_FINDINGS deduction at 30.00 points max
        $cappedFindingsDeduction = min(30.00, $findingsTotalDeduction);

        // 4. Compute Other HI Components (Age, Inspection Overdue, Thermo, Vegetation, Material, Construction)
        // Age Deduction: -1.0 point per 5 years asset age (max -10)
        $installYear = (int)($asset['tahun_pemasangan'] ?? date('Y'));
        $currentYear = (int)date('Y');
        $assetAgeYears = max(0, $currentYear - $installYear);
        $ageDeduction  = min(10.00, floor($assetAgeYears / 5) * 1.00);

        // Inspection Overdue Deduction: -3.00 if last inspection > 180 days
        $lastInspectionAt = $asset['last_inspection_at'] ?? null;
        $inspectionOverdueDeduction = 0.00;
        if ($lastInspectionAt) {
            $inspTime = new \DateTime($lastInspectionAt);
            $inspDays = (int)$inspTime->diff(new \DateTime($now))->format('%a');
            if ($inspDays > 180) {
                $inspectionOverdueDeduction = 3.00;
            }
        }

        // Phase 2F: Fetch Official Latest Valid Vegetation Observation
        $vegObs = $this->db->table('vegetation_observations')
            ->where('asset_id', $assetId)
            ->where('is_valid', 1)
            ->orderBy('observed_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $vegDeduction = 0.00;
        $vegDetail    = [
            'code'      => 'VEGETATION',
            'name'      => 'Risiko Vegetasi Jaringan',
            'status'    => 'PLACEHOLDER',
            'deduction' => 0.00,
        ];

        if ($vegObs) {
            $distMeters  = (float)$vegObs['distance_meters'];
            $windContact = (bool)$vegObs['wind_contact'];
            $vegRes      = self::resolveVegetationDeduction($distMeters, $windContact);
            
            $vegDeduction = $vegRes['deduction'];
            $vegDetail    = [
                'code'               => 'VEGETATION',
                'name'               => 'Risiko Vegetasi Jaringan',
                'status'             => 'ACTIVE',
                'observation_id'     => (int)$vegObs['id'],
                'inspection_id'      => $vegObs['inspection_id'] ? (int)$vegObs['inspection_id'] : null,
                'distance_meters'    => $distMeters,
                'wind_contact'       => $windContact,
                'observed_at'        => $vegObs['observed_at'],
                'deduction'          => -$vegDeduction,
                'severity'           => $vegRes['severity'],
                'operational_status' => $vegRes['operational_status'],
                'reason_code'        => $vegRes['reason_code'],
                'rule_version'       => $vegRes['rule_version'],
            ];
        }

        // Phase 2F: Fetch Official Latest Valid Thermovision Observation
        $thermoObs = $this->db->table('thermovision_observations')
            ->where('asset_id', $assetId)
            ->where('is_valid', 1)
            ->orderBy('observed_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $thermoDeduction = 0.00;
        $thermoDetail    = [
            'code'               => 'THERMOVISION',
            'name'               => 'Pengukuran Thermovision Hotspot',
            'status'             => 'PLACEHOLDER',
            'inspection_domain'  => 'JTM_PDKB',
            'temperature_c'      => 0.00,
            'severity'           => 'NORMAL',
            'operational_status' => 'NORMAL',
            'deduction'          => 0.00,
            'rule_version'       => 'THERMOVISION_v1.0_PLACEHOLDER',
        ];

        if ($thermoObs) {
            $domain      = $thermoObs['inspection_domain'] ?: 'JTM_PDKB';
            $measuredC   = (float)$thermoObs['measured_temperature_c'];
            $thermoRes   = self::resolveThermovisionDeduction($domain, $measuredC);

            $thermoDeduction = $thermoRes['deduction'];
            $thermoDetail    = [
                'code'               => 'THERMOVISION',
                'name'               => 'Pengukuran Thermovision Hotspot',
                'status'             => 'ACTIVE',
                'observation_id'     => (int)$thermoObs['id'],
                'inspection_id'      => $thermoObs['inspection_id'] ? (int)$thermoObs['inspection_id'] : null,
                'inspection_domain'  => $domain,
                'temperature_c'      => $measuredC,
                'ambient_temperature_c' => $thermoObs['ambient_temperature_c'] ? (float)$thermoObs['ambient_temperature_c'] : null,
                'measurement_point'  => $thermoObs['measurement_point'],
                'observed_at'        => $thermoObs['observed_at'],
                'deduction'          => -$thermoDeduction,
                'severity'           => $thermoRes['severity'],
                'operational_status' => $thermoRes['operational_status'],
                'rule_version'       => $thermoRes['rule_version'],
            ];
        }

        // Aggregate All Component Deductions
        $explanationJson = [
            'ACTIVE_FINDINGS' => [
                'code'               => 'ACTIVE_FINDINGS',
                'name'               => 'Temuan Aktif Belum Selesai',
                'status'             => 'ACTIVE',
                'deduction'          => -$cappedFindingsDeduction,
                'uncapped_deduction' => -$findingsTotalDeduction,
                'active_cases_count' => count($activeCases),
                'breakdown'          => $findingBreakdown,
            ],
            'AGE' => [
                'code'         => 'AGE',
                'name'         => 'Umur / Masa Pakai Aset',
                'status'       => 'ACTIVE',
                'deduction'    => -$ageDeduction,
                'asset_age_yrs'=> $assetAgeYears,
            ],
            'INSPECTION' => [
                'code'      => 'INSPECTION',
                'name'      => 'Jadwal Inspeksi Terlewat',
                'status'    => 'ACTIVE',
                'deduction' => -$inspectionOverdueDeduction,
            ],
            'VEGETATION'       => $vegDetail,
            'MATERIAL_ANOMALY' => [
                'code'      => 'MATERIAL_ANOMALY',
                'name'      => 'Anomali Material & Sparepart',
                'status'    => 'PLACEHOLDER',
                'deduction' => 0.00,
            ],
            'THERMOVISION' => $thermoDetail,
            'CONSTRUCTION' => [
                'code'      => 'CONSTRUCTION',
                'name'      => 'Faktor Jenis Konstruksi',
                'status'    => 'PLACEHOLDER',
                'deduction' => 0.00,
            ],
        ];

        $totalDeduction = $cappedFindingsDeduction + $ageDeduction + $inspectionOverdueDeduction + $vegDeduction + $thermoDeduction;
        $finalScore     = max(0.00, round($baseScore - $totalDeduction, 2));
        $category       = self::classifyCategory($finalScore);

        // Build Active Rules Snapshot JSON for reproducible audit
        $rulesSnapshotJson = [
            'ACTIVE_FINDINGS_CAP' => 30.00,
            'SEVERITY_DEDUCTION'  => self::$baseSeverityDeduction,
            'AGING_MODEL'         => 'MODEL_A_STEPPED',
            'THERMOVISION_LADDER' => [
                'JTM_PDKB' => ['MEDIUM' => 50.00, 'HIGH' => 70.00, 'CRITICAL' => 85.00, 'EMERGENCY' => 100.00],
                'HAR_GTT'  => ['MEDIUM' => 60.00, 'HIGH' => 80.00, 'CRITICAL' => 100.00, 'EMERGENCY' => 120.00],
            ],
            'ENGINE_VERSION'      => self::ENGINE_VERSION,
        ];

        // Generate Canonical SHA-256 Calculation Fingerprint with Recursive Array Key Sorting
        $rawHashPayload = [
            'asset_id'         => (int)$assetId,
            'base_score'       => number_format($baseScore, 2, '.', ''),
            'total_deduction'  => number_format($totalDeduction, 2, '.', ''),
            'final_score'      => number_format($finalScore, 2, '.', ''),
            'category'         => $category,
            'explanation'      => $explanationJson,
            'rules_snapshot'   => $rulesSnapshotJson,
            'engine_version'   => self::ENGINE_VERSION,
            'trigger_event'    => $triggerEvent,
        ];

        $canonicalPayload = self::canonicalizeArrayRecursive($rawHashPayload);
        $canonicalJson   = json_encode($canonicalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $calculationHash = hash('sha256', $canonicalJson);

        return [
            'asset_id'            => (int)$assetId,
            'kode_asset'          => $asset['kode_asset'] ?? ('AST-' . $assetId),
            'nama_asset'          => $asset['nama_asset'] ?? '',
            'base_score'          => $baseScore,
            'total_deduction'     => $totalDeduction,
            'final_score'         => $finalScore,
            'category'            => $category,
            'explanation_json'    => $explanationJson,
            'rules_snapshot_json' => $rulesSnapshotJson,
            'calculation_hash'    => $calculationHash,
            'engine_version'      => self::ENGINE_VERSION,
            'trigger_event'       => $triggerEvent,
            'calculated_at'       => $now,
        ];
    }

    /**
     * Phase 1C: Mandatory Strict Atomic Persistence (Snapshot Update + History Insert)
     * With Deterministic Transaction Rollback Flag & Fully Qualified \RuntimeException
     */
    public function persistHealthIndexCalculation(
        int $assetId,
        string $triggerEvent = 'EVENT',
        ?int $userId = null
    ): array {
        // 1. Calculate Health Index via Core Engine
        $calcResult = $this->calculateAssetHealthIndex($assetId, $triggerEvent);

        $transactionCompleted = false;

        $this->db->transStart();

        try {
            // 2. Step A: Mandatory Snapshot Cache Update on assets table
            $snapshotUpdated = $this->db->table('assets')
                ->where('id', $assetId)
                ->update([
                    'health_score'                    => $calcResult['final_score'],
                    'health_category'                 => $calcResult['category'],
                    'health_index_last_calculated_at' => $calcResult['calculated_at'],
                    'updated_at'                      => $calcResult['calculated_at'],
                ]);

            if ($snapshotUpdated === false) {
                throw new \RuntimeException('Gagal memperbarui Health Index snapshot cache aset.');
            }

            // 3. Step B: Mandatory Audit Trail History Insert on asset_health_history table
            $historyInserted = $this->db->table('asset_health_history')->insert([
                'asset_id'            => $assetId,
                'base_score'          => $calcResult['base_score'],
                'total_deduction'     => $calcResult['total_deduction'],
                'health_score'        => $calcResult['final_score'],
                'health_category'     => $calcResult['category'],
                'explanation_json'    => json_encode($calcResult['explanation_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'rules_snapshot_json' => json_encode($calcResult['rules_snapshot_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'trigger_event'       => $triggerEvent,
                'calculation_source'  => 'EVENT',
                'engine_version'      => $calcResult['engine_version'],
                'calculation_hash'    => $calcResult['calculation_hash'],
                'calculated_by'       => $userId,
                'calculated_at'       => $calcResult['calculated_at'],
                'created_at'          => $calcResult['calculated_at'],
            ]);

            if ($historyInserted === false) {
                throw new \RuntimeException('Gagal mencatat Health Index history record.');
            }

            $this->db->transComplete();
            $transactionCompleted = true;

            if ($this->db->transStatus() === false) {
                $dbErr = $this->db->error();
                throw new \RuntimeException('Gagal mempersist calculation snapshot dan health history ke database. Details: ' . ($dbErr['message'] ?? 'Unknown error'));
            }

            return $calcResult;
        } catch (\Throwable $e) {
            if (!$transactionCompleted) {
                $this->db->transRollback();
            }
            throw $e;
        }
    }
}
