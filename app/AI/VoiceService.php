<?php

namespace App\AI;

class VoiceService
{
    /**
     * Prepare Web Speech Synthesis & Recognition Configuration
     */
    public function getVoiceConfig(): array
    {
        return [
            'supported'   => true,
            'lang'        => 'id-ID',
            'engine'      => 'WebSpeechAPI',
            'pitch'       => 1.0,
            'rate'        => 1.0,
            'auto_listen' => true,
        ];
    }
}
