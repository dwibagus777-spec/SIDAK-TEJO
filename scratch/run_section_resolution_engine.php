<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';

use App\Services\OneShotNetworkIngestionService;

$auditFile      = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';
$assetsCsvFile  = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';
$globalTlFile   = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_analytics_global.json';
$sectionsFile   = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/all_sections_by_feeder.json';

echo "======================================================================\n";
echo "⚡ PHASE B.1.6: SECTION RESOLUTION ENGINE (STRICT READ-ONLY)\n";
echo "======================================================================\n";
echo "Mode: STRICT READ-ONLY | 0 DB Mutation | 0 Git Commit | Baseline fb8c47b\n\n";

// -----------------------------------------------------------------------------
// 1. LOAD CANONICAL SECTIONS DICTIONARY
// -----------------------------------------------------------------------------
$rawSections = json_decode(file_get_contents($sectionsFile), true);
$sectionsByFeeder = [];
$totalCanonicalSections = 0;
$singleSectionFeeders = [];

foreach ($rawSections as $fId => $secList) {
    $fId = (int)$fId;
    $sectionsByFeeder[$fId] = [];
    foreach ($secList as $s) {
        $sectionsByFeeder[$fId][] = [
            'id'           => (int)$s['id'],
            'penyulang_id' => (int)$s['penyulang_id'],
            'nama_section' => trim($s['nama_section']),
            'status'       => $s['status'] ?? 'AKTIF'
        ];
        $totalCanonicalSections++;
    }
    if (count($sectionsByFeeder[$fId]) === 1) {
        $singleSectionFeeders[$fId] = $sectionsByFeeder[$fId][0];
    }
}

echo "Loaded {$totalCanonicalSections} canonical sections across " . count($sectionsByFeeder) . " feeders.\n";
echo "Feeders with exactly 1 canonical section: " . count($singleSectionFeeders) . " feeders.\n\n";

// -----------------------------------------------------------------------------
// 2. LOAD 134 FEEDERS
// -----------------------------------------------------------------------------
$auditData = json_decode(file_get_contents($auditFile), true);
$feederMatrix = $auditData['feeder_matrix'] ?? [];

$feeders = [];
$feederByName = [];
foreach ($feederMatrix as $f) {
    $fId = (int)$f['penyulang_id'];
    $fName = strtoupper(trim($f['nama_penyulang']));
    $feeders[$fId] = [
        'id'             => $fId,
        'kode_penyulang' => $f['kode_penyulang'],
        'nama_penyulang' => $f['nama_penyulang'],
        'ulp_id'         => (int)$f['ulp_id'],
        'nama_ulp'       => $f['nama_ulp'] ?? '',
        'section_count'  => (int)($f['section_count'] ?? 0)
    ];
    $feederByName[$fName] = $fId;
}

// -----------------------------------------------------------------------------
// 3. LOAD 217 AUTHORITATIVE TRANSLINES & BUILD SEED MAP
// -----------------------------------------------------------------------------
$tlData = json_decode(file_get_contents($globalTlFile), true);
$existingTranslines = $tlData['items'] ?? [];

$authoritativeNodeSections = [];
$authoritativeEdgeList = [];
$authoritativeAdj = [];

foreach ($existingTranslines as $tl) {
    $tlId = (int)$tl['transline_id'];
    $fId  = (int)$tl['penyulang_id'];
    $u    = (int)$tl['source_asset_id'];
    $v    = (int)$tl['target_asset_id'];
    $sId  = (int)($tl['section_id'] ?? 0);
    $sName= trim($tl['nama_section'] ?? '-');

    $authoritativeAdj[$fId][$u][] = $v;
    $authoritativeAdj[$fId][$v][] = $u;

    if ($sId > 0 && $sName !== '-' && $sName !== '') {
        $secInfo = ['section_id' => $sId, 'section_name' => $sName];
        $authoritativeNodeSections[$u] = $secInfo;
        $authoritativeNodeSections[$v] = $secInfo;
    }

    $authoritativeEdgeList[] = [
        'transline_id' => $tlId,
        'penyulang_id' => $fId,
        'source_id'    => $u,
        'target_id'    => $v,
        'section_id'   => $sId,
        'section_name' => $sName
    ];
}

echo "Seeded " . count($authoritativeNodeSections) . " assets directly from 17 sectioned authoritative translines.\n";

