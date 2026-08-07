<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetModel extends Model
{
    protected $table            = 'assets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'deleted_at';

    protected $allowedFields    = [
        'kode_asset', 'nama_asset', 'jenis_asset', 'ulp_id', 'penyulang_id', 'section_id',
        'parent_asset_id', 'asset_type_id', 'construction_type_id', 'sequence_no',
        'lokasi', 'latitude', 'longitude', 'tahun_instalasi', 'installation_date', 'merk', 'type',
        'nomor_seri', 'kapasitas', 'status', 'health_score', 'health_category', 'asset_version',
        'foto', 'qr_code', 'barcode', 'deleted_by', 'deleted_reason',
        'created_at', 'updated_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function __construct()
    {
        parent::__construct();
        $this->ensureTableExists();
    }

    private static bool $tableChecked = false;

    /**
     * Pastikan tabel assets dan data bawaan PLN tersedia di database
     */
    private function ensureTableExists(): void
    {
        if (self::$tableChecked) {
            return;
        }
        self::$tableChecked = true;
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('assets')) {
                $forge = \Config\Database::forge();
                $forge->addField([
                    'id' => [
                        'type'           => 'INT',
                        'constraint'     => 11,
                        'unsigned'       => true,
                        'auto_increment' => true,
                    ],
                    'kode_asset' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'unique'     => true,
                    ],
                    'nama_asset' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                    ],
                    'jenis_asset' => [
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
                    'section_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'null'       => true,
                    ],
                    'lokasi' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'latitude' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'null'       => true,
                    ],
                    'longitude' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'null'       => true,
                    ],
                    'tahun_instalasi' => [
                        'type'       => 'INT',
                        'constraint' => 4,
                        'null'       => true,
                    ],
                    'merk' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'type' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'nomor_seri' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'kapasitas' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'status' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'default'    => 'NORMAL', // NORMAL, BERMASALAH, CRITICAL
                    ],
                    'foto' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                    ],
                    'qr_code' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                    ],
                    'barcode' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
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
                $forge->createTable('assets', true);

                $this->seedDefaults();
            }
        } catch (\Throwable $e) {
            log_message('error', '[AssetModel] Gagal membuat tabel assets: ' . $e->getMessage());
        }
    }

    private function seedDefaults(): void
    {
        $sampleAssets = [
            [
                'kode_asset'      => 'AST-GRD-001',
                'nama_asset'      => 'Gardu SDJ-045 Sidoarjo Kota',
                'jenis_asset'     => 'Gardu',
                'ulp_id'          => 1,
                'lokasi'          => 'Jl. Raya Pahlawan No. 45, Sidoarjo',
                'latitude'        => '-7.4478',
                'longitude'       => '112.7183',
                'tahun_instalasi' => 2018,
                'merk'            => 'Schneider Electric',
                'type'            => 'Portal 20KV',
                'nomor_seri'      => 'SN-GRD-2018-045',
                'kapasitas'       => '250 kVA',
                'status'          => 'NORMAL',
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ],
            [
                'kode_asset'      => 'AST-TRF-002',
                'nama_asset'      => 'Trafo Distribusi 160kVA Krian',
                'jenis_asset'     => 'Trafo',
                'ulp_id'          => 2,
                'lokasi'          => 'Jl. Raya Krian No. 12, Krian',
                'latitude'        => '-7.4125',
                'longitude'       => '112.5832',
                'tahun_instalasi' => 2020,
                'merk'            => 'Trafoindo',
                'type'            => 'Oil Immersed 20KV/400V',
                'nomor_seri'      => 'SN-TRF-2020-160',
                'kapasitas'       => '160 kVA',
                'status'          => 'BERMASALAH',
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ],
            [
                'kode_asset'      => 'AST-KUB-003',
                'nama_asset'      => 'Kubikel Outgoing Waru',
                'jenis_asset'     => 'Kubikel',
                'ulp_id'          => 3,
                'lokasi'          => 'GI Waru Panel 3, Waru',
                'latitude'        => '-7.3521',
                'longitude'       => '112.7231',
                'tahun_instalasi' => 2021,
                'merk'            => 'ABB',
                'type'            => 'Unifluorc 24kV',
                'nomor_seri'      => 'SN-KUB-2021-089',
                'kapasitas'       => '630 A',
                'status'          => 'CRITICAL',
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($sampleAssets as $asset) {
            $this->db->table('assets')->insert($asset);
        }
    }
}
