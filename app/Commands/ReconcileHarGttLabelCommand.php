<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ReconcileHarGttLabelCommand extends BaseCommand
{
    protected $group       = 'Finding';
    protected $name        = 'temuan:reconcile-har-gtt';
    protected $description = 'Reconciles historical pelaksana labels from HAR GARDU to HAR GTT consistently.';
    protected $usage       = 'temuan:reconcile-har-gtt [--execute]';
    protected $options     = [
        '--execute' => 'Execute transactional update (default: dry-run)',
    ];

    public function run(array $params)
    {
        $isExecute = CLI::getOption('execute') !== null;
        $db = \Config\Database::connect();
        $svc = new \App\Services\HarGttReconciliationService($db);

        CLI::write("==================================================================", 'yellow');
        CLI::write("   SIDAK TEJO — REKONSILIASI LABEL PELAKSANA HAR GARDU -> HAR GTT", 'yellow');
        CLI::write("==================================================================", 'yellow');
        CLI::write("Mode: " . ($isExecute ? "LIVE EXECUTION (TRANSACTIONAL UPDATE)" : "DRY-RUN (SIMULASI AUDIT)"), $isExecute ? 'red' : 'green');
        CLI::newLine();

        $snap = $svc->inspect();
        $cTemuan = $snap['temuan_count'];
        $cTemuanGtt = $db->table('temuan')->where('pelaksana', 'HAR GTT')->countAllResults();
        CLI::write("Tabel 'temuan':", 'white');
        CLI::write("  • Record berlabel 'HAR GARDU' : {$cTemuan} baris", 'yellow');
        CLI::write("  • Record berlabel 'HAR GTT'   : {$cTemuanGtt} baris", 'cyan');

        $cWo = $snap['work_orders_count'];
        if ($db->tableExists('work_orders') && $db->fieldExists('pelaksana', 'work_orders')) {
            CLI::write("Tabel 'work_orders':", 'white');
            CLI::write("  • Record berlabel 'HAR GARDU' : {$cWo} baris", 'yellow');
        }

        if (!$isExecute) {
            CLI::newLine();
            CLI::write("Proyeksi Pasca Eksekusi:", 'green');
            CLI::write("  • Target perubahan ke 'HAR GTT' : " . ($cTemuan + $cWo) . " baris", 'green');
            CLI::write("  • Jalankan perintah dengan flag --execute untuk menerapkan perubahan.", 'yellow');
            CLI::write("==================================================================", 'yellow');
            return;
        }

        $res = $svc->reconcile(true, 'CLI_SPARK');

        CLI::newLine();
        CLI::write("Hasil Eksekusi Transaksional:", 'green');
        CLI::write("  • Temuan dimutasi ke 'HAR GTT'      : {$res['temuan_updated']} baris", 'green');
        CLI::write("  • Work Orders dimutasi ke 'HAR GTT' : {$res['work_orders_updated']} baris", 'green');
        CLI::write("  • File Bukti Audit JSON             : " . ($res['audit_file'] ?? '-'), 'cyan');
        CLI::write("==================================================================", 'yellow');
    }
}
