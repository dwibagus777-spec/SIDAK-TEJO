<?php

/**
 * SIDAK TEJO — TL-MF-02 Multi-Feeder Completion Orchestrator
 * Comprehensive Dry-Run Simulation Runner across all 134 Feeders
 *
 * Invariants Enforced:
 * 1. STRICT DRY-RUN: 0 INSERT, 0 UPDATE, 0 DELETE, 0 DDL on production database.
 * 2. Pre- and Post-Run Cryptographic Fingerprints compared.
 * 3. Short-span firewall: <5.0m strictly blocked, 5-10m semantic review.
 * 4. Queue categorization: 101 NO_ASSET skipped, 1 NEAR_COMPLETE skipped, 32 READY_FOR_AI processed.
 */

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

function apiCall(string $url): array {
    global $cookieFile, $resolveOpt;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-DRY-RUN/1.0',
        'X-Requested-With: XMLHttpRequest'
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $code,
        'body' => $res,
        'json' => json_decode($res, true),
        'error' => $err,
    ];
}

echo "======================================================================\n";
echo "⚡ SIDAK TEJO — TL-MF-02 MULTI-FEEDER COMPLETION DRY-RUN SIMULATION\n";
echo "Mode: DRY-RUN (Strict Read-Only | 0 Database Writes)\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "======================================================================\n\n";

$runId = 'RUN-MF02-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

// STEP 1: CAPTURE PRE-EXECUTION FINGERPRINT
echo "=== STEP 1: CAPTURING PRE-EXECUTION DATABASE FINGERPRINT ===\n";
$preAudit = apiCall($baseUrl . '/gis/api-global-topology-audit');
if ($preAudit['code'] !== 200 || !isset($preAudit['json']['fingerprints']['post'])) {
    echo "FATAL: Could not capture pre-execution fingerprint! HTTP {$preAudit['code']}\n";
    exit(1);
}

$preFingerprints = $preAudit['json']['fingerprints']['post'];
echo "Pre-Run Table Counts & Signatures:\n";
foreach ($preFingerprints as $tbl => $info) {
    printf("  %-25s | Rows: %-5d | SHA: %s\n", $tbl, $info['count'], substr($info['sha256'], 0, 16) . '...');
}

// STEP 2: LOAD GLOBAL FEEDER INVENTORY & BUILD PRIORITY QUEUE
echo "\n=== STEP 2: BUILDING GLOBAL FEEDER PRIORITY QUEUE ===\n";
$feederMatrix = $preAudit['json']['feeder_matrix'] ?? [];

$noAssetQueue     = [];
$nearCompleteQueue = [];
$readyQueue       = [];

foreach ($feederMatrix as $f) {
    $fId = (int)$f['penyulang_id'];
    $totalAssets = (int)$f['total_jtm_assets'];
    $activeTls   = (int)$f['existing_translines'];
    $connected   = (int)$f['connected_assets'];

    $connPct = $totalAssets > 0 ? round(($connected / $totalAssets) * 100, 1) : 0.0;

    $meta = [
        'penyulang_id'        => $fId,
        'kode_penyulang'      => $f['kode_penyulang'],
        'nama_penyulang'      => $f['nama_penyulang'],
        'ulp_id'              => (int)$f['ulp_id'],
        'nama_ulp'            => $f['nama_ulp'],
        'total_jtm_assets'    => $totalAssets,
        'valid_gps_assets'    => (int)$f['valid_gps_assets'],
        'existing_translines' => $activeTls,
        'connected_assets'    => $connected,
        'isolated_assets'     => (int)$f['isolated_assets'],
        'connectivity_pct'    => $connPct,
    ];

    if ($totalAssets === 0) {
        $meta['classification'] = 'NO_ASSET';
        $noAssetQueue[] = $meta;
    } elseif ($connPct >= 99.0) {
        $meta['classification'] = 'NEAR_COMPLETE';
        $nearCompleteQueue[] = $meta;
    } else {
        $meta['classification'] = 'READY_FOR_AI';
        $readyQueue[] = $meta;
    }
}

// Rank READY_FOR_AI queue: highest assets first, then lowest connectivity
usort($readyQueue, function ($a, $b) {
    if ($a['total_jtm_assets'] !== $b['total_jtm_assets']) {
        return $b['total_jtm_assets'] <=> $a['total_jtm_assets'];
    }
    return $a['connectivity_pct'] <=> $b['connectivity_pct'];
});

