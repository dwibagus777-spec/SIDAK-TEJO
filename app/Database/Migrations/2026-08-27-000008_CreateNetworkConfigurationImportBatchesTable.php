<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000008:
 * CR-06F Batch Provenance, Scoped SECTION_REF, and Connectivity Evaluation Schema
 */
class CreateNetworkConfigurationImportBatchesTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Table: network_configuration_import_batches
        if (!$db->tableExists('network_configuration_import_batches')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'batch_uuid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'unique'     => true,
                ],
                'source_filename' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'source_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'EXCEL', // EXCEL, JSON, MANUAL_ENTRY
                ],
                'import_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'VALIDATING', // VALIDATING, REJECTED, COMMITTING, COMMITTED, ROLLED_BACK, FAILED
                ],
                'total_sections' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'committed_sections' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'rejected_sections' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'validation_summary' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'imported_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'started_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'completed_at' => [
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
            $this->forge->addKey('import_status');
            $this->forge->createTable('network_configuration_import_batches', true);
        }

        // 2. Enhance network_section_configurations
        if ($db->tableExists('network_section_configurations')) {
            $configFieldsToAdd = [];

            if (!$db->fieldExists('import_batch_id', 'network_section_configurations')) {
                $configFieldsToAdd['import_batch_id'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'section_id',
                ];
            }

            if (!$db->fieldExists('section_ref', 'network_section_configurations')) {
                $configFieldsToAdd['section_ref'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'import_batch_id',
                ];
            }

            if (!$db->fieldExists('topology_connectivity_status', 'network_section_configurations')) {
                $configFieldsToAdd['topology_connectivity_status'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'UNVERIFIED', // VERIFIED, UNVERIFIED, DISCONTINUOUS
                    'after'      => 'verification_status',
                ];
            }

            if (!empty($configFieldsToAdd)) {
                $this->forge->addColumn('network_section_configurations', $configFieldsToAdd);
            }
        }

        // 3. Enhance network_section_accessories (observed condition vs topology truth)
        if ($db->tableExists('network_section_accessories')) {
            $accFieldsToAdd = [];

            if (!$db->fieldExists('initial_observed_condition', 'network_section_accessories')) {
                $accFieldsToAdd['initial_observed_condition'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'GOOD', // GOOD, DEFECTIVE, MISSING
                    'after'      => 'condition_status',
                ];
            }

            if (!empty($accFieldsToAdd)) {
                $this->forge->addColumn('network_section_accessories', $accFieldsToAdd);
            }
        }
    }

    public function down()
    {
        // No destructive drop
    }
}
