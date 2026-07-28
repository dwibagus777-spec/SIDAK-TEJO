<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class SemanticSearch extends BaseConfig
{
    /**
     * Active Embedding Provider
     * Supported: 'gemini', 'openai', 'ollama'
     */
    public string $activeEmbeddingProvider = 'gemini';

    /**
     * Active Vector Store Provider
     * Supported: 'flatfile' (Local JSON/Array), 'qdrant', 'pgvector'
     */
    public string $activeVectorStore = 'flatfile';

    /**
     * Active Re-Ranker Strategy
     * Supported: 'rrf' (Reciprocal Rank Fusion), 'cosine'
     */
    public string $activeReRanker = 'rrf';

    /**
     * Chunking Configuration for PDF & Text Documents
     */
    public int $chunkSize    = 500; // Words per chunk
    public int $chunkOverlap = 50;  // Words overlap

    /**
     * Provider Configurations
     */
    public array $providers = [
        'gemini' => [
            'model'     => 'text-embedding-004',
            'dimension' => 768
        ],
        'openai' => [
            'model'     => 'text-embedding-3-small',
            'dimension' => 1536
        ],
        'ollama' => [
            'model'     => 'nomic-embed-text',
            'dimension' => 768
        ]
    ];
}
