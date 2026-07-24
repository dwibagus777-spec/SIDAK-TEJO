<?php

namespace App\Services;

/**
 * Cloudinary Upload Service (Tanpa SDK - menggunakan HTTP API langsung)
 * Upload foto ke Cloudinary sehingga foto tidak hilang saat Railway redeploy
 */
class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private bool $enabled;

    public function __construct()
    {
        $this->cloudName = getenv('CLOUDINARY_CLOUD_NAME') ?: '';
        $this->apiKey    = getenv('CLOUDINARY_API_KEY') ?: '';
        $this->apiSecret = getenv('CLOUDINARY_API_SECRET') ?: '';
        $this->enabled   = !empty($this->cloudName) && !empty($this->apiKey) && !empty($this->apiSecret);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Upload file ke Cloudinary
     * @param string $filePath Path disk file yang akan diupload
     * @param string $folder Folder di Cloudinary (e.g. "sidak-tejo/temuan")
     * @return array ['success' => bool, 'url' => string, 'public_id' => string, 'error' => string]
     */
    public function upload(string $filePath, string $folder = 'sidak-tejo/temuan'): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Cloudinary tidak dikonfigurasi'];
        }

        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File tidak ditemukan: ' . $filePath];
        }

        $timestamp  = time();
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature  = hash('sha256', $paramsToSign . $this->apiSecret);

        $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";

        $postFields = [
            'file'      => new \CURLFile($filePath),
            'api_key'   => $this->apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder'    => $folder,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'cURL error: ' . $error];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['secure_url'])) {
            return [
                'success'   => true,
                'url'       => $data['secure_url'],
                'public_id' => $data['public_id'] ?? '',
            ];
        }

        $errorMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode . ' - ' . $response);
        return ['success' => false, 'error' => $errorMsg];
    }
}
