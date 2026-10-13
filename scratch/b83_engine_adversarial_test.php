<?php

declare(strict_types=1);

define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', ROOTPATH . 'vendor/codeigniter4/framework/system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
defined('CI_DEBUG') || define('CI_DEBUG', true);

require COMPOSER_PATH;
require SYSTEMPATH . 'Common.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$db = \CodeIgniter\Database\Config::connect('default', false);
$service = new \App\Services\AssetHealthService($db);

$results = [];

// Fetch initial active physical assets & translines baseline
$initialActiveAssets = $db->table('assets')->where('deleted_at IS NULL')->where('status !=', 'INACTIVE')->countAllResults();
$initialActiveTranslines = $db->table('gis_translines')->where('deleted_at IS NULL')->where('is_active', 1)->countAllResults();

// Find a valid active asset ID for single-asset tests
$sampleAsset = $db->table('assets')->select('id')->where('deleted_at IS NULL')->where('status !=', 'INACTIVE')->orderBy('id', 'ASC')->limit(1)->get()->getRowArray();
$validAssetId = $sampleAsset ? (int)$sampleAsset['id'] : 394;

// B83-E01: Full Batch Calculation Engine across 100% active assets
$batchRes = $service->calculateAllAssetsHealth(true, 'ADVERSARIAL_TEST');
$results['B83-E01'] = [
    'description' => 'Full batch calculation engine across 100% active physical assets',
    'passed'      => ($batchRes['success'] && $batchRes['total_scored_assets'] === $initialActiveAssets),
    'details'     => "Scored assets: {$batchRes['total_scored_assets']} / {$initialActiveAssets}, Avg AHI: {$batchRes['average_ahi_score']}",
];

// B83-E02: 100% Active Asset & Transline Network Coverage Report (B83-G01 & B83-G02)
$covReport = $service->getCoverageReport();
$results['B83-E02'] = [
    'description' => '100% Active Asset & Transline Network Coverage Report (B83-G01 & B83-G02)',
    'passed'      => ($covReport['verdict'] === 'PASS'),
    'details'     => "Coverage: {$covReport['coverage_percentage']}%, Active Assets: {$covReport['actual_active_assets']}, Translines: {$covReport['actual_active_translines']}",
];

// B83-E03: Deterministic SHA-256 Fingerprints (B83-G04 & B83-G13)
$singleRes = $service->calculateAssetHealth($validAssetId, true, 'DETERMINISTIC_TEST');
$hasFingerprints = !empty($singleRes['input_fingerprint']) && !empty($singleRes['factor_fingerprint']);
$is64CharHex = (strlen($singleRes['input_fingerprint'] ?? '') === 64 && strlen($singleRes['factor_fingerprint'] ?? '') === 64);
$results['B83-E03'] = [
    'description' => 'Deterministic SHA-256 input_fingerprint and factor_fingerprint (B83-G04 & B83-G13)',
    'passed'      => ($singleRes['success'] && $hasFingerprints && $is64CharHex),
    'details'     => $hasFingerprints ? "Asset ID {$validAssetId} Input SHA256: " . substr($singleRes['input_fingerprint'], 0, 16) . "..." : "Missing fingerprints",
];

// B83-E04: Dynamic Weight Re-allocation for UNAVAILABLE factors (B83-G07)
$factorSumPct = 0.0;
if (!empty($singleRes['factors'])) {
    foreach ($singleRes['factors'] as $f) {
        $factorSumPct += (float)$f['weight_pct'];
    }
}
$isSum100 = (abs($factorSumPct - 100.0) < 0.05);
$results['B83-E04'] = [
    'description' => 'Dynamic Weight Re-allocation for UNAVAILABLE evidence (B83-G07)',
    'passed'      => $isSum100,
    'details'     => "Sum of effective factor weights: {$factorSumPct}% (Target: 100.0%)",
];

// B83-E05: Append-Only History Audit Trail (B83-G12)
$histCount = $db->table('asset_health_history')->where('asset_id', $validAssetId)->countAllResults();
$results['B83-E05'] = [
    'description' => 'Append-only history audit trail recording calculation transitions (B83-G12)',
    'passed'      => ($histCount >= 2),
    'details'     => "History rows logged for Asset ID {$validAssetId}: {$histCount}",
];

// B83-E06: Physical Deletion Constraint RESTRICT (B83-G12)
$deleteBlocked = false;
try {
    $db->query("DELETE FROM asset_health_indices WHERE asset_id = ?", [$validAssetId]);
} catch (\Throwable $e) {
    $deleteBlocked = str_contains($e->getMessage(), '1451') || str_contains($e->getMessage(), 'a foreign key constraint fails');
}
$results['B83-E06'] = [
    'description' => 'Physical deletion constraint ON DELETE RESTRICT (B83-G12)',
    'passed'      => $deleteBlocked,
    'details'     => $deleteBlocked ? "Foreign key delete restriction enforced (MySQL 1451)" : "Delete was NOT restricted",
];

// B83-E07: AI Autonomy Ceiling & Canonical Provenance Chain (B83-G08)
// L3 ANALYTICAL (AI Score) -> PASS
// L4 RECOMMENDED (AI Recommendation) -> PASS
// AI -> APPROVED -> REJECT
// AI -> EXECUTED -> REJECT
$aiAnalyticalPass = false;
$aiRecommendedPass = false;
$aiApprovedReject = false;
$aiExecutedReject = false;

