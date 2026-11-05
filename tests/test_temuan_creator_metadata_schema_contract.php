<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migrationPath = $root . '/app/Database/Migrations/2026-11-05-000001_AddTemuanCreatorMetadata.php';
$runnerPath = $root . '/app/Controllers/MigrateController.php';
$migration = is_file($migrationPath) ? file_get_contents($migrationPath) : '';
$runner = file_get_contents($runnerPath);

$checks = [
    'Temuan creator metadata migration is present' => $migration !== '',
    'Migration adds every insert metadata column idempotently' => preg_match_all(
        "/'((?:created_by_name|created_by_nip|updated_by_name|updated_by_nip))'\\s*=>/",
        $migration
    ) === 4 && substr_count($migration, 'fieldExists($field, \'temuan\')') === 1,
    'Protected migration runner invokes the creator metadata migration' => strpos(
        $runner,
        'AddTemuanCreatorMetadata'
    ) !== false,
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}

printf("SUMMARY: %d checks, %d failed\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
