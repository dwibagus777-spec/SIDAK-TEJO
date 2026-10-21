<?php

namespace App\Commands;

use App\Services\PlnStandardBomRuleService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * AuditFindingBomCommand
 *
 * Usage:
 *   php spark finding:audit-bom [--id=160] [--feeder=...] [--execute]
 */
class AuditFindingBomCommand extends BaseCommand
{
    protected $group       = 'Finding';
    protected $name        = 'finding:audit-bom';
    protected $description = 'Audits and synchronizes PLN standard BOM requirements and cleans invalid numeric NOGA.';
    protected $usage       = 'finding:audit-bom [--id=<num>] [--feeder=<id>] [--execute]';
    protected $options     = [
        '--id'      => 'Specific finding ID (e.g. 160)',
        '--feeder'  => 'Filter by feeder ID',
        '--execute' => 'Persist BOM requirements and clean invalid NOGA',
    ];

    public function run(array $params)
    {
        $targetId  = CLI::getOption('id');
        $feederId  = CLI::getOption('feeder');
        $isExecute = CLI::getOption('execute') !== null;

        CLI::write("==================================================================", 'yellow');
        CLI::write("   SIDAK TEJO — PLN STANDARD BOM & NOGA AUDIT & SYNC              ", 'yellow');
        CLI::write("==================================================================", 'yellow');
        CLI::write("Mode: " . ($isExecute ? "LIVE EXECUTION (PERSIST BOM & CLEAN NOGA)" : "DRY-RUN (READ-ONLY AUDIT)"), $isExecute ? 'red' : 'green');
        if (!empty($targetId)) {
            CLI::write("Target Finding ID: #" . $targetId, 'cyan');
        }
        if (!empty($feederId)) {
            CLI::write("Filter Feeder ID: #" . $feederId, 'cyan');
        }
        CLI::newLine();

        $service = new PlnStandardBomRuleService();

        if (!empty($targetId)) {
            $db = \Config\Database::connect();
            $temuan = $db->table('temuan')->where('id', (int)$targetId)->get()->getRowArray();
            if (!$temuan) {
                CLI::error("Temuan #{$targetId} tidak ditemukan.");
                return;
            }

            $bom = $service->resolveBomFromFinding($temuan);
            $cleanNoga = $service->sanitizeNoga($temuan['noga'] ?? null, $temuan['detail_temuan'] ?? '');
            $candidates = $service->findCandidateAssets($temuan, 150.0);

            CLI::write("1. Data Temuan #{$targetId}:", 'white');
            CLI::write("   • Nomor Temuan  : " . ($temuan['nomor_temuan'] ?? '-'), 'white');
            CLI::write("   • Detail        : " . ($temuan['detail_temuan'] ?? '-'), 'white');
            CLI::write("   • NOGA (Lama)   : " . var_export($temuan['noga'] ?? null, true), 'yellow');
            CLI::write("   • NOGA (Bersih) : " . var_export($cleanNoga, true), 'green');
            CLI::write("   • Asset ID Terkini: " . var_export($temuan['asset_id'] ?? null, true), 'yellow');
            CLI::newLine();

            CLI::write("2. Resolusi Kebutuhan Material (BOM Standar PLN):", 'white');
            CLI::write("   • Status Mapping : " . $bom['mapping_status'], $bom['mapping_status'] === 'RESOLVED' ? 'green' : 'red');
            CLI::write("   • Komponen Kunci : " . $bom['finding_component_key'], 'cyan');
            CLI::write("   • Konstruksi     : " . ($bom['construction_code'] ?? '-'), 'cyan');
            CLI::write("   • Konduktor      : " . $bom['conductor_type'] . " " . $bom['conductor_size'] . " mm²", 'cyan');
            CLI::write("   • Rincian Standar: " . $bom['formatted_summary'], 'yellow');
            CLI::newLine();

            CLI::write("3. Kandidat Aset Tiang Terdekat di Penyulang:", 'white');
            if (!empty($candidates['candidates'])) {
                foreach ($candidates['candidates'] as $idx => $c) {
                    $color = ($idx === 0) ? 'green' : 'white';
                    CLI::write(sprintf("   [%d] ID #%d | %s | %s | Jarak: %.1fm | Section: %s",
                        $idx + 1, $c['id'], $c['kode_asset'], $c['nama_asset'], $c['distance_meters'], $c['nama_section'] ?? '-'
                    ), $color);
                }
            } else {
                CLI::write("   Tidak ada tiang dalam radius pencarian.", 'yellow');
            }
            CLI::newLine();

            if ($isExecute) {
                CLI::write("Mengeksekusi penyimpanan BOM & sanitasi NOGA...", 'yellow');
                $syncRes = $service->syncTemuanMaterials((int)$targetId, true);
                if (($syncRes['status'] ?? '') === 'success') {
                    CLI::write("✔ Berhasil menyimpan BOM! " . $syncRes['materials_count'] . " material tersimpan.", 'green');
                } else {
                    CLI::error("Gagal sync: " . ($syncRes['message'] ?? 'Unknown error'));
                }

                // If deterministic candidate pole found (within 25m) and asset_id is null, link it
                if (empty($temuan['asset_id']) && $candidates['is_deterministic']) {
                    $first = $candidates['candidates'][0];
                    CLI::write("Menautkan secara deterministik ke aset #{$first['id']} ({$first['kode_asset']})...", 'cyan');
                    $linkRes = $service->linkFindingToAsset((int)$targetId, (int)$first['id']);
                    if ($linkRes['status'] === 'success') {
                        CLI::write("✔ Penautan berhasil!", 'green');
                    } else {
                        CLI::error("Gagal link: " . $linkRes['message']);
                    }
                }
            }
            return;
        }

        // Batch audit
        CLI::write("Menjalankan audit batch seluruh temuan...", 'white');
        $result = $service->batchSyncBom($isExecute, $feederId ? (int)$feederId : null);

        CLI::write("Hasil Audit Batch:", 'white');
        CLI::write("  • Total Temuan Dievaluasi : " . number_format($result['total_evaluated']), 'cyan');
        CLI::write("  • BOM Terpetakan Standar  : " . number_format($result['resolved_bom_count']), 'green');
        CLI::write("  • Perlu Verifikasi Lapangan: " . number_format($result['unmapped_bom_count']), 'yellow');
        CLI::write("  • Total Item Material Dihasilkan: " . number_format($result['total_items_generated']), 'cyan');
        CLI::newLine();

        if (!empty($result['sample_synced'])) {
            CLI::write("Sampel Temuan:", 'white');
            foreach ($result['sample_synced'] as $s) {
                CLI::write(sprintf("  • #%d [%s]: NOGA '%s' -> '%s' | Status: %s | %s",
                    $s['id'], $s['nomor_temuan'], (string)$s['old_noga'], (string)$s['new_noga'], $s['status'], $s['summary']
                ), 'white');
            }
        }
    }
}
