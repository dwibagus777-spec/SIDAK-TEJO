<?php

namespace App\Services\SemanticSearch\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * Generate dense vector embedding for single text query
     *
     * @param string $text Input text
     * @return array Float vector array
     */
    public function embedQuery(string $text): array;

    /**
     * Generate dense vector embeddings for batch of texts
     *
     * @param array $texts Array of strings
     * @return array Array of float vector arrays
     */
    public function embedBatch(array $texts): array;

    /**
     * Get vector dimension size (e.g. 768 or 1536)
     */
    public function getDimension(): int;

    /**
     * Get provider name
     */
    public function getProviderName(): string;
}
