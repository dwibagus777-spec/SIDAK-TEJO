<?php

namespace App\Services;

use App\Repositories\IntegrationRepository;

class IntegrationService
{
    private IntegrationRepository $repo;

    public function __construct()
    {
        $this->repo = new IntegrationRepository();
    }

    // ========================================================================
    // API KEY MANAGEMENT
    // ========================================================================

    public function generateApiKey(int $userId, array $permissions = ['*'], int $rateLimit = 1000): array
    {
        $key = 'stj_' . bin2hex(random_bytes(24));
        $secret = hash('sha256', $key . time());

        $db = \Config\Database::connect();
        $db->table('api_keys')->insert([
            'user_id'     => $userId,
            'api_key'     => $key,
            'secret'      => $secret,
            'permissions' => json_encode($permissions),
            'rate_limit'  => $rateLimit,
            'is_active'   => 1,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return ['api_key' => $key, 'secret' => $secret];
    }

    public function revokeApiKey(string $key): bool
    {
        $db = \Config\Database::connect();
        return $db->table('api_keys')->where('api_key', $key)->update(['is_active' => 0]);
    }

    // ========================================================================
    // JWT TOKEN MANAGEMENT
    // ========================================================================

    public function generateJWT(array $user): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'iss'  => 'sidaktejo.site',
            'sub'  => $user['id'] ?? 0,
            'name' => $user['nama'] ?? '',
            'role' => $user['role'] ?? '',
            'iat'  => time(),
            'exp'  => time() + 3600,
        ]);

