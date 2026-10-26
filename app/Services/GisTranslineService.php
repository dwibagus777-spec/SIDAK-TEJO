<?php

namespace App\Services;

use App\Models\GisTranslineModel;
use App\Models\AssetRelationshipModel;
use App\Repositories\AssetRepository;
use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * Service for Authoritative GIS Transline CRUD & Multi-Segment Topology Reconciliation
 */
class GisTranslineService
{
    public const INVARIANT_RULE = 'GIS_TRANSLINE_APPEND_ONLY';

    protected BaseConnection $db;
    protected GisTranslineModel $translineModel;
    protected AssetRelationshipModel $relModel;
    protected AssetRepository $assetRepository;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->translineModel = new GisTranslineModel();
        $this->relModel = new AssetRelationshipModel();
        $this->assetRepository = new AssetRepository();
    }

    public function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Strict JTM Pole Validation.
     *
     * Rule: Translines MUST ONLY connect physical JTM pole assets.
     * Non-pole assets such as ROW trees (POHON_ROW), substations/transformers (GARDU, GTT),
     * switching/protection devices (RECLOSER, LBS, PMCB), and conductors cannot be nodes of translines.
     *
     * @param array|null $asset
     * @return array<string, mixed> ['valid' => bool, 'reason' => string, 'type' => string]
     */
    public static function isJtmPoleAsset(?array $asset): array
    {
        if (!$asset || empty($asset['id'])) {
            return [
                'valid'  => false,
                'reason' => 'Data aset tidak ditemukan di database.',
            ];
        }

        $jenis = strtoupper(trim((string)($asset['jenis_asset'] ?? '')));
        $nama  = strtoupper(trim((string)($asset['nama_asset'] ?? '')));
        $kode  = strtoupper(trim((string)($asset['kode_asset'] ?? '')));

        // 1. Explicitly prohibited non-JTM pole types
        $prohibitedPatterns = [
            'POHON'         => 'Pohon / Vegetasi ROW',
            'ROW'           => 'Pohon ROW',
            'GARDU'         => 'Gardu Distribusi / Trafo',
            'GTT'           => 'Gardu Trafo Tiang',
            'TRAFO'         => 'Transformator Distribusi',
            'RECLOSER'      => 'Recloser Proteksi',
            'LBS'           => 'Load Break Switch (LBS)',
            'LBSM'          => 'Load Break Switch Motorized (LBSM)',
            'PMCB'          => 'Pole Mounted Circuit Breaker (PMCB)',
            'GH'            => 'Gardu Hubung (GH)',
            'ASS'           => 'Automatic Section Switch (ASS)',
            'SECTIONALIZER' => 'Automatic Sectionalizer',
            'KONDUKTOR'     => 'Entitas Konduktor Terpisah',
            'KABEL'         => 'Kabel Jaringan',
            'GI'            => 'Gardu Induk',
        ];

        foreach ($prohibitedPatterns as $pattern => $label) {
            if (str_contains($jenis, $pattern)) {
                return [
                    'valid'  => false,
                    'reason' => "Tipe aset '{$jenis}' merupakan {$label}, bukan tiang JTM fisik. Transline hanya boleh menghubungkan tiang JTM.",
                ];
            }
        }

        // 2. Recognized authoritative JTM pole types
        $validPoleTypes = [
            'TIANG_BETON',
            'TIANG_BESI',
            'TIANG_KAYU',
            'TIANG',
            'JTM',
            'JTM_TIANG',
            'TIANG_JTM',
            'POLE',
        ];

        if (in_array($jenis, $validPoleTypes, true)) {
            return [
                'valid'  => true,
                'reason' => 'VALID_JTM_POLE',
                'type'   => $jenis,
            ];
        }

        // 3. Backward-compatible fallback for legacy unclassified poles (jenis_asset IS NULL / empty)
        if (empty($jenis)) {
            foreach ($prohibitedPatterns as $pattern => $label) {
                if (str_contains($nama, $pattern) || str_contains($kode, $pattern)) {
                    return [
                        'valid'  => false,
                        'reason' => "Aset '{$kode}' terindikasi sebagai {$label}, bukan tiang JTM fisik.",
                    ];
                }
            }
            return [
                'valid'  => true,
                'reason' => 'VALID_LEGACY_POLE_BY_NAME',
                'type'   => 'TIANG',
            ];
        }

        return [
            'valid'  => false,
            'reason' => "Tipe aset '{$jenis}' tidak terdaftar sebagai tiang JTM valid.",
        ];
    }

    /**
     * Resolves a non-pole source asset (e.g., GI or Protection Device) to its nearest verified JTM pole.
     *
     * Invariant: Never create a transline between GI/protection and a pole.
     * Instead, link the calculation/context via spatial & network relationship.
     */
    public function resolveSourceContextToNearestJtmPole(int $sourceAssetId, int $penyulangId): ?array
    {
        $asset = $this->db->table('assets')
            ->where('id', $sourceAssetId)
            ->where('penyulang_id', $penyulangId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();
        if (!$asset) {
            return null;
        }

        // Collect connected pole IDs in active translines of this feeder so we prefer poles on the active network graph
        $connectedPoleIds = [];
        if ($this->db->tableExists('gis_translines')) {
            $tlEndpoints = $this->db->table('gis_translines')
                ->select('source_asset_id, target_asset_id')
                ->where('penyulang_id', $penyulangId)
                ->where('is_active', 1)
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();
            foreach ($tlEndpoints as $ep) {
                $u = (int)($ep['source_asset_id'] ?? 0);
                $v = (int)($ep['target_asset_id'] ?? 0);
                if ($u > 0 && $v > 0 && $u !== $v) {
                    $connectedPoleIds[$u] = true;
                    $connectedPoleIds[$v] = true;
                }
            }
        }

        // Load all valid JTM poles on the same feeder
        $rawPoles = $this->db->table('assets')
            ->where('penyulang_id', $penyulangId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $validPolesById = [];
        foreach ($rawPoles as $p) {
            if ((int)($p['penyulang_id'] ?? 0) === $penyulangId && self::isJtmPoleAsset($p)['valid']) {
                $validPolesById[(int)$p['id']] = $p;
            }
        }

        $snapToConnectedPole = function (array $pole) use ($connectedPoleIds, $validPolesById): array {
            $pid = (int)$pole['id'];
            if (empty($connectedPoleIds) || isset($connectedPoleIds[$pid])) {
                return $pole;
            }
            $pLat = (float)($pole['latitude'] ?? 0);
            $pLng = (float)($pole['longitude'] ?? 0);
            if ($pLat == 0.0 || $pLng == 0.0) {
                return $pole;
            }
            $best = $pole;
            $bestD = 50.0;
            foreach ($connectedPoleIds as $cid => $_) {
                if (!isset($validPolesById[$cid])) continue;
                $cPole = $validPolesById[$cid];
                $cLat = (float)($cPole['latitude'] ?? 0);
                $cLng = (float)($cPole['longitude'] ?? 0);
                if ($cLat == 0.0 || $cLng == 0.0) continue;
                $d = $this->haversineDistance($pLat, $pLng, $cLat, $cLng);
                if ($d <= $bestD) {
                    $bestD = $d;
                    $best = $cPole;
                }
            }
            return $best;
        };

        $check = self::isJtmPoleAsset($asset);
        if ($check['valid']) {
            $snapped = $snapToConnectedPole($asset);
            return [
                'source_type'             => 'JTM_POLE',
                'mounting_relation'       => 'DIRECT_POLE',
                'source_asset'            => $asset,
                'anchor_pole_asset'       => $snapped,
                'distance_to_pole_meters' => 0.0,
                'verified'                => true,
            ];
        }

        // 1. Explicit parent_asset_id mounting relationship
        if (!empty($asset['parent_asset_id'])) {
            $parentId = (int)$asset['parent_asset_id'];
            $parentPole = $validPolesById[$parentId] ?? null;
            if ($parentPole) {
                $snapped = $snapToConnectedPole($parentPole);
                return [
                    'source_type'             => 'DEVICE_CONTEXT',
                    'mounting_relation'       => 'PARENT_ASSET_ID',
                    'source_asset'            => $asset,
                    'anchor_pole_asset'       => $snapped,
                    'distance_to_pole_meters' => 0.0,
                    'verified'                => true,
                ];
            }
        }

        // 2. Explicit asset_relationships mounting table (MOUNTED_ON_POLE / parent_asset_id / target_asset_id)
        if ($this->db->tableExists('asset_relationships')) {
            $relRows = $this->db->table('asset_relationships')
                ->groupStart()
                    ->where('child_asset_id', $sourceAssetId)
                    ->orWhere('source_asset_id', $sourceAssetId)
                ->groupEnd()
                ->where('is_active', 1)
                ->get()
                ->getResultArray();
            foreach ($relRows as $rel) {
                $candPoleId = (int)($rel['parent_asset_id'] ?: ($rel['target_asset_id'] ?? 0));
                if ($candPoleId > 0 && isset($validPolesById[$candPoleId])) {
                    $snapped = $snapToConnectedPole($validPolesById[$candPoleId]);
                    return [
                        'source_type'             => 'DEVICE_CONTEXT',
                        'mounting_relation'       => 'ASSET_RELATIONSHIP',
                        'source_asset'            => $asset,
                        'anchor_pole_asset'       => $snapped,
                        'distance_to_pole_meters' => round((float)($rel['distance_meters'] ?? 0.0), 2),
                        'verified'                => true,
                    ];
                }
            }
        }

        // 3. Nearest verified JTM pole on the same feeder (preferring connected poles in gis_translines)
        $nearestPole = null;
        $minDist = PHP_FLOAT_MAX;
        $nearestConnectedPole = null;
        $minConnectedDist = PHP_FLOAT_MAX;
        $srcLat = (float)($asset['latitude'] ?? 0);
        $srcLng = (float)($asset['longitude'] ?? 0);

        foreach ($validPolesById as $pid => $p) {
            $pLat = (float)($p['latitude'] ?? 0);
            $pLng = (float)($p['longitude'] ?? 0);
            if ($pLat == 0.0 || $pLng == 0.0 || $srcLat == 0.0 || $srcLng == 0.0) {
                continue;
            }
            $d = $this->haversineDistance($srcLat, $srcLng, $pLat, $pLng);
            if ($d < $minDist) {
                $minDist = $d;
                $nearestPole = $p;
            }
            if (isset($connectedPoleIds[$pid]) && $d < $minConnectedDist) {
                $minConnectedDist = $d;
                $nearestConnectedPole = $p;
            }
        }

        if ($nearestConnectedPole && $minConnectedDist <= 250.0) {
            return [
                'source_type'             => 'DEVICE_CONTEXT',
                'mounting_relation'       => 'NEAREST_CONNECTED_POLE',
                'source_asset'            => $asset,
                'anchor_pole_asset'       => $nearestConnectedPole,
                'distance_to_pole_meters' => round($minConnectedDist, 2),
                'verified'                => true,
            ];
        }

        if ($nearestPole && $minDist <= 50.0) {
            return [
                'source_type'             => 'DEVICE_CONTEXT',
                'mounting_relation'       => 'NEAREST_POLE',
                'source_asset'            => $asset,
                'anchor_pole_asset'       => $nearestPole,
                'distance_to_pole_meters' => round($minDist, 2),
                'verified'                => true,
            ];
        }

        return [
            'source_type'             => 'UNVERIFIED',
            'source_asset'            => $asset,
            'anchor_pole_asset'       => null,
            'distance_to_pole_meters' => ($minDist !== PHP_FLOAT_MAX) ? round($minDist, 2) : null,
            'verified'                => false,
            'reason'                  => 'Jarak ke tiang JTM terdekat melebihi batas fisik atau tiang belum terhubung ke jaringan transline.',
        ];
    }

    /**
     * Audits and safely reconciles non-pole translines with JSON backup and deterministic re-anchoring.
     * Segments pointing to non-pole devices (Recloser, LBS, GTT, Pohon) are re-anchored to the nearest JTM pole
     * on the same feeder (<= 35m) or safely deactivated if no pole exists, preserving full lineage and rollback.
     */
    public function reconcileNonPoleTranslines(bool $execute = false): array
    {
        if (!$this->db->tableExists('gis_translines')) {
            return ['status' => 'error', 'message' => 'Tabel gis_translines tidak ditemukan.'];
        }

        $sql = "
            SELECT 
                tl.id,
                tl.transline_code,
                tl.penyulang_id,
                tl.source_asset_id,
                tl.target_asset_id,
                tl.distance_meters,
                tl.conductor_type,
                tl.conductor_size,
                p.nama_penyulang,
                sa.kode_asset AS source_code,
                sa.nama_asset AS source_name,
                sa.jenis_asset AS source_jenis,
                sa.latitude AS source_lat,
                sa.longitude AS source_lng,
                sa.penyulang_id AS source_penyulang_id,
                ta.kode_asset AS target_code,
                ta.nama_asset AS target_name,
                ta.jenis_asset AS target_jenis,
                ta.latitude AS target_lat,
                ta.longitude AS target_lng,
                ta.penyulang_id AS target_penyulang_id
            FROM gis_translines tl
            LEFT JOIN penyulang p ON p.id = tl.penyulang_id
            LEFT JOIN assets sa ON sa.id = tl.source_asset_id
            LEFT JOIN assets ta ON ta.id = tl.target_asset_id
            WHERE tl.deleted_at IS NULL
            ORDER BY tl.penyulang_id ASC, tl.id ASC
        ";

        $res = $this->db->query($sql);
        $translines = ($res && is_object($res)) ? $res->getResultArray() : [];
        $invalidSegments = [];
        $reanchorProposals = [];
        $now = date('Y-m-d H:i:s');

        foreach ($translines as $tl) {
            $srcAsset = [
                'id'          => $tl['source_asset_id'],
                'kode_asset'  => $tl['source_code'],
                'nama_asset'  => $tl['source_name'],
                'jenis_asset' => $tl['source_jenis'],
                'latitude'    => $tl['source_lat'],
                'longitude'   => $tl['source_lng'],
            ];
            $tgtAsset = [
                'id'          => $tl['target_asset_id'],
                'kode_asset'  => $tl['target_code'],
                'nama_asset'  => $tl['target_name'],
                'jenis_asset' => $tl['target_jenis'],
                'latitude'    => $tl['target_lat'],
                'longitude'   => $tl['target_lng'],
            ];

            $srcCheck = self::isJtmPoleAsset($srcAsset);
            $tgtCheck = self::isJtmPoleAsset($tgtAsset);

            if (!$srcCheck['valid'] || !$tgtCheck['valid']) {
                $feederId = (int)$tl['penyulang_id'];
                $actionType = 'REANCHOR';
                $newSrcId = (int)$tl['source_asset_id'];
                $newTgtId = (int)$tl['target_asset_id'];
                $resolutionNotes = [];

                if (!$srcCheck['valid']) {
                    $res = $this->resolveSourceContextToNearestJtmPole($newSrcId, $feederId);
                    if ($res && !empty($res['anchor_pole_asset']['id']) && !empty($res['verified'])) {
                        $newSrcId = (int)$res['anchor_pole_asset']['id'];
                        $resolutionNotes[] = "Source re-anchored from non-pole #{$tl['source_asset_id']} ({$tl['source_code']}) to pole #{$newSrcId} ({$res['anchor_pole_asset']['kode_asset']})";
                    } else {
                        $actionType = 'REVIEW_REQUIRED';
                        $resolutionNotes[] = "Tidak ada tiang JTM terverifikasi (<= 35m & seksi sama) untuk source #{$tl['source_asset_id']} ({$tl['source_code']}). Dimasukkan ke daftar review.";
                    }
                }

                if (!$tgtCheck['valid']) {
                    $res = $this->resolveSourceContextToNearestJtmPole($newTgtId, $feederId);
                    if ($res && !empty($res['anchor_pole_asset']['id']) && !empty($res['verified'])) {
                        $newTgtId = (int)$res['anchor_pole_asset']['id'];
                        $resolutionNotes[] = "Target re-anchored from non-pole #{$tl['target_asset_id']} ({$tl['target_code']}) to pole #{$newTgtId} ({$res['anchor_pole_asset']['kode_asset']})";
                    } else {
                        $actionType = 'REVIEW_REQUIRED';
                        $resolutionNotes[] = "Tidak ada tiang JTM terverifikasi (<= 35m & seksi sama) untuk target #{$tl['target_asset_id']} ({$tl['target_code']}). Dimasukkan ke daftar review.";
                    }
                }

                // Disallow self-loops if source and target resolve to same pole
                if ($newSrcId === $newTgtId) {
                    $actionType = 'DEACTIVATE';
                    $resolutionNotes[] = "Source and target resolved to the same pole #{$newSrcId}; deactivating zero-length loop.";
                }

                $invalidSegments[] = [
                    'transline_id'      => (int)$tl['id'],
                    'transline_code'    => $tl['transline_code'],
                    'feeder_id'         => $feederId,
                    'feeder_name'       => $tl['nama_penyulang'] ?? "Feeder #{$feederId}",
                    'original_source'   => ['id' => $tl['source_asset_id'], 'code' => $tl['source_code'], 'type' => $tl['source_jenis'], 'reason' => $srcCheck['reason'] ?? 'VALID'],
                    'original_target'   => ['id' => $tl['target_asset_id'], 'code' => $tl['target_code'], 'type' => $tl['target_jenis'], 'reason' => $tgtCheck['reason'] ?? 'VALID'],
                    'action'            => $actionType,
                    'new_source_id'     => $newSrcId,
                    'new_target_id'     => $newTgtId,
                    'resolution_notes'  => implode('; ', $resolutionNotes),
                ];
            }
        }

        $count = count($invalidSegments);
        $backupPath = WRITEPATH . 'backups/non_pole_transline_reconciliation_' . date('Ymd_His') . '.json';
        if (!is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }

        file_put_contents($backupPath, json_encode([
            'audited_at'          => $now,
            'total_violations'    => $count,
            'violations_snapshot' => $invalidSegments,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($execute && $count > 0) {
            $this->db->transStart();
            foreach ($invalidSegments as $inv) {
                if ($inv['action'] === 'REANCHOR') {
                    $newCode = 'TL-' . $inv['feeder_id'] . '-' . $inv['new_source_id'] . '-' . $inv['new_target_id'];
                    $this->db->table('gis_translines')->where('id', $inv['transline_id'])->update([
                        'source_asset_id' => $inv['new_source_id'],
                        'target_asset_id' => $inv['new_target_id'],
                        'transline_code'  => $newCode,
                        'notes'           => $inv['resolution_notes'],
                        'updated_at'      => $now,
                    ]);
                } elseif ($inv['action'] === 'DEACTIVATE') {
                    $this->db->table('gis_translines')->where('id', $inv['transline_id'])->update([
                        'is_active'  => 0,
                        'status'     => 'DEACTIVATED_NON_POLE',
                        'deleted_at' => $now,
                        'notes'      => $inv['resolution_notes'],
                        'updated_at' => $now,
                    ]);
                } else {
                    // REVIEW_REQUIRED: preserve transline intact, record audit review note
                    $this->db->table('gis_translines')->where('id', $inv['transline_id'])->update([
                        'notes'      => '[REVIEW_REQUIRED] ' . $inv['resolution_notes'],
                        'updated_at' => $now,
                    ]);
                }
            }
            $this->db->transComplete();
        }

        return [
            'status'            => 'success',
            'executed'          => $execute,
            'total_violations'  => $count,
            'backup_file'       => $backupPath,
            'sample_violations' => array_slice($invalidSegments, 0, 10),
            'message'           => $execute
                ? "Berhasil merekonsiliasi {$count} segmen transline non-tiang dengan aman ke tiang JTM dan backup JSON."
                : "Terdeteksi {$count} segmen transline non-tiang siap direkonsiliasi.",
        ];
    }

    /**
     * Get all active transline segments for a feeder
     *
     * @param int $feederId
     * @return array<int, array<string, mixed>>
     */
    public function getFeederTranslines(int $feederId): array
    {
        if ($feederId <= 0) return [];
        return $this->translineModel->getActiveTranslinesByFeeder($feederId);
    }

    /**
     * Resolve a bidirectional transline segment between two assets (A -> B or B -> A)
     *
     * @param int $penyulangId
     * @param int $assetAId
     * @param int $assetBId
     * @return array<string, mixed>|null
     */
    public function resolveTranslinePair(int $penyulangId, int $assetAId, int $assetBId): ?array
    {
        if ($assetAId <= 0 || $assetBId <= 0 || $assetAId === $assetBId) {
            return null;
        }

        $builder = $this->db->table('gis_translines')
            ->where('is_active', 1)
            ->groupStart()
                ->groupStart()
                    ->where('source_asset_id', $assetAId)->where('target_asset_id', $assetBId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('source_asset_id', $assetBId)->where('target_asset_id', $assetAId)
                ->groupEnd()
            ->groupEnd();

        if ($penyulangId > 0) {
            $builder->where('penyulang_id', $penyulangId);
        }

        $row = $builder->get()->getRowArray();
        if ($row) {
            return $row;
        }

        // Spatial proximity fallback for co-located or alias markers
        $assetB = $this->db->table('assets')->select('latitude, longitude')->where('id', $assetBId)->get()->getRowArray();
        if ($assetB && !empty($assetB['latitude']) && !empty($assetB['longitude'])) {
            $lat = (float)$assetB['latitude'];
            $lng = (float)$assetB['longitude'];
            $coAssets = $this->db->table('assets')
                ->select('id')
                ->where('penyulang_id', $penyulangId)
                ->where('id !=', $assetBId)
                ->where('latitude >=', $lat - 0.00015)
                ->where('latitude <=', $lat + 0.00015)
                ->where('longitude >=', $lng - 0.00015)
                ->where('longitude <=', $lng + 0.00015)
                ->get()
                ->getResultArray();

            $coIds = array_column($coAssets, 'id');
            if (!empty($coIds)) {
                $builder2 = $this->db->table('gis_translines')
                    ->where('is_active', 1)
                    ->where('penyulang_id', $penyulangId)
                    ->groupStart()
                        ->groupStart()
                            ->where('source_asset_id', $assetAId)->whereIn('target_asset_id', $coIds)
                        ->groupEnd()
                        ->orGroupStart()
                            ->whereIn('source_asset_id', $coIds)->where('target_asset_id', $assetAId)
                        ->groupEnd()
                    ->groupEnd();
                $coRow = $builder2->get()->getRowArray();
                if ($coRow) {
                    return $coRow;
                }
            }
        }

        return null;
    }

    /**
     * Delete an active transline segment identified by pair (A -> B or B -> A)
     *
     * @param int $penyulangId
     * @param int $assetAId
     * @param int $assetBId
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function deleteTranslineByPair(int $penyulangId, int $assetAId, int $assetBId, ?array $actor = null): array
    {
        $row = $this->resolveTranslinePair($penyulangId, $assetAId, $assetBId);
        if (!$row) {
            return [
                'status'  => 'error',
                'message' => 'Tidak ditemukan sambungan jalur aktif antara kedua aset tersebut.'
            ];
        }

        return $this->deleteTransline((int)$row['id'], $actor);
    }

    /**
     * Create or update an individual transline segment (Atomic CRUD)
     *
     * Invariant: GIS_TRANSLINE_APPEND_ONLY.
     * Mode ADD strictly appends or reactivates without mutating or deleting other edges.
     *
     * @param array $data
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function saveTransline(array $data, ?array $actor = null): array
    {
        $sourceId = (int)($data['source_asset_id'] ?? 0);
        $targetId = (int)($data['target_asset_id'] ?? 0);
        $penyulangId = (int)($data['penyulang_id'] ?? 0);

        if ($sourceId <= 0 || $targetId <= 0) {
            return [
                'status'  => 'error',
                'message' => 'Kedua ID aset tiang awal dan tiang akhir wajib ditentukan.'
            ];
        }

        if ($sourceId === $targetId) {
            return [
                'status'  => 'error',
                'message' => 'Tiang awal dan tiang akhir tidak boleh merupakan aset yang sama (self-loop dilarang).'
            ];
        }

        $sourceAsset = $this->db->table('assets')->select('id, nama_asset, kode_asset, jenis_asset, latitude, longitude, penyulang_id')->where('id', $sourceId)->get()->getRowArray();
        $targetAsset = $this->db->table('assets')->select('id, nama_asset, kode_asset, jenis_asset, latitude, longitude, penyulang_id')->where('id', $targetId)->get()->getRowArray();

        if (!$sourceAsset || !$targetAsset) {
            return [
                'status'  => 'error',
                'message' => 'Aset sumber atau target tidak ditemukan.'
            ];
        }

        // Strict JTM Pole Validation: Transline MUST ONLY connect physical JTM poles
        $srcPoleCheck = self::isJtmPoleAsset($sourceAsset);
        if (!$srcPoleCheck['valid']) {
            return [
                'status'  => 'error',
                'message' => "Aset sumber [{$sourceAsset['kode_asset']}] ditolak: " . $srcPoleCheck['reason']
            ];
        }

        $tgtPoleCheck = self::isJtmPoleAsset($targetAsset);
        if (!$tgtPoleCheck['valid']) {
            return [
                'status'  => 'error',
                'message' => "Aset target [{$targetAsset['kode_asset']}] ditolak: " . $tgtPoleCheck['reason']
            ];
        }

        $sourcePenyulang = (int)($sourceAsset['penyulang_id'] ?? 0);
        $targetPenyulang = (int)($targetAsset['penyulang_id'] ?? 0);

        // Feeder Isolation Constraint: Disallow cross-feeder topology
        if ($sourcePenyulang > 0 && $targetPenyulang > 0 && $sourcePenyulang !== $targetPenyulang) {
            return [
                'status'  => 'error',
                'message' => "Aset sumber ({$sourceAsset['kode_asset']}) berada di penyulang ID {$sourcePenyulang}, sedangkan aset target ({$targetAsset['kode_asset']}) berada di penyulang ID {$targetPenyulang}. Topologi antar-penyulang dilarang."
            ];
        }

        if ($penyulangId <= 0) {
            $penyulangId = $sourcePenyulang > 0 ? $sourcePenyulang : $targetPenyulang;
        }

        if ($penyulangId > 0 && (($sourcePenyulang > 0 && $sourcePenyulang !== $penyulangId) || ($targetPenyulang > 0 && $targetPenyulang !== $penyulangId))) {
            return [
                'status'  => 'error',
                'message' => "Aset tidak sesuai dengan penyulang ID {$penyulangId} yang ditentukan."
            ];
        }

        $conductorType     = (string)($data['conductor_type'] ?? 'AAAC');
        $conductorSize     = (string)($data['conductor_size'] ?? '150 mm²');
        $conductorMaterial = (string)($data['conductor_material'] ?? 'ALUMINUM_ALLOY');
        $installationType  = (string)($data['installation_type'] ?? 'OVERHEAD');
        $circuitConfig     = (string)($data['circuit_config'] ?? '3_PHASE');
        $actorName         = $actor['name'] ?? 'SYSTEM';
        $actorId           = $actor['id'] ?? null;

        $distance = (float)($data['distance_meters'] ?? 0);
        if ($distance <= 0 && !empty($sourceAsset['latitude']) && !empty($targetAsset['latitude'])) {
            $distance = round($this->haversineDistance(
                (float)$sourceAsset['latitude'], (float)$sourceAsset['longitude'],
                (float)$targetAsset['latitude'], (float)$targetAsset['longitude']
            ), 2);
        }

        // Geometry: default to straight LineString if not provided
        $geometry = $data['geometry'] ?? null;
        if (empty($geometry)) {
            $coords = [
                [(float)$sourceAsset['longitude'], (float)$sourceAsset['latitude']],
                [(float)$targetAsset['longitude'], (float)$targetAsset['latitude']],
            ];
            $geometry = json_encode($coords);
        } elseif (is_array($geometry)) {
            $coords = $geometry['coordinates'] ?? $geometry;
            $geometry = json_encode($coords);
        }

        $translineCode = $data['transline_code'] ?? ('TL-' . $penyulangId . '-' . $sourceId . '-' . $targetId);
        $mode          = strtoupper((string)($data['connection_mode'] ?? 'ADD'));

        $this->db->transBegin();
        try {
            // Mode ADD is strictly append-only (GIS_TRANSLINE_APPEND_ONLY invariant).
            // Under NO circumstance can ADD mutate, deactivate, or delete other edges.
            if ($mode === 'REPLACE') {
                // Deactivate previous connections originating from source
                $this->db->table('gis_translines')
                    ->where('penyulang_id', $penyulangId)
                    ->where('source_asset_id', $sourceId)
                    ->where('target_asset_id !=', $targetId)
                    ->update([
                        'is_active'  => 0,
                        'status'     => 'DELETED',
                        'deleted_at' => date('Y-m-d H:i:s'),
                    ]);

                if ($this->db->tableExists('asset_relationships')) {
                    $relQuery = $this->db->table('asset_relationships')
                        ->where('source_asset_id', $sourceId)
                        ->where('target_asset_id !=', $targetId);
                    if ($this->db->fieldExists('penyulang_id', 'asset_relationships')) {
                        $relQuery->where('penyulang_id', $penyulangId);
                    }
                    $relQuery->delete();
                }
            }

            // 1. Check existing row in gis_translines (bidirectional pair search)
            $existing = $this->db->table('gis_translines')
                ->groupStart()
                    ->where('source_asset_id', $sourceId)->where('target_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('source_asset_id', $targetId)->where('target_asset_id', $sourceId)
                ->groupEnd()
                ->where('penyulang_id', $penyulangId)
                ->get()
                ->getRowArray();

            $translineId = null;
            $isDuplicate = false;
            $payload = [
                'transline_code'     => $translineCode,
                'penyulang_id'       => $penyulangId,
                'source_asset_id'    => $sourceId,
                'target_asset_id'    => $targetId,
                'geometry'           => $geometry,
                'geometry_type'      => 'LineString',
                'conductor_type'     => $conductorType,
                'conductor_size'     => $conductorSize,
                'conductor_material' => $conductorMaterial,
                'installation_type'  => $installationType,
                'circuit_config'     => $circuitConfig,
                'distance_meters'    => $distance,
                'status'             => 'ACTIVE',
                'is_active'          => 1,
                'updated_by'         => $actorName,
                'updated_at'         => date('Y-m-d H:i:s'),
                'deleted_at'         => null,
            ];

            if ($existing) {
                $translineId = (int)$existing['id'];
                if ((int)$existing['is_active'] === 1 && empty($existing['deleted_at'])) {
                    $isDuplicate = true;
                }
                // Update or reactivate existing record without increasing count (idempotent duplicate protection)
                $this->db->table('gis_translines')->where('id', $translineId)->update($payload);
            } else {
                $payload['created_by'] = $actorName;
                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('gis_translines')->insert($payload);
                $translineId = (int)$this->db->insertID();
            }

            // 2. Synchronize asset_relationships
            $relExisting = $this->db->table('asset_relationships')
                ->groupStart()
                    ->where('parent_asset_id', $sourceId)->where('child_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('parent_asset_id', $targetId)->where('child_asset_id', $sourceId)
                ->groupEnd()
                ->get()
                ->getRowArray();

            $relData = [
                'parent_asset_id'    => $sourceId,
                'child_asset_id'     => $targetId,
                'source_asset_id'    => $sourceId,
                'target_asset_id'    => $targetId,
                'penyulang_id'       => $penyulangId,
                'relationship_type'  => 'NETWORK',
                'conductor_type'     => $conductorType,
                'conductor_size'     => $conductorSize,
                'conductor_material' => $conductorMaterial,
                'installation_type'  => $installationType,
                'circuit_config'     => $circuitConfig,
                'distance_meters'    => $distance,
                'source'             => 'GIS_TRANSLINE_CRUD',
                'status'             => 'VERIFIED',
                'verified_by'        => $actorId,
                'verified_at'        => date('Y-m-d H:i:s'),
                'is_active'          => 1,
                'updated_at'         => date('Y-m-d H:i:s'),
            ];

            $existingRelFields = $this->db->getFieldNames('asset_relationships');
            $filteredRelData = array_intersect_key($relData, array_flip($existingRelFields));

            if ($relExisting) {
                $this->db->table('asset_relationships')->where('id', $relExisting['id'])->update($filteredRelData);
            } else {
                $filteredRelData['created_by'] = $actorName;
                $filteredRelData['created_at'] = date('Y-m-d H:i:s');
                $filteredRelData = array_intersect_key($filteredRelData, array_flip($existingRelFields));
                $this->db->table('asset_relationships')->insert($filteredRelData);
            }

            // 3. Update assets.parent_asset_id
            $this->db->table('assets')->where('id', $targetId)->update(['parent_asset_id' => $sourceId]);

            // 4. Rebuild full derived multi-segment topology snapshot from ALL active gis_translines
            $freshTopology = $this->rebuildFeederTopologySnapshot($penyulangId, $actorName);

            $this->db->transCommit();

            return [
                'status'          => 'success',
                'is_direct_commit'=> true,
                'is_duplicate'    => $isDuplicate,
                'message'         => $isDuplicate
                    ? "Sambungan transline #{$translineId} ({$sourceAsset['nama_asset']} ➔ {$targetAsset['nama_asset']}) sudah ada dan aktif."
                    : "Transline #{$translineId} ({$sourceAsset['nama_asset']} ➔ {$targetAsset['nama_asset']}) berhasil disimpan.",
                'transline_id'    => $translineId,
                'translines'      => $this->getFeederTranslines($penyulangId),
                'topology'        => $freshTopology,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'message' => 'Exception in saveTransline: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete an individual transline segment by ID (Atomic CRUD)
     *
     * @param int $translineId
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function deleteTransline(int $translineId, ?array $actor = null): array
    {
        if ($translineId <= 0) {
            return ['status' => 'error', 'message' => 'ID Transline tidak valid.'];
        }

        $row = $this->translineModel->find($translineId);
        if (!$row) {
            return ['status' => 'error', 'message' => "Transline #{$translineId} tidak ditemukan."];
        }

        $penyulangId = (int)$row['penyulang_id'];
        $sourceId    = (int)$row['source_asset_id'];
        $targetId    = (int)$row['target_asset_id'];
        $actorName   = $actor['name'] ?? 'SYSTEM';

        $this->db->transBegin();
        try {
            // 1. Soft-delete row in gis_translines
            $this->db->table('gis_translines')->where('id', $translineId)->update([
                'is_active'  => 0,
                'status'     => 'DELETED',
                'updated_by' => $actorName,
                'deleted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // 2. Remove / deactivate relationship
            $this->db->table('asset_relationships')
                ->groupStart()
                    ->where('parent_asset_id', $sourceId)->where('child_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('parent_asset_id', $targetId)->where('child_asset_id', $sourceId)
                ->groupEnd()
                ->delete();

            // 3. Clear parent_asset_id
            $this->db->table('assets')->where('id', $targetId)->where('parent_asset_id', $sourceId)->update(['parent_asset_id' => null]);
            $this->db->table('assets')->where('id', $sourceId)->where('parent_asset_id', $targetId)->update(['parent_asset_id' => null]);

            // 4. Rebuild full derived topology snapshot from remaining active gis_translines
            $freshTopology = $this->rebuildFeederTopologySnapshot($penyulangId, $actorName);

            $this->db->transCommit();

            return [
                'status'          => 'success',
                'is_direct_commit'=> true,
                'message'         => "Transline #{$translineId} berhasil dihapus.",
                'translines'      => $this->getFeederTranslines($penyulangId),
                'topology'        => $freshTopology,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['status' => 'error', 'message' => 'Gagal menghapus transline: ' . $e->getMessage()];
        }
    }

    /**
     * Update custom polyline geometry vertices for a specific segment
     *
     * @param int $penyulangId
     * @param int $sourceId
     * @param int $targetId
     * @param array $geoJsonGeometry
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function updateSegmentGeometry(int $penyulangId, int $sourceId, int $targetId, array $geoJsonGeometry, ?array $actor = null, ?int $explicitTranslineId = null, ?int $sectionId = null): array
    {
        $coords = $geoJsonGeometry['coordinates'] ?? [];
        if (empty($coords)) {
            return ['status' => 'error', 'message' => 'Koordinat segmen tidak boleh kosong.'];
        }

        // Strict JTM Pole Validation: Transline MUST ONLY connect physical JTM poles
        $sourceAsset = $this->db->table('assets')->select('id, nama_asset, kode_asset, jenis_asset')->where('id', $sourceId)->get()->getRowArray();
        $targetAsset = $this->db->table('assets')->select('id, nama_asset, kode_asset, jenis_asset')->where('id', $targetId)->get()->getRowArray();
        if ($sourceAsset) {
            $srcPoleCheck = self::isJtmPoleAsset($sourceAsset);
            if (!$srcPoleCheck['valid']) {
                return ['status' => 'error', 'message' => "Aset sumber [{$sourceAsset['kode_asset']}] ditolak: " . $srcPoleCheck['reason']];
            }
        }
        if ($targetAsset) {
            $tgtPoleCheck = self::isJtmPoleAsset($targetAsset);
            if (!$tgtPoleCheck['valid']) {
                return ['status' => 'error', 'message' => "Aset target [{$targetAsset['kode_asset']}] ditolak: " . $tgtPoleCheck['reason']];
            }
        }

        $actorName = $actor['name'] ?? 'SYSTEM';

        $this->db->transBegin();
        try {
            // Locate existing transline row
            $row = null;
            if ($explicitTranslineId !== null && $explicitTranslineId > 0) {
                $row = $this->db->table('gis_translines')
                    ->where('id', $explicitTranslineId)
                    ->get()
                    ->getRowArray();
            }

            if (!$row) {
                $row = $this->db->table('gis_translines')
                    ->groupStart()
                        ->where('source_asset_id', $sourceId)->where('target_asset_id', $targetId)
                    ->groupEnd()
                    ->orGroupStart()
                        ->where('source_asset_id', $targetId)->where('target_asset_id', $sourceId)
                    ->groupEnd()
                    ->where('penyulang_id', $penyulangId)
                    ->where('is_active', 1)
                    ->get()
                    ->getRowArray();
            }

            if ($row) {
                $updateData = [
                    'geometry'   => json_encode($coords),
                    'updated_by' => $actorName,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                if ($sectionId !== null && $this->db->fieldExists('section_id', 'gis_translines')) {
                    $updateData['section_id'] = $sectionId;
                }
                $this->db->table('gis_translines')->where('id', $row['id'])->update($updateData);
                $translineId = (int)$row['id'];
            } else {
                // Create new record for this custom segment
                $insertData = [
                    'transline_code'  => "TL-{$penyulangId}-{$sourceId}-{$targetId}",
                    'penyulang_id'    => $penyulangId,
                    'source_asset_id' => $sourceId,
                    'target_asset_id' => $targetId,
                    'geometry'        => json_encode($coords),
                    'geometry_type'   => 'LineString',
                    'status'          => 'ACTIVE',
                    'is_active'       => 1,
                    'created_by'      => $actorName,
                    'created_at'      => date('Y-m-d H:i:s'),
                ];
                if ($sectionId !== null && $this->db->fieldExists('section_id', 'gis_translines')) {
                    $insertData['section_id'] = $sectionId;
                }
                $this->db->table('gis_translines')->insert($insertData);
                $translineId = (int)$this->db->insertID();
            }

            // If section_id was updated, ensure connected endpoints in assets table receive the section link
            if ($sectionId !== null && $sectionId > 0 && $this->db->tableExists('assets')) {
                foreach ([$sourceId, $targetId] as $endAssetId) {
                    if ($endAssetId > 0) {
                        $this->db->table('assets')
                            ->where('id', $endAssetId)
                            ->groupStart()
                                ->where('section_id IS NULL')
                                ->orWhere('section_id', 0)
                            ->groupEnd()
                            ->update([
                                'section_id'                => $sectionId,
                                'section_resolution_method' => 'MANUAL_GIS_TOPOLOGY',
                                'updated_at'                => date('Y-m-d H:i:s'),
                            ]);
                    }
                }
            }

            // Rebuild full derived topology snapshot
            $freshTopology = $this->rebuildFeederTopologySnapshot($penyulangId, $actorName);

            $this->db->transCommit();

            return [
                'status'       => 'success',
                'message'      => "Bentuk geometri transline #{$translineId} berhasil diperbarui.",
                'transline_id' => $translineId,
                'translines'   => $this->getFeederTranslines($penyulangId),
                'topology'     => $freshTopology,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['status' => 'error', 'message' => 'Gagal memperbarui bentuk segmen: ' . $e->getMessage()];
        }
    }

    /**
     * Predict transline sections from GI / source through network adjacency continuity.
     *
     * @param int $penyulangId
     * @return array<string, mixed>
     */
    public function predictTranslineSection(int $penyulangId): array
    {
        if (!$this->db->tableExists('gis_translines') || !$this->db->tableExists('assets')) {
            return ['status' => 'error', 'message' => 'Tabel gis_translines atau assets tidak ditemukan.'];
        }

        // Fetch all active translines for this feeder
        $translines = $this->db->table('gis_translines')
            ->where('penyulang_id', $penyulangId)
            ->where('is_active', 1)
            ->where('deleted_at IS NULL')
            ->get()->getResultArray();

        // Fetch all assets with their current section_id
        $assets = $this->db->table('assets')
            ->select('id, section_id')
            ->where('penyulang_id', $penyulangId)
            ->where('deleted_at IS NULL')
            ->get()->getResultArray();

        $assetSectionMap = [];
        foreach ($assets as $a) {
            $assetSectionMap[(int)$a['id']] = $a['section_id'] !== null ? (int)$a['section_id'] : null;
        }

        // Build adjacency graph
        $adjacency = [];
        foreach ($translines as $tl) {
            $s = (int)$tl['source_asset_id'];
            $t = (int)$tl['target_asset_id'];
            $adjacency[$s][] = $t;
            $adjacency[$t][] = $s;
        }

        $predictions = [];
        $unresolvedCount = 0;
        foreach ($assetSectionMap as $aId => $sId) {
            if ($sId === null || $sId === 0) {
                $unresolvedCount++;
                // Check neighbors
                $neighborSections = [];
                foreach ($adjacency[$aId] ?? [] as $neighborId) {
                    $nSec = $assetSectionMap[$neighborId] ?? null;
                    if ($nSec !== null && $nSec > 0) {
                        $neighborSections[] = $nSec;
                    }
                }
                if (!empty($neighborSections)) {
                    $counts = array_count_values($neighborSections);
                    arsort($counts);
                    $predictedSectionId = key($counts);
                    $confidence = round(current($counts) / count($neighborSections), 2);
                    $predictions[$aId] = [
                        'asset_id'             => $aId,
                        'predicted_section_id' => $predictedSectionId,
                        'confidence'           => $confidence,
                        'resolution_source'    => 'TOPOLOGY_ADJACENCY_CONTINUITY'
                    ];
                }
            }
        }

        return [
            'status'           => 'success',
            'penyulang_id'     => $penyulangId,
            'total_assets'     => count($assets),
            'unresolved_count' => $unresolvedCount,
            'predicted_count'  => count($predictions),
            'predictions'      => $predictions,
        ];
    }

    /**
     * Rebuild the full derived MultiLineString topology snapshot from ALL active gis_translines
     *
     * @param int $penyulangId
     * @param string $actorName
     * @return array<string, mixed>
     */
    public function rebuildFeederTopologySnapshot(int $penyulangId, string $actorName): array
    {
        if ($penyulangId <= 0) {
            return ['type' => 'MultiLineString', 'coordinates' => [], 'nodes' => [], 'edges' => []];
        }

        // Fetch all active translines for this feeder
        $activeRows = $this->translineModel->getActiveTranslinesByFeeder($penyulangId);

        $multiLineCoords = [];
        $allNodes = [];
        $edges = [];

        foreach ($activeRows as $r) {
            $geomStr = $r['geometry'] ?? '';
            $segCoords = !empty($geomStr) ? json_decode($geomStr, true) : null;

            if (!empty($segCoords) && is_array($segCoords) && count($segCoords) >= 2) {
                $multiLineCoords[] = $segCoords;
                foreach ($segCoords as $pt) {
                    $allNodes[] = $pt;
                }

                $edges[] = [
                    'transline_id'       => (int)$r['id'],
                    'edge_id'            => (int)$r['id'],
                    'from_asset_id'      => (int)$r['source_asset_id'],
                    'to_asset_id'        => (int)$r['target_asset_id'],
                    'conductor_type'     => $r['conductor_type'] ?? 'AAAC',
                    'conductor_size'     => $r['conductor_size'] ?? '150 mm²',
                    'conductor_label'    => "{$r['conductor_type']} {$r['conductor_size']}",
                    'conductor_material' => $r['conductor_material'] ?? 'ALUMINUM_ALLOY',
                    'installation_type'  => $r['installation_type'] ?? 'OVERHEAD',
                    'circuit_config'     => $r['circuit_config'] ?? '3_PHASE',
                    'length_meter'       => (float)($r['distance_meters'] ?? 0),
                    'coordinates'        => $segCoords,
                    'status'             => 'ACTIVE',
                ];
            }
        }

        // Fallback: If gis_translines is empty, fallback to asset_relationships & assets
        if (empty($multiLineCoords)) {
            $fallback = $this->assetRepository->getFeederNetworkSegments($penyulangId);
            $multiLineCoords = $fallback['coordinates'] ?? [];
            $allNodes = $fallback['nodes'] ?? [];
            $edges = $fallback['edges'] ?? [];
        }

        $freshTopology = [
            'type'        => 'MultiLineString',
            'coordinates' => $multiLineCoords,
            'nodes'       => $allNodes,
            'edges'       => $edges,
        ];

        // Persist to network_topology_versions as derived snapshot
        if ($this->db->tableExists('network_topology_versions') && !empty($multiLineCoords)) {
            $ntvFields = $this->db->getFieldNames('network_topology_versions');

            // Supersede old active versions
            $supPayload = ['is_active' => 0];
            if (in_array('version_status', $ntvFields, true)) {
                $supPayload['version_status'] = 'SUPERSEDED';
            }
            if (in_array('superseded_at', $ntvFields, true)) {
                $supPayload['superseded_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('updated_at', $ntvFields, true)) {
                $supPayload['updated_at'] = date('Y-m-d H:i:s');
            }

            $this->db->table('network_topology_versions')
                     ->where('penyulang_id', $penyulangId)
                     ->where('is_active', 1)
                     ->update($supPayload);

            $maxVerRow = $this->db->table('network_topology_versions')
                                    ->where('penyulang_id', $penyulangId)
                                    ->selectMax('version_no')
                                    ->get()
                                    ->getRowArray();
            $maxVer = (int)($maxVerRow['version_no'] ?? 0);
            $newVer = $maxVer + 1;

            $insertPayload = [
                'penyulang_id'     => $penyulangId,
                'version_no'       => $newVer,
                'geojson_topology' => json_encode($freshTopology),
                'nodes_count'      => count($allNodes),
                'segments_count'   => count($multiLineCoords),
                'is_active'        => 1,
                'version_status'   => 'ACTIVE',
                'created_by'       => $actorName,
                'created_at'       => date('Y-m-d H:i:s'),
            ];
            $filteredInsert = array_intersect_key($insertPayload, array_flip($ntvFields));

            $this->db->table('network_topology_versions')->insert($filteredInsert);
        }

        return $freshTopology;
    }

    /**
     * TL-01 Visual Realization: Authoritative Read-Only Transline Query Service
     *
     * Pure SELECT query with single controlled JOIN between gis_translines,
     * source assets, and target assets (zero N+1 queries).
     *
     * HARD SAFETY INVARIANTS:
     * - Zero writes (INSERT=0, UPDATE=0, DELETE=0, DDL=0).
     * - Endpoints resolve strictly to assets.id.
     * - Temuan is strictly forbidden as an endpoint (domain-typed rejection).
     * - Coordinates originate strictly from assets.latitude and assets.longitude.
     * - NO synthetic/canonical fallback in production service. Returns empty state if DB table has 0 rows.
     * - Scoped by ULP, Penyulang, and Section with cross-scope rejection and anomaly diagnostics.
     *
     * @param array<string, mixed> $scope ['penyulang_id' => int, 'section_id' => ?int, 'ulp_id' => ?int, 'options' => ?array]
     * @param int|null $userUlpId
     * @return array<string, mixed>
     */
    public function getAuthoritativeTranslines(array $scope = [], ?int $userUlpId = null): array
    {
        $penyulangId = (int)($scope['penyulang_id'] ?? 0);
        $sectionId   = isset($scope['section_id']) && $scope['section_id'] !== '' ? (int)$scope['section_id'] : 0;
        $ulpId       = isset($scope['ulp_id']) && $scope['ulp_id'] !== '' ? (int)$scope['ulp_id'] : 0;
        $options     = (array)($scope['options'] ?? []);

        // 1. Domain-Typed Temuan Firewall Check
        $sourceType = strtoupper(trim((string)($options['source_type'] ?? 'ASSET')));
        $targetType = strtoupper(trim((string)($options['target_type'] ?? 'ASSET')));
        if ($sourceType === 'TEMUAN' || $sourceType === 'FINDING' || $targetType === 'TEMUAN' || $targetType === 'FINDING') {
            return [
                'success'     => false,
                'status'      => 'error',
                'error_code'  => 'TEMUAN_ENDPOINT_FORBIDDEN',
                'message'     => 'Titik temuan/finding tidak boleh menjadi endpoint transline JTM. Topologi jaringan hanya menghubungkan antar aset JTM.',
                'data'        => [],
                'diagnostics' => [],
            ];
        }

        // 2. Scope Validation: Scope must not be empty
        if ($penyulangId <= 0 && $ulpId <= 0) {
            return [
                'success'     => true,
                'status'      => 'empty',
                'message'     => 'Penyulang atau ULP wajib dipilih untuk memuat data transline.',
                'scope'       => [
                    'ulp_id'       => null,
                    'penyulang_id' => null,
                    'section_id'   => null,
                ],
                'total'       => 0,
                'data'        => [],
                'diagnostics' => [],
            ];
        }

        // 3. Feeder & ULP Authorization Scope Firewall
        $feederUlpId = null;
        if ($penyulangId > 0) {
            if ($this->db->tableExists('penyulang')) {
                $feeder = $this->db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
                if (!$feeder) {
                    return [
                        'success'     => true,
                        'status'      => 'success',
                        'message'     => "Penyulang ID {$penyulangId} tidak ditemukan atau belum memiliki transline.",
                        'scope'       => [
                            'ulp_id'       => null,
                            'penyulang_id' => $penyulangId,
                            'section_id'   => $sectionId > 0 ? $sectionId : null,
                        ],
                        'total'       => 0,
                        'data'        => [],
                        'translines'  => [],
                        'diagnostics' => [],
                    ];
                }
                $feederUlpId = (int)($feeder['ulp_id'] ?? 0);

                // Check authorization boundary against session userUlpId
                if ($userUlpId !== null && $userUlpId > 0 && $feederUlpId > 0 && $userUlpId !== $feederUlpId) {
                    return [
                        'success'     => false,
                        'status'      => 'error',
                        'error_code'  => 'UNAUTHORIZED_FEEDER_ACCESS',
                        'message'     => 'Akses ditolak: Penyulang berada di luar batas otorisasi ULP Anda.',
                        'data'        => [],
                        'diagnostics' => [],
                    ];
                }

                // Check cross-scope if request explicitly passed an ulp_id that differs from feeder's ULP
                if ($ulpId > 0 && $feederUlpId > 0 && $ulpId !== $feederUlpId) {
                    return [
                        'success'     => false,
                        'status'      => 'error',
                        'error_code'  => 'CROSS_SCOPE_REQUEST',
                        'message'     => "Penyulang #{$penyulangId} tidak berada dalam ULP #{$ulpId}.",
                        'data'        => [],
                        'diagnostics' => [],
                    ];
                }
            }

            // 4. Section Scope Validation
            if ($sectionId > 0 && $this->db->tableExists('sections')) {
                $section = $this->db->table('sections')->where('id', $sectionId)->get()->getRowArray();
                if ($section && isset($section['penyulang_id']) && (int)$section['penyulang_id'] !== $penyulangId) {
                    return [
                        'success'     => false,
                        'status'      => 'error',
                        'error_code'  => 'CROSS_SCOPE_REQUEST',
                        'message'     => "Seksi #{$sectionId} tidak berada dalam penyulang #{$penyulangId}.",
                        'data'        => [],
                        'diagnostics' => [],
                    ];
                }
            }
        } elseif ($ulpId > 0) {
            // ULP level authorization check
            if ($userUlpId !== null && $userUlpId > 0 && $ulpId !== $userUlpId) {
                return [
                    'success'     => false,
                    'status'      => 'error',
                    'error_code'  => 'UNAUTHORIZED_FEEDER_ACCESS',
                    'message'     => 'Akses ditolak: ULP yang diminta berada di luar batas otorisasi Anda.',
                    'data'        => [],
                    'diagnostics' => [],
                ];
            }
            $feederUlpId = $ulpId;
        }

        // 5. Query Authoritative Translines (Single controlled JOIN, zero N+1)
        if (!$this->db->tableExists('gis_translines') || !$this->db->tableExists('assets')) {
            return [
                'success'     => true,
                'status'      => 'success',
                'message'     => 'Tabel gis_translines atau assets belum tersedia.',
                'scope'       => [
                    'ulp_id'       => $feederUlpId,
                    'penyulang_id' => $penyulangId > 0 ? $penyulangId : null,
                    'section_id'   => $sectionId > 0 ? $sectionId : null,
                ],
                'total'       => 0,
                'data'        => [],
                'diagnostics' => [],
            ];
        }

        $hasSectionCol = $this->db->fieldExists('section_id', 'gis_translines');
        $sectionSelect = $hasSectionCol ? 't.section_id,' : '';

        $builder = $this->db->table('gis_translines t')
            ->select('
                t.id, t.transline_code, t.penyulang_id, ' . $sectionSelect . '
                t.source_asset_id, t.target_asset_id,
                t.conductor_type, t.conductor_size, t.distance_meters,
                t.status, t.is_active, t.created_by,
                sa.id AS sa_id, sa.kode_asset AS sa_code, sa.nama_asset AS sa_name,
                sa.latitude AS sa_lat, sa.longitude AS sa_lng,
                sa.penyulang_id AS sa_penyulang_id, sa.section_id AS sa_section_id,
                ta.id AS ta_id, ta.kode_asset AS ta_code, ta.nama_asset AS ta_name,
                ta.latitude AS ta_lat, ta.longitude AS ta_lng,
                ta.penyulang_id AS ta_penyulang_id, ta.section_id AS ta_section_id
            ')
            ->join('assets sa', 'sa.id = t.source_asset_id', 'left')
            ->join('assets ta', 'ta.id = t.target_asset_id', 'left');

        if ($penyulangId > 0) {
            $builder->where('t.penyulang_id', $penyulangId);
        } elseif ($ulpId > 0 && $this->db->tableExists('penyulang')) {
            $builder->join('penyulang p', 'p.id = t.penyulang_id', 'inner')
                    ->where('p.ulp_id', $ulpId);
        }

        if ($sectionId > 0) {
            if ($hasSectionCol) {
                $builder->where('t.section_id', $sectionId);
            } else {
                $builder->groupStart()
                        ->where('sa.section_id', $sectionId)
                        ->orWhere('ta.section_id', $sectionId)
                        ->groupEnd();
            }
        }

        // Active filters
        if ($this->db->fieldExists('is_active', 'gis_translines')) {
            $builder->where('t.is_active', 1);
        }
        if ($this->db->fieldExists('status', 'gis_translines')) {
            $builder->where('t.status', 'ACTIVE');
        }
        if ($this->db->fieldExists('deleted_at', 'gis_translines')) {
            $builder->where('t.deleted_at IS NULL');
        }

        $rows = $builder->orderBy('t.id', 'ASC')->get()->getResultArray();

        // 6. Integrity Verification & Diagnostics Loop (Pure Read-Only: No Mutation)
        $data = [];
        $diagnostics = [];

        foreach ($rows as $r) {
            $tId = (int)$r['id'];
            $sourceId = $r['sa_id'] !== null ? (int)$r['sa_id'] : null;
            $targetId = $r['ta_id'] !== null ? (int)$r['ta_id'] : null;

            // Check orphan source or target
            if ($sourceId === null || $targetId === null) {
                $diagnostics[] = [
                    'transline_id' => $tId,
                    'code'         => 'ORPHAN_ENDPOINT',
                    'type'         => 'ORPHAN_ENDPOINT',
                    'message'      => "Transline #{$tId} memiliki endpoint yang tidak ditemukan pada tabel assets (source: " . ($r['source_asset_id'] ?? 'null') . ", target: " . ($r['target_asset_id'] ?? 'null') . ").",
                ];
                continue;
            }

            // Check self-loop
            if ($sourceId === $targetId) {
                $diagnostics[] = [
                    'transline_id' => $tId,
                    'code'         => 'IDENTICAL_ENDPOINTS',
                    'type'         => 'IDENTICAL_ENDPOINTS',
                    'message'      => "Transline #{$tId} memiliki source dan target identik (aset #{$sourceId}).",
                ];
                continue;
            }

            // Check coordinates
            $saLat = $r['sa_lat'] !== null ? (float)$r['sa_lat'] : null;
            $saLng = $r['sa_lng'] !== null ? (float)$r['sa_lng'] : null;
            $taLat = $r['ta_lat'] !== null ? (float)$r['ta_lat'] : null;
            $taLng = $r['ta_lng'] !== null ? (float)$r['ta_lng'] : null;

            $hasValidCoords = ($saLat !== null && $saLng !== null && $taLat !== null && $taLng !== null &&
                               !($saLat == 0.0 && $saLng == 0.0) && !($taLat == 0.0 && $taLng == 0.0));

            if (!$hasValidCoords) {
                $diagnostics[] = [
                    'transline_id' => $tId,
                    'code'         => 'MISSING_COORDINATE',
                    'type'         => 'MISSING_COORDINATE',
                    'message'      => "Transline #{$tId} memiliki koordinat aset yang hilang atau bernilai 0.",
                ];
                continue;
            }

            // Check scope consistency (Domain & scope integrity)
            $tFeeder = (int)$r['penyulang_id'];
            $saFeeder = (int)($r['sa_penyulang_id'] ?? 0);
            $taFeeder = (int)($r['ta_penyulang_id'] ?? 0);

            if (($saFeeder > 0 && $saFeeder !== $tFeeder) || ($taFeeder > 0 && $taFeeder !== $tFeeder)) {
                $diagnostics[] = [
                    'transline_id' => $tId,
                    'code'         => 'CROSS_SCOPE_ENDPOINT',
                    'type'         => 'CROSS_SCOPE_ENDPOINT',
                    'message'      => "Transline #{$tId} pada penyulang #{$tFeeder} menghubungkan aset dari penyulang berbeda (source: #{$saFeeder}, target: #{$taFeeder}).",
                ];
                continue;
            }

            $lengthM = (float)($r['distance_meters'] ?? 0.0);
            if ($lengthM <= 0.0) {
                $lengthM = round($this->haversineDistance($saLat, $saLng, $taLat, $taLng), 2);
            }

            $conductorLabel = trim(($r['conductor_type'] ?? 'AAAC') . ' ' . ($r['conductor_size'] ?? '150 mm²'));
            $translineCode  = $r['transline_code'] ?: ('TL-' . $tFeeder . '-' . $tId);

            $data[] = [
                'id'                 => $tId,
                'transline_id'       => $tId,
                'code'               => $translineCode,
                'transline_code'     => $translineCode,
                'penyulang_id'       => $tFeeder,
                'section_id'         => !empty($r['section_id']) ? (int)$r['section_id'] : null,
                'source_asset_id'    => $sourceId,
                'target_asset_id'    => $targetId,
                'source_section_id'  => !empty($r['sa_section_id']) ? (int)$r['sa_section_id'] : null,
                'target_section_id'  => !empty($r['ta_section_id']) ? (int)$r['ta_section_id'] : null,
                'from_asset_id'      => $sourceId,
                'to_asset_id'        => $targetId,
                'source_asset'       => [
                    'id'         => $sourceId,
                    'code'       => $r['sa_code'] ?: ('AST-' . $sourceId),
                    'kode_asset' => $r['sa_code'] ?: ('AST-' . $sourceId),
                    'name'       => $r['sa_name'] ?: ('Tiang #' . $sourceId),
                    'nama_asset' => $r['sa_name'] ?: ('Tiang #' . $sourceId),
                    'latitude'   => $saLat,
                    'longitude'  => $saLng,
                ],
                'target_asset'       => [
                    'id'         => $targetId,
                    'code'       => $r['ta_code'] ?: ('AST-' . $targetId),
                    'kode_asset' => $r['ta_code'] ?: ('AST-' . $targetId),
                    'name'       => $r['ta_name'] ?: ('Tiang #' . $targetId),
                    'nama_asset' => $r['ta_name'] ?: ('Tiang #' . $targetId),
                    'latitude'   => $taLat,
                    'longitude'  => $taLng,
                ],
                'coordinates'        => [
                    [$saLng, $saLat],
                    [$taLng, $taLat],
                ],
                'length_m'           => $lengthM,
                'length_meter'       => $lengthM,
                'distance_meters'    => $lengthM,
                'conductor'          => $conductorLabel,
                'conductor_label'    => $conductorLabel,
                'conductor_type'     => $r['conductor_type'] ?? 'AAAC',
                'conductor_size'     => $r['conductor_size'] ?? '150 mm²',
                'conductor_material' => $r['conductor_material'] ?? 'ALUMINUM_ALLOY',
                'status'             => $r['status'] ?? 'ACTIVE',
                'is_active'          => 1,
                'created_by'         => $r['created_by'] ?? null,
            ];
        }

        return [
            'success'     => true,
            'status'      => 'success',
            'scope'       => [
                'ulp_id'       => $feederUlpId,
                'penyulang_id' => $penyulangId > 0 ? $penyulangId : null,
                'section_id'   => $sectionId > 0 ? $sectionId : null,
            ],
            'total'       => count($data),
            'data'        => $data,
            'translines'  => $data,
            'diagnostics' => $diagnostics,
        ];
    }
}

