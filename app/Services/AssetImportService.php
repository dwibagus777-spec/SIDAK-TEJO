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

class AssetImportService
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
     * Process Uploaded Excel File (Old Standard Template)
     * Governed by CR-05 Zero Orphan & Atomic Import Invariants.
     */
    public function processImport(string $filePath): array
    {
        $db = \Config\Database::connect();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            log_message('error', '[AssetImportService] Gagal membaca berkas Excel: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Format berkas Excel tidak dapat dibaca: ' . $e->getMessage(),
            ];
        }

        if (count($rows) <= 1) {
            return [
                'success' => false,
                'message' => 'Berkas Excel kosong atau hanya berisi baris header.',
            ];
        }

        // Fetch lookup maps
        $ulpMap       = $this->getUlpLookupMap();
        $penyulangMap = $this->getPenyulangLookupMap();
        $sectionMap   = $this->getSectionLookupMap();

        // Fetch existing asset codes for uniqueness check
        $existingCodes = [];
        if ($db->tableExists('assets')) {
            $query = $db->table('assets')->select('kode_asset')->where('deleted_at IS NULL')->get();
            if ($query && method_exists($query, 'getResultArray')) {
                foreach ($query->getResultArray() as $r) {
                    if (!empty($r['kode_asset'])) {
                        $existingCodes[strtoupper(trim($r['kode_asset']))] = true;
                    }
                }
            }
        }

        // =========================================================================
        // PHASE 1 — PURE IN-MEMORY VALIDATION (ZERO WRITES / ZERO TRANSACTION)
        // =========================================================================
        $batchSequenceCache = [];
        $validBatch         = [];
        $errorReport        = [];
        $now                = date('Y-m-d H:i:s');
        $rowIndex           = 0;

        foreach ($rows as $rowNum => $row) {
            $rowIndex++;
            if ($rowIndex === 1) {
                continue; // Header row
            }

            // Extract columns by letter A-O
            $jenisAsset     = trim((string)($row['A'] ?? ''));
            $kodeAsset      = trim((string)($row['B'] ?? ''));
            $namaAsset      = trim((string)($row['C'] ?? ''));
            $ulpName        = trim((string)($row['D'] ?? ''));
            $penyulangName  = trim((string)($row['E'] ?? ''));
            $sectionName    = trim((string)($row['F'] ?? ''));
            $merk           = trim((string)($row['G'] ?? ''));
            $type           = trim((string)($row['H'] ?? ''));
            $nomorSeri      = trim((string)($row['I'] ?? ''));
            $kapasitas      = trim((string)($row['J'] ?? ''));
            $tahunInstalasi = trim((string)($row['K'] ?? ''));
            $alamat         = trim((string)($row['L'] ?? ''));
            $latitude       = trim((string)($row['M'] ?? ''));
            $longitude      = trim((string)($row['N'] ?? ''));
            $status         = strtoupper(trim((string)($row['O'] ?? 'NORMAL')));

            // Skip entirely empty row
            if (empty($kodeAsset) && empty($namaAsset) && empty($jenisAsset)) {
                continue;
            }

            $errors = [];

            if (empty($namaAsset)) {
                $errors[] = 'Nama Asset wajib diisi.';
            }

            if (empty($jenisAsset)) {
                $errors[] = 'Jenis Asset wajib diisi.';
            }

            // ULP lookup
            $ulpId = null;
            if (!empty($ulpName)) {
                $ulpKey = strtolower(trim($ulpName));
                if (isset($ulpMap[$ulpKey])) {
                    $ulpId = $ulpMap[$ulpKey];
                } else {
                    $errors[] = "ULP '{$ulpName}' tidak ditemukan di database.";
                }
            }

            // Penyulang lookup
            $penyulangId = null;
            if (!empty($penyulangName)) {
                $pKey = strtolower(trim(preg_replace('/^(penyulang|feeder|f\.|fdr)\s+/i', '', $penyulangName)));
                if (isset($penyulangMap[$pKey])) {
                    $penyulangId = $penyulangMap[$pKey];
                } else {
                    $errors[] = "Penyulang '{$penyulangName}' tidak ditemukan di database.";
                }
            }

            // Domain Invariant: Zero Orphan Distribution Assets
            $isFeederRequired = $this->assetService->requiresFeederRelation($jenisAsset ?: 'Gardu');
            if ($isFeederRequired) {
                if (empty($ulpId)) {
                    $errors[] = "ULP wajib diisi dan harus valid untuk jenis aset distribusi '{$jenisAsset}'.";
                }
                if (empty($penyulangId)) {
                    $errors[] = "Penyulang wajib diisi dan harus terdaftar di database untuk jenis aset '{$jenisAsset}'.";
                }
            } else {
                if (empty($ulpId)) {
                    $errors[] = 'ULP wajib diisi.';
                }
            }

            // Section lookup (Optional: if empty or not found in DB -> fallback to NULL gracefully)
            $sectionId = null;
            if (!empty($sectionName)) {
                $sKey = strtolower(trim($sectionName));
                $sectionId = $sectionMap[$sKey] ?? null;
            }

            // Status validation
            if (!in_array($status, ['NORMAL', 'BERMASALAH', 'CRITICAL'])) {
                $status = 'NORMAL';
            }

            // Generate or Validate Kode Asset
            if (empty($kodeAsset)) {
                try {
                    $kodeAsset = $this->assetService->generateKodeAsset($jenisAsset ?: 'Gardu', $ulpName, $penyulangName, $batchSequenceCache);
                } catch (\Throwable $e) {
                    $errors[] = 'Gagal generate Kode Asset: ' . $e->getMessage();
                }
            } else {
                $codeUpper = strtoupper($kodeAsset);
                if (isset($existingCodes[$codeUpper])) {
                    $errors[] = "Kode Asset '{$kodeAsset}' sudah digunakan (harus unik).";
                }
            }

            if (!empty($errors)) {
                $errorReport[] = [
                    'baris'      => $rowNum,
                    'kode_asset' => $kodeAsset ?: '-',
                    'nama_asset' => $namaAsset ?: '-',
                    'alasan'     => implode(' | ', $errors),
                ];
                continue;
            }

            // Mark code as used in current batch
            $existingCodes[strtoupper($kodeAsset)] = true;

            $validBatch[] = [
                'kode_asset'      => $kodeAsset,
                'nama_asset'      => $namaAsset,
                'jenis_asset'     => $jenisAsset,
                'ulp_id'          => $ulpId,
                'penyulang_id'    => $penyulangId,
                'section_id'      => $sectionId,
                'lokasi'          => $alamat ?: null,
                'latitude'        => $latitude !== '' ? (float)$latitude : null,
                'longitude'       => $longitude !== '' ? (float)$longitude : null,
                'tahun_instalasi' => is_numeric($tahunInstalasi) ? (int)$tahunInstalasi : null,
                'merk'            => $merk ?: null,
                'type'            => $type ?: null,
                'nomor_seri'      => $nomorSeri ?: null,
                'kapasitas'       => $kapasitas ?: null,
                'status'          => $status,
                'created_at'      => $now,
                'updated_at'      => $now,
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
                'file_name'    => basename($filePath),
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
                    'Import BERHASIL: Seluruh %d aset baru berhasil diimport ke database.',
                    count($validBatch)
                ),
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', '[AssetImportService] Atomic import transaction failed: ' . $e->getMessage());

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
                'message'          => 'Gagal menyimpan transaksi ke database: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create Error Report Excel File
     */
    private function createErrorReportSpreadsheet(array $errorReport): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Error Import');

        // Headers
        $sheet->setCellValue('A1', 'Nomor Baris Excel');
        $sheet->setCellValue('B1', 'Kode Asset');
        $sheet->setCellValue('C1', 'Nama Asset');
        $sheet->setCellValue('D1', 'Alasan Error / Penolakan');

        // Styling Header
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

    private function getUlpLookupMap(): array
    {
        $map = [];
        try {
            $ulps = $this->ulpModel->findAll();
            foreach ($ulps as $u) {
                if (!empty($u['nama_ulp'])) {
                    $rawName = strtolower(trim($u['nama_ulp']));
                    $map[$rawName] = (int)$u['id'];

                    $noPrefix = preg_replace('/^ulp\s+/i', '', $rawName);
                    $map[$noPrefix] = (int)$u['id'];

                    if (!str_starts_with($rawName, 'ulp ')) {
                        $map['ulp ' . $rawName] = (int)$u['id'];
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[AssetImportService] Gagal fetch ULP map: ' . $e->getMessage());
        }
        return $map;
    }

    private function getPenyulangLookupMap(): array
    {
        $map = [];
        try {
            $penyulangs = $this->penyulangModel->findAll();
            foreach ($penyulangs as $p) {
                if (!empty($p['nama_penyulang'])) {
                    $raw = strtolower(trim($p['nama_penyulang']));
                    $map[$raw] = (int)$p['id'];

                    $noPrefix = preg_replace('/^(penyulang|feeder|f\.|fdr)\s+/i', '', $raw);
                    $map[$noPrefix] = (int)$p['id'];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[AssetImportService] Gagal fetch Penyulang map: ' . $e->getMessage());
        }
        return $map;
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
            log_message('error', '[AssetImportService] Gagal fetch Section map: ' . $e->getMessage());
        }
        return $map;
    }
}
