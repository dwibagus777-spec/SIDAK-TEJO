<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemSettingModel extends Model
{
    protected $table            = 'system_settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['setting_key', 'setting_value', 'updated_by', 'updated_at'];
    protected $useTimestamps    = false;

    public function __construct()
    {
        parent::__construct();
        $this->ensureTableExists();
    }

    private static bool $tableChecked = false;

    /**
     * Pastikan tabel system_settings dan data default selalu tersedia di database
     */
    private function ensureTableExists(): void
    {
        if (self::$tableChecked) {
            return;
        }
        self::$tableChecked = true;
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('system_settings')) {
                $forge = \Config\Database::forge();
                $forge->addField([
                    'id' => [
                        'type'           => 'INT',
                        'constraint'     => 11,
                        'unsigned'       => true,
                        'auto_increment' => true,
                    ],
                    'setting_key' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'unique'     => true,
                    ],
                    'setting_value' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'updated_by' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ],
                    'updated_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('system_settings', true);

                // Seed Default Settings
                $this->seedDefaults();
            } else {
                // Ensure default keys exist
                $this->seedMissingDefaults();
            }
        } catch (\Throwable $e) {
            log_message('error', '[SystemSettingModel] Gagal membuat tabel system_settings: ' . $e->getMessage());
        }
    }

    /**
     * Isi data bawaan awal jika tabel baru dibuat
     */
    private function seedDefaults(): void
    {
        $defaults = [
            [
                'setting_key'   => 'daily_motivation',
                'setting_value' => '⚡ Tetap Utamakan K3 & Keselamatan Kerja! Semangat Petugas Inspeksi & HAR PLN UP3 Sidoarjo! Bekerja Keras, Pulang Selamat! ⚡',
                'updated_by'    => 'System Default',
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key'   => 'running_text',
                'setting_value' => '⚡ PLN UP3 Sidoarjo - Sistem Inspeksi Jaringan Distribusi 20KV Terintegrasi Realtime ⚡',
                'updated_by'    => 'System Default',
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key'   => 'dashboard_message',
                'setting_value' => 'Selamat datang di Sistem Informasi Data dan Tindak Lanjut Temuan Inspeksi Jaringan PLN UP3 Sidoarjo.',
                'updated_by'    => 'System Default',
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key'   => 'dashboard_title',
                'setting_value' => 'SIDAK TEJO',
                'updated_by'    => 'System Default',
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key'   => 'dashboard_subtitle',
                'setting_value' => 'Sistem Data dan Tindak Lanjut Temuan Inspeksi Sidoarjo',
                'updated_by'    => 'System Default',
                'updated_at'    => date('Y-m-d H:i:s'),
            ]
        ];

        foreach ($defaults as $row) {
            $this->db->table('system_settings')->insert($row);
        }
    }

    /**
     * Pastikan tidak ada key bawaan yang terlewat
     */
    private function seedMissingDefaults(): void
    {
        $defaults = [
            'daily_motivation'   => '⚡ Tetap Utamakan K3 & Keselamatan Kerja! Semangat Petugas Inspeksi & HAR PLN UP3 Sidoarjo! Bekerja Keras, Pulang Selamat! ⚡',
            'running_text'       => '⚡ PLN UP3 Sidoarjo - Sistem Inspeksi Jaringan Distribusi 20KV Terintegrasi Realtime ⚡',
            'dashboard_message'  => 'Selamat datang di Sistem Informasi Data dan Tindak Lanjut Temuan Inspeksi Jaringan PLN UP3 Sidoarjo.',
            'dashboard_title'    => 'SIDAK TEJO',
            'dashboard_subtitle' => 'Sistem Data dan Tindak Lanjut Temuan Inspeksi Sidoarjo',
        ];

        foreach ($defaults as $key => $val) {
            $existing = $this->db->table('system_settings')->where('setting_key', $key)->get()->getRowArray();
            if (!$existing) {
                $this->db->table('system_settings')->insert([
                    'setting_key'   => $key,
                    'setting_value' => $val,
                    'updated_by'    => 'System Default',
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
