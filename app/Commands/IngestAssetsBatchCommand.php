<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class IngestAssetsBatchCommand extends BaseCommand
{
    protected $group       = 'Ingest';
    protected $name        = 'ingest:assets-batch';
    protected $description = 'Ingest JTM.xlsx, jtm_gardu.csv, and jtm_trafo.csv idempotently with feeder normalization and pole linking';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $isExecute = in_array('--execute', $params, true) || in_array('execute', $params, true) || CLI::getOption('execute') !== null || !empty($params['execute']);
        $mode = $isExecute ? 'EXECUTE' : 'DRY-RUN';

        CLI::write("==================================================", 'yellow');
        CLI::write("  INGEST ASSETS BATCH (JTM, GARDU, TRAFO) [{$mode}]", 'yellow');
        CLI::write("==================================================", 'yellow');

        if ($isExecute) {
            $backupFile = WRITEPATH . 'backup_assets_' . date('Ymd_His') . '.json';
            $a = $db->table('assets')->where('deleted_at IS NULL')->get()->getResultArray();
            $r = $db->table('asset_relationships')->where('is_active', 1)->get()->getResultArray();
            file_put_contents($backupFile, json_encode(['assets' => $a, 'rels' => $r]));
            CLI::write("Pre-mutation backup written to: {$backupFile} (" . count($a) . " assets, " . count($r) . " rels).", 'green');
        }

        // 1. Preload Feeders & ULPs
        $dbFeeders = $db->table('penyulang')->select('id, kode_penyulang, nama_penyulang, ulp_id')->get()->getResultArray();
        $dbFeederMap = [];
        foreach ($dbFeeders as $df) {
            $norm = $this->normalizeName($df['nama_penyulang']);
            $dbFeederMap[$norm][] = $df;
        }

        $dbUlps = $db->table('ulps')->select('id, nama_ulp')->get()->getResultArray();
        $dbUlpMap = [];
        foreach ($dbUlps as $du) {
            $norm = $this->normalizeName(str_ireplace('ULP', '', $du['nama_ulp']));
            $dbUlpMap[$norm] = (int)$du['id'];
        }

        // High-confidence 1:1 Feeder Aliases
        $feederAliases = [
            'SWALUH'              => 'SUWALUH',
            'SURYAMULTICEMERLANG' => 'SMC',
            'MUTIARACITRAASRI'    => 'MCA',
            'POKPHAND2'           => 'PHOKPHAND2',
            'GLAGAHARUM'          => 'GLAGAHARUM',
            'JAVAPACIFIK1'        => 'JAVAPASIFIK1',
            'JAVAPACIFIK2'        => 'JAVAPASIFIK2',
            'JAVAPACIFIK3'        => 'JAVAPASIFIK3',
            'JAVAPACIFIK4'        => 'JAVAPASIFIK4',
            'JAVAPASIFIC1'        => 'JAVAPASIFIK1',
            'JAVAPASIFIC2'        => 'JAVAPASIFIK2',
            'JAVAPASIFIC3'        => 'JAVAPASIFIK3',
            'JAVAPASIFIC4'        => 'JAVAPASIFIK4',
            'PERMATAHIJAUBARU'    => 'PERMATAHIJAU',
        ];

        // 2. Preload Existing Assets into In-Memory Hash Maps for O(1) Instant Deduplication
        CLI::write("Preloading existing active assets into memory cache...", 'white');
        $existingRows = $db->query(
            "SELECT id, nama_asset, jenis_asset, penyulang_id, ROUND(latitude, 5) lat, ROUND(longitude, 5) lng 
             FROM assets WHERE deleted_at IS NULL"
        )->getResultArray();

        $existingByName = [];
        $existingByCoord = [];
        $existingPolesByFeeder = [];

        foreach ($existingRows as $er) {
            $n = strtolower(trim((string)$er['nama_asset']));
            if ($n !== '') {
                $fId = (int)($er['penyulang_id'] ?? 0);
                $existingByName["{$fId}|{$n}"] = (int)$er['id'];
                $existingByName["0|{$n}"] = (int)$er['id'];
            }
            if ($er['lat'] !== null && $er['lng'] !== null) {
                $cKey = "{$er['lat']}_{$er['lng']}_" . strtolower(trim((string)$er['jenis_asset']));
                $existingByCoord[$cKey] = (int)$er['id'];
            }

            // Index poles for nearest pole search
            $rawJ = strtoupper(trim((string)$er['jenis_asset']));
            if (in_array($rawJ, ['JTM', 'TIANG_BETON', 'TIANG_BESI', 'TIANG', 'JTM_TIANG'])) {
                $fId = (int)($er['penyulang_id'] ?? 0);
                if ($fId > 0 && $er['lat'] !== null && $er['lng'] !== null) {
                    $existingPolesByFeeder[$fId][] = [
                        'id'  => (int)$er['id'],
                        'lat' => (float)$er['lat'],
                        'lng' => (float)$er['lng'],
                    ];
                }
            }
        }
        CLI::write("Preloaded " . count($existingRows) . " existing assets.", 'green');

        $overallStats = [];

        // ---------------------------------------------------------------------
        // FILE 1: JTM.xlsx (6,448 physical poles)
        // ---------------------------------------------------------------------
        $jtmFile = FCPATH . '../JTM.xlsx';
        if (file_exists($jtmFile)) {
            CLI::write("\n[1/3] Reading and processing JTM.xlsx...", 'cyan');
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($jtmFile);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
            unset($spreadsheet);

            $rows = [];
            for ($r = 1; $r < count($sheetData); $r++) {
                $row = $sheetData[$r];
                $ulpName    = trim((string)($row[1] ?? ''));
                $feederName = trim((string)($row[2] ?? ''));
                $assetName  = trim((string)($row[3] ?? ''));
                $latVal     = $row[4] ?? null;
                $lngVal     = $row[5] ?? null;
                $jenis      = trim((string)($row[6] ?? ''));
                $constr     = trim((string)($row[7] ?? ''));

                if ($assetName !== '') {
                    $rows[] = [
                        'row_num'    => $r + 1,
                        'ulp_name'   => $ulpName,
                        'feeder_name'=> $feederName,
                        'asset_name' => $assetName,
                        'section'    => '',
                        'lat'        => is_numeric($latVal) ? (float)$latVal : null,
                        'lng'        => is_numeric($lngVal) ? (float)$lngVal : null,
                        'jenis'      => $jenis ?: 'jtm_tiang',
                        'constr'     => $constr,
                    ];
                }
            }
            $overallStats['JTM.xlsx'] = $this->processBatch(
                $db, $rows, 'jtm_tiang', $dbFeederMap, $dbUlpMap, $feederAliases,
                $existingByName, $existingByCoord, $existingPolesByFeeder,
                $isExecute, false
            );
        } else {
            CLI::error("File not found: JTM.xlsx");
        }

        // ---------------------------------------------------------------------
        // FILE 2: asset-statistics-SIDOARJO-jtm_gardu.csv (513 gardu)
        // ---------------------------------------------------------------------
        $garduFile = FCPATH . '../asset-statistics-SIDOARJO-jtm_gardu.csv';
        if (file_exists($garduFile)) {
            CLI::write("\n[2/3] Reading and processing asset-statistics-SIDOARJO-jtm_gardu.csv...", 'cyan');
            $rows = $this->readCsvWithHeader($garduFile);
            $overallStats['jtm_gardu.csv'] = $this->processBatch(
                $db, $rows, 'jtm_gardu', $dbFeederMap, $dbUlpMap, $feederAliases,
                $existingByName, $existingByCoord, $existingPolesByFeeder,
                $isExecute, true
            );
        } else {
            CLI::error("File not found: asset-statistics-SIDOARJO-jtm_gardu.csv");
        }

        // ---------------------------------------------------------------------
        // FILE 3: asset-statistics-SIDOARJO-jtm_trafo.csv (4,263 trafo)
        // ---------------------------------------------------------------------
        $trafoFile = FCPATH . '../asset-statistics-SIDOARJO-jtm_trafo.csv';
        if (file_exists($trafoFile)) {
            CLI::write("\n[3/3] Reading and processing asset-statistics-SIDOARJO-jtm_trafo.csv...", 'cyan');
            $rows = $this->readCsvWithHeader($trafoFile);
            $overallStats['jtm_trafo.csv'] = $this->processBatch(
                $db, $rows, 'jtm_trafo', $dbFeederMap, $dbUlpMap, $feederAliases,
                $existingByName, $existingByCoord, $existingPolesByFeeder,
                $isExecute, true
            );
        } else {
            CLI::error("File not found: asset-statistics-SIDOARJO-jtm_trafo.csv");
        }

        // ---------------------------------------------------------------------
        // FINAL SUMMARY REPORT
        // ---------------------------------------------------------------------
        CLI::write("\n==================================================", 'yellow');
        CLI::write("           FINAL INGESTION SUMMARY REPORT", 'yellow');
        CLI::write("==================================================", 'yellow');
        foreach ($overallStats as $fileName => $s) {
            CLI::write("File: {$fileName}");
            CLI::write("  - Total Source Rows:   " . $s['source_count']);
            CLI::write("  - Valid Records:       " . $s['valid_count'], 'green');
            CLI::write("  - Duplicate (Skipped): " . $s['duplicate_count'], 'yellow');
            CLI::write("  - Rejected / Invalid:  " . $s['rejected_count'], $s['rejected_count'] > 0 ? 'red' : 'white');
            CLI::write("  - Linked to JTM Pole:  " . $s['linked_pole_count'], 'cyan');
            CLI::write("  - Inserted to DB:      " . $s['inserted_count'], 'green');
            if (!empty($s['exceptions'])) {
                CLI::write("  - Exceptions Sample (" . count($s['exceptions']) . "):", 'yellow');
                foreach (array_slice($s['exceptions'], 0, 6) as $ex) {
                    CLI::write("      * {$ex}");
                }
                if (count($s['exceptions']) > 6) {
                    CLI::write("      ... (" . (count($s['exceptions']) - 6) . " more)");
                }
            }
        }
    }

    private function processBatch(
        $db, array $rows, string $targetCategory,
        array $dbFeederMap, array $dbUlpMap, array $feederAliases,
        array &$existingByName, array &$existingByCoord, array &$existingPolesByFeeder,
        bool $isExecute, bool $linkToNearestPole
    ): array {
        $stats = [
            'source_count'      => count($rows),
            'valid_count'       => 0,
            'duplicate_count'   => 0,
            'rejected_count'    => 0,
            'linked_pole_count' => 0,
            'inserted_count'    => 0,
            'exceptions'        => [],
        ];

        $validPayloads = [];

        foreach ($rows as $r) {
            $rowNum     = $r['row_num'] ?? 0;
            $assetName  = trim($r['asset_name'] ?? '');
            $ulpName    = trim($r['ulp_name'] ?? '');
            $feederName = trim($r['feeder_name'] ?? '');
            $section    = trim($r['section'] ?? '');
            $lat        = $r['lat'];
            $lng        = $r['lng'];
            $constr     = trim($r['constr'] ?? '');

            if ($assetName === '') {
                $stats['rejected_count']++;
                continue;
            }

            if ($lat === null || $lng === null || abs($lat) < 0.0001 || abs($lng) < 0.0001) {
                $stats['rejected_count']++;
                $stats['exceptions'][] = "Row #{$rowNum} [{$assetName}]: Koordinat tidak valid ({$lat}, {$lng})";
                continue;
            }

            // Feeder matching
            $matchedFeeder = $this->matchFeeder($feederName, $dbFeederMap, $feederAliases);
            $feederId = null;
            if ($matchedFeeder['status'] === 'EXACT') {
                $feederId = (int)$matchedFeeder['matched'][0]['id'];
            } elseif ($matchedFeeder['status'] === 'AMBIGUOUS') {
                $stats['exceptions'][] = "Row #{$rowNum} [{$assetName}]: Feeder '{$feederName}' ambigu -> Ditetapkan ULP Unassigned";
            } else {
                $stats['exceptions'][] = "Row #{$rowNum} [{$assetName}]: Feeder '{$feederName}' tidak terdaftar di DB -> Ditetapkan ULP Unassigned";
            }

            // ULP matching
            $normUlp = $this->normalizeName(str_ireplace('ULP', '', $ulpName));
            $ulpId = $dbUlpMap[$normUlp] ?? null;
            if (!$ulpId && $feederId) {
                $fRow = $db->table('penyulang')->select('ulp_id')->where('id', $feederId)->get()->getRowArray();
                if ($fRow && !empty($fRow['ulp_id'])) {
                    $ulpId = (int)$fRow['ulp_id'];
                }
            }

            // In-memory Fast Deduplication Check
            $lowerName = strtolower($assetName);
            $fCheckKey = ($feederId ?? 0) . "|{$lowerName}";
            $globalCheckKey = "0|{$lowerName}";
            $coordKey = round($lat, 5) . '_' . round($lng, 5) . '_' . strtolower($targetCategory);

            if (isset($existingByName[$fCheckKey]) || isset($existingByName[$globalCheckKey]) || isset($existingByCoord[$coordKey])) {
                $stats['duplicate_count']++;
                continue;
            }

            // Nearest Pole Linking for Equipment (Gardu / Trafo)
            $parentPoleId = null;
            $poleDistance = null;
            if ($linkToNearestPole && $feederId && !empty($existingPolesByFeeder[$feederId])) {
                $bestDist = 50.0; // max 50m
                $bestPoleId = null;
                foreach ($existingPolesByFeeder[$feederId] as $p) {
                    $d = $this->haversineDistance($lat, $lng, $p['lat'], $p['lng']);
                    if ($d < $bestDist) {
                        $bestDist = $d;
                        $bestPoleId = $p['id'];
                    }
                }
                if ($bestPoleId !== null) {
                    $parentPoleId = $bestPoleId;
                    $poleDistance = round($bestDist, 1);
                    $stats['linked_pole_count']++;
                }
            }

            $stats['valid_count']++;
            $validPayloads[] = [
                'nama_asset'      => $assetName,
                'jenis_asset'     => $targetCategory,
                'penyulang_id'    => $feederId,
                'ulp_id'          => $ulpId,
                'lokasi'          => !empty($section) ? ('Section ' . $section) : null,
                'latitude'        => $lat,
                'longitude'       => $lng,
                'type'            => $constr ?: null,
                'parent_asset_id' => $parentPoleId,
                'status'          => 'OPERASIONAL',
            ];

            // Mark in memory cache so subsequent duplicate rows within same file are caught
            $existingByName[$fCheckKey] = true;
            $existingByCoord[$coordKey] = true;
        }

        // Execute batch insert if requested
        if ($isExecute && !empty($validPayloads)) {
            $now = date('Y-m-d H:i:s');
            $db->transBegin();
            try {
                $chunks = array_chunk($validPayloads, 250);
                foreach ($chunks as $chunk) {
                    foreach ($chunk as $item) {
                        $fPrefix = $item['penyulang_id'] ? ("F" . $item['penyulang_id']) : 'UNAS';
                        $catPrefix = strtoupper(substr($targetCategory, 0, 4));
                        $hash = strtoupper(substr(md5($item['nama_asset'] . $item['latitude'] . $item['longitude']), 0, 6));
                        $kodeAsset = "AST-{$catPrefix}-{$fPrefix}-{$hash}";

                        $row = [
                            'kode_asset'      => $kodeAsset,
                            'nama_asset'      => $item['nama_asset'],
                            'jenis_asset'     => $item['jenis_asset'],
                            'penyulang_id'    => $item['penyulang_id'],
                            'ulp_id'          => $item['ulp_id'],
                            'lokasi'          => $item['lokasi'],
                            'latitude'        => $item['latitude'],
                            'longitude'       => $item['longitude'],
                            'type'            => $item['type'],
                            'parent_asset_id' => $item['parent_asset_id'],
                            'status'          => $item['status'],
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];

                        $db->table('assets')->insert($row);
                        $newId = (int)$db->insertID();

                        if ($newId > 0 && !empty($item['parent_asset_id']) && $db->tableExists('asset_relationships')) {
                            $db->table('asset_relationships')->insert([
                                'source_asset_id'   => $newId,
                                'target_asset_id'   => $item['parent_asset_id'],
                                'relationship_type' => 'MOUNTED_ON_POLE',
                                'parent_asset_id'   => $item['parent_asset_id'],
                                'child_asset_id'    => $newId,
                                'source'            => 'AUTO_INGEST',
                                'confidence_score'  => 1.0,
                                'is_active'         => 1,
                                'created_at'        => $now,
                            ]);
                        }

                        // If newly inserted asset is a pole, add to existingPolesByFeeder
                        if ($targetCategory === 'jtm_tiang' && $item['penyulang_id']) {
                            $existingPolesByFeeder[$item['penyulang_id']][] = [
                                'id'  => $newId,
                                'lat' => $item['latitude'],
                                'lng' => $item['longitude'],
                            ];
                        }

                        $stats['inserted_count']++;
                    }
                }
                $db->transCommit();
                CLI::write("  Successfully inserted {$stats['inserted_count']} records to database.", 'green');
            } catch (\Throwable $e) {
                $db->transRollback();
                CLI::error("  Database insertion error: " . $e->getMessage());
            }
        }

        return $stats;
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function readCsvWithHeader(string $filePath): array
    {
        $rows = [];
        $h = fopen($filePath, 'r');
        if (!$h) return [];

        $bom = fread($h, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($h);
        }

        $header = fgetcsv($h);
        $r = 2;
        while (($data = fgetcsv($h)) !== false) {
            if (count($data) >= 8) {
                $rows[] = [
                    'row_num'    => $r++,
                    'ulp_name'   => trim($data[1] ?? ''),
                    'feeder_name'=> trim($data[2] ?? ''),
                    'asset_name' => trim($data[3] ?? ''),
                    'section'    => trim($data[4] ?? ''),
                    'lat'        => is_numeric($data[5] ?? null) ? (float)$data[5] : null,
                    'lng'        => is_numeric($data[6] ?? null) ? (float)$data[6] : null,
                    'jenis'      => trim($data[7] ?? ''),
                    'constr'     => trim($data[8] ?? ''),
                ];
            }
        }
        fclose($h);
        return $rows;
    }

    private function normalizeName(string $name): string
    {
        $n = strtoupper(trim($name));
        $n = preg_replace('/^(PENYULANG|FEEDER|P\.)\s+/i', '', $n);
        $n = preg_replace('/[^A-Z0-9]/', '', $n);
        return $n;
    }

    private function matchFeeder(string $fileFeeder, array $dbMap, array $aliases): array
    {
        $norm = $this->normalizeName($fileFeeder);

        if (isset($dbMap[$norm])) {
            return [
                'status'  => count($dbMap[$norm]) === 1 ? 'EXACT' : 'AMBIGUOUS',
                'matched' => $dbMap[$norm],
            ];
        }

        if (isset($aliases[$norm])) {
            $aliasNorm = $aliases[$norm];
            if (isset($dbMap[$aliasNorm])) {
                return [
                    'status'  => count($dbMap[$aliasNorm]) === 1 ? 'EXACT' : 'AMBIGUOUS',
                    'matched' => $dbMap[$aliasNorm],
                ];
            }
        }

        return [
            'status'  => 'UNMATCHED',
            'matched' => [],
        ];
    }
}
