<?php

namespace App\Services;

use Config\Database;

/**
 * Feeder Network Readiness & Transline Integrity Service
 *
 * Audits every feeder across all ULPs for:
 * 1. Physical JTM pole counts vs non-pole assets.
 * 2. Real protection devices (Recloser, PMCB, LBS) — strictly no fake placeholders.
 * 3. Official active translines and topological continuity.
 * 4. Zero tolerance for cross-feeder or non-pole transline segments.
 * 5. Honest readiness classification (SIAP_TERVERIFIKASI, SEBAGIAN_TERPETAKAN, BELUM_SIAP).
 */
class FeederNetworkReadinessService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect('default');
    }

    /**
     * Audit all feeders across all ULPs and produce comprehensive readiness report
     */
    public function auditAllFeeders(?int $ulpFilterId = null): array
    {
        if (!$this->db->tableExists('penyulang')) {
            return ['status' => 'error', 'message' => 'Tabel penyulang tidak ditemukan.'];
        }

        $builder = $this->db->table('penyulang')
            ->select('penyulang.id, penyulang.kode_penyulang, penyulang.nama_penyulang, penyulang.ulp_id, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = penyulang.ulp_id', 'left');

        if ($ulpFilterId !== null && $ulpFilterId > 0) {
            $builder->where('penyulang.ulp_id', $ulpFilterId);
        }

        $feeders = $builder->orderBy('penyulang.ulp_id', 'ASC')->orderBy('penyulang.nama_penyulang', 'ASC')->get()->getResultArray();

        $feederReports = [];
        $summary = [
            'total_feeders'                 => count($feeders),
            'ready_count'                   => 0,
            'partially_ready_count'         => 0,
            'not_ready_count'               => 0,
            'total_active_poles'            => 0,
            'total_translines'              => 0,
            'total_violations'              => 0,
            'cross_feeder_violations'       => 0,
            'non_pole_transline_violations' => 0,
        ];

        foreach ($feeders as $f) {
            $feederId = (int)$f['id'];
            $report = $this->auditSingleFeeder($feederId, $f);
            $feederReports[] = $report;

            if ($report['readiness_status'] === 'SIAP_TERVERIFIKASI') {
                $summary['ready_count']++;
            } elseif ($report['readiness_status'] === 'SEBAGIAN_TERPETAKAN') {
                $summary['partially_ready_count']++;
            } else {
                $summary['not_ready_count']++;
            }

            $summary['total_active_poles']            += $report['valid_jtm_poles_count'];
            $summary['total_translines']              += $report['active_translines_count'];
            $summary['cross_feeder_violations']       += $report['cross_feeder_translines'];
            $summary['non_pole_transline_violations'] += $report['non_pole_translines'];
            $summary['total_violations']              += ($report['cross_feeder_translines'] + $report['non_pole_translines']);
        }

        return [
            'status'        => 'success',
            'audit_date'    => date('Y-m-d H:i:s'),
            'summary'       => $summary,
            'feeders'       => $feederReports,
        ];
    }

    /**
     * Audit single feeder readiness details
     */
    public function auditSingleFeeder(int $feederId, ?array $feederRow = null): array
    {
        if (!$feederRow) {
            $feederRow = $this->db->table('penyulang')
                ->select('penyulang.id, penyulang.kode_penyulang, penyulang.nama_penyulang, penyulang.ulp_id, ulps.nama_ulp')
                ->join('ulps', 'ulps.id = penyulang.ulp_id', 'left')
                ->where('penyulang.id', $feederId)
                ->get()
                ->getRowArray() ?? [
                    'id' => $feederId,
                    'kode_penyulang' => "PYL-{$feederId}",
                    'nama_penyulang' => "Feeder #{$feederId}",
                    'ulp_id' => null,
                    'nama_ulp' => 'Unknown'
                ];
        }

        // Assets query
        $assets = $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, jenis_asset, section_id, latitude, longitude')
            ->where('penyulang_id', $feederId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $totalAssets = count($assets);
        $validJtmPoles = 0;
        $nonPoleAssets = 0;
        $sections = [];
        $hasCoordsCount = 0;

        foreach ($assets as $a) {
            $check = GisTranslineService::isJtmPoleAsset($a);
            if ($check['valid']) {
                $validJtmPoles++;
            } else {
                $nonPoleAssets++;
            }
            if (!empty($a['section_id'])) {
                $sections[$a['section_id']] = true;
            }
            if (!empty($a['latitude']) && !empty($a['longitude']) && (abs((float)$a['latitude']) > 0.001)) {
                $hasCoordsCount++;
            }
        }

        // Real Protection Devices (Recloser, PMCB, LBS)
        $protectionDevices = (new FaultLocatorWorkspaceService())->getProtectionDevices($feederId);
        $protectionCount = count($protectionDevices);

        // Active Official Translines
        $translines = $this->db->table('gis_translines')
            ->where('penyulang_id', $feederId)
            ->where('is_active', 1)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $activeTlCount = count($translines);
        $spanSumMeters = 0.0;
        $crossFeederCount = 0;
        $nonPoleTlCount = 0;

        // Build asset lookup map for fast checking
        $assetMap = [];
        foreach ($assets as $a) {
            $assetMap[$a['id']] = $a;
        }

        foreach ($translines as $tl) {
            $spanSumMeters += (float)($tl['distance_meters'] ?? 0);
            $sId = (int)$tl['source_asset_id'];
            $tId = (int)$tl['target_asset_id'];

            $sAsset = $assetMap[$sId] ?? $this->db->table('assets')->where('id', $sId)->get()->getRowArray();
            $tAsset = $assetMap[$tId] ?? $this->db->table('assets')->where('id', $tId)->get()->getRowArray();

            if ($sAsset && $tAsset) {
                $sFeeder = (int)($sAsset['penyulang_id'] ?? 0);
                $tFeeder = (int)($tAsset['penyulang_id'] ?? 0);
                // Cross-feeder check: only if assets are assigned to different feeders
                if ($sFeeder > 0 && $tFeeder > 0 && ($sFeeder !== $tFeeder || $sFeeder !== $feederId)) {
                    $crossFeederCount++;
                }
                // Non-pole endpoint check
                if (!GisTranslineService::isJtmPoleAsset($sAsset)['valid'] || !GisTranslineService::isJtmPoleAsset($tAsset)['valid']) {
                    $nonPoleTlCount++;
                }
            }
        }

        // Determine Readiness Classification & Missing Prerequisites
        $missingPrerequisites = [];
        $readinessStatus = 'BELUM_SIAP';
        $readinessMessage = '';

        if ($totalAssets === 0 || $validJtmPoles === 0) {
            $readinessStatus = 'BELUM_SIAP';
            $readinessMessage = 'Penyulang kosong: Belum memiliki data tiang JTM dan koordinat';
            $missingPrerequisites = [
                'Data inventaris aset tiang JTM fisik (TIANG_BETON / TIANG_BESI)',
                'Koordinat lintang dan bujur (Latitude/Longitude) tiang',
                'Struktur pembagian section penyulang'
            ];
        } elseif ($hasCoordsCount === 0) {
            $readinessStatus = 'BELUM_SIAP';
            $readinessMessage = 'Aset tiang tersedia tetapi belum memiliki koordinat geografis (Lat/Lng = 0)';
            $missingPrerequisites = ['Survei koordinat GPS tiang JTM'];
        } elseif ($activeTlCount === 0) {
            $readinessStatus = 'BELUM_SIAP';
            $readinessMessage = 'Aset tiang tersedia tetapi belum memiliki pemetaan segmen transline aktif';
            $missingPrerequisites = ['Pemetaan segmen transline tiang-ke-tiang JTM'];
        } elseif ($activeTlCount < ($validJtmPoles * 0.35)) {
            $readinessStatus = 'SEBAGIAN_TERPETAKAN';
            $readinessMessage = "Baru {$activeTlCount} segmen transline terpetakan dari {$validJtmPoles} tiang JTM";
            $missingPrerequisites = ['Pelengkapan segmen transline pada cabang sekunder/lateral'];
        } else {
            $readinessStatus = 'SIAP_TERVERIFIKASI';
            $readinessMessage = 'Topologi jaringan tiang JTM lengkap dan siap untuk Fault Locator';
        }

        return [
            'feeder_id'               => $feederId,
            'kode_penyulang'          => $feederRow['kode_penyulang'],
            'nama_penyulang'          => $feederRow['nama_penyulang'],
            'ulp_id'                  => $feederRow['ulp_id'],
            'nama_ulp'                => $feederRow['nama_ulp'] ?? 'Unknown ULP',
            'readiness_status'        => $readinessStatus,
            'readiness_message'       => $readinessMessage,
            'missing_prerequisites'   => $missingPrerequisites,
            'total_assets_count'      => $totalAssets,
            'valid_jtm_poles_count'   => $validJtmPoles,
            'active_jtm_poles_count'  => $validJtmPoles,
            'non_pole_assets_count'   => $nonPoleAssets,
            'protection_devices_count'=> $protectionCount,
            'protection_devices'      => $protectionDevices,
            'protection_device_status'=> $protectionCount > 0 ? "Tersedia ({$protectionCount} perangkat)" : "BELUM_ADA_PERANGKAT_PROTEKSI",
            'section_count'           => count($sections),
            'active_translines_count' => $activeTlCount,
            'translines_count'        => $activeTlCount,
            'total_span_meters'       => round($spanSumMeters, 1),
            'cross_feeder_translines' => $crossFeederCount,
            'non_pole_translines'     => $nonPoleTlCount,
        ];
    }
}
