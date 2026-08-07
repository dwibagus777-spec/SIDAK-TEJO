<?php

namespace App\Controllers;

use Config\Services;

class MigrateController extends BaseController
{
    /**
     * Production Migration & Catalog Seeder Runner
     * Executes CI4 Database Migrations and populates missing catalogs safely.
     */
    public function autoMigrate()
    {
        try {
            $migrations = Services::migrations();
            $result = $migrations->latest();

            // Instantiate Services to trigger ensureCatalogSeeded() for initial catalog population
            $constructionService = new \App\Services\ConstructionService();
            $inspectionService   = new \App\Services\InspectionCatalogService();

            $db = \Config\Database::connect();
            $tables = $db->listTables();

            return $this->response->setJSON([
                'success'           => true,
                'message'           => 'Database migration dan catalog seeding berhasil dieksekusi di database production Hostinger!',
                'db_name'           => $db->getDatabase(),
                'total_tables'      => count($tables),
                'asset_types_exist' => in_array('asset_types', $tables),
                'inspections_exist' => in_array('inspections', $tables),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }
}
