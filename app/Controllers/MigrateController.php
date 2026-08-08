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

        try {
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

            $db->resetDataCache();
            $relColumns = $db->query("SHOW COLUMNS FROM asset_relationships")->getResultArray();
            $relColumnNames = array_column($relColumns, 'Field');
            $assetColumns = $db->query("SHOW COLUMNS FROM assets")->getResultArray();
            $assetColumnNames = array_column($assetColumns, 'Field');
            $baseColumns = $db->query("SHOW COLUMNS FROM network_baselines")->getResultArray();
            $baseColumnNames = array_column($baseColumns, 'Field');
            $historyCheck = $db->query("SHOW TABLES LIKE 'asset_history'")->getResultArray();

            return $this->response->setJSON([
                'db_name'                   => $db->getDatabase(),
                'asset_history_exist'       => count($historyCheck) > 0,
                'network_type_present'      => in_array('network_type', $baseColumnNames),
                'installation_date_present' => in_array('installation_date', $assetColumnNames),
                'network_baselines_fields'  => implode(', ', $baseColumnNames),
                'assets_fields'             => implode(', ', $assetColumnNames),
                'rel_fields'                => implode(', ', $relColumnNames),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
