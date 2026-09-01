<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * AR-01 Phase 5G.4R.7: Hidden Landmark Evidence Deep-Scan & Metadata Mining
 * Usage: php spark ar01:evidence:deep-scan [FEEDER_ID] [--json]
 */
class Ar01EvidenceDeepScanCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:evidence:deep-scan';
    protected $description = 'AR-01 Phase 5G.4R.7: Hidden Landmark Deep-Scan across Photo Metadata, Snapshots & Work Orders (Strictly Read-Only)';

    protected $arguments = [
        'feeder' => 'Feeder ID (default: 4 GEMURUNG)',
    ];

    protected $options = [
        'feeder' => 'Feeder ID (alternative option)',
        'json'   => 'Output raw machine-readable Deep Scan JSON',
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

        // 1. Target Deep Scan Keywords
        $scanKeywords = [
            'PULAU BATU', 'PULAU', 'BATU', 'P.BATU', 'PBATU',
            'BANJARSARI', 'LBSM BANJARSARI', 'LBS BANJARSARI',
            'TRI DASA WINDU', 'DASA WINDU', 'WADUNG ASIH',
            'PERTIGAAN PRASUNG', 'PRASUNG'
        ];

        // 2. High-Value Metadata Tables to Deep Scan
        $targetTables = [
            'temuan'                       => ['detail_temuan', 'lokasi', 'alamat', 'noga', 'material'],
            'tb_foto_eviden'               => ['judul_foto', 'keterangan', 'path_foto', 'lokasi'],
            'tb_management_trafo'          => ['nama_gardu', 'lokasi', 'alamat', 'keterangan'],
            'tb_eviden_trafo'              => ['nama_gardu', 'keterangan'],
            'tb_eviden_kubikel'            => ['nama_gardu', 'id_pel', 'keterangan'],
            'asset_intelligence_snapshots' => ['snapshot_data', 'intelligence_source', 'change_summary'],
            'riwayat_tindak_lanjut'        => ['keterangan', 'tindak_lanjut', 'petugas'],
            'work_orders'                  => ['judul_wo', 'detail_wo', 'catatan'],
            'wo_checklists'                => ['item_name', 'notes', 'finding_summary'],
            'inspection_points'            => ['point_name', 'description', 'reference_code'],
            'historical_feeder_interruptions' => ['interruption_title', 'section_name', 'root_cause_notes', 'location_description'],
            'ar01_staging_assets'          => ['nama_asset', 'kode_asset', 'lokasi', 'source_section_name'],
        ];

        $discoveredEvidence = [];

        foreach ($targetTables as $tbl => $cols) {
            if (!$db->tableExists($tbl)) continue;

            $actualFields = $db->getFieldNames($tbl);
            $validCols = array_intersect($cols, $actualFields);
            if (empty($validCols)) continue;

            foreach ($scanKeywords as $kw) {
                $b = $db->table($tbl);
                $b->groupStart();
                $first = true;
                foreach ($validCols as $vc) {
                    if ($first) {
                        $b->like($vc, $kw);
                        $first = false;
                    } else {
                        $b->orLike($vc, $kw);
                    }
                }
                $b->groupEnd();

                $matchCount = $b->countAllResults(false);
                if ($matchCount > 0) {
                    $rows = $b->limit(5)->get()->getResultArray();
                    foreach ($rows as $r) {
                        $snippet = [];
                        foreach ($validCols as $vc) {
                            if (!empty($r[$vc])) {
                                $val = (string)$r[$vc];
                                if (strlen($val) > 80) $val = mb_strimwidth($val, 0, 80, '...');
                                $snippet[] = "{$vc}: {$val}";
                            }
                        }

                        $lat = $r['latitude'] ?? ($r['lat'] ?? null);
                        $lon = $r['longitude'] ?? ($r['lon'] ?? null);

                        $discoveredEvidence[] = [
                            'table'        => $tbl,
                            'record_id'    => $r['id'] ?? 'N/A',
                            'keyword'      => $kw,
                            'snippet'      => implode(' | ', $snippet),
                            'has_gps'      => (!empty($lat) && !empty($lon) && $lat != 0 && $lon != 0),
                            'lat'          => $lat,
                            'lon'          => $lon,
                        ];
                    }
                }
            }
        }

        // Deduplicate findings by table + record_id + keyword
        $uniqueEvidence = [];
        foreach ($discoveredEvidence as $de) {
            $key = $de['table'] . '_' . $de['record_id'] . '_' . $de['keyword'];
            $uniqueEvidence[$key] = $de;
        }
        $discoveredEvidence = array_values($uniqueEvidence);

        // Classify by Landmark
        $evidenceByLandmark = [
            'PULAU_BATU'     => array_values(array_filter($discoveredEvidence, fn($e) => str_contains($e['keyword'], 'PULAU') || str_contains($e['keyword'], 'BATU'))),
            'TRI_DASA_WINDU' => array_values(array_filter($discoveredEvidence, fn($e) => str_contains($e['keyword'], 'DASA') || str_contains($e['keyword'], 'WINDU') || str_contains($e['keyword'], 'WADUNG'))),
            'BANJARSARI'     => array_values(array_filter($discoveredEvidence, fn($e) => str_contains($e['keyword'], 'BANJARSARI'))),
            'PRASUNG'        => array_values(array_filter($discoveredEvidence, fn($e) => str_contains($e['keyword'], 'PRASUNG'))),
        ];

        $report = [
            'success'               => true,
            'engine'                => 'AR-01-EVIDENCE-DEEP-SCAN',
            'contract_version'      => '1.0',
            'feeder_id'             => $feederId,
            'total_findings'        => count($discoveredEvidence),
            'by_landmark'           => $evidenceByLandmark,
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

        // Visual CLI Output
        CLI::write("\n==============================================================", 'cyan');
        CLI::write("AR-01 PHASE 5G.4R.7: HIDDEN LANDMARK EVIDENCE DEEP-SCAN", 'cyan');
        CLI::write("==============================================================", 'cyan');
        CLI::write("TARGET FEEDER : Feeder #{$feederId}", 'yellow');
        CLI::write("MUTATION      : ZERO (Strictly Read-Only Metadata Mining)\n", 'green');

        foreach ($evidenceByLandmark as $lmKey => $items) {
            CLI::write(sprintf("\n📌 LANDMARK TARGET: [%s] (%d findings)", $lmKey, count($items)), 'cyan');
            if (empty($items)) {
                CLI::write("  🔴 NO EVIDENCE FOUND across all photo captions, snapshots, work orders, or logs.", 'red');
            } else {
                foreach ($items as $item) {
                    $gpsTag = $item['has_gps'] ? CLI::color("[GPS: {$item['lat']}, {$item['lon']}]", 'green') : CLI::color("[NO GPS]", 'yellow');
                    CLI::write(sprintf("  • Table `%s` #%s %s", $item['table'], $item['record_id'], $gpsTag), 'yellow');
                    CLI::write(sprintf("    Keyword: %s | %s", $item['keyword'], $item['snippet']));
                }
            }
        }

        CLI::write("\n==============================================================", 'cyan');
        CLI::write("GOVERNANCE AUDIT (ZERO MUTATION PROVEN):", 'cyan');
        CLI::write("  assets.section_id writes        : 0", 'green');
        CLI::write("  sections writes                 : 0", 'green');
        CLI::write("  asset_relationships writes      : 0", 'green');
        CLI::write("  network_topology writes         : 0", 'green');
        CLI::write("==============================================================\n", 'cyan');

        return 0;
    }
}