echo "Global Feeder Queue Summary:\n";
echo "  - Total Feeders in Registry   : " . count($feederMatrix) . "\n";
echo "  - Feeders with No Assets      : " . count($noAssetQueue) . " (SKIPPED)\n";
echo "  - Near-Complete Feeders       : " . count($nearCompleteQueue) . " (SKIPPED)\n";
echo "  - Ready for AI Priority Queue : " . count($readyQueue) . " feeders\n\n";

// STEP 3: EXECUTE DRY-RUN SIMULATION ACROSS PRIORITY QUEUE
echo "=== STEP 3: EXECUTING DRY-RUN SIMULATION ON ALL 32 PRIORITY FEEDERS ===\n";
printf("%-3s | %-4s | %-22s | %-15s | %-6s | %-4s | %-5s | %-6s | %-5s | %-5s | %s\n",
    "#", "ID", "Nama Penyulang", "ULP", "Assets", "TLs", "Isol", "Cand", "Proj", "Hold", "Status");
echo str_repeat('-', 110) . "\n";

$feedersReport = [];
$totalProjectedTranslines = 0;
$totalBlockedShortSpansGlobal = 0;
$totalHeldSemanticSpansGlobal = 0;

foreach ($readyQueue as $idx => $fMeta) {
    $qNum = $idx + 1;
    $fId = $fMeta['penyulang_id'];
    $fName = $fMeta['nama_penyulang'];
    $ulpName = $fMeta['nama_ulp'];

    $previewRes = apiCall($baseUrl . "/gis/api-transline-tl04-preview?penyulang_id={$fId}");

    $fReport = [
        'queue_rank'          => $qNum,
        'penyulang_id'        => $fId,
        'kode_penyulang'      => $fMeta['kode_penyulang'],
        'nama_penyulang'      => $fName,
        'ulp_id'              => $fMeta['ulp_id'],
        'nama_ulp'            => $ulpName,
        'total_jtm_assets'    => $fMeta['total_jtm_assets'],
        'existing_translines' => $fMeta['existing_translines'],
        'initial_isolated'    => $fMeta['isolated_assets'],
        'status'              => 'UNKNOWN',
        'candidates_analyzed' => 0,
        'defensible_count'    => 0,
        'projected_batch'     => [],
        'projected_count'     => 0,
        'blocked_short_spans' => 0,
        'held_semantic_spans' => 0,
    ];

    if ($previewRes['code'] === 200 && isset($previewRes['json']['summary'])) {
        $pJson = $previewRes['json'];
        $rawBatch = $pJson['defensible_batch_preview'] ?? [];
        $sum = $pJson['summary'] ?? [];

        $fReport['candidates_analyzed'] = (int)($sum['total_candidates_analyzed'] ?? 0);
        $fReport['defensible_count']    = (int)($sum['defensible_count'] ?? 0);

        // Apply Multi-Tier Span Firewall
        $eligible = [];
        $blockedShort = 0;
        $heldSemantic = 0;

        foreach ($rawBatch as $cand) {
            $dist = (float)$cand['distance_meters'];
            if ($dist < 5.0) {
                $blockedShort++;
                continue; // Blocked < 5m
            }
            if ($dist < 10.0) {
                // Check sequential evidence
                $seqDelta = $cand['score_breakdown']['sequence_delta'] ?? 999;
                $isMainline = in_array($cand['candidate_type'] ?? '', ['ISOLATED_CHAIN_SEGMENT', 'MAINLINE']);
                if ($seqDelta > 2 || !$isMainline) {
                    $heldSemantic++;
                    continue; // Held for semantic review
                }
            }
            if ($dist <= 85.0) {
                $eligible[] = $cand;
            }
        }

        // Cap batch at 10 edges
        $projectedBatch = array_slice($eligible, 0, 10);
        $projCount = count($projectedBatch);

        $fReport['projected_batch']     = $projectedBatch;
        $fReport['projected_count']     = $projCount;
        $fReport['blocked_short_spans'] = $blockedShort;
        $fReport['held_semantic_spans'] = $heldSemantic;

        // Honest termination status
        if ($projCount === 0 && $fReport['defensible_count'] === 0) {
            $fReport['status'] = 'NATURAL_STABILIZED';
        } elseif ($fReport['defensible_count'] > $projCount) {
            $fReport['status'] = 'MAX_BATCH_LIMIT_REACHED'; // 1 batch evaluated, more candidates remain
        } else {
            $fReport['status'] = 'NATURAL_STABILIZED';
        }

        $totalProjectedTranslines += $projCount;
        $totalBlockedShortSpansGlobal += $blockedShort;
        $totalHeldSemanticSpansGlobal += $heldSemantic;
    } else {
        $fReport['status'] = 'ANOMALY_HELD';
        $fReport['quarantine_reason'] = "Preview endpoint returned HTTP {$previewRes['code']}";
    }

    $feedersReport[] = $fReport;

    printf("%-3d | %-4d | %-22s | %-15s | %-6d | %-4d | %-5d | %-6d | %-5d | %-5d | %s\n",
        $qNum,
        $fId,
        substr($fName, 0, 22),
        substr($ulpName, 0, 15),
        $fReport['total_jtm_assets'],
        $fReport['existing_translines'],
        $fReport['initial_isolated'],
        $fReport['defensible_count'],
        $fReport['projected_count'],
        $fReport['held_semantic_spans'] + $fReport['blocked_short_spans'],
        $fReport['status']
    );

    usleep(50000); // 50ms politeness pause
}

