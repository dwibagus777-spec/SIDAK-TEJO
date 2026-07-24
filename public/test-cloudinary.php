<?php
// Test Cloudinary upload directly
// Access: /test-cloudinary.php

header('Content-Type: text/plain; charset=utf-8');

$cloudName = getenv('CLOUDINARY_CLOUD_NAME') ?: '';
$apiKey    = getenv('CLOUDINARY_API_KEY') ?: '';
$apiSecret = getenv('CLOUDINARY_API_SECRET') ?: '';

echo "=== CLOUDINARY UPLOAD TEST ===\n";
echo "Cloud: $cloudName | Key: " . substr($apiKey, 0, 6) . "...\n\n";

if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
    echo "❌ ENV VARS NOT SET!\n";
    exit;
}

// Create tiny 1x1 red pixel PNG as test image (no disk needed)
$img = imagecreatetruecolor(100, 100);
$red = imagecolorallocate($img, 220, 50, 50);
imagefill($img, 0, 0, $red);
$tmpFile = sys_get_temp_dir() . '/cloudinary_test_' . time() . '.jpg';
imagejpeg($img, $tmpFile, 80);
imagedestroy($img);

echo "Test file created: $tmpFile (" . filesize($tmpFile) . " bytes)\n\n";

// Build signature
$timestamp = time();
$folder = 'sidak-tejo/test';
// Params MUST be sorted alphabetically
$paramsToSign = "folder={$folder}&timestamp={$timestamp}";
$signature = hash('sha256', $paramsToSign . $apiSecret);

echo "Params to sign: $paramsToSign\n";
echo "Signature: " . substr($signature, 0, 20) . "...\n\n";

$apiUrl = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

$postFields = [
    'file'      => new CURLFile($tmpFile, 'image/jpeg', 'test.jpg'),
    'api_key'   => $apiKey,
    'timestamp' => (string)$timestamp,
    'signature' => $signature,
    'folder'    => $folder,
];

echo "Uploading to: $apiUrl\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_VERBOSE        => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

@unlink($tmpFile);

echo "HTTP Code: $httpCode\n";
if ($curlError) {
    echo "cURL Error: $curlError\n";
} else {
    $data = json_decode($response, true);
    if ($httpCode === 200 && isset($data['secure_url'])) {
        echo "✅ UPLOAD SUCCESS!\n";
        echo "URL: " . $data['secure_url'] . "\n";
        echo "Public ID: " . ($data['public_id'] ?? '') . "\n";
    } else {
        echo "❌ UPLOAD FAILED!\n";
        echo "Response: " . substr($response, 0, 500) . "\n";
    }
}