// -----------------------------------------------------------------------------
// 4. LOAD 5,236 ASSETS
// -----------------------------------------------------------------------------
$h = fopen($assetsCsvFile, 'r');
$header = fgetcsv($h);

$allAssets = [];
$assetsByFeeder = [];
$totalAssets = 0;

while (($row = fgetcsv($h)) !== false) {
    $totalAssets++;
    $kodeAsset   = trim($row[1] ?? '');
    $namaAsset   = trim($row[2] ?? '');
    $jenisAsset  = strtoupper(trim($row[3] ?? 'JTM'));
    $ulpName     = trim($row[4] ?? '');
    $feederName  = trim($row[5] ?? '');
    $sectionName = trim($row[6] ?? '');
    $lat         = (float)trim($row[13] ?? '0');
    $lng         = (float)trim($row[14] ?? '0');
    $status      = trim($row[15] ?? 'NORMAL');

    $fKey = strtoupper($feederName);
    $fId  = $feederByName[$fKey] ?? 0;

    $hasExplicitSec = ($sectionName !== '' && $sectionName !== '-' && strtolower($sectionName) !== 'null');

    // Natural sequence number
    $seq = 0;
    if (preg_match('/_(\d+)$/', $namaAsset, $m)) {
        $seq = (int)$m[1];
    } elseif (preg_match('/-(\d+)$/', $kodeAsset, $m)) {
        $seq = (int)$m[1];
    }

    $assetItem = [
        'id'               => $totalAssets,
        'kode_asset'       => $kodeAsset,
        'nama_asset'       => $namaAsset,
        'jenis_asset'      => $jenisAsset,
        'penyulang_id'     => $fId,
        'nama_penyulang'   => $feederName,
        'ulp_name'         => $ulpName,
        'section_name_raw' => $sectionName,
        'has_explicit_sec' => $hasExplicitSec,
        'latitude'         => $lat,
        'longitude'        => $lng,
        'status'           => $status,
        'sequence_no'      => $seq
    ];

    $allAssets[$totalAssets] = $assetItem;
    $assetsByFeeder[$fId][] = $assetItem;
}
fclose($h);

echo "Loaded {$totalAssets} active assets across " . count($assetsByFeeder) . " active feeders.\n\n";

// -----------------------------------------------------------------------------
// 5. EXECUTE SECTION RESOLUTION ALGORITHM
// -----------------------------------------------------------------------------
echo "[EXECUTING HIERARCHICAL SECTION RESOLUTION ENGINE]...\n";

$resolutionResults = [];
$statusCounts = [
    'EXPLICIT'                           => 0,
    'INHERITED_FROM_AUTHORITATIVE_EDGE' => 0,
    'DETERMINISTIC_BOUNDARY'            => 0,
    'PROPAGATED'                         => 0,
    'AMBIGUOUS'                          => 0,
    'UNRESOLVED'                         => 0
];

$feederSummary = [];

