<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixTemuanAssetFk extends Migration
{
    public function up()
    {
        $db = $this->db;

        if (! $db->tableExists('temuan')) {
            throw new \RuntimeException('[FixTemuanAssetFk] Table temuan does not exist. Aborting.');
        }
        if (! $db->tableExists('assets')) {
            throw new \RuntimeException('[FixTemuanAssetFk] Table assets does not exist. Aborting.');
        }

        $columns = $db->query(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, EXTRA
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND ((TABLE_NAME = \'temuan\' AND COLUMN_NAME = \'asset_id\')
                 OR (TABLE_NAME = \'assets\' AND COLUMN_NAME = \'id\'))'
        )->getResultArray();
        $columnByTable = [];
        foreach ($columns as $column) {
            $columnByTable[$column['TABLE_NAME']] = $column;
        }

        $temuanAssetCol = $columnByTable['temuan'] ?? null;
        $assetsIdCol    = $columnByTable['assets'] ?? null;
        if ($temuanAssetCol === null) {
            throw new \RuntimeException('[FixTemuanAssetFk] Column temuan.asset_id does not exist. Aborting.');
        }
        if ($assetsIdCol === null) {
            throw new \RuntimeException('[FixTemuanAssetFk] Column assets.id does not exist. Aborting.');
        }

        $engines = $db->query(
            'SELECT TABLE_NAME, ENGINE
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (\'temuan\', \'assets\')'
        )->getResultArray();
        $engineByTable = [];
        foreach ($engines as $table) {
            $engineByTable[$table['TABLE_NAME']] = strtoupper((string) $table['ENGINE']);
        }
        if (($engineByTable['assets'] ?? null) !== 'INNODB'
            || ($engineByTable['temuan'] ?? null) !== 'INNODB') {
            throw new \RuntimeException('[FixTemuanAssetFk] Both tables must use InnoDB. Aborting.');
        }

        if (strtolower($assetsIdCol['DATA_TYPE']) !== 'int'
            || stripos($assetsIdCol['COLUMN_TYPE'], 'unsigned') === false
            || $assetsIdCol['IS_NULLABLE'] !== 'NO') {
            throw new \RuntimeException('[FixTemuanAssetFk] assets.id must be a NOT NULL INT UNSIGNED. Aborting.');
        }

        $assetsIdIndex = $db->query(
            'SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'assets\'
               AND NON_UNIQUE = 0
             GROUP BY INDEX_NAME
             HAVING COUNT(*) = 1
                AND SUM(COLUMN_NAME = \'id\' AND SEQ_IN_INDEX = 1) = 1'
        )->getRowArray();
        if ($assetsIdIndex === null) {
            throw new \RuntimeException('[FixTemuanAssetFk] assets.id must have a primary or unique index. Aborting.');
        }

        if (! in_array(strtolower($temuanAssetCol['DATA_TYPE']), ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true)) {
            throw new \RuntimeException('[FixTemuanAssetFk] temuan.asset_id must have an integer type. Aborting.');
        }

        $negativeCount = (int) ($db->query(
            'SELECT COUNT(*) AS total FROM temuan WHERE asset_id < 0'
        )->getRowArray()['total'] ?? 0);
        if ($negativeCount > 0) {
            throw new \RuntimeException("[FixTemuanAssetFk] Found {$negativeCount} negative temuan.asset_id values. No rows were changed.");
        }

        $outOfRangeCount = (int) ($db->query(
            'SELECT COUNT(*) AS total FROM temuan WHERE asset_id > 4294967295'
        )->getRowArray()['total'] ?? 0);
        if ($outOfRangeCount > 0) {
            throw new \RuntimeException("[FixTemuanAssetFk] Found {$outOfRangeCount} temuan.asset_id values outside INT UNSIGNED range. No rows were changed.");
        }

        $orphanCount = (int) ($db->query(
            'SELECT COUNT(*) AS total
             FROM temuan t
             LEFT JOIN assets a ON t.asset_id = a.id
             WHERE t.asset_id IS NOT NULL AND a.id IS NULL'
        )->getRowArray()['total'] ?? 0);
        if ($orphanCount > 0) {
            throw new \RuntimeException("[FixTemuanAssetFk] Found {$orphanCount} orphan temuan.asset_id values. No rows were changed.");
        }

        $existingIndexes = $db->query(
            'SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'temuan\'
               AND COLUMN_NAME = \'asset_id\'
               AND SEQ_IN_INDEX = 1'
        )->getResultArray();
        $targetIndexName = $db->query(
            'SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'temuan\'
               AND INDEX_NAME = \'idx_temuan_asset_id_fk\''
        )->getRowArray();
        if ($existingIndexes === [] && $targetIndexName !== null) {
            throw new \RuntimeException('[FixTemuanAssetFk] Index name idx_temuan_asset_id_fk is already used by another column. Aborting.');
        }

        $constraints = $db->query(
            'SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.ORDINAL_POSITION,
                    k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
                    r.DELETE_RULE, r.UPDATE_RULE
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
             JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
              AND r.TABLE_NAME = k.TABLE_NAME
              AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             WHERE k.CONSTRAINT_SCHEMA = DATABASE()
               AND k.TABLE_NAME = \'temuan\'
               AND k.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION'
        )->getResultArray();
        $assetConstraints = array_values(array_filter(
            $constraints,
            static fn (array $constraint): bool => $constraint['COLUMN_NAME'] === 'asset_id'
        ));
        $expectedConstraint = null;
        if ($assetConstraints !== []) {
            $first = $assetConstraints[0];
            $sameConstraintRows = array_values(array_filter(
                $assetConstraints,
                static fn (array $constraint): bool => $constraint['CONSTRAINT_NAME'] === $first['CONSTRAINT_NAME']
            ));
            $isExpected = count($assetConstraints) === 1
                && count($sameConstraintRows) === 1
                && $first['ORDINAL_POSITION'] === '1'
                && $first['REFERENCED_TABLE_NAME'] === 'assets'
                && $first['REFERENCED_COLUMN_NAME'] === 'id'
                && strtoupper($first['DELETE_RULE']) === 'SET NULL'
                && strtoupper($first['UPDATE_RULE']) === 'CASCADE';
            if (! $isExpected) {
                throw new \RuntimeException('[FixTemuanAssetFk] An incompatible foreign key uses temuan.asset_id. Resolve it manually before migration.');
            }
            $expectedConstraint = $first['CONSTRAINT_NAME'];
        }

        $namedConstraint = $db->query(
            'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME = \'fk_temuan_asset\''
        )->getResultArray();
        if ($namedConstraint !== []
            && ($expectedConstraint !== 'fk_temuan_asset' || count($namedConstraint) !== 1)) {
            throw new \RuntimeException('[FixTemuanAssetFk] Constraint name fk_temuan_asset is already used by a different relationship. Aborting.');
        }

        $columnTypeMatches = strtolower($temuanAssetCol['DATA_TYPE']) === 'int'
            && stripos($temuanAssetCol['COLUMN_TYPE'], 'unsigned') !== false;
        $indexIsPresent = $existingIndexes !== [];
        if (! $columnTypeMatches || $temuanAssetCol['IS_NULLABLE'] !== 'YES' || ! $indexIsPresent) {
            $default = $temuanAssetCol['COLUMN_DEFAULT'];
            if ($default !== null && strtoupper((string) $default) !== 'NULL') {
                throw new \RuntimeException('[FixTemuanAssetFk] temuan.asset_id has a non-null default that cannot be safely preserved during type normalization.');
            }
            if ($temuanAssetCol['EXTRA'] !== '') {
                throw new \RuntimeException('[FixTemuanAssetFk] Refusing to normalize a generated or auto-increment temuan.asset_id column.');
            }
            $comment = $db->escape((string) $temuanAssetCol['COLUMN_COMMENT']);
            $db->query("ALTER TABLE `temuan` MODIFY `asset_id` INT UNSIGNED NULL DEFAULT NULL COMMENT {$comment}");
            if (! $indexIsPresent) {
                $db->query('ALTER TABLE `temuan` ADD INDEX `idx_temuan_asset_id_fk` (`asset_id`)');
            }
        }

        if ($expectedConstraint === null) {
            $db->query(
                'ALTER TABLE `temuan`
                 ADD CONSTRAINT `fk_temuan_asset`
                 FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`)
                 ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    public function down()
    {
        $db = $this->db;
        if (! $db->tableExists('temuan')) {
            return;
        }

        $constraint = $db->query(
            'SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,
                    k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
             JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
              AND r.TABLE_NAME = k.TABLE_NAME
              AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             WHERE k.CONSTRAINT_SCHEMA = DATABASE()
               AND k.TABLE_NAME = \'temuan\'
               AND k.CONSTRAINT_NAME = \'fk_temuan_asset\''
        )->getResultArray();
        if ($constraint === []) {
            return;
        }

        if (count($constraint) !== 1
            || $constraint[0]['COLUMN_NAME'] !== 'asset_id'
            || $constraint[0]['REFERENCED_TABLE_NAME'] !== 'assets'
            || $constraint[0]['REFERENCED_COLUMN_NAME'] !== 'id'
            || strtoupper($constraint[0]['DELETE_RULE']) !== 'SET NULL'
            || strtoupper($constraint[0]['UPDATE_RULE']) !== 'CASCADE') {
            throw new \RuntimeException('[FixTemuanAssetFk] Refusing to drop fk_temuan_asset because its definition differs from this migration.');
        }

        $db->query('ALTER TABLE `temuan` DROP FOREIGN KEY `fk_temuan_asset`');
    }
}