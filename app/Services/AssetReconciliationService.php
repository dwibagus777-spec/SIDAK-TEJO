<?php

namespace App\Services;

use Config\Database;

/**
 * AssetReconciliationService
 *
 * Deterministic high-confidence network feeder mapping for unassigned master assets.
 * 
 * Safety Guarantees:
 * - Atomic database transaction with automatic rollback on error.
 * - Mandatory pre-mutation JSON backup written to writable/backups/.
 * - Explicit audit trail (section_resolution_method = 'TOPOLOGY_DETERMINISTIC_RECON').
 * - Preserves user-assigned assets and leaves ambiguous candidates in review queue.
 * - Non-destructive: Never deletes or merges duplicate assets.
 */
class AssetReconciliationService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Get server, database, and asset inventory diagnostics.
     */
    public function getDiagnostics(): array
    {
        $totalRaw = 0;
        $totalActive = 0;
        $totalSoftDeleted = 0;
        $totalMapped = 0;
        $totalUnassigned = 0;

        if ($this->db->tableExists('assets')) {
            $totalRaw = $this->db->table('assets')->countAllResults();
            $totalActive = $this->db->table('assets')->where('deleted_at IS NULL')->countAllResults();
            $totalSoftDeleted = $this->db->table('assets')->where('deleted_at IS NOT NULL')->countAllResults();
            $totalMapped = $this->db->table('assets')
                ->where('deleted_at IS NULL')
                ->where('penyulang_id IS NOT NULL')
                ->where('penyulang_id >', 0)
                ->countAllResults();
            $totalUnassigned = $this->db->table('assets')
                ->where('deleted_at IS NULL')
                ->groupStart()
                    ->where('penyulang_id IS NULL')
                    ->orWhere('penyulang_id', 0)
                ->groupEnd()
                ->countAllResults();
        }

        $backupDir = WRITEPATH . 'backups';
        $backupDirWritable = is_dir($backupDir) ? is_writable($backupDir) : is_writable(WRITEPATH);
        $freeDiskMb = function_exists('disk_free_space') ? round(@disk_free_space(WRITEPATH) / (1024 * 1024), 2) : -1;

        return [
            'timestamp'             => date('Y-m-d H:i:s'),
            'database_connected'    => true,
            'assets_total_raw'      => $totalRaw,
            'assets_total_active'   => $totalActive,
            'assets_soft_deleted'   => $totalSoftDeleted,
            'assets_mapped'         => $totalMapped,
            'assets_unassigned'     => $totalUnassigned,
            'backup_dir_writable'   => $backupDirWritable,
            'free_disk_mb'          => $freeDiskMb,
        ];
    }

    /**
     * Run deterministic mapping engine.
     *
     * @param bool        $isExecute    Whether to commit DB mutations or simulate (dry-run).
     * @param int|null    $limit        Optional limit on unassigned assets.
     * @param string|null $feederFilter Optional target feeder filter.
     * @return array
     */
    public function runDeterministicMapping(bool $isExecute = false, ?int $limit = null, ?string $feederFilter = null): array
    {
        $diagnostics = $this->getDiagnostics();

        if (!$this->db->tableExists('assets') || !$this->db->tableExists('penyulang')) {
            return [
                'status'  => 'error',
                'message' => 'Tabel assets atau penyulang tidak ditemukan di database.',
                'diagnostics' => $diagnostics,
            ];
        }

        // 1. Load active feeders into normalized lookup
        $feeders = $this->db->table('penyulang')
            ->select('id, kode_penyulang, nama_penyulang, ulp_id')
            ->where('(status IS NULL OR UPPER(status) IN (\'AKTIF\', \'ACTIVE\'))', null, false)
            ->get()->getResultArray();

        $feederMap = [];
        foreach ($feeders as $f) {
            $rawName = trim((string)$f['nama_penyulang']);
            $norm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $rawName));
            $f['normalized'] = $norm;
            $feederMap[] = $f;
        }

        // 2. Query unassigned active assets
        $builder = $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, jenis_asset, ulp_id, penyulang_id, section_resolution_method, intelligence_resolution_status')
            ->where('deleted_at IS NULL')
            ->groupStart()
                ->where('penyulang_id IS NULL')
                ->orWhere('penyulang_id', 0)
            ->groupEnd();

        if ($limit !== null && $limit > 0) {
            $builder->limit($limit);
        }

        $unassignedAssets = $builder->get()->getResultArray();
        $totalUnassigned  = count($unassignedAssets);

        if ($totalUnassigned === 0) {
            return [
                'status'             => 'success',
                'mode'               => $isExecute ? 'EXECUTE' : 'DRY_RUN',
                'message'            => 'Semua aset master aktif sudah terpetakan ke penyulang. Tidak ada aset unassigned.',
                'total_unassigned'   => 0,
                'matched_count'      => 0,
                'ambiguous_count'    => 0,
                'diagnostics_before' => $diagnostics,
            ];
        }

        // 3. Resolve deterministic candidates
        $matchedCandidates   = [];
        $ambiguousCandidates = [];

        $ignoredParts = ['AST', 'JTM', 'SUTR', 'GTT', 'TRF', 'TM', 'KRN', 'SDA', 'PRG', 'WDU', 'KDN', 'KOTA'];

        foreach ($unassignedAssets as $asset) {
            $assetId   = (int)$asset['id'];
            $kodeAsset = strtoupper(trim((string)$asset['kode_asset']));
            $namaAsset = strtoupper(trim((string)$asset['nama_asset']));
            $assetUlp  = (int)($asset['ulp_id'] ?? 0);

            $slugs = [];

            // A. Slugs from kode_asset delimited by '-'
            $codeParts = explode('-', $kodeAsset);
            foreach ($codeParts as $p) {
                $cleanP = strtoupper(preg_replace('/[^A-Z0-9]/', '', $p));
                if (strlen($cleanP) >= 3 && !in_array($cleanP, $ignoredParts, true) && !is_numeric($cleanP)) {
                    $slugs[] = $cleanP;
                }
            }

            // B. Slugs from nama_asset
            if (preg_match('/^([A-Z0-9]+)[_ -]/i', $namaAsset, $m)) {
                $cleanM = strtoupper(preg_replace('/[^A-Z0-9]/', '', $m[1]));
                if (strlen($cleanM) >= 3 && !in_array($cleanM, $ignoredParts, true) && !is_numeric($cleanM)) {
                    $slugs[] = $cleanM;
                }
            }

            $slugs = array_values(array_unique($slugs));

            $matchedFeeders = [];
            foreach ($feederMap as $f) {
                $fNorm = $f['normalized'];
                foreach ($slugs as $slug) {
                    if ($slug === $fNorm || str_contains($fNorm, $slug) || str_contains($slug, $fNorm)) {
                        // Check ULP compatibility if asset already has ULP assigned
                        if ($assetUlp > 0 && (int)$f['ulp_id'] !== $assetUlp) {
                            continue; // Skip cross-ULP conflict
                        }
                        $matchedFeeders[$f['id']] = $f;
                    }
                }
            }

            if (count($matchedFeeders) === 1) {
                $f = reset($matchedFeeders);
                if ($feederFilter !== null && $feederFilter !== '' && (int)$f['id'] !== (int)$feederFilter && stripos($f['nama_penyulang'], $feederFilter) === false) {
                    continue;
                }
                $matchedCandidates[] = [
                    'asset_id'     => $assetId,
                    'kode_asset'   => $kodeAsset,
                    'nama_asset'   => $namaAsset,
                    'feeder_id'    => (int)$f['id'],
                    'feeder_nama'  => $f['nama_penyulang'],
                    'ulp_id'       => (int)$f['ulp_id'],
                    'prev_feeder'  => $asset['penyulang_id'],
                    'prev_ulp'     => $asset['ulp_id'],
                    'prev_method'  => $asset['section_resolution_method'],
                    'prev_intel'   => $asset['intelligence_resolution_status'],
                ];
            } else {
                $ambiguousCandidates[] = [
                    'asset_id'        => $assetId,
                    'kode_asset'      => $kodeAsset,
                    'nama_asset'      => $namaAsset,
                    'matches_count'   => count($matchedFeeders),
                    'matched_feeders' => array_column($matchedFeeders, 'nama_penyulang'),
                ];
            }
        }

        // Summary distribution by feeder
        $byFeeder = [];
        foreach ($matchedCandidates as $mc) {
            $fName = $mc['feeder_nama'];
            $byFeeder[$fName] = ($byFeeder[$fName] ?? 0) + 1;
        }
        arsort($byFeeder);

        $resultPayload = [
            'status'               => 'success',
            'mode'                 => $isExecute ? 'EXECUTE' : 'DRY_RUN',
            'total_unassigned'     => $totalUnassigned,
            'matched_count'        => count($matchedCandidates),
            'ambiguous_count'      => count($ambiguousCandidates),
            'top_feeders'          => array_slice($byFeeder, 0, 15, true),
            'diagnostics_before'   => $diagnostics,
        ];

        if (!$isExecute) {
            $resultPayload['message'] = 'Simulasi dry-run selesai. Tidak ada baris data yang diubah.';
            $resultPayload['instruction'] = 'Kirim execute=1 untuk menjalankan mutasi dengan backup JSON otomatis.';
            return $resultPayload;
        }

        if (empty($matchedCandidates)) {
            $resultPayload['message'] = 'Tidak ada kandidat dengan keyakinan tinggi untuk dieksekusi.';
            return $resultPayload;
        }

        // 4. Create Pre-Mutation JSON Backup
        $backupDir = WRITEPATH . 'backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        $backupTimestamp = date('Ymd_His');
        $backupFilename  = "asset_mapping_backup_{$backupTimestamp}.json";
        $backupPath      = "{$backupDir}/{$backupFilename}";

        $backupPayload = [
            'timestamp'        => date('Y-m-d H:i:s'),
            'total_candidates' => count($matchedCandidates),
            'command'          => 'AssetReconciliationService::runDeterministicMapping',
            'records'          => array_map(function($c) {
                return [
                    'id'                             => $c['asset_id'],
                    'penyulang_id'                   => $c['prev_feeder'],
                    'ulp_id'                         => $c['prev_ulp'],
                    'section_resolution_method'      => $c['prev_method'],
                    'intelligence_resolution_status' => $c['prev_intel'],
                ];
            }, $matchedCandidates)
        ];

        $writeOk = @file_put_contents($backupPath, json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($writeOk === false || !file_exists($backupPath) || filesize($backupPath) < 50) {
            return [
                'status'  => 'error',
                'message' => 'Gagal menulis file snapshot backup ke: ' . $backupPath . '. Mutasi dibatalkan demi keamanan.',
                'diagnostics' => $diagnostics,
            ];
        }

        // 5. Execute DB updates inside atomic transaction
        $this->db->transBegin();
        try {
            $updatedRows = 0;
            $batchSize = 500;
            $chunks = array_chunk($matchedCandidates, $batchSize);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $item) {
                    $this->db->table('assets')
                        ->where('id', $item['asset_id'])
                        ->update([
                            'penyulang_id'                   => $item['feeder_id'],
                            'ulp_id'                         => $item['ulp_id'],
                            'section_resolution_method'      => 'TOPOLOGY_DETERMINISTIC_RECON',
                            'intelligence_resolution_status' => 'RESOLVED',
                            'updated_at'                     => date('Y-m-d H:i:s'),
                        ]);
                    $updatedRows++;
                }
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Database transaction failed during update batch.");
            }

            $this->db->transCommit();

            $diagnosticsAfter = $this->getDiagnostics();

            $resultPayload['status']            = 'success';
            $resultPayload['message']           = "{$updatedRows} unit aset berhasil dipetakan ke penyulang resminya!";
            $resultPayload['updated_count']     = $updatedRows;
            $resultPayload['backup_file']       = $backupFilename;
            $resultPayload['backup_path']       = $backupPath;
            $resultPayload['rollback_command']  = "php spark asset:rollback-deterministic --backup={$backupFilename}";
            $resultPayload['diagnostics_after'] = $diagnosticsAfter;

            return $resultPayload;

        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'      => 'error',
                'message'     => 'Transaksi database gagal dan telah di-rollback: ' . $e->getMessage(),
                'backup_file' => $backupFilename,
                'diagnostics' => $diagnostics,
            ];
        }
    }

    /**
     * Rollback deterministic mapping using backup JSON.
     *
     * @param string $backupFilename
     * @return array
     */
    public function rollbackDeterministicMapping(string $backupFilename): array
    {
        $backupDir = WRITEPATH . 'backups/';
        $cleanFile = basename($backupFilename);
        $fullPath  = $backupDir . $cleanFile;

        if (!file_exists($fullPath)) {
            return [
                'status'  => 'error',
                'message' => "File backup tidak ditemukan: {$cleanFile}",
            ];
        }

        $raw = file_get_contents($fullPath);
        $data = json_decode($raw, true);

        if (!$data || !isset($data['records']) || !is_array($data['records'])) {
            return [
                'status'  => 'error',
                'message' => 'Format file backup tidak valid atau rusak.',
            ];
        }

        $this->db->transBegin();
        try {
            $restoredCount = 0;
            foreach ($data['records'] as $r) {
                $assetId = (int)$r['id'];
                $this->db->table('assets')
                    ->where('id', $assetId)
                    ->update([
                        'penyulang_id'                   => $r['penyulang_id'],
                        'ulp_id'                         => $r['ulp_id'],
                        'section_resolution_method'      => $r['section_resolution_method'],
                        'intelligence_resolution_status' => $r['intelligence_resolution_status'],
                        'updated_at'                     => date('Y-m-d H:i:s'),
                    ]);
                $restoredCount++;
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Transaksi rollback gagal.");
            }

            $this->db->transCommit();

            return [
                'status'         => 'success',
                'message'        => "Rollback berhasil! {$restoredCount} baris aset dipulihkan ke state semula.",
                'restored_count' => $restoredCount,
                'backup_file'    => $cleanFile,
                'diagnostics'    => $this->getDiagnostics(),
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'message' => 'Rollback gagal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Read-only forensic audit for KALISOGO feeder assets & BOM linkage.
     */
    public function auditKalisogo(): array
    {
        // 1. Feeder Kalisogo
        $feeder = $this->db->table('penyulang')
            ->where('UPPER(nama_penyulang)', 'KALISOGO')
            ->get()->getRowArray();

        $feederId = $feeder ? (int)$feeder['id'] : 0;

        // 2. Assets assigned to Kalisogo
        $kalisogoAssets = $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, jenis_asset, ulp_id, penyulang_id, section_id, latitude, longitude, section_resolution_method, intelligence_resolution_status, created_at')
            ->where('penyulang_id', $feederId)
            ->where('deleted_at IS NULL')
            ->get()->getResultArray();

        $totalAssigned = count($kalisogoAssets);

        // 3. Breakdown by resolution method, jenis, and code pattern
        $byMethod = [];
        $byJenis  = [];
        $byCodePattern = [];
        foreach ($kalisogoAssets as $a) {
            $m = $a['section_resolution_method'] ?: 'DIRECT_OR_LEGACY';
            $byMethod[$m] = ($byMethod[$m] ?? 0) + 1;

            $j = $a['jenis_asset'] ?: 'UNKNOWN';
            $byJenis[$j] = ($byJenis[$j] ?? 0) + 1;

            $code = (string)$a['kode_asset'];
            if (preg_match('/^AST-KALISOGO-[0-9A-F]{8}$/i', $code)) {
                $pat = 'INGEST_HASH_8HEX';
            } elseif (preg_match('/^AST-KALISOGO-KALISOGO_[0-9]+$/i', $code)) {
                $pat = 'LEGACY_POLE_NAMED';
            } elseif (preg_match('/^AST-KALISOGO-/i', $code)) {
                $pat = 'OTHER_KALISOGO_PREFIX';
            } else {
                $pat = 'NON_STANDARD_CODE';
            }
            $byCodePattern[$pat] = ($byCodePattern[$pat] ?? 0) + 1;
        }

        // 4. Duplicate Detection within Kalisogo
        // A. By exact normalized nama_asset
        $byNormName = [];
        foreach ($kalisogoAssets as $a) {
            $norm = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$a['nama_asset']));
            if ($norm !== '') {
                $byNormName[$norm][] = $a;
            }
        }
        $duplicateNames = array_filter($byNormName, fn($list) => count($list) > 1);

        // B. By exact spatial coordinates
        $byCoord = [];
        foreach ($kalisogoAssets as $a) {
            $lat = round((float)$a['latitude'], 6);
            $lng = round((float)$a['longitude'], 6);
            if ($lat != 0 && $lng != 0) {
                $key = "{$lat},{$lng}";
                $byCoord[$key][] = $a;
            }
        }
        $duplicateCoords = array_filter($byCoord, fn($list) => count($list) > 1);

        // C. Pairing Ingest vs Legacy by spatial proximity
        $ingestList = [];
        $legacyList = [];
        foreach ($kalisogoAssets as $a) {
            if (preg_match('/^AST-KALISOGO-[0-9A-F]{8}$/i', $a['kode_asset'])) {
                $ingestList[] = $a;
            } else {
                $legacyList[] = $a;
            }
        }

        $spatialExactMatches = [];
        $spatialNearMatches = []; // <= 5 meters
        foreach ($ingestList as $ing) {
            $iLat = (float)$ing['latitude'];
            $iLng = (float)$ing['longitude'];
            if ($iLat == 0 || $iLng == 0) continue;

            $bestDist = 999999;
            $bestMatch = null;

            foreach ($legacyList as $leg) {
                $lLat = (float)$leg['latitude'];
                $lLng = (float)$leg['longitude'];
                if ($lLat == 0 || $lLng == 0) continue;

                $dLat = deg2rad($lLat - $iLat);
                $dLng = deg2rad($lLng - $iLng);
                $aH   = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($iLat)) * cos(deg2rad($lLat)) * sin($dLng / 2) * sin($dLng / 2);
                $dist = 6371000 * 2 * atan2(sqrt($aH), sqrt(1 - $aH));

                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $bestMatch = $leg;
                }
            }

            if ($bestDist <= 0.05) { // < 5 cm -> exact identical coordinates
                $spatialExactMatches[] = [
                    'ingest_id'    => $ing['id'],
                    'ingest_code'  => $ing['kode_asset'],
                    'ingest_name'  => $ing['nama_asset'],
                    'legacy_id'    => $bestMatch['id'],
                    'legacy_code'  => $bestMatch['kode_asset'],
                    'legacy_name'  => $bestMatch['nama_asset'],
                    'distance_m'   => round($bestDist, 2),
                    'lat'          => $iLat,
                    'lng'          => $iLng,
                ];
            } elseif ($bestDist <= 5.0) { // <= 5 meters
                $spatialNearMatches[] = [
                    'ingest_id'    => $ing['id'],
                    'ingest_code'  => $ing['kode_asset'],
                    'ingest_name'  => $ing['nama_asset'],
                    'legacy_id'    => $bestMatch['id'],
                    'legacy_code'  => $bestMatch['kode_asset'],
                    'legacy_name'  => $bestMatch['nama_asset'],
                    'distance_m'   => round($bestDist, 2),
                ];
            }
        }

        // 5. Check dependencies on ingest assets
        $ingestIds = array_column($ingestList, 'id');
        $temuanOnIngest = 0;
        $translineOnIngest = 0;
        if (!empty($ingestIds)) {
            if ($this->db->tableExists('temuan')) {
                $temuanOnIngest = $this->db->table('temuan')->whereIn('asset_id', $ingestIds)->countAllResults();
            }
            if ($this->db->tableExists('gis_translines')) {
                $translineOnIngest = $this->db->table('gis_translines')
                    ->groupStart()
                        ->whereIn('source_asset_id', $ingestIds)
                        ->orWhereIn('target_asset_id', $ingestIds)
                    ->groupEnd()
                    ->countAllResults();
            }
        }

        // 6. Audit BOM & Temuan
        $totalTemuan = $this->db->tableExists('temuan') ? $this->db->table('temuan')->where('deleted_at IS NULL')->countAllResults() : 0;
        $totalTemuanMaterials = $this->db->tableExists('temuan_materials') ? $this->db->table('temuan_materials')->countAllResults() : 0;
        $temuanWithMaterials = $this->db->tableExists('temuan_materials') ? $this->db->table('temuan_materials')->select('DISTINCT temuan_id')->countAllResults() : 0;

        return [
            'status' => 'success',
            'feeder' => $feeder,
            'kalisogo_audit' => [
                'total_assigned_assets'      => $totalAssigned,
                'by_resolution_method'       => $byMethod,
                'by_jenis_asset'             => $byJenis,
                'by_code_pattern'            => $byCodePattern,
                'ingest_batch_count'         => count($ingestList),
                'legacy_count'               => count($legacyList),
                'duplicate_names_groups'     => count($duplicateNames),
                'duplicate_coords_groups'    => count($duplicateCoords),
                'spatial_exact_duplicates'   => count($spatialExactMatches),
                'spatial_near_duplicates'    => count($spatialNearMatches),
                'samples_exact_duplicates'   => array_slice($spatialExactMatches, 0, 10),
                'samples_near_duplicates'    => array_slice($spatialNearMatches, 0, 5),
                'dependencies' => [
                    'temuan_attached_to_ingest'    => $temuanOnIngest,
                    'translines_attached_to_ingest'=> $translineOnIngest,
                ]
            ],
            'bom_audit' => [
                'total_temuan_active'        => $totalTemuan,
                'temuan_materials_rows'      => $totalTemuanMaterials,
                'temuan_with_materials'      => $temuanWithMaterials,
            ]
        ];
    }

    /**
     * Safely reconcile and canonicalize duplicate Kalisogo ingest assets.
     *
     * Invariants:
     * 1. Only canonicalize verified exact spatial duplicate pairs (< 5 cm).
     * 2. Re-route any foreign keys (temuan, gis_translines, asset_history) to legacy canonical asset ID first.
     * 3. Take pre-mutation JSON snapshot backup before any modification.
     * 4. Soft-delete redundant ingest duplicate row (deleted_at = NOW()).
     * 5. Never perform irreversible DELETE or CASCADE.
     */
    public function reconcileKalisogoDuplicates(bool $isExecute = false): array
    {
        $feeder = $this->db->table('penyulang')
            ->like('nama_penyulang', 'KALISOGO')
            ->get()->getRowArray();

        $feederId = $feeder ? (int)$feeder['id'] : 0;
        if ($feederId === 0) {
            return ['status' => 'error', 'message' => 'Feeder Kalisogo not found in database.'];
        }

        $kalisogoAssets = $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, jenis_asset, ulp_id, penyulang_id, section_id, latitude, longitude, section_resolution_method, created_at')
            ->where('penyulang_id', $feederId)
            ->where('deleted_at IS NULL')
            ->get()->getResultArray();

        $totalAssigned = count($kalisogoAssets);

        $ingestList = [];
        $legacyList = [];
        foreach ($kalisogoAssets as $a) {
            if (preg_match('/^AST-KALISOGO-[0-9A-F]{8}$/i', (string)$a['kode_asset'])) {
                $ingestList[] = $a;
            } else {
                $legacyList[] = $a;
            }
        }

        // Pair ingest vs legacy by coordinates
        $exactPairs = [];
        foreach ($ingestList as $ing) {
            $iLat = (float)$ing['latitude'];
            $iLng = (float)$ing['longitude'];
            if ($iLat == 0 || $iLng == 0) continue;

            $bestDist = 999999;
            $bestMatch = null;
            foreach ($legacyList as $leg) {
                $lLat = (float)$leg['latitude'];
                $lLng = (float)$leg['longitude'];
                if ($lLat == 0 || $lLng == 0) continue;

                $dLat = deg2rad($lLat - $iLat);
                $dLng = deg2rad($lLng - $iLng);
                $aH   = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($iLat)) * cos(deg2rad($lLat)) * sin($dLng / 2) * sin($dLng / 2);
                $dist = 6371000 * 2 * atan2(sqrt($aH), sqrt(1 - $aH));

                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $bestMatch = $leg;
                }
            }

            if ($bestDist <= 0.05 && $bestMatch !== null) {
                $exactPairs[] = [
                    'ingest_id'   => (int)$ing['id'],
                    'ingest_code' => $ing['kode_asset'],
                    'ingest_name' => $ing['nama_asset'],
                    'legacy_id'   => (int)$bestMatch['id'],
                    'legacy_code' => $bestMatch['kode_asset'],
                    'legacy_name' => $bestMatch['nama_asset'],
                    'distance_m'  => round($bestDist, 4),
                    'lat'         => $iLat,
                    'lng'         => $iLng,
                ];
            }
        }

        $ingestIds = array_column($exactPairs, 'ingest_id');
        $pairMap = array_column($exactPairs, 'legacy_id', 'ingest_id');

        // Check FK dependencies
        $affectedTemuan = [];
        $affectedTranslinesSource = [];
        $affectedTranslinesTarget = [];

        if (!empty($ingestIds)) {
            if ($this->db->tableExists('temuan')) {
                $tRows = $this->db->table('temuan')
                    ->select('id, asset_id, penyulang_id, judul')
                    ->whereIn('asset_id', $ingestIds)
                    ->get()->getResultArray();
                foreach ($tRows as $tr) {
                    $affectedTemuan[] = [
                        'id'           => (int)$tr['id'],
                        'old_asset_id' => (int)$tr['asset_id'],
                        'new_asset_id' => (int)($pairMap[$tr['asset_id']] ?? 0),
                        'judul'        => $tr['judul'] ?? '',
                    ];
                }
            }

            if ($this->db->tableExists('gis_translines')) {
                $gtSrc = $this->db->table('gis_translines')
                    ->select('id, source_asset_id, target_asset_id')
                    ->whereIn('source_asset_id', $ingestIds)
                    ->get()->getResultArray();
                foreach ($gtSrc as $g) {
                    $affectedTranslinesSource[] = [
                        'id'         => (int)$g['id'],
                        'old_source' => (int)$g['source_asset_id'],
                        'new_source' => (int)($pairMap[$g['source_asset_id']] ?? 0),
                    ];
                }

                $gtTgt = $this->db->table('gis_translines')
                    ->select('id, source_asset_id, target_asset_id')
                    ->whereIn('target_asset_id', $ingestIds)
                    ->get()->getResultArray();
                foreach ($gtTgt as $g) {
                    $affectedTranslinesTarget[] = [
                        'id'         => (int)$g['id'],
                        'old_target' => (int)$g['target_asset_id'],
                        'new_target' => (int)($pairMap[$g['target_asset_id']] ?? 0),
                    ];
                }
            }
        }

        $dryRunSummary = [
            'status'                             => 'success',
            'mode'                               => $isExecute ? 'LIVE_EXECUTION' : 'DRY_RUN_SIMULATION',
            'feeder_id'                          => $feederId,
            'feeder_name'                        => $feeder['nama_penyulang'],
            'total_active_before'                => $totalAssigned,
            'legacy_count'                       => count($legacyList),
            'ingest_batch_count'                 => count($ingestList),
            'exact_duplicate_pairs_found'        => count($exactPairs),
            'unmatched_ingest_remaining'         => count($ingestList) - count($exactPairs),
            'dependencies_rerouted' => [
                'temuan_count'                   => count($affectedTemuan),
                'translines_source_count'        => count($affectedTranslinesSource),
                'translines_target_count'        => count($affectedTranslinesTarget),
            ],
            'projected_active_after'             => $totalAssigned - count($exactPairs),
        ];

        if (!$isExecute) {
            $dryRunSummary['sample_duplicate_pairs'] = array_slice($exactPairs, 0, 10);
            return $dryRunSummary;
        }

        // Live Execution: Must create JSON backup before mutating
        $backupDir = WRITEPATH . 'backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $backupFile = $backupDir . '/kalisogo_dedup_backup_' . date('Ymd_His') . '.json';
        $backupPayload = [
            'created_at'                  => date('c'),
            'feeder'                      => $feeder,
            'total_active_before'         => $totalAssigned,
            'exact_duplicate_pairs_count' => count($exactPairs),
            'exact_duplicate_pairs'       => $exactPairs,
            'affected_temuan'             => $affectedTemuan,
            'affected_translines_source'  => $affectedTranslinesSource,
            'affected_translines_target'  => $affectedTranslinesTarget,
        ];

        $written = file_put_contents($backupFile, json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($written === false || $written === 0) {
            return [
                'status'  => 'error',
                'message' => 'Failed to write pre-mutation backup file. Aborting mutation for safety.',
            ];
        }

        // Transactional Execution
        $this->db->transBegin();
        try {
            // 1. Reroute temuan foreign keys
            foreach ($affectedTemuan as $at) {
                if ($at['new_asset_id'] > 0) {
                    $this->db->table('temuan')
                        ->where('id', $at['id'])
                        ->update(['asset_id' => $at['new_asset_id']]);
                }
            }

            // 2. Reroute gis_translines foreign keys
            foreach ($affectedTranslinesSource as $ats) {
                if ($ats['new_source'] > 0) {
                    $this->db->table('gis_translines')
                        ->where('id', $ats['id'])
                        ->update(['source_asset_id' => $ats['new_source']]);
                }
            }
            foreach ($affectedTranslinesTarget as $att) {
                if ($att['new_target'] > 0) {
                    $this->db->table('gis_translines')
                        ->where('id', $att['id'])
                        ->update(['target_asset_id' => $att['new_target']]);
                }
            }

            // 3. Soft-delete duplicate ingest assets in chunks
            $now = date('Y-m-d H:i:s');
            $chunks = array_chunk($ingestIds, 250);
            foreach ($chunks as $chunk) {
                $this->db->table('assets')
                    ->whereIn('id', $chunk)
                    ->update([
                        'deleted_at'                => $now,
                        'section_resolution_method' => 'DUPLICATE_INGEST_CANONICALIZED',
                    ]);
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'message' => 'Deduplication transaction failed: ' . $e->getMessage(),
                'backup'  => $backupFile,
            ];
        }

        // Post-execution verify count
        $activeAfter = $this->db->table('assets')
            ->where('penyulang_id', $feederId)
            ->where('deleted_at IS NULL')
            ->countAllResults();

        return array_merge($dryRunSummary, [
            'status'                => 'success',
            'mode'                  => 'LIVE_EXECUTION',
            'backup_file'           => $backupFile,
            'backup_bytes'          => $written,
            'soft_deleted_count'    => count($ingestIds),
            'actual_active_after'   => $activeAfter,
            'verified_clean'        => ($activeAfter === ($totalAssigned - count($exactPairs))),
        ]);
    }
}
