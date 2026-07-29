<?php

namespace App\Models;

use CodeIgniter\Model;

class AiLogModel extends Model
{
    protected $table            = 'ai_conversation_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = false;
    protected $allowedFields    = [
        'user_id',
        'user_name',
        'user_role',
        'channel',
        'user_command',
        'intent',
        'ai_response',
        'action_type',
        'created_at'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->ensureTableExists();
    }

    private function ensureTableExists()
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists($this->table)) {
            $forge = \Config\Database::forge();
            $forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'user_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'user_role' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'channel' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'voice',
                ],
                'user_command' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'intent' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'ai_response' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'action_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $forge->addKey('id', true);
            $forge->createTable($this->table, true);
        }
    }
}
