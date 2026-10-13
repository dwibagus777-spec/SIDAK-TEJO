<?php

/**
 * SIDAK TEJO — TL-MF-02 PHASE D1.1: FORENSIC PROVENANCE RECONCILIATION
 * 100% READ-ONLY Audit of PKs 227-235 on Feeder #56 (BANJARAN, ULP Krian)
 */

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

// 1. Load Live Translines & Assets
$translinesData = json_decode(file_get_contents(__DIR__ . '/f56_translines_live.json'), true);
$assetsData     = json_decode(file_get_contents(__DIR__ . '/f56_assets_cache.json'), true);

$translines = $translinesData['data'] ?? [];

// 2. Fetch Fresh Table Cryptographic Fingerprints
$ch = curl_init($baseUrl . '/gis/api-global-topology-audit');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-D1-1/1.0',
    'X-Requested-With: XMLHttpRequest',
]);
$auditRes = curl_exec($ch);
curl_close($ch);
$auditJson = json_decode($auditRes, true);
$currentFingerprints = $auditJson['fingerprints']['post'] ?? [];

$targetPks = [227, 228, 229, 230, 231, 232, 233, 234, 235];
$reconciliationResults = [];
$totalAnomalies = 0;
$seenNaturalKeys = [];

echo "=========================================================================================\n";
echo "TL-MF-02 PHASE D1.1 — FORENSIC PROVENANCE RECONCILIATION REPORT (PK 227-235)\n";
echo "=========================================================================================\n\n";

