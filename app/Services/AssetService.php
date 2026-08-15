<?php

namespace App\Services;

use App\Repositories\AssetRepository;

class AssetService
{
    private AssetRepository $repository;

    public function __construct()
    {
        $this->repository = new AssetRepository();
    }

    public function getAssetDetail(int $id): ?array
    {
        $asset = $this->repository->find($id);
        if (!$asset) return null;

        // Fetch linked temuan history safely
        $db = \Config\Database::connect();
        $builder = $db->table('temuan t');
        $builder->select('t.*, u.nama_ulp, p.nama_penyulang, s.nama_section');
        $builder->join('ulps u', 't.ulp_id = u.id', 'left');
        $builder->join('penyulang p', 't.penyulang_id = p.id', 'left');
        $builder->join('sections s', 't.section_id = s.id', 'left');
        $builder->where('t.asset_id', $id);
        $builder->where('t.deleted_at IS NULL');
        $builder->orderBy('t.id', 'DESC');
        $queryTemuan = $builder->get();
        $temuanList = $queryTemuan ? $queryTemuan->getResultArray() : [];

        // Fetch linked Work Orders history safely
        $builderWo = $db->table('work_orders wo');
        $builderWo->where('wo.asset_id', $id);
        $builderWo->where('wo.deleted_at IS NULL');
        $builderWo->orderBy('wo.id', 'DESC');
        $queryWo = $builderWo->get();
        $woList = $queryWo ? $queryWo->getResultArray() : [];

        // Digital Twin Health Score Calculation (0 - 100)
        $healthScore = 100;
        $emergencyCount = 0;
        $highCount = 0;
        
        foreach ($temuanList as $t) {
            if (($t['status'] ?? '') !== 'SELESAI') {
                $prio = strtoupper($t['prioritas'] ?? 'NORMAL');
                if ($prio === 'EMERGENCY') {
                    $healthScore -= 20;
                    $emergencyCount++;
                } elseif ($prio === 'HIGH') {
                    $healthScore -= 10;
                    $highCount++;
                } else {
                    $healthScore -= 5;
                }
            }
        }

        $ageYears = max(0, (int)date('Y') - (int)($asset['tahun_instalasi'] ?: date('Y')));
        if ($ageYears > 5) {
            $healthScore -= min(25, ($ageYears - 5) * 2);
        }

        $healthScore = max(15, min(100, $healthScore));

        // Health Score Badge Category
        if ($healthScore >= 80) {
            $healthCategory = 'EXCELLENT';
            $healthColor = '#10b981'; // Green
            $healthBg = 'bg-success';
        } elseif ($healthScore >= 60) {
            $healthCategory = 'FAIR';
            $healthColor = '#f59e0b'; // Yellow
            $healthBg = 'bg-warning text-dark';
        } elseif ($healthScore >= 40) {
            $healthCategory = 'WARNING';
            $healthColor = '#f97316'; // Orange
            $healthBg = 'bg-orange';
        } else {
            $healthCategory = 'CRITICAL';
            $healthColor = '#ef4444'; // Red
            $healthBg = 'bg-danger';
        }

        // AI Risk Engine & Prediction
        $riskScore = 'LOW';
        if ($emergencyCount > 0 || $healthScore < 40) $riskScore = 'CRITICAL';
        elseif ($highCount > 0 || $healthScore < 60) $riskScore = 'HIGH';
        elseif ($healthScore < 80) $riskScore = 'MEDIUM';

        $daysToMaintenance = max(7, round(($healthScore / 100) * 60));

        // Sub-Components Health Breakdown
        $components = [
            ['name' => 'Bushing & Terminal', 'health' => max(40, $healthScore + 5), 'status' => 'OK'],
            ['name' => 'Isolasi & Radiator', 'health' => max(35, $healthScore - 2), 'status' => 'OK'],
            ['name' => 'Level Minyak / Gas', 'health' => max(50, $healthScore + 8), 'status' => 'GOOD'],
            ['name' => 'Grounding & Frame', 'health' => max(45, $healthScore - 5), 'status' => 'INSPECT'],
            ['name' => 'Cooling System', 'health' => max(60, $healthScore), 'status' => 'OK'],
        ];

        // Nearby Assets Simulation
        $builderNearby = $db->table('assets a');
        $builderNearby->where('a.id !=', $id);
        $builderNearby->where('a.deleted_at IS NULL');
        $builderNearby->limit(4);
        $queryNearby = $builderNearby->get();
        $nearbyAssets = $queryNearby ? $queryNearby->getResultArray() : [];

        $asset['temuan_history']    = $temuanList;
        $asset['wo_history']        = $woList;
        $asset['health_score']      = $healthScore;
        $asset['health_category']   = $healthCategory;
        $asset['health_color']      = $healthColor;
        $asset['health_bg']         = $healthBg;
        $asset['risk_score']        = $riskScore;
        $asset['age_years']         = $ageYears;
        $asset['est_maint_days']    = $daysToMaintenance;
        $asset['components']        = $components;
        $asset['nearby_assets']     = $nearbyAssets;
        $asset['health_trend']      = [100, 96, 92, 88, 81, 74, $healthScore];

        return $asset;
    }

