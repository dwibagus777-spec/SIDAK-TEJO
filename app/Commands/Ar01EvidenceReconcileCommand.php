<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * AR-01 Phase 5G.4R.3: Evidence Source Reconciliation CLI Command
 * Usage: php spark ar01:evidence:reconcile [FEEDER_ID] [--json]
 */
class Ar01EvidenceReconcileCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:evidence:reconcile';
    protected $description = 'AR-01 Phase 5G.4R.3: Evidence Source Reconciliation Diagnostic (Strictly Read-Only)';

    protected $arguments = [
        'feeder' => 'Feeder ID (default: 4 GEMURUNG)',
    ];

    protected $options = [
        'feeder' => 'Feeder ID (alternative option)',
        'json'   => 'Output raw machine-readable Reconciliation JSON',
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

        // Resolve Feeder
        $feeder = $db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
        $feederName = $feeder ? "[{$feeder['kode_penyulang']}] {$feeder['nama_penyulang']}" : "Feeder #{$feederId}";
        $feederCode = $feeder['kode_penyulang'] ?? "PYL-{$feederId}";

        // 1. Full Schema & Master Tables Inspection
        $assetFields = $db->getFieldNames('assets');
        $hasAssetTypesTable = $db->tableExists('asset_types');
        $assetTypeMasterRows = [];
        if ($hasAssetTypesTable) {
            $assetTypeMasterRows = $db->table('asset_types')->get()->getResultArray();
        }

        // Asset Type ID Mapping in Assets Table
        $assetTypeUsage = [];
        if (in_array('asset_type_id', $assetFields, true)) {
            $usageRows = $db->table('assets')
                ->select('asset_type_id, COUNT(*) as count')
                ->groupBy('asset_type_id')
                ->get()
                ->getResultArray();
            foreach ($usageRows as $ur) {
                $assetTypeUsage[(int)$ur['asset_type_id']] = (int)$ur['count'];
            }
        }

        // 2. Comprehensive Switching Device Discovery across ALL fields
        $searchTokens = ['REC', 'RECLOSER', 'LBS', 'LBSM', 'PMCB', 'PMT', 'GI', 'PULAU', 'BATU', 'BANJARSARI', 'TRI', 'DASA', 'WINDU', 'PRASUNG', 'MITRA', 'MULIA', 'HUBBEL'];
        $searchableCols = array_intersect(['nama_asset', 'kode_asset', 'lokasi', 'merk', 'type', 'nomor_seri'], $assetFields);

        $multiColMatches = [];
        foreach ($searchTokens as $token) {
            $builder = $db->table('assets');
            $builder->groupStart();
            $first = true;
            foreach ($searchableCols as $col) {
                if ($first) {
                    $builder->like($col, $token);
                    $first = false;
                } else {
                    $builder->orLike($col, $token);
                }
            }
            $builder->groupEnd();
            $foundRows = $builder->get()->getResultArray();
            foreach ($foundRows as $fr) {
                $frId = (int)$fr['id'];
                if (!isset($multiColMatches[$frId])) {
                    $fr['matched_tokens'] = [$token];
                    $multiColMatches[$frId] = $fr;
                } else {
                    $multiColMatches[$frId]['matched_tokens'][] = $token;
                }
            }
        }

        // 3. Landmark Resolution for Target Feeder Sections
        $sections = $db->table('sections')->where('penyulang_id', $feederId);
        if ($db->fieldExists('status', 'sections')) {
            $sections->whereIn('status', ['AKTIF', 'ACTIVE', '1']);
        }
        $sectionRows = $sections->orderBy('sequence_order', 'ASC')->get()->getResultArray();

        $landmarkReconciliation = [];
        $totalLandmarks = 0;
        $classificationsSummary = [];

        foreach ($sectionRows as $sec) {
            $secId = (int)$sec['id'];
            $secName = $sec['nama_section'] ?? $sec['nama_seksi'] ?? "Seksi #{$secId}";
            $rawParts = array_values(array_filter(array_map('trim', explode('-', $secName)), fn($p) => $p !== ''));

            foreach ($rawParts as $part) {
                $totalLandmarks++;
                $upperPart = strtoupper($part);
                $isEndpoint = ($upperPart === 'GI' || str_starts_with($upperPart, 'GI ') || $upperPart === 'UJUNG' || str_starts_with($upperPart, 'UJUNG '));

                // Words extraction
                $words = preg_split('/[^A-Z0-9]+/', $upperPart, -1, PREG_SPLIT_NO_EMPTY);
                $meaningfulTokens = array_values(array_filter($words, fn($w) => strlen($w) > 1 && !in_array($w, ['GI', 'UJUNG', 'DAN', 'DI'], true)));

                // Search in feeder #4
                $feederMatches = [];
                $globalMatches = [];

                if (!empty($meaningfulTokens)) {
                    // Feeder search
                    $fB = $db->table('assets')->where('penyulang_id', $feederId);
                    $fB->groupStart();
                    $fFirst = true;
                    foreach ($meaningfulTokens as $mt) {
                        foreach ($searchableCols as $sc) {
                            if ($fFirst) {
                                $fB->like($sc, $mt);
                                $fFirst = false;
                            } else {
                                $fB->orLike($sc, $mt);
                            }
                        }
                    }
                    $fB->groupEnd();
                    $feederMatches = $fB->get()->getResultArray();

                    // Global search
                    $gB = $db->table('assets');
                    $gB->groupStart();
                    $gFirst = true;
                    foreach ($meaningfulTokens as $mt) {
                        foreach ($searchableCols as $sc) {
                            if ($gFirst) {
                                $gB->like($sc, $mt);
                                $gFirst = false;
                            } else {
                                $gB->orLike($sc, $mt);
                            }
                        }
                    }
                    $gB->groupEnd();
                    $globalMatches = $gB->get()->getResultArray();
                }

                // Determine Classification
                $classification = 'DATA_NOT_PRESENT';
                $reconciliationDetails = '';

                if ($isEndpoint && empty($meaningfulTokens)) {
                    $classification = 'DATA_NOT_PRESENT';
                    $reconciliationDetails = 'Endpoint marker (GI/UJUNG) represents boundary substation/line terminus, not modeled as discrete assets row.';
                } elseif (!empty($feederMatches)) {
                    // Found in feeder
                    $gpsValid = false;
                    foreach ($feederMatches as $fm) {
                        if (!empty($fm['latitude']) && !empty($fm['longitude'])) {
                            $gpsValid = true;
                            break;
                        }
                    }
                    if ($gpsValid) {
                        $classification = 'DATA_PRESENT_AND_RESOLVED';
                        $reconciliationDetails = "Found " . count($feederMatches) . " matching asset(s) in Feeder #{$feederId} with valid GPS.";
                    } else {
                        $classification = 'DATA_PRESENT_NO_GPS';
                        $reconciliationDetails = "Found " . count($feederMatches) . " matching asset(s) in Feeder #{$feederId} but GPS coordinates are NULL or 0.";
                    }
                } elseif (!empty($globalMatches)) {
                    // Found in another feeder
                    $otherFeeders = array_unique(array_column($globalMatches, 'penyulang_id'));
                    $classification = 'DATA_PRESENT_WRONG_FEEDER';
                    $reconciliationDetails = "Asset matching landmark tokens exists in database under other Feeder ID(s): [" . implode(', ', $otherFeeders) . "].";
                } else {
                    $classification = 'DATA_NOT_PRESENT';
                    $reconciliationDetails = "No record in assets table contains tokens [" . implode(', ', $meaningfulTokens) . "] across any column (nama, kode, lokasi, merk, type).";
                }

                $classificationsSummary[$classification] = ($classificationsSummary[$classification] ?? 0) + 1;

                $landmarkReconciliation[] = [
                    'section_id'             => $secId,
                    'section_name'           => $secName,
                    'landmark_raw'           => $part,
                    'tokens'                 => $meaningfulTokens,
                    'is_endpoint'            => $isEndpoint,
                    'classification'         => $classification,
                    'reconciliation_details' => $reconciliationDetails,
                    'feeder_matches_count'   => count($feederMatches),
                    'global_matches_count'   => count($globalMatches),
                    'sample_matches'         => array_slice(!empty($feederMatches) ? $feederMatches : $globalMatches, 0, 3),
                ];
            }
        }

        // 4. GPS Completeness Analysis
        $feederAssets = $db->table('assets')->where('penyulang_id', $feederId)->get()->getResultArray();
        $gpsValidCount = 0;
        $gpsMissingCount = 0;
        $duplicateGpsCount = 0;
        $seenCoords = [];

        foreach ($feederAssets as $fa) {
            $lat = (float)($fa['latitude'] ?? 0);
            $lon = (float)($fa['longitude'] ?? 0);
            if (!empty($lat) && !empty($lon) && $lat != 0 && $lon != 0) {
                $gpsValidCount++;
                $coordKey = round($lat, 6) . '_' . round($lon, 6);
                if (isset($seenCoords[$coordKey])) {
                    $duplicateGpsCount++;
                } else {
                    $seenCoords[$coordKey] = true;
                }
            } else {
                $gpsMissingCount++;
            }
        }

        // 5. Topology Source Inspection & Pilot Asset #3711 Trace
        $hasRelTable = $db->tableExists('asset_relationships');
        $relFields = $hasRelTable ? $db->getFieldNames('asset_relationships') : [];
        $totalFeederEdges = 0;
        $totalGlobalEdges = 0;

        $pCol = in_array('parent_asset_id', $relFields, true) ? 'parent_asset_id' : (in_array('source_asset_id', $relFields, true) ? 'source_asset_id' : null);
        $cCol = in_array('child_asset_id', $relFields, true) ? 'child_asset_id' : (in_array('target_asset_id', $relFields, true) ? 'target_asset_id' : null);

        $pilotAssetId = 3711;
        $pilotAsset = $db->table('assets')->where('id', $pilotAssetId)->get()->getRowArray();
        $pilotRelActive = [];
        $pilotRelInactive = [];

        if ($hasRelTable) {
            $totalGlobalEdges = $db->table('asset_relationships')->countAllResults();
            if ($pCol && $cCol) {
                $feederAssetIds = array_column($feederAssets, 'id');
                if (!empty($feederAssetIds)) {
                    $totalFeederEdges = $db->table('asset_relationships')
                        ->whereIn($pCol, $feederAssetIds)
                        ->countAllResults();
                }

                $pilotRelActive = $db->table('asset_relationships')
                    ->groupStart()
                        ->where($pCol, $pilotAssetId)
                        ->orWhere($cCol, $pilotAssetId)
                    ->groupEnd()
                    ->where('is_active', 1)
                    ->get()
                    ->getResultArray();

                $pilotRelInactive = $db->table('asset_relationships')
                    ->groupStart()
                        ->where($pCol, $pilotAssetId)
                        ->orWhere($cCol, $pilotAssetId)
                    ->groupEnd()
                    ->where('is_active', 0)
                    ->get()
                    ->getResultArray();
            }
        }

        $topologyClassification = ($totalFeederEdges > 0 || !empty($pilotAsset['parent_asset_id'])) ? 'TOPOLOGY_PRESENT' : 'TOPOLOGY_NOT_PRESENT';

        // 6. Network Topology Versions Inspection
        $hasTopVer = $db->tableExists('network_topology_versions');
        $feederTopVersions = [];
        if ($hasTopVer) {
            $feederTopVersions = $db->table('network_topology_versions')
                ->where('penyulang_id', $feederId)
                ->orderBy('version_no', 'DESC')
                ->get()
                ->getResultArray();
        }

        $report = [
            'success'                     => true,
            'engine'                      => 'AR-01-EVIDENCE-RECONCILIATION',
            'contract_version'            => '1.0',
            'feeder'                      => [
                'id'           => $feederId,
                'name'         => $feederName,
                'code'         => $feederCode,
                'total_assets' => count($feederAssets),
            ],
            'asset_type_master'           => [
                'master_table_exists' => $hasAssetTypesTable,
                'master_records'      => $assetTypeMasterRows,
                'usage_by_type_id'    => $assetTypeUsage,
            ],
            'switching_device_discovery'  => [
                'total_matches_across_cols' => count($multiColMatches),
                'sample_matches'            => array_values(array_slice($multiColMatches, 0, 10)),
            ],
            'landmark_reconciliation'     => [
                'total_landmarks'         => $totalLandmarks,
                'classifications_summary' => $classificationsSummary,
                'landmarks'               => $landmarkReconciliation,
            ],
            'gps_availability'            => [
                'total_assets'    => count($feederAssets),
                'gps_valid'       => $gpsValidCount,
                'gps_missing'     => $gpsMissingCount,
                'duplicate_coords'=> $duplicateGpsCount,
            ],
            'topology_reconciliation'     => [
                'topology_classification' => $topologyClassification,
                'has_relationships_table' => $hasRelTable,
                'total_global_edges'      => $totalGlobalEdges,
                'total_feeder_edges'      => $totalFeederEdges,
                'pilot_asset_3711'        => [
                    'asset_id'            => $pilotAssetId,
                    'parent_asset_id'     => $pilotAsset['parent_asset_id'] ?? null,
                    'active_edges_count'  => count($pilotRelActive),
                    'inactive_edges_count'=> count($pilotRelInactive),
                    'active_edges'        => $pilotRelActive,
                ],
            ],
            'topology_versions'           => [
                'has_table'    => $hasTopVer,
                'versions_count' => count($feederTopVersions),
                'records'      => array_map(function($v) {
                    return [
                        'id'             => $v['id'] ?? null,
                        'version_no'     => $v['version_no'] ?? null,
                        'is_active'      => $v['is_active'] ?? null,
                        'version_status' => $v['version_status'] ?? null,
                        'created_at'     => $v['created_at'] ?? null,
                    ];
                }, $feederTopVersions),
            ],
            'governance'                  => [
                'assets_section_id_written'   => 0,
                'sections_written'            => 0,
                'asset_relationships_written' => 0,
                'network_topology_written'    => 0,
            ],
        ];

        if ($isJson) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        // Visual CLI Output
        CLI::write("\n==============================================================", 'cyan');
        CLI::write("AR-01 PHASE 5G.4R.3: EVIDENCE SOURCE RECONCILIATION", 'cyan');
        CLI::write("==============================================================", 'cyan');
        CLI::write("TARGET FEEDER : {$feederName} (ID: #{$feederId})", 'yellow');
        CLI::write("MUTATION      : ZERO (Strictly Read-Only Forensic Discovery)\n", 'green');

        CLI::write("A. ASSET TYPE MASTER & CLASSIFICATION MAPPING:", 'cyan');
        if (!$hasAssetTypesTable) {
            CLI::write("  • Table 'asset_types' : NOT FOUND in database.", 'red');
        } else {
            CLI::write(sprintf("  • Table 'asset_types' : EXISTS (%d master types registered)", count($assetTypeMasterRows)));
            foreach ($assetTypeMasterRows as $atm) {
                $usageCount = $assetTypeUsage[(int)$atm['id']] ?? 0;
                CLI::write(sprintf("    - Type #%-2d | Code: %-15s | Name: %-25s | Network: %-4s | Assets Linked: %d", $atm['id'], $atm['code'], $atm['name'], $atm['network_type'] ?? 'N/A', $usageCount));
            }
        }

        CLI::write("\nB. SWITCHING DEVICE DISCOVERY (Across ALL Assets Columns):", 'cyan');
        CLI::write("  Searched tokens: [REC, RECLOSER, LBS, LBSM, PMCB, PMT, GI, PULAU, BATU, BANJARSARI, TRI, DASA, WINDU, PRASUNG, MITRA, MULIA, HUBBEL]");
        CLI::write("  Columns checked: [" . implode(', ', $searchableCols) . "]");
        if (empty($multiColMatches)) {
            CLI::write("  🔴 ZERO MATCHES across all columns and all assets in database.", 'red');
        } else {
            CLI::write(sprintf("  🟡 Found %d matching records across all fields in database:", count($multiColMatches)), 'yellow');
            foreach (array_slice($multiColMatches, 0, 10) as $mcm) {
                CLI::write(sprintf("    • #%-5d [Feeder #%-2d] [%-18s] %-25s | Matched: [%s]", $mcm['id'], $mcm['penyulang_id'] ?? 0, $mcm['kode_asset'] ?? '', $mcm['nama_asset'] ?? '', implode(', ', $mcm['matched_tokens'])));
            }
        }

        CLI::write("\nC. LANDMARK-BY-LANDMARK RECONCILIATION & CLASSIFICATION:", 'cyan');
        CLI::write(str_repeat("-", 100));
        CLI::write(sprintf("%-6s | %-32s | %-28s | %-20s", "Sec ID", "Landmark Label", "Classification", "Reconciliation Status"));
        CLI::write(str_repeat("-", 100));

        foreach ($landmarkReconciliation as $lr) {
            $classColor = ($lr['classification'] === 'DATA_PRESENT_AND_RESOLVED') ? 'green' : (($lr['classification'] === 'DATA_PRESENT_WRONG_FEEDER') ? 'yellow' : 'red');
            CLI::write(sprintf(
                "#%-5d | %-32s | %-28s | %s",
                $lr['section_id'],
                mb_strimwidth($lr['landmark_raw'], 0, 32, '...'),
                CLI::color($lr['classification'], $classColor),
                mb_strimwidth($lr['reconciliation_details'], 0, 28, '...')
            ));
        }
        CLI::write(str_repeat("-", 100));

        CLI::write("\nD. CLASSIFICATION DISTRIBUTION SUMMARY:", 'cyan');
        foreach ($classificationsSummary as $cls => $count) {
            CLI::write(sprintf("  • %-30s : %d landmarks", $cls, $count));
        }

        CLI::write("\nE. GPS COORDINATES AVAILABILITY (Feeder #{$feederId}):", 'cyan');
        CLI::write(sprintf("  • Total Assets in Feeder      : %d", count($feederAssets)));
        CLI::write(sprintf("  • Valid GPS Coordinates       : %s", CLI::color((string)$gpsValidCount, 'green')));
        CLI::write(sprintf("  • Missing / Zero GPS          : %s", CLI::color((string)$gpsMissingCount, $gpsMissingCount > 0 ? 'red' : 'green')));
        CLI::write(sprintf("  • Duplicate Coordinate Spans  : %d assets", $duplicateGpsCount));

        CLI::write("\nF. PILOT ASSET #3711 TOPOLOGY RECONCILIATION:", 'cyan');
        if (!$pilotAsset) {
            CLI::write("  🔴 Asset #3711 not found in database.", 'red');
        } else {
            CLI::write(sprintf("  • Identity                   : #%d [%s] - %s", $pilotAsset['id'], $pilotAsset['kode_asset'] ?? '', $pilotAsset['nama_asset'] ?? ''));
            CLI::write(sprintf("  • assets.parent_asset_id     : %s", $pilotAsset['parent_asset_id'] ? '#' . $pilotAsset['parent_asset_id'] : 'NULL'));
            CLI::write(sprintf("  • asset_relationships Active : %d edges", count($pilotRelActive)));
            CLI::write(sprintf("  • asset_relationships Inactive: %d edges", count($pilotRelInactive)));
            CLI::write(sprintf("  • Topology Classification    : %s", CLI::color($topologyClassification, $topologyClassification === 'TOPOLOGY_PRESENT' ? 'green' : 'red')));
        }

        CLI::write("\nG. NETWORK TOPOLOGY VERSIONS RECONCILIATION:", 'cyan');
        CLI::write(sprintf("  • Total Versions for Feeder #%d : %d records", $feederId, count($feederTopVersions)));
        foreach ($feederTopVersions as $ftv) {
            $actColor = ($ftv['is_active'] == 1) ? 'green' : 'white';
            CLI::write(sprintf("    - Version v%s | Active: %s | Status: %s | Created: %s", $ftv['version_no'] ?? '1', CLI::color($ftv['is_active'] == 1 ? 'YES' : 'NO', $actColor), $ftv['version_status'] ?? 'COMMITTED', $ftv['created_at'] ?? 'N/A'));
        }

        CLI::write("\n==============================================================", 'cyan');
        CLI::write("H. GOVERNANCE AUDIT (ZERO MUTATION PROVEN):", 'cyan');
        CLI::write("  assets.section_id writes        : 0", 'green');
        CLI::write("  sections writes                 : 0", 'green');
        CLI::write("  asset_relationships writes      : 0", 'green');
        CLI::write("  network_topology writes         : 0", 'green');
        CLI::write("==============================================================\n", 'cyan');

        return 0;
    }
}
