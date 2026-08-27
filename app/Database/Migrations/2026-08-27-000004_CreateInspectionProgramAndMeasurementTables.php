<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CR-06C: Inspection Programs & GTT Measurement Templates Schema
 * Domain normalization for JTM, GARDU_KUBIKEL, TRAFO, JTR
 */
class CreateInspectionProgramAndMeasurementTables extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Table: inspection_programs
        if (!$db->tableExists('inspection_programs')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'program_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'unique'     => true,
                ],
                'nama_pekerjaan' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'asset_domain' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'JTM', // JTM, GARDU_KUBIKEL, TRAFO, JTR
                ],
                'inspection_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'VISUAL_L1', // VISUAL_L1, THERMOVISION, GROUNDING_TEST, LEAKAGE_CURRENT, PARTIAL_DISCHARGE, LOAD_MEASUREMENT
                ],
                'executor_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'INSPEKSI', // INSPEKSI, PDKB, HAR_GARDU, HAR_KONSTRUKSI, HAR_ROW, YANTEK
                ],
                'inspection_category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'PREVENTIVE', // PREVENTIVE, CORRECTIVE, SPECIAL_AUDIT
                ],
                'active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
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
            $this->forge->addKey('asset_domain');
            $this->forge->addKey('inspection_type');
            $this->forge->createTable('inspection_programs', true);
        }

        // 2. Table: inspection_measurement_templates
        if (!$db->tableExists('inspection_measurement_templates')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'inspection_program_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'template_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'unique'     => true,
                ],
                'template_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'asset_domain' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'GTT', // GTT, TRAFO, KUBIKEL
                ],
                'active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
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
            $this->forge->addKey('asset_domain');
            if ($db->tableExists('inspection_programs')) {
                $this->forge->addForeignKey('inspection_program_id', 'inspection_programs', 'id', 'SET NULL', 'SET NULL');
            }
            $this->forge->createTable('inspection_measurement_templates', true);
        }

        // 3. Table: inspection_measurement_points
        if (!$db->tableExists('inspection_measurement_points')) {
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
                'point_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'point_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'phase' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => true, // R, S, T, N, RS, ST, TR, RN, SN, TN
                ],
                'line' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => true, // MAIN, A, B, C, D
                ],
                'measurement_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'CURRENT_AMPERE', // CURRENT_AMPERE, VOLTAGE_VOLT, FUSE_RATING_AMPERE, RESISTANCE_OHM, TEMPERATURE_CELSIUS
                ],
                'unit' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'A',
                ],
                'sequence_order' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                ],
                'mandatory' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
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
            $this->forge->addForeignKey('template_id', 'inspection_measurement_templates', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('inspection_measurement_points', true);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('inspection_measurement_points')) {
            $this->forge->dropTable('inspection_measurement_points', true);
        }
        if ($db->tableExists('inspection_measurement_templates')) {
            $this->forge->dropTable('inspection_measurement_templates', true);
        }
        if ($db->tableExists('inspection_programs')) {
            $this->forge->dropTable('inspection_programs', true);
        }
    }
}
