<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFieldObservationTables extends Migration
{
    public function up()
    {
        // 1. vegetation_observations Table
        if (!$this->db->tableExists('vegetation_observations')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'inspection_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'distance_meters' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                ],
                'wind_contact' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'foto_evidence_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'observed_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'observed_at' => [
                    'type' => 'DATETIME',
                ],
                'supersedes_observation_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'is_valid' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'invalidated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'invalidated_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'invalidation_reason' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
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
            $this->forge->addKey(['asset_id', 'is_valid', 'observed_at'], false, false, 'idx_vegobs_asset_valid_date');
            $this->forge->createTable('vegetation_observations');

            if ($this->db->tableExists('assets')) {
                $this->db->query("ALTER TABLE `vegetation_observations` ADD CONSTRAINT `fk_vegobs_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
            }
        }

        // 2. thermovision_observations Table
        if (!$this->db->tableExists('thermovision_observations')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'inspection_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'inspection_domain' => [
                    'type'       => 'ENUM',
                    'constraint' => ['JTM_PDKB', 'HAR_GTT'],
                    'default'    => 'JTM_PDKB',
                ],
                'measured_temperature_c' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                ],
                'ambient_temperature_c' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'measurement_point' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'foto_thermal_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'observed_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'observed_at' => [
                    'type' => 'DATETIME',
                ],
                'supersedes_observation_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'is_valid' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'invalidated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'invalidated_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'invalidation_reason' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
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
            $this->forge->addKey(['asset_id', 'is_valid', 'observed_at'], false, false, 'idx_thermoobs_asset_valid_date');
            $this->forge->createTable('thermovision_observations');

            if ($this->db->tableExists('assets')) {
                try {
                    $assetCol = $this->db->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'id'")->getRow();
                    if ($assetCol) {
                        $isUnsigned = stripos($assetCol->COLUMN_TYPE, 'unsigned') !== false;
                        if (!$isUnsigned) {
                            $this->db->query("ALTER TABLE `thermovision_observations` MODIFY `asset_id` INT(11);");
                        }
                    }
                    $this->db->query("ALTER TABLE `thermovision_observations` ADD CONSTRAINT `fk_thermoobs_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
                } catch (\Throwable $e) {
                    log_message('warning', 'Foreign key fk_thermoobs_asset bypassed gracefully: ' . $e->getMessage());
                }
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('thermovision_observations', true);
        $this->forge->dropTable('vegetation_observations', true);
    }
}
