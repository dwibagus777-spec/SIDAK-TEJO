<?php

namespace App\Controllers;

use App\Repositories\AssetRepository;
use App\Services\AssetImportService;
use App\Services\AssetExportService;
use App\Models\AssetModel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class AssetImportController extends BaseController
{
    private AssetRepository $repository;
    private AssetImportService $importService;
    private AssetExportService $exportService;

    public function __construct()
    {
        $this->repository    = new AssetRepository();
        $this->importService = new AssetImportService();
        $this->exportService = new AssetExportService();
    }

    /**
     * Download Excel Import Template
     */
    public function downloadTemplate(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $spreadsheet = $this->exportService->generateTemplateSpreadsheet();
            $filename    = 'Template_Import_Master_Asset_PLN.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit();
        } catch (\Throwable $e) {
            log_message('error', '[AssetImportController::downloadTemplate] Exception: ' . $e->getMessage());
            return redirect()->to(site_url('master-assets'))->with('error', 'Gagal mengunduh template Excel: ' . $e->getMessage());
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
        $result   = $this->importService->processImport($tempPath);

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

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit();
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

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\r\n");
            $writer->setSheetIndex(0);
            $writer->save('php://output');
            exit();
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
