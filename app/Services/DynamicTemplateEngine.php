<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Dedicated Service for Building Dynamic Template Spreadsheets
 * Metadata-driven, Zero DB interaction.
 */
class DynamicTemplateEngine
{
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

    /**
     * Generate Dynamic Template Spreadsheet based on Metadata
     */
    public function generate(
        string $jenisAsset = 'Gardu', 
        string $namaUlp = 'Sidoarjo Kota', 
        string $namaUp3 = 'UP3 Sidoarjo',
        ?int $ulpId = null,
        ?int $penyulangId = null,
        ?string $namaPenyulang = null
    ): Spreadsheet {
        self::ensureComposerAutoload();

        $spreadsheet = new Spreadsheet();

        // ----------------------------------------------------
        // SHEET 1: DATA_ASSET
        // ----------------------------------------------------
        $sheetData = $spreadsheet->getActiveSheet();
        $sheetData->setTitle('DATA_ASSET');

        // Fetch headers from Metadata class
        $headers = AssetTemplateMetadata::getHeaderDefinition($jenisAsset);

        // Write Headers
        foreach ($headers as $colIndex => $h) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheetData->setCellValue($colLetter . '1', $h['label']);
        }

        // Header Styling (PLN Blue #005EB8)
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $headerRange   = 'A1:' . $lastColLetter . '1';

        $sheetData->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
        $sheetData->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF005EB8');
        $sheetData->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add 1 Sample Row
        $sampleData = AssetTemplateMetadata::getSampleRow($jenisAsset, $namaUp3, $namaUlp);
        foreach ($headers as $colIndex => $h) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $val       = $sampleData[$h['key']] ?? '';
            $sheetData->setCellValue($colLetter . '2', $val);
        }

        // Apply Borders & Auto-Width
        $sheetData->getStyle('A1:' . $lastColLetter . '2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range(1, count($headers)) as $colIndex) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheetData->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // ----------------------------------------------------
        // SHEET 2: SYSTEM_METADATA
        // ----------------------------------------------------
        $sheetMeta = $spreadsheet->createSheet();
        $sheetMeta->setTitle('SYSTEM_METADATA');
        $sheetMeta->setCellValue('A1', 'KEY');
        $sheetMeta->setCellValue('B1', 'VALUE');
        $sheetMeta->getStyle('A1:B1')->getFont()->setBold(true);

        $metaData = [
            ['UP3_ID', '1'],
            ['ULP_ID', (string)($ulpId ?? '')],
            ['PENYULANG_ID', (string)($penyulangId ?? '')],
            ['PENYULANG_NAME', (string)($namaPenyulang ?? '')],
            ['JENIS_ASSET', strtoupper($jenisAsset)],
        ];

        $mRow = 2;
        foreach ($metaData as $md) {
            $sheetMeta->setCellValue('A' . $mRow, $md[0]);
            $sheetMeta->setCellValue('B' . $mRow, $md[1]);
            $mRow++;
        }
        $sheetMeta->getColumnDimension('A')->setAutoSize(true);
        $sheetMeta->getColumnDimension('B')->setAutoSize(true);

        // ----------------------------------------------------
        // SHEET 3: PETUNJUK_PENGISIAN
        // ----------------------------------------------------
        $sheetInfo = $spreadsheet->createSheet();
        $sheetInfo->setTitle('PETUNJUK_PENGISIAN');
        $sheetInfo->setCellValue('A1', 'PETUNJUK PENGISIAN TEMPLATE IMPORT MASTER ASSET PLN');
        $sheetInfo->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $instructions = [
            ['1. Kolom UP3, ULP, Jenis Asset, dan Nama Asset', 'WAJIB DIISI.'],
            ['2. Kolom Section', 'OPSIONAL. Jika kosong, sistem tetap menerima import (status: Belum Dipetakan).'],
            ['3. Kolom Kode Asset', 'TIDAK PERLU DIISI / TIDAK ADA. Sistem akan men-generate Kode Asset otomatis secara unik.'],
            ['4. Validasi Duplikat', 'Sistem mengecek kombinasi UP3 + ULP + Jenis Asset + Nama Asset. Jika sudah ada di DB, data ditolak.'],
            ['5. Format Koordinat', 'Latitude dan Longitude menggunakan format desimal, contoh: -7.4478 dan 112.7183.'],
            ['6. Jenis Asset Terpilih', 'Template ini disesuaikan khusus untuk Jenis Asset: ' . strtoupper($jenisAsset)],
        ];

        $rowIdx = 3;
        foreach ($instructions as $inst) {
            $sheetInfo->setCellValue('A' . $rowIdx, $inst[0]);
            $sheetInfo->setCellValue('B' . $rowIdx, $inst[1]);
            $sheetInfo->getStyle('A' . $rowIdx)->getFont()->setBold(true);
            $rowIdx++;
        }
        $sheetInfo->getColumnDimension('A')->setAutoSize(true);
        $sheetInfo->getColumnDimension('B')->setAutoSize(true);

        // Re-activate first sheet
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Generate Clean CSV Content with UTF-8 BOM (\xEF\xBB\xBF) for MS Excel Windows Compatibility
     */
    public function generateCsv(
        string $jenisAsset = 'Gardu', 
        string $namaUlp = 'Sidoarjo Kota', 
        string $namaUp3 = 'UP3 Sidoarjo'
    ): string {
        $headers = AssetTemplateMetadata::getHeaderDefinition($jenisAsset);
        $sampleData = AssetTemplateMetadata::getSampleRow($jenisAsset, $namaUp3, $namaUlp);

        // UTF-8 BOM
        $csv = "\xEF\xBB\xBF";

        // Header Row
        $headerLabels = array_map(fn($h) => '"' . str_replace('"', '""', $h['label']) . '"', $headers);
        $csv .= implode(',', $headerLabels) . "\r\n";

        // Sample Data Row
        $sampleVals = array_map(function($h) use ($sampleData) {
            $val = $sampleData[$h['key']] ?? '';
            return '"' . str_replace('"', '""', $val) . '"';
        }, $headers);
        $csv .= implode(',', $sampleVals) . "\r\n";

        return $csv;
    }
}
