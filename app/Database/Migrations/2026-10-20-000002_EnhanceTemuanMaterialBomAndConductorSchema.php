<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 2026-10-20-000002: Enhance Temuan Material BOM & Conductor Schema
 *
 * Implements:
 * 1. Additive columns on `temuan`: `conductor_type` (AAAC/AAACS/MVTIC/XLPE), `conductor_size` (70/110/150/240 mm²), `finding_component_key`.
 * 2. Relaxes `temuan_materials.asset_id` and `temuan_materials.construction_type_id` to NULLABLE so findings at feeder/section level or without pole asset IDs can still persist structured BOM materials, and adds `conductor_type` and `conductor_size` to `temuan_materials`.
 * 3. Seeds the 16 canonical conductor materials (`MAT-COND-{AAAC|AAACS|MVTIC|XLPE}-{70|110|150|240}`) in `master_materials`.
 * 4. Seeds complete PLN standard BOM mappings for `TM-1` through `TM-11` (supporting both hyphenated `TM-1` and unhyphenated `TM1` codes) in `construction_bom_items`.
 * 5. Deterministically backfills `conductor_type`, `conductor_size`, `finding_component_key`, and structured `temuan_materials` rows from existing `temuan` records where BOM components are explicitly identified.
 */
class EnhanceTemuanMaterialBomAndConductorSchema extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        // 1. Add conductor_type, conductor_size, finding_component_key to `temuan`
        if ($this->db->tableExists('temuan')) {
            if (!$this->db->fieldExists('conductor_type', 'temuan')) {
                try {
                    $this->db->query("ALTER TABLE `temuan` ADD COLUMN `conductor_type` VARCHAR(24) NULL");
                } catch (\Throwable $e) {}
            }
            if (!$this->db->fieldExists('conductor_size', 'temuan')) {
                try {
                    $this->db->query("ALTER TABLE `temuan` ADD COLUMN `conductor_size` VARCHAR(16) NULL");
                } catch (\Throwable $e) {}
            }
            if (!$this->db->fieldExists('finding_component_key', 'temuan')) {
                try {
                    $this->db->query("ALTER TABLE `temuan` ADD COLUMN `finding_component_key` VARCHAR(40) NULL");
                } catch (\Throwable $e) {}
            }
        }

        // 2. Alter `temuan_materials` so asset_id and construction_type_id can be NULL, and add conductor_type, conductor_size
        if ($this->db->tableExists('temuan_materials')) {
            try {
                $this->db->query("ALTER TABLE `temuan_materials` MODIFY COLUMN `asset_id` INT(11) UNSIGNED NULL DEFAULT NULL");
            } catch (\Throwable $e) {
                log_message('warning', '[Migration] temuan_materials.asset_id modify warning: ' . $e->getMessage());
            }
            try {
                $this->db->query("ALTER TABLE `temuan_materials` MODIFY COLUMN `construction_type_id` INT(11) UNSIGNED NULL DEFAULT NULL");
            } catch (\Throwable $e) {
                log_message('warning', '[Migration] temuan_materials.construction_type_id modify warning: ' . $e->getMessage());
            }

            if (!$this->db->fieldExists('conductor_type', 'temuan_materials')) {
                try {
                    $this->db->query("ALTER TABLE `temuan_materials` ADD COLUMN `conductor_type` VARCHAR(24) NULL");
                } catch (\Throwable $e) {}
            }
            if (!$this->db->fieldExists('conductor_size', 'temuan_materials')) {
                try {
                    $this->db->query("ALTER TABLE `temuan_materials` ADD COLUMN `conductor_size` VARCHAR(16) NULL");
                } catch (\Throwable $e) {}
            }
        }

        // 3. Upsert 16 canonical conductor materials (AAAC, AAACS, MVTIC, XLPE x 70, 110, 150, 240 mm²) in `master_materials`
        if ($this->db->tableExists('master_materials')) {
            $conductorTypes = [
                'AAAC'  => 'Konduktor AAAC (All Aluminium Alloy Conductor) Telanjang 20 kV',
                'AAACS' => 'Konduktor AAACS (All Aluminium Alloy Conductor Insulated) 20 kV',
                'MVTIC' => 'Konduktor MVTIC (Medium Voltage Twisted Insulated Cable) 20 kV',
                'XLPE'  => 'Kabel Tanah / Udara XLPE (Cross-Linked Polyethylene) 20 kV',
            ];
            $sizes = ['70', '110', '150', '240'];

            foreach ($conductorTypes as $cType => $cDesc) {
                foreach ($sizes as $cSize) {
                    $mCode = "MAT-COND-{$cType}-{$cSize}";
                    $mName = "Konduktor {$cType} {$cSize} mm² (20 kV)";
                    $mAlias = "{$cType} {$cSize} mm²";

                    $existing = $this->db->table('master_materials')
                        ->where('material_code', $mCode)
                        ->get()
                        ->getRowArray();

                    if ($existing) {
                        $this->db->table('master_materials')
                            ->where('id', (int)$existing['id'])
                            ->update([
                                'nama_material'     => $mName,
                                'nama_lapangan'     => $mAlias,
                                'satuan'            => 'meter',
                                'material_domain'   => 'JTM',
                                'material_category' => 'KONDUKTOR',
                                'specification'     => "{$cDesc} Penampang {$cSize} mm² Standar PLN",
                                'status'            => 'AKTIF',
                                'updated_at'        => $now,
                            ]);
                    } else {
                        $this->db->table('master_materials')->insert([
                            'material_code'     => $mCode,
                            'nama_material'     => $mName,
                            'nama_lapangan'     => $mAlias,
                            'satuan'            => 'meter',
                            'material_domain'   => 'JTM',
                            'material_category' => 'KONDUKTOR',
                            'specification'     => "{$cDesc} Penampang {$cSize} mm² Standar PLN",
                            'source_workbook'   => 'KONSTRUKSI_JTM_2026.xlsx',
                            'source_sheet'      => 'KONDUKTOR JTM',
                            'status'            => 'AKTIF',
                            'created_at'        => $now,
                            'updated_at'        => $now,
                        ]);
                    }
                }
            }
        }

        // 4. Populate `construction_bom_items` for TM-1 through TM-11 (matching both TM-1 and TM1)
        if ($this->db->tableExists('master_materials') && $this->db->tableExists('construction_types') && $this->db->tableExists('construction_bom_items')) {
            $matByCode = [];
            $mmQ1 = $this->db->table('master_materials')->get();
            foreach (($mmQ1 && is_object($mmQ1) ? $mmQ1->getResultArray() : []) as $m) {
                $matByCode[$m['material_code']] = $m;
            }

            $tmBomDefinitions = [
                'TM-1' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 1.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-005', 'qty' => 2.0, 'cat' => 'BAUT_DAN_MUR'],
                    ['code' => 'CANON-HDW-002', 'qty' => 1.0, 'cat' => 'BAUT_DAN_MUR'],
                    ['code' => 'CANON-HDW-015', 'qty' => 1.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-HDW-016', 'qty' => 2.0, 'cat' => 'AKSESORIS'],
                    ['code' => 'CANON-MAT-001', 'qty' => 3.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-ACC-010', 'qty' => 3.0, 'cat' => 'PENGIKAT'],
                    ['code' => 'CANON-ACC-011', 'qty' => 1.0, 'cat' => 'PENGIKAT'],
                    ['code' => 'CANON-ACC-012', 'qty' => 2.0, 'cat' => 'PENGIKAT'],
                    ['code' => 'CANON-HDW-018', 'qty' => 2.0, 'cat' => 'BAND'],
                    ['code' => 'CANON-HDW-019', 'qty' => 1.0, 'cat' => 'PIPA'],
                ],
                'TM-2' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-004', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-005', 'qty' => 4.0, 'cat' => 'BAUT_DAN_MUR'],
                    ['code' => 'CANON-HDW-002', 'qty' => 4.0, 'cat' => 'BAUT_DAN_MUR'],
                    ['code' => 'CANON-MAT-001', 'qty' => 6.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-ACC-011', 'qty' => 2.0, 'cat' => 'PENGIKAT'],
                    ['code' => 'CANON-ACC-012', 'qty' => 4.0, 'cat' => 'PENGIKAT'],
                    ['code' => 'CANON-HDW-015', 'qty' => 1.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-HDW-016', 'qty' => 2.0, 'cat' => 'AKSESORIS'],
                ],
                'TM-3' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-MAT-004', 'qty' => 3.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-HDW-007', 'qty' => 3.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-HDW-016', 'qty' => 2.0, 'cat' => 'AKSESORIS'],
                ],
                'TM-4' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-MAT-004', 'qty' => 3.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-HDW-007', 'qty' => 3.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-HDW-002', 'qty' => 4.0, 'cat' => 'BAUT_DAN_MUR'],
                ],
                'TM-5' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-004', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-005', 'qty' => 4.0, 'cat' => 'BAUT_DAN_MUR'],
                    ['code' => 'CANON-HDW-002', 'qty' => 4.0, 'cat' => 'BAUT_DAN_MUR'],
                    ['code' => 'CANON-MAT-004', 'qty' => 6.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-HDW-007', 'qty' => 6.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-HDW-023', 'qty' => 3.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-MAT-001', 'qty' => 3.0, 'cat' => 'ISOLATOR'],
                ],
                'TM-6' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-MAT-004', 'qty' => 6.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-HDW-007', 'qty' => 6.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-HDW-023', 'qty' => 3.0, 'cat' => 'CLAMP'],
                ],
                'TM-7' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-MAT-004', 'qty' => 6.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-HDW-007', 'qty' => 6.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-MAT-001', 'qty' => 3.0, 'cat' => 'ISOLATOR'],
                ],
                'TM-8' => [
                    ['code' => 'CANON-HDW-013', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-MAT-003', 'qty' => 3.0, 'cat' => 'PROTECTION'],
                    ['code' => 'CANON-MAT-002', 'qty' => 3.0, 'cat' => 'PROTECTION'],
                    ['code' => 'CANON-MAT-001', 'qty' => 3.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-HDW-023', 'qty' => 3.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-HDW-025', 'qty' => 6.0, 'cat' => 'CONNECTOR'],
                    ['code' => 'CANON-HDW-022', 'qty' => 1.0, 'cat' => 'GROUNDING'],
                ],
                'TM-9' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-MAT-003', 'qty' => 3.0, 'cat' => 'PROTECTION'],
                    ['code' => 'CANON-MAT-002', 'qty' => 3.0, 'cat' => 'PROTECTION'],
                    ['code' => 'CANON-HDW-025', 'qty' => 6.0, 'cat' => 'CONNECTOR'],
                ],
                'TM-10' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-MAT-004', 'qty' => 3.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-HDW-007', 'qty' => 3.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-MAT-002', 'qty' => 3.0, 'cat' => 'PROTECTION'],
                    ['code' => 'CANON-HDW-022', 'qty' => 1.0, 'cat' => 'GROUNDING'],
                ],
                'TM-11' => [
                    ['code' => 'CANON-HDW-001', 'qty' => 2.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-003', 'qty' => 4.0, 'cat' => 'CROSS_ARM_TRAVERS'],
                    ['code' => 'CANON-HDW-002', 'qty' => 4.0, 'cat' => 'BAUT_DAN_MUR'],
                    ['code' => 'CANON-MAT-004', 'qty' => 6.0, 'cat' => 'ISOLATOR'],
                    ['code' => 'CANON-HDW-007', 'qty' => 6.0, 'cat' => 'CLAMP'],
                    ['code' => 'CANON-MAT-002', 'qty' => 3.0, 'cat' => 'PROTECTION'],
                    ['code' => 'CANON-MAT-003', 'qty' => 3.0, 'cat' => 'PROTECTION'],
                    ['code' => 'CANON-HDW-025', 'qty' => 6.0, 'cat' => 'CONNECTOR'],
                    ['code' => 'CANON-HDW-022', 'qty' => 1.0, 'cat' => 'GROUNDING'],
                ],
            ];

            foreach ($tmBomDefinitions as $tmCode => $bomItems) {
                $altCode = str_replace('-', '', $tmCode);
                $ctRow = $this->db->table('construction_types')
                    ->where('code', $tmCode)
                    ->orWhere('code', $altCode)
                    ->get()
                    ->getRowArray();

                if (!$ctRow) {
                    continue;
                }

                $ctId = (int)$ctRow['id'];
                $this->db->table('construction_bom_items')->where('construction_type_id', $ctId)->delete();

                foreach ($bomItems as $idx => $item) {
                    $mat = $matByCode[$item['code']] ?? null;
                    if (!$mat) {
                        continue;
                    }
                    $this->db->table('construction_bom_items')->insert([
                        'construction_type_id' => $ctId,
                        'material_id'          => (int)$mat['id'],
                        'raw_material_name'    => $mat['nama_material'],
                        'material_alias'       => $mat['nama_lapangan'] ?: $mat['nama_material'],
                        'component_category'   => $item['cat'],
                        'quantity'             => $item['qty'],
                        'quantity_status'      => 'KNOWN',
                        'unit'                 => $mat['satuan'] ?: 'buah',
                        'mandatory'            => 1,
                        'source_sheet'         => 'KONSTRUKSI JTM',
                        'mapping_status'       => 'RESOLVED',
                        'sort_order'           => $idx + 1,
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ]);
                }
            }
        }

        // 5. Deterministic backfill of conductor_type, conductor_size, finding_component_key, and temuan_materials
        if ($this->db->tableExists('temuan') && $this->db->tableExists('temuan_materials') && $this->db->tableExists('master_materials')) {
            $matByCode = [];
            $mmQ2 = $this->db->table('master_materials')->get();
            foreach (($mmQ2 && is_object($mmQ2) ? $mmQ2->getResultArray() : []) as $m) {
                $matByCode[$m['material_code']] = $m;
            }

            $ctByCode = [];
            if ($this->db->tableExists('construction_types')) {
                $ctQ = $this->db->table('construction_types')->get();
                foreach (($ctQ && is_object($ctQ) ? $ctQ->getResultArray() : []) as $ct) {
                    $ctByCode[strtoupper($ct['code'])] = (int)$ct['id'];
                    $ctByCode[strtoupper(str_replace('-', '', $ct['code']))] = (int)$ct['id'];
                }
            }

            // Dominant conductor per penyulang_id from gis_translines
            $feederConductorMap = [];
            if ($this->db->tableExists('gis_translines') && $this->db->fieldExists('conductor_type', 'gis_translines')) {
                $hasTlSize = $this->db->fieldExists('conductor_size', 'gis_translines');
                $hasTlDel  = $this->db->fieldExists('deleted_at', 'gis_translines');
                $tlSizeSelect = $hasTlSize ? 'conductor_size' : "'150' AS conductor_size";
                $tlSizeGroup  = $hasTlSize ? ', conductor_size' : '';
                $tlWhere      = $hasTlDel ? 'deleted_at IS NULL AND penyulang_id IS NOT NULL' : 'penyulang_id IS NOT NULL';
                $tlQuery = $this->db->query("
                    SELECT penyulang_id, conductor_type, {$tlSizeSelect}, COUNT(*) as cnt
                    FROM gis_translines
                    WHERE {$tlWhere}
                    GROUP BY penyulang_id, conductor_type{$tlSizeGroup}
                    ORDER BY penyulang_id ASC, cnt DESC
                ");
                $tlRows = ($tlQuery && is_object($tlQuery)) ? $tlQuery->getResultArray() : [];
                foreach ($tlRows as $tr) {
                    $pid = (int)$tr['penyulang_id'];
                    if (!isset($feederConductorMap[$pid])) {
                        $cType = strtoupper(trim((string)($tr['conductor_type'] ?? 'AAAC')));
                        if (!in_array($cType, ['AAAC', 'AAACS', 'MVTIC', 'XLPE'], true)) {
                            $cType = 'AAAC';
                        }
                        $cSizeRaw = preg_replace('/[^0-9]/', '', (string)($tr['conductor_size'] ?? '150'));
                        $cSize = in_array($cSizeRaw, ['70', '110', '150', '240'], true) ? $cSizeRaw : '150';
                        $feederConductorMap[$pid] = ['type' => $cType, 'size' => $cSize];
                    }
                }
            }

            // Valid asset_ids in `assets` table to satisfy FK constraint
            $validAssetIds = [];
            if ($this->db->tableExists('assets')) {
                $aQuery = $this->db->table('assets')->select('id')->get();
                foreach (($aQuery && is_object($aQuery) ? $aQuery->getResultArray() : []) as $aRow) {
                    $validAssetIds[(int)$aRow['id']] = true;
                }
            }

            // Existing temuan_materials map to avoid duplicates
            $existingTmMat = [];
            $etmQuery = $this->db->table('temuan_materials')->select('temuan_id, material_id')->get();
            foreach (($etmQuery && is_object($etmQuery) ? $etmQuery->getResultArray() : []) as $etm) {
                $existingTmMat[(int)$etm['temuan_id'] . ':' . (int)$etm['material_id']] = true;
            }

            $temuanQuery = $this->db->table('temuan')
                ->where('deleted_at IS NULL', null, false)
                ->where("(conductor_type IS NULL OR conductor_type = '' OR finding_component_key IS NULL)", null, false)
                ->get();
            $temuanRows = ($temuanQuery && is_object($temuanQuery)) ? $temuanQuery->getResultArray() : [];
            $batchMaterials = [];

            foreach ($temuanRows as $r) {
                $tid = (int)$r['id'];
                $pid = (int)($r['penyulang_id'] ?? 0);
                $txt = strtolower(($r['jenis_temuan'] ?? '') . ' ' . ($r['material'] ?? '') . ' ' . ($r['detail_temuan'] ?? '') . ' ' . ($r['konduktor'] ?? ''));

                // Determine conductor_type and conductor_size
                $cType = !empty($r['conductor_type']) ? strtoupper(trim((string)$r['conductor_type'])) : null;
                $cSize = !empty($r['conductor_size']) ? preg_replace('/[^0-9]/', '', (string)$r['conductor_size']) : null;

                if (!$cType) {
                    if (preg_match('/\b(aaacs|a3cs)\b/i', $txt)) {
                        $cType = 'AAACS';
                    } elseif (preg_match('/\b(mvtic|tic)\b/i', $txt)) {
                        $cType = 'MVTIC';
                    } elseif (preg_match('/\b(xlpe|sktm|kabel\s*tanah)\b/i', $txt)) {
                        $cType = 'XLPE';
                    } elseif (preg_match('/\b(aaac|a3c)\b/i', $txt)) {
                        $cType = 'AAAC';
                    } elseif (isset($feederConductorMap[$pid])) {
                        $cType = $feederConductorMap[$pid]['type'];
                    } else {
                        $cType = 'AAAC';
                    }
                }

                if (!$cSize || !in_array($cSize, ['70', '110', '150', '240'], true)) {
                    if (preg_match('/\b(70|110|150|240)\s*(?:mm|mm2|mm²)?\b/i', (string)($r['konduktor'] ?? ''), $mSz)) {
                        $cSize = $mSz[1];
                    } elseif (preg_match('/\b(70|110|150|240)\s*(?:mm|mm2|mm²)\b/i', $txt, $mSz2)) {
                        $cSize = $mSz2[1];
                    } elseif ($cType === 'XLPE') {
                        $cSize = '240';
                    } elseif (isset($feederConductorMap[$pid])) {
                        $cSize = $feederConductorMap[$pid]['size'];
                    } else {
                        $cSize = '150';
                    }
                }

                // Determine construction_type_id if mentioned in text
                $ctId = null;
                if (preg_match('/\b(tm[-\s]?(?:11|10|[1-9])|lbsm|lbs|pmcb|recloser|recl)\b/i', $txt, $mCt)) {
                    $rawCt = strtoupper(str_replace([' ', '-'], '', $mCt[1]));
                    if ($rawCt === 'RECL') {
                        $rawCt = 'RECLOSER';
                    }
                    $ctId = $ctByCode[$rawCt] ?? null;
                }

                // Determine primary component & BOM materials from text
                $matchedCodes = [];
                $primaryKey = null;

                if (preg_match('/\b(arm\s*tie|armtie|begel\s*arm)\b/i', $txt)) {
                    $matchedCodes['CANON-HDW-003'] = 2.0;
                    $primaryKey = $primaryKey ?? 'ARMTIE';
                }
                if (preg_match('/\b(cross\s*arm|crossarm|travers|kanal|unp)\b/i', $txt)) {
                    $matchedCodes['CANON-HDW-001'] = 1.0;
                    $primaryKey = $primaryKey ?? 'CROSSARM';
                }
                if (preg_match('/\b(verlink|perlink)\b/i', $txt)) {
                    $matchedCodes['CANON-HDW-019'] = 1.0;
                    $primaryKey = $primaryKey ?? 'CROSSARM';
                }
                if (preg_match('/\b(pin\s*isolator|isolator\s*pin|pin\s*post|isolator\s*tumpu|isolator\s*retak|pin\s*bantu)\b/i', $txt)) {
                    $matchedCodes['CANON-MAT-001'] = 1.0;
                    $primaryKey = $primaryKey ?? 'PIN_ISOLATOR';
                }
                if (preg_match('/\b(hang\s*isolator|isolator\s*hang|strain\s*insulator|isolator\s*tarik|piring)\b/i', $txt)) {
                    $matchedCodes['CANON-MAT-004'] = 1.0;
                    $primaryKey = $primaryKey ?? 'HANG_ISOLATOR';
                }
                if (preg_match('/\b(top\s*tie|toptie|side\s*tie|bending|binding|plp)\b/i', $txt)) {
                    $matchedCodes['CANON-ACC-012'] = 1.0;
                    $primaryKey = $primaryKey ?? 'PIN_ISOLATOR';
                }
                if (preg_match('/\b(strain\s*clamp|strain\s*claim|dead\s*end|arm\s*clevis)\b/i', $txt)) {
                    $matchedCodes['CANON-HDW-007'] = 1.0;
                    $primaryKey = $primaryKey ?? 'HANG_ISOLATOR';
                }
                if (preg_match('/\b(arrester|la\b|lightning)\b/i', $txt)) {
                    $matchedCodes['CANON-MAT-002'] = 1.0;
                    $primaryKey = $primaryKey ?? 'ARRESTER';
                }
                if (preg_match('/\b(fco|cut\s*out|dexsel|dexel|deksel|dexxel|co\s*branch|co\s*gtt|fuse)\b/i', $txt)) {
                    $matchedCodes['CANON-MAT-003'] = 1.0;
                    $primaryKey = $primaryKey ?? 'FCO';
                }
                if (preg_match('/\b(skun|terminasi|termjnasi|therminasi|sepatu\s*kabel|bimetal|lug)\b/i', $txt)) {
                    $matchedCodes['CANON-HDW-025'] = 1.0;
                    $primaryKey = $primaryKey ?? 'SKUN_KONEKTOR';
                }
                if (preg_match('/\b(linetap|line\s*tap|lentap|pg\s*clamp|parallel\s*groove|jumperan|konektor|connector|llc|joint)\b/i', $txt)) {
                    $matchedCodes['CANON-HDW-023'] = 1.0;
                    $primaryKey = $primaryKey ?? 'SKUN_KONEKTOR';
                }
                if (preg_match('/\b(gsw|ground|arde|pentanahan|skur|schoor|kontramas|wespek|wespeck|wesbek|wespack|wespex)\b/i', $txt)) {
                    $matchedCodes['CANON-HDW-016'] = 1.0;
                    $primaryKey = $primaryKey ?? 'GSW_GROUNDING';
                }
                if (preg_match('/\b(konduktor|kawat|kabel|ngecer|rantas|mekar|andongan|treeguard|cover)\b/i', $txt)) {
                    $condMatCode = "MAT-COND-{$cType}-{$cSize}";
                    if (isset($matByCode[$condMatCode])) {
                        $matchedCodes[$condMatCode] = 15.0; // standard 15m span repair
                    }
                    $primaryKey = $primaryKey ?? 'KONDUKTOR';
                }
                if (empty($matchedCodes)) {
                    if (strtoupper((string)$r['jenis_temuan']) === 'HOTSPOT') {
                        $matchedCodes['CANON-HDW-025'] = 1.0;
                        $primaryKey = 'SKUN_KONEKTOR';
                    } else {
                        $matchedCodes['CANON-HDW-003'] = 1.0;
                        $primaryKey = 'KONSTRUKSI_LAIN';
                    }
                }

                // Update temuan row with normalized conductor_type, conductor_size, finding_component_key
                $updateFields = [
                    'conductor_type'        => $cType,
                    'conductor_size'        => $cSize,
                    'finding_component_key' => $primaryKey,
                ];
                if (empty($r['konduktor'])) {
                    $updateFields['konduktor'] = "{$cType} {$cSize} mm²";
                }
                $this->db->table('temuan')->where('id', $tid)->update($updateFields);

                // Insert structured temuan_materials rows if not already present
                foreach ($matchedCodes as $mCode => $qty) {
                    $mat = $matByCode[$mCode] ?? null;
                    if (!$mat) {
                        continue;
                    }
                    $matId = (int)$mat['id'];
                    $dedupKey = "{$tid}:{$matId}";
                    if (isset($existingTmMat[$dedupKey])) {
                        continue;
                    }

                    $rawAssetId = (!empty($r['asset_id']) && (int)$r['asset_id'] > 0) ? (int)$r['asset_id'] : null;
                    $safeAssetId = ($rawAssetId !== null && isset($validAssetIds[$rawAssetId])) ? $rawAssetId : null;

                    $batchMaterials[] = [
                        'temuan_id'               => $tid,
                        'asset_id'                => $safeAssetId,
                        'construction_type_id'    => $ctId,
                        'material_id'             => $matId,
                        'canonical_code_snapshot' => $mat['material_code'],
                        'canonical_name_snapshot' => $mat['nama_material'],
                        'unit_snapshot'           => $mat['satuan'] ?: 'buah',
                        'quantity'                => number_format((float)$qty, 2, '.', ''),
                        'conductor_type'          => $cType,
                        'conductor_size'          => $cSize,
                        'justification_note'      => 'Klasifikasi BOM otomatis dari detail temuan inspeksi',
                        'source_mode'             => 'BOM_CLASSIFIED',
                        'created_by'              => !empty($r['created_by']) ? (int)$r['created_by'] : null,
                        'created_at'              => $r['created_at'] ?: $now,
                        'updated_at'              => $now,
                    ];
                    $existingTmMat[$dedupKey] = true;
                }
            }
            if (!empty($batchMaterials)) {
                foreach (array_chunk($batchMaterials, 150) as $chunk) {
                    $this->db->table('temuan_materials')->insertBatch($chunk);
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('temuan_materials')) {
            $this->db->table('temuan_materials')
                ->where('source_mode', 'BOM_CLASSIFIED')
                ->delete();

            if ($this->db->fieldExists('conductor_size', 'temuan_materials')) {
                $this->forge->dropColumn('temuan_materials', 'conductor_size');
            }
            if ($this->db->fieldExists('conductor_type', 'temuan_materials')) {
                $this->forge->dropColumn('temuan_materials', 'conductor_type');
            }
        }

        if ($this->db->tableExists('temuan')) {
            if ($this->db->fieldExists('finding_component_key', 'temuan')) {
                $this->forge->dropColumn('temuan', 'finding_component_key');
            }
            if ($this->db->fieldExists('conductor_size', 'temuan')) {
                $this->forge->dropColumn('temuan', 'conductor_size');
            }
            if ($this->db->fieldExists('conductor_type', 'temuan')) {
                $this->forge->dropColumn('temuan', 'conductor_type');
            }
        }

        if ($this->db->tableExists('master_materials')) {
            $this->db->table('master_materials')
                ->like('material_code', 'MAT-COND-', 'after')
                ->delete();
        }
    }
}
