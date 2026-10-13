<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

// Step 1: GET /login to retrieve CSRF token and initial session cookie
$ch = curl_init($baseUrl . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0']);
$html = curl_exec($ch);
curl_close($ch);

// Find CSRF input: name="csrf_test_name" or similar
$csrfName = 'csrf_test_name';
$csrfHash = '';
if (preg_match('/name=["\'](csrf_[^"\']+)["\']\s+value=["\']([^"\']+)["\']/i', $html, $m)) {
    $csrfName = $m[1];
    $csrfHash = $m[2];
} elseif (preg_match('/<input[^>]+name=["\']([^"\']*csrf[^"\']*)["\'][^>]+value=["\']([^"\']+)["\']/i', $html, $m)) {
    $csrfName = $m[1];
    $csrfHash = $m[2];
}

echo "Found CSRF: {$csrfName} = " . substr($csrfHash, 0, 10) . "...\n";

// Step 2: POST /login with credentials and CSRF
$postData = [
    $csrfName     => $csrfHash,
    'username'    => 'admin',
    'password'    => 'admin123',
    'remember_me' => '1',
];

$ch = curl_init($baseUrl . '/login');
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0',
    'X-Requested-With: XMLHttpRequest',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login status: {$httpCode}\n";
echo "Response: " . substr($res, 0, 300) . "\n";
