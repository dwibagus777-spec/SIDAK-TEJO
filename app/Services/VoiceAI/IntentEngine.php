<?php

namespace App\Services\VoiceAI;

use App\Services\VoiceAI\Contracts\AIProviderInterface;

class IntentEngine
{
    protected AIProviderInterface $aiProvider;

    public array $definedIntents = [
        'NAVIGATE_PAGE' => [
            'description' => 'Navigasi ke halaman tertentu di aplikasi (misal: halaman temuan, eviden, laporan, dashboard)',
            'keywords'    => ['buka', 'tampilkan', 'pindah', 'halaman', 'menu', 'nyang', 'go to', 'open', 'show']
        ],
        'SEARCH_TEMUAN' => [
            'description' => 'Pencarian data temuan inspeksi berdasarkan penyulang/ULP/status/kata kunci',
            'keywords'    => ['cari', 'goleki', 'search', 'lacak', 'temukne', 'find']
        ],
        'CHECK_STATUS_ULP' => [
            'description' => 'Mengecek status dan statistik temuan per ULP',
            'keywords'    => ['piro', 'berapa', 'jumlah', 'statistik', 'status', 'total', 'count', 'how many']
        ],
        'CREATE_TEMUAN_TRIGGER' => [
            'description' => 'Membuka form input temuan baru',
            'keywords'    => ['input', 'tambah', 'gawe', 'create', 'add', 'nambah']
        ],
        'GENERAL_QA' => [
            'description' => 'Pertanyaan umum teknis/SOP/pengetahuan umum AI',
            'keywords'    => []
        ]
    ];

    public function __construct(AIProviderInterface $aiProvider)
    {
        $this->aiProvider = $aiProvider;
    }

    public function parse(string $userText): array
    {
        $lowerText = strtolower(trim($userText));

        // Quick Keyword Rule-based Detection
        foreach ($this->definedIntents as $intentKey => $def) {
            foreach ($def['keywords'] as $kw) {
                if (str_contains($lowerText, $kw)) {
                    if ($intentKey === 'NAVIGATE_PAGE') {
                        $target = $this->extractNavigationTarget($lowerText);
                        return [
                            'intent'     => 'NAVIGATE_PAGE',
                            'params'     => ['target_page' => $target['page'], 'url' => $target['url']],
                            'confidence' => 0.95
                        ];
                    }
                    if ($intentKey === 'SEARCH_TEMUAN') {
                        return [
                            'intent'     => 'SEARCH_TEMUAN',
                            'params'     => ['query' => $userText],
                            'confidence' => 0.90
                        ];
                    }
                    if ($intentKey === 'CREATE_TEMUAN_TRIGGER') {
                        return [
                            'intent'     => 'CREATE_TEMUAN_TRIGGER',
                            'params'     => ['url' => site_url('temuan/create')],
                            'confidence' => 0.95
                        ];
                    }
                }
            }
        }

        // Fallback to LLM NLU Intent Detection
        return $this->aiProvider->detectIntent($userText, $this->definedIntents);
    }

    private function extractNavigationTarget(string $text): array
    {
        if (str_contains($text, 'eviden') || str_contains($text, 'kubikel') || str_contains($text, 'trafo')) {
            return ['page' => 'Eviden', 'url' => site_url('eviden')];
        }
        if (str_contains($text, 'laporan') || str_contains($text, 'report')) {
            return ['page' => 'Laporan', 'url' => site_url('laporan')];
        }
        if (str_contains($text, 'penyulang')) {
            return ['page' => 'Penyulang', 'url' => site_url('penyulang')];
        }
        if (str_contains($text, 'section') || str_contains($text, 'ruas')) {
            return ['page' => 'Section', 'url' => site_url('sections')];
        }
        if (str_contains($text, 'ulp') || str_contains($text, 'unit')) {
            return ['page' => 'ULP', 'url' => site_url('ulps')];
        }
        return ['page' => 'Data Temuan', 'url' => site_url('temuan')];
    }
}
