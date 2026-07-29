<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'user_id', 'username', 'role', 'nip', 'nama_lengkap', 
        'ulp_id', 'penyulang_id', 'section_id', 'temuan_id', 'wo_id', 
        'aktivitas', 'detail', 'data_lama_json', 'data_baru_json', 'diff_json', 
        'ip_address', 'user_agent', 'browser', 'os', 'device', 'app_type', 
        'latitude', 'longitude', 'session_id', 'version_number', 'created_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

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

            if (!$db->tableExists('audit_logs')) {
                $forge->addField([
                    'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'user_id'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'username'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                    'role'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                    'nip'            => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                    'nama_lengkap'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                    'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'penyulang_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'section_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'temuan_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'wo_id'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'aktivitas'      => ['type' => 'VARCHAR', 'constraint' => 100],
                    'detail'         => ['type' => 'TEXT', 'null' => true],
                    'data_lama_json' => ['type' => 'LONGTEXT', 'null' => true],
                    'data_baru_json' => ['type' => 'LONGTEXT', 'null' => true],
                    'diff_json'      => ['type' => 'LONGTEXT', 'null' => true],
                    'ip_address'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                    'user_agent'     => ['type' => 'TEXT', 'null' => true],
                    'browser'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                    'os'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                    'device'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                    'app_type'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'WEB'],
                    'latitude'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                    'longitude'      => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                    'session_id'     => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
                    'version_number' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                    'created_at'     => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('audit_logs', true);
            } else {
                $fieldsToEnsure = ['nip', 'nama_lengkap', 'ulp_id', 'penyulang_id', 'section_id', 'temuan_id', 'wo_id', 'data_lama_json', 'data_baru_json', 'diff_json', 'browser', 'os', 'device', 'app_type', 'latitude', 'longitude', 'session_id', 'version_number'];
                foreach ($fieldsToEnsure as $col) {
                    if (!$db->fieldExists($col, 'audit_logs')) {
                        $forge->addColumn('audit_logs', [$col => ['type' => 'LONGTEXT', 'null' => true]]);
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[AuditLogModel] Table ensure error: ' . $e->getMessage());
        }
    }
}
