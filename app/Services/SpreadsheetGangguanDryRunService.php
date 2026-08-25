<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Spreadsheet Gangguan Dry-Run Service (CR-01 Phase 1)
 *
 * Responsibilities:
 * - 100% In-Memory Parsing & Staging Simulation (ZERO Database Writes).
 * - Multi-Format Reading (CSV, XLS, XLSX).
 * - Header Detection & Alias Dictionary Column Mapping.
 * - Unknown Column Isolation & Manual Mapping Requirement Triggering.
 * - Sealed M-04 Deterministic Normalization Reuse.
 * - Tiered Duplicate Candidate Detection (Exact Hash, Composite Business Key, Ambiguous).
 * - Comprehensive Validation & Preview Generation.
 */
class SpreadsheetGangguanDryRunService
{
    protected HistoricalInterruptionNormalizerService $normalizer;

    /**
     * Comprehensive Column Alias Dictionary
     */
    protected array $aliasDictionary = [
        'event_date' => [
            'tanggal', 'tgl', 'date', 'tanggal gangguan', 'tgl padam', 'tgl trip', 'waktu padam'
        ],
        'substation_name' => [
            'gardu induk', 'gi', 'gardu_induk', 'nama gi', 'substation'
        ],
        'ulp_name' => [
            'ulp', 'unit', 'unit layanan', 'rayon', 'unit_layanan', 'nama ulp'
        ],
        'switching_device_type' => [
            'pmt-recl/pmcb', 'pmt-recl', 'pmt', 'tipe pmt', 'device_type', 'tipe alat'
        ],
        'feeder_name' => [
            'penyulang', 'nama penyulang', 'feeder', 'feeder_name', 'penyulang padam', 'nama fdr'
        ],
        'device_name' => [
            'recloser', 'nama recloser', 'device', 'nama alat', 'peralatan'
        ],
        'interruption_started_at' => [
            'jam pmt lepas', 'jam trip', 'jam padam', 'waktu padam', 'start_time', 'jam buka'
        ],
        'interruption_ended_at' => [
            'jam pmt masuk', 'jam nyala', 'jam kembali', 'end_time', 'jam tutup', 'waktu normal'
        ],
        'outage_duration_minutes' => [
            'lama padam', 'durasi', 'durasi padam', 'duration', 'lama padam (menit)'
        ],
        'fault_current_amperes' => [
            '( amper )', 'amper', 'ampere', 'arus gangguan', 'if_ampere', 'arus (a)', 'arus trip'
        ],
        'energy_not_supplied_kwh' => [
            '( kwh )', 'kwh', 'ens', 'kwh hilang', 'energy not supplied', 'ens (kwh)'
        ],
        'interruption_category' => [
            'kategori', 'jenis gangguan', 'jenis ggn', 'kategori padam', 'sifat gangguan'
        ],
        'relay_trip_type' => [
            'rele kerja', 'rele', 'relay', 'jenis rele', 'indikasi rele'
        ],
        'faulted_phase' => [
            'fasa', 'fasa gangguan', 'phase', 'fasa trip'
        ],
        'weather_condition' => [
            'cuaca', 'kondisi cuaca', 'weather'
        ],
        'interruption_group' => [
            'kelompok ggn', 'kelompok gangguan', 'klasifikasi gangguan', 'group'
        ],
        'field_narrative_raw' => [
            'keterangan', 'indikasi', 'uraian', 'kronologi', 'keterangan gangguan', 'narasi'
        ],
        'restoration_action_raw' => [
            'tindak lanjut', 'penanganan', 'action', 'upaya perbaikan', 'tindakan'
        ],
        'cause_raw' => [
            'penyebab sesuai kode gangguan', 'penyebab', 'kode gangguan', 'cause', 'sebab gangguan'
        ]
    ];