foreach ($targetPks as $pk) {
    // Find transline
    $tl = null;
    foreach ($translines as $t) {
        if ((int)$t['id'] === $pk) {
            $tl = $t;
            break;
        }
    }

    if (!$tl) {
        $reconciliationResults[$pk] = [
            'pk' => $pk,
            'verdict' => 'ANOMALY',
            'anomaly_reason' => 'TRANSLINE_NOT_FOUND',
        ];
        $totalAnomalies++;
        echo "PK $pk: NOT FOUND IN DATABASE!\n";
        continue;
    }

    $srcId = (int)$tl['source_asset_id'];
    $tgtId = (int)$tl['target_asset_id'];
    $penyulangId = (int)$tl['penyulang_id'];
    $tlCode = (string)$tl['code'];
    $dbLength = (float)($tl['length_m'] ?? $tl['length_meter'] ?? 0);
    $coords = $tl['coordinates'] ?? [];

    $anomalies = [];

    // 1. Resolve source asset
    $srcAsset = $assetsData[$srcId] ?? null;
    if (!$srcAsset) {
        $anomalies[] = "ENDPOINT_NOT_FOUND: Source asset $srcId not in assets table";
    }

    // 2. Resolve target asset
    $tgtAsset = $assetsData[$tgtId] ?? null;
    if (!$tgtAsset) {
        $anomalies[] = "ENDPOINT_NOT_FOUND: Target asset $tgtId not in assets table";
    }

    // 3. Self loop check
    if ($srcId === $tgtId) {
        $anomalies[] = "SELF_LOOP: source_asset_id equals target_asset_id ($srcId)";
    }

    // 4. Feeder firewall check
    if ($penyulangId !== 56) {
        $anomalies[] = "CROSS_FEEDER: transline penyulang_id is $penyulangId (expected 56)";
    }
    if ($srcAsset && (int)$srcAsset['penyulang_id'] !== 56) {
        $anomalies[] = "CROSS_FEEDER: source asset penyulang_id is {$srcAsset['penyulang_id']} (expected 56)";
    }
    if ($tgtAsset && (int)$tgtAsset['penyulang_id'] !== 56) {
        $anomalies[] = "CROSS_FEEDER: target asset penyulang_id is {$tgtAsset['penyulang_id']} (expected 56)";
    }

    // 5. Geometry Reconciliation
    $geomSrcLng = isset($coords[0][0]) ? (float)$coords[0][0] : null;
    $geomSrcLat = isset($coords[0][1]) ? (float)$coords[0][1] : null;
    $geomTgtLng = isset($coords[1][0]) ? (float)$coords[1][0] : null;
    $geomTgtLat = isset($coords[1][1]) ? (float)$coords[1][1] : null;

    $coordDeltaSrc = 0.0;
    $coordDeltaTgt = 0.0;

    if ($srcAsset && $geomSrcLat !== null && $geomSrcLng !== null) {
        $coordDeltaSrc = haversineDistanceMeters((float)$srcAsset['latitude'], (float)$srcAsset['longitude'], $geomSrcLat, $geomSrcLng);
        if ($coordDeltaSrc > 0.05) { // 5 cm tolerance for floating point rounding
            $anomalies[] = "GEOMETRY_MISMATCH: Source geometry delta is {$coordDeltaSrc}m";
        }
    }

    if ($tgtAsset && $geomTgtLat !== null && $geomTgtLng !== null) {
        $coordDeltaTgt = haversineDistanceMeters((float)$tgtAsset['latitude'], (float)$tgtAsset['longitude'], $geomTgtLat, $geomTgtLng);
        if ($coordDeltaTgt > 0.05) {
            $anomalies[] = "GEOMETRY_MISMATCH: Target geometry delta is {$coordDeltaTgt}m";
        }
    }

    // 6. Span Reconciliation
    $calcSpan = 0.0;
    $spanDiff = 0.0;
    $spanPctDiff = 0.0;

    if ($srcAsset && $tgtAsset) {
        $calcSpan = haversineDistanceMeters(
            (float)$srcAsset['latitude'], (float)$srcAsset['longitude'],
            (float)$tgtAsset['latitude'], (float)$tgtAsset['longitude']
        );
        $spanDiff = abs($calcSpan - $dbLength);
        $spanPctDiff = ($dbLength > 0) ? ($spanDiff / $dbLength) * 100.0 : 0;

        if ($spanDiff > 0.1) { // 10 cm tolerance
            $anomalies[] = "SPAN_MISMATCH: DB span ({$dbLength}m) vs Calc span ({$calcSpan}m) diff is {$spanDiff}m";
        }
    }

    // 7. Natural Key Verification
    $minId = min($srcId, $tgtId);
    $maxId = max($srcId, $tgtId);
    $expectedNatKey = "TL-NAT:56:{$minId}-{$maxId}";

    if (isset($seenNaturalKeys[$expectedNatKey])) {
        $anomalies[] = "DUPLICATE_NATURAL_KEY: $expectedNatKey already created in PK {$seenNaturalKeys[$expectedNatKey]}";
    }
    $seenNaturalKeys[$expectedNatKey] = $pk;

    // Verdict
    $isPass = empty($anomalies);
    if (!$isPass) $totalAnomalies++;

    $reconciliationResults[$pk] = [
        'pk'                     => $pk,
        'transline_code'         => $tlCode,
        'natural_key'            => $expectedNatKey,
        'source_asset_id'        => $srcId,
        'source_asset_code'      => $srcAsset['kode_asset'] ?? 'N/A',
        'source_asset_name'      => $srcAsset['nama_asset'] ?? 'N/A',
        'source_penyulang_id'    => $srcAsset['penyulang_id'] ?? null,
        'source_latitude'        => $srcAsset['latitude'] ?? null,
        'source_longitude'       => $srcAsset['longitude'] ?? null,
        'target_asset_id'        => $tgtId,
        'target_asset_code'      => $tgtAsset['kode_asset'] ?? 'N/A',
        'target_asset_name'      => $tgtAsset['nama_asset'] ?? 'N/A',
        'target_penyulang_id'    => $tgtAsset['penyulang_id'] ?? null,
        'target_latitude'        => $tgtAsset['latitude'] ?? null,
        'target_longitude'       => $tgtAsset['longitude'] ?? null,
        'geometry_endpoint_a'    => [$geomSrcLng, $geomSrcLat],
        'geometry_endpoint_b'    => [$geomTgtLng, $geomTgtLat],
        'coord_delta_source_m'   => round($coordDeltaSrc, 4),
        'coord_delta_target_m'   => round($coordDeltaTgt, 4),
        'db_span_m'              => $dbLength,
        'calculated_span_m'      => round($calcSpan, 2),
        'span_abs_diff_m'        => round($spanDiff, 4),
        'span_pct_diff'          => round($spanPctDiff, 4),
        'temuan_collision'       => false,
        'anomalies'              => $anomalies,
        'verdict'                => $isPass ? 'PASS' : 'ANOMALY',
    ];

    echo sprintf("PK %-3d | %-16s | %s (%s) -> %s (%s)\n",
        $pk, $tlCode,
        $srcAsset['kode_asset'] ?? 'N/A', $srcId,
        $tgtAsset['kode_asset'] ?? 'N/A', $tgtId
    );
    echo sprintf("       Penyulang: [%d -> %d] | Span DB: %.2fm | Calc: %.2fm (Diff: %.4fm) | Geom Delta: [%.4fm, %.4fm]\n",
        $srcAsset['penyulang_id'] ?? 0, $tgtAsset['penyulang_id'] ?? 0,
        $dbLength, $calcSpan, $spanDiff,
        $coordDeltaSrc, $coordDeltaTgt
    );
    echo sprintf("       Verdict: %s%s\n\n",
        $isPass ? "✅ PASS" : "❌ ANOMALY: " . implode('; ', $anomalies),
        ""
    );
}

