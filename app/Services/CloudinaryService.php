<?php

namespace App\Services;

/**
 * Cloudinary Upload Service (Tanpa SDK - menggunakan HTTP API langsung dengan CURLFile)
 * Terbukti 100% sukses di test-cloudinary.php
 */
class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private bool $enabled;

    public function __construct()
    {
        $this->cloudName = trim(function_exists('env') ? (env('CLOUDINARY_CLOUD_NAME') ?: getenv('CLOUDINARY_CLOUD_NAME') ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? '')) : (getenv('CLOUDINARY_CLOUD_NAME') ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? '')));
        $this->apiKey    = trim(function_exists('env') ? (env('CLOUDINARY_API_KEY') ?: getenv('CLOUDINARY_API_KEY') ?: ($_ENV['CLOUDINARY_API_KEY'] ?? '')) : (getenv('CLOUDINARY_API_KEY') ?: ($_ENV['CLOUDINARY_API_KEY'] ?? '')));
        $this->apiSecret = trim(function_exists('env') ? (env('CLOUDINARY_API_SECRET') ?: getenv('CLOUDINARY_API_SECRET') ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? '')) : (getenv('CLOUDINARY_API_SECRET') ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? '')));
        $this->enabled   = !empty($this->cloudName) && !empty($this->apiKey) && !empty($this->apiSecret);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Upload file ke Cloudinary menggunakan CURLFile (multipart/form-data)
     *
     * @param string $filePath Path disk file (e.g. $_FILES['file']['tmp_name'])
     * @param string $folder   Folder Cloudinary (e.g. "sidak-tejo/temuan")
     * @return array ['success' => bool, 'url' => string, 'error' => string]
     */
    public function upload(string $filePath, string $folder = 'sidak-tejo/temuan'): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Cloudinary tidak dikonfigurasi'];
        }

        if (empty($filePath) || !file_exists($filePath)) {
            return ['success' => false, 'error' => 'File tidak ditemukan: ' . $filePath];
        }

        $timestamp    = time();
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature    = hash('sha256', $paramsToSign . $this->apiSecret);

        $apiUrl = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";

        // Deteksi mime type
        $mimeType = 'image/jpeg';
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath) ?: 'image/jpeg';
        }

        $postFields = [
            'file'      => new \CURLFile($filePath, $mimeType, basename($filePath)),
            'api_key'   => $this->apiKey,
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

        if ($curlError) {
            log_message('error', '[CloudinaryService] cURL error: ' . $curlError);
            return ['success' => false, 'error' => 'cURL error: ' . $curlError];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['secure_url'])) {
            log_message('info', '[CloudinaryService] ✅ Upload SUCCESS: ' . $data['secure_url']);
            return [
                'success'   => true,
                'url'       => $data['secure_url'],
                'public_id' => $data['public_id'] ?? '',
            ];
        }

        $errorMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode . ' - ' . substr((string)$response, 0, 200));
        log_message('error', '[CloudinaryService] ❌ Upload FAILED: ' . $errorMsg);
        return ['success' => false, 'error' => $errorMsg];
    }
}
