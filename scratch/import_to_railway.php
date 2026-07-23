<?php

$host = 'tokaido.proxy.rlwy.net';
$port = 25359;
$user = 'root';
$pass = 'ryK0OXBsIFwtXpgPSLlxTHIvNGybulMI';
$db   = 'railway';

try {
    echo "Connecting to Railway MySQL Cloud ($host:$port)...\n";
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Connected successfully!\n";
    echo "Reading sidaktejo.sql file...\n";
    $sqlFile = __DIR__ . '/../sidaktejo.sql';
    
    if (!file_exists($sqlFile)) {
        die("Error: File sidaktejo.sql not found at $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);
    echo "Executing SQL import to Railway...\n";
    
    $pdo->exec($sql);
    
    echo "\n=======================================================\n";
    echo "SUCCESS! All tables and data imported to Railway MySQL!\n";
    echo "=======================================================\n";

} catch (PDOException $e) {
    echo "PDO Error: " . $e->getMessage() . "\n";
}
