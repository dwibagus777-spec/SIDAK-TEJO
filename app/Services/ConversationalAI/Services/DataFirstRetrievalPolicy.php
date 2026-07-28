<?php

namespace App\Services\ConversationalAI\Services;

use App\Services\SemanticSearch\Services\HybridSearchService;
use App\Services\SemanticSearch\Services\ContextRetrievalEngine;

class DataFirstRetrievalPolicy
{
    protected HybridSearchService $hybridSearchService;
    protected ContextRetrievalEngine $contextEngine;

    public function __construct(
        HybridSearchService $hybridSearchService,
        ContextRetrievalEngine $contextEngine
    ) {
        $this->hybridSearchService = $hybridSearchService;
        $this->contextEngine       = $contextEngine;
    }

    public function retrieveDataFirst(string $query, array $filters = []): array
    {
        // 1. Direct System DB & Vector Hybrid Search
        $retrievedContext = $this->contextEngine->retrieveContext($query, $filters, 1500);

        if (!empty($retrievedContext['snippets'])) {
            return [
                'source_level'    => 'SYSTEM_DATA',
                'context_text'    => $retrievedContext['context_text'],
                'snippets'        => $retrievedContext['snippets'],
                'has_system_data' => true
            ];
        }

        // 2. Fallback to General AI Synthesis
        return [
            'source_level'    => 'GENERAL_KNOWLEDGE',
            'context_text'    => 'Tidak ada data spesifik ditemukan di sistem SIDAK TEJO.',
            'snippets'        => [],
            'has_system_data' => false
        ];
    }
}
