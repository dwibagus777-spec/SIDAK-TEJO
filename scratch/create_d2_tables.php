<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

echo "Recreating Track D2 Transline Proposal tables in MySQL sidaktejo...\n";

// Ensure column proposal_uuid exists
if ($db->tableExists('gis_transline_proposals')) {
    $fields = $db->getFieldNames('gis_transline_proposals');
    if (!in_array('proposal_uuid', $fields)) {
        $db->query("DROP TABLE `gis_transline_proposals`;");
        echo "Dropped legacy gis_transline_proposals table.\n";
    }
}

$queryProposals = "CREATE TABLE IF NOT EXISTS `gis_transline_proposals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `proposal_uuid` VARCHAR(64) NOT NULL UNIQUE,
    `base_topology_snapshot` VARCHAR(64) NOT NULL DEFAULT 'TOPOLOGY-20260928-245-81c43a7f',
    `start_asset_id` VARCHAR(50) NOT NULL,
    `end_asset_id` VARCHAR(50) NOT NULL,
    `feeder_id` VARCHAR(50) NULL,
    `section_id` VARCHAR(50) NULL,
    `proposed_length_meters` DOUBLE NOT NULL DEFAULT 0,
    `candidate_type` ENUM('AUTO_MATCH', 'NEEDS_HUMAN_REVIEW', 'PROVISIONAL', 'INVALID') NOT NULL DEFAULT 'NEEDS_HUMAN_REVIEW',
    `rejection_reason` VARCHAR(255) NULL,
    `status` ENUM('PENDING_REVIEW', 'PROMOTED_AUTHORITATIVE', 'REJECTED', 'SUPERSEDED') NOT NULL DEFAULT 'PENDING_REVIEW',
    `evidence_json` TEXT NULL,
    `input_fingerprint` VARCHAR(64) NOT NULL,
    `output_fingerprint` VARCHAR(64) NOT NULL,
    `promoted_by` VARCHAR(100) NULL,
    `promoted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_start_end` (`start_asset_id`, `end_asset_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_candidate_type` (`candidate_type`),
    INDEX `idx_uuid` (`proposal_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$db->query($queryProposals);
echo "Table gis_transline_proposals created or verified.\n";

echo "Done.\n";
