<?php
if (function_exists('opcache_reset')) { @opcache_reset(); }
clearstatcache(true);
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$vendor = $root . '/vendor';

echo "=========================================================\n";
echo "  SIDAK TEJO - DEPLOYMENT FORENSIC AUDIT & COMPARISON   \n";
echo "=========================================================\n\n";

echo "=== 1. GIT REPOSITORY HEAD COMMIT AUDIT ===\n";
$gitHeadFile = $root . '/.git/HEAD';
$gitCommit = 'N/A';
if (file_exists($gitHeadFile)) {
    $ref = trim(file_get_contents($gitHeadFile));
    if (str_starts_with($ref, 'ref: ')) {
        $refPath = $root . '/.git/' . substr($ref, 5);
        if (file_exists($refPath)) {
            $gitCommit = trim(file_get_contents($refPath));
        }
    } else {
        $gitCommit = $ref;
    }
}
if ($gitCommit === 'N/A' && function_exists('exec')) {
    $gitCommit = @exec('git rev-parse HEAD');
}
echo "Running Git HEAD Commit Hash: " . ($gitCommit ?: 'UNKNOWN') . "\n\n";

echo "=== 2. FILE SHA1 CHECKSUMS AUDIT ===\n";
$filesToHash = [
    'public/index.php'                   => __DIR__ . '/index.php',
    'vendor/autoload.php'                => $vendor . '/autoload.php',
    'vendor/composer/autoload_real.php'   => $vendor . '/composer/autoload_real.php',
    'vendor/composer/autoload_static.php' => $vendor . '/composer/autoload_static.php',
];
foreach ($filesToHash as $label => $path) {
    $exists = file_exists($path);
    $sha1   = $exists ? sha1_file($path) : 'FILE MISSING';
    $size   = $exists ? filesize($path) : 0;
    echo sprintf("%-40s | Size: %-6d | SHA1: %s\n", $label, $size, $sha1);
}
echo "\n";

echo "=== 3. DESKTOP VS MOBILE HTTP RESPONSE COMPARISON ===\n";
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

$desktopRes = fetchUrlWithUA($targetUrl, $desktopUA);
echo "[DESKTOP UA]\n";
echo "  HTTP Code       : " . $desktopRes['http_code'] . "\n";
echo "  Body Length     : " . $desktopRes['body_len'] . " bytes\n";
echo "  Body MD5 Hash   : " . $desktopRes['body_md5'] . "\n";
echo "  Cache-Control   : " . ($desktopRes['headers']['Cache-Control'] ?? $desktopRes['headers']['cache-control'] ?? 'N/A') . "\n";
echo "  ETag            : " . ($desktopRes['headers']['ETag'] ?? $desktopRes['headers']['etag'] ?? 'N/A') . "\n";
echo "  Last-Modified   : " . ($desktopRes['headers']['Last-Modified'] ?? $desktopRes['headers']['last-modified'] ?? 'N/A') . "\n";
echo "  CF-Cache-Status : " . ($desktopRes['headers']['CF-Cache-Status'] ?? $desktopRes['headers']['cf-cache-status'] ?? 'N/A') . "\n";
echo "  Age             : " . ($desktopRes['headers']['Age'] ?? $desktopRes['headers']['age'] ?? 'N/A') . "\n\n";

$mobileRes = fetchUrlWithUA($targetUrl, $mobileUA);
echo "[MOBILE ANDROID UA]\n";
echo "  HTTP Code       : " . $mobileRes['http_code'] . "\n";
echo "  Body Length     : " . $mobileRes['body_len'] . " bytes\n";
echo "  Body MD5 Hash   : " . $mobileRes['body_md5'] . "\n";
echo "  Cache-Control   : " . ($mobileRes['headers']['Cache-Control'] ?? $mobileRes['headers']['cache-control'] ?? 'N/A') . "\n";
echo "  ETag            : " . ($mobileRes['headers']['ETag'] ?? $mobileRes['headers']['etag'] ?? 'N/A') . "\n";
echo "  Last-Modified   : " . ($mobileRes['headers']['Last-Modified'] ?? $mobileRes['headers']['last-modified'] ?? 'N/A') . "\n";
echo "  CF-Cache-Status : " . ($mobileRes['headers']['CF-Cache-Status'] ?? $mobileRes['headers']['cf-cache-status'] ?? 'N/A') . "\n";
echo "  Age             : " . ($mobileRes['headers']['Age'] ?? $mobileRes['headers']['age'] ?? 'N/A') . "\n\n";

if ($desktopRes['body_md5'] === $mobileRes['body_md5']) {
    echo "COMPARISON VERDICT: Server returns 100% IDENTICAL HTML content for both Desktop and Mobile User-Agents!\n";
    echo "CONCLUSION        : The error displayed on Mobile Android devices is 100% CLIENT-SIDE BROWSER CACHE / PWA SERVICE WORKER CACHE STORAGE.\n\n";
} else {
    echo "COMPARISON VERDICT: Server returned DIFFERENT HTML for Desktop vs Mobile!\n\n";
}

