<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CR-06E: Feeder Health Intelligence Policy Versions, Rules & Classifications
 * Gate 6: calculation_policy_version audit trail
 * Gate 7: Parameterized Weights & Thresholds (No hardcoded magic numbers)
 */
class CreateFeederHealthPolicyAndClassificationTables extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Table: feeder_health_policy_versions
        if (!$db->tableExists('feeder_health_policy_versions')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'policy_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'unique'     => true,
                ],
                'policy_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'ACTIVE', // DRAFT, ACTIVE, SUPERSEDED
                ],
                'effective_from' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'effective_to' => [
                    'type' => 'DATETIME',
                    'null' => true,
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
            $this->forge->addKey('status');
            $this->forge->createTable('feeder_health_policy_versions', true);
        }

        // 2. Table: feeder_health_policy_rules
        if (!$db->tableExists('feeder_health_policy_rules')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'policy_version_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'metric_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'weight' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'default'    => 0.2000,
                ],
                'threshold_sempurna_min' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 85.00,
                ],
                'threshold_sakit_min' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 70.00,
                ],
                'threshold_kronis_min' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 50.00,
                ],
                'threshold_kritis_max' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 49.99,
                ],
                'rule_params_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('policy_version_id');
            $this->forge->addForeignKey('policy_version_id', 'feeder_health_policy_versions', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('feeder_health_policy_rules', true);
        }

        // 3. Table: feeder_health_classifications
        if (!$db->tableExists('feeder_health_classifications')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'penyulang_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'calculation_policy_version' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'FHI-v1.0',
                ],
                'period_month' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 7, // e.g. '2026-08'
                ],
                'health_score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 100.00,
                ],
                'health_classification' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'SEMPURNA', // SEMPURNA, SAKIT, KRONIS, KRITIS
                ],
                'interruption_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'interruption_duration_minutes' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                ],
                'critical_findings_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'recurring_findings_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'bom_degradation_score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 0.00,
                ],
                'overload_events_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'explanation_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'calculated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('penyulang_id');
            $this->forge->addKey('period_month');
            $this->forge->addKey('health_classification');
            if ($db->tableExists('penyulang')) {
                $this->forge->addForeignKey('penyulang_id', 'penyulang', 'id', 'CASCADE', 'CASCADE');
            }
            $this->forge->createTable('feeder_health_classifications', true);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('feeder_health_classifications')) {
            $this->forge->dropTable('feeder_health_classifications', true);
        }
        if ($db->tableExists('feeder_health_policy_rules')) {
            $this->forge->dropTable('feeder_health_policy_rules', true);
        }
        if ($db->tableExists('feeder_health_policy_versions')) {
            $this->forge->dropTable('feeder_health_policy_versions', true);
        }
    }
}
