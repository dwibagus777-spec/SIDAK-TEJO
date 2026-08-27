<?php

namespace App\Controllers;

use App\Services\NetworkConfigurationService;
use App\Services\NetworkConfigurationIngestionService;
use App\Models\PenyulangModel;
use App\Models\NetworkConfigurationImportBatchModel;
use CodeIgniter\HTTP\ResponseInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Controller for Network Configuration Operational Activation (CR-06F)
 * Governed by 8 Hardening Gates (F1 - F8).
 */
class NetworkConfigurationController extends BaseController
{
    protected NetworkConfigurationService $ncService;
    protected NetworkConfigurationIngestionService $ingestService;
    protected PenyulangModel $penyulangModel;
    protected NetworkConfigurationImportBatchModel $batchModel;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->ncService     = new NetworkConfigurationService();
        $this->ingestService = new NetworkConfigurationIngestionService();
        $this->penyulangModel= new PenyulangModel();
        $this->batchModel    = new NetworkConfigurationImportBatchModel();
    }

    /**
     * Render Network Configuration Workspace.
     */
    public function index()
    {
        $penyulangId = $this->request->getGet('penyulang_id');
        $feeders     = $this->penyulangModel->where('status', 'AKTIF')->orderBy('nama_penyulang', 'ASC')->findAll();
        $coverage    = $this->ncService->getSectionCoverageMetrics($penyulangId ? (int)$penyulangId : null);

        $selectedFeeder = null;
        $sectionsWithConfig = [];

        if ($penyulangId) {
            $selectedFeeder = $this->penyulangModel->find((int)$penyulangId);
            if ($selectedFeeder) {
                $sectionsWithConfig = $this->ncService->getFeederActiveConfigurations((int)$penyulangId);
            }
        }

        $recentBatches = $this->batchModel->orderBy('id', 'DESC')->limit(10)->findAll();

        return view('network_configuration/index', [
            'title'              => 'Network Configuration Activation (CR-06F) | SIDAK TEJO',
            'active_menu'        => 'network_configuration',
            'coverage'           => $coverage,
            'feeders'            => $feeders,
            'selectedFeeder'     => $selectedFeeder,
            'sectionsWithConfig' => $sectionsWithConfig,
            'recentBatches'      => $recentBatches,
        ]);
    }

    /**
     * Handle Excel Upload Ingestion.
     */
    public function upload(): ResponseInterface
    {
        $file = $this->request->getFile('file_excel');
        if (!$file || !$file->isValid()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'File Excel tidak valid atau tidak ditemukan.',
            ]);
        }

        $allowedExts = ['xlsx', 'xls'];
        if (!in_array($file->getClientExtension(), $allowedExts, true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Format file harus berupa .xlsx atau .xls',
            ]);
        }

        $tempPath = $file->getTempName();
        $userId   = session()->get('user_id') ? (int)session()->get('user_id') : 1;

        try {
            $result = $this->ingestService->ingestFromExcel($tempPath, $userId);
            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat proses ingestion: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Download Standard CR-06F Contract v1.1.1 Template Excel.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();

        // -------------------------------------------------------------
        // Sheet 1: SECTION_CONFIGURATIONS
        // -------------------------------------------------------------
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('SECTION_CONFIGURATIONS');

        $headers1 = [
            'SECTION_REF',
            'KODE_ULP',
            'KODE_PENYULANG',
            'NAMA_SECTION',
            'IMPORT_ACTION',
            'CONFIGURATION_SOURCE',
            'CHANGE_REASON',
            'EFFECTIVE_FROM',
        ];
        $sheet1->fromArray([$headers1], null, 'A1');

        // Sample rows
        $sampleData1 = [
            ['SEC-CDR-001', '51301', 'CDR', 'Section A CANDRAMAS', 'ACTIVATE_NEW_VERSION', 'INITIAL_AUDIT', 'As-Built Line Audit 2026', date('Y-m-d H:i:s')],
            ['SEC-CDR-002', '51301', 'CDR', 'Section B CANDRAMAS', 'ACTIVATE_NEW_VERSION', 'INITIAL_AUDIT', 'As-Built Line Audit 2026', date('Y-m-d H:i:s')],
        ];
        $sheet1->fromArray($sampleData1, null, 'A2');
        $this->styleHeader($sheet1, 'A1:H1', '0D6EFD');

        // -------------------------------------------------------------
        // Sheet 2: CONDUCTOR_SEGMENTS
        // -------------------------------------------------------------
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('CONDUCTOR_SEGMENTS');

        $headers2 = [
            'SECTION_REF',
            'SEQUENCE_ORDER',
            'KODE_MATERIAL_KONDUKTOR',
            'PANJANG_METER',
            'START_NODE',
            'END_NODE',
            'SEGMENT_LABEL',
        ];
        $sheet2->fromArray([$headers2], null, 'A1');

        $sampleData2 = [
            ['SEC-CDR-001', 1, 'AAACS 240', 250.0, 'GI_CANDRAMAS', 'PB01', 'Saluran Keluar GI Trunk 1'],
            ['SEC-CDR-001', 2, 'AAAC 150', 450.0, 'PB01', 'TM1_CANDRAMAS', 'Overhead Main Feeder Segment 2'],
            ['SEC-CDR-002', 1, 'AAAC 150', 600.0, 'TM1_CANDRAMAS', 'TM8_CANDRAMAS', 'Overhead Section B Trunk'],
        ];
        $sheet2->fromArray($sampleData2, null, 'A2');
        $this->styleHeader($sheet2, 'A1:G1', '198754');

        // -------------------------------------------------------------
        // Sheet 3: NETWORK_ACCESSORIES
        // -------------------------------------------------------------
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('NETWORK_ACCESSORIES');

        $headers3 = [
            'SECTION_REF',
            'JENIS_AKSESORIS',
            'KODE_MATERIAL',
            'JUMLAH',
            'LOKASI_REFERENSI',
            'INITIAL_OBSERVED_CONDITION',
        ];
        $sheet3->fromArray([$headers3], null, 'A1');

        $sampleData3 = [
            ['SEC-CDR-001', 'GSW', 'MAT-ACC-GSW', 1, 'Span Tiang 1 s/d Tiang 12', 'GOOD'],
            ['SEC-CDR-001', 'LA', 'LA', 3, 'Portal Tiang PB01', 'GOOD'],
            ['SEC-CDR-002', 'CLD', 'CLD', 2, 'Tiang TM8 Percabangan', 'GOOD'],
        ];
        $sheet3->fromArray($sampleData3, null, 'A2');
        $this->styleHeader($sheet3, 'A1:F1', 'FD7E14');

        // Reset to first sheet
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'NETWORK_CONFIGURATION_TEMPLATE_v1.1.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function styleHeader($sheet, string $range, string $hexColor): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $hexColor],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        foreach (range('A', 'Z') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
