<?php

namespace App\Controllers;

use App\Repositories\TemuanRepository;
use App\Repositories\UlpRepository;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;
use App\Services\AuthService;
use App\Services\TemuanService;
use CodeIgniter\API\ResponseTrait;

class Api extends BaseController
{
    use ResponseTrait;

    private TemuanRepository $temuanRepository;
    private UlpRepository $ulpRepository;
    private PenyulangRepository $penyulangRepository;
    private SectionRepository $sectionRepository;
    private AuthService $authService;
    private TemuanService $temuanService;

    public function __construct()
    {
        $this->temuanRepository = new TemuanRepository();
        $this->ulpRepository = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
        $this->sectionRepository = new SectionRepository();
        $this->authService = new AuthService();
        $this->temuanService = new TemuanService();
    }

    /**
     * POST /api/auth/login
     */
    public function login()
    {
        $username = $this->request->getPost('username') ?: '';
        $password = $this->request->getPost('password') ?: '';

        if ($username === '' || $password === '') {
            return $this->fail('Username dan password wajib diisi.');
        }

        $res = $this->authService->login($username, $password);

        if ($res['success']) {
            return $this->respond([
                'status' => 200,
                'error' => null,
                'messages' => [
                    'success' => 'Otentikasi berhasil.'
                ],
                'user' => [
                    'id' => session()->get('user_id'),
                    'nama' => session()->get('user_name'),
                    'role' => session()->get('user_role'),
                    'ulp_id' => session()->get('user_ulp_id'),
                ]
            ]);
        }

        return $this->failUnauthorized($res['message']);
    }

