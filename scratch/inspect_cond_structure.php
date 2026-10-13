<?php
$data = json_decode(file_get_contents(__DIR__ . '/post_commit_forensic_results.json'), true);
$cond118 = $data['checks']['conductor_analytics_118']['data'];
$condGlobal = $data['checks']['conductor_analytics_global']['data'];

echo "Keys in cond118: " . implode(', ', array_keys($cond118)) . "\n";
echo "cond118 summary or metrics:\n";
if (isset($cond118['data'])) {
    echo "Keys in cond118['data']: " . implode(', ', array_keys($cond118['data'])) . "\n";
    if (isset($cond118['data']['kpis'])) {
        print_r($cond118['data']['kpis']);
    }
}

echo "\nKeys in condGlobal: " . implode(', ', array_keys($condGlobal)) . "\n";
if (isset($condGlobal['data'])) {
    echo "Keys in condGlobal['data']: " . implode(', ', array_keys($condGlobal['data'])) . "\n";
    if (isset($condGlobal['data']['kpis'])) {
        print_r($condGlobal['data']['kpis']);
    }
}
