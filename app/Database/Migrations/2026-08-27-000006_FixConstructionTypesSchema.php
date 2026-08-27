<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hotfix migration to guarantee construction_types has all CR-06 canonical columns
 * regardless of prior migration state on production.
 */
class FixConstructionTypesSchema extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('construction_types')) {
            $fieldsToAdd = [];

            if (!$db->fieldExists('construction_code', 'construction_types')) {
                $fieldsToAdd['construction_code'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'id',
                ];
            }

            if (!$db->fieldExists('construction_name', 'construction_types')) {
                $fieldsToAdd['construction_name'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                    'after'      => 'construction_code',
                ];
            }

            if (!$db->fieldExists('construction_family', 'construction_types')) {
                $fieldsToAdd['construction_family'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'JTM',
                ];
            }

            if (!$db->fieldExists('asset_domain', 'construction_types')) {
                $fieldsToAdd['asset_domain'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'TIANG',
                ];
            }

            if (!$db->fieldExists('approval_status', 'construction_types')) {
                $fieldsToAdd['approval_status'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'ACTIVE',
                ];
            }

            if (!$db->fieldExists('source_sheet', 'construction_types')) {
                $fieldsToAdd['source_sheet'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ];
            }

            if (!$db->fieldExists('source_row', 'construction_types')) {
                $fieldsToAdd['source_row'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ];
            }

            if (!empty($fieldsToAdd)) {
                $this->forge->addColumn('construction_types', $fieldsToAdd);
            }

            // Sync construction_code and construction_name from legacy columns if available
            try {
                if ($db->fieldExists('code', 'construction_types') && $db->fieldExists('construction_code', 'construction_types')) {
                    $db->query("UPDATE construction_types SET construction_code = code WHERE construction_code IS NULL OR construction_code = ''");
                }
                if ($db->fieldExists('name', 'construction_types') && $db->fieldExists('construction_name', 'construction_types')) {
                    $db->query("UPDATE construction_types SET construction_name = name WHERE construction_name IS NULL OR construction_name = ''");
                }
            } catch (\Throwable $e) {
                log_message('error', '[FixConstructionTypesSchema] Column sync notice: ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        // No destructive rollback needed
    }
}
