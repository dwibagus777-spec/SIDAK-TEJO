<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class VoiceAI extends BaseConfig
{
    /**
     * Active AI LLM Provider
     * Supported: 'gemini', 'openai', 'deepseek', 'ollama', 'openrouter', 'azure'
     */
    public string $activeAIProvider = 'gemini';

    /**
     * Active Speech-to-Text Provider
     * Supported: 'client' (Browser/Android Native), 'whisper'
     */
    public string $activeSTTProvider = 'client';

    /**
     * Active Text-to-Speech Provider
     * Supported: 'client' (Browser/Android Native), 'google_tts', 'elevenlabs'
     */
    public string $activeTTSProvider = 'client';

    /**
     * Active Search / Knowledge Provider
     * Supported: 'system' (MySQL Query Builder), 'elastic'
     */
    public string $activeSearchProvider = 'system';

    /**
     * API Credentials & Configurations
     */
    public array $providers = [
        'gemini' => [
            'api_key' => '',
            'model'   => 'gemini-1.5-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/'
        ],
        'openai' => [
            'api_key' => '',
            'model'   => 'gpt-4o-mini',
            'base_url' => 'https://api.openai.com/v1/'
        ],
        'deepseek' => [
            'api_key' => '',
            'model'   => 'deepseek-chat',
            'base_url' => 'https://api.deepseek.com/v1/'
        ],
        'ollama' => [
            'model'   => 'llama3',
            'base_url' => 'http://localhost:11434/api/'
        ],
        'openrouter' => [
            'api_key' => '',
            'model'   => 'google/gemini-flash-1.5',
            'base_url' => 'https://openrouter.ai/api/v1/'
        ]
    ];
}
