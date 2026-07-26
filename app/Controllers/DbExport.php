<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class DbExport extends Controller
{
    public function index()
    {
        $db = \Config\Database::connect();
        
        try {
            $tables = $db->listTables();
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setBody("Database Connection Error: " . $e->getMessage());
        }

        $output = "-- SIDAK TEJO Live Database Export from Railway\n";
        $output .= "-- Exported on: " . date('Y-m-d H:i:s') . "\n";
        $output .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $output .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $output .= "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $table) {
            // Get CREATE TABLE
            try {
                $query = $db->query("SHOW CREATE TABLE `{$table}`");
                $row = $query->getRowArray();
                $createTableSql = array_values($row)[1];

                $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $output .= $createTableSql . ";\n\n";

                $rows = $db->table($table)->get()->getResultArray();
                if (!empty($rows)) {
                    foreach ($rows as $r) {
                        $cols = array_keys($r);
                        $escapedCols = array_map(fn($c) => "`{$c}`", $cols);
                        $escapedVals = array_map(function($val) use ($db) {
                            if ($val === null) return 'NULL';
                            return $db->escape($val);
                        }, array_values($r));

                        $output .= "INSERT INTO `{$table}` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
                    }
                    $output .= "\n";
                }
            } catch (\Throwable $e) {
                // Skip problematic view/table if any
                continue;
            }
        }

        $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        return $this->response
            ->setHeader('Content-Type', 'application/sql')
            ->setHeader('Content-Disposition', 'attachment; filename="railway_live_sidak_tejo.sql"')
            ->setBody($output);
    }
}
