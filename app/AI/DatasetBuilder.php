<?php

namespace App\AI;

class DatasetBuilder
{
    /**
     * Build feature-engineered dataset array for Machine Learning training
     */
    public function buildDataset(array $assets, array $temuanList, array $woList): array
    {
        $dataset = [];

        foreach ($assets as $asset) {
            $assetId = $asset['id'];
            
            // Calculate features
            $temuanCount = 0;
            $emergencyCount = 0;
            $highCount = 0;
            foreach ($temuanList as $t) {
                if (($t['asset_id'] ?? null) == $assetId) {
                    $temuanCount++;
                    if (($t['prioritas'] ?? '') === 'EMERGENCY') $emergencyCount++;
                    if (($t['prioritas'] ?? '') === 'HIGH') $highCount++;
                }
            }

            $woCount = 0;
            $woCompletedCount = 0;
            foreach ($woList as $w) {
                if (($w['asset_id'] ?? null) == $assetId) {
                    $woCount++;
                    if (($w['status'] ?? '') === 'COMPLETED') $woCompletedCount++;
                }
            }

            $tahun = (int)($asset['tahun_instalasi'] ?: date('Y'));
            $umurAset = max(1, date('Y') - $tahun);

            $dataset[] = [
                'kode_asset'       => $asset['kode_asset'],
                'jenis_asset'      => $asset['jenis_asset'],
                'nama_ulp'         => $asset['nama_ulp'] ?? 'Unknown',
                'umur_aset_tahun'  => $umurAset,
                'jumlah_temuan'    => $temuanCount,
                'jumlah_emergency' => $emergencyCount,
                'jumlah_high'      => $highCount,
                'jumlah_wo'        => $woCount,
                'jumlah_wo_selesai'=> $woCompletedCount,
                'status_aset'      => $asset['status'] ?? 'NORMAL',
                'target_label'     => ($asset['status'] === 'CRITICAL' || $emergencyCount > 0) ? 1 : 0,
            ];
        }

        return $dataset;
    }

    /**
     * Export dataset to CSV string format
     */
    public function exportCsv(array $dataset): string
    {
        if (empty($dataset)) return "";

        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

        // Headers
        fputcsv($output, array_keys($dataset[0]));

        // Rows
        foreach ($dataset as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }
}
