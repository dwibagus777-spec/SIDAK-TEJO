<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Services\ConstructionIntelligenceService;
use App\Services\InspectionMeasurementService;
use App\Services\FeederHealthIntelligenceService;

class ConstructionIntelligenceSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        $ciService = new ConstructionIntelligenceService($db);
        $inspService = new InspectionMeasurementService($db);
        $fhiService = new FeederHealthIntelligenceService($db);

        // =========================================================================
        // 1. SEED CANONICAL MASTER MATERIALS & ALIASES (MATERIAL JTM & KONDUKTOR ACCESORIS)
        // =========================================================================
        $materials = [
            [
                'material_code'     => 'MAT-ISO-PIN-20KV',
                'nama_material'     => 'Pin Post Insulator 20 kV Porcelain/Polymer',
                'nama_lapangan'     => 'PIN',
                'satuan'            => 'SET',
                'material_domain'   => 'JTM',
                'material_category' => 'INSULATOR',
                'specification'     => '20 kV Post Insulator, Creepage 600mm',
                'source_sheet'      => 'MATERIAL JTM',
                'source_row'        => 2,
                'aliases'           => ['PIN', 'PIN POST', 'ISOLATOR PIN', 'ISOLATOR TUMPU'],
            ],
            [
                'material_code'     => 'MAT-PROT-LA-24KV',
                'nama_material'     => 'Polymer Lightning Arrester 24 kV 10 kA',
                'nama_lapangan'     => 'LA',
                'satuan'            => 'SET',
                'material_domain'   => 'JTM',
                'material_category' => 'PROTECTION',
                'specification'     => '24 kV, 10 kA Class 1, Polymer Housing',
                'source_sheet'      => 'MATERIAL JTM',
                'source_row'        => 3,
                'aliases'           => ['LA', 'LIGHTNING ARRESTER', 'ARRESTER', 'PENANGKAL PETIR'],
            ],
            [
                'material_code'     => 'MAT-PROT-FCO-24KV',
                'nama_material'     => 'Fuse Cut Out Switch 24 kV 100A',
                'nama_lapangan'     => 'FCO',
                'satuan'            => 'SET',
                'material_domain'   => 'JTM',
                'material_category' => 'SWITCHING',
                'specification'     => '24 kV, 100A, Polymer Insulator + Fuse Link',
                'source_sheet'      => 'MATERIAL JTM',
                'source_row'        => 4,
                'aliases'           => ['FCO', 'FUSE CUT OUT', 'CUT OUT', 'SEKRING JTM'],
            ],
            [
                'material_code'     => 'MAT-ISO-HANG-20KV',
                'nama_material'     => 'Strain Insulator 20 kV Lengkap (SIR)',
                'nama_lapangan'     => 'HANG',
                'satuan'            => 'SET',
                'material_domain'   => 'JTM',
                'material_category' => 'INSULATOR',
                'specification'     => '20 kV Strain Set (Suspension + Hardware Fitting)',
                'source_sheet'      => 'MATERIAL JTM',
                'source_row'        => 5,
                'aliases'           => ['HANG', 'STRAIN INSULATOR', 'ISOLATOR TARIK', 'SUSPENSION SET'],
            ],
            [
                'material_code'     => 'MAT-POLE-BETON-12M',
                'nama_material'     => 'Tiang Beton 12 Meter / 350 daN',
                'nama_lapangan'     => 'TIANG',
                'satuan'            => 'BTG',
                'material_domain'   => 'JTM',
                'material_category' => 'POLE',
                'specification'     => 'Prestressed Concrete Pole 12m/350daN',
                'source_sheet'      => 'MATERIAL JTM',
                'source_row'        => 6,
                'aliases'           => ['TIANG', 'TIANG BETON', 'POLE', 'TIANG 12M'],
            ],
            [
                'material_code'     => 'MAT-HDW-TRAVERS-UNP',
                'nama_material'     => 'Travers Cross Arm UNP 2000mm Galvanized',
                'nama_lapangan'     => 'CROSS ARM',
                'satuan'            => 'BTG',
                'material_domain'   => 'JTM',
                'material_category' => 'HARDWARE',
                'specification'     => 'UNP 100x50x5x2000mm Hot Dip Galvanized',
                'source_sheet'      => 'MATERIAL JTM',
                'source_row'        => 7,
                'aliases'           => ['CROSS ARM', 'TRAVERS', 'TRAVES', 'ARM UNP'],
            ],
            // Conductors from Sheet 3
            [
                'material_code'     => 'MAT-COND-AAAC-70',
                'nama_material'     => 'Konduktor AAAC 70 mm²',
                'nama_lapangan'     => 'AAAC 70',
                'satuan'            => 'METER',
                'material_domain'   => 'JTM',
                'material_category' => 'CONDUCTOR',
                'specification'     => 'All Aluminum Alloy Conductor 70 mm²',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 2,
                'aliases'           => ['AAAC 70', 'AAAC70', 'KABEL AAAC 70'],
            ],
            [
                'material_code'     => 'MAT-COND-AAAC-150',
                'nama_material'     => 'Konduktor AAAC 150 mm²',
                'nama_lapangan'     => 'AAAC 150',
                'satuan'            => 'METER',
                'material_domain'   => 'JTM',
                'material_category' => 'CONDUCTOR',
                'specification'     => 'All Aluminum Alloy Conductor 150 mm²',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 3,
                'aliases'           => ['AAAC 150', 'AAAC150', 'KABEL AAAC 150'],
            ],
            [
                'material_code'     => 'MAT-COND-AAAC-240',
                'nama_material'     => 'Konduktor AAAC 240 mm²',
                'nama_lapangan'     => 'AAAC 240',
                'satuan'            => 'METER',
                'material_domain'   => 'JTM',
                'material_category' => 'CONDUCTOR',
                'specification'     => 'All Aluminum Alloy Conductor 240 mm²',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 4,
                'aliases'           => ['AAAC 240', 'AAAC240', 'KABEL AAAC 240'],
            ],
            [
                'material_code'     => 'MAT-COND-AAACS-150',
                'nama_material'     => 'Konduktor AAAC-S 150 mm² (Covered/Shielded)',
                'nama_lapangan'     => 'AAACS 150',
                'satuan'            => 'METER',
                'material_domain'   => 'JTM',
                'material_category' => 'CONDUCTOR',
                'specification'     => 'Crosslinked Polyethylene Covered Aluminum Conductor 150 mm²',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 5,
                'aliases'           => ['AAACS 150', 'AAAC-S 150', 'A3CS 150', 'AAACS150'],
            ],
            [
                'material_code'     => 'MAT-COND-AAACS-240',
                'nama_material'     => 'Konduktor AAAC-S 240 mm² (Covered/Shielded)',
                'nama_lapangan'     => 'AAACS 240',
                'satuan'            => 'METER',
                'material_domain'   => 'JTM',
                'material_category' => 'CONDUCTOR',
                'specification'     => 'Crosslinked Polyethylene Covered Aluminum Conductor 240 mm²',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 6,
                'aliases'           => ['AAACS 240', 'AAAC-S 240', 'A3CS 240', 'AAACS240'],
            ],
            [
                'material_code'     => 'MAT-COND-XLPE-150',
                'nama_material'     => 'Kabel Tanah XLPE 20 kV 3x150 mm²',
                'nama_lapangan'     => 'XLPE 150',
                'satuan'            => 'METER',
                'material_domain'   => 'JTM',
                'material_category' => 'CONDUCTOR',
                'specification'     => 'Underground Cable 20 kV XLPE Copper/Alu 3x150mm²',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 7,
                'aliases'           => ['XLPE 150', 'KABEL TANAH 150', 'XLPE150', 'SKTM 150'],
            ],
            [
                'material_code'     => 'MAT-COND-XLPE-240',
                'nama_material'     => 'Kabel Tanah XLPE 20 kV 3x240 mm²',
                'nama_lapangan'     => 'XLPE 240',
                'satuan'            => 'METER',
                'material_domain'   => 'JTM',
                'material_category' => 'CONDUCTOR',
                'specification'     => 'Underground Cable 20 kV XLPE Copper/Alu 3x240mm²',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 8,
                'aliases'           => ['XLPE 240', 'KABEL TANAH 240', 'XLPE240', 'SKTM 240'],
            ],
            // Accessories from Sheet 3
            [
                'material_code'     => 'MAT-ACC-GSW',
                'nama_material'     => 'Ground Steel Wire (GSW) 22-38 mm²',
                'nama_lapangan'     => 'GSW',
                'satuan'            => 'METER',
                'material_domain'   => 'ACCESSORY',
                'material_category' => 'PROTECTION',
                'specification'     => 'Galvanized Steel Overhead Ground Wire',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 9,
                'aliases'           => ['GSW', 'GROUND STEEL WIRE', 'KAWAT GSW', 'GROUND WIRE'],
            ],
            [
                'material_code'     => 'MAT-ACC-EGLA',
                'nama_material'     => 'Externally Gapped Line Arrester (EGLA)',
                'nama_lapangan'     => 'EGLA',
                'satuan'            => 'SET',
                'material_domain'   => 'ACCESSORY',
                'material_category' => 'PROTECTION',
                'specification'     => '20 kV Transmission/Distribution Line Arrester with External Gap',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 10,
                'aliases'           => ['EGLA', 'ARRESTER EGLA', 'LINE ARRESTER'],
            ],
            [
                'material_code'     => 'MAT-ACC-CLD',
                'nama_material'     => 'Current Limiting Device (CLD)',
                'nama_lapangan'     => 'CLD',
                'satuan'            => 'SET',
                'material_domain'   => 'ACCESSORY',
                'material_category' => 'PROTECTION',
                'specification'     => 'Medium Voltage Current Limiting Device',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 11,
                'aliases'           => ['CLD', 'DEVICE CLD'],
            ],
            [
                'material_code'     => 'MAT-ACC-MCA',
                'nama_material'     => 'Medium Voltage Covered Conductor Accessory (MCA)',
                'nama_lapangan'     => 'MCA',
                'satuan'            => 'SET',
                'material_domain'   => 'ACCESSORY',
                'material_category' => 'HARDWARE',
                'specification'     => 'Protective Accessories for Covered Conductors',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 12,
                'aliases'           => ['MCA', 'AKSESORIS MCA'],
            ],
            [
                'material_code'     => 'MAT-ACC-ANIMAL-GUARD',
                'nama_material'     => 'Penghalang Binatang / Animal Guard',
                'nama_lapangan'     => 'PENGHALANG BINATANG',
                'satuan'            => 'PCS',
                'material_domain'   => 'ACCESSORY',
                'material_category' => 'PROTECTION',
                'specification'     => 'Cone / Sheet Animal Barrier for Poles and Cross Arms',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 13,
                'aliases'           => ['PENGHALANG BINATANG', 'ANIMAL GUARD', 'PENAHAN BINATANG'],
            ],
            [
                'material_code'     => 'MAT-ACC-GROUNDING',
                'nama_material'     => 'Pentanahan Lengkap (Grounding Rod + BC Wire)',
                'nama_lapangan'     => 'GROUNDING',
                'satuan'            => 'SET',
                'material_domain'   => 'ACCESSORY',
                'material_category' => 'GROUNDING',
                'specification'     => 'Copper Bonded Ground Rod 5/8" x 3m + BC 50mm²',
                'source_sheet'      => 'KONDUKTOR ACCESORIS',
                'source_row'        => 14,
                'aliases'           => ['GROUNDING', 'GROUND GSW', 'PENTANAHAN', 'ARDE', 'EARTHING'],
            ],
        ];

        foreach ($materials as $m) {
            $ciService->registerMaterial($m);
        }

        // =========================================================================
        // 2. SEED CONSTRUCTION TYPES & BOMs (JTM, MVTIC, GTT, KUBIKEL-DRAFT)
        // =========================================================================
        // A. JTM Constructions (17 types from Sheet 4)
        $jtmConstructions = [
            'TM1'        => ['name' => 'Konstruksi Tiang Tumpu Tunggal TM-1', 'boms' => ['Pin Post Insulator 20 kV Porcelain/Polymer' => 3, 'Travers Cross Arm UNP 2000mm Galvanized' => 1]],
            'TM1A'       => ['name' => 'Konstruksi Tiang Tumpu Sudut Kecil TM-1A', 'boms' => ['Pin Post Insulator 20 kV Porcelain/Polymer' => 3, 'Travers Cross Arm UNP 2000mm Galvanized' => 1]],
            'TM1_TYPE_C' => ['name' => 'Konstruksi Tiang Tumpu Tipe C TM-1C', 'boms' => ['Pin Post Insulator 20 kV Porcelain/Polymer' => 3]],
            'TM2'        => ['name' => 'Konstruksi Tiang Tumpu Ganda Sudut Sedang TM-2', 'boms' => ['Pin Post Insulator 20 kV Porcelain/Polymer' => 6, 'Travers Cross Arm UNP 2000mm Galvanized' => 2]],
            'TM4'        => ['name' => 'Konstruksi Tiang Tarik Awal / Akhir TM-4', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 3, 'Travers Cross Arm UNP 2000mm Galvanized' => 1]],
            'TM4A'       => ['name' => 'Konstruksi Tiang Tarik Sudut TM-4A', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6, 'Travers Cross Arm UNP 2000mm Galvanized' => 2]],
            'TM5'        => ['name' => 'Konstruksi Tiang Penegang / Tension TM-5', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6, 'Travers Cross Arm UNP 2000mm Galvanized' => 2]],
            'TM5C'       => ['name' => 'Konstruksi Tiang Penegang Tipe C TM-5C', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6]],
            'TM8'        => ['name' => 'Konstruksi Tiang Percabangan Sudut TM-8', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6, 'Pin Post Insulator 20 kV Porcelain/Polymer' => 3]],
            'TM8C'       => ['name' => 'Konstruksi Tiang Percabangan Tipe C TM-8C', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6]],
            'TM10'       => ['name' => 'Konstruksi Tiang Transposisi Fasa TM-10', 'boms' => ['Pin Post Insulator 20 kV Porcelain/Polymer' => 6]],
            'TM11'       => ['name' => 'Konstruksi Tiang Penegang Lengkap LA TM-11', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6, 'Polymer Lightning Arrester 24 kV 10 kA' => 3, 'Travers Cross Arm UNP 2000mm Galvanized' => 2, 'Pentanahan Lengkap (Grounding Rod + BC Wire)' => 1]],
            'TM11_DS'    => ['name' => 'Konstruksi Tiang TM-11 dengan Disconnecting Switch (DS)', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6, 'Polymer Lightning Arrester 24 kV 10 kA' => 3]],
            'TM11_CO'    => ['name' => 'Konstruksi Tiang TM-11 dengan Cut Out (CO)', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6, 'Fuse Cut Out Switch 24 kV 100A' => 3, 'Polymer Lightning Arrester 24 kV 10 kA' => 3]],
            'TM12'       => ['name' => 'Konstruksi Tiang Transposisi Tarik TM-12', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6]],
            'TM13'       => ['name' => 'Konstruksi Tiang Sudut Besar / Terminal TM-13', 'boms' => ['Strain Insulator 20 kV Lengkap (SIR)' => 6, 'Travers Cross Arm UNP 2000mm Galvanized' => 2]],
            'TMTP'       => ['name' => 'Konstruksi Tiang Tanpa Travers (Vertikal) TM-TP', 'boms' => ['Pin Post Insulator 20 kV Porcelain/Polymer' => 3]],
        ];

        foreach ($jtmConstructions as $code => $data) {
            $c = $ciService->registerConstructionType([
                'construction_code'   => $code,
                'construction_name'   => $data['name'],
                'construction_family' => 'JTM',
                'asset_domain'        => 'TIANG',
                'approval_status'     => 'ACTIVE',
                'source_sheet'        => 'KONSTRUKSI JTM',
            ]);

            foreach ($data['boms'] as $rawMaterial => $qty) {
                $ciService->addBomItem($c['id'], [
                    'raw_material_name' => $rawMaterial,
                    'quantity'          => $qty,
                    'quantity_status'   => 'KNOWN',
                    'source_sheet'      => 'KONSTRUKSI JTM',
                ]);
            }
        }

        // B. MVTIC Constructions (10 types from Sheet 5)
        for ($i = 1; $i <= 10; $i++) {
            $suffix = $i;
            if ($i === 4) {
                $mCodes = ['TMMVTIC4', 'TMMVTIC4_DS', 'TMMVTIC4_CO'];
            } elseif ($i === 5) {
                $mCodes = ['TMMVTIC5', 'TMMVTIC5A', 'TMMVTIC5B'];
            } else {
                $mCodes = ['TMMVTIC' . $i];
            }

            foreach ($mCodes as $mCode) {
                $c = $ciService->registerConstructionType([
                    'construction_code'   => $mCode,
                    'construction_name'   => "Konstruksi Kabel Pilin Udara TM MVTIC-{$suffix}",
                    'construction_family' => 'MVTIC',
                    'asset_domain'        => 'TIANG',
                    'approval_status'     => 'ACTIVE',
                    'source_sheet'        => 'KONSTRUKSI MVTIC',
                ]);

                $ciService->addBomItem($c['id'], [
                    'raw_material_name' => 'Medium Voltage Covered Conductor Accessory (MCA)',
                    'quantity'          => 1,
                    'quantity_status'   => 'KNOWN',
                    'source_sheet'      => 'KONSTRUKSI MVTIC',
                ]);
            }
        }

        // C. GTT Constructions (Sheet 6)
        $gttConstructions = [
            'GTT1' => [
                'name' => 'Gardu Trafo Tiang 1 Tiang (Cantol)',
                'boms' => [
                    'Polymer Lightning Arrester 24 kV 10 kA' => 3,
                    'Fuse Cut Out Switch 24 kV 100A'         => 3,
                    'Pentanahan Lengkap (Grounding Rod + BC Wire)' => 1,
                ]
            ],
            'GTT2' => [
                'name' => 'Gardu Trafo Tiang 2 Tiang (Portal)',
                'boms' => [
                    'Polymer Lightning Arrester 24 kV 10 kA' => 3,
                    'Fuse Cut Out Switch 24 kV 100A'         => 3,
                    'Travers Cross Arm UNP 2000mm Galvanized' => 4,
                    'Pentanahan Lengkap (Grounding Rod + BC Wire)' => 1,
                ]
            ],
        ];

        foreach ($gttConstructions as $code => $data) {
            $c = $ciService->registerConstructionType([
                'construction_code'   => $code,
                'construction_name'   => $data['name'],
                'construction_family' => 'GTT',
                'asset_domain'        => 'GARDU',
                'approval_status'     => 'ACTIVE',
                'source_sheet'        => 'KONSTRUKSI GTT',
            ]);

            foreach ($data['boms'] as $rawMaterial => $qty) {
                $ciService->addBomItem($c['id'], [
                    'raw_material_name' => $rawMaterial,
                    'quantity'          => $qty,
                    'quantity_status'   => 'KNOWN',
                    'source_sheet'      => 'KONSTRUKSI GTT',
                ]);
            }
        }

        // D. Gardu Kubikel (Sheet 7: DRAFT Governance)
        $kubikelConstructions = [
            'KUBIKEL_INCOMING' => 'Kubikel 20 kV Cell Incoming (DRAFT)',
            'KUBIKEL_OUTGOING' => 'Kubikel 20 kV Cell Outgoing (DRAFT)',
            'KUBIKEL_PB'       => 'Kubikel 20 kV Pemutus Beban (DRAFT)',
        ];

        foreach ($kubikelConstructions as $code => $name) {
            $c = $ciService->registerConstructionType([
                'construction_code'   => $code,
                'construction_name'   => $name,
                'construction_family' => 'GARDU_KUBIKEL',
                'asset_domain'        => 'KUBIKEL',
                'approval_status'     => 'DRAFT', // Explicitly DRAFT
                'source_sheet'        => 'KONSTRUKSI GARDU KUBIKEL',
            ]);

            $ciService->addBomItem($c['id'], [
                'raw_material_name' => 'Kabel Tanah XLPE 20 kV 3x240 mm²',
                'quantity'          => null,
                'quantity_status'   => 'UNKNOWN',
                'source_sheet'      => 'KONSTRUKSI GARDU KUBIKEL',
            ]);
        }

        // =========================================================================
        // 3. SEED INSPECTION PROGRAMS & GTT MEASUREMENT TEMPLATES (SHEET 8 & 9)
        // =========================================================================
        $programs = [
            ['program_code' => 'INSP-JTM-VISUAL-L1',   'nama_pekerjaan' => 'Inspeksi Visual Level 1 JTM',        'asset_domain' => 'JTM',            'inspection_type' => 'VISUAL_L1',        'executor_type' => 'INSPEKSI'],
            ['program_code' => 'INSP-JTM-THERMO',      'nama_pekerjaan' => 'Inspeksi Thermovision JTM',           'asset_domain' => 'JTM',            'inspection_type' => 'THERMOVISION',     'executor_type' => 'INSPEKSI'],
            ['program_code' => 'INSP-JTM-GROUND',      'nama_pekerjaan' => 'Pengukuran Nilai Pentanahan JTM',     'asset_domain' => 'JTM',            'inspection_type' => 'GROUNDING_TEST',   'executor_type' => 'INSPEKSI'],
            ['program_code' => 'INSP-GTT-LOAD-MEASURE','nama_pekerjaan' => 'Pengukuran Beban Gardu Tiang (GTT)', 'asset_domain' => 'TRAFO',          'inspection_type' => 'LOAD_MEASUREMENT', 'executor_type' => 'HAR_GARDU'],
            ['program_code' => 'INSP-KUBIKEL-PD',      'nama_pekerjaan' => 'Pengukuran Partial Discharge Kubikel','asset_domain' => 'GARDU_KUBIKEL',  'inspection_type' => 'PARTIAL_DISCHARGE','executor_type' => 'HAR_GARDU'],
            ['program_code' => 'INSP-JTR-VISUAL',      'nama_pekerjaan' => 'Inspeksi Jaringan Tegangan Rendah',   'asset_domain' => 'JTR',            'inspection_type' => 'VISUAL_L1',        'executor_type' => 'YANTEK'],
        ];

        foreach ($programs as $p) {
            $inspService->registerInspectionProgram($p);
        }

        // GTT Measurement Template (Sheet 9)
        $gttProgram = $db->table('inspection_programs')->where('program_code', 'INSP-GTT-LOAD-MEASURE')->get()->getRowArray();
        $gttTemplate = $inspService->registerMeasurementTemplate([
            'inspection_program_id' => $gttProgram['id'] ?? null,
            'template_code'         => 'TMPL-GTT-LOAD-4LINE',
            'template_name'         => 'Form Pengukuran Beban Trafo GTT (Utama & Line A/B/C/D)',
            'asset_domain'          => 'GTT',
        ]);

        $measurementPoints = [
            // Utama
            ['point_code' => 'MAIN_R', 'point_name' => 'Arus Beban Utama Fasa R', 'phase' => 'R', 'line' => 'MAIN', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            ['point_code' => 'MAIN_S', 'point_name' => 'Arus Beban Utama Fasa S', 'phase' => 'S', 'line' => 'MAIN', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            ['point_code' => 'MAIN_T', 'point_name' => 'Arus Beban Utama Fasa T', 'phase' => 'T', 'line' => 'MAIN', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            ['point_code' => 'MAIN_N', 'point_name' => 'Arus Beban Utama Netral', 'phase' => 'N', 'line' => 'MAIN', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            // Line A
            ['point_code' => 'LINE_A_R', 'point_name' => 'Arus Line A Fasa R', 'phase' => 'R', 'line' => 'A', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            ['point_code' => 'LINE_A_S', 'point_name' => 'Arus Line A Fasa S', 'phase' => 'S', 'line' => 'A', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            ['point_code' => 'LINE_A_T', 'point_name' => 'Arus Line A Fasa T', 'phase' => 'T', 'line' => 'A', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            // Line B
            ['point_code' => 'LINE_B_R', 'point_name' => 'Arus Line B Fasa R', 'phase' => 'R', 'line' => 'B', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            ['point_code' => 'LINE_B_S', 'point_name' => 'Arus Line B Fasa S', 'phase' => 'S', 'line' => 'B', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            ['point_code' => 'LINE_B_T', 'point_name' => 'Arus Line B Fasa T', 'phase' => 'T', 'line' => 'B', 'measurement_type' => 'CURRENT_AMPERE', 'unit' => 'A'],
            // Voltages
            ['point_code' => 'VOLT_RN', 'point_name' => 'Tegangan Fasa-Netral R-N', 'phase' => 'RN', 'line' => 'MAIN', 'measurement_type' => 'VOLTAGE_VOLT', 'unit' => 'V'],
            ['point_code' => 'VOLT_SN', 'point_name' => 'Tegangan Fasa-Netral S-N', 'phase' => 'SN', 'line' => 'MAIN', 'measurement_type' => 'VOLTAGE_VOLT', 'unit' => 'V'],
            ['point_code' => 'VOLT_TN', 'point_name' => 'Tegangan Fasa-Netral T-N', 'phase' => 'TN', 'line' => 'MAIN', 'measurement_type' => 'VOLTAGE_VOLT', 'unit' => 'V'],
            ['point_code' => 'VOLT_RS', 'point_name' => 'Tegangan Fasa-Fasa R-S',   'phase' => 'RS', 'line' => 'MAIN', 'measurement_type' => 'VOLTAGE_VOLT', 'unit' => 'V'],
            ['point_code' => 'VOLT_ST', 'point_name' => 'Tegangan Fasa-Fasa S-T',   'phase' => 'ST', 'line' => 'MAIN', 'measurement_type' => 'VOLTAGE_VOLT', 'unit' => 'V'],
            ['point_code' => 'VOLT_TR', 'point_name' => 'Tegangan Fasa-Fasa T-R',   'phase' => 'TR', 'line' => 'MAIN', 'measurement_type' => 'VOLTAGE_VOLT', 'unit' => 'V'],
        ];

        foreach ($measurementPoints as $idx => $pt) {
            $pt['sequence_order'] = $idx + 1;
            $inspService->addMeasurementPoint($gttTemplate['id'], $pt);
        }

        // =========================================================================
        // 4. SEED FEEDER HEALTH POLICY FHI-v1.0 (GATE 6 & 7)
        // =========================================================================
        $fhiService->ensureDefaultPolicy();
    }
}
