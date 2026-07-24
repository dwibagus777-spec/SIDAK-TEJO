<?php
// Live upload tester inside Railway environment
// Access: /test-app-upload.php

header('Content-Type: text/html; charset=utf-8');

$cloudName = trim(env('CLOUDINARY_CLOUD_NAME') ?: getenv('CLOUDINARY_CLOUD_NAME') ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? ''));
$apiKey    = trim(env('CLOUDINARY_API_KEY') ?: getenv('CLOUDINARY_API_KEY') ?: ($_ENV['CLOUDINARY_API_KEY'] ?? ''));
$apiSecret = trim(env('CLOUDINARY_API_SECRET') ?: getenv('CLOUDINARY_API_SECRET') ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? ''));

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$result = null;

if ($isPost && !empty($_FILES['foto']['tmp_name'])) {
    $tmpFile = $_FILES['foto']['tmp_name'];
    $fileName = $_FILES['foto']['name'];
    $fileSize = $_FILES['foto']['size'];

    $timestamp    = time();
    $folder       = 'sidak-tejo/temuan';
    $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
    $signature    = hash('sha256', $paramsToSign . $apiSecret);

    $apiUrl = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

    $mimeType = $_FILES['foto']['type'] ?: 'image/jpeg';

    $postFields = [
        'file'      => new \CURLFile($tmpFile, $mimeType, $fileName),
        'api_key'   => $apiKey,
        'timestamp' => (string)$timestamp,
        'signature' => $signature,
        'folder'    => $folder,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result = [
        'file_name' => $fileName,
        'file_size' => $fileSize,
        'http_code' => $httpCode,
        'curl_err'  => $curlError,
        'response'  => json_decode($response, true) ?: $response,
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Real File Upload</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; line-height: 1.5; }
        .box { background: #f8fafc; border: 1px solid #cbd5e1; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #dcfce7; border-color: #86efac; color: #166534; }
        .error { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
        button { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 16px; cursor: pointer; }
    </style>
</head>
<body>
    <h2>🧪 Test Upload Foto Real ke Cloudinary</h2>

    <div class="box">
        <strong>Status Konfigurasi Cloudinary:</strong><br>
        Cloud Name: <code><?= htmlspecialchars($cloudName ?: 'TIDAK KETEMU ❌') ?></code><br>
        API Key: <code><?= htmlspecialchars($apiKey ? substr($apiKey, 0, 6) . '...' : 'TIDAK KETEMU ❌') ?></code><br>
        API Secret: <code><?= $apiSecret ? 'ADA ✅' : 'TIDAK KETEMU ❌' ?></code>
    </div>

    <?php if ($result): ?>
        <?php if ($result['http_code'] === 200 && isset($result['response']['secure_url'])): ?>
            <div class="box success">
                <h3>✅ UPLOAD BERHASIL KE CLOUDINARY!</h3>
                <p><strong>URL Foto:</strong> <a href="<?= htmlspecialchars($result['response']['secure_url']) ?>" target="_blank"><?= htmlspecialchars($result['response']['secure_url']) ?></a></p>
                <img src="<?= htmlspecialchars($result['response']['secure_url']) ?>" style="max-width: 100%; border-radius: 8px; margin-top: 10px;">
            </div>
        <?php else: ?>
            <div class="box error">
                <h3>❌ UPLOAD GAGAL!</h3>
                <p>HTTP Code: <?= $result['http_code'] ?></p>
                <p>Error: <?= htmlspecialchars($result['curl_err'] ?: json_encode($result['response'])) ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="box">
        <label style="display:block; margin-bottom: 10px; font-weight: bold;">Pilih Foto dari HP/Komputer:</label>
        <input type="file" name="foto" accept="image/*" required style="margin-bottom: 15px; display: block;"><br>
        <button type="submit">Upload Test Foto</button>
    </form>
</body>
</html>