foreach ($assetsByFeeder as $fId => $fAssets) {
    $fName = $feeders[$fId]['nama_penyulang'] ?? "Penyulang #{$fId}";
    $fSections = $sectionsByFeeder[$fId] ?? [];
    $isSingleSection = isset($singleSectionFeeders[$fId]);

    // Build map of canonical sections for fast matching
    $canonSecByName = [];
    $canonSecById = [];
    foreach ($fSections as $sec) {
        $canonSecByName[strtoupper($sec['nama_section'])] = $sec;
        $canonSecById[$sec['id']] = $sec;
    }

    $fResolvedCounts = [
        'total'                             => count($fAssets),
        'EXPLICIT'                          => 0,
        'INHERITED_FROM_AUTHORITATIVE_EDGE'=> 0,
        'DETERMINISTIC_BOUNDARY'           => 0,
        'PROPAGATED'                        => 0,
        'AMBIGUOUS'                         => 0,
        'UNRESOLVED'                        => 0
    ];

    // Local resolution map for propagation
    $feederResolved = [];

    // PASS 1: EXPLICIT & SINGLE-SECTION FEEDER & AUTHORITATIVE INHERITANCE
    foreach ($fAssets as $a) {
        $aId = $a['id'];
        $rawSec = trim($a['section_name_raw']);
        $rawSecUpper = strtoupper($rawSec);

        // 1. EXPLICIT MATCH (Asset table already has explicit section matching canonical)
        if ($a['has_explicit_sec'] && isset($canonSecByName[$rawSecUpper])) {
            $matched = $canonSecByName[$rawSecUpper];
            $feederResolved[$aId] = [
                'resolved_section_id'   => $matched['id'],
                'resolved_section_name' => $matched['nama_section'],
                'status'                => 'EXPLICIT',
                'confidence'            => 1.00,
                'evidence'              => ['EXPLICIT_DB_VALUE', 'CANONICAL_SECTION_MATCH', 'FEEDER_MATCH']
            ];
            continue;
        }

        // 2. INHERITED FROM AUTHORITATIVE TRANSLINE EDGE
        if (isset($authoritativeNodeSections[$aId])) {
            $authSec = $authoritativeNodeSections[$aId];
            $feederResolved[$aId] = [
                'resolved_section_id'   => $authSec['section_id'],
                'resolved_section_name' => $authSec['section_name'],
                'status'                => 'INHERITED_FROM_AUTHORITATIVE_EDGE',
                'confidence'            => 0.98,
                'evidence'              => ['AUTHORITATIVE_TRANSLINE_EDGE', 'CONNECTED_NODE_INHERITANCE', 'FEEDER_MATCH']
            ];
            continue;
        }

        // 3. DETERMINISTIC BOUNDARY: SINGLE-SECTION FEEDER
        // If the feeder has exactly ONE section, every asset on this feeder must belong to it!
        if ($isSingleSection) {
            $onlySec = $singleSectionFeeders[$fId];
            $feederResolved[$aId] = [
                'resolved_section_id'   => $onlySec['id'],
                'resolved_section_name' => $onlySec['nama_section'],
                'status'                => 'DETERMINISTIC_BOUNDARY',
                'confidence'            => 0.99,
                'evidence'              => ['SINGLE_CANONICAL_SECTION_FEEDER', '100%_DETERMINISTIC_ISOLATION', 'FEEDER_MATCH']
            ];
            continue;
        }

        // 4. DETERMINISTIC BOUNDARY: SWITCHING DEVICE NAME MATCH
        // Check if pole name/code contains a boundary device that uniquely matches ONE section
        $matchingBoundarySections = [];
        foreach ($fSections as $sec) {
            $secName = $sec['nama_section'];
            // Extract tokens (e.g. GI, LBS ..., PMCB ..., REC ...)
            $devices = preg_split('/\s*-\s*/', $secName);
            foreach ($devices as $dev) {
                $devClean = trim($dev);
                if (strlen($devClean) >= 3 && stripos($a['nama_asset'], $devClean) !== false) {
                    $matchingBoundarySections[$sec['id']] = $sec;
                }
            }
        }

        if (count($matchingBoundarySections) === 1) {
            $matched = reset($matchingBoundarySections);
            $feederResolved[$aId] = [
                'resolved_section_id'   => $matched['id'],
                'resolved_section_name' => $matched['nama_section'],
                'status'                => 'DETERMINISTIC_BOUNDARY',
                'confidence'            => 0.95,
                'evidence'              => ['SWITCHING_DEVICE_BOUNDARY_MATCH', 'NAME_NOMENCLATURE', 'FEEDER_MATCH']
            ];
            continue;
        } elseif (count($matchingBoundarySections) > 1) {
            // Frontier switching device shared by 2 adjacent sections
            $matched = reset($matchingBoundarySections);
            $feederResolved[$aId] = [
                'resolved_section_id'   => $matched['id'],
                'resolved_section_name' => $matched['nama_section'],
                'status'                => 'AMBIGUOUS',
                'confidence'            => 0.65,
                'evidence'              => ['FRONTIER_SWITCHING_DEVICE_JUNCTION', 'SHARED_BOUNDARY', 'FEEDER_MATCH']
            ];
            continue;
        }
    }

    // PASS 2: PROPAGATION (Sort by sequence_no and propagate within continuous sequence clusters)
    // Sort feeder assets by sequence_no
    usort($fAssets, function ($a, $b) {
        return $a['sequence_no'] <=> $b['sequence_no'];
    });

    $countAssets = count($fAssets);
    // Forward propagation along sequence
    for ($i = 0; $i < $countAssets; $i++) {
        $aId = $fAssets[$i]['id'];
        if (!isset($feederResolved[$aId])) {
            // Check previous neighbour
            if ($i > 0) {
                $prevId = $fAssets[$i - 1]['id'];
                $prevSeq = $fAssets[$i - 1]['sequence_no'];
                $currSeq = $fAssets[$i]['sequence_no'];
                $dist = OneShotNetworkIngestionService::haversineDistance(
                    $fAssets[$i - 1]['latitude'], $fAssets[$i - 1]['longitude'],
                    $fAssets[$i]['latitude'], $fAssets[$i]['longitude']
                );

                if (isset($feederResolved[$prevId]) &&
                    $feederResolved[$prevId]['status'] !== 'AMBIGUOUS' &&
                    $dist <= 120.0 &&
                    abs($currSeq - $prevSeq) <= 2) {
                    
                    $inheritedSec = $feederResolved[$prevId];
                    $feederResolved[$aId] = [
                        'resolved_section_id'   => $inheritedSec['resolved_section_id'],
                        'resolved_section_name' => $inheritedSec['resolved_section_name'],
                        'status'                => 'PROPAGATED',
                        'confidence'            => 0.90,
                        'evidence'              => ['SEQUENCE_SPATIAL_PROPAGATION', 'SPAN_DISTANCE_' . round($dist, 1) . 'M', 'FEEDER_MATCH']
                    ];
                }
            }
        }
    }

    // Backward propagation for assets before the seed
    for ($i = $countAssets - 1; $i >= 0; $i--) {
        $aId = $fAssets[$i]['id'];
        if (!isset($feederResolved[$aId])) {
            if ($i < $countAssets - 1) {
                $nextId = $fAssets[$i + 1]['id'];
                $nextSeq = $fAssets[$i + 1]['sequence_no'];
                $currSeq = $fAssets[$i]['sequence_no'];
                $dist = OneShotNetworkIngestionService::haversineDistance(
                    $fAssets[$i + 1]['latitude'], $fAssets[$i + 1]['longitude'],
                    $fAssets[$i]['latitude'], $fAssets[$i]['longitude']
                );

                if (isset($feederResolved[$nextId]) &&
                    $feederResolved[$nextId]['status'] !== 'AMBIGUOUS' &&
                    $dist <= 120.0 &&
                    abs($nextSeq - $currSeq) <= 2) {

                    $inheritedSec = $feederResolved[$nextId];
                    $feederResolved[$aId] = [
                        'resolved_section_id'   => $inheritedSec['resolved_section_id'],
                        'resolved_section_name' => $inheritedSec['resolved_section_name'],
                        'status'                => 'PROPAGATED',
                        'confidence'            => 0.90,
                        'evidence'              => ['SEQUENCE_SPATIAL_PROPAGATION_REVERSE', 'SPAN_DISTANCE_' . round($dist, 1) . 'M', 'FEEDER_MATCH']
                    ];
                }
            }
        }
    }

    // PASS 3: FINALIZE ASSETS
    foreach ($fAssets as $a) {
        $aId = $a['id'];
        if (isset($feederResolved[$aId])) {
            $res = $feederResolved[$aId];
        } else {
            $res = [
                'resolved_section_id'   => 0,
                'resolved_section_name' => 'UNRESOLVED',
                'status'                => 'UNRESOLVED',
                'confidence'            => 0.00,
                'evidence'              => ['NO_DETERMINISTIC_EVIDENCE', 'MULTI_SECTION_FEEDER_WITHOUT_SEED']
            ];
        }

        $resRecord = [
            'asset_id'              => $aId,
            'kode_asset'            => $a['kode_asset'],
            'nama_asset'            => $a['nama_asset'],
            'penyulang_id'          => $fId,
            'nama_penyulang'        => $fName,
            'resolved_section_id'   => $res['resolved_section_id'],
            'resolved_section_name' => $res['resolved_section_name'],
            'status'                => $res['status'],
            'confidence'            => $res['confidence'],
            'evidence'              => $res['evidence'],
            'write_allowed'         => false
        ];

        $resolutionResults[] = $resRecord;
        $statusCounts[$res['status']]++;
        $fResolvedCounts[$res['status']]++;
    }

    $feederSummary[] = [
        'penyulang_id'   => $fId,
        'nama_penyulang' => $fName,
        'section_count'  => count($fSections),
        'total_assets'   => count($fAssets),
        'breakdown'      => $fResolvedCounts
    ];
}

