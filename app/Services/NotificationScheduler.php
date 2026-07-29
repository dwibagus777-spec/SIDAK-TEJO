<?php

namespace App\Services;

use App\Repositories\TemuanRepository;
use App\Repositories\WorkOrderRepository;

class NotificationScheduler
{
    private NotificationService $notificationService;
    private TemuanRepository $temuanRepo;
    private WorkOrderRepository $woRepo;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
        $this->temuanRepo          = new TemuanRepository();
        $this->woRepo              = new WorkOrderRepository();
    }

    /**
     * SLA Escalation & Overdue Reminder Check
     */
    public function checkSlaEscalations(): array
    {
        $temuans = $this->temuanRepo->getFilteredTemuan(['status' => 'BELUM']);
        $escalatedCount = 0;

        foreach ($temuans as $t) {
            if (!empty($t['tanggal_temuan'])) {
                $hoursElapsed = (time() - strtotime($t['tanggal_temuan'])) / 3600;
                
                // SLA Overdue (Emergency > 24h, High > 48h)
                if (($t['prioritas'] === 'EMERGENCY' && $hoursElapsed > 24) || ($t['prioritas'] === 'HIGH' && $hoursElapsed > 48)) {
                    $title = "⚠️ ESKALASI SLA TERLAMBAT: " . $t['nomor_temuan'];
                    $msg   = "Temuan " . $t['jenis_temuan'] . " di ULP " . ($t['nama_ulp'] ?? '-') . " telah melewati batas SLA penanganan (" . round($hoursElapsed) . " jam elapsed). Mohon atensi Supervisor!";
                    
                    $this->notificationService->dispatchNotification('SLA_ESCALATION', $title, $msg);
                    $escalatedCount++;
                }
            }
        }

        return [
            'success'   => true,
            'escalated' => $escalatedCount,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Daily Morning Summary Report Dispatcher (07.00 WIB)
     */
    public function sendDailySummary(): bool
    {
        $db = \Config\Database::connect();
        $totalTemuan = $db->table('temuan')->where('deleted_at IS NULL')->countAllResults();
        $totalSelesai= $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'SELESAI')->countAllResults();
        $totalProses = $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'PROSES')->countAllResults();

        $title = "🌅 LAPORAN RINGKASAN HARIAN SIDAK TEJO (" . date('d-m-Y') . ")";
        $msg   = "Selamat Pagi Team PLN!\n\n" .
                 "📊 Summary Status Hari Ini:\n" .
                 "• Total Temuan: {$totalTemuan}\n" .
                 "• Temuan Selesai: {$totalSelesai}\n" .
                 "• Dalam Pengerjaan: {$totalProses}\n\n" .
                 "Mari tingkatkan keandalan listrik dan utamakan Keselamatan Kerja (K3)!";

        return $this->notificationService->dispatchNotification('DAILY_SUMMARY', $title, $msg);
    }
}
