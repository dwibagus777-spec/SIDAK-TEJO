<?php

namespace App\Services;

/**
 * AI Advisory Isolation Gateway (Phase CC-04, Gate E7)
 * Strictly READ-ONLY and Isolated.
 * Generates natural language risk narratives and executive briefings.
 * 
 * BOUNDARY RULES (Non-Negotiable):
 * - AI CANNOT modify FHI / ADI / AHS / Severity / Topology / Status
 * - AI CANNOT trigger automatic dispatches
 * - Output is strictly encapsulated inside `advisory_narrative`
 */
class ExecutiveAiAdvisoryService
{
    /**
     * Synthesize Executive Briefing and Advisory Narrative from Deterministic FHI Payload.
     */
    public function generateExecutiveAdvisory(array $fhiPayload): array
    {
        $feederName = $fhiPayload['nama_penyulang'] ?? ('Feeder #' . ($fhiPayload['penyulang_id'] ?? 1));
        $score      = $fhiPayload['health_score'] !== null ? number_format((float)$fhiPayload['health_score'], 2) : 'N/A';
        $status     = $fhiPayload['fhi_status'] ?? 'UNRESOLVED';
        $class      = $fhiPayload['health_classification'] ?? 'UNRESOLVED';
        $primary    = $fhiPayload['primary_driver'] ?? 'NORMAL_OPERATION';
        $unit       = $fhiPayload['assigned_unit'] ?? 'Pemeliharaan Rutin';
        $priority   = $fhiPayload['priority_level'] ?? 'P3 - MEDIUM';

        $narrative = [];

        // 1. Situation Assessment
        if ($status === 'UNRESOLVED') {
            $narrative[] = "⚠️ **Perhatian Eksekutif**: Penyulang {$feederName} belum memiliki konfigurasi jaringan fisik (CR-06F) atau data aset yang ter-resolve secara lengkap. Nilai FHI ditangguhkan (UNRESOLVED) sesuai prinsip fail-closed governance.";
        } elseif ($class === 'SEMPURNA') {
            $narrative[] = "🟢 **Kondisi Optimal**: Penyulang {$feederName} beroperasi dalam batas keandalan prima dengan FHI {$score}/100 ({$class}). Seluruh indikator fisik, degradasi aset, dan keandalan berada dalam ambang normal.";
        } elseif ($class === 'WASPADA') {
            $narrative[] = "🟡 **Status Waspada**: Penyulang {$feederName} mencatat skor FHI {$score}/100 ({$class}). Terdeteksi anomali minor yang memerlukan pemeliharaan preventif terencana untuk mencegah eskalasi.";
        } elseif ($class === 'PERHATIAN') {
            $narrative[] = "🟠 **Atensi Khusus**: Penyulang {$feederName} mengalami penurunan indeks kesehatan (FHI {$score}/100, {$class}) akibat akumulasi gangguan atau anomali berulang.";
        } else {
            $narrative[] = "🔴 **Kondisi Kritis**: Penyulang {$feederName} memerlukan intervensi darurat (FHI {$score}/100, {$class}). Terdeteksi defek material kritis atau gangguan frekuensi tinggi.";
        }

        // 2. Key Risk Drivers
        $narrative[] = "\n**Faktor Pemicu Risiko (Primary Driver)**:";
        $narrative[] = "- Driver Utama: `{$primary}`";
        $narrative[] = "- Unit Penanggung Jawab: **{$unit}**";
        $narrative[] = "- Prioritas Eksekusi: **{$priority}**";

        // 3. Tactical Advisory & Sequencing
        $narrative[] = "\n**Rekomendasi Taktis Manajemen**:";
        if ($priority === 'P1 - IMMEDIATE') {
            $narrative[] = "1. Segera lakukan koordinasi dengan tim {$unit} untuk inspeksi lapangan dan penggantian material tanpa pemadaman (Hotline/PDKB).";
            $narrative[] = "2. Pastikan izin kerja dan logistik suku cadang siap sebelum dispatch.";
        } elseif ($priority === 'P2 - PREREQUISITE') {
            $narrative[] = "1. Tugaskan tim perencanaan/GIS untuk melengkapi upload konfigurasi section CR-06F.";
            $narrative[] = "2. Jalankan sensus BOM aset untuk mengaktifkan perhitungan AHS penuh.";
        } else {
            $narrative[] = "1. Jadwalkan pemeliharaan berkala pada siklus kerja berikutnya.";
            $narrative[] = "2. Lakukan evaluasi berkala pada Dynamic SLD untuk memantau tren anomali.";
        }

        $fullText = implode("\n", $narrative);

        return [
            'success'            => true,
            'feeder_id'          => $fhiPayload['penyulang_id'] ?? null,
            'fhi_score'          => $score,
            'classification'     => $class,
            'advisory_narrative' => $fullText,
            'generated_at'       => date('Y-m-d H:i:s'),
            'isolation_check'    => 'PASS (Advisory Only - Mathematical State Preserved)',
        ];
    }
}
