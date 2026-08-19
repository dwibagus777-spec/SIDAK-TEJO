<?php

$dir = __DIR__ . '/public/plugins';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$stepPath = 'C:/Users/INSPEKSIKOTA/.gemini/antigravity/brain/62aaadff-0375-490e-8c91-4552157a26aa/.system_generated/steps/4927/content.md';
$content = file_get_contents($stepPath);

$pos = strpos($content, 'var __Html5QrcodeLibrary__');
if ($pos !== false) {
    $js = substr($content, $pos);
    file_put_contents($dir . '/html5-qrcode.min.js', $js);
    echo "Saved " . strlen($js) . " bytes to " . realpath($dir . '/html5-qrcode.min.js') . "\n";
} else {
    echo "Marker not found!\n";
}
