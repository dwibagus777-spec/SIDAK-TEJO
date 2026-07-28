<?php

namespace App\Services\VoiceAI;

use Config\Services;

class ConversationMemory
{
    protected string $sessionId;
    protected int $maxHistory;

    public function __construct(string $sessionId = 'global', int $maxHistory = 10)
    {
        $this->sessionId  = $sessionId ?: 'global';
        $this->maxHistory = $maxHistory;
    }

    public function addMessage(string $role, string $content, ?string $intent = null): void
    {
        $session = Services::session();
        $key = 'voice_ai_history_' . $this->sessionId;

        $history = $session->get($key) ?: [];
        $history[] = [
            'role'       => $role,
            'content'    => $content,
            'intent'     => $intent,
            'timestamp'  => date('Y-m-d H:i:s')
        ];

        if (count($history) > $this->maxHistory) {
            $history = array_slice($history, -$this->maxHistory);
        }

        $session->set($key, $history);
    }

    public function getHistory(): array
    {
        $session = Services::session();
        $key = 'voice_ai_history_' . $this->sessionId;
        return $session->get($key) ?: [];
    }

    public function clear(): void
    {
        $session = Services::session();
        $key = 'voice_ai_history_' . $this->sessionId;
        $session->remove($key);
    }
}
