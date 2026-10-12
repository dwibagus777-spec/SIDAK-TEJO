<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateKubikelInspectionTables extends Migration
{
    public function up()
    {
        $tables = [
            'inspeksi_kubikel',
            'inspeksi_kubikel_checklist',
            'inspeksi_kubikel_foto',
            'inspeksi_kubikel_temuan',
            'inspeksi_kubikel_rekomendasi',
            'inspeksi_kubikel_histori',
            'inspeksi_kubikel_material',
        ];
        $existingTables = array_values(array_filter($tables, fn (string $table): bool => $this->db->tableExists($table, false)));
        if ($existingTables !== []) {
            throw new \RuntimeException(
                '[CreateKubikelInspectionTables] Refusing to create or skip a partial/existing schema: '
                . implode(', ', $existingTables)
            );
        }

        // 1. Tabel inspeksi_kubikel (utama untuk inspeksi kubikel)
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nomor_inspeksi' => [
                'type'           => 'VARCHAR',
                'constraint'     => 50,
                'null'           => true,
            ],
            'inspection_type_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'ulp_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'penyulang_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'section_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'gardu_induk_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'asset_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'id_pel' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => true,
            ],
            'inspector_user_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => false,
            ],
            'tgl_inspeksi' => [
                'type'           => 'DATE',
                'null'           => false,
            ],
            'waktu_mulai' => [
                'type'           => 'TIME',
                'null'           => true,
            ],
            'waktu_selesai' => [
                'type'           => 'TIME',
                'null'           => true,
            ],
            'status' => [
                'type'           => "ENUM('DRAFT','IN_PROGRESS','SELESAI','DITUTUP','DIBATALKAN')",
                'null'           => false,
                'default'        => 'DRAFT',
            ],
            'status_temuan' => [
                'type'           => "ENUM('ADA','TIDAK_ADA','MENUNGGU_VERIFIKASI')",
                'null'           => false,
                'default'        => 'TIDAK_ADA',
            ],
            'catatan_umum' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'alasan_penutupan' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'supervisor_approval_user_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'supervisor_approval_at' => [
                'type'           => 'DATETIME',
                'null'           => true,
            ],
            'created_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ],
            'deleted_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('nomor_inspeksi');
        $this->forge->addKey(['ulp_id', 'penyulang_id', 'tgl_inspeksi']);
        $this->forge->addKey('inspector_user_id');
        $this->forge->addKey('asset_id');
        $this->forge->createTable('inspeksi_kubikel');

        // 2. Tabel inspeksi_kubikel_checklist (detail checklist per inspeksi)
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'inspeksi_kubikel_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => false,
            ],
            'komponen' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => false,
            ],
            'fasa' => [
                'type'           => "ENUM('R','S','T','N','TIDAK_BERFASA')",
                'null'           => false,
                'default'        => 'TIDAK_BERFASA',
            ],
            'posisi' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => true,
            ],
            'item_pemeriksaan' => [
                'type'           => 'VARCHAR',
                'constraint'     => 255,
                'null'           => false,
            ],
            'kondisi' => [
                'type'           => "ENUM('BAIK','RUSAK_RINGAN','RUSAK_SEDANG','RUSAK_BERAT','TIDAK_DITEMUKAN','TIDAK_APLIKABEL')",
                'null'           => false,
                'default'        => 'BAIK',
            ],
            'temuan' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'urgensi' => [
                'type'           => "ENUM('RENDAH','SEDANG','TINGGI','KRITIS')",
                'null'           => true,
            ],
            'rekomendasi' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'tindak_lanjut' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'material_digunakan' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'status_pekerjaan' => [
                'type'           => "ENUM('BELUM','PROSES','SELESAI','DITANGGUHKAN')",
                'null'           => false,
                'default'        => 'BELUM',
            ],
            'urutan' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => false,
                'default'        => 0,
            ],
            'created_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('inspeksi_kubikel_id');
        $this->forge->addKey(['inspeksi_kubikel_id', 'urutan']);
        $this->forge->addForeignKey('inspeksi_kubikel_id', 'inspeksi_kubikel', 'id', 'CASCADE', 'CASCADE', 'fk_ik_checklist_inspeksi');
        $this->forge->createTable('inspeksi_kubikel_checklist');

        // 3. Tabel inspeksi_kubikel_foto (foto untuk inspeksi/checklist/temuan)
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'inspeksi_kubikel_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => false,
            ],
            'checklist_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'kategori' => [
                'type'           => "ENUM('SEBELUM','SESUDAH','TEMUAN','BUKTI','LAINNYA')",
                'null'           => false,
                'default'        => 'LAINNYA',
            ],
            'nama_file' => [
                'type'           => 'VARCHAR',
                'constraint'     => 255,
                'null'           => false,
            ],
            'path_file' => [
                'type'           => 'VARCHAR',
                'constraint'     => 500,
                'null'           => false,
            ],
            'mime_type' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => false,
            ],
            'ukuran' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'deskripsi' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'uploaded_by' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'created_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('inspeksi_kubikel_id');
        $this->forge->addKey('checklist_id');
        $this->forge->addForeignKey('inspeksi_kubikel_id', 'inspeksi_kubikel', 'id', 'CASCADE', 'CASCADE', 'fk_ik_foto_inspeksi');
        $this->forge->addForeignKey('checklist_id', 'inspeksi_kubikel_checklist', 'id', 'CASCADE', 'SET NULL', 'fk_ik_foto_checklist');
        $this->forge->createTable('inspeksi_kubikel_foto');

        // 4. Tabel inspeksi_kubikel_temuan (temuan terpisah dari checklist untuk tracking)
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'inspeksi_kubikel_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => false,
            ],
            'checklist_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'komponen' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => false,
            ],
            'fasa' => [
                'type'           => "ENUM('R','S','T','N','TIDAK_BERFASA')",
                'null'           => false,
                'default'        => 'TIDAK_BERFASA',
            ],
            'posisi' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => true,
            ],
            'deskripsi_temuan' => [
                'type'           => 'TEXT',
                'null'           => false,
            ],
            'urgensi' => [
                'type'           => "ENUM('RENDAH','SEDANG','TINGGI','KRITIS')",
                'null'           => false,
                'default'        => 'SEDANG',
            ],
            'rekomendasi' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'penerima_rekomendasi' => [
                'type'           => "ENUM('ULP','HAR_GTT','BEYOND_KWH','ADMINISTRATOR','LAINNYA')",
                'null'           => false,
                'default'        => 'ULP',
            ],
            'status_temuan' => [
                'type'           => "ENUM('BARU','DITUGASKAN','DIPROSES','SELESAI','DIVERIFIKASI','DITOLAK')",
                'null'           => false,
                'default'        => 'BARU',
            ],
            'assigned_to_user_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'verifikasi_user_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'verifikasi_at' => [
                'type'           => 'DATETIME',
                'null'           => true,
            ],
            'fingerprint' => [
                'type'           => 'VARCHAR',
                'constraint'     => 255,
                'null'           => true,
            ],
            'idempotency_key' => [
                'type'           => 'VARCHAR',
                'constraint'     => 64,
                'null'           => true,
            ],
            'created_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ],
            'deleted_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('inspeksi_kubikel_id');
        $this->forge->addKey('checklist_id');
        $this->forge->addKey('fingerprint');
        $this->forge->addKey('idempotency_key');
        $this->forge->addForeignKey('inspeksi_kubikel_id', 'inspeksi_kubikel', 'id', 'CASCADE', 'CASCADE', 'fk_ik_temuan_inspeksi');
        $this->forge->addForeignKey('checklist_id', 'inspeksi_kubikel_checklist', 'id', 'CASCADE', 'SET NULL', 'fk_ik_temuan_checklist');
        $this->forge->createTable('inspeksi_kubikel_temuan');

        // 5. Tabel inspeksi_kubikel_rekomendasi (tracking rekomendasi ke Beyond KWH dll)
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'temuan_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => false,
            ],
            'penerima' => [
                'type'           => "ENUM('ULP','HAR_GTT','BEYOND_KWH','ADMINISTRATOR','LAINNYA')",
                'null'           => false,
            ],
            'penerima_user_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'deskripsi' => [
                'type'           => 'TEXT',
                'null'           => false,
            ],
            'status' => [
                'type'           => "ENUM('TERKIRIM','DIBACA','DIPROSES','SELESAI','DITOLAK')",
                'null'           => false,
                'default'        => 'TERKIRIM',
            ],
            'waktu_kirim' => [
                'type'           => 'DATETIME',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'waktu_baca' => [
                'type'           => 'DATETIME',
                'null'           => true,
            ],
            'waktu_selesai' => [
                'type'           => 'DATETIME',
                'null'           => true,
            ],
            'catatan_penerima' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'notification_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'created_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('temuan_id');
        $this->forge->addKey('penerima_user_id');
        $this->forge->addKey('notification_id');
        $this->forge->addForeignKey('temuan_id', 'inspeksi_kubikel_temuan', 'id', 'CASCADE', 'CASCADE', 'fk_ik_rekomendasi_temuan');
        $this->forge->createTable('inspeksi_kubikel_rekomendasi');

        // 6. Tabel inspeksi_kubikel_histori (audit trail untuk penutupan, koreksi, dll)
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'inspeksi_kubikel_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => false,
            ],
            'jenis_perubahan' => [
                'type'           => "ENUM('STATUS','TEMUAN','REKOMENDASI','PENUTUPAN','KOREKSI','VERIFIKASI','PENGHAPUSAN','INFORMASI_UTAMA')",
                'null'           => false,
            ],
            'nilai_lama' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'nilai_baru' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'user_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => false,
            ],
            'alasan' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'ip_address' => [
                'type'           => 'VARCHAR',
                'constraint'     => 45,
                'null'           => true,
            ],
            'user_agent' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'created_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('inspeksi_kubikel_id');
        $this->forge->addKey(['inspeksi_kubikel_id', 'jenis_perubahan']);
        $this->forge->addForeignKey('inspeksi_kubikel_id', 'inspeksi_kubikel', 'id', 'CASCADE', 'CASCADE', 'fk_ik_histori_inspeksi');
        $this->forge->createTable('inspeksi_kubikel_histori');

        // 7. Tabel inspeksi_kubikel_material (material yang digunakan pada pekerjaan)
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'inspeksi_kubikel_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => false,
            ],
            'temuan_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'kode_material' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => true,
            ],
            'nama_material' => [
                'type'           => 'VARCHAR',
                'constraint'     => 255,
                'null'           => false,
            ],
            'satuan' => [
                'type'           => 'VARCHAR',
                'constraint'     => 50,
                'null'           => true,
            ],
            'jumlah_rencana' => [
                'type'           => 'DECIMAL(10,2)',
                'null'           => true,
            ],
            'jumlah_digunakan' => [
                'type'           => 'DECIMAL(10,2)',
                'null'           => true,
            ],
            'sisa' => [
                'type'           => 'DECIMAL(10,2)',
                'null'           => true,
            ],
            'status' => [
                'type'           => "ENUM('DIRNCANA','DIPESAN','DITERIMA','DIGUNAKAN','SELESAI')",
                'null'           => false,
                'default'        => 'DIRNCANA',
            ],
            'catatan' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'created_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('inspeksi_kubikel_id');
        $this->forge->addKey('temuan_id');
        $this->forge->addForeignKey('inspeksi_kubikel_id', 'inspeksi_kubikel', 'id', 'CASCADE', 'CASCADE', 'fk_ik_material_inspeksi');
        $this->forge->addForeignKey('temuan_id', 'inspeksi_kubikel_temuan', 'id', 'CASCADE', 'SET NULL', 'fk_ik_material_temuan');
        $this->forge->createTable('inspeksi_kubikel_material');
    }

    public function down()
    {
        // Destructive by design: every Kubikel inspection row is removed.
        $this->forge->dropTable('inspeksi_kubikel_material', true);
        $this->forge->dropTable('inspeksi_kubikel_histori', true);
        $this->forge->dropTable('inspeksi_kubikel_rekomendasi', true);
        $this->forge->dropTable('inspeksi_kubikel_temuan', true);
        $this->forge->dropTable('inspeksi_kubikel_foto', true);
        $this->forge->dropTable('inspeksi_kubikel_checklist', true);
        $this->forge->dropTable('inspeksi_kubikel', true);
    }
}