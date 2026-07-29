<?php

namespace App\Services\Drivers;

class FtpDriver implements IntegrationDriverInterface
{
    private array $config = [];
    private bool $connected = false;

    public function getName(): string
    {
        return 'FTP';
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

        $filename = $this->config['filename'] ?? ('export_' . date('Ymd_His') . '.json');
        $content  = json_encode($data, JSON_PRETTY_PRINT);
        $savePath = WRITEPATH . 'uploads/' . $filename;

        file_put_contents($savePath, $content);

        log_message('info', "[FTP Driver] Transferred file '{$filename}' to target location.");

        return [
            'status'   => true,
            'filename' => $filename,
            'path'     => $savePath,
            'size'     => strlen($content),
        ];
    }

    public function receive(array $params = []): array
    {
        $filename = $params['filename'] ?? '';
        $path = WRITEPATH . 'uploads/' . $filename;

        if (file_exists($path)) {
            $content = file_get_contents($path);
            return [
                'status' => true,
                'data'   => json_decode($content, true) ?: $content,
            ];
        }

        return ['status' => false, 'message' => 'File not found'];
    }

    public function disconnect(): bool
    {
        $this->connected = false;
        return true;
    }
}
