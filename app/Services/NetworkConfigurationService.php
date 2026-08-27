<?php

namespace App\Services;

use App\Models\NetworkSectionConfigurationModel;
use App\Models\NetworkSectionConductorModel;
use App\Models\NetworkSectionAccessoryModel;
use App\Models\MasterMaterialModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Service for Physical Network Configuration Truth (CR-06B)
 * Governed by 7 Hardening Gates:
 * Gate 4: Single ACTIVE version invariant per section
 * Domain Invariant IX: Equipment (Trafo/Gardu/Kubikel) cannot be Transline
 * Invariant X: Mixed Conductor support per section segment
 */
class NetworkConfigurationService
{
    protected BaseConnection $db;
    protected NetworkSectionConfigurationModel $configModel;
    protected NetworkSectionConductorModel $conductorModel;
    protected NetworkSectionAccessoryModel $accessoryModel;
    protected MasterMaterialModel $materialModel;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db             = $db ?? \Config\Database::connect();
        $this->configModel    = new NetworkSectionConfigurationModel();
        $this->conductorModel = new NetworkSectionConductorModel();
        $this->accessoryModel = new NetworkSectionAccessoryModel();
        $this->materialModel  = new MasterMaterialModel();
    }

    /**
     * Create a new configuration for a section.
     * If immediately set to ACTIVE, enforces Gate 4 single active invariant.
     */
    public function createSectionConfiguration(int $sectionId, array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $status = $data['verification_status'] ?? 'DRAFT';

        // Calculate next version number for this section
        $latest = $this->configModel
            ->where('section_id', $sectionId)
            ->orderBy('version_number', 'DESC')
            ->first();

        $versionNumber = $latest ? ((int)$latest['version_number'] + 1) : 1;

        $payload = [
            'section_id'           => $sectionId,
            'version_number'       => $versionNumber,
            'effective_from'       => $data['effective_from'] ?? $now,
            'effective_to'         => null,
            'verification_status'  => $status,
            'configuration_source' => $data['configuration_source'] ?? 'INITIAL_AUDIT',
            'inspection_id'        => $data['inspection_id'] ?? null,
            'changed_by'           => $data['changed_by'] ?? null,
            'change_reason'        => $data['change_reason'] ?? 'Initial Section Configuration',
        ];

        $configId = (int)$this->configModel->insert($payload, true);

        // If newly created is ACTIVE, enforce Gate 4 invariant immediately
        if ($status === 'ACTIVE') {
            $this->enforceSingleActiveInvariant($sectionId, $configId);
        }

        // Add conductors if provided
        if (!empty($data['conductors']) && is_array($data['conductors'])) {
            foreach ($data['conductors'] as $idx => $cond) {
                $cond['sequence_order'] = $idx + 1;
                $this->addConductorSegment($configId, $cond);
            }
        }

        // Add accessories if provided
        if (!empty($data['accessories']) && is_array($data['accessories'])) {
            foreach ($data['accessories'] as $acc) {
                $this->addAccessory($configId, $acc);
            }
        }

        return $this->getFullConfiguration($configId);
    }

    /**
     * Activate a configuration and supersede any existing ACTIVE configuration for the section (Gate 4).
     */
    public function activateConfiguration(int $configurationId): array
    {
        $target = $this->configModel->find($configurationId);
        if (!$target) {
            throw new \RuntimeException("Configuration ID {$configurationId} tidak ditemukan.");
        }

        $sectionId = (int)$target['section_id'];
        $now       = date('Y-m-d H:i:s');

        $this->db->transBegin();
        try {
            // Update target to ACTIVE
            $this->configModel->update($configurationId, [
                'verification_status' => 'ACTIVE',
                'effective_from'      => $target['effective_from'] ?? $now,
                'effective_to'        => null,
            ]);

            // Enforce single active invariant: supersede all previous active versions
            $this->enforceSingleActiveInvariant($sectionId, $configurationId);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return $this->getFullConfiguration($configurationId);
    }

    /**
     * Gate 4 Invariant: Only ONE configuration per section can be ACTIVE with effective_to = NULL.
     */
    private function enforceSingleActiveInvariant(int $sectionId, int $activeConfigId): void
    {
        $now = date('Y-m-d H:i:s');

        $this->configModel
            ->where('section_id', $sectionId)
            ->where('id !=', $activeConfigId)
            ->where('verification_status', 'ACTIVE')
            ->set([
                'verification_status' => 'SUPERSEDED',
                'effective_to'        => $now,
            ])
            ->update();
    }

    /**
     * Add Conductor Segment to a Configuration (Invariant X: Mixed Conductor support).
     * Enforces Invariant IX: Equipment cannot be Transline/Conductor.
     */
    public function addConductorSegment(int $configurationId, array $data): array
    {
        $materialId = (int)($data['conductor_material_id'] ?? 0);
        $material   = $this->materialModel->find($materialId);

        if (!$material) {
            throw new \InvalidArgumentException("Conductor Material ID {$materialId} tidak ditemukan di Master Material.");
        }

        // Domain Invariant IX Check: Must be CONDUCTOR category, cannot be equipment (Trafo, Gardu, Kubikel)
        if (in_array(strtoupper($material['material_domain']), ['GARDU', 'TRAFO', 'KUBIKEL'])) {
            throw new \DomainException("Domain Invariant IX Violation: Equipment '{$material['nama_material']}' ({$material['material_domain']}) dilarang dijadikan Transline/Conductor.");
        }

        $payload = [
            'network_section_configuration_id' => $configurationId,
            'conductor_material_id'            => $materialId,
            'sequence_order'                   => (int)($data['sequence_order'] ?? 1),
            'segment_label'                    => $data['segment_label'] ?? null,
            'start_node_id'                    => $data['start_node_id'] ?? null,
            'end_node_id'                      => $data['end_node_id'] ?? null,
            'length_m'                         => isset($data['length_m']) ? (float)$data['length_m'] : null,
            'verified'                         => isset($data['verified']) ? (int)$data['verified'] : 1,
            'created_at'                       => date('Y-m-d H:i:s'),
        ];

        $condId = (int)$this->conductorModel->insert($payload, true);
        return $this->conductorModel->find($condId);
    }

    /**
     * Add Accessory to a Section Configuration (GSW, LA, CLD, MCA, etc.)
     */
    public function addAccessory(int $configurationId, array $data): array
    {
        $materialId = (int)($data['accessory_material_id'] ?? 0);
        $material   = $this->materialModel->find($materialId);

        if (!$material) {
            throw new \InvalidArgumentException("Accessory Material ID {$materialId} tidak ditemukan di Master Material.");
        }

        $payload = [
            'network_section_configuration_id' => $configurationId,
            'accessory_material_id'            => $materialId,
            'accessory_type'                   => strtoupper(trim((string)($data['accessory_type'] ?? 'GSW'))),
            'quantity'                         => (int)($data['quantity'] ?? 1),
            'location_reference'               => $data['location_reference'] ?? null,
            'condition_status'                 => $data['condition_status'] ?? 'GOOD',
            'verified'                         => isset($data['verified']) ? (int)$data['verified'] : 1,
            'created_at'                       => date('Y-m-d H:i:s'),
        ];

        $accId = (int)$this->accessoryModel->insert($payload, true);
        return $this->accessoryModel->find($accId);
    }

    /**
     * Get the active configuration for a section (Gate 4).
     */
    public function getActiveConfiguration(int $sectionId): ?array
    {
        $config = $this->configModel
            ->where('section_id', $sectionId)
            ->where('verification_status', 'ACTIVE')
            ->where('effective_to IS NULL')
            ->orderBy('version_number', 'DESC')
            ->first();

        if (!$config) {
            return null;
        }

        return $this->getFullConfiguration((int)$config['id']);
    }

    /**
     * Get historical configuration as of a specific point in time (Time-Travel).
     */
    public function getConfigurationAt(int $sectionId, string $dateTime): ?array
    {
        $config = $this->configModel
            ->where('section_id', $sectionId)
            ->where('effective_from <=', $dateTime)
            ->groupStart()
                ->where('effective_to >=', $dateTime)
                ->orWhere('effective_to IS NULL')
            ->groupEnd()
            ->orderBy('version_number', 'DESC')
            ->first();

        if (!$config) {
            return null;
        }

        return $this->getFullConfiguration((int)$config['id']);
    }

    /**
     * Get full configuration with conductors and accessories joined.
     */
    public function getFullConfiguration(int $configurationId): array
    {
        $config = $this->configModel->find($configurationId);
        if (!$config) {
            return [];
        }

        $conductors = $this->conductorModel
            ->select('network_section_conductors.*, master_materials.material_code, master_materials.nama_material, master_materials.satuan')
            ->join('master_materials', 'master_materials.id = network_section_conductors.conductor_material_id', 'left')
            ->where('network_section_configuration_id', $configurationId)
            ->orderBy('sequence_order', 'ASC')
            ->findAll();

        $accessories = $this->accessoryModel
            ->select('network_section_accessories.*, master_materials.material_code, master_materials.nama_material')
            ->join('master_materials', 'master_materials.id = network_section_accessories.accessory_material_id', 'left')
            ->where('network_section_configuration_id', $configurationId)
            ->findAll();

        $config['conductors']  = $conductors;
        $config['accessories'] = $accessories;

        return $config;
    }

    /**
     * Get Section Coverage Metrics across the grid (Gate F2: Honest Empty State support).
     */
    public function getSectionCoverageMetrics(?int $penyulangId = null): array
    {
        $secBuilder = $this->db->table('sections');
        if ($penyulangId !== null) {
            $secBuilder->where('penyulang_id', $penyulangId);
        }
        $totalSections = $secBuilder->countAllResults();

        // Configured sections: distinct section_id with ACTIVE configuration
        $cfgBuilder = $this->db->table('network_section_configurations')
            ->select('section_id')
            ->distinct()
            ->where('verification_status', 'ACTIVE')
            ->where('effective_to IS NULL');

        if ($penyulangId !== null) {
            $cfgBuilder->join('sections', 'sections.id = network_section_configurations.section_id')
                ->where('sections.penyulang_id', $penyulangId);
        }
        $configuredSections = $cfgBuilder->countAllResults();

        $unconfiguredSections = max(0, $totalSections - $configuredSections);
        $coveragePct = $totalSections > 0 ? round(($configuredSections / $totalSections) * 100, 2) : 0.00;

        return [
            'total_sections'        => $totalSections,
            'configured_sections'   => $configuredSections,
            'unconfigured_sections' => $unconfiguredSections,
            'coverage_pct'          => $coveragePct,
            'status'                => $coveragePct === 0.00 ? 'HONEST_EMPTY_STATE' : ($coveragePct >= 100.00 ? 'FULLY_CONFIGURED' : 'PARTIALLY_CONFIGURED'),
        ];
    }

    /**
     * Get all active configurations for a feeder.
     */
    public function getFeederActiveConfigurations(int $penyulangId): array
    {
        $sections = $this->db->table('sections')
            ->where('penyulang_id', $penyulangId)
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($sections as $s) {
            $active = $this->getActiveConfiguration((int)$s['id']);
            $result[] = [
                'section_id'   => (int)$s['id'],
                'nama_section' => $s['nama_section'],
                'has_config'   => $active !== null,
                'config'       => $active,
            ];
        }

        return $result;
    }
}
