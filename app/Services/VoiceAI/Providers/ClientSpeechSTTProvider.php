<?php

namespace App\Services\VoiceAI\Providers;

use App\Services\VoiceAI\Contracts\SpeechSTTInterface;

class ClientSpeechSTTProvider implements SpeechSTTInterface
{
    public function transcribe(string $audioSource, string $language = 'id'): array
    {
        // Client-side STT (Web Speech API or Android SpeechRecognizer) passes string directly
        return [
            'text'       => trim($audioSource),
            'confidence' => 0.98
        ];
    }

    public function getProviderName(): string
    {
        return 'client';
    }
}
