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
        $detail   = strtolower(trim((string)($input['detail_temuan'] ?? '')));

        // Parse accessories payload if provided
        $accessories = $input['accessories'] ?? [];
        if (empty($accessories) && !empty($input['structured_accessories_json'])) {
            $decoded = json_decode((string)$input['structured_accessories_json'], true);
            if (is_array($decoded)) {
                $accessories = $decoded;
            }
        }

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
            'EMERGENCY' => '3 Hari',
            'HIGH'      => '7 Hari',
            'MEDIUM'    => '31 Hari',
            default     => '3 Bulan'
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

        // 7. Equipment & Safety Tools
        $tools = ['Tangga 12 Meter', 'Truck Crane / Mobil PDKB', 'Hotstick 20KV', 'Grounding Set 20KV', 'Helm Safety & Safety Belt'];

        // 8. CR-ACCESSORY-PHASE-01: Semantic Reasoning based on JTM Accessories & Phase Context
        foreach ($accessories as $acc) {
            $code = strtoupper((string)($acc['accessory_code'] ?? ($acc['code'] ?? '')));
            $status = strtoupper((string)($acc['status'] ?? ($acc['observation']['status'] ?? 'ADA')));
            $cond = strtoupper((string)($acc['condition'] ?? ($acc['observation']['condition'] ?? 'BAIK')));
            $qty = (int)($acc['qty'] ?? ($acc['configuration']['qty'] ?? 1));
            $unit = (string)($acc['unit'] ?? ($acc['configuration']['unit'] ?? 'buah'));
            $positions = $acc['positions'] ?? ($acc['configuration']['positions'] ?? []);
            if (is_string($positions)) {
                $positions = array_filter(array_map('trim', explode(',', $positions)));
            }
            $posStr = !empty($positions) ? ' (' . implode('-', $positions) . ')' : '';

            if ($status === 'ADA') {
                if ($code === 'FIOHL') {
                    $checklist[] = "Verifikasi Indikator Trip FIOHL Fasa{$posStr}";
                    $tools[] = 'FIOHL Reset Stick / Tool';
                    $causes[] = 'Arus Lebih Transient / Gangguan SUTM';
                } elseif (str_contains($code, 'FCO')) {
                    if ($cond === 'RUSAK' || $cond === 'PERLU_PENGGANTIAN' || str_contains($detail, 'fco')) {
                        $matName = ($code === 'FCO_BRANCH') ? "Fuse Cut Out Branch/Lateral 24 kV [{$qty} {$unit}]" : "Fuse Cut Out Switch 24 kV [{$qty} {$unit}]";
                        if (!in_array($matName, $materials)) $materials[] = $matName;
                        $checklist[] = "Pemeriksaan Fuse Link & Tabung FCO Fasa{$posStr}";
                        $tools[] = 'Telescopic Hotstick 20 kV';
                    }
                } elseif ($code === 'ARRESTER') {
                    if ($cond === 'RUSAK' || $cond === 'PERLU_PENGGANTIAN' || str_contains($detail, 'arrester') || str_contains($detail, 'la')) {
                        $matName = "Polymer Lightning Arrester 24 kV [{$qty} {$unit}]";
                        if (!in_array($matName, $materials)) $materials[] = $matName;
                        $checklist[] = "Pengukuran Tahanan Pentanahan Arrester Jaringan Fasa{$posStr}";
                        $tools[] = 'Earth Ground Tester';
                        $causes[] = 'Surge Sambaran Petir / Overvoltage';
                    }
                }
            }
        }

        // 9. Impact Assessment
        $impacts = [
            'Pelanggan Terdampak' => '500 - 1,200 Pelanggan',
            'Kemungkinan Padam'  => $prio === 'EMERGENCY' ? 'TINGGI (90%)' : 'SEDANG (40%)',
            'Risiko Trip'        => 'Risiko Trip Feeder 20KV',
            'Risiko OCR/DGR'     => 'Proteksi OCR / DGR Terpicu'
        ];

        // 10. SOP PLN
        $sop = "SOP-PLN-INSP-2026: Ikuti Standar K3 Listrik 20KV, gunakan APD level 3, pastikan grounding terpasang sebelum menyentuh konduktor.";

        return [
            'risk_level'     => $risk,
            'risk_color'     => $riskColor,
            'sla_time'       => $slaTime,
            'team'           => $team,
            'checklist'      => array_values(array_unique($checklist)),
            'materials'      => array_values(array_unique($materials)),
            'causes'         => array_values(array_unique($causes)),
            'impacts'        => $impacts,
            'sop'            => $sop,
            'tools'          => array_values(array_unique($tools)),
            'confidence'     => 95
        ];
    }
}
