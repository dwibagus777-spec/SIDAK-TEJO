<?php

namespace App\Services\SemanticSearch\Providers;

use App\Services\SemanticSearch\Contracts\EmbeddingProviderInterface;

class OpenAIEmbeddingProvider implements EmbeddingProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected int $dimension;

    public function __construct(array $config = [])
    {
        $this->apiKey    = $config['api_key'] ?? (getenv('OPENAI_API_KEY') ?: '');
        $this->model     = $config['model'] ?? 'text-embedding-3-small';
        $this->dimension = $config['dimension'] ?? 1536;
    }

    public function embedQuery(string $text): array
    {
        if (empty($this->apiKey)) {
            return array_fill(0, $this->dimension, 0.01);
        }

        try {
            $client = \Config\Services::curlrequest();
            $url = 'https://api.openai.com/v1/embeddings';

            $res = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json'
                ],
                'json' => [
                    'model' => $this->model,
                    'input' => $text
                ],
                'timeout' => 10,
                'http_errors' => false
            ]);

            if ($res->getStatusCode() === 200) {
                $body = json_decode($res->getBody(), true);
                if (!empty($body['data'][0]['embedding'])) {
                    return $body['data'][0]['embedding'];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'OpenAIEmbeddingProvider Error: ' . $e->getMessage());
        }

        return array_fill(0, $this->dimension, 0.01);
    }

    public function embedBatch(array $texts): array
    {
        $batch = [];
        foreach ($texts as $t) {
            $batch[] = $this->embedQuery($t);
        }
        return $batch;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function getProviderName(): string
    {
        return 'openai';
    }
}
