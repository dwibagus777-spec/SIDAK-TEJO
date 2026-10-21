<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class InvestigateFinding656Command extends BaseCommand
{
    protected $group       = 'Finding';
    protected $name        = 'finding:investigate-656';
    protected $description = 'Deep investigation of temuan #656 and #657 across database, soft-deleted rows, backups, and logs.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        CLI::write("==================================================================", 'yellow');
        CLI::write("   SIDAK TEJO — INVESTIGASI MENDALAM TEMUAN #656 & #657           ", 'yellow');
        CLI::write("==================================================================", 'yellow');

        // 1. Check exact ID 656 and 657 in temuan (with deleted_at)
        $exact = $db->query("SELECT id, nomor_temuan, jenis_temuan, detail_temuan, pelaksana, noga, latitude, longitude, created_at, updated_at, deleted_at FROM temuan WHERE id IN (656, 657)")->getResultArray();
        CLI::write("1. Pencarian Langsung ID IN (656, 657) pada tabel 'temuan' (termasuk soft-deleted):", 'white');
        if (empty($exact)) {
            CLI::write("   -> TIDAK DITEMUKAN baris dengan id = 656 atau 657.", 'red');
        } else {
            foreach ($exact as $r) {
                CLI::write(sprintf("   -> ID #%d | %s | %s | deleted_at: %s", $r['id'], $r['nomor_temuan'], $r['detail_temuan'], var_export($r['deleted_at'], true)), 'green');
            }
        }
        CLI::newLine();

        // 2. Statistics on ID range and count of deleted rows
        $stats = $db->query("
            SELECT 
                MIN(id) AS min_id, 
                MAX(id) AS max_id, 
                COUNT(*) AS total_all_rows, 
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS active_rows, 
                SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS soft_deleted_rows
            FROM temuan
        ")->getRowArray();
        CLI::write("2. Statistik Rentang ID dan Baris pada tabel 'temuan':", 'white');
        CLI::write(sprintf("   • ID Minimum       : %d", $stats['min_id']), 'cyan');
        CLI::write(sprintf("   • ID Maksimum      : %d", $stats['max_id']), 'cyan');
        CLI::write(sprintf("   • Total Seluruh Baris : %d baris", $stats['total_all_rows']), 'cyan');
        CLI::write(sprintf("   • Baris Aktif      : %d baris", $stats['active_rows']), 'green');
        CLI::write(sprintf("   • Baris Terhapus (Soft-Deleted): %d baris", $stats['soft_deleted_rows']), 'yellow');
        CLI::newLine();

        // 3. Search for sequence numbers 656 or 657 in nomor_temuan or detail_temuan or noga
        CLI::write("3. Pencarian Berdasarkan String '656' atau '657' (nomor_temuan, detail_temuan, noga):", 'white');
        $likeRows = $db->query("
            SELECT id, nomor_temuan, jenis_temuan, detail_temuan, noga, deleted_at 
            FROM temuan 
            WHERE nomor_temuan LIKE '%656%' OR nomor_temuan LIKE '%657%' 
               OR detail_temuan LIKE '%656%' OR detail_temuan LIKE '%657%'
               OR noga LIKE '%656%' OR noga LIKE '%657%'
        ")->getResultArray();
        if (empty($likeRows)) {
            CLI::write("   -> Tidak ada temuan yang memuat string '656' atau '657'.", 'light_gray');
        } else {
            foreach ($likeRows as $lr) {
                CLI::write(sprintf("   -> ID #%d [%s] (deleted: %s): %s | NOGA: %s", 
                    $lr['id'], $lr['nomor_temuan'], var_export($lr['deleted_at'], true), substr($lr['detail_temuan'], 0, 70), var_export($lr['noga'], true)
                ), 'green');
            }
        }
        CLI::newLine();

        // 4. Check all soft-deleted rows in temuan
        CLI::write("4. Rincian Seluruh Baris Soft-Deleted pada tabel 'temuan':", 'white');
        $deletedRows = $db->query("SELECT id, nomor_temuan, jenis_temuan, detail_temuan, deleted_at FROM temuan WHERE deleted_at IS NOT NULL ORDER BY id ASC")->getResultArray();
        if (empty($deletedRows)) {
            CLI::write("   -> Tidak ada baris soft-deleted.", 'green');
        } else {
            CLI::write(sprintf("   -> Ditemukan %d baris soft-deleted:", count($deletedRows)), 'yellow');
            foreach ($deletedRows as $dr) {
                CLI::write(sprintf("      * ID #%d [%s]: %s (deleted_at: %s)", $dr['id'], $dr['nomor_temuan'], substr($dr['detail_temuan'], 0, 60), $dr['deleted_at']), 'yellow');
            }
        }
        CLI::newLine();

        // 5. Search in other tables referencing temuan
        CLI::write("5. Pencarian pada Tabel Terkait (temuan_materials, dll.):", 'white');
        $tables = $db->listTables();
        $refFound = false;
        foreach ($tables as $table) {
            $fields = $db->getFieldNames($table);
            if (in_array('temuan_id', $fields, true)) {
                $c656 = $db->table($table)->whereIn('temuan_id', [656, 657])->countAllResults();
                if ($c656 > 0) {
                    $refFound = true;
                    CLI::write("   -> Tabel {$table} memuat {$c656} baris yang merujuk temuan_id 656/657!", 'red');
                }
            }
        }
        if (!$refFound) {
            CLI::write("   -> Tidak ada tabel anak atau relasi FK yang merujuk ID 656 atau 657.", 'green');
        }
        CLI::newLine();

        // 6. Search in writable/backups and audit files
        CLI::write("6. Pencarian pada Berkas Backup JSON dan Log Sistem:", 'white');
        $backupDirs = [WRITEPATH . 'backups', WRITEPATH . 'audits', WRITEPATH . 'logs'];
        $backupHits = [];
        foreach ($backupDirs as $dir) {
            if (!is_dir($dir)) continue;
            $files = glob($dir . '/*.*');
            foreach ($files as $f) {
                $content = @file_get_contents($f);
                if ($content && (strpos($content, '656') !== false || strpos($content, '657') !== false)) {
                    $backupHits[] = basename($f);
                }
            }
        }
        if (empty($backupHits)) {
            CLI::write("   -> Tidak ditemukan jejak 656/657 di berkas backup/audits.", 'green');
        } else {
            CLI::write("   -> Ditemukan penyebutan '656'/'657' pada berkas: " . implode(', ', array_unique($backupHits)), 'cyan');
        }
        CLI::newLine();

        // 7. Check all currently linked findings in temuan table
        CLI::write("7. Seluruh Temuan yang Terikat Aset (asset_id IS NOT NULL):", 'white');
        $linked = $db->query("
            SELECT t.id, t.nomor_temuan, t.asset_id, t.latitude, t.longitude, a.kode_asset, a.nama_asset, p.nama_penyulang, t.pelaksana, t.detail_temuan 
            FROM temuan t
            LEFT JOIN assets a ON a.id = t.asset_id
            LEFT JOIN penyulang p ON p.id = t.penyulang_id
            WHERE t.asset_id IS NOT NULL AND t.deleted_at IS NULL
            ORDER BY t.id ASC
        ")->getResultArray();
        CLI::write(sprintf("   • Total Temuan Terikat Aset: %d temuan", count($linked)), 'green');
        foreach ($linked as $l) {
            CLI::write(sprintf("   -> Temuan #%d [%s - %s] => Aset #%d (%s - %s) di Penyulang %s | Koordinat: %s, %s",
                $l['id'], $l['nomor_temuan'], $l['pelaksana'], $l['asset_id'], $l['kode_asset'] ?? 'UNKNOWN', $l['nama_asset'] ?? 'UNKNOWN', $l['nama_penyulang'] ?? '-', $l['latitude'], $l['longitude']
            ), 'green');
        }
        CLI::write("==================================================================", 'yellow');
    }
}
