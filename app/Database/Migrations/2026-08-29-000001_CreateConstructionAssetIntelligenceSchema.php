<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration CR-06G-01: Create Construction Asset Intelligence Schema
 * - Creates asset_intelligence_snapshots table for explainable degradation audit trail
 * - Adds additive intelligence status columns to assets table
 * - Enforces Hardening Invariant: "No Data != Healthy" (RESOLUTION_STATUS & nullable health scores)
 */
class CreateConstructionAssetIntelligenceSchema extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Create asset_intelligence_snapshots
        if (!$db->tableExists('asset_intelligence_snapshots')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => false,
                ],
                'penyulang_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'section_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'construction_type_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'resolution_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'UNRESOLVED', // RESOLVED, PARTIAL, UNRESOLVED, INVALID
                ],
                'bom_completeness_ratio' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'default'    => '0.0000',
                ],
                'active_findings_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'recurring_findings_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'asset_degradation_index' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'null'       => true,
                ],
                'asset_health_score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'health_category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'UNRESOLVED', // GOOD, WARNING, POOR, CRITICAL, UNRESOLVED
                ],
                'degradation_breakdown_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'snapshot_version' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'CR-06G-v1.0',
                ],
                'calculated_at' => [
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
            $this->forge->addKey('asset_id');
            $this->forge->addKey(['penyulang_id', 'section_id']);
            $this->forge->addKey('resolution_status');
            $this->forge->addKey('health_category');
            $this->forge->createTable('asset_intelligence_snapshots', true);
        }

        // 2. Add additive columns to assets table if not exist
        if ($db->tableExists('assets')) {
            $fieldsToAdd = [];

            if (!$db->fieldExists('degradation_index', 'assets')) {
                $fieldsToAdd['degradation_index'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'null'       => true,
                    'after'      => 'health_category',
                ];
            }

            if (!$db->fieldExists('intelligence_resolution_status', 'assets')) {
                $fieldsToAdd['intelligence_resolution_status'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'UNRESOLVED',
                    'after'      => 'degradation_index',
                ];
            }

            if (!empty($fieldsToAdd)) {
                $this->forge->addColumn('assets', $fieldsToAdd);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('asset_intelligence_snapshots')) {
            $this->forge->dropTable('asset_intelligence_snapshots', true);
        }

        if ($db->tableExists('assets')) {
            if ($db->fieldExists('degradation_index', 'assets')) {
                $this->forge->dropColumn('assets', 'degradation_index');
            }
            if ($db->fieldExists('intelligence_resolution_status', 'assets')) {
                $this->forge->dropColumn('assets', 'intelligence_resolution_status');
            }
        }
    }
}
