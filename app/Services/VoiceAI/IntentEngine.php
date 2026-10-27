<?php

namespace App\Services\VoiceAI;

use App\Services\VoiceAI\Contracts\AIProviderInterface;

class IntentEngine
{
    protected AIProviderInterface $aiProvider;

    public array $definedIntents = [
        'NAVIGATE_PAGE' => [
            'description' => 'Navigasi ke halaman aplikasi (misal: input temuan, dashboard, eviden, laporan, monitoring, KPI)',
            'keywords'    => ['buka', 'tampilkan', 'pindah', 'halaman', 'menu', 'go to', 'open', 'show', 'view']
        ],
        'SEARCH_TEMUAN' => [
            'description' => 'Pencarian data temuan inspeksi berdasarkan penyulang, ULP, nomor temuan, status, prioritas',
            'keywords'    => ['cari', 'goleki', 'search', 'lacak', 'temukne', 'find', 'nomor', 'stj-']
        ],
        'GENERATE_REPORT' => [
            'description' => 'Membuat/mengunduh laporan temuan (harian, mingguan, bulanan, ULP, hotspot)',
            'keywords'    => ['buat laporan', 'generate report', 'cetak laporan', 'download laporan', 'export laporan', 'laporan hari ini', 'laporan minggu ini', 'laporan bulan ini']
        ],
        'DATATABLE_FILTER' => [
            'description' => 'Memfilter tabel data temuan tanpa reload halaman',
            'keywords'    => ['filter', 'temuan minggu ini', 'temuan hari ini', 'temuan belum selesai', 'temuan emergency']
        ],
        'GET_EXECUTIVE_SUMMARY' => [
            'description' => 'Ringkasan narasi eksekutif AI pada dashboard',
            'keywords'    => ['ringkasan', 'summary', 'rekap', 'kondisi hari ini', 'ikhtisar', 'overview']
        ],
        'GET_SLA_NOTIFICATIONS' => [
            'description' => 'Pengecekan status SLA, overdue, dan temuan emergency',
            'keywords'    => ['sla', 'overdue', 'terlambat', 'emergency', 'sisa waktu']
        ],
        'GET_RANKING' => [
            'description' => 'Informasi ranking petugas dan ranking ULP',
            'keywords'    => ['ranking', 'peringkat', 'top 10', 'siapa ranking', 'terbaik', 'terbanyak']
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

        // 1. Report Intent
        if (str_contains($lowerText, 'laporan') || str_contains($lowerText, 'report')) {
            if (str_contains($lowerText, 'buat') || str_contains($lowerText, 'generate') || str_contains($lowerText, 'cetak') || str_contains($lowerText, 'download')) {
                return [
                    'intent'     => 'GENERATE_REPORT',
                    'params'     => ['query' => $userText],
                    'confidence' => 0.98
                ];
            }
        }

        // 2. Ranking Intent
        if (str_contains($lowerText, 'ranking') || str_contains($lowerText, 'peringkat') || str_contains($lowerText, 'terbaik') || str_contains($lowerText, 'top 10')) {
            return [
                'intent'     => 'GET_RANKING',
                'params'     => ['query' => $userText],
                'confidence' => 0.95
            ];
        }

        // 3. Specific Temuan Query / Filter Intent
        if (str_contains($lowerText, 'temuan')) {
            $temuanIntent = $this->parseTemuanNavigation($lowerText);
            if ($temuanIntent !== null) {
                return $temuanIntent;
            }
        }

        // 4. Navigation Keyword Check
        if (str_starts_with($lowerText, 'buka ') || str_starts_with($lowerText, 'open ') || str_starts_with($lowerText, 'go to ')) {
            $target = $this->extractNavigationTarget($lowerText);
            return [
                'intent'     => 'NAVIGATE_PAGE',
                'params'     => ['target_page' => $target['page'], 'url' => $target['url']],
                'confidence' => 0.98
            ];
        }

        // 5. Quick Keyword Search / SLA / Summary Check
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
                    if ($intentKey === 'GET_SLA_NOTIFICATIONS') {
                        return [
                            'intent'     => 'GET_SLA_NOTIFICATIONS',
                            'params'     => ['query' => $userText],
                            'confidence' => 0.92
                        ];
                    }
                    if ($intentKey === 'GET_EXECUTIVE_SUMMARY') {
                        return [
                            'intent'     => 'GET_EXECUTIVE_SUMMARY',
                            'params'     => ['query' => $userText],
                            'confidence' => 0.92
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
        if (str_contains($text, 'input') || str_contains($text, 'tambah')) {
            return ['page' => 'Input Temuan', 'url' => site_url('temuan/create')];
        }
        if (str_contains($text, 'dashboard')) {
            return ['page' => 'Dashboard Executive', 'url' => site_url('executive-dashboard')];
        }
        if (str_contains($text, 'eviden') || str_contains($text, 'trafo') || str_contains($text, 'kubikel')) {
            return ['page' => 'Eviden', 'url' => site_url('eviden')];
        }
        if (str_contains($text, 'laporan') || str_contains($text, 'report')) {
            return ['page' => 'Laporan', 'url' => site_url('laporan')];
        }
        if (str_contains($text, 'monitoring') || str_contains($text, 'peta') || str_contains($text, 'gis')) {
            return ['page' => 'Monitoring GIS', 'url' => site_url('executive-dashboard#tab-monitoring')];
        }
        if (str_contains($text, 'kpi')) {
            return ['page' => 'Executive KPI', 'url' => site_url('executive-dashboard#tab-executive')];
        }
        return ['page' => 'Data Temuan', 'url' => site_url('temuan')];
    }

    /**
     * Parse explicit temuan navigation intent with strict priority & location/feeder filters.
     */
    public function parseTemuanNavigation(string $text): ?array
    {
        $prioritas = null;
        if (preg_match('/\b(emergency|darurat|kritis)\b/i', $text)) {
            $prioritas = 'EMERGENCY';
        } elseif (preg_match('/\b(high|tinggi|urgent)\b/i', $text)) {
            $prioritas = 'HIGH';
        } elseif (preg_match('/\b(medium|sedang)\b/i', $text)) {
            $prioritas = 'MEDIUM';
        } elseif (preg_match('/\b(low|rendah)\b/i', $text)) {
            $prioritas = 'LOW';
        }

        $penyulangId = null;
        $ulpId = null;
        $namaPenyulang = null;

        try {
            $db = \Config\Database::connect();
            $feeders = $db->table('penyulang')
                ->select('id, nama_penyulang, ulp_id')
                ->get()
                ->getResultArray();

            // Match longest feeder name first to prevent substring collision
            usort($feeders, function ($a, $b) {
                return strlen($b['nama_penyulang']) - strlen($a['nama_penyulang']);
            });

            foreach ($feeders as $f) {
                $fName = strtolower(trim((string)$f['nama_penyulang']));
                if (empty($fName)) continue;
                $fRegex = preg_quote($fName, '/');
                $fRegex = preg_replace('/\s+/', '\s*', $fRegex);
                if (preg_match('/\b' . $fRegex . '\b/i', $text)) {
                    $penyulangId = (int)$f['id'];
                    $ulpId = !empty($f['ulp_id']) ? (int)$f['ulp_id'] : null;
                    $namaPenyulang = $f['nama_penyulang'];
                    break;
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[IntentEngine] Feeder lookup failed: ' . $e->getMessage());
        }

        // Dedicated fallback for known Banjar Kemantren variations if DB query was unavailable
        if (!$penyulangId && preg_match('/banjar\s*kemantren/i', $text)) {
            $penyulangId = 15;
            $ulpId = 1;
            $namaPenyulang = 'BANJAR KEMANTREN';
        }

        return [
            'intent'     => 'NAVIGATE_TEMUAN',
            'params'     => [
                'prioritas'      => $prioritas,
                'penyulang_id'   => $penyulangId,
                'ulp_id'         => $ulpId,
                'nama_penyulang' => $namaPenyulang
            ],
            'confidence' => 0.99
        ];
    }
}
