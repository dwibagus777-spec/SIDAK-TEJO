<?php

namespace App\Services;

use App\Models\AssetModel;
use App\Models\UlpModel;
use App\Models\PenyulangModel;
use App\Models\SectionModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AssetImportService
{
    private AssetModel $assetModel;
    private UlpModel $ulpModel;
    private PenyulangModel $penyulangModel;
    private SectionModel $sectionModel;

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
    }

    /**
     * Process Uploaded Excel File
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

        $validBatch  = [];
        $errorReport = [];
        $inserted    = 0;
        $failed      = 0;
        $now         = date('Y-m-d H:i:s');
        $rowIndex    = 0;

        foreach ($rows as $rowNum => $row) {
            $rowIndex++;
            if ($rowIndex === 1) {
                // Header row
                continue;
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

            // Validation rules
            $errors = [];

            if (empty($kodeAsset)) {
                $errors[] = 'Kode Asset wajib diisi.';
            } else {
                $codeUpper = strtoupper($kodeAsset);
                if (isset($existingCodes[$codeUpper])) {
                    $errors[] = "Kode Asset '{$kodeAsset}' sudah digunakan (harus unik).";
                }
            }

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
            }

            // Penyulang lookup
            $penyulangId = null;
            if (!empty($penyulangName)) {
                $pKey = strtolower($penyulangName);
                if (isset($penyulangMap[$pKey])) {
                    $penyulangId = $penyulangMap[$pKey];
                } else {
                    $errors[] = "Penyulang '{$penyulangName}' tidak ditemukan di database.";
                }
            }

            // Section lookup
            $sectionId = null;
            if (!empty($sectionName)) {
                $sKey = strtolower($sectionName);
                if (isset($sectionMap[$sKey])) {
                    $sectionId = $sectionMap[$sKey];
                } else {
                    $errors[] = "Section '{$sectionName}' tidak ditemukan di database.";
                }
            }

            // Status validation
            if (!in_array($status, ['NORMAL', 'BERMASALAH', 'CRITICAL'])) {
                $status = 'NORMAL';
            }

            if (!empty($errors)) {
                $failed++;
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
                'latitude'        => $latitude ?: null,
                'longitude'       => $longitude ?: null,
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

        // Perform batch insert
        if (!empty($validBatch)) {
            try {
                // Chunk insert by 500 rows for high performance & low memory
                $chunks = array_chunk($validBatch, 500);
                foreach ($chunks as $chunk) {
                    $db->table('assets')->insertBatch($chunk);
                }
                $inserted = count($validBatch);
            } catch (\Throwable $e) {
                log_message('error', '[AssetImportService] Batch insert gagal: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Gagal melakukan batch insert ke database: ' . $e->getMessage(),
                ];
            }
        }

        // Generate error report spreadsheet if any rows failed
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
            'message'          => "Import selesai: {$inserted} data berhasil diimport, {$failed} data gagal.",
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

        // Headers
        $sheet->setCellValue('A1', 'Nomor Baris Excel');
        $sheet->setCellValue('B1', 'Kode Asset');
        $sheet->setCellValue('C1', 'Nama Asset');
        $sheet->setCellValue('D1', 'Alasan Error');

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
                    $map[strtolower(trim($u['nama_ulp']))] = (int)$u['id'];
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
                    $map[strtolower(trim($p['nama_penyulang']))] = (int)$p['id'];
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
