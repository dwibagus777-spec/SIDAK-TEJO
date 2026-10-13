<?php
$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];
$c = curl_init('https://sidaktejo.site/sld/view/15');
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($c, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($c, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($c, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($c);
curl_close($c);
echo 'HTML Length: ' . strlen($html) . PHP_EOL;
echo substr($html, 0, 500) . PHP_EOL;
if (strpos($html, 'sldEngine') !== false) echo "Contains sldEngine!\n";
if (strpos($html, 'login') !== false) echo "Contains login!\n";
if (strpos($html, 'Sign in') !== false) echo "Contains Sign in!\n";
