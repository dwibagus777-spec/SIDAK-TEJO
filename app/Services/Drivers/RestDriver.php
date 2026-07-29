<?php

namespace App\Services\Drivers;

class RestDriver implements IntegrationDriverInterface
{
    private array $config = [];
    private bool $connected = false;

    public function getName(): string
    {
        return 'REST';
    }

    public function connect(array $config): bool
    {
        $this->config = $config;
        $this->connected = true;
        return true;
    }

    public function send(array $data): array
    {
        if (!$this->connected) {
            return ['status' => false, 'message' => 'Not connected'];
        }

        $url = $this->config['endpoint'] ?? '';
        $method = strtoupper($this->config['method'] ?? 'POST');
        $headers = $this->config['headers'] ?? ['Content-Type: application/json'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status'      => ($code >= 200 && $code < 300),
            'status_code' => $code,
            'response'    => json_decode($res, true) ?: $res,
        ];
    }

    public function receive(array $params = []): array
    {
        if (!$this->connected) {
            return ['status' => false, 'message' => 'Not connected'];
        }

        $url = $this->config['endpoint'] ?? '';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status'      => ($code >= 200 && $code < 300),
            'status_code' => $code,
            'data'        => json_decode($res, true) ?: $res,
        ];
    }

    public function disconnect(): bool
    {
        $this->connected = false;
        return true;
    }
}