    /**
     * GET /api/temuan
     */
    public function getTemuan()
    {
        $ulpId = $this->request->getGet('ulp_id');
        $ulpIdFilter = ($ulpId !== null && $ulpId !== '') ? (int)$ulpId : null;

        $filters = [
            'status' => $this->request->getGet('status'),
            'prioritas' => $this->request->getGet('prioritas'),
        ];

        $data = $this->temuanRepository->getFilteredTemuan($filters, $ulpIdFilter);
        
        // Bersihkan data kembalian
        $result = [];
        foreach ($data as $row) {
            $result[] = [
                'id' => $row['id'],
                'nomor_temuan' => $row['nomor_temuan'],
                'nama_ulp' => $row['nama_ulp'],
                'nama_penyulang' => $row['nama_penyulang'],
                'nama_section' => $row['nama_section'],
                'jenis_temuan' => $row['jenis_temuan'],
                'pelaksana' => $row['pelaksana'],
                'prioritas' => $row['prioritas'],
                'potensi_gangguan' => $row['potensi_gangguan'],
                'tanggal_temuan' => $row['tanggal_temuan'],
                'status' => $row['status'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
            ];
        }

        return $this->respond($result);
    }

    /**
     * GET /api/temuan/(:num)
     */
    public function detailTemuan(int $id)
    {
        $temuan = $this->temuanRepository->getDetail($id);
        if (!$temuan) {
            return $this->failNotFound('Data temuan tidak ditemukan.');
        }

        return $this->respond($temuan);
    }

    /**
     * POST /api/temuan/create
     */
    public function createTemuan()
    {
        // Validasi input
        $rules = [
            'ulp_id'           => 'required',
            'penyulang_id'     => 'required',
            'section_id'       => 'required',
            'jenis_temuan'     => 'required',
            'pelaksana'        => 'required',
            'prioritas'        => 'required',
            'potensi_gangguan' => 'required',
            'konduktor'        => 'required',
            'material'         => 'required',
            'detail_temuan'    => 'required',
            'alamat'           => 'required',
            'tanggal_temuan'   => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $data = [
            'ulp_id'           => (int)$this->request->getPost('ulp_id'),
            'penyulang_id'     => (int)$this->request->getPost('penyulang_id'),
            'section_id'       => (int)$this->request->getPost('section_id'),
            'jenis_temuan'     => $this->request->getPost('jenis_temuan'),
            'pelaksana'        => $this->request->getPost('pelaksana'),
            'prioritas'        => $this->request->getPost('prioritas'),
            'potensi_gangguan' => $this->request->getPost('potensi_gangguan'),
            'konduktor'        => trim($this->request->getPost('konduktor')),
            'noga'             => trim($this->request->getPost('noga')) ?: null,
            'material'         => trim($this->request->getPost('material')),
            'detail_temuan'    => trim($this->request->getPost('detail_temuan')),
            'alamat'           => trim($this->request->getPost('alamat')),
            'latitude'         => $this->request->getPost('latitude') !== '' ? (float)$this->request->getPost('latitude') : null,
            'longitude'        => $this->request->getPost('longitude') !== '' ? (float)$this->request->getPost('longitude') : null,
            'tanggal_temuan'   => $this->request->getPost('tanggal_temuan'),
        ];

        $files = $this->request->getFileMultiple('foto');

        $res = $this->temuanService->createTemuan($data, $files);

        if ($res['success']) {
            return $this->respondCreated($res);
        }

        return $this->fail($res['message']);
    }

    /**
     * POST /api/temuan/tindak-lanjut
     */
    public function tindakLanjut()
    {
        $id = $this->request->getPost('temuan_id');
        if (!$id) {
            return $this->fail('Field temuan_id wajib disertakan.');
        }

        $rules = [
            'status_progress' => 'required|in_list[PROSES,SELESAI]',
            'komentar'        => 'required',
            'pelaksana'       => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $progressData = [
            'status_progress' => $this->request->getPost('status_progress'),
            'komentar'        => trim($this->request->getPost('komentar')),
            'pelaksana'       => trim($this->request->getPost('pelaksana'))
        ];

        $uploadFiles = [];
        $fotoSebelum = $this->request->getFile('foto_sebelum');
        if ($fotoSebelum && $fotoSebelum->isValid()) {
            $uploadFiles['foto_sebelum'] = $fotoSebelum;
        }

        $fotoProses = $this->request->getFile('foto_proses');
        if ($fotoProses && $fotoProses->isValid()) {
            $uploadFiles['foto_proses'] = $fotoProses;
        }

        $fotoSesudah = $this->request->getFile('foto_sesudah');
        if ($fotoSesudah && $fotoSesudah->isValid()) {
            $uploadFiles['foto_sesudah'] = $fotoSesudah;
        }

        $res = $this->temuanService->addTindakLanjut((int)$id, $progressData, $uploadFiles);

        if ($res['success']) {
            return $this->respond($res);
        }

        return $this->fail($res['message']);
    }

    /**
     * GET /api/options
     */
    public function getOptions()
    {
        $ulps = $this->ulpRepository->getActiveUlps();
        return $this->respond([
            'ulps' => $ulps
        ]);
    }

    /**
     * GET /api/penyulangs/(:num) & GET /api/penyulang-by-ulp/(:num)
     */
    public function getPenyulangsByUlp(int $ulpId)
    {
        $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($ulpId);
        return $this->respond([
            'status' => 200,
            'data'   => $penyulangs,
        ]);
    }

    /**
     * GET /api/network/penyulang
     * Cascading Filter Params: gi_id (optional), ulp_id (optional)
     */
    public function getNetworkPenyulangs()
    {
        $giId  = $this->request->getGet('gi_id');
        $ulpId = $this->request->getGet('ulp_id');

        $db = \Config\Database::connect();
        $builder = $db->table('penyulang p');
        $builder->select('p.id, p.kode_penyulang, p.nama_penyulang, p.gi_id, p.ulp_id, p.status');
        $builder->where('p.status', 'AKTIF');

        if (!empty($giId)) {
            $builder->where('p.gi_id', (int)$giId);
        }
        if (!empty($ulpId)) {
            $builder->where('p.ulp_id', (int)$ulpId);
        }

        $builder->orderBy('p.nama_penyulang', 'ASC');
        $penyulangs = $builder->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $penyulangs,
        ]);
    }

    /**
     * GET /api/network/ulps
     * Filter active ULPs linked to selected Gardu Induk (optional)
     */
    public function getNetworkUlps()
    {
        $giId = $this->request->getGet('gi_id');

        $db = \Config\Database::connect();
        $builder = $db->table('ulps u');
        $builder->select('DISTINCT u.id, u.kode_ulp, u.nama_ulp', false);
        $builder->where('u.status', 'AKTIF');

        if (!empty($giId)) {
            $builder->join('penyulang p', 'u.id = p.ulp_id');
            $builder->where('p.gi_id', (int)$giId);
        }

        $builder->orderBy('u.nama_ulp', 'ASC');
        $ulps = $builder->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $ulps,
        ]);
    }

    /**
     * GET /api/sections/(:num) & GET /api/section-by-penyulang/(:num)
     */
    public function getSectionsByPenyulang(int $penyulangId)
    {
        $sections = $this->sectionRepository->getActiveSectionsByPenyulang($penyulangId);
        return $this->respond([
            'status' => 200,
            'data'   => $sections,
        ]);
    }

    /**
     * GET /api/temuan/terdekat
     */
    public function getTemuanTerdekat()
    {
        $lat = $this->request->getGet('latitude');
        $lng = $this->request->getGet('longitude');
        $radius = $this->request->getGet('radius'); // in meters

        if ($lat === null || $lng === null) {
            return $this->fail('Parameter latitude dan longitude wajib disertakan.');
        }

        $lat = (float)$lat;
        $lng = (float)$lng;
        $radius = (float)($radius ?: 500) / 1000; // convert to km

        $db = \Config\Database::connect();
        
        $sql = "SELECT t.*, p.nama_penyulang, s.nama_section, u.nama_ulp,
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(t.latitude)) * cos(radians(t.longitude) - radians(?)) + 
                        sin(radians(?)) * sin(radians(t.latitude))
                    )) AS distance_km
                FROM temuan t
                LEFT JOIN penyulang p ON t.penyulang_id = p.id
                LEFT JOIN sections s ON t.section_id = s.id
                LEFT JOIN ulps u ON t.ulp_id = u.id
                WHERE t.latitude IS NOT NULL 
                  AND t.longitude IS NOT NULL";
        $params = [$lat, $lng, $lat];

        $role = strtolower((string)($this->request->getGet('role') ?: session()->get('user_role')));
        if ($role === 'har_row') {
            $sql .= " AND t.jenis_temuan = 'ROW'";
        }

        $sql .= " HAVING distance_km <= ?
                  ORDER BY distance_km ASC
                  LIMIT 50";
        $params[] = $radius;
                  
        $query = $db->query($sql, $params);
        $results = $query->getResultArray();
        
        foreach ($results as &$row) {
            $distMeters = $row['distance_km'] * 1000;
            if ($distMeters < 1000) {
                $row['distance_text'] = round($distMeters) . ' m';
            } else {
                $row['distance_text'] = round($row['distance_km'], 2) . ' km';
            }
        }

        return $this->respond($results);
    }

    /**
     * POST /api/auth/change-password
     */
    public function changePassword()
    {
        $userId = $this->request->getPost('user_id');
        $currentPassword = $this->request->getPost('current_password');
        $newPassword = $this->request->getPost('new_password');

        if (!$userId || !$currentPassword || !$newPassword) {
            return $this->fail('Data tidak lengkap (user_id, current_password, new_password).');
        }

        $userRepo = new \App\Repositories\UserRepository();
        $user = $userRepo->find((int)$userId);

        if (!$user) {
            return $this->failNotFound('User tidak ditemukan.');
        }

        if (!password_verify($currentPassword, $user['password'])) {
            return $this->fail('Password lama tidak sesuai.');
        }

        if (strlen($newPassword) < 6) {
            return $this->fail('Password baru minimal 6 karakter.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $userRepo->update((int)$userId, ['password' => $newHash]);

        return $this->respond([
            'status' => 200,
            'messages' => [
                'success' => 'Password berhasil diperbarui.'
            ]
        ]);
    }

    public function debugAssets()
    {
        $db = \Config\Database::connect();
        $totalRaw = $db->table('assets')->countAllResults();
        $totalActive = $db->table('assets')->where('deleted_at IS NULL')->countAllResults();

        $samplePenyulang15 = $db->table('assets')->where('penyulang_id', 15)->where('deleted_at IS NULL')->limit(5)->get()->getResultArray();
        $distinctJenis = $db->table('assets')->select('jenis_asset, count(*) as cnt')->where('deleted_at IS NULL')->groupBy('jenis_asset')->get()->getResultArray();
        $distinctPenyulang = $db->table('assets')->select('penyulang_id, count(*) as cnt')->where('deleted_at IS NULL')->groupBy('penyulang_id')->limit(20)->get()->getResultArray();

        $sampleAssets = $db->table('assets')->select('id, kode_asset, nama_asset, jenis_asset, ulp_id, penyulang_id, deleted_at')->where('deleted_at IS NULL')->limit(5)->get()->getResultArray();

        return $this->respond([
            'total_raw'            => $totalRaw,
            'total_active'         => $totalActive,
            'penyulang_15_count'   => count($samplePenyulang15),
            'penyulang_15_samples' => $samplePenyulang15,
            'distinct_jenis'       => $distinctJenis,
            'distinct_penyulang'   => $distinctPenyulang,
            'sample_assets'        => $sampleAssets,
        ]);
    }

    /**
     * GET /api/system/version
     * Public deployment fingerprint endpoint
     */
    public function version()
    {
        $config = config('BuildVersion');
        return $this->respond([
            'status'      => 200,
            'system'      => $config->SYSTEM_NAME ?? 'SIDAK TEJO',
            'description' => $config->SYSTEM_DESC ?? 'Sistem Data dan Tindak Lanjut Temuan Inspeksi Sidoarjo',
            'version'     => $config->SYSTEM_VERSION ?? 'v2.5.0-ENTERPRISE',
            'build_id'    => $config->BUILD_ID ?? '20260818.005',
            'commit_id'   => $config->COMMIT_ID ?? 'ff8265d',
            'deployed_at' => $config->DEPLOYED_AT ?? '2026-08-18 23:30:00',
            'environment' => $config->ENVIRONMENT ?? 'production',
        ]);
    }
}
