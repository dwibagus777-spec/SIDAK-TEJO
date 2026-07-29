<?php

namespace App\AI;

class RecommendationEngine
{
    /**
     * Generate Explainable AI Recommendation with explicit decision reasoning
     */
    public function generateRecommendation(array $data): array
    {
        $riskScore = (float)($data['risk_score'] ?? 0);
        $jenisTemuan = strtoupper($data['jenis_temuan'] ?? 'HOTSPOT');
        $prioritas   = strtoupper($data['prioritas'] ?? 'MEDIUM');
        $status      = strtoupper($data['status'] ?? 'BELUM');
        $pelaksana   = strtoupper($data['pelaksana'] ?? 'INSPEKSI');
        $temuanCount = (int)($data['temuan_count'] ?? 1);
        $assetAge    = (int)($data['asset_age'] ?? 5);

        // Determine recommended pelaksana
        $recPelaksana = match($jenisTemuan) {
            'ROW'       => 'HAR ROW',
            'HOTSPOT'   => ($prioritas === 'EMERGENCY') ? 'PDKB' : 'HAR GARDU',
            'KONSTRUKSI'=> 'HAR KONSTRUKSI',
            default     => $pelaksana
        };

        // Rationale bullets for Explainable AI
        $reasons = [];

        if ($riskScore >= 76) {
            $recommendationText = "Segera lakukan penanganan darurat tanpa penundaan! Aset berada pada kondisi KRITIS.";
            $reasons[] = "Risk Score sangat tinggi (" . $riskScore . " - CRITICAL).";
        } elseif ($riskScore >= 51) {
            $recommendationText = "Jadwalkan pemeliharaan prioritas tinggi minggu ini untuk mencegah padam beruntun.";
            $reasons[] = "Risk Score berada pada kategori HIGH (" . $riskScore . ").";
        } else {
            $recommendationText = "Lakukan pemeliharaan rutin terencana sesuai jadwal bulanan.";
            $reasons[] = "Risk Score dalam batas aman (" . $riskScore . " - LOW/MEDIUM).";
        }

        if ($prioritas === 'EMERGENCY') {
            $reasons[] = "Prioritas temuan masuk kategori EMERGENCY (SLA 24 jam).";
        }
        if ($temuanCount > 3) {
            $reasons[] = "Terdeteksi akumulasi " . $temuanCount . " temuan aktif pada aset/wilayah ini.";
        }
        if ($assetAge > 10) {
            $reasons[] = "Aset telah beroperasi selama " . $assetAge . " tahun (kategori penuaan komponen).";
        }

        $reasons[] = "Direkomendasikan eksekusi oleh tim: " . $recPelaksana;

        return [
            'recommendation_text'   => $recommendationText,
            'recommended_pelaksana' => $recPelaksana,
            'priority_level'        => ($riskScore >= 76 || $prioritas === 'EMERGENCY') ? 'URGENT' : 'STANDARD',
            'reasons'               => $reasons,
        ];
    }
}
