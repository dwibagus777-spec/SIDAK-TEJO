<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

// 1. Create table operational_capital_portfolios if missing
if (!$db->tableExists('operational_capital_portfolios')) {
    $db->query("CREATE TABLE operational_capital_portfolios (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        portfolio_code VARCHAR(100) NOT NULL,
        portfolio_name VARCHAR(150) NOT NULL,
        fiscal_year INT NOT NULL DEFAULT 2026,
        target_feeder_count INT NOT NULL DEFAULT 1,
        total_capex_budget DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        total_opex_budget DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        simulated_saidi_reduction_mins DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        simulated_saifi_reduction_freq DECIMAL(8,4) NOT NULL DEFAULT 0.00,
        portfolio_status VARCHAR(100) NOT NULL DEFAULT 'DRAFT_PROPOSED',
        ratified_by VARCHAR(100) NULL,
        ratified_at DATETIME NULL,
        executive_certificate_hash VARCHAR(64) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Table operational_capital_portfolios created successfully!\n";
} else {
    echo "Table operational_capital_portfolios already exists!\n";
}

// 2. Create table operational_capital_portfolio_events if missing
if (!$db->tableExists('operational_capital_portfolio_events')) {
    $db->query("CREATE TABLE operational_capital_portfolio_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        portfolio_id BIGINT UNSIGNED NOT NULL,
        portfolio_code VARCHAR(100) NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        previous_status VARCHAR(100) NOT NULL,
        new_status VARCHAR(100) NOT NULL,
        event_description TEXT NULL,
        recorded_by VARCHAR(100) NOT NULL,
        recorded_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Table operational_capital_portfolio_events created successfully!\n";
} else {
    echo "Table operational_capital_portfolio_events already exists!\n";
}
