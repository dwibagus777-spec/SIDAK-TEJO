<?php

namespace App\Services;

use App\AI\IntentParser;
use App\AI\QueryBuilder;
use App\AI\ResponseFormatter;
use App\AI\VoiceService;
use App\AI\PromptEngine;

class AiCopilotService
{
    private IntentParser $intentParser;
    private QueryBuilder $queryBuilder;
    private ResponseFormatter $formatter;
    private VoiceService $voiceService;
    private PromptEngine $promptEngine;

    public function __construct()
    {
        $this->intentParser  = new IntentParser();
        $this->queryBuilder  = new QueryBuilder();
        $this->formatter     = new ResponseFormatter();
        $this->voiceService  = new VoiceService();
        $this->promptEngine  = new PromptEngine();
    }

    /**
     * Process User Prompt Input and return AI Response Card
     */
    public function processPrompt(string $prompt, string $role, ?int $userUlpId = null, string $userName = 'User'): array
    {
        $intentData  = $this->intentParser->parseIntent($prompt);
        $queryResult = $this->queryBuilder->executeQuery($intentData, $userUlpId);
        $response    = $this->formatter->formatCardResponse($intentData, $queryResult);

        // Audit log AI interaction
        log_activity('AI_COPILOT_INTERACTION', "Prompt: '{$prompt}' -> Intent: " . ($intentData['intent'] ?? 'UNKNOWN'));

        return $response;
    }
}
