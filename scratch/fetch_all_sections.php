<?php
$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$auditFile  = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';

$auditData = json_decode(file_get_contents($auditFile), true);
$matrix = $auditData['feeder_matrix'] ?? [];

$allSections = [];
$totalSectionsCount = 0;

echo "Fetching sections from production for all 134 feeders...\n";

// Batch fetch sections for feeders that have section_count > 0
$feederIdsToFetch = [];
foreach ($matrix as $f) {
    $fId = (int)$f['penyulang_id'];
    $sc = (int)($f['section_count'] ?? 0);
    if ($sc > 0) {
        $feederIdsToFetch[] = $fId;
    }
}

echo "Found " . count($feederIdsToFetch) . " feeders with sections to query.\n";

$startTime = microtime(true);
$successCount = 0;

foreach ($feederIdsToFetch as $idx => $fId) {
    $cmd = sprintf(
        'curl.exe -s -k --resolve sidaktejo.site:443:91.108.119.5 -b %s --max-time 15 "https://sidaktejo.site/api/master-network/sections/%d"',
        escapeshellarg($cookieFile),
        $fId
    );
    $out = shell_exec($cmd);
    $json = json_decode($out, true);

    if (is_array($json) && !empty($json)) {
        $allSections[$fId] = $json;
        $totalSectionsCount += count($json);
        $successCount++;
    } else {
        $allSections[$fId] = [];
    }

    if (($idx + 1) % 25 === 0 || ($idx + 1) === count($feederIdsToFetch)) {
        echo sprintf("  Progress: %d / %d feeders queried (%d sections cached)...\n",
            $idx + 1, count($feederIdsToFetch), $totalSectionsCount
        );
    }
}

$duration = round(microtime(true) - $startTime, 2);
echo "Done in {$duration}s! Total sections retrieved: {$totalSectionsCount}\n";

$cacheFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/all_sections_by_feeder.json';
file_put_contents($cacheFile, json_encode($allSections, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Cached to: {$cacheFile}\n";
