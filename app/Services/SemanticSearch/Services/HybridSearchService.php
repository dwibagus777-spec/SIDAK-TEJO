<?php

namespace App\Services\SemanticSearch\Services;

use App\Services\SemanticSearch\Contracts\EmbeddingProviderInterface;
use App\Services\SemanticSearch\Contracts\VectorStoreInterface;
use App\Services\SemanticSearch\Contracts\ReRankerInterface;
use Config\Database;

class HybridSearchService
{
    protected EmbeddingProviderInterface $embeddingProvider;
    protected VectorStoreInterface $vectorStore;
    protected ReRankerInterface $reRanker;

    public function __construct(
        EmbeddingProviderInterface $embeddingProvider,
        VectorStoreInterface $vectorStore,
        ReRankerInterface $reRanker
    ) {
        $this->embeddingProvider = $embeddingProvider;
        $this->vectorStore        = $vectorStore;
        $this->reRanker           = $reRanker;
    }

    public function search(string $query, array $filters = [], int $limit = 10): array
    {
        $queryClean = trim($query);
        if (empty($queryClean)) {
            return [];
        }

        // 1. Keyword / SQL Fulltext Search
        $keywordResults = $this->executeKeywordSearch($queryClean, $filters, $limit * 2);

        // 2. Vector Semantic Search
        $queryVector = $this->embeddingProvider->embedQuery($queryClean);
        $vectorResults = $this->vectorStore->search('domain_records', $queryVector, $limit * 2, $filters);
        
        $knowledgeVectorResults = $this->vectorStore->search('knowledge_documents', $queryVector, $limit * 2);
        $allVectorResults = array_merge($vectorResults, $knowledgeVectorResults);

        // 3. Re-Ranking & Reciprocal Rank Fusion (RRF)
        return $this->reRanker->reRank($keywordResults, $allVectorResults, $limit);
    }

    private function executeKeywordSearch(string $query, array $filters = [], int $limit = 10): array
    {
        $db = Database::connect();
        $results = [];

        $builder = $db->table('temuan')
            ->select('temuan.id, temuan.nomor_temuan, temuan.jenis_temuan, temuan.detail_temuan, temuan.alamat, temuan.status, temuan.prioritas, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = temuan.ulp_id', 'left')
            ->groupStart()
                ->like('temuan.nomor_temuan', $query)
                ->orLike('temuan.detail_temuan', $query)
                ->orLike('temuan.alamat', $query)
            ->groupEnd();

        if (!empty($filters['ulp_id'])) {
            $builder->where('temuan.ulp_id', $filters['ulp_id']);
        }
        if (!empty($filters['status'])) {
            $builder->where('temuan.status', strtoupper($filters['status']));
        }

        $rows = $builder->limit($limit)->get()->getResultArray();
        foreach ($rows as $r) {
            $results[] = [
                'id'       => "kw_temuan_{$r['id']}",
                'type'     => 'TEMUAN',
                'title'    => "Temuan {$r['nomor_temuan']} ({$r['jenis_temuan']})",
                'content'  => "{$r['detail_temuan']} di {$r['alamat']} [Status {$r['status']}, Prioritas {$r['prioritas']}]",
                'payload'  => $r
            ];
        }

        return $results;
    }
}
