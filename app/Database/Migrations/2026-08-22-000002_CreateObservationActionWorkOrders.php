<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateObservationActionWorkOrders extends Migration
{
    public function up()
    {
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

            try {
                $this->db->query("ALTER TABLE `observation_action_work_orders` ADD CONSTRAINT `fk_actwo_actioncase` FOREIGN KEY (`action_case_id`) REFERENCES `observation_action_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
            } catch (\Throwable $e) {
                log_message('warning', 'Foreign key fk_actwo_actioncase bypassed gracefully: ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('observation_action_work_orders', true);
    }
}
