<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Preventive Map Intelligence Service (Phase CC-02)
 *
 * Responsibilities:
 * - Aggregates 3-Layer Spatial DTO (Section Topology, Asset Health, Finding Evidence).
 * - Read-only exploration and selection data provider for Leaflet.js.
 * - Invariant: MAP_CLICK_SELECTION_ONLY.
 */
class PreventiveMapIntelligenceService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Get 3-Layer Spatial Intelligence DTO for a given Feeder ID.
     *
     * @param int $feederId
     * @return array
     */
    public function getFeederMapData(int $feederId = 1): array
    {
        $feeder = $this->db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
        $feederName = strtoupper(trim($feeder['nama_penyulang'] ?? 'BALUNG'));

        // Layer A: Sections on this Feeder
        $sections = $this->db->tableExists('sections')
            ? $this->db->table('sections')->where('penyulang_id', $feederId)->get()->getResultArray()
            : [];

        $sectionLayers = [];
        foreach ($sections as $sec) {
            $sId = (int)$sec['id'];
            $density = $this->db->table('temuan')
                                ->where('section_id', $sId)
                                ->whereIn('status', ['BELUM', 'OPEN', 'IN_PROGRESS', 'WAITING_EXECUTION'])
                                ->countAllResults();

            $tier = match (true) {
                $density >= 3 => 'CRITICAL_PREVENTIVE_ATTENTION',
                $density >= 1 => 'HIGH_RISK_RECURRENCE',
                default       => 'LOW_STABLE',
            };

            $sectionLayers[] = [
                'section_id'          => $sId,
                'section_name'        => $sec['nama_section'] ?? "SEC-{$sId}",
                'finding_density'     => $density,
                'preventive_risk_tier'=> $tier,
            ];
        }

        // Layer B: Assets on this Feeder
        $assets = $this->db->tableExists('assets')
            ? $this->db->table('assets')
                       ->select('id, kode_asset, nama_asset, jenis_asset, section_id, latitude, longitude, health_index')
                       ->where('penyulang_id', $feederId)
                       ->limit(25)
                       ->get()
                       ->getResultArray()
            : [];

        // Layer C: Active Findings on this Feeder
        $findings = $this->db->table('temuan')
                             ->select('id, nomor_temuan, jenis_temuan, prioritas, status, section_id, asset_id, latitude, longitude, detail_temuan')
                             ->where('penyulang_id', $feederId)
                             ->whereIn('status', ['BELUM', 'OPEN', 'IN_PROGRESS', 'WAITING_EXECUTION'])
                             ->limit(25)
                             ->get()
                             ->getResultArray();

        // Center Coordinate (fallback Sidoarjo center: -7.4523, 112.7165)
        $centerLat = !empty($findings[0]['latitude']) ? (float)$findings[0]['latitude'] : -7.4523;
        $centerLng = !empty($findings[0]['longitude']) ? (float)$findings[0]['longitude'] : 112.7165;

        return [
            'status'         => 'success',
            'feeder_id'      => $feederId,
            'feeder_name'    => $feederName,
            'map_center'     => ['lat' => $centerLat, 'lng' => $centerLng],
            'zoom_level'     => 14,
            'layer_sections' => $sectionLayers,
            'layer_assets'   => $assets,
            'layer_findings' => $findings,
            'governance'     => 'MAP_SELECTION_ONLY_ZERO_AUTONOMOUS_ACTIONS',
        ];
    }
}
