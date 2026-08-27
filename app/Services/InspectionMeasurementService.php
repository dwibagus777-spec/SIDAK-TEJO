<?php

namespace App\Services;

use App\Models\InspectionProgramModel;
use App\Models\InspectionMeasurementTemplateModel;
use App\Models\InspectionMeasurementPointModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Service for Inspection Programs & GTT Measurement Intelligence (CR-06C)
 */
class InspectionMeasurementService
{
    protected BaseConnection $db;
    protected InspectionProgramModel $programModel;
    protected InspectionMeasurementTemplateModel $templateModel;
    protected InspectionMeasurementPointModel $pointModel;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db            = $db ?? \Config\Database::connect();
        $this->programModel  = new InspectionProgramModel();
        $this->templateModel = new InspectionMeasurementTemplateModel();
        $this->pointModel    = new InspectionMeasurementPointModel();
    }

    /**
     * Register or Update an Inspection Program
     */
    public function registerInspectionProgram(array $data): array
    {
        $code = strtoupper(trim((string)($data['program_code'] ?? '')));
        if (empty($code)) {
            throw new \InvalidArgumentException('Program code wajib diisi.');
        }

        $existing = $this->programModel->where('program_code', $code)->first();

        $payload = [
            'program_code'        => $code,
            'nama_pekerjaan'      => trim((string)($data['nama_pekerjaan'] ?? $code)),
            'asset_domain'        => strtoupper(trim((string)($data['asset_domain'] ?? 'JTM'))),
            'inspection_type'     => strtoupper(trim((string)($data['inspection_type'] ?? 'VISUAL_L1'))),
            'executor_type'       => strtoupper(trim((string)($data['executor_type'] ?? 'INSPEKSI'))),
            'inspection_category' => strtoupper(trim((string)($data['inspection_category'] ?? 'PREVENTIVE'))),
            'active'              => isset($data['active']) ? (int)$data['active'] : 1,
        ];

        if ($existing) {
            $this->programModel->update($existing['id'], $payload);
            $programId = (int)$existing['id'];
        } else {
            $programId = (int)$this->programModel->insert($payload, true);
        }

        return $this->programModel->find($programId);
    }

    /**
     * Register or Update a Measurement Template
     */
    public function registerMeasurementTemplate(array $data): array
    {
        $code = strtoupper(trim((string)($data['template_code'] ?? '')));
        if (empty($code)) {
            throw new \InvalidArgumentException('Template code wajib diisi.');
        }

        $existing = $this->templateModel->where('template_code', $code)->first();

        $payload = [
            'inspection_program_id' => $data['inspection_program_id'] ?? null,
            'template_code'         => $code,
            'template_name'         => trim((string)($data['template_name'] ?? $code)),
            'asset_domain'          => strtoupper(trim((string)($data['asset_domain'] ?? 'GTT'))),
            'active'                => isset($data['active']) ? (int)$data['active'] : 1,
        ];

        if ($existing) {
            $this->templateModel->update($existing['id'], $payload);
            $templateId = (int)$existing['id'];
        } else {
            $templateId = (int)$this->templateModel->insert($payload, true);
        }

        // Add measurement points if provided
        if (!empty($data['points']) && is_array($data['points'])) {
            foreach ($data['points'] as $idx => $p) {
                $p['sequence_order'] = $idx + 1;
                $this->addMeasurementPoint($templateId, $p);
            }
        }

        return $this->getFullTemplate($templateId);
    }

    /**
     * Add Measurement Point to Template (GTT Load Measurement Schema)
     */
    public function addMeasurementPoint(int $templateId, array $data): array
    {
        $code = strtoupper(trim((string)($data['point_code'] ?? '')));
        if (empty($code)) {
            throw new \InvalidArgumentException('Point code wajib diisi.');
        }

        $existing = $this->pointModel
            ->where('template_id', $templateId)
            ->where('point_code', $code)
            ->first();

        $payload = [
            'template_id'      => $templateId,
            'point_code'       => $code,
            'point_name'       => trim((string)($data['point_name'] ?? $code)),
            'phase'            => !empty($data['phase']) ? strtoupper(trim((string)$data['phase'])) : null,
            'line'             => !empty($data['line']) ? strtoupper(trim((string)$data['line'])) : null,
            'measurement_type' => strtoupper(trim((string)($data['measurement_type'] ?? 'CURRENT_AMPERE'))),
            'unit'             => trim((string)($data['unit'] ?? 'A')),
            'sequence_order'   => (int)($data['sequence_order'] ?? 1),
            'mandatory'        => isset($data['mandatory']) ? (int)$data['mandatory'] : 1,
        ];

        if ($existing) {
            $this->pointModel->update($existing['id'], $payload);
            $pointId = (int)$existing['id'];
        } else {
            $pointId = (int)$this->pointModel->insert($payload, true);
        }

        return $this->pointModel->find($pointId);
    }

    /**
     * Get Full Template with Points
     */
    public function getFullTemplate(int $templateId): array
    {
        $template = $this->templateModel->find($templateId);
        if (!$template) {
            return [];
        }

        $points = $this->pointModel
            ->where('template_id', $templateId)
            ->orderBy('sequence_order', 'ASC')
            ->findAll();

        $template['points'] = $points;
        return $template;
    }
}