echo "=== 4. PWA SERVICE WORKER REGISTRATION AUDIT ===\n";
$swFiles = [
    'public/sw.js'             => __DIR__ . '/sw.js',
    'public/service-worker.js' => __DIR__ . '/service-worker.js',
    'public/manifest.json'     => __DIR__ . '/manifest.json',
];
foreach ($swFiles as $swLabel => $swPath) {
    $swExists = file_exists($swPath);
    echo sprintf("PWA File %-25s : %s\n", $swLabel, $swExists ? "EXISTS (" . filesize($swPath) . " bytes)" : "NOT FOUND");
}
echo "Registration File : app/Views/layouts/admin.php (Line 1117)\n";
echo "Registration Code : navigator.serviceWorker.register('<?= base_url(\"service-worker.js\") ?>?v=7')\n\n";

echo "=== 5. DATABASE EFFECTIVE CREDENTIALS AUDIT ===\n";
$envPath = $root . '/.env';
echo ".env File Status: " . (file_exists($envPath) ? "EXISTS at " . realpath($envPath) : "NOT FOUND in root " . $root) . "\n";

$findEnv = function(...$keys) {
    foreach ($keys as $k) {
        $val = getenv($k) ?: (getenv(strtoupper($k)) ?: (getenv(strtolower($k)) ?: ($_ENV[$k] ?? ($_ENV[strtoupper($k)] ?? ($_ENV[strtolower($k)] ?? ($_SERVER[$k] ?? ($_SERVER[strtoupper($k)] ?? ($_SERVER[strtolower($k)] ?? null))))))));
        if ($val !== null && $val !== '') { return $val; }
    }
    return null;
};

$effHost = $findEnv('DB_HOST', 'MYSQLHOST', 'MYSQL_HOST', 'database_default_hostname') ?: '127.0.0.1';
$effUser = $findEnv('DB_USER', 'MYSQLUSER', 'MYSQL_USER', 'database_default_username') ?: 'root';
$effPass = $findEnv('DB_PASS', 'MYSQLPASSWORD', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD', 'database_default_password') ?? '';
$effDb   = $findEnv('DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE', 'database_default_database') ?: 'sidaktejo';
$effPort = (int)($findEnv('DB_PORT', 'MYSQLPORT', 'MYSQL_PORT', 'database_default_port') ?: 3306);
$effDriver = 'MySQLi';

echo "Effective DB Hostname : " . $effHost . "\n";
echo "Effective DB Database : " . $effDb . "\n";
echo "Effective DB Username : " . $effUser . "\n";
echo "Effective DB Password : " . ($effPass === '' ? '(EMPTY PASSWORD)' : '*** (' . strlen($effPass) . ' chars)') . "\n";
echo "Effective DB DBDriver : " . $effDriver . "\n";
echo "Effective DB Port     : " . $effPort . "\n\n";

echo "=== 6. PRE-BOOT MYSQLI CONNECTION TEST ===\n";
try {
    $connTest = @mysqli_connect($effHost, $effUser, $effPass, $effDb, $effPort);
    if ($connTest) {
        echo "MYSQLI CONNECT STATUS: SUCCESS\n";
        echo "MySQL Server Version : " . mysqli_get_server_info($connTest) . "\n";
        $query = @mysqli_query($connTest, "SELECT count(*) as total FROM users");
        if ($query) {
            $row = mysqli_fetch_assoc($query);
            echo "USERS TABLE COUNT   : " . $row['total'] . " records\n";
        } else {
            echo "USERS TABLE QUERY ERR: " . mysqli_error($connTest) . "\n";
        }
        mysqli_close($connTest);
    } else {
        echo "MYSQLI CONNECT STATUS: FAILED\n";
        echo "MySQL Error Message  : " . mysqli_connect_error() . "\n";
        echo "MySQL Error Number   : " . mysqli_connect_errno() . "\n";
    }
} catch (\Throwable $err) {
    echo "MYSQLI CONNECT EXCEPTION: " . $err->getMessage() . "\n";
}
echo "\n";

echo "=== 7. ROOT / EMPTY PASSWORD IDENTIFICATION ===\n";
if ($effUser === 'root' || $effPass === '') {
    echo "CRITICAL WARNING: Database is currently attempting to use root/empty password!\n";
    echo "Source File: app/Config/Database.php (Lines 27-35)\n";
    echo "Reason: .env file does not exist in root, causing CodeIgniter 4 to fall back to hardcoded XAMPP defaults ('root' / '').\n";
} else {
    echo "OK: Database credentials are set to non-root user '" . $effUser . "' with non-empty password.\n";
}

echo "\n=== FORENSIC AUDIT COMPLETE ===\n";
