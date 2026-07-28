<?php
// Script to fix ci_sessions table structure on Railway DB
$host = getenv('MYSQLHOST') ?: 'altaria.proxy.rlwy.net';
$port = getenv('MYSQLPORT') ?: 48116;
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'mdHhnrBwvreraXsIHEKlSEdVnWJBUoed';
$db   = getenv('MYSQLDATABASE') ?: 'railway';

// If running inside Railway container or local, try connecting
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Re-creating ci_sessions table with proper PRIMARY KEY...\n";
    $pdo->exec("DROP TABLE IF EXISTS `ci_sessions`");
    $pdo->exec("
        CREATE TABLE `ci_sessions` (
            `id` varchar(128) NOT NULL,
            `ip_address` varchar(45) NOT NULL,
            `timestamp` int(10) unsigned DEFAULT 0 NOT NULL,
            `data` blob NOT NULL,
            PRIMARY KEY (`id`, `ip_address`),
            KEY `ci_sessions_timestamp` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "SUCCESS: ci_sessions table fixed with PRIMARY KEY (id, ip_address).\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
