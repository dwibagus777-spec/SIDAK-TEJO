<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Services\HistoricalInterruptionImportService;

class HistoricalInterruptionSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = WRITEPATH . 'uploads/rekap_gangguan_sda.csv';
        if (!file_exists($csvPath)) {
            return;
        }

        $rows = [];
        if (($handle = fopen($csvPath, 'r')) !== false) {
            $header = fgetcsv($handle, 2000, ',');
            while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                if (!empty($data) && count($data) >= 10) {
                    $rows[] = $data;
                }
            }
            fclose($handle);
        }

        if (!empty($rows)) {
            $importService = new HistoricalInterruptionImportService($this->db);
            $importService->importRows($rows, 'BATCH-SDA-2025-2026-V1', 'rekap_gangguan_sda.csv');
        }
    }
}
