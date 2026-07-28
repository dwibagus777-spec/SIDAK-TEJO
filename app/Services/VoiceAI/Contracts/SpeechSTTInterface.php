<?php

namespace App\Services\VoiceAI\Contracts;

interface SpeechSTTInterface
{
    /**
     * Transcribe audio file or binary stream to text
     *
     * @param string $audioSource File path, URL, or base64 audio data
     * @param string $language Target language code ('id', 'jv', 'en', 'auto')
     * @return array Standardized STT result ['text' => string, 'confidence' => float]
     */
    public function transcribe(string $audioSource, string $language = 'id'): array;

    /**
     * Get STT provider name
     */
    public function getProviderName(): string;
}
