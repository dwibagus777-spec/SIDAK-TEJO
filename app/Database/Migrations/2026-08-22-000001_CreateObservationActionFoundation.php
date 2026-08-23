<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateObservationActionFoundation extends Migration
{
    public function up()
    {
        // 1. observation_action_cases Table
        if (!$this->db->tableExists('observation_action_cases')) {
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
                'source_observation_type' => [
                    'type'       => 'ENUM',
                    'constraint' => ['VEGETATION', 'THERMOVISION'],
                ],
                'source_observation_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'severity_at_open' => [
                    'type'       => 'ENUM',
                    'constraint' => ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL', 'EMERGENCY'],
                ],
                'priority' => [
                    'type'       => 'TINYINT',
                    'constraint' => 2,
                    'comment'    => '1: Emergency, 2: Top, 3: High, 4: Medium, 5: Normal',
                ],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => [
                        'OPEN',
                        'EMERGENCY_ACTION_TRIGGERED',
                        'ACKNOWLEDGED',
                        'ACTION_PLANNED',
                        'IN_PROGRESS',
                        'RESOLVED',
                        'VERIFIED',
                        'SUPERSEDED'
                    ],
                    'default'    => 'OPEN',
                ],
                'opened_at' => [
                    'type' => 'DATETIME',
                ],
                'opened_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'acknowledged_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'acknowledged_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'planned_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'started_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'resolved_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'resolved_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'verified_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'verified_by' => [
                    'type'       => 'INT',
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
            $this->forge->addKey(['asset_id', 'status'], false, false, 'idx_actioncases_asset_status');
            $this->forge->createTable('observation_action_cases');

            if ($this->db->tableExists('assets')) {
                try {
                    $assetCol = $this->db->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'id'")->getRow();
                    if ($assetCol) {
                        $isUnsigned = stripos($assetCol->COLUMN_TYPE, 'unsigned') !== false;
                        if (!$isUnsigned) {
                            $this->db->query("ALTER TABLE `observation_action_cases` MODIFY `asset_id` INT(11);");
                        }
                    }
                    $this->db->query("ALTER TABLE `observation_action_cases` ADD CONSTRAINT `fk_actioncase_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
                } catch (\Throwable $e) {
                    log_message('warning', 'Foreign key fk_actioncase_asset bypassed gracefully: ' . $e->getMessage());
                }
            }
        }

        // 2. observation_action_events Table (Append-Only Lifecycle Events)
        if (!$this->db->tableExists('observation_action_events')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'action_case_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'from_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'to_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'event_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 80,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'performed_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'performed_at' => [
                    'type' => 'DATETIME',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['action_case_id', 'performed_at'], false, false, 'idx_actionevents_case_date');
            $this->forge->createTable('observation_action_events');

            $this->db->query("ALTER TABLE `observation_action_events` ADD CONSTRAINT `fk_actionevent_case` FOREIGN KEY (`action_case_id`) REFERENCES `observation_action_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
        }

        // 3. observation_action_work_orders Table
        if (!$this->db->tableExists('observation_action_work_orders')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'action_case_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'work_order_number' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'unique'     => true,
                ],
                'work_order_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'CORRECTIVE_MAINTENANCE',
                ],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['ISSUED', 'SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'],
                    'default'    => 'ISSUED',
                ],
                'issued_at' => [
                    'type' => 'DATETIME',
                ],
                'scheduled_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'started_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'completed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_by' => [
                    'type'       => 'INT',
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
            $this->forge->createTable('observation_action_work_orders');

            $this->db->query("ALTER TABLE `observation_action_work_orders` ADD CONSTRAINT `fk_actwo_actioncase` FOREIGN KEY (`action_case_id`) REFERENCES `observation_action_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
        }
    }

    public function down()
    {
        $this->forge->dropTable('observation_action_work_orders', true);
        $this->forge->dropTable('observation_action_events', true);
        $this->forge->dropTable('observation_action_cases', true);
    }
}
