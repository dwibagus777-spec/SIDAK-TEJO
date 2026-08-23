<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\HealthIndexEngine;
use CodeIgniter\HTTP\ResponseInterface;

class FieldObservationController extends BaseController
{
    protected HealthIndexEngine $engine;

    public function __construct()
    {
        $this->engine = new HealthIndexEngine();
    }

    /**
     * POST /field-observation/vegetation
     * Handles Field Vegetation Observation Submission & Triggers Atomic HI Persistence
     */
    public function storeVegetation(): ResponseInterface
    {
        $rules = [
            'asset_id'        => 'required|is_natural_no_zero',
            'distance_meters' => 'required|numeric|greater_than_equal_to[0]',
            'wind_contact'    => 'permit_empty|in_list[0,1]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Validasi input observasi vegetasi gagal.',
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $assetId        = (int)$this->request->getPost('asset_id');
        $distanceMeters = (float)$this->request->getPost('distance_meters');
        $windContact    = (bool)$this->request->getPost('wind_contact');
        $inspectionId   = $this->request->getPost('inspection_id') ? (int)$this->request->getPost('inspection_id') : null;
        $userId         = session()->get('user_id') ? (int)session()->get('user_id') : 1;
        $nowStr         = date('Y-m-d H:i:s');

        // Handle File Upload if provided
        $fotoEvidencePath = null;
        $file = $this->request->getFile('foto_evidence');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $file->move(WRITEPATH . 'uploads/vegetation', $newName);
            $fotoEvidencePath = 'uploads/vegetation/' . $newName;
        }

        $db = \Config\Database::connect();

        // 1. Insert Append-Only Observation Record
        $db->table('vegetation_observations')->insert([
            'asset_id'           => $assetId,
            'inspection_id'      => $inspectionId,
            'distance_meters'    => $distanceMeters,
            'wind_contact'       => $windContact ? 1 : 0,
            'foto_evidence_path' => $fotoEvidencePath,
            'observed_by'        => $userId,
            'observed_at'        => $nowStr,
            'is_valid'           => 1,
            'created_at'         => $nowStr,
            'updated_at'         => $nowStr,
        ]);
        $obsId = $db->insertID();

        // 2. Trigger HealthIndexEngine Atomic Persistence
        try {
            $hiResult = $this->engine->persistHealthIndexCalculation($assetId, 'FIELD_INSPECTION_VEGETATION', $userId);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Observasi vegetasi berhasil dicatat dan Health Index aset diperbarui.',
                'data'    => [
                    'observation_id'   => $obsId,
                    'hi_final_score'   => $hiResult['final_score'],
                    'hi_category'      => $hiResult['category'],
                    'calculation_hash' => $hiResult['calculation_hash'],
                    'vegetation_detail'=> $hiResult['explanation_json']['VEGETATION'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Observasi tersimpan namun gagal memperbarui Health Index: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /field-observation/thermovision
     * Handles Field Thermovision Hotspot Submission & Triggers Atomic HI Persistence
     */
    public function storeThermovision(): ResponseInterface
    {
        $rules = [
            'asset_id'               => 'required|is_natural_no_zero',
            'inspection_domain'      => 'required|in_list[JTM_PDKB,HAR_GTT]',
            'measured_temperature_c' => 'required|numeric',
            'measurement_point'      => 'required|string|max_length[150]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Validasi input observasi thermovision gagal.',
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $assetId        = (int)$this->request->getPost('asset_id');
        $domain         = $this->request->getPost('inspection_domain');
        $measuredC      = (float)$this->request->getPost('measured_temperature_c');
        $ambientC       = $this->request->getPost('ambient_temperature_c') !== null && $this->request->getPost('ambient_temperature_c') !== '' ? (float)$this->request->getPost('ambient_temperature_c') : null;
        $point          = trim((string)$this->request->getPost('measurement_point'));
        $inspectionId   = $this->request->getPost('inspection_id') ? (int)$this->request->getPost('inspection_id') : null;
        $userId         = session()->get('user_id') ? (int)session()->get('user_id') : 1;
        $nowStr         = date('Y-m-d H:i:s');

        // Handle File Upload if provided
        $fotoThermalPath = null;
        $file = $this->request->getFile('foto_thermal');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $file->move(WRITEPATH . 'uploads/thermovision', $newName);
            $fotoThermalPath = 'uploads/thermovision/' . $newName;
        }

        $db = \Config\Database::connect();

        // 1. Insert Append-Only Observation Record
        $db->table('thermovision_observations')->insert([
            'asset_id'               => $assetId,
            'inspection_id'          => $inspectionId,
            'inspection_domain'      => $domain,
            'measured_temperature_c' => $measuredC,
            'ambient_temperature_c'  => $ambientC,
            'measurement_point'      => $point,
            'foto_thermal_path'      => $fotoThermalPath,
            'observed_by'            => $userId,
            'observed_at'            => $nowStr,
            'is_valid'               => 1,
            'created_at'             => $nowStr,
            'updated_at'             => $nowStr,
        ]);
        $obsId = $db->insertID();

        // 2. Trigger HealthIndexEngine Atomic Persistence
        try {
            $hiResult = $this->engine->persistHealthIndexCalculation($assetId, 'FIELD_INSPECTION_THERMOVISION', $userId);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Observasi thermovision berhasil dicatat dan Health Index aset diperbarui.',
                'data'    => [
                    'observation_id'   => $obsId,
                    'hi_final_score'   => $hiResult['final_score'],
                    'hi_category'      => $hiResult['category'],
                    'calculation_hash' => $hiResult['calculation_hash'],
                    'thermovision_detail' => $hiResult['explanation_json']['THERMOVISION'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Observasi tersimpan namun gagal memperbarui Health Index: ' . $e->getMessage(),
            ]);
        }
    }
}
