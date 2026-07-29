<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "=== PURE PHP LOGIN & DB FORENSIC AUDIT ===\n";

$root = dirname(__DIR__);
$envFile = $root . '/.env';

echo "1. Checking .env file: " . (file_exists($envFile) ? "EXISTS" : "NOT FOUND") . "\n";

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

echo "2. Resolved DB Credentials:\n";
echo "   Host: " . $host . "\n";
echo "   User: " . $user . "\n";
echo "   Pass: " . ($pass === '' ? '(empty)' : '*** (' . strlen($pass) . ' chars)') . "\n";
echo "   DB:   " . $db . "\n";
echo "   Port: " . $port . "\n\n";

echo "3. Testing direct mysqli_connect()...\n";
$mysqli = @mysqli_connect($host, $user, $pass, $db, $port);

if ($mysqli) {
    echo "   MYSQLI CONNECT: SUCCESS (Server Version: " . mysqli_get_server_info($mysqli) . ")\n";
    $query = @mysqli_query($mysqli, "SELECT count(*) as total FROM users");
    if ($query) {
        $row = mysqli_fetch_assoc($query);
        echo "   USERS TABLE QUERY: SUCCESS (Total Users: " . $row['total'] . ")\n";
    } else {
        echo "   USERS TABLE QUERY FAILED: " . mysqli_error($mysqli) . "\n";
    }
    mysqli_close($mysqli);
} else {
    echo "   MYSQLI CONNECT FAILED: " . mysqli_connect_error() . " (Errno: " . mysqli_connect_errno() . ")\n";
}

echo "\n=== END PURE PHP AUDIT ===\n";
