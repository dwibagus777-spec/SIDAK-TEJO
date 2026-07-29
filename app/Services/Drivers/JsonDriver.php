<?php

namespace App\Services\Drivers;

class JsonDriver implements IntegrationDriverInterface
{
    private array $config = [];
    private bool $connected = false;

    public function getName(): string
    {
        return 'JSON';
    }

    public function connect(array $config): bool
    {
        $this->config = $config;
        $this->connected = true;
        return true;
    }

    public function send(array $data): array
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return [
            'status'  => true,
            'format'  => 'JSON',
            'content' => $json,
            'length'  => strlen($json),
        ];
    }

    public function receive(array $params = []): array
    {
        $raw = $params['content'] ?? '';
        $data = json_decode($raw, true);

        return [
            'status' => ($data !== null),
            'data'   => $data ?: [],
            'error'  => json_last_error_msg(),
        ];
    }

    public function disconnect(): bool
    {
        $this->connected = false;
        return true;
    }
}
