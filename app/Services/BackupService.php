<?php

namespace App\Services;

use ZipArchive;

class BackupService
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = WRITEPATH . 'backups/';
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Dapatkan daftar berkas backup yang tersedia
     */
    public function getBackupList(): array
    {
        $list = [];
        $files = glob($this->backupDir . 'backup_sidaktejo_*.zip');

        if ($files) {
            foreach ($files as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $time = filemtime($file);

                $list[] = [
                    'filename' => $filename,
                    'filepath' => $file,
                    'size'     => $size,
                    'size_fmt' => $this->formatSize($size),
                    'created_at' => date('Y-m-d H:i:s', $time),
                ];
            }
        }

        usort($list, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $list;
    }

    /**
     * Buat kueri SQL Dump Database
     */
    public function generateSqlDump(): string
    {
        $db = \Config\Database::connect();
        $tables = $db->listTables();

        $sql = "-- SIDAK TEJO Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $table) {
            try {
                $query = $db->query("SHOW CREATE TABLE `{$table}`");
                $row = $query->getRowArray();
                if (!$row) continue;
                
                $createSql = array_values($row)[1];

                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql .= $createSql . ";\n\n";

                $rows = $db->table($table)->get()->getResultArray();
                if (!empty($rows)) {
                    foreach ($rows as $r) {
                        $cols = array_keys($r);
                        $escapedCols = array_map(fn($c) => "`{$c}`", $cols);
                        $escapedVals = array_map(function($val) use ($db) {
                            if ($val === null) return 'NULL';
                            return $db->escape($val);
                        }, array_values($r));

                        $sql .= "INSERT INTO `{$table}` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
                    }
                    $sql .= "\n";
                }
            } catch (\Throwable $ex) {
                log_message('warning', "[BackupService] Error backing up table {$table}: " . $ex->getMessage());
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $sql;
    }

    /**
     * Buat Full Backup ZIP (Database + Folder Foto + Config)
     */
    public function createBackupZip(): ?string
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $timestamp = date('Ymd_His');
        $zipFilename = 'backup_sidaktejo_' . $timestamp . '.zip';
        $zipPath = $this->backupDir . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            log_message('error', '[BackupService] Gagal membuat file ZIP backup.');
            return null;
        }

        // 1. Database Dump SQL
        $sqlData = $this->generateSqlDump();
        $zip->addFromString('database.sql', $sqlData);

        // 2. Folder Foto (public/foto/)
        $fotoDir = FCPATH . 'foto/';
        if (is_dir($fotoDir)) {
            $this->addFolderToZip($fotoDir, $zip, 'foto/');
        }

        // 3. Folder Config (app/Config/)
        $configDir = APPPATH . 'Config/';
        if (is_dir($configDir)) {
            $this->addFolderToZip($configDir, $zip, 'config/');
        }

        // 4. Manifest Metadata
        $manifest = [
            'app_name'     => 'SIDAK TEJO',
            'version'      => '2.0',
            'created_at'   => date('Y-m-d H:i:s'),
            'timestamp'    => $timestamp,
            'has_database' => true,
            'has_foto'     => is_dir($fotoDir),
            'has_config'   => is_dir($configDir),
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $zip->close();
        log_activity('CREATE_BACKUP', 'Membuat full backup ZIP: ' . $zipFilename);

        return $zipFilename;
    }

    /**
     * Restore sistem dari file ZIP backup
     */
    public function restoreFromZip(string $zipFilePath): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        if (!file_exists($zipFilePath)) {
            return ['success' => false, 'message' => 'Berkas ZIP backup tidak ditemukan.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            return ['success' => false, 'message' => 'Gagal membuka berkas ZIP. File mungkin rusak.'];
        }

        $extractPath = WRITEPATH . 'temp_restore_' . time() . '/';
        @mkdir($extractPath, 0755, true);

        $zip->extractTo($extractPath);
        $zip->close();

        $restoredItems = [];

        // 1. Restore Database jika database.sql ada
        $sqlFile = $extractPath . 'database.sql';
        if (file_exists($sqlFile)) {
            $db = \Config\Database::connect();
            $sqlContent = file_get_contents($sqlFile);
            
            // Execute SQL in chunks
            $queries = array_filter(array_map('trim', explode(";\n", $sqlContent)));
            $db->query("SET FOREIGN_KEY_CHECKS = 0");
            foreach ($queries as $q) {
                if (!empty($q)) {
                    try {
                        $db->query($q);
                    } catch (\Throwable $e) {
                        log_message('warning', '[Restore] SQL Query Warning: ' . $e->getMessage());
                    }
                }
            }
            $db->query("SET FOREIGN_KEY_CHECKS = 1");
            $restoredItems[] = 'Database SQL';
        }

        // 2. Restore Foto jika folder foto/ ada
        $tempFotoDir = $extractPath . 'foto/';
        if (is_dir($tempFotoDir)) {
            $targetFotoDir = FCPATH . 'foto/';
            if (!is_dir($targetFotoDir)) {
                @mkdir($targetFotoDir, 0755, true);
            }
            $this->copyDirectory($tempFotoDir, $targetFotoDir);
            $restoredItems[] = 'Folder Foto';
        }

        // Bersihkan folder temporer
        $this->deleteDirectory($extractPath);

        log_activity('RESTORE_BACKUP', 'Melakukan restore sistem dari backup: ' . implode(', ', $restoredItems));

        return [
            'success' => true,
            'message' => 'Restore berhasil diselesaikan untuk: ' . implode(', ', $restoredItems) . '.',
            'items'   => $restoredItems
        ];
    }

    /**
     * Hapus berkas backup ZIP
     */
    public function deleteBackup(string $filename): bool
    {
        $filename = basename($filename);
        $filePath = $this->backupDir . $filename;

        if (file_exists($filePath) && str_ends_with($filename, '.zip')) {
            @unlink($filePath);
            log_activity('DELETE_BACKUP', 'Menghapus berkas backup: ' . $filename);
            return true;
        }
        return false;
    }

    /**
     * Helper rekursif memasukkan folder ke ZIP
     */
    private function addFolderToZip(string $folderPath, ZipArchive $zip, string $zipSubFolder = ''): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(realpath($folderPath)) + 1);
                $zip->addFile($filePath, $zipSubFolder . str_replace('\\', '/', $relativePath));
            }
        }
    }

    private function copyDirectory(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                if (is_dir($src . '/' . $file)) {
                    $this->copyDirectory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function deleteDirectory(string $dir): bool
    {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
