<?php

namespace App\Services\VoiceAI;

use App\Services\VoiceAI\Contracts\AIProviderInterface;
use App\Services\VoiceAI\Contracts\SpeechSTTInterface;
use App\Services\VoiceAI\Contracts\SpeechTTSInterface;
use App\Services\VoiceAI\Contracts\SearchProviderInterface;
use App\Services\VoiceAI\Providers\GeminiAIProvider;
use App\Services\VoiceAI\Providers\OpenAIAIProvider;
use App\Services\VoiceAI\Providers\OllamaAIProvider;
use App\Services\VoiceAI\Providers\ClientSpeechSTTProvider;
use App\Services\VoiceAI\Providers\ClientSpeechTTSProvider;
use App\Services\VoiceAI\Providers\SystemSearchProvider;
use Config\VoiceAI as VoiceAIConfig;

class VoiceAIFactory
{
    protected VoiceAIConfig $config;

    public function __construct(?VoiceAIConfig $config = null)
    {
        $this->config = $config ?? config('VoiceAI');
    }

    public function makeAIProvider(?string $name = null): AIProviderInterface
    {
        $providerName = strtolower($name ?? $this->config->activeAIProvider);
        $providerConfig = $this->config->providers[$providerName] ?? [];

        return match ($providerName) {
            'openai'    => new OpenAIAIProvider($providerConfig),
            'ollama'    => new OllamaAIProvider($providerConfig),
            'gemini'    => new GeminiAIProvider($providerConfig),
            default     => new GeminiAIProvider($providerConfig),
        };
    }

    public function makeSTTProvider(?string $name = null): SpeechSTTInterface
    {
        $providerName = strtolower($name ?? $this->config->activeSTTProvider);

        return match ($providerName) {
            'client'  => new ClientSpeechSTTProvider(),
            default   => new ClientSpeechSTTProvider(),
        };
    }

    public function makeTTSProvider(?string $name = null): SpeechTTSInterface
    {
        $providerName = strtolower($name ?? $this->config->activeTTSProvider);

        return match ($providerName) {
            'client'  => new ClientSpeechTTSProvider(),
            default   => new ClientSpeechTTSProvider(),
        };
    }

    public function makeSearchProvider(?string $name = null): SearchProviderInterface
    {
        $providerName = strtolower($name ?? $this->config->activeSearchProvider);

        return match ($providerName) {
            'system'  => new SystemSearchProvider(),
            default   => new SystemSearchProvider(),
        };
    }
}
