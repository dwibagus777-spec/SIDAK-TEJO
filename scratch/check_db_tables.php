<?php
$mysqli = new mysqli('localhost', 'root', '', 'sidaktejo');
if ($mysqli->connect_error) {
    echo "DB error: " . $mysqli->connect_error;
    exit(1);
}

$tables = [
    'operational_field_execution_records',
    'operational_execution_progress_events',
    'planned_shutdown_scopes',
    'remediation_work_packages',
    'material_requisitions',
    'gis_translines',
    'assets'
];

foreach ($tables as $t) {
    $res = $mysqli->query("SHOW TABLES LIKE '$t'");
    echo sprintf("%-40s : %s\n", $t, ($res && $res->num_rows > 0 ? 'EXISTS' : 'MISSING'));
}
