<?php

namespace App\Services\VoiceAI;

use App\Services\VoiceAI\Contracts\AIProviderInterface;
use App\Services\VoiceAI\Contracts\SpeechSTTInterface;
use App\Services\VoiceAI\Contracts\SpeechTTSInterface;
use App\Services\VoiceAI\Contracts\SearchProviderInterface;

class VoiceAIService
{
    protected AIProviderInterface $aiProvider;
    protected SpeechSTTInterface $sttProvider;
    protected SpeechTTSInterface $ttsProvider;
    protected SearchProviderInterface $searchProvider;
    protected IntentEngine $intentEngine;

    public function __construct(
        AIProviderInterface $aiProvider,
        SpeechSTTInterface $sttProvider,
        SpeechTTSInterface $ttsProvider,
        SearchProviderInterface $searchProvider
    ) {
        $this->aiProvider     = $aiProvider;
        $this->sttProvider    = $sttProvider;
        $this->ttsProvider    = $ttsProvider;
        $this->searchProvider = $searchProvider;
        $this->intentEngine   = new IntentEngine($aiProvider);
    }

    public function processRequest(array $params): array
    {
        $sessionId = $params['session_id'] ?? 'default_session';
        $language  = $params['language'] ?? 'id';
        $userText  = trim($params['text'] ?? '');
        $audioFile = $params['audio_file'] ?? null;

        // 1. Voice-to-Text (STT) if audio is passed
        if (!empty($audioFile)) {
            $sttResult = $this->sttProvider->transcribe($audioFile, $language);
            $userText  = $sttResult['text'] ?? '';
        }

        if (empty($userText)) {
            return [
                'success'       => false,
                'message'       => 'Perintah atau rekaman suara tidak terdengar.',
                'response_text' => 'Maaf, suara tidak terdeteksi. Silakan coba lagi.'
            ];
        }

        // 2. Conversation Memory
        $memory = new ConversationMemory($sessionId);
        $memory->addMessage('user', $userText);

        // 3. Intent Detection & NLU
        $intentResult = $this->intentEngine->parse($userText);
        $intent = $intentResult['intent'] ?? 'GENERAL_QA';

        $action = null;
        $responseText = '';

        // 4. Action Execution Based on Intent
        if ($intent === 'NAVIGATE_PAGE') {
            $targetPage = $intentResult['params']['target_page'] ?? 'Data Temuan';
            $targetUrl  = $intentResult['params']['url'] ?? site_url('temuan');
            $responseText = "Membuka halaman {$targetPage}.";
            $action = [
                'type' => 'NAVIGATE',
                'url'  => $targetUrl
            ];
        } elseif ($intent === 'CREATE_TEMUAN_TRIGGER') {
            $responseText = "Membuka form input temuan baru.";
            $action = [
                'type' => 'NAVIGATE',
                'url'  => site_url('temuan/create')
            ];
        } elseif ($intent === 'SEARCH_TEMUAN') {
            $searchResults = $this->searchProvider->searchSystemData($userText);
            $count = count($searchResults);
            if ($count > 0) {
                $responseText = "Ditemukan {$count} data temuan yang sesuai dengan pencarian Anda.";
            } else {
                $responseText = "Tidak ditemukan data temuan yang sesuai dengan kata kunci tersebut.";
            }
            $action = [
                'type'    => 'SEARCH_RESULTS',
                'results' => $searchResults
            ];
        } else {
            // General QA & LLM Knowledge Synthesis
            $searchResults = $this->searchProvider->searchSystemData($userText);
            $context = "";
            if (!empty($searchResults)) {
                $context = "\nData Sistem Terkait:\n" . json_encode($searchResults);
            }

            $systemPrompt = "Anda adalah AI Assistant SIDAK TEJO (Sistem Data dan Tindak Lanjut Temuan Inspeksi Sidoarjo PLN). "
                . "Jawablah dengan ramah, lugas, profesional, dan dalam bahasa yang digunakan pengguna (Indonesia/Jawa/Inggris)." . $context;

            $history = $memory->getHistory();
            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($history as $h) {
                $messages[] = ['role' => $h['role'], 'content' => $h['content']];
            }

            $aiRes = $this->aiProvider->chat($messages);
            $responseText = $aiRes['text'] ?? 'Maaf, sistem tidak dapat memproses pertanyaan saat ini.';
        }

        // Store Assistant Response to Memory
        $memory->addMessage('assistant', $responseText, $intent);

        // 5. Text-to-Speech (TTS)
        $ttsResult = $this->ttsProvider->synthesize($responseText, $language);

        return [
            'success'       => true,
            'session_id'    => $sessionId,
            'user_text'     => $userText,
            'intent'        => $intent,
            'confidence'    => $intentResult['confidence'] ?? 1.0,
            'response_text' => $responseText,
            'action'        => $action,
            'tts'           => $ttsResult,
            'provider'      => [
                'ai'     => $this->aiProvider->getProviderName(),
                'stt'    => $this->sttProvider->getProviderName(),
                'tts'    => $this->ttsProvider->getProviderName(),
                'search' => $this->searchProvider->getProviderName(),
            ]
        ];
    }
}
