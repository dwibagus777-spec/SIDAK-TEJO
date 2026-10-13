<?php

require_once 'vendor/autoload.php';
// We can use CodeIgniter database or query directly
$db = \Config\Database::connect();

echo "=== QUERYING CONSTRUCTION_TYPES TABLE ===\n";

if ($db->tableExists('construction_types')) {
    $types = $db->table('construction_types')->orderBy('id', 'ASC')->get()->getResultArray();
    echo "Total construction types in DB: " . count($types) . "\n\n";

    printf("%-4s | %-12s | %-45s | %-10s | %-10s\n", "ID", "Code", "Name", "Family", "Status");
    echo str_repeat('-', 90) . "\n";

    foreach ($types as $t) {
        printf("%-4d | %-12s | %-45s | %-10s | %-10s\n",
            $t['id'],
            $t['construction_code'] ?? ($t['code'] ?? ''),
            substr($t['construction_name'] ?? ($t['name'] ?? ''), 0, 45),
            $t['construction_family'] ?? ($t['family'] ?? ''),
            $t['approval_status'] ?? ($t['status'] ?? '')
        );
    }
} else {
    echo "construction_types table not found in current local DB connection.\n";
}
