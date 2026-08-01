<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AssetExportService
{
    private static function ensureComposerAutoload(): void
    {
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, false)) {
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
                if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, false)) {
                    break;
                }
            }
        }
    }

    /**
     * Create Template Excel Spreadsheet with Sample Row
     */
    public function generateTemplateSpreadsheet(): Spreadsheet
    {
        self::ensureComposerAutoload();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import Asset');

        $headers = [
            'Jenis Asset', 'Kode Asset', 'Nama Asset', 'ULP', 'Penyulang',
            'Section', 'Merk', 'Tipe', 'Nomor Seri', 'Kapasitas',
            'Tahun Instalasi', 'Alamat', 'Latitude', 'Longitude', 'Status'
        ];

        // Write Headers
        foreach ($headers as $colIndex => $header) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($columnLetter . '1', $header);
        }

        // Header Styling
        $sheet->getStyle('A1:O1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
        $sheet->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF005EB8'); // PLN Blue
        $sheet->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add 1 Sample Row
        $sampleData = [
            'Gardu', 'AST-GRD-Sample-01', 'Gardu SDJ-045 Sidoarjo', 'Sidoarjo Kota', 'BY PASS',
            'LBSM SIDOMULYO', 'Schneider Electric', 'Portal 20KV', 'SN-2026-001', '250 kVA',
            '2021', 'Jl. Raya Pahlawan No. 45, Sidoarjo', '-7.4478', '112.7183', 'NORMAL'
        ];

        foreach ($sampleData as $colIndex => $val) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($columnLetter . '2', $val);
        }

        // Apply Borders
        $sheet->getStyle('A1:O2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range(1, 15) as $colIndex) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Build Export Spreadsheet for Assets
     */
    public function buildAssetSpreadsheet(array $assets): Spreadsheet
    {
        self::ensureComposerAutoload();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Master Asset PLN');

        $headers = [
            'No', 'Kode Asset', 'Nama Asset', 'Jenis Asset', 'ULP',
            'Penyulang', 'Section', 'Merk', 'Tipe', 'Nomor Seri',
            'Kapasitas', 'Tahun Instalasi', 'Alamat', 'Latitude', 'Longitude', 'Status'
        ];

        foreach ($headers as $colIndex => $header) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($columnLetter . '1', $header);
        }

        $sheet->getStyle('A1:P1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
        $sheet->getStyle('A1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF005EB8');
        $sheet->getStyle('A1:P1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNum = 2;
        foreach ($assets as $idx => $a) {
            $sheet->setCellValue('A' . $rowNum, $idx + 1);
            $sheet->setCellValue('B' . $rowNum, $a['kode_asset'] ?? '-');
            $sheet->setCellValue('C' . $rowNum, $a['nama_asset'] ?? '-');
            $sheet->setCellValue('D' . $rowNum, $a['jenis_asset'] ?? '-');
            $sheet->setCellValue('E' . $rowNum, $a['nama_ulp'] ?? '-');
            $sheet->setCellValue('F' . $rowNum, $a['nama_penyulang'] ?? '-');
            $sheet->setCellValue('G' . $rowNum, $a['nama_section'] ?? '-');
            $sheet->setCellValue('H' . $rowNum, $a['merk'] ?? '-');
            $sheet->setCellValue('I' . $rowNum, $a['type'] ?? '-');
            $sheet->setCellValue('J' . $rowNum, $a['nomor_seri'] ?? '-');
            $sheet->setCellValue('K' . $rowNum, $a['kapasitas'] ?? '-');
            $sheet->setCellValue('L' . $rowNum, $a['tahun_instalasi'] ?? '-');
            $sheet->setCellValue('M' . $rowNum, $a['lokasi'] ?? '-');
            $sheet->setCellValue('N' . $rowNum, $a['latitude'] ?? '-');
            $sheet->setCellValue('O' . $rowNum, $a['longitude'] ?? '-');
            $sheet->setCellValue('P' . $rowNum, strtoupper($a['status'] ?? 'NORMAL'));
            $rowNum++;
        }

        $lastRow = max(2, $rowNum - 1);
        $sheet->getStyle('A1:P' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range(1, 16) as $colIndex) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
