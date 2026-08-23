<?php

namespace App\Services;

use App\Models\TemuanModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;
use RuntimeException;

class FindingMatchingService
{
    protected TemuanModel $temuanModel;
    protected BaseConnection $db;

    /**
     * Ranking hirarki severity resmi (LOW < MEDIUM < HIGH < CRITICAL)
     */
    protected static array $severityRank = [
        'LOW'      => 1,
        'MEDIUM'   => 2,
        'HIGH'     => 3,
        'CRITICAL' => 4,
    ];

    public function __construct(?TemuanModel $temuanModel = null, ?BaseConnection $db = null)
    {
        $this->temuanModel = $temuanModel ?? new TemuanModel();
        $this->db          = $db ?? \Config\Database::connect();
    }

    /**
     * Unit 1: Canonicalization Layer (Trim, Uppercase, Whitespace Normalization)
     */
    public static function canonicalizeString(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $cleaned = preg_replace('/\s+/', ' ', trim($value));
        return strtoupper($cleaned);
    }

    /**
     * Unit 2: Canonical SHA-256 Fingerprint Engine
     * Formula: SHA256(ASSET_ID | JENIS_TEMUAN | COMPONENT_CODE | DEFECT_LOCATION_CODE)
     */
    public static function generateFingerprint(
        $assetId,
        string $jenisTemuan,
        ?string $componentCode = '',
        ?string $defectLocationCode = ''
    ): string {
        if (empty($assetId)) {
            throw new InvalidArgumentException('asset_id wajib tersedia untuk menghasilkan Finding Fingerprint.');
        }

        $cAssetId = self::canonicalizeString((string)$assetId);
        $cJenis   = self::canonicalizeString($jenisTemuan);
        $cComp    = self::canonicalizeString($componentCode);
        $cLoc     = self::canonicalizeString($defectLocationCode);

        $canonicalPayload = $cAssetId . '|' . $cJenis . '|' . $cComp . '|' . $cLoc;

        return hash('sha256', $canonicalPayload);
    }

    /**
     * Unit 3: Canonical Severity Comparison Engine (LOW < MEDIUM < HIGH < CRITICAL)
     */
    public static function calculatePeakSeverity(?string $existingSeverity, ?string $newSeverity): string
    {
        $oldRank = self::$severityRank[strtoupper((string)$existingSeverity)] ?? 0;
        $newRank = self::$severityRank[strtoupper((string)$newSeverity)] ?? 0;

        $maxRank = max($oldRank, $newRank);

        foreach (self::$severityRank as $name => $rank) {
            if ($rank === $maxRank) {
                return $name;
            }
        }

        return strtoupper((string)($newSeverity ?: 'MEDIUM'));
    }

