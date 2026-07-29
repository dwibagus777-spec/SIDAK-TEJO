<?php

namespace App\Services;

use App\AI\SmartRecommendationEngine;

class SmartRecommendationService
{
    private SmartRecommendationEngine $engine;

    public function __construct()
    {
        $this->engine = new SmartRecommendationEngine();
    }

    /**
     * Get Recommendation for Input Temuan Form or Detail View
     */
    public function getRecommendation(array $inputData): array
    {
        return $this->engine->generateRecommendation($inputData);
    }
}
