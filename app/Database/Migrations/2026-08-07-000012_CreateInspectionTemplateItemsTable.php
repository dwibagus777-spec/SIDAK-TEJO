<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInspectionTemplateItemsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('inspection_template_items')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'template_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'item_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
            ],
            'item_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'default'    => 'CHECKLIST', // CHECKLIST, NUMERIC_MEASUREMENT, TEXT_INPUT
            ],
            'unit' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true, // °C, Ampere, Volt, mm
            ],
            'min_value' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'max_value' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'is_photo_required' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'photo_label' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
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
        $this->forge->addKey('template_id');
        $this->forge->createTable('inspection_template_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('inspection_template_items', true);
    }
}
