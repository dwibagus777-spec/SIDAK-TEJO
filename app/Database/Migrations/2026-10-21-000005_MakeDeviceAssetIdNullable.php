<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeDeviceAssetIdNullable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('fault_cases')) {
            try {
                $this->db->query("ALTER TABLE `fault_cases` MODIFY COLUMN `device_asset_id` INT(11) UNSIGNED NULL DEFAULT NULL");
            } catch (\Throwable $e) {
                log_message('error', '[Migration] Failed to make device_asset_id nullable: ' . $e->getMessage());
            }
            try {
                $this->db->query("ALTER TABLE `fault_cases` MODIFY COLUMN `fault_event_id` INT(11) UNSIGNED NULL DEFAULT NULL");
            } catch (\Throwable $e) {
                log_message('error', '[Migration] Failed to make fault_event_id nullable: ' . $e->getMessage());
            }
        }

        if ($this->db->tableExists('fault_actual_findings')) {
            try {
                $this->db->query("ALTER TABLE `fault_actual_findings` MODIFY COLUMN `found_asset_id` INT(11) UNSIGNED NULL DEFAULT NULL");
            } catch (\Throwable $e) {
                log_message('error', '[Migration] Failed to make found_asset_id nullable: ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        // No-op
    }
}
