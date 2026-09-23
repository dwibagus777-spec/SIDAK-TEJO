<?php

namespace App\Services;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * TranslineIntegrityAuditService
 *
 * Dedicated Service for verifying GIS Transline Append-Only Integrity,
 * detecting topological anomalies, and auditing edge lifecycle state transitions
 * (PRESERVED, ADDED, REMOVED, MODIFIED).
 *
 * Architectural Invariants:
 * 1. STRICT READ-ONLY AUDITING: 0 write operations (INSERT=0, UPDATE=0, DELETE=0).
 * 2. CANONICAL EDGE FINGERPRINT: min(A,B) . '-' . max(A,B) guarantees undirected uniqueness.
 * 3. FEEDER ISOLATION: Transline endpoints must belong strictly to the same feeder.
 * 4. SECURE ACCESS CONTROL: Restricted to authenticated sessions or authorized token.
 */
class TranslineIntegrityAuditService
{
    public const INVARIANT = 'GIS_TRANSLINE_APPEND_ONLY';

    public const ANOMALY_ORPHAN_SOURCE        = 'ORPHAN_SOURCE_ASSET';
    public const ANOMALY_ORPHAN_TARGET        = 'ORPHAN_TARGET_ASSET';
    public const ANOMALY_CROSS_FEEDER         = 'CROSS_FEEDER_LEAKAGE';
    public const ANOMALY_SELF_LOOP            = 'SELF_LOOP';
    public const ANOMALY_DUPLICATE_EDGE       = 'DUPLICATE_BIDIRECTIONAL_EDGE';
    public const ANOMALY_MISSING_COORDINATES  = 'MISSING_COORDINATES';
    public const ANOMALY_TOPOLOGY_DISCREPANCY = 'TOPOLOGY_VERSION_DISCREPANCY';

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Compute canonical undirected edge fingerprint
     */
    public static function canonicalEdgeFingerprint(int $assetAId, int $assetBId): string
    {
        $min = min($assetAId, $assetBId);
        $max = max($assetAId, $assetBId);
        return "{$min}-{$max}";
    }