        $base64Header  = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $secret = getenv('JWT_SECRET') ?: 'sidaktejo_enterprise_jwt_secret_2026';
        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", $secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return "$base64Header.$base64Payload.$base64Signature";
    }

    public function validateJWT(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $signature] = $parts;

        $secret = getenv('JWT_SECRET') ?: 'sidaktejo_enterprise_jwt_secret_2026';
        $expectedSig = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        ));

        if (!hash_equals($expectedSig, $signature)) return null;

        $data = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        if (!$data || (($data['exp'] ?? 0) < time())) return null;

        return $data;
    }

    public function refreshToken(string $token): ?string
    {
        $data = $this->validateJWT($token);
        if (!$data) return null;

        return $this->generateJWT([
            'id'   => $data['sub'],
            'nama' => $data['name'],
            'role' => $data['role'],
        ]);
    }

    // ========================================================================
    // AUTHENTICATION MIDDLEWARE
    // ========================================================================

    public function authenticate(\CodeIgniter\HTTP\IncomingRequest $request): ?array
    {
        // 1. Check Bearer Token (JWT)
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $payload = $this->validateJWT($token);
            if ($payload) {
                return ['type' => 'jwt', 'user_id' => $payload['sub'], 'role' => $payload['role'], 'permissions' => ['*']];
            }
        }

        // 2. Check API Key Header
        $apiKey = $request->getHeaderLine('X-API-Key') ?: $request->getGet('api_key');
        if ($apiKey) {
            $keyData = $this->repo->validateApiKey($apiKey);
            if ($keyData) {
                return [
                    'type'        => 'api_key',
                    'user_id'     => $keyData['user_id'],
                    'api_key'     => $apiKey,
                    'permissions' => json_decode($keyData['permissions'] ?? '["*"]', true),
                    'rate_limit'  => (int)$keyData['rate_limit'],
                ];
            }
        }

        return null;
    }

    // ========================================================================
    // RATE LIMITER
    // ========================================================================

    public function checkRateLimit(string $identifier, int $maxRequests = 1000, int $windowSeconds = 3600): bool
    {
        $cache = \Config\Services::cache();
        $key = 'rate_limit_' . md5($identifier);
        $current = (int)$cache->get($key);

        if ($current >= $maxRequests) {
            return false;
        }

        $cache->save($key, $current + 1, $windowSeconds);
        return true;
    }

    // ========================================================================
    // WEBHOOK ENGINE
    // ========================================================================

    public function registerWebhook(string $url, string $event, ?string $secret = null): bool
    {
        $db = \Config\Database::connect();
        return $db->table('webhooks')->insert([
            'url'        => $url,
            'event'      => $event,
            'secret'     => $secret ?: bin2hex(random_bytes(16)),
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function fireWebhook(string $event, array $payload): void
    {
        $hooks = $this->repo->getWebhooksByEvent($event);
        foreach ($hooks as $hook) {
            $this->dispatchWebhook($hook, $event, $payload);
        }
    }

    private function dispatchWebhook(array $hook, string $event, array $payload, int $attempt = 1): void
    {
        try {
            $json = json_encode([
                'event'     => $event,
                'timestamp' => date('Y-m-d H:i:s'),
                'data'      => $payload,
            ]);

            $signature = hash_hmac('sha256', $json, $hook['secret'] ?? '');

            $ch = curl_init($hook['url']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-Webhook-Signature: ' . $signature,
                    'X-Webhook-Event: ' . $event,
                    'User-Agent: SIDAKTEJO-Webhook/1.0',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $status = ($httpCode >= 200 && $httpCode < 400) ? 'SUCCESS' : 'FAILED';

            $this->repo->logWebhookAttempt([
                'webhook_id'    => $hook['id'],
                'event'         => $event,
                'payload'       => $json,
                'response_code' => $httpCode,
                'response_body' => substr($response ?: '', 0, 1000),
                'attempt'       => $attempt,
                'status'        => $status,
            ]);

            // Auto-retry (max 3 attempts)
            if ($status === 'FAILED' && $attempt < 3) {
                sleep(min($attempt * 2, 10));
                $this->dispatchWebhook($hook, $event, $payload, $attempt + 1);
            }
        } catch (\Throwable $e) {
            log_message('error', '[IntegrationService::dispatchWebhook] ' . $e->getMessage());
            $this->repo->logWebhookAttempt([
                'webhook_id'    => $hook['id'],
                'event'         => $event,
                'payload'       => json_encode($payload),
                'response_code' => 0,
                'response_body' => $e->getMessage(),
                'attempt'       => $attempt,
                'status'        => 'ERROR',
            ]);
        }
    }

    // ========================================================================
    // API LOGGING
    // ========================================================================

    public function logRequest(array $data): void
    {
        $this->repo->logApiRequest($data);
    }

    // ========================================================================
    // HEALTH CHECK
    // ========================================================================

    public function healthCheck(): array
    {
        $checks = [];

        // Database
        try {
            $db = \Config\Database::connect();
            $db->query('SELECT 1');
            $checks['database'] = ['status' => 'OK', 'latency_ms' => round(microtime(true) * 1000 - $db->getConnectStart() * 1000, 2)];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
        }

        // Cache
        try {
            $cache = \Config\Services::cache();
            $cache->save('health_check', 'ok', 10);
            $val = $cache->get('health_check');
            $checks['cache'] = ['status' => $val === 'ok' ? 'OK' : 'DEGRADED'];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
        }

        // Disk
        $checks['disk'] = [
            'status'     => 'OK',
            'free_space' => round(disk_free_space(WRITEPATH) / 1048576, 2) . ' MB',
        ];

        // Memory
        $checks['memory'] = [
            'status'       => 'OK',
            'usage'        => round(memory_get_usage(true) / 1048576, 2) . ' MB',
            'peak'         => round(memory_get_peak_usage(true) / 1048576, 2) . ' MB',
        ];

        $allOk = true;
        foreach ($checks as $c) {
            if (($c['status'] ?? '') !== 'OK') {
                $allOk = false;
                break;
            }
        }

        return [
            'status'    => $allOk ? 'HEALTHY' : 'DEGRADED',
            'timestamp' => date('Y-m-d H:i:s'),
            'version'   => 'v1.0.0',
            'checks'    => $checks,
        ];
    }

    // ========================================================================
    // EXPORT ENGINE
    // ========================================================================

    public function exportData(array $data, string $format): string
    {
        return match ($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'xml'  => $this->arrayToXml($data),
            'csv'  => $this->arrayToCsv($data),
            default => json_encode($data),
        };
    }

    private function arrayToXml(array $data, string $rootElement = 'data', ?\SimpleXMLElement $xml = null): string
    {
        if ($xml === null) {
            $xml = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$rootElement}></{$rootElement}>");
        }

        foreach ($data as $key => $value) {
            $key = is_numeric($key) ? 'item' : $key;
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXml($value, $rootElement, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }

        return $xml->asXML();
    }

    private function arrayToCsv(array $data): string
    {
        if (empty($data)) return '';

        $flat = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $flat[] = $row;
            }
        }
        if (empty($flat)) return json_encode($data);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($flat[0]));
        foreach ($flat as $row) {
            fputcsv($output, array_values($row));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
