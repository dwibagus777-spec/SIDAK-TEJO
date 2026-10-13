<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';

$outputJson = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/TOPOLOGY_CANDIDATE_DRY_RUN.json';
$data = json_decode(file_get_contents($outputJson), true);

// Run candidate engine to get all candidates
require_once 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/run_topology_candidate_engine_b17.php';

echo "======================================================================\n";
echo "🔍 FORENSIC AUDIT: 1,730 REVIEW_REQUIRED CANDIDATES TAXONOMY\n";
echo "======================================================================\n";

$candidates = $run1['candidates'] ?? [];
$reviewList = [];

foreach ($candidates as $c) {
    if ($c['classification'] === 'REVIEW_REQUIRED') {
        $reviewList[] = $c;
    }
}

echo "Total REVIEW_REQUIRED candidates: " . count($reviewList) . "\n\n";

// Classify each candidate by primary root cause
$categories = [
    'REVIEW_SECTION_UNKNOWN'   => [], // Both or one asset section_id == 0
    'REVIEW_SEQUENCE_JUMP'     => [], // sequence delta > 3
    'REVIEW_DISTANCE_LONG'     => [], // span > 85m
    'REVIEW_DEGREE_HIGH'       => [], // degree >= 2
    'REVIEW_STATUS_PENALTY'    => [], // asset status is OPERATIONAL (score 0.70)
    'REVIEW_MULTI_FACTOR_LOW'  => []  // combination of several penalties
];

$feederReviewCounts = [];

foreach ($reviewList as $c) {
    $ev = $c['evidence'];
    $secU = (int)$c['source_section_id'];
    $secV = (int)$c['target_section_id'];
    $dist = (float)$c['distance_meters'];
    $seqDelta = (int)($ev['sequence_delta'] ?? 999);
    $degScore = (float)($ev['degree_score'] ?? 1.0);
    $statusScore = (float)($ev['asset_status_score'] ?? 1.0);
    $fName = $c['nama_penyulang'];

    $feederReviewCounts[$fName] = ($feederReviewCounts[$fName] ?? 0) + 1;

    // Determine primary driver of low confidence
    if ($secU === 0 || $secV === 0) {
        $categories['REVIEW_SECTION_UNKNOWN'][] = $c;
    } elseif ($seqDelta > 3) {
        $categories['REVIEW_SEQUENCE_JUMP'][] = $c;
    } elseif ($dist > 85.0) {
        $categories['REVIEW_DISTANCE_LONG'][] = $c;
    } elseif ($degScore < 0.85) {
        $categories['REVIEW_DEGREE_HIGH'][] = $c;
    } elseif ($statusScore < 1.0) {
        $categories['REVIEW_STATUS_PENALTY'][] = $c;
    } else {
        $categories['REVIEW_MULTI_FACTOR_LOW'][] = $c;
    }
}

echo "Itemized Root Cause Taxonomy for 1,730 REVIEW Candidates:\n";
echo sprintf("%-28s | %-8s | %-8s | %s\n", "Review Category", "Count", "Percent", "Field Engineering Description");
echo str_repeat("-", 95) . "\n";

foreach ($categories as $cat => $items) {
    $cnt = count($items);
    $pct = round(($cnt / count($reviewList)) * 100.0, 2);
    $desc = match($cat) {
        'REVIEW_SECTION_UNKNOWN'  => 'Aset belum memiliki section_id kanonikal (Seksi 0 / Unmapped)',
        'REVIEW_SEQUENCE_JUMP'    => 'Nomor urut tiang melompat (seqDelta > 3 tiang)',
        'REVIEW_DISTANCE_LONG'    => 'Panjang bentang mendekati batas fisik (85m < span <= 120m)',
        'REVIEW_DEGREE_HIGH'      => 'Titik tiang merupakan persimpangan/degree tinggi (degree >= 2)',
        'REVIEW_STATUS_PENALTY'   => 'Status aset OPERATIONAL/non-NORMAL (penalti status aset 0.70)',
        'REVIEW_MULTI_FACTOR_LOW' => 'Kombinasi deviasi jarak + sudut bearing'
    };
    echo sprintf("%-28s | %8d | %6.2f%%  | %s\n", $cat, $cnt, $pct, $desc);
}

echo "\nTop Feeders Contributing to REVIEW_REQUIRED:\n";
arsort($feederReviewCounts);
$topF = array_slice($feederReviewCounts, 0, 10, true);
foreach ($topF as $fn => $cnt) {
    $pct = round(($cnt / count($reviewList)) * 100.0, 2);
    echo sprintf("  • %-24s: %4d candidates (%5.2f%%)\n", $fn, $cnt, $pct);
}

$summaryData = [
    'total_review_candidates' => count($reviewList),
    'taxonomy'                => array_map(fn($arr) => count($arr), $categories),
    'feeder_distribution'     => $feederReviewCounts,
    'samples_per_category'    => array_map(fn($arr) => array_slice($arr, 0, 5), $categories)
];
file_put_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/forensic_1730_review_taxonomy.json', json_encode($summaryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
