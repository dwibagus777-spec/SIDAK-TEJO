<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-network?penyulang_id=15&layers=JTM,GARDU,TRAFO,SWITCH');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$features = $data['data']['features'] ?? [];
$translines = $data['data']['translines'] ?? [];

$assets = [];
foreach ($features as $f) {
    if (($f['properties']['entity_type'] ?? '') === 'ASSET') {
        $assets[] = [
            'id' => (int)$f['properties']['id'],
            'section_id' => $f['properties']['section_id'] ?? null,
            'kode_asset' => $f['properties']['kode_asset'] ?? null,
            'coordinates' => $f['geometry']['coordinates'] ?? null,
        ];
    }
}
usort($assets, fn($a, $b) => $a['id'] <=> $b['id']);
usort($translines, fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);

$assetsHash = hash('sha256', json_encode($assets));
$translinesHash = hash('sha256', json_encode($translines));

echo "Current Live Production Database Status:\n";
echo "  Assets: " . count($assets) . " (Hash: {$assetsHash})\n";
echo "  Translines: " . count($translines) . " (Hash: {$translinesHash})\n\n";

$preFile = 'C:/Users/INSPEKSIKOTA/.gemini/antigravity/brain/e5e83a3d-4509-4271-8129-9355c979dd3c/scratch/perf_pre_fingerprint.json';
if (file_exists($preFile)) {
    $pre = json_decode(file_get_contents($preFile), true);
    $assetsMatch = ($assetsHash === $pre['assets_hash']);
    $translinesMatch = ($translinesHash === $pre['translines_hash']);
    echo "Comparison Against Baseline Pre-Fingerprint:\n";
    echo "  Assets Hash Match: " . ($assetsMatch ? "MATCH (100% IDENTICAL - ZERO MUTATION)" : "MISMATCH") . "\n";
    echo "  Translines Hash Match: " . ($translinesMatch ? "MATCH (100% IDENTICAL - ZERO MUTATION)" : "MISMATCH") . "\n";
    echo "  Active Transline Count: " . count($translines) . " (Baseline: " . $pre['transline_count'] . ")\n";
    echo "  Zero-Write Firewall Invariant: " . ($assetsMatch && $translinesMatch ? "PASSED" : "FAILED") . "\n";
} else {
    echo "Pre-fingerprint file not found.\n";
}
