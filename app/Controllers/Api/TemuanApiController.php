<?php

namespace App\Controllers\Api;

use App\Services\TemuanService;
use App\Repositories\TemuanRepository;

class TemuanApiController extends BaseApiController
{
    private TemuanService $temuanService;
    private TemuanRepository $temuanRepository;

    public function __construct()
    {
        $this->temuanService = new TemuanService();
        $this->temuanRepository = new TemuanRepository();
    }

    /**
     * GET /api/v1/temuan
     */
    public function index()
    {
        $user = $this->getJwtUser();
        $ulpIdFilter = $user['ulp_id'] ?? null;
        if (in_array($user['role'] ?? '', ['administrator', 'pdkb', 'har_crane'])) {
            $ulpIdFilter = null;
        }

        $filters = [
            'q'          => $this->request->getGet('q'),
            'ulp_id'     => $this->request->getGet('ulp_id'),
            'pelaksana'  => $this->request->getGet('pelaksana'),
            'prioritas'  => $this->request->getGet('prioritas'),
            'status'     => $this->request->getGet('status'),
            'start_date' => $this->request->getGet('start_date'),
            'end_date'   => $this->request->getGet('end_date'),
        ];

        $page = max(1, (int)($this->request->getGet('page') ?: 1));
        $perPage = max(10, min(100, (int)($this->request->getGet('per_page') ?: 20)));

        $dataList = $this->temuanRepository->getFilteredTemuan($filters, $ulpIdFilter);
        
        // Format photo URLs for Flutter JSON consumption
        foreach ($dataList as &$item) {
            $photos = json_decode($item['foto'] ?? '', true) ?: [];
            if (is_string($item['foto'] ?? null) && empty($photos) && !empty($item['foto'])) {
                $photos = [$item['foto']];
            }
            $item['foto_urls'] = array_map(fn($p) => get_photo_url($p, $item['foto_path'] ?? 'foto/'), $photos);
            $item['sla_status'] = get_sla_status($item['prioritas'] ?? 'MEDIUM', $item['tanggal_temuan'] ?? date('Y-m-d'), $item['status'] ?? 'BELUM');
        }

        $totalRecords = count($dataList);
        $offset = ($page - 1) * $perPage;
        $paginatedData = array_slice($dataList, $offset, $perPage);

        return $this->respondSuccess([
            'items'        => $paginatedData,
            'page'         => $page,
            'per_page'     => $perPage,
            'total_items'  => $totalRecords,
            'total_pages'  => ceil($totalRecords / $perPage)
        ], 'Daftar temuan berhasil dimuat.');
    }

