<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=sidaktejo;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $ulpId = 1;
    $res = $pdo->prepare("
        SELECT id, nama_penyulang, ulp_id 
        FROM penyulang 
        WHERE ulp_id = ? AND status = 'AKTIF'
        ORDER BY nama_penyulang ASC
    ");
    $res->execute([$ulpId]);
    $data = $res->fetchAll();

    echo "Direct query for ULP 1 (Sidoarjo Kota) - Count: " . count($data) . "\n";
    foreach (array_slice($data, 0, 10) as $r) {
        echo "- {$r['nama_penyulang']} (ulp_id: {$r['ulp_id']})\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