    /**
     * Audit feeder translines and compare with historical/baseline snapshot
     *
     * @param int $feederId (0 for all feeders)
     * @param array|null $baselineEdges Map of [fingerprint => edgeData] or null
     * @return array<string, mixed>
     */
    public function auditFeeder(int $feederId = 0, ?array $baselineEdges = null): array
    {
        // 1. Fetch assets map for validation
        $assetBuilder = $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, penyulang_id, latitude, longitude, deleted_at')
            ->where('deleted_at IS NULL');
        if ($feederId > 0) {
            $assetBuilder->where('penyulang_id', $feederId);
        }
        $rawAssets = $assetBuilder->get()->getResultArray();
        $assetsMap = [];
        foreach ($rawAssets as $a) {
            $assetsMap[(int)$a['id']] = $a;
        }

        // If feederId > 0, also fetch assets from other feeders into a lightweight index to detect cross-feeder leakage
        $globalFeederMap = [];
        if ($feederId > 0) {
            $crossAssets = $this->db->table('assets')->select('id, penyulang_id, kode_asset')->where('deleted_at IS NULL')->get()->getResultArray();
            foreach ($crossAssets as $ca) {
                $globalFeederMap[(int)$ca['id']] = (int)($ca['penyulang_id'] ?? 0);
            }
        } else {
            foreach ($assetsMap as $id => $a) {
                $globalFeederMap[$id] = (int)($a['penyulang_id'] ?? 0);
            }
        }

        // 2. Fetch all translines for scope
        $tlBuilder = $this->db->table('gis_translines');
        if ($feederId > 0) {
            $tlBuilder->where('penyulang_id', $feederId);
        }
        $allTranslines = $tlBuilder->orderBy('id', 'ASC')->get()->getResultArray();

        // 3. Fetch active topology snapshot count from network_topology_versions
        $snapshotSegmentsCount = null;
        if ($this->db->tableExists('network_topology_versions') && $feederId > 0) {
            $activeSnapshot = $this->db->table('network_topology_versions')
                ->where('penyulang_id', $feederId)
                ->where('is_active', 1)
                ->get()
                ->getRowArray();
            if ($activeSnapshot) {
                $snapshotSegmentsCount = (int)($activeSnapshot['segments_count'] ?? 0);
            }
        }

        // 4. Anomaly detection & Edge classification
        $anomalies = [];
        $activeFingerprints = [];
        $activeEdges = [];
        $inactiveCount = 0;
        $activeCount = 0;

        foreach ($allTranslines as $tl) {
            $tlId        = (int)$tl['id'];
            $srcId       = (int)$tl['source_asset_id'];
            $tgtId       = (int)$tl['target_asset_id'];
            $tlFeederId  = (int)$tl['penyulang_id'];
            $isActive    = (int)($tl['is_active'] ?? 0) === 1 && empty($tl['deleted_at']);

            if (!$isActive) {
                $inactiveCount++;
                continue;
            }

            $activeCount++;
            $fingerprint = self::canonicalEdgeFingerprint($srcId, $tgtId);

            // Anomaly 1: Self-loop
            if ($srcId === $tgtId) {
                $anomalies[] = [
                    'type'         => self::ANOMALY_SELF_LOOP,
                    'severity'     => 'CRITICAL',
                    'transline_id' => $tlId,
                    'fingerprint'  => $fingerprint,
                    'message'      => "Transline #{$tlId} menghubungkan aset ke dirinya sendiri (ID: {$srcId}).",
                ];
            }

            // Anomaly 2: Orphan Source
            if (!isset($globalFeederMap[$srcId])) {
                $anomalies[] = [
                    'type'         => self::ANOMALY_ORPHAN_SOURCE,
                    'severity'     => 'CRITICAL',
                    'transline_id' => $tlId,
                    'fingerprint'  => $fingerprint,
                    'message'      => "Aset sumber #{$srcId} tidak ditemukan dalam database assets.",
                ];
            }

            // Anomaly 3: Orphan Target
            if (!isset($globalFeederMap[$tgtId])) {
                $anomalies[] = [
                    'type'         => self::ANOMALY_ORPHAN_TARGET,
                    'severity'     => 'CRITICAL',
                    'transline_id' => $tlId,
                    'fingerprint'  => $fingerprint,
                    'message'      => "Aset target #{$tgtId} tidak ditemukan dalam database assets.",
                ];
            }

            // Anomaly 4: Cross-feeder leakage
            $srcFeeder = $globalFeederMap[$srcId] ?? null;
            $tgtFeeder = $globalFeederMap[$tgtId] ?? null;
            if ($srcFeeder !== null && $tgtFeeder !== null && $srcFeeder !== $tgtFeeder) {
                $anomalies[] = [
                    'type'         => self::ANOMALY_CROSS_FEEDER,
                    'severity'     => 'CRITICAL',
                    'transline_id' => $tlId,
                    'fingerprint'  => $fingerprint,
                    'message'      => "Transline #{$tlId} menghubungkan aset dari penyulang berbeda: sumber #{$srcId} (feeder {$srcFeeder}) ➔ target #{$tgtId} (feeder {$tgtFeeder}).",
                ];
            } elseif ($srcFeeder !== null && $srcFeeder !== $tlFeederId) {
                $anomalies[] = [
                    'type'         => self::ANOMALY_CROSS_FEEDER,
                    'severity'     => 'CRITICAL',
                    'transline_id' => $tlId,
                    'fingerprint'  => $fingerprint,
                    'message'      => "Transline #{$tlId} tercatat di penyulang {$tlFeederId}, tetapi aset sumber berada di penyulang {$srcFeeder}.",
                ];
            }

            // Anomaly 5: Duplicate active bidirectional edge
            if (isset($activeFingerprints[$fingerprint])) {
                $prevId = $activeFingerprints[$fingerprint];
                $anomalies[] = [
                    'type'         => self::ANOMALY_DUPLICATE_EDGE,
                    'severity'     => 'HIGH',
                    'transline_id' => $tlId,
                    'fingerprint'  => $fingerprint,
                    'message'      => "Duplikasi segmen terdeteksi antara aset {$srcId} dan {$tgtId}: transline #{$tlId} duplikat dengan transline #{$prevId}.",
                ];
            } else {
                $activeFingerprints[$fingerprint] = $tlId;
            }

            // Anomaly 6: Missing coordinates
            $srcAsset = $assetsMap[$srcId] ?? null;
            $tgtAsset = $assetsMap[$tgtId] ?? null;
            if ($srcAsset && ((float)($srcAsset['latitude'] ?? 0) == 0.0 || (float)($srcAsset['longitude'] ?? 0) == 0.0)) {
                $anomalies[] = [
                    'type'         => self::ANOMALY_MISSING_COORDINATES,
                    'severity'     => 'WARNING',
                    'transline_id' => $tlId,
                    'fingerprint'  => $fingerprint,
                    'message'      => "Aset sumber ({$srcAsset['kode_asset']}) tidak memiliki koordinat spasial valid.",
                ];
            }
            if ($tgtAsset && ((float)($tgtAsset['latitude'] ?? 0) == 0.0 || (float)($tgtAsset['longitude'] ?? 0) == 0.0)) {
                $anomalies[] = [
                    'type'         => self::ANOMALY_MISSING_COORDINATES,
                    'severity'     => 'WARNING',
                    'transline_id' => $tlId,
                    'fingerprint'  => $fingerprint,
                    'message'      => "Aset target ({$tgtAsset['kode_asset']}) tidak memiliki koordinat spasial valid.",
                ];
            }

            $activeEdges[$fingerprint] = [
                'transline_id'       => $tlId,
                'transline_code'     => $tl['transline_code'] ?? null,
                'penyulang_id'       => $tlFeederId,
                'source_asset_id'    => $srcId,
                'target_asset_id'    => $tgtId,
                'conductor_type'     => $tl['conductor_type'] ?? null,
                'conductor_size'     => $tl['conductor_size'] ?? null,
                'distance_meters'    => (float)($tl['distance_meters'] ?? 0),
                'status'             => $tl['status'] ?? 'ACTIVE',
            ];
        }

        // Anomaly 7: Topology version discrepancy
        if ($snapshotSegmentsCount !== null && $snapshotSegmentsCount !== count($activeFingerprints)) {
            $anomalies[] = [
                'type'         => self::ANOMALY_TOPOLOGY_DISCREPANCY,
                'severity'     => 'MEDIUM',
                'transline_id' => null,
                'fingerprint'  => null,
                'message'      => "Discrepancy antara snapshot topologi ({$snapshotSegmentsCount} segmen) dan gis_translines aktif (" . count($activeFingerprints) . " segmen).",
            ];
        }

        // 5. Compare with baseline (if provided)
        $edgeStates = [
            'PRESERVED' => 0,
            'ADDED'     => 0,
            'REMOVED'   => 0,
            'MODIFIED'  => 0,
        ];
        $detailedDiff = [];

        if ($baselineEdges !== null) {
            foreach ($baselineEdges as $bp => $bData) {
                if (isset($activeEdges[$bp])) {
                    $cur = $activeEdges[$bp];
                    // Check if attributes changed
                    $isModified = ($cur['conductor_type'] !== ($bData['conductor_type'] ?? null)) ||
                                  ($cur['conductor_size'] !== ($bData['conductor_size'] ?? null));
                    if ($isModified) {
                        $edgeStates['MODIFIED']++;
                        $detailedDiff[$bp] = ['state' => 'MODIFIED', 'baseline' => $bData, 'current' => $cur];
                    } else {
                        $edgeStates['PRESERVED']++;
                        $detailedDiff[$bp] = ['state' => 'PRESERVED', 'current' => $cur];
                    }
                } else {
                    $edgeStates['REMOVED']++;
                    $detailedDiff[$bp] = ['state' => 'REMOVED', 'baseline' => $bData];
                }
            }

            foreach ($activeEdges as $cp => $cData) {
                if (!isset($baselineEdges[$cp])) {
                    $edgeStates['ADDED']++;
                    $detailedDiff[$cp] = ['state' => 'ADDED', 'current' => $cData];
                }
            }
        } else {
            // Default when no baseline provided: all active edges are considered preserved
            $edgeStates['PRESERVED'] = count($activeEdges);
        }

        $criticalCount = count(array_filter($anomalies, fn($a) => ($a['severity'] ?? '') === 'CRITICAL'));

        return [
            'feeder_id'              => $feederId,
            'audit_invariant'        => self::INVARIANT,
            'timestamp'              => date('Y-m-d H:i:s T'),
            'is_healthy'             => $criticalCount === 0,
            'total_assets_registered'=> count($assetsMap),
            'total_transline_rows'   => count($allTranslines),
            'active_edges_count'     => $activeCount,
            'inactive_edges_count'   => $inactiveCount,
            'unique_fingerprints'    => count($activeFingerprints),
            'edge_states'            => $edgeStates,
            'anomalies_count'        => count($anomalies),
            'critical_anomalies'     => $criticalCount,
            'anomalies'              => $anomalies,
            'active_edges_summary'   => array_values(array_slice($activeEdges, 0, 50)),
            'detailed_diff_sample'   => array_slice($detailedDiff, 0, 50, true),
        ];
    }
}