// STEP 4: CAPTURE POST-EXECUTION FINGERPRINT & PROVE ZERO WRITES
echo "\n=== STEP 4: CAPTURING POST-EXECUTION DATABASE FINGERPRINT ===\n";
$postAudit = apiCall($baseUrl . '/gis/api-global-topology-audit');
if ($postAudit['code'] !== 200 || !isset($postAudit['json']['fingerprints']['post'])) {
    echo "FATAL: Could not capture post-execution fingerprint! HTTP {$postAudit['code']}\n";
    exit(1);
}

$postFingerprints = $postAudit['json']['fingerprints']['post'];

$zeroWritePass = true;
echo "Post-Run Fingerprint Comparison & Invariant Audit:\n";
foreach ($preFingerprints as $tbl => $pre) {
    $post = $postFingerprints[$tbl] ?? ['count' => -1, 'sha256' => ''];
    $countDelta = $post['count'] - $pre['count'];
    $shaMatch = ($post['sha256'] === $pre['sha256']);

    if ($countDelta !== 0 || !$shaMatch) {
        $zeroWritePass = false;
    }

    printf("  %-25s | Count: %-5d -> %-5d (Δ=%-2d) | SHA Match: %s\n",
        $tbl, $pre['count'], $post['count'], $countDelta, $shaMatch ? 'IDENTICAL' : 'MUTATED');
}

echo "\nZERO-WRITE FIREWALL RESULT: " . ($zeroWritePass ? "✅ PASS (ABSOLUTE 0 WRITES)" : "❌ FAILED") . "\n";
echo "Total Feeders Simulated        : " . count($feedersReport) . "\n";
echo "Total Projected Translines     : " . $totalProjectedTranslines . "\n";
echo "Total Blocked Short-Spans (<5m): " . $totalBlockedShortSpansGlobal . "\n";
echo "Total Held Semantic Spans      : " . $totalHeldSemanticSpansGlobal . "\n";

// Save full JSON audit output
$fullResult = [
    'status'                       => 'SUCCESS',
    'run_id'                       => $runId,
    'mode'                         => 'dry-run',
    'timestamp'                    => date('c'),
    'zero_write_firewall_pass'     => $zeroWritePass,
    'global_queue_summary'         => [
        'total_feeders'       => count($feederMatrix),
        'no_asset_count'      => count($noAssetQueue),
        'near_complete_count' => count($nearCompleteQueue),
        'ready_for_ai_count'  => count($readyQueue),
    ],
    'orchestration_totals'         => [
        'feeders_processed'             => count($feedersReport),
        'total_projected_translines'    => $totalProjectedTranslines,
        'total_blocked_short_spans'     => $totalBlockedShortSpansGlobal,
        'total_held_semantic_spans'     => $totalHeldSemanticSpansGlobal,
    ],
    'pre_fingerprints'             => $preFingerprints,
    'post_fingerprints'            => $postFingerprints,
    'feeders_report'               => $feedersReport,
];

file_put_contents(__DIR__ . '/tl_mf02_dry_run_simulation.json', json_encode($fullResult, JSON_PRETTY_PRINT));
echo "Saved JSON simulation output to: scratch/tl_mf02_dry_run_simulation.json\n";
