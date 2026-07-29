<?php
if (function_exists('opcache_reset')) { @opcache_reset(); }
clearstatcache(true);
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$vendor = $root . '/vendor';

echo "====================================================\n";
echo "  SIDAK TEJO - DEPLOYMENT FORENSIC AUDIT REPORT     \n";
echo "====================================================\n\n";

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

echo "=== 3. MOBILE VS DESKTOP & PWA SERVICE WORKER AUDIT ===\n";
echo "Request User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n";
echo "Request IP:         " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n";

$swFiles = [
    'public/sw.js'             => __DIR__ . '/sw.js',
    'public/service-worker.js' => __DIR__ . '/service-worker.js',
    'public/manifest.json'     => __DIR__ . '/manifest.json',
];
foreach ($swFiles as $swLabel => $swPath) {
    $swExists = file_exists($swPath);
    echo sprintf("PWA File %-25s : %s\n", $swLabel, $swExists ? "EXISTS (" . filesize($swPath) . " bytes)" : "NOT FOUND");
}
echo "\n";

echo "=== 4. CDN / CLOUDFLARE / CACHE HEADERS AUDIT ===\n";
echo "CF-Ray:           " . ($_SERVER['HTTP_CF_RAY'] ?? 'NOT VIA CLOUDFLARE') . "\n";
echo "CF-Connecting-IP: " . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'N/A') . "\n";
echo "Cache-Control:    " . ($_SERVER['HTTP_CACHE_CONTROL'] ?? 'N/A') . "\n";
echo "Accept-Encoding:  " . ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'N/A') . "\n\n";

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
