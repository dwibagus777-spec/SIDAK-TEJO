<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        // 1. Seed ULPs
        $ulps = [
            [
                'kode_ulp' => '51301',
                'nama_ulp' => 'ULP Sidoarjo Kota',
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'kode_ulp' => '51302',
                'nama_ulp' => 'ULP Krian',
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'kode_ulp' => '51303',
                'nama_ulp' => 'ULP Porong',
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'kode_ulp' => '51304',
                'nama_ulp' => 'ULP Waru',
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
        
        $this->db->table('ulps')->insertBatch($ulps);

        // Get ULP IDs
        $db = \Config\Database::connect();
        $ulpSdaId = $db->table('ulps')->where('kode_ulp', '51301')->get()->getRow()->id;
        $ulpKrianId = $db->table('ulps')->where('kode_ulp', '51302')->get()->getRow()->id;
        $ulpPorongId = $db->table('ulps')->where('kode_ulp', '51303')->get()->getRow()->id;
        $ulpWaruId = $db->table('ulps')->where('kode_ulp', '51304')->get()->getRow()->id;

        // 2. Seed Penyulangs
        $penyulangs = [
            [
                'id_unik_penyulang' => 'P_GJM_01',
                'kode_penyulang' => 'GJM01',
                'nama_penyulang' => 'Penyulang Gajah Mada',
                'ulp_id' => $ulpSdaId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id_unik_penyulang' => 'P_MJP_02',
                'kode_penyulang' => 'MJP02',
                'nama_penyulang' => 'Penyulang Majapahit',
                'ulp_id' => $ulpSdaId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id_unik_penyulang' => 'P_KR_01',
                'kode_penyulang' => 'KRN01',
                'nama_penyulang' => 'Penyulang Krian Baru',
                'ulp_id' => $ulpKrianId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id_unik_penyulang' => 'P_PRG_01',
                'kode_penyulang' => 'PRG01',
                'nama_penyulang' => 'Penyulang Porong Asri',
                'ulp_id' => $ulpPorongId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('penyulang')->insertBatch($penyulangs);

        // Get Penyulang IDs
        $penyulangGjmId = $db->table('penyulang')->where('id_unik_penyulang', 'P_GJM_01')->get()->getRow()->id;
        $penyulangMjpId = $db->table('penyulang')->where('id_unik_penyulang', 'P_MJP_02')->get()->getRow()->id;
        $penyulangKrnId = $db->table('penyulang')->where('id_unik_penyulang', 'P_KR_01')->get()->getRow()->id;
        $penyulangPrgId = $db->table('penyulang')->where('id_unik_penyulang', 'P_PRG_01')->get()->getRow()->id;

        // 3. Seed Sections
        $sections = [
            [
                'penyulang_id' => $penyulangGjmId,
                'nama_section' => 'Section Gardu GJM01-A1',
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'penyulang_id' => $penyulangGjmId,
                'nama_section' => 'Section Gardu GJM01-A2',
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'penyulang_id' => $penyulangMjpId,
                'nama_section' => 'Section Gardu MJP02-B1',
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'penyulang_id' => $penyulangKrnId,
                'nama_section' => 'Section Gardu KRN01-C1',
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'penyulang_id' => $penyulangPrgId,
                'nama_section' => 'Section Gardu PRG01-D1',
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('sections')->insertBatch($sections);

        // 4. Seed Users (RBAC)
        $users = [
            // --- SUPER ADMIN ---
            [
                'nama' => 'Administrator',
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'administrator',
                'ulp_id' => null,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            
            // --- TIM PDKB (CROSS-ULP) ---
            [
                'nama' => 'Tim PDKB Sidoarjo',
                'username' => 'pdkb_sda',
                'password' => password_hash('pdkb123', PASSWORD_DEFAULT),
                'role' => 'pdkb',
                'ulp_id' => null,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],

            // --- TIM HAR CRANE (CROSS-ULP) ---
            [
                'nama' => 'HAR Crane Sidoarjo',
                'username' => 'harcrane_sda',
                'password' => password_hash('harcrane123', PASSWORD_DEFAULT),
                'role' => 'har_crane',
                'ulp_id' => null,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],

            // ================= ULP SIDOARJO KOTA =================
            [
                'nama' => 'Admin ULP Sidoarjo Kota',
                'username' => 'admin_ulp_sda',
                'password' => password_hash('adminulp123', PASSWORD_DEFAULT),
                'role' => 'admin_ulp',
                'ulp_id' => $ulpSdaId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'Inspeksi Sidoarjo Kota',
                'username' => 'inspeksi_sda',
                'password' => password_hash('inspeksi123', PASSWORD_DEFAULT),
                'role' => 'inspeksi',
                'ulp_id' => $ulpSdaId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'HAR Konstruksi Sidoarjo Kota',
                'username' => 'harkons_sda',
                'password' => password_hash('harkons123', PASSWORD_DEFAULT),
                'role' => 'har_konstruksi',
                'ulp_id' => $ulpSdaId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'HAR ROW Sidoarjo Kota',
                'username' => 'harrow_sda',
                'password' => password_hash('harrow123', PASSWORD_DEFAULT),
                'role' => 'har_row',
                'ulp_id' => $ulpSdaId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'Yantek Sidoarjo Kota',
                'username' => 'yantek_sda',
                'password' => password_hash('yantek123', PASSWORD_DEFAULT),
                'role' => 'yantek',
                'ulp_id' => $ulpSdaId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],

            // ================= ULP KRIAN =================
            [
                'nama' => 'Admin ULP Krian',
                'username' => 'admin_ulp_krn',
                'password' => password_hash('adminulp123', PASSWORD_DEFAULT),
                'role' => 'admin_ulp',
                'ulp_id' => $ulpKrianId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'Inspeksi Krian',
                'username' => 'inspeksi_krn',
                'password' => password_hash('inspeksi123', PASSWORD_DEFAULT),
                'role' => 'inspeksi',
                'ulp_id' => $ulpKrianId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'HAR Konstruksi Krian',
                'username' => 'harkons_krn',
                'password' => password_hash('harkons123', PASSWORD_DEFAULT),
                'role' => 'har_konstruksi',
                'ulp_id' => $ulpKrianId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'HAR ROW Krian',
                'username' => 'harrow_krn',
                'password' => password_hash('harrow123', PASSWORD_DEFAULT),
                'role' => 'har_row',
                'ulp_id' => $ulpKrianId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'Yantek Krian',
                'username' => 'yantek_krn',
                'password' => password_hash('yantek123', PASSWORD_DEFAULT),
                'role' => 'yantek',
                'ulp_id' => $ulpKrianId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],

            // ================= ULP PORONG =================
            [
                'nama' => 'Admin ULP Porong',
                'username' => 'admin_ulp_prg',
                'password' => password_hash('adminulp123', PASSWORD_DEFAULT),
                'role' => 'admin_ulp',
                'ulp_id' => $ulpPorongId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'Inspeksi Porong',
                'username' => 'inspeksi_prg',
                'password' => password_hash('inspeksi123', PASSWORD_DEFAULT),
                'role' => 'inspeksi',
                'ulp_id' => $ulpPorongId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'HAR Konstruksi Porong',
                'username' => 'harkons_prg',
                'password' => password_hash('harkons123', PASSWORD_DEFAULT),
                'role' => 'har_konstruksi',
                'ulp_id' => $ulpPorongId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'HAR ROW Porong',
                'username' => 'harrow_prg',
                'password' => password_hash('harrow123', PASSWORD_DEFAULT),
                'role' => 'har_row',
                'ulp_id' => $ulpPorongId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama' => 'Yantek Porong',
                'username' => 'yantek_prg',
                'password' => password_hash('yantek123', PASSWORD_DEFAULT),
                'role' => 'yantek',
                'ulp_id' => $ulpPorongId,
                'status' => 'AKTIF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('users')->insertBatch($users);
    }
}
