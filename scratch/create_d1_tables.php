<?php
// scratch/create_d1_tables.php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

echo "Creating D1 Digital Twin tables in MySQL sidaktejo...\n";

$queryScenarios = "CREATE TABLE IF NOT EXISTS `digital_twin_scenarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `scenario_uuid` VARCHAR(64) NOT NULL UNIQUE,
    `base_topology_snapshot` VARCHAR(64) NOT NULL DEFAULT 'TOPOLOGY-20260928-245-81c43a7f',
    `base_data_fingerprint` VARCHAR(64) NOT NULL,
    `scenario_type` ENUM('OUTAGE_SCENARIO', 'MAINTENANCE_SCENARIO', 'CAPEX_SCENARIO', 'REPLACEMENT_SCENARIO', 'RISK_MITIGATION_SCENARIO') NOT NULL,
    `scenario_name` VARCHAR(255) NOT NULL,
    `feeder_id` VARCHAR(50) NULL,
    `section_id` VARCHAR(50) NULL,
    `asset_id` VARCHAR(50) NULL,
    `input_parameters_json` TEXT NOT NULL,
    `simulation_results_json` TEXT NULL,
    `comparison_kpi_json` TEXT NULL,
    `input_fingerprint` VARCHAR(64) NOT NULL,
    `output_fingerprint` VARCHAR(64) NULL,
    `forensic_bundle_hash` VARCHAR(64) NULL,
    `status` ENUM('DRAFT', 'SIMULATED', 'ANALYZED', 'RECOMMENDED', 'HUMAN_APPROVED', 'EXECUTION_QUEUED', 'EXECUTED') NOT NULL DEFAULT 'DRAFT',
    `recommendation_level` VARCHAR(20) NOT NULL DEFAULT 'L4_RECOMMENDED',
    `created_by` VARCHAR(100) NOT NULL DEFAULT 'SYSTEM_SIMULATOR',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_scenario_type` (`scenario_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_uuid` (`scenario_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$db->query($queryScenarios);
echo "Table digital_twin_scenarios created or verified.\n";

$queryBundles = "CREATE TABLE IF NOT EXISTS `digital_twin_provenance_bundles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bundle_id` VARCHAR(100) NOT NULL UNIQUE,
    `scenario_uuid` VARCHAR(64) NOT NULL,
    `production_snapshot_id` VARCHAR(64) NOT NULL,
    `input_hash` VARCHAR(64) NOT NULL,
    `output_hash` VARCHAR(64) NOT NULL,
    `prev_bundle_hash` VARCHAR(64) NOT NULL,
    `bundle_hash` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_bundle_scenario` (`scenario_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$db->query($queryBundles);
echo "Table digital_twin_provenance_bundles created or verified.\n";

echo "Done.\n";
