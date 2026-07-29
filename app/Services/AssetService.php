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

        // Fetch linked temuan history
        $db = \Config\Database::connect();
        $builder = $db->table('temuan t');
        $builder->select('t.*, u.nama_ulp, p.nama_penyulang, s.nama_section');
        $builder->join('ulps u', 't.ulp_id = u.id', 'left');
        $builder->join('penyulang p', 't.penyulang_id = p.id', 'left');
        $builder->join('sections s', 't.section_id = s.id', 'left');
        $builder->where('t.asset_id', $id);
        $builder->where('t.deleted_at IS NULL');
        $builder->orderBy('t.id', 'DESC');
        $temuanList = $builder->get()->getResultArray();

        // Fetch linked Work Orders history
        $builderWo = $db->table('work_orders wo');
        $builderWo->where('wo.asset_id', $id);
        $builderWo->where('wo.deleted_at IS NULL');
        $builderWo->orderBy('wo.id', 'DESC');
        $woList = $builderWo->get()->getResultArray();

        $asset['temuan_history'] = $temuanList;
        $asset['wo_history']     = $woList;

        return $asset;
    }

    public function generateKodeAsset(string $jenis): string
    {
        $prefix = match(strtoupper($jenis)) {
            'GARDU'     => 'AST-GRD-',
            'TRAFO'     => 'AST-TRF-',
            'KUBIKEL'   => 'AST-KUB-',
            'LBS'       => 'AST-LBS-',
            'RECLOSER'  => 'AST-REC-',
            'SECTION'   => 'AST-SEC-',
            'PENYULANG' => 'AST-PYL-',
            'TIANG'     => 'AST-TNG-',
            'JTM'       => 'AST-JTM-',
            'JTR'       => 'AST-JTR-',
            'PHB'       => 'AST-PHB-',
            'APP'       => 'AST-APP-',
            'METER'     => 'AST-MTR-',
            'GROUNDING' => 'AST-GND-',
            default     => 'AST-PLN-'
        };

        $rand = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 4));
        return $prefix . date('Ym') . '-' . $rand;
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