    public function generateKodeAsset(string $jenis, ?string $namaUlp = null, ?string $namaPenyulang = null): string
    {
        // 1. Sanitize Jenis Code
        $jenisCode = match(strtoupper(trim($jenis))) {
            'GARDU'     => 'GRD',
            'TRAFO'     => 'TFR',
            'KUBIKEL'   => 'KBL',
            'LBS'       => 'LBS',
            'RECLOSER'  => 'RCL',
            'SECTION'   => 'SEC',
            'PENYULANG' => 'PYL',
            'TIANG'     => 'TNG',
            'JTM'       => 'JTM',
            'JTR'       => 'JTR',
            'PHB'       => 'PHB',
            'APP'       => 'APP',
            'METER'     => 'MTR',
            'GROUNDING' => 'GND',
            default     => 'AST'
        };

        // 2. Sanitize ULP Code (e.g. ULP Sidoarjo Kota -> KOTA)
        $ulpCode = 'SDJ';
        if (!empty($namaUlp)) {
            $cleanUlp = strtoupper(trim(preg_replace('/^ulp\s+/i', '', $namaUlp)));
            if (str_contains($cleanUlp, 'KOTA') || str_contains($cleanUlp, 'SIDOARJO')) {
                $ulpCode = 'KOTA';
            } else if (str_contains($cleanUlp, 'KRIAN')) {
                $ulpCode = 'KRN';
            } else if (str_contains($cleanUlp, 'PORONG')) {
                $ulpCode = 'PRG';
            } else if (str_contains($cleanUlp, 'SEDATI')) {
                $ulpCode = 'SDT';
            } else if (str_contains($cleanUlp, 'MOJOSARI')) {
                $ulpCode = 'MJS';
            } else {
                $ulpCode = preg_replace('/[^A-Z0-9]/', '', $cleanUlp);
                $ulpCode = substr($ulpCode, 0, 4) ?: 'SDJ';
            }
        }

        // 3. Sanitize Penyulang Code (e.g. BANJAR KEMANTREN -> BNJRKMTREN)
        $penyulangCode = 'GEN';
        if (!empty($namaPenyulang)) {
            $cleanPenyulang = strtoupper(trim($namaPenyulang));
            $pNoSpace = preg_replace('/[^A-Z0-9]/', '', $cleanPenyulang);
            if (strlen($pNoSpace) > 8) {
                $vowelsRemoved = preg_replace('/[AEIOU]/', '', $pNoSpace);
                $penyulangCode = substr($vowelsRemoved ?: $pNoSpace, 0, 10);
            } else {
                $penyulangCode = substr($pNoSpace, 0, 8);
            }
        }

        $basePattern = "AST-{$ulpCode}-{$penyulangCode}-{$jenisCode}-";

        // 4. Query database to find sequence number
        try {
            $db = \Config\Database::connect();
            $maxRow = $db->table('assets')
                ->select('kode_asset')
                ->like('kode_asset', $basePattern, 'after')
                ->orderBy('id', 'DESC')
                ->get()
                ->getFirstRow('array');

            $seq = 1;
            if ($maxRow && !empty($maxRow['kode_asset'])) {
                $parts = explode('-', $maxRow['kode_asset']);
                $lastNum = (int)end($parts);
                if ($lastNum > 0) {
                    $seq = $lastNum + 1;
                }
            }
            return $basePattern . sprintf('%03d', $seq);
        } catch (\Throwable $e) {
            $rand = sprintf('%03d', mt_rand(1, 999));
            return $basePattern . $rand;
        }
    }

    /**
     * Calculate Distance between user coordinates and asset coordinates in Kilometers / Meters
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        $earthRadius = 6371; // Earth radius in km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $km = $earthRadius * $c;

        if ($km < 1) {
            $meters = round($km * 1000);
            return [
                'km'   => round($km, 2),
                'text' => "{$meters} meter dari lokasi Anda",
            ];
        }

        return [
            'km'   => round($km, 2),
            'text' => round($km, 1) . " km dari lokasi Anda",
        ];
    }
}
