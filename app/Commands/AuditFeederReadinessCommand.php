<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FeederNetworkReadinessService;

class AuditFeederReadinessCommand extends BaseCommand
{
    protected $group       = 'Sidak';
    protected $name        = 'feeder:audit-readiness';
    protected $description = 'Audit readiness and transline integrity for all feeders across all ULPs';

    public function run(array $params)
    {
        CLI::write("=== SIDAK TEJO: AUDIT KESIAPAN PENYULANG & INTEGRITAS TRANSLINE ===", 'yellow');
        CLI::write("Waktu Server: " . date('Y-m-d H:i:s'), 'white');

        $service = new FeederNetworkReadinessService();
        $res = $service->auditAllFeeders();

        if ($res['status'] !== 'success') {
            CLI::error($res['message']);
            return;
        }

        $sum = $res['summary'];
        CLI::write("\n--- RINGKASAN SELURUH PENYULANG ---", 'cyan');
        CLI::write("Total Penyulang Terdaftar: {$sum['total_feeders']}");
        CLI::write("Penyulang SIAP TERVERIFIKASI: {$sum['ready_count']}", 'green');
        CLI::write("Penyulang SEBAGIAN TERPETAKAN: {$sum['partially_ready_count']}", 'yellow');
        CLI::write("Penyulang BELUM SIAP: {$sum['not_ready_count']}", 'red');
        CLI::write("Total Tiang JTM Aktif: {$sum['total_active_poles']}");
        CLI::write("Total Segmen Transline: {$sum['total_translines']}");
        CLI::write("Total Pelanggaran (Cross-feeder / Non-pole): {$sum['total_violations']}", $sum['total_violations'] === 0 ? 'green' : 'red');

        CLI::write("\n--- DETAIL PER PENYULANG ---", 'cyan');
        $thead = ['ID', 'ULP', 'Kode', 'Nama Penyulang', 'Tiang JTM', 'Proteksi', 'Transline (m)', 'Status'];
        $tbody = [];

        foreach ($res['feeders'] as $f) {
            $tbody[] = [
                $f['feeder_id'],
                substr($f['nama_ulp'], 0, 15),
                $f['kode_penyulang'],
                substr($f['nama_penyulang'], 0, 20),
                $f['valid_jtm_poles_count'],
                $f['protection_devices_count'],
                "{$f['active_translines_count']} (" . number_format($f['total_span_meters'], 0) . "m)",
                $f['readiness_status'],
            ];
        }

        CLI::table($tbody, $thead);

        CLI::write("\nAudit selesai tanpa mutasi data (READ-ONLY).\n", 'green');
    }
}
