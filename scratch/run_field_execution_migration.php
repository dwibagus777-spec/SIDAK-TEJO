<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';
require_once 'app/Database/Migrations/2026-08-23-000014_CreateOperationalFieldExecutionRecordsTable.php';

use App\Database\Migrations\CreateOperationalFieldExecutionRecordsTable;

$db = \Config\Database::connect('default');

try {
    $migration = new CreateOperationalFieldExecutionRecordsTable();
    $migration->up();
    echo "Migration completed successfully!\n";
} catch (\Throwable $e) {
    echo "Migration note: " . $e->getMessage() . "\n";
}

// Add outage duration columns if missing
if ($db->tableExists('operational_field_execution_records')) {
    $fields = $db->getFieldNames('operational_field_execution_records');
    if (!in_array('planned_outage_duration_mins', $fields)) {
        $db->query("ALTER TABLE operational_field_execution_records ADD COLUMN planned_outage_duration_mins INT DEFAULT 240 AFTER progress_percentage");
    }
    if (!in_array('actual_outage_duration_mins', $fields)) {
        $db->query("ALTER TABLE operational_field_execution_records ADD COLUMN actual_outage_duration_mins INT DEFAULT 0 AFTER planned_outage_duration_mins");
    }
    if (!in_array('outage_start_at', $fields)) {
        $db->query("ALTER TABLE operational_field_execution_records ADD COLUMN outage_start_at DATETIME NULL AFTER actual_outage_duration_mins");
    }
    if (!in_array('outage_end_at', $fields)) {
        $db->query("ALTER TABLE operational_field_execution_records ADD COLUMN outage_end_at DATETIME NULL AFTER outage_start_at");
    }
    // Modify execution_status column to support VARCHAR(100) to allow WORK_VERIFIED_CLOSED
    $db->query("ALTER TABLE operational_field_execution_records MODIFY COLUMN execution_status VARCHAR(100) DEFAULT 'WORK_PENDING_FIELD_START'");
    echo "Schema column additions & status VARCHAR modification applied successfully on MySQL default database!\n";
}
