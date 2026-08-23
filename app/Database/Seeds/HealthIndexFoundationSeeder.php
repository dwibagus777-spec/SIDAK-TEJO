<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class HealthIndexFoundationSeeder extends Seeder
{
    public function run()
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $components = [
            [
                'code'          => 'AGE',
                'name'          => 'Umur / Masa Pakai Aset',
                'description'   => 'Pengurangan poin berdasarkan usia pakai instalasi aset sejak tahun instalasi.',
                'display_order' => 1,
                'icon'          => 'fas fa-hourglass-half',
                'color'         => '#6B7280',
                'is_active'     => 1,
            ],
            [
                'code'          => 'ACTIVE_FINDINGS',
                'name'          => 'Temuan Aktif Belum Selesai',
                'description'   => 'Pengurangan poin berdasarkan akumulasi jumlah dan tingkat keparahan temuan aktif.',
                'display_order' => 2,
                'icon'          => 'fas fa-exclamation-triangle',
                'color'         => '#EF4444',
                'is_active'     => 1,
            ],
            [
                'code'          => 'INSPECTION',
                'name'          => 'Jadwal Inspeksi Terlewat',
                'description'   => 'Pengurangan poin akibat keterlambatan inspeksi rutin dari interval yang ditentukan.',
                'display_order' => 3,
                'icon'          => 'fas fa-calendar-times',
                'color'         => '#F59E0B',
                'is_active'     => 1,
            ],
            [
                'code'          => 'VEGETATION',
                'name'          => 'Risiko Vegetasi Jaringan',
                'description'   => 'Pengurangan poin berdasarkan jarak aman pohon/vegetasi terdekat pada segmen jaringan.',
                'display_order' => 4,
                'icon'          => 'fas fa-tree',
                'color'         => '#10B981',
                'is_active'     => 1,
            ],
            [
                'code'          => 'MATERIAL_ANOMALY',
                'name'          => 'Anomali Material & Sparepart',
                'description'   => 'Pengurangan poin akibat indikasi kerusakan material terpasang atau komponen pengganti.',
                'display_order' => 5,
                'icon'          => 'fas fa-cogs',
                'color'         => '#8B5CF6',
                'is_active'     => 1,
            ],
            [
                'code'          => 'THERMOVISION',
                'name'          => 'Pengukuran Thermovision Hotspot',
                'description'   => 'Pengurangan poin akibat anomali suhu berlebih (hotspot) pada komponen aset.',
                'display_order' => 6,
                'icon'          => 'fas fa-temperature-high',
                'color'         => '#DC2626',
                'is_active'     => 1,
            ],
            [
                'code'          => 'CONSTRUCTION',
                'name'          => 'Faktor Jenis Konstruksi',
                'description'   => 'Penyesuaian karakter dasar ketahanan fisik jenis konstruksi aset.',
                'display_order' => 7,
                'icon'          => 'fas fa-hard-hat',
                'color'         => '#3B82F6',
                'is_active'     => 1,
            ],
        ];

        foreach ($components as $item) {
            $existing = $db->table('hi_components')->where('code', $item['code'])->get()->getRow();
            if ($existing) {
                $db->table('hi_components')->where('code', $item['code'])->update(array_merge($item, ['updated_at' => $now]));
                $componentId = (int)$existing->id;
            } else {
                $db->table('hi_components')->insert(array_merge($item, ['created_at' => $now, 'updated_at' => $now]));
                $componentId = (int)$db->insertID();
            }

            // Populate Default Rule Entry for Component
            $existingRule = $db->table('hi_rules')->where('component_id', $componentId)->where('construction_type_id', null)->get()->getRow();
            $ruleData = [
                'component_id'         => $componentId,
                'construction_type_id' => null,
                'weight'               => 1.00,
                'min_deduction'        => 0.00,
                'max_deduction'        => $item['code'] === 'ACTIVE_FINDINGS' ? 30.00 : 25.00,
                'config_json'          => json_encode(['engine_version' => '1.0', 'status' => in_array($item['code'], ['AGE', 'ACTIVE_FINDINGS', 'INSPECTION']) ? 'ACTIVE' : 'PLACEHOLDER']),
                'priority'             => 100,
                'rule_version'         => '1.0',
                'is_active'            => 1,
            ];

            if ($existingRule) {
                $db->table('hi_rules')->where('id', $existingRule->id)->update(array_merge($ruleData, ['updated_at' => $now]));
            } else {
                $db->table('hi_rules')->insert(array_merge($ruleData, ['created_at' => $now, 'updated_at' => $now]));
            }
        }
    }
}
