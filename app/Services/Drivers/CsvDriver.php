<?php

namespace App\Services\Drivers;

class CsvDriver implements IntegrationDriverInterface
{
    private array $config = [];
    private bool $connected = false;

    public function getName(): string
    {
        return 'CSV';
    }

    public function connect(array $config): bool
    {
        $this->config = $config;
        $this->connected = true;
        return true;
    }

    public function send(array $data): array
    {
        if (empty($data)) {
            return ['status' => false, 'message' => 'Empty data set'];
        }

        $stream = fopen('php://temp', 'r+');
        if (isset($data[0]) && is_array($data[0])) {
            fputcsv($stream, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($stream, array_values($row));
            }
        } else {
            fputcsv($stream, array_keys($data));
            fputcsv($stream, array_values($data));
        }

        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        return [
            'status'  => true,
            'format'  => 'CSV',
            'content' => $csvContent,
            'length'  => strlen($csvContent),
        ];
    }

    public function receive(array $params = []): array
    {
        $csvContent = $params['content'] ?? '';
        if (empty($csvContent)) {
            return ['status' => false, 'message' => 'No CSV content provided'];
        }

        $lines = explode("\n", trim($csvContent));
        $header = str_getcsv(array_shift($lines));
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $row = str_getcsv($line);
            if (count($row) === count($header)) {
                $rows[] = array_combine($header, $row);
            }
        }

        return ['status' => true, 'data' => $rows];
    }

    public function disconnect(): bool
    {
        $this->connected = false;
        return true;
    }
}
