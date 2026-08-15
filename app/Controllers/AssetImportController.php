<?php

namespace App\Controllers;

use App\Repositories\AssetRepository;
use App\Services\AssetImportService;
use App\Services\AssetExportService;
use App\Services\DynamicAssetImportService;
use App\Services\DynamicTemplateEngine;
use App\Models\AssetModel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class AssetImportController extends BaseController
{
    private AssetRepository $repository;
    private AssetImportService $importService;
    private AssetExportService $exportService;
    private DynamicAssetImportService $dynamicImportService;

    public function __construct()
    {
        $this->repository           = new AssetRepository();
        $this->importService        = new AssetImportService();
        $this->exportService        = new AssetExportService();
        $this->dynamicImportService = new DynamicAssetImportService();
    }

    /**
     * Factual Production Vendor Diagnostic Endpoint: /master-assets/debug-vendor
     */
    public function debugVendor(): \CodeIgniter\HTTP\ResponseInterface
    {
        $fcPath   = defined('FCPATH') ? FCPATH : __DIR__ . '/../../public/';
        $rootPath = defined('ROOTPATH') ? ROOTPATH : realpath($fcPath . '../') . '/';

        $vendorDir   = realpath($rootPath . 'vendor');
        $autoloadFile= realpath($rootPath . 'vendor/autoload.php');
        $psr4File    = realpath($rootPath . 'vendor/composer/autoload_psr4.php');
        $spreadsheetFile = realpath($rootPath . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php');

        $physicalChecks = [
            'vendor/' => is_dir($rootPath . 'vendor') ? 'FOUND' : 'NOT FOUND',
            'vendor/autoload.php' => is_file($rootPath . 'vendor/autoload.php') ? 'FOUND' : 'NOT FOUND',
            'vendor/composer/autoload_psr4.php' => is_file($rootPath . 'vendor/composer/autoload_psr4.php') ? 'FOUND' : 'NOT FOUND',
            'vendor/phpoffice/' => is_dir($rootPath . 'vendor/phpoffice') ? 'FOUND' : 'NOT FOUND',
            'vendor/phpoffice/phpspreadsheet/' => is_dir($rootPath . 'vendor/phpoffice/phpspreadsheet') ? 'FOUND' : 'NOT FOUND',
            'vendor/phpoffice/phpspreadsheet/src/' => is_dir($rootPath . 'vendor/phpoffice/phpspreadsheet/src') ? 'FOUND' : 'NOT FOUND',
            'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/' => is_dir($rootPath . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet') ? 'FOUND' : 'NOT FOUND',
            'Spreadsheet.php' => is_file($rootPath . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php') ? 'FOUND' : 'NOT FOUND',
        ];

        $psr4Content = is_file($psr4File) ? file_get_contents($psr4File) : '';
        $isPsr4Registered = str_contains($psr4Content, 'PhpOffice\\PhpSpreadsheet');

        $composerLockFile = realpath($rootPath . 'composer.lock');
        $phpspreadsheetVersionInLock = 'NOT FOUND';
        if (is_file($composerLockFile)) {
            $lockContent = file_get_contents($composerLockFile);
            $lockData = json_decode($lockContent, true);
            if (is_array($lockData) && !empty($lockData['packages'])) {
                foreach ($lockData['packages'] as $pkg) {
                    if (isset($pkg['name']) && $pkg['name'] === 'phpoffice/phpspreadsheet') {
                        $phpspreadsheetVersionInLock = $pkg['version'] ?? 'INSTALLED';
                        break;
                    }
                }
            }
        }

        $composerJsonFile = realpath($rootPath . 'composer.json');
        $phpspreadsheetVersionInJson = 'NOT FOUND';
        if (is_file($composerJsonFile)) {
            $jsonContent = file_get_contents($composerJsonFile);
            $jsonData = json_decode($jsonContent, true);
            if (isset($jsonData['require']['phpoffice/phpspreadsheet'])) {
                $phpspreadsheetVersionInJson = $jsonData['require']['phpoffice/phpspreadsheet'];
            }
        }

        return $this->response->setStatusCode(200)->setJSON([
            'vendor_exists'                => is_dir($rootPath . 'vendor'),
            'autoload_exists'              => is_file($rootPath . 'vendor/autoload.php'),
            'spreadsheet_exists'           => is_file($rootPath . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php'),
            'autoload_registered'          => $isPsr4Registered,
            'composer_packages'            => [
                'phpoffice/phpspreadsheet_in_json' => $phpspreadsheetVersionInJson,
                'phpoffice/phpspreadsheet_in_lock' => $phpspreadsheetVersionInLock,
            ],
            'vendor_path'                  => $rootPath . 'vendor',
            'realpath_vendor'              => $vendorDir,
            'realpath_autoload'            => $autoloadFile,
            'physical_verification'        => $physicalChecks,
            'class_exists_spreadsheet'     => class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, true),
        ]);
    }

    /**
     * Comprehensive Diagnostic Endpoint: /master-assets/debug-runtime
     */
    public function debugRuntime(): \CodeIgniter\HTTP\ResponseInterface
    {
        $compPath = defined('COMPOSER_PATH') ? COMPOSER_PATH : null;
        $rootPath = defined('ROOTPATH') ? ROOTPATH : null;
        $fcPath   = defined('FCPATH') ? FCPATH : null;
        $appPath  = defined('APPPATH') ? APPPATH : null;

        $targetSpreadsheetFile = 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php';

        $candidates = [
            'COMPOSER_PATH'   => $compPath,
            'ROOTPATH_vendor' => $rootPath ? $rootPath . 'vendor/autoload.php' : null,
            'APPPATH_parent'  => $appPath ? realpath($appPath . '/../vendor/autoload.php') : null,
            'FCPATH_parent'   => $fcPath ? realpath($fcPath . '/../vendor/autoload.php') : null,
            'DIR_parent'      => realpath(__DIR__ . '/../../vendor/autoload.php'),
        ];

        $candidateResults = [];
        foreach ($candidates as $key => $path) {
            $candidateResults[$key] = [
                'raw_path'   => $path,
                'realpath'   => $path ? realpath($path) : false,
                'file_exists'=> $path ? file_exists($path) : false,
                'is_file'    => $path ? is_file($path) : false,
            ];
        }

        $spreadSheetFileCandidates = [
            'ROOTPATH_src' => $rootPath ? $rootPath . $targetSpreadsheetFile : null,
            'FCPATH_src'   => $fcPath ? realpath($fcPath . '/../' . $targetSpreadsheetFile) : null,
            'APPPATH_src'  => $appPath ? realpath($appPath . '/../' . $targetSpreadsheetFile) : null,
            'DIR_src'      => realpath(__DIR__ . '/../../' . $targetSpreadsheetFile),
        ];

        $spreadsheetFileResults = [];
        foreach ($spreadSheetFileCandidates as $key => $path) {
            $spreadsheetFileResults[$key] = [
                'raw_path'   => $path,
                'realpath'   => $path ? realpath($path) : false,
                'file_exists'=> $path ? file_exists($path) : false,
            ];
        }

        $autoloadFuncs = [];
        foreach (spl_autoload_functions() as $f) {
            if (is_array($f)) {
                $autoloadFuncs[] = (is_object($f[0]) ? get_class($f[0]) : $f[0]) . '::' . $f[1];
            } else {
                $autoloadFuncs[] = (string)$f;
            }
        }

        return $this->response->setStatusCode(200)->setJSON([
            'timestamp'                   => date('Y-m-d H:i:s'),
            'PHP_VERSION'                 => PHP_VERSION,
            'ROOTPATH'                    => $rootPath,
            'FCPATH'                      => $fcPath,
            'APPPATH'                     => $appPath,
            'COMPOSER_PATH'               => $compPath,
            'composer_candidates'         => $candidateResults,
            'spreadsheet_file_candidates' => $spreadsheetFileResults,
            'class_exists_true'           => class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, true),
            'class_exists_false'          => class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, false),
            'spl_autoload_functions'      => $autoloadFuncs,
            'include_path'                => get_include_path(),
        ]);
    }

    /**
     * Read-Only Production Diagnostic Endpoint for Photo References
     * GET /master-assets/debug-foto/{id}
     */
    public function debugFoto(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db = \Config\Database::connect();
        
        $dbName = 'unknown';
        try {
            $dbRow = $db->query("SELECT DATABASE() AS db_name")->getRowArray();
            $dbName = $dbRow['db_name'] ?? 'unknown';
        } catch (\Throwable $e) {}

        $temuan = $db->table('temuan')->where('id', $id)->get()->getRowArray();
        
        $photoForensic = [];
        if ($temuan && !empty($temuan['foto'])) {
            $photos = json_decode((string)$temuan['foto'], true);
            if (!is_array($photos)) {
                $photos = array_filter(array_map('trim', explode(',', (string)$temuan['foto'])));
            }

            foreach ($photos as $p) {
                $cleanName = basename(trim((string)$p));
                $fcPathFile = FCPATH . 'foto/' . $cleanName;
                $parentPathFile = rtrim(dirname(FCPATH), '/\\') . '/foto/' . $cleanName;

                $photoForensic[] = [
                    'db_reference'     => $p,
                    'clean_name'       => $cleanName,
                    'fcpath_target'    => $fcPathFile,
                    'fcpath_exists'    => file_exists($fcPathFile),
                    'parent_target'    => $parentPathFile,
                    'parent_exists'    => file_exists($parentPathFile),
                    'filesize_bytes'   => file_exists($fcPathFile) ? filesize($fcPathFile) : (file_exists($parentPathFile) ? filesize($parentPathFile) : 0),
                    'resolved_url'     => get_photo_url($p, $temuan['foto_path'] ?? 'foto/', 'full'),
                ];
            }
        }

        $historyForensic = [];
        if ($db->tableExists('tindak_lanjut')) {
            $histRows = $db->table('tindak_lanjut')->where('temuan_id', $id)->orderBy('id', 'ASC')->get()->getResultArray();
            foreach ($histRows as $i => $h) {
                $photosCheck = [];
                foreach (['foto_sebelum', 'foto_proses', 'foto_sesudah'] as $k) {
                    $ref = $h[$k] ?? null;
                    $clean = !empty($ref) ? basename($ref) : null;
                    $fcExist = !empty($clean) ? file_exists(FCPATH . 'foto/' . $clean) : false;
                    $photosCheck[$k] = [
                        'db_reference' => $ref,
                        'clean_name'   => $clean,
                        'file_exists'  => $fcExist,
                        'filesize'     => $fcExist ? filesize(FCPATH . 'foto/' . $clean) : 0,
                        'url'          => !empty($ref) ? get_photo_url($ref) : null,
                    ];
                }

                $historyForensic[] = [
                    'entry_index' => $i + 1,
                    'history_id'  => $h['id'],
                    'tanggal'     => $h['tanggal'] ?? null,
                    'status'      => $h['status'] ?? null,
                    'komentar'    => $h['komentar'] ?? null,
                    'photos'      => $photosCheck,
                ];
            }
        }

        return $this->response->setJSON([
            'diagnostic_timestamp' => date('Y-m-d H:i:s'),
            'current_database'     => $dbName,
            'temuan_id'            => $id,
            'temuan_exists'        => !empty($temuan),
            'temuan_raw'           => $temuan ? [
                'id'            => $temuan['id'],
                'nomor_temuan'  => $temuan['nomor_temuan'],
                'foto_raw'      => $temuan['foto'],
                'foto_path'     => $temuan['foto_path'] ?? null,
                'detail_temuan' => $temuan['detail_temuan'],
                'status'        => $temuan['status'],
                'created_at'    => $temuan['created_at'],
                'updated_at'    => $temuan['updated_at'],
            ] : null,
            'main_photo_forensic'  => $photoForensic,
            'history_forensic'     => $historyForensic,
        ]);
    }

    /**
     * Download Excel Import Template
     */
    /**
     * Reusable Endpoint: Get Penyulangs by ULP ID
     * GET /master-assets/penyulang-by-ulp/{ulp_id}
     */
    public function getPenyulangByUlp(int $ulpId): \CodeIgniter\HTTP\ResponseInterface
    {
        $penyulangModel = new \App\Models\PenyulangModel();
        $penyulangs = $penyulangModel
            ->where('ulp_id', $ulpId)
            ->where('status', 'AKTIF')
            ->orderBy('nama_penyulang', 'ASC')
            ->findAll();

        return $this->response->setJSON($penyulangs ?: []);
    }

    /**
     * Download Excel/CSV Import Template with Chained Validation
     */
    public function downloadTemplate(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($this->request->getGet('debug') === '1') {
            $spreadsheetClass = \PhpOffice\PhpSpreadsheet\Spreadsheet::class;
            return $this->response->setJSON([
                'composer_path_defined' => defined('COMPOSER_PATH') ? COMPOSER_PATH : null,
                'composer_path_realpath' => defined('COMPOSER_PATH') ? realpath(COMPOSER_PATH) : null,
                'composer_path_exists' => defined('COMPOSER_PATH') ? file_exists(COMPOSER_PATH) : false,
                'rootpath' => defined('ROOTPATH') ? ROOTPATH : null,
                'fcpath' => defined('FCPATH') ? FCPATH : null,
                'apppath' => defined('APPPATH') ? APPPATH : null,
                'class_spreadsheet_exists' => class_exists($spreadsheetClass, true),
                'vendor_phpoffice_dir_exists' => is_dir(FCPATH . '../vendor/phpoffice/phpspreadsheet'),
                'vendor_autoload_fcpath_parent' => is_file(realpath(FCPATH . '../vendor/autoload.php')),
                'vendor_autoload_apppath_parent' => is_file(realpath(APPPATH . '../vendor/autoload.php')),
            ]);
        }

        try {
            $jenisAsset  = $this->request->getGet('jenis_asset');
            $ulpId       = $this->request->getGet('ulp_id');
            $penyulangId = $this->request->getGet('penyulang_id');
            $format      = strtolower((string)($this->request->getGet('format') ?: 'xlsx'));
            $up3         = $this->request->getGet('up3') ?: 'UP3 Sidoarjo';

            // Check if request is using new Context-Aware mode (both ulp_id and penyulang_id provided)
            $isContextMode = (!empty($ulpId) || !empty($penyulangId));

            if ($isContextMode) {
                // --- STRICT CHAINED SERVER VALIDATION ---
                // 1. Check ULP exists
                $ulpModel = new \App\Models\UlpModel();
                $ulpData  = !empty($ulpId) ? $ulpModel->find($ulpId) : null;
                if (!$ulpData) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'status'  => 422,
                        'message' => 'ULP yang dipilih tidak ditemukan dalam sistem.'
                    ]);
                }

                // 2. Check Penyulang exists and belongs to ULP
                $penyulangModel = new \App\Models\PenyulangModel();
                $penyulangData  = !empty($penyulangId) ? $penyulangModel->find($penyulangId) : null;
                if (!$penyulangData) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'status'  => 422,
                        'message' => 'Penyulang yang dipilih tidak ditemukan dalam sistem.'
                    ]);
                }

                if ((int)$penyulangData['ulp_id'] !== (int)$ulpId) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'status'  => 422,
                        'message' => 'Penyulang ' . $penyulangData['nama_penyulang'] . ' tidak termasuk dalam ULP ' . $ulpData['nama_ulp'] . '.'
                    ]);
                }

                // 3. Check Format
                if (!in_array($format, ['xlsx', 'csv'], true)) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'status'  => 422,
                        'message' => 'Format template harus berupa xlsx atau csv.'
                    ]);
                }

                $namaUlp       = $ulpData['nama_ulp'];
                $namaPenyulang = $penyulangData['nama_penyulang'];
                $sanitizedPenyulang = preg_replace('/[^a-zA-Z0-9_]/', '_', $namaPenyulang);
                $sanitizedJenis     = preg_replace('/[^a-zA-Z0-9_]/', '_', $jenisAsset ?: 'Asset');

                $dynamicEngine = new \App\Services\DynamicTemplateEngine();

                if ($format === 'csv') {
                    $csvContent = $dynamicEngine->generateCsv(
                        $jenisAsset ?: 'Gardu',
                        $namaUlp,
                        $up3,
                        (int)$ulpId,
                        (int)$penyulangId,
                        $namaPenyulang
                    );
                    $filename   = 'Template_Import_' . $sanitizedJenis . '_' . $sanitizedPenyulang . '.csv';

                    return $this->response
                        ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                        ->setHeader('Content-Disposition', 'attachment;filename="' . $filename . '"')
                        ->setHeader('Cache-Control', 'max-age=0')
                        ->setBody($csvContent);
                } else {
                    $spreadsheet = $dynamicEngine->generate(
                        $jenisAsset ?: 'Gardu',
                        $namaUlp,
                        $up3,
                        (int)$ulpId,
                        (int)$penyulangId,
                        $namaPenyulang
                    );
                    $filename = 'Template_Import_' . $sanitizedJenis . '_' . $sanitizedPenyulang . '.xlsx';
                }
            } else if (!empty($jenisAsset)) {
                // Dynamic Template Flow (Legacy Partial Request)
                $dynamicEngine = new \App\Services\DynamicTemplateEngine();
                $spreadsheet    = $dynamicEngine->generate($jenisAsset, 'Semua ULP', $up3);
                $filename       = 'Template_Import_Asset_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $jenisAsset) . '.xlsx';
            } else {
                // Static Template Flow (Old / Backward Compatible)
                $spreadsheet = $this->exportService->generateTemplateSpreadsheet();
                $filename    = 'Template_Import_Master_Asset_PLN.xlsx';
            }

            ob_start();
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $content = ob_get_clean();

            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment;filename="' . $filename . '"')
                ->setHeader('Cache-Control', 'max-age=0')
                ->setBody($content);
        } catch (\Throwable $e) {
            log_message('error', '[downloadTemplate] ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $this->response->setStatusCode(500)->setJSON([
                'error'   => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show Excel Import Form View
     */
    public function importView(): string
    {
        // Ensure assets table exists in DB when visiting import
        new AssetModel();

        $ulpModel = new \App\Models\UlpModel();

        return view('assets/import', [
            'title' => 'Import Master Asset PLN',
            'ulps'  => $ulpModel->where('status', 'AKTIF')->findAll(),
        ]);
    }

    /**
     * Process Uploaded Excel Import File
     */
    public function processImport()
    {
        // Validation: Max 10MB, xlsx/xls
        $validationRule = [
            'file_excel' => [
                'label' => 'Berkas Excel',
                'rules' => 'uploaded[file_excel]'
                    . '|max_size[file_excel,10240]'
                    . '|ext_in[file_excel,xlsx,xls,csv]',
                'errors' => [
                    'uploaded' => 'Silakan pilih berkas Excel atau CSV terlebih dahulu.',
                    'max_size' => 'Ukuran berkas maksimal adalah 10 MB.',
                    'ext_in'   => 'Format berkas harus berupa .xlsx, .xls, atau .csv.',
                ]
            ]
        ];

        if (!$this->validate($validationRule)) {
            return redirect()->to(site_url('master-assets/import'))->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $file = $this->request->getFile('file_excel');
        if (!$file->isValid() || $file->hasMoved()) {
            return redirect()->to(site_url('master-assets/import'))->with('error', 'Berkas yang diunggah tidak valid.');
        }

        $tempPath = $file->getTempName();

        // Read spreadsheet header row for auto-detection
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPath);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            log_message('error', '[AssetImportController::processImport] Gagal membaca Excel: ' . $e->getMessage());
            return redirect()->to(site_url('master-assets/import'))->with('error', 'Format berkas Excel tidak dapat dibaca: ' . $e->getMessage());
        }

        if (count($rows) <= 1) {
            return redirect()->to(site_url('master-assets/import'))->with('error', 'Berkas Excel kosong atau hanya berisi baris header.');
        }

        // Inspection: Check if header row 1 contains "Kode Asset"
        $headerRow = $rows[1] ?? [];
        $hasKodeAssetColumn = false;

        foreach ($headerRow as $cellVal) {
            if (strcasecmp(trim((string)$cellVal), 'Kode Asset') === 0) {
                $hasKodeAssetColumn = true;
                break;
            }
        }

        if ($hasKodeAssetColumn) {
            // Old Template Flow -> Delegate to AssetImportService
            $result = $this->importService->processImport($tempPath);
        } else {
            // New Dynamic Template Flow -> Delegate to DynamicAssetImportService
            $result = $this->dynamicImportService->processImport($rows);
        }

        if (!$result['success']) {
            return redirect()->to(site_url('master-assets/import'))->with('error', $result['message']);
        }

        // Store result summary in session flash
        session()->setFlashdata('import_summary', $result);

        return redirect()->to(site_url('master-assets/import'))->with('success', $result['message']);
    }

    /**
     * Download Error Report Excel File
     */
    public function downloadErrorReport(): \CodeIgniter\HTTP\ResponseInterface
    {
        $path = $this->request->getGet('file');
        if (!empty($path) && file_exists($path) && str_starts_with(realpath($path), realpath(WRITEPATH . 'uploads'))) {
            return $this->response->download($path, null)->setFileName('Laporan_Error_Import_Asset.xlsx');
        }
        return redirect()->to(site_url('master-assets/import'))->with('error', 'Berkas laporan error tidak ditemukan.');
    }

    /**
     * Export Filtered Assets to Excel (.xlsx)
     */
    public function exportExcel()
    {
        try {
            $filters    = $this->getFilterParameters();
            $ulpIdFilter= $this->getUlpRoleFilter();
            $assets     = $this->repository->getFilteredAssets($filters, $ulpIdFilter);

            $spreadsheet = $this->exportService->buildAssetSpreadsheet($assets);
            $filename    = 'Master_Asset_PLN_' . date('Ymd_His') . '.xlsx';

            ob_start();
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $content = ob_get_clean();

            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment;filename="' . $filename . '"')
                ->setHeader('Cache-Control', 'max-age=0')
                ->setBody($content);
        } catch (\Throwable $e) {
            log_message('error', '[AssetImportController::exportExcel] Exception: ' . $e->getMessage());
            return redirect()->to(site_url('master-assets'))->with('error', 'Gagal mengekspor data ke Excel: ' . $e->getMessage());
        }
    }

    /**
     * Export Filtered Assets to CSV (.csv)
     */
    public function exportCsv()
    {
        try {
            $filters    = $this->getFilterParameters();
            $ulpIdFilter= $this->getUlpRoleFilter();
            $assets     = $this->repository->getFilteredAssets($filters, $ulpIdFilter);

            $spreadsheet = $this->exportService->buildAssetSpreadsheet($assets);
            $filename    = 'Master_Asset_PLN_' . date('Ymd_His') . '.csv';

            ob_start();
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\r\n");
            $writer->setSheetIndex(0);
            $writer->save('php://output');
            $content = ob_get_clean();

            return $this->response
                ->setHeader('Content-Type', 'text/csv')
                ->setHeader('Content-Disposition', 'attachment;filename="' . $filename . '"')
                ->setHeader('Cache-Control', 'max-age=0')
                ->setBody($content);
        } catch (\Throwable $e) {
            log_message('error', '[AssetImportController::exportCsv] Exception: ' . $e->getMessage());
            return redirect()->to(site_url('master-assets'))->with('error', 'Gagal mengekspor data ke CSV: ' . $e->getMessage());
        }
    }

    /**
     * Export Filtered Assets to PDF Report
     */
    public function exportPdf(): string
    {
        $filters    = $this->getFilterParameters();
        $ulpIdFilter= $this->getUlpRoleFilter();
        $assets     = $this->repository->getFilteredAssets($filters, $ulpIdFilter);

        return view('assets/print_pdf', [
            'assets'   => $assets,
            'filters'  => $filters,
            'printDate'=> date('d-m-Y H:i:s'),
        ]);
    }

    private function getFilterParameters(): array
    {
        return [
            'ulp_id'      => $this->request->getGet('ulp_id'),
            'penyulang_id'=> $this->request->getGet('penyulang_id'),
            'section_id'  => $this->request->getGet('section_id'),
            'jenis_asset' => $this->request->getGet('jenis_asset'),
            'status'      => $this->request->getGet('status'),
            'search'      => $this->request->getGet('search'),
        ];
    }

    private function getUlpRoleFilter(): ?int
    {
        $session   = session();
        $role      = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            return (int)$userUlpId;
        }

        return null;
    }
}
