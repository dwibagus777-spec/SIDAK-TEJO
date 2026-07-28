<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\VoiceAI\VoiceAIFactory;
use App\Services\VoiceAI\VoiceAIService;
use CodeIgniter\HTTP\ResponseInterface;

class VoiceAIApiController extends BaseController
{
    protected VoiceAIService $voiceAIService;

    public function __construct()
    {
        $factory = new VoiceAIFactory();
        $this->voiceAIService = new VoiceAIService(
            $factory->makeAIProvider(),
            $factory->makeSTTProvider(),
            $factory->makeTTSProvider(),
            $factory->makeSearchProvider()
        );
    }

    /**
     * POST /api/v1/voice-ai/process
     * Accepts text/audio input and returns Voice AI intent, action, text, and TTS data
     */
    public function process(): ResponseInterface
    {
        try {
            $json = $this->request->getJSON(true) ?? [];
            
            $sessionId = $json['session_id'] ?? $this->request->getPost('session_id') ?? 'sess_' . session_id();
            $text      = $json['text'] ?? $this->request->getPost('text') ?? '';
            $language  = $json['language'] ?? $this->request->getPost('language') ?? 'id';
            $audioFile = $this->request->getFile('audio') ?? null;

            $params = [
                'session_id' => $sessionId,
                'text'       => $text,
                'language'   => $language,
                'audio_file' => $audioFile
            ];

            $result = $this->voiceAIService->processRequest($params);
            return $this->response->setStatusCode(200)->setJSON($result);

        } catch (\Throwable $e) {
            log_message('error', 'VoiceAIApiController Error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => 'Terjadi kesalahan sistem saat memproses Voice AI Assistant.',
                'detail'  => $e->getMessage()
            ]);
        }
    }
}