    /**
     * GET /api/v1/temuan/terdekat
     */
    public function terdekat()
    {
        $lat = (float)($this->request->getGet('lat') ?: -7.4478);
        $lng = (float)($this->request->getGet('lng') ?: 112.7183);
        $radius = (float)($this->request->getGet('radius') ?: 10.0); // km

        $user = $this->getJwtUser();
        $ulpId = $user['ulp_id'] ?? null;
        if (in_array($user['role'] ?? '', ['administrator', 'pdkb', 'har_crane'])) {
            $ulpId = null;
        }

        $db = \Config\Database::connect();
        $builder = $db->table('temuan t')
            ->select("t.*, u.nama_ulp, p.nama_penyulang, s.nama_section,
                (6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians({$lat})) * cos(radians(t.latitude)) * cos(radians(t.longitude) - radians({$lng})) + sin(radians({$lat})) * sin(radians(t.latitude)))))) AS distance_km")
            ->join('ulps u', 'u.id = t.ulp_id', 'left')
            ->join('penyulang p', 'p.id = t.penyulang_id', 'left')
            ->join('sections s', 's.id = t.section_id', 'left')
            ->where('t.deleted_at IS NULL')
            ->where('t.latitude IS NOT NULL')
            ->where('t.longitude IS NOT NULL');

        if ($ulpId !== null) {
            $builder->where('t.ulp_id', $ulpId);
        }

        $builder->having('distance_km <=', $radius)
                ->orderBy('distance_km', 'ASC')
                ->limit(50);

        $results = $builder->get()->getResultArray();
        foreach ($results as &$item) {
            $photos = json_decode($item['foto'] ?? '', true) ?: [];
            $item['foto_urls'] = array_map(fn($p) => get_photo_url($p, $item['foto_path'] ?? 'foto/'), $photos);
            $item['distance_km'] = round((float)$item['distance_km'], 2);
        }

        return $this->respondSuccess($results, 'Daftar temuan terdekat.');
    }

    /**
     * GET /api/v1/temuan/(:num)
     */
    public function show(int $id)
    {
        $item = $this->temuanRepository->findWithRelations($id);
        if (!$item) {
            return $this->respondError('Data temuan tidak ditemukan.', 404);
        }

        $photos = json_decode($item['foto'] ?? '', true) ?: [];
        $item['foto_urls'] = array_map(fn($p) => get_photo_url($p, $item['foto_path'] ?? 'foto/'), $photos);
        $item['tindak_lanjut_history'] = (new \App\Repositories\TindakLanjutRepository())->getByTemuanId($id);

        return $this->respondSuccess($item, 'Detail temuan.');
    }

    /**
     * POST /api/v1/temuan
     */
    public function create()
    {
        $user = $this->getJwtUser();
        $files = $this->request->getFiles();

        $rules = [
            'ulp_id'         => 'required|numeric',
            'penyulang_id'   => 'required|numeric',
            'jenis_temuan'   => 'required',
            'pelaksana'      => 'required',
            'prioritas'      => 'required',
            'detail_temuan'  => 'required',
            'alamat'         => 'required',
            'tanggal_temuan' => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->respondError('Validasi gagal.', 422, $this->validator->getErrors());
        }

        $uploadedPhotos = [];
        if (!empty($files['foto'])) {
            $photoFiles = is_array($files['foto']) ? $files['foto'] : [$files['foto']];
            $uploadedPhotos = $this->temuanService->uploadMultiplePhotos($photoFiles);
        }

        $postData = $this->request->getPost();
        $postData['foto'] = json_encode($uploadedPhotos);
        $postData['foto_path'] = 'foto/';
        $postData['status'] = $postData['status'] ?? 'BELUM';
        $postData['created_by'] = $user['user_id'];
        $postData['created_by_name'] = $user['nama_pegawai'];
        $postData['created_by_nip'] = $user['nip'];

        $id = $this->temuanService->createTemuan($postData);
        if ($id) {
            return $this->respondSuccess(['id' => $id], 'Temuan baru berhasil disimpan.', 201);
        }

        return $this->respondError('Gagal menyimpan temuan.', 500);
    }

    /**
     * POST /api/v1/temuan/update/(:num)
     */
    public function update(int $id)
    {
        $temuan = $this->temuanRepository->find($id);
        if (!$temuan) {
            return $this->respondError('Data temuan tidak ditemukan.', 404);
        }

        $user = $this->getJwtUser();
        $postData = $this->request->getPost() ?: ($this->request->getJSON(true) ?: []);

        $postData['updated_by'] = $user['user_id'];
        $postData['updated_by_name'] = $user['nama_pegawai'];
        $postData['updated_by_nip'] = $user['nip'];

        if ($this->temuanService->updateTemuan($id, $postData)) {
            return $this->respondSuccess(null, 'Temuan berhasil diperbarui.');
        }

        return $this->respondError('Gagal memperbarui temuan.', 500);
    }

    /**
     * DELETE /api/v1/temuan/delete/(:num)
     */
    public function delete(int $id)
    {
        if ($this->temuanService->deleteTemuan($id)) {
            return $this->respondSuccess(null, 'Temuan berhasil dihapus.');
        }

        return $this->respondError('Gagal menghapus temuan.', 500);
    }

    /**
     * POST /api/v1/temuan/tindak-lanjut/(:num)
     */
    public function tindakLanjut(int $id)
    {
        $user = $this->getJwtUser();
        $files = $this->request->getFiles();
        $postData = $this->request->getPost();

        $uploaded = [];
        foreach (['foto_sebelum', 'foto_proses', 'foto_sesudah'] as $field) {
            if (!empty($files[$field]) && $files[$field]->isValid()) {
                $uploaded[$field] = $this->temuanService->uploadPhoto($files[$field]);
            }
        }

        $res = $this->temuanService->updateTemuanPekerjaan(
            $id,
            $postData['status_progress'] ?? 'SELESAI',
            $uploaded['foto_sebelum'] ?? null,
            $uploaded['foto_proses'] ?? null,
            $uploaded['foto_sesudah'] ?? null,
            $postData['catatan'] ?? '',
            $user['user_id'],
            $user['nama_pegawai']
        );

        if ($res) {
            return $this->respondSuccess(null, 'Status pekerjaan berhasil diperbarui.');
        }

        return $this->respondError('Gagal memperbarui status pekerjaan.', 500);
    }

    /**
     * GET /api/history
     */
    public function history()
    {
        $user = $this->getJwtUser();
        $db = \Config\Database::connect();
        
        $logs = $db->table('tindak_lanjut tl')
            ->select('tl.*, t.nomor_temuan, t.jenis_temuan, u.nama_ulp')
            ->join('temuan t', 't.id = tl.temuan_id', 'left')
            ->join('ulps u', 'u.id = t.ulp_id', 'left')
            ->orderBy('tl.id', 'DESC')
            ->limit(50)
            ->get()->getResultArray();

        return $this->respondSuccess($logs, 'Riwayat tindak lanjut temuan.');
    }

    /**
     * GET /api/dashboard
     */
    public function dashboard()
    {
        $user = $this->getJwtUser();
        $role = $user['role'] ?? 'inspeksi';
        $ulpId = $user['ulp_id'] ?? null;

        $stats = $this->temuanRepository->getComprehensiveAnalytics($role, $ulpId);
        return $this->respondSuccess($stats, 'Data dashboard overview.');
    }

    /**
     * GET /api/chart
     */
    public function chart()
    {
        $user = $this->getJwtUser();
        $role = $user['role'] ?? 'inspeksi';
        $ulpId = $user['ulp_id'] ?? null;

        $stats = $this->temuanRepository->getComprehensiveAnalytics($role, $ulpId);
        return $this->respondSuccess([
            'temuan_vs_realisasi' => [
                'total_temuan' => $stats['total_temuan'],
                'total_realisasi' => $stats['total_realisasi']
            ],
            'temuan_mingguan'   => $stats['temuan_mingguan'],
            'realisasi_harian'  => $stats['realisasi_harian'],
            'temuan_bulanan'    => $stats['temuan_bulanan'],
            'realisasi_bulanan' => $stats['realisasi_bulanan'],
            'status_breakdown'  => $stats['status_breakdown'],
            'prioritas_breakdown'=> $stats['prioritas_breakdown'],
            'jenis_breakdown'   => $stats['jenis_breakdown'],
            'sla'               => $stats['sla']
        ], 'Data grafik analitik real-time.');
    }

    /**
     * GET /api/notifikasi
     */
    public function notifikasi()
    {
        $user = $this->getJwtUser();
        $userUlp = $user['ulp_id'] ?? null;

        $db = \Config\Database::connect();
        $builder = $db->table('temuan')
            ->select('id, nomor_temuan, prioritas, status, detail_temuan, created_at')
            ->where('deleted_at IS NULL')
            ->where('status !=', 'SELESAI');

        if ($userUlp) {
            $builder->where('ulp_id', $userUlp);
        }

        $items = $builder->orderBy('id', 'DESC')->limit(20)->get()->getResultArray();
        $notifications = [];
        foreach ($items as $r) {
            $notifications[] = [
                'id'         => $r['id'],
                'title'      => "Temuan {$r['nomor_temuan']} [{$r['prioritas']}]",
                'message'    => $r['detail_temuan'],
                'type'       => $r['prioritas'] === 'EMERGENCY' ? 'danger' : 'warning',
                'created_at' => $r['created_at']
            ];
        }

        return $this->respondSuccess($notifications, 'Daftar notifikasi temuan aktif.');
    }
}
