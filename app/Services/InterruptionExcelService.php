<?php

namespace App\Services;

use ZipArchive;
use SimpleXMLElement;
use CodeIgniter\Database\BaseConnection;

/**
 * Service to parse, validate, preview, and import XLSX interruptions
 * strictly aligned to the 22 business columns in E:\GANGGUAN PENYULANG.xlsx
 */
class InterruptionExcelService
{
    protected BaseConnection $db;

    // The canonical 22 columns in Sheet1 of E:\GANGGUAN PENYULANG.xlsx
    public const CANONICAL_COLUMNS = [
        0  => 'UP3',
        1  => 'tanggal',
        2  => 'tgl ke-',
        3  => 'bln ke-',
        4  => 'thn',
        5  => 'GARDU INDUK',
        6  => 'ULP',
        7  => 'PMT-RECL/PMCB',
        8  => 'PENYULANG',
        9  => 'RECLOSER',
        10 => 'TGL PMT Trip',
        11 => 'JAM PMT Lepas',
        12 => 'TGL PMT Masuk',
        13 => 'JAM PMT Masuk',
        14 => 'masuk total',
        15 => 'LAMA PADAM',
        16 => 'KATEGORI',
        17 => 'RELE KERJA',
        18 => 'fasa',
        19 => 'cuaca',
        20 => 'KETERANGAN',
        21 => 'PENYEBAB SESUAI KODE GANGGUAN',
    ];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Parse raw rows directly from XLSX using native PHP ZipArchive & SimpleXML
     *
     * @param string $filePath Absolute path to XLSX
     * @return array [header => array, rows => array]
     * @throws \RuntimeException
     */
    public function parseXlsx(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Berkas Excel tidak ditemukan di: {$filePath}");
        }

        $zip = new ZipArchive();
        $res = $zip->open($filePath);
        if ($res !== true) {
            throw new \RuntimeException("Gagal membuka arsip berkas XLSX (ZipArchive error code: {$res}).");
        }

