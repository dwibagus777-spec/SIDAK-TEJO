<?php

namespace App\Services\ConversationalAI;

use App\Services\ConversationalAI\Services\TextNormalizationService;
use App\Services\ConversationalAI\Services\AmbiguityResolverService;
use App\Services\ConversationalAI\Services\SessionContextManager;
use App\Services\ConversationalAI\Services\DataFirstRetrievalPolicy;
use App\Services\VoiceAI\VoiceAIService;

class ConversationalAIPipeline
{
    protected TextNormalizationService $normalizer;
    protected AmbiguityResolverService $ambiguityResolver;
    protected DataFirstRetrievalPolicy $dataPolicy;
    protected VoiceAIService $voiceAIService;

    public function __construct(
        TextNormalizationService $normalizer,
        AmbiguityResolverService $ambiguityResolver,
        DataFirstRetrievalPolicy $dataPolicy,
        VoiceAIService $voiceAIService
    ) {
        $this->normalizer        = $normalizer;
        $this->ambiguityResolver = $ambiguityResolver;
        $this->dataPolicy        = $dataPolicy;
        $this->voiceAIService    = $voiceAIService;
    }

    public function execute(string $rawInput, string $sessionId = 'default_session'): array
    {
        // Step 1: Text Normalization (Typo, PLN Jargon, Javanese)
        $normResult = $this->normalizer->normalize($rawInput);
        $cleanText  = $normResult['normalized'];

        // Step 2: Anaphora Resolution & Session Memory
        $contextMgr = new SessionContextManager($sessionId);
        $resolvedText = $contextMgr->resolveAnaphora($cleanText);

        // Step 3: Data-First Retrieval
        $retrieved = $this->dataPolicy->retrieveDataFirst($resolvedText);

        // Step 4: Execute Core Voice AI Pipeline with Enriched Context
        $voiceAiParams = [
            'session_id' => $sessionId,
            'text'       => $resolvedText,
            'language'   => $normResult['detected_language'] ?? 'id'
        ];

        $response = $this->voiceAIService->processRequest($voiceAiParams);

        $response['pipeline_meta'] = [
            'original_input'    => $rawInput,
            'normalized_text'   => $cleanText,
            'resolved_text'     => $resolvedText,
            'detected_language' => $normResult['detected_language'],
            'data_source_level' => $retrieved['source_level']
        ];

        return $response;
    }
}
