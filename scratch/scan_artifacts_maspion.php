<?php

$dirs = [
    'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits',
    'C:/Users/INSPEKSIKOTA/.gemini/antigravity/brain/e5e83a3d-4509-4271-8129-9355c979dd3c'
];

echo "=== SCANNING ALL ARTIFACTS FOR 'MASPION 1' OR MISLABELED PYL-016 ===\n";

foreach ($dirs as $dir) {
    $files = scandir($dir);
    foreach ($files as $f) {
        if (!str_starts_with($f, 'TL01_') && !str_starts_with($f, 'tl01_') && $f !== 'GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json') {
            continue;
        }
        $filePath = $dir . '/' . $f;
        $content = file_get_contents($filePath);

        $hasMaspion1 = (stripos($content, 'maspion 1') !== false);
        $hasMaspion2 = (stripos($content, 'maspion 2') !== false);
        $hasPyl016 = (stripos($content, 'pyl-016') !== false);

        echo "File: {$f}\n";
        echo "  - Maspion 1: " . ($hasMaspion1 ? "FOUND!" : "NO") . "\n";
        echo "  - Maspion 2: " . ($hasMaspion2 ? "FOUND!" : "NO") . "\n";
        echo "  - PYL-016:   " . ($hasPyl016 ? "FOUND" : "NO") . "\n";

        if ($hasPyl016) {
            // Find lines containing PYL-016
            $lines = explode("\n", $content);
            foreach ($lines as $idx => $l) {
                if (stripos($l, 'pyl-016') !== false) {
                    echo "    Line " . ($idx + 1) . ": " . trim($l) . "\n";
                }
            }
        }
    }
}
