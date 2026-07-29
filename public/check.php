<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

$mysqli = @mysqli_connect($host, $user, $pass, $db, $port);
if ($mysqli) {
    echo "MYSQLI CONNECT: SUCCESS (MySQL Version: " . mysqli_get_server_info($mysqli) . ")\n";
    $query = @mysqli_query($mysqli, "SELECT count(*) as total FROM users");
    if ($query) {
        $row = mysqli_fetch_assoc($query);
        echo "USERS TABLE QUERY: SUCCESS (Total Users: " . $row['total'] . ")\n";
    } else {
        echo "USERS TABLE QUERY FAILED: " . mysqli_error($mysqli) . "\n";
    }
    mysqli_close($mysqli);
} else {
    echo "MYSQLI CONNECT FAILED: " . mysqli_connect_error() . " (Errno: " . mysqli_connect_errno() . ")\n";
}

echo "\n=== LANGKAH 8: CACHE & WRITEPATH AUDIT ===\n";
$writable = $root . '/writable';
echo "writable path: " . $writable . "\n";
echo "is_dir(writable/cache): " . (is_dir($writable . '/cache') ? 'YES' : 'NO') . "\n";
echo "is_writable(writable/cache): " . (is_writable($writable . '/cache') ? 'YES' : 'NO') . "\n";

echo "\n=== AUDIT COMPLETE ===\n";
