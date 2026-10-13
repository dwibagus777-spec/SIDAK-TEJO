<?php
$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';

function testUrl($path, $cookieFile) {
    $cmd = sprintf(
        'curl.exe -s -k --resolve sidaktejo.site:443:91.108.119.5 -b %s --max-time 15 "https://sidaktejo.site%s"',
        escapeshellarg($cookieFile),
        $path
    );
    $out = shell_exec($cmd);
    return json_decode($out, true);
}

$sec15 = testUrl('/api/master-network/sections/15', $cookieFile);
echo "Sections for Feeder 15 (BANJAR KEMANTREN):\n";
print_r($sec15);

$sec118 = testUrl('/api/master-network/sections/118', $cookieFile);
echo "\nSections for Feeder 118 (BAHAGIA STEEL 1):\n";
print_r($sec118);

$sec4 = testUrl('/api/master-network/sections/4', $cookieFile);
echo "\nSections for Feeder 4 (GEMURUNG):\n";
print_r($sec4);

$sec23 = testUrl('/api/master-network/sections/23', $cookieFile);
echo "\nSections for Feeder 23 (GEDANGAN):\n";
print_r($sec23);
