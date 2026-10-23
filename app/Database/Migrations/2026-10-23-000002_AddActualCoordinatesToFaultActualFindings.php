<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddActualCoordinatesToFaultActualFindings extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('fault_actual_findings')) {
            return;
        }

        $fields = [];

        if (!$this->db->fieldExists('actual_latitude', 'fault_actual_findings')) {
            $fields['actual_latitude'] = [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'null'       => true,
                'default'    => null,
                'after'      => 'actual_observed_distance_m',
            ];
        }

        if (!$this->db->fieldExists('actual_longitude', 'fault_actual_findings')) {
            $fields['actual_longitude'] = [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'null'       => true,
                'default'    => null,
                'after'      => 'actual_latitude',
            ];
        }

        if (!empty($fields)) {
            $this->forge->addColumn('fault_actual_findings', $fields);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('fault_actual_findings')) {
            return;
        }

        $dropColumns = [];
        if ($this->db->fieldExists('actual_latitude', 'fault_actual_findings')) {
            $dropColumns[] = 'actual_latitude';
        }
        if ($this->db->fieldExists('actual_longitude', 'fault_actual_findings')) {
            $dropColumns[] = 'actual_longitude';
        }

        if (!empty($dropColumns)) {
            $this->forge->dropColumn('fault_actual_findings', $dropColumns);
        }
    }
}
