<?php

namespace App\Services\VoiceAI\Contracts;

interface SpeechTTSInterface
{
    /**
     * Synthesize text into speech audio stream or URL
     *
     * @param string $text Text to synthesize
     * @param string $language Target language code ('id', 'jv', 'en')
     * @param array $options Additional options (voice_name, pitch, rate)
     * @return array Standardized TTS result ['audio_url' => ?string, 'audio_base64' => ?string, 'is_client_side' => bool]
     */
    public function synthesize(string $text, string $language = 'id', array $options = []): array;

    /**
     * Get TTS provider name
     */
    public function getProviderName(): string;
}
