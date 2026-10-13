<?php
$h = fopen('scratch/prod_assets.csv', 'r');
$header = fgetcsv($h);

$types = [];
$sampleSwitchPoles = [];

while (($r = fgetcsv($h)) !== false) {
    $jenis = $r[3] ?? '';
    $tipe = $r[8] ?? '';
    $nama = $r[2] ?? '';
    $fName = $r[5] ?? '';
    $sec = $r[6] ?? '';

    $types[$tipe] = ($types[$tipe] ?? 0) + 1;

    if (preg_match('/(LBS|PMCB|REC|RECLOSER|FUSE|SWITCH|DS|GI)/i', $nama) || preg_match('/(LBS|PMCB|REC|RECLOSER|SWITCH|DS)/i', $tipe)) {
        if (count($sampleSwitchPoles) < 20) {
            $sampleSwitchPoles[] = [
                'nama' => $nama,
                'tipe' => $tipe,
                'jenis' => $jenis,
                'feeder' => $fName,
                'section' => $sec
            ];
        }
    }
}
fclose($h);

echo "Asset Types in CSV:\n";
arsort($types);
foreach (array_slice($types, 0, 15) as $t => $cnt) {
    echo "  • {$t}: {$cnt}\n";
}

echo "\nSample Switching/Boundary Assets found in names/types:\n";
foreach ($sampleSwitchPoles as $s) {
    echo "  - [{$s['feeder']}] {$s['nama']} (Tipe: {$s['tipe']}, Jenis: {$s['jenis']}, Sec: {$s['section']})\n";
}
