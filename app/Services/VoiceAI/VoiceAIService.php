<?php

namespace App\Services\VoiceAI;

use App\Services\VoiceAI\Contracts\AIProviderInterface;
use App\Services\VoiceAI\Contracts\SpeechSTTInterface;
use App\Services\VoiceAI\Contracts\SpeechTTSInterface;
use App\Services\VoiceAI\Contracts\SearchProviderInterface;
use App\Models\AiLogModel;
use App\Repositories\TemuanRepository;

class VoiceAIService
{
    protected AIProviderInterface $aiProvider;
    protected SpeechSTTInterface $sttProvider;
    protected SpeechTTSInterface $ttsProvider;
    protected SearchProviderInterface $searchProvider;
    protected IntentEngine $intentEngine;
    protected TemuanRepository $temuanRepo;

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
        $this->temuanRepo     = new TemuanRepository();
    }

    public function processRequest(array $params): array
    {
        $sessionId = $params['session_id'] ?? 'sess_' . session_id();
        $language  = $params['language'] ?? 'id';
        $userText  = trim($params['text'] ?? '');
        $audioFile = $params['audio_file'] ?? null;
        $channel   = $params['channel'] ?? 'voice';

        $session  = session();
        $userId   = $session->get('user_id') ?: 1;
        $userName = $session->get('user_name') ?: 'User';
        $userRole = strtolower((string)($session->get('user_role') ?: 'administrator'));
        $userUlp  = $session->get('user_ulp_id');

        // 1. Voice-to-Text (STT) if audio uploaded
        if (!empty($audioFile)) {
            $sttResult = $this->sttProvider->transcribe($audioFile, $language);
            $userText  = $sttResult['text'] ?? '';
        }

        if (empty($userText)) {
            return [
                'success'       => false,
                'message'       => 'Suara atau teks perintah tidak terdeteksi.',
                'response_text' => 'Maaf, perintah tidak terdengar. Silakan ucapkan lagi atau gunakan kata kunci Halo SIDAK.'
            ];
        }

        // 2. Conversation Memory
        $memory = new ConversationMemory($sessionId);
        $memory->addMessage('user', $userText);

        // 3. Intent Detection & NLU
        $intentResult = $this->intentEngine->parse($userText);
        $intent       = $intentResult['intent'] ?? 'GENERAL_QA';

        $action       = null;
        $responseText = '';
        $actionType   = 'NONE';

        // 4. Intent Execution Switch
        if ($intent === 'NAVIGATE_PAGE') {
            $actionType   = 'NAVIGATE';
            $targetPage   = $intentResult['params']['target_page'] ?? 'Data Temuan';
            $targetUrl    = $intentResult['params']['url'] ?? site_url('temuan');
            $responseText = "Siap, membuka halaman {$targetPage}.";
            $action = [
                'type' => 'NAVIGATE',
                'url'  => $targetUrl
            ];
        } elseif ($intent === 'CREATE_TEMUAN_TRIGGER') {
            $actionType   = 'NAVIGATE';
            $responseText = "Membuka form input temuan baru.";
            $action = [
                'type' => 'NAVIGATE',
                'url'  => site_url('temuan/create')
            ];
        } elseif ($intent === 'GENERATE_REPORT') {
            $actionType   = 'GENERATE_REPORT';
            $responseText = "Menyiapkan dan membuka halaman laporan temuan.";
            $action = [
                'type' => 'NAVIGATE',
                'url'  => site_url('laporan')
            ];
        } elseif ($intent === 'GET_RANKING') {
            $actionType = 'RANKING';
            $analytics  = $this->temuanRepo->getExecutiveAnalyticsData([], $userRole, $userUlp);
            $topOff     = $analytics['top_input_officers'][0]['created_by_name'] ?? 'Officer';
            $topUlp     = $analytics['ulp_ranking'][0]['nama_ulp'] ?? 'ULP Unit';
            $responseText = "Peringkat pertama officer input terbanyak saat ini adalah {$topOff}, dan ULP dengan temuan terbanyak adalah {$topUlp}.";
            $action = [
                'type' => 'SHOW_RANKING',
                'data' => [
                    'top_officer' => $topOff,
                    'top_ulp'     => $topUlp
                ]
            ];
        } elseif ($intent === 'GET_SLA_NOTIFICATIONS') {
            $actionType = 'SLA_STATUS';
            $analytics  = $this->temuanRepo->getExecutiveAnalyticsData([], $userRole, $userUlp);
            $overdue    = $analytics['kpi']['overdue'] ?? 0;
            $emerg      = $analytics['sla']['details']['EMERGENCY']['overdue'] ?? 0;
            $responseText = "Status SLA hari ini: terdapat {$overdue} temuan overdue terlambat, diantaranya {$emerg} prioritas Emergency.";
            $action = [
                'type' => 'SLA_INFO',
                'data' => $analytics['sla']
            ];
        } elseif ($intent === 'GET_EXECUTIVE_SUMMARY') {
            $actionType   = 'SUMMARY';
            $responseText = $this->generateExecutiveSummaryText($userRole, $userUlp);
            $action = [
                'type' => 'SHOW_SUMMARY',
                'text' => $responseText
            ];
        } elseif ($intent === 'SEARCH_TEMUAN') {
            $actionType    = 'SEARCH';
            $searchResults = $this->searchProvider->searchSystemData($userText);
            $count         = count($searchResults);
            if ($count > 0) {
                $first = $searchResults[0];
                $responseText = "Ditemukan {$count} temuan terkait. Hasil teratas: {$first['title']}.";
            } else {
                $responseText = "Tidak ditemukan data temuan dengan kata kunci tersebut di database.";
            }
            $action = [
                'type'    => 'SEARCH_RESULTS',
                'results' => $searchResults
            ];
        } else {
            // General QA & Natural Language Knowledge synthesis
            $actionType    = 'GENERAL_QA';
            $searchResults = $this->searchProvider->searchSystemData($userText);
            $context       = !empty($searchResults) ? "\nData Sistem Terkait:\n" . json_encode($searchResults) : "";

            $systemPrompt = "Anda adalah AI Assistant SIDAK TEJO (Sistem Data dan Tindak Lanjut Temuan Inspeksi PLN UP3 Sidoarjo). "
                . "Pengguna bernama {$userName} dengan role {$userRole}. Jawablah singkat (maksimal 3 kalimat), tepat, dan ramah dalam bahasa yang digunakan pengguna (Indonesia/Jawa/Inggris)." . $context;

            $history  = $memory->getHistory();
            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($history as $h) {
                $messages[] = ['role' => $h['role'], 'content' => $h['content']];
            }

            $aiRes        = $this->aiProvider->chat($messages);
            $responseText = $aiRes['text'] ?? 'Maaf, sistem tidak dapat memproses pertanyaan tersebut saat ini.';
        }

        // 5. Store Assistant Response to Memory
        $memory->addMessage('assistant', $responseText, $intent);

        // 6. Log Conversation to Database (FITUR 15)
        try {
            $aiLogModel = new AiLogModel();
            $aiLogModel->insert([
                'user_id'      => $userId,
                'user_name'    => $userName,
                'user_role'    => $userRole,
                'channel'      => $channel,
                'user_command' => $userText,
                'intent'       => $intent,
                'ai_response'  => $responseText,
                'action_type'  => $actionType,
                'created_at'   => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to log AI conversation: ' . $e->getMessage());
        }

        // 7. Text-to-Speech (TTS)
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
                'search' => $this->searchProvider->getProviderName(),
            ]
        ];
    }

    /**
     * Generate Natural Language Executive Summary (FITUR 7)
     */
    public function generateExecutiveSummaryText(string $role, ?int $ulpId = null): string
    {
        $analytics = $this->temuanRepo->getExecutiveAnalyticsData([], $role, $ulpId);
        $kpi       = $analytics['kpi'];
        $sla       = $analytics['sla'];

        $total   = number_format($kpi['total_temuan'] ?? 0);
        $hariIni = number_format($kpi['temuan_hari_ini'] ?? 0);
        $selesai = number_format($kpi['selesai'] ?? 0);
        $overdue = number_format($kpi['overdue'] ?? 0);
        $emerg   = $sla['details']['EMERGENCY']['overdue'] ?? 0;

        $topPelaksana = 'YANTEK';
        if (!empty($analytics['charts']['pelaksana'])) {
            $topPelaksana = $analytics['charts']['pelaksana'][0]['pelaksana'] ?? 'YANTEK';
        }

        return "Hari ini terdapat {$hariIni} temuan baru dari total {$total} temuan. "
             . "Sebanyak {$selesai} temuan telah selesai dikerjakan, sementara {$overdue} temuan mengalami overdue SLA (termasuk {$emerg} Emergency). "
             . "Pelaksana {$topPelaksana} saat ini memiliki beban pekerjaan tertinggi.";
    }

    /**
     * Get Proactive Smart Notifications (FITUR 8)
     */
    public function getSmartNotifications(string $role, ?int $ulpId = null): array
    {
        $analytics = $this->temuanRepo->getExecutiveAnalyticsData([], $role, $ulpId);
        $kpi       = $analytics['kpi'];
        $sla       = $analytics['sla'];

        $notifications = [];

        $emergOverdue = $sla['details']['EMERGENCY']['overdue'] ?? 0;
        if ($emergOverdue > 0) {
            $notifications[] = [
                'type'    => 'EMERGENCY',
                'title'   => 'Temuan Emergency Overdue!',
                'message' => "Terdapat {$emergOverdue} temuan Emergency yang melewati SLA 24 jam dan belum ditangani.",
                'icon'    => 'fas fa-bolt text-danger'
            ];
        }

        $totalOverdue = $kpi['overdue'] ?? 0;
        if ($totalOverdue > 5) {
            $notifications[] = [
                'type'    => 'SLA_WARNING',
                'title'   => 'Peringatan SLA Terlambat',
                'message' => "Total {$totalOverdue} pekerjaan telah melebihi batas waktu SLA.",
                'icon'    => 'fas fa-clock text-warning'
            ];
        }

        $achHarian = $kpi['ach_harian'] ?? 0;
        if ($achHarian < 50) {
            $notifications[] = [
                'type'    => 'TARGET',
                'title'   => 'Target Inspeksi Harian',
                'message' => "Pencapaian target harian baru {$achHarian}%. Tingkatkan inspeksi di lapangan.",
                'icon'    => 'fas fa-bullseye text-info'
            ];
        }

        return $notifications;
    }
}
