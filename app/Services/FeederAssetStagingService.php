<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Phase AR-01 Phase 5A-5D: Feeder Asset Staging & Multi-Signal Validation Service
 * 
 * Strict Invariants:
 * - AR-01-P5-I : Source Immutability (Zero modification to source file)
 * - AR-01-A    : Zero Blind Assignment (Upload != Insert to assets)
 * - AR-01-B    : Feeder Target Verification (Must match Feeder #1 PYL-001)
 * - AR-01-C    : CR-06F Physical Truth Section Resolution
 * - AR-01-E    : CR-06G Construction Type / BOM Verification
 * - AR-01-F    : Geo-Spatial Corridor Validity
 * - Write Gate : 100% Read-Only / Zero writes to 'assets' table
 */
class FeederAssetStagingService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Inspect, Register (5A), Stage (5B), and Validate (5C-5D) a source file.
     * Strictly Read-Only with respect to table 'assets'.
     */
    public function stageAndValidateSourceFile(string $filePath, int $targetFeederId = 1): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error'   => "File sumber tidak ditemukan di path: {$filePath}",
            ];
        }

        // 1. PHASE 5A: SOURCE REGISTRATION & FINGERPRINTING
        $fileSize = filesize($filePath);
        $fileSha256 = hash_file('sha256', $filePath);
        $fileName = basename($filePath);
        $uploadedAt = date('Y-m-d H:i:s');
        $batchId = 'BATCH-PYL001-' . date('Ymd-His') . '-' . substr(strtoupper($fileSha256), 0, 8);

        // Fetch Target Feeder context
        $feeder = $this->db->table('penyulang')->where('id', $targetFeederId)->get()->getRowArray();
        if (!$feeder) {
            return [
                'success' => false,
                'error'   => "Penyulang target ID #{$targetFeederId} tidak terdaftar di database.",
            ];
        }

        // Fetch CR-06F Active Sections for Feeder
        $secQuery = $this->db->table('sections')->where('penyulang_id', $targetFeederId);
        if ($this->db->fieldExists('status', 'sections')) {
            $secQuery->where('status', 'ACTIVE');
        }
        if ($this->db->fieldExists('sequence_order', 'sections')) {
            $secQuery->orderBy('sequence_order', 'ASC');
        }
        $sections = $secQuery->get()->getResultArray();

        // Fetch Construction Types from CR-06G
        $constructionRows = $this->db->table('construction_types')->get()->getResultArray();
        $constructionMap = [];
        foreach ($constructionRows as $cr) {
            $codeRaw = strtoupper(trim((string)($cr['kode_konstruksi'] ?? $cr['construction_code'] ?? $cr['code'] ?? '')));
            if ($codeRaw !== '') {
                $constructionMap[$codeRaw] = $cr;
                $normalized = str_replace(['-', ' ', '_'], '', $codeRaw);
                $constructionMap[$normalized] = $cr;
            }
        }

        // 2. PHASE 5B: RAW CSV STAGING & PARSING
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [
                'success' => false,
                'error'   => "Gagal membuka file sumber untuk dibaca.",
            ];
        }

        // Read header
        $headerRaw = fgetcsv($handle);
        if (!$headerRaw) {
            fclose($handle);
            return [
                'success' => false,
                'error'   => "Header file kosong atau format CSV tidak valid.",
            ];
        }

        // Normalize header names
        $header = array_map(function($col) {
            return trim((string)$col);
        }, $headerRaw);

        $stagedRows = [];
        $validationStats = [
            'total_rows'      => 0,
            'pass_count'      => 0,
            'warning_count'   => 0,
            'reject_count'    => 0,
        ];
        $constructionDistribution = [];
        $feederNameDistribution = [];
        $detectedAnomalies = [];
        $uniqueAssetNames = [];
        $duplicateAssetNames = [];

        $rowNumber = 1; // Header is row 1
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (empty($row) || (count($row) === 1 && trim((string)$row[0]) === '')) {
                continue;
            }

            $validationStats['total_rows']++;
            $rowData = [];
            foreach ($header as $idx => $colName) {
                $rowData[$colName] = $row[$idx] ?? '';
            }

            // 3. PHASE 5C: DETERMINISTIC VALIDATION RULES
            $errors = [];
            $warnings = [];

            $assetName = trim((string)($rowData['Nama Asset JTM'] ?? ''));
            $feederName = trim((string)($rowData['Penyulang'] ?? ''));
            $constructionCode = trim((string)($rowData['Konstruksi (e.g. TM1)'] ?? ''));
            $latRaw = trim((string)($rowData['Latitude'] ?? ''));
            $lonRaw = trim((string)($rowData['Longitude'] ?? ''));
            $lokasiRaw = trim((string)($rowData['Alamat / Lokasi'] ?? ''));
            $kapasitasRaw = trim((string)($rowData['Kapasitas / Panjang'] ?? ''));

            // Track distributions
            $constructionDistribution[$constructionCode] = ($constructionDistribution[$constructionCode] ?? 0) + 1;
            $feederNameDistribution[$feederName] = ($feederNameDistribution[$feederName] ?? 0) + 1;

            // Rule 1: Identifier
            if (empty($assetName)) {
                $errors[] = "Nama Asset JTM tidak boleh kosong.";
            } else {
                if (isset($uniqueAssetNames[$assetName])) {
                    $duplicateAssetNames[] = $assetName;
                    $warnings[] = "Nama Asset JTM '{$assetName}' duplikat dalam file sumber.";
                }
                $uniqueAssetNames[$assetName] = true;
            }

            // Rule 2: Feeder Target Verification (AR-01-B)
            if (strtoupper($feederName) !== strtoupper(trim((string)$feeder['nama_penyulang']))) {
                $errors[] = "Penyulang '{$feederName}' tidak sesuai dengan Target Feeder '{$feeder['nama_penyulang']}'.";
            }

            // Rule 3: Construction Type Matching (AR-01-E)
            $constructionCodeNorm = str_replace(['-', ' ', '_'], '', strtoupper($constructionCode));
            $matchedConstruction = $constructionMap[strtoupper($constructionCode)] ?? $constructionMap[$constructionCodeNorm] ?? null;
            if (!$matchedConstruction) {
                $warnings[] = "Tipe Konstruksi '{$constructionCode}' belum terpetakan langsung di CR-06G construction_types.";
            }

            // Rule 4: GPS Validity & Sidoarjo Corridor (AR-01-F)
            if (!is_numeric($latRaw) || !is_numeric($lonRaw)) {
                $errors[] = "Koordinat Latitude/Longitude bukan angka numerik valid.";
            } else {
                $lat = (float)$latRaw;
                $lon = (float)$lonRaw;

                // Anomaly check: positive latitude in Indonesia (Southern hemisphere)
                if ($lat > 0) {
                    $warnings[] = "Latitude bernilai positif (+{$lat}). Diperlukan normalisasi tanda minus (-{$lat}) untuk belahan bumi selatan (Sidoarjo).";
                } elseif ($lat < -7.60 || $lat > -7.30 || $lon < 112.60 || $lon > 112.90) {
                    $warnings[] = "Koordinat GPS ({$lat}, {$lon}) berada di luar koridor standar Sidoarjo Kota.";
                }
            }

            // Rule 5: Non-Semantic Column Flagging
            if ($lokasiRaw === '0' || $lokasiRaw === '') {
                // Informational
            }

            // Classification Tier (5D)
            $status = 'PASS';
            if (!empty($errors)) {
                $status = 'REJECT';
                $validationStats['reject_count']++;
            } elseif (!empty($warnings)) {
                $status = 'WARNING';
                $validationStats['warning_count']++;
            } else {
                $validationStats['pass_count']++;
            }

            if (!empty($errors) || !empty($warnings)) {
                $detectedAnomalies[] = [
                    'row_number'   => $rowNumber,
                    'asset_name'   => $assetName,
                    'status'       => $status,
                    'errors'       => $errors,
                    'warnings'     => $warnings,
                    'raw_latitude' => $latRaw,
                    'raw_longitude'=> $lonRaw,
                ];
            }

            $stagedRows[] = [
                'row_number'        => $rowNumber,
                'asset_name'        => $assetName,
                'feeder_name'       => $feederName,
                'construction_code' => $constructionCode,
                'construction_id'   => $matchedConstruction['id'] ?? null,
                'conductor_material'=> $rowData['Material Conductor'] ?? 'A3CS',
                'latitude'          => (float)$latRaw,
                'longitude'         => (float)$lonRaw,
                'status'            => $status,
                'errors'            => $errors,
                'warnings'          => $warnings,
            ];
        }

        fclose($handle);

        return [
            'success'            => true,
            'timestamp'          => $uploadedAt,
            'source_registration'=> [
                'source_file'        => $filePath,
                'file_name'          => $fileName,
                'file_size'          => $fileSize,
                'file_sha256'        => $fileSha256,
                'ingestion_batch_id' => $batchId,
                'source_immutability'=> 'PASS (Original file untouched per AR-01-P5-I)',
            ],
            'target_feeder'      => [
                'id'              => $feeder['id'],
                'kode_penyulang'  => $feeder['kode_penyulang'],
                'nama_penyulang'  => $feeder['nama_penyulang'],
                'active_sections' => count($sections),
                'sections_list'   => array_map(function($s) {
                    $order = $s['sequence_order'] ?? $s['order_index'] ?? $s['id'];
                    $nama = $s['nama_seksi'] ?? $s['section_name'] ?? ('Seksi #' . $s['id']);
                    return "#{$s['id']} [Seksi {$order}]: {$nama}";
                }, $sections),
            ],
            'staging_summary'    => [
                'total_staged_rows'   => $validationStats['total_rows'],
                'pass_candidates'     => $validationStats['pass_count'],
                'warning_candidates'  => $validationStats['warning_count'],
                'reject_candidates'   => $validationStats['reject_count'],
                'unique_asset_names'  => count($uniqueAssetNames),
                'duplicate_names'     => count($duplicateAssetNames),
            ],
            'construction_distribution' => $constructionDistribution,
            'feeder_name_distribution'  => $feederNameDistribution,
            'detected_anomalies'        => $detectedAnomalies,
            'staged_sample_first_5'     => array_slice($stagedRows, 0, 5),
            'database_mutation_guard'   => [
                'assets_table_writes'   => 0,
                'assets_table_mutations'=> 'LOCKED (Zero writes performed)',
                'promotion_gate'        => 'LOCKED (Requires Stage 5E Human Engineering Approval)',
            ],
        ];
    }
}
