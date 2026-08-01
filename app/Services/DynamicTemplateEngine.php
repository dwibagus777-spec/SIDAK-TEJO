<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
     * Generate Dynamic Template Spreadsheet based on Asset Type
     */
    public function generate(string $jenisAsset = 'Gardu', string $namaUlp = 'Sidoarjo Kota', string $namaUp3 = 'UP3 Sidoarjo'): Spreadsheet
    {
        self::ensureComposerAutoload();

        $spreadsheet = new Spreadsheet();

        // ----------------------------------------------------
        // SHEET 1: Data Asset
        // ----------------------------------------------------
        $sheetData = $spreadsheet->getActiveSheet();
        $sheetData->setTitle('Data Asset');

        $headers = $this->getHeaderDefinition($jenisAsset);

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
        $sampleData = $this->getSampleRowData($jenisAsset, $namaUp3, $namaUlp);
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
        // SHEET 2: Petunjuk Pengisian
        // ----------------------------------------------------
        $sheetInfo = $spreadsheet->createSheet();
        $sheetInfo->setTitle('Petunjuk Pengisian');
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
     * Get Header Definition based on Asset Type
     */
    private function getHeaderDefinition(string $jenis): array
    {
        $jenisUpper = strtoupper($jenis);

        // Mandatory Base Fields for All Asset Types
        $baseHeader = [
            ['key' => 'up3',         'label' => 'UP3'],
            ['key' => 'ulp',         'label' => 'ULP'],
            ['key' => 'jenis_asset', 'label' => 'Jenis Asset'],
        ];

        $specificHeader = match ($jenisUpper) {
            'GARDU' => [
                ['key' => 'nama_asset',      'label' => 'Nama Gardu'],
                ['key' => 'penyulang',       'label' => 'Penyulang'],
                ['key' => 'merk',            'label' => 'Merk'],
                ['key' => 'kapasitas',       'label' => 'Kapasitas (kVA)'],
                ['key' => 'tahun_instalasi', 'label' => 'Tahun Instalasi'],
                ['key' => 'lokasi',          'label' => 'Alamat / Lokasi'],
                ['key' => 'latitude',        'label' => 'Latitude'],
                ['key' => 'longitude',       'label' => 'Longitude'],
                ['key' => 'section',         'label' => 'Section (Opsional)'],
            ],
            'TIANG' => [
                ['key' => 'nama_asset',      'label' => 'Nama / No Tiang'],
                ['key' => 'penyulang',       'label' => 'Penyulang'],
                ['key' => 'type',            'label' => 'Material Tiang'],
                ['key' => 'kapasitas',       'label' => 'Tinggi Tiang (M)'],
                ['key' => 'lokasi',          'label' => 'Alamat / Lokasi'],
                ['key' => 'latitude',        'label' => 'Latitude'],
                ['key' => 'longitude',       'label' => 'Longitude'],
                ['key' => 'section',         'label' => 'Section (Opsional)'],
            ],
            'TRAFO' => [
                ['key' => 'nama_asset',      'label' => 'Nama Trafo'],
                ['key' => 'penyulang',       'label' => 'Penyulang'],
                ['key' => 'merk',            'label' => 'Merk Trafo'],
                ['key' => 'type',            'label' => 'Tipe Trafo'],
                ['key' => 'nomor_seri',      'label' => 'Nomor Seri'],
                ['key' => 'kapasitas',       'label' => 'Kapasitas (kVA)'],
                ['key' => 'tahun_instalasi', 'label' => 'Tahun Instalasi'],
                ['key' => 'lokasi',          'label' => 'Alamat / Lokasi'],
                ['key' => 'latitude',        'label' => 'Latitude'],
                ['key' => 'longitude',       'label' => 'Longitude'],
                ['key' => 'section',         'label' => 'Section (Opsional)'],
            ],
            'LBS', 'RECLOSER', 'KUBIKEL', 'SECTION' => [
                ['key' => 'nama_asset',      'label' => 'Nama ' . ucfirst(strtolower($jenis))],
                ['key' => 'penyulang',       'label' => 'Penyulang'],
                ['key' => 'merk',            'label' => 'Merk'],
                ['key' => 'type',            'label' => 'Tipe'],
                ['key' => 'nomor_seri',      'label' => 'Nomor Seri'],
                ['key' => 'kapasitas',       'label' => 'Kapasitas'],
                ['key' => 'lokasi',          'label' => 'Alamat / Lokasi'],
                ['key' => 'latitude',        'label' => 'Latitude'],
                ['key' => 'longitude',       'label' => 'Longitude'],
                ['key' => 'section',         'label' => 'Section (Opsional)'],
            ],
            default => [
                ['key' => 'nama_asset',      'label' => 'Nama Asset'],
                ['key' => 'penyulang',       'label' => 'Penyulang'],
                ['key' => 'merk',            'label' => 'Merk'],
                ['key' => 'type',            'label' => 'Tipe'],
                ['key' => 'nomor_seri',      'label' => 'Nomor Seri'],
                ['key' => 'kapasitas',       'label' => 'Kapasitas'],
                ['key' => 'tahun_instalasi', 'label' => 'Tahun Instalasi'],
                ['key' => 'lokasi',          'label' => 'Alamat / Lokasi'],
                ['key' => 'latitude',        'label' => 'Latitude'],
                ['key' => 'longitude',       'label' => 'Longitude'],
                ['key' => 'section',         'label' => 'Section (Opsional)'],
            ],
        };

        return array_merge($baseHeader, $specificHeader);
    }

    /**
     * Get Sample Row Data per Asset Type
     */
    private function getSampleRowData(string $jenis, string $up3, string $ulp): array
    {
        return [
            'up3'             => $up3 ?: 'UP3 Sidoarjo',
            'ulp'             => $ulp ?: 'Sidoarjo Kota',
            'jenis_asset'     => $jenis,
            'nama_asset'      => $jenis . ' SDJ-001',
            'penyulang'       => 'BY PASS',
            'merk'            => 'Schneider Electric',
            'type'            => 'Portal 20KV',
            'nomor_seri'      => 'SN-' . date('Y') . '-001',
            'kapasitas'       => '250 kVA',
            'tahun_instalasi' => '2021',
            'lokasi'          => 'Jl. Raya Pahlawan No. 45, Sidoarjo',
            'latitude'        => '-7.4478',
            'longitude'       => '112.7183',
            'section'         => 'LBSM SIDOMULYO',
        ];
    }
}
