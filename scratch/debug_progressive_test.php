<?php

require __DIR__ . '/../tests/unit/TranslineAiProgressiveCompletionTest.php';

$test = new \Tests\Unit\TranslineAiProgressiveCompletionTest();
$test->setUp();

$autoService = new \App\Services\TranslineAutoCompletionService();

for ($i = 1; $i <= 6; $i++) {
    $res = $autoService->executeBatch(15, ['batch_number' => $i]);
    echo "Iter {$i}: created={$res['created_count']}, stop=" . var_export($res['stop'] ?? null, true) . ", action={$res['action']}, remaining_auto={$res['remaining_auto_complete_count']}\n";
    if (!empty($res['stop']) || $res['created_count'] === 0) {
        echo "STOPPED at iter {$i}\n";
        break;
    }
}