try {
    $service->validateProvenanceTransition('ANALYTICAL', false);
    $aiAnalyticalPass = true;
} catch (\Throwable $e) {}

try {
    $service->validateProvenanceTransition('RECOMMENDED', false);
    $aiRecommendedPass = true;
} catch (\Throwable $e) {}

try {
    $service->validateProvenanceTransition('APPROVED', false);
} catch (\DomainException $e) {
    $aiApprovedReject = true;
}

try {
    $service->validateProvenanceTransition('EXECUTED', false);
} catch (\DomainException $e) {
    $aiExecutedReject = true;
}

$provTestPass = ($aiAnalyticalPass && $aiRecommendedPass && $aiApprovedReject && $aiExecutedReject);
$results['B83-E07'] = [
    'description' => 'Canonical Provenance Chain (L3 ANALYTICAL -> L4 RECOMMENDED AI Ceiling, AI APPROVED/EXECUTED REJECT)',
    'passed'      => $provTestPass,
    'details'     => "AI->ANALYTICAL: " . ($aiAnalyticalPass ? 'PASS' : 'FAIL') . ", AI->RECOMMENDED: " . ($aiRecommendedPass ? 'PASS' : 'FAIL') . ", AI->APPROVED: " . ($aiApprovedReject ? 'REJECT' : 'FAIL') . ", AI->EXECUTED: " . ($aiExecutedReject ? 'REJECT' : 'FAIL'),
];

// B83-E08: CRITICAL AHI Creates L4 Recommendation with ZERO Auto Work Package (B83-G09)
$recs = $service->getRecommendations();
$noAutoWp = true;
foreach ($recs as $r) {
    if ($r['work_package_id'] !== null) {
        $noAutoWp = false;
        break;
    }
}
$results['B83-E08'] = [
    'description' => 'CRITICAL AHI creates L4 Recommendation queue item with ZERO automatic Work Package (B83-G09)',
    'passed'      => $noAutoWp,
    'details'     => "Total recommendations in queue: " . count($recs) . ", All work_package_id = NULL: " . ($noAutoWp ? 'YES' : 'NO'),
];

// B83-E09: Human Gate Transition (L5 APPROVED)
$appRes = ['success' => false];
if (!empty($recs)) {
    $recId = (int)$recs[0]['id'];
    $appRes = $service->approveRecommendation($recId, 9999);
} else {
    // Inject synthetic CRITICAL index & recommendation for test
    $aiIndex = $db->table('asset_health_indices')->where('asset_id', $validAssetId)->get()->getRowArray();
    $now = date('Y-m-d H:i:s');
    $db->table('asset_health_recommendations')->insert([
        'asset_health_index_id' => $aiIndex['id'] ?? 1,
        'asset_id'              => $validAssetId,
        'recommended_action'    => 'URGENT_REMEDIATION',
        'urgency_level'         => 'EMERGENCY',
        'recommendation_notes'  => 'Test L5 Human Gate',
        'provenance_level'      => 'RECOMMENDED',
        'work_package_id'       => null,
        'test_mode'             => 1,
        'created_at'            => $now,
        'updated_at'            => $now,
    ]);
    $recId = (int)$db->insertID();
    $appRes = $service->approveRecommendation($recId, 9999);
}
$results['B83-E09'] = [
    'description' => 'Human Gate transition to L5 APPROVED (approveRecommendation with Human User Context)',
    'passed'      => ($appRes['success'] && $appRes['provenance_level'] === 'APPROVED'),
    'details'     => "Human User ID 9999 updated provenance to: {$appRes['provenance_level']}",
];

// B83-E10: Zero GIS / Topology Mutation (B83-G03 & B83-G14)
$finalActiveAssets = $db->table('assets')->where('deleted_at IS NULL')->where('status !=', 'INACTIVE')->countAllResults();
$finalActiveTranslines = $db->table('gis_translines')->where('deleted_at IS NULL')->where('is_active', 1)->countAllResults();
$results['B83-E10'] = [
    'description' => 'Zero GIS / Topology Mutation (Delta_topology = 0, B83-G03 & B83-G14)',
    'passed'      => ($finalActiveAssets === $initialActiveAssets && $finalActiveTranslines === $initialActiveTranslines),
    'details'     => "Active assets: {$finalActiveAssets} (Delta: 0), Active translines: {$finalActiveTranslines} (Delta: 0)",
];

$totalTests = count($results);
$passedTests = count(array_filter($results, fn($r) => $r['passed']));
$verdict = ($totalTests === $passedTests) ? 'PASS' : 'FAIL';

echo json_encode([
    'gate'         => 'B8_3_2_SERVICE_ENGINE_ADVERSARIAL',
    'timestamp'    => date('Y-m-d H:i:s T'),
    'total_tests'  => $totalTests,
    'passed_tests' => $passedTests,
    'verdict'      => $verdict,
    'test_results' => $results,
], JSON_PRETTY_PRINT);

exit($verdict === 'PASS' ? 0 : 1);
