<?php

namespace App\Services\SemanticSearch\Providers;

use App\Services\SemanticSearch\Contracts\ReRankerInterface;

class RRFReRanker implements ReRankerInterface
{
    protected int $kConstant;

    public function __construct(int $kConstant = 60)
    {
        $this->kConstant = $kConstant;
    }

    public function reRank(array $keywordResults, array $vectorResults, int $topK = 10): array
    {
        $scores = [];
        $payloads = [];

        // Process Keyword Ranks
        foreach ($keywordResults as $rank => $item) {
            $id = $item['id'] ?? ('kw_' . $rank);
            $rrf = 1.0 / ($this->kConstant + ($rank + 1));
            $scores[$id] = ($scores[$id] ?? 0.0) + $rrf;
            $payloads[$id] = $item;
        }

        // Process Vector Ranks
        foreach ($vectorResults as $rank => $item) {
            $id = $item['id'] ?? ('vec_' . $rank);
            $rrf = 1.0 / ($this->kConstant + ($rank + 1));
            $scores[$id] = ($scores[$id] ?? 0.0) + $rrf;
            if (!isset($payloads[$id])) {
                $payloads[$id] = $item;
            }
        }

        // Sort by RRF fused score descending
        arsort($scores);

        $fused = [];
        $count = 0;
        foreach ($scores as $id => $rrfScore) {
            if ($count >= $topK) {
                break;
            }
            $item = $payloads[$id];
            $item['rrf_score'] = round($rrfScore, 6);
            $fused[] = $item;
            $count++;
        }

        return $fused;
    }

    public function getStrategyName(): string
    {
        return 'rrf';
    }
}
