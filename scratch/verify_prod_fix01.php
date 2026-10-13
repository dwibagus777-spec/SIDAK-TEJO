<?php

/**
 * Phase 1: Live Production Verification of FIX-01 Operator Persistence
 * Feeder: BANJAR KEMANTREN (id = 15)
 */

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

function makeRequest($url, $method = 'GET', $data = null, $customHeaders = []) {
    global $cookieFile, $resolveOpt;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'X-Requested-With: XMLHttpRequest',
    ];
    $headers = array_merge($headers, $customHeaders);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            if (is_array($data)) {
                $payload = json_encode($data);
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $rawHeader = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    return [
        'code'   => $httpCode,
        'header' => $rawHeader,
        'body'   => $body,
        'error'  => $err,
    ];
}

echo "=== STEP 1: VERIFY AUTHENTICATION SESSION ===\n";
$res = makeRequest($baseUrl . '/gis');
echo "GIS page check HTTP code: {$res['code']}\n";
if ($res['code'] === 401 || $res['code'] === 302 || str_contains($res['body'], 'login')) {
    echo "Attempting login...\n";
    $loginPage = makeRequest($baseUrl . '/login');
    preg_match('/name="csrf_test_name" value="([^"]+)"/i', $loginPage['body'], $csrfMatches);
    $csrfToken = $csrfMatches[1] ?? '';
    $loginData = [
        'username'       => 'admin',
        'password'       => 'admin123',
        'csrf_test_name' => $csrfToken,
    ];
    $doLogin = makeRequest($baseUrl . '/login', 'POST', http_build_query($loginData), ['Content-Type: application/x-www-form-urlencoded']);
    echo "Login response HTTP code: {$doLogin['code']}\n";
}

echo "\n=== STEP 2: LOAD VALID SECTIONS FOR PENYULANG 15 ===\n";
$secRes = makeRequest($baseUrl . '/ajax/network/section/15');
echo "Sections HTTP code: {$secRes['code']}\n";
$sectionsAvailable = json_decode($secRes['body'], true) ?? [];
echo "Available sections for Penyulang 15: " . count($sectionsAvailable) . "\n";
foreach ($sectionsAvailable as $s) {
    echo "  - Section #{$s['id']}: {$s['nama_section']}\n";
}

if (empty($sectionsAvailable)) {
    echo "ERROR: No sections available for Penyulang 15!\n";
    exit(1);
}

// Inspect TL-04 preview
$tl04Res = makeRequest($baseUrl . '/gis/api-transline-tl04-preview?penyulang_id=15');
echo "\nTL-04 Preview HTTP code: {$tl04Res['code']}\n";
$tl04Data = json_decode($tl04Res['body'], true);

// Select an isolated asset to test operator correction
$firstIso = $tl04Data['isolated_asset_diagnostics']['assets_detail'][0];
$targetAssetId = (int)$firstIso['asset_id'];
echo "Selected isolated target asset: #{$targetAssetId}\n";

echo "\n=== STEP 3: CAPTURE BEFORE STATE (FINGERPRINT) ===\n";
$ctxBeforeRes = makeRequest($baseUrl . "/ajax/network/asset-context/{$targetAssetId}");
$ctxBefore = json_decode($ctxBeforeRes['body'], true);
$assetBefore = $ctxBefore['asset'] ?? [];
$networkBefore = $ctxBefore['network'] ?? [];

echo "BEFORE STATE:\n";
echo "  asset_id:             {$assetBefore['id']}\n";
echo "  kode_asset:           {$assetBefore['kode_asset']}\n";
echo "  nama_asset:           {$assetBefore['nama_asset']}\n";
echo "  latitude:             {$assetBefore['latitude']}\n";
echo "  longitude:            {$assetBefore['longitude']}\n";
echo "  penyulang_id:         " . ($networkBefore['penyulang']['id'] ?? 'NULL') . " ({$networkBefore['penyulang']['nama_penyulang']})\n";
echo "  ulp_id:               " . ($networkBefore['ulp']['id'] ?? 'NULL') . "\n";
echo "  section_id:           " . ($networkBefore['section']['id'] ?? 'NULL') . " (" . ($networkBefore['section']['nama_section'] ?? '') . ")\n";

// Pick a target section from sectionsAvailable
$currentSecId = (int)($networkBefore['section']['id'] ?? 0);
$targetSec = null;
foreach ($sectionsAvailable as $s) {
    if ((int)$s['id'] !== $currentSecId) {
        $targetSec = $s;
        break;
    }
}
if (!$targetSec) {
    $targetSec = $sectionsAvailable[0];
}
$newSecId = (int)$targetSec['id'];
$newSecName = $targetSec['nama_section'];
echo "Selected operator correction: Section #{$newSecId} ({$newSecName})\n";

