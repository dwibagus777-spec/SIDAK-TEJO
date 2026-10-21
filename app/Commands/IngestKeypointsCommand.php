<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class IngestKeypointsCommand extends BaseCommand
{
    protected $group       = 'Ingest';
    protected $name        = 'ingest:keypoints';
    protected $description = 'Ingest PMCB and Recloser Keypoints from CSV with strict pole linking';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $isExecute = in_array('--execute', $params, true) || in_array('execute', $params, true) || CLI::getOption('execute') !== null || !empty($params['execute']);
        $mode = $isExecute ? 'EXECUTE' : 'DRY-RUN';

        CLI::write("=== INGEST KEYPOINTS COMMAND [{$mode}] ===", 'yellow');

        $csvFiles = [
            'PMCB'     => FCPATH . '../asset-statistics-SIDOARJO-jtm_pmcb.csv',
            'RECLOSER' => FCPATH . '../asset-statistics-SIDOARJO-jtm_recloser.csv',
        ];

        // 1. Feeder & ULP Lookup
        $feeders = $db->table('penyulang')->select('id, nama_penyulang, ulp_id')->get()->getResultArray();
        $feederMap = [];
        foreach ($feeders as $f) {
            $key = strtoupper(trim(preg_replace('/^(PENYULANG|FEEDER)\s+/i', '', $f['nama_penyulang'])));
            $feederMap[$key] = (int)$f['id'];
        }

        $ulps = $db->table('ulps')->select('id, nama_ulp')->get()->getResultArray();
        $ulpMap = [];
        foreach ($ulps as $u) {
            $key = strtoupper(trim(preg_replace('/^ULP\s+/i', '', $u['nama_ulp'])));
            $ulpMap[$key] = (int)$u['id'];
        }

        $grandSummary = [];

        foreach ($csvFiles as $csvType => $filePath) {
            CLI::write("\n--- Processing CSV: {$csvType} (" . basename($filePath) . ") ---", 'cyan');
            if (!file_exists($filePath)) {
                CLI::error("File not found: {$filePath}");
                continue;
            }

            $handle = fopen($filePath, 'r');
            if (!$handle) {
                CLI::error("Cannot open file: {$filePath}");
                continue;
            }

            // Remove UTF-8 BOM if present
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $header = fgetcsv($handle);
            $rows = [];
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 8) {
                    $rows[] = array_combine(array_slice($header, 0, count($data)), $data);
                }
            }
            fclose($handle);

            CLI::write("Total source rows: " . count($rows));

            $stats = [
                'total'      => count($rows),
                'valid'      => 0,
                'rejected'   => 0,
                'duplicate'  => 0,
                'inserted'   => 0,
                'rejections' => [],
                'records'    => [],
            ];

            foreach ($rows as $idx => $r) {
                $rowNum = $idx + 2;
                $assetName = trim($r['Nama Aset'] ?? $r['Nama Asset'] ?? '');
                $feederName = trim($r['Nama Penyulang'] ?? '');
                $ulpName = trim($r['Nama ULP'] ?? '');
                $section = trim($r['Section'] ?? '');
                $lat = is_numeric($r['Latitude'] ?? null) ? (float)$r['Latitude'] : null;
                $lng = is_numeric($r['Longitude'] ?? null) ? (float)$r['Longitude'] : null;
                $jenisCsv = strtolower(trim($r['Jenis'] ?? ''));

                if (empty($assetName)) {
                    $stats['rejected']++;
                    $stats['rejections'][] = "Row #{$rowNum}: Nama Aset kosong.";
                    continue;
                }

                if ($lat === null || $lng === null || abs($lat) < 0.0001 || abs($lng) < 0.0001) {
                    $stats['rejected']++;
                    $stats['rejections'][] = "Row #{$rowNum} ({$assetName}): Koordinat tidak valid ({$r['Latitude']}, {$r['Longitude']}).";
                    continue;
                }

                // Match Feeder
                $cleanFeeder = strtoupper(trim(preg_replace('/^(PENYULANG|FEEDER)\s+/i', '', $feederName)));
                $feederId = $feederMap[$cleanFeeder] ?? null;
                if (!$feederId) {
                    // Try partial match
                    foreach ($feederMap as $fn => $fid) {
                        if (str_contains($cleanFeeder, $fn) || str_contains($fn, $cleanFeeder)) {
                            $feederId = $fid;
                            break;
                        }
                    }
                }

                // Match ULP
                $cleanUlp = strtoupper(trim(preg_replace('/^ULP\s+/i', '', $ulpName)));
                $ulpId = $ulpMap[$cleanUlp] ?? null;
                if (!$ulpId) {
                    foreach ($ulpMap as $un => $uid) {
                        if (str_contains($cleanUlp, $un) || str_contains($un, $cleanUlp)) {
                            $ulpId = $uid;
                            break;
                        }
                    }
                }

                // Determine Canonical Category (PMCB, GH, RECLOSER, LBSM, ASS)
                $upperName = strtoupper($assetName);
                if (preg_match('/\bGH\b|GARDU\s*HUBUNG/i', $upperName)) {
                    $canonicalCategory = 'GH';
                } elseif (preg_match('/\b(PMCB|CB)\b/i', $upperName)) {
                    $canonicalCategory = 'PMCB';
                } elseif (preg_match('/\b(RECLOSER|RECL)\b/i', $upperName)) {
                    $canonicalCategory = 'RECLOSER';
                } elseif (preg_match('/\b(LBSM|LBS\s*MOTOR)\b/i', $upperName)) {
                    $canonicalCategory = 'LBSM';
                } elseif (preg_match('/\b(ASS|SECTIONALIZER)\b/i', $upperName)) {
                    $canonicalCategory = 'ASS';
                } else {
                    $canonicalCategory = ($jenisCsv === 'jtm_pmcb') ? 'PMCB' : 'RECLOSER';
                }

                // Check Duplicates in DB
                $dupQuery = $db->table('assets')
                    ->where('deleted_at IS NULL')
                    ->groupStart()
                        ->where('nama_asset', $assetName)
                        ->orGroupStart()
                            ->where('ROUND(latitude, 5) =', round($lat, 5))
                            ->where('ROUND(longitude, 5) =', round($lng, 5))
                            ->whereIn('UPPER(jenis_asset)', ['RECLOSER', 'PMCB', 'GH', 'LBSM', 'ASS', 'RECLOSER_LBS'])
                        ->groupEnd()
                    ->groupEnd();

                if ($feederId) {
                    $dupQuery->where('penyulang_id', $feederId);
                }

                $existingAsset = $dupQuery->get()->getRowArray();
                if ($existingAsset) {
                    $stats['duplicate']++;
                    CLI::write("  [DUPLICATE] {$assetName} matches existing Asset #{$existingAsset['id']} ({$existingAsset['nama_asset']})", 'dark');
                    continue;
                }

                // Find Nearest Valid JTM Pole on Same Feeder
                $parentPole = null;
                $poleDistance = null;
                if ($feederId) {
                    $poles = $db->query(
                        "SELECT id, kode_asset, nama_asset, latitude, longitude,
                                (6371000 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) AS dist_m
                         FROM assets
                         WHERE penyulang_id = ?
                           AND deleted_at IS NULL
                           AND (UPPER(jenis_asset) IN ('JTM', 'TIANG_BETON', 'TIANG_BESI', 'TIANG') OR kode_asset LIKE '%TIANG%')
                           AND latitude IS NOT NULL AND longitude IS NOT NULL
                         ORDER BY dist_m ASC
                         LIMIT 1",
                        [$lat, $lng, $lat, $feederId]
                    )->getRowArray();

                    if ($poles && (float)$poles['dist_m'] <= 150.0) {
                        $parentPole = $poles;
                        $poleDistance = round((float)$poles['dist_m'], 1);
                    }
                }

                $stats['valid']++;
                $stats['records'][] = [
                    'asset_name'      => $assetName,
                    'category'        => $canonicalCategory,
                    'feeder_id'       => $feederId,
                    'feeder_name'     => $feederName,
                    'ulp_id'          => $ulpId,
                    'section'         => $section,
                    'latitude'        => $lat,
                    'longitude'       => $lng,
                    'parent_pole_id'  => $parentPole['id'] ?? null,
                    'parent_pole_code'=> $parentPole['kode_asset'] ?? null,
                    'pole_distance'   => $poleDistance,
                ];

                $linkInfo = $parentPole ? " -> Linked to Pole #{$parentPole['id']} ({$parentPole['kode_asset']}) [{$poleDistance}m]" : " -> [NO POLE LINK]";
                CLI::write("  [VALID] {$canonicalCategory}: {$assetName} ({$feederName}, Sec: {$section}){$linkInfo}", 'green');
            }

            CLI::write("\nSummary for {$csvType}: Total: {$stats['total']}, Valid: {$stats['valid']}, Rejected: {$stats['rejected']}, Duplicate: {$stats['duplicate']}");

            if ($isExecute && !empty($stats['records'])) {
                CLI::write("Executing insertion to database...", 'yellow');
                $db->transBegin();
                try {
                    $insertedCount = 0;
                    $now = date('Y-m-d H:i:s');

                    foreach ($stats['records'] as $rec) {
                        $codePrefix = $rec['category'];
                        $fPrefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($rec['feeder_name']));
                        $hash = strtoupper(substr(md5($rec['asset_name'] . $rec['latitude'] . $rec['longitude']), 0, 6));
                        $kodeAsset = "KP-{$codePrefix}-{$fPrefix}-{$hash}";

                        $insertPayload = [
                            'kode_asset'      => $kodeAsset,
                            'nama_asset'      => $rec['asset_name'],
                            'jenis_asset'     => $rec['category'],
                            'penyulang_id'    => $rec['feeder_id'],
                            'ulp_id'          => $rec['ulp_id'],
                            'lokasi'          => !empty($rec['section']) ? ('Section ' . $rec['section']) : null,
                            'latitude'        => $rec['latitude'],
                            'longitude'       => $rec['longitude'],
                            'parent_asset_id' => $rec['parent_pole_id'],
                            'status'          => 'OPERASIONAL',
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];

                        $db->table('assets')->insert($insertPayload);
                        $newAssetId = (int)$db->insertID();

                        // Link relationship
                        if ($newAssetId > 0 && !empty($rec['parent_pole_id']) && $db->tableExists('asset_relationships')) {
                            $db->table('asset_relationships')->insert([
                                'source_asset_id'   => $newAssetId,
                                'target_asset_id'   => $rec['parent_pole_id'],
                                'relationship_type' => 'MOUNTED_ON_POLE',
                                'parent_asset_id'   => $rec['parent_pole_id'],
                                'child_asset_id'    => $newAssetId,
                                'distance_meters'   => $rec['pole_distance'] ?? 0,
                                'confidence_score'  => 1.0,
                                'status'            => 'VALIDATED',
                                'is_active'         => 1,
                                'created_at'        => $now,
                            ]);
                        }
                        $insertedCount++;
                    }

                    $db->transCommit();
                    $stats['inserted'] = $insertedCount;
                    CLI::write("Successfully inserted {$insertedCount} {$csvType} keypoints!", 'green');
                } catch (\Throwable $e) {
                    $db->transRollback();
                    CLI::error("Database insertion failed: " . $e->getMessage());
                }
            }

            $grandSummary[$csvType] = $stats;
        }

        CLI::write("\n=== GRAND SUMMARY ===", 'yellow');
        foreach ($grandSummary as $type => $s) {
            CLI::write(sprintf("  %-10s | Total: %2d | Valid: %2d | Rejected: %2d | Duplicate: %2d | Inserted: %2d",
                $type, $s['total'], $s['valid'], $s['rejected'], $s['duplicate'], $s['inserted'] ?? 0
            ));
        }
    }
}
