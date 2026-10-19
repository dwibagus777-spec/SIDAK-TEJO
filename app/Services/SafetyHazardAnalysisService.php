<?php

namespace App\Services;

/** Explainable K2/K3 hazard suggestions based on finding text and known context. */
class SafetyHazardAnalysisService
{
    public const VERSION = 'K2K3-RULES-2026.10.1';

    private const SOURCES = [
        [
            'title' => 'Permen ESDM No. 10 Tahun 2021 tentang Keselamatan Ketenagalistrikan',
            'url'   => 'https://jdih.esdm.go.id/dokumen/view?id=2153',
        ],
        [
            'title' => 'Permenaker No. 12 Tahun 2015 tentang K3 Listrik di Tempat Kerja',
            'url'   => 'https://jdih.kemnaker.go.id/peraturan/detail/610/peraturan-menteri-nomor-12-tahun-2015',
        ],
        [
            'title' => 'Permenaker No. 33 Tahun 2015 tentang Perubahan atas Permenaker No. 12 Tahun 2015',
            'url'   => 'https://jdih.kemnaker.go.id/peraturan/detail/1156/peraturan-menteri-nomor-33-tahun-2015',
        ],
        [
            'title' => 'Permenaker No. 9 Tahun 2016 tentang K3 dalam Pekerjaan pada Ketinggian',
            'url'   => 'https://jdih.kemnaker.go.id/peraturan/detail/1210/peraturan-menteri-nomor-9-tahun-2016',
        ],
        [
            'title' => 'PLN: Keselamatan, Kesehatan dan Keamanan Kerja',
            'url'   => 'https://www.pln.co.id/governance-id/occupational-health-and-safety-id',
        ],
    ];

