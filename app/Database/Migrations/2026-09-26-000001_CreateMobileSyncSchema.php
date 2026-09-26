<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase B.7: Mobile Field App & Offline Inspection Sync
 * Migration B.7.1: Local Testbed Schema Migration
 *
 * Implements the data architecture for:
 * 1. mobile_devices         — Registered device governance & hardware fingerprinting (Guards B7-G11, B7-G19).
 * 2. mobile_sync_batches    — Transport-level batch envelopes (Guards B7-G03).
 * 3. mobile_sync_journal    — Immutable audit log with strictly monotonic server sequence (Guards B7-G02, B7-G05, B7-G10, B7-G12, B7-G15, B7-G16, B7-G19).
 * 4. mobile_evidence_chunks — Chunked binary staging & idempotency deduplication (Guards B7-G08, B7-G18).
 *
 * Hard Invariants:
 * - B7-G01: Zero authority bypass (Sync is purely transport/journaling).
 * - B7-G14: Zero modification to gis_translines, assets, or network_topology_versions (Δtopology = 0).
 * - B7-G15: Per-operation atomicity support.
 * - B7-G16: Monotonic server cursor via journal_seq (BIGINT AUTO_INCREMENT PRIMARY KEY).
 * - B7-G19: Device revocation non-cascade (No cascading deletes to sync journal).
 * - Idempotency: Safe to execute repeatedly without schema corruption or duplicate index errors.
 */
class CreateMobileSyncSchema extends Migration
{
    public function up()
    {
        $db = $this->db ?? \Config\Database::connect();
        $forge = $this->forge ?? \Config\Database::forge();

        // =====================================================================
        // 1. Table: mobile_devices (Guards B7-G11, B7-G19)
        // =====================================================================
        if (!$db->tableExists('mobile_devices')) {
            $forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'device_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'device_identity_fingerprint' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'device_model' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'app_version' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'ACTIVE',
                    // ACTIVE, SUSPENDED, REVOKED
                ],
                'registered_at' => [
                    'type' => 'DATETIME',
                ],
                'revoked_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'last_seen_at' => [
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

            $forge->addKey('id', true);
            $forge->addUniqueKey('device_id', 'uk_device_id');
            $forge->addKey('user_id');
            $forge->addKey('status');

            if ($db->tableExists('users')) {
                // RESTRICT delete on user: cannot delete user with registered devices
                $forge->addForeignKey('user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
            }

            $forge->createTable('mobile_devices', true);
        }

        // =====================================================================
        // 2. Table: mobile_sync_batches (Guard B7-G03)
        // =====================================================================
        if (!$db->tableExists('mobile_sync_batches')) {
            $forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'sync_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'device_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'client_sent_at' => [
                    'type' => 'DATETIME',
                ],
                'server_received_at' => [
                    'type' => 'DATETIME',
                ],
                'operation_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'payload_sha256' => [
                    'type'       => 'CHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'RECEIVED',
                    // RECEIVED, PROCESSING, COMPLETED, PARTIAL
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

            $forge->addKey('id', true);
            $forge->addUniqueKey('sync_id', 'uk_batch_sync_id');
            $forge->addKey('device_id');
            $forge->addKey('user_id');
            $forge->addKey('status');

            $forge->createTable('mobile_sync_batches', true);
        }

        // =====================================================================
        // 3. Table: mobile_sync_journal (Guards B7-G02, G05, G10, G12, G15, G16, G19, G20)
        // Note: journal_seq is BIGINT AUTO_INCREMENT PRIMARY KEY for strictly monotonic cursor.
        // NO CASCADE FK from devices/batches (B7-G19: Forensic history is permanent).
        // =====================================================================
        if (!$db->tableExists('mobile_sync_journal')) {
            $forge->addField([
                'journal_seq' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'client_submission_uuid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'sync_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'device_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'operation_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    // ARRIVAL, INVESTIGATION, FINDING, AMEND, EVIDENCE, FEEDBACK
                ],
                'entity_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    // fault_case, field_investigation, field_finding, field_evidence, fault_feedback
                ],
                'entity_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'payload_sha256' => [
                    'type'       => 'CHAR',
                    'constraint' => 64,
                ],
                'payload_json' => [
                    'type' => 'LONGTEXT',
                ],
                'client_created_at' => [
                    'type' => 'DATETIME',
                ],
                'client_timezone_offset' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'default'    => '+07:00',
                ],
                'server_received_at' => [
                    'type' => 'DATETIME',
                ],
                'server_processed_at' => [
                    'type' => 'DATETIME',
                ],
                'attempt_no' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 1,
                ],
                'sync_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'ACCEPTED',
                    // ACCEPTED, DUPLICATE, REJECTED
                ],
                'response_http_code' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 200,
                ],
                'domain_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $forge->addKey('journal_seq', true); // Primary Key
            $forge->addUniqueKey('client_submission_uuid', 'uk_client_submission_uuid');
            $forge->addKey('sync_id');
            $forge->addKey('device_id');
            $forge->addKey('user_id');
            $forge->addKey('operation_type');
            $forge->addKey('sync_status');
            $forge->addKey('created_at');

            $forge->createTable('mobile_sync_journal', true);
        }

        // =====================================================================
        // 4. Table: mobile_evidence_chunks (Guards B7-G08, B7-G18)
        // =====================================================================
        if (!$db->tableExists('mobile_evidence_chunks')) {
            $forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'evidence_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'client_submission_uuid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'chunk_index' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'total_chunks' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'expected_chunk_size' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'actual_chunk_size' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'chunk_sha256' => [
                    'type'       => 'CHAR',
                    'constraint' => 64,
                ],
                'full_file_sha256' => [
                    'type'       => 'CHAR',
                    'constraint' => 64,
                ],
                'temp_file_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'CHUNK_RECEIVED',
                    // CHUNK_RECEIVED, DUPLICATE, REJECTED
                ],
                'received_at' => [
                    'type' => 'DATETIME',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $forge->addKey('id', true);
            $forge->addUniqueKey(['evidence_id', 'chunk_index'], 'uk_evidence_chunk');
            $forge->addKey('client_submission_uuid');
            $forge->addKey('status');

            $forge->createTable('mobile_evidence_chunks', true);
        }
    }

    public function down()
    {
        $forge = $this->forge ?? \Config\Database::forge();

        // Drop in reverse dependency order
        $forge->dropTable('mobile_evidence_chunks', true);
        $forge->dropTable('mobile_sync_journal', true);
        $forge->dropTable('mobile_sync_batches', true);
        $forge->dropTable('mobile_devices', true);
    }
}
