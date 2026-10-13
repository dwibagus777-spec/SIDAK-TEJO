<?php
$inv = json_decode(file_get_contents('scratch/full_134_feeder_inventory.json'), true);
$totA = 0;
$totConn = 0;
$totIsol = 0;
$totTl = 0;
$totCand = 0;
$totD = 0;
$totHigh = 0;
$totReview = 0;
$totBlocked = 0;
$feedersWithAssets = 0;
$feedersReadyAi = 0;

foreach ($inv as $r) {
    $totA += $r['total_assets'];
    $totConn += $r['connected_assets'];
    $totIsol += $r['isolated_assets'];
    $totTl += $r['active_translines'];
    $totCand += $r['candidate_count'];
    $totD += $r['defensible_count'];
    $totHigh += $r['high_confidence_count'];
    $totReview += $r['review_count'];
    $totBlocked += $r['ambiguous_count'];
    if ($r['total_assets'] > 0) $feedersWithAssets++;
    if ($r['status'] === 'READY_FOR_AI') $feedersReadyAi++;
}

echo "=== GLOBAL 134 FEEDER INVENTORY SUMMARY ===\n";
echo "Total Feeders Scanned           : " . count($inv) . "\n";
echo "Feeders with Assets             : {$feedersWithAssets}\n";
echo "Feeders Empty (No Assets)       : " . (count($inv) - $feedersWithAssets) . "\n";
echo "Feeders READY_FOR_AI            : {$feedersReadyAi}\n";
echo "Total Master JTM Assets         : {$totA}\n";
echo "Total Connected Assets          : {$totConn}\n";
echo "Total Isolated Assets           : {$totIsol}\n";
echo "Total Active Translines         : {$totTl}\n";
echo "Total Candidates Analyzed       : {$totCand}\n";
echo "Total AUTO_DEFENSIBLE Candidates: {$totD}\n";
echo "Total High Confidence Candidates: {$totHigh}\n";
echo "Total Review Required Candidates: {$totReview}\n";
echo "Total Ambiguous / Blocked       : {$totBlocked}\n";
