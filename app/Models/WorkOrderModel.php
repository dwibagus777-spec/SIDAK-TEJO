<?php

namespace App\Models;

use CodeIgniter\Model;

class WorkOrderModel extends Model
{
    protected $table            = 'work_orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'deleted_at';

    protected $allowedFields    = [
        'nomor_wo', 'temuan_id', 'asset_id', 'judul_wo', 'detail_wo',
        'assigned_to', 'assigned_team', 'pelaksana', 'prioritas',
        'status', 'target_selesai', 'tanggal_selesai', 'catatan',
        'created_by', 'created_at', 'updated_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function __construct()
    {
        parent::__construct();
        $this->ensureTablesExist();
    }

    private static bool $tablesChecked = false;

    /**
     * Pastikan tabel work_orders, wo_checklists, wo_materials, wo_histories tersedia
     */
    private function ensureTablesExist(): void
    {
        if (self::$tablesChecked) {
            return;
        }
        self::$tablesChecked = true;
        try {
            $db = \Config\Database::connect();
            $forge = \Config\Database::forge();

            // 1. Ensure asset_id column exists in temuan table
            if ($db->tableExists('temuan') && !$db->fieldExists('asset_id', 'temuan')) {
                $forge->addColumn('temuan', [
                    'asset_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'null'       => true,
                        'after'      => 'nomor_temuan'
                    ]
                ]);
            }

            // 2. Work Orders Table
            if (!$db->tableExists('work_orders')) {
                $forge->addField([
                    'id' => [
                        'type'           => 'INT',
                        'constraint'     => 11,
                        'unsigned'       => true,
                        'auto_increment' => true,
                    ],
                    'nomor_wo' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'unique'     => true,
                    ],
                    'temuan_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'null'       => true,
                    ],
                    'asset_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'null'       => true,
                    ],
                    'judul_wo' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                    ],
                    'detail_wo' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'assigned_to' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'assigned_team' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'pelaksana' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'prioritas' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'default'    => 'MEDIUM',
                    ],
                    'status' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'default'    => 'OPEN', // OPEN, ASSIGNED, PROGRESS, WAITING_MATERIAL, WAITING_PADAM, COMPLETED, CANCELLED
                    ],
                    'target_selesai' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                    'tanggal_selesai' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                    'catatan' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'created_by' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'created_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                    'updated_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                    'deleted_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('work_orders', true);
            }

            // 3. WO Checklists Table
            if (!$db->tableExists('wo_checklists')) {
                $forge->addField([
                    'id' => [
                        'type'           => 'INT',
                        'constraint'     => 11,
                        'unsigned'       => true,
                        'auto_increment' => true,
                    ],
                    'wo_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                    ],
                    'item_text' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                    ],
                    'is_completed' => [
                        'type'       => 'TINYINT',
                        'constraint' => 1,
                        'default'    => 0,
                    ],
                    'completed_by' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'completed_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('wo_checklists', true);
            }

            // 4. WO Materials Table
            if (!$db->tableExists('wo_materials')) {
                $forge->addField([
                    'id' => [
                        'type'           => 'INT',
                        'constraint'     => 11,
                        'unsigned'       => true,
                        'auto_increment' => true,
                    ],
                    'wo_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                    ],
                    'nama_material' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                    ],
                    'jumlah' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '10,2',
                        'default'    => 1.00,
                    ],
                    'satuan' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'default'    => 'Pcs',
                    ],
                    'harga' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '15,2',
                        'default'    => 0.00,
                    ],
                    'status_penggunaan' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'default'    => 'TERPAKAI', // TERPAKAI, TERSISA, DIBATALKAN
                    ],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('wo_materials', true);
            }

            // 5. WO Histories Table
            if (!$db->tableExists('wo_histories')) {
                $forge->addField([
                    'id' => [
                        'type'           => 'INT',
                        'constraint'     => 11,
                        'unsigned'       => true,
                        'auto_increment' => true,
                    ],
                    'wo_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
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
                    'aktivitas' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                    ],
                    'catatan' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'foto_sebelum' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                    ],
                    'foto_proses' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                    ],
                    'foto_sesudah' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                    ],
                    'created_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('wo_histories', true);
            }
        } catch (\Throwable $e) {
            log_message('error', '[WorkOrderModel] Gagal membuat tabel WO: ' . $e->getMessage());
        }
    }
}
