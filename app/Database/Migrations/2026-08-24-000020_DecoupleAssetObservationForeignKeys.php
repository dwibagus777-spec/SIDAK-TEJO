<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DecoupleAssetObservationForeignKeys extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Drop existing CASCADE foreign keys if they exist
        $fksToDrop = [
            'asset_health_history'      => 'fk_hihistory_asset',
            'observation_action_cases'  => 'fk_actioncase_asset',
            'thermovision_observations' => 'fk_thermoobs_asset',
            'vegetation_observations'   => 'fk_vegobs_asset',
        ];

        foreach ($fksToDrop as $table => $fkName) {
            if ($db->tableExists($table)) {
                $checkFk = $db->query("
                    SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
                    WHERE CONSTRAINT_SCHEMA = '{$db->database}' 
                    AND TABLE_NAME = '{$table}' 
                    AND CONSTRAINT_NAME = '{$fkName}'
                ")->getResultArray();

                if (!empty($checkFk)) {
                    $db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
                }
            }
        }

        // 2. Modify asset_id columns to NULLABLE INT(11) UNSIGNED
        $tablesToModify = [
            'asset_health_history',
            'observation_action_cases',
            'thermovision_observations',
            'vegetation_observations',
            'asset_change_history',
        ];

        foreach ($tablesToModify as $table) {
            if ($db->tableExists($table) && in_array('asset_id', $db->getFieldNames($table))) {
                $db->query("ALTER TABLE `{$table}` MODIFY `asset_id` INT(11) UNSIGNED NULL DEFAULT NULL");
            }
        }

        // 3. Re-add Foreign Keys with ON DELETE SET NULL ON UPDATE CASCADE
        if ($db->tableExists('asset_health_history') && $db->tableExists('assets')) {
            $db->query("ALTER TABLE `asset_health_history` ADD CONSTRAINT `fk_hihistory_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }

        if ($db->tableExists('observation_action_cases') && $db->tableExists('assets')) {
            $db->query("ALTER TABLE `observation_action_cases` ADD CONSTRAINT `fk_actioncase_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }

        if ($db->tableExists('thermovision_observations') && $db->tableExists('assets')) {
            $db->query("ALTER TABLE `thermovision_observations` ADD CONSTRAINT `fk_thermoobs_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }

        if ($db->tableExists('vegetation_observations') && $db->tableExists('assets')) {
            $db->query("ALTER TABLE `vegetation_observations` ADD CONSTRAINT `fk_vegobs_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }

        // 4. Detach any remaining seed asset references (ID 1, 2, 3) to NULL across all tables
        $tablesToDetach = [
            'ai_correction_feedback',
            'field_corrections',
            'temuan',
            'asset_change_history',
            'asset_health_history',
            'observation_action_cases',
            'thermovision_observations',
            'vegetation_observations',
        ];

        foreach ($tablesToDetach as $t) {
            if ($db->tableExists($t) && in_array('asset_id', $db->getFieldNames($t))) {
                $db->query("UPDATE `{$t}` SET `asset_id` = NULL WHERE `asset_id` IN (1, 2, 3)");
            }
        }

        // 5. Controlled deletion of the 3 seed assets from assets table
        if ($db->tableExists('assets')) {
            $db->query("
                DELETE FROM `assets` 
                WHERE `id` IN (1, 2, 3) 
                AND `kode_asset` IN ('AST-GRD-001', 'AST-TRF-002', 'AST-KUB-003')
            ");
        }
    }

    public function down()
    {
        // Down migration intentionally preserves decoupled structure for safety
    }
}
