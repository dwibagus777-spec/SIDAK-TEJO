<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

// 1. Create table operational_ahi_recalibration_lineage if missing
if (!$db->tableExists('operational_ahi_recalibration_lineage')) {
    $db->query("CREATE TABLE operational_ahi_recalibration_lineage (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        lineage_code VARCHAR(100) NOT NULL,
        acceptance_id BIGINT UNSIGNED NOT NULL,
        acceptance_code VARCHAR(100) NOT NULL,
        feeder_name VARCHAR(100) NOT NULL,
        section_name VARCHAR(100) NOT NULL,
        before_ahi_score DECIMAL(5,2) NOT NULL,
        qa_evidence_hash VARCHAR(64) NOT NULL,
        qa_quality_score DECIMAL(5,2) NOT NULL,
        technical_acceptance_by VARCHAR(100) NOT NULL,
        post_work_evidence_hash VARCHAR(64) NOT NULL,
        recalculated_boost DECIMAL(5,2) NOT NULL,
        after_ahi_score DECIMAL(5,2) NOT NULL,
        lineage_fingerprint VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Table operational_ahi_recalibration_lineage created successfully!\n";
} else {
    echo "Table operational_ahi_recalibration_lineage already exists!\n";
}

// 2. Create table operational_reliability_learning_events if missing
if (!$db->tableExists('operational_reliability_learning_events')) {
    $db->query("CREATE TABLE operational_reliability_learning_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        event_code VARCHAR(100) NOT NULL,
        feeder_name VARCHAR(100) NOT NULL,
        section_name VARCHAR(100) NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        job_effectiveness_index DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        outage_efficiency_pct DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        recurrence_risk_level VARCHAR(50) NOT NULL DEFAULT 'LOW',
        material_variance_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        advisory_recommendation TEXT NULL,
        learning_fingerprint VARCHAR(64) NOT NULL,
        recorded_by VARCHAR(100) NOT NULL,
        recorded_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Table operational_reliability_learning_events created successfully!\n";
} else {
    echo "Table operational_reliability_learning_events already exists!\n";
}
