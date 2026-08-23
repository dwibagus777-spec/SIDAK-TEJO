<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFindingRecurrenceAndAssetLink extends Migration
{
    public function up()
    {
        // 1. ADDITIVE COLUMNS TO TABLE temuan
        if ($this->db->tableExists('temuan')) {
            $fields = [
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'section_id',
                ],
                'component_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'jenis_temuan',
                ],
                'defect_location_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'component_code',
                ],
                'finding_fingerprint' => [
                    'type'       => 'CHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'after'      => 'defect_location_code',
                ],
                'first_detected_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'tanggal_temuan',
                ],
                'last_observed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'first_detected_at',
                ],
                'observation_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 1,
                    'after'      => 'last_observed_at',
                ],
                'recurrence_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                    'after'      => 'observation_count',
                ],
                'is_recurring' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'recurrence_count',
                ],
                'is_overdue' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'is_recurring',
                ],
                'current_severity' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'prioritas',
                ],
                'peak_severity' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'current_severity',
                ],
                'case_status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['OPEN', 'IN_PROGRESS', 'WAITING_EXECUTION', 'RESOLVED', 'CLOSED', 'CANCELLED'],
                    'default'    => 'OPEN',
                    'after'      => 'status',
                ],
            ];

            // Selective Add Field
            foreach ($fields as $fieldName => $fieldConfig) {
                if (!$this->db->fieldExists($fieldName, 'temuan')) {
                    $this->forge->addColumn('temuan', [$fieldName => $fieldConfig]);
                }
            }

            // Deterministic Index Creation with INFORMATION_SCHEMA check (Created BEFORE FK)
            $indexes = [
                'idx_temuan_asset'       => "CREATE INDEX `idx_temuan_asset` ON `temuan` (`asset_id`)",
                'idx_temuan_fingerprint' => "CREATE INDEX `idx_temuan_fingerprint` ON `temuan` (`finding_fingerprint`)",
                'idx_temuan_open_case'   => "CREATE INDEX `idx_temuan_open_case` ON `temuan` (`asset_id`, `finding_fingerprint`, `case_status`)",
                'idx_temuan_open_aging'  => "CREATE INDEX `idx_temuan_open_aging` ON `temuan` (`case_status`, `first_detected_at`)",
            ];

            foreach ($indexes as $indexName => $createSql) {
                $idxCheck = $this->db->query("SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'temuan' AND INDEX_NAME = '$indexName'")->getRow();
                if (!$idxCheck) {
                    $this->db->query($createSql);
                }
            }

            // Safe Foreign Key Addition (FK asset_id -> assets.id) with Type Harmonization & Graceful Fallback
            if ($this->db->tableExists('assets') && $this->db->fieldExists('asset_id', 'temuan')) {
                try {
                    $assetCol = $this->db->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'id'")->getRow();
                    if ($assetCol) {
                        $isUnsigned = stripos($assetCol->COLUMN_TYPE, 'unsigned') !== false;
                        if (!$isUnsigned) {
                            $this->db->query("ALTER TABLE `temuan` MODIFY `asset_id` INT(11) NULL;");
                        }
                    }
                    $fkCheck = $this->db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'temuan' AND CONSTRAINT_NAME = 'fk_temuan_asset'")->getRow();
                    if (!$fkCheck) {
                        $this->db->query("ALTER TABLE `temuan` ADD CONSTRAINT `fk_temuan_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
                    }
                } catch (\Throwable $e) {
                    log_message('warning', 'Foreign key fk_temuan_asset bypassed gracefully: ' . $e->getMessage());
                }
            }

            // Deterministic Legacy Data Backfill (HARDENED v3)
            $this->db->query("
                UPDATE `temuan` SET 
                    `first_detected_at` = COALESCE(`first_detected_at`, `tanggal_temuan`, `created_at`),
                    `last_observed_at` = COALESCE(`last_observed_at`, `first_detected_at`, `tanggal_temuan`, `created_at`),
                    `observation_count` = CASE WHEN `observation_count` IS NULL OR `observation_count` < 1 THEN 1 ELSE `observation_count` END,
                    `recurrence_count` = CASE WHEN `observation_count` > 1 THEN `observation_count` - 1 ELSE 0 END,
                    `is_recurring` = CASE WHEN `observation_count` > 1 THEN 1 ELSE 0 END,
                    `case_status` = CASE WHEN `status` IN ('SELESAI', 'CLOSED') THEN 'RESOLVED' ELSE `case_status` END;
            ");
        }

        // 2. CREATE TABLE temuan_observations (Histori Penemuan Ulang)
        if (!$this->db->tableExists('temuan_observations')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'temuan_id' => [
                    'type'       => 'INT', // Matched with temuan.id (INT 11 UNSIGNED)
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'inspection_id' => [
                    'type'       => 'INT', // Matched with inspections.id if exists
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'observed_at' => [
                    'type' => 'DATETIME',
                ],
                'severity' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'condition_notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'foto_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'observed_by' => [
                    'type'       => 'INT', // Matched with users.id (INT 11 UNSIGNED)
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['temuan_id', 'observed_at'], false, false, 'idx_obs_temuan_date');
            $this->forge->addKey('observed_by', false, false, 'idx_obs_user');
            $this->forge->createTable('temuan_observations');

            if ($this->db->tableExists('users')) {
                try {
                    $userCol = $this->db->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id'")->getRow();
                    if ($userCol) {
                        $isUnsigned = stripos($userCol->COLUMN_TYPE, 'unsigned') !== false;
                        if (!$isUnsigned) {
                            $this->db->query("ALTER TABLE `temuan_observations` MODIFY `observed_by` INT(11) NULL;");
                        }
                    }
                    $fkCheck = $this->db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'temuan_observations' AND CONSTRAINT_NAME = 'fk_obs_user'")->getRow();
                    if (!$fkCheck) {
                        $this->db->query("ALTER TABLE `temuan_observations` ADD CONSTRAINT `fk_obs_user` FOREIGN KEY (`observed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
                    }
                } catch (\Throwable $e) {
                    log_message('warning', 'Foreign key fk_obs_user bypassed gracefully: ' . $e->getMessage());
                }
            }

            // 3. Baseline Observation History Backfill for Legacy Findings (HARDENED v3)
            $this->db->query("
                INSERT INTO `temuan_observations` (
                    `temuan_id`,
                    `observed_at`,
                    `severity`,
                    `condition_notes`,
                    `foto_path`,
                    `observed_by`,
                    `created_at`,
                    `updated_at`
                )
                SELECT
                    t.id,
                    COALESCE(t.first_detected_at, t.tanggal_temuan, t.created_at, NOW()),
                    t.current_severity,
                    t.detail_temuan,
                    t.foto_path,
                    t.created_by,
                    COALESCE(t.created_at, t.first_detected_at, NOW()),
                    NOW()
                FROM `temuan` t
                WHERE NOT EXISTS (
                    SELECT 1 FROM `temuan_observations` o WHERE o.temuan_id = t.id
                );
            ");
        }
    }

    public function down()
    {
        // 1. Drop Table temuan_observations first
        $this->forge->dropTable('temuan_observations', true);

        // 2. Drop Foreign Key and Composite Indexes explicitly before dropping columns
        if ($this->db->tableExists('temuan')) {
            $fkCheck = $this->db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'temuan' AND CONSTRAINT_NAME = 'fk_temuan_asset'")->getRow();
            if ($fkCheck) {
                $this->db->query("ALTER TABLE `temuan` DROP FOREIGN KEY `fk_temuan_asset`;");
            }

            $indexesToDrop = [
                'idx_temuan_open_case',
                'idx_temuan_open_aging',
                'idx_temuan_fingerprint',
                'idx_temuan_asset',
            ];

            foreach ($indexesToDrop as $indexName) {
                $idxCheck = $this->db->query("SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'temuan' AND INDEX_NAME = '$indexName'")->getRow();
                if ($idxCheck) {
                    $this->db->query("DROP INDEX `$indexName` ON `temuan`;");
                }
            }

            $columnsToDrop = [
                'asset_id', 'component_code', 'defect_location_code', 
                'finding_fingerprint', 'first_detected_at', 'last_observed_at', 
                'observation_count', 'recurrence_count', 'is_recurring', 
                'is_overdue', 'current_severity', 'peak_severity', 'case_status'
            ];

            foreach ($columnsToDrop as $col) {
                if ($this->db->fieldExists($col, 'temuan')) {
                    $this->forge->dropColumn('temuan', $col);
                }
            }
        }
    }
}
