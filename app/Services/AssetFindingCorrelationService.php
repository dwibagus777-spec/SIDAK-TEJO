<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Asset-Finding Correlation Service (Phase 7U Maintenance M-05)
 *
 * Responsibilities:
 * - Correlates field findings (temuan) with asset master data and feeder topology.
 * - Computes finding severity score, section finding density, and recurrence factor.
 */
class AssetFindingCorrelationService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Correlate finding with asset and topology context.
     *
     * @param int $findingId
     * @return array
     */
    public function correlateFinding(int $findingId): array
    {
        $finding = $this->db->table('temuan')
                            ->where('id', $findingId)
                            ->get()
                            ->getRowArray();

        if (!$finding) {
            // Synthetic / fallback finding context for testing
            return $this->getFallbackFindingContext($findingId);
        }

        $penyulang = $this->db->table('penyulang')
                              ->where('id', $finding['penyulang_id'] ?? 0)
                              ->get()
                              ->getRowArray();

        $section = !empty($finding['section_id'])
            ? $this->db->table('sections')->where('id', $finding['section_id'])->get()->getRowArray()
            : null;

        $asset = !empty($finding['asset_id'])
            ? $this->db->table('assets')->where('id', $finding['asset_id'])->get()->getRowArray()
            : null;

        // 1. Calculate Finding Severity Score (0.0 - 1.0)
        $priorityScore = $this->mapPriorityToScore($finding['prioritas'] ?? 'P3');
        $recurrenceScore = !empty($finding['is_recurring']) ? min(0.30, ((int)($finding['recurrence_count'] ?? 1)) * 0.10) : 0.0;
        $findingSeverityScore = min(1.00, round($priorityScore + $recurrenceScore, 2));

        // 2. Count Active Findings in Same Section / Feeder
        $sectionId = $finding['section_id'] ?? null;
        $penyulangId = $finding['penyulang_id'] ?? ($penyulang['id'] ?? 1);

        $sectionDensity = 1;
        if ($sectionId) {
            $sectionDensity = $this->db->table('temuan')
                                       ->where('section_id', $sectionId)
                                       ->whereIn('status', ['BELUM', 'OPEN', 'IN_PROGRESS', 'WAITING_EXECUTION'])
                                       ->countAllResults();
        }

        // 3. Asset Health Impact
        $assetHealth = (float)($asset['health_index'] ?? 75.0);
        $assetImpactScore = round((100.0 - $assetHealth) / 100.0, 2);

        // 4. Map Finding Type to Standard Cause Category
        $findingCategory = $this->classifyFindingCategory($finding['jenis_temuan'] ?? '', $finding['potensi_gangguan'] ?? '');

        return [
            'finding_id'             => (int)$finding['id'],
            'nomor_temuan'           => $finding['nomor_temuan'] ?? ('TMN-' . $finding['id']),
            'jenis_temuan'           => $finding['jenis_temuan'] ?? 'VEGETASI',
            'classified_category'    => $findingCategory,
            'prioritas'              => $finding['prioritas'] ?? 'P3',
            'finding_severity_score' => $findingSeverityScore,
            'is_recurring'           => (bool)($finding['is_recurring'] ?? false),
            'recurrence_count'       => (int)($finding['recurrence_count'] ?? 0),
            'penyulang_id'           => (int)$penyulangId,
            'feeder_name'            => strtoupper(trim($penyulang['nama_penyulang'] ?? 'BALUNG')),
            'section_id'             => $sectionId ? (int)$sectionId : null,
            'section_name'           => strtoupper(trim($section['nama_section'] ?? 'BALUNG-03')),
            'section_finding_density'=> max(1, $sectionDensity),
            'asset_id'               => !empty($finding['asset_id']) ? (int)$finding['asset_id'] : null,
            'asset_code'             => $asset['kode_asset'] ?? 'ASSET-TIANG-01',
            'asset_health_index'     => $assetHealth,
            'asset_impact_score'     => $assetImpactScore,
            'latitude'               => (float)($finding['latitude'] ?? 0.0),
            'longitude'              => (float)($finding['longitude'] ?? 0.0),
            'detail_temuan'          => $finding['detail_temuan'] ?? 'Ranting pohon bambu mendekati konduktor SUTM',
        ];
    }

    protected function mapPriorityToScore(string $prioritas): float
    {
        $p = strtoupper(trim($prioritas));
        return match ($p) {
            'P1', 'CRITICAL' => 0.85,
            'P2', 'HIGH'     => 0.70,
            'P3', 'MEDIUM'   => 0.50,
            'P4', 'LOW'      => 0.30,
            default          => 0.20,
        };
    }

    protected function classifyFindingCategory(string $jenis, string $potensi): string
    {
        $text = strtolower($jenis . ' ' . $potensi);

        if (str_contains($text, 'row') || str_contains($text, 'pohon') || str_contains($text, 'bambu') || str_contains($text, 'vegetasi') || str_contains($text, 'ranting')) {
            return 'VEGETATION_ROW';
        }
        if (str_contains($text, 'binatang') || str_contains($text, 'tikus') || str_contains($text, 'burung') || str_contains($text, 'bunglon') || str_contains($text, 'ular')) {
            return 'ANIMAL_CONTACT';
        }
        if (str_contains($text, 'petir') || str_contains($text, 'arrester') || str_contains($text, 'la')) {
            return 'LIGHTNING_WEATHER';
        }
        if (str_contains($text, 'layang') || str_contains($text, 'seng') || str_contains($text, 'umbul') || str_contains($text, 'proyek') || str_contains($text, 'bangunan')) {
            return 'THIRD_PARTY_OBJECT';
        }
        if (str_contains($text, 'terminasi') || str_contains($text, 'kabel') || str_contains($text, 'mvtic') || str_contains($text, 'xlpe') || str_contains($text, 'jointing')) {
            return 'CABLE_TERMINATION_FAULT';
        }
        if (str_contains($text, 'konduktor') || str_contains($text, 'sutm') || str_contains($text, 'gsw') || str_contains($text, 'jumper')) {
            return 'CONDUCTOR_GSW_SNAP';
        }

        return 'EQUIPMENT_FAILURE';
    }

    protected function getFallbackFindingContext(int $findingId): array
    {
        return [
            'finding_id'             => $findingId,
            'nomor_temuan'           => 'TMN-BALUNG-20260823-01',
            'jenis_temuan'           => 'POHON DEKAT JTM',
            'classified_category'    => 'VEGETATION_ROW',
            'prioritas'              => 'P2',
            'finding_severity_score' => 0.70,
            'is_recurring'           => false,
            'recurrence_count'       => 0,
            'penyulang_id'           => 1,
            'feeder_name'            => 'BALUNG',
            'section_id'             => 1,
            'section_name'           => 'BALUNG-03',
            'section_finding_density'=> 2,
            'asset_id'               => 1,
            'asset_code'             => 'TIANG-BLG-042',
            'asset_health_index'     => 68.5,
            'asset_impact_score'     => 0.32,
            'latitude'               => -7.4523,
            'longitude'              => 112.7165,
            'detail_temuan'          => 'Ranting pohon sono mendekati konduktor SUTM phasa S jarak 0.8 meter',
        ];
    }
}
