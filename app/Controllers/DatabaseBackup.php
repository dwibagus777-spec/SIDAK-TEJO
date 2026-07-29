<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class DatabaseBackup extends BaseController
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = WRITEPATH . 'backups/database/';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public function index()
    {
        if (!check_role(['administrator', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Hanya Administrator yang memiliki akses ke modul Backup Database.');
        }

        $db = \Config\Database::connect();
        
        // Database Metadata
        $dbName = $db->database;
        $dbHost = $db->hostname;
        $tables = [];
        $dbSize = 0;

        try {
            $tables = $db->listTables();
            $query = $db->query("SELECT SUM(data_length + index_length) AS db_size FROM information_schema.TABLES WHERE table_schema = " . $db->escape($dbName));
            if ($query) {
                $row = $query->getRowArray();
                $dbSize = (float)($row['db_size'] ?? 0);
            }
        } catch (\Throwable $e) {
            log_message('error', '[DatabaseBackup] Error fetching DB metadata: ' . $e->getMessage());
        }

        // List Backup Files
        $files = [];
        if (is_dir($this->backupDir)) {
            $scan = scandir($this->backupDir);
            foreach ($scan as $file) {
                if (str_ends_with($file, '.sql')) {
                    $filePath = $this->backupDir . $file;
                    $files[] = [
                        'filename'   => $file,
                        'size'       => filesize($filePath),
                        'size_formatted' => $this->formatSize(filesize($filePath)),
                        'created_at' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'is_old'     => (time() - filemtime($filePath)) > (30 * 86400),
                    ];
                }
            }
            usort($files, fn($a, $b) => strcmp($b['filename'], $a['filename']));
        }

        // Recent Audit Logs for Backup
        $auditLogs = [];
        if ($db->tableExists('activity_logs')) {
            try {
                $auditLogs = $db->table('activity_logs')
                    ->whereIn('action', ['DATABASE_BACKUP', 'DATABASE_RESTORE', 'DATABASE_DOWNLOAD', 'DATABASE_DELETE'])
                    ->orderBy('id', 'DESC')
                    ->get(20)
                    ->getResultArray();
            } catch (\Throwable $e) {
                log_message('error', '[DatabaseBackup] Audit log error: ' . $e->getMessage());
            }
        }

        $data = [
            'title'       => 'Module Backup & Restore Database Hostinger',
            'db_name'     => $dbName,
            'db_host'     => $dbHost,
            'db_size'     => $this->formatSize($dbSize),
            'table_count' => count($tables),
            'files'       => $files,
            'audit_logs'  => $auditLogs,
        ];

        return view('backup/index', $data);
    }

    public function create()
    {
        if (!check_role(['administrator', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Hanya Administrator yang berhak membuat backup.');
        }

        $db = \Config\Database::connect();
        
        try {
            $tables = $db->listTables();
            $output = "-- =====================================================\n";
            $output .= "-- SIDAK TEJO HOSTINGER DATABASE BACKUP\n";
            $output .= "-- Domain: https://sidaktejo.site\n";
            $output .= "-- Database: " . $db->database . "\n";
            $output .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- =====================================================\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS = 0;\n";
            $output .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
            $output .= "SET NAMES utf8mb4;\n\n";

            foreach ($tables as $table) {
                $qCreate = $db->query("SHOW CREATE TABLE `{$table}`");
                if (!$qCreate) continue;
                $rowCreate = $qCreate->getRowArray();
                if (!$rowCreate) continue;

                $createSql = array_values($rowCreate)[1];

                $output .= "-- -----------------------------------------------------\n";
                $output .= "-- Table structure for `{$table}`\n";
                $output .= "-- -----------------------------------------------------\n";
                $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $output .= $createSql . ";\n\n";

                $qRows = $db->table($table)->get();
                if ($qRows && ($rows = $qRows->getResultArray())) {
                    $output .= "-- Data for `{$table}` (" . count($rows) . " rows)\n";
                    foreach ($rows as $r) {
                        $cols = array_keys($r);
                        $escapedCols = array_map(fn($c) => "`{$c}`", $cols);
                        $escapedVals = array_map(function($val) use ($db) {
                            if ($val === null) return 'NULL';
                            return $db->escape($val);
                        }, array_values($r));

                        $output .= "INSERT INTO `{$table}` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
                    }
                    $output .= "\n";
                }
            }

            $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            $filename = 'sidaktejo_backup_' . date('Ymd_His') . '.sql';
            $filePath = $this->backupDir . $filename;

            file_put_contents($filePath, $output);
            $fileSize = filesize($filePath);

            log_activity('DATABASE_BACKUP', "Berhasil membuat backup database: {$filename} (" . $this->formatSize($fileSize) . ")");

            return redirect()->to(site_url('backup-database'))->with('success', "Backup database Hostinger berhasil dibuat: {$filename}");
        } catch (\Throwable $e) {
            log_message('error', '[DatabaseBackup::create] Failed: ' . $e->getMessage());
            return redirect()->to(site_url('backup-database'))->with('error', 'Gagal membuat backup database: ' . $e->getMessage());
        }
    }

    public function download(string $filename)
    {
        if (!check_role(['administrator', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $filename = basename($filename);
        $filePath = $this->backupDir . $filename;

        if (!file_exists($filePath)) {
            return redirect()->to(site_url('backup-database'))->with('error', 'File backup tidak ditemukan.');
        }

        log_activity('DATABASE_DOWNLOAD', "Mengunduh file backup database: {$filename}");

        return $this->response->download($filePath, null);
    }

    public function delete(string $filename)
    {
        if (!check_role(['administrator', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Hanya Administrator yang berhak menghapus backup.');
        }

        $filename = basename($filename);
        $filePath = $this->backupDir . $filename;

        if (file_exists($filePath)) {
            unlink($filePath);
            log_activity('DATABASE_DELETE', "Menghapus file backup database: {$filename}");
            return redirect()->to(site_url('backup-database'))->with('success', "File backup {$filename} berhasil dihapus.");
        }

        return redirect()->to(site_url('backup-database'))->with('error', 'File backup tidak ditemukan.');
    }

    public function cleanOldBackups()
    {
        if (!check_role(['administrator', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $count = 0;
        if (is_dir($this->backupDir)) {
            $scan = scandir($this->backupDir);
            foreach ($scan as $file) {
                if (str_ends_with($file, '.sql')) {
                    $filePath = $this->backupDir . $file;
                    if ((time() - filemtime($filePath)) > (30 * 86400)) {
                        unlink($filePath);
                        $count++;
                    }
                }
            }
        }

        log_activity('DATABASE_DELETE', "Pembersihan otomatis: {$count} file backup lama (>30 hari) dihapus.");

        return redirect()->to(site_url('backup-database'))->with('success', "Pembersihan selesai: {$count} file backup lama dihapus.");
    }

    public function restore()
    {
        if (!check_role(['administrator', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Hanya Super Admin yang dapat memulihkan database.');
        }

        $file = $this->request->getFile('backup_file');
        $selectedFile = $this->request->getPost('selected_filename');

        $sqlContent = '';
        $sourceName = '';

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $sqlContent = file_get_contents($file->getTempName());
            $sourceName = $file->getClientName();
        } elseif (!empty($selectedFile)) {
            $filePath = $this->backupDir . basename($selectedFile);
            if (file_exists($filePath)) {
                $sqlContent = file_get_contents($filePath);
                $sourceName = basename($selectedFile);
            }
        }

        if (empty($sqlContent)) {
            return redirect()->to(site_url('backup-database'))->with('error', 'File SQL backup tidak valid atau kosong.');
        }

        $db = \Config\Database::connect();
        
        try {
            $db->query("SET FOREIGN_KEY_CHECKS = 0;");
            
            // Execute statements split by semicolon
            $statements = array_filter(array_map('trim', explode(";\n", $sqlContent)));
            foreach ($statements as $stmt) {
                if (!empty($stmt) && !str_starts_with($stmt, '--')) {
                    $db->query($stmt);
                }
            }

            $db->query("SET FOREIGN_KEY_CHECKS = 1;");

            log_activity('DATABASE_RESTORE', "Berhasil memulihkan database Hostinger dari file: {$sourceName}");

            return redirect()->to(site_url('backup-database'))->with('success', "Database Hostinger berhasil dipulihkan dari {$sourceName}.");
        } catch (\Throwable $e) {
            log_message('error', '[DatabaseBackup::restore] Restore error: ' . $e->getMessage());
            return redirect()->to(site_url('backup-database'))->with('error', 'Gagal memulihkan database: ' . $e->getMessage());
        }
    }

    private function formatSize(float $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' Bytes';
    }
}
