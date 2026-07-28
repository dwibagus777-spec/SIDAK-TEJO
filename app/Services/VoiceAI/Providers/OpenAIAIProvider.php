<?php

namespace App\Services\VoiceAI\Providers;

use App\Services\VoiceAI\Contracts\AIProviderInterface;

class OpenAIAIProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->apiKey  = $config['api_key'] ?? (getenv('OPENAI_API_KEY') ?: '');
        $this->model   = $config['model'] ?? 'gpt-4o-mini';
        $this->baseUrl = $config['base_url'] ?? 'https://api.openai.com/v1/';
    }

    public function chat(array $messages, array $options = []): array
    {
        if (empty($this->apiKey)) {
            $lastMsg = end($messages)['content'] ?? '';
            return [
                'text' => "OpenAI: " . $lastMsg,
                'raw'  => null
            ];
        }

        try {
            $client = \Config\Services::curlrequest();
            $endpoint = rtrim($this->baseUrl, '/') . '/chat/completions';
            $res = $client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json'
                ],
                'json' => [
                    'model'    => $this->model,
                    'messages' => $messages
                ],
                'timeout' => 15,
                'http_errors' => false
            ]);

            if ($res->getStatusCode() === 200) {
                $body = json_decode($res->getBody(), true);
                $text = $body['choices'][0]['message']['content'] ?? 'Maaf, tidak dapat menghasilkan respon.';
                return ['text' => $text, 'raw' => $body];
            }
        } catch (\Throwable $e) {
            log_message('error', 'OpenAIAIProvider error: ' . $e->getMessage());
        }

        return ['text' => 'Respon OpenAI fallback.', 'raw' => null];
    }

    public function detectIntent(string $userQuery, array $availableIntents): array
    {
        return ['intent' => 'GENERAL_QA', 'params' => [], 'confidence' => 0.8];
    }

    public function getProviderName(): string
    {
        return 'openai';
    }
}
