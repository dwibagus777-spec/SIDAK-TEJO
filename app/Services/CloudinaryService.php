<?php

namespace App\Services;

/**
 * Cloudinary Upload Service (CodeIgniter 4 Specialist Implementation)
 * Menggunakan Direct REST API v1_1 dengan CURLFile (multipart/form-data)
 */
class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private bool $enabled;

    public function __construct()
    {
        // Check berlapis: env() CI4 -> getenv() OS -> $_ENV superglobal -> $_SERVER superglobal
        $this->cloudName = trim(
            (function_exists('env') ? env('CLOUDINARY_CLOUD_NAME') : null)
            ?: getenv('CLOUDINARY_CLOUD_NAME')
            ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? '')
            ?: ($_SERVER['CLOUDINARY_CLOUD_NAME'] ?? '')
        );

        $this->apiKey = trim(
            (function_exists('env') ? env('CLOUDINARY_API_KEY') : null)
            ?: getenv('CLOUDINARY_API_KEY')
            ?: ($_ENV['CLOUDINARY_API_KEY'] ?? '')
            ?: ($_SERVER['CLOUDINARY_API_KEY'] ?? '')
        );

        $this->apiSecret = trim(
            (function_exists('env') ? env('CLOUDINARY_API_SECRET') : null)
            ?: getenv('CLOUDINARY_API_SECRET')
            ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? '')
            ?: ($_SERVER['CLOUDINARY_API_SECRET'] ?? '')
        );

        $this->enabled = !empty($this->cloudName) && !empty($this->apiKey) && !empty($this->apiSecret);

        log_message('info', sprintf(
            '[CLOUDINARY_INIT] Status: %s | CloudName: %s | APIKey: %s',
            $this->enabled ? 'ENABLED ✅' : 'DISABLED ❌',
            $this->cloudName ?: 'NOT_SET',
            $this->apiKey ? substr($this->apiKey, 0, 6) . '...' : 'NOT_SET'
        ));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Upload file disk ke Cloudinary
     *
     * @param string $filePath Path fisik file di server
     * @param string $folder Folder Cloudinary
     * @return array ['success' => bool, 'url' => string, 'public_id' => string, 'error' => string, 'http_code' => int, 'raw_response' => string]
     */
    public function upload(string $filePath, string $folder = 'sidak-tejo/temuan'): array
    {
        if (!$this->enabled) {
            $err = 'Cloudinary tidak dikonfigurasi. Variabel CLOUDINARY_CLOUD_NAME, API_KEY, atau API_SECRET kosong.';
            log_message('error', '[CLOUDINARY_UPLOAD_FAIL] ' . $err);
            return ['success' => false, 'error' => $err, 'http_code' => 0, 'raw_response' => ''];
        }

        if (empty($filePath) || !file_exists($filePath)) {
            $err = 'File tidak ditemukan di path: ' . $filePath;
            log_message('error', '[CLOUDINARY_UPLOAD_FAIL] ' . $err);
            return ['success' => false, 'error' => $err, 'http_code' => 0, 'raw_response' => ''];
        }

        $fileSize = filesize($filePath);
        $mimeType = 'image/jpeg';
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath) ?: 'image/jpeg';
        }

        log_message('info', sprintf(
            '[CLOUDINARY_UPLOAD_START] File: %s | Size: %d bytes | MIME: %s | Target Folder: %s',
            basename($filePath),
            $fileSize,
            $mimeType,
            $folder
        ));

        try {
            $timestamp    = time();
            $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
            $signature    = hash('sha256', $paramsToSign . $this->apiSecret);

            $apiUrl = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";

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
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            log_message('info', sprintf(
                '[CLOUDINARY_UPLOAD_RESPONSE] HTTP: %d | cURL Error: %s | Raw Body: %s',
                $httpCode,
                $curlError ?: 'NONE',
                substr((string)$response, 0, 500)
            ));

            if ($curlError) {
                $err = 'cURL Network Error: ' . $curlError;
                log_message('error', '[CLOUDINARY_UPLOAD_FAIL] ' . $err);
                return ['success' => false, 'error' => $err, 'http_code' => $httpCode, 'raw_response' => (string)$response];
            }

            $data = json_decode((string)$response, true);

            if ($httpCode === 200 && isset($data['secure_url'])) {
                log_message('info', '[CLOUDINARY_UPLOAD_SUCCESS] URL: ' . $data['secure_url']);
                return [
                    'success'      => true,
                    'url'          => $data['secure_url'],
                    'public_id'    => $data['public_id'] ?? '',
                    'http_code'    => 200,
                    'raw_response' => (string)$response,
                ];
            }

            $errorMsg = $data['error']['message'] ?? ('HTTP Status ' . $httpCode . ' - ' . substr((string)$response, 0, 300));
            log_message('error', '[CLOUDINARY_UPLOAD_ERROR_RESPONSE] ' . $errorMsg);
            return [
                'success'      => false,
                'error'        => $errorMsg,
                'http_code'    => $httpCode,
                'raw_response' => (string)$response,
            ];

        } catch (\Throwable $e) {
            $err = 'Exception during Cloudinary upload: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            log_message('critical', '[CLOUDINARY_UPLOAD_EXCEPTION] ' . $err . "\nStack trace:\n" . $e->getTraceAsString());
            return [
                'success'      => false,
                'error'        => $err,
                'http_code'    => 500,
                'raw_response' => $e->getMessage(),
            ];
        }
    }
}
