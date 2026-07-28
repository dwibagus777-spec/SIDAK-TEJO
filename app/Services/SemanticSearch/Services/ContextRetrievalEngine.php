<?php

namespace App\Services\SemanticSearch\Services;

class ContextRetrievalEngine
{
    protected HybridSearchService $hybridSearchService;

    public function __construct(HybridSearchService $hybridSearchService)
    {
        $this->hybridSearchService = $hybridSearchService;
    }

    public function retrieveContext(string $query, array $filters = [], int $maxTokens = 1500): array
    {
        $results = $this->hybridSearchService->search($query, $filters, 5);

        $snippets = [];
        $totalWords = 0;

        foreach ($results as $item) {
            $content = $item['content'] ?? ($item['payload']['content'] ?? ($item['title'] ?? ''));
            $wordCount = count(explode(' ', $content));

            if ($totalWords + $wordCount > 1000) {
                break;
            }

            $snippets[] = [
                'type'     => $item['type'] ?? 'DOCUMENT',
                'title'    => $item['title'] ?? 'Snippet',
                'content'  => $content,
                'relevance' => $item['rrf_score'] ?? ($item['score'] ?? 0.8)
            ];

            $totalWords += $wordCount;
        }

        return [
            'query'         => $query,
            'snippets'      => $snippets,
            'snippet_count' => count($snippets),
            'context_text'  => $this->buildFormattedContextText($snippets)
        ];
    }

    private function buildFormattedContextText(array $snippets): string
    {
        if (empty($snippets)) {
            return "Tidak ada dokumen atau data pendukung yang ditemukan.";
        }

        $lines = ["=== KONTEKS DATA & DOKUMEN SISTEM SIDAK TEJO ==="];
        foreach ($snippets as $idx => $s) {
            $n = $idx + 1;
            $lines[] = "[{$n}] ({$s['type']}) {$s['title']}:\n{$s['content']}";
        }

        return implode("\n\n", $lines);
    }
}
