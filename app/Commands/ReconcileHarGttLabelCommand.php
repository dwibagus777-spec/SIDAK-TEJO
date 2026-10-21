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

        CLI::write("==================================================================", 'yellow');
        CLI::write("   SIDAK TEJO — REKONSILIASI LABEL PELAKSANA HAR GARDU -> HAR GTT", 'yellow');
        CLI::write("==================================================================", 'yellow');
        CLI::write("Mode: " . ($isExecute ? "LIVE EXECUTION (TRANSACTIONAL UPDATE)" : "DRY-RUN (SIMULASI AUDIT)"), $isExecute ? 'red' : 'green');
        CLI::newLine();

        // 1. Temuan table
        $cTemuan = $db->table('temuan')->where('pelaksana', 'HAR GARDU')->countAllResults();
        $cTemuanGtt = $db->table('temuan')->where('pelaksana', 'HAR GTT')->countAllResults();
        CLI::write("Tabel 'temuan':", 'white');
        CLI::write("  • Record berlabel 'HAR GARDU' : {$cTemuan} baris", 'yellow');
        CLI::write("  • Record berlabel 'HAR GTT'   : {$cTemuanGtt} baris", 'cyan');

        // 2. Work Orders table
        $cWo = 0;
        if ($db->tableExists('work_orders') && $db->fieldExists('pelaksana', 'work_orders')) {
            $cWo = $db->table('work_orders')->where('pelaksana', 'HAR GARDU')->countAllResults();
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

        $db->transStart();
        $updatedTemuan = 0;
        $updatedWo = 0;

        if ($cTemuan > 0) {
            $db->table('temuan')
                ->where('pelaksana', 'HAR GARDU')
                ->update(['pelaksana' => 'HAR GTT', 'updated_at' => date('Y-m-d H:i:s')]);
            $updatedTemuan = $cTemuan;
        }

        if ($cWo > 0) {
            $db->table('work_orders')
                ->where('pelaksana', 'HAR GARDU')
                ->update(['pelaksana' => 'HAR GTT', 'updated_at' => date('Y-m-d H:i:s')]);
            $updatedWo = $cWo;
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            CLI::error("Gagal melakukan pembaruan label pada database.");
            return;
        }

        CLI::newLine();
        CLI::write("Hasil Eksekusi Transaksional:", 'green');
        CLI::write("  • Temuan dimutasi ke 'HAR GTT'      : {$updatedTemuan} baris", 'green');
        CLI::write("  • Work Orders dimutasi ke 'HAR GTT' : {$updatedWo} baris", 'green');
        CLI::write("  • Total Terverifikasi Berhasil     : " . ($updatedTemuan + $updatedWo) . " baris", 'green');
        CLI::write("==================================================================", 'yellow');
    }
}
