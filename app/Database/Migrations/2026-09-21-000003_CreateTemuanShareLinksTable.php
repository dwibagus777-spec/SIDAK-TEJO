<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CR-HOTFIX-03: Additive Domain — Temuan Share Links
 *
 * Implements:
 * 1. Table temuan_share_links:
 *    - Stores cryptographic sha256 hash of random tokens.
 *    - Raw token is never stored in database (one-way hash verification).
 *    - Allows public, read-only access to finding details & network asset context.
 *    - Supports expiration and active revocation.
 *
 * Guaranteed Invariants:
 * - Completely additive; zero disruption to existing temuan, assets, or users.
 * - Zero database writes on read views.
 */
class CreateTemuanShareLinksTable extends Migration
{
    public function up()
    {
        $db = $this->db ?? \Config\Database::connect();

        if (!$db->tableExists('temuan_share_links')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'temuan_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => false,
                ],
                'token_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'created_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'expires_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'revoked_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('temuan_id');
            $this->forge->addUniqueKey('token_hash');
            $this->forge->createTable('temuan_share_links', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('temuan_share_links', true);
    }
}
