<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHierarchyToAssetsTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('assets')) {
            return;
        }

        $fields = [];

        if (!$this->db->fieldExists('parent_asset_id', 'assets')) {
            $fields['parent_asset_id'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'section_id',
            ];
        }

        if (!$this->db->fieldExists('asset_type_id', 'assets')) {
            $fields['asset_type_id'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'jenis_asset',
            ];
        }

        if (!$this->db->fieldExists('construction_type_id', 'assets')) {
            $fields['construction_type_id'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'asset_type_id',
            ];
        }

        if (!$this->db->fieldExists('sequence_no', 'assets')) {
            $fields['sequence_no'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'tahun_instalasi',
            ];
        }

        if (!empty($fields)) {
            $this->forge->addColumn('assets', $fields);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('assets')) {
            return;
        }

        $dropFields = [];
        foreach (['parent_asset_id', 'asset_type_id', 'construction_type_id', 'sequence_no'] as $col) {
            if ($this->db->fieldExists($col, 'assets')) {
                $dropFields[] = $col;
            }
        }

        if (!empty($dropFields)) {
            $this->forge->dropColumn('assets', $dropFields);
        }
    }
}
