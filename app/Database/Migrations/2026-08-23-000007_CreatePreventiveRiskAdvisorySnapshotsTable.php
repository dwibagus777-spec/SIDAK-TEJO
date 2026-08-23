<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePreventiveRiskAdvisorySnapshotsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'snapshot_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'comment'    => 'Unique bundle code (e.g. PREV-SNP-STJ-20260823120000-01)',
            ],
            'asset_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK to assets.id',
            ],
            'temuan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK to temuan.id',
            ],
            'penyulang_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'FK to penyulang.id',
            ],
            'section_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK to sections.id',
            ],
            'feeder_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'section_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'preventive_risk_tier' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'CRITICAL_PREVENTIVE_ATTENTION',
                    'HIGH_RISK_RECURRENCE',
                    'MODERATE_DEGRADATION',
                    'LOW_STABLE'
                ],
                'default'    => 'MODERATE_DEGRADATION',
            ],
            'correlation_confidence_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => 0.00,
                'comment'    => 'Aggregated correlation score (0.00 to 1.00)',
            ],
            'scoring_model_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'PREVENTIVE_SCORING_v1.0',
                'comment'    => 'Immutable scoring model version pin',
            ],
            'scoring_weight_severity' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => 0.40,
            ],
            'scoring_weight_historical_recurrence' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => 0.35,
            ],
            'scoring_weight_asset_health' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => 0.25,
            ],
            'active_findings_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
            ],
            'historical_case_matches_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'historical_case_reference_set' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'JSON / string summary of matched historical case dates & causes',
            ],
            'dominant_historical_cause' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'median_historical_outage_min' => [
                'type'       => 'DECIMAL',
                'constraint' => '8,2',
                'default'    => 0.00,
            ],
            'recommended_review_focus' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
                'comment'    => 'Advisory review direction (NOT operational command)',
            ],
            'historical_knowledge_source_class' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'EXTERNAL_HISTORICAL_INTERRUPTION_KNOWLEDGE',
            ],
            'correlation_engine_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'PREVENTIVE_CORRELATION_v1.0',
            ],
            'evaluation_timestamp' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'advisory_payload_json' => [
                'type' => 'LONGTEXT',
                'null' => false,
                'comment' => 'Full structured lineage payload',
            ],
            'governance_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'ADVISORY_PROPOSED',
                    'SUPERVISOR_REVIEWED',
                    'MITIGATION_PLANNED',
                    'ARCHIVED'
                ],
                'default'    => 'ADVISORY_PROPOSED',
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

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('snapshot_code', false, false, 'idx_prev_snp_code');
        $this->forge->addKey(['penyulang_id', 'preventive_risk_tier'], false, false, 'idx_prev_fdr_tier');
        $this->forge->addKey('asset_id', false, false, 'idx_prev_asset');
        $this->forge->addKey('temuan_id', false, false, 'idx_prev_temuan');

        $this->forge->createTable('preventive_risk_advisory_snapshots', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('preventive_risk_advisory_snapshots', true);
    }
}