    /**
     * Unit 4: Smart Open Case Matcher & Process Engine with MySQL Advisory Lock (GET_LOCK) & Transaction
     */
    public function processInspectionFinding(array $inputData, int $userId): array
    {
        $assetId     = $inputData['asset_id'] ?? null;
        $jenisTemuan = $inputData['jenis_temuan'] ?? '';
        $compCode    = $inputData['component_code'] ?? null;
        $locCode     = $inputData['defect_location_code'] ?? null;
        $severity    = strtoupper((string)($inputData['severity'] ?? 'MEDIUM'));
        $notes       = $inputData['detail_temuan'] ?? '';
        $fotoPath    = $inputData['foto_path'] ?? null;

        // Invariant Validation: asset_id mandatory
        if (empty($assetId)) {
            throw new InvalidArgumentException('asset_id wajib tersedia untuk menghasilkan Finding Fingerprint.');
        }

        // Standardized Timezone Clock
        $now = Time::now('Asia/Jakarta')->toDateTimeString();

        // Generate Canonical Fingerprint
        $fingerprint = self::generateFingerprint($assetId, $jenisTemuan, $compCode, $locCode);

        // MySQL Advisory Named Lock Key (Independent of row existence)
        $lockKey = 'fi_lock_' . substr(md5($fingerprint), 0, 24);

        // Acquire Advisory Lock (5-second timeout)
        $lockRow = $this->db->query("SELECT GET_LOCK(?, 5) AS acquired", [$lockKey])->getRowArray();

        if (empty($lockRow['acquired']) || (int)$lockRow['acquired'] !== 1) {
            throw new RuntimeException("Gagal mendapatkan lock konkurensi untuk fingerprint temuan. Silakan coba beberapa saat lagi.");
        }

        try {
            // Begin Transaction for Atomic Matching & Concurrency Protection
            $this->db->transStart();

            // Query Open Master Case with Explicit FOR UPDATE Row Lock
            $openCase = $this->db->query("
                SELECT * FROM `temuan` 
                WHERE `asset_id` = ? 
                  AND `finding_fingerprint` = ? 
                  AND `case_status` IN ('OPEN', 'IN_PROGRESS', 'WAITING_EXECUTION') 
                FOR UPDATE
            ", [(int)$assetId, $fingerprint])->getRowArray();

            if ($openCase) {
                // === WORKFLOW A: MATCH FOUND -> RE-OBSERVATION ===
                $newObsCount  = (int)$openCase['observation_count'] + 1;
                $newRecCount  = $newObsCount - 1;
                $peakSeverity = self::calculatePeakSeverity($openCase['peak_severity'], $severity);

                // 1. Update Master Case
                $this->db->table('temuan')->where('id', $openCase['id'])->update([
                    'last_observed_at'  => $now,
                    'observation_count' => $newObsCount,
                    'recurrence_count'  => $newRecCount,
                    'is_recurring'      => 1,
                    'current_severity'  => $severity,
                    'peak_severity'     => $peakSeverity,
                    'updated_at'        => $now,
                ]);

                // 2. Insert Re-Observation History Log
                $this->db->table('temuan_observations')->insert([
                    'temuan_id'       => $openCase['id'],
                    'inspection_id'   => $inputData['inspection_id'] ?? null,
                    'observed_at'     => $now,
                    'severity'        => $severity,
                    'condition_notes' => $notes,
                    'foto_path'       => $fotoPath,
                    'observed_by'     => $userId,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);

                $result = [
                    'action'        => 'RE_OBSERVATION',
                    'temuan_id'     => (int)$openCase['id'],
                    'fingerprint'   => $fingerprint,
                    'obs_count'     => $newObsCount,
                    'rec_count'     => $newRecCount,
                    'peak_severity' => $peakSeverity,
                    'message'       => "Observasi ulang berhasil dicatat pada Master Case #{$openCase['id']}",
                ];
            } else {
                // === WORKFLOW B: NO MATCH -> NEW MASTER FINDING CASE ===
                $nomorTemuan = $inputData['nomor_temuan'] ?? ('TMN-' . date('YmdHis') . '-' . rand(100, 999));

                // Fetch Asset context for hierarchy IDs
                $assetContext = $this->db->table('assets')->where('id', (int)$assetId)->get()->getRowArray();

                $this->db->table('temuan')->insert([
                    'nomor_temuan'         => $nomorTemuan,
                    'asset_id'             => (int)$assetId,
                    'ulp_id'               => $inputData['ulp_id'] ?? ($assetContext['ulp_id'] ?? 1),
                    'penyulang_id'         => $inputData['penyulang_id'] ?? ($assetContext['penyulang_id'] ?? 1),
                    'section_id'           => $inputData['section_id'] ?? ($assetContext['section_id'] ?? 1),
                    'jenis_temuan'         => $jenisTemuan,
                    'component_code'       => $compCode,
                    'defect_location_code' => $locCode,
                    'finding_fingerprint'  => $fingerprint,
                    'first_detected_at'    => $now,
                    'last_observed_at'     => $now,
                    'observation_count'    => 1,
                    'recurrence_count'     => 0,
                    'is_recurring'         => 0,
                    'is_overdue'           => 0,
                    'prioritas'            => $inputData['prioritas'] ?? $severity,
                    'current_severity'     => $severity,
                    'peak_severity'        => $severity,
                    'pelaksana'            => $inputData['pelaksana'] ?? 'INSPEKSI',
                    'potensi_gangguan'     => $inputData['potensi_gangguan'] ?? 'DGR',
                    'tanggal_temuan'       => $inputData['tanggal_temuan'] ?? date('Y-m-d'),
                    'status'               => 'BELUM', // Backward Compatibility
                    'case_status'          => 'OPEN',  // Anchor #8 Domain State
                    'created_by'           => $userId,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);

                $newCaseId = $this->db->insertID();

                // Insert Baseline Observation #1
                $this->db->table('temuan_observations')->insert([
                    'temuan_id'       => $newCaseId,
                    'inspection_id'   => $inputData['inspection_id'] ?? null,
                    'observed_at'     => $now,
                    'severity'        => $severity,
                    'condition_notes' => $notes,
                    'foto_path'       => $fotoPath,
                    'observed_by'     => $userId,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);

                $result = [
                    'action'        => 'NEW_MASTER_CASE',
                    'temuan_id'     => (int)$newCaseId,
                    'fingerprint'   => $fingerprint,
                    'obs_count'     => 1,
                    'rec_count'     => 0,
                    'peak_severity' => $severity,
                    'message'       => "Master Finding Case baru #{$newCaseId} berhasil dibuat",
                ];
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                $dbError = $this->db->error();
                throw new RuntimeException('Gagal memproses temuan inspeksi: Transaksi database dibatalkan. Details: ' . ($dbError['message'] ?? 'Unknown error'));
            }

            return $result;
        } finally {
            // Cleanly Release Advisory Lock
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lockKey]);
        }
    }
}
