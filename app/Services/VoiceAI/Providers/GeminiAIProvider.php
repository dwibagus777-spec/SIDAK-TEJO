<?php

namespace App\Services\VoiceAI\Providers;

use App\Services\VoiceAI\Contracts\AIProviderInterface;

class GeminiAIProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->apiKey  = $config['api_key'] ?? (getenv('GEMINI_API_KEY') ?: '');
        $this->model   = $config['model'] ?? 'gemini-1.5-flash';
        $this->baseUrl = $config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/';
    }

    public function chat(array $messages, array $options = []): array
    {
        if (empty($this->apiKey)) {
            // Fallback response when API key is not configured
            $lastMsg = end($messages)['content'] ?? '';
            return [
                'text' => "Google Gemini AI: " . $this->generateFallbackResponse($lastMsg),
                'raw'  => null
            ];
        }

        try {
            $client = \Config\Services::curlrequest();
            $contents = [];
            foreach ($messages as $msg) {
                $role = ($msg['role'] === 'user') ? 'user' : 'model';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content']]]
                ];
            }

            $endpoint = $this->baseUrl . "models/{$this->model}:generateContent?key=" . $this->apiKey;
            $res = $client->post($endpoint, [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => ['contents' => $contents],
                'timeout' => 15,
                'http_errors' => false
            ]);

            if ($res->getStatusCode() === 200) {
                $body = json_decode($res->getBody(), true);
                $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, tidak dapat menghasilkan respon.';
                return ['text' => $text, 'raw' => $body];
            }
        } catch (\Throwable $e) {
            log_message('error', 'GeminiAIProvider error: ' . $e->getMessage());
        }

        $lastMsg = end($messages)['content'] ?? '';
        return [
            'text' => $this->generateFallbackResponse($lastMsg),
            'raw'  => null
        ];
    }

    public function detectIntent(string $userQuery, array $availableIntents): array
    {
        $prompt = "Analisis kalimat pengguna: \"{$userQuery}\". "
            . "Pilihlah salah satu Intent dari daftar berikut: " . json_encode(array_keys($availableIntents)) . ". "
            . "Format respon berupa JSON persis: {\"intent\": \"NAMA_INTENT\", \"params\": {}, \"confidence\": 0.95}";

        $res = $this->chat([['role' => 'user', 'content' => $prompt]]);
        $json = json_decode($res['text'], true);

        if (is_array($json) && isset($json['intent'])) {
            return $json;
        }

        return ['intent' => 'GENERAL_QA', 'params' => [], 'confidence' => 0.7];
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    private function generateFallbackResponse(string $query): string
    {
        return "Sistem SIDAK TEJO siap membantu memproses perintah: \"{$query}\". Silakan pastikan API Key LLM sudah terkonfigurasi.";
    }
}
