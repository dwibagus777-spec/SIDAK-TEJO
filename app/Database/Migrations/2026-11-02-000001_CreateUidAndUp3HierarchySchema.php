<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUidAndUp3HierarchySchema extends Migration
{
    public function up()
    {
        // 1. Create uids table
        if (!$this->db->tableExists('uids')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'kode_uid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'unique'     => true,
                ],
                'nama_uid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['AKTIF', 'NONAKTIF'],
                    'default'    => 'AKTIF',
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
            $this->forge->createTable('uids', true);
        }

        // 2. Create up3s table
        if (!$this->db->tableExists('up3s')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'uid_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'kode_up3' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'unique'     => true,
                ],
                'nama_up3' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['AKTIF', 'NONAKTIF'],
                    'default'    => 'AKTIF',
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
            $this->forge->addKey('uid_id');
            $this->forge->createTable('up3s', true);
        }

        // 3. Add up3_id to ulps
        if ($this->db->tableExists('ulps') && !$this->db->fieldExists('up3_id', 'ulps')) {
            $this->forge->addColumn('ulps', [
                'up3_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'id',
                ],
            ]);
            $this->db->query('ALTER TABLE `ulps` ADD INDEX `idx_ulps_up3_id` (`up3_id`)');
        }

        // 4. Add up3_id to gardu_induk
        if ($this->db->tableExists('gardu_induk') && !$this->db->fieldExists('up3_id', 'gardu_induk')) {
            $this->forge->addColumn('gardu_induk', [
                'up3_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'id',
                ],
            ]);
            $this->db->query('ALTER TABLE `gardu_induk` ADD INDEX `idx_gardu_induk_up3_id` (`up3_id`)');
        }

        // 5. Add uid_id and up3_id to users
        if ($this->db->tableExists('users')) {
            $userCols = [];
            if (!$this->db->fieldExists('uid_id', 'users')) {
                $userCols['uid_id'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'role',
                ];
            }
            if (!$this->db->fieldExists('up3_id', 'users')) {
                $userCols['up3_id'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'uid_id',
                ];
            }
            if (!empty($userCols)) {
                $this->forge->addColumn('users', $userCols);
                if (isset($userCols['uid_id'])) {
                    $this->db->query('ALTER TABLE `users` ADD INDEX `idx_users_uid_id` (`uid_id`)');
                }
                if (isset($userCols['up3_id'])) {
                    $this->db->query('ALTER TABLE `users` ADD INDEX `idx_users_up3_id` (`up3_id`)');
                }
            }
        }

        // 6. Authoritative backfill: UID Jawa Timur & UP3 Sidoarjo
        $now = date('Y-m-d H:i:s');
        $uidExists = $this->db->table('uids')->where('id', 1)->countAllResults();
        if ($uidExists === 0) {
            $this->db->table('uids')->insert([
                'id'         => 1,
                'kode_uid'   => 'UID_JATIM',
                'nama_uid'   => 'UID Jawa Timur',
                'status'     => 'AKTIF',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $up3Exists = $this->db->table('up3s')->where('id', 1)->countAllResults();
        if ($up3Exists === 0) {
            $this->db->table('up3s')->insert([
                'id'         => 1,
                'uid_id'     => 1,
                'kode_up3'   => 'UP3_SDA',
                'nama_up3'   => 'UP3 Sidoarjo',
                'status'     => 'AKTIF',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Link existing ulps & gardu_induk to UP3 Sidoarjo (1)
        if ($this->db->tableExists('ulps')) {
            $this->db->table('ulps')->where('up3_id IS NULL')->update(['up3_id' => 1]);
        }
        if ($this->db->tableExists('gardu_induk')) {
            $this->db->table('gardu_induk')->where('up3_id IS NULL')->update(['up3_id' => 1]);
        }
        if ($this->db->tableExists('users')) {
            $this->db->table('users')->where('uid_id IS NULL')->update(['uid_id' => 1, 'up3_id' => 1]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('users')) {
            if ($this->db->fieldExists('up3_id', 'users')) {
                $this->forge->dropColumn('users', 'up3_id');
            }
            if ($this->db->fieldExists('uid_id', 'users')) {
                $this->forge->dropColumn('users', 'uid_id');
            }
        }

        if ($this->db->tableExists('gardu_induk') && $this->db->fieldExists('up3_id', 'gardu_induk')) {
            $this->forge->dropColumn('gardu_induk', 'up3_id');
        }

        if ($this->db->tableExists('ulps') && $this->db->fieldExists('up3_id', 'ulps')) {
            $this->forge->dropColumn('ulps', 'up3_id');
        }

        if ($this->db->tableExists('up3s')) {
            $this->forge->dropTable('up3s', true);
        }

        if ($this->db->tableExists('uids')) {
            $this->forge->dropTable('uids', true);
        }
    }
}
