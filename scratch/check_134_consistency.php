<?php

$json = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
$matrix = $json['feeder_matrix'] ?? [];

echo "Total feeders in JSON: " . count($matrix) . "\n";

$csvLines = file('e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/TL01_ALL_NETWORK_COVERAGE_MATRIX.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
echo "Total rows in CSV (including header): " . count($csvLines) . "\n";

// Check 1: Search for any occurrence of "Maspion 1" or "MASPION 1" in entire JSON and CSV
$jsonStr = file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json');
$csvStr = file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/TL01_ALL_NETWORK_COVERAGE_MATRIX.csv');

echo "\n--- CHECK 1: Maspion Occurrences ---\n";
echo "JSON occurrences of 'Maspion 1': " . substr_count(strtolower($jsonStr), 'maspion 1') . "\n";
echo "CSV occurrences of 'Maspion 1': " . substr_count(strtolower($csvStr), 'maspion 1') . "\n";
echo "JSON occurrences of 'Maspion 2': " . substr_count(strtolower($jsonStr), 'maspion 2') . "\n";
echo "CSV occurrences of 'Maspion 2': " . substr_count(strtolower($csvStr), 'maspion 2') . "\n";

// Check 2: Verify PYL-016 in JSON and CSV
echo "\n--- CHECK 2: PYL-016 Record ---\n";
foreach ($matrix as $f) {
    if ($f['kode_penyulang'] === 'PYL-016') {
        echo "JSON: ID={$f['penyulang_id']} | Kode={$f['kode_penyulang']} | Nama='{$f['nama_penyulang']}' | Assets={$f['total_jtm_assets']}\n";
    }
}
for ($i = 1; $i < count($csvLines); $i++) {
    $c = str_getcsv($csvLines[$i]);
    if ($c[1] === 'PYL-016') {
        echo "CSV: ULP={$c[0]} | Kode={$c[1]} | Nama='{$c[2]}' | Assets={$c[4]}\n";
    }
}

// Check 3: Verify MASPION in JSON and CSV
echo "\n--- CHECK 3: MASPION Record ---\n";
foreach ($matrix as $f) {
    if (stripos($f['nama_penyulang'], 'maspion') !== false) {
        echo "JSON: ID={$f['penyulang_id']} | Kode={$f['kode_penyulang']} | Nama='{$f['nama_penyulang']}' | Assets={$f['total_jtm_assets']}\n";
    }
}
for ($i = 1; $i < count($csvLines); $i++) {
    $c = str_getcsv($csvLines[$i]);
    if (stripos($c[2], 'maspion') !== false) {
        echo "CSV: ULP={$c[0]} | Kode={$c[1]} | Nama='{$c[2]}' | Assets={$c[4]}\n";
    }
}

// Check 4: Check if any feeder has duplicate codes or IDs
echo "\n--- CHECK 4: Uniqueness & Consistency ---\n";
$ids = [];
$codes = [];
$discrepancies = [];
foreach ($matrix as $f) {
    $id = $f['penyulang_id'];
    $code = $f['kode_penyulang'];
    if (isset($ids[$id])) {
        $discrepancies[] = "Duplicate ID: {$id}";
    }
    if (isset($codes[$code])) {
        $discrepancies[] = "Duplicate Code: {$code}";
    }
    $ids[$id] = true;
    $codes[$code] = true;
}
echo "Discrepancies found in JSON: " . (empty($discrepancies) ? "NONE (All unique)" : implode(", ", $discrepancies)) . "\n";
