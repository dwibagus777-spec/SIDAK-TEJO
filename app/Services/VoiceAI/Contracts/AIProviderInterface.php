<?php

namespace App\Services\VoiceAI\Contracts;

interface AIProviderInterface
{
    /**
     * Send chat completion request to LLM provider
     *
     * @param array $messages Array of ['role' => 'user'|'assistant'|'system', 'content' => string]
     * @param array $options Additional options (temperature, max_tokens, etc.)
     * @return array Standardized response ['text' => string, 'raw' => mixed]
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Detect user intent and extract parameters using LLM
     *
     * @param string $userQuery User spoken/written text
     * @param array $availableIntents List of system intent definitions
     * @return array Standardized intent ['intent' => string, 'params' => array, 'confidence' => float]
     */
    public function detectIntent(string $userQuery, array $availableIntents): array;

    /**
     * Get provider identifier name
     */
    public function getProviderName(): string;
}
