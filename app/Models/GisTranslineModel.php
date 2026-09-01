<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for Authoritative GIS Transline Segments
 */
class GisTranslineModel extends Model
{
    protected $table            = 'gis_translines';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'transline_code',
        'penyulang_id',
        'source_asset_id',
        'target_asset_id',
        'geometry',
        'geometry_type',
        'conductor_type',
        'conductor_size',
        'conductor_material',
        'installation_type',
        'circuit_config',
        'distance_meters',
        'status',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_at',
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

        try {
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
                    'transline_code' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'penyulang_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'unsigned'   => true,
                    ],
                    'source_asset_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'unsigned'   => true,
                    ],
                    'target_asset_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'unsigned'   => true,
                    ],
                    'geometry' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'geometry_type' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 32,
                        'default'    => 'LineString',
                    ],
                    'conductor_type' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 64,
                        'default'    => 'AAAC',
                    ],
                    'conductor_size' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 64,
                        'default'    => '150 mm²',
                    ],
                    'conductor_material' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 64,
                        'default'    => 'ALUMINUM_ALLOY',
                    ],
                    'installation_type' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 64,
                        'default'    => 'OVERHEAD',
                    ],
                    'circuit_config' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 64,
                        'default'    => '3_PHASE',
                    ],
                    'distance_meters' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '10,2',
                        'default'    => 0.00,
                    ],
                    'status' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 32,
                        'default'    => 'ACTIVE',
                    ],
                    'is_active' => [
                        'type'       => 'TINYINT',
                        'constraint' => 1,
                        'default'    => 1,
                    ],
                    'created_by' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'default'    => 'SYSTEM',
                    ],
                    'updated_by' => [
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
                $forge->addKey('penyulang_id');
                $forge->addKey('source_asset_id');
                $forge->addKey('target_asset_id');
                $forge->addKey('is_active');
                $forge->createTable($this->table, true);
            }
            self::$tableChecked = true;
        } catch (\Throwable $e) {
            log_message('error', '[GisTranslineModel::ensureTableExists] ' . $e->getMessage());
        }
    }

    /**
     * Get all active translines for a specific feeder
     */
    public function getActiveTranslinesByFeeder(int $feederId): array
    {
        if ($feederId <= 0) return [];

        return $this->where('penyulang_id', $feederId)
                    ->where('is_active', 1)
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }
}
