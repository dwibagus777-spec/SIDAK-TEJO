<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * MapDeterministicAssetsCommand
 *
 * Deterministic high-confidence network feeder mapping for unassigned assets.
 * 
 * Strict Safety Guarantee:
 * - Reversible transaction.
 * - Mandatory pre-mutation JSON backup written to writable/backups/.
 * - Explicit audit trail (section_resolution_method = 'TOPOLOGY_DETERMINISTIC_RECON').
 * - Preserves user-assigned assets and leaves ambiguous candidates in review queue.
 */
class MapDeterministicAssetsCommand extends BaseCommand
{
    protected $group       = 'Asset';
    protected $name        = 'asset:map-deterministic';
    protected $description = 'Maps unassigned master assets to feeders using deterministic slug topology with rollback backup.';
    protected $usage       = 'asset:map-deterministic [--dry-run] [--execute] [--limit=<n>] [--feeder=<name_or_id>]';
    protected $options     = [
        '--dry-run' => 'Simulate mapping without modifying the database (Default)',
        '--execute' => 'Execute the deterministic mapping inside a transaction with pre-backup',
        '--limit'   => 'Limit number of assets to process in this run',
        '--feeder'  => 'Filter mapping for a specific target feeder ID or name',
    ];

    public function run(array $params)
    {
        $db = Database::connect();
        $isExecute = CLI::getOption('execute') !== null;
        $isDryRun  = CLI::getOption('dry-run') !== null || !$isExecute;
        $limit     = CLI::getOption('limit') ? (int)CLI::getOption('limit') : null;
        $feederFilter = CLI::getOption('feeder') ?? null;

        CLI::write("==================================================================", 'cyan');
        CLI::write("   SIDAK TEJO — DETERMINISTIC ASSET FEEDER RECONCILIATION ENGINE  ", 'cyan');
        CLI::write("==================================================================", 'cyan');
        CLI::write("Mode: " . ($isExecute ? "LIVE EXECUTION (WITH BACKUP)" : "DRY-RUN (READ-ONLY SIMULATION)"), $isExecute ? 'yellow' : 'green');

        if (!$db->tableExists('assets') || !$db->tableExists('penyulang')) {
            CLI::error("Tabel assets atau penyulang tidak ditemukan.");
            return;
        }

        // 1. Load active feeders into normalized dictionary
        $feeders = $db->table('penyulang')
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
        $builder = $db->table('assets')
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

        CLI::write("Ditemukan {$totalUnassigned} unit aset belum memiliki relasi penyulang.", 'white');

        if ($totalUnassigned === 0) {
            CLI::write("Semua aset master sudah terpetakan ke penyulang. Tidak ada pekerjaan diperlukan.", 'green');
            return;
        }

        // 3. Resolve deterministic candidates
        $matchedCandidates = [];
        $ambiguousCandidates = [];

        foreach ($unassignedAssets as $asset) {
            $assetId   = (int)$asset['id'];
            $kodeAsset = strtoupper(trim((string)$asset['kode_asset']));
            $namaAsset = strtoupper(trim((string)$asset['nama_asset']));
            $assetUlp  = (int)($asset['ulp_id'] ?? 0);

            // Extract potential slug
            $slugs = [];
            // Pattern 1: AST-{SLUG}-{HASH}
            if (preg_match('/^AST-([A-Z0-9]+)-/i', $kodeAsset, $m)) {
                $slugs[] = strtoupper($m[1]);
            }
            // Pattern 2: {SLUG}_{NUMBER}
            if (preg_match('/^([A-Z0-9]+)_[0-9]+/i', $namaAsset, $m)) {
                $slugs[] = strtoupper($m[1]);
            }

            $matchedFeeders = [];
            foreach ($feederMap as $f) {
                $fNorm = $f['normalized'];
                foreach ($slugs as $slug) {
                    if ($slug === $fNorm || str_contains($fNorm, $slug) || str_contains($slug, $fNorm)) {
                        // Check ULP compatibility if asset already has ULP
                        if ($assetUlp > 0 && (int)$f['ulp_id'] !== $assetUlp) {
                            continue; // Skip cross-ULP match
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

        CLI::write("\nHasil Analisis Pencocokan:", 'yellow');
        CLI::write("  • Kandidat High-Confidence (Dapat Direkonsiliasi): " . count($matchedCandidates) . " unit", 'green');
        CLI::write("  • Ambigu / Butuh Review Lapangan                  : " . count($ambiguousCandidates) . " unit", 'light_gray');

        // Group matched candidates by feeder
        $byFeeder = [];
        foreach ($matchedCandidates as $mc) {
            $fName = $mc['feeder_nama'];
            $byFeeder[$fName] = ($byFeeder[$fName] ?? 0) + 1;
        }
        arsort($byFeeder);

        CLI::write("\nDistribusi Calon Pemetaan per Penyulang (Top 10):", 'white');
        $topCount = 0;
        foreach ($byFeeder as $fName => $cnt) {
            CLI::write(sprintf("  - %-30s : %5d unit", $fName, $cnt), 'cyan');
            $topCount++;
            if ($topCount >= 10) break;
        }

        if (!$isExecute) {
            CLI::write("\n[DRY RUN SELESAI] Tidak ada data database yang diubah.", 'yellow');
            CLI::write("Untuk menerapkan perubahan secara permanen dengan backup JSON otomatis, jalankan:", 'white');
            CLI::write("  php spark asset:map-deterministic --execute\n", 'cyan');
            return;
        }

        if (empty($matchedCandidates)) {
            CLI::write("Tidak ada kandidat valid untuk dieksekusi.", 'yellow');
            return;
        }

        // 4. Create Pre-Mutation JSON Backup
        $backupDir = WRITEPATH . 'backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $backupTimestamp = date('Ymd_His');
        $backupFile = "{$backupDir}/asset_mapping_backup_{$backupTimestamp}.json";

        $backupPayload = [
            'timestamp'           => date('Y-m-d H:i:s'),
            'total_candidates'    => count($matchedCandidates),
            'command'             => 'asset:map-deterministic',
            'records'             => array_map(function($c) {
                return [
                    'id'                             => $c['asset_id'],
                    'penyulang_id'                   => $c['prev_feeder'],
                    'ulp_id'                         => $c['prev_ulp'],
                    'section_resolution_method'      => $c['prev_method'],
                    'intelligence_resolution_status' => $c['prev_intel'],
                ];
            }, $matchedCandidates)
        ];

        file_put_contents($backupFile, json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        CLI::write("\n[BACKUP] Snapshot data awal aman tersimpan di: {$backupFile}", 'green');

        // 5. Execute within reversible database transaction
        $db->transBegin();
        try {
            $updatedRows = 0;
            $batchSize = 500;
            $chunks = array_chunk($matchedCandidates, $batchSize);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $item) {
                    $db->table('assets')
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

            if ($db->transStatus() === false) {
                throw new \RuntimeException("Database transaction failed during update batch.");
            }

            $db->transCommit();
            CLI::write("\n[SUKSES] {$updatedRows} unit aset berhasil dipetakan ke penyulang resminya!", 'green');
            CLI::write("Pencatatan metode: section_resolution_method = 'TOPOLOGY_DETERMINISTIC_RECON'", 'cyan');
            CLI::write("Status resolusi : intelligence_resolution_status = 'RESOLVED'", 'cyan');
            CLI::write("\nJika perlu membatalkan perubahan ini, jalankan:", 'yellow');
            CLI::write("  php spark asset:rollback-deterministic --backup=" . basename($backupFile) . "\n", 'white');

        } catch (\Throwable $e) {
            $db->transRollback();
            CLI::error("\n[GAGAL] Transaksi dibatalkan (Rollback): " . $e->getMessage());
        }
    }
}
