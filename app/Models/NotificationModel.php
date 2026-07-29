<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['user_id', 'type', 'title', 'message', 'channel', 'status', 'target', 'role', 'temuan_id', 'is_read', 'read_at', 'created_at', 'updated_at'];
    protected $useTimestamps    = false;

    public function __construct()
    {
        parent::__construct();
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        try {
            $db = \Config\Database::connect();
            $forge = \Config\Database::forge();

            // 1. Table `notifications`
            if (!$db->tableExists('notifications')) {
                $forge->addField([
                    'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'user_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'temuan_id'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'role'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                    'type'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'INFO'],
                    'title'      => ['type' => 'VARCHAR', 'constraint' => 255],
                    'message'    => ['type' => 'TEXT'],
                    'channel'    => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'IN_APP'],
                    'status'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'SENT'],
                    'is_read'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                    'target'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                    'read_at'    => ['type' => 'DATETIME', 'null' => true],
                    'created_at' => ['type' => 'DATETIME', 'null' => true],
                    'updated_at' => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('notifications', true);
            } else {
                // Ensure missing columns exist
                if (!$db->fieldExists('role', 'notifications')) {
                    $forge->addColumn('notifications', ['role' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]]);
                }
                if (!$db->fieldExists('temuan_id', 'notifications')) {
                    $forge->addColumn('notifications', ['temuan_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true]]);
                }
                if (!$db->fieldExists('is_read', 'notifications')) {
                    $forge->addColumn('notifications', ['is_read' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]]);
                }
                if (!$db->fieldExists('updated_at', 'notifications')) {
                    $forge->addColumn('notifications', ['updated_at' => ['type' => 'DATETIME', 'null' => true]]);
                }
            }

            // 2. Table `notification_templates`
            if (!$db->tableExists('notification_templates')) {
                $forge->addField([
                    'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'template_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'unique' => true],
                    'title' => ['type' => 'VARCHAR', 'constraint' => 255],
                    'body' => ['type' => 'TEXT'],
                    'channel' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'ALL'],
                    'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                    'updated_at' => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('notification_templates', true);

                // Seed Default Templates
                $db->table('notification_templates')->insertBatch([
                    ['template_key' => 'TEMUAN_BARU', 'title' => '🚨 Temuan Inspeksi Baru ({nomor_temuan})', 'body' => 'Ditemukan temuan baru {jenis_temuan} di ULP {nama_ulp} Penyulang {nama_penyulang}. Prioritas: {prioritas}.', 'channel' => 'ALL', 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                    ['template_key' => 'WO_SELESAI', 'title' => '✅ Work Order Selesai ({nomor_wo})', 'body' => 'Work Order {judul_wo} telah diselesaikan oleh petugas {assigned_to}.', 'channel' => 'ALL', 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                    ['template_key' => 'SLA_OVERDUE', 'title' => '⚠️ PERINGATAN SLA TERLAMBAT ({nomor_temuan})', 'body' => 'Temuan {nomor_temuan} telah melebihi SLA penanganan. Segera ambil tindakan!', 'channel' => 'ALL', 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                ]);
            }

            // 3. Table `notification_rules`
            if (!$db->tableExists('notification_rules')) {
                $forge->addField([
                    'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'rule_name' => ['type' => 'VARCHAR', 'constraint' => 255],
                    'condition_field' => ['type' => 'VARCHAR', 'constraint' => 100],
                    'condition_op' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '='],
                    'condition_val' => ['type' => 'VARCHAR', 'constraint' => 100],
                    'action_channel' => ['type' => 'VARCHAR', 'constraint' => 50],
                    'target_role' => ['type' => 'VARCHAR', 'constraint' => 100],
                    'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('notification_rules', true);
            }

            // 4. Table `user_notification_preferences`
            if (!$db->tableExists('user_notification_preferences')) {
                $forge->addField([
                    'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'user_id' => ['type' => 'INT', 'constraint' => 11, 'unique' => true],
                    'push_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                    'wa_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                    'email_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                    'telegram_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                    'voice_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                    'dnd_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                    'dnd_start' => ['type' => 'TIME', 'null' => true],
                    'dnd_end' => ['type' => 'TIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('user_notification_preferences', true);
            }

        } catch (\Throwable $e) {
            log_message('error', '[NotificationModel] Gagal membuat tabel: ' . $e->getMessage());
        }
    }
}
