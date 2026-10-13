<?php

function analyzeCsv($path) {
    if (!file_exists($path)) {
        echo "File not found: $path\n";
        return;
    }
    $f = fopen($path, 'r');
    $header = fgetcsv($f);
    $penyulang = [];
    $ulp = [];
    $jenis = [];
    $constructions = [];
    $validGps = 0;
    $invalidGps = 0;
    $count = 0;

    // Find indices
    $pIdx = array_search('Penyulang', $header);
    $uIdx = array_search('ULP', $header);
    $jIdx = array_search('Jenis Asset', $header);
    $cIdx = array_search('Konstruksi (e.g. TM1)', $header);
    $latIdx = array_search('Latitude', $header);
    $lngIdx = array_search('Longitude', $header);

    while (($row = fgetcsv($f)) !== false) {
        $count++;
        if ($pIdx !== false) {
            $p = trim($row[$pIdx] ?? '');
            $penyulang[$p] = ($penyulang[$p] ?? 0) + 1;
        }
        if ($uIdx !== false) {
            $u = trim($row[$uIdx] ?? '');
            $ulp[$u] = ($ulp[$u] ?? 0) + 1;
        }
        if ($jIdx !== false) {
            $j = trim($row[$jIdx] ?? '');
            $jenis[$j] = ($jenis[$j] ?? 0) + 1;
        }
        if ($cIdx !== false) {
            $c = trim($row[$cIdx] ?? '');
            $constructions[$c] = ($constructions[$c] ?? 0) + 1;
        }
        if ($latIdx !== false && $lngIdx !== false) {
            $lat = (float)($row[$latIdx] ?? 0);
            $lng = (float)($row[$lngIdx] ?? 0);
            if ($lat < -6 && $lat > -9 && $lng > 111 && $lng < 115) {
                $validGps++;
            } else {
                $invalidGps++;
            }
        }
    }
    fclose($f);

    echo "====================================================\n";
    echo "FILE: " . basename($path) . "\n";
    echo "Total Rows: $count\n";
    echo "Valid GPS: $validGps, Invalid GPS: $invalidGps\n";
    echo "ULP breakdown:\n";
    print_r($ulp);
    echo "Penyulang breakdown:\n";
    print_r($penyulang);
    echo "Jenis Asset breakdown:\n";
    print_r($jenis);
    echo "Top 10 Constructions:\n";
    arsort($constructions);
    print_r(array_slice($constructions, 0, 10, true));
}

$files = [
    'e:/XAMPP/htdocs/SIDAK TEJO/writable/Template_Import_MULTI_PENYULANG_PART1.csv',
    'e:/XAMPP/htdocs/SIDAK TEJO/writable/Template_Import_JTM_SIWALAN_PANJI.csv',
];

foreach ($files as $file) {
    analyzeCsv($file);
}
