<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixTemuanAssetFk extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // ================================================================
        // PRECONDITION CHECKS (Read-Only Verification)
        // ================================================================

        // 1. Verify tables exist
        if (!$db->tableExists('temuan')) {
            log_message('error', '[FixTemuanAssetFk] Table temuan does not exist. Aborting.');
            return;
        }

        if (!$db->tableExists('assets')) {
            log_message('error', '[FixTemuanAssetFk] Table assets does not exist. Aborting.');
            return;
        }

        // 2. Check current asset_id column type in temuan
        $temuanAssetCol = $db->query("
            SELECT COLUMN_TYPE, IS_NULLABLE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'temuan' 
              AND COLUMN_NAME = 'asset_id'
        ")->getRowArray();

        if (!$temuanAssetCol) {
            log_message('error', '[FixTemuanAssetFk] Column temuan.asset_id does not exist. Aborting.');
            return;
        }

        // 3. Check assets.id column type
        $assetsIdCol = $db->query("
            SELECT COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'assets' 
              AND COLUMN_NAME = 'id'
        ")->getRowArray();

        if (!$assetsIdCol) {
            log_message('error', '[FixTemuanAssetFk] Column assets.id does not exist. Aborting.');
            return;
        }

        // 4. Check for orphan records (temuan.asset_id referencing non-existent assets.id)
        $orphanCount = $db->query("
            SELECT COUNT(*) as cnt
            FROM `temuan` t
            LEFT JOIN `assets` a ON t.asset_id = a.id
            WHERE t.asset_id IS NOT NULL AND a.id IS NULL
        ")->getRowArray();

        $orphanCount = (int)($orphanCount['cnt'] ?? 0);

        if ($orphanCount > 0) {
            log_message('error', "[FixTemuanAssetFk] Found {$orphanCount} orphan records in temuan.asset_id. Cannot create FK. Manual cleanup required first.");
            // Optionally: auto-fix by setting orphans to NULL
            // $db->query("UPDATE `temuan` SET `asset_id` = NULL WHERE `asset_id` IS NOT NULL AND `asset_id` NOT IN (SELECT `id` FROM `assets`)");
            return;
        }

        // 5. Check if FK already exists
        $fkExists = $db->query("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'temuan' 
              AND CONSTRAINT_NAME = 'fk_temuan_asset'
        ")->getRowArray();

        if ($fkExists) {
            log_message('info', '[FixTemuanAssetFk] FK fk_temuan_asset already exists. Skipping creation.');
            // Still verify column type matches
            $isUnsigned = stripos($temuanAssetCol['COLUMN_TYPE'], 'unsigned') !== false;
            if (!$isUnsigned) {
                log_message('warning', '[FixTemuanAssetFk] FK exists but temuan.asset_id is SIGNED. Consider manual type fix.');
            }
            return;
        }

        // ================================================================
        // MIGRATION STEPS
        // ================================================================

        $forge = \Config\Database::forge();

        // Step 1: Ensure temuan.asset_id is UNSIGNED INT(11) NULL to match assets.id
        $isUnsigned = stripos($temuanAssetCol['COLUMN_TYPE'], 'unsigned') !== false;
        
        if (!$isUnsigned) {
            log_message('info', '[FixTemuanAssetFk] Converting temuan.asset_id from SIGNED to UNSIGNED...');
            
            // Use MODIFY COLUMN to change type
            try {
                $db->query("ALTER TABLE `temuan` MODIFY `asset_id` INT(11) UNSIGNED NULL DEFAULT NULL");
                log_message('info', '[FixTemuanAssetFk] Successfully modified temuan.asset_id to UNSIGNED.');
            } catch (\Throwable $e) {
                log_message('error', '[FixTemuanAssetFk] Failed to modify temuan.asset_id type: ' . $e->getMessage());
                throw $e;
            }
        } else {
            log_message('info', '[FixTemuanAssetFk] temuan.asset_id is already UNSIGNED.');
        }

        // Step 2: Create the foreign key constraint
        try {
            $db->query("
                ALTER TABLE `temuan` 
                ADD CONSTRAINT `fk_temuan_asset` 
                FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) 
                ON DELETE SET NULL ON UPDATE CASCADE
            ");
            log_message('info', '[FixTemuanAssetFk] Successfully created FK fk_temuan_asset.');
        } catch (\Throwable $e) {
            log_message('error', '[FixTemuanAssetFk] Failed to create FK fk_temuan_asset: ' . $e->getMessage());
            throw $e;
        }

        // ================================================================
        // POST-MIGRATION VALIDATION
        // ================================================================

        // Verify FK exists
        $fkVerify = $db->query("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'temuan' 
              AND CONSTRAINT_NAME = 'fk_temuan_asset'
        ")->getRowArray();

        if (!$fkVerify) {
            log_message('error', '[FixTemuanAssetFk] POST-VERIFICATION FAILED: FK fk_temuan_asset not found after creation.');
            throw new \RuntimeException('FK creation verification failed');
        }

        // Verify no new orphans introduced
        $orphanVerify = $db->query("
            SELECT COUNT(*) as cnt
            FROM `temuan` t
            LEFT JOIN `assets` a ON t.asset_id = a.id
            WHERE t.asset_id IS NOT NULL AND a.id IS NULL
        ")->getRowArray();

        $orphanVerify = (int)($orphanVerify['cnt'] ?? 0);

        if ($orphanVerify > 0) {
            log_message('error', "[FixTemuanAssetFk] POST-VERIFICATION FAILED: {$orphanVerify} new orphan records detected after FK creation.");
            throw new \RuntimeException('Orphan records detected after FK creation');
        }

        // Verify column type
        $typeVerify = $db->query("
            SELECT COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'temuan' 
              AND COLUMN_NAME = 'asset_id'
        ")->getRowArray();

        $isUnsignedVerify = stripos($typeVerify['COLUMN_TYPE'] ?? '', 'unsigned') !== false;
        
        if (!$isUnsignedVerify) {
            log_message('error', '[FixTemuanAssetFk] POST-VERIFICATION FAILED: temuan.asset_id is not UNSIGNED after migration.');
            throw new \RuntimeException('Column type verification failed');
        }

        log_message('info', '[FixTemuanAssetFk] Migration completed successfully. FK fk_temuan_asset active, column type UNSIGNED, zero orphans.');
    }

    public function down()
    {
        $db = \Config\Database::connect();

        // Rollback: Drop FK first
        $fkExists = $db->query("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'temuan' 
              AND CONSTRAINT_NAME = 'fk_temuan_asset'
        ")->getRowArray();

        if ($fkExists) {
            try {
                $db->query("ALTER TABLE `temuan` DROP FOREIGN KEY `fk_temuan_asset`");
                log_message('info', '[FixTemuanAssetFk] Rollback: Dropped FK fk_temuan_asset.');
            } catch (\Throwable $e) {
                log_message('error', '[FixTemuanAssetFk] Rollback failed to drop FK: ' . $e->getMessage());
            }
        }

        // Rollback: Revert column type to SIGNED (to match pre-migration state)
        try {
            $db->query("ALTER TABLE `temuan` MODIFY `asset_id` INT(11) NULL DEFAULT NULL");
            log_message('info', '[FixTemuanAssetFk] Rollback: Reverted temuan.asset_id to SIGNED INT(11).');
        } catch (\Throwable $e) {
            log_message('error', '[FixTemuanAssetFk] Rollback failed to revert column type: ' . $e->getMessage());
        }

        log_message('info', '[FixTemuanAssetFk] Rollback completed.');
    }
}