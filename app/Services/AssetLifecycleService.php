<?php

namespace App\Services;

use Config\AssetStatus;
use Config\AssetEvent;
use Config\Database;

class AssetLifecycleService
{
    private AssetHistoryService $historyService;
    private HealthScoreService $healthScoreService;
    private AssetVerificationService $verificationService;

    public function __construct()
    {
        $this->historyService      = new AssetHistoryService();
        $this->healthScoreService  = new HealthScoreService();
        $this->verificationService = new AssetVerificationService();
    }

    /**
     * Trigger Event: Temuan Created linked to Asset (Transition NORMAL -> BERMASALAH)
     */
    public function triggerTemuanCreated(int $assetId, string $nomorTemuan, ?int $userId = null, ?string $deskripsi = null): bool
    {
        $db = Database::connect();
        $asset = $db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) return false;

        $oldStatus = $asset['status'] ?? AssetStatus::NORMAL;
        $newStatus = AssetStatus::BERMASALAH;

        // Update Status & Version
        $db->table('assets')->where('id', $assetId)->update([
            'status'        => $newStatus,
            'asset_version' => (int)($asset['asset_version'] ?? 1) + 1,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        // Record Audit History
        $this->historyService->logEvent(
            $assetId,
            AssetEvent::TEMUAN_CREATED,
            $oldStatus,
            $newStatus,
            $nomorTemuan,
            $deskripsi ?: "Temuan baru dilaporkan: {$nomorTemuan}",
            $userId
        );

        // Recalculate Health Score
        $this->healthScoreService->refreshCachedHealthScore($assetId);

        return true;
    }

    /**
     * Trigger Event: Work Order Created for Asset (Transition BERMASALAH -> MAINTENANCE)
     */
    public function triggerWorkOrderCreated(int $assetId, string $nomorWo, ?int $userId = null, ?string $deskripsi = null): bool
    {
        $db = Database::connect();
        $asset = $db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) return false;

        $oldStatus = $asset['status'] ?? AssetStatus::BERMASALAH;
        $newStatus = AssetStatus::MAINTENANCE;

        // Update Status
        $db->table('assets')->where('id', $assetId)->update([
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Record Audit History
        $this->historyService->logEvent(
            $assetId,
            AssetEvent::WO_CREATED,
            $oldStatus,
            $newStatus,
            $nomorWo,
            $deskripsi ?: "Work Order perbaikan diterbitkan: {$nomorWo}",
            $userId
        );

        // Recalculate Health Score
        $this->healthScoreService->refreshCachedHealthScore($assetId);

        return true;
    }

    /**
     * Trigger Event: Work Order Completed for Asset (Transition MAINTENANCE -> MENUNGGU_VERIFIKASI)
     */
    public function triggerWorkOrderCompleted(int $assetId, string $nomorWo, ?int $userId = null, ?string $fotoSesudah = null): bool
    {
        $db = Database::connect();
        $asset = $db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) return false;

        $oldStatus = $asset['status'] ?? AssetStatus::MAINTENANCE;
        $newStatus = AssetStatus::MENUNGGU_VERIFIKASI;

        // Update Status
        $db->table('assets')->where('id', $assetId)->update([
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Record Audit History
        $this->historyService->logEvent(
            $assetId,
            AssetEvent::WO_COMPLETED,
            $oldStatus,
            $newStatus,
            $nomorWo,
            "Pekerjaan HAR selesai ({$nomorWo}). Menunggu verifikasi inspeksi Supervisor.",
            $userId,
            null,
            null,
            $fotoSesudah
        );

        // Recalculate Health Score
        $this->healthScoreService->refreshCachedHealthScore($assetId);

        return true;
    }

    /**
     * Soft Delete Asset with Mandatory Reason Audit
     */
    public function softDeleteAsset(int $assetId, int $userId, string $reason): bool
    {
        $db = Database::connect();
        $asset = $db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) return false;

        $oldStatus = $asset['status'] ?? AssetStatus::NORMAL;
        $newStatus = AssetStatus::DIHAPUS;

        // Soft Delete Record
        $db->table('assets')->where('id', $assetId)->update([
            'status'         => $newStatus,
            'deleted_at'     => date('Y-m-d H:i:s'),
            'deleted_by'     => $userId,
            'deleted_reason' => trim($reason),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // Record Audit History
        $this->historyService->logEvent(
            $assetId,
            AssetEvent::DELETE,
            $oldStatus,
            $newStatus,
            'SOFT-DELETE-' . date('YmdHis'),
            "Aset di-soft delete. Alasan: " . trim($reason),
            $userId
        );

        return true;
    }

    /**
     * Restore Soft-Deleted Asset (Administrator Action)
     */
    public function restoreAsset(int $assetId, int $adminId): bool
    {
        $db = Database::connect();
        $asset = $db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) return false;

        $oldStatus = $asset['status'] ?? AssetStatus::DIHAPUS;
        $newStatus = AssetStatus::NORMAL;

        // Restore Record
        $db->table('assets')->where('id', $assetId)->update([
            'status'         => $newStatus,
            'deleted_at'     => null,
            'deleted_by'     => null,
            'deleted_reason' => null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // Record Audit History
        $this->historyService->logEvent(
            $assetId,
            AssetEvent::RESTORE,
            $oldStatus,
            $newStatus,
            'RESTORE-' . date('YmdHis'),
            "Aset berhasil dipulihkan (restore) oleh Administrator.",
            $adminId
        );

        // Recalculate Health Score
        $this->healthScoreService->refreshCachedHealthScore($assetId);

        return true;
    }
}
