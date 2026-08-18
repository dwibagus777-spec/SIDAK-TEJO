<?php

namespace App\Controllers;

use Config\Database;
use Config\Services;

class MigrateController extends BaseController
{
    /**
     * Production Migration & Catalog Seeder Runner
     * Executes DB table creation DDLs and seeds initial catalogs safely.
     */
    public function autoMigrate()
    {
        $db = Database::connect();
        $executed = [];

        // Fail-safe check for missing vendor composer files on remote host
        $missingVendorFiles = [
            FCPATH . '../vendor/symfony/deprecation-contracts/function.php' => "<?php if (!function_exists('trigger_deprecation')) { function trigger_deprecation() {} }",
            FCPATH . '../vendor/phpunit/phpunit/src/Framework/Assert/Functions.php' => "<?php // Dummy placeholder",
            FCPATH . '../vendor/myclabs/deep-copy/src/DeepCopy/deep_copy.php' => "<?php // Dummy placeholder",
        ];
        foreach ($missingVendorFiles as $vPath => $vDummy) {
            if (!file_exists($vPath)) {
                @mkdir(dirname($vPath), 0777, true);
                @file_put_contents($vPath, $vDummy);
            }
        }

        try {
            // Auto-deploy git sync on Hostinger production
            $this->autoDeploy();

            // Auto-heal orphan asset penyulang_ids by matching kode_asset with penyulang table
            if ($db->tableExists('assets') && $db->tableExists('penyulang')) {
                $db->query("UPDATE assets a 
                    JOIN penyulang p ON (
                        a.kode_asset LIKE CONCAT('%', p.kode_penyulang, '%') 
                        OR (a.kode_asset LIKE '%BNJRKMNTRN%' AND p.id = 15)
                    ) 
                    SET a.penyulang_id = p.id 
                    WHERE (a.penyulang_id IS NULL OR a.penyulang_id = 0) AND a.deleted_at IS NULL");
            }

            // 0. Table gardu_induk (Master Gardu Induk)
            $db->query("CREATE TABLE IF NOT EXISTS `gardu_induk` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `kode_gi` VARCHAR(50) NOT NULL UNIQUE,
                `nama_gi` VARCHAR(150) NOT NULL,
                `lokasi` VARCHAR(255) NULL,
                `latitude` DECIMAL(10,8) NULL,
                `longitude` DECIMAL(11,8) NULL,
                `status` VARCHAR(20) DEFAULT 'ACTIVE',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'gardu_induk';

            if ($db->tableExists('penyulang')) {
                $penyulangCols = array_column($db->query("SHOW COLUMNS FROM penyulang")->getResultArray(), 'Field');
                if (!in_array('gi_id', $penyulangCols)) {
                    try {
                        $db->query("ALTER TABLE `penyulang` ADD COLUMN `gi_id` INT UNSIGNED NULL");
                    } catch (\Throwable $exGi) {}
                }
            }

            // Seed Master Gardu Induk if empty
            if ($db->tableExists('gardu_induk')) {
                $giCheck = $db->query("SELECT id FROM gardu_induk LIMIT 1")->getResultArray();
                if (empty($giCheck)) {
                    $gis = [
                        ['kode' => 'GI-BDR-001', 'nama' => 'GI BUDURAN',  'lokasi' => 'Buduran, Sidoarjo'],
                        ['kode' => 'GI-SDR-001', 'nama' => 'GI SIDOARJO', 'lokasi' => 'Sidoarjo Kota'],
                        ['kode' => 'GI-WRU-001', 'nama' => 'GI WARU',     'lokasi' => 'Waru, Sidoarjo'],
                        ['kode' => 'GI-KRN-001', 'nama' => 'GI KRIAN',    'lokasi' => 'Krian, Sidoarjo'],
                    ];
                    foreach ($gis as $g) {
                        $db->query("INSERT INTO `gardu_induk` (`kode_gi`, `nama_gi`, `lokasi`, `status`, `created_at`, `updated_at`) VALUES ('{$g['kode']}', '{$g['nama']}', '{$g['lokasi']}', 'ACTIVE', NOW(), NOW())");
                    }
                }
            }

            // 0B. Table inspection_plannings (Release v2.3.0.30 - Inspection Planning Layer)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_plannings` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `nomor_planning` VARCHAR(100) NOT NULL UNIQUE,
                `title` VARCHAR(255) NOT NULL,
                `inspection_type_id` INT UNSIGNED NOT NULL,
                `gi_id` INT UNSIGNED NULL,
                `ulp_id` INT UNSIGNED NULL,
                `penyulang_id` INT UNSIGNED NULL,
                `jenis_asset` VARCHAR(50) DEFAULT 'SEMUA',
                `assigned_inspector_id` INT UNSIGNED NULL,
                `created_by_user_id` INT UNSIGNED NOT NULL,
                `scheduled_date` DATE NULL,
                `published_at` DATETIME NULL,
                `completed_at` DATETIME NULL,
                `total_assets` INT DEFAULT 0,
                `status` VARCHAR(30) DEFAULT 'DRAFT',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_plannings';

            // 0C. Table inspection_planning_assets (Release v2.3.0.30 - Planning Asset Snapshots)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_planning_assets` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `planning_id` INT UNSIGNED NOT NULL,
                `asset_id` INT UNSIGNED NOT NULL,
                `sequence_no` INT DEFAULT 1,
                `created_at` DATETIME NULL,
                UNIQUE KEY `uniq_planning_asset` (`planning_id`, `asset_id`),
                UNIQUE KEY `uniq_planning_seq` (`planning_id`, `sequence_no`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_planning_assets';

            // 1. Table asset_types (Migration 000004)
            $db->query("CREATE TABLE IF NOT EXISTS `asset_types` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `network_type` VARCHAR(20) DEFAULT 'JTM',
                `icon` VARCHAR(50) DEFAULT 'box',
                `marker_shape` VARCHAR(30) DEFAULT 'circle',
                `marker_size` INT DEFAULT 20,
                `default_color` VARCHAR(20) DEFAULT '#005eb8',
                `is_active` TINYINT(1) DEFAULT 1,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'asset_types';

            // 2. Table construction_types (Migration 000005)
            $db->query("CREATE TABLE IF NOT EXISTS `construction_types` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `network_type` VARCHAR(20) DEFAULT 'JTM',
                `asset_category` VARCHAR(50) NULL,
                `construction_group` VARCHAR(50) NULL,
                `voltage_level` VARCHAR(20) DEFAULT '20kV',
                `standard_reference` VARCHAR(100) DEFAULT 'PLN Standar Konstruksi',
                `description` TEXT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'construction_types';

            // 3. Table network_baselines (Migration 000006 & Schema Reconciliation)
            $db->query("CREATE TABLE IF NOT EXISTS `network_baselines` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NULL,
                `name` VARCHAR(100) NOT NULL,
                `type` VARCHAR(20) DEFAULT 'JTM',
                `network_type` VARCHAR(20) DEFAULT 'JTM',
                `ulp_id` INT UNSIGNED NULL,
                `penyulang_id` INT UNSIGNED NULL,
                `gardu_id` INT UNSIGNED NULL,
                `trafo_id` INT UNSIGNED NULL,
                `version` VARCHAR(20) DEFAULT 'v1.0',
                `effective_date` DATE NULL,
                `total_assets` INT DEFAULT 0,
                `status` VARCHAR(20) DEFAULT 'ACTIVE',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            if ($db->tableExists('network_baselines')) {
                $baseColsRes = $db->query("SHOW COLUMNS FROM network_baselines")->getResultArray();
                $baseColNames = array_column($baseColsRes, 'Field');

                $baseColsPatch = [
                    'network_type'   => "ALTER TABLE `network_baselines` ADD COLUMN `network_type` VARCHAR(20) DEFAULT 'JTM'",
                    'gardu_id'       => "ALTER TABLE `network_baselines` ADD COLUMN `gardu_id` INT UNSIGNED NULL",
                    'trafo_id'       => "ALTER TABLE `network_baselines` ADD COLUMN `trafo_id` INT UNSIGNED NULL",
                    'version'        => "ALTER TABLE `network_baselines` ADD COLUMN `version` VARCHAR(20) DEFAULT 'v1.0'",
                    'effective_date' => "ALTER TABLE `network_baselines` ADD COLUMN `effective_date` DATE NULL",
                ];
                foreach ($baseColsPatch as $col => $sql) {
                    if (!in_array($col, $baseColNames)) {
                        try {
                            $db->query($sql);
                        } catch (\Throwable $exBase) {}
                    }
                }
            }
            $executed[] = 'network_baselines';

            // 4. Table baseline_assets (Migration 000007)
            $db->query("CREATE TABLE IF NOT EXISTS `baseline_assets` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `baseline_id` INT UNSIGNED NOT NULL,
                `asset_id` INT UNSIGNED NOT NULL,
                `sequence_no` INT NOT NULL,
                `distance_from_previous` DECIMAL(10,2) DEFAULT 0.00,
                `section_name` VARCHAR(100) NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `uk_base_asset` (`baseline_id`, `asset_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'baseline_assets';

            // 5. Add columns to assets (Migration 000008 & v2.1.0/v2.3.0 schema reconciliation)
            if ($db->tableExists('assets')) {
                $assetColumnsPatch = [
                    'parent_asset_id'      => "ALTER TABLE `assets` ADD COLUMN `parent_asset_id` INT UNSIGNED NULL",
                    'asset_type_id'        => "ALTER TABLE `assets` ADD COLUMN `asset_type_id` INT UNSIGNED NULL",
                    'construction_type_id' => "ALTER TABLE `assets` ADD COLUMN `construction_type_id` INT UNSIGNED NULL",
                    'sequence_no'          => "ALTER TABLE `assets` ADD COLUMN `sequence_no` INT NULL",
                    'tahun_instalasi'      => "ALTER TABLE `assets` ADD COLUMN `tahun_instalasi` INT NULL",
                    'installation_date'    => "ALTER TABLE `assets` ADD COLUMN `installation_date` DATE NULL",
                    'health_score'         => "ALTER TABLE `assets` ADD COLUMN `health_score` DECIMAL(5,2) DEFAULT 100.00",
                    'health_category'      => "ALTER TABLE `assets` ADD COLUMN `health_category` VARCHAR(20) DEFAULT 'GOOD'",
                    'asset_version'        => "ALTER TABLE `assets` ADD COLUMN `asset_version` VARCHAR(20) DEFAULT 'v1.0'",
                    'deleted_by'           => "ALTER TABLE `assets` ADD COLUMN `deleted_by` INT UNSIGNED NULL",
                    'deleted_reason'       => "ALTER TABLE `assets` ADD COLUMN `deleted_reason` TEXT NULL",
                ];

                foreach ($assetColumnsPatch as $col => $sql) {
                    try {
                        $db->query($sql);
                    } catch (\Throwable $eAsset) {}
                }
            }
            $executed[] = 'assets_columns_updated';

            // 6. Table asset_relationships (Migration 000009)
            $db->query("CREATE TABLE IF NOT EXISTS `asset_relationships` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `source_asset_id` INT UNSIGNED NOT NULL,
                `target_asset_id` INT UNSIGNED NOT NULL,
                `relationship_type` VARCHAR(50) DEFAULT 'CONNECTED_TO',
                `sequence_no` INT DEFAULT 0,
                `effective_date` DATE NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `notes` TEXT NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `uk_asset_rel` (`source_asset_id`, `target_asset_id`, `relationship_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            if ($db->tableExists('asset_relationships')) {
                try {
                    $db->query("ALTER TABLE `asset_relationships` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1");
                } catch (\Throwable $exRel) {}

                try {
                    $db->query("ALTER TABLE `asset_relationships` ADD COLUMN `sequence_no` INT DEFAULT 0");
                } catch (\Throwable $exRel) {}

                try {
                    $db->query("ALTER TABLE `asset_relationships` ADD COLUMN `effective_date` DATE NULL");
                } catch (\Throwable $exRel) {}
            }
            $executed[] = 'asset_relationships';

            // 6.5. Table asset_history (Audit Trail Log)
            $db->query("CREATE TABLE IF NOT EXISTS `asset_history` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `asset_id` INT UNSIGNED NOT NULL,
                `tanggal` DATETIME NULL,
                `jenis_event` VARCHAR(50) NOT NULL,
                `status_lama` VARCHAR(50) NULL,
                `status_baru` VARCHAR(50) NULL,
                `referensi` VARCHAR(100) NULL,
                `deskripsi` TEXT NULL,
                `user_id` INT UNSIGNED NULL,
                `approved_by` INT UNSIGNED NULL,
                `foto_sebelum` VARCHAR(255) NULL,
                `foto_sesudah` VARCHAR(255) NULL,
                `ip_address` VARCHAR(50) NULL,
                `user_agent` VARCHAR(255) NULL,
                `device` VARCHAR(50) NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                KEY `idx_asset_hist` (`asset_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'asset_history';

            // 7. Table inspection_types (Migration 000010)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_types` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `category` VARCHAR(50) DEFAULT 'ROUTINE',
                `description` TEXT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_types';

            // 8. Table inspection_templates (Migration 000011)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_templates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `title` VARCHAR(150) NOT NULL,
                `asset_type` VARCHAR(50) NOT NULL,
                `inspection_type_code` VARCHAR(50) NOT NULL,
                `version` INT DEFAULT 1,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_templates';

            // 9. Table inspection_template_items (Migration 000012)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_template_items` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `template_id` INT UNSIGNED NOT NULL,
                `item_code` VARCHAR(50) NOT NULL,
                `item_name` VARCHAR(150) NOT NULL,
                `check_category` VARCHAR(50) DEFAULT 'VISUAL',
                `sequence_no` INT DEFAULT 1,
                `is_required` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `uk_tpl_item` (`template_id`, `item_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_template_items';

            // 10. Table inspections (Migration 000013)
            $db->query("CREATE TABLE IF NOT EXISTS `inspections` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `inspection_number` VARCHAR(50) NOT NULL UNIQUE,
                `ulp_id` INT UNSIGNED NOT NULL,
                `feeder_id` INT UNSIGNED NULL,
                `inspection_type_id` INT UNSIGNED NOT NULL,
                `inspector_name` VARCHAR(100) NOT NULL,
                `scheduled_date` DATE NOT NULL,
                `status` VARCHAR(20) DEFAULT 'DRAFT',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspections';

            // 11. Table inspection_points (Migration 000014)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_points` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `inspection_id` INT UNSIGNED NOT NULL,
                `asset_id` INT UNSIGNED NOT NULL,
                `sequence_no` INT DEFAULT 1,
                `status` VARCHAR(20) DEFAULT 'PENDING',
                `notes` TEXT NULL,
                `inspected_at` DATETIME NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `uk_insp_asset` (`inspection_id`, `asset_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_points';

            // 12. Table inspection_results (Migration 000015)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_results` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `inspection_point_id` INT UNSIGNED NOT NULL,
                `template_item_id` INT UNSIGNED NOT NULL,
                `result_status` VARCHAR(20) DEFAULT 'PASS',
                `measurement_value` DECIMAL(10,2) NULL,
                `notes` TEXT NULL,
                `temuan_id` INT UNSIGNED NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `uk_point_template_item` (`inspection_point_id`, `template_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_results';

            // 13. Table inspection_photos (Migration 000016)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_photos` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `inspection_point_id` INT UNSIGNED NOT NULL,
                `photo_type` VARCHAR(50) DEFAULT 'CONDITION',
                `file_path` VARCHAR(255) NOT NULL,
                `caption` VARCHAR(150) NULL,
                `client_uuid` VARCHAR(100) NULL,
                `created_at` DATETIME NULL,
                UNIQUE KEY `uk_photo_client_uuid` (`client_uuid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_photos';

            // Seed Catalogs
            $constructionService = new \App\Services\ConstructionService();
            $constructionService->ensureStandardCatalogsSeeded();

            $inspectionService = new \App\Services\InspectionCatalogService();
            $inspectionService->ensureCatalogSeeded();

            // Seed Default Network Baseline if empty
            if ($db->tableExists('network_baselines')) {
                $baseCheck = $db->query("SELECT id FROM network_baselines LIMIT 1")->getResultArray();
                if (empty($baseCheck)) {
                    $db->query("INSERT INTO `network_baselines` (`name`, `network_type`, `status`, `created_at`, `updated_at`) VALUES ('Baseline JTM Sidoarjo Kota (Feeder GEDANGAN)', 'JTM', 'ACTIVE', NOW(), NOW())");
                }
            }

            // Sync active assets into baseline_assets if baseline has no assets attached
            if ($db->tableExists('network_baselines') && $db->tableExists('baseline_assets') && $db->tableExists('assets')) {
                $baselines = $db->query("SELECT id FROM `network_baselines`")->getResultArray();
                foreach ($baselines as $b) {
                    $bId = (int)$b['id'];
                    $bAssetCheck = $db->query("SELECT id FROM `baseline_assets` WHERE `baseline_id` = {$bId} LIMIT 1")->getResultArray();
                    if (empty($bAssetCheck)) {
                        $assets = $db->query("SELECT id FROM `assets` WHERE `status` != 'DELETED' ORDER BY `id` ASC")->getResultArray();
                        $seq = 1;
                        foreach ($assets as $ast) {
                            $aId = (int)$ast['id'];
                            $db->query("INSERT IGNORE INTO `baseline_assets` (`baseline_id`, `asset_id`, `sequence_no`, `created_at`, `updated_at`) VALUES ({$bId}, {$aId}, {$seq}, NOW(), NOW())");
                            $seq++;
                        }
                    }
                }
            }

            $db->resetDataCache();
            $relColumns = $db->query("SHOW COLUMNS FROM asset_relationships")->getResultArray();
            $relColumnNames = array_column($relColumns, 'Field');
            $assetColumns = $db->query("SHOW COLUMNS FROM assets")->getResultArray();
            $assetColumnNames = array_column($assetColumns, 'Field');
            $baseColumns = $db->query("SHOW COLUMNS FROM network_baselines")->getResultArray();
            $baseColumnNames = array_column($baseColumns, 'Field');
            $historyCheck = $db->query("SHOW TABLES LIKE 'asset_history'")->getResultArray();

            // STORAGE CAPABILITY AUDIT (TEST S1, S2, S3, S4)
            $persistentParent = '/home/u532206332/domains/sidaktejo.site/';
            $persistentDir = $persistentParent . 'sidak_storage/foto/';
            $persistentWritable = false;
            $persistentCreated = false;

            if (is_dir($persistentParent) && is_writable($persistentParent)) {
                if (!is_dir($persistentDir)) {
                    @mkdir($persistentDir, 0755, true);
                }
                $persistentCreated = is_dir($persistentDir);
                $persistentWritable = is_writable($persistentDir);
            }

            $canaryFile = $persistentDir . 'CANARY_STORAGE_PERSISTENCE.txt';
            $canaryWritten = false;
            if ($persistentWritable) {
                @file_put_contents($canaryFile, 'CANARY_TEST_TIMESTAMP_' . date('Y-m-d H:i:s'));
                $canaryWritten = is_file($canaryFile);
            }

            $writableUploadsDir = WRITEPATH . 'uploads/foto/';
            if (!is_dir($writableUploadsDir)) {
                @mkdir($writableUploadsDir, 0755, true);
            }
            $writableCanaryFile = $writableUploadsDir . 'CANARY_STORAGE_PERSISTENCE.txt';
            @file_put_contents($writableCanaryFile, 'CANARY_TEST_TIMESTAMP_' . date('Y-m-d H:i:s'));

            $symlinkSupported = function_exists('symlink');
            $symlinkCreated = false;
            $symlinkPath = FCPATH . 'test_symlink_dir';
            if ($symlinkSupported && !file_exists($symlinkPath)) {
                try {
                    @symlink($persistentDir, $symlinkPath);
                    $symlinkCreated = is_link($symlinkPath);
                } catch (\Throwable $symEx) {
                    $symlinkCreated = false;
                }
            }

            $storageAudit = [
                'fcpath'                         => FCPATH,
                'writepath'                        => WRITEPATH,
                'persistent_parent_writable'     => is_writable($persistentParent),
                'persistent_dir_created'         => $persistentCreated,
                'persistent_dir_writable'        => $persistentWritable,
                'persistent_canary_exists'       => is_file($canaryFile),
                'writable_uploads_canary_exists' => is_file($writableCanaryFile),
                'symlink_function_exists'        => $symlinkSupported,
                'symlink_created'                => $symlinkCreated,
            ];

            // LEGACY FILE & BACKUP RESTORE ROUTINE TO SIDAK_STORAGE_PATH
            $targetDir = defined('SIDAK_STORAGE_PATH') ? SIDAK_STORAGE_PATH : WRITEPATH . 'uploads/foto/';
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }

            $restoredFromBackupCount = 0;
            $copiedFromLegacyCount = 0;

            if (is_file(WRITEPATH . 'backups/backup-public-foto-20260814.zip') && class_exists('\ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open(WRITEPATH . 'backups/backup-public-foto-20260814.zip') === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entryName = $zip->getNameIndex($i);
                        $cleanFile = basename($entryName);
                        if (!empty($cleanFile) && !str_starts_with($cleanFile, '.')) {
                            $dest = $targetDir . $cleanFile;
                            if (!file_exists($dest)) {
                                $stream = $zip->getStream($entryName);
                                if ($stream) {
                                    file_put_contents($dest, stream_get_contents($stream));
                                    fclose($stream);
                                    $restoredFromBackupCount++;
                                }
                            }
                        }
                    }
                    $zip->close();
                }
            }

            if (is_dir(FCPATH . 'foto')) {
                $legacyFiles = array_diff(scandir(FCPATH . 'foto'), ['.', '..']);
                foreach ($legacyFiles as $f) {
                    $source = FCPATH . 'foto/' . $f;
                    $dest = $targetDir . $f;
                    if (is_file($source) && !file_exists($dest)) {
                        @copy($source, $dest);
                        $copiedFromLegacyCount++;
                    }
                }
            }

            $persistentFilesList = is_dir($targetDir) ? array_values(array_diff(scandir($targetDir), ['.', '..'])) : [];
            $persistentFilesCount = count($persistentFilesList);

            return $this->response->setJSON([
                'db_name'                   => $db->getDatabase(),
                'asset_history_exist'       => count($historyCheck) > 0,
                'network_type_present'      => in_array('network_type', $baseColumnNames),
                'installation_date_present' => in_array('installation_date', $assetColumnNames),
                'persistent_storage_path'   => $targetDir,
                'persistent_files_count'    => $persistentFilesCount,
                'persistent_files_samples'  => array_slice($persistentFilesList, 0, 20),
                'restored_from_backup'      => $restoredFromBackupCount,
                'copied_from_legacy'        => $copiedFromLegacyCount,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function debugJson()
    {
        $db = Database::connect();
        $totalRaw = $db->table('assets')->countAllResults();
        $totalActive = $db->table('assets')->where('deleted_at IS NULL')->countAllResults();

        $distinctJenis = $db->table('assets')->select('jenis_asset, count(*) as cnt')->where('deleted_at IS NULL')->groupBy('jenis_asset')->get()->getResultArray();
        $distinctPenyulang = $db->table('assets')->select('penyulang_id, count(*) as cnt')->where('deleted_at IS NULL')->groupBy('penyulang_id')->limit(20)->get()->getResultArray();
        $sample15 = $db->table('assets')->where('penyulang_id', 15)->where('deleted_at IS NULL')->limit(5)->get()->getResultArray();
        $sampleAssets = $db->table('assets')->select('id, kode_asset, nama_asset, jenis_asset, ulp_id, penyulang_id, deleted_at')->where('deleted_at IS NULL')->limit(5)->get()->getResultArray();

        return $this->response->setJSON([
            'total_raw'          => $totalRaw,
            'total_active'       => $totalActive,
            'distinct_jenis'     => $distinctJenis,
            'distinct_penyulang' => $distinctPenyulang,
            'sample_15_cnt'      => count($sample15),
            'sample_15'          => $sample15,
            'sample_assets'      => $sampleAssets,
        ]);
    }

    /**
     * Automatic Git Deployment Sync for Hostinger
     */
    public function autoDeploy()
    {
        $output = [];
        try {
            $cmd = 'cd ' . escapeshellarg(FCPATH . '..') . ' && git fetch origin main 2>&1 && git reset --hard origin/main 2>&1';
            
            if (function_exists('shell_exec')) {
                $res = @shell_exec($cmd);
                $output[] = (string)$res;
            } elseif (function_exists('exec')) {
                $outLines = [];
                @exec($cmd, $outLines);
                $output = $outLines;
            }
        } catch (\Throwable $e) {
            $output[] = 'Shell exec notice: ' . $e->getMessage();
        }

        // Purge CodeIgniter view cache
        try {
            $cacheFiles = glob(WRITEPATH . 'cache/*');
            if (is_array($cacheFiles)) {
                foreach ($cacheFiles as $cf) {
                    if (is_file($cf) && !str_contains($cf, 'index.html')) {
                        @unlink($cf);
                    }
                }
            }
        } catch (\Throwable $e) {}

        return $this->response->setJSON([
            'status'     => 'success',
            'message'    => 'Hostinger Git deployment synced & view cache purged!',
            'git_output' => implode("\n", (array)$output)
        ]);
    }
}





