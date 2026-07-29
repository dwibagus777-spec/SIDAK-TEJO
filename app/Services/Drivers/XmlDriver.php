<?php

namespace App\Services\Drivers;

class XmlDriver implements IntegrationDriverInterface
{
    private array $config = [];
    private bool $connected = false;

    public function getName(): string
    {
        return 'XML';
    }

    public function connect(array $config): bool
    {
        $this->config = $config;
        $this->connected = true;
        return true;
    }

    public function send(array $data): array
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><root/>');
        $this->arrayToXml($data, $xml);
        $content = $xml->asXML();

        return [
            'status'  => true,
            'format'  => 'XML',
            'content' => $content,
            'length'  => strlen($content),
        ];
    }

    public function receive(array $params = []): array
    {
        $content = $params['content'] ?? '';
        if (empty($content)) {
            return ['status' => false, 'message' => 'Empty XML input'];
        }

        try {
            $xmlObj = simplexml_load_string($content);
            $json = json_encode($xmlObj);
            $array = json_decode($json, true);

            return ['status' => true, 'data' => $array];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    private function arrayToXml(array $data, \SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            $key = is_numeric($key) ? 'item' : $key;
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }

    public function disconnect(): bool
    {
        $this->connected = false;
        return true;
    }
}
