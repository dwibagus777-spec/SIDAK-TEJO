<?php
$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-global-topology-audit');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    echo "HTTP {$code}: {$res}\n";
    exit(1);
}

$data = json_decode($res, true);
$postFps = $data['fingerprints']['post'] ?? [];

file_put_contents('scratch/post_highzoom_fingerprint.json', json_encode($postFps, JSON_PRETTY_PRINT));
echo "Saved post-fingerprint to scratch/post_highzoom_fingerprint.json\n\n";

$preFps = json_decode(file_get_contents('scratch/pre_highzoom_fingerprint.json'), true);

echo "=== ZERO-WRITE FIREWALL AUDIT COMPARISON ===\n";
printf("%-25s | %-10s | %-10s | %-10s\n", "Table Name", "Pre Count", "Post Count", "Hash Match");
echo str_repeat("-", 65) . "\n";

$allMatched = true;
foreach ($preFps as $table => $preInfo) {
    $postInfo = $postFps[$table] ?? null;
    if (!$postInfo) {
        printf("%-25s | %-10d | %-10s | %-10s\n", $table, $preInfo['count'], "MISSING", "FAIL");
        $allMatched = false;
        continue;
    }
    
    $countMatch = ($preInfo['count'] === $postInfo['count']);
    $hashMatch = ($preInfo['sha256'] === $postInfo['sha256']);
    $status = ($countMatch && $hashMatch) ? "MATCH ✅" : "MISMATCH ❌";
    if (!$countMatch || !$hashMatch) $allMatched = false;

    printf("%-25s | %-10d | %-10d | %-10s\n", $table, $preInfo['count'], $postInfo['count'], $status);
}

echo str_repeat("-", 65) . "\n";
if ($allMatched) {
    echo "VERDICT: 100% ZERO-WRITE FIREWALL VERIFIED (0 INSERT, 0 UPDATE, 0 DELETE, 0 DDL)\n";
} else {
    echo "VERDICT: DATABASE MUTATION DETECTED! INVESTIGATE IMMEDIATELY!\n";
    exit(1);
}
