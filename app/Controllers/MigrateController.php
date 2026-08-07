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

            // 3. Table network_baselines (Migration 000006)
            $db->query("CREATE TABLE IF NOT EXISTS `network_baselines` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `type` VARCHAR(20) DEFAULT 'JTM',
                `ulp_id` INT UNSIGNED NULL,
                `penyulang_id` INT UNSIGNED NULL,
                `total_assets` INT DEFAULT 0,
                `status` VARCHAR(20) DEFAULT 'AKTIF',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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

            // 5. Add columns to assets (Migration 000008)
            $fields = $db->getFieldNames('assets');
            if (!in_array('parent_asset_id', $fields)) {
                $db->query("ALTER TABLE `assets` ADD COLUMN `parent_asset_id` INT UNSIGNED NULL AFTER `section_id`");
            }
            if (!in_array('asset_type_id', $fields)) {
                $db->query("ALTER TABLE `assets` ADD COLUMN `asset_type_id` INT UNSIGNED NULL AFTER `parent_asset_id`");
            }
            if (!in_array('construction_type_id', $fields)) {
                $db->query("ALTER TABLE `assets` ADD COLUMN `construction_type_id` INT UNSIGNED NULL AFTER `asset_type_id`");
            }
            if (!in_array('sequence_no', $fields)) {
                $db->query("ALTER TABLE `assets` ADD COLUMN `sequence_no` INT NULL AFTER `construction_type_id`");
            }
            $executed[] = 'assets_columns_updated';

            // 6. Table asset_relationships (Migration 000009)
            $db->query("CREATE TABLE IF NOT EXISTS `asset_relationships` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `source_asset_id` INT UNSIGNED NOT NULL,
                `target_asset_id` INT UNSIGNED NOT NULL,
                `relationship_type` VARCHAR(50) DEFAULT 'CONNECTED_TO',
                `notes` TEXT NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `uk_asset_rel` (`source_asset_id`, `target_asset_id`, `relationship_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'asset_relationships';

            // 7. Table inspection_types (Migration 000010)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_types` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `category` VARCHAR(50) DEFAULT 'JTM',
                `description` TEXT NULL,
                `default_interval_months` INT DEFAULT 3,
                `icon` VARCHAR(50) DEFAULT 'clipboard-check',
                `is_active` TINYINT(1) DEFAULT 1,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_types';

            // 8. Table inspection_templates (Migration 000011)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_templates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `inspection_type_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `asset_category` VARCHAR(50) NULL,
                `construction_type_id` INT UNSIGNED NULL,
                `version` VARCHAR(20) DEFAULT 'v1.0',
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_templates';

            // 9. Table inspection_template_items (Migration 000012)
            $db->query("CREATE TABLE IF NOT EXISTS `inspection_template_items` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `template_id` INT UNSIGNED NOT NULL,
                `item_name` VARCHAR(150) NOT NULL,
                `item_type` VARCHAR(30) DEFAULT 'CHECKLIST',
                `unit` VARCHAR(20) NULL,
                `min_value` DECIMAL(10,2) NULL,
                `max_value` DECIMAL(10,2) NULL,
                `is_photo_required` TINYINT(1) DEFAULT 0,
                `photo_label` VARCHAR(100) NULL,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $executed[] = 'inspection_template_items';

            // 10. Table inspections (Migration 000013)
            $db->query("CREATE TABLE IF NOT EXISTS `inspections` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `nomor_inspeksi` VARCHAR(50) NOT NULL UNIQUE,
                `inspection_type_id` INT UNSIGNED NOT NULL,
                `baseline_id` INT UNSIGNED NULL,
                `ulp_id` INT UNSIGNED NULL,
                `penyulang_id` INT UNSIGNED NULL,
                `inspector_user_id` INT UNSIGNED NOT NULL,
                `start_time` DATETIME NULL,
                `end_time` DATETIME NULL,
                `status` VARCHAR(20) DEFAULT 'DRAFT',
                `total_points` INT DEFAULT 0,
                `passed_points` INT DEFAULT 0,
                `failed_points` INT DEFAULT 0,
                `notes` TEXT NULL,
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

            $allTables = $db->listTables();

            return $this->response->setJSON([
                'success'           => true,
                'message'           => 'Database DDL migration dan catalog seeding berhasil dieksekusi 100% di database Hostinger!',
                'db_name'           => $db->getDatabase(),
                'executed_steps'    => $executed,
                'total_tables'      => count($allTables),
                'asset_types_exist' => in_array('asset_types', $allTables),
                'inspections_exist' => in_array('inspections', $allTables),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }
}
