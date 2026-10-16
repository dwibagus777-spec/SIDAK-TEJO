<?php
$urls = [
    'https://sidaktejo.site/verify_static.php',
    'https://sidaktejo.site/compare_classmaps.php',
    'https://sidaktejo.site/this-page-does-not-exist',
];

foreach ($urls as $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    echo "URL: $url\n";
    echo "HTTP: $httpCode\n";
    echo "Content-Type: $contentType\n";
    echo "Body length: " . strlen($response) . "\n";
    echo "Has stack trace: " . (strpos($response, 'PUBLIC_INDEX_EXCEPTION') !== false || strpos($response, 'CodeIgniter\\\\CodeIgniter') !== false ? 'YES' : 'NO') . "\n";
    echo "---\n";
}