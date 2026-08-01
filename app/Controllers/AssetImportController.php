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
     * Download Excel Import Template
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
            $jenisAsset = $this->request->getGet('jenis_asset');
            $ulpId      = $this->request->getGet('ulp_id');
            $up3        = $this->request->getGet('up3') ?: 'UP3 Sidoarjo';

            if (!empty($jenisAsset)) {
                // Dynamic Template Flow (New)
                $namaUlp = 'Semua ULP';
                if (!empty($ulpId)) {
                    $ulpModel = new \App\Models\UlpModel();
                    $ulpData  = $ulpModel->find($ulpId);
                    if ($ulpData && !empty($ulpData['nama_ulp'])) {
                        $namaUlp = $ulpData['nama_ulp'];
                    }
                }

                $dynamicEngine = new \App\Services\DynamicTemplateEngine();
                $spreadsheet    = $dynamicEngine->generate($jenisAsset, $namaUlp, $up3);
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
                'error'        => true,
                'message'      => $e->getMessage(),
                'class'        => get_class($e),
                'file'         => $e->getFile(),
                'line'         => $e->getLine(),
                'trace'        => array_map(function ($t) {
                    return [
                        'file'     => $t['file'] ?? '[internal]',
                        'line'     => $t['line'] ?? 0,
                        'function' => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''),
                    ];
                }, $e->getTrace()),
                'class_exists_spreadsheet' => class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, true),
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

        return view('assets/import', [
            'title' => 'Import Master Asset PLN',
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
                    . '|ext_in[file_excel,xlsx,xls]',
                'errors' => [
                    'uploaded' => 'Silakan pilih berkas Excel terlebih dahulu.',
                    'max_size' => 'Ukuran berkas maksimal adalah 10 MB.',
                    'ext_in'   => 'Format berkas harus berupa .xlsx atau .xls.',
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
