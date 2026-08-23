<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConductorAttributesAndMasterConductors extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        
        // 1. Add conductor columns to asset_relationships if not exists
        if ($db->tableExists('asset_relationships')) {
            $fieldsToAdd = [];

            if (!$db->fieldExists('conductor_type', 'asset_relationships')) {
                $fieldsToAdd['conductor_type'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'AAAC',
                    'after'      => 'distance_meters',
                ];
            }
            if (!$db->fieldExists('conductor_size', 'asset_relationships')) {
                $fieldsToAdd['conductor_size'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => '150 mm²',
                    'after'      => 'conductor_type',
                ];
            }
            if (!$db->fieldExists('conductor_material', 'asset_relationships')) {
                $fieldsToAdd['conductor_material'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'ALUMINUM',
                    'after'      => 'conductor_size',
                ];
            }
            if (!$db->fieldExists('installation_type', 'asset_relationships')) {
                $fieldsToAdd['installation_type'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'OVERHEAD',
                    'after'      => 'conductor_material',
                ];
            }
            if (!$db->fieldExists('circuit_config', 'asset_relationships')) {
                $fieldsToAdd['circuit_config'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => '3_PHASE',
                    'after'      => 'installation_type',
                ];
            }

            if (!empty($fieldsToAdd)) {
                $this->forge->addColumn('asset_relationships', $fieldsToAdd);
            }
        }

        // 2. Create master_conductors table
        if (!$db->tableExists('master_conductors')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'conductor_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'unique'     => true,
                ],
                'conductor_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'conductor_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'material' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'ALUMINUM',
                ],
                'size_mm2' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 150,
                ],
                'voltage_class' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => '20 kV',
                ],
                'installation_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'OVERHEAD',
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'AKTIF',
                ],
                'sort_order' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->createTable('master_conductors', true);

            // Seed master conductors
            $standardConductors = [
                // AAAC
                ['AAAC-35',  'AAAC', 'AAAC 35 mm²',  'ALUMINUM_ALLOY', 35,  '20 kV', 'OVERHEAD', 1],
                ['AAAC-50',  'AAAC', 'AAAC 50 mm²',  'ALUMINUM_ALLOY', 50,  '20 kV', 'OVERHEAD', 2],
                ['AAAC-70',  'AAAC', 'AAAC 70 mm²',  'ALUMINUM_ALLOY', 70,  '20 kV', 'OVERHEAD', 3],
                ['AAAC-95',  'AAAC', 'AAAC 95 mm²',  'ALUMINUM_ALLOY', 95,  '20 kV', 'OVERHEAD', 4],
                ['AAAC-120', 'AAAC', 'AAAC 120 mm²', 'ALUMINUM_ALLOY', 120, '20 kV', 'OVERHEAD', 5],
                ['AAAC-150', 'AAAC', 'AAAC 150 mm²', 'ALUMINUM_ALLOY', 150, '20 kV', 'OVERHEAD', 6],
                ['AAAC-185', 'AAAC', 'AAAC 185 mm²', 'ALUMINUM_ALLOY', 185, '20 kV', 'OVERHEAD', 7],
                ['AAAC-240', 'AAAC', 'AAAC 240 mm²', 'ALUMINUM_ALLOY', 240, '20 kV', 'OVERHEAD', 8],
                ['AAAC-300', 'AAAC', 'AAAC 300 mm²', 'ALUMINUM_ALLOY', 300, '20 kV', 'OVERHEAD', 9],

                // A3CS
                ['A3CS-50',  'A3CS', 'A3CS 50 mm² (Shielded)',  'ALUMINUM_ALLOY', 50,  '20 kV', 'OVERHEAD_INSULATED', 10],
                ['A3CS-70',  'A3CS', 'A3CS 70 mm² (Shielded)',  'ALUMINUM_ALLOY', 70,  '20 kV', 'OVERHEAD_INSULATED', 11],
                ['A3CS-95',  'A3CS', 'A3CS 95 mm² (Shielded)',  'ALUMINUM_ALLOY', 95,  '20 kV', 'OVERHEAD_INSULATED', 12],
                ['A3CS-150', 'A3CS', 'A3CS 150 mm² (Shielded)', 'ALUMINUM_ALLOY', 150, '20 kV', 'OVERHEAD_INSULATED', 13],
                ['A3CS-240', 'A3CS', 'A3CS 240 mm² (Shielded)', 'ALUMINUM_ALLOY', 240, '20 kV', 'OVERHEAD_INSULATED', 14],

                // A3C
                ['A3C-35',  'A3C', 'A3C 35 mm²',  'ALUMINUM_ALLOY', 35,  '20 kV', 'OVERHEAD', 15],
                ['A3C-50',  'A3C', 'A3C 50 mm²',  'ALUMINUM_ALLOY', 50,  '20 kV', 'OVERHEAD', 16],
                ['A3C-70',  'A3C', 'A3C 70 mm²',  'ALUMINUM_ALLOY', 70,  '20 kV', 'OVERHEAD', 17],
                ['A3C-150', 'A3C', 'A3C 150 mm²', 'ALUMINUM_ALLOY', 150, '20 kV', 'OVERHEAD', 18],
                ['A3C-240', 'A3C', 'A3C 240 mm²', 'ALUMINUM_ALLOY', 240, '20 kV', 'OVERHEAD', 19],

                // ACSR
                ['ACSR-70',  'ACSR', 'ACSR 70 mm²',  'ALUMINUM_STEEL', 70,  '20 kV', 'OVERHEAD', 20],
                ['ACSR-150', 'ACSR', 'ACSR 150 mm²', 'ALUMINUM_STEEL', 150, '20 kV', 'OVERHEAD', 21],
                ['ACSR-240', 'ACSR', 'ACSR 240 mm²', 'ALUMINUM_STEEL', 240, '20 kV', 'OVERHEAD', 22],

                // MV-TIC
                ['MV-TIC-70',  'MV-TIC', 'MV-TIC 3x70 mm²',   'ALUMINUM_INSULATED', 70,  '20 kV', 'OVERHEAD_BUNDLE', 25],
                ['MV-TIC-95',  'MV-TIC', 'MV-TIC 3x95 mm²',   'ALUMINUM_INSULATED', 95,  '20 kV', 'OVERHEAD_BUNDLE', 26],
                ['MV-TIC-150', 'MV-TIC', 'MV-TIC 3x150 mm²',  'ALUMINUM_INSULATED', 150, '20 kV', 'OVERHEAD_BUNDLE', 27],
                ['MV-TIC-240', 'MV-TIC', 'MV-TIC 3x240 mm²',  'ALUMINUM_INSULATED', 240, '20 kV', 'OVERHEAD_BUNDLE', 28],

                // XLPE
                ['XLPE-70',  'XLPE', 'XLPE 3x70 mm² (Underground)',  'COPPER_XLPE', 70,  '20 kV', 'UNDERGROUND', 30],
                ['XLPE-95',  'XLPE', 'XLPE 3x95 mm² (Underground)',  'COPPER_XLPE', 95,  '20 kV', 'UNDERGROUND', 31],
                ['XLPE-150', 'XLPE', 'XLPE 3x150 mm² (Underground)', 'COPPER_XLPE', 150, '20 kV', 'UNDERGROUND', 32],
                ['XLPE-240', 'XLPE', 'XLPE 3x240 mm² (Underground)', 'COPPER_XLPE', 240, '20 kV', 'UNDERGROUND', 33],
                ['XLPE-300', 'XLPE', 'XLPE 3x300 mm² (Underground)', 'COPPER_XLPE', 300, '20 kV', 'UNDERGROUND', 34],
            ];

            $now = date('Y-m-d H:i:s');
            foreach ($standardConductors as $c) {
                $db->table('master_conductors')->insert([
                    'conductor_code'    => $c[0],
                    'conductor_type'    => $c[1],
                    'conductor_name'    => $c[2],
                    'material'          => $c[3],
                    'size_mm2'          => $c[4],
                    'voltage_class'     => $c[5],
                    'installation_type' => $c[6],
                    'status'            => 'AKTIF',
                    'sort_order'        => $c[7],
                    'created_at'        => $now,
                ]);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('master_conductors')) {
            $this->forge->dropTable('master_conductors', true);
        }
    }
}
