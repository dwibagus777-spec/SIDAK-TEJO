<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$resolve = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

// Fetch assets and existing translines
$ch = curl_init('https://sidaktejo.site/gis/api-network?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
$res = curl_exec($ch);
curl_close($ch);

$netData = json_decode($res, true)['data'] ?? [];
$assets = $netData['assets'] ?? [];
$translines = $netData['translines'] ?? [];

echo "Total assets: " . count($assets) . "\n";
echo "Initial translines: " . count($translines) . "\n";

// Helper functions
function hDist($lat1, $lon1, $lat2, $lon2) {
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return round(2 * $r * atan2(sqrt($a), sqrt(1 - $a)), 2);
}

function calcBearing($lat1, $lon1, $lat2, $lon2) {
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dLon = deg2rad($lon2 - $lon1);
    $y = sin($dLon) * cos($p2);
    $x = cos($p1) * sin($p2) - sin($p1) * cos($p2) * cos($dLon);
    return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
}

function bearingDelta($b1, $b2) {
    $d = abs($b1 - $b2);
    return $d > 180.0 ? 360.0 - $d : $d;
}

function parseSeq($code) {
    if (!$code) return null;
    if (preg_match('/(\d+)$/', trim($code), $m)) return (int)$m[1];
    return null;
}

// In-memory simulation of progressive batches
$simEdges = [];
foreach ($translines as $tl) {
    $s = (int)($tl['source_asset_id'] ?? $tl['from_asset_id']);
    $t = (int)($tl['target_asset_id'] ?? $tl['to_asset_id']);
    $min = min($s, $t);
    $max = max($s, $t);
    $simEdges["{$min}-{$max}"] = true;
}

$simAssets = [];
foreach ($assets as $a) {
    $simAssets[(int)$a['id']] = $a;
}

for ($batch = 1; $batch <= 15; $batch++) {
    // 1. Compute degrees & adjacency
    $deg = [];
    $adj = [];
    foreach ($simAssets as $id => $a) {
        $deg[$id] = 0;
        $adj[$id] = [];
    }
    foreach (array_keys($simEdges) as $edgeKey) {
        list($u, $v) = explode('-', $edgeKey);
        $u = (int)$u; $v = (int)$v;
        $deg[$u]++; $deg[$v]++;
        $adj[$u][] = $v; $adj[$v][] = $u;
    }

    // Terminal anchors (degree 1)
    $anchors = array_keys(array_filter($deg, fn($d) => $d === 1));
    $anchorMap = array_fill_keys($anchors, true);

    // Incoming bearings for terminal anchors
    $incomingBearings = [];
    foreach ($anchors as $aId) {
        $nbrs = $adj[$aId] ?? [];
        if (count($nbrs) === 1) {
            $pId = $nbrs[0];
            $incomingBearings[$aId] = calcBearing(
                (float)$simAssets[$pId]['latitude'], (float)$simAssets[$pId]['longitude'],
                (float)$simAssets[$aId]['latitude'], (float)$simAssets[$aId]['longitude']
            );
        }
    }

    // 2. Find candidate pairs within 85m
    $candidates = [];
    $allIds = array_keys($simAssets);
    $n = count($allIds);

    for ($i = 0; $i < $n; $i++) {
        $idA = $allIds[$i];
        $aA = $simAssets[$idA];
        for ($j = $i + 1; $j < $n; $j++) {
            $idB = $allIds[$j];
            $aB = $simAssets[$idB];

            $min = min($idA, $idB);
            $max = max($idA, $idB);
            $key = "{$min}-{$max}";
            if (isset($simEdges[$key])) continue;

            $dist = hDist((float)$aA['latitude'], (float)$aA['longitude'], (float)$aB['latitude'], (float)$aB['longitude']);
            if ($dist < 2.0 || $dist > 85.0) continue;

            $degA = $deg[$idA];
            $degB = $deg[$idB];

            // Anchor score (35)
            $anchorPts = (isset($anchorMap[$idA]) || isset($anchorMap[$idB])) ? 35 : 0;

            // Sequence score (25)
            $seqA = parseSeq($aA['kode_asset']);
            $seqB = parseSeq($aB['kode_asset']);
            $seqPts = 0;
            if ($seqA !== null && $seqB !== null) {
                $dSeq = abs($seqA - $seqB);
                if ($dSeq === 1) $seqPts = 25;
                elseif ($dSeq === 2) $seqPts = 18;
                elseif ($dSeq <= 5) $seqPts = 10;
                elseif ($dSeq <= 10) $seqPts = 5;
                else $seqPts = 10; // Same feeder series
            } else {
                $seqPts = 10;
            }

            // Bearing score (20)
            $bearingPts = 10;
            $candBearing = calcBearing((float)$aA['latitude'], (float)$aA['longitude'], (float)$aB['latitude'], (float)$aB['longitude']);
            if (isset($incomingBearings[$idA])) {
                $dTheta = bearingDelta($incomingBearings[$idA], $candBearing);
                if ($dTheta <= 15.0) $bearingPts = 20;
                elseif ($dTheta <= 30.0) $bearingPts = 10;
                else $bearingPts = 0;
            } elseif (isset($incomingBearings[$idB])) {
                $revBearing = calcBearing((float)$aB['latitude'], (float)$aB['longitude'], (float)$aA['latitude'], (float)$aA['longitude']);
                $dTheta = bearingDelta($incomingBearings[$idB], $revBearing);
                if ($dTheta <= 15.0) $bearingPts = 20;
                elseif ($dTheta <= 30.0) $bearingPts = 10;
                else $bearingPts = 0;
            }

            // Degree score (10)
            $degPts = 0;
            if ($degA + 1 > 4 || $degB + 1 > 4) {
                continue; // Blocked
            } elseif (($degA === 1 && $degB <= 1) || ($degB === 1 && $degA <= 1)) {
                $degPts = 10;
            } elseif (($degA === 2 && $degB <= 1) || ($degB === 2 && $degA <= 1)) {
                $degPts = 5;
            } else {
                $degPts = 3;
            }

            // Distance score (10)
            $distPts = 0;
            if ($dist >= 20.0 && $dist <= 55.0) $distPts = 10;
            elseif ($dist >= 5.0 && $dist < 20.0) $distPts = 7;
            elseif ($dist >= 2.0 && $dist < 5.0) $distPts = 5;
            elseif ($dist > 55.0 && $dist <= 85.0) $distPts = 4;

            $totalScore = $anchorPts + $seqPts + $bearingPts + $degPts + $distPts;
            $conf = round($totalScore / 100.0, 2);

            if ($conf >= 0.85 && $dist <= 85.0 && $degA <= 2 && $degB <= 2) {
                $candidates[] = [
                    'key'   => $key,
                    'u'     => $idA,
                    'v'     => $idB,
                    'dist'  => $dist,
                    'score' => $totalScore,
                    'conf'  => $conf,
                ];
            }
        }
    }

    if (empty($candidates)) {
        echo "Batch #{$batch}: 0 AUTO_COMPLETE candidates. GRAPH STABILIZED!\n";
        break;
    }

    // Sort by conf DESC, dist ASC
    usort($candidates, function($a, $b) {
        $c = $b['conf'] <=> $a['conf'];
        if ($c !== 0) return $c;
        return $a['dist'] <=> $b['dist'];
    });

    // Pick top 10 candidates with degree progression check
    $picked = [];
    $simDeg = $deg;
    foreach ($candidates as $c) {
        $u = $c['u']; $v = $c['v'];
        if ($simDeg[$u] + 1 > 4 || $simDeg[$v] + 1 > 4) continue;
        $simDeg[$u]++; $simDeg[$v]++;
        $simEdges[$c['key']] = true;
        $picked[] = $c;
        if (count($picked) >= 10) break;
    }

    $connected = count(array_filter($simDeg, fn($d) => $d > 0));
    $isolated = count(array_filter($simDeg, fn($d) => $d === 0));
    echo "Batch #{$batch}: Committed " . count($picked) . " translines. Total lines: " . count($simEdges) . " | Connected assets: {$connected} | Isolated: {$isolated}\n";
    foreach ($picked as $p) {
        echo "  TL: {$simAssets[$p['u']]['kode_asset']} <--> {$simAssets[$p['v']]['kode_asset']} ({$p['dist']}m, Score: {$p['score']})\n";
    }
}
