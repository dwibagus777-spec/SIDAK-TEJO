<?php

namespace App\Controllers;

class DbExport extends BaseController
{
    public function index()
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Hanya Administrator yang memiliki akses ke fitur ini.');
        }

        $db = \Config\Database::connect();
        
        try {
            $tables = $db->listTables();
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setBody("Database Connection Error: " . $e->getMessage());
        }

        $output = "-- SIDAK TEJO Live Database Export\n";
        $output .= "-- Exported on: " . date('Y-m-d H:i:s') . "\n";
        $output .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $output .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $output .= "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $table) {
            try {
                $query = $db->query("SHOW CREATE TABLE `{$table}`");
                $row = $query->getRowArray();
                if (!$row) continue;
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
                continue;
            }
        }

        $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        log_activity('EXPORT_DATABASE', 'Mengekspor mentahan kueri database SQL.');

        return $this->response
            ->setHeader('Content-Type', 'application/sql')
            ->setHeader('Content-Disposition', 'attachment; filename="sidak_tejo_database_export.sql"')
            ->setBody($output);
    }
}
