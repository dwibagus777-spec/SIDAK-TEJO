<?php

namespace App\Services\SemanticSearch\Contracts;

interface VectorStoreInterface
{
    /**
     * Upsert a vector point into specified collection
     *
     * @param string $collection Collection or index name
     * @param string $id Unique point ID
     * @param array $vector Dense float vector
     * @param array $payload Metadata payload
     * @return bool Success status
     */
    public function upsert(string $collection, string $id, array $vector, array $payload = []): bool;

    /**
     * Search nearest neighbor vectors using similarity
     *
     * @param string $collection Collection name
     * @param array $queryVector Target query vector
     * @param int $topK Number of matches
     * @param array $filter Hard metadata filters
     * @return array Ranked vector search results
     */
    public function search(string $collection, array $queryVector, int $topK = 10, array $filter = []): array;

    /**
     * Delete point from collection
     */
    public function delete(string $collection, string $id): bool;

    /**
     * Get store provider name
     */
    public function getProviderName(): string;
}
