<?php
$tlData = json_decode(file_get_contents('scratch/resp_analytics_global.json'), true);
$items = $tlData['items'] ?? [];

$sectionCounts = [];
$feederSectionTl = [];
$noSectionCount = 0;

foreach ($items as $tl) {
    $fName = $tl['nama_penyulang'] ?? 'Unknown';
    $sId = (int)($tl['section_id'] ?? 0);
    $sName = $tl['nama_section'] ?? '-';

    if ($sId > 0 && $sName !== '-' && $sName !== '') {
        $sectionCounts[$sName] = ($sectionCounts[$sName] ?? 0) + 1;
        $feederSectionTl[$fName][$sName] = ($feederSectionTl[$fName][$sName] ?? 0) + 1;
    } else {
        $noSectionCount++;
        $feederSectionTl[$fName]['NO_SECTION'] = ($feederSectionTl[$fName]['NO_SECTION'] ?? 0) + 1;
    }
}

echo "Total Authoritative Translines: " . count($items) . "\n";
echo "Translines WITH explicit Section: " . (count($items) - $noSectionCount) . "\n";
echo "Translines WITHOUT Section (section_id=0): {$noSectionCount}\n\n";

echo "Sections among Authoritative Translines:\n";
foreach ($sectionCounts as $sName => $cnt) {
    echo "  • {$sName}: {$cnt} translines\n";
}

echo "\nBreakdown by Feeder:\n";
foreach ($feederSectionTl as $fName => $secs) {
    echo "Feeder {$fName}:\n";
    foreach ($secs as $sName => $cnt) {
        echo "  - {$sName}: {$cnt} translines\n";
    }
}
