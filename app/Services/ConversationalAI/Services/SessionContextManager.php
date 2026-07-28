<?php

namespace App\Services\ConversationalAI\Services;

use Config\Services;

class SessionContextManager
{
    protected string $sessionId;

    public function __construct(string $sessionId = 'default_session')
    {
        $this->sessionId = $sessionId ?: 'default_session';
    }

    public function resolveAnaphora(string $text): string
    {
        $session = Services::session();
        $state = $session->get('conv_state_' . $this->sessionId) ?: [];
        $lastEntity = $state['last_entity'] ?? null;

        if (!$lastEntity) {
            return $text;
        }

        // Replace pronouns "itu", "tersebut", "dia" with last active entity
        $pronouns = ['/\bitu\b/i', '/\btersebut\b/i', '/\bdia\b/i'];
        return preg_replace($pronouns, $lastEntity, $text);
    }

    public function updateActiveEntity(string $entityName): void
    {
        $session = Services::session();
        $key = 'conv_state_' . $this->sessionId;

        $state = $session->get($key) ?: [];
        $state['last_entity'] = $entityName;
        $state['updated_at']  = date('Y-m-d H:i:s');

        $session->set($key, $state);
    }

    public function getActiveEntity(): ?string
    {
        $session = Services::session();
        $state = $session->get('conv_state_' . $this->sessionId) ?: [];
        return $state['last_entity'] ?? null;
    }
}
