<?php

namespace App\Services;

use Config\Database;
use App\Models\InspectionModel;
use App\Models\InspectionPointModel;
use App\Models\InspectionResultModel;
use App\Models\InspectionPhotoModel;
use App\Services\TemuanService;
use App\Services\AssetLifecycleService;
use App\Services\BaselineService;
use App\Services\InspectionCatalogService;

class InspectionExecutionService
{
    private InspectionModel $inspectionModel;
    private InspectionPointModel $pointModel;
    private InspectionResultModel $resultModel;
    private InspectionPhotoModel $photoModel;
    private TemuanService $temuanService;
    private AssetLifecycleService $lifecycleService;
    private BaselineService $baselineService;
    private InspectionCatalogService $catalogService;

    public function __construct()
    {
        $this->inspectionModel  = new InspectionModel();
        $this->pointModel       = new InspectionPointModel();
        $this->resultModel      = new InspectionResultModel();
        $this->photoModel       = new InspectionPhotoModel();
        $this->temuanService    = new TemuanService();
        $this->lifecycleService = new AssetLifecycleService();
        $this->baselineService  = new BaselineService();
        $this->catalogService   = new InspectionCatalogService();
    }

    /**
     * Start a Guided Inspection run based on a Feeder Baseline
     */
    public function startInspection(int $inspectionTypeId, int $baselineId, int $inspectorUserId): array
    {
        $baseline = $this->baselineService->getBaselineDetail($baselineId);
        if (!$baseline) {
            throw new \InvalidArgumentException("Baseline jaringan dengan ID {$baselineId} tidak ditemukan.");
        }

        $nomorInspeksi = 'INS-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

        $inspectionId = $this->inspectionModel->insert([
            'nomor_inspeksi'     => $nomorInspeksi,
            'inspection_type_id' => $inspectionTypeId,
            'baseline_id'        => $baselineId,
            'ulp_id'             => $baseline['ulp_id'] ?? null,
            'penyulang_id'       => $baseline['penyulang_id'] ?? null,
            'inspector_user_id'  => $inspectorUserId,
            'start_time'         => date('Y-m-d H:i:s'),
            'status'             => 'IN_PROGRESS',
            'total_points'       => count($baseline['assets'] ?? []),
            'passed_points'      => 0,
            'failed_points'      => 0,
        ]);

        $points = [];
        foreach (($baseline['assets'] ?? []) as $idx => $bAsset) {
            $pointId = $this->pointModel->insert([
                'inspection_id' => $inspectionId,
                'asset_id'      => $bAsset['asset_id'],
                'sequence_no'   => $bAsset['sequence_no'] ?? ($idx + 1),
                'status'        => 'PENDING',
            ]);

            $points[] = [
                'point_id'    => $pointId,
                'asset_id'    => $bAsset['asset_id'],
                'sequence_no' => $bAsset['sequence_no'] ?? ($idx + 1),
                'kode_asset'  => $bAsset['kode_asset'] ?? '',
                'nama_asset'  => $bAsset['nama_asset'] ?? '',
                'jenis_asset' => $bAsset['jenis_asset'] ?? '',
            ];
        }

        return [
            'inspection_id'  => $inspectionId,
            'nomor_inspeksi' => $nomorInspeksi,
            'total_points'   => count($points),
            'points'         => $points,
        ];
    }