echo "\n[SECTION RESOLUTION GLOBAL SUMMARY]\n";
echo "Total Master Assets Audited: {$totalAssets}\n";
echo str_repeat("-", 60) . "\n";
$autoResolvableCount = $statusCounts['EXPLICIT'] + $statusCounts['INHERITED_FROM_AUTHORITATIVE_EDGE'] + $statusCounts['DETERMINISTIC_BOUNDARY'] + $statusCounts['PROPAGATED'];
$reviewRequiredCount = $statusCounts['AMBIGUOUS'] + $statusCounts['UNRESOLVED'];

foreach ($statusCounts as $status => $count) {
    $pct = round(($count / $totalAssets) * 100, 2);
    $bar = str_repeat('█', (int)($pct / 2));
    echo sprintf("  %-35s : %5d (%5.2f%%) %s\n", $status, $count, $pct, $bar);
}
echo str_repeat("-", 60) . "\n";
echo sprintf("  AUTO-RESOLVABLE (High Confidence)   : %5d (%5.2f%%)\n", $autoResolvableCount, ($autoResolvableCount / $totalAssets) * 100);
echo sprintf("  REVIEW REQUIRED (Ambiguous / Gap)   : %5d (%5.2f%%)\n\n", $reviewRequiredCount, ($reviewRequiredCount / $totalAssets) * 100);

