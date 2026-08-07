<?php

namespace App\Services;

use Config\AssetStatus;
use Config\AssetEvent;
use Config\Database;

class AssetVerificationService
{
    private AssetHistoryService $historyService;
    private HealthScoreService $healthScoreService;

    public function __construct()
    {
        $this->historyService     = new AssetHistoryService();
        $this->healthScoreService = new HealthScoreService();
    }

    /**
     * Supervisor Inspection PASS Workflow (Transition MENUNGGU_VERIFIKASI -> NORMAL)
     */
    public function verifyInspectionPass(int $assetId, int $supervisorId, ?string $catatan = null, ?string $fotoSesudah = null): bool
    {
        $db = Database::connect();
        $asset = $db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) return false;

        $oldStatus = $asset['status'] ?? AssetStatus::NORMAL;
        $newStatus = AssetStatus::NORMAL;

        // Update Asset Status to NORMAL
        $db->table('assets')->where('id', $assetId)->update([
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Record Timeline History Event
        $this->historyService->logEvent(
            $assetId,
            AssetEvent::INSPECTION_PASS,
            $oldStatus,
            $newStatus,
            'VERIFY-PASS-' . date('YmdHis'),
            $catatan ?: 'Inspeksi Supervisor LULUS. Aset dinyatakan kembali NORMAL.',
            null,
            $supervisorId,
            null,
            $fotoSesudah
        );

        // Recalculate Health Score
        $this->healthScoreService->refreshCachedHealthScore($assetId);

        return true;
    }

    /**
     * Supervisor Inspection FAIL Workflow (Transition MENUNGGU_VERIFIKASI -> BERMASALAH)
     */
    public function verifyInspectionFail(int $assetId, int $supervisorId, ?string $catatan = null, ?string $fotoSesudah = null): bool
    {
        $db = Database::connect();
        $asset = $db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) return false;

        $oldStatus = $asset['status'] ?? AssetStatus::NORMAL;
        $newStatus = AssetStatus::BERMASALAH;

        // Update Asset Status to BERMASALAH
        $db->table('assets')->where('id', $assetId)->update([
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Record Timeline History Event
        $this->historyService->logEvent(
            $assetId,
            AssetEvent::INSPECTION_FAIL,
            $oldStatus,
            $newStatus,
            'VERIFY-FAIL-' . date('YmdHis'),
            $catatan ?: 'Inspeksi Supervisor GAGAL. Aset dikembalikan ke status BERMASALAH.',
            null,
            $supervisorId,
            null,
            $fotoSesudah
        );

        // Recalculate Health Score
        $this->healthScoreService->refreshCachedHealthScore($assetId);

        return true;
    }
}