    /**
     * Map Canonical Keys to M-04 Expected Primary Keys
     */
    protected array $canonicalToM04Keys = [
        'event_date'              => 'tanggal',
        'substation_name'         => 'GARDU INDUK',
        'ulp_name'                => 'ULP',
        'switching_device_type'   => 'PMT-RECL/PMCB',
        'feeder_name'             => 'PENYULANG',
        'device_name'             => 'RECLOSER',
        'interruption_started_at' => 'JAM PMT Lepas',
        'interruption_ended_at'   => 'JAM PMT Masuk',
        'outage_duration_minutes' => 'LAMA PADAM',
        'fault_current_amperes'   => '( AMPER )',
        'energy_not_supplied_kwh' => '( Kwh )',
        'interruption_category'   => 'KATEGORI',
        'relay_trip_type'         => 'RELE KERJA',
        'faulted_phase'           => 'fasa',
        'weather_condition'       => 'cuaca',
        'interruption_group'      => 'KELOMPOK GGN',
        'field_narrative_raw'     => 'KETERANGAN',
        'restoration_action_raw'  => 'TINDAK LANJUT',
        'cause_raw'               => 'PENYEBAB SESUAI KODE GANGGUAN',
    ];

    /**
     * Required Canonical Columns for Valid Integration
     */
    protected array $requiredColumns = [
        'event_date',
        'feeder_name',
        'interruption_category',
        'field_narrative_raw'
    ];

    public function __construct(?HistoricalInterruptionNormalizerService $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new HistoricalInterruptionNormalizerService();
    }

    /**
     * Execute Dry-Run simulation on a spreadsheet file.
     *
     * @param string $filePath Absolute path to spreadsheet file
     * @param string|null $sheetName Optional worksheet name
     * @param int $previewLimit Maximum normalized rows to include in preview
     * @return array Structured dry-run report
     */
    public function executeDryRun(string $filePath, ?string $sheetName = null, int $previewLimit = 10): array
    {
        $startTime = microtime(true);

        // 1. File & Format Validation
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return $this->buildErrorResult("File tidak ditemukan atau tidak dapat dibaca: {$filePath}");
        }

