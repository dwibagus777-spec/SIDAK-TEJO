<?php

namespace App\AI;

class SmartRecommendationEngine
{
    /**
     * Generate Smart AI Recommendation based on input fields (Rule-based ML-ready)
     */
    public function generateRecommendation(array $input): array
    {
        $jenis    = strtolower(trim((string)($input['jenis_temuan'] ?? '')));
        $prio     = strtoupper(trim((string)($input['prioritas'] ?? 'MEDIUM')));
        $potensi  = strtolower(trim((string)($input['potensi_gangguan'] ?? '')));

        // 1. Risk Estimation
        $risk = 'SEDANG'; $riskColor = '#f59e0b';
        if ($prio === 'EMERGENCY' || str_contains($potensi, 'kritis') || str_contains($jenis, 'hotspot')) {
            $risk = 'KRITIS'; $riskColor = '#ef4444';
        } elseif ($prio === 'HIGH' || str_contains($potensi, 'tinggi')) {
            $risk = 'TINGGI'; $riskColor = '#f97316';
        } elseif ($prio === 'LOW') {
            $risk = 'RENDAH'; $riskColor = '#10b981';
        }

        // 2. SLA Completion Time Estimate
        $slaTime = match($prio) {
            'EMERGENCY' => '0 - 24 Jam',
            'HIGH'      => '2 Hari',
            'MEDIUM'    => '7 Hari',
            default     => '14 Hari'
        };

        // 3. Team Recommendation
        $team = 'HAR KONSTRUKSI';
        if (str_contains($jenis, 'row') || str_contains($jenis, 'pohon')) {
            $team = 'HAR ROW';
        } elseif (str_contains($jenis, 'hotspot') || str_contains($jenis, 'thermovision')) {
            $team = 'TIM PDKB SPECIALIST';
        } elseif (str_contains($jenis, 'gardu') || str_contains($jenis, 'trafo')) {
            $team = 'HAR GARDU';
        }

        // 4. Digital Checklist Tasks
        $checklist = [
            'Pastikan Safety Briefing & APD Lengkap',
            'Ambil Foto Sebelum (Before)',
            'Ambil Foto Proses Perbaikan',
            'Ambil Foto Sesudah (After)',
            'Verifikasi Titik Koordinat GPS',
            'Catat Pemakaian Material Digital'
        ];

        // 5. Recommended Materials
        $materials = ['Isolator Tumpu 20KV', 'PG Clamp', 'Spacer 20KV', 'Grounding Wire', 'Kabel AAAC'];
        if (str_contains($jenis, 'gardu')) {
            $materials = ['Minyak Trafo Shell Diala', 'Bushing Trafo 20KV', 'Fuse Cut Out (FCO)', 'Arrester 20KV'];
        } elseif (str_contains($jenis, 'row')) {
            $materials = ['Tali Tambang Safety', 'Gergaji Mesin (Chainsaw)', 'Sabuk Pengaman High Altitude'];
        }

        // 6. Cause Analysis
        $causes = ['Pohon & Ranting ROW', 'Korosi & Usia Aset', 'Petir / Overvoltage', 'Hewan / Benda Asing', 'Konstruksi Miring'];

        // 7. Impact Assessment
        $impacts = [
            'Pelanggan Terdampak' => '500 - 1,200 Pelanggan',
            'Kemungkinan Padam'  => $prio === 'EMERGENCY' ? 'TINGGI (90%)' : 'SEDANG (40%)',
            'Risiko Trip'        => 'Risiko Trip Feeder 20KV',
            'Risiko OCR/DGR'     => 'Proteksi OCR / DGR Terpicu'
        ];

        // 8. SOP PLN
        $sop = "SOP-PLN-INSP-2026: Ikuti Standar K3 Listrik 20KV, gunakan APD level 3, pastikan grounding terpasang sebelum menyentuh konduktor.";

        // 9. Equipment & Safety Tools
        $tools = ['Tangga 12 Meter', 'Truck Crane / Mobil PDKB', 'Hotstick 20KV', 'Grounding Set 20KV', 'Helm Safety & Safety Belt'];

        return [
            'risk_level'     => $risk,
            'risk_color'     => $riskColor,
            'sla_time'       => $slaTime,
            'team'           => $team,
            'checklist'      => $checklist,
            'materials'      => $materials,
            'causes'         => $causes,
            'impacts'        => $impacts,
            'sop'            => $sop,
            'tools'          => $tools,
            'confidence'     => 94
        ];
    }
}