        // 1. Shared Strings
        $sharedStrings = [];
        $ssXmlStr = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXmlStr !== false) {
            $xmlSS = simplexml_load_string($ssXmlStr);
            if ($xmlSS) {
                foreach ($xmlSS->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $t = '';
                        foreach ($si->r as $r) {
                            $t .= (string)$r->t;
                        }
                        $sharedStrings[] = $t;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Read sheet1.xml (or first worksheet)
        $sheetXmlStr = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlStr === false) {
            $zip->close();
            throw new \RuntimeException("Worksheet sheet1.xml tidak ditemukan di dalam berkas XLSX.");
        }

        $xmlSheet = simplexml_load_string($sheetXmlStr);
        $zip->close();

        if (!$xmlSheet || !isset($xmlSheet->sheetData)) {
            throw new \RuntimeException("Format XML worksheet tidak valid.");
        }

        $header = [];
        $rows   = [];

        foreach ($xmlSheet->sheetData->row as $r) {
            $rowNum = (int)$r['r'];
            $cells = [];

            foreach ($r->c as $c) {
                $cellRef = (string)$c['r'];
                preg_match('/^([A-Z]+)/', $cellRef, $m);
                $colLetters = $m[1] ?? 'A';

                $type = (string)$c['t'];
                $val = '';
                if ($type === 's') {
                    $idx = (int)$c->v;
                    $val = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'e') {
                    $val = (string)$c->v; // e.g. #REF!, #VALUE!
                } else {
                    $val = (string)$c->v;
                }
                $cells[$colLetters] = $val;
            }

            // Convert to zero-indexed array
            $rowArr = [];
            foreach ($cells as $colLetters => $v) {
                $colIdx = 0;
                for ($i = 0; $i < strlen($colLetters); $i++) {
                    $colIdx = $colIdx * 26 + (ord($colLetters[$i]) - ord('A') + 1);
                }
                $rowArr[$colIdx - 1] = $v;
            }
            ksort($rowArr);

            if ($rowNum === 1) {
                $header = $rowArr;
                continue;
            }

            // Skip completely empty rows
            $hasContent = false;
            foreach ($rowArr as $v) {
                if (trim((string)$v) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (!$hasContent) {
                continue;
            }

            $rows[$rowNum] = $rowArr;
        }

        return [
            'header' => $header,
            'rows'   => $rows,
        ];
    }

    /**
     * Preview analysis of XLSX: validates headers, inspects rows, flags #REF! errors,
     * checks duplicate hashes against DB, and returns statistical summary.
     */
    public function previewXlsx(string $filePath): array
    {
        $parsed = $this->parseXlsx($filePath);
        $header = $parsed['header'];
        $rawRows = $parsed['rows'];

        // Strict header validation: Check that expected key columns match
        $headerIssues = [];
        $columnMapping = [];
        foreach (self::CANONICAL_COLUMNS as $idx => $expectedName) {
            $actualName = trim((string)($header[$idx] ?? ''));
            $isMatched = (strcasecmp($actualName, $expectedName) === 0);
            $columnMapping[] = [
                'index'    => $idx,
                'expected' => $expectedName,
                'actual'   => $actualName,
                'matched'  => $isMatched,
            ];
            // Key columns must strictly match
            if (!$isMatched && in_array($idx, [0, 1, 5, 6, 7, 8, 10, 11, 16, 17, 21])) {
                $headerIssues[] = "Kolom index {$idx} tidak sesuai: diharapkan '{$expectedName}', terbaca '{$actualName}'";
            }
        }

        $headerValid = empty($headerIssues);

        // Cache Master ULP and Master Penyulang for lookup
        $ulpLookup = $this->getUlpLookup();
        $feederLookup = $this->getFeederLookup();

        $validCount     = 0;
        $warningCount   = 0;
        $rejectedCount  = 0;
        $duplicateCount = 0;
        $errors         = [];
        $samples        = [];
        $existingHashes = $this->getExistingHashes();

        foreach ($rawRows as $rowNum => $row) {
            $rowIssues = [];
            $normalized = $this->normalizeRow($row, $rowNum, $ulpLookup, $feederLookup, $rowIssues);

            if ($normalized === null) {
                $rejectedCount++;
                $errors[] = [
                    'row'    => $rowNum,
                    'type'   => 'REJECTED',
                    'reason' => implode('; ', $rowIssues),
                    'data'   => array_slice($row, 0, 10),
                ];
                continue;
            }

            if (isset($existingHashes[$normalized['source_record_hash']])) {
                $duplicateCount++;
                $rowIssues[] = 'Sudah pernah diimpor (duplikat hash SHA-256)';
            }

            if (!empty($rowIssues)) {
                $warningCount++;
                $errors[] = [
                    'row'    => $rowNum,
                    'type'   => 'WARNING',
                    'reason' => implode('; ', $rowIssues),
                    'data'   => [
                        'tanggal'   => $normalized['event_date'],
                        'penyulang' => $normalized['feeder_name'],
                        'kategori'  => $normalized['kategori_raw'],
                    ],
                ];
            } else {
                $validCount++;
            }

            if (count($samples) < 5) {
                $samples[] = [
                    'row'         => $rowNum,
                    'event_date'  => $normalized['event_date'],
                    'ulp'         => $normalized['ulp_name'],
                    'penyulang'   => $normalized['feeder_name'],
                    'rele'        => $normalized['relay_trip_type'],
                    'kategori'    => $normalized['interruption_category'],
                    'lama_padam'  => $normalized['lama_padam_raw'],
                    'durasi_menit'=> $normalized['outage_duration_minutes'],
                    'keterangan'  => mb_strimwidth($normalized['field_narrative_raw'], 0, 45, '...'),
                ];
            }
        }

        return [
            'total_physical_rows' => count($rawRows),
            'header_valid'        => $headerValid,
            'header_issues'       => $headerIssues,
            'valid_count'         => $validCount,
            'warning_count'       => $warningCount,
            'rejected_count'      => $rejectedCount,
            'duplicate_count'     => $duplicateCount,
            'ready_to_import'     => $headerValid ? (($validCount + $warningCount) - $duplicateCount) : 0,
            'column_mapping'      => $columnMapping,
            'samples'             => $samples,
            'issues'              => array_slice($errors, 0, 50), // Show up to 50 issues in preview
            'total_issues_count'  => count($errors),
        ];
    }

    /**
     * Execute governed batch import inside a database transaction.
     * Prevents duplicate imports via source_record_hash.
     */
    public function importXlsx(string $filePath, int $userId = 1, string $batchCode = ''): array
    {
        $parsed = $this->parseXlsx($filePath);
        $header = $parsed['header'];
        $rawRows = $parsed['rows'];

        // Strict header guard: Fail-fast if workbook columns do not conform
        foreach ([0, 1, 5, 6, 7, 8, 10, 11, 16, 17, 21] as $keyCol) {
            $expected = self::CANONICAL_COLUMNS[$keyCol];
            $actual = trim((string)($header[$keyCol] ?? ''));
            if (strcasecmp($expected, $actual) !== 0) {
                throw new \InvalidArgumentException("Struktur kolom berkas tidak sesuai: Kolom #{$keyCol} seharusnya '{$expected}', terbaca '{$actual}'. Impor dibatalkan demi keamanan integritas database.");
            }
        }

        if (empty($batchCode)) {
            $batchCode = 'BATCH-XLSX-' . date('Ymd-His');
        }

        $ulpLookup    = $this->getUlpLookup();
        $feederLookup = $this->getFeederLookup();
        $existingHashes = $this->getExistingHashes();

        $this->db->transStart();

        // 1. Create entry in interruption_import_batches if table exists
        $batchId = 0;
        if ($this->db->tableExists('interruption_import_batches')) {
            $this->db->table('interruption_import_batches')->insert([
                'batch_code'         => $batchCode,
                'source_file_name'   => basename($filePath),
                'source_record_count'=> count($rawRows),
                'import_status'      => 'PROCESSING',
                'created_by'         => $userId,
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
            $batchId = (int)$this->db->insertID();
        }

        $insertedCount  = 0;
        $skippedDupCount= 0;
        $rejectedCount  = 0;
        $batchRecords   = [];
        $batchChunkSize = 200;

        foreach ($rawRows as $rowNum => $row) {
            $rowIssues = [];
            $normalized = $this->normalizeRow($row, $rowNum, $ulpLookup, $feederLookup, $rowIssues);

            if ($normalized === null) {
                $rejectedCount++;
                continue;
            }

            if (isset($existingHashes[$normalized['source_record_hash']])) {
                $skippedDupCount++;
                continue;
            }

            // Mark hash as used
            $existingHashes[$normalized['source_record_hash']] = true;
            $normalized['batch_id'] = $batchId;
            $batchRecords[] = $normalized;

            if (count($batchRecords) >= $batchChunkSize) {
                $this->db->table('historical_feeder_interruptions')->insertBatch($batchRecords);
                $insertedCount += count($batchRecords);
                $batchRecords = [];
            }
        }

        if (!empty($batchRecords)) {
            $this->db->table('historical_feeder_interruptions')->insertBatch($batchRecords);
            $insertedCount += count($batchRecords);
        }

        // Update batch record
        if ($batchId > 0) {
            $this->db->table('interruption_import_batches')->where('id', $batchId)->update([
                'imported_count'  => $insertedCount,
                'duplicate_count' => $skippedDupCount,
                'rejected_count'  => $rejectedCount,
                'import_status'   => 'COMPLETED',
                'completed_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException("Gagal melakukan impor data: Transaksi database dibatalkan (rollback).");
        }

        return [
            'batch_id'        => $batchId,
            'batch_code'      => $batchCode,
            'inserted_count'  => $insertedCount,
            'duplicate_count' => $skippedDupCount,
            'rejected_count'  => $rejectedCount,
            'total_rows'      => count($rawRows),
        ];
    }

    /**
     * Normalize a single row array according to the 22 columns
     */
    public function normalizeRow(array $row, int $rowNum, array $ulpLookup, array $feederLookup, array &$issues): ?array
    {
        // Col 1: tanggal
        $rawTanggal = $row[1] ?? null;
        if ($rawTanggal === null || trim((string)$rawTanggal) === '') {
            $issues[] = "Baris tidak memiliki tanggal kejadian";
            return null;
        }

        $eventDate = $this->parseExcelDate($rawTanggal);
        if (!$eventDate) {
            $issues[] = "Format tanggal kejadian tidak dapat diurai ('" . esc($rawTanggal) . "')";
            return null;
        }

        // Col 0: UP3
        $up3Name = trim((string)($row[0] ?? 'SDA'));

        // Col 5: GARDU INDUK
        $giName = trim((string)($row[5] ?? ''));

        // Col 6: ULP
        $rawUlp = trim((string)($row[6] ?? ''));
        $cleanUlpKey = strtoupper(trim(str_ireplace('ULP', '', $rawUlp)));
        $ulpId = $ulpLookup[$cleanUlpKey] ?? null;

        // Col 7: PMT-RECL/PMCB
        $deviceType = trim((string)($row[7] ?? ''));

        // Col 8: PENYULANG
        $rawFeeder = trim((string)($row[8] ?? ''));
        $feederKey = strtoupper($rawFeeder);
        $penyulangId = $feederLookup[$feederKey] ?? null;

        // Col 9: RECLOSER
        $deviceName = trim((string)($row[9] ?? ''));

        // Col 10: TGL PMT Trip
        $rawTripDate = $row[10] ?? null;
        $tripDate = !empty($rawTripDate) ? ($this->parseExcelDate($rawTripDate) ?: $eventDate) : $eventDate;

        // Col 11: JAM PMT Lepas
        $jamLepas = $this->parseTime($row[11] ?? null);

        // Col 12: TGL PMT Masuk
        $rawMasukDate = $row[12] ?? null;
        $masukDate = !empty($rawMasukDate) ? ($this->parseExcelDate($rawMasukDate) ?: $eventDate) : $eventDate;

        // Col 13: JAM PMT Masuk
        $jamMasuk = $this->parseTime($row[13] ?? null);

        // Col 14: masuk total
        $masukTotal = trim((string)($row[14] ?? ''));

        // Col 15: LAMA PADAM
        $rawLamaPadam = trim((string)($row[15] ?? ''));

        // Calculate started_at, ended_at, duration
        $startedAt = $jamLepas ? "{$tripDate} {$jamLepas}" : "{$tripDate} 00:00:00";
        $endedAt   = $jamMasuk ? "{$masukDate} {$jamMasuk}" : null;
        $durationMinutes = $this->parseDurationMinutes($rawLamaPadam, $startedAt, $endedAt);

        // Col 16: KATEGORI
        $rawKategori = trim((string)($row[16] ?? ''));
        $category = 'TEMPORARY';
        if (str_contains($rawKategori, '#REF!')) {
            $issues[] = "Nilai Kategori adalah formula rusak (#REF!) - Dibiarkan mentah dan ditandai FLAGGED untuk ditinjau.";
            $category = 'UNKNOWN';
        } elseif (stripos($rawKategori, 'Permanen') !== false) {
            $category = 'PERMANENT';
        } elseif (stripos($rawKategori, 'Temporer') !== false) {
            $category = 'TEMPORARY';
        } else {
            $category = !empty($rawKategori) ? strtoupper($rawKategori) : 'UNKNOWN';
            if ($category === 'UNKNOWN') {
                $issues[] = "Kategori kosong / tidak dikenal ('{$rawKategori}') - Ditandai untuk ditinjau.";
            }
        }

        // Col 17: RELE KERJA
        $releKerja = strtoupper(trim((string)($row[17] ?? '')));

        // Col 18: fasa
        $fasa = strtoupper(trim((string)($row[18] ?? '')));

        // Col 19: cuaca
        $cuaca = strtolower(trim((string)($row[19] ?? '')));

        // Col 20: KETERANGAN
        $keterangan = trim((string)($row[20] ?? ''));

        // Col 21: PENYEBAB SESUAI KODE GANGGUAN
        $penyebab = trim((string)($row[21] ?? ''));

        // Resolve Cause Canonical Taxonomy via CauseCodeResolutionService if available
        $causeResolver = new CauseCodeResolutionService($this->db);
        $causeRes = $causeResolver->resolveCause($penyebab);

        // Extract Section from Keterangan
        $extractedSection = $this->extractSection($keterangan);

        // Compute deterministic hash encompassing all business columns
        $hashSource = implode('|#|', [
            $up3Name,
            $eventDate,
            $giName,
            $rawUlp,
            $deviceType,
            $rawFeeder,
            $deviceName,
            $tripDate,
            $jamLepas,
            $masukDate,
            $jamMasuk,
            $masukTotal,
            $rawLamaPadam,
            $rawKategori,
            $releKerja,
            $fasa,
            $cuaca,
            $keterangan,
            $penyebab,
        ]);
        $recordHash = hash('sha256', $hashSource);

        $now = date('Y-m-d H:i:s');
        $hasIssues = !empty($issues) || ($causeRes['cause_mapping_status'] !== 'RESOLVED');

        return [
            'batch_id'                 => 0,
            'source_system'            => 'EXCEL_WORKBOOK_IMPORT',
            'source_sheet_name'        => 'Sheet1',
            'source_row_number'        => $rowNum,
            'source_record_hash'       => $recordHash,
            'raw_payload_json'         => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'event_date'               => $eventDate,
            'interruption_started_at'  => $startedAt,
            'interruption_ended_at'    => $endedAt,
            'masuk_total_raw'          => $masukTotal,
            'outage_duration_minutes'  => $durationMinutes,
            'lama_padam_raw'           => $rawLamaPadam,
            'substation_name'          => $giName,
            'up3_name'                 => $up3Name,
            'ulp_name'                 => $rawUlp,
            'ulp_id'                   => $ulpId,
            'feeder_name'              => $rawFeeder,
            'penyulang_id'             => $penyulangId,
            'switching_device_type'    => $deviceType ?: 'RECL-PMCB',
            'device_name'              => $deviceName,
            'relay_trip_type'          => $releKerja,
            'faulted_phase'            => $fasa,
            'weather_condition'        => $cuaca,
            'interruption_category'    => $category,
            'kategori_raw'             => $rawKategori,
            'interruption_group'       => 'GGN_PENYULANG',
            'cause_raw'                => $penyebab,
            'cause_canonical_code'     => $causeRes['cause_canonical_code'] ?? 'CAUSE_UNMAPPED_ADVISORY',
            'cause_category'           => $causeRes['cause_category'] ?? 'UNKNOWN_INVESTIGATION',
            'cause_mapping_status'     => $causeRes['cause_mapping_status'] ?? 'UNMAPPED',
            'field_narrative_raw'      => $keterangan,
            'restoration_action_raw'   => '',
            'extracted_zone_section'   => $extractedSection,
            'data_quality_score'       => $hasIssues ? 0.70 : 1.00,
            'ingestion_status'         => $hasIssues ? 'FLAGGED' : 'VALID',
            'created_at'               => $now,
            'updated_at'               => $now,
        ];
    }

    /**
     * Parse date from Excel serial number (e.g. 45658) or text (e.g. '2025-01-01', '01/01/2025')
     */
    public function parseExcelDate($raw): ?string
    {
        if ($raw === null || trim((string)$raw) === '') {
            return null;
        }

        $rawStr = trim((string)$raw);

        // Numeric serial number from Excel
        if (is_numeric($rawStr) && (float)$rawStr > 30000 && (float)$rawStr < 60000) {
            $days = (int)$rawStr;
            // 25569 = difference between 1970-01-01 and 1900-01-01 (Excel leap bug adjustment)
            $unixTime = ($days - 25569) * 86400;
            return gmdate('Y-m-d', $unixTime);
        }

        // Standard YYYY-MM-DD
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $rawStr, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }

        // DD-MM-YYYY or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $rawStr, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        $time = strtotime($rawStr);
        if ($time !== false) {
            return date('Y-m-d', $time);
        }

        return null;
    }

    /**
     * Parse varied time representations e.g. "14.52", "14:52", "14.52.00", or decimal day fraction
     */
    public function parseTime($raw): ?string
    {
        if ($raw === null || trim((string)$raw) === '') {
            return null;
        }

        $rawStr = trim((string)$raw);

        // Excel time fraction (e.g. 0.6194 = 14:52)
        if (is_numeric($rawStr) && (float)$rawStr > 0 && (float)$rawStr < 1) {
            $totalSeconds = round((float)$rawStr * 86400);
            $hours = floor($totalSeconds / 3600);
            $minutes = floor(($totalSeconds % 3600) / 60);
            $seconds = $totalSeconds % 60;
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        // Dot notation "14.52" or "14.52.00"
        if (preg_match('/^(\d{1,2})\.(\d{1,2})(?:\.(\d{1,2}))?$/', $rawStr, $m)) {
            $h = (int)$m[1];
            $i = (int)$m[2];
            $s = isset($m[3]) ? (int)$m[3] : 0;
            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }

        // Colon notation "14:52" or "14:52:00"
        if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $rawStr, $m)) {
            $h = (int)$m[1];
            $i = (int)$m[2];
            $s = isset($m[3]) ? (int)$m[3] : 0;
            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }

        return null;
    }

    /**
     * Parse duration minutes from raw text e.g. "0.04.00" (4 mins), "1.28.00" (88 mins), or timestamps
     */
    public function parseDurationMinutes(?string $rawDuration, ?string $startedAt, ?string $endedAt): ?float
    {
        if (!empty($rawDuration)) {
            $raw = trim($rawDuration);
            // format H.i.s or H:i:s
            if (preg_match('/^(\d{1,2})[\.:](\d{1,2})[\.:](\d{1,2})$/', $raw, $m)) {
                $hours = (int)$m[1];
                $mins  = (int)$m[2];
                $secs  = (int)$m[3];
                return round(($hours * 60) + $mins + ($secs / 60), 2);
            }
            if (preg_match('/^(\d{1,2})[\.:](\d{1,2})$/', $raw, $m)) {
                $hours = (int)$m[1];
                $mins  = (int)$m[2];
                return round(($hours * 60) + $mins, 2);
            }
            if (is_numeric($raw)) {
                return round((float)$raw, 2);
            }
        }

        // Fallback to time delta
        if (!empty($startedAt) && !empty($endedAt)) {
            $t1 = strtotime($startedAt);
            $t2 = strtotime($endedAt);
            if ($t2 > $t1) {
                return round(($t2 - $t1) / 60, 2);
            }
        }

        return null;
    }

    /**
     * Regex extraction of zone/section e.g. "zona 2 section 2"
     */
    public function extractSection(string $narrative): ?string
    {
        if (preg_match('/(zona\s*\d+\s*(?:section|sec|seksi)\s*\d+)/i', $narrative, $m)) {
            return strtoupper(trim($m[1]));
        }
        if (preg_match('/(section|sec|seksi)\s*(\d+)/i', $narrative, $m)) {
            return 'SECTION ' . $m[2];
        }
        if (preg_match('/zona\s*(\d+)/i', $narrative, $m)) {
            return 'ZONA ' . $m[1];
        }
        return null;
    }

    protected function getUlpLookup(): array
    {
        $rows = $this->db->table('ulps')->select('id, nama_ulp')->get()->getResultArray();
        $lookup = [];
        foreach ($rows as $r) {
            $cleanName = strtoupper(trim(str_ireplace('ULP', '', (string)$r['nama_ulp'])));
            $lookup[$cleanName] = (int)$r['id'];
        }
        return $lookup;
    }

    protected function getFeederLookup(): array
    {
        $rows = $this->db->table('penyulang')->select('id, nama_penyulang')->get()->getResultArray();
        $lookup = [];
        foreach ($rows as $r) {
            $lookup[strtoupper(trim((string)$r['nama_penyulang']))] = (int)$r['id'];
        }
        return $lookup;
    }

    protected function getExistingHashes(): array
    {
        $rows = $this->db->table('historical_feeder_interruptions')
            ->select('source_record_hash')
            ->where('source_record_hash IS NOT NULL')
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['source_record_hash']] = true;
        }
        return $map;
    }
}
