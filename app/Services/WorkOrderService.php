<?php

namespace App\Services;

use App\Repositories\WorkOrderRepository;

class WorkOrderService
{
    private WorkOrderRepository $repository;

    public function __construct()
    {
        $this->repository = new WorkOrderRepository();
    }

    public function generateNomorWO(): string
    {
        $todayStr = date('Ymd');
        $rand = sprintf('%03d', mt_rand(1, 999));
        return 'WO-' . $todayStr . '-' . $rand;
    }

    public function createWorkOrder(array $data, ?array $defaultChecklists = null): int
    {
        if (empty($data['nomor_wo'])) {
            $data['nomor_wo'] = $this->generateNomorWO();
        }

        $woId = $this->repository->insert($data);

        // Add default checklists if provided
        if (!empty($defaultChecklists)) {
            foreach ($defaultChecklists as $chkText) {
                if (!empty(trim($chkText))) {
                    $this->repository->addChecklist($woId, trim($chkText));
                }
            }
        } else {
            // Default standard checklists
            $standard = [
                'Pemeriksaan APD & Peralatan K3',
                'Pengecekan Koordinat & Kondisi Fisik Aset',
                'Pengukuran Parameter Tegangan & Beban',
                'Eksekusi Perbaikan & Pengantian Material',
                'Pengujian Ulang & Pembersihan Lokasi Kerja'
            ];
            foreach ($standard as $chkText) {
                $this->repository->addChecklist($woId, $chkText);
            }
        }

        // Add history log
        $this->repository->addHistory([
            'wo_id'      => $woId,
            'user_name'  => $data['created_by'] ?: 'System',
            'aktivitas'  => 'Menerbitkan Work Order Baru (' . $data['nomor_wo'] . ')',
            'catatan'    => 'Prioritas: ' . ($data['prioritas'] ?? 'MEDIUM') . ' | Status: ' . ($data['status'] ?? 'OPEN'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Enterprise Asset Lifecycle Hook: Update Asset Status to MAINTENANCE
        try {
            if (!empty($data['asset_id'])) {
                (new \App\Services\AssetLifecycleService())->triggerWorkOrderCreated(
                    (int)$data['asset_id'],
                    $data['nomor_wo'],
                    null,
                    $data['catatan'] ?? null
                );
            }
        } catch (\Throwable $e) {
            log_message('warning', '[AssetLifecycle] createWorkOrder hook: ' . $e->getMessage());
        }

        return $woId;
    }

    public function updateStatus(int $woId, string $newStatus, string $userName, ?string $catatan = null, ?string $fotoSebel = null, ?string $fotoProses = null, ?string $fotoSesudah = null): bool
    {
        $wo = $this->repository->find($woId);
        if (!$wo) return false;

        $updateData = [
            'status'  => strtoupper($newStatus),
            'catatan' => $catatan ?: $wo['catatan'],
        ];

        if (strtoupper($newStatus) === 'COMPLETED') {
            $updateData['tanggal_selesai'] = date('Y-m-d H:i:s');
        }

        $this->repository->update($woId, $updateData);

        // Add History Log
        $this->repository->addHistory([
            'wo_id'        => $woId,
            'user_name'    => $userName,
            'aktivitas'    => 'Mengubah Status WO dari ' . $wo['status'] . ' menjadi ' . strtoupper($newStatus),
            'catatan'      => $catatan,
            'foto_sebelum' => $fotoSebel,
            'foto_proses'  => $fotoProses,
            'foto_sesudah' => $fotoSesudah,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        // Enterprise Asset Lifecycle Hook: On WO Completion -> Update Asset Status to MENUNGGU_VERIFIKASI
        try {
            if (!empty($wo['asset_id']) && (strtoupper($newStatus) === 'COMPLETED' || strtoupper($newStatus) === 'SELESAI')) {
                (new \App\Services\AssetLifecycleService())->triggerWorkOrderCompleted(
                    (int)$wo['asset_id'],
                    $wo['nomor_wo'],
                    null,
                    $fotoSesudah
                );
            }
        } catch (\Throwable $e) {
            log_message('warning', '[AssetLifecycle] updateStatus hook: ' . $e->getMessage());
        }

        return true;
    }
}
