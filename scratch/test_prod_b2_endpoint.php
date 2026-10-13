<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$fingerprint = 'ad2c9fcb833ca2680d00bb45adfa27a4e87730745aa65bf1c3419c89cb713397';
$key = 'sidak_transline_audit_2026';

$url = "https://sidaktejo.site/master-assets/b2-atomic-commit?key={$key}&fingerprint={$fingerprint}&commit=0";

echo "Testing Simulation Mode on Live Production (commit=0)...\n";
echo "URL: {$url}\n\n";

$cmd = sprintf(
    'curl.exe -s -k --resolve sidaktejo.site:443:91.108.119.5 -b %s --max-time 30 "%s"',
    escapeshellarg($cookieFile),
    $url
);

$out = shell_exec($cmd);
echo "Response:\n";
echo substr($out, 0, 1000) . "\n";