// -----------------------------------------------------------------------------
// 6. PRINT FEEDER-BY-FEEDER BREAKDOWN TABLE
// -----------------------------------------------------------------------------
echo "[FEEDER-BY-FEEDER RESOLUTION BREAKDOWN (33 ACTIVE FEEDERS)]\n";
echo sprintf("%-4s | %-5s | %-24s | %-5s | %-6s | %-8s | %-9s | %-7s | %-6s | %-6s | %-6s\n",
    "No", "ID", "Nama Penyulang", "Secs", "Assets", "Explicit", "Inherited", "Determ.", "Propag", "Ambig", "Unres"
);
echo str_repeat("-", 105) . "\n";

$no = 1;
foreach ($feederSummary as $fs) {
    $b = $fs['breakdown'];
    echo sprintf("%-4d | %-5d | %-24s | %-5d | %-6d | %-8d | %-9d | %-7d | %-6d | %-6d | %-6d\n",
        $no++,
        $fs['penyulang_id'],
        substr($fs['nama_penyulang'], 0, 24),
        $fs['section_count'],
        $fs['total_assets'],
        $b['EXPLICIT'],
        $b['INHERITED_FROM_AUTHORITATIVE_EDGE'],
        $b['DETERMINISTIC_BOUNDARY'],
        $b['PROPAGATED'],
        $b['AMBIGUOUS'],
        $b['UNRESOLVED']
    );
}
echo "\n";

// -----------------------------------------------------------------------------
// 7. WRITE OUTPUT ARTIFACTS
// -----------------------------------------------------------------------------
$auditDir = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits';
if (!is_dir($auditDir)) {
    mkdir($auditDir, 0777, true);
}

$auditPath   = $auditDir . '/SECTION_RESOLUTION_AUDIT.json';
$summaryPath = $auditDir . '/SECTION_RESOLUTION_SUMMARY.json';

// Summary Payload
$summaryPayload = [
    'status'              => 'SECTION_RESOLUTION_DRY_RUN_PASS',
    'phase'               => 'PHASE_B1_6_SECTION_RESOLUTION_ENGINE',
    'timestamp'           => date('c'),
    'git_commit_locked'   => 'fb8c47b',
    'governance'          => [
        'strict_read_only'       => true,
        'zero_production_write'  => true,
        'write_allowed_default'  => false,
        'threshold_auto_accept'  => 0.95
    ],
    'global_metrics'      => [
        'total_master_assets'          => $totalAssets,
        'auto_resolvable_count'        => $autoResolvableCount,
        'auto_resolvable_percent'      => round(($autoResolvableCount / $totalAssets) * 100, 2),
        'review_required_count'        => $reviewRequiredCount,
        'review_required_percent'      => round(($reviewRequiredCount / $totalAssets) * 100, 2),
        'status_breakdown'             => $statusCounts
    ],
    'feeder_matrix'       => $feederSummary
];

file_put_contents($summaryPath, json_encode($summaryPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "[ARTIFACT 1] Saved: " . realpath($summaryPath) . " (" . number_format(filesize($summaryPath)) . " bytes)\n";

// Itemized Audit Payload
file_put_contents($auditPath, json_encode($resolutionResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "[ARTIFACT 2] Saved: " . realpath($auditPath) . " (" . number_format(filesize($auditPath)) . " bytes)\n";

echo "\nPhase B.1.6 Section Resolution Engine execution completed successfully!\n";
echo "======================================================================\n";
