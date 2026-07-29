<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Services\IntegrationService;

abstract class BaseApiController extends ResourceController
{
    protected IntegrationService $integrationService;
    protected ?array $apiUser = null;
    protected float $startTime;

    public function __construct()
    {
        $this->integrationService = new IntegrationService();
        $this->startTime = microtime(true);
    }

    protected function respondStandard(bool $status, int $code, string $message, mixed $data = null, array $meta = []): \CodeIgniter\HTTP\ResponseInterface
    {
        $durationMs = round((microtime(true) - $this->startTime) * 1000, 2);

        // Audit log
        $this->integrationService->logRequest([
            'user_id'       => $this->apiUser['user_id'] ?? null,
            'api_key'       => $this->apiUser['api_key'] ?? null,
            'method'        => $this->request->getMethod(true),
            'endpoint'      => $this->request->getUri()->getPath(),
            'request_body'  => substr(json_encode($this->request->getJSON(true) ?: $this->request->getRawInput()), 0, 1000),
            'response_body' => substr(json_encode($data), 0, 1000),
            'status_code'   => $code,
            'duration_ms'   => $durationMs,
            'ip_address'    => $this->request->getIPAddress(),
            'user_agent'    => (string)$this->request->getUserAgent(),
        ]);

        $responseBody = [
            'status'  => $status,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
            'meta'    => array_merge([
                'version'     => 'v1',
                'duration_ms' => $durationMs,
                'timestamp'   => date('Y-m-d H:i:s'),
            ], $meta),
        ];

        return $this->response->setStatusCode($code)->setJSON($responseBody);
    }

    protected function verifyAuth(): bool
    {
        $auth = $this->integrationService->authenticate($this->request);
        if (!$auth) {
            return false;
        }

        // Rate limiter check
        $identifier = $auth['api_key'] ?? ($this->request->getIPAddress());
        $rateLimit  = $auth['rate_limit'] ?? 1000;

        if (!$this->integrationService->checkRateLimit($identifier, $rateLimit)) {
            $this->respondStandard(false, 429, 'Rate limit exceeded. Try again later.');
            return false;
        }

        $this->apiUser = $auth;
        return true;
    }
}