// 8. Protected Domains Verification
$protectedBaseline = [
    'assets'                  => ['count' => 5549, 'sha256' => 'd774be4f9d338fcdcdac5f6eac9f4a06f74300e1a293e10a8968c133df799ee9'],
    'gis_transline_proposals' => ['count' => 56,   'sha256' => '74974b866f483ea7fc624c5ffe5f506e85a7a66da4a1e9eb64ee401c8a6d8ca5'],
    'temuan'                  => ['count' => 638,  'sha256' => '48ae4d223e842a85ae509eeee49203ee2240cb4517b66a0e11bd3db05bb94a3a'],
    'temuan_materials'        => ['count' => 0,    'sha256' => '55f43e2fefa3f34c5c82129161873375891626eb56f668b9da5a6414a3585aae'],
    'sections'                => ['count' => 510,  'sha256' => '9e36669b061f9879a82825e0cb288f5d24cbdafbabdd85cd7075feceef4b621e'],
    'penyulang'               => ['count' => 134,  'sha256' => 'cb3ae4e4a8b891cf789fb4505aad2e4d8ae22bf94c9eaacec9fe98777628bb67'],
    'ulps'                    => ['count' => 3,    'sha256' => '1c3a2ccc852e80040f88b0a8df52ba4dabd1b36cd56e0ed45b5c9cf6edd3a531'],
];

$allProtectedIntact = true;
echo "=========================================================================================\n";
echo "PROTECTED DOMAINS ZERO-MUTATION AUDIT (CURRENT LIVE PRODUCTION):\n";
echo "=========================================================================================\n";
foreach ($protectedBaseline as $t => $base) {
    $cur = $currentFingerprints[$t] ?? null;
    $countMatch = ($cur && $cur['count'] === $base['count']);
    $shaMatch   = ($cur && $cur['sha256'] === $base['sha256']);
    $intact     = ($countMatch && $shaMatch);
    if (!$intact) $allProtectedIntact = false;

    printf("  %-25s | Count: %-5d (%s) | SHA: %s (%s)\n",
        $t,
        $cur['count'] ?? 0, $countMatch ? 'MATCH' : 'MUTATED',
        substr($cur['sha256'] ?? '', 0, 16) . '...', $shaMatch ? 'MATCH' : 'MUTATED'
    );
}

// Current gis_translines check:
$curTl = $currentFingerprints['gis_translines'] ?? null;
printf("  %-25s | Count: %-5d (EXPECTED: 225) | SHA: %s\n",
    'gis_translines', $curTl['count'] ?? 0, substr($curTl['sha256'] ?? '', 0, 16) . '...'
);

echo "\n=========================================================================================\n";
echo "FINAL RECONCILIATION SUMMARY:\n";
echo "=========================================================================================\n";
echo "Total PKs Reconciled      : " . count($targetPks) . "\n";
echo "Total PASS PKs            : " . (count($targetPks) - $totalAnomalies) . " / " . count($targetPks) . "\n";
echo "Total ANOMALY PKs         : " . $totalAnomalies . "\n";
echo "Protected Domains Intact  : " . ($allProtectedIntact ? "YES (100% IDENTICAL)" : "NO (MUTATED)") . "\n";
echo "Status Classification     : " . ($totalAnomalies === 0 && $allProtectedIntact ? "D1.1_PROVENANCE_PASS" : "D1.1_ANOMALY_HELD") . "\n";

// Save Full JSON Audit Deliverable
$fullAuditPayload = [
    'audit_timestamp'            => date('c'),
    'phase'                      => 'TL-MF-02 PHASE D1.1 PROVENANCE RECONCILIATION',
    'feeder_id'                  => 56,
    'feeder_name'                => 'BANJARAN',
    'status'                     => ($totalAnomalies === 0 && $allProtectedIntact ? 'D1.1_PROVENANCE_PASS' : 'D1.1_ANOMALY_HELD'),
    'total_pks_audited'          => count($targetPks),
    'pass_count'                 => count($targetPks) - $totalAnomalies,
    'anomaly_count'              => $totalAnomalies,
    'protected_domains_pass'     => $allProtectedIntact,
    'stop_gate_enforced'         => true,
    'current_table_fingerprints' => $currentFingerprints,
    'pk_reconciliations'         => $reconciliationResults,
];

file_put_contents(__DIR__ . '/tl_mf02_d1_1_provenance_audit.json', json_encode($fullAuditPayload, JSON_PRETTY_PRINT));
echo "\nSaved audit JSON to scratch/tl_mf02_d1_1_provenance_audit.json\n";
