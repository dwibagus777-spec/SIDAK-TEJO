<?php

namespace App\Services\Drivers;

class SoapDriver implements IntegrationDriverInterface
{
    private array $config = [];
    private bool $connected = false;

    public function getName(): string
    {
        return 'SOAP';
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

        $endpoint = $this->config['endpoint'] ?? '';
        $action   = $this->config['action'] ?? 'Execute';

        $xmlBody = '<?xml version="1.0" encoding="UTF-8"?>';
        $xmlBody .= '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">';
        $xmlBody .= '<soapenv:Header/>';
        $xmlBody .= '<soapenv:Body>';
        $xmlBody .= '<ns1:' . htmlspecialchars($action) . '>';
        foreach ($data as $k => $v) {
            $xmlBody .= '<' . htmlspecialchars($k) . '>' . htmlspecialchars((string)$v) . '</' . htmlspecialchars($k) . '>';
        }
        $xmlBody .= '</ns1:' . htmlspecialchars($action) . '>';
        $xmlBody .= '</soapenv:Body></soapenv:Envelope>';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xmlBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $action . '"',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status'      => ($code >= 200 && $code < 300),
            'status_code' => $code,
            'raw_xml'     => $res,
        ];
    }

    public function receive(array $params = []): array
    {
        return $this->send($params);
    }

    public function disconnect(): bool
    {
        $this->connected = false;
        return true;
    }
}
