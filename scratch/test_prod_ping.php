<?php
$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$cmd = sprintf(
    'curl.exe -s -k --resolve sidaktejo.site:443:91.108.119.5 -b %s --max-time 15 "https://sidaktejo.site/gis/api-conductor-analytics?scope=global"',
    escapeshellarg($cookieFile)
);
$out = shell_exec($cmd);
$json = json_decode($out, true);
echo "Live Production Global Analytics:\n";
echo "Status: " . ($json['status'] ?? 'FAIL') . "\n";
echo "Translines: " . ($json['summary']['transline_resmi_count'] ?? -1) . "\n";
