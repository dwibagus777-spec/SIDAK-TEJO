<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUsedQuantityToTemuanMaterials extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('temuan_materials')) {
            $fields = [];
            if (!$this->db->fieldExists('used_quantity', 'temuan_materials')) {
                $fields['used_quantity'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                    'after'      => 'quantity',
                ];
            }
            if (!$this->db->fieldExists('work_status', 'temuan_materials')) {
                $fields['work_status'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'PENDING',
                    'after'      => 'used_quantity',
                ];
            }

            if (!empty($fields)) {
                $this->forge->addColumn('temuan_materials', $fields);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('temuan_materials')) {
            if ($this->db->fieldExists('work_status', 'temuan_materials')) {
                $this->forge->dropColumn('temuan_materials', 'work_status');
            }
            if ($this->db->fieldExists('used_quantity', 'temuan_materials')) {
                $this->forge->dropColumn('temuan_materials', 'used_quantity');
            }
        }
    }
}
