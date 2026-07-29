<?php

namespace App\Models;

use CodeIgniter\Model;

class IntegrationModel extends Model
{
    protected $table            = 'api_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['user_id', 'api_key', 'method', 'endpoint', 'request_body', 'response_body', 'status_code', 'duration_ms', 'ip_address', 'user_agent', 'created_at'];
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

            // 1. API Logs
            if (!$db->tableExists('api_logs')) {
                $forge->addField([
                    'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'user_id'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'api_key'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                    'method'        => ['type' => 'VARCHAR', 'constraint' => 10],
                    'endpoint'      => ['type' => 'VARCHAR', 'constraint' => 255],
                    'request_body'  => ['type' => 'TEXT', 'null' => true],
                    'response_body' => ['type' => 'TEXT', 'null' => true],
                    'status_code'   => ['type' => 'INT', 'constraint' => 6, 'default' => 200],
                    'duration_ms'   => ['type' => 'FLOAT', 'null' => true],
                    'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                    'user_agent'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                    'created_at'    => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('api_logs', true);
            }

            // 2. API Keys
            if (!$db->tableExists('api_keys')) {
                $forge->addField([
                    'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'user_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'api_key'     => ['type' => 'VARCHAR', 'constraint' => 128, 'unique' => true],
                    'secret'      => ['type' => 'VARCHAR', 'constraint' => 255],
                    'permissions' => ['type' => 'TEXT', 'null' => true],
                    'rate_limit'  => ['type' => 'INT', 'constraint' => 11, 'default' => 1000],
                    'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                    'last_used_at'=> ['type' => 'DATETIME', 'null' => true],
                    'created_at'  => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('api_keys', true);
            }

            // 3. Webhooks
            if (!$db->tableExists('webhooks')) {
                $forge->addField([
                    'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'url'         => ['type' => 'VARCHAR', 'constraint' => 500],
                    'event'       => ['type' => 'VARCHAR', 'constraint' => 100],
                    'secret'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                    'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                    'retry_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                    'last_triggered_at' => ['type' => 'DATETIME', 'null' => true],
                    'last_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                    'created_at'  => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('webhooks', true);
            }

            // 4. Webhook Logs
            if (!$db->tableExists('webhook_logs')) {
                $forge->addField([
                    'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'webhook_id'   => ['type' => 'INT', 'constraint' => 11],
                    'event'        => ['type' => 'VARCHAR', 'constraint' => 100],
                    'payload'      => ['type' => 'TEXT', 'null' => true],
                    'response_code'=> ['type' => 'INT', 'constraint' => 6, 'null' => true],
                    'response_body'=> ['type' => 'TEXT', 'null' => true],
                    'attempt'      => ['type' => 'INT', 'constraint' => 3, 'default' => 1],
                    'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDING'],
                    'created_at'   => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('webhook_logs', true);
            }

        } catch (\Throwable $e) {
            log_message('error', '[IntegrationModel] Gagal membuat tabel: ' . $e->getMessage());
        }
    }
}
