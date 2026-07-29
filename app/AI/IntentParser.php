<?php

namespace App\AI;

class IntentParser
{
    /**
     * Parse natural language input (Indonesian, Javanese, English) into structured Intent
     */
    public function parseIntent(string $input): array
    {
        $text = strtolower(trim($input));

        // 1. Smart Navigation Intents
        if (str_contains($text, 'buka gis') || str_contains($text, 'peta') || str_contains($text, 'map')) {
            return ['intent' => 'NAVIGATE', 'target' => 'gis', 'redirect' => site_url('gis')];
        }
        if (str_contains($text, 'buka wo') || str_contains($text, 'work order')) {
            return ['intent' => 'NAVIGATE', 'target' => 'work-orders', 'redirect' => site_url('work-orders')];
        }
        if (str_contains($text, 'buka dashboard') || str_contains($text, 'home')) {
            return ['intent' => 'NAVIGATE', 'target' => 'dashboard', 'redirect' => site_url('dashboard')];
        }
        if (str_contains($text, 'buka data temuan') || str_contains($text, 'list temuan')) {
            return ['intent' => 'NAVIGATE', 'target' => 'temuan', 'redirect' => site_url('temuan')];
        }

        // 2. Query Intents
        if (str_contains($text, 'emergency') || str_contains($text, 'darurat') || str_contains($text, 'kritis')) {
            return ['intent' => 'EMERGENCY_COUNT', 'filter_prio' => 'EMERGENCY'];
        }
        if (str_contains($text, 'high priority') || str_contains($text, 'high') || str_contains($text, 'sla 3 hari')) {
            return ['intent' => 'HIGH_PRIORITY_COUNT', 'filter_prio' => 'HIGH'];
        }
        if (str_contains($text, 'petugas terbaik') || str_contains($text, 'top petugas') || str_contains($text, 'ranking petugas')) {
            return ['intent' => 'TOP_OFFICER'];
        }
        if (str_contains($text, 'hotspot') || str_contains($text, 'penyulang paling')) {
            return ['intent' => 'HOTSPOT_FEEDER'];
        }
        if (str_contains($text, 'melewati sla') || str_contains($text, 'terlambat') || str_contains($text, 'overdue')) {
            return ['intent' => 'SLA_BREACH'];
        }
        if (str_contains($text, 'asset paling berisiko') || str_contains($text, 'aset berisiko') || str_contains($text, 'risk asset')) {
            return ['intent' => 'RISK_ASSET'];
        }
        if (str_contains($text, 'pekerjaan saya') || str_contains($text, 'tugas saya')) {
            return ['intent' => 'MY_TASKS'];
        }
        if (str_contains($text, 'pekerjaan selesai') || str_contains($text, 'tuntas') || str_contains($text, 'selesai bulan ini')) {
            return ['intent' => 'COMPLETED_COUNT'];
        }
        if (str_contains($text, 'ulp') && (str_contains($text, 'performa') || str_contains($text, 'terbaik'))) {
            return ['intent' => 'ULP_PERFORMANCE'];
        }

        // Default: General Search & Summary Intent
        return ['intent' => 'SUMMARY', 'keyword' => $text];
    }
}
