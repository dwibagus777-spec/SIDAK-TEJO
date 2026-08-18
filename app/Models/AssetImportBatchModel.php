<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetImportBatchModel extends Model
{
    protected $table            = 'asset_import_batches';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'batch_code',
        'ulp_id',
        'penyulang_id',
        'file_name',
        'total_rows',
        'success_rows',
        'failed_rows',
        'imported_by',
        'imported_at',
        'status',
        'notes',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function __construct()
    {
        parent::__construct();
        $this->ensureTableExists();
    }

    private static bool $tableChecked = false;

    private function ensureTableExists(): void
    {
        if (self::$tableChecked) {
            return;
        }
        self::$tableChecked = true;

        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists($this->table)) {
                $forge = \Config\Database::forge();
                $forge->addField([
                    'id' => [
                        'type'           => 'BIGINT',
                        'constraint'     => 20,
                        'unsigned'       => true,
                        'auto_increment' => true,
                    ],
                    'batch_code' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                    ],
                    'ulp_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'null'       => true,
                    ],
                    'penyulang_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'null'       => true,
                    ],
                    'file_name' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                    ],
                    'total_rows' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'default'    => 0,
                    ],
                    'success_rows' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'default'    => 0,
                    ],
                    'failed_rows' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'default'    => 0,
                    ],
                    'imported_by' => [
                        'type'       => 'BIGINT',
                        'constraint' => 20,
                        'unsigned'   => true,
                        'null'       => true,
                    ],
                    'imported_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                    'status' => [
                        'type'       => 'ENUM',
                        'constraint' => ['ACTIVE', 'ROLLED_BACK'],
                        'default'    => 'ACTIVE',
                    ],
                    'notes' => [
                        'type' => 'TEXT',
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
                $forge->addKey('batch_code');
                $forge->addKey('penyulang_id');
                $forge->addKey('status');
                $forge->createTable($this->table, true);
            }

            // Ensure import_batch_id & deleted_reason exist in assets table
            if ($db->tableExists('assets')) {
                $forge = \Config\Database::forge();
                if (!$db->fieldExists('import_batch_id', 'assets')) {
                    $forge->addColumn('assets', [
                        'import_batch_id' => [
                            'type'       => 'BIGINT',
                            'constraint' => 20,
                            'unsigned'   => true,
                            'null'       => true,
                        ]
                    ]);
                }
                if (!$db->fieldExists('deleted_reason', 'assets')) {
                    $forge->addColumn('assets', [
                        'deleted_reason' => [
                            'type'       => 'VARCHAR',
                            'constraint' => 255,
                            'null'       => true,
                        ]
                    ]);
                }
                if (!$db->fieldExists('deleted_by', 'assets')) {
                    $forge->addColumn('assets', [
                        'deleted_by' => [
                            'type'       => 'BIGINT',
                            'constraint' => 20,
                            'unsigned'   => true,
                            'null'       => true,
                        ]
                    ]);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[AssetImportBatchModel::ensureTableExists] Exception: ' . $e->getMessage());
        }
    }
}
