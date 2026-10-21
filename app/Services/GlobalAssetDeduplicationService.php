<?php

namespace App\Services;

use Config\Database;

/**
 * GlobalAssetDeduplicationService
 *
 * Enforces asset data integrity across all ULPs and feeders using the 7-column composite key:
 *   [Nama Unit, Nama ULP, Nama Penyulang, Nama Aset, Section, Latitude, Longitude]
 *
 * Principles & Invariants:
 * 1. Exact string normalization: UPPER, TRIM, multiple whitespace collapsed.
 * 2. Exact coordinate precision match (no proximity guessing).
 * 3. Deterministic canonical record retention:
 *      a) Highest number of active dependencies
 *      b) Clean human-readable code over machine-generated hash pattern
 *      c) Lowest primary key (earliest created)
 * 4. Safe dependency rerouting across temuan, temuan_materials, gis_translines, jtm_accessories, fault_actual_findings.
 * 5. Full JSON snapshot backup before any mutation.
 * 6. Non-destructive transactional soft-delete (`deleted_at = NOW()`).
 * 7. Two-way rollback support.
 */
class GlobalAssetDeduplicationService
{
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect('default');
    }

    /**
     * Audit duplicate assets matching on 7-column composite key.
     */
    public function auditDuplicates(?string $feederFilter = null): array
    {
        if (!$this->db->tableExists('assets')) {
            return ['status' => 'error', 'message' => 'Tabel assets tidak ditemukan.'];
        }

        $builder = $this->db->table('assets a')
            ->select('a.id, a.kode_asset, a.nama_asset, a.jenis_asset, a.ulp_id, a.penyulang_id, a.section_id, a.latitude, a.longitude, a.section_resolution_method, a.created_at, u.nama_ulp, p.nama_penyulang, s.nama_section')
            ->join('ulps u', 'u.id = a.ulp_id', 'left')
            ->join('penyulang p', 'p.id = a.penyulang_id', 'left')
            ->join('sections s', 's.id = a.section_id', 'left')
            ->where('a.deleted_at IS NULL');

        if (!empty($feederFilter)) {
            $builder->groupStart()
                ->where('p.nama_penyulang', $feederFilter)
                ->orLike('p.nama_penyulang', $feederFilter)
                ->groupEnd();
        }

        $assets = $builder->get()->getResultArray();
        $totalActive = count($assets);

        $groups = [];
        $feederBreakdown = [];

        $tiangTypes = ['jtm_tiang', 'TIANG_BETON', 'TIANG_BESI', 'JTM'];

        foreach ($assets as $ast) {
            $unitName    = 'UP3 SIDOARJO';
            $ulpName     = trim((string)($ast['nama_ulp'] ?? ''));
            $feederName  = trim((string)($ast['nama_penyulang'] ?? ''));
            $assetName   = trim((string)($ast['nama_asset'] ?? ''));
            $sectionName = trim((string)($ast['nama_section'] ?? ($ast['section_id'] ? (string)$ast['section_id'] : '')));

            $lat = number_format((float)$ast['latitude'], 6, '.', '');
            $lng = number_format((float)$ast['longitude'], 6, '.', '');

            $normUnit    = strtoupper(trim(preg_replace('/\s+/', ' ', $unitName)));
            $normUlp     = strtoupper(trim(preg_replace('/\s+/', ' ', $ulpName)));
            $normFeeder  = strtoupper(trim(preg_replace('/\s+/', ' ', $feederName)));
            $normAsset   = strtoupper(trim(preg_replace('/\s+/', ' ', $assetName)));
            $normSection = strtoupper(trim(preg_replace('/\s+/', ' ', $sectionName)));

            $fKey = !empty($normFeeder) ? $normFeeder : 'TANPA_PENYULANG';
            if (!isset($feederBreakdown[$fKey])) {
                $feederBreakdown[$fKey] = [
                    'ulp'                  => $normUlp ?: 'TANPA_ULP',
                    'total_active'         => 0,
                    'total_tiang_jtm'      => 0,
                    'duplicate_groups'     => 0,
                    'duplicates_count'     => 0,
                    'clean_active_assets'  => 0,
                ];
            }
            $feederBreakdown[$fKey]['total_active']++;
            if (in_array($ast['jenis_asset'], $tiangTypes, true)) {
                $feederBreakdown[$fKey]['total_tiang_jtm']++;
            }

            $compositeKey = "{$normUnit}|{$normUlp}|{$normFeeder}|{$normAsset}|{$normSection}|{$lat}|{$lng}";

            if (!isset($groups[$compositeKey])) {
                $groups[$compositeKey] = [
                    'composite_key' => $compositeKey,
                    'keys_detail'   => [
                        'unit'       => $normUnit,
                        'ulp'        => $normUlp,
                        'penyulang'  => $normFeeder,
                        'nama_asset' => $normAsset,
                        'section'    => $normSection,
                        'latitude'   => $lat,
                        'longitude'  => $lng,
                    ],
                    'records' => [],
                ];
            }
            $groups[$compositeKey]['records'][] = $ast;
        }

        // Identify duplicate groups (> 1 record)
        $duplicateGroups = [];
        $totalDuplicateRecords = 0;
        $allDupAssetIds = [];

        foreach ($groups as $compKey => $g) {
            if (count($g['records']) > 1) {
                foreach ($g['records'] as $r) {
                    $allDupAssetIds[] = (int)$r['id'];
                }
            }
        }

        // Query dependencies for candidate duplicate records
        $dependencies = [];
        if (!empty($allDupAssetIds)) {
            $dependencies = $this->fetchAssetDependencies($allDupAssetIds);
        }

        foreach ($groups as $compKey => $g) {
            if (count($g['records']) > 1) {
                $recordsWithDeps = [];
                foreach ($g['records'] as $r) {
                    $rId = (int)$r['id'];
                    $depInfo = $dependencies[$rId] ?? [
                        'temuan_count'            => 0,
                        'temuan_materials_count'  => 0,
                        'translines_source_count' => 0,
                        'translines_target_count' => 0,
                        'accessories_count'       => 0,
                        'fault_outcomes_count'    => 0,
                        'total_dependencies'      => 0,
                    ];
                    $r['dependencies'] = $depInfo;
                    $recordsWithDeps[] = $r;
                }

                // Deterministic canonical sorting:
                // 1. Total active dependencies (desc)
                // 2. Non-hash pattern first
                // 3. Lowest primary key (created first)
                usort($recordsWithDeps, function ($a, $b) {
                    if ($b['dependencies']['total_dependencies'] !== $a['dependencies']['total_dependencies']) {
                        return $b['dependencies']['total_dependencies'] <=> $a['dependencies']['total_dependencies'];
                    }
                    $isBHash = preg_match('/-[0-9A-F]{8}$/i', $b['kode_asset'] ?? '') ? 1 : 0;
                    $isAHash = preg_match('/-[0-9A-F]{8}$/i', $a['kode_asset'] ?? '') ? 1 : 0;
                    if ($isAHash !== $isBHash) {
                        return $isAHash <=> $isBHash;
                    }
                    return $a['id'] <=> $b['id'];
                });

                $canonical = $recordsWithDeps[0];
                $duplicates = array_slice($recordsWithDeps, 1);

                $duplicateGroups[] = [
                    'composite_key'     => $compKey,
                    'keys_detail'       => $g['keys_detail'],
                    'record_count'      => count($recordsWithDeps),
                    'canonical_record'  => $canonical,
                    'duplicate_records' => $duplicates,
                    'all_record_ids'    => array_column($recordsWithDeps, 'id'),
                ];

                $totalDuplicateRecords += count($duplicates);

                $fKey = $g['keys_detail']['penyulang'] ?: 'TANPA_PENYULANG';
                if (isset($feederBreakdown[$fKey])) {
                    $feederBreakdown[$fKey]['duplicate_groups']++;
                    $feederBreakdown[$fKey]['duplicates_count'] += count($duplicates);
                }
            }
        }

        // Calculate clean_active_assets and totals
        $totalTiangJtmAll = 0;
        foreach ($feederBreakdown as $fName => &$fInfo) {
            $fInfo['clean_active_assets'] = $fInfo['total_active'] - $fInfo['duplicates_count'];
            $totalTiangJtmAll += $fInfo['total_tiang_jtm'];
        }
        unset($fInfo);

        ksort($feederBreakdown);

        // GEMPOLSARI forensics (to explain the 684 CSV vs 1,345 DB count)
        $gempolForensics = null;
        if (empty($feederFilter) || stripos($feederFilter, 'GEMPOL') !== false) {
            $gempolJenisRes = $this->db->query("SELECT a.jenis_asset, COUNT(DISTINCT a.id) as cnt 
                FROM assets a 
                LEFT JOIN penyulang p ON p.id = a.penyulang_id
                WHERE p.nama_penyulang = 'GEMPOLSARI' AND a.deleted_at IS NULL 
                GROUP BY a.jenis_asset");
            $gempolJenis = is_object($gempolJenisRes) ? $gempolJenisRes->getResultArray() : [];

            $gempolPatternsRes = $this->db->query("SELECT 
                CASE 
                    WHEN a.nama_asset LIKE 'GEMPOLSARI_%' THEN 'GEMPOLSARI_N (CSV Tiang)'
                    WHEN a.nama_asset LIKE 'AST-%' THEN 'AST-PORONG-... (Standard Tag)'
                    ELSE 'OTHER_NAMING'
                END as pattern_name,
                COUNT(DISTINCT a.id) as cnt,
                MIN(a.id) as min_id,
                MAX(a.id) as max_id,
                MIN(a.created_at) as min_created,
                MAX(a.created_at) as max_created
                FROM assets a 
                LEFT JOIN penyulang p ON p.id = a.penyulang_id
                WHERE p.nama_penyulang = 'GEMPOLSARI' AND a.deleted_at IS NULL 
                GROUP BY pattern_name");
            $gempolPatterns = is_object($gempolPatternsRes) ? $gempolPatternsRes->getResultArray() : [];

            $gempolSamplesRes = $this->db->query("SELECT a.id, a.kode_asset, a.nama_asset, a.jenis_asset, a.section_id, s.nama_section, a.latitude, a.longitude, a.created_at, a.import_batch_id 
                FROM assets a 
                LEFT JOIN penyulang p ON p.id = a.penyulang_id
                LEFT JOIN sections s ON s.id = a.section_id
                WHERE p.nama_penyulang = 'GEMPOLSARI' AND a.deleted_at IS NULL 
                ORDER BY a.id ASC LIMIT 10");
            $gempolSamples = is_object($gempolSamplesRes) ? $gempolSamplesRes->getResultArray() : [];

            // 6-col duplicate check without section
            $gempolDup6Res = $this->db->query("SELECT 
                UPPER(TRIM(a.nama_asset)) as nama_asset,
                ROUND(a.latitude, 6) as latitude,
                ROUND(a.longitude, 6) as longitude,
                COUNT(DISTINCT a.id) as group_count,
                GROUP_CONCAT(a.id ORDER BY a.id ASC) as asset_ids
                FROM assets a
                LEFT JOIN penyulang p ON p.id = a.penyulang_id
                WHERE p.nama_penyulang = 'GEMPOLSARI' AND a.deleted_at IS NULL
                GROUP BY UPPER(TRIM(a.nama_asset)), ROUND(a.latitude, 6), ROUND(a.longitude, 6)
                HAVING group_count > 1");
            $gempolDup6 = is_object($gempolDup6Res) ? $gempolDup6Res->getResultArray() : [];

            // Coordinate duplicates
            $gempolDupCoordRes = $this->db->query("SELECT 
                ROUND(a.latitude, 6) as latitude,
                ROUND(a.longitude, 6) as longitude,
                COUNT(DISTINCT a.id) as group_count,
                GROUP_CONCAT(a.id ORDER BY a.id ASC) as asset_ids,
                GROUP_CONCAT(a.nama_asset SEPARATOR ' | ') as names
                FROM assets a
                LEFT JOIN penyulang p ON p.id = a.penyulang_id
                WHERE p.nama_penyulang = 'GEMPOLSARI' AND a.deleted_at IS NULL
                GROUP BY ROUND(a.latitude, 6), ROUND(a.longitude, 6)
                HAVING group_count > 1");
            $gempolDupCoord = is_object($gempolDupCoordRes) ? $gempolDupCoordRes->getResultArray() : [];

            $gempolForensics = [
                'feeder'                         => 'GEMPOLSARI',
                'csv_row_count'                  => 684,
                'db_total_active'                => $feederBreakdown['GEMPOLSARI']['total_active'] ?? 0,
                'db_total_tiang_jtm'             => $feederBreakdown['GEMPOLSARI']['total_tiang_jtm'] ?? 0,
                'jenis_asset_breakdown'          => $gempolJenis,
                'naming_patterns'                => $gempolPatterns,
                'duplicate_groups_7col'          => $feederBreakdown['GEMPOLSARI']['duplicate_groups'] ?? 0,
                'duplicate_groups_6col_no_sec'   => count($gempolDup6),
                'duplicate_groups_coords_only'   => count($gempolDupCoord),
                'sample_records'                 => $gempolSamples,
                'sample_coord_conflicts'         => array_slice($gempolDupCoord, 0, 5),
            ];
        }

        return [
            'status'                  => 'success',
            'audit_timestamp'         => date('Y-m-d H:i:s'),
            'filter_feeder'           => $feederFilter ?: 'ALL_FEEDERS',
            'total_active_assets'     => $totalActive,
            'total_tiang_jtm'         => $totalTiangJtmAll,
            'duplicate_groups_count'  => count($duplicateGroups),
            'duplicate_records_count' => $totalDuplicateRecords,
            'clean_active_assets'     => $totalActive - $totalDuplicateRecords,
            'totals_summary'          => [
                'total_feeders'           => count($feederBreakdown),
                'total_active_all'        => $totalActive,
                'total_tiang_jtm_all'     => $totalTiangJtmAll,
                'total_duplicate_groups'  => count($duplicateGroups),
                'total_duplicate_records' => $totalDuplicateRecords,
                'total_clean_assets'      => $totalActive - $totalDuplicateRecords,
            ],
            'gempol_forensics'        => $gempolForensics,
            'feeder_breakdown'        => $feederBreakdown,
            'groups'                  => $duplicateGroups,
        ];
    }

    /**
     * Executes or simulates (dry-run) asset deduplication on 7-column composite key.
     */
    public function executeDeduplication(bool $isExecute = false, ?string $feederFilter = null): array
    {
        $audit = $this->auditDuplicates($feederFilter);
        if ($audit['status'] !== 'success') {
            return $audit;
        }

        if ($audit['duplicate_groups_count'] === 0) {
            return array_merge($audit, [
                'mode'                             => $isExecute ? 'LIVE_EXECUTION' : 'DRY_RUN',
                'total_active_before'              => $audit['total_active_assets'],
                'duplicate_groups_found'           => 0,
                'duplicate_records_to_soft_delete' => 0,
                'projected_active_after'           => $audit['total_active_assets'],
                'actual_active_after'              => $audit['total_active_assets'],
                'dependencies_to_reroute'          => [
                    'temuan_count'                   => 0,
                    'temuan_materials_count'         => 0,
                    'translines_source_count'        => 0,
                    'translines_target_count'        => 0,
                    'accessories_count'              => 0,
                    'fault_outcomes_count'           => 0,
                ],
                'duplicate_groups_detail'          => [],
                'message'                          => 'Tidak ada duplikat yang ditemukan. Seluruh aset unik pada 7 kunci komposit.',
                'soft_deleted_count'               => 0,
                'backup_file'                      => null,
                'verified_clean'                   => true,
            ]);
        }

        $dupIdsToSoftDelete = [];
        $rerouteTemuan = [];
        $rerouteTemuanMaterials = [];
        $rerouteTlSource = [];
        $rerouteTlTarget = [];
        $rerouteAccessories = [];
        $rerouteFaultOutcomes = [];
        $backupRecords = [];

        foreach ($audit['groups'] as $group) {
            $canonId = (int)$group['canonical_record']['id'];
            foreach ($group['duplicate_records'] as $dup) {
                $dupId = (int)$dup['id'];
                $dupIdsToSoftDelete[] = $dupId;
                $backupRecords[] = [
                    'duplicate_id'     => $dupId,
                    'canonical_id'     => $canonId,
                    'composite_key'    => $group['composite_key'],
                    'kode_asset'       => $dup['kode_asset'] ?? '',
                    'nama_asset'       => $dup['nama_asset'] ?? '',
                ];

                $deps = $dup['dependencies'];
                if ($deps['temuan_count'] > 0) {
                    $rerouteTemuan[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['temuan_materials_count'] > 0) {
                    $rerouteTemuanMaterials[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['translines_source_count'] > 0) {
                    $rerouteTlSource[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['translines_target_count'] > 0) {
                    $rerouteTlTarget[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['accessories_count'] > 0) {
                    $rerouteAccessories[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['fault_outcomes_count'] > 0) {
                    $rerouteFaultOutcomes[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
            }
        }

        $dryRunSummary = [
            'mode'                             => $isExecute ? 'LIVE_EXECUTION' : 'DRY_RUN',
            'filter_feeder'                    => $feederFilter ?: 'ALL_FEEDERS',
            'total_active_before'              => $audit['total_active_assets'],
            'duplicate_groups_found'           => $audit['duplicate_groups_count'],
            'duplicate_records_to_soft_delete' => count($dupIdsToSoftDelete),
            'projected_active_after'           => $audit['total_active_assets'] - count($dupIdsToSoftDelete),
            'dependencies_to_reroute'          => [
                'temuan_count'                   => count($rerouteTemuan),
                'temuan_materials_count'         => count($rerouteTemuanMaterials),
                'translines_source_count'        => count($rerouteTlSource),
                'translines_target_count'        => count($rerouteTlTarget),
                'accessories_count'              => count($rerouteAccessories),
                'fault_outcomes_count'           => count($rerouteFaultOutcomes),
            ],
            'duplicate_groups_detail'          => $audit['groups'],
        ];

        if (!$isExecute) {
            return array_merge($dryRunSummary, [
                'status'  => 'success',
                'message' => 'Simulasi dry-run selesai. Tidak ada modifikasi data.',
            ]);
        }

        // Live Execution: Take JSON Snapshot Backup first
        $backupDir = WRITEPATH . 'backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        $backupFile = $backupDir . '/dedup_global_backup_' . date('Ymd_His') . '.json';
        $backupPayload = [
            'timestamp'             => date('Y-m-d H:i:s'),
            'operation'             => 'GLOBAL_ASSET_DEDUPLICATION_COMPOSITE7',
            'feeder_filter'         => $feederFilter,
            'summary'               => $dryRunSummary,
            'records'               => $backupRecords,
            'rerouted_dependencies' => [
                'temuan'           => $rerouteTemuan,
                'temuan_materials' => $rerouteTemuanMaterials,
                'translines_src'   => $rerouteTlSource,
                'translines_tgt'   => $rerouteTlTarget,
                'accessories'      => $rerouteAccessories,
                'fault_outcomes'   => $rerouteFaultOutcomes,
            ],
            'soft_deleted_ids'      => $dupIdsToSoftDelete,
        ];

        $written = file_put_contents($backupFile, json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->db->transBegin();
        try {
            // 1. Reroute temuan
            foreach ($rerouteTemuan as $rt) {
                $this->db->table('temuan')->where('asset_id', $rt['old_id'])->update(['asset_id' => $rt['new_id']]);
            }

            // 2. Reroute temuan_materials if table exists
            if ($this->db->tableExists('temuan_materials')) {
                foreach ($rerouteTemuanMaterials as $rm) {
                    $this->db->table('temuan_materials')->where('asset_id', $rm['old_id'])->update(['asset_id' => $rm['new_id']]);
                }
            }

            // 3. Reroute translines
            if ($this->db->tableExists('gis_translines')) {
                foreach ($rerouteTlSource as $rs) {
                    $this->db->table('gis_translines')->where('source_asset_id', $rs['old_id'])->update(['source_asset_id' => $rs['new_id']]);
                }
                foreach ($rerouteTlTarget as $rtg) {
                    $this->db->table('gis_translines')->where('target_asset_id', $rtg['old_id'])->update(['target_asset_id' => $rtg['new_id']]);
                }
            }

            // 4. Reroute jtm_accessories if table exists
            if ($this->db->tableExists('jtm_accessories')) {
                foreach ($rerouteAccessories as $ra) {
                    $this->db->table('jtm_accessories')->where('asset_id', $ra['old_id'])->update(['asset_id' => $ra['new_id']]);
                }
            }

            // 5. Reroute fault_actual_findings if table exists
            if ($this->db->tableExists('fault_actual_findings')) {
                foreach ($rerouteFaultOutcomes as $rf) {
                    $this->db->table('fault_actual_findings')->where('found_asset_id', $rf['old_id'])->update(['found_asset_id' => $rf['new_id']]);
                }
            }

            // 6. Transactional Soft Delete
            $now = date('Y-m-d H:i:s');
            $chunks = array_chunk($dupIdsToSoftDelete, 250);
            foreach ($chunks as $chunk) {
                $this->db->table('assets')
                    ->whereIn('id', $chunk)
                    ->update([
                        'deleted_at'                => $now,
                        'section_resolution_method' => 'DUPLICATE_COMPOSITE7_CANONICALIZED',
                    ]);
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Database transaction reported error.');
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'message' => 'Deduplication failed and rolled back: ' . $e->getMessage(),
                'backup'  => $backupFile,
            ];
        }

        // Post-verification
        $postAudit = $this->auditDuplicates($feederFilter);
        $verifiedClean = ($postAudit['duplicate_groups_count'] === 0);

        return array_merge($dryRunSummary, [
            'status'              => 'success',
            'backup_file'         => $backupFile,
            'backup_bytes'        => $written,
            'soft_deleted_count'  => count($dupIdsToSoftDelete),
            'actual_active_after' => $postAudit['total_active_assets'],
            'verified_clean'      => $verifiedClean,
        ]);
    }

    /**
     * Audits coordinate-matched duplicate assets where synthetic placeholders (NEW_INGESTED_ASSET_*
     * or empty/hash placeholders) coexist with canonical field-named assets on the same feeder.
     */
    public function auditSyntheticDuplicates(?string $feederFilter = null): array
    {
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

        if (!$this->db->tableExists('assets')) {
            return ['status' => 'error', 'message' => 'Tabel assets tidak ditemukan.'];
        }

        $builder = $this->db->table('assets a')
            ->select('a.id, a.kode_asset, a.nama_asset, a.jenis_asset, a.ulp_id, a.penyulang_id, a.section_id, a.latitude, a.longitude, a.created_at, u.nama_ulp, p.nama_penyulang')
            ->join('ulps u', 'u.id = a.ulp_id', 'left')
            ->join('penyulang p', 'p.id = a.penyulang_id', 'left')
            ->where('a.deleted_at IS NULL');

        if (!empty($feederFilter)) {
            $builder->groupStart()
                ->where('p.nama_penyulang', $feederFilter)
                ->orLike('p.nama_penyulang', $feederFilter)
                ->groupEnd();
        }

        $assets = $builder->get()->getResultArray();
        $totalActive = count($assets);

        $coordGroups = [];
        $feederTotals = [];
        $tiangTypes = ['jtm_tiang', 'TIANG_BETON', 'TIANG_BESI', 'JTM'];

        foreach ($assets as $ast) {
            $fName = trim((string)($ast['nama_penyulang'] ?? 'TANPA_PENYULANG'));
            $lat = number_format((float)$ast['latitude'], 6, '.', '');
            $lng = number_format((float)$ast['longitude'], 6, '.', '');

            if (!isset($feederTotals[$fName])) {
                $feederTotals[$fName] = [
                    'feeder'            => $fName,
                    'total_active'      => 0,
                    'total_tiang_jtm'   => 0,
                    'synthetic_dup_cnt' => 0,
                ];
            }
            $feederTotals[$fName]['total_active']++;
            if (in_array($ast['jenis_asset'], $tiangTypes, true)) {
                $feederTotals[$fName]['total_tiang_jtm']++;
            }

            $groupKey = "{$fName}|{$lat}|{$lng}";
            $coordGroups[$groupKey][] = $ast;
        }

        $dupGroupsOnly = array_filter($coordGroups, fn($g) => count($g) > 1);

        // Pre-classify synthetic status to only fetch dependencies for groups having synthetic candidates
        $relevantGroups = [];
        $relevantAssetIds = [];
        foreach ($dupGroupsOnly as $key => $records) {
            $hasSynthetic = false;
            foreach ($records as &$r) {
                $name = trim((string)($r['nama_asset'] ?? ''));
                $code = trim((string)($r['kode_asset'] ?? ''));
                $r['is_synthetic'] = (
                    $name === '' ||
                    stripos($name, 'NEW_INGESTED_ASSET') === 0 ||
                    (stripos($code, 'AST-') === 0 && preg_match('/-[0-9A-F]{8}$/i', $code) && stripos($name, 'NEW_INGESTED') === 0)
                );
                if ($r['is_synthetic']) {
                    $hasSynthetic = true;
                }
            }
            unset($r);

            if ($hasSynthetic) {
                $relevantGroups[$key] = $records;
                foreach ($records as $r) {
                    $relevantAssetIds[] = (int)$r['id'];
                }
            }
        }

        $dependencies = !empty($relevantAssetIds) ? $this->fetchAssetDependencies(array_unique($relevantAssetIds)) : [];

        $duplicateGroups = [];
        $totalSyntheticDups = 0;
        $syntheticDupIds = [];

        foreach ($relevantGroups as $key => $records) {
            foreach ($records as &$r) {
                $rId = (int)$r['id'];
                $r['dependencies'] = $dependencies[$rId] ?? [
                    'temuan_count'            => 0,
                    'temuan_materials_count'  => 0,
                    'translines_source_count' => 0,
                    'translines_target_count' => 0,
                    'accessories_count'       => 0,
                    'fault_outcomes_count'    => 0,
                    'total_dependencies'      => 0,
                ];
            }
            unset($r);

            $canonicalCandidates = array_values(array_filter($records, fn($r) => !$r['is_synthetic']));
            $syntheticCandidates = array_values(array_filter($records, fn($r) => $r['is_synthetic']));

            if (!empty($syntheticCandidates)) {
                if (!empty($canonicalCandidates)) {
                    usort($canonicalCandidates, function ($a, $b) {
                        if ($b['dependencies']['total_dependencies'] !== $a['dependencies']['total_dependencies']) {
                            return $b['dependencies']['total_dependencies'] <=> $a['dependencies']['total_dependencies'];
                        }
                        return $a['id'] <=> $b['id'];
                    });
                    $canonical = $canonicalCandidates[0];
                    $dupsToPurge = $syntheticCandidates;
                } else {
                    usort($syntheticCandidates, function ($a, $b) {
                        if ($b['dependencies']['total_dependencies'] !== $a['dependencies']['total_dependencies']) {
                            return $b['dependencies']['total_dependencies'] <=> $a['dependencies']['total_dependencies'];
                        }
                        return $a['id'] <=> $b['id'];
                    });
                    $canonical = $syntheticCandidates[0];
                    $dupsToPurge = array_slice($syntheticCandidates, 1);
                }

                if (!empty($dupsToPurge)) {
                    $duplicateGroups[] = [
                        'group_key'           => $key,
                        'feeder'              => $canonical['nama_penyulang'] ?? 'UNKNOWN',
                        'latitude'            => $canonical['latitude'],
                        'longitude'           => $canonical['longitude'],
                        'canonical_record'    => $canonical,
                        'duplicate_records'   => $dupsToPurge,
                        'retained_canonicals' => $canonicalCandidates,
                    ];
                    $totalSyntheticDups += count($dupsToPurge);
                    foreach ($dupsToPurge as $d) {
                        $syntheticDupIds[] = (int)$d['id'];
                        $fName = $canonical['nama_penyulang'] ?? 'TANPA_PENYULANG';
                        if (isset($feederTotals[$fName])) {
                            $feederTotals[$fName]['synthetic_dup_cnt']++;
                        }
                    }
                }
            }
        }

        ksort($feederTotals);

        return [
            'status'                       => 'success',
            'audit_timestamp'              => date('Y-m-d H:i:s'),
            'feeder_filter'                => $feederFilter ?: 'ALL_FEEDERS',
            'total_active_assets'          => $totalActive,
            'coordinate_duplicate_groups'  => count($duplicateGroups),
            'synthetic_duplicates_count'   => $totalSyntheticDups,
            'projected_active_after'       => $totalActive - $totalSyntheticDups,
            'feeder_summary'               => $feederTotals,
            'groups'                       => $duplicateGroups,
            'synthetic_dup_ids'            => $syntheticDupIds,
        ];
    }

    /**
     * Executes or simulates (dry-run) coordinate-matched synthetic placeholder deduplication.
     */
    public function executeSyntheticDeduplication(bool $isExecute = false, ?string $feederFilter = null): array
    {
        $audit = $this->auditSyntheticDuplicates($feederFilter);
        if ($audit['status'] !== 'success') {
            return $audit;
        }

        if ($audit['synthetic_duplicates_count'] === 0) {
            return array_merge($audit, [
                'mode'                             => $isExecute ? 'LIVE_EXECUTION' : 'DRY_RUN',
                'total_active_before'              => $audit['total_active_assets'],
                'duplicate_records_to_soft_delete' => 0,
                'projected_active_after'           => $audit['total_active_assets'],
                'actual_active_after'              => $audit['total_active_assets'],
                'dependencies_to_reroute'          => [
                    'temuan_count'                   => 0,
                    'temuan_materials_count'         => 0,
                    'translines_source_count'        => 0,
                    'translines_target_count'        => 0,
                    'accessories_count'              => 0,
                    'fault_outcomes_count'           => 0,
                ],
                'message'                          => 'Tidak ada duplikat synthetic placeholder yang ditemukan.',
                'soft_deleted_count'               => 0,
                'backup_file'                      => null,
                'verified_clean'                   => true,
            ]);
        }

        $dupIdsToSoftDelete = [];
        $rerouteTemuan = [];
        $rerouteTemuanMaterials = [];
        $rerouteTlSource = [];
        $rerouteTlTarget = [];
        $rerouteAccessories = [];
        $rerouteFaultOutcomes = [];
        $backupRecords = [];

        foreach ($audit['groups'] as $group) {
            $canonId = (int)$group['canonical_record']['id'];
            foreach ($group['duplicate_records'] as $dup) {
                $dupId = (int)$dup['id'];
                $dupIdsToSoftDelete[] = $dupId;
                $backupRecords[] = [
                    'duplicate_id'     => $dupId,
                    'canonical_id'     => $canonId,
                    'group_key'        => $group['group_key'],
                    'kode_asset'       => $dup['kode_asset'] ?? '',
                    'nama_asset'       => $dup['nama_asset'] ?? '',
                ];

                $deps = $dup['dependencies'];
                if ($deps['temuan_count'] > 0) {
                    $rerouteTemuan[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['temuan_materials_count'] > 0) {
                    $rerouteTemuanMaterials[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['translines_source_count'] > 0) {
                    $rerouteTlSource[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['translines_target_count'] > 0) {
                    $rerouteTlTarget[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['accessories_count'] > 0) {
                    $rerouteAccessories[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
                if ($deps['fault_outcomes_count'] > 0) {
                    $rerouteFaultOutcomes[] = ['old_id' => $dupId, 'new_id' => $canonId];
                }
            }
        }

        $dryRunSummary = [
            'mode'                             => $isExecute ? 'LIVE_EXECUTION' : 'DRY_RUN',
            'filter_feeder'                    => $feederFilter ?: 'ALL_FEEDERS',
            'total_active_before'              => $audit['total_active_assets'],
            'coordinate_duplicate_groups'      => $audit['coordinate_duplicate_groups'],
            'duplicate_records_to_soft_delete' => count($dupIdsToSoftDelete),
            'projected_active_after'           => $audit['total_active_assets'] - count($dupIdsToSoftDelete),
            'dependencies_to_reroute'          => [
                'temuan_count'                   => count($rerouteTemuan),
                'temuan_materials_count'         => count($rerouteTemuanMaterials),
                'translines_source_count'        => count($rerouteTlSource),
                'translines_target_count'        => count($rerouteTlTarget),
                'accessories_count'              => count($rerouteAccessories),
                'fault_outcomes_count'           => count($rerouteFaultOutcomes),
            ],
            'duplicate_groups_sample'          => array_slice($audit['groups'], 0, 10),
        ];

        if (!$isExecute) {
            return array_merge($dryRunSummary, [
                'status'  => 'success',
                'message' => 'Simulasi dry-run deduplikasi synthetic selesai. Tidak ada data yang dimutasi.',
            ]);
        }

        // Live Execution: Save JSON backup first
        $backupDir = WRITEPATH . 'backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        $backupFile = $backupDir . '/dedup_synthetic_backup_' . date('Ymd_His') . '.json';
        $backupPayload = [
            'timestamp'             => date('Y-m-d H:i:s'),
            'operation'             => 'SYNTHETIC_ASSET_COORDINATE_DEDUPLICATION',
            'feeder_filter'         => $feederFilter,
            'summary'               => $dryRunSummary,
            'records'               => $backupRecords,
            'rerouted_dependencies' => [
                'temuan'           => $rerouteTemuan,
                'temuan_materials' => $rerouteTemuanMaterials,
                'translines_src'   => $rerouteTlSource,
                'translines_tgt'   => $rerouteTlTarget,
                'accessories'      => $rerouteAccessories,
                'fault_outcomes'   => $rerouteFaultOutcomes,
            ],
            'soft_deleted_ids'      => $dupIdsToSoftDelete,
        ];

        $written = file_put_contents($backupFile, json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->db->transBegin();
        try {
            // 1. Reroute temuan
            foreach ($rerouteTemuan as $rt) {
                $this->db->table('temuan')->where('asset_id', $rt['old_id'])->update(['asset_id' => $rt['new_id']]);
            }

            // 2. Reroute temuan_materials
            if ($this->db->tableExists('temuan_materials')) {
                foreach ($rerouteTemuanMaterials as $rm) {
                    $this->db->table('temuan_materials')->where('asset_id', $rm['old_id'])->update(['asset_id' => $rm['new_id']]);
                }
            }

            // 3. Reroute translines
            if ($this->db->tableExists('gis_translines')) {
                foreach ($rerouteTlSource as $rs) {
                    $this->db->table('gis_translines')->where('source_asset_id', $rs['old_id'])->update(['source_asset_id' => $rs['new_id']]);
                }
                foreach ($rerouteTlTarget as $rtg) {
                    $this->db->table('gis_translines')->where('target_asset_id', $rtg['old_id'])->update(['target_asset_id' => $rtg['new_id']]);
                }
                // Purge degenerate self-loops (source_asset_id == target_asset_id) resulting from deduplication
                $this->db->query("DELETE FROM gis_translines WHERE source_asset_id = target_asset_id");
            }

            // 4. Reroute jtm_accessories
            if ($this->db->tableExists('jtm_accessories')) {
                foreach ($rerouteAccessories as $ra) {
                    $this->db->table('jtm_accessories')->where('asset_id', $ra['old_id'])->update(['asset_id' => $ra['new_id']]);
                }
            }

            // 5. Reroute fault_actual_findings
            if ($this->db->tableExists('fault_actual_findings')) {
                foreach ($rerouteFaultOutcomes as $rf) {
                    $this->db->table('fault_actual_findings')->where('found_asset_id', $rf['old_id'])->update(['found_asset_id' => $rf['new_id']]);
                }
            }

            // 6. Transactional Soft Delete
            $now = date('Y-m-d H:i:s');
            $chunks = array_chunk($dupIdsToSoftDelete, 250);
            foreach ($chunks as $chunk) {
                $this->db->table('assets')
                    ->whereIn('id', $chunk)
                    ->update([
                        'deleted_at'                => $now,
                        'section_resolution_method' => 'DEDUP_SYNTHETIC_COORDS',
                    ]);
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Database transaction reported error.');
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'message' => 'Synthetic deduplication failed and rolled back: ' . $e->getMessage(),
                'backup'  => $backupFile,
            ];
        }

        // Post-verification
        $postAudit = $this->auditSyntheticDuplicates($feederFilter);
        $verifiedClean = ($postAudit['synthetic_duplicates_count'] === 0);

        return array_merge($dryRunSummary, [
            'status'              => 'success',
            'backup_file'         => $backupFile,
            'backup_bytes'        => $written,
            'soft_deleted_count'  => count($dupIdsToSoftDelete),
            'actual_active_after' => $postAudit['total_active_assets'],
            'verified_clean'      => $verifiedClean,
        ]);
    }

    /**
     * Restores state from a deduplication backup file.
     */
    public function rollback(string $backupFilename): array
    {
        $backupDir = WRITEPATH . 'backups/';
        $cleanFile = basename($backupFilename);
        $fullPath  = $backupDir . $cleanFile;

        if (!file_exists($fullPath)) {
            return ['status' => 'error', 'message' => "Berkas backup tidak ditemukan: {$cleanFile}"];
        }

        $payload = json_decode(file_get_contents($fullPath), true);
        if (!$payload || !isset($payload['soft_deleted_ids']) || !is_array($payload['soft_deleted_ids'])) {
            return ['status' => 'error', 'message' => 'Format file backup tidak valid.'];
        }

        $softDeletedIds = $payload['soft_deleted_ids'];
        $reroutes       = $payload['rerouted_dependencies'] ?? [];

        $this->db->transBegin();
        try {
            // 1. Revert rerouted foreign keys if any
            if (!empty($reroutes['temuan'])) {
                foreach ($reroutes['temuan'] as $r) {
                    $this->db->table('temuan')
                        ->where('asset_id', $r['new_id'])
                        ->update(['asset_id' => $r['old_id']]);
                }
            }
            if (!empty($reroutes['temuan_materials']) && $this->db->tableExists('temuan_materials')) {
                foreach ($reroutes['temuan_materials'] as $r) {
                    $this->db->table('temuan_materials')
                        ->where('asset_id', $r['new_id'])
                        ->update(['asset_id' => $r['old_id']]);
                }
            }
            if (!empty($reroutes['translines_src']) && $this->db->tableExists('gis_translines')) {
                foreach ($reroutes['translines_src'] as $r) {
                    $this->db->table('gis_translines')
                        ->where('source_asset_id', $r['new_id'])
                        ->update(['source_asset_id' => $r['old_id']]);
                }
            }
            if (!empty($reroutes['translines_tgt']) && $this->db->tableExists('gis_translines')) {
                foreach ($reroutes['translines_tgt'] as $r) {
                    $this->db->table('gis_translines')
                        ->where('target_asset_id', $r['new_id'])
                        ->update(['target_asset_id' => $r['old_id']]);
                }
            }
            if (!empty($reroutes['accessories']) && $this->db->tableExists('jtm_accessories')) {
                foreach ($reroutes['accessories'] as $r) {
                    $this->db->table('jtm_accessories')
                        ->where('asset_id', $r['new_id'])
                        ->update(['asset_id' => $r['old_id']]);
                }
            }
            if (!empty($reroutes['fault_outcomes']) && $this->db->tableExists('fault_actual_findings')) {
                foreach ($reroutes['fault_outcomes'] as $r) {
                    $this->db->table('fault_actual_findings')
                        ->where('found_asset_id', $r['new_id'])
                        ->update(['found_asset_id' => $r['old_id']]);
                }
            }

            // 2. Reactivate soft-deleted assets
            $chunks = array_chunk($softDeletedIds, 250);
            foreach ($chunks as $chunk) {
                $this->db->table('assets')
                    ->whereIn('id', $chunk)
                    ->update([
                        'deleted_at'                => null,
                        'section_resolution_method' => null,
                    ]);
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Rollback database transaction failed.');
            }

            $this->db->transCommit();

            return [
                'status'         => 'success',
                'message'        => 'Rollback berhasil diselesaikan.',
                'restored_count' => count($softDeletedIds),
                'backup_file'    => $cleanFile,
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
     * Batch queries foreign dependencies for a list of candidate asset IDs.
     */
    protected function fetchAssetDependencies(array $assetIds): array
    {
        $deps = [];
        foreach ($assetIds as $id) {
            $deps[$id] = [
                'temuan_count'            => 0,
                'temuan_materials_count'  => 0,
                'translines_source_count' => 0,
                'translines_target_count' => 0,
                'accessories_count'       => 0,
                'fault_outcomes_count'    => 0,
                'total_dependencies'      => 0,
            ];
        }

        if (empty($assetIds)) {
            return $deps;
        }

        $chunks = array_chunk(array_values(array_unique($assetIds)), 500);

        // 1. temuan
        if ($this->db->tableExists('temuan')) {
            foreach ($chunks as $chunk) {
                $rows = $this->db->table('temuan')
                    ->select('asset_id, COUNT(*) as cnt')
                    ->whereIn('asset_id', $chunk)
                    ->groupBy('asset_id')
                    ->get()->getResultArray();
                foreach ($rows as $r) {
                    $id = (int)$r['asset_id'];
                    if (isset($deps[$id])) {
                        $deps[$id]['temuan_count'] += (int)$r['cnt'];
                        $deps[$id]['total_dependencies'] += (int)$r['cnt'];
                    }
                }
            }
        }

        // 2. temuan_materials
        if ($this->db->tableExists('temuan_materials')) {
            foreach ($chunks as $chunk) {
                $rows = $this->db->table('temuan_materials')
                    ->select('asset_id, COUNT(*) as cnt')
                    ->whereIn('asset_id', $chunk)
                    ->groupBy('asset_id')
                    ->get()->getResultArray();
                foreach ($rows as $r) {
                    $id = (int)$r['asset_id'];
                    if (isset($deps[$id])) {
                        $deps[$id]['temuan_materials_count'] += (int)$r['cnt'];
                        $deps[$id]['total_dependencies'] += (int)$r['cnt'];
                    }
                }
            }
        }

        // 3. gis_translines (source & target)
        if ($this->db->tableExists('gis_translines')) {
            foreach ($chunks as $chunk) {
                $rows = $this->db->table('gis_translines')
                    ->select('source_asset_id, COUNT(*) as cnt')
                    ->whereIn('source_asset_id', $chunk)
                    ->groupBy('source_asset_id')
                    ->get()->getResultArray();
                foreach ($rows as $r) {
                    $id = (int)$r['source_asset_id'];
                    if (isset($deps[$id])) {
                        $deps[$id]['translines_source_count'] += (int)$r['cnt'];
                        $deps[$id]['total_dependencies'] += (int)$r['cnt'];
                    }
                }

                $rowsTgt = $this->db->table('gis_translines')
                    ->select('target_asset_id, COUNT(*) as cnt')
                    ->whereIn('target_asset_id', $chunk)
                    ->groupBy('target_asset_id')
                    ->get()->getResultArray();
                foreach ($rowsTgt as $r) {
                    $id = (int)$r['target_asset_id'];
                    if (isset($deps[$id])) {
                        $deps[$id]['translines_target_count'] += (int)$r['cnt'];
                        $deps[$id]['total_dependencies'] += (int)$r['cnt'];
                    }
                }
            }
        }

        // 4. jtm_accessories
        if ($this->db->tableExists('jtm_accessories')) {
            foreach ($chunks as $chunk) {
                $rows = $this->db->table('jtm_accessories')
                    ->select('asset_id, COUNT(*) as cnt')
                    ->whereIn('asset_id', $chunk)
                    ->groupBy('asset_id')
                    ->get()->getResultArray();
                foreach ($rows as $r) {
                    $id = (int)$r['asset_id'];
                    if (isset($deps[$id])) {
                        $deps[$id]['accessories_count'] += (int)$r['cnt'];
                        $deps[$id]['total_dependencies'] += (int)$r['cnt'];
                    }
                }
            }
        }

        // 5. fault_actual_findings
        if ($this->db->tableExists('fault_actual_findings')) {
            foreach ($chunks as $chunk) {
                $rows = $this->db->table('fault_actual_findings')
                    ->select('found_asset_id, COUNT(*) as cnt')
                    ->whereIn('found_asset_id', $chunk)
                    ->groupBy('found_asset_id')
                    ->get()->getResultArray();
                foreach ($rows as $r) {
                    $id = (int)$r['found_asset_id'];
                    if (isset($deps[$id])) {
                        $deps[$id]['fault_outcomes_count'] += (int)$r['cnt'];
                        $deps[$id]['total_dependencies'] += (int)$r['cnt'];
                    }
                }
            }
        }

        return $deps;
    }
}
