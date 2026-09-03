<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * TL-01 Phase 2B: AI-Assisted Transline Completion
 * Creates additive proposal storage table `gis_transline_proposals`.
 *
 * Guaranteed Invariants:
 * - Strictly additive: 0 modification to authoritative `gis_translines`.
 * - Proposal != Authoritative Transline.
 * - Stores deterministic classifications: AUTO_MATCH, NEEDS_REVIEW, INVALID, MISSING.
 * - Stores explainable evidence_json and confidence_score.
 * - Preserves natural_key (penyulang_id:min-max) for deduplication and stale detection.
 * - Unconfirmed proposals NEVER alter network topology.
 */
class CreateGisTranslineProposalsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('gis_transline_proposals')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'penyulang_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'section_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'source_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'target_asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'natural_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                ],
                'proposed_conductor_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'AAAC',
                ],
                'proposed_conductor_size' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => '150 mm²',
                ],
                'proposed_distance' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                ],
                'proposed_geometry' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'classification' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    // Technical evaluation by engine: AUTO_MATCH, NEEDS_REVIEW, INVALID, MISSING
                ],
                'confidence_score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 0.00,
                ],
                'evidence_json' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'proposal_source' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'DETERMINISTIC_ENGINE',
                ],
                'engine_version' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'v1.0',
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'PENDING_REVIEW',
                ],
                'reviewed_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'reviewed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'review_note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'confirmed_transline_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('penyulang_id');
            $this->forge->addKey('section_id');
            $this->forge->addKey('source_asset_id');
            $this->forge->addKey('target_asset_id');
            $this->forge->addKey('natural_key');
            $this->forge->addKey('classification');
            $this->forge->addKey('status');
            $this->forge->addKey('confirmed_transline_id');

            if ($db->tableExists('penyulang')) {
                $this->forge->addForeignKey('penyulang_id', 'penyulang', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($db->tableExists('sections')) {
                $this->forge->addForeignKey('section_id', 'sections', 'id', 'SET NULL', 'CASCADE');
            }
            if ($db->tableExists('assets')) {
                $this->forge->addForeignKey('source_asset_id', 'assets', 'id', 'RESTRICT', 'CASCADE');
                $this->forge->addForeignKey('target_asset_id', 'assets', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($db->tableExists('gis_translines')) {
                $this->forge->addForeignKey('confirmed_transline_id', 'gis_translines', 'id', 'SET NULL', 'CASCADE');
            }

            $this->forge->createTable('gis_transline_proposals', true);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('gis_transline_proposals')) {
            $this->forge->dropTable('gis_transline_proposals', true);
        }
    }
}
