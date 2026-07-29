<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\VoiceAI\VoiceAIFactory;
use App\Services\VoiceAI\VoiceAIService;
use App\Models\AiLogModel;
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
            $channel   = $json['channel'] ?? $this->request->getPost('channel') ?? 'voice';
            $audioFile = $this->request->getFile('audio') ?? null;

            $params = [
                'session_id' => $sessionId,
                'text'       => $text,
                'language'   => $language,
                'channel'    => $channel,
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

    /**
     * GET /api/v1/voice-ai/summary
     * Returns AI executive summary text
     */
    public function summary(): ResponseInterface
    {
        $session  = session();
        $userRole = strtolower((string)($session->get('user_role') ?: 'administrator'));
        $userUlp  = $session->get('user_ulp_id');

        $summaryText = $this->voiceAIService->generateExecutiveSummaryText($userRole, $userUlp);

        return $this->response->setStatusCode(200)->setJSON([
            'success'      => true,
            'timestamp'    => date('Y-m-d H:i:s'),
            'summary_text' => $summaryText
        ]);
    }

    /**
     * GET /api/v1/voice-ai/notifications
     * Returns smart proactive notifications
     */
    public function notifications(): ResponseInterface
    {
        $session  = session();
        $userRole = strtolower((string)($session->get('user_role') ?: 'administrator'));
        $userUlp  = $session->get('user_ulp_id');

        $notifs = $this->voiceAIService->getSmartNotifications($userRole, $userUlp);

        return $this->response->setStatusCode(200)->setJSON([
            'success'       => true,
            'timestamp'     => date('Y-m-d H:i:s'),
            'notifications' => $notifs
        ]);
    }

    /**
     * GET /api/v1/voice-ai/logs
     * Returns AI conversation logs (FITUR 15)
     */
    public function logs(): ResponseInterface
    {
        $aiLogModel = new AiLogModel();
        $logs = $aiLogModel->orderBy('id', 'DESC')->limit(50)->findAll();

        return $this->response->setStatusCode(200)->setJSON([
            'success' => true,
            'logs'    => $logs
        ]);
    }
}