    /**
     * Save Point Execution Result inside an Atomic DB Transaction
     * Idempotent & Atomic Bridge to TemuanService & AssetLifecycleService
     */
    public function savePointResult(int $pointId, array $itemsData, array $photosData = [], ?string $notes = null): array
    {
        $db = Database::connect();
        $point = $this->pointModel->find($pointId);

        if (!$point) {
            throw new \InvalidArgumentException("Inspection Point #{$pointId} tidak ditemukan.");
        }

        $inspection = $this->inspectionModel->find($point['inspection_id']);

        $db->transStart();

        $pointHasFailure = false;
        $createdTemuanIds = [];

        foreach ($itemsData as $item) {
            $templateItemId   = (int)($item['template_item_id'] ?? 0);
            $resultStatus     = strtoupper((string)($item['result_status'] ?? 'PASS'));
            $measurementValue = isset($item['measurement_value']) && $item['measurement_value'] !== '' ? (float)$item['measurement_value'] : null;
            $itemNotes        = $item['notes'] ?? null;

            if ($resultStatus === 'FAIL') {
                $pointHasFailure = true;
            }

            // Idempotent Upsert into inspection_results (unique key: uk_point_template_item)
            $existingResult = $this->resultModel
                ->where('inspection_point_id', $pointId)
                ->where('template_item_id', $templateItemId)
                ->first();

            $resultData = [
                'inspection_point_id' => $pointId,
                'template_item_id'    => $templateItemId,
                'result_status'       => $resultStatus,
                'measurement_value'   => $measurementValue,
                'notes'               => $itemNotes,
                'updated_at'          => date('Y-m-d H:i:s'),
            ];

            if ($existingResult) {
                $this->resultModel->update($existingResult['id'], $resultData);
                $resultId = $existingResult['id'];
            } else {
                $resultData['created_at'] = date('Y-m-d H:i:s');
                $resultId = $this->resultModel->insert($resultData);
            }

            // IF RESULT IS FAIL -> BRIDGE TRANSACTIONALLY TO TemuanService & AssetLifecycleService
            if ($resultStatus === 'FAIL') {
                $temuanData = [
                    'asset_id'       => $point['asset_id'],
                    'ulp_id'         => $inspection['ulp_id'] ?? null,
                    'penyulang_id'   => $inspection['penyulang_id'] ?? null,
                    'jenis_temuan'   => 'KONSTRUKSI',
                    'pelaksana'      => 'HAR KONSTRUKSI',
                    'prioritas'      => 'TINGGI',
                    'detail_temuan'  => 'Temuan abnormalitas dari inspeksi ' . ($inspection['nomor_inspeksi'] ?? '') . ': ' . ($itemNotes ?: 'Pemeriksaan checklist gagal.'),
                    'status'         => 'TEMUAN_CREATED',
                    'created_by'     => $inspection['inspector_user_id'] ?? 1,
                ];

                // Create Temuan Record
                $temuanId = $this->temuanService->createTemuan($temuanData);
                $createdTemuanIds[] = $temuanId;

                // SOLE EAM LIFECYCLE AUTHORITY TRANSITION: Transition Asset to BERMASALAH
                $this->lifecycleService->transitionStatus(
                    $point['asset_id'],
                    \App\Services\AssetLifecycleService::STATUS_BERMASALAH,
                    [
                        'event'          => \Config\AssetEvent::TEMUAN_CREATED,
                        'reference_code' => $inspection['nomor_inspeksi'] ?? null,
                        'notes'          => 'Status aset berubah menjadi BERMASALAH akibat temuan abnormalitas inspeksi.',
                        'actor_id'       => $inspection['inspector_user_id'] ?? 1,
                    ]
                );

                // Link created Temuan ID back to inspection_results
                $this->resultModel->update($resultId, ['temuan_id' => $temuanId]);
            }
        }

        // Idempotent Photo Insert (unique key: uk_photo_client_uuid)
        foreach ($photosData as $photo) {
            $clientUuid = $photo['client_uuid'] ?? null;
            if ($clientUuid) {
                $existingPhoto = $this->photoModel->where('client_uuid', $clientUuid)->first();
                if ($existingPhoto) {
                    continue; // Skip duplicate upload retry
                }
            }

            $this->photoModel->insert([
                'inspection_point_id' => $pointId,
                'photo_type'          => $photo['photo_type'] ?? 'CONDITION',
                'file_path'           => $photo['file_path'] ?? '',
                'caption'             => $photo['caption'] ?? null,
                'client_uuid'         => $clientUuid,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);
        }

        // Update Point Status
        $pointStatus = $pointHasFailure ? 'FAILED' : 'PASSED';
        $this->pointModel->update($pointId, [
            'status'       => $pointStatus,
            'notes'        => $notes,
            'inspected_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        // Recalculate Inspection Totals
        $passedCount = $this->pointModel->where('inspection_id', $point['inspection_id'])->where('status', 'PASSED')->countAllResults();
        $failedCount = $this->pointModel->where('inspection_id', $point['inspection_id'])->where('status', 'FAILED')->countAllResults();

        $this->inspectionModel->update($point['inspection_id'], [
            'passed_points' => $passedCount,
            'failed_points' => $failedCount,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new \RuntimeException("Gagal menyimpan hasil inspeksi point #{$pointId}. Transaksi di-rollback.");
        }

        return [
            'success'            => true,
            'point_id'           => $pointId,
            'status'             => $pointStatus,
            'created_temuan_ids' => $createdTemuanIds,
        ];
    }
}
