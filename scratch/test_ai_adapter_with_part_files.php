<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\AiDataAdapterService;

$adapter = new AiDataAdapterService();

$filesToTest = [
    'writable/Template_Import_JTM_SIWALAN_PANJI.csv',
    'writable/Template_Import_MULTI_PENYULANG_PART1.csv',
    'C:\\Users\\INSPEKSIKOTA\\Downloads\\asset-statistics-SIDOARJO-jtm_tiang.csv'
];

echo "========================================\n";
echo "AI DATA ADAPTER FORENSIC TEST ON PART FILES\n";
echo "========================================\n\n";

foreach ($filesToTest as $filePath) {
    echo "Testing File: {$filePath}\n";
    if (!file_exists($filePath)) {
        echo "  - File not found!\n\n";
        continue;
    }

    try {
        $result = $adapter->parseAndTransformToCanonical($filePath);
        echo "  - Source File: " . $result['source_file'] . "\n";
        echo "  - Source Rows: " . $result['source_rows'] . "\n";
        echo "  - Mapping Evidence Count: " . count($result['mapping_evidence']) . "\n";
        foreach ($result['mapping_evidence'] as $ev) {
            echo "    * Col [{$ev['column_index']}] '" . trim($ev['source_column']) . "' -> " . ($ev['canonical_field'] ?? 'UNMAPPED') . " (conf: {$ev['confidence']})\n";
        }
        if (!empty($result['canonical_rows'])) {
            echo "  - Sample Row 1:\n";
            print_r($result['canonical_rows'][0]);
        }
    } catch (\Throwable $e) {
        echo "  - EXCEPTION: " . $e->getMessage() . "\n";
        echo "  - STACK TRACE:\n" . $e->getTraceAsString() . "\n";
    }
    echo "\n----------------------------------------\n";
}
