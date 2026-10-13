<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: CreateAssetHealthIntelligenceCoreSchema (B8.3 Asset Health Intelligence)
 *
 * Implements 4 core tables for B8.3:
 * 1. asset_health_indices
 * 2. asset_health_factors
 * 3. asset_health_history
 * 4. asset_health_recommendations
 *
 * HARDENED GOVERNANCE & HARD GUARDS:
 * - B83-G01: 100% active asset coverage ($5,236 / 5,236$).
 * - B83-G02: 100% active transline network context ($245 / 245$).
 * - B83-G03: Zero GIS / Asset master mutation ($\Delta_{\text{topology}} = 0$).
 * - B83-G08: AI Autonomy Ceiling = L4 RECOMMENDED.
 * - B83-G12: Permanent Audit Non-Cascade (ON DELETE RESTRICT).
 */
class CreateAssetHealthIntelligenceCoreSchema extends Migration
{
    public function up(): void
    {
        // 1. asset_health_indices
        if (!$this->db->tableExists('asset_health_indices')) {
            $this->forge->addField([
                'id'                     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'asset_id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
                'ahi_score'              => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false],
                'health_category'        => [
                    'type'       => 'ENUM',
                    'constraint' => ['EXCELLENT', 'GOOD', 'FAIR', 'POOR', 'CRITICAL'],
                    'null'       => false,
                ],
                'failure_risk_level'     => [
                    'type'       => 'ENUM',
                    'constraint' => ['VERY_LOW', 'LOW', 'MEDIUM', 'HIGH', 'VERY_HIGH'],
                    'null'       => false,
                ],
                'weight_profile_id'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'WP-1.0', 'null' => false],
                'weight_profile_version' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => '1.0.0', 'null' => false],
                'algorithm_version'      => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'AHI-1.0.0', 'null' => false],
                'topology_snapshot_id'   => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'TOPOLOGY-20260928-245-81c43a7f', 'null' => false],
                'input_fingerprint'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'factor_fingerprint'     => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'provenance_level'       => [
                    'type'       => 'ENUM',
                    'constraint' => ['OBSERVED', 'DERIVED', 'ANALYTICAL', 'RECOMMENDED', 'APPROVED', 'EXECUTED'],
                    'default'    => 'ANALYTICAL',
                ],
                'test_mode'              => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'calculated_at'          => ['type' => 'DATETIME', 'null' => false],
                'updated_at'             => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('asset_id');
            $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'RESTRICT', 'fk_ahi_asset');
            $this->forge->createTable('asset_health_indices', true);
        }

        // 2. asset_health_factors
        if (!$this->db->tableExists('asset_health_factors')) {
            $this->forge->addField([
                'id'                    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'asset_health_index_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'factor_code'           => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'factor_name'           => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false],
                'weight_pct'            => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false],
                'raw_score'             => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false],
                'penalty_score'         => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false],
                'weighted_contribution' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false],
                'evidence_status'       => [
                    'type'       => 'ENUM',
                    'constraint' => ['AVAILABLE', 'UNAVAILABLE', 'INSUFFICIENT_EVIDENCE'],
                    'default'    => 'AVAILABLE',
                ],
                'evidence_source'       => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'evidence_id'           => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'provenance_level'      => [
                    'type'       => 'ENUM',
                    'constraint' => ['OBSERVED', 'DERIVED', 'ANALYTICAL', 'RECOMMENDED', 'APPROVED', 'EXECUTED'],
                    'default'    => 'OBSERVED',
                ],
                'created_at'            => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addForeignKey('asset_health_index_id', 'asset_health_indices', 'id', 'CASCADE', 'RESTRICT', 'fk_ahf_index');
            $this->forge->createTable('asset_health_factors', true);
        }

        // 3. asset_health_history
        // Drop legacy table if it exists without B8.3 columns
        if ($this->db->tableExists('asset_health_history') && !$this->db->fieldExists('asset_health_index_id', 'asset_health_history')) {
            $this->forge->dropTable('asset_health_history', true);
        }

        if (!$this->db->tableExists('asset_health_history')) {
            $this->forge->addField([
                'id'                    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'asset_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
                'asset_health_index_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'previous_score'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
                'new_score'             => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false],
                'previous_category'     => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
                'new_category'          => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false],
                'trigger_event'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'algorithm_version'     => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => false],
                'calculated_at'         => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'RESTRICT', 'fk_ahh_asset');
            $this->forge->addForeignKey('asset_health_index_id', 'asset_health_indices', 'id', 'CASCADE', 'RESTRICT', 'fk_ahh_index');
            $this->forge->createTable('asset_health_history', true);
        }

        // 4. asset_health_recommendations
        if (!$this->db->tableExists('asset_health_recommendations')) {
            $this->forge->addField([
                'id'                    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'asset_health_index_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'asset_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
                'recommended_action'    => [
                    'type'       => 'ENUM',
                    'constraint' => ['MONITOR', 'SCHEDULED_INSPECTION', 'OVERHAUL', 'REPLACE_COMPONENT', 'URGENT_REMEDIATION'],
                    'default'    => 'MONITOR',
                ],
                'urgency_level'         => [
                    'type'       => 'ENUM',
                    'constraint' => ['LOW', 'MEDIUM', 'HIGH', 'EMERGENCY'],
                    'default'    => 'LOW',
                ],
                'recommendation_notes'  => ['type' => 'TEXT', 'null' => true],
                'estimated_bom_json'    => ['type' => 'TEXT', 'null' => true],
                'provenance_level'      => [
                    'type'       => 'ENUM',
                    'constraint' => ['OBSERVED', 'DERIVED', 'ANALYTICAL', 'RECOMMENDED', 'APPROVED', 'EXECUTED'],
                    'default'    => 'RECOMMENDED',
                ],
                'work_package_id'       => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'approved_by'           => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'test_mode'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at'            => ['type' => 'DATETIME', 'null' => false],
                'updated_at'            => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addForeignKey('asset_health_index_id', 'asset_health_indices', 'id', 'CASCADE', 'RESTRICT', 'fk_ahr_index');
            $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'RESTRICT', 'fk_ahr_asset');
            $this->forge->addForeignKey('work_package_id', 'remediation_work_packages', 'id', 'CASCADE', 'RESTRICT', 'fk_ahr_wp');
            $this->forge->createTable('asset_health_recommendations', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('asset_health_recommendations', true);
        $this->forge->dropTable('asset_health_history', true);
        $this->forge->dropTable('asset_health_factors', true);
        $this->forge->dropTable('asset_health_indices', true);
    }
}
