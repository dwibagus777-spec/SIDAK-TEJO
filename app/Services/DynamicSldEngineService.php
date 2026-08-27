<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Dynamic Single Line Diagram (SLD) Engine (CR-06D)
 * Governed by Gate 5:
 * - Topology Truth <- Active Network Configuration Version
 * - Visual Health Overlay <- Observation / Inspection / Defect State
 */
class DynamicSldEngineService
{
    protected BaseConnection $db;
    protected NetworkConfigurationService $configService;

    public function __construct(?BaseConnection $db = null, ?NetworkConfigurationService $configService = null)
    {
        $this->db            = $db ?? \Config\Database::connect();
        $this->configService = $configService ?? new NetworkConfigurationService($this->db);
    }

    /**
     * Render Complete Dynamic SLD Payload for a Section.
     * Separates Topology Truth from Visual Health Overlay (Gate 5).
     */
    public function renderSectionSld(int $sectionId, ?string $asOfDate = null): array
    {
        // 1. Resolve Configuration (Current Active or Historical Time-Travel)
        if ($asOfDate !== null) {
            $config = $this->configService->getConfigurationAt($sectionId, $asOfDate);
        } else {
            $config = $this->configService->getActiveConfiguration($sectionId);
        }

        if (!$config) {
            return [
                'success'        => false,
                'section_id'     => $sectionId,
                'message'        => 'Tidak ada konfigurasi aktif untuk section ini.',
                'topology_truth' => null,
                'health_overlay' => null,
            ];
        }

        // 2. Build Topology Truth (Structure, Segments, Conductor Sizes, Distance)
        $topologyTruth = $this->buildTopologyTruth($config);

        // 3. Build Visual Health Overlay (Inspection Status, Broken LA, Hotspot Findings)
        $healthOverlay = $this->buildVisualHealthOverlay($sectionId, $config);

        return [
            'success'                => true,
            'section_id'             => $sectionId,
            'configuration_version'  => $config['version_number'] ?? 1,
            'verification_status'    => $config['verification_status'] ?? 'ACTIVE',
            'effective_from'         => $config['effective_from'] ?? null,
            'effective_to'           => $config['effective_to'] ?? null,
            'topology_truth'         => $topologyTruth,
            'health_overlay'         => $healthOverlay,
            'rendered_at'            => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Build Topology Truth from Network Configuration
     */
    private function buildTopologyTruth(array $config): array
    {
        $segments = [];
        foreach ($config['conductors'] ?? [] as $c) {
            $segments[] = [
                'segment_id'     => $c['id'],
                'segment_label'  => $c['segment_label'] ?? ('Segment #' . $c['sequence_order']),
                'sequence_order' => (int)$c['sequence_order'],
                'conductor_code' => $c['material_code'] ?? 'UNKNOWN',
                'conductor_name' => $c['nama_material'] ?? 'Unknown Conductor',
                'length_m'       => $c['length_m'] !== null ? (float)$c['length_m'] : null,
                'start_node'     => $c['start_node_id'],
                'end_node'       => $c['end_node_id'],
                'verified'       => (bool)($c['verified'] ?? true),
            ];
        }

        $accessories = [];
        foreach ($config['accessories'] ?? [] as $a) {
            $accessories[] = [
                'accessory_id'       => $a['id'],
                'accessory_type'     => $a['accessory_type'],
                'material_code'      => $a['material_code'] ?? 'UNKNOWN',
                'nama_material'      => $a['nama_material'] ?? $a['accessory_type'],
                'quantity'           => (int)$a['quantity'],
                'location_reference' => $a['location_reference'],
                'nominal_condition'  => $a['condition_status'] ?? 'GOOD',
            ];
        }

        return [
            'total_segments'    => count($segments),
            'total_accessories' => count($accessories),
            'segments'          => $segments,
            'accessories'       => $accessories,
        ];
    }

    /**
     * Build Visual Health Overlay (Observations, Broken LA, Defective Insulators)
     * Gate 5: Modifies icons & colors, NOT the underlying topology.
     */
    private function buildVisualHealthOverlay(int $sectionId, array $config): array
    {
        $defects     = [];
        $iconBadges  = [];
        $overallRisk = 'NORMAL';

        // Check accessories condition in configuration
        foreach ($config['accessories'] ?? [] as $a) {
            $cond = strtoupper($a['condition_status'] ?? 'GOOD');
            if ($cond === 'DEFECTIVE' || $cond === 'MISSING') {
                $defects[] = [
                    'element_type' => 'ACCESSORY',
                    'element_id'   => $a['id'],
                    'label'        => $a['accessory_type'] . ' (' . ($a['location_reference'] ?: 'Section ' . $sectionId) . ')',
                    'condition'    => $cond,
                    'severity'     => $cond === 'MISSING' ? 'CRITICAL' : 'WARNING',
                    'sld_icon'     => 'icon-warning-' . strtolower($a['accessory_type']),
                    'color_hex'    => $cond === 'MISSING' ? '#DC3545' : '#FFC107',
                ];
            }
        }

        // Query open findings for this section if temuan table exists
        if ($this->db->tableExists('temuan')) {
            try {
                $findings = $this->db->table('temuan')
                    ->where('section_id', $sectionId)
                    ->where('status_pekerjaan !=', 'SELESAI')
                    ->where('deleted_at IS NULL')
                    ->get()
                    ->getResultArray();

                foreach ($findings as $f) {
                    $isCritical = str_contains(strtoupper($f['kategori_anomali'] ?? ''), 'KRITIS') ||
                                  str_contains(strtoupper($f['prioritas'] ?? ''), 'P1');

                    $defects[] = [
                        'element_type' => 'FINDING',
                        'element_id'   => $f['id'],
                        'label'        => $f['judul_temuan'] ?? 'Anomali Jaringan',
                        'condition'    => 'ANOMALY_OPEN',
                        'severity'     => $isCritical ? 'CRITICAL' : 'WARNING',
                        'sld_icon'     => $isCritical ? 'icon-alert-triangle-red' : 'icon-alert-circle-yellow',
                        'color_hex'    => $isCritical ? '#DC3545' : '#FD7E14',
                    ];
                }
            } catch (\Throwable $e) {
                log_message('error', '[DynamicSldEngineService] Fetch findings fallback: ' . $e->getMessage());
            }
        }

        if (!empty($defects)) {
            $hasCritical = false;
            foreach ($defects as $d) {
                if ($d['severity'] === 'CRITICAL') {
                    $hasCritical = true;
                    break;
                }
            }
            $overallRisk = $hasCritical ? 'CRITICAL_WARNING' : 'MODERATE_WARNING';
        }

        return [
            'overall_visual_status' => $overallRisk,
            'defect_count'          => count($defects),
            'defect_overlays'       => $defects,
        ];
    }
}
