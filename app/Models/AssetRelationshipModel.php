<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetRelationshipModel extends Model
{
    protected $table            = 'asset_relationships';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'source_asset_id',
        'target_asset_id',
        'parent_asset_id',
        'child_asset_id',
        'penyulang_id',
        'relationship_type',
        'sequence_no',
        'conductor_type',
        'conductor_size',
        'conductor_material',
        'installation_type',
        'circuit_config',
        'effective_date',
        'is_active',
        'source',
        'confidence_score',
        'status',
        'distance_meters',
        'angle_score',
        'hierarchy_score',
        'distance_score',
        'created_by',
        'verified_by',
        'verified_at',
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
                    'parent_asset_id' => [
                        'type'       => 'BIGINT',
                        'constraint' => 20,
                        'unsigned'   => true,
                    ],
                    'child_asset_id' => [
                        'type'       => 'BIGINT',
                        'constraint' => 20,
                        'unsigned'   => true,
                    ],
                    'source_asset_id' => [
                        'type'       => 'BIGINT',
                        'constraint' => 20,
                        'unsigned'   => true,
                        'null'       => true,
                    ],
                    'target_asset_id' => [
                        'type'       => 'BIGINT',
                        'constraint' => 20,
                        'unsigned'   => true,
                        'null'       => true,
                    ],
                    'relationship_type' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'default'    => 'NETWORK',
                    ],
                    'sequence_no' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'default'    => 0,
                    ],
                    'source' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 30,
                        'default'    => 'AUTO',
                    ],
                    'confidence_score' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '5,2',
                        'null'       => true,
                    ],
                    'status' => [
                        'type'       => 'ENUM',
                        'constraint' => ['CANDIDATE', 'VERIFIED', 'REJECTED'],
                        'default'    => 'CANDIDATE',
                    ],
                    'distance_meters' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '10,2',
                        'null'       => true,
                    ],
                    'angle_score' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '5,2',
                        'null'       => true,
                    ],
                    'hierarchy_score' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '5,2',
                        'null'       => true,
                    ],
                    'distance_score' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '5,2',
                        'null'       => true,
                    ],
                    'effective_date' => [
                        'type' => 'DATE',
                        'null' => true,
                    ],
                    'is_active' => [
                        'type'       => 'TINYINT',
                        'constraint' => 1,
                        'default'    => 1,
                    ],
                    'created_by' => [
                        'type'       => 'BIGINT',
                        'constraint' => 20,
                        'unsigned'   => true,
                        'null'       => true,
                    ],
                    'verified_by' => [
                        'type'       => 'BIGINT',
                        'constraint' => 20,
                        'unsigned'   => true,
                        'null'       => true,
                    ],
                    'verified_at' => [
                        'type' => 'DATETIME',
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
                $forge->addKey(['parent_asset_id', 'child_asset_id'], false, true);
                $forge->addKey('parent_asset_id');
                $forge->addKey('child_asset_id');
                $forge->addKey('status');
                $forge->addKey('confidence_score');
                $forge->createTable($this->table, true);
            } else {
                // Ensure new columns exist on table
                $fieldsToAdd = [];
                if (!$db->fieldExists('parent_asset_id', $this->table)) {
                    $fieldsToAdd['parent_asset_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0];
                }
                if (!$db->fieldExists('child_asset_id', $this->table)) {
                    $fieldsToAdd['child_asset_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0];
                }
                if (!$db->fieldExists('source', $this->table)) {
                    $fieldsToAdd['source'] = ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'AUTO'];
                }
                if (!$db->fieldExists('confidence_score', $this->table)) {
                    $fieldsToAdd['confidence_score'] = ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true];
                }
                if (!$db->fieldExists('status', $this->table)) {
                    $fieldsToAdd['status'] = ['type' => "ENUM('CANDIDATE','VERIFIED','REJECTED')", 'default' => 'CANDIDATE'];
                }
                if (!$db->fieldExists('distance_meters', $this->table)) {
                    $fieldsToAdd['distance_meters'] = ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true];
                }
                if (!$db->fieldExists('angle_score', $this->table)) {
                    $fieldsToAdd['angle_score'] = ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true];
                }
                if (!$db->fieldExists('hierarchy_score', $this->table)) {
                    $fieldsToAdd['hierarchy_score'] = ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true];
                }
                if (!$db->fieldExists('distance_score', $this->table)) {
                    $fieldsToAdd['distance_score'] = ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true];
                }
                if (!$db->fieldExists('created_by', $this->table)) {
                    $fieldsToAdd['created_by'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true];
                }
                if (!$db->fieldExists('verified_by', $this->table)) {
                    $fieldsToAdd['verified_by'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true];
                }
                if (!$db->fieldExists('verified_at', $this->table)) {
                    $fieldsToAdd['verified_at'] = ['type' => 'DATETIME', 'null' => true];
                }

                if (!empty($fieldsToAdd)) {
                    $forge = \Config\Database::forge();
                    $forge->addColumn($this->table, $fieldsToAdd);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[AssetRelationshipModel::ensureTableExists] Exception: ' . $e->getMessage());
        }
    }
}
