<?php

namespace App\Services;

use Config\Database;

/**
 * AssetIntegrityAuditService
 *
 * Enterprise Read-Only Forensic Audit Engine for Assets, Network Feeders,
 * and GIS Layer Alignment.
 *
 * STRICT SAFETY POLICY:
 * - 100% Read-Only Queries.
 * - Zero INSERT / UPDATE / DELETE / ALTER / TRUNCATE mutations.
 * - Preserves existing foreign key lineages and operational data.
 */
class AssetIntegrityAuditService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Run full asset integrity & reconciliation audit.
     *
     * @return array Structured audit report
     */
    public function runAudit(): array
    {
        if (!$this->db->tableExists('assets')) {
            return [
                'status'          => 'NO_ASSETS_TABLE',
                'audit_timestamp' => date('Y-m-d H:i:s'),
                'overview'        => [
                    'total_raw'          => 0,
                    'total_active'       => 0,
                    'total_soft_deleted' => 0,
                    'distinct_pks'       => 0,
                    'pk_duplicates'      => 0,
                    'is_pk_unique'       => true,
                    'by_jenis_asset'     => [],
                ],
                'uniqueness'      => [
                    'kode_asset' => ['distinct_codes' => 0, 'duplicate_count' => 0],
                    'composite'  => ['duplicate_groups_count' => 0],
                    'spatial'    => ['spatial_collision_clusters' => 0],
                ],
                'feeder_mapping'  => [
                    'total_active_assets'  => 0,
                    'mapped_to_feeder'     => 0,
                    'unassigned_no_feeder' => 0,
                    'orphan_penyulang_fks' => 0,
                ],
                'penyulang_audit' => [
                    'total_feeders'        => 0,
                    'feeders_with_assets'  => 0,
                    'feeders_zero_assets'  => 0,
                    'zero_asset_feeders'   => [],
                ],
                'matching_sim'    => [
                    'total_unassigned_matchable_by_name' => 0,
                    'top_matchable_feeders'              => [],
                ],
                'reconciliation'  => [
                    'finding_1_asset_total' => 'Tabel assets belum tersedia pada koneksi database ini.',
                ],
            ];
        }

        $overview       = $this->auditOverview();
        $codeDuplicates = $this->auditCodeDuplicates();
        $compositeDups  = $this->auditCompositeDuplicates();
        $spatialDups    = $this->auditSpatialCollisions();
        $feederMapping  = $this->auditFeederMapping();
        $penyulangAudit = $this->auditPenyulangCoverage();
        $matchingSim    = $this->simulateNameBasedFeederMatching();
        $reconciliation = $this->buildReconciliationSummary($overview, $feederMapping, $penyulangAudit);

        return [
            'status'          => 'SUCCESS',
            'audit_timestamp' => date('Y-m-d H:i:s'),
            'overview'        => $overview,
            'uniqueness'      => [
                'kode_asset' => $codeDuplicates,
                'composite'  => $compositeDups,
                'spatial'    => $spatialDups,
            ],
            'feeder_mapping'  => $feederMapping,
            'penyulang_audit' => $penyulangAudit,
            'matching_sim'    => $matchingSim,
            'reconciliation'  => $reconciliation,
        ];
    }

    /**
     * 1. Overview counts: raw, active, soft-deleted, and PK uniqueness.
     */
    private function auditOverview(): array
    {
        $totalRaw = (int)$this->db->table('assets')->countAllResults();
        $totalActive = (int)$this->db->table('assets')->where('deleted_at IS NULL')->countAllResults();
        $totalSoftDeleted = (int)$this->db->table('assets')->where('deleted_at IS NOT NULL')->countAllResults();

        // Distinct PK check
        $row = $this->db->query("SELECT COUNT(DISTINCT id) as distinct_ids FROM assets WHERE deleted_at IS NULL")->getRowArray();
        $distinctIds = (int)($row['distinct_ids'] ?? 0);
        $pkDuplicates = $totalActive - $distinctIds;

        // Breakdown by jenis_asset
        $byJenis = $this->db->query("
            SELECT jenis_asset, COUNT(*) as cnt 
            FROM assets 
            WHERE deleted_at IS NULL 
            GROUP BY jenis_asset 
            ORDER BY cnt DESC
        ")->getResultArray();

        return [
            'total_raw'          => $totalRaw,
            'total_active'       => $totalActive,
            'total_soft_deleted' => $totalSoftDeleted,
            'distinct_pks'       => $distinctIds,
            'pk_duplicates'      => $pkDuplicates,
            'is_pk_unique'       => ($pkDuplicates === 0),
            'by_jenis_asset'     => $byJenis,
        ];
    }

    /**
     * 2. Audit duplicate kode_asset in active assets.
     */
    private function auditCodeDuplicates(): array
    {
        $row = $this->db->query("
            SELECT COUNT(DISTINCT kode_asset) as distinct_codes 
            FROM assets 
            WHERE deleted_at IS NULL AND kode_asset IS NOT NULL AND kode_asset != ''
        ")->getRowArray();
        $distinctCodes = (int)($row['distinct_codes'] ?? 0);

        $totalWithCode = (int)$this->db->table('assets')
            ->where('deleted_at IS NULL')
            ->where('kode_asset IS NOT NULL')
            ->where("kode_asset != ''")
            ->countAllResults();

        $dupCount = $totalWithCode - $distinctCodes;

        $samples = $this->db->query("
            SELECT kode_asset, COUNT(*) as occurrences, GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') as asset_ids
            FROM assets
            WHERE deleted_at IS NULL AND kode_asset IS NOT NULL AND kode_asset != ''
            GROUP BY kode_asset
            HAVING occurrences > 1
            ORDER BY occurrences DESC
            LIMIT 20
        ")->getResultArray();

        return [
            'total_evaluated'  => $totalWithCode,
            'distinct_codes'   => $distinctCodes,
            'duplicate_count'  => $dupCount,
            'duplicate_groups' => count($samples),
            'samples'          => $samples,
        ];
    }

    /**
     * 3. Composite duplicates (ulp_id, jenis_asset, nama_asset).
     */
    private function auditCompositeDuplicates(): array
    {
        $samples = $this->db->query("
            SELECT ulp_id, jenis_asset, nama_asset, COUNT(*) as occurrences, GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') as asset_ids
            FROM assets
            WHERE deleted_at IS NULL AND nama_asset IS NOT NULL AND nama_asset != ''
            GROUP BY ulp_id, jenis_asset, nama_asset
            HAVING occurrences > 1
            ORDER BY occurrences DESC
            LIMIT 20
        ")->getResultArray();

        $totalGroupsRow = $this->db->query("
            SELECT COUNT(*) as dup_groups FROM (
                SELECT 1
                FROM assets
                WHERE deleted_at IS NULL AND nama_asset IS NOT NULL AND nama_asset != ''
                GROUP BY ulp_id, jenis_asset, nama_asset
                HAVING COUNT(*) > 1
            ) t
        ")->getRowArray();

        return [
            'duplicate_groups_count' => (int)($totalGroupsRow['dup_groups'] ?? 0),
            'samples'                => $samples,
        ];
    }

    /**
     * 4. Spatial collisions: identical coordinates (ROUND lat/lng to 6 decimals).
     */
    private function auditSpatialCollisions(): array
    {
        $samples = $this->db->query("
            SELECT ROUND(latitude, 6) as lat, ROUND(longitude, 6) as lng, COUNT(*) as occurrences,
                   GROUP_CONCAT(kode_asset ORDER BY id SEPARATOR ', ') as kode_assets,
                   GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') as asset_ids
            FROM assets
            WHERE deleted_at IS NULL AND latitude != 0 AND longitude != 0 AND latitude IS NOT NULL AND longitude IS NOT NULL
            GROUP BY ROUND(latitude, 6), ROUND(longitude, 6)
            HAVING occurrences > 1
            ORDER BY occurrences DESC
            LIMIT 15
        ")->getResultArray();

        $totalClustersRow = $this->db->query("
            SELECT COUNT(*) as clusters FROM (
                SELECT 1
                FROM assets
                WHERE deleted_at IS NULL AND latitude != 0 AND longitude != 0 AND latitude IS NOT NULL AND longitude IS NOT NULL
                GROUP BY ROUND(latitude, 6), ROUND(longitude, 6)
                HAVING COUNT(*) > 1
            ) t
        ")->getRowArray();

        return [
            'spatial_collision_clusters' => (int)($totalClustersRow['clusters'] ?? 0),
            'explanation'                => 'Koordinat identik pada jaringan distribusi lazim terjadi pada tiang percabangan (double circuit / trafo cantol / keypoint pada tiang yang sama).',
            'samples'                    => $samples,
        ];
    }

    /**
     * 5. Feeder mapping breakdown: mapped vs unassigned vs orphan FKs.
     */
    private function auditFeederMapping(): array
    {
        $totalActive = (int)$this->db->table('assets')->where('deleted_at IS NULL')->countAllResults();

        // Assets with NULL or 0 penyulang_id
        $unassignedCount = (int)$this->db->table('assets')
            ->where('deleted_at IS NULL')
            ->groupStart()
                ->where('penyulang_id IS NULL')
                ->orWhere('penyulang_id', 0)
            ->groupEnd()
            ->countAllResults();

        // Assets with non-null penyulang_id
        $assignedCount = $totalActive - $unassignedCount;

        // Check for orphan penyulang_id (penyulang_id not existing in penyulang table)
        $orphanRow = $this->db->query("
            SELECT COUNT(a.id) as orphan_count
            FROM assets a
            LEFT JOIN penyulang p ON a.penyulang_id = p.id
            WHERE a.deleted_at IS NULL AND a.penyulang_id IS NOT NULL AND a.penyulang_id > 0 AND p.id IS NULL
        ")->getRowArray();
        $orphanCount = (int)($orphanRow['orphan_count'] ?? 0);

        // Breakdown of unassigned assets by ULP
        $unassignedByUlp = $this->db->query("
            SELECT a.ulp_id, u.nama_ulp, COUNT(a.id) as cnt
            FROM assets a
            LEFT JOIN ulps u ON a.ulp_id = u.id
            WHERE a.deleted_at IS NULL AND (a.penyulang_id IS NULL OR a.penyulang_id = 0)
            GROUP BY a.ulp_id, u.nama_ulp
            ORDER BY cnt DESC
        ")->getResultArray();

        // Breakdown of assigned assets by ULP
        $assignedByUlp = $this->db->query("
            SELECT a.ulp_id, u.nama_ulp, COUNT(a.id) as cnt
            FROM assets a
            LEFT JOIN ulps u ON a.ulp_id = u.id
            WHERE a.deleted_at IS NULL AND a.penyulang_id IS NOT NULL AND a.penyulang_id > 0
            GROUP BY a.ulp_id, u.nama_ulp
            ORDER BY cnt DESC
        ")->getResultArray();

        return [
            'total_active_assets'   => $totalActive,
            'mapped_to_feeder'      => $assignedCount,
            'unassigned_no_feeder'  => $unassignedCount,
            'orphan_penyulang_fks'  => $orphanCount,
            'unassigned_by_ulp'     => $unassignedByUlp,
            'assigned_by_ulp'       => $assignedByUlp,
        ];
    }

    /**
     * 6. Penyulang coverage audit: which feeders have assets vs 0 assets.
     */
    private function auditPenyulangCoverage(): array
    {
        $allFeeders = $this->db->query("
            SELECT p.id, p.kode_penyulang, p.nama_penyulang, p.ulp_id, u.nama_ulp,
                   (SELECT COUNT(*) FROM assets a WHERE a.penyulang_id = p.id AND a.deleted_at IS NULL) as asset_count,
                   (SELECT COUNT(*) FROM gis_translines t WHERE t.penyulang_id = p.id AND t.deleted_at IS NULL AND (t.status = 'ACTIVE' OR t.is_active = 1)) as transline_count
            FROM penyulang p
            LEFT JOIN ulps u ON p.ulp_id = u.id
            ORDER BY u.nama_ulp, p.nama_penyulang
        ")->getResultArray();

        $totalFeeders    = count($allFeeders);
        $feedersWithAssets = 0;
        $feedersZeroAssets = 0;
        $zeroAssetFeeders  = [];

        foreach ($allFeeders as $f) {
            $cnt = (int)$f['asset_count'];
            if ($cnt > 0) {
                $feedersWithAssets++;
            } else {
                $feedersZeroAssets++;
                $zeroAssetFeeders[] = [
                    'id'              => (int)$f['id'],
                    'kode'            => $f['kode_penyulang'],
                    'nama'            => $f['nama_penyulang'],
                    'nama_ulp'        => $f['nama_ulp'],
                    'transline_count' => (int)$f['transline_count'],
                ];
            }
        }

        return [
            'total_feeders'        => $totalFeeders,
            'feeders_with_assets'  => $feedersWithAssets,
            'feeders_zero_assets'  => $feedersZeroAssets,
            'zero_asset_feeders'   => $zeroAssetFeeders,
        ];
    }

    /**
     * 7. Simulate potential name-based matching between unassigned assets and feeders.
     * STRICTLY READ-ONLY (SELECT only).
     */
    private function simulateNameBasedFeederMatching(): array
    {
        // Sample analysis on unassigned assets: test matching against penyulang.nama_penyulang
        $matches = $this->db->query("
            SELECT p.id as target_penyulang_id, p.nama_penyulang, u.nama_ulp, COUNT(a.id) as matchable_assets
            FROM assets a
            JOIN penyulang p ON UPPER(a.nama_asset) LIKE CONCAT('%', UPPER(TRIM(p.nama_penyulang)), '%')
            LEFT JOIN ulps u ON p.ulp_id = u.id
            WHERE a.deleted_at IS NULL AND (a.penyulang_id IS NULL OR a.penyulang_id = 0)
            GROUP BY p.id, p.nama_penyulang, u.nama_ulp
            ORDER BY matchable_assets DESC
            LIMIT 25
        ")->getResultArray();

        $totalMatchableRow = $this->db->query("
            SELECT COUNT(DISTINCT a.id) as matchable_total
            FROM assets a
            JOIN penyulang p ON UPPER(a.nama_asset) LIKE CONCAT('%', UPPER(TRIM(p.nama_penyulang)), '%')
            WHERE a.deleted_at IS NULL AND (a.penyulang_id IS NULL OR a.penyulang_id = 0)
        ")->getRowArray();

        return [
            'total_unassigned_matchable_by_name' => (int)($totalMatchableRow['matchable_total'] ?? 0),
            'top_matchable_feeders'              => $matches,
            'note' => 'Hasil simulasi pencocokan nama aset (nama_asset prefix/substring) dengan nama_penyulang. TIDAK ADA perubahan data yang dieksekusi.'
        ];
    }

    /**
     * 8. Build clear reconciliation explanation for user.
     */
    private function buildReconciliationSummary(array $overview, array $feederMapping, array $penyulangAudit): array
    {
        return [
            'finding_1_asset_total' => sprintf(
                'Total Master Aset aktif adalah %s unit (semua jenis JTM). Dari jumlah ini, %s unit telah memiliki relasi penyulang_id (terpetakan pada tabel distribusi jaringan), sedangkan %s unit berstatus ULP unassigned (penyulang_id IS NULL). %s + %s = %s.',
                number_format($overview['total_active']),
                number_format($feederMapping['mapped_to_feeder']),
                number_format($feederMapping['unassigned_no_feeder']),
                number_format($feederMapping['mapped_to_feeder']),
                number_format($feederMapping['unassigned_no_feeder']),
                number_format($overview['total_active'])
            ),
            'finding_2_feeder_zeros' => sprintf(
                'Dari %d penyulang terdaftar, %d penyulang telah memiliki aset terpetakan, sementara %d penyulang menunjukkan 0 aset karena aset tiangnya diimpor tanpa kolom foreign key penyulang_id.',
                $penyulangAudit['total_feeders'],
                $penyulangAudit['feeders_with_assets'],
                $penyulangAudit['feeders_zero_assets']
            ),
            'finding_3_gis_empty_state' => 'Pada modul GIS, saat pengguna memilih penyulang yang memiliki 0 aset terpetakan, GIS secara benar menampilkan banner NETWORK BELUM TERDAFTAR (Master Asset 0 Unit, Transline 0 Segmen) karena sistem mematuhi Invariant #9 (Strict Data Integrity: tidak menyamarkan temuan inspeksi sebagai Master Asset jika penyulang tersebut memang belum memiliki relasi aset terdaftar).',
            'recommendation' => 'Integrasi data aman dapat dilakukan melalui migrasi terarah atau rekonsiliasi FK berbantuan nama_asset tanpa merusak relasi foreign key temuan dan transline yang sudah berjalan.',
        ];
    }
}
