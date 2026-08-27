<?php

namespace App\Services;

use App\Models\AssetModel;
use App\Models\UlpModel;
use App\Models\PenyulangModel;
use App\Models\SectionModel;
use App\Models\AssetImportBatchModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Dedicated Service for Processing Dynamic Template Excel Imports
 * Governed by CR-05 Zero Orphan & Atomic Import Invariants.
 */
class DynamicAssetImportService
{
    private AssetModel $assetModel;
    private UlpModel $ulpModel;
    private PenyulangModel $penyulangModel;
    private SectionModel $sectionModel;
    private AssetService $assetService;

    private static function ensureComposerAutoload(): void
    {
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, true)) {
            return;
        }

        $candidates = [
            defined('COMPOSER_PATH') ? COMPOSER_PATH : '',
            defined('ROOTPATH') ? ROOTPATH . 'vendor/autoload.php' : '',
            defined('APPPATH') ? realpath(APPPATH . '../vendor/autoload.php') : '',
            defined('FCPATH') ? realpath(FCPATH . '../vendor/autoload.php') : '',
            realpath(__DIR__ . '/../../vendor/autoload.php'),
        ];

        foreach ($candidates as $path) {
            if (!empty($path) && is_file($path)) {
                require_once $path;
                if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, true)) {
                    break;
                }
            }
        }
    }

    public function __construct()
    {
        self::ensureComposerAutoload();
        $this->assetModel     = new AssetModel();
        $this->ulpModel       = new UlpModel();
        $this->penyulangModel = new PenyulangModel();
        $this->sectionModel   = new SectionModel();
        $this->assetService   = new AssetService();
    }

    /**
     * Check if asset type strictly requires feeder (penyulang) relation.
     */
    public function requiresFeederRelation(string $jenis): bool
    {
        return $this->assetService->requiresFeederRelation($jenis);
    }

    /**
     * Process Uploaded Excel File with In-Memory Pre-Validation and Atomic DB Commit.
     */
    public function processImport(array $rows, array $metadata = [], string $originalFileName = ''): array
    {
        $db = \Config\Database::connect();

        // 1. Fetch lookup master datasets safely
        $ulpList         = [];
        $penyulangList   = [];
        try {
            $ulpList = $this->ulpModel->findAll() ?: [];
        } catch (\Throwable $e) {
            log_message('error', '[DynamicAssetImportService] ULP fetch fallback: ' . $e->getMessage());
        }

        try {
            $penyulangList = $this->penyulangModel->findAll() ?: [];
        } catch (\Throwable $e) {
            log_message('error', '[DynamicAssetImportService] Penyulang fetch fallback: ' . $e->getMessage());
        }

        $sectionMap      = $this->getSectionLookupMap();
        $constructionMap = $this->getConstructionTypeLookupMap();

        // 2. Parse header row 1 to map column letter -> field key
        $headerRow = $rows[1] ?? [];
        $columnMap = [];

        foreach ($headerRow as $colLetter => $cellVal) {
            $h = strtolower(trim((string)$cellVal));
            if (str_contains($h, 'up3')) {
                $columnMap['up3'] = $colLetter;
            } elseif (str_contains($h, 'ulp')) {
                $columnMap['ulp'] = $colLetter;
            } elseif (str_contains($h, 'jenis')) {
                $columnMap['jenis_asset'] = $colLetter;
            } elseif (str_contains($h, 'nama')) {
                $columnMap['nama_asset'] = $colLetter;
            } elseif (str_contains($h, 'penyulang') || str_contains($h, 'feeder')) {
                $columnMap['penyulang'] = $colLetter;
            } elseif (str_contains($h, 'konstruksi')) {
                $columnMap['konstruksi'] = $colLetter;
            } elseif (str_contains($h, 'merk')) {
                $columnMap['merk'] = $colLetter;
            } elseif (str_contains($h, 'tipe') || str_contains($h, 'material')) {
                $columnMap['type'] = $colLetter;
            } elseif (str_contains($h, 'seri')) {
                $columnMap['nomor_seri'] = $colLetter;
            } elseif (str_contains($h, 'kapasitas') || str_contains($h, 'tinggi')) {
                $columnMap['kapasitas'] = $colLetter;
            } elseif (str_contains($h, 'tahun')) {
                $columnMap['tahun_instalasi'] = $colLetter;
            } elseif (str_contains($h, 'alamat') || str_contains($h, 'lokasi')) {
                $columnMap['lokasi'] = $colLetter;
            } elseif (str_contains($h, 'lat')) {
                $columnMap['latitude'] = $colLetter;
            } elseif (str_contains($h, 'long')) {
                $columnMap['longitude'] = $colLetter;
            } elseif (str_contains($h, 'section')) {
                $columnMap['section'] = $colLetter;
            }
        }

        // =========================================================================
        // PHASE 1 — PURE IN-MEMORY VALIDATION (NO TRANSACTION OPENED / NO WRITES)
        // =========================================================================
        $batchComposites    = [];
        $batchSequenceCache = [];
        $validBatch         = [];
        $errorReport        = [];
        $now                = date('Y-m-d H:i:s');
        $rowIndex           = 0;

        foreach ($rows as $rowNum => $row) {
            $rowIndex++;
            if ($rowIndex === 1) {
                continue; // Skip header row
            }

            // Extract row values
            $up3Name        = trim((string)($row[$columnMap['up3'] ?? 'A'] ?? ''));
            $ulpRawInput    = trim((string)($row[$columnMap['ulp'] ?? 'B'] ?? ''));
            $jenisAsset     = trim((string)($row[$columnMap['jenis_asset'] ?? 'C'] ?? ''));
            $namaAsset      = trim((string)($row[$columnMap['nama_asset'] ?? 'D'] ?? ''));
            $penyulangRaw   = trim((string)($row[$columnMap['penyulang'] ?? 'E'] ?? ''));
            $konstruksiName = trim((string)($row[$columnMap['konstruksi'] ?? ''] ?? ''));
            $merk           = trim((string)($row[$columnMap['merk'] ?? 'F'] ?? ''));
            $type           = trim((string)($row[$columnMap['type'] ?? 'G'] ?? ''));
            $nomorSeri      = trim((string)($row[$columnMap['nomor_seri'] ?? 'H'] ?? ''));
            $kapasitas      = trim((string)($row[$columnMap['kapasitas'] ?? 'I'] ?? ''));
            $tahunInstalasi = trim((string)($row[$columnMap['tahun_instalasi'] ?? 'J'] ?? ''));
            $alamat         = trim((string)($row[$columnMap['lokasi'] ?? 'K'] ?? ''));
            $latitude       = trim((string)($row[$columnMap['latitude'] ?? 'L'] ?? ''));
            $longitude      = trim((string)($row[$columnMap['longitude'] ?? 'M'] ?? ''));
            $sectionName    = trim((string)($row[$columnMap['section'] ?? 'N'] ?? ''));

            // Normalize Jenis Asset
            $jLower = strtolower(trim($jenisAsset));
            if (empty($jenisAsset) && !empty($metadata['JENIS_ASSET'])) {
                $jLower = strtolower(trim($metadata['JENIS_ASSET']));
            }

            if (preg_match('/^jtm/i', $jLower) || $jLower === 'jtm_tiang' || $jLower === 'tiang') {
                $jenisAsset = 'JTM';
            } elseif (preg_match('/^jtr/i', $jLower)) {
                $jenisAsset = 'JTR';
            } elseif (preg_match('/^gardu/i', $jLower) || $jLower === 'gd') {
                $jenisAsset = 'GARDU';
            } elseif (preg_match('/^trafo/i', $jLower)) {
                $jenisAsset = 'TRAFO';
            } elseif (preg_match('/^lbsm/i', $jLower)) {
                $jenisAsset = 'LBSM';
            } elseif (preg_match('/^lbs/i', $jLower)) {
                $jenisAsset = 'LBS';
            } elseif (preg_match('/^recloser/i', $jLower)) {
                $jenisAsset = 'RECLOSER';
            } elseif (preg_match('/^kubikel/i', $jLower)) {
                $jenisAsset = 'KUBIKEL';
            } else {
                $jenisAsset = !empty($jenisAsset) ? strtoupper(trim($jenisAsset)) : 'JTM';
            }

            // Skip entirely empty row
            if (empty($namaAsset) && empty($jenisAsset) && empty($ulpRawInput) && empty($penyulangRaw)) {
                continue;
            }

            $rowErrors = [];

            // 1. Mandatory field checks
            if (empty($namaAsset)) {
                $rowErrors[] = 'Nama Asset wajib diisi.';
            }

            if (empty($jenisAsset)) {
                $rowErrors[] = 'Jenis Asset wajib diisi.';
            }

            // 2. Multi-strategy Feeder (Penyulang) Resolution
            $resolvedPenyulang = $this->resolvePenyulang($penyulangRaw, $namaAsset, $metadata, $penyulangList);
            $penyulangId       = $resolvedPenyulang['id'] ?? null;
            $penyulangName     = $resolvedPenyulang['name'] ?? null;
            $penyulangUlpId    = $resolvedPenyulang['ulp_id'] ?? null;

            // 3. Multi-strategy ULP Resolution
            $resolvedUlp = $this->resolveUlp($ulpRawInput, $metadata, $ulpList, $penyulangUlpId);
            $ulpId       = $resolvedUlp['id'] ?? null;
            $ulpName     = $resolvedUlp['name'] ?? null;

            // 4. Domain Invariant: Zero Orphan Distribution Assets
            $isFeederRequired = $this->requiresFeederRelation($jenisAsset);
            if ($isFeederRequired) {
                if (empty($ulpId)) {
                    $rowErrors[] = "ULP tidak dapat di-resolve untuk jenis aset {$jenisAsset}. Pastikan nama ULP valid.";
                }
                if (empty($penyulangId)) {
                    $rowErrors[] = sprintf(
                        'Penyulang tidak ditemukan/gagal di-resolve untuk aset "%s" (%s). Wajib terikat ke Master Feeder.',
                        $namaAsset ?: '-',
                        $jenisAsset
                    );
                }
            } else {
                if (empty($ulpId)) {
                    $rowErrors[] = 'ULP wajib diisi.';
                }
            }

            // Cross-check: If both ULP and Penyulang resolved, verify Penyulang belongs to ULP
            if (!empty($ulpId) && !empty($penyulangId) && !empty($penyulangUlpId)) {
                if ((int)$penyulangUlpId !== (int)$ulpId) {
                    $rowErrors[] = "Penyulang '{$penyulangName}' tidak termasuk dalam ULP '{$ulpName}'.";
                }
            }

            // 5. Section lookup (Optional: gracefully fallback to NULL if empty/unresolved)
            $sectionId = null;
            if (!empty($sectionName)) {
                $sKey = strtolower(trim($sectionName));
                $sectionId = $sectionMap[$sKey] ?? null;
            }

            // 6. Construction Type lookup & Compatibility Matrix
            $constructionTypeId = null;
            if (!empty($konstruksiName)) {
                $cKey  = strtolower(trim($konstruksiName));
                $cNorm = preg_replace('/[^a-z0-9]/', '', $cKey);

                $matchedObj = $constructionMap[$cKey] ?? ($constructionMap[$cNorm] ?? null);

                if ($matchedObj !== null) {
                    $cId   = $matchedObj['id'];
                    $cCode = $matchedObj['code'];

                    if (!\App\Services\AssetMatrixService::isCompatible($jenisAsset, $cCode)) {
                        $rowErrors[] = "Konstruksi '{$konstruksiName}' ({$cCode}) tidak cocok untuk Jenis Asset '{$jenisAsset}'.";
                    } else {
                        $constructionTypeId = $cId;
                    }
                } else {
                    $rowErrors[] = "Konstruksi '{$konstruksiName}' tidak ditemukan di Master Konstruksi.";
                }
            }

            // 7. Composite Duplicate Check: ULP + Jenis Asset + Nama Asset
            if (!empty($ulpId) && !empty($jenisAsset) && !empty($namaAsset)) {
                $compositeKey = strtolower($ulpId . '_' . $jenisAsset . '_' . $namaAsset);

                if (isset($batchComposites[$compositeKey])) {
                    $rowErrors[] = "Data duplikat di dalam berkas Excel ini (ULP + Jenis + Nama sama).";
                } else {
                    $existCount = 0;
                    try {
                        if ($db->tableExists('assets')) {
                            $existCount = $db->table('assets')
                                ->where('ulp_id', $ulpId)
                                ->where('jenis_asset', $jenisAsset)
                                ->where('nama_asset', $namaAsset)
                                ->where('deleted_at IS NULL')
                                ->countAllResults();
                        }
                    } catch (\Throwable $e) {}

                    if ($existCount > 0) {
                        $rowErrors[] = "Asset '{$namaAsset}' ({$jenisAsset}) sudah ada di database untuk ULP tersebut (Duplikat).";
                    }
                }
            }

            // Collect errors if any
            if (!empty($rowErrors)) {
                $errorReport[] = [
                    'baris'      => $rowNum,
                    'kode_asset' => '-',
                    'nama_asset' => $namaAsset ?: '-',
                    'alasan'     => implode(' | ', $rowErrors),
                ];
                continue;
            }

            // Mark composite key in current batch
            $compositeKey = strtolower($ulpId . '_' . $jenisAsset . '_' . $namaAsset);
            $batchComposites[$compositeKey] = true;

            // Generate canonical Asset Code
            try {
                $kodeAsset = $this->assetService->generateKodeAsset($jenisAsset, $ulpName, $penyulangName, $batchSequenceCache);
            } catch (\Throwable $e) {
                $errorReport[] = [
                    'baris'      => $rowNum,
                    'kode_asset' => '-',
                    'nama_asset' => $namaAsset ?: '-',
                    'alasan'     => 'Gagal generate Kode Asset: ' . $e->getMessage(),
                ];
                continue;
            }

            $validBatch[] = [
                'kode_asset'           => $kodeAsset,
                'nama_asset'           => $namaAsset,
                'jenis_asset'          => $jenisAsset,
                'ulp_id'               => $ulpId,
                'penyulang_id'         => $penyulangId,
                'section_id'           => $sectionId,
                'construction_type_id' => $constructionTypeId,
                'lokasi'               => $alamat ?: null,
                'latitude'             => $latitude !== '' ? (float)$latitude : null,
                'longitude'            => $longitude !== '' ? (float)$longitude : null,
                'tahun_instalasi'      => is_numeric($tahunInstalasi) ? (int)$tahunInstalasi : null,
                'merk'                 => $merk ?: null,
                'type'                 => $type ?: null,
                'nomor_seri'           => $nomorSeri ?: null,
                'kapasitas'            => $kapasitas ?: null,
                'status'               => 'NORMAL',
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        // =========================================================================
        // HARD GATE: ONE INVALID ROW = ZERO DATABASE WRITES (ATOMIC ALL-OR-NOTHING)
        // =========================================================================
        if (!empty($errorReport)) {
            $errorExcelPath = $this->createErrorReportSpreadsheet($errorReport);
            return [
                'success'          => false,
                'inserted'         => 0,
                'failed'           => count($errorReport),
                'total'            => count($errorReport) + count($validBatch),
                'errors'           => $errorReport,
                'error_excel_path' => $errorExcelPath,
                'message'          => sprintf(
                    'Import DIBATALKAN: Terdapat %d baris tidak valid. 0 aset baru dimasukkan ke database (Semua data harus valid sebelum diimport).',
                    count($errorReport)
                ),
            ];
        }

        if (empty($validBatch)) {
            return [
                'success'          => false,
                'inserted'         => 0,
                'failed'           => 0,
                'total'            => 0,
                'errors'           => [],
                'error_excel_path' => null,
                'message'          => 'Berkas Excel tidak memiliki baris data aset untuk diimport.',
            ];
        }

        // =========================================================================
        // PHASE 2 — ATOMIC DATABASE TRANSACTION (100% VALID DATA ONLY)
        // =========================================================================
        $db->transBegin();

        try {
            $batchCode = 'BATCH-' . date('Ymd-His') . '-' . rand(100, 999);
            $sampleRow = $validBatch[0];
            $batchUlp  = $sampleRow['ulp_id'] ?? null;
            $batchPen  = $sampleRow['penyulang_id'] ?? null;

            $batchModel = new AssetImportBatchModel();
            $batchId = $batchModel->insert([
                'batch_code'   => $batchCode,
                'ulp_id'       => $batchUlp,
                'penyulang_id' => $batchPen,
                'file_name'    => !empty($originalFileName) ? basename($originalFileName) : ('Import_Asset_' . date('Ymd_His') . '.xlsx'),
                'total_rows'   => count($validBatch),
                'success_rows' => count($validBatch),
                'failed_rows'  => 0,
                'imported_by'  => session()->get('user_id') ?? null,
                'imported_at'  => date('Y-m-d H:i:s'),
                'status'       => 'ACTIVE',
            ], true);

            if (!$batchId) {
                $err = $db->error();
                throw new \RuntimeException('Gagal membuat log import batch: ' . ($err['message'] ?? 'Unknown error'));
            }

            foreach ($validBatch as &$vItem) {
                $vItem['import_batch_id'] = $batchId;
            }
            unset($vItem);

            $chunks = array_chunk($validBatch, 500);
            foreach ($chunks as $chunk) {
                $insertedCount = $db->table('assets')->insertBatch($chunk);
                if ($insertedCount === false) {
                    $err = $db->error();
                    throw new \RuntimeException('Gagal insert batch assets: ' . ($err['message'] ?? 'Query insertBatch gagal'));
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction integrity check failed.');
            }

            $db->transCommit();

            return [
                'success'          => true,
                'inserted'         => count($validBatch),
                'failed'           => 0,
                'total'            => count($validBatch),
                'batch_id'         => $batchId,
                'errors'           => [],
                'error_excel_path' => null,
                'message'          => sprintf(
                    'Import BERHASIL: Seluruh %d aset baru berhasil di-generate & diimport secara utuh ke database.',
                    count($validBatch)
                ),
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', '[DynamicAssetImportService] Atomic import transaction failed: ' . $e->getMessage());

            return [
                'success'          => false,
                'inserted'         => 0,
                'failed'           => count($validBatch),
                'total'            => count($validBatch),
                'errors'           => [
                    [
                        'baris'      => 'ALL',
                        'kode_asset' => '-',
                        'nama_asset' => 'Transaction Rollback',
                        'alasan'     => $e->getMessage(),
                    ]
                ],
                'error_excel_path' => null,
                'message'          => 'Gagal menyimpan transaksi ke database (seluruh perubahan dibatalkan): ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Multi-Strategy Feeder (Penyulang) Resolver.
     * Order of Precedence:
     * 1. SYSTEM_METADATA - PENYULANG_ID
     * 2. SYSTEM_METADATA - PENYULANG_NAME
     * 3. Explicit Excel Column
     * 4. Smart Prefix Extraction from Asset Name (e.g. CANDRAMAS_011 -> CANDRAMAS)
     */
    private function resolvePenyulang(
        ?string $explicitColumn,
        ?string $assetName,
        array $metadata,
        array $penyulangList
    ): array {
        // Strategy 1: SYSTEM_METADATA by ID
        $metaId = $metadata['PENYULANG_ID'] ?? null;
        if (!empty($metaId)) {
            foreach ($penyulangList as $p) {
                if ((int)$p['id'] === (int)$metaId) {
                    return [
                        'id'     => (int)$p['id'],
                        'name'   => $p['nama_penyulang'],
                        'ulp_id' => !empty($p['ulp_id']) ? (int)$p['ulp_id'] : null,
                        'source' => 'SYSTEM_METADATA_ID',
                    ];
                }
            }
        }

        // Strategy 2: SYSTEM_METADATA by Name
        $metaName = $metadata['PENYULANG_NAME'] ?? null;
        if (!empty($metaName)) {
            $matched = $this->resolvePenyulangByName($metaName, $penyulangList);
            if ($matched !== null) {
                return $matched + ['source' => 'SYSTEM_METADATA_NAME'];
            }
        }

        // Strategy 3: Explicit Excel Column
        if (!empty($explicitColumn)) {
            $matched = $this->resolvePenyulangByName($explicitColumn, $penyulangList);
            if ($matched !== null) {
                return $matched + ['source' => 'EXPLICIT_COLUMN'];
            }
        }

        // Strategy 4: Extract Prefix from Asset Name (e.g. CANDRAMAS_011 -> CANDRAMAS)
        $candidatePrefix = $this->extractFeederPrefix($assetName);
        if (!empty($candidatePrefix)) {
            $matched = $this->resolvePenyulangByName($candidatePrefix, $penyulangList);
            if ($matched !== null) {
                return $matched + ['source' => 'ASSET_NAME_PREFIX'];
            }
        }

        return [
            'id'     => null,
            'name'   => null,
            'ulp_id' => null,
            'source' => null,
        ];
    }

    /**
     * Resolve Penyulang entity from list by string name.
     */
    private function resolvePenyulangByName(?string $rawName, array $penyulangList): ?array
    {
        if (empty($rawName)) {
            return null;
        }

        $clean = strtoupper(trim($rawName));
        $noPrefix = strtoupper(trim(preg_replace('/^(penyulang|feeder|f\.|fdr)\s+/i', '', $clean)));
        $normAlnum = preg_replace('/[^A-Z0-9]/', '', $noPrefix);

        // Pass 1: Exact match on nama_penyulang
        foreach ($penyulangList as $p) {
            if (strcasecmp(trim($p['nama_penyulang']), $clean) === 0 || strcasecmp(trim($p['nama_penyulang']), $noPrefix) === 0) {
                return [
                    'id'     => (int)$p['id'],
                    'name'   => $p['nama_penyulang'],
                    'ulp_id' => !empty($p['ulp_id']) ? (int)$p['ulp_id'] : null,
                ];
            }
        }

        // Pass 2: Alphanumeric match (ignoring spaces, underscores, dashes)
        if (!empty($normAlnum)) {
            foreach ($penyulangList as $p) {
                $pNorm = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($p['nama_penyulang'])));
                if ($pNorm === $normAlnum) {
                    return [
                        'id'     => (int)$p['id'],
                        'name'   => $p['nama_penyulang'],
                        'ulp_id' => !empty($p['ulp_id']) ? (int)$p['ulp_id'] : null,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Extract Feeder Prefix Candidate from Asset Name (e.g. CANDRAMAS_011 -> CANDRAMAS).
     */
    private function extractFeederPrefix(?string $assetName): ?string
    {
        if (empty($assetName)) {
            return null;
        }

        $clean = trim($assetName);

        // Pattern 1: Delimited by _, -, /, or space followed by digits or codes (e.g. CANDRAMAS_011, CANDRAMAS-016)
        if (preg_match('/^([A-Za-z0-9\s]+?)[_\-\/\s]+(\d+|[A-Za-z0-9]+)$/u', $clean, $matches)) {
            $candidate = trim($matches[1]);
            if (strlen($candidate) >= 3) {
                return $candidate;
            }
        }

        // Pattern 2: Alpha string directly followed by numbers (e.g. CANDRAMAS011)
        if (preg_match('/^([A-Za-z\s]+)\d+$/u', $clean, $matches)) {
            $candidate = trim($matches[1]);
            if (strlen($candidate) >= 3) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Multi-Strategy ULP Resolver.
     * Order of Precedence:
     * 1. SYSTEM_METADATA - ULP_ID
     * 2. SYSTEM_METADATA - ULP_NAME
     * 3. Explicit Excel Column
     * 4. Fallback to Resolved Feeder ULP ID
     */
    private function resolveUlp(
        ?string $explicitColumn,
        array $metadata,
        array $ulpList,
        ?int $resolvedFeederUlpId = null
    ): array {
        // Strategy 1: SYSTEM_METADATA by ID
        $metaId = $metadata['ULP_ID'] ?? null;
        if (!empty($metaId)) {
            foreach ($ulpList as $u) {
                if ((int)$u['id'] === (int)$metaId) {
                    return [
                        'id'     => (int)$u['id'],
                        'name'   => $u['nama_ulp'],
                        'source' => 'SYSTEM_METADATA_ID',
                    ];
                }
            }
        }

        // Strategy 2: SYSTEM_METADATA by Name
        $metaName = $metadata['ULP_NAME'] ?? null;
        if (!empty($metaName)) {
            $matched = $this->resolveUlpByName($metaName, $ulpList);
            if ($matched !== null) {
                return $matched + ['source' => 'SYSTEM_METADATA_NAME'];
            }
        }

        // Strategy 3: Explicit Excel Column
        if (!empty($explicitColumn)) {
            $matched = $this->resolveUlpByName($explicitColumn, $ulpList);
            if ($matched !== null) {
                return $matched + ['source' => 'EXPLICIT_COLUMN'];
            }
        }

        // Strategy 4: Feeder ULP Linkage Fallback
        if (!empty($resolvedFeederUlpId)) {
            foreach ($ulpList as $u) {
                if ((int)$u['id'] === (int)$resolvedFeederUlpId) {
                    return [
                        'id'     => (int)$u['id'],
                        'name'   => $u['nama_ulp'],
                        'source' => 'FEEDER_ULP_LINKAGE',
                    ];
                }
            }
        }

        return [
            'id'     => null,
            'name'   => null,
            'source' => null,
        ];
    }

    /**
     * Resolve ULP entity from list by string name.
     */
    private function resolveUlpByName(?string $rawName, array $ulpList): ?array
    {
        if (empty($rawName)) {
            return null;
        }

        $clean = strtoupper(trim($rawName));
        $noPrefix = strtoupper(trim(preg_replace('/^ulp\s+/i', '', $clean)));

        foreach ($ulpList as $u) {
            $uName = strtoupper(trim($u['nama_ulp']));
            $uNoPrefix = strtoupper(trim(preg_replace('/^ulp\s+/i', '', $uName)));

            if ($clean === $uName || $noPrefix === $uNoPrefix || $clean === $uNoPrefix || $noPrefix === $uName) {
                return [
                    'id'   => (int)$u['id'],
                    'name' => $u['nama_ulp'],
                ];
            }

            // Keyword matching for canonical ULPs
            if ((str_contains($clean, 'KOTA') || str_contains($clean, 'SIDOARJO')) && str_contains($uName, 'KOTA')) {
                return ['id' => (int)$u['id'], 'name' => $u['nama_ulp']];
            }
            if (str_contains($clean, 'KRIAN') && str_contains($uName, 'KRIAN')) {
                return ['id' => (int)$u['id'], 'name' => $u['nama_ulp']];
            }
            if (str_contains($clean, 'PORONG') && str_contains($uName, 'PORONG')) {
                return ['id' => (int)$u['id'], 'name' => $u['nama_ulp']];
            }
            if (str_contains($clean, 'SEDATI') && str_contains($uName, 'SEDATI')) {
                return ['id' => (int)$u['id'], 'name' => $u['nama_ulp']];
            }
            if (str_contains($clean, 'MOJOSARI') && str_contains($uName, 'MOJOSARI')) {
                return ['id' => (int)$u['id'], 'name' => $u['nama_ulp']];
            }
        }

        return null;
    }

    /**
     * Create Error Report Excel File
     */
    private function createErrorReportSpreadsheet(array $errorReport): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Error Import');

        $sheet->setCellValue('A1', 'Nomor Baris Excel');
        $sheet->setCellValue('B1', 'Kode Asset');
        $sheet->setCellValue('C1', 'Nama Asset');
        $sheet->setCellValue('D1', 'Alasan Error / Penolakan');

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEE6B6B');

        $rowNum = 2;
        foreach ($errorReport as $err) {
            $sheet->setCellValue('A' . $rowNum, $err['baris']);
            $sheet->setCellValue('B' . $rowNum, $err['kode_asset']);
            $sheet->setCellValue('C' . $rowNum, $err['nama_asset']);
            $sheet->setCellValue('D' . $rowNum, $err['alasan']);
            $rowNum++;
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tempPath = WRITEPATH . 'uploads/error_import_' . time() . '.xlsx';
        if (!is_dir(dirname($tempPath))) {
            @mkdir(dirname($tempPath), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return $tempPath;
    }

    private function getSectionLookupMap(): array
    {
        $map = [];
        try {
            $sections = $this->sectionModel->findAll();
            foreach ($sections as $s) {
                if (!empty($s['nama_section'])) {
                    $map[strtolower(trim($s['nama_section']))] = (int)$s['id'];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[DynamicAssetImportService] Gagal fetch Section map: ' . $e->getMessage());
        }
        return $map;
    }

    private function getConstructionTypeLookupMap(): array
    {
        $map = [];
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('construction_types')) {
                $items = $db->table('construction_types')->select('id, code, name')->get()->getResultArray();
                foreach ($items as $item) {
                    $obj = [
                        'id'   => (int)$item['id'],
                        'code' => (string)$item['code'],
                    ];
                    if (!empty($item['code'])) {
                        $rawCode = strtolower(trim($item['code']));
                        $map[$rawCode] = $obj;

                        $normCode = preg_replace('/[^a-z0-9]/', '', $rawCode);
                        $map[$normCode] = $obj;
                    }
                    if (!empty($item['name'])) {
                        $rawName = strtolower(trim($item['name']));
                        $map[$rawName] = $obj;
                    }
                }

                $aliasMap = [
                    'gtt 1 tiang' => 'gtt1',
                    'gtt 1'       => 'gtt1',
                    'gtt1 tiang'  => 'gtt1',
                    'gtt 2 tiang' => 'gtt2',
                    'gtt 2'       => 'gtt2',
                    'gtt2 tiang'  => 'gtt2',
                    'gt2'         => 'gtt2',
                    'pemisah'     => 'pms',
                    'pemutus'     => 'pmt',
                    'tmtp3'       => 'tmtp',
                    'tm-tp'       => 'tmtp',
                    'tm-tp3'      => 'tmtp',
                    'tm-16'       => 'tm16',
                    'tm-16a'      => 'tm16a',
                    'tmvitc'      => 'tmmvtic',
                    'tm-vitc'     => 'tmmvtic',
                    'mvtic'       => 'tmmvtic',
                ];

                foreach ($aliasMap as $alias => $targetCode) {
                    if (isset($map[$targetCode])) {
                        $map[$alias] = $map[$targetCode];
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[DynamicAssetImportService] Gagal fetch Construction map: ' . $e->getMessage());
        }
        return $map;
    }
}