        $filename = basename($filePath);
        $fileSize = filesize($filePath);
        $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx', 'xls', 'txt'], true)) {
            return $this->buildErrorResult("Format file '.{$ext}' tidak didukung. Harap gunakan CSV, XLS, atau XLSX.");
        }

        // 2. Read Raw Rows & Header
        $rawResult = $this->readRawRows($filePath, $ext, $sheetName);
        if (!$rawResult['success']) {
            return $this->buildErrorResult($rawResult['error']);
        }

        $rawHeaders = $rawResult['headers'];
        $rawRows    = $rawResult['rows'];
        $actualSheetName = $rawResult['sheet_name'];

        // 3. Column Mapping Engine
        $mapping = $this->detectColumnMapping($rawHeaders);

        // 4. Process Rows In-Memory with M-04 Normalization & Duplicate Detection
        $totalRows       = count($rawRows);
        $validRows       = 0;
        $invalidRows     = 0;
        $warningRows     = 0;
        $validationErrors = [];
        $validationWarnings = [];

        $exactHashes          = [];
        $compositeKeys        = [];
        $duplicateExact       = [];
        $duplicateComposite   = [];
        $duplicateAmbiguous   = [];

        $previewRecords = [];

        foreach ($rawRows as $rowIndex => $rawRow) {
            $rowNum = $rowIndex + 2; // +1 for 1-based index, +1 for header row

            // Skip entirely blank rows
            if (empty(array_filter($rawRow, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                continue;
            }

            // Remap row into associative array based on detected mapping
            $mappedRow = $this->remapRow($rawRow, $mapping['column_indexes'], $rawHeaders);

            // M-04 Deterministic Normalization
            $normalized = $this->normalizer->normalizeRow($mappedRow);

            // Row-level Validation
            $rowErrors = $this->validateNormalizedRow($normalized, $mappedRow, $rowNum);
            $rowWarnings = $this->checkRowWarnings($normalized, $mappedRow, $rowNum);

            if (!empty($rowErrors)) {
                $invalidRows++;
                $validationErrors = array_merge($validationErrors, $rowErrors);
            } else {
                $validRows++;
                if (!empty($rowWarnings)) {
                    $warningRows++;
                    $validationWarnings = array_merge($validationWarnings, $rowWarnings);
                }
            }

            // Duplicate Detection Analysis
            $rawJson = json_encode($rawRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $recordHash = hash('sha256', $rawJson);

            $fName = $normalized['feeder_name'] ?? '';
            $eDate = $normalized['event_date'] ?? '';
            $sTime = $normalized['interruption_started_at'] ?? '';
            $rType = $normalized['relay_trip_type'] ?? '';

            // Tier 1: Exact Payload Hash
            if (isset($exactHashes[$recordHash])) {
                $duplicateExact[] = [
                    'row_number'        => $rowNum,
                    'original_row'      => $exactHashes[$recordHash],
                    'hash'              => $recordHash,
                    'duplicate_tier'    => 'TIER_1_EXACT_HASH',
                    'collision_risk'    => 'LOW',
                    'recommendation'    => 'AUTO_SKIP_IDEMPOTENT'
                ];
            } else {
                $exactHashes[$recordHash] = $rowNum;
            }

            // Tier 2: Composite Business Key (Only if not already an exact duplicate)
            if (!isset($exactHashes[$recordHash]) || $exactHashes[$recordHash] === $rowNum) {
                if (!empty($fName) && $fName !== 'UNKNOWN_FEEDER' && !empty($eDate) && !empty($sTime)) {
                    $compositeKey = "{$eDate}|{$fName}|{$sTime}|{$rType}";
                    if (isset($compositeKeys[$compositeKey])) {
                        $duplicateComposite[] = [
                            'row_number'        => $rowNum,
                            'original_row'      => $compositeKeys[$compositeKey],
                            'composite_key'     => $compositeKey,
                            'duplicate_tier'    => 'TIER_2_COMPOSITE_BUSINESS_KEY',
                            'collision_risk'    => 'CONTEXT_DEPENDENT',
                            'recommendation'    => 'MANUAL_REVIEW_REQUIRED'
                        ];
                    } else {
                        $compositeKeys[$compositeKey] = $rowNum;
                    }
                } else {
                    // Ambiguous record (missing key components)
                    $duplicateAmbiguous[] = [
                        'row_number'     => $rowNum,
                        'reason'         => 'Missing critical composite key components (Feeder/Date/Time)',
                        'collision_risk' => 'CONTEXT_DEPENDENT',
                        'recommendation' => 'MANUAL_REVIEW_REQUIRED'
                    ];
                }
            }

            // Collect preview samples (or all rows if previewLimit <= 0)
            if ($previewLimit <= 0 || count($previewRecords) < $previewLimit) {
                $previewRecords[] = array_merge($normalized, [
                    '_source_row_number' => $rowNum,
                    '_validation_status' => empty($rowErrors) ? (empty($rowWarnings) ? 'VALID' : 'WARNING') : 'INVALID',
                    '_raw_hash'          => $recordHash,
                ]);
            }
        }

        $totalDuplicateCandidates = count($duplicateExact) + count($duplicateComposite);
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'success' => true,
            'source'  => [
                'filename'    => $filename,
                'format'      => $ext,
                'sheet_name'  => $actualSheetName,
                'file_size'   => $fileSize,
                'duration_ms' => $durationMs,
            ],
            'mapping' => [
                'detected_headers'        => $rawHeaders,
                'mapped_fields'           => $mapping['mapped_fields'],
                'unknown_columns'         => $mapping['unknown_columns'],
                'manual_mapping_required' => $mapping['manual_mapping_required'],
            ],
            'summary' => [
                'total_rows'           => $totalRows,
                'valid_rows'           => $validRows,
                'invalid_rows'         => $invalidRows,
                'warning_rows'         => $warningRows,
                'duplicate_candidates' => $totalDuplicateCandidates,
            ],
            'validation' => [
                'errors'   => array_slice($validationErrors, 0, 50),
                'warnings' => array_slice($validationWarnings, 0, 50),
            ],
            'duplicates' => [
                'exact_hash'             => $duplicateExact,
                'composite_business_key' => $duplicateComposite,
                'manual_review_required' => $duplicateAmbiguous,
            ],
            'preview' => $previewRecords,
            'metadata' => [
                'mode'                => 'DRY_RUN',
                'database_writes'     => 0,
                'schema_mutations'    => 0,
                'scoring_version'     => 'PREVENTIVE_SCORING_v1.0',
                'processed_in_memory' => true,
            ],
        ];
    }

    /**
     * Read raw rows and headers from CSV or Excel file.
     */
    protected function readRawRows(string $filePath, string $ext, ?string $sheetName): array
    {
        if ($ext === 'csv' || $ext === 'txt') {
            return $this->readRawCsv($filePath);
        }

        return $this->readRawExcel($filePath, $sheetName);
    }

    /**
     * Native CSV reader.
     */
    protected function readRawCsv(string $filePath): array
    {
        if (($handle = fopen($filePath, 'r')) === false) {
            return ['success' => false, 'error' => "Gagal membuka file CSV: {$filePath}"];
        }

        $headers = [];
        $rows = [];
        $headerRead = false;

        while (($row = fgetcsv($handle, 8192, ',')) !== false) {
            if (!$headerRead) {
                $headers = array_map(fn($h) => trim((string)$h), $row);
                $headerRead = true;
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        return [
            'success'    => true,
            'headers'    => $headers,
            'rows'       => $rows,
            'sheet_name' => 'CSV_PRIMARY_STREAM'
        ];
    }

    /**
     * PhpSpreadsheet Excel reader (XLSX / XLS).
     */
    protected function readRawExcel(string $filePath, ?string $sheetName): array
    {
        if (!class_exists(IOFactory::class)) {
            return ['success' => false, 'error' => "PhpSpreadsheet library tidak tersedia di runtime."];
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($filePath);
            
            if ($sheetName && $spreadsheet->sheetNameExists($sheetName)) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
            } else {
                $worksheet = $spreadsheet->getActiveSheet();
            }

            $sheetTitle = $worksheet->getTitle();
            $allRows    = $worksheet->toArray(null, true, true, false);

            if (empty($allRows)) {
                return ['success' => false, 'error' => "Lembar kerja spreadsheet kosong."];
            }

            $headers = array_map(fn($h) => trim((string)$h), array_shift($allRows));
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [
                'success'    => true,
                'headers'    => $headers,
                'rows'       => $allRows,
                'sheet_name' => $sheetTitle
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => "Gagal memproses file spreadsheet: " . $e->getMessage()];
        }
    }

    /**
     * Detect column mapping from raw headers.
     */
    protected function detectColumnMapping(array $rawHeaders): array
    {
        $mappedFields          = [];
        $columnIndexMap        = [];
        $unknownColumns        = [];
        $matchedCanonicalKeys  = [];

        foreach ($rawHeaders as $idx => $headerName) {
            $cleanHeader = strtolower(trim((string)$headerName));
            if ($cleanHeader === '') {
                continue;
            }

            $matchedKey = null;
            foreach ($this->aliasDictionary as $canonicalKey => $aliases) {
                if (in_array($cleanHeader, $aliases, true)) {
                    $matchedKey = $canonicalKey;
                    break;
                }
            }

            // Fallback substring matching if exact match not found
            if (!$matchedKey) {
                foreach ($this->aliasDictionary as $canonicalKey => $aliases) {
                    foreach ($aliases as $alias) {
                        if (str_contains($cleanHeader, $alias)) {
                            $matchedKey = $canonicalKey;
                            break 2;
                        }
                    }
                }
            }

            if ($matchedKey) {
                $mappedFields[$matchedKey]       = $headerName;
                $columnIndexMap[$matchedKey]     = $idx;
                $matchedCanonicalKeys[]          = $matchedKey;
            } else {
                $unknownColumns[] = [
                    'index'       => $idx,
                    'header_name' => $headerName
                ];
            }
        }

        // Check required columns
        $manualMappingRequired = [];
        foreach ($this->requiredColumns as $req) {
            if (!in_array($req, $matchedCanonicalKeys, true)) {
                $manualMappingRequired[] = $req;
            }
        }

        return [
            'mapped_fields'           => $mappedFields,
            'column_indexes'          => $columnIndexMap,
            'unknown_columns'         => $unknownColumns,
            'manual_mapping_required' => $manualMappingRequired
        ];
    }

    /**
     * Remap indexed row array into associative array using detected column indexes.
     */
    protected function remapRow(array $rawRow, array $columnIndexMap, array $rawHeaders): array
    {
        $mapped = [];

        // 1. Map raw header names directly
        foreach ($rawHeaders as $idx => $headerName) {
            $mapped[$headerName] = $rawRow[$idx] ?? null;
        }

        // 2. Map Canonical English keys and M-04 Indonesian keys
        foreach ($columnIndexMap as $canonicalKey => $colIdx) {
            $val = $rawRow[$colIdx] ?? null;
            $mapped[$canonicalKey] = $val;

            // Also populate the standard key expected by M-04 Normalizer
            if (isset($this->canonicalToM04Keys[$canonicalKey])) {
                $m04Key = $this->canonicalToM04Keys[$canonicalKey];
                $mapped[$m04Key] = $val;
            }
        }

        // 3. Preserve indexed access
        foreach ($rawRow as $idx => $val) {
            $mapped[$idx] = $val;
        }

        return $mapped;
    }

    /**
     * Validate normalized row against enterprise business invariants.
     */
    protected function validateNormalizedRow(array $normalized, array $mappedRow, int $rowNum): array
    {
        $errors = [];

        // 1. Feeder Name Check
        if (empty($normalized['feeder_name']) || $normalized['feeder_name'] === 'UNKNOWN_FEEDER') {
            $errors[] = [
                'row'     => $rowNum,
                'field'   => 'feeder_name',
                'message' => 'Nama penyulang kosong atau tidak teridentifikasi.',
            ];
        }

        // 2. Event Date Check
        if (empty($normalized['event_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized['event_date'])) {
            $errors[] = [
                'row'     => $rowNum,
                'field'   => 'event_date',
                'message' => 'Format tanggal kejadian tidak valid (harus YYYY-MM-DD).',
            ];
        }

        // 3. Category Check
        if (!in_array($normalized['interruption_category'] ?? '', ['PERMANENT', 'TEMPORARY'], true)) {
            $errors[] = [
                'row'     => $rowNum,
                'field'   => 'interruption_category',
                'message' => 'Kategori gangguan harus PERMANENT atau TEMPORARY.',
            ];
        }

        return $errors;
    }

    /**
     * Check for non-blocking warnings in row data.
     */
    protected function checkRowWarnings(array $normalized, array $mappedRow, int $rowNum): array
    {
        $warnings = [];

        if (empty($normalized['field_narrative_raw'])) {
            $warnings[] = [
                'row'     => $rowNum,
                'field'   => 'field_narrative_raw',
                'message' => 'Uraian narasi gangguan kosong.',
            ];
        }

        if (($normalized['outage_duration_minutes'] ?? 0) <= 0) {
            $warnings[] = [
                'row'     => $rowNum,
                'field'   => 'outage_duration_minutes',
                'message' => 'Durasi padam tercatat 0 menit.',
            ];
        }

        return $warnings;
    }

    /**
     * Build standard error response.
     */
    protected function buildErrorResult(string $errorMessage): array
    {
        return [
            'success' => false,
            'error'   => $errorMessage,
            'summary' => [
                'total_rows'           => 0,
                'valid_rows'           => 0,
                'invalid_rows'         => 0,
                'warning_rows'         => 0,
                'duplicate_candidates' => 0,
            ],
            'metadata' => [
                'mode'                => 'DRY_RUN',
                'database_writes'     => 0,
                'schema_mutations'    => 0,
                'processed_in_memory' => true,
            ]
        ];
    }
}
