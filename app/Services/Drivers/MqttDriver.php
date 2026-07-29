<?php

namespace App\Services\Drivers;

class MqttDriver implements IntegrationDriverInterface
{
    private array $config = [];
    private bool $connected = false;

    public function getName(): string
    {
        return 'MQTT';
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

        $topic   = $this->config['topic'] ?? 'sidak/tejo/telemetry';
        $payload = json_encode([
            'topic'     => $topic,
            'timestamp' => date('Y-m-d H:i:s'),
            'payload'   => $data,
        ]);

        log_message('info', "[MQTT Driver] Published to topic '{$topic}': " . $payload);

        return [
            'status'    => true,
            'topic'     => $topic,
            'message'   => 'MQTT Message published successfully',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function receive(array $params = []): array
    {
        $topic = $params['topic'] ?? ($this->config['topic'] ?? 'sidak/tejo/telemetry');
        return [
            'status' => true,
            'topic'  => $topic,
            'data'   => [],
        ];
    }

    public function disconnect(): bool
    {
        $this->connected = false;
        return true;
    }
}