    public function analyze(array $finding, int $historicalSimilarCount = 0, array $historicalFindings = []): array
    {
        $text = mb_strtolower(implode(' ', array_filter([
            $finding['jenis_temuan'] ?? '',
            $finding['detail_temuan'] ?? '',
            $finding['konduktor'] ?? '',
            $finding['material'] ?? '',
            $finding['pelaksana'] ?? '',
            $finding['nama_asset'] ?? '',
        ])));

        $hazards = [];
        $add = static function (string $key, string $name, string $category, string $trigger, string $level, array $controls) use (&$hazards): void {
            if (!isset($hazards[$key])) {
                $hazards[$key] = [
                    'hazard' => $name,
                    'category' => $category,
                    'trigger' => $trigger,
                    'level' => $level,
                    'controls' => $controls,
                    'needs_field_verification' => true,
                ];
            }
        };

        if (preg_match('/row|pohon|dahan|ranting|vegetasi|pemangkasan|rabas/', $text)) {
            $add('electrical_contact', 'Kontak vegetasi dengan jaringan dan paparan listrik', 'K2', 'Temuan menyebut ROW, pohon, dahan, atau vegetasi dekat jaringan.', 'HIGH', ['Verifikasi kondisi jaringan dan batas aman melalui SOP/izin kerja yang berlaku.', 'Pekerjaan ditangani personel berwenang dengan pengendalian K2/K3 dan APD sesuai SOP.']);
            $add('falling_branch', 'Dahan atau material jatuh mengenai pekerja/publik', 'K3', 'Pekerjaan atau kondisi vegetasi dapat melibatkan benda jatuh.', 'MEDIUM', ['Tetapkan area steril dan pengamanan publik sebelum pekerjaan.', 'Periksa arah jatuh, alat, komunikasi, dan kondisi cuaca dalam JSA/toolbox meeting.']);
        }

        if (preg_match('/isolator|pin|insulator/', $text)) {
            $add('electrical_equipment', 'Kegagalan isolasi atau paparan listrik', 'K2', 'Deskripsi menyebut isolator atau pin rusak.', 'HIGH', ['Petugas berwenang memverifikasi status peralatan dan kondisi kelistrikan sesuai SOP PLN.', 'Jangan menyimpulkan jaringan aman dari deskripsi temuan saja.']);
            $add('falling_object', 'Alat atau komponen jatuh saat pemeriksaan/perbaikan', 'K3', 'Perbaikan komponen jaringan dapat melibatkan alat dan posisi kerja di atas.', 'MEDIUM', ['Amankan area di bawah titik kerja.', 'Periksa metode kerja, pengikatan alat, dan APD dalam JSA/toolbox meeting.']);
        }

        if (preg_match('/phbtr|panel|kubikel|hubung bagi/', $text)) {
            $add('electrical_panel', 'Paparan listrik atau kejadian busur listrik pada panel', 'K2', 'Deskripsi menyebut panel/PHBTR/kubikel bermasalah.', 'HIGH', ['Personel kompeten mengidentifikasi sumber energi dan menerapkan prosedur kerja serta izin yang berlaku.', 'Konfirmasi kebutuhan isolasi energi dengan pengawas berwenang.']);
        }

        if (preg_match('/tinggi|tiang|panjat|naik|ketinggian/', $text) || in_array(strtoupper((string)($finding['pelaksana'] ?? '')), ['HAR ROW', 'HAR CRANE', 'PDKB'], true)) {
            $add('work_at_height', 'Jatuh dari ketinggian', 'K3', 'Jenis pekerjaan atau petugas mengindikasikan kemungkinan kerja di ketinggian.', 'HIGH', ['Pastikan asesmen kerja di ketinggian, kompetensi, sistem pencegah jatuh, dan rencana penyelamatan sesuai Permenaker/SOP.', 'Verifikasi kondisi titik kerja sebelum mulai.']);
        }

        if (preg_match('/hewan|ular|tawon|lebah|sarang|serangga/', $text)) {
            $add('animal', 'Gigitan atau sengatan hewan/serangga', 'K3', 'Deskripsi temuan menyebut hewan, sarang, atau serangga.', 'MEDIUM', ['Periksa area oleh petugas dan gunakan pengendalian sesuai jenis bahaya.', 'Hentikan pekerjaan dan minta dukungan yang sesuai bila kondisi tidak terkendali.']);
        }

        if (preg_match('/crane|angkat|alat berat|lalu lintas|jalan raya/', $text)) {
            $add('plant_traffic', 'Tertabrak, tertimpa, atau terjepit alat/kendaraan', 'K3', 'Deskripsi menyebut alat angkat, alat berat, atau lokasi lalu lintas.', 'MEDIUM', ['Buat zona aman, pengaturan lalu lintas, dan komunikasi petugas sesuai rencana kerja.', 'Verifikasi kapasitas/pergerakan alat melalui personel yang berwenang.']);
        }

        $priority = strtoupper((string)($finding['prioritas'] ?? ''));
        if (in_array($priority, ['EMERGENCY', 'HIGH'], true)) {
            $add('priority_review', 'Pemeriksaan segera oleh pengawas', 'K2/K3', 'Prioritas temuan tersimpan sebagai ' . $priority . '.', $priority === 'EMERGENCY' ? 'HIGH' : 'MEDIUM', ['Tinjau ulang bahaya dan kontrol sebelum penugasan atau pekerjaan dimulai.']);
        }

        if ($historicalSimilarCount > 0) {
            $history = ['count' => $historicalSimilarCount, 'basis' => 'Temuan lain dengan ULP dan jenis temuan yang sama; hanya sebagai konteks pengulangan.'];
        } else {
            $history = ['count' => 0, 'basis' => 'Tidak ada temuan serupa terdeteksi pada query terbatas untuk ULP dan jenis temuan ini.'];
        }
        $patternCounts = [];
        foreach ($historicalFindings as $pastFinding) {
            foreach ($this->analyze($pastFinding)['hazards'] as $pastHazard) {
                $label = $pastHazard['hazard'];
                $patternCounts[$label] = ($patternCounts[$label] ?? 0) + 1;
            }
        }
        arsort($patternCounts);
        $history['hazard_patterns'] = array_map(static fn($label, $count) => ['hazard' => $label, 'count' => $count], array_keys($patternCounts), array_values($patternCounts));

        return [
            'version' => self::VERSION,
            'generated_at' => date('c'),
            'risk_label' => empty($hazards) ? 'PERLU VERIFIKASI' : (count(array_filter($hazards, static fn($h) => $h['level'] === 'HIGH')) ? 'PERLU TINJAUAN SEGERA' : 'PERLU VERIFIKASI'),
            'hazards' => array_values($hazards),
            'historical_context' => $history,
            'notice' => 'Saran awal berbasis teks temuan. Bukan pengganti JSA/HIRADC, SOP PLN, izin kerja, atau keputusan pengawas K2/K3.',
            'sources' => self::SOURCES,
        ];
    }
}
