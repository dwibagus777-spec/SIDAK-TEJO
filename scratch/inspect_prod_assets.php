<?php
$h = fopen('e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv', 'r');
$header = fgetcsv($h);
$feeders = [];
$total = 0;
$statuses = [];
while (($r = fgetcsv($h)) !== false) {
    $total++;
    $f = $r[5] ?? 'UNKNOWN';
    $feeders[$f] = ($feeders[$f] ?? 0) + 1;
    $s = $r[15] ?? 'EMPTY';
    $statuses[$s] = ($statuses[$s] ?? 0) + 1;
}
fclose($h);

echo "Total assets: $total\n";
echo "Statuses:\n";
print_r($statuses);
echo "Feeders (" . count($feeders) . "):\n";
print_r($feeders);
