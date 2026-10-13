<?php
$h = fopen('e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv', 'r');
$header = fgetcsv($h);
$singleFeeders = [
    'JAVA PASIFIK 4', 'JAVA PASIFIK 3', 'HASIL KARYA 5', 'HASIL KARYA 4',
    'HASIL KARYA 3', 'HASIL KARYA 1', 'GERRY FOOD', 'EMDEKI 2',
    'EMDEKI 1', 'BAHAGIA STEEL 4', 'ASIA 2', 'ASIA 1'
];
$statuses = [];
while (($r = fgetcsv($h)) !== false) {
    $f = $r[5] ?? '';
    if (in_array($f, $singleFeeders, true)) {
        $st = $r[15] ?? '';
        $statuses[$f][$st] = ($statuses[$f][$st] ?? 0) + 1;
    }
}
fclose($h);
print_r($statuses);
