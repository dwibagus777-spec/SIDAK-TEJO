<?php
$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';

$cmd = sprintf(
    'curl.exe -s -k --resolve sidaktejo.site:443:91.108.119.5 -b %s --max-time 15 "https://sidaktejo.site/master-assets/transline-audit?penyulang_id=15"',
    escapeshellarg($cookieFile)
);
$out = shell_exec($cmd);
file_put_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/raw_transline_audit_15.json', $out);
$json = json_decode($out, true);
echo "Response type: " . gettype($json) . "\n";
if (is_array($json)) {
    echo "Keys in response:\n";
    print_r(array_keys($json));
    echo "Sample data:\n";
    print_r(array_slice($json, 0, 5, true));
} else {
    echo "Raw response:\n" . substr($out, 0, 500) . "\n";
}
