<?php

namespace App\AI;

class ResponseFormatter
{
    /**
     * Format query data into an AI Card response
     */
    public function formatCardResponse(array $intentData, array $queryResult): array
    {
        $intent = $intentData['intent'] ?? 'SUMMARY';

        if ($intent === 'NAVIGATE') {
            return [
                'type' => 'NAVIGATE',
                'title' => 'Membuka Menu Halaman...',
                'body' => 'Mengarahkan Anda langsung ke halaman ' . strtoupper($intentData['target']) . '.',
                'insight' => 'Rute navigasi diproses instan.',
                'confidence' => 98,
                'redirect' => $intentData['redirect'],
                'follow_up' => ['Apakah ada data lain yang ingin Anda cari?']
            ];
        }

        if ($intent === 'EMERGENCY_COUNT') {
            $cnt = $queryResult['count'] ?? 0;
            return [
                'type' => 'CARD',
                'title' => '🚨 Temuan Emergency Hari Ini',
                'body' => "Saat ini terdapat **{$cnt} pekerjaan Emergency** yang membutuhkan penanganan segera di lapangan.",
                'insight' => 'Disarankan pengerahan Tim PDKB & HAR Gardu untuk menyelesaikan pekerjaan emergency sebelum pukul 11:00 WIB.',
                'confidence' => 95,
                'action_url' => $queryResult['action_url'],
                'action_label' => $queryResult['action_label'],
                'follow_up' => [
                    'Apakah Anda ingin melihat persebaran lokasi di Peta GIS?',
                    'Apakah Anda ingin menerbitkan Work Order?'
                ]
            ];
        }

        if ($intent === 'HIGH_PRIORITY_COUNT') {
            $cnt = $queryResult['count'] ?? 0;
            return [
                'type' => 'CARD',
                'title' => '⚡ Temuan High Priority',
                'body' => "Terdeteksi **{$cnt} temuan High Priority** dengan SLA batas penanganan 3 hari.",
                'insight' => 'Prioritas penanganan pada Penyulang Klurak & Krian 04.',
                'confidence' => 92,
                'action_url' => $queryResult['action_url'],
                'action_label' => $queryResult['action_label'],
                'follow_up' => ['Ingin melihat daftar temuan High Priority?']
            ];
        }

        if ($intent === 'TOP_OFFICER') {
            $officers = $queryResult['top_officers'] ?? [];
            $names = [];
            foreach ($officers as $off) {
                $names[] = $off['created_by_name'] . ' (' . $off['total_input'] . ' temuan)';
            }
            $listStr = implode(', ', $names);

            return [
                'type' => 'CARD',
                'title' => '🏆 Top Petugas Inspeksi Teraktif',
                'body' => "Petugas teraktif bulan ini: **{$listStr}**.",
                'insight' => 'Apresiasi kinerja petugas dengan tingkat keandalan data tinggi.',
                'confidence' => 96,
                'action_url' => site_url('dashboard'),
                'action_label' => 'Buka Leaderboard Utama',
                'follow_up' => ['Apakah ingin melihat pencapaian target harian?']
            ];
        }

        // Default Summary Card Response
        $total = $queryResult['total_temuan'] ?? 0;
        $selesai = $queryResult['total_selesai'] ?? 0;

        return [
            'type' => 'CARD',
            'title' => '🤖 Ringkasan AI Copilot SIDAK TEJO',
            'body' => "Sistem mencatat **{$total} Total Temuan** dengan **{$selesai} Pekerjaan Selesai (Tuntas)**.",
            'insight' => 'Seluruh sistem beroperasi dalam kondisi optimal.',
            'confidence' => 90,
            'action_url' => site_url('dashboard'),
            'action_label' => 'Buka Dashboard',
            'follow_up' => [
                'Tampilkan Emergency hari ini',
                'Buka Peta GIS',
                'Buka AI Center'
            ]
        ];
    }
}
