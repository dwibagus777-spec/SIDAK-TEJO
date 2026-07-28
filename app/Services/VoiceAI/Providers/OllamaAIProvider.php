<?php

namespace App\Services\VoiceAI\Providers;

use App\Services\VoiceAI\Contracts\AIProviderInterface;

class OllamaAIProvider implements AIProviderInterface
{
    protected string $model;
    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->model   = $config['model'] ?? 'llama3';
        $this->baseUrl = $config['base_url'] ?? 'http://localhost:11434/api/';
    }

    public function chat(array $messages, array $options = []): array
    {
        try {
            $client = \Config\Services::curlrequest();
            $endpoint = rtrim($this->baseUrl, '/') . '/chat';
            $res = $client->post($endpoint, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'model'    => $this->model,
                    'messages' => $messages,
                    'stream'   => false
                ],
                'timeout' => 25,
                'http_errors' => false
            ]);

            if ($res->getStatusCode() === 200) {
                $body = json_decode($res->getBody(), true);
                $text = $body['message']['content'] ?? 'Maaf, Ollama tidak dapat merespon.';
                return ['text' => $text, 'raw' => $body];
            }
        } catch (\Throwable $e) {
            log_message('error', 'OllamaAIProvider error: ' . $e->getMessage());
        }

        return ['text' => 'Ollama Local LLM Offline/Unavailable.', 'raw' => null];
    }

    public function detectIntent(string $userQuery, array $availableIntents): array
    {
        return ['intent' => 'GENERAL_QA', 'params' => [], 'confidence' => 0.75];
    }

    public function getProviderName(): string
    {
        return 'ollama';
    }
}
