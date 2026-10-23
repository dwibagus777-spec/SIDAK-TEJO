<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPhaseVoltagesAndTopologyDistanceToFaultCases extends Migration
{
    public function up()
    {
        $db = $this->db ?? \Config\Database::connect();

        if (!$db->tableExists('fault_cases')) {
            return;
        }

        $fieldsToAdd = [];

        if (!$db->fieldExists('voltage_r_kv', 'fault_cases')) {
            $fieldsToAdd['voltage_r_kv'] = [
                'type'       => 'DECIMAL',
                'constraint' => '8,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'current_n',
            ];
        }
        if (!$db->fieldExists('voltage_s_kv', 'fault_cases')) {
            $fieldsToAdd['voltage_s_kv'] = [
                'type'       => 'DECIMAL',
                'constraint' => '8,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'voltage_r_kv',
            ];
        }
        if (!$db->fieldExists('voltage_t_kv', 'fault_cases')) {
            $fieldsToAdd['voltage_t_kv'] = [
                'type'       => 'DECIMAL',
                'constraint' => '8,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'voltage_s_kv',
            ];
        }
        if (!$db->fieldExists('topology_path_length_m', 'fault_cases')) {
            $fieldsToAdd['topology_path_length_m'] = [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'target_distance_meters',
            ];
        }
        if (!$db->fieldExists('relay_measured_distance_m', 'fault_cases')) {
            $fieldsToAdd['relay_measured_distance_m'] = [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'topology_path_length_m',
            ];
        }

        if (!empty($fieldsToAdd)) {
            $this->forge->addColumn('fault_cases', $fieldsToAdd);
        }
    }

    public function down()
    {
        $db = $this->db ?? \Config\Database::connect();

        if (!$db->tableExists('fault_cases')) {
            return;
        }

        foreach (['relay_measured_distance_m', 'topology_path_length_m', 'voltage_t_kv', 'voltage_s_kv', 'voltage_r_kv'] as $col) {
            if ($db->fieldExists($col, 'fault_cases')) {
                $this->forge->dropColumn('fault_cases', $col);
            }
        }
    }
}
