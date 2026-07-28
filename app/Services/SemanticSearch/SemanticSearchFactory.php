<?php

namespace App\Services\SemanticSearch;

use App\Services\SemanticSearch\Contracts\EmbeddingProviderInterface;
use App\Services\SemanticSearch\Contracts\VectorStoreInterface;
use App\Services\SemanticSearch\Contracts\ReRankerInterface;
use App\Services\SemanticSearch\Providers\GeminiEmbeddingProvider;
use App\Services\SemanticSearch\Providers\OpenAIEmbeddingProvider;
use App\Services\SemanticSearch\Providers\FlatFileVectorProvider;
use App\Services\SemanticSearch\Providers\RRFReRanker;
use App\Services\SemanticSearch\Services\HybridSearchService;
use App\Services\SemanticSearch\Services\ContextRetrievalEngine;
use Config\SemanticSearch as SemanticSearchConfig;

class SemanticSearchFactory
{
    protected SemanticSearchConfig $config;

    public function __construct(?SemanticSearchConfig $config = null)
    {
        $this->config = $config ?? config('SemanticSearch');
    }

    public function makeEmbeddingProvider(?string $name = null): EmbeddingProviderInterface
    {
        $providerName = strtolower($name ?? $this->config->activeEmbeddingProvider);
        $providerConfig = $this->config->providers[$providerName] ?? [];

        return match ($providerName) {
            'openai'  => new OpenAIEmbeddingProvider($providerConfig),
            'gemini'  => new GeminiEmbeddingProvider($providerConfig),
            default   => new GeminiEmbeddingProvider($providerConfig),
        };
    }

    public function makeVectorStore(?string $name = null): VectorStoreInterface
    {
        $storeName = strtolower($name ?? $this->config->activeVectorStore);

        return match ($storeName) {
            'flatfile' => new FlatFileVectorProvider(),
            default    => new FlatFileVectorProvider(),
        };
    }

    public function makeReRanker(?string $name = null): ReRankerInterface
    {
        $rankerName = strtolower($name ?? $this->config->activeReRanker);

        return match ($rankerName) {
            'rrf'     => new RRFReRanker(),
            default   => new RRFReRanker(),
        };
    }

    public function makeHybridSearchService(): HybridSearchService
    {
        return new HybridSearchService(
            $this->makeEmbeddingProvider(),
            $this->makeVectorStore(),
            $this->makeReRanker()
        );
    }

    public function makeContextRetrievalEngine(): ContextRetrievalEngine
    {
        return new ContextRetrievalEngine($this->makeHybridSearchService());
    }
}
