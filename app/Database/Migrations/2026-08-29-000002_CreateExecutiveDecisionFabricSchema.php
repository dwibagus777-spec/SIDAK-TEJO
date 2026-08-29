<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration for Phase CC-04: Executive Decision Fabric (Contract v1.2)
 * Creates executive_decision_logs and extends feeder_health_classifications with Invariants E2-A, E3-A, E6-A, E9-A.
 */
class CreateExecutiveDecisionFabricSchema extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Additive columns to feeder_health_classifications
        if ($db->tableExists('feeder_health_classifications')) {
            $newCols = [];
            if (!$db->fieldExists('fhi_status', 'feeder_health_classifications')) {
                $newCols['fhi_status'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'UNRESOLVED', // RESOLVED, PARTIAL, UNRESOLVED
                ];
            }
            if (!$db->fieldExists('data_completeness_ratio', 'feeder_health_classifications')) {
                $newCols['data_completeness_ratio'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'default'    => 0.0000,
                ];
            }
            if (!$db->fieldExists('physical_coverage_ratio', 'feeder_health_classifications')) {
                $newCols['physical_coverage_ratio'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'default'    => 0.0000,
                ];
            }
            if (!$db->fieldExists('asset_health_score', 'feeder_health_classifications')) {
                $newCols['asset_health_score'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ];
            }
            if (!$db->fieldExists('finding_severity_score', 'feeder_health_classifications')) {
                $newCols['finding_severity_score'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ];
            }
            if (!$db->fieldExists('reliability_score', 'feeder_health_classifications')) {
                $newCols['reliability_score'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ];
            }
            if (!$db->fieldExists('recurrence_score', 'feeder_health_classifications')) {
                $newCols['recurrence_score'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ];
            }
            if (!$db->fieldExists('primary_driver', 'feeder_health_classifications')) {
                $newCols['primary_driver'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ];
            }
            if (!$db->fieldExists('primary_driver_score', 'feeder_health_classifications')) {
                $newCols['primary_driver_score'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ];
            }
            if (!$db->fieldExists('assigned_unit', 'feeder_health_classifications')) {
                $newCols['assigned_unit'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ];
            }
            if (!$db->fieldExists('priority_level', 'feeder_health_classifications')) {
                $newCols['priority_level'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => true,
                ];
            }
            if (!$db->fieldExists('fingerprint_json', 'feeder_health_classifications')) {
                $newCols['fingerprint_json'] = [
                    'type' => 'TEXT',
                    'null' => true,
                ];
            }
            if (!$db->fieldExists('advisory_narrative', 'feeder_health_classifications')) {
                $newCols['advisory_narrative'] = [
                    'type' => 'TEXT',
                    'null' => true,
                ];
            }
            if (!$db->fieldExists('created_at', 'feeder_health_classifications')) {
                $newCols['created_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                ];
            }
            if (!$db->fieldExists('updated_at', 'feeder_health_classifications')) {
                $newCols['updated_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                ];
            }

            if (!empty($newCols)) {
                $this->forge->addColumn('feeder_health_classifications', $newCols);
            }
        }

        // 2. Table: executive_decision_logs (Gate E9-A Closed-Loop Governance)
        if (!$db->tableExists('executive_decision_logs')) {
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
                'feeder_health_classification_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'recommendation_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'recommended_action' => [
                    'type' => 'TEXT',
                ],
                'assigned_unit' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'priority_level' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'P2 - HIGH',
                ],
                'baseline_fhi' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                ],
                'approval_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'PENDING', // PENDING, APPROVED, REJECTED, DISPATCHED, COMPLETED, VERIFIED
                ],
                'approved_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'approved_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'work_order_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'outcome_verified_fhi' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'delta_fhi' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'outcome_notes' => [
                    'type' => 'TEXT',
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
            $this->forge->addKey('penyulang_id');
            $this->forge->addKey('approval_status');
            $this->forge->addKey('priority_level');
            $this->forge->createTable('executive_decision_logs', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('executive_decision_logs', true);
    }
}
