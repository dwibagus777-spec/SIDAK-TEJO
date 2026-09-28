<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase B.8.1: Remediation Core — Closed-Loop Work Package Lifecycle Schema
 *
 * Implements the data architecture for:
 * 1. remediation_work_packages      — Header paket perbaikan dengan client_operation_uuid idempotency (B81-G03).
 * 2. remediation_package_findings   — Relasi M-to-M finding terkonfirmasi dengan Work Package (B81-G01, B81-G04).
 * 3. remediation_package_history    — Audit history append-only permanen dengan ON DELETE RESTRICT (B81-G02).
 *
 * Hard Invariants:
 * - B81-G01: Finding Assignment Atomicity (Resource-level pessimistic locking).
 * - B81-G02: Audit History Non-Cascade (ON DELETE RESTRICT, no deletion).
 * - B81-G03: Work Package Creation Idempotency (UNIQUE client_operation_uuid).
 * - B81-G04: Pre-Commit Finding Revalidation (finding_status_at_attach vs live status).
 * - B81-G05: Verification Evidence Content Integrity (SHA-256 content verification).
 * - B.7 Immutability: Δtopology = 0 on gis_translines, assets, network_topology_versions.
 * - Idempotency: Safe to execute repeatedly without schema corruption or duplicate index errors.
 */
class CreateRemediationCoreSchema extends Migration
{
    public function up()
    {
        $db = $this->db ?? \Config\Database::connect();
        $forge = $this->forge ?? \Config\Database::forge();

        // =====================================================================
        // 1. Table: remediation_work_packages
        // =====================================================================
        if (!$db->tableExists('remediation_work_packages')) {
            $forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'client_operation_uuid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'package_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'title' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'feeder_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'priority' => [
                    'type'       => 'ENUM',
                    'constraint' => ['LOW', 'MEDIUM', 'HIGH', 'EMERGENCY'],
                    'default'    => 'MEDIUM',
                ],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => [
                        'DRAFT',
                        'ENGINEER_SUBMITTED',
                        'PENDING_APPROVAL',
                        'APPROVED',
                        'REJECTED',
                        'RELEASED',
                        'IN_PROGRESS',
                        'COMPLETED',
                        'VERIFIED'
                    ],
                    'default'    => 'DRAFT',
                ],
                'assigned_team_leader' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                    'null'       => true,
                ],
                'assigned_team_members' => [
                    'type' => 'JSON',
                    'null' => true,
                ],
                'created_by' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                ],
                'submitted_by' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'submitted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'approved_by' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'approved_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'rejected_by' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'rejected_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'rejection_notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'released_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'started_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'completed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'verified_by' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'verified_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'verification_notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'verification_evidence_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'verification_evidence_sha256' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'test_mode' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                ],
            ]);

            $forge->addPrimaryKey('id');
            $forge->addUniqueKey('client_operation_uuid', 'uk_wp_op_uuid');
            $forge->addUniqueKey('package_code', 'uk_wp_code');
            $forge->addKey('status', false, false, 'idx_wp_status');
            $forge->addKey('feeder_code', false, false, 'idx_wp_feeder');
            $forge->createTable('remediation_work_packages', true);
        }

        // =====================================================================
        // 2. Table: remediation_package_findings
        // =====================================================================
        if (!$db->tableExists('remediation_package_findings')) {
            $forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'work_package_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                ],
                'finding_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'case_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'transline_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'is_primary' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'finding_status_at_attach' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'CONFIRMED',
                ],
                'item_remediation_status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['PENDING', 'IN_REPAIR', 'RESOLVED', 'CANNOT_RESOLVE'],
                    'default'    => 'PENDING',
                ],
                'item_notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                ],
            ]);

            $forge->addPrimaryKey('id');
            $forge->addUniqueKey(['work_package_id', 'finding_id'], 'uk_wp_finding');
            $forge->addKey('finding_id', false, false, 'idx_rpf_finding');
            $forge->addKey('asset_id', false, false, 'idx_rpf_asset');

            if ($db->tableExists('remediation_work_packages')) {
                $forge->addForeignKey('work_package_id', 'remediation_work_packages', 'id', 'RESTRICT', 'RESTRICT');
            }
            if ($db->tableExists('field_findings')) {
                $forge->addForeignKey('finding_id', 'field_findings', 'id', 'RESTRICT', 'RESTRICT');
            }
            if ($db->tableExists('fault_cases')) {
                $forge->addForeignKey('case_id', 'fault_cases', 'id', 'RESTRICT', 'RESTRICT');
            }

            $forge->createTable('remediation_package_findings', true);
        }

        // =====================================================================
        // 3. Table: remediation_package_history (B81-G02: Permanent Audit Non-Cascade)
        // =====================================================================
        if (!$db->tableExists('remediation_package_history')) {
            $forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'work_package_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                ],
                'previous_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => true,
                ],
                'new_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                ],
                'action_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'actor_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                ],
                'actor_role' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                ],
                'transition_notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                ],
            ]);

            $forge->addPrimaryKey('id');
            $forge->addKey('work_package_id', false, false, 'idx_rph_wp');

            // B81-G02: RESTRICT ON DELETE to prevent cascade purge
            if ($db->tableExists('remediation_work_packages')) {
                $forge->addForeignKey('work_package_id', 'remediation_work_packages', 'id', 'RESTRICT', 'RESTRICT');
            }

            $forge->createTable('remediation_package_history', true);
        }
    }

    public function down()
    {
        $forge = $this->forge ?? \Config\Database::forge();
        $forge->dropTable('remediation_package_history', true);
        $forge->dropTable('remediation_package_findings', true);
        $forge->dropTable('remediation_work_packages', true);
    }
}
