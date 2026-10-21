<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

class BatchTranslineValidationService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Dry-Run analysis across all candidate translines
     */
    public function dryRun(?int $penyulangId = null): array
    {
        $candidates = $this->collectCandidates($penyulangId);

        $summary = [
            'total_evaluated'   => count($candidates),
            'valid_count'       => 0,
            'rejected_count'    => 0,
            'duplicate_count'   => 0,
            'conflict_count'    => 0,
            'requires_input'    => 0,
            'feeders_evaluated' => [],
            'valid_segments'    => [],
            'rejected_segments' => [],
            'duplicates'        => [],
            'feeders_needing_input' => [],
        ];

        $existingTranslinesMap = $this->loadExistingTranslinesMap($penyulangId);

        foreach ($candidates as $cand) {
            $fId = (int)$cand['penyulang_id'];
            $fName = $cand['nama_penyulang'] ?? "Penyulang #{$fId}";
            if (!isset($summary['feeders_evaluated'][$fId])) {
                $summary['feeders_evaluated'][$fId] = [
                    'penyulang_id'   => $fId,
                    'nama_penyulang' => $fName,
                    'total'          => 0,
                    'valid'          => 0,
                    'rejected'       => 0,
                    'duplicate'      => 0,
                ];
            }
            $summary['feeders_evaluated'][$fId]['total']++;

            $sId = (int)$cand['source_asset_id'];
            $tId = (int)$cand['target_asset_id'];

            // 1. Endpoint Cardinality & Self-Loop
            if ($sId <= 0 || $tId <= 0 || $sId === $tId) {
                $summary['rejected_count']++;
                $summary['feeders_evaluated'][$fId]['rejected']++;
                $summary['rejected_segments'][] = [
                    'feeder' => $fName,
                    'pair'   => "{$sId} -> {$tId}",
                    'reason' => 'SELF_LOOP_OR_INVALID_ID',
                    'detail' => 'Source dan Target ID harus integer positif berbeda.',
                ];
                continue;
            }

            // 2. Anti-Duplicate Check in Active gis_translines
            $pairKey = min($sId, $tId) . '-' . max($sId, $tId);
            if (isset($existingTranslinesMap[$fId][$pairKey])) {
                $summary['duplicate_count']++;
                $summary['feeders_evaluated'][$fId]['duplicate']++;
                $summary['duplicates'][] = [
                    'feeder'        => $fName,
                    'pair'          => "{$sId} <-> {$tId}",
                    'transline_id'  => $existingTranslinesMap[$fId][$pairKey],
                ];
                continue;
            }

            // 3. Strict JTM Pole Validation (No Protection Devices, Trafo, GTT, Tree ROW)
            $srcAsset = $cand['source_asset'] ?? $this->fetchAsset($sId);
            $tgtAsset = $cand['target_asset'] ?? $this->fetchAsset($tId);

            if (!$srcAsset || !$tgtAsset) {
                $summary['rejected_count']++;
                $summary['feeders_evaluated'][$fId]['rejected']++;
                $summary['rejected_segments'][] = [
                    'feeder' => $fName,
                    'pair'   => "{$sId} -> {$tId}",
                    'reason' => 'ASSET_NOT_FOUND',
                    'detail' => 'Salah satu aset tidak ditemukan di database.',
                ];
                continue;
            }

            $srcPoleCheck = GisTranslineService::isJtmPoleAsset($srcAsset);
            if (!$srcPoleCheck['valid']) {
                $summary['rejected_count']++;
                $summary['feeders_evaluated'][$fId]['rejected']++;
                $summary['rejected_segments'][] = [
                    'feeder' => $fName,
                    'pair'   => "{$sId} -> {$tId}",
                    'reason' => 'SOURCE_NON_JTM_POLE',
                    'detail' => "[{$srcAsset['kode_asset']}] ditolak: " . $srcPoleCheck['reason'],
                ];
                continue;
            }

            $tgtPoleCheck = GisTranslineService::isJtmPoleAsset($tgtAsset);
            if (!$tgtPoleCheck['valid']) {
                $summary['rejected_count']++;
                $summary['feeders_evaluated'][$fId]['rejected']++;
                $summary['rejected_segments'][] = [
                    'feeder' => $fName,
                    'pair'   => "{$sId} -> {$tId}",
                    'reason' => 'TARGET_NON_JTM_POLE',
                    'detail' => "[{$tgtAsset['kode_asset']}] ditolak: " . $tgtPoleCheck['reason'],
                ];
                continue;
            }

            // 4. Same Feeder Verification
            $sFeeder = (int)($srcAsset['penyulang_id'] ?? 0);
            $tFeeder = (int)($tgtAsset['penyulang_id'] ?? 0);
            if ($sFeeder !== $fId || $tFeeder !== $fId) {
                $summary['rejected_count']++;
                $summary['feeders_evaluated'][$fId]['rejected']++;
                $summary['rejected_segments'][] = [
                    'feeder' => $fName,
                    'pair'   => "{$sId} -> {$tId}",
                    'reason' => 'CROSS_FEEDER_ILLEGAL',
                    'detail' => "Aset tiang tidak berada dalam feeder yang sama (Src: #{$sFeeder}, Tgt: #{$tFeeder}, Feeder: #{$fId}).",
                ];
                continue;
            }

            // 5. Distance & Spatial Span Firewalls
            $latA = (float)($srcAsset['latitude'] ?? 0);
            $lonA = (float)($srcAsset['longitude'] ?? 0);
            $latB = (float)($tgtAsset['latitude'] ?? 0);
            $lonB = (float)($tgtAsset['longitude'] ?? 0);

            if (abs($latA) < 0.0001 || abs($lonA) < 0.0001 || abs($latB) < 0.0001 || abs($lonB) < 0.0001) {
                $summary['rejected_count']++;
                $summary['feeders_evaluated'][$fId]['rejected']++;
                $summary['rejected_segments'][] = [
                    'feeder' => $fName,
                    'pair'   => "{$sId} -> {$tId}",
                    'reason' => 'INVALID_COORDINATES',
                    'detail' => 'Koordinat geografis tiang tidak valid.',
                ];
                continue;
            }

            $dist = (float)($cand['distance_meters'] ?? $this->haversineMeters($latA, $lonA, $latB, $lonB));
            if ($dist < 5.0) {
                $summary['rejected_count']++;
                $summary['feeders_evaluated'][$fId]['rejected']++;
                $summary['rejected_segments'][] = [
                    'feeder' => $fName,
                    'pair'   => "{$sId} -> {$tId}",
                    'reason' => 'SHORT_SPAN_FIREWALL',
                    'detail' => "Jarak span ({$dist} m) terlalu dekat (< 5m), kemungkinan duplikasi tiang atau portal.",
                ];
                continue;
            }

            if ($dist > 150.0) {
                $summary['rejected_count']++;
                $summary['feeders_evaluated'][$fId]['rejected']++;
                $summary['rejected_segments'][] = [
                    'feeder' => $fName,
                    'pair'   => "{$sId} -> {$tId}",
                    'reason' => 'SPAN_EXCEEDS_CEILING',
                    'detail' => "Jarak span ({$dist} m) melebihi batas bentang aman PLN (> 150m tanpa tiang sisipan).",
                ];
                if (!isset($summary['feeders_needing_input'][$fId])) {
                    $summary['feeders_needing_input'][$fId] = [
                        'penyulang_id'   => $fId,
                        'nama_penyulang' => $fName,
                        'issue'          => 'Bentang tiang terputus melebihi 150m, memerlukan input tiang sisipan fisik.',
                    ];
                }
                continue;
            }

            // 6. Conductor Assignment (Default AAACS if missing)
            $rawCond = trim((string)($cand['conductor_type'] ?? ''));
            $isDefaultCond = false;
            if (empty($rawCond) || strtolower($rawCond) === 'null') {
                $condType = 'AAACS';
                $condSize = '150 mm²';
                $isDefaultCond = true;
            } else {
                $condType = strtoupper($rawCond);
                $condSize = !empty($cand['conductor_size']) ? trim($cand['conductor_size']) : '150 mm²';
            }

            $geometry = json_encode([
                [$lonA, $latA],
                [$lonB, $latB],
            ]);

            $validRecord = [
                'proposal_id'          => $cand['id'] ?? null,
                'penyulang_id'         => $fId,
                'nama_penyulang'       => $fName,
                'source_asset_id'      => $sId,
                'target_asset_id'      => $tId,
                'source_code'          => $srcAsset['kode_asset'],
                'target_code'          => $tgtAsset['kode_asset'],
                'distance_meters'      => round($dist, 2),
                'conductor_type'       => $condType,
                'conductor_size'       => $condSize,
                'conductor_is_default' => $isDefaultCond,
                'geometry'             => $geometry,
            ];

            $summary['valid_count']++;
            $summary['feeders_evaluated'][$fId]['valid']++;
            $summary['valid_segments'][] = $validRecord;
        }

        $summary['feeders_evaluated'] = array_values($summary['feeders_evaluated']);
        $summary['feeders_needing_input'] = array_values($summary['feeders_needing_input']);

        return $summary;
    }

    /**
     * Transactional Batch Execution with Pre-Mutation Backup & Rollback
     */
    public function executeBatch(?int $penyulangId = null, string $actor = 'ADMIN_BATCH'): array
    {
        $dryRun = $this->dryRun($penyulangId);

        if (empty($dryRun['valid_segments'])) {
            return [
                'status'         => 'SKIPPED',
                'message'        => 'Tidak ada segmen transline valid yang memenuhi syarat untuk disimpan.',
                'summary'        => $dryRun,
                'saved_count'    => 0,
            ];
        }

        // 1. Create Pre-Execution Backup
        $writePath = defined('WRITEPATH') ? WRITEPATH : (__DIR__ . '/../../writable/');
        $backupDir = rtrim($writePath, '/\\') . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0777, true);
        }

        $backupFile = $backupDir . DIRECTORY_SEPARATOR . 'transline_batch_backup_' . date('Ymd_His') . '.json';
        $existingTranslines = $this->db->table('gis_translines')->where('deleted_at IS NULL')->get()->getResultArray();
        $existingProposals = $this->db->tableExists('gis_transline_proposals')
            ? ($this->db->fieldExists('deleted_at', 'gis_transline_proposals') 
                ? $this->db->table('gis_transline_proposals')->where('deleted_at IS NULL')->get()->getResultArray() 
                : $this->db->table('gis_transline_proposals')->get()->getResultArray())
            : [];

        file_put_contents($backupFile, json_encode([
            'backup_timestamp'  => date('Y-m-d H:i:s'),
            'actor'             => $actor,
            'total_translines'  => count($existingTranslines),
            'total_proposals'   => count($existingProposals),
            'gis_translines'    => $existingTranslines,
            'proposals'         => $existingProposals,
        ], JSON_PRETTY_PRINT));

        // 2. Transactional Insertion
        $this->db->transBegin();
        try {
            $now = date('Y-m-d H:i:s');
            $savedCount = 0;
            $confirmedProposals = [];
            $byFeederSaved = [];

            foreach ($dryRun['valid_segments'] as $seg) {
                $fId = $seg['penyulang_id'];
                $sId = $seg['source_asset_id'];
                $tId = $seg['target_asset_id'];
                $transCode = "TL-{$fId}-{$sId}-{$tId}";

                $payload = [
                    'transline_code'     => $transCode,
                    'penyulang_id'       => $fId,
                    'source_asset_id'    => $sId,
                    'target_asset_id'    => $tId,
                    'geometry'           => $seg['geometry'],
                    'conductor_type'     => $seg['conductor_type'],
                    'conductor_size'     => $seg['conductor_size'],
                    'conductor_material' => 'ALUMINUM_ALLOY',
                    'distance_meters'    => $seg['distance_meters'],
                    'status'             => 'ACTIVE',
                    'is_active'          => 1,
                    'created_by'         => $actor,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];

                if ($this->db->fieldExists('geometry_type', 'gis_translines')) {
                    $payload['geometry_type'] = 'LineString';
                }
                if ($this->db->fieldExists('installation_type', 'gis_translines')) {
                    $payload['installation_type'] = 'OVERHEAD';
                }
                if ($this->db->fieldExists('circuit_config', 'gis_translines')) {
                    $payload['circuit_config'] = '3_PHASE';
                }

                $this->db->table('gis_translines')->insert($payload);
                $newId = (int)$this->db->insertID();

                if ($newId > 0 && !empty($seg['proposal_id']) && $this->db->tableExists('gis_transline_proposals')) {
                    $this->db->table('gis_transline_proposals')
                        ->where('id', $seg['proposal_id'])
                        ->update([
                            'status'                 => 'CONFIRMED',
                            'confirmed_transline_id' => $newId,
                            'updated_at'             => $now,
                        ]);
                    $confirmedProposals[] = $seg['proposal_id'];
                }

                $savedCount++;
                if (!isset($byFeederSaved[$fId])) {
                    $byFeederSaved[$fId] = [
                        'penyulang_id'   => $fId,
                        'nama_penyulang' => $seg['nama_penyulang'],
                        'count'          => 0,
                    ];
                }
                $byFeederSaved[$fId]['count']++;
            }

            $this->db->transCommit();

            return [
                'status'              => 'SUCCESS',
                'backup_file'         => basename($backupFile),
                'saved_count'         => $savedCount,
                'confirmed_proposals' => count($confirmedProposals),
                'feeders_saved'       => array_values($byFeederSaved),
                'summary'             => $dryRun,
            ];

        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw new \RuntimeException("Batch Transline Execution gagal dan telah di-rollback: " . $e->getMessage());
        }
    }

    /**
     * Collect candidates from gis_transline_proposals or consecutive pole chains
     */
    protected function collectCandidates(?int $penyulangId = null): array
    {
        $candidates = [];

        // Source A: Pending Proposals from gis_transline_proposals
        if ($this->db->tableExists('gis_transline_proposals')) {
            $cols = $this->db->getFieldNames('gis_transline_proposals');
            $fCol = in_array('feeder_id', $cols, true) ? 'feeder_id' : (in_array('penyulang_id', $cols, true) ? 'penyulang_id' : null);
            $sCol = in_array('start_asset_id', $cols, true) ? 'start_asset_id' : (in_array('source_asset_id', $cols, true) ? 'source_asset_id' : null);
            $tCol = in_array('end_asset_id', $cols, true) ? 'end_asset_id' : (in_array('target_asset_id', $cols, true) ? 'target_asset_id' : null);
            $distCol = in_array('proposed_length_meters', $cols, true) ? 'proposed_length_meters' : (in_array('proposed_distance', $cols, true) ? 'proposed_distance' : null);

            $builder = $this->db->table('gis_transline_proposals p');
            if ($fCol) {
                $builder->select("p.*, py.nama_penyulang")
                        ->join('penyulang py', "py.id = p.{$fCol}", 'left');
                if ($penyulangId !== null && $penyulangId > 0) {
                    $builder->where("p.{$fCol}", $penyulangId);
                }
            } else {
                $builder->select('p.*');
            }

            $builder->where('p.status', 'PENDING_REVIEW');
            if (in_array('deleted_at', $cols, true)) {
                $builder->where('p.deleted_at IS NULL');
            }

            $props = $builder->get()->getResultArray();
            foreach ($props as $pr) {
                $candidates[] = [
                    'id'              => (int)$pr['id'],
                    'penyulang_id'    => (int)($fCol ? $pr[$fCol] : 0),
                    'nama_penyulang'  => $pr['nama_penyulang'] ?? null,
                    'source_asset_id' => (int)($sCol ? $pr[$sCol] : 0),
                    'target_asset_id' => (int)($tCol ? $pr[$tCol] : 0),
                    'distance_meters' => (float)($distCol ? ($pr[$distCol] ?? 0) : 0),
                    'conductor_type'  => $pr['proposed_conductor_type'] ?? null,
                    'conductor_size'  => $pr['proposed_conductor_size'] ?? null,
                ];
            }
        }

        // Source B: Candidates from TranslineNetworkCompletionEngine for active feeders
        $engine = new TranslineNetworkCompletionEngine($this->db);
        $feedersToScan = [];
        if ($penyulangId !== null && $penyulangId > 0) {
            $feedersToScan[] = $penyulangId;
        } else {
            $feederPoles = $this->db->table('assets')
                ->select('penyulang_id, COUNT(*) as cnt')
                ->where('deleted_at IS NULL')
                ->where('penyulang_id IS NOT NULL')
                ->where('penyulang_id >', 0)
                ->groupBy('penyulang_id')
                ->having('cnt >=', 2)
                ->get()->getResultArray();
            foreach ($feederPoles as $fp) {
                $feedersToScan[] = (int)$fp['penyulang_id'];
            }
        }

        $seenCandidateKeys = [];
        foreach ($candidates as $c) {
            $key = min($c['source_asset_id'], $c['target_asset_id']) . '-' . max($c['source_asset_id'], $c['target_asset_id']);
            $seenCandidateKeys[$key] = true;
        }

        foreach ($feedersToScan as $fScanId) {
            try {
                $graph = $engine->buildFeederGraph($fScanId);
                $discovered = $engine->discoverCandidates($graph);

                foreach ($discovered as $disc) {
                    $sId = (int)$disc['source_asset_id'];
                    $tId = (int)$disc['target_asset_id'];
                    $candKey = min($sId, $tId) . '-' . max($sId, $tId);
                    if (isset($seenCandidateKeys[$candKey])) {
                        continue;
                    }
                    $seenCandidateKeys[$candKey] = true;

                    // Only candidates that passed basic gates
                    if (!empty($disc['gate_valid']) || !empty($disc['is_auto_complete'])) {
                        $candidates[] = [
                            'id'              => null,
                            'penyulang_id'    => $fScanId,
                            'nama_penyulang'  => $graph['assets'][$sId]['penyulang_name'] ?? null,
                            'source_asset_id' => $sId,
                            'target_asset_id' => $tId,
                            'distance_meters' => (float)$disc['distance_meters'],
                            'conductor_type'  => $disc['conductor_type'] ?? 'AAACS',
                            'conductor_size'  => $disc['conductor_size'] ?? '150 mm²',
                            'source_asset'    => $graph['assets'][$sId] ?? null,
                            'target_asset'    => $graph['assets'][$tId] ?? null,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Feeder without sufficient topology graph or invalid geometry skipped safely
            }
        }

        return $candidates;
    }

    protected function loadExistingTranslinesMap(?int $penyulangId = null): array
    {
        $map = [];
        if (!$this->db->tableExists('gis_translines')) {
            return $map;
        }

        $builder = $this->db->table('gis_translines')
            ->select('id, penyulang_id, source_asset_id, target_asset_id')
            ->where('deleted_at IS NULL')
            ->where('is_active', 1);

        if ($penyulangId !== null && $penyulangId > 0) {
            $builder->where('penyulang_id', $penyulangId);
        }

        $rows = $builder->get()->getResultArray();
        foreach ($rows as $r) {
            $fId = (int)$r['penyulang_id'];
            $sId = (int)$r['source_asset_id'];
            $tId = (int)$r['target_asset_id'];
            $key = min($sId, $tId) . '-' . max($sId, $tId);
            $map[$fId][$key] = (int)$r['id'];
        }

        return $map;
    }

    protected function fetchAsset(int $id): ?array
    {
        if (!$this->db->tableExists('assets') || $id <= 0) {
            return null;
        }
        return $this->db->table('assets')->where('id', $id)->where('deleted_at IS NULL')->get()->getRowArray();
    }

    /**
     * Haversine distance in meters
     */
    protected function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    /**
     * Validate and persist a single candidate transline segment
     */
    public function validateSingleSegment(
        int $sourceAssetId,
        int $targetAssetId,
        ?string $conductorType = 'AAACS',
        ?string $conductorSize = '150 mm²',
        string $actor = 'ADMIN_UI'
    ): array {
        if ($sourceAssetId <= 0 || $targetAssetId <= 0 || $sourceAssetId === $targetAssetId) {
            return [
                'status'  => 'error',
                'message' => 'Source dan Target ID harus bernilai positif dan tidak boleh sama (menghindari self-loop).',
            ];
        }

        $srcAsset = $this->fetchAsset($sourceAssetId);
        $tgtAsset = $this->fetchAsset($targetAssetId);

        if (!$srcAsset || !$tgtAsset) {
            return [
                'status'  => 'error',
                'message' => 'Salah satu aset tiang tidak ditemukan di database atau telah dihapus.',
            ];
        }

        $srcPoleCheck = GisTranslineService::isJtmPoleAsset($srcAsset);
        if (!$srcPoleCheck['valid']) {
            return [
                'status'  => 'error',
                'message' => "Aset sumber [{$srcAsset['kode_asset']}] bukan tiang JTM valid: " . $srcPoleCheck['reason'],
            ];
        }

        $tgtPoleCheck = GisTranslineService::isJtmPoleAsset($tgtAsset);
        if (!$tgtPoleCheck['valid']) {
            return [
                'status'  => 'error',
                'message' => "Aset tujuan [{$tgtAsset['kode_asset']}] bukan tiang JTM valid: " . $tgtPoleCheck['reason'],
            ];
        }

        $sFeeder = (int)($srcAsset['penyulang_id'] ?? 0);
        $tFeeder = (int)($tgtAsset['penyulang_id'] ?? 0);
        if ($sFeeder <= 0 || $tFeeder <= 0 || $sFeeder !== $tFeeder) {
            return [
                'status'  => 'error',
                'message' => "Tiang tidak berada pada penyulang yang sama (Src Feeder: #{$sFeeder}, Tgt Feeder: #{$tFeeder}).",
            ];
        }

        $latA = (float)($srcAsset['latitude'] ?? 0);
        $lonA = (float)($srcAsset['longitude'] ?? 0);
        $latB = (float)($tgtAsset['latitude'] ?? 0);
        $lonB = (float)($tgtAsset['longitude'] ?? 0);

        if (abs($latA) < 0.0001 || abs($lonA) < 0.0001 || abs($latB) < 0.0001 || abs($lonB) < 0.0001) {
            return [
                'status'  => 'error',
                'message' => 'Koordinat geografis tiang tidak valid.',
            ];
        }

        $dist = $this->haversineMeters($latA, $lonA, $latB, $lonB);
        if ($dist < 5.0) {
            return [
                'status'  => 'error',
                'message' => "Jarak span ({$dist} m) terlalu dekat (< 5m), kemungkinan duplikasi tiang.",
            ];
        }
        if ($dist > 150.0) {
            return [
                'status'  => 'error',
                'message' => "Jarak span ({$dist} m) melebihi batas bentang aman PLN (> 150m tanpa tiang sisipan).",
            ];
        }

        // Anti-duplicate check in gis_translines
        $existing = $this->db->table('gis_translines')
            ->where('penyulang_id', $sFeeder)
            ->where('deleted_at IS NULL')
            ->where('is_active', 1)
            ->groupStart()
                ->where("source_asset_id = {$sourceAssetId} AND target_asset_id = {$targetAssetId}")
                ->orWhere("source_asset_id = {$targetAssetId} AND target_asset_id = {$sourceAssetId}")
            ->groupEnd()
            ->get()->getRowArray();

        if ($existing) {
            return [
                'status'  => 'error',
                'message' => "Segmen transline antara tiang ini sudah ada di database (ID: #{$existing['id']}).",
            ];
        }

        // Conductor assignment: default to AAACS 150 mm² if empty
        $rawCond = trim((string)$conductorType);
        $isDefaultCond = false;
        if (empty($rawCond) || strtolower($rawCond) === 'null') {
            $condType = 'AAACS';
            $condSize = '150 mm²';
            $isDefaultCond = true;
        } else {
            $condType = strtoupper($rawCond);
            $condSize = !empty($conductorSize) ? trim($conductorSize) : '150 mm²';
        }

        $now = date('Y-m-d H:i:s');
        $transCode = "TL-{$sFeeder}-{$sourceAssetId}-{$targetAssetId}";
        $geometry = json_encode([
            [$lonA, $latA],
            [$lonB, $latB],
        ]);

        $payload = [
            'transline_code'     => $transCode,
            'penyulang_id'       => $sFeeder,
            'source_asset_id'    => $sourceAssetId,
            'target_asset_id'    => $targetAssetId,
            'geometry'           => $geometry,
            'conductor_type'     => $condType,
            'conductor_size'     => $condSize,
            'conductor_material' => 'ALUMINUM_ALLOY',
            'distance_meters'    => $dist,
            'status'             => 'ACTIVE',
            'is_active'          => 1,
            'created_by'         => $actor,
            'created_at'         => $now,
            'updated_at'         => $now,
        ];

        if ($this->db->fieldExists('geometry_type', 'gis_translines')) {
            $payload['geometry_type'] = 'LineString';
        }
        if ($this->db->fieldExists('installation_type', 'gis_translines')) {
            $payload['installation_type'] = 'OVERHEAD';
        }
        if ($this->db->fieldExists('circuit_config', 'gis_translines')) {
            $payload['circuit_config'] = '3_PHASE';
        }

        $this->db->table('gis_translines')->insert($payload);
        $translineId = (int)$this->db->insertID();

        return [
            'status'               => 'success',
            'message'              => 'Segmen transline berhasil divalidasi dan disimpan secara resmi.',
            'transline_id'         => $translineId,
            'transline_code'       => $transCode,
            'penyulang_id'         => $sFeeder,
            'distance_meters'      => $dist,
            'conductor_type'       => $condType,
            'conductor_size'       => $condSize,
            'conductor_is_default' => $isDefaultCond,
        ];
    }
}
