<?php

namespace App\AI;

class PromptEngine
{
    /**
     * Build role-scoped prompt context for AI Copilot
     */
    public function buildPromptContext(string $role, string $userName): string
    {
        $roleLabel = get_role_label($role);
        return "Anda adalah SIDAK AI Copilot, Asisten Digital Resmi SIDAK TEJO untuk user {$userName} (Role: {$roleLabel}). Berikan jawaban yang presisi, profesional, dan berbasis data.";
    }
}
