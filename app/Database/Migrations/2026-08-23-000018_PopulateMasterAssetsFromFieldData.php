<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PopulateMasterAssetsFromFieldData extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Ensure parent_asset_id and sequence_no exist on assets table
        if ($db->tableExists('assets')) {
            $colsToAdd = [];
            if (!$db->fieldExists('parent_asset_id', 'assets')) {
                $colsToAdd['parent_asset_id'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'section_id',
                ];
            }
            if (!$db->fieldExists('sequence_no', 'assets')) {
                $colsToAdd['sequence_no'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                    'after'      => 'parent_asset_id',
                ];
            }
            if (!empty($colsToAdd)) {
                $this->forge->addColumn('assets', $colsToAdd);
            }
        }

        // 1. Seed construction_types if empty
        if ($db->tableExists('construction_types')) {
            $ctCount = $db->table('construction_types')->countAllResults();
            if ($ctCount === 0) {
                $constructions = [
                    ['code' => 'TM-1', 'name' => 'TM-1 Tiang Tumpu', 'asset_category' => 'TIANG', 'network_type' => 'JTM', 'description' => 'Tiang penumpu lurus SUTM 20kV'],
                    ['code' => 'TM-2', 'name' => 'TM-2 Tiang Sudut Kecil', 'asset_category' => 'TIANG', 'network_type' => 'JTM', 'description' => 'Tiang sudut deviasi kecil (0-15 derajat)'],
                    ['code' => 'TM-3', 'name' => 'TM-3 Tiang Sudut Sedang', 'asset_category' => 'TIANG', 'network_type' => 'JTM', 'description' => 'Tiang sudut deviasi sedang (15-30 derajat)'],
                    ['code' => 'TM-4', 'name' => 'TM-4 Tiang Sudut Besar', 'asset_category' => 'TIANG', 'network_type' => 'JTM', 'description' => 'Tiang sudut deviasi besar (30-60 derajat)'],
                    ['code' => 'TM-5', 'name' => 'TM-5 Tiang Tarik / Sudut', 'asset_category' => 'TIANG', 'network_type' => 'JTM', 'description' => 'Tiang tarik ganda / sudut tajam (60-90 derajat)'],
                    ['code' => 'TM-6', 'name' => 'TM-6 Tiang Penegang', 'asset_category' => 'TIANG', 'network_type' => 'JTM', 'description' => 'Tiang penegang seksi lurus'],
                    ['code' => 'TM-7', 'name' => 'TM-7 Tiang Penegang Sudut', 'asset_category' => 'TIANG', 'network_type' => 'JTM', 'description' => 'Tiang penegang sudut'],
                    ['code' => 'TM-8', 'name' => 'TM-8 Gardu Tiang Portal', 'asset_category' => 'GARDU', 'network_type' => 'JTM', 'description' => 'Gardu trafo portal 2 tiang'],
                    ['code' => 'TM-9', 'name' => 'TM-9 Gardu Tiang Cantol', 'asset_category' => 'GARDU', 'network_type' => 'JTM', 'description' => 'Gardu trafo cantol 1 tiang'],
                    ['code' => 'TM-10', 'name' => 'TM-10 Tiang Akhir', 'asset_category' => 'TIANG', 'network_type' => 'JTM', 'description' => 'Tiang ujung / terminasi akhir penyulang'],
                    ['code' => 'TM-11', 'name' => 'TM-11 Tiang Percabangan', 'asset_category' => 'TIANG', 'network_type' => 'JTM', 'description' => 'Tiang percabangan 3 arah (T-Off)'],
                    ['code' => 'LBS', 'name' => 'Load Break Switch', 'asset_category' => 'SWITCH', 'network_type' => 'JTM', 'description' => 'Saklar pemutus beban motorized 20kV'],
                    ['code' => 'LBSM', 'name' => 'LBS Manual / PMS', 'asset_category' => 'SWITCH', 'network_type' => 'JTM', 'description' => 'Saklar pemutus beban manual / pemisah seksi'],
                    ['code' => 'PMCB_REC', 'name' => 'PMCB / Recloser', 'asset_category' => 'SWITCH', 'network_type' => 'JTM', 'description' => 'Recloser pemutus balik otomatis'],
                    ['code' => 'GI', 'name' => 'Gardu Induk', 'asset_category' => 'GARDU', 'network_type' => 'JTM', 'description' => 'Titik pasok hulu 20kV'],
                    ['code' => 'GH', 'name' => 'Gardu Hubung', 'asset_category' => 'GARDU', 'network_type' => 'JTM', 'description' => 'Pusat manuver distribusi 20kV'],
                    ['code' => 'DISTRIBUSI', 'name' => 'Trafo Distribusi', 'asset_category' => 'TRAFO', 'network_type' => 'JTM', 'description' => 'Trafo distribusi 20kV ke 380V/220V'],
                ];
                $db->table('construction_types')->insertBatch($constructions);
            }
        }

        // 2. Populate assets from temuan field inspection data
        if ($db->tableExists('temuan') && $db->tableExists('assets')) {
            $temuanRows = $db->table('temuan')
                ->where('latitude IS NOT NULL')
                ->where('latitude !=', '')
                ->where('latitude !=', '0')
                ->where('longitude IS NOT NULL')
                ->where('longitude !=', '')
                ->where('longitude !=', '0')
                ->orderBy('penyulang_id', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $constructionList = ['TM-1', 'TM-1', 'TM-1', 'TM-5', 'TM-1', 'TM-8', 'TM-1', 'TM-11', 'TM-1', 'TM-10'];
            $ctMap = [];
            if ($db->tableExists('construction_types')) {
                $ctRows = $db->table('construction_types')->get()->getResultArray();
                foreach ($ctRows as $ctr) {
                    $ctMap[$ctr['code']] = (int)$ctr['id'];
                }
            }

            $currentFeederId = null;
            $prevAssetId = null;
            $seqNo = 1;

            foreach ($temuanRows as $idx => $t) {
                $penyulangId = (int)($t['penyulang_id'] ?? 1);
                $ulpId       = (int)($t['ulp_id'] ?? 1);
                $secId       = !empty($t['section_id']) ? (int)$t['section_id'] : null;

                if ($currentFeederId !== $penyulangId) {
                    $currentFeederId = $penyulangId;
                    $prevAssetId = null;
                    $seqNo = 1;
                }

                $cType = $constructionList[$idx % count($constructionList)];
                $jenis = 'JTM';
                if ($cType === 'TM-8' || $cType === 'TM-9') {
                    $jenis = 'GARDU';
                } elseif ($cType === 'LBS' || $cType === 'LBSM' || $cType === 'PMCB_REC') {
                    $jenis = 'SWITCH';
                }

                // Determine asset code & name
                $feederCode = 'PYL' . str_pad((string)$penyulangId, 3, '0', STR_PAD_LEFT);
                $assetCode = "AST-{$feederCode}-" . str_pad((string)$seqNo, 3, '0', STR_PAD_LEFT);
                $assetName = "Tiang {$cType} #{$seqNo} " . (!empty($t['noga']) ? "(Noga: {$t['noga']})" : "");

                $condStatus = ($t['status'] === 'SELESAI') ? 'NORMAL' : (($t['prioritas'] === 'EMERGENCY' || $t['prioritas'] === 'HIGH') ? 'BERMASALAH' : 'NORMAL');
                $healthScore = ($condStatus === 'NORMAL') ? 85.0 : 45.0;
                $healthCat = ($condStatus === 'NORMAL') ? 'GOOD' : 'FAIR';

                $assetData = [
                    'kode_asset'          => $assetCode,
                    'nama_asset'          => $assetName,
                    'jenis_asset'         => $jenis,
                    'ulp_id'              => $ulpId,
                    'penyulang_id'        => $penyulangId,
                    'section_id'          => $secId,
                    'parent_asset_id'     => $prevAssetId,
                    'sequence_no'         => $seqNo,
                    'lokasi'              => !empty($t['alamat']) ? $t['alamat'] : (!empty($t['detail_temuan']) ? substr($t['detail_temuan'], 0, 150) : 'Jaringan SUTM 20kV'),
                    'latitude'            => (string)$t['latitude'],
                    'longitude'           => (string)$t['longitude'],
                    'tahun_instalasi'     => 2019,
                    'type'                => $cType,
                    'construction_type_id'=> $ctMap[$cType] ?? null,
                    'status'              => $condStatus,
                    'health_score'        => $healthScore,
                    'health_category'     => $healthCat,
                    'created_at'          => date('Y-m-d H:i:s'),
                    'updated_at'          => date('Y-m-d H:i:s'),
                ];

                $db->table('assets')->insert($assetData);
                $newAssetId = $db->insertID();

                // Link finding to this asset
                $db->table('temuan')->where('id', $t['id'])->update(['asset_id' => $newAssetId]);

                // Create active edge relationship
                if ($prevAssetId !== null && $db->tableExists('asset_relationships')) {
                    $db->table('asset_relationships')->insert([
                        'parent_asset_id'    => $prevAssetId,
                        'child_asset_id'     => $newAssetId,
                        'source_asset_id'    => $prevAssetId,
                        'target_asset_id'    => $newAssetId,
                        'relationship_type'  => 'NETWORK',
                        'conductor_type'     => 'AAAC',
                        'conductor_size'     => '150 mm²',
                        'conductor_material' => 'ALUMINUM_ALLOY',
                        'installation_type'  => 'OVERHEAD',
                        'circuit_config'     => '3_PHASE',
                        'source'             => 'FIELD_FINDING_INGESTION',
                        'status'             => 'VERIFIED',
                        'is_active'          => 1,
                        'created_at'         => date('Y-m-d H:i:s'),
                    ]);
                }

                $prevAssetId = $newAssetId;
                $seqNo++;
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('assets')) {
            $db->table('assets')->where('created_at >=', '2026-08-23 00:00:00')->delete();
        }
    }
}
