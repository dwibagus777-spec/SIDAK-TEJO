<?php
$baseUrl = 'https://sidaktejo.site/network-intelligence';
$cookieFile = __DIR__ . '/prod_cookie.txt';
$key = 'sidak_transline_audit_2026';

function callEndpoint(string $url, string $cookieFile): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $startTime = microtime(true);
    $res = curl_exec($ch);
    $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'code'    => $httpCode,
        'elapsed' => $elapsedMs,
        'body'    => (string)$res,
        'json'    => json_decode((string)$res, true),
        'error'   => $err,
    ];
}

echo "=========================================================\n";
echo "  SIDAK TEJO — PHASE B.3 NETWORK INTELLIGENCE LIVE AUDIT  \n";
echo "=========================================================\n\n";

// 1. Audit Endpoint
$auditUrl = "{$baseUrl}/audit?key=" . urlencode($key);
echo "[1] Testing /audit endpoint...\n";
$auditRes = callEndpoint($auditUrl, $cookieFile);
echo "HTTP Code: {$auditRes['code']} ({$auditRes['elapsed']} ms)\n";

if ($auditRes['json']) {
    $meta = $auditRes['json']['audit_metadata'] ?? [];
    echo "Title: " . ($meta['report_title'] ?? '') . "\n";
    echo "Snapshot: " . ($meta['snapshot_id'] ?? '') . "\n";
    echo "Overall Verdict: " . ($meta['overall_verdict'] ?? '') . "\n";
    echo "Read-Only Verified: " . (!empty($meta['read_only_verified']) ? 'TRUE' : 'FALSE') . "\n";
    echo "Database Mutations: " . ($meta['database_mutations'] ?? '0') . "\n";

    echo "\n=== 7 FORENSIC SCORECARD ===\n";
    foreach ($auditRes['json']['forensic_scorecard'] as $c) {
        echo " - [{$c['status']}] {$c['name']}\n";
    }

    // Save report to writable/audits
    file_put_contents(__DIR__ . '/../writable/audits/B3_NETWORK_INTELLIGENCE_REPORT.json', json_encode($auditRes['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "\nSaved report to writable/audits/B3_NETWORK_INTELLIGENCE_REPORT.json\n";
} else {
    echo "Raw response:\n" . substr($auditRes['body'], 0, 500) . "\n";
}

// 2. Feeder 118 Traversal
echo "\n[2] Testing Feeder 118 Traversal (/feeder/118)...\n";
$f118Url = "{$baseUrl}/feeder/118?key=" . urlencode($key);
$f118Res = callEndpoint($f118Url, $cookieFile);
echo "HTTP Code: {$f118Res['code']} ({$f118Res['elapsed']} ms)\n";
if ($f118Res['json']) {
    $p = $f118Res['json']['payload'] ?? [];
    echo "Feeder 118 Root: Asset #{$p['root_asset_id']} ({$p['root_asset_code']})\n";
    echo "Root Resolution: {$p['root_resolution']} (Confidence: {$p['root_confidence']})\n";
    echo "Total Nodes in Tree: {$p['total_nodes_in_tree']}\n";
    echo "Total Tree Distance: {$p['total_tree_dist_m']} m\n";
}

// 3. Path Analysis on Feeder 118
echo "\n[3] Testing Path Analysis on Feeder 118 (/path?from=5245&to=5246)...\n";
$pathUrl = "{$baseUrl}/path?from=5245&to=5246&key=" . urlencode($key);
$pathRes = callEndpoint($pathUrl, $cookieFile);
echo "HTTP Code: {$pathRes['code']} ({$pathRes['elapsed']} ms)\n";
if ($pathRes['json']) {
    $p = $pathRes['json']['payload'] ?? [];
    echo "Reachable: " . (!empty($p['reachable']) ? 'TRUE' : 'FALSE') . "\n";
    echo "Algorithm: " . ($p['algorithm'] ?? '') . "\n";
    echo "Distance: " . ($p['distance_m'] ?? 0) . " m\n";
    echo "Edge Count: " . ($p['edge_count'] ?? 0) . "\n";
}

// 4. Section Topology on Feeder 15
echo "\n[4] Testing Section Topology on Feeder 15 (/sections/15)...\n";
$secUrl = "{$baseUrl}/sections/15?key=" . urlencode($key);
$secRes = callEndpoint($secUrl, $cookieFile);
echo "HTTP Code: {$secRes['code']} ({$secRes['elapsed']} ms)\n";
if ($secRes['json']) {
    $s = $secRes['json']['payload']['summary'] ?? [];
    echo "Section Count: " . ($s['section_count'] ?? 0) . "\n";
    echo "Intra Section Edges: " . ($s['intra_section_edges'] ?? 0) . "\n";
    echo "Boundary Switch Edges: " . ($s['boundary_switch_edges'] ?? 0) . "\n";
    echo "Cross Section Edges: " . ($s['cross_section_edges'] ?? 0) . "\n";
}

// 5. Integrity Analyzer
echo "\n[5] Testing Global Network Integrity (/integrity)...\n";
$intUrl = "{$baseUrl}/integrity?key=" . urlencode($key);
$intRes = callEndpoint($intUrl, $cookieFile);
echo "HTTP Code: {$intRes['code']} ({$intRes['elapsed']} ms)\n";
if ($intRes['json']) {
    $p = $intRes['json']['payload'] ?? [];
    echo "Verdict: " . ($p['verdict'] ?? '') . "\n";
    $obs = $p['observations'] ?? [];
    echo "Total Nodes: " . ($obs['total_nodes_evaluated'] ?? 0) . "\n";
    echo "Total Edges: " . ($obs['total_edges_evaluated'] ?? 0) . "\n";
    echo "Cross Feeder Edges: " . ($obs['cross_feeder_count'] ?? 0) . "\n";
    echo "Cross ULP Edges: " . ($obs['cross_ulp_count'] ?? 0) . "\n";
    echo "Multi Edge Count: " . ($obs['multi_edge_count'] ?? 0) . "\n";
}
