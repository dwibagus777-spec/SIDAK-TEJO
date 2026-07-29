<?php

namespace App\Services\Drivers;

interface IntegrationDriverInterface
{
    public function getName(): string;
    public function connect(array $config): bool;
    public function send(array $data): array;
    public function receive(array $params = []): array;
    public function disconnect(): bool;
}
