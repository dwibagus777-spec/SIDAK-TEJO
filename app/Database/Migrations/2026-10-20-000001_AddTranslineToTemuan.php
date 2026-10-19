<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTranslineToTemuan extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('temuan')) {
            if (!$this->db->fieldExists('transline_id', 'temuan')) {
                $this->forge->addColumn('temuan', ['transline_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'section_id'
                ]]);
            }
            if (!$this->db->fieldExists('transline_snapshot', 'temuan')) {
                $this->forge->addColumn('temuan', ['transline_snapshot' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'alamat'
                ]]);
            }
        }
        if ($this->db->tableExists('ai_decision_logs')) {
            foreach ([
                'k2_k3_analysis' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'explanation'
                ],
                'analysis_version' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => true,
                    'after' => 'k2_k3_analysis'
                ],
                'input_summary' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'analysis_version'
                ],
                'rule_sources' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'input_summary'
                ],
            ] as $field => $definition) {
                if (!$this->db->fieldExists($field, 'ai_decision_logs')) {
                    $this->forge->addColumn('ai_decision_logs', [$field => $definition]);
                }
            }
        }

        if (!$this->db->tableExists('captive_non_penugasan')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'nomor' => ['type' => 'VARCHAR', 'constraint' => 40],
                'temuan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'transline_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'jenis' => ['type' => 'VARCHAR', 'constraint' => 24],
                'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'POTENSI'],
                'ulp_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'section_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'source_asset_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'target_asset_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'source_label' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
                'target_label' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
                'source_section_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'target_section_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'source_latitude' => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'source_longitude' => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'target_latitude' => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'target_longitude' => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'latitude' => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'longitude' => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'distance_meters' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'conductor_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'conductor_size' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'target_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'target_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'nomor_gangguan' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'detail_temuan' => ['type' => 'TEXT'],
                'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'created_by_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'completed_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'completed_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('nomor', false, true);
            $this->forge->addKey('transline_id');
            $this->forge->addKey(['ulp_id', 'status']);
            $this->forge->addKey(['penyulang_id', 'jenis', 'status']);
            $this->forge->createTable('captive_non_penugasan', true);
        }
    }

    public function down()
    {
    }
}
