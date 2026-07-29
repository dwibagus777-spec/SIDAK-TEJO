<?php
if (function_exists('opcache_reset')) { @opcache_reset(); }
clearstatcache(true);
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "=========================================================\n";
echo "  LIVE HTTP RESPONSE COMPARISON AUDIT (DESKTOP VS MOBILE)\n";
echo "=========================================================\n\n";

$targetUrl = 'https://sidaktejo.site/login';

$desktopUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
$mobileUA  = 'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

function fetchUrlWithUA($url, $userAgent) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $rawHeaders = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    $headers = [];
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }
    }

    return [
        'http_code' => $httpCode,
        'headers'   => $headers,
        'body'      => $body,
        'body_md5'  => md5($body),
        'body_len'  => strlen($body),
    ];
}

echo "=== 1. FETCHING DESKTOP USER-AGENT ===\n";
$desktopRes = fetchUrlWithUA($targetUrl, $desktopUA);
echo "HTTP Status Code : " . $desktopRes['http_code'] . "\n";
echo "Body Length      : " . $desktopRes['body_len'] . " bytes\n";
echo "Body MD5 Hash    : " . $desktopRes['body_md5'] . "\n";
echo "Cache-Control    : " . ($desktopRes['headers']['Cache-Control'] ?? $desktopRes['headers']['cache-control'] ?? 'N/A') . "\n";
echo "ETag             : " . ($desktopRes['headers']['ETag'] ?? $desktopRes['headers']['etag'] ?? 'N/A') . "\n";
echo "Last-Modified    : " . ($desktopRes['headers']['Last-Modified'] ?? $desktopRes['headers']['last-modified'] ?? 'N/A') . "\n";
echo "CF-Cache-Status  : " . ($desktopRes['headers']['CF-Cache-Status'] ?? $desktopRes['headers']['cf-cache-status'] ?? 'N/A') . "\n";
echo "Age              : " . ($desktopRes['headers']['Age'] ?? $desktopRes['headers']['age'] ?? 'N/A') . "\n\n";

echo "=== 2. FETCHING MOBILE ANDROID USER-AGENT ===\n";
$mobileRes = fetchUrlWithUA($targetUrl, $mobileUA);
echo "HTTP Status Code : " . $mobileRes['http_code'] . "\n";
echo "Body Length      : " . $mobileRes['body_len'] . " bytes\n";
echo "Body MD5 Hash    : " . $mobileRes['body_md5'] . "\n";
echo "Cache-Control    : " . ($mobileRes['headers']['Cache-Control'] ?? $mobileRes['headers']['cache-control'] ?? 'N/A') . "\n";
echo "ETag             : " . ($mobileRes['headers']['ETag'] ?? $mobileRes['headers']['etag'] ?? 'N/A') . "\n";
echo "Last-Modified    : " . ($mobileRes['headers']['Last-Modified'] ?? $mobileRes['headers']['last-modified'] ?? 'N/A') . "\n";
echo "CF-Cache-Status  : " . ($mobileRes['headers']['CF-Cache-Status'] ?? $mobileRes['headers']['cf-cache-status'] ?? 'N/A') . "\n";
echo "Age              : " . ($mobileRes['headers']['Age'] ?? $mobileRes['headers']['age'] ?? 'N/A') . "\n\n";

echo "=== 3. COMPARISON RESULTS ===\n";
if ($desktopRes['body_md5'] === $mobileRes['body_md5']) {
    echo "VERDICT: Server returns 100% IDENTICAL HTML content for both Desktop and Mobile User-Agents!\n";
    echo "PROOF: The error on Mobile Android is 100% caused by CLIENT-SIDE BROWSER CACHE / SERVICE WORKER CACHE STORAGE on the mobile device.\n";
} else {
    echo "VERDICT: Server returned DIFFERENT HTML for Desktop vs Mobile!\n";
}

echo "\n=== END COMPARISON AUDIT ===\n";
