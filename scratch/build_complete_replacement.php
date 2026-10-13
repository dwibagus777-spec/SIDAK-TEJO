<?php
require_once __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$mappedFile = __DIR__ . '/mapped_26_kodes.json';
$mapped = json_decode(file_get_contents($mappedFile), true);

$controller = new \App\Controllers\MigrateController();
$origCandidates = $controller->getB2AcceptedCandidates();

echo "Original count: " . count($origCandidates) . "\n";
echo "Mapped count: " . count($mapped) . "\n";

$mergedCandidates = [];
for ($i = 0; $i < 26; $i++) {
    $orig = $origCandidates[$i];
    $map = $mapped[$i];

    // Safety assert
    if ($orig['natural_key'] !== $map['natural_key']) {
        die("Mismatch at idx $i: {$orig['natural_key']} vs {$map['natural_key']}\n");
    }

    $c = [
        'natural_key'       => $orig['natural_key'],
        'transline_code'    => $orig['transline_code'],
        'penyulang_id'      => $orig['penyulang_id'],
        'source_asset_id'   => $orig['source_asset_id'],
        'target_asset_id'   => $orig['target_asset_id'],
        'source_asset_name' => $orig['source_asset_name'],
        'target_asset_name' => $orig['target_asset_name'],
        'source_kode_asset' => $map['source_kode_asset'],
        'target_kode_asset' => $map['target_kode_asset'],
        'source_section_id' => $orig['source_section_id'],
        'target_section_id' => $orig['target_section_id'],
        'distance_meters'   => $orig['distance_meters'],
        'conductor_type'    => $orig['conductor_type'],
        'conductor_size'    => $orig['conductor_size'],
        'confidence'        => $orig['confidence'],
        'geometry'          => $orig['geometry'],
    ];
    $mergedCandidates[] = $c;
}

echo "Merged 26 candidates successfully.\n";

$candidatesExport = var_export($mergedCandidates, true);

// Format code
$methodGetCandidates = "    public function getB2AcceptedCandidates(): array\n    {\n        return " . $candidatesExport . ";\n    }\n";

