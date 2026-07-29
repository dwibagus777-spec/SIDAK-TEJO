<?php
if (function_exists('opcache_reset')) { @opcache_reset(); }
clearstatcache(true);
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$vendor = $root . '/vendor';

echo "=== LANGKAH 1: DIAGNOSTIC & DATABASE AUDIT ===\n";
echo "PHP_VERSION: " . PHP_VERSION . "\n";
echo "getcwd(): " . getcwd() . "\n";
echo "__DIR__: " . __DIR__ . "\n";

$findEnv = function(...$keys) {
    foreach ($keys as $k) {
        $val = getenv($k) ?: (getenv(strtoupper($k)) ?: (getenv(strtolower($k)) ?: ($_ENV[$k] ?? ($_ENV[strtoupper($k)] ?? ($_ENV[strtolower($k)] ?? ($_SERVER[$k] ?? ($_SERVER[strtoupper($k)] ?? ($_SERVER[strtolower($k)] ?? null))))))));
        if ($val !== null && $val !== '') { return $val; }
    }
    return null;
};

$host = $findEnv('DB_HOST', 'MYSQLHOST', 'MYSQL_HOST', 'database_default_hostname') ?: '127.0.0.1';
$user = $findEnv('DB_USER', 'MYSQLUSER', 'MYSQL_USER', 'database_default_username') ?: 'root';
$pass = $findEnv('DB_PASS', 'MYSQLPASSWORD', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD', 'database_default_password') ?? '';
$db   = $findEnv('DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE', 'database_default_database') ?: 'sidaktejo';
$port = (int)($findEnv('DB_PORT', 'MYSQLPORT', 'MYSQL_PORT', 'database_default_port') ?: 3306);

echo "\n=== LANGKAH 7: DATABASE CONNECTION AUDIT ===\n";
echo "Resolved DB Host: " . $host . "\n";
echo "Resolved DB User: " . $user . "\n";
echo "Resolved DB Pass: " . ($pass === '' ? '(empty)' : '*** (' . strlen($pass) . ' chars)') . "\n";
echo "Resolved DB Name: " . $db . "\n";
echo "Resolved DB Port: " . $port . "\n";

$possibleCreds = [
    ['host' => $host, 'user' => $user, 'pass' => $pass, 'db' => $db],
    ['host' => 'localhost', 'user' => 'u532206332_sidaktejo', 'pass' => 'Sidaktejo123!', 'db' => 'u532206332_sidaktejo'],
    ['host' => 'localhost', 'user' => 'u532206332_sidak', 'pass' => 'Sidaktejo123!', 'db' => 'u532206332_sidak'],
    ['host' => 'localhost', 'user' => 'u532206332_user', 'pass' => 'Sidaktejo123!', 'db' => 'u532206332_sidaktejo'],
    ['host' => 'localhost', 'user' => 'u532206332_sidaktejo', 'pass' => '', 'db' => 'u532206332_sidaktejo'],
];

$connected = false;
foreach ($possibleCreds as $c) {
    try {
        $conn = @mysqli_connect($c['host'], $c['user'], $c['pass'], $c['db']);
        if ($conn) {
            echo "CONNECT SUCCESS with user: {$c['user']} | db: {$c['db']}\n";
            $q = @mysqli_query($conn, "SELECT count(*) as total FROM users");
            if ($q) {
                $r = mysqli_fetch_assoc($q);
                echo "USERS TABLE TOTAL: " . $r['total'] . "\n";
            } else {
                echo "USERS QUERY ERR: " . mysqli_error($conn) . "\n";
            }
            mysqli_close($conn);
            $connected = true;
            break;
        } else {
            echo "CONNECT FAILED with user: {$c['user']} | db: {$c['db']} -> " . mysqli_connect_error() . "\n";
        }
    } catch (\Throwable $err) {
        echo "CONNECT EXCEPTION with user: {$c['user']} | db: {$c['db']} -> " . $err->getMessage() . "\n";
    }
}

echo "\n=== LANGKAH 8: CACHE & WRITEPATH AUDIT ===\n";
$writable = $root . '/writable';
echo "writable path: " . $writable . "\n";
echo "is_dir(writable/cache): " . (is_dir($writable . '/cache') ? 'YES' : 'NO') . "\n";
echo "is_writable(writable/cache): " . (is_writable($writable . '/cache') ? 'YES' : 'NO') . "\n";

echo "\n=== AUDIT COMPLETE ===\n";
