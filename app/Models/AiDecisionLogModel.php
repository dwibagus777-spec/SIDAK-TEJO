<?php

namespace App\Models;

use CodeIgniter\Model;

class AiDecisionLogModel extends Model
{
    protected $table            = 'ai_decision_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['target_type', 'target_id', 'engine_name', 'input_data', 'score', 'output_recommendation', 'explanation', 'created_at'];
    protected $useTimestamps    = false;

    public function __construct()
    {
        parent::__construct();
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('ai_decision_logs')) {
                $forge = \Config\Database::forge();
                $forge->addField([
                    'id' => [
                        'type'           => 'INT',
                        'constraint'     => 11,
                        'unsigned'       => true,
                        'auto_increment' => true,
                    ],
                    'target_type' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50, // ASSET, TEMUAN, WO
                    ],
                    'target_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                    ],
                    'engine_name' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                    ],
                    'input_data' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'score' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '5,2',
                        'default'    => 0.00,
                    ],
                    'output_recommendation' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'explanation' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'created_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('ai_decision_logs', true);
            }
        } catch (\Throwable $e) {
            log_message('error', '[AiDecisionLogModel] Gagal membuat tabel: ' . $e->getMessage());
        }
    }
}
