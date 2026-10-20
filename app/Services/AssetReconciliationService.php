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
}
