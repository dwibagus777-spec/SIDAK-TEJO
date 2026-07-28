<?php

namespace App\Services\SemanticSearch\Contracts;

interface ReRankerInterface
{
    /**
     * Re-rank and fuse keyword search results and vector search results
     *
     * @param array $keywordResults BM25/SQL Keyword search matches
     * @param array $vectorResults Semantic vector search matches
     * @param int $topK Final limit
     * @return array Fused & re-ranked results with unified relevance score
     */
    public function reRank(array $keywordResults, array $vectorResults, int $topK = 10): array;

    /**
     * Get Re-Ranker strategy name
     */
    public function getStrategyName(): string;
}
