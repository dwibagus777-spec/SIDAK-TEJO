<?php

namespace App\Services\VoiceAI\Providers;

use App\Services\VoiceAI\Contracts\SpeechTTSInterface;

class ClientSpeechTTSProvider implements SpeechTTSInterface
{
    public function synthesize(string $text, string $language = 'id', array $options = []): array
    {
        // Client-side TTS instructs Web Speech API / Android TextToSpeech to speak text directly
        return [
            'audio_url'      => null,
            'audio_base64'   => null,
            'is_client_side' => true,
            'text'           => $text,
            'language'       => $language
        ];
    }

    public function getProviderName(): string
    {
        return 'client';
    }
}
