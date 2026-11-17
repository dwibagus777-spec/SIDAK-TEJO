<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\GangguanSync;

/**
 * Synchronizes feeder interruption records from the configured Google Sheets
 * source (`DATA_GANGGUAN`) into `historical_feeder_interruptions` with
 * TTL freshness caching, checksum short-circuiting, and ACID replacement of
 * spreadsheet-sourced rows while preserving manual direct entries.
 */
class GoogleSheetsGangguanSyncService
{
    public const SOURCE_SYSTEM_SYNC = 'GOOGLE_SHEETS_SYNC';
    private const PARSER_VERSION    = 'v2-ens-multidot-fix';

    private const MONTH_MAP = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'may' => 5, 'mei' => 5, 'jun' => 6, 'jul' => 7,
        'aug' => 8, 'agu' => 8, 'agt' => 8, 'sep' => 9,
        'oct' => 10, 'okt' => 10, 'nov' => 11, 'dec' => 12, 'des' => 12,
    ];

    protected BaseConnection $db;
    protected GangguanSync $config;
    protected CauseCodeResolutionService $causeResolver;
    protected string $statusFilePath;

    /** @var callable|null */
    protected $httpFetcher;

    public function __construct(
        ?BaseConnection $db = null,
        ?GangguanSync $config = null,
        ?callable $httpFetcher = null,
        ?string $statusFilePath = null
    ) {
        $this->db             = $db ?? \Config\Database::connect();
        $this->config         = $config ?? config(GangguanSync::class);
        $this->causeResolver  = new CauseCodeResolutionService($this->db);
        $this->httpFetcher    = $httpFetcher;
        $this->statusFilePath = $statusFilePath
            ?? (defined('WRITEPATH') ? WRITEPATH . 'cache/gangguan_sheets_sync_status.json' : sys_get_temp_dir() . '/gangguan_sheets_sync_status.json');
    }

    /**
     * Ensure interruption table is synchronized with Google Sheets within configured TTL.
     * On network/parse errors, preserves existing database records and returns error state.
     */
    public function ensureSynced(bool $force = false): array
    {
        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            return $this->buildStatusPayload('UNAVAILABLE', 'Tabel histori gangguan belum tersedia di database.');
        }

        $cachedStatus = $this->readStatusFile();
        $hasGoogleSyncedRows = $this->hasGoogleSyncedRows();

        if (!$force && $hasGoogleSyncedRows && $this->isWithinTtl($cachedStatus)) {
            $cachedStatus['cache_hit'] = true;
            return $cachedStatus;
        }

        return $this->syncFromGoogleSheets();
    }

    /**
     * Perform a live fetch + parse + transactional DB sync from Google Sheets.
     */
    public function syncFromGoogleSheets(): array
    {
        $prevStatus = $this->readStatusFile();
        $checkedAt  = date('Y-m-d H:i:s');

        try {
            $csvRaw = $this->fetchCsvContent();
            $parsed = $this->parseCsvRecords($csvRaw);

            $records         = $parsed['records'];
            $datasetChecksum = $parsed['dataset_checksum'];
            $years           = $parsed['years'];
            $skippedRows     = $parsed['skipped_rows'];

            if (empty($records)) {
                throw new \RuntimeException('Spreadsheet tidak memiliki baris data gangguan yang valid.');
            }

            $hasGoogleSyncedRows = $this->hasGoogleSyncedRows();
            $prevChecksum = (string)($prevStatus['dataset_checksum'] ?? '');

            if ($hasGoogleSyncedRows && $prevChecksum !== '' && hash_equals($prevChecksum, $datasetChecksum)) {
                $status = $this->buildStatusPayload(
                    'SYNCED',
                    null,
                    [
                        'last_checked_at'  => $checkedAt,
                        'last_synced_at'   => $prevStatus['last_synced_at'] ?? $checkedAt,
                        'row_count'        => count($records),
                        'years'            => $years,
                        'skipped_rows'     => $skippedRows,
                        'dataset_checksum' => $datasetChecksum,
                        'changed'          => false,
                        'cache_hit'        => false,
                    ]
                );
                $this->writeStatusFile($status);
                return $status;
            }

            $insertedCount = $this->persistRecordsTransactionally($records, $datasetChecksum, count($records) + $skippedRows);

            $status = $this->buildStatusPayload(
                'SYNCED',
                null,
                [
                    'last_checked_at'  => $checkedAt,
                    'last_synced_at'   => $checkedAt,
                    'row_count'        => $insertedCount,
                    'years'            => $years,
                    'skipped_rows'     => $skippedRows,
                    'dataset_checksum' => $datasetChecksum,
                    'changed'          => true,
                    'cache_hit'        => false,
                ]
            );
            $this->writeStatusFile($status);
            return $status;
        } catch (\Throwable $e) {
            $safeError = $this->sanitizeErrorMessage($e->getMessage());
            log_message('warning', '[GoogleSheetsGangguanSyncService] Sync failed: ' . $safeError);

            $dbCount = $this->countStoredRows();
            $status = $this->buildStatusPayload(
                'ERROR',
                $safeError,
                [
                    'last_checked_at'  => $checkedAt,
                    'last_synced_at'   => $prevStatus['last_synced_at'] ?? $this->getLastBatchTimestamp(),
                    'row_count'        => $prevStatus['row_count'] ?? $dbCount,
                    'years'            => $prevStatus['years'] ?? [],
                    'dataset_checksum' => $prevStatus['dataset_checksum'] ?? '',
                    'changed'          => false,
                    'cache_hit'        => false,
                    'using_cached_db'  => $dbCount > 0,
                ]
            );
            $this->writeStatusFile($status);
            return $status;
        }
    }

    /**
     * Return current sync status without triggering a network request.
     */
    public function getSyncStatus(): array
    {
        $cached = $this->readStatusFile();
        if (!empty($cached['status'])) {
            return $cached;
        }

        $dbCount = $this->countStoredRows();
        return $this->buildStatusPayload(
            $dbCount > 0 ? 'STANDBY' : 'EMPTY',
            null,
            [
                'last_synced_at' => $this->getLastBatchTimestamp(),
                'row_count'      => $dbCount,
            ]
        );
    }

    /**
     * Parse raw CSV string from Google Sheets into normalized DB-ready records.
     * Publicly testable without HTTP calls.
     */
    public function parseCsvRecords(string $csvRaw): array
    {
        $cleanCsv = preg_replace('/^\xEF\xBB\xBF/', '', $csvRaw) ?? $csvRaw;
        if (trim($cleanCsv) === '') {
            throw new \RuntimeException('Respons CSV spreadsheet kosong.');
        }

        $trimmedHead = ltrim($cleanCsv);
        if (preg_match('/^<(?:!doctype|html)\b/i', $trimmedHead)) {
            throw new \RuntimeException('Akses spreadsheet ditolak atau memerlukan autentikasi.');
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Gagal membuka buffer pembaca CSV.');
        }

        fwrite($stream, $cleanCsv);
        rewind($stream);

        $header = fgetcsv($stream);
        if (!is_array($header) || count($header) < 10) {
            fclose($stream);
            throw new \RuntimeException('Header kolom spreadsheet tidak valid atau tidak terbaca.');
        }

        $colMap = $this->buildColumnIndexMap($header);
        foreach (['tanggal', 'ulp', 'penyulang', 'kategori'] as $requiredKey) {
            if (!isset($colMap[$requiredKey])) {
                fclose($stream);
                throw new \RuntimeException("Kolom wajib '{$requiredKey}' tidak ditemukan pada header spreadsheet.");
            }
        }

        $ulpLookup    = $this->getUlpLookup();
        $feederLookup = $this->getFeederLookup();
        $causeCache   = [];
        $records      = [];
        $yearsMap     = [];
        $skippedRows  = 0;
        $rowNumber    = 1;
        $now          = date('Y-m-d H:i:s');

        while (($row = fgetcsv($stream)) !== false) {
            $rowNumber++;
            if (!$this->rowHasContent($row)) {
                continue;
            }

            $normalized = $this->normalizeCsvRow($row, $rowNumber, $colMap, $ulpLookup, $feederLookup, $causeCache, $now);
            if ($normalized === null) {
                $skippedRows++;
                continue;
            }

            $yr = (int)substr($normalized['event_date'], 0, 4);
            if ($yr > 0) {
                $yearsMap[$yr] = true;
            }
            $records[] = $normalized;
        }

        fclose($stream);

        $years = array_keys($yearsMap);
        rsort($years);

        $hashPayload = array_map(static fn(array $r): string => $r['source_record_hash'], $records);
        $datasetChecksum = hash('sha256', self::PARSER_VERSION . "\n" . implode("\n", $hashPayload));

        return [
            'records'          => $records,
            'dataset_checksum' => $datasetChecksum,
            'years'            => $years,
            'skipped_rows'     => $skippedRows,
        ];
    }

    protected function fetchCsvContent(): string
    {
        $url = $this->config->getExportCsvUrl();
        if ($this->httpFetcher !== null) {
            $body = call_user_func($this->httpFetcher, $url);
            if (!is_string($body) || trim($body) === '') {
                throw new \RuntimeException('Data CSV dari sumber spreadsheet kosong.');
            }
            return $body;
        }

        $headers = [
            'Accept: text/csv, text/plain;q=0.9',
            'Cache-Control: no-cache, no-store, must-revalidate',
            'Pragma: no-cache',
        ];
        $token = $this->config->getAuthBearerToken();
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => $this->config->httpTimeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'SIDAK-TEJO-Sync/1.0',
            ]);
            $body     = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                throw new \RuntimeException('Koneksi ke Google Sheets gagal: ' . ($curlErr ?: 'network error'));
            }
            if ($httpCode === 401 || $httpCode === 403) {
                throw new \RuntimeException('Akses ke Google Sheets ditolak (HTTP ' . $httpCode . '). Pastikan izin baca spreadsheet tersedia.');
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new \RuntimeException('Google Sheets mengembalikan status HTTP ' . $httpCode . '.');
            }

            return (string)$body;
        }

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => $this->config->httpTimeoutSeconds,
                'header'        => implode("\r\n", $headers),
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new \RuntimeException('Gagal mengunduh CSV dari Google Sheets.');
        }

        return (string)$body;
    }

    protected function buildColumnIndexMap(array $header): array
    {
        $map = [];
        foreach ($header as $idx => $rawName) {
            $norm = strtolower(preg_replace('/\s+/', ' ', trim((string)$rawName)));
            if ($norm === '' && $idx === 0) {
                $map['up3'] = 0;
                continue;
            }
            $map[$norm] = $idx;
        }

        $aliases = [
            'up3'                         => ['up3', ' '],
            'tanggal'                     => ['tanggal', 'tgl'],
            'thn'                         => ['thn', 'tahun'],
            'gardu induk'                 => ['gardu induk', 'gi'],
            'ulp'                         => ['ulp'],
            'pmt-recl/pmcb'               => ['pmt-recl/pmcb', 'pmt-recl'],
            'penyulang'                   => ['penyulang', 'nama penyulang'],
            'nama switching'              => ['nama switching'],
            'recloser'                    => ['recloser'],
            'tgl pmt trip'                => ['tgl pmt trip'],
            'jam pmt lepas'               => ['jam pmt lepas'],
            'tgl pmt masuk'               => ['tgl pmt masuk'],
            'jam pmt masuk'               => ['jam pmt masuk'],
            'masuk total'                 => ['masuk total'],
            'lama padam'                  => ['lama padam'],
            'amper'                       => ['( amper )', 'amper', 'ampere'],
            'kwh'                         => ['( kwh )', 'kwh'],
            'kategori'                    => ['kategori'],
            'rele kerja'                  => ['rele kerja', 'rele'],
            'fasa'                        => ['fasa'],
            'cuaca'                       => ['cuaca'],
            'kelompok ggn'                => ['kelompok ggn'],
            'keterangan'                  => ['keterangan'],
            'tindak lanjut'               => ['tindak lanjut'],
            'penyebab'                    => ['penyebab'],
            'penyebab sesuai kode gangguan' => ['penyebab sesuai kode gangguan'],
        ];

        $resolved = [];
        foreach ($aliases as $canonical => $candidates) {
            foreach ($candidates as $cand) {
                if (isset($map[$cand])) {
                    $resolved[$canonical] = $map[$cand];
                    break;
                }
            }
        }

        return $resolved;
    }

    protected function normalizeCsvRow(
        array $row,
        int $rowNumber,
        array $colMap,
        array $ulpLookup,
        array $feederLookup,
        array &$causeCache,
        string $now
    ): ?array {
        $get = static function (string $key, ?int $fallbackIdx = null) use ($row, $colMap): string {
            $idx = $colMap[$key] ?? $fallbackIdx;
            if ($idx === null || !array_key_exists($idx, $row)) {
                return '';
            }
            return trim((string)$row[$idx]);
        };

        $rawDate   = $get('tanggal', 1);
        $eventDate = $this->parseDate($rawDate);
        if ($eventDate === null) {
            return null;
        }

        $up3Name = $get('up3', 0) ?: 'SDA';
        $giName  = strtoupper($get('gardu induk', 5)) ?: 'SIDOARJO';

        $rawUlp      = strtoupper($get('ulp', 6));
        $cleanUlpKey = preg_replace('/\s+/', ' ', trim(str_ireplace('ULP', '', $rawUlp))) ?? '';
        $ulpId       = $ulpLookup[$cleanUlpKey] ?? null;

        $deviceType = strtoupper(preg_replace('/\s+/', ' ', $get('pmt-recl/pmcb', 7)) ?? '') ?: 'RECL-PMCB';
        $rawFeeder  = strtoupper(preg_replace('/\s+/', ' ', $get('penyulang', 8)) ?? '');
        if ($rawFeeder === '') {
            return null;
        }
        $penyulangId = $this->resolveFeederId($rawFeeder, $feederLookup);

        $namaSwitching = preg_replace('/\s+/', ' ', $get('nama switching', 9)) ?? '';
        $recloserName  = preg_replace('/\s+/', ' ', $get('recloser', 10)) ?? '';
        $deviceName    = $recloserName !== '' ? $recloserName : $namaSwitching;

        $tripDate  = $this->parseDate($get('tgl pmt trip', 11)) ?: $eventDate;
        $jamLepas  = $this->parseTime($get('jam pmt lepas', 12));
        $masukDate = $this->parseDate($get('tgl pmt masuk', 13)) ?: $tripDate;
        $jamMasuk  = $this->parseTime($get('jam pmt masuk', 14));

        $startedAt  = $jamLepas ? "{$tripDate} {$jamLepas}" : "{$eventDate} 00:00:00";
        $endedAt    = $jamMasuk ? "{$masukDate} {$jamMasuk}" : null;
        $masukTotal = $get('masuk total', 15);
        $lamaPadam  = $get('lama padam', 16);
        $duration   = $this->parseDurationMinutes($lamaPadam, $startedAt, $endedAt);

        $amperes = $this->parseDecimal($get('amper', 17));
        $ensKwh  = $this->parseDecimal($get('kwh', 18), 3);

        $rawKategori = $get('kategori', 19);
        $category    = $this->normalizeCategory($rawKategori);

        $releKerja = strtoupper(preg_replace('/\s+/', ' ', $get('rele kerja', 20)) ?? '');
        $fasa      = strtoupper(preg_replace('/\s+/', ' ', $get('fasa', 21)) ?? '');
        $cuaca     = strtolower(preg_replace('/\s+/', ' ', $get('cuaca', 22)) ?? '');
        $kelompok  = preg_replace('/\s+/', ' ', $get('kelompok ggn', 24)) ?: 'GGN_PENYULANG';
        $narrative = $get('keterangan', 26);
        $action    = $get('tindak lanjut', 27);

        $causeCodeRaw = strtoupper(preg_replace('/\s+/', ' ', $get('penyebab sesuai kode gangguan', 30)) ?? '');
        if ($causeCodeRaw === '') {
            $causeCodeRaw = strtoupper(preg_replace('/\s+/', ' ', $get('penyebab', 28)) ?? '');
        }
        if ($causeCodeRaw === '') {
            $causeCodeRaw = 'TIDAK TERCATAT';
        }

        if (!isset($causeCache[$causeCodeRaw])) {
            $causeCache[$causeCodeRaw] = $this->causeResolver->resolveCause($causeCodeRaw);
        }
        $causeRes = $causeCache[$causeCodeRaw];

        // Include every trimmed cell in the row hash so edits to any column in Google Sheets trigger sync
        $rawRowCells = array_map(static fn($v): string => trim((string)$v), $row);
        $recordHash  = hash('sha256', $rowNumber . '|#|' . implode('|#|', $rawRowCells));

        $hasIssues = ($category === 'UNKNOWN') || ($causeRes['cause_mapping_status'] === 'UNMAPPED');

        return [
            'batch_id'                 => 0,
            'source_system'            => self::SOURCE_SYSTEM_SYNC,
            'source_sheet_name'        => $this->config->sheetName,
            'source_row_number'        => $rowNumber,
            'source_record_hash'       => $recordHash,
            'raw_payload_json'         => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'event_date'               => $eventDate,
            'interruption_started_at'  => $startedAt,
            'interruption_ended_at'    => $endedAt,
            'masuk_total_raw'          => $masukTotal,
            'outage_duration_minutes'  => $duration,
            'lama_padam_raw'           => $lamaPadam,
            'substation_name'          => $giName,
            'up3_name'                 => $up3Name,
            'ulp_name'                 => $rawUlp ?: 'SIDOARJO',
            'ulp_id'                   => $ulpId,
            'feeder_name'              => $rawFeeder,
            'penyulang_id'             => $penyulangId,
            'switching_device_type'    => $deviceType,
            'device_name'              => $deviceName,
            'relay_trip_type'          => $releKerja,
            'faulted_phase'            => $fasa,
            'weather_condition'        => $cuaca,
            'fault_current_amperes'    => $amperes,
            'energy_not_supplied_kwh'  => $ensKwh,
            'interruption_category'    => $category,
            'kategori_raw'             => $rawKategori,
            'interruption_group'       => $kelompok,
            'cause_raw'                => $causeCodeRaw,
            'cause_canonical_code'     => $causeRes['cause_canonical_code'],
            'cause_category'           => $causeRes['cause_category'],
            'cause_mapping_status'     => $causeRes['cause_mapping_status'],
            'field_narrative_raw'      => $narrative,
            'restoration_action_raw'   => $action,
            'extracted_zone_section'   => $this->extractSection($narrative),
            'extracted_distance_kms'   => $this->extractDistanceKms($narrative),
            'asset_reference_raw'      => $this->extractAssetRef($narrative),
            'data_quality_score'       => $hasIssues ? 0.70 : 1.00,
            'ingestion_status'         => $hasIssues ? 'FLAGGED' : 'VALID',
            'created_at'               => $now,
            'updated_at'               => $now,
        ];
    }

    protected function persistRecordsTransactionally(array $records, string $datasetChecksum, int $totalRead): int
    {
        $tableCols = array_flip($this->db->getFieldNames('historical_feeder_interruptions'));
        $batchCode = 'BATCH-GSHEET-' . date('Ymd-His');
        $flagged   = count(array_filter($records, static fn(array $r): bool => ($r['ingestion_status'] ?? '') === 'FLAGGED'));

        $this->db->transBegin();

        try {
            $batchId = 0;
            if ($this->db->tableExists('interruption_import_batches')) {
                $batchCols = array_flip($this->db->getFieldNames('interruption_import_batches'));
                $batchRow  = [
                    'batch_code'           => $batchCode,
                    'source_system'        => self::SOURCE_SYSTEM_SYNC,
                    'source_filename'      => 'GOOGLE_SHEETS_' . $this->config->sheetName,
                    'source_sheet_name'    => $this->config->sheetName,
                    'total_rows_read'      => $totalRead,
                    'total_rows_imported'  => count($records),
                    'total_rows_duplicate' => 0,
                    'total_rows_flagged'   => $flagged,
                    'batch_checksum'       => $datasetChecksum,
                    'status'               => 'COMPLETED',
                    'imported_by'          => 'GOOGLE_SHEETS_SYNC_SERVICE',
                    'notes'                => 'Sinkronisasi otomatis Google Sheets DATA_GANGGUAN (' . count($records) . ' baris).',
                    'created_at'           => date('Y-m-d H:i:s'),
                    'updated_at'           => date('Y-m-d H:i:s'),
                ];
                $this->db->table('interruption_import_batches')->insert(array_intersect_key($batchRow, $batchCols));
                $batchId = (int)$this->db->insertID();
            }

            // Replace only rows owned by Google Sheets Sync; never delete or overwrite manual/XLSX/legacy rows.
            if (!isset($tableCols['source_system'])) {
                throw new \RuntimeException('Skema tabel histori gangguan belum memiliki kolom source_system.');
            }

            $this->db->table('historical_feeder_interruptions')
                ->where('source_system', self::SOURCE_SYSTEM_SYNC)
                ->delete();

            $chunks = array_chunk($records, 200);
            foreach ($chunks as $chunk) {
                $filtered = [];
                foreach ($chunk as $rec) {
                    $rec['batch_id'] = $batchId;
                    $filtered[] = array_intersect_key($rec, $tableCols);
                }
                $this->db->table('historical_feeder_interruptions')->insertBatch($filtered);
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Transaksi database gagal diselesaikan.');
            }

            $this->db->transCommit();
            return count($records);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    protected function parseDate(string $rawDate): ?string
    {
        $raw = trim($rawDate);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $raw, $m)) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            $d = (int)$m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        if (preg_match('/^(\d{1,2})[-\s]([a-zA-Z]{3,})[-\s](\d{2,4})$/', $raw, $m)) {
            $d = (int)$m[1];
            $monKey = strtolower(substr($m[2], 0, 3));
            $mo = self::MONTH_MAP[$monKey] ?? 0;
            $y = strlen($m[3]) === 2 ? (2000 + (int)$m[3]) : (int)$m[3];
            return ($mo > 0 && checkdate($mo, $d, $y)) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $raw, $m)) {
            $d = (int)$m[1];
            $mo = (int)$m[2];
            $y = (int)$m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    protected function parseTime(string $rawTime): ?string
    {
        $clean = trim(str_replace('.', ':', $rawTime));
        if ($clean === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $clean, $m)) {
            $h = (int)$m[1];
            $i = (int)$m[2];
            $s = isset($m[3]) ? (int)$m[3] : 0;
            if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59 && $s >= 0 && $s <= 59) {
                return sprintf('%02d:%02d:%02d', $h, $i, $s);
            }
        }

        return null;
    }

    protected function parseDurationMinutes(string $rawDuration, ?string $start, ?string $end): ?float
    {
        if ($rawDuration !== '') {
            $clean = trim(str_replace('.', ':', $rawDuration));
            if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $clean, $m)) {
                $total = ((int)$m[1] * 60.0) + (int)$m[2] + ((isset($m[3]) ? (int)$m[3] : 0) / 60.0);
                return round($total, 2);
            }
            if (is_numeric($rawDuration)) {
                return round((float)$rawDuration, 2);
            }
        }

        if ($start && $end) {
            $diff = strtotime($end) - strtotime($start);
            if ($diff > 0) {
                return round($diff / 60.0, 2);
            }
        }

        return null;
    }

    protected function parseDecimal(string $raw, int $precision = 2): ?float
    {
        $str = trim($raw, " \t\n\r\0\x0B\"'");
        if ($str === '' || $str === '-') {
            return null;
        }

        if (str_contains($str, ',') && str_contains($str, '.')) {
            // Indonesian thousand dot + decimal comma: e.g. 1.234,56
            if (strrpos($str, ',') > strrpos($str, '.')) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        } elseif (substr_count($str, '.') >= 2) {
            // Google Sheets locale quirk (e.g. "7.914.779" in column ( Kwh )): thousands '.' + final '.' decimal
            $lastDot = strrpos($str, '.');
            $str = str_replace('.', '', substr($str, 0, $lastDot)) . '.' . substr($str, $lastDot + 1);
        }

        return is_numeric($str) ? round((float)$str, $precision) : null;
    }

    protected function normalizeCategory(string $raw): string
    {
        $upper = strtoupper(trim($raw));
        if ($upper === '' || str_contains($upper, '#REF!') || str_contains($upper, '#VALUE!')) {
            return 'UNKNOWN';
        }
        if (str_contains($upper, 'PERMANEN')) {
            return 'PERMANENT';
        }
        if (str_contains($upper, 'TEMPORER') || str_contains($upper, 'TEMPORARY')) {
            return 'TEMPORARY';
        }
        return 'UNKNOWN';
    }

    protected function extractSection(string $narrative): ?string
    {
        if (preg_match('/(zona\s*\d+\s*(?:section|sec|seksi|sect)?\s*\d+)/i', $narrative, $m)) {
            return strtoupper(trim($m[1]));
        }
        return null;
    }

    protected function extractDistanceKms(string $narrative): ?float
    {
        if (preg_match('/(?:jarak|panjang)?\s*[:=]?\s*(\d+(?:[,\.]\d+)?)\s*kms?/i', $narrative, $m)) {
            return (float)str_replace(',', '.', $m[1]);
        }
        return null;
    }

    protected function extractAssetRef(string $narrative): ?string
    {
        if (preg_match('/\b(PA[\s\.\-]?\d+|PB[\s\.\-]?\d+|PC[\s\.\-]?\d+|GTT[\s\.\-]?\w+|LBSM?[\s\.\-]?\w+|TM[\s\.\-]?\d+)\b/i', $narrative, $m)) {
            return strtoupper(trim($m[1]));
        }
        return null;
    }

    protected function getUlpLookup(): array
    {
        if (!$this->db->tableExists('ulps')) {
            return [];
        }
        $rows = $this->db->table('ulps')->select('id, nama_ulp')->get()->getResultArray();
        $lookup = [];
        foreach ($rows as $r) {
            $clean = strtoupper(trim(str_ireplace('ULP', '', (string)$r['nama_ulp'])));
            if ($clean !== '') {
                $lookup[$clean] = (int)$r['id'];
            }
        }
        return $lookup;
    }

    protected function getFeederLookup(): array
    {
        if (!$this->db->tableExists('penyulang')) {
            return [];
        }
        $rows = $this->db->table('penyulang')->select('id, nama_penyulang')->get()->getResultArray();
        $lookup = [];
        foreach ($rows as $r) {
            $key = strtoupper(trim((string)$r['nama_penyulang']));
            if ($key !== '') {
                $lookup[$key] = (int)$r['id'];
            }
        }
        return $lookup;
    }

    protected function resolveFeederId(string $feederUpper, array $feederLookup): ?int
    {
        if (isset($feederLookup[$feederUpper])) {
            return $feederLookup[$feederUpper];
        }
        $compact = preg_replace('/[^A-Z0-9]/', '', $feederUpper);
        foreach ($feederLookup as $name => $id) {
            if (preg_replace('/[^A-Z0-9]/', '', $name) === $compact && $compact !== '') {
                return $id;
            }
        }
        return null;
    }

    protected function rowHasContent(array $row): bool
    {
        foreach ($row as $v) {
            if ($v !== null && trim((string)$v) !== '') {
                return true;
            }
        }
        return false;
    }

    protected function hasGoogleSyncedRows(): bool
    {
        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            return false;
        }
        return $this->db->table('historical_feeder_interruptions')
            ->where('source_system', self::SOURCE_SYSTEM_SYNC)
            ->countAllResults() > 0;
    }

    protected function countStoredRows(): int
    {
        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            return 0;
        }
        return (int)$this->db->table('historical_feeder_interruptions')->countAllResults();
    }

    protected function getLastBatchTimestamp(): ?string
    {
        if (!$this->db->tableExists('interruption_import_batches')) {
            return null;
        }
        $row = $this->db->table('interruption_import_batches')
            ->select('created_at')
            ->where('source_system', self::SOURCE_SYSTEM_SYNC)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        return $row['created_at'] ?? null;
    }

    protected function isWithinTtl(array $status): bool
    {
        $ttl = $this->config->autoSyncTtlSeconds;
        if ($ttl <= 0 || empty($status['last_checked_at']) || ($status['status'] ?? '') !== 'SYNCED') {
            return false;
        }
        $ts = strtotime((string)$status['last_checked_at']);
        return $ts !== false && (time() - $ts) < $ttl;
    }

    protected function buildStatusPayload(string $status, ?string $error, array $extra = []): array
    {
        return array_merge([
            'status'           => $status,
            'source_label'     => $this->config->sourceLabel,
            'sheet_name'       => $this->config->sheetName,
            'last_checked_at'  => null,
            'last_synced_at'   => null,
            'row_count'        => 0,
            'years'            => [],
            'skipped_rows'     => 0,
            'dataset_checksum' => '',
            'changed'          => false,
            'cache_hit'        => false,
            'last_error'       => $error,
        ], $extra);
    }

    protected function sanitizeErrorMessage(string $message): string
    {
        // Strip any accidental URL or query token before storing/rendering
        $clean = preg_replace('/https?:\/\/\S+/i', '[redacted-source]', $message) ?? $message;
        return mb_substr(trim($clean), 0, 240);
    }

    protected function readStatusFile(): array
    {
        if (!is_file($this->statusFilePath)) {
            return [];
        }
        $raw = @file_get_contents($this->statusFilePath);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function writeStatusFile(array $status): void
    {
        $dir = dirname($this->statusFilePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($this->statusFilePath, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
}
