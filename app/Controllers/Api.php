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
     * GET /api/penyulangs/(:num)
     */
    public function getPenyulangsByUlp(int $ulpId)
    {
        $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($ulpId);
        return $this->respond($penyulangs);
    }

    /**
     * GET /api/sections/(:num)
     */
    public function getSectionsByPenyulang(int $penyulangId)
    {
        $sections = $this->sectionRepository->getActiveSectionsByPenyulang($penyulangId);
        return $this->respond($sections);
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
                  AND t.longitude IS NOT NULL
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
}