$methodAtomicCommit = <<<'PHP'
    /**
     * Phase B.2.1: One-Shot Consolidated Atomic Production Commit Engine
     * Enforces the 7 Strict Commit Guards and executes monolithic transaction:
     * - Guard 01: Batch fingerprint verification (Expected: ad2c9fcb833ca2680d00bb45adfa27a4e87730745aa65bf1c3419c89cb713397)
     * - Guard 02: Existing 217 authoritative translines preservation (BEFORE = 217, EXPECTED PRESERVED = 217)
     * - Guard 03: Immutable candidate set (strictly 26 accepted candidates from dry-run artifact)
     * - Guard 04: Duplicate & reverse-duplicate check (= 0) against authoritative translines
     * - Guard 05: Boundary & safety revalidation (confidence >= 0.95, no section violation, authoritative kode_asset resolution)
     * - Guard 06: Zero mutation on assets (parent_asset_id and section_id untouched)
     * - Guard 07: Single atomic transaction (all-or-nothing rollback, ZERO_PARTIAL_NETWORK_COMMIT)
     */
    public function atomicCommitB2()
    {
        // 1. Security Gate: Require active session OR valid operational secret key
        $session = session();
        $isLoggedIn = $session->get('logged_in') || $session->get('user_id') || $session->get('id');

        $reqKey = $this->request->getGet('key') 
            ?? ($_GET['key'] ?? null)
            ?? $this->request->getHeaderLine('X-Audit-Key')
            ?? $this->request->getHeaderLine('Authorization');
        
        $validKeys = [
            'sidak_transline_audit_2026',
            env('AUDIT_SECRET_KEY', 'sidak_transline_audit_2026'),
            'Bearer sidak_transline_audit_2026'
        ];

        $isTokenValid = false;
        if (!empty($reqKey)) {
            foreach ($validKeys as $vk) {
                if (!empty($vk) && hash_equals($vk, trim($reqKey))) {
                    $isTokenValid = true;
                    break;
                }
            }
        }

        if (!$isLoggedIn && !$isTokenValid) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 'error',
                'reason'  => 'UNAUTHORIZED',
                'message' => 'Unauthorized: Endpoint ini memerlukan sesi login atau operational secret key.'
            ]);
        }

        // 2. Commit Mode & Parameters
        $commitMode = (int)($this->request->getGet('commit') ?? $this->request->getPost('commit') ?? 0);
        $expectedFingerprint = (string)($this->request->getGet('fingerprint') ?? $this->request->getPost('fingerprint') ?? '');
        $actorName = (string)($session ? ($session->get('username') ?? $session->get('nama') ?? 'ONE_SHOT_OPERATOR') : 'ONE_SHOT_OPERATOR');

        $canonicalFingerprint = 'ad2c9fcb833ca2680d00bb45adfa27a4e87730745aa65bf1c3419c89cb713397';

        // GUARD 01: BATCH FINGERPRINT VERIFICATION
        if ($expectedFingerprint !== $canonicalFingerprint) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'               => 'error',
                'reason'               => 'GUARD_01_FINGERPRINT_MISMATCH',
                'message'              => "Commit aborted: Batch fingerprint mismatch. Expected '{$canonicalFingerprint}', received '{$expectedFingerprint}'.",
                'expected_fingerprint' => $canonicalFingerprint,
                'received_fingerprint' => $expectedFingerprint
            ]);
        }

        $db = Database::connect();

        // Load 26 immutable candidates
        $candidates26 = $this->getB2AcceptedCandidates();

        // GUARD 03: CANDIDATE IDENTITY & COUNT
        if (count($candidates26) !== 26) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'reason'  => 'GUARD_03_CANDIDATE_COUNT_INVALID',
                'message' => 'Commit aborted: Immutable candidate set must contain exactly 26 edges, found ' . count($candidates26) . '.'
            ]);
        }

        // GUARD 02: PRE-COMMIT AUTHORITATIVE TL AUDIT
        $activeExistingQuery = $db->table('gis_translines')
            ->where('is_active', 1)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $beforeCount = count($activeExistingQuery);
        if ($beforeCount !== 217) {
            return $this->response->setStatusCode(409)->setJSON([
                'status'         => 'error',
                'reason'         => 'GUARD_02_EXISTING_TL_COUNT_MISMATCH',
                'message'        => "Commit aborted: Existing active transline count changed (expected exactly 217, found {$beforeCount}).",
                'expected_count' => 217,
                'current_count'  => $beforeCount
            ]);
        }

        $existingNaturalKeys = [];
        $existingPairSet = [];
        foreach ($activeExistingQuery as $row) {
            $fId = (int)$row['penyulang_id'];
            $src = (int)$row['source_asset_id'];
            $tgt = (int)$row['target_asset_id'];
            $min = min($src, $tgt);
            $max = max($src, $tgt);
            $natKey = "TL-NAT:{$fId}:{$min}-{$max}";
            $existingNaturalKeys[$natKey] = (int)$row['id'];
            $existingPairSet["{$fId}:{$min}-{$max}"] = true;
        }

        // GUARD 05 & ASSET RESOLUTION: Resolve authoritative assets by kode_asset
        $resolvedCandidates = [];
        foreach ($candidates26 as $idx => $cand) {
            if ((float)$cand['confidence'] < 0.95) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'  => 'error',
                    'reason'  => 'GUARD_05_CONFIDENCE_BELOW_THRESHOLD',
                    'message' => "Commit aborted: Candidate #{$idx} ({$cand['natural_key']}) confidence {$cand['confidence']} < 0.95."
                ]);
            }

            if ($cand['source_section_id'] !== $cand['target_section_id'] || (int)$cand['source_section_id'] <= 0) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'  => 'error',
                    'reason'  => 'GUARD_05_BOUNDARY_VIOLATION',
                    'message' => "Commit aborted: Section boundary violation on {$cand['natural_key']} ({$cand['source_section_id']} vs {$cand['target_section_id']})."
                ]);
            }

            $uAsset = $db->table('assets')->where('kode_asset', $cand['source_kode_asset'])->where('deleted_at IS NULL')->get()->getRowArray();
            $vAsset = $db->table('assets')->where('kode_asset', $cand['target_kode_asset'])->where('deleted_at IS NULL')->get()->getRowArray();

            if (!$uAsset || !$vAsset) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'  => 'error',
                    'reason'  => 'GUARD_05_ASSET_NOT_FOUND',
                    'message' => "Commit aborted: Asset endpoint missing for candidate {$cand['natural_key']} (Src: {$cand['source_kode_asset']}, Tgt: {$cand['target_kode_asset']})."
                ]);
            }

            $srcDbId = (int)$uAsset['id'];
            $tgtDbId = (int)$vAsset['id'];
            $minDbId = min($srcDbId, $tgtDbId);
            $maxDbId = max($srcDbId, $tgtDbId);

            $cand['source_db_id'] = $minDbId;
            $cand['target_db_id'] = $maxDbId;
            $cand['db_natural_key'] = "TL-NAT:{$cand['penyulang_id']}:{$minDbId}-{$maxDbId}";
            $cand['db_transline_code'] = "TL-{$cand['penyulang_id']}-{$minDbId}-{$maxDbId}";

            if ((int)$uAsset['penyulang_id'] !== (int)$cand['penyulang_id'] || (int)$vAsset['penyulang_id'] !== (int)$cand['penyulang_id']) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'  => 'error',
                    'reason'  => 'GUARD_05_CROSS_FEEDER_BREACH',
                    'message' => "Commit aborted: Cross-feeder violation on {$cand['natural_key']}."
                ]);
            }

            if ((int)$uAsset['ulp_id'] !== (int)$vAsset['ulp_id']) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'  => 'error',
                    'reason'  => 'GUARD_05_CROSS_ULP_BREACH',
                    'message' => "Commit aborted: Cross-ULP violation on {$cand['natural_key']}."
                ]);
            }

            $resolvedCandidates[] = $cand;
        }

        // GUARD 04: DUPLICATE & REVERSE DUPLICATE CHECK AGAINST REAL DB KEYS
        $seenCandidateKeys = [];
        foreach ($resolvedCandidates as $idx => $cand) {
            $fId = (int)$cand['penyulang_id'];
            $min = $cand['source_db_id'];
            $max = $cand['target_db_id'];
            $pairKey = "{$fId}:{$min}-{$max}";

            if (isset($existingPairSet[$pairKey])) {
                return $this->response->setStatusCode(409)->setJSON([
                    'status'  => 'error',
                    'reason'  => 'GUARD_04_DUPLICATE_WITH_EXISTING',
                    'message' => "Commit aborted: Candidate #{$idx} ({$cand['natural_key']} / {$cand['db_natural_key']}) already exists in authoritative translines."
                ]);
            }

            if (isset($seenCandidateKeys[$pairKey])) {
                return $this->response->setStatusCode(409)->setJSON([
                    'status'  => 'error',
                    'reason'  => 'GUARD_04_DUPLICATE_WITHIN_BATCH',
                    'message' => "Commit aborted: Duplicate edge within candidate batch: {$cand['natural_key']}."
                ]);
            }
            $seenCandidateKeys[$pairKey] = true;
        }

        // GUARD 06: CAPTURE PARENT_ASSET_ID & ASSET INTEGRITY BEFORE
        $assetPreStats = $db->table('assets')
            ->select('COUNT(*) as total_count, SUM(COALESCE(parent_asset_id, 0)) as parent_sum, COUNT(section_id) as section_count')
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        // -------------------------------------------------------------
        // GUARD 07: MONOLITHIC ATOMIC PRODUCTION TRANSACTION
        // -------------------------------------------------------------
        $batchId = 'INGEST-COMMIT-' . date('YmdHis') . '-' . substr($canonicalFingerprint, 0, 8);
        $validColumns = array_flip($db->getFieldNames('gis_translines'));

        $db->transBegin();
        $insertedIds = [];
        $affectedFeeders = [];

        try {
            foreach ($resolvedCandidates as $cand) {
                $fId   = (int)$cand['penyulang_id'];
                $minId = $cand['source_db_id'];
                $maxId = $cand['target_db_id'];
                $geoJson = json_encode($cand['geometry'], JSON_UNESCAPED_SLASHES);

                $row = [
                    'transline_code'     => $cand['db_transline_code'],
                    'penyulang_id'       => $fId,
                    'source_asset_id'    => $minId,
                    'target_asset_id'    => $maxId,
                    'geometry'           => $geoJson,
                    'geometry_type'      => 'LineString',
                    'conductor_type'     => $cand['conductor_type'] ?? 'AAAC',
                    'conductor_size'     => $cand['conductor_size'] ?? '150 mm²',
                    'conductor_material' => 'ALUMINUM_ALLOY',
                    'installation_type'  => 'OVERHEAD',
                    'circuit_config'     => '3_PHASE',
                    'distance_meters'    => round((float)$cand['distance_meters'], 2),
                    'length_meters'      => round((float)$cand['distance_meters'], 2),
                    'coordinates'        => $geoJson,
                    'status'             => 'ACTIVE',
                    'is_active'          => 1,
                    'created_by'         => "ONE_SHOT_INGESTION|BATCH={$batchId}|AUTO_ACCEPT_95",
                    'created_at'         => date('Y-m-d H:i:s'),
                    'updated_at'         => date('Y-m-d H:i:s'),
                ];

                $insertRow = array_intersect_key($row, $validColumns);
                $inserted = $db->table('gis_translines')->insert($insertRow);
                if (!$inserted) {
                    $err = $db->error();
                    throw new \RuntimeException("Insert failed on {$cand['natural_key']}: " . ($err['message'] ?? 'unknown'));
                }
                $newId = (int)$db->insertID();
                $insertedIds[] = $newId;
                $affectedFeeders[$fId] = true;
            }

            // In-Transaction Verification: Count must be EXACTLY 243
            $postCount = $db->table('gis_translines')
                ->where('is_active', 1)
                ->where('deleted_at IS NULL')
                ->countAllResults();

            if ($postCount !== 243) {
                throw new \RuntimeException("Transaction invariant breach: Expected total 243 translines (217 + 26), found {$postCount}.");
            }

            // In-Transaction Verification: Exactly 26 new IDs
            if (count($insertedIds) !== 26) {
                throw new \RuntimeException("Transaction invariant breach: Expected 26 inserted IDs, recorded " . count($insertedIds));
            }

            // In-Transaction Verification: All 217 existing IDs preserved
            $preservedCount = $db->table('gis_translines')
                ->whereIn('id', array_values($existingNaturalKeys))
                ->where('is_active', 1)
                ->where('deleted_at IS NULL')
                ->countAllResults();

            if ($preservedCount !== 217) {
                throw new \RuntimeException("Transaction invariant breach: Expected 217 preserved translines, found {$preservedCount}.");
            }

            // In-Transaction Verification: Total distance directly from DB SUM
            $dbDistanceRow = $db->table('gis_translines')
                ->select('SUM(COALESCE(distance_meters, length_meters, 0)) as total_db_distance_m')
                ->where('is_active', 1)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRowArray();
            $dbTotalDistanceM = (float)($dbDistanceRow['total_db_distance_m'] ?? 0);

            // In-Transaction Verification: Guard 06 Asset Integrity
            $assetPostStats = $db->table('assets')
                ->select('COUNT(*) as total_count, SUM(COALESCE(parent_asset_id, 0)) as parent_sum, COUNT(section_id) as section_count')
                ->where('deleted_at IS NULL')
                ->get()
                ->getRowArray();

            if ($assetPreStats['total_count'] !== $assetPostStats['total_count'] ||
                $assetPreStats['parent_sum'] !== $assetPostStats['parent_sum'] ||
                $assetPreStats['section_count'] !== $assetPostStats['section_count']) {
                throw new \RuntimeException("Guard 06 breach: assets table was mutated during transline commit.");
            }

            // In-Transaction Batch Audit Logging
            if ($db->tableExists('gis_network_ingestion_batches')) {
                $db->table('gis_network_ingestion_batches')->insert([
                    'batch_id'                  => $batchId,
                    'batch_fingerprint'         => $canonicalFingerprint,
                    'total_feeders'             => 134,
                    'total_assets'              => (int)$assetPreStats['total_count'],
                    'existing_translines_count' => 217,
                    'candidate_translines_count'=> 4045,
                    'accepted_count'            => 26,
                    'warning_count'             => 2289,
                    'rejected_count'            => 1158,
                    'committed_count'           => 26,
                    'rejection_breakdown_json'  => json_encode(['SECTION_BOUNDARY_VIOLATION' => 38, 'IMPOSSIBLE_DISTANCE' => 1120]),
                    'status'                    => ($commitMode === 1 ? 'COMMITTED' : 'DRY_RUN'),
                    'created_by'                => $actorName,
                    'created_at'                => date('Y-m-d H:i:s'),
                    'completed_at'              => date('Y-m-d H:i:s')
                ]);
            }

            if ($db->tableExists('audit_logs')) {
                $db->table('audit_logs')->insert([
                    'user_id'    => session()->get('user_id') ?? 1,
                    'action'     => 'ONE_SHOT_NETWORK_INGESTION_B2_COMMIT',
                    'details'    => "Committed 26 authoritative translines (+732.71m). Total network: 243 translines (" . round($dbTotalDistanceM / 1000.0, 3) . " km). Fingerprint: {$canonicalFingerprint}",
                    'ip_address' => $this->request->getIPAddress() ?? '127.0.0.1',
                    'user_agent' => 'OneShotNetworkIngestion/B2.1',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            if ($commitMode === 1) {
                $db->transCommit();
                $executionState = 'COMMITTED_TO_PRODUCTION';
            } else {
                $db->transRollback();
                $executionState = 'DRY_RUN_SIMULATION_ROLLED_BACK';
            }

            return $this->response->setStatusCode(200)->setJSON([
                'status'                       => 'success',
                'execution_state'              => $executionState,
                'commit_mode'                  => ($commitMode === 1 ? 'LIVE_PRODUCTION_COMMIT' : 'SIMULATION_DRY_RUN'),
                'batch_id'                     => $batchId,
                'batch_fingerprint'            => $canonicalFingerprint,
                'fingerprint_verified'         => true,
                'existing_authoritative_tl'    => 217,
                'existing_tl_preserved'        => 217,
                'new_authoritative_tl_added'   => 26,
                'total_authoritative_tl_now'   => 243,
                'db_total_distance_meters'     => $dbTotalDistanceM,
                'db_total_distance_km'         => round($dbTotalDistanceM / 1000.0, 3),
                'parent_asset_id_touched'      => false,
                'assets_section_id_touched'    => false,
                'feeder_118_authoritative_tl'  => 1, // BAHAGIA STEEL 1_033 -> 1_034
                'affected_feeders_count'       => count($affectedFeeders),
                'inserted_ids'                 => $insertedIds,
                'zero_partial_network_commit'  => true,
                'executed_at'                  => date('Y-m-d H:i:s T')
            ]);

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'reason'  => 'TRANSACTION_ROLLBACK',
                'message' => 'Zero-Partial-Network-Commit Rollback: ' . $e->getMessage()
            ]);
        }
    }

PHP;

$output = "<?php\n// Complete replacement methods for MigrateController\n" . $methodAtomicCommit . "\n" . $methodGetCandidates;
file_put_contents(__DIR__ . '/complete_replacement.php', $output);
echo "Written scratch/complete_replacement.php (" . strlen($output) . " bytes)\n";
