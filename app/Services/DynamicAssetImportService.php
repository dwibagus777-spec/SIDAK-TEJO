<?php

namespace App\Services;

use App\Models\AssetModel;
use App\Models\UlpModel;
use App\Models\PenyulangModel;
use App\Models\SectionModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Dedicated Service for Processing New Dynamic Template Excel Imports
 * Isolated from old template import logic for Zero Regression guarantee.
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
     * Process Uploaded New Template Excel File
     */
    public function processImport(array $rows, string $originalFileName = ''): array
    {
        $db = \Config\Database::connect();

        $ulpMap          = $this->getUlpLookupMap();
        $penyulangMap    = $this->getPenyulangLookupMap();
        $sectionMap      = $this->getSectionLookupMap();
        $constructionMap = $this->getConstructionTypeLookupMap();

        // Parse header row 1 to map column letter -> field key
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
            } elseif (str_contains($h, 'penyulang')) {
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

        // Intra-batch duplicate tracker & sequence cache
        $batchComposites    = [];
        $batchSequenceCache = [];
        $validBatch         = [];
        $errorReport        = [];
        $inserted        = 0;
        $failed          = 0;
        $now             = date('Y-m-d H:i:s');
        $rowIndex        = 0;

        foreach ($rows as $rowNum => $row) {
            $rowIndex++;
            if ($rowIndex === 1) {
                continue; // Header row
            }

            // Extract values using mapped columns
            $up3Name        = trim((string)($row[$columnMap['up3'] ?? 'A'] ?? ''));
            $ulpName        = trim((string)($row[$columnMap['ulp'] ?? 'B'] ?? ''));
            $jenisAsset     = trim((string)($row[$columnMap['jenis_asset'] ?? 'C'] ?? ''));
            $namaAsset      = trim((string)($row[$columnMap['nama_asset'] ?? 'D'] ?? ''));
            $penyulangName  = trim((string)($row[$columnMap['penyulang'] ?? 'E'] ?? ''));
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

            // Level 1: Priority 1 — Jenis Asset Normalization (Source of Truth from Excel Jenis Column)
            $jLower = strtolower(trim($jenisAsset));

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
            if (empty($namaAsset) && empty($jenisAsset) && empty($ulpName)) {
                continue;
            }

            $errors = [];

            // Mandatory Validations
            if (empty($namaAsset)) {
                $errors[] = 'Nama Asset wajib diisi.';
            }

            if (empty($jenisAsset)) {
                $errors[] = 'Jenis Asset wajib diisi.';
            }

            // ULP lookup
            $ulpId = null;
            if (!empty($ulpName)) {
                $ulpKey = strtolower($ulpName);
                if (isset($ulpMap[$ulpKey])) {
                    $ulpId = $ulpMap[$ulpKey];
                } else {
                    $errors[] = "ULP '{$ulpName}' tidak ditemukan di database.";
                }
            } else {
                $errors[] = 'ULP wajib diisi.';
            }

            // Penyulang lookup (Optional)
            $penyulangId = null;
            if (!empty($penyulangName)) {
                $pKey = strtolower($penyulangName);
                $penyulangId = $penyulangMap[$pKey] ?? null;
            }

            // Section lookup (OPTIONAL: If empty or not found -> NULL, DO NOT FAIL!)
            $sectionId = null;
            if (!empty($sectionName)) {
                $sKey = strtolower($sectionName);
                $sectionId = $sectionMap[$sKey] ?? null;
            }

            // Construction Type lookup (Validates against Master Konstruksi & Compatibility Matrix)
            $constructionTypeId = null;
            if (!empty($konstruksiName)) {
                $cKey  = strtolower(trim($konstruksiName));
                $cNorm = preg_replace('/[^a-z0-9]/', '', $cKey);

                $matchedObj = $constructionMap[$cKey] ?? ($constructionMap[$cNorm] ?? null);

                if ($matchedObj !== null) {
                    $cId   = $matchedObj['id'];
                    $cCode = $matchedObj['code'];

                    // Central Compatibility Matrix Check
                    if (!\App\Services\AssetMatrixService::isCompatible($jenisAsset, $cCode)) {
                        $errors[] = "Konstruksi '{$konstruksiName}' ({$cCode}) tidak cocok untuk Jenis Asset '{$jenisAsset}'.";
                    } else {
                        $constructionTypeId = $cId;
                    }
                } else {
                    $errors[] = "Konstruksi '{$konstruksiName}' tidak ditemukan di Master Konstruksi.";
                }
            }

            // Composite Duplicate Check: ULP + Jenis Asset + Nama Asset (Case-insensitive, Soft-delete aware)
            if (!empty($ulpId) && !empty($jenisAsset) && !empty($namaAsset)) {
                $compositeKey = strtolower($ulpId . '_' . $jenisAsset . '_' . $namaAsset);

                if (isset($batchComposites[$compositeKey])) {
                    $errors[] = "Data duplikat di dalam berkas Excel ini (ULP + Jenis + Nama sama).";
                } else {
                    // Check against DB assets table
                    $existCount = $db->table('assets')
                        ->where('ulp_id', $ulpId)
                        ->where('jenis_asset', $jenisAsset)
                        ->where('nama_asset', $namaAsset)
                        ->where('deleted_at IS NULL')
                        ->countAllResults();

                    if ($existCount > 0) {
                        $errors[] = "Asset '{$namaAsset}' ({$jenisAsset}) sudah ada di database untuk ULP tersebut (Duplikat).";
                    }
                }
            }

            if (!empty($errors)) {
                $failed++;
                $errorReport[] = [
                    'baris'      => $rowNum,
                    'kode_asset' => '(Auto Generate)',
                    'nama_asset' => $namaAsset ?: '-',
                    'alasan'     => implode(' | ', $errors),
                ];
                continue;
            }

            // Mark composite key as used in current batch
            $compositeKey = strtolower($ulpId . '_' . $jenisAsset . '_' . $namaAsset);
            $batchComposites[$compositeKey] = true;

            // Auto Generate Kode Asset (e.g. AST-KOTA-BNJRKMTREN-GRD-001)
            $kodeAsset = $this->assetService->generateKodeAsset($jenisAsset, $ulpName, $penyulangName, $batchSequenceCache);

            $validBatch[] = [
                'kode_asset'           => $kodeAsset,
                'nama_asset'           => $namaAsset,
                'jenis_asset'          => $jenisAsset,
                'ulp_id'               => $ulpId,
                'penyulang_id'         => $penyulangId,
                'section_id'           => $sectionId,
                'construction_type_id' => $constructionTypeId,
                'lokasi'               => $alamat ?: null,
                'latitude'             => $latitude ?: null,
                'longitude'            => $longitude ?: null,
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

        // DB Transaction for safe atomic batch insert with Import Batch Tracking
        if (!empty($validBatch)) {
            $db->transBegin();
            try {
                $batchCode = 'BATCH-' . date('Ymd-His') . '-' . rand(100, 999);
                $sampleRow = $validBatch[0];
                $batchUlp  = $sampleRow['ulp_id'] ?? null;
                $batchPen  = $sampleRow['penyulang_id'] ?? null;

                $batchModel = new \App\Models\AssetImportBatchModel();
                $batchId = $batchModel->insert([
                    'batch_code'   => $batchCode,
                    'ulp_id'       => $batchUlp,
                    'penyulang_id' => $batchPen,
                    'file_name'    => !empty($originalFileName) ? basename($originalFileName) : ('Import_Asset_' . date('Ymd_His') . '.xlsx'),
                    'total_rows'   => count($rows) - 1,
                    'success_rows' => count($validBatch),
                    'failed_rows'  => $failed,
                    'imported_by'  => session()->get('user_id') ?? null,
                    'imported_at'  => date('Y-m-d H:i:s'),
                    'status'       => 'ACTIVE',
                ]);

                if ($batchId) {
                    foreach ($validBatch as &$vItem) {
                        $vItem['import_batch_id'] = $batchId;
                    }
                    unset($vItem);
                }

                $chunks = array_chunk($validBatch, 500);
                foreach ($chunks as $chunk) {
                    $db->table('assets')->insertBatch($chunk);
                }
                
                if ($db->transStatus() === false) {
                    $dbError = $db->error();
                    $dbErrorMsg = !empty($dbError['message']) ? $dbError['message'] : 'Transaction Rollback';
                    log_message('error', '[DynamicAssetImportService] Transaction Failed: ' . json_encode($dbError));
                    $db->transRollback();
                    return [
                        'success' => false,
                        'message' => 'Gagal menyimpan batch data ke database: ' . $dbErrorMsg,
                    ];
                }
                
                $db->transCommit();
                $inserted = count($validBatch);
            } catch (\Throwable $e) {
                $db->transRollback();
                log_message('error', '[DynamicAssetImportService] Transaction Exception: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Gagal melakukan insert ke database: ' . $e->getMessage(),
                ];
            }
        }

        $errorExcelPath = null;
        if (!empty($errorReport)) {
            $errorExcelPath = $this->createErrorReportSpreadsheet($errorReport);
        }

        return [
            'success'          => true,
            'inserted'         => $inserted,
            'failed'           => $failed,
            'total'            => $inserted + $failed,
            'errors'           => $errorReport,
            'error_excel_path' => $errorExcelPath,
            'message'          => "Import selesai: {$inserted} data baru berhasil di-generate & diimport, {$failed} data gagal/duplikat.",
        ];
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
        $sheet->setCellValue('D1', 'Alasan Error');

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
            log_message('error', '[DynamicAssetImportService] Gagal fetch ULP map: ' . $e->getMessage());
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
                    $map[strtolower(trim($p['nama_penyulang']))] = (int)$p['id'];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[DynamicAssetImportService] Gagal fetch Penyulang map: ' . $e->getMessage());
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

                // Legacy spreadsheet alias & equipment name mapping
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
