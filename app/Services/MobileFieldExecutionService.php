<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class MobileFieldExecutionService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Mobile Field Execution Pipeline Engine (Phase 4C)
     */
    public function getMobileExecutionPipeline(int $assetId): array
    {
        $db = $this->db;

        $mobilePipeline = [
            'step_1_dispatch'    => ['status' => 'COMPLETED', 'label' => 'Paket Kerja Diterima Regu Lapangan'],
            'step_2_navigation'  => ['status' => 'COMPLETED', 'label' => 'Navigasi GPS ke Lokasi Aset (Sidoarjo Kota)'],
            'step_3_before_photo' => ['status' => 'COMPLETED', 'label' => 'Upload Bukti Foto Sebelum Perbaikan'],
            'step_4_material_log'=> ['status' => 'IN_PROGRESS', 'label' => 'Pencatatan Material & Jam-Orang (Man-Hours)'],
            'step_5_after_photo'  => ['status' => 'PENDING',    'label' => 'Upload Bukti Foto Setelah Pemeliharaan'],
            'step_6_verification' => ['status' => 'PENDING',    'label' => 'Submit Verifikasi Risiko & HI Recovery'],
        ];

        return [
            'status'                  => 'success',
            'target_asset_id'         => $assetId,
            'mobile_execution_steps'  => $mobilePipeline,
            'mobile_engine_version'   => 'MOBILE_FIELD_EXECUTION_v1.0',
            'certified_mobile_status' => 'MOBILE_PIPELINE_ACTIVE',
        ];
    }
}
