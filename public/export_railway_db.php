<?php
// Direct standalone Railway Database Exporter

// Load environment variables from Railway / env
$host = getenv('database.default.hostname') ?: (getenv('DB_HOST') ?: 'localhost');
$port = getenv('database.default.port') ?: (getenv('DB_PORT') ?: 3306);
$user = getenv('database.default.username') ?: (getenv('DB_USER') ?: 'root');
$pass = getenv('database.default.password') ?: (getenv('DB_PASS') ?: '');
$db   = getenv('database.default.database') ?: (getenv('DB_NAME') ?: 'railway');

// Fallback lookup if env() arrays exist in $_ENV / $_SERVER
if (empty($pass) && isset($_ENV['database.default.password'])) $pass = $_ENV['database.default.password'];
if (empty($user) && isset($_ENV['database.default.username'])) $user = $_ENV['database.default.username'];
if (empty($db) && isset($_ENV['database.default.database'])) $db = $_ENV['database.default.database'];

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (\Throwable $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// Get tables
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="railway_live_sidak_tejo.sql"');

echo "-- SIDAK TEJO Live Database Export from Railway\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n";
echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
echo "SET NAMES utf8mb4;\n\n";

foreach ($tables as $table) {
    try {
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        echo "DROP TABLE IF EXISTS `{$table}`;\n";
        echo $createStmt[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $escapedCols = array_map(fn($c) => "`{$c}`", $cols);
                $escapedVals = array_map(function($val) use ($pdo) {
                    if ($val === null) return 'NULL';
                    return $pdo->quote($val);
                }, array_values($row));

                echo "INSERT INTO `{$table}` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
            }
            echo "\n";
        }
    } catch (\Throwable $ex) {
        continue;
    }
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
exit;
