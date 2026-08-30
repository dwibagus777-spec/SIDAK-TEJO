<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration for AR-01 Phase 5G: Field Section Resolution & Topology Traceability Schema
 */
class CreateFieldSectionResolutionSchema extends Migration
{
    public function up()
    {
        // 1. Add Section Resolution Columns to 'assets' table
        if ($this->db->tableExists('assets')) {
            $fieldsToAdd = [];
            if (!$this->db->fieldExists('section_resolution_method', 'assets')) {
                $fieldsToAdd['section_resolution_method'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'UNRESOLVED',
                    'null'       => true,
                ];
            }
            if (!$this->db->fieldExists('section_verified_by', 'assets')) {
                $fieldsToAdd['section_verified_by'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ];
            }
            if (!$this->db->fieldExists('section_verified_at', 'assets')) {
                $fieldsToAdd['section_verified_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                ];
            }
            if (!$this->db->fieldExists('field_sequence', 'assets')) {
                $fieldsToAdd['field_sequence'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ];
            }

            if (!empty($fieldsToAdd)) {
                $this->forge->addColumn('assets', $fieldsToAdd);
            }
        }

        // 2. Create 'asset_section_history' table for immutable audit trail
        if (!$this->db->tableExists('asset_section_history')) {
            $this->forge->addField([
                'id'                       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'asset_id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'penyulang_id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'old_section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'new_section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'old_sequence'             => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'new_sequence'             => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'resolution_method'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'FIELD_VERIFIED'],
                'verified_by'              => ['type' => 'VARCHAR', 'constraint' => 100],
                'reason'                   => ['type' => 'TEXT'],
                'latitude_at_verification' => ['type' => 'DOUBLE', 'null' => true],
                'longitude_at_verification'=> ['type' => 'DOUBLE', 'null' => true],
                'created_at'               => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['asset_id', 'created_at']);
            $this->forge->addKey(['penyulang_id', 'new_section_id']);
            $this->forge->createTable('asset_section_history', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('asset_section_history', true);
    }
}
