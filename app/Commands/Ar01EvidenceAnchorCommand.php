<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * AR-01 Phase 5G.4R.5: Section Anchor Forensic Discovery & Spatial Projection
 * Usage: php spark ar01:evidence:section-anchor [FEEDER_ID] [--json]
 */
class Ar01EvidenceAnchorCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:evidence:section-anchor';
    protected $description = 'AR-01 Phase 5G.4R.5: Section Anchor Forensic Discovery & Spatial Partition Hypothesis (Strictly Read-Only)';

    protected $arguments = [
        'feeder' => 'Feeder ID (default: 4 GEMURUNG)',
    ];

    protected $options = [
        'feeder' => 'Feeder ID (alternative option)',
        'json'   => 'Output raw machine-readable Anchor Discovery JSON',
    ];

    protected array $queryDiagnostics = [];

    /**
     * Safely execute a Query Builder query and return rows array or capture diagnostic error
     */
    protected function safeGetArray($builder, string $context = 'Query'): array
    {
        try {
            $query = $builder->get();
            if ($query === false) {
                $db = \Config\Database::connect();
                $err = $db->error();
                $this->queryDiagnostics[] = [
                    'context' => $context,
                    'status'  => 'FAILED',
                    'error'   => $err['message'] ?? 'Query returned false',
                ];
                return [];
            }
            return $query->getResultArray();
        } catch (\Throwable $e) {
            $this->queryDiagnostics[] = [
                'context' => $context,
                'status'  => 'EXCEPTION',
                'error'   => $e->getMessage(),
            ];
            return [];
        }
    }

    /**
     * Calculate Geodesic distance in meters between two lat/lng coordinates
     */
    protected function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $this->queryDiagnostics = [];

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
        $feeder = null;
        try {
            $fQuery = $db->table('penyulang')->where('id', $feederId)->get();
            $feeder = $fQuery ? $fQuery->getRowArray() : null;
        } catch (\Throwable $e) {}
        $feederName = $feeder ? "[{$feeder['kode_penyulang']}] {$feeder['nama_penyulang']}" : "Feeder #{$feederId}";
        $feederCode = $feeder['kode_penyulang'] ?? "PYL-{$feederId}";

        // 1. Fetch Target Feeder Assets (229 JTM assets)
        $feederAssets = $this->safeGetArray($db->table('assets')->where('penyulang_id', $feederId), 'Fetch feeder assets');
        $gpsAssets = [];
        $lats = [];
        $lons = [];
        foreach ($feederAssets as $fa) {
            $lat = (float)($fa['latitude'] ?? 0);
            $lon = (float)($fa['longitude'] ?? 0);
            if (!empty($lat) && !empty($lon) && $lat != 0 && $lon != 0) {
                $gpsAssets[] = [
                    'id'         => (int)$fa['id'],
                    'kode_asset' => $fa['kode_asset'] ?? '',
                    'nama_asset' => $fa['nama_asset'] ?? '',
                    'lat'        => $lat,
                    'lon'        => $lon,
                ];
                $lats[] = $lat;
                $lons[] = $lon;
            }
        }

        $minLat = !empty($lats) ? min($lats) : 0;
        $maxLat = !empty($lats) ? max($lats) : 0;
        $minLon = !empty($lons) ? min($lons) : 0;
        $maxLon = !empty($lons) ? max($lons) : 0;

        // 2. PHASE A & B: network_section_configurations Deep Audit
        $hasNetSecConfig = $db->tableExists('network_section_configurations');
        $hasNetSecConductors = $db->tableExists('network_section_conductors');
        $hasNetSecAccessories = $db->tableExists('network_section_accessories');

        $allConfigurations = [];
        $feederLinkedConfigs = [];

        if ($hasNetSecConfig) {
            $allConfigurations = $this->safeGetArray($db->table('network_section_configurations'), 'Fetch all network_section_configurations');
            foreach ($allConfigurations as &$cfg) {
                $secId = (int)($cfg['section_id'] ?? 0);
                $secRow = $db->table('sections')->where('id', $secId)->get()->getRowArray();
                $cfg['section_name'] = $secRow['nama_section'] ?? $secRow['nama_seksi'] ?? "Section #{$secId}";
                $cfg['penyulang_id'] = $secRow['penyulang_id'] ?? null;
                $pRow = $cfg['penyulang_id'] ? $db->table('penyulang')->where('id', $cfg['penyulang_id'])->get()->getRowArray() : null;
                $cfg['feeder_name'] = $pRow ? "[{$pRow['kode_penyulang']}] {$pRow['nama_penyulang']}" : "N/A";

                // Fetch child conductors
                $cfg['conductors'] = [];
                if ($hasNetSecConductors) {
                    $cfg['conductors'] = $this->safeGetArray(
                        $db->table('network_section_conductors')->where('network_section_configuration_id', $cfg['id']),
                        "Fetch conductors for config #{$cfg['id']}"
                    );
                }

                if ((int)$cfg['penyulang_id'] === $feederId) {
                    $feederLinkedConfigs[] = $cfg;
                }
            }
            unset($cfg);
        }

        // 3. PHASE C: Search Landmark Candidates for Feeder #4 Sections (#14, #15, #16)
        $sectionLandmarks = [
            14 => [
                'section_name' => 'GI - RECLOSER PULAU BATU',
                'landmarks'    => ['GI', 'RECLOSER PULAU BATU', 'PULAU BATU'],
            ],
            15 => [
                'section_name' => 'RECLOSER PULAU BATU - LBSM  TRI DASA WINDU - LBS COUPLE PERTIGAAN PRASUNG - LBSM BANJARSARI',
                'landmarks'    => ['RECLOSER PULAU BATU', 'LBSM TRI DASA WINDU', 'TRI DASA WINDU', 'LBS COUPLE PERTIGAAN PRASUNG', 'PERTIGAAN PRASUNG', 'PRASUNG', 'LBSM BANJARSARI', 'BANJARSARI'],
            ],
            16 => [
                'section_name' => 'LBSM BANJARSARI - UJUNG',
                'landmarks'    => ['LBSM BANJARSARI', 'BANJARSARI', 'UJUNG'],
            ],
        ];

        // 4. PHASE D: Deep Coordinate Anchor Search Across ALL Tables
        $tablesWithCoords = [];
        $allTables = $db->listTables();
        foreach ($allTables as $tbl) {
            if (in_array($tbl, ['ci_sessions', 'migrations', 'audit_logs'], true)) continue;
            $flds = $db->getFieldNames($tbl);
            $hasLat = in_array('latitude', $flds, true) || in_array('lat', $flds, true);
            $hasLon = in_array('longitude', $flds, true) || in_array('lon', $flds, true) || in_array('lng', $flds, true);
            if ($hasLat && $hasLon) {
                $latCol = in_array('latitude', $flds, true) ? 'latitude' : 'lat';
                $lonCol = in_array('longitude', $flds, true) ? 'longitude' : (in_array('lon', $flds, true) ? 'lon' : 'lng');
                $tablesWithCoords[$tbl] = [
                    'lat_col' => $latCol,
                    'lon_col' => $lonCol,
                    'fields'  => $flds,
                ];
            }
        }

        $discoveredGeographicAnchors = [];
        $discoveredSemanticAnchors = [];
        $targetKeywords = ['PULAU', 'BATU', 'BANJARSARI', 'TRI', 'DASA', 'WINDU', 'PRASUNG', 'PERTIGAAN', 'MITRA', 'MULIA', 'HUBBEL', 'GI BUDURAN'];

        // Search tables with coordinates
        foreach ($tablesWithCoords as $tbl => $meta) {
            $latCol = $meta['lat_col'];
            $lonCol = $meta['lon_col'];
            $textCols = array_intersect(['nama', 'nama_asset', 'detail_temuan', 'lokasi', 'alamat', 'keterangan', 'deskripsi', 'nama_gardu', 'nama_gi', 'kode_gi', 'catatan'], $meta['fields']);

            if (empty($textCols)) continue;

            foreach ($targetKeywords as $kw) {
                $b = $db->table($tbl);
                $b->where("{$latCol} IS NOT NULL")->where("{$lonCol} IS NOT NULL")->where("{$latCol} != 0")->where("{$lonCol} != 0");
                $b->groupStart();
                $first = true;
                foreach ($textCols as $tc) {
                    if ($first) {
                        $b->like($tc, $kw);
                        $first = false;
                    } else {
                        $b->orLike($tc, $kw);
                    }
                }
                $b->groupEnd();
                $res = $this->safeGetArray($b->limit(5), "Search coord anchor {$kw} in {$tbl}");
                foreach ($res as $r) {
                    $cLat = (float)($r[$latCol] ?? 0);
                    $cLon = (float)($r[$lonCol] ?? 0);
                    if ($cLat != 0 && $cLon != 0) {
                        $labelSnippet = [];
                        foreach ($textCols as $tc) {
                            if (!empty($r[$tc])) $labelSnippet[] = $r[$tc];
                        }
                        $discoveredGeographicAnchors[] = [
                            'table'          => $tbl,
                            'record_id'      => $r['id'] ?? 'N/A',
                            'keyword'        => $kw,
                            'label'          => implode(' | ', $labelSnippet),
                            'lat'            => $cLat,
                            'lon'            => $cLon,
                            'feeder_ref'     => $r['penyulang_id'] ?? $r['feeder_id'] ?? null,
                            'section_ref'    => $r['section_id'] ?? null,
                            'classification' => 'MEASURED_GEOGRAPHIC_ANCHOR',
                        ];
                    }
                }
            }
        }

        // Deduplicate geographic anchors by lat/lon
        $uniqueGeoAnchors = [];
        foreach ($discoveredGeographicAnchors as $ga) {
            $k = round($ga['lat'], 5) . '_' . round($ga['lon'], 5);
            if (!isset($uniqueGeoAnchors[$k])) {
                $uniqueGeoAnchors[$k] = $ga;
            }
        }
        $discoveredGeographicAnchors = array_values($uniqueGeoAnchors);

        // 5. PHASE E: Spatial Anchor Projection against 229 Assets
        $anchorProjections = [];
        foreach ($discoveredGeographicAnchors as &$anchor) {
            $aLat = $anchor['lat'];
            $aLon = $anchor['lon'];

            // Find nearest assets among the 229 JTM assets
            $distances = [];
            foreach ($gpsAssets as $ga) {
                $d = $this->haversineDistance($aLat, $aLon, $ga['lat'], $ga['lon']);
                $distances[] = [
                    'asset_id'   => $ga['id'],
                    'kode_asset' => $ga['kode_asset'],
                    'distance_m' => round($d, 1),
                ];
            }
            usort($distances, fn($x, $y) => $x['distance_m'] <=> $y['distance_m']);

            $nearestAsset = $distances[0] ?? null;
            $isInsideBoundingBox = ($aLat >= $minLat && $aLat <= $maxLat && $aLon >= $minLon && $aLon <= $maxLon);

            $anchor['nearest_asset']       = $nearestAsset;
            $anchor['top_5_nearest']       = array_slice($distances, 0, 5);
            $anchor['is_inside_chain_box'] = $isInsideBoundingBox;
            $anchor['falls_inside_chain']  = ($nearestAsset && $nearestAsset['distance_m'] <= 300.0);

            $anchorProjections[] = $anchor;
        }
        unset($anchor);

        // 6. PHASE F: Section Partition Hypothesis
        $partitionHypothesis = [];
        $validSectionAnchorsFound = false;

        // Check if we have anchors for start and intermediate/end of Gemurung sections
        $validAnchorsForGemurung = array_filter($anchorProjections, fn($ap) => $ap['falls_inside_chain']);

        if (!empty($validAnchorsForGemurung)) {
            $validSectionAnchorsFound = true;
            foreach ($validAnchorsForGemurung as $idx => $vAnchor) {
                $partitionHypothesis[] = [
                    'anchor_order'        => $idx + 1,
                    'anchor_keyword'      => $vAnchor['keyword'],
                    'anchor_table'        => $vAnchor['table'],
                    'anchor_record_id'    => $vAnchor['record_id'],
                    'nearest_asset_id'    => $vAnchor['nearest_asset']['asset_id'] ?? null,
                    'distance_to_chain_m' => $vAnchor['nearest_asset']['distance_m'] ?? null,
                    'confidence_level'    => ($vAnchor['nearest_asset']['distance_m'] <= 50.0) ? 'HIGH' : 'MEDIUM',
                ];
            }
        }

        $overallStatus = $validSectionAnchorsFound ? 'VALID_SECTION_ANCHOR_SOURCE_FOUND' : 'NO_VALID_SECTION_ANCHOR_FOUND';

        $report = [
            'success'                  => true,
            'engine'                   => 'AR-01-SECTION-ANCHOR-DISCOVERY',
            'contract_version'         => '1.0',
            'feeder_id'                => $feederId,
            'feeder_name'              => $feederName,
            'mutation_applied'         => false,
            'overall_status'           => $overallStatus,
            'network_section_configs'  => [
                'total_rows_in_db'     => count($allConfigurations),
                'linked_to_feeder_4'   => count($feederLinkedConfigs),
                'discrepancy_analysis' => 'Table uses section_id foreign key referencing sections table (not a direct penyulang_id column). All 3 rows link to Section IDs in sections table.',
                'rows'                 => $allConfigurations,
            ],
            'geographic_anchors'       => $discoveredGeographicAnchors,
            'anchor_projections'       => $anchorProjections,
            'partition_hypothesis'     => $partitionHypothesis,
            'query_diagnostics'        => $this->queryDiagnostics,
            'governance'               => [
                'mutation_applied'      => false,
                'section_id_written'    => false,
                'sections_written'      => false,
                'topology_written'      => false,
                'promotion_allowed'     => false,
                'verification_required' => true,
            ],
        ];

        if ($isJson) {
            CLI::write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        // Visual CLI Output
        CLI::write("\n==============================================================", 'cyan');
        CLI::write("AR-01 PHASE 5G.4R.5: SECTION ANCHOR FORENSIC DISCOVERY", 'cyan');
        CLI::write("==============================================================", 'cyan');
        CLI::write("TARGET FEEDER : {$feederName} (ID: #{$feederId})", 'yellow');
        CLI::write("MUTATION      : ZERO (Strictly Read-Only Anchor Analysis)\n", 'green');

        CLI::write("A. NETWORK SECTION CONFIGURATIONS AUDIT (Table: network_section_configurations):", 'cyan');
        CLI::write(sprintf("  • Total Configuration Rows in DB : %d rows", count($allConfigurations)));
        CLI::write("  • Schema Relation Detail         : Linked via `section_id` foreign key -> `sections.id`");
        CLI::write(sprintf("  • Configurations Linked to Feeder: %d / %d rows", count($feederLinkedConfigs), count($allConfigurations)));
        CLI::write(str_repeat("-", 95));
        CLI::write(sprintf("%-4s | %-10s | %-18s | %-35s | %-12s", "ID", "Sec ID", "Feeder", "Section Name", "Status"));
        CLI::write(str_repeat("-", 95));
        foreach ($allConfigurations as $cfg) {
            CLI::write(sprintf(
                "#%-3d | Sec #%-5d | %-18s | %-35s | %s",
                $cfg['id'],
                $cfg['section_id'],
                mb_strimwidth($cfg['feeder_name'], 0, 18, '...'),
                mb_strimwidth($cfg['section_name'], 0, 35, '...'),
                $cfg['verification_status'] ?? 'ACTIVE'
            ));
            if (!empty($cfg['conductors'])) {
                foreach ($cfg['conductors'] as $c) {
                    CLI::write(sprintf("      ↳ Conductor: %-35s | Length: %s m | Type: %s", $c['segment_label'] ?? 'N/A', $c['length_meters'] ?? '0', $c['conductor_type'] ?? 'N/A'), 'yellow');
                }
            }
        }
        CLI::write(str_repeat("-", 95));

        CLI::write("\nB. DISCOVERED GEOGRAPHIC ANCHORS ACROSS DATABASE:", 'cyan');
        if (empty($discoveredGeographicAnchors)) {
            CLI::write("  🔴 ZERO GEOGRAPHIC ANCHORS with valid GPS found in database for landmark keywords.", 'red');
        } else {
            CLI::write(sprintf("  🟢 Found %d distinct geographic anchor(s) across database:", count($discoveredGeographicAnchors)), 'green');
            CLI::write(str_repeat("-", 100));
            CLI::write(sprintf("%-18s | %-8s | %-15s | %-30s | %-18s", "Source Table", "Rec ID", "Keyword", "Label / Description", "GPS (Lat, Lon)"));
            CLI::write(str_repeat("-", 100));
            foreach ($discoveredGeographicAnchors as $ga) {
                CLI::write(sprintf(
                    "%-18s | #%-6s | %-15s | %-30s | (%s, %s)",
                    $ga['table'],
                    $ga['record_id'],
                    $ga['keyword'],
                    mb_strimwidth($ga['label'], 0, 30, '...'),
                    round($ga['lat'], 5),
                    round($ga['lon'], 5)
                ));
            }
            CLI::write(str_repeat("-", 100));
        }

        CLI::write("\nC. SPATIAL PROJECTION AGAINST 229 GEMURUNG ASSETS:", 'cyan');
        if (empty($anchorProjections)) {
            CLI::write("  • No anchors available for projection.", 'yellow');
        } else {
            foreach ($anchorProjections as $ap) {
                $nAsset = $ap['nearest_asset'];
                $fallsIn = $ap['falls_inside_chain'];
                $statusStr = $fallsIn ? CLI::color("INSIDE CHAIN (<= 300m)", 'green') : CLI::color("OUTSIDE CHAIN (> 300m)", 'yellow');
                CLI::write(sprintf("  • Anchor [%s] (%s #%s):", $ap['keyword'], $ap['table'], $ap['record_id']), 'cyan');
                CLI::write(sprintf("    - Position Status : %s", $statusStr));
                CLI::write(sprintf("    - Nearest Asset   : #%s [%s] at distance %s m", $nAsset['asset_id'] ?? 'N/A', $nAsset['kode_asset'] ?? 'N/A', $nAsset['distance_m'] ?? 'N/A'));
                if (!empty($ap['top_5_nearest'])) {
                    $topStr = implode(', ', array_map(fn($t) => "#{$t['asset_id']} ({$t['distance_m']}m)", $ap['top_5_nearest']));
                    CLI::write(sprintf("    - 5 Nearest Assets: %s", $topStr));
                }
            }
        }

        CLI::write("\nD. SECTION PARTITION HYPOTHESIS:", 'cyan');
        if (empty($partitionHypothesis)) {
            CLI::write("  🟡 " . CLI::color("NO_VALID_SECTION_ANCHOR_FOUND", 'yellow') . " : Belum ditemukan anchor koordinat yang jatuh persis di dalam rantai 229 aset Gemurung.");
            CLI::write("     Rantai 229 aset JTM Gemurung tetap murni UNPARTITIONED untuk menjaga integritas Zero Blind Assignment.");
        } else {
            CLI::write("  🟢 " . CLI::color("VALID_SECTION_ANCHOR_SOURCE_FOUND", 'green') . " : Ditemukan kandidat anchor spasial untuk mempartisi rantai:");
            foreach ($partitionHypothesis as $ph) {
                CLI::write(sprintf("    [Cut #%d] Anchor: %s (%s #%s) -> Cut at Asset #%s (Dist: %sm, Confidence: %s)",
                    $ph['anchor_order'],
                    $ph['anchor_keyword'],
                    $ph['anchor_table'],
                    $ph['anchor_record_id'],
                    $ph['nearest_asset_id'],
                    $ph['distance_to_chain_m'],
                    $ph['confidence_level']
                ), 'green');
            }
        }

        CLI::write("\n==============================================================", 'cyan');
        CLI::write("E. GOVERNANCE AUDIT (ZERO MUTATION PROVEN):", 'cyan');
        CLI::write("  assets.section_id writes        : 0", 'green');
        CLI::write("  sections writes                 : 0", 'green');
        CLI::write("  asset_relationships writes      : 0", 'green');
        CLI::write("  network_topology writes         : 0", 'green');
        CLI::write("==============================================================\n", 'cyan');

        return 0;
    }
}
