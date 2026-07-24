<?php

namespace App\Services;

/**
 * Cloudinary Upload Service (Tanpa SDK - menggunakan HTTP API langsung)
 * Upload foto ke Cloudinary menggunakan Base64 Data URI - tidak butuh filesystem permission
 */
class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private bool $enabled;

    public function __construct()
    {
        $this->cloudName = trim(getenv('CLOUDINARY_CLOUD_NAME') ?: '');
        $this->apiKey    = trim(getenv('CLOUDINARY_API_KEY') ?: '');
        $this->apiSecret = trim(getenv('CLOUDINARY_API_SECRET') ?: '');
        $this->enabled   = !empty($this->cloudName) && !empty($this->apiKey) && !empty($this->apiSecret);

        log_message('info', '[Cloudinary] enabled=' . ($this->enabled ? 'YES' : 'NO')
            . ' cloud=' . $this->cloudName);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Upload file ke Cloudinary menggunakan Base64 Data URI
     * Tidak memerlukan CURLFile - 100% aman di Railway/server manapun
     *
     * @param string $filePath   Path ke file yang akan diupload
     * @param string $folder     Folder Cloudinary tujuan
     * @return array ['success' => bool, 'url' => string, 'error' => string]
     */
    public function upload(string $filePath, string $folder = 'sidak-tejo/temuan'): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Cloudinary tidak dikonfigurasi'];
        }

        // Baca file sebagai binary
        $fileData = @file_get_contents($filePath);
        if ($fileData === false || strlen($fileData) === 0) {
            return ['success' => false, 'error' => 'Tidak bisa membaca file: ' . $filePath];
        }

        // Deteksi MIME type
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileData) ?: 'image/jpeg';

        // Konversi ke Base64 Data URI - tidak perlu CURLFile/filesystem permission
        $dataUri = "data:{$mimeType};base64," . base64_encode($fileData);
        unset($fileData); // bebaskan memory

        // Build Cloudinary signature (params sorted alphabetically)
        $timestamp    = time();
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature    = hash('sha256', $paramsToSign . $this->apiSecret);

        $apiUrl = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";

        log_message('info', "[Cloudinary] Uploading via base64 to {$apiUrl} | folder={$folder} | ts={$timestamp}");

        $postData = http_build_query([
            'file'      => $dataUri,
            'api_key'   => $this->apiKey,
            'timestamp' => (string)$timestamp,
            'signature' => $signature,
            'folder'    => $folder,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        log_message('info', "[Cloudinary] HTTP={$httpCode} | Response: " . substr((string)$response, 0, 400));

        if ($curlError) {
            log_message('error', '[Cloudinary] cURL error: ' . $curlError);
            return ['success' => false, 'error' => 'cURL error: ' . $curlError];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['secure_url'])) {
            log_message('info', '[Cloudinary] ✅ Upload SUCCESS: ' . $data['secure_url']);
            return [
                'success'   => true,
                'url'       => $data['secure_url'],
                'public_id' => $data['public_id'] ?? '',
            ];
        }

        $errorMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode . ' - ' . substr((string)$response, 0, 200));
        log_message('error', '[Cloudinary] ❌ Upload FAILED: ' . $errorMsg);
        return ['success' => false, 'error' => $errorMsg];
    }
}
