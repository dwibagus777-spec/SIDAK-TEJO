<?php

namespace App\Services\SemanticSearch\Providers;

use App\Services\SemanticSearch\Contracts\EmbeddingProviderInterface;

class GeminiEmbeddingProvider implements EmbeddingProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected int $dimension;

    public function __construct(array $config = [])
    {
        $this->apiKey    = $config['api_key'] ?? (getenv('GEMINI_API_KEY') ?: '');
        $this->model     = $config['model'] ?? 'text-embedding-004';
        $this->dimension = $config['dimension'] ?? 768;
    }

    public function embedQuery(string $text): array
    {
        if (empty($this->apiKey)) {
            return $this->generateDeterministicVector($text);
        }

        try {
            $client = \Config\Services::curlrequest();
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:embedContent?key=" . $this->apiKey;

            $res = $client->post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'model'   => "models/{$this->model}",
                    'content' => ['parts' => [['text' => $text]]]
                ],
                'timeout' => 10,
                'http_errors' => false
            ]);

            if ($res->getStatusCode() === 200) {
                $body = json_decode($res->getBody(), true);
                if (!empty($body['embedding']['values'])) {
                    return $body['embedding']['values'];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'GeminiEmbeddingProvider Error: ' . $e->getMessage());
        }

        return $this->generateDeterministicVector($text);
    }

    public function embedBatch(array $texts): array
    {
        $batch = [];
        foreach ($texts as $txt) {
            $batch[] = $this->embedQuery($txt);
        }
        return $batch;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    private function generateDeterministicVector(string $text): array
    {
        $hash = sha1($text);
        $vec = [];
        for ($i = 0; $i < $this->dimension; $i++) {
            $charHex = substr($hash, ($i % 40), 1);
            $val = (hexdec($charHex) / 15.0) - 0.5;
            $vec[] = round($val, 6);
        }
        return $vec;
    }
}
