<?php

namespace App\Services\SemanticSearch\Providers;

use App\Services\SemanticSearch\Contracts\VectorStoreInterface;

class FlatFileVectorProvider implements VectorStoreInterface
{
    protected string $storageDir;

    public function __construct()
    {
        $this->storageDir = WRITEPATH . 'semantic_vectors/';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    public function upsert(string $collection, string $id, array $vector, array $payload = []): bool
    {
        $file = $this->getCollectionFile($collection);
        $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

        $data[$id] = [
            'id'       => $id,
            'vector'   => $vector,
            'payload'  => $payload,
            'updated'  => date('Y-m-d H:i:s')
        ];

        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    public function search(string $collection, array $queryVector, int $topK = 10, array $filter = []): array
    {
        $file = $this->getCollectionFile($collection);
        if (!file_exists($file)) {
            return [];
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        $matches = [];

        foreach ($data as $item) {
            // Apply hard payload filters
            if (!$this->matchesFilter($item['payload'] ?? [], $filter)) {
                continue;
            }

            $sim = $this->cosineSimilarity($queryVector, $item['vector']);
            $matches[] = [
                'id'       => $item['id'],
                'score'    => round($sim, 4),
                'payload'  => $item['payload'] ?? []
            ];
        }

        // Sort descending by similarity score
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, $topK);
    }

    public function delete(string $collection, string $id): bool
    {
        $file = $this->getCollectionFile($collection);
        if (!file_exists($file)) {
            return true;
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        if (isset($data[$id])) {
            unset($data[$id]);
            return file_put_contents($file, json_encode($data)) !== false;
        }

        return true;
    }

    public function getProviderName(): string
    {
        return 'flatfile';
    }

    private function getCollectionFile(string $collection): string
    {
        return $this->storageDir . preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection) . '.json';
    }

    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = min(count($vecA), count($vecB));

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    private function matchesFilter(array $payload, array $filter): bool
    {
        foreach ($filter as $key => $val) {
            if (!isset($payload[$key]) || $payload[$key] != $val) {
                return false;
            }
        }
        return true;
    }
}
