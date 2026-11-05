<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/Temuan.php');
$service = file_get_contents($root . '/app/Services/TemuanService.php');
$gisView = file_get_contents($root . '/app/Views/gis/index.php');

$checks = [];
$checks['GIS quick entry can save without an attached photo'] = preg_match(
    '/createTemuan\(\$data,\s*\$files,\s*\$photoRequired\s*\)/',
    $controller
) === 1 && preg_match(
    '/function createTemuan\(array \$data, \?array \$files = null, bool \$photoRequired = true\)/',
    $service
) === 1;

$checks['GIS coordinates are persisted from the quick form context'] = preg_match(
    '/[\'\"]latitude[\'\"]\s*=>[^,;]*context_lat/s',
    $controller
) === 1 && preg_match(
    '/[\'\"]longitude[\'\"]\s*=>[^,;]*context_lng/s',
    $controller
) === 1;

$validationStart = strpos($controller, 'if (!$this->validate($this->getTemuanFormRules()))');
$validationEnd = strpos($controller, '$materialInput =', $validationStart ?: 0);
$validationBlock = ($validationStart !== false && $validationEnd !== false)
    ? substr($controller, $validationStart, $validationEnd - $validationStart)
    : '';
$checks['AJAX validation failures return structured JSON instead of an HTML view'] = strpos(
    $validationBlock,
    'return $respondWithError('
) !== false && strpos(
    $controller,
    "'success' => false"
) !== false && strpos(
    $controller,
    'setJSON('
) !== false;

$submitStart = strpos($gisView, "document.getElementById('btn-gis-modal-submit').addEventListener('click'");
$submitEnd = strpos($gisView, '})();', $submitStart ?: 0);
$submitBlock = ($submitStart !== false && $submitEnd !== false)
    ? substr($gisView, $submitStart, $submitEnd - $submitStart)
    : '';
$checks['GIS quick form reads failure responses without assuming JSON'] = strpos(
    $submitBlock,
    'r.text()'
) !== false && strpos(
    $submitBlock,
    'data.message'
) !== false;

$checks['GIS quick form provides selectable section_id and auto-syncs context'] = strpos(
    $gisView,
    'id="gis-modal-section-select"'
) !== false && strpos(
    $gisView,
    'function populateGisModalSectionOptions(ctx)'
) !== false && strpos(
    $gisView,
    'function syncGisQuickModalFromContext(ctx)'
) !== false;

$checks['GIS quick form prevents duplicate submissions and refreshes CSRF token'] = strpos(
    $submitBlock,
    "btn.dataset.submitting === '1'"
) !== false && strpos(
    $submitBlock,
    "'X-CSRF-TOKEN': csrfVal"
) !== false;

$checks['Service validates UPLOAD_ERR_NO_FILE safely and handles DB exceptions cleanly'] = strpos(
    $service,
    'UPLOAD_ERR_NO_FILE'
) !== false && strpos(
    $service,
    'Gagal menyimpan temuan ke database.'
) !== false;

$checks['Controller enforces ULP scope on restricted roles and catches unexpected exceptions'] = strpos(
    $controller,
    'empty($userUlpId) || (int)$userUlpId !== $ulpIdInput'
) !== false && strpos(
    $controller,
    'COALESCE(NULLIF(a.ulp_id, 0), p.ulp_id) AS ulp_id'
) !== false;

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}

printf("SUMMARY: %d checks, %d failed\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
