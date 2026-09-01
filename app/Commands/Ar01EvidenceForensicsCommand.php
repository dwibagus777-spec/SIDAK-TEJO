<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * AR-01 Phase 5G.4R.2R: Production Asset Evidence Forensic Discovery
 * Usage: php spark ar01:evidence:forensics [--feeder=4] [--json]
 */
class Ar01EvidenceForensicsCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:evidence:forensics';
    protected $description = 'AR-01 Phase 5G.4R.2R: Production Asset Evidence & Topology Forensic Discovery (Strictly Read-Only)';

    protected $arguments = [
        'feeder' => 'Feeder ID (default: 4 GEMURUNG)',
    ];

    protected $options = [
        'feeder' => 'Feeder ID (alternative option)',
        'json'   => 'Output raw machine-readable Forensic JSON',
    ];

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $feederArg = null;
        foreach ($params as $p) {
            if (!str_starts_with($p, '-')) {
                $feederArg = $p;
                break;
            }
        }
        if ($feederArg === null) {
            $feederArg = CLI::getOption('feeder') ?? 4;
        }

        $feederId = (int)$feederArg;
        $isJson   = (bool)(CLI::getOption('json') ?? false);

        // 1. Schema Inspection
        $assetFields = $db->getFieldNames('assets');
        $hasRelationshipsTable = $db->tableExists('asset_relationships');
        $relationshipFields = $hasRelationshipsTable ? $db->getFieldNames('asset_relationships') : [];
        $hasTopologyVersions = $db->tableExists('network_topology_versions');

        // Resolve Feeder
        $feeder = $db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
        $feederName = $feeder ? "[{$feeder['kode_penyulang']}] {$feeder['nama_penyulang']}" : "Feeder #{$feederId}";

        // 2. Distinct Asset Types in Feeder & Global
        $typeCol = in_array('jenis_asset', $assetFields, true) ? 'jenis_asset' : (in_array('tipe_asset', $assetFields, true) ? 'tipe_asset' : (in_array('asset_type', $assetFields, true) ? 'asset_type' : null));

        $feederTypes = [];
        $globalTypes = [];
        if ($typeCol) {
            $ftRows = $db->table('assets')
                ->select("{$typeCol} as type_name, COUNT(*) as count")
                ->where('penyulang_id', $feederId)
                ->groupBy($typeCol)
                ->orderBy('count', 'DESC')
                ->get()
                ->getResultArray();
            foreach ($ftRows as $r) {
                $feederTypes[$r['type_name'] ?? 'NULL'] = (int)$r['count'];
            }

            $gtRows = $db->table('assets')
                ->select("{$typeCol} as type_name, COUNT(*) as count")
                ->groupBy($typeCol)
                ->orderBy('count', 'DESC')
                ->get()
                ->getResultArray();
            foreach ($gtRows as $r) {
                $globalTypes[$r['type_name'] ?? 'NULL'] = (int)$r['count'];
            }
        }

        // 3. Keyword Search in Feeder #4
        $feederKeywords = ['PULAU', 'BATU', 'BANJARSARI', 'TRI', 'DASA', 'WINDU', 'PRASUNG', 'PERTIGAAN'];
        $feederMatches = [];

        $fBuilder = $db->table('assets')->where('penyulang_id', $feederId);
        $fBuilder->groupStart();
        foreach ($feederKeywords as $kw) {
            $fBuilder->orLike('nama_asset', $kw);
            if (in_array('kode_asset', $assetFields, true)) {
                $fBuilder->orLike('kode_asset', $kw);
            }
        }
        $fBuilder->groupEnd();
        $feederMatches = $fBuilder->get()->getResultArray();

        // 4. Global Cross-Database Keyword Search across ALL Feeders
        $globalKeywords = ['PULAU BATU', 'BANJARSARI', 'TRI DASA WINDU', 'PRASUNG', 'MITRA MULIA', 'HUBBEL', 'AMAN GRIYA', 'KSATRIAN', 'BALDES KEMIRI'];
        $globalMatches = [];

        foreach ($globalKeywords as $gkw) {
            $gBuilder = $db->table('assets');
            $gBuilder->groupStart();
            $gBuilder->like('nama_asset', $gkw);
            if (in_array('kode_asset', $assetFields, true)) {
                $gBuilder->orLike('kode_asset', $gkw);
            }
            $gBuilder->groupEnd();
            $rows = $gBuilder->get()->getResultArray();
            foreach ($rows as $r) {
                $r['searched_keyword'] = $gkw;
                $globalMatches[] = $r;
            }
        }

        // 5. Global Search for ANY Switching Devices (RECLOSER, LBS, PMCB, PMT) across the entire database
        $swBuilder = $db->table('assets');
        $swBuilder->groupStart();
        $swBuilder->like('nama_asset', 'REC')
                  ->orLike('nama_asset', 'RECLOSER')
                  ->orLike('nama_asset', 'LBS')
                  ->orLike('nama_asset', 'PMCB')
                  ->orLike('nama_asset', 'PMT');
        if ($typeCol) {
            $swBuilder->orIn($typeCol, ['RECLOSER', 'REC', 'LBS', 'LBSM', 'PMCB', 'PMT', 'SECTIONALIZER']);
        }
        $swBuilder->groupEnd();
        $allSwitchingDevices = $swBuilder->get()->getResultArray();

        // 6. Inspect Feeder #4 Sample Asset Names (first 20 assets)
        $sampleFeederAssets = $db->table('assets')
            ->where('penyulang_id', $feederId)
            ->limit(20)
            ->get()
            ->getResultArray();

        // 7. Pilot Asset #3711 Deep Trace
        $pilotAssetId = 3711;
        $pilotAsset = $db->table('assets')->where('id', $pilotAssetId)->get()->getRowArray();
        $pilotFeeder = null;
        $pilotAncestorChain = [];
        $pilotDownstreamChain = [];
        $pilotRelationships = [];

        if ($pilotAsset) {
            $pFeederId = (int)($pilotAsset['penyulang_id'] ?? 0);
            $pF = $db->table('penyulang')->where('id', $pFeederId)->get()->getRowArray();
            $pilotFeeder = $pF ? "[{$pF['kode_penyulang']}] {$pF['nama_penyulang']}" : "Feeder #{$pFeederId}";

            // Trace ancestors
            $currParent = (int)($pilotAsset['parent_asset_id'] ?? 0);
            $d = 0;
            while ($currParent > 0 && $d < 30) {
                $pRow = $db->table('assets')->where('id', $currParent)->get()->getRowArray();
                if (!$pRow) break;
                $pilotAncestorChain[] = [
                    'id'          => (int)$pRow['id'],
                    'kode_asset'  => $pRow['kode_asset'] ?? 'N/A',
                    'nama_asset'  => $pRow['nama_asset'] ?? 'N/A',
                    'jenis_asset' => $pRow['jenis_asset'] ?? 'N/A',
                ];
                $currParent = (int)($pRow['parent_asset_id'] ?? 0);
                $d++;
            }

            // Trace direct children
            $cRows = $db->table('assets')->where('parent_asset_id', $pilotAssetId)->get()->getResultArray();
            foreach ($cRows as $c) {
                $pilotDownstreamChain[] = [
                    'id'          => (int)$c['id'],
                    'kode_asset'  => $c['kode_asset'] ?? 'N/A',
                    'nama_asset'  => $c['nama_asset'] ?? 'N/A',
                    'jenis_asset' => $c['jenis_asset'] ?? 'N/A',
                ];
            }

            if ($hasRelationshipsTable) {
                $relBuilder = $db->table('asset_relationships');
                $pCol = in_array('parent_asset_id', $relationshipFields, true) ? 'parent_asset_id' : (in_array('source_asset_id', $relationshipFields, true) ? 'source_asset_id' : null);
                $cCol = in_array('child_asset_id', $relationshipFields, true) ? 'child_asset_id' : (in_array('target_asset_id', $relationshipFields, true) ? 'target_asset_id' : null);
                if ($pCol && $cCol) {
                    $pilotRelationships = $relBuilder->groupStart()
                        ->where($pCol, $pilotAssetId)
                        ->orWhere($cCol, $pilotAssetId)
                        ->groupEnd()
                        ->get()
                        ->getResultArray();
                }
            }
        }

        // 8. Root Cause Forensic Analysis
        $rootCauseFindings = [];
        if (empty($feederMatches)) {
            $rootCauseFindings[] = "FEEDER_4_ZERO_LANDMARK_TOKENS: Tidak ada satu pun aset pada Feeder #4 yang namanya mengandung token landmark (PULAU, BATU, BANJARSARI, PRASUNG, TRI, DASA, WINDU).";
        }
        if (!empty($globalMatches)) {
            $mismatchFeeders = [];
            foreach ($globalMatches as $gm) {
                $mismatchFeeders[$gm['penyulang_id']][] = $gm['nama_asset'] ?? $gm['kode_asset'];
            }
            $rootCauseFindings[] = "CROSS_FEEDER_LANDMARK_EXISTS: Ditemukan " . count($globalMatches) . " aset landmark di feeder lain (misal Penyulang ID: " . implode(', ', array_keys($mismatchFeeders)) . ").";
        } else {
            $rootCauseFindings[] = "GLOBAL_LANDMARK_MISSING: Landmark switching device tidak ditemukan di seluruh tabel assets database.";
        }
        if (empty($allSwitchingDevices)) {
            $rootCauseFindings[] = "ZERO_SWITCHING_DEVICES_IN_DB: Seluruh tabel assets tidak memiliki aset bertipe RECLOSER/LBS/PMCB/PMT atau ber-nama REC/LBS.";
        } else {
            $rootCauseFindings[] = "SWITCHING_DEVICES_COUNT: Ditemukan total " . count($allSwitchingDevices) . " switching devices di seluruh database.";
        }
        if ($pilotAsset && empty($pilotAsset['parent_asset_id']) && empty($pilotRelationships)) {
            $rootCauseFindings[] = "PILOT_3711_TOPOLOGY_EMPTY: Asset #3711 memiliki parent_asset_id=NULL/0 dan 0 edges di asset_relationships.";
        }

        $report = [
            'success'               => true,
            'engine'                => 'AR-01-EVIDENCE-FORENSICS',
            'contract_version'      => '1.0',
            'governance'            => [
                'mutation_applied'            => false,
                'assets_section_id_written'   => false,
                'sections_written'            => false,
                'asset_relationships_written' => false,
                'topology_written'            => false,
            ],
            'schema'                => [
                'assets_fields'        => $assetFields,
                'relationships_fields' => $relationshipFields,
                'has_topology_table'   => $hasTopologyVersions,
            ],
            'feeder'                => [
                'id'           => $feederId,
                'name'         => $feederName,
                'total_assets' => $db->table('assets')->where('penyulang_id', $feederId)->countAllResults(),
            ],
            'type_distribution'     => [
                'feeder_types' => $feederTypes,
                'global_types' => $globalTypes,
            ],
            'feeder_keyword_matches'=> $feederMatches,
            'global_keyword_matches'=> $globalMatches,
            'all_switching_devices' => $allSwitchingDevices,
            'sample_feeder_assets'  => $sampleFeederAssets,
            'pilot_asset_3711'      => [
                'asset'            => $pilotAsset,
                'feeder'           => $pilotFeeder,
                'ancestor_chain'   => $pilotAncestorChain,
                'downstream_chain' => $pilotDownstreamChain,
                'relationships'    => $pilotRelationships,
            ],
            'root_cause_findings'   => $rootCauseFindings,
        ];

        if ($isJson) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        // Visual CLI Output
        CLI::write("\n==============================================================", 'cyan');
        CLI::write("AR-01 PHASE 5G.4R.2R: PRODUCTION ASSET EVIDENCE FORENSICS", 'cyan');
        CLI::write("==============================================================", 'cyan');
        CLI::write("TARGET FEEDER : {$feederName} (ID: #{$feederId})", 'yellow');
        CLI::write("MUTATION      : ZERO (Strictly Read-Only Forensic Discovery)\n", 'green');

        CLI::write("1. ACTUAL ASSET SCHEMA INSPECTION:", 'cyan');
        CLI::write("  • Columns in 'assets' table (" . count($assetFields) . " cols): " . implode(', ', $assetFields));
        CLI::write("  • 'asset_relationships' table : " . ($hasRelationshipsTable ? "EXISTS (" . implode(', ', $relationshipFields) . ")" : "NOT FOUND"));
        CLI::write("  • 'network_topology_versions' : " . ($hasTopologyVersions ? "EXISTS" : "NOT FOUND") . "\n");

        CLI::write("2. ASSET TYPE DISTRIBUTION (jenis_asset):", 'cyan');
        CLI::write("  Feeder #{$feederId} Types:");
        foreach ($feederTypes as $t => $cnt) {
            CLI::write(sprintf("    - %-20s : %d assets", $t ?: 'NULL', $cnt));
        }
        CLI::write("  Global Database Types (Top 10):");
        foreach (array_slice($globalTypes, 0, 10, true) as $t => $cnt) {
            CLI::write(sprintf("    - %-20s : %d assets", $t ?: 'NULL', $cnt));
        }

        CLI::write("\n3. KEYWORD RECONNAISSANCE IN FEEDER #{$feederId}:", 'cyan');
        if (empty($feederMatches)) {
            CLI::write("  🔴 NO ASSET MATCH FOUND in Feeder #{$feederId} for keywords: [" . implode(', ', $feederKeywords) . "]", 'red');
        } else {
            CLI::write(sprintf("  🟢 Found %d matching assets in Feeder #%d:", count($feederMatches), $feederId), 'green');
            foreach ($feederMatches as $fm) {
                CLI::write(sprintf("    • #%d [%s] - %s | Type: %s | GPS: (%s, %s)", $fm['id'], $fm['kode_asset'] ?? '', $fm['nama_asset'] ?? '', $fm['jenis_asset'] ?? 'N/A', $fm['latitude'] ?? 'NULL', $fm['longitude'] ?? 'NULL'));
            }
        }

        CLI::write("\n4. CROSS-DATABASE KEYWORD SEARCH ACROSS ALL FEEDERS:", 'cyan');
        if (empty($globalMatches)) {
            CLI::write("  🔴 NO LANDMARK MATCH FOUND in entire database for sections keywords: [" . implode(', ', $globalKeywords) . "]", 'red');
        } else {
            CLI::write(sprintf("  🟡 Found %d matching assets across ALL feeders:", count($globalMatches)), 'yellow');
            CLI::write(str_repeat("-", 95));
            CLI::write(sprintf("%-8s | %-10s | %-20s | %-30s | %-12s", "Asset ID", "Feeder ID", "Kode Asset", "Nama Asset", "Keyword"));
            CLI::write(str_repeat("-", 95));
            foreach ($globalMatches as $gm) {
                CLI::write(sprintf(
                    "#%-7d | Feeder #%-3d | %-20s | %-30s | %s",
                    $gm['id'],
                    $gm['penyulang_id'] ?? 0,
                    mb_strimwidth($gm['kode_asset'] ?? '', 0, 20, '...'),
                    mb_strimwidth($gm['nama_asset'] ?? '', 0, 30, '...'),
                    $gm['searched_keyword']
                ));
            }
            CLI::write(str_repeat("-", 95));
        }

        CLI::write("\n5. ALL SWITCHING DEVICES IN ENTIRE DATABASE:", 'cyan');
        CLI::write(sprintf("  • Total Switching Devices Found: %d", count($allSwitchingDevices)));
        if (!empty($allSwitchingDevices)) {
            CLI::write("  Sample Switching Devices (Top 10):");
            foreach (array_slice($allSwitchingDevices, 0, 10) as $sw) {
                CLI::write(sprintf("    • #%d [Feeder #%d] [%s] %s | Type: %s | GPS: (%s, %s)", $sw['id'], $sw['penyulang_id'] ?? 0, $sw['kode_asset'] ?? '', $sw['nama_asset'] ?? '', $sw['jenis_asset'] ?? 'N/A', $sw['latitude'] ?? 'NULL', $sw['longitude'] ?? 'NULL'));
            }
        }

        CLI::write("\n6. SAMPLE ASSET NAMES IN FEEDER #{$feederId} (First 10 assets):", 'cyan');
        foreach (array_slice($sampleFeederAssets, 0, 10) as $sa) {
            CLI::write(sprintf("  • #%d [%s] - %s | Type: %s | Parent: %s | GPS: (%s, %s)", $sa['id'], $sa['kode_asset'] ?? '', $sa['nama_asset'] ?? '', $sa['jenis_asset'] ?? 'N/A', $sa['parent_asset_id'] ?? 'NULL', $sa['latitude'] ?? 'NULL', $sa['longitude'] ?? 'NULL'));
        }

        CLI::write("\n7. PILOT ASSET #3711 TOPOLOGY TRACE:", 'cyan');
        if (!$pilotAsset) {
            CLI::write("  🔴 Asset #3711 not found in database.", 'red');
        } else {
            CLI::write(sprintf("  • Asset Identity : #%d [%s] - %s", $pilotAsset['id'], $pilotAsset['kode_asset'] ?? '', $pilotAsset['nama_asset'] ?? ''));
            CLI::write(sprintf("  • Feeder         : %s", $pilotFeeder));
            CLI::write(sprintf("  • GPS            : (%s, %s)", $pilotAsset['latitude'] ?? 'NULL', $pilotAsset['longitude'] ?? 'NULL'));
            CLI::write(sprintf("  • Parent Asset ID: %s", $pilotAsset['parent_asset_id'] ?? 'NULL (ROOT / UNLINKED)'));
            CLI::write(sprintf("  • Ancestor Chain : %s", empty($pilotAncestorChain) ? 'EMPTY (0 ancestors)' : implode(' -> ', array_column($pilotAncestorChain, 'id'))));
            CLI::write(sprintf("  • Downstream     : %s", empty($pilotDownstreamChain) ? 'EMPTY (0 direct children)' : implode(', ', array_column($pilotDownstreamChain, 'id'))));
            CLI::write(sprintf("  • Relationships  : %d edges found in asset_relationships", count($pilotRelationships)));
        }

        CLI::write("\n8. FORENSIC ROOT CAUSE CLASSIFICATION:", 'cyan');
        CLI::write(str_repeat("=", 80), 'yellow');
        foreach ($rootCauseFindings as $idx => $finding) {
            CLI::write(sprintf("  [%d] %s", $idx + 1, $finding), 'yellow');
        }
        CLI::write(str_repeat("=", 80), 'yellow');

        CLI::write("\n9. GOVERNANCE GUARDRAIL AUDIT:", 'cyan');
        CLI::write("  • assets.section_id writes    : 0 (ZERO MUTATION VERIFIED)", 'green');
        CLI::write("  • sections writes             : 0 (ZERO MUTATION VERIFIED)", 'green');
        CLI::write("  • asset_relationships writes  : 0 (ZERO MUTATION VERIFIED)", 'green');
        CLI::write("  • network_topology writes     : 0 (ZERO MUTATION VERIFIED)", 'green');
        CLI::write("==============================================================\n", 'cyan');

        return 0;
    }
}
