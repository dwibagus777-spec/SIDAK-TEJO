<?php

namespace App\Services\SemanticSearch\Services;

use App\Services\SemanticSearch\Contracts\EmbeddingProviderInterface;
use App\Services\SemanticSearch\Contracts\VectorStoreInterface;

class DocumentIngestionService
{
    protected EmbeddingProviderInterface $embeddingProvider;
    protected VectorStoreInterface $vectorStore;
    protected int $chunkSize;
    protected int $chunkOverlap;

    public function __construct(
        EmbeddingProviderInterface $embeddingProvider,
        VectorStoreInterface $vectorStore,
        int $chunkSize = 500,
        int $chunkOverlap = 50
    ) {
        $this->embeddingProvider = $embeddingProvider;
        $this->vectorStore        = $vectorStore;
        $this->chunkSize           = $chunkSize;
        $this->chunkOverlap       = $chunkOverlap;
    }

    public function ingestTextDocument(string $docId, string $title, string $text, array $metadata = []): array
    {
        $chunks = $this->chunkText($text);
        $indexedChunks = [];

        foreach ($chunks as $index => $chunkText) {
            $chunkId = "doc_{$docId}_chunk_{$index}";
            $vector  = $this->embeddingProvider->embedQuery($chunkText);

            $payload = array_merge([
                'doc_id'       => $docId,
                'chunk_index'  => $index,
                'title'        => $title,
                'content'      => $chunkText,
                'total_chunks' => count($chunks)
            ], $metadata);

            $this->vectorStore->upsert('knowledge_documents', $chunkId, $vector, $payload);
            $indexedChunks[] = $chunkId;
        }

        return [
            'doc_id'        => $docId,
            'title'         => $title,
            'chunks_count'  => count($indexedChunks),
            'chunk_ids'     => $indexedChunks
        ];
    }

    public function chunkText(string $text): array
    {
        $words = preg_split('/\s+/', trim($text));
        $totalWords = count($words);
        
        if ($totalWords === 0) {
            return [];
        }

        $chunks = [];
        $i = 0;

        while ($i < $totalWords) {
            $chunkWords = array_slice($words, $i, $this->chunkSize);
            $chunks[] = implode(' ', $chunkWords);
            $i += ($this->chunkSize - $this->chunkOverlap);
        }

        return $chunks;
    }
}
