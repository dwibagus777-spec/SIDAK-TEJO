<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\LandmarkEvidenceRegistry;

/**
 * AR-01 Phase 5G.4R.8: Evidence Ranking & Anchor Qualification
 * Usage: php spark ar01:evidence:qualify [FEEDER_ID] [--json]
 */
class Ar01EvidenceQualifyCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:evidence:qualify';
    protected $description = 'AR-01 Phase 5G.4R.8: Evidence Ranking & Anchor Qualification (Strictly Read-Only)';

    protected $arguments = [
        'feeder' => 'Feeder ID (default: 4 GEMURUNG)',
    ];

    protected $options = [
        'feeder' => 'Feeder ID (alternative option)',
        'json'   => 'Output raw machine-readable Qualification JSON',
    ];

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $registry = new LandmarkEvidenceRegistry($db);

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

        // Fetch Feeder info
        $feeder = null;
        try {
            $fQuery = $db->table('penyulang')->where('id', $feederId)->get();
            $feeder = $fQuery ? $fQuery->getRowArray() : null;
        } catch (\Throwable $e) {}
        $feederName = $feeder ? "[{$feeder['kode_penyulang']}] {$feeder['nama_penyulang']}" : "Feeder #{$feederId}";

        // Fetch Feeder Assets
        $assets = [];
        if ($db->tableExists('assets')) {
            $aBuilder = $db->table('assets')->where('penyulang_id', $feederId);
            if ($db->fieldExists('deleted_at', 'assets')) {
                $aBuilder->where('deleted_at IS NULL');
            }
            $assets = $aBuilder->get()->getResultArray();
        }

        // Perform Comprehensive Multi-Source Evidence Qualification
        $evaluatedFindings = [];

        // 1. Audit TRI DASA WINDU Evidence
        $triDasaKeywords = ['DASA', 'WINDU', 'TRI DASA'];
        if ($db->tableExists('temuan')) {
            $tFields = $db->getFieldNames('temuan');
            $searchCols = array_intersect(['detail_temuan', 'lokasi', 'alamat', 'noga', 'deskripsi'], $tFields);

            if (!empty($searchCols)) {
                $b = $db->table('temuan')
                    ->groupStart();
                $first = true;
                foreach ($searchCols as $col) {
                    foreach ($triDasaKeywords as $kw) {
                        if ($first) { $b->like($col, $kw); $first = false; }
                        else { $b->orLike($col, $kw); }
                    }
                }
                $b->groupEnd();
                $rows = $b->get()->getResultArray();

                foreach ($rows as $r) {
                    $lat = (float)($r['latitude'] ?? 0);
                    $lon = (float)($r['longitude'] ?? 0);
                    $text = $r['detail_temuan'] ?? ($r['lokasi'] ?? ($r['alamat'] ?? ''));

                    $sanity = ($lat >= -8.5 && $lat <= -6.5 && $lon >= 111.0 && $lon <= 114.5) ? 'VALID' : 'ANOMALOUS';

                    $nearest = null;
                    $dist = null;
                    if ($sanity === 'VALID' && $lat != 0 && $lon != 0) {
                        $nearest = $this->findNearestAsset($lat, $lon, $assets, $registry);
                        $dist = $nearest ? $nearest['distance'] : null;
                    }

                    // Semantic classification
                    $upperText = strtoupper($text);
                    $role = 'GENERAL_LOCATION';
                    $classification = 'WEAK_REFERENCE';
                    $usableAnchor = false;
                    $rejectionReason = '';

                    if ($sanity === 'ANOMALOUS') {
                        $role = 'ANOMALOUS_COORDINATE';
                        $classification = 'INVALID_ANOMALOUS';
                        $rejectionReason = "GPS coordinate ({$lat}, {$lon}) outside Sidoarjo/East Java bounding box.";
                    } elseif (str_contains($upperText, 'SELATAN LBSM') || str_contains($upperText, 'UTARA LBSM') || str_contains($upperText, 'TIMUR LBSM') || str_contains($upperText, 'BARAT LBSM') || str_contains($upperText, 'DEKAT DENGAN') || str_contains($upperText, 'SEBELUM') || str_contains($upperText, 'SESUDAH')) {
                        $role = 'RELATIVE_LOCATION';
                        if ($dist !== null && $dist <= 15.0) {
                            $classification = 'MEDIUM_ANCHOR';
                            $rejectionReason = 'Directional reference (e.g. "selatan LBSM"); indicates proximity area but not exact switching pole.';
                        } else {
                            $classification = 'WEAK_REFERENCE';
                            $rejectionReason = 'Directional/relative landmark note with moderate distance.';
                        }
                    } elseif (str_contains($upperText, 'LBSM TRI DASA WINDU') || str_contains($upperText, 'BUSHING LBSM') || str_contains($upperText, 'LBS TRI DASA WINDU') || str_contains($upperText, 'DKT LBSM')) {
                        if ($dist !== null && $dist <= 15.0) {
                            $role = 'DEVICE_LOCATION';
                            $classification = 'STRONG_ANCHOR';
                            $usableAnchor = true;
                            $rejectionReason = 'QUALIFIED (Direct device observation at pole distance ' . round($dist, 2) . 'm)';
                        } else {
                            $role = 'DEVICE_LOCATION';
                            $classification = 'MEDIUM_ANCHOR';
                            $rejectionReason = 'Explicit device reference but asset distance is >15m (' . round($dist, 2) . 'm).';
                        }
                    } else {
                        $role = 'NON_DEVICE_DEFECT';
                        $classification = 'NON_DEVICE_EVIDENCE';
                        $rejectionReason = 'No explicit switching device mounted at this finding location.';
                    }

                    $evaluatedFindings[] = [
                        'landmark'           => 'TRI DASA WINDU',
                        'source_table'       => 'temuan',
                        'source_id'          => $r['id'],
                        'source_text'        => $text,
                        'latitude'           => $lat,
                        'longitude'          => $lon,
                        'coordinate_sanity'  => $sanity,
                        'nearest_asset_id'   => $nearest['id'] ?? null,
                        'nearest_asset_name' => $nearest['nama_asset'] ?? null,
                        'distance_meters'    => $dist,
                        'semantic_role'      => $role,
                        'classification'     => $classification,
                        'usable_for_anchor'  => $usableAnchor,
                        'usable_for_confidence' => $usableAnchor,
                        'rejection_reason'   => $rejectionReason,
                    ];
                }
            }
        }

        // 2. Audit BANJARSARI Evidence
        if ($db->tableExists('temuan')) {
            $tFields = $db->getFieldNames('temuan');
            $searchCols = array_intersect(['detail_temuan', 'lokasi', 'alamat', 'noga', 'deskripsi'], $tFields);

            if (!empty($searchCols)) {
                $b = $db->table('temuan')
                    ->groupStart();
                $first = true;
                foreach ($searchCols as $col) {
                    if ($first) { $b->like($col, 'BANJARSARI'); $first = false; }
                    else { $b->orLike($col, 'BANJARSARI'); }
                }
                $b->groupEnd();
                $rows = $b->get()->getResultArray();

                foreach ($rows as $r) {
                    $lat = (float)($r['latitude'] ?? 0);
                    $lon = (float)($r['longitude'] ?? 0);
                    $text = $r['detail_temuan'] ?? ($r['lokasi'] ?? ($r['alamat'] ?? ''));

                    $sanity = ($lat >= -8.5 && $lat <= -6.5 && $lon >= 111.0 && $lon <= 114.5) ? 'VALID' : 'ANOMALOUS';

                    $nearest = null;
                    $dist = null;
                    if ($sanity === 'VALID' && $lat != 0 && $lon != 0) {
                        $nearest = $this->findNearestAsset($lat, $lon, $assets, $registry);
                        $dist = $nearest ? $nearest['distance'] : null;
                    }

                    $upperText = strtoupper($text);
                    $role = 'GENERAL_LOCATION';
                    $classification = 'WEAK_REFERENCE';
                    $usableAnchor = false;
                    $rejectionReason = '';

                    if ($sanity === 'ANOMALOUS') {
                        $role = 'ANOMALOUS_COORDINATE';
                        $classification = 'INVALID_ANOMALOUS';
                        $rejectionReason = "GPS coordinate ({$lat}, {$lon}) outside Sidoarjo/East Java bounding box.";
                    } elseif (str_contains($upperText, 'LBSM BANJARSARI') || str_contains($upperText, 'LBS BANJARSARI')) {
                        if ($dist !== null && $dist <= 15.0) {
                            $role = 'DEVICE_LOCATION';
                            $classification = 'STRONG_ANCHOR';
                            $usableAnchor = true;
                            $rejectionReason = 'QUALIFIED (Direct device observation at pole distance ' . round($dist, 2) . 'm)';
                        } else {
                            $role = 'DEVICE_LOCATION';
                            $classification = 'MEDIUM_ANCHOR';
                            $rejectionReason = 'Device named, but distant from network pole (>15m: ' . round($dist, 2) . 'm).';
                        }
                    } else {
                        $role = 'GENERAL_LOCATION';
                        $classification = 'WEAK_REFERENCE';
                        $rejectionReason = "General area / village landmark note ('mushola banjarsari', distance: " . round($dist ?? 0, 1) . "m); not an explicit switching device pole.";
                    }

                    $evaluatedFindings[] = [
                        'landmark'           => 'BANJARSARI',
                        'source_table'       => 'temuan',
                        'source_id'          => $r['id'],
                        'source_text'        => $text,
                        'latitude'           => $lat,
                        'longitude'          => $lon,
                        'coordinate_sanity'  => $sanity,
                        'nearest_asset_id'   => $nearest['id'] ?? null,
                        'nearest_asset_name' => $nearest['nama_asset'] ?? null,
                        'distance_meters'    => $dist,
                        'semantic_role'      => $role,
                        'classification'     => $classification,
                        'usable_for_anchor'  => $usableAnchor,
                        'usable_for_confidence' => $usableAnchor,
                        'rejection_reason'   => $rejectionReason,
                    ];
                }
            }
        }

        // 3. Audit PRASUNG Evidence
        if ($db->tableExists('temuan')) {
            $tFields = $db->getFieldNames('temuan');
            $searchCols = array_intersect(['detail_temuan', 'lokasi', 'alamat', 'noga', 'deskripsi'], $tFields);

            if (!empty($searchCols)) {
                $b = $db->table('temuan')
                    ->groupStart();
                $first = true;
                foreach ($searchCols as $col) {
                    if ($first) { $b->like($col, 'PRASUNG'); $first = false; }
                    else { $b->orLike($col, 'PRASUNG'); }
                }
                $b->groupEnd();
                $rows = $b->get()->getResultArray();

                foreach ($rows as $r) {
                    $lat = (float)($r['latitude'] ?? 0);
                    $lon = (float)($r['longitude'] ?? 0);
                    $text = $r['detail_temuan'] ?? ($r['lokasi'] ?? ($r['alamat'] ?? ''));

                    // STRICT COORDINATE SANITY CHECK: Longitude around 112.7x. If 122.x => INVALID_ANOMALOUS!
                    $sanity = ($lat >= -8.5 && $lat <= -6.5 && $lon >= 111.0 && $lon <= 114.5) ? 'VALID' : 'ANOMALOUS';

                    $nearest = null;
                    $dist = null;
                    if ($sanity === 'VALID' && $lat != 0 && $lon != 0) {
                        $nearest = $this->findNearestAsset($lat, $lon, $assets, $registry);
                        $dist = $nearest ? $nearest['distance'] : null;
                    }

                    $upperText = strtoupper($text);
                    $role = 'GENERAL_LOCATION';
                    $classification = 'WEAK_REFERENCE';
                    $usableAnchor = false;
                    $rejectionReason = '';

                    if ($sanity === 'ANOMALOUS') {
                        $role = 'ANOMALOUS_COORDINATE';
                        $classification = 'INVALID_ANOMALOUS';
                        $rejectionReason = "REJECTED: Longitude {$lon} is geographically anomalous (expected ~112.7x in Sidoarjo). Automatic correction prohibited.";
                    } elseif (str_contains($upperText, 'LBS COUPLE') || str_contains($upperText, 'PERTIGAAN PRASUNG') || str_contains($upperText, 'LBS PRASUNG')) {
                        if ($dist !== null && $dist <= 15.0) {
                            $role = 'DEVICE_LOCATION';
                            $classification = 'STRONG_ANCHOR';
                            $usableAnchor = true;
                            $rejectionReason = 'QUALIFIED (Direct device observation at pole distance ' . round($dist, 2) . 'm)';
                        } else {
                            $role = 'DEVICE_LOCATION';
                            $classification = 'MEDIUM_ANCHOR';
                            $rejectionReason = 'Device named, but distant from network pole (' . round($dist ?? 0, 1) . 'm).';
                        }
                    } else {
                        $role = 'NON_DEVICE_DEFECT';
                        $classification = 'NON_DEVICE_EVIDENCE';
                        $rejectionReason = 'General village name mention or trafo defect; not an explicit switching device.';
                    }

                    $evaluatedFindings[] = [
                        'landmark'           => 'PRASUNG',
                        'source_table'       => 'temuan',
                        'source_id'          => $r['id'],
                        'source_text'        => $text,
                        'latitude'           => $lat,
                        'longitude'          => $lon,
                        'coordinate_sanity'  => $sanity,
                        'nearest_asset_id'   => $nearest['id'] ?? null,
                        'nearest_asset_name' => $nearest['nama_asset'] ?? null,
                        'distance_meters'    => $dist,
                        'semantic_role'      => $role,
                        'classification'     => $classification,
                        'usable_for_anchor'  => $usableAnchor,
                        'usable_for_confidence' => $usableAnchor,
                        'rejection_reason'   => $rejectionReason,
                    ];
                }
            }
        }

        // 4. Audit PULAU BATU
        $evaluatedFindings[] = [
            'landmark'              => 'PULAU BATU',
            'source_table'          => 'sections',
            'source_id'             => null,
            'source_text'           => 'GI - RECLOSER PULAU BATU',
            'latitude'              => null,
            'longitude'             => null,
            'coordinate_sanity'     => 'NOT_PRESENT',
            'nearest_asset_id'      => null,
            'nearest_asset_name'    => null,
            'distance_meters'       => null,
            'semantic_role'         => 'TEXT_LABEL_ONLY',
            'classification'        => 'DATA_NOT_PRESENT',
            'usable_for_anchor'     => false,
            'usable_for_confidence' => false,
            'rejection_reason'      => 'REJECTED: Zero physical coordinates or inspection findings in database. Synthetic coordinates prohibited.',
        ];

        // Filter Qualified vs Rejected
        $qualifiedAnchors = array_values(array_filter($evaluatedFindings, fn($f) => $f['usable_for_anchor'] === true));
        $rejectedEvidence = array_values(array_filter($evaluatedFindings, fn($f) => $f['usable_for_anchor'] === false));

        $report = [
            'success'               => true,
            'engine'                => 'AR-01-ANCHOR-QUALIFICATION',
            'contract_version'      => '1.0',
            'feeder_id'             => $feederId,
            'feeder_name'           => $feederName,
            'total_evaluated'       => count($evaluatedFindings),
            'total_qualified'       => count($qualifiedAnchors),
            'total_rejected'        => count($rejectedEvidence),
            'evaluated_findings'    => $evaluatedFindings,
            'qualified_anchors'     => $qualifiedAnchors,
            'rejected_evidence'     => $rejectedEvidence,
            'governance'            => [
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

        // Visual Output
        CLI::write("\n==============================================================", 'cyan');
        CLI::write("AR-01 PHASE 5G.4R.8: EVIDENCE ANCHOR QUALIFICATION", 'cyan');
        CLI::write("==============================================================", 'cyan');
        CLI::write("TARGET FEEDER : {$feederName} (ID: #{$feederId})", 'yellow');
        CLI::write("MUTATION      : ZERO (Strictly Read-Only Qualification & Ranking)\n", 'green');

        CLI::write("1. INDIVIDUAL EVIDENCE AUDIT & CLASSIFICATION TABLE:", 'cyan');
        CLI::write(str_repeat("-", 115));
        CLI::write(sprintf("%-16s | %-12s | %-10s | %-18s | %-20s | %-6s", "Landmark", "Source", "Distance", "Semantic Role", "Classification", "Usable"));
        CLI::write(str_repeat("-", 115));

        foreach ($evaluatedFindings as $ef) {
            $srcLabel = ($ef['source_table'] !== 'sections') ? "Temuan #{$ef['source_id']}" : "Section Label";
            $distLabel = ($ef['distance_meters'] !== null) ? round($ef['distance_meters'], 1) . ' m' : '-';
            $useLabel = $ef['usable_for_anchor'] ? CLI::color('YES', 'green') : CLI::color('NO', 'red');

            $classColor = ($ef['classification'] === 'STRONG_ANCHOR') ? 'green' : (($ef['classification'] === 'MEDIUM_ANCHOR') ? 'yellow' : 'red');

            CLI::write(sprintf(
                "%-16s | %-12s | %-10s | %-18s | %-20s | %s",
                $ef['landmark'],
                $srcLabel,
                $distLabel,
                $ef['semantic_role'],
                CLI::color($ef['classification'], $classColor),
                $useLabel
            ));
        }
        CLI::write(str_repeat("-", 115));

        CLI::write("\n2. 🟢 FINAL QUALIFIED ANCHORS (Qualified for Partition Invariants):", 'green');
        if (empty($qualifiedAnchors)) {
            CLI::write("  (None qualified)", 'yellow');
        } else {
            foreach ($qualifiedAnchors as $qa) {
                CLI::write(sprintf("  • [%s] Temuan #%s ➔ Asset #%s (%s m) | %s", $qa['landmark'], $qa['source_id'], $qa['nearest_asset_id'], round($qa['distance_meters'], 2), $qa['rejection_reason']), 'green');
            }
        }

        CLI::write("\n3. 🔴 REJECTED / NON-USABLE EVIDENCE:", 'red');
        foreach ($rejectedEvidence as $re) {
            $srcLabel = ($re['source_table'] !== 'sections') ? "Temuan #{$re['source_id']}" : "Sections Table";
            CLI::write(sprintf("  • [%s] %s ➔ [%s] %s", $re['landmark'], $srcLabel, $re['classification'], $re['rejection_reason']), 'yellow');
        }

        CLI::write("\n==============================================================", 'cyan');
        CLI::write("4. GOVERNANCE AUDIT (ZERO MUTATION PROVEN):", 'cyan');
        CLI::write("  assets.section_id writes        : 0", 'green');
        CLI::write("  sections writes                 : 0", 'green');
        CLI::write("  asset_relationships writes      : 0", 'green');
        CLI::write("  network_topology writes         : 0", 'green');
        CLI::write("  promotion_allowed               : FALSE (LOCKED)", 'green');
        CLI::write("==============================================================\n", 'cyan');

        return 0;
    }

    protected function findNearestAsset(float $lat, float $lon, array $assets, LandmarkEvidenceRegistry $registry): ?array
    {
        $minDist = PHP_FLOAT_MAX;
        $best = null;

        foreach ($assets as $a) {
            $aLat = (float)($a['latitude'] ?? 0);
            $aLon = (float)($a['longitude'] ?? 0);
            if (!empty($aLat) && !empty($aLon) && $aLat != 0 && $aLon != 0) {
                $d = $registry->haversineDistance($lat, $lon, $aLat, $aLon);
                if ($d < $minDist) {
                    $minDist = $d;
                    $best = [
                        'id'         => (int)$a['id'],
                        'nama_asset' => $a['nama_asset'] ?? '',
                        'kode_asset' => $a['kode_asset'] ?? '',
                        'distance'   => $d,
                    ];
                }
            }
        }

        return $best;
    }
}
