<?php

require 'app/Config/Database.php';

try {
    $db = \Config\Database::connect();
    echo "DB Driver: " . $db->DBDriver . "\n";
    echo "Database: " . $db->database . "\n";
    
    $tables = ['penyulang', 'ulps', 'sections', 'assets', 'gis_translines', 'gis_transline_proposals', 'temuan'];
    foreach ($tables as $t) {
        if ($db->tableExists($t)) {
            $cnt = $db->table($t)->countAllResults();
            echo sprintf("Table %-25s: %d rows\n", $t, $cnt);
        } else {
            echo sprintf("Table %-25s: NOT FOUND\n", $t);
        }
    }
} catch (\Throwable $e) {
    echo "Error connecting to local DB: " . $e->getMessage() . "\n";
}
