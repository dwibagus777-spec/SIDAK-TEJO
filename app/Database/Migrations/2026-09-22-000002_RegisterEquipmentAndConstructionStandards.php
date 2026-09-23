<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 2026-09-22-000002: Register Equipment and Construction Standards (Phase 2)
 * 
 * Separates standard equipment catalog from operational asset instances:
 * - Registers PMCB, LBS, LBSM, ASS, AVS, RECLOSER as authoritative construction types
 *   with asset_domain = 'EQUIPMENT', appropriate family and active approval status.
 * - Links existing equipment assets to their standard construction_type_id
 *   without overwriting their operational instance names in assets.nama_asset.
 */
class RegisterEquipmentAndConstructionStandards extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('construction_types')) {
            return;
        }

        $standards = [
            [
                'code'                 => 'PMCB',
                'name'                 => 'Pole Mounted Circuit Breaker (PMCB)',
                'construction_code'    => 'PMCB',
                'construction_name'    => 'Pole Mounted Circuit Breaker (PMCB)',
                'construction_family'  => 'PROTECTION',
                'network_type'         => 'JTM',
                'asset_category'       => 'SWITCH',
                'asset_domain'         => 'EQUIPMENT',
                'approval_status'      => 'ACTIVE',
                'voltage_level'        => '20kV',
                'description'          => 'Peralatan Pemutus Beban Otomatis Pasangan Luar Tiang (PMCB / Vacuum Circuit Breaker)',
                'standard_reference'   => 'SPLN D3.023: 2012 / Buku 5 PLN',
                'is_active'            => 1,
                'sort_order'           => 40,
            ],
            [
                'code'                 => 'LBS',
                'name'                 => 'Load Break Switch (LBS) Manual / Gas Insulated',
                'construction_code'    => 'LBS',
                'construction_name'    => 'Load Break Switch (LBS) Manual / Gas Insulated',
                'construction_family'  => 'SWITCHING',
                'network_type'         => 'JTM',
                'asset_category'       => 'SWITCH',
                'asset_domain'         => 'EQUIPMENT',
                'approval_status'      => 'ACTIVE',
                'voltage_level'        => '20kV',
                'description'          => 'Sakelar Pemutus Beban Seksi Manual 20 kV (LBS SF6/Gas Insulated)',
                'standard_reference'   => 'SPLN D3.024: 2012 / Buku 5 PLN',
                'is_active'            => 1,
                'sort_order'           => 41,
            ],
            [
                'code'                 => 'LBSM',
                'name'                 => 'Load Break Switch Motorized (LBS Motorized)',
                'construction_code'    => 'LBSM',
                'construction_name'    => 'Load Break Switch Motorized (LBS Motorized)',
                'construction_family'  => 'SWITCHING',
                'network_type'         => 'JTM',
                'asset_category'       => 'SWITCH',
                'asset_domain'         => 'EQUIPMENT',
                'approval_status'      => 'ACTIVE',
                'voltage_level'        => '20kV',
                'description'          => 'Sakelar Pemutus Beban Seksi Berpenggerak Motor / Remote RTU (LBS Motorized)',
                'standard_reference'   => 'SPLN D3.024: 2012 / Buku 5 PLN',
                'is_active'            => 1,
                'sort_order'           => 42,
            ],
            [
                'code'                 => 'ASS',
                'name'                 => 'Automatic Sectionalizing Switch (ASS)',
                'construction_code'    => 'ASS',
                'construction_name'    => 'Automatic Sectionalizing Switch (ASS)',
                'construction_family'  => 'SWITCHING',
                'network_type'         => 'JTM',
                'asset_category'       => 'SWITCH',
                'asset_domain'         => 'EQUIPMENT',
                'approval_status'      => 'ACTIVE',
                'voltage_level'        => '20kV',
                'description'          => 'Sakelar Pemutus Beban Otomatis Pelanggan Khusus / Sectionalizer (ASS 20kV)',
                'standard_reference'   => 'SPLN D3.024: 2012 / Buku 5 PLN',
                'is_active'            => 1,
                'sort_order'           => 43,
            ],
            [
                'code'                 => 'AVS',
                'name'                 => 'Automatic Voltage Switch / Sectionalizer (AVS)',
                'construction_code'    => 'AVS',
                'construction_name'    => 'Automatic Voltage Switch / Sectionalizer (AVS)',
                'construction_family'  => 'PROTECTION',
                'network_type'         => 'JTM',
                'asset_category'       => 'SWITCH',
                'asset_domain'         => 'EQUIPMENT',
                'approval_status'      => 'ACTIVE',
                'voltage_level'        => '20kV',
                'description'          => 'Sakelar Pemutus Otomatis Berbasis Tegangan Hilang (AVS 20kV)',
                'standard_reference'   => 'SPLN D3.024: 2012 / Buku 5 PLN',
                'is_active'            => 1,
                'sort_order'           => 44,
            ],
            [
                'code'                 => 'RECLOSER',
                'name'                 => 'Automatic Circuit Recloser (ACR / Recloser 20 kV)',
                'construction_code'    => 'RECLOSER',
                'construction_name'    => 'Automatic Circuit Recloser (ACR / Recloser 20 kV)',
                'construction_family'  => 'PROTECTION',
                'network_type'         => 'JTM',
                'asset_category'       => 'SWITCH',
                'asset_domain'         => 'EQUIPMENT',
                'approval_status'      => 'ACTIVE',
                'voltage_level'        => '20kV',
                'description'          => 'Peralatan Penutup Balik Otomatis JTM 20 kV (Recloser Elektronik / Mikroprosesor)',
                'standard_reference'   => 'SPLN D3.023: 2012 / Buku 5 PLN',
                'is_active'            => 1,
                'sort_order'           => 45,
            ],
        ];

        $stdMap = [];
        $now = date('Y-m-d H:i:s');

        foreach ($standards as $s) {
            $existing = $this->db->table('construction_types')
                ->where('construction_code', $s['construction_code'])
                ->orWhere('code', $s['code'])
                ->get()
                ->getRowArray();

            if ($existing) {
                $id = (int)$existing['id'];
                $updateData = [
                    'code'                => $s['code'],
                    'name'                => $s['name'],
                    'construction_code'   => $s['construction_code'],
                    'construction_name'   => $s['construction_name'],
                    'construction_family' => $s['construction_family'],
                    'asset_domain'        => $s['asset_domain'],
                    'approval_status'     => $s['approval_status'],
                    'is_active'           => 1,
                    'updated_at'          => $now,
                ];
                $this->db->table('construction_types')->where('id', $id)->update($updateData);
                $stdMap[$s['code']] = $id;
            } else {
                $insertData = array_merge($s, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->db->table('construction_types')->insert($insertData);
                $stdMap[$s['code']] = (int)$this->db->insertID();
            }
        }

        // Link existing assets to standard construction_type_id
        if ($this->db->tableExists('assets') && $this->db->fieldExists('construction_type_id', 'assets')) {
            // Prioritize LBSM before LBS to avoid substring collision
            $linkingOrder = ['LBSM', 'PMCB', 'LBS', 'ASS', 'AVS', 'RECLOSER'];

            foreach ($linkingOrder as $eqCode) {
                if (empty($stdMap[$eqCode])) {
                    continue;
                }
                $cId = $stdMap[$eqCode];

                // Check patterns for equipment name in nama_asset or kode_asset
                $this->db->query("
                    UPDATE `assets` 
                    SET `construction_type_id` = {$cId}
                    WHERE (`nama_asset` LIKE '{$eqCode}%' 
                       OR `nama_asset` LIKE '% {$eqCode} %' 
                       OR `nama_asset` LIKE '% {$eqCode}'
                       OR `kode_asset` LIKE '{$eqCode}%')
                      AND (`construction_type_id` IS NULL OR `construction_type_id` = 0)
                      AND `deleted_at` IS NULL
                ");
            }
        }
    }

    public function down()
    {
        // Reversible down migration
        if ($this->db->tableExists('construction_types')) {
            $codes = ['PMCB', 'LBS', 'LBSM', 'ASS', 'AVS', 'RECLOSER'];
            $this->db->table('construction_types')
                ->whereIn('construction_code', $codes)
                ->where('asset_domain', 'EQUIPMENT')
                ->delete();
        }
    }
}
