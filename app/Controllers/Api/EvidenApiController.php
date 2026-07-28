<?php

namespace App\Controllers\Api;

use App\Models\EvidenKubikelModel;
use App\Models\EvidenTrafoModel;
use App\Models\FotoEvidenModel;

class EvidenApiController extends BaseApiController
{
    private EvidenKubikelModel $kubikelModel;
    private EvidenTrafoModel $trafoModel;
    private FotoEvidenModel $fotoModel;

    public function __construct()
    {
        $this->kubikelModel = new EvidenKubikelModel();
        $this->trafoModel   = new EvidenTrafoModel();
        $this->fotoModel    = new FotoEvidenModel();
    }

    /**
     * GET /api/v1/eviden/kubikel
     */
    public function kubikelList()
    {
        $page = max(1, (int)($this->request->getGet('page') ?: 1));
        $perPage = max(10, min(100, (int)($this->request->getGet('per_page') ?: 20)));

        $db = \Config\Database::connect();
        $builder = $db->table('eviden_kubikel k')
            ->select('k.*, u.nama_ulp, p.nama_penyulang')
            ->join('ulps u', 'u.id = k.ulp_id', 'left')
            ->join('penyulang p', 'p.id = k.penyulang_id', 'left')
            ->orderBy('k.id_kubikel', 'DESC');

        $totalRecords = $builder->countAllResults(false);
        $offset = ($page - 1) * $perPage;
        $items = $builder->limit($perPage, $offset)->get()->getResultArray();

        // Attach photos to each kubikel
        if (!empty($items)) {
            $ids = array_column($items, 'id_kubikel');
            $fotos = $this->fotoModel->where('kategori', 'KUBIKEL')->whereIn('id_parent', $ids)->findAll();

            $fotoMap = [];
            foreach ($fotos as $f) {
                $fotoMap[$f['id_parent']][] = [
                    'id_foto'    => $f['id_foto'],
                    'jenis_foto' => $f['jenis_foto'],
                    'path'       => $f['path'],
                    'url'        => get_photo_url($f['path'])
                ];
            }

            foreach ($items as &$item) {
                $item['fotos'] = $fotoMap[$item['id_kubikel']] ?? [];
            }
        }

        return $this->respondSuccess([
            'items'       => $items,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_items' => $totalRecords,
            'total_pages' => ceil($totalRecords / $perPage)
        ], 'Daftar eviden kubikel.');
    }

    /**
     * GET /api/v1/eviden/trafo
     */
    public function trafoList()
    {
        $page = max(1, (int)($this->request->getGet('page') ?: 1));
        $perPage = max(10, min(100, (int)($this->request->getGet('per_page') ?: 20)));

        $db = \Config\Database::connect();
        $builder = $db->table('eviden_trafo t')
            ->select('t.*, u.nama_ulp, p.nama_penyulang')
            ->join('ulps u', 'u.id = t.ulp_id', 'left')
            ->join('penyulang p', 'p.id = t.penyulang_id', 'left')
            ->orderBy('t.id_trafo', 'DESC');

        $totalRecords = $builder->countAllResults(false);
        $offset = ($page - 1) * $perPage;
        $items = $builder->limit($perPage, $offset)->get()->getResultArray();

        if (!empty($items)) {
            $ids = array_column($items, 'id_trafo');
            $fotos = $this->fotoModel->where('kategori', 'TRAFO')->whereIn('id_parent', $ids)->findAll();

            $fotoMap = [];
            foreach ($fotos as $f) {
                $fotoMap[$f['id_parent']][] = [
                    'id_foto'    => $f['id_foto'],
                    'jenis_foto' => $f['jenis_foto'],
                    'path'       => $f['path'],
                    'url'        => get_photo_url($f['path'])
                ];
            }

            foreach ($items as &$item) {
                $item['fotos'] = $fotoMap[$item['id_trafo']] ?? [];
            }
        }

        return $this->respondSuccess([
            'items'       => $items,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_items' => $totalRecords,
            'total_pages' => ceil($totalRecords / $perPage)
        ], 'Daftar eviden trafo.');
    }
}
