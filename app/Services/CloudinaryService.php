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
        // Railway uses actual env vars (not .env file), so getenv() is correct
        $this->cloudName = trim(getenv('CLOUDINARY_CLOUD_NAME') ?: '');
        $this->apiKey    = trim(getenv('CLOUDINARY_API_KEY') ?: '');
        $this->apiSecret = trim(getenv('CLOUDINARY_API_SECRET') ?: '');
        $this->enabled   = !empty($this->cloudName) && !empty($this->apiKey) && !empty($this->apiSecret);

        log_message('info', '[CloudinaryService] enabled=' . ($this->enabled ? 'YES' : 'NO') 
            . ' cloud=' . $this->cloudName 
            . ' key=' . substr($this->apiKey, 0, 6) . '...');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Upload file ke Cloudinary via signed upload API
     * Signature harus dibuat dengan params SORTED alphabetically
     */
    public function upload(string $filePath, string $folder = 'sidak-tejo/temuan'): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Cloudinary tidak dikonfigurasi'];
        }

        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File tidak ditemukan: ' . $filePath];
        }

        $timestamp = time();

        // CRITICAL: Params must be sorted alphabetically for correct signature
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature = hash('sha256', $paramsToSign . $this->apiSecret);

        $apiUrl = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";

        // Use CURLFile to post actual binary file
        $postFields = [
            'file'      => new \CURLFile($filePath),
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
            CURLOPT_SSL_VERIFYPEER => false, // Required on some Railway environments
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        log_message('info', "[CloudinaryService] HTTP={$httpCode} | Response: " . substr($response, 0, 300));

        if ($curlError) {
            log_message('error', '[CloudinaryService] cURL error: ' . $curlError);
            return ['success' => false, 'error' => 'cURL error: ' . $curlError];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['secure_url'])) {
            log_message('info', '[CloudinaryService] Upload SUCCESS: ' . $data['secure_url']);
            return [
                'success'   => true,
                'url'       => $data['secure_url'],
                'public_id' => $data['public_id'] ?? '',
            ];
        }

        $errorMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode . ' - ' . substr($response, 0, 200));
        log_message('error', '[CloudinaryService] Upload FAILED: ' . $errorMsg);
        return ['success' => false, 'error' => $errorMsg];
    }
}
