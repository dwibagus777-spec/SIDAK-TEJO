<?php

namespace App\Services;

use App\Models\AssetHistoryModel;

class AssetHistoryService
{
    private AssetHistoryModel $historyModel;

    public function __construct()
    {
        $this->historyModel = new AssetHistoryModel();
    }

    /**
     * Record explicit Asset Lifecycle History Event with Client Metadata
     */
    public function logEvent(
        int $assetId,
        string $jenisEvent,
        ?string $statusLama,
        string $statusBaru,
        ?string $referensi = null,
        ?string $deskripsi = null,
        ?int $userId = null,
        ?int $approvedBy = null,
        ?string $fotoSebelum = null,
        ?string $fotoSesudah = null
    ): bool {
        $request = \Config\Services::request();

        $ipAddress = $request->getIPAddress();
        $userAgent = (string)$request->getUserAgent();

        $device = 'DESKTOP';
        if ($request->getUserAgent()->isMobile()) {
            $device = 'MOBILE_PHONE';
        } elseif ($request->getUserAgent()->isRobot()) {
            $device = 'BOT_SERVICE';
        }

        if ($userId === null && session()->has('user_id')) {
            $userId = (int)session()->get('user_id');
        }

        $data = [
            'asset_id'     => $assetId,
            'tanggal'      => date('Y-m-d H:i:s'),
            'jenis_event'  => $jenisEvent,
            'status_lama'  => $statusLama,
            'status_baru'  => $statusBaru,
            'referensi'    => $referensi,
            'deskripsi'    => $deskripsi,
            'user_id'      => $userId,
            'approved_by'  => $approvedBy,
            'foto_sebelum' => $fotoSebelum,
            'foto_sesudah' => $fotoSesudah,
            'ip_address'   => $ipAddress,
            'user_agent'   => substr($userAgent, 0, 255),
            'device'       => $device,
        ];

        return (bool)$this->historyModel->insert($data);
    }

    /**
     * Get timeline entries for an asset
     */
    public function getAssetTimeline(int $assetId, int $limit = 50): array
    {
        return $this->historyModel->getTimelineByAssetId($assetId, $limit);
    }
}