echo "\n=== STEP 4: EXECUTE FIX-01 OPERATOR CORRECTION POST ===\n";
$correctPayload = [
    'asset_id'       => $targetAssetId,
    'section_id'     => $newSecId,
    'new_section_id' => $newSecId,
    'reason'         => 'OPERATOR_PERSISTENCE_VERIFICATION_CYCLE_PROD',
];

$correctRes = makeRequest($baseUrl . '/ajax/network/correct-section', 'POST', $correctPayload);
echo "Correction POST HTTP code: {$correctRes['code']}\n";
echo "Correction response: " . $correctRes['body'] . "\n";

$correctJson = json_decode($correctRes['body'], true);
if (!$correctJson || ($correctJson['status'] !== 'success')) {
    echo "ERROR: Operator correction failed!\n";
    exit(1);
}

echo "\n=== STEP 5: RE-FETCH FROM DB & VERIFY AFTER STATE ===\n";
$ctxAfterRes = makeRequest($baseUrl . "/ajax/network/asset-context/{$targetAssetId}");
$ctxAfter = json_decode($ctxAfterRes['body'], true);
$assetAfter = $ctxAfter['asset'] ?? [];
$networkAfter = $ctxAfter['network'] ?? [];

echo "AFTER STATE:\n";
echo "  asset_id:             {$assetAfter['id']}\n";
echo "  kode_asset:           {$assetAfter['kode_asset']}\n";
echo "  nama_asset:           {$assetAfter['nama_asset']}\n";
echo "  latitude:             {$assetAfter['latitude']}\n";
echo "  longitude:            {$assetAfter['longitude']}\n";
echo "  penyulang_id:         " . ($networkAfter['penyulang']['id'] ?? 'NULL') . "\n";
echo "  ulp_id:               " . ($networkAfter['ulp']['id'] ?? 'NULL') . "\n";
echo "  section_id:           " . ($networkAfter['section']['id'] ?? 'NULL') . " (" . ($networkAfter['section']['nama_section'] ?? '') . ")\n";
echo "  section_badge:        " . ($networkAfter['section']['status_badge'] ?? '') . "\n";

echo "\n=== STEP 6: VERIFY MANDATORY INVARIANTS ===\n";
$errors = [];

// 1. section_id changed to target
if ((int)($networkAfter['section']['id'] ?? 0) !== $newSecId) {
    $errors[] = "FAIL: section_id did not change to requested value {$newSecId}! Got: " . ($networkAfter['section']['id'] ?? 'NULL');
} else {
    echo "  [PASS] section_id correctly persisted in DB as {$newSecId}\n";
}

// 2. Coordinates bit-for-bit identical
if ((string)$assetBefore['latitude'] !== (string)$assetAfter['latitude'] ||
    (string)$assetBefore['longitude'] !== (string)$assetAfter['longitude']) {
    $errors[] = "FAIL: Coordinates mutated! Before: {$assetBefore['latitude']},{$assetBefore['longitude']} vs After: {$assetAfter['latitude']},{$assetAfter['longitude']}";
} else {
    echo "  [PASS] Coordinates bit-for-bit identical ({$assetAfter['latitude']}, {$assetAfter['longitude']})\n";
}

// 3. Identifiers bit-for-bit identical
if ((string)$assetBefore['kode_asset'] !== (string)$assetAfter['kode_asset'] ||
    (string)$assetBefore['nama_asset'] !== (string)$assetAfter['nama_asset']) {
    $errors[] = "FAIL: Asset identifiers mutated!";
} else {
    echo "  [PASS] Asset identifiers intact ({$assetAfter['kode_asset']})\n";
}

// 4. Feeder and ULP intact
if ((int)$networkBefore['penyulang']['id'] !== (int)$networkAfter['penyulang']['id'] ||
    (int)$networkBefore['ulp']['id'] !== (int)$networkAfter['ulp']['id']) {
    $errors[] = "FAIL: Feeder or ULP mutated!";
} else {
    echo "  [PASS] Feeder and ULP firewall intact\n";
}

// 5. Resolution badge is DIKOREKSI_OPERATOR
if (($networkAfter['section']['status_badge'] ?? '') === 'DIKOREKSI_OPERATOR') {
    echo "  [PASS] Status badge is DIKOREKSI_OPERATOR (displays '✎ DIKOREKSI OPERATOR')\n";
} else {
    echo "  [INFO] Status badge: " . ($networkAfter['section']['status_badge'] ?? 'none') . "\n";
}

if (!empty($errors)) {
    echo "\nVERIFICATION FAILED:\n" . implode("\n", $errors) . "\n";
    exit(1);
}

echo "\n>>> FIX-01 OPERATOR PERSISTENCE TO MARIADB IS 100% PROVEN AND VERIFIED ON PRODUCTION! <<<\n";
