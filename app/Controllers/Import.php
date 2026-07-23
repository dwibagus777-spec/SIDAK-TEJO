<?php

namespace App\Controllers;

use App\Models\TemuanModel;
use App\Models\UlpModel;
use App\Models\PenyulangModel;
use App\Models\SectionModel;
use App\Models\UserModel;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;

class Import extends BaseController
{
    // =========================================================================
    // INDEX - Halaman Utama Import CSV
    // =========================================================================
    public function index()
    {
        $ulpModel = new UlpModel();
        $data = [
            'title' => 'Impor Data dari CSV',
            'ulps'  => $ulpModel->where('status', 'AKTIF')->findAll(),
        ];
        return view('import/index', $data);
    }

    // =========================================================================
    // AJAX - Ambil daftar penyulang berdasarkan ulp_id (untuk dropdown Section)
    // =========================================================================
    public function ajaxGetPenyulang()
    {
        $ulpId = $this->request->getGet('ulp_id');
        $model = new PenyulangModel();
        $penyulangs = $model->where('ulp_id', $ulpId)->where('status', 'AKTIF')->findAll();
        return $this->response->setJSON($penyulangs);
    }

    // =========================================================================
    // AJAX - Download template Section CSV dengan ULP+Penyulang terisi otomatis
    // =========================================================================
    public function templateSectionDynamic()
    {
        $ulpId      = (int) $this->request->getGet('ulp_id');
        $penyulangId = (int) $this->request->getGet('penyulang_id');

        $ulpModel      = new UlpModel();
        $penyulangModel = new PenyulangModel();

        $ulp       = $ulpModel->find($ulpId);
        $penyulang = $penyulangModel->find($penyulangId);

        if (!$ulp || !$penyulang) {
            http_response_code(400);
            echo 'ULP atau Penyulang tidak valid.';
            exit;
        }

        $headers = [
            'No',
            'Nama Section*',
            'Nama ULP (otomatis: jangan diubah)',
            'Nama Penyulang (otomatis: jangan diubah)',
            'Status (AKTIF/NONAKTIF)',
        ];
        $sample = [
            '1',
            'SECTION A',
            $ulp['nama_ulp'],
            $penyulang['nama_penyulang'],
            'AKTIF',
        ];
        $this->downloadCsv('template_section_' . strtolower(str_replace(' ', '_', $penyulang['nama_penyulang'])) . '.csv', $headers, [$sample]);
    }

    // =========================================================================
    // AJAX - Download template Penyulang CSV dengan ULP terisi otomatis
    // =========================================================================
    public function templatePenyulangDynamic()
    {
        $ulpId    = (int) $this->request->getGet('ulp_id');
        $ulpModel = new UlpModel();
        $ulp      = $ulpModel->find($ulpId);

        if (!$ulp) {
            http_response_code(400);
            echo 'ULP tidak valid.';
            exit;
        }

        $headers = [
            'No',
            'Nama Penyulang*',
            'Nama ULP (otomatis: jangan diubah)',
            'Status (AKTIF/NONAKTIF)',
        ];
        $sample = [
            '1',
            'PENYULANG CONTOH',
            $ulp['nama_ulp'],
            'AKTIF',
        ];

        $filename = 'template_penyulang_' . strtolower(str_replace(' ', '_', $ulp['nama_ulp'])) . '.csv';
        $this->downloadCsv($filename, $headers, [$sample]);
    }

    // =========================================================================
    // DOWNLOAD TEMPLATE - Unduh template CSV sesuai modul (fix: switch, bukan array)
    // =========================================================================
    public function template(string $modul)
    {
        switch ($modul) {
            case 'temuan':    return $this->templateTemuan();
            case 'penyulang': return $this->templatePenyulang();
            case 'ulp':       return $this->templateUlp();
            case 'user':      return $this->templateUser();
            default:
                return redirect()->back()->with('error', 'Modul tidak ditemukan.');
        }
    }

    // =========================================================================
    // PROCESS IMPORT - Proses upload CSV dan simpan ke database
    // =========================================================================
    public function process()
    {
        $modul = $this->request->getPost('modul');
        $file  = $this->request->getFile('file_csv');

        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return redirect()->back()->with('error', 'File tidak valid atau gagal diunggah.');
        }

        $ext = strtolower($file->getClientExtension());
        if ($ext !== 'csv') {
            return redirect()->back()->with('error', 'Format file harus CSV (.csv).');
        }

        // Read CSV
        $handle = fopen($file->getTempName(), 'r');
        if (!$handle) {
            return redirect()->back()->with('error', 'Gagal membaca file CSV.');
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        // Remove header row
        array_shift($rows);

        if (empty($rows)) {
            return redirect()->back()->with('error', 'File CSV kosong atau hanya berisi baris header.');
        }

        switch ($modul) {
            case 'temuan':    return $this->importTemuan($rows);
            case 'penyulang': return $this->importPenyulang($rows);
            case 'ulp':       return $this->importUlp($rows);
            case 'user':      return $this->importUser($rows);
            case 'section':   return $this->importSection($rows);
            default:
                return redirect()->back()->with('error', 'Modul tidak dikenali.');
        }
    }

    // =========================================================================
    // IMPORT TEMUAN
    // Kolom: No | Nomor Temuan | Nama ULP | Nama Penyulang | Jenis Temuan |
    //        Pelaksana | Prioritas | Potensi Gangguan | Detail Temuan |
    //        Alamat | Latitude | Longitude | Tanggal Temuan | Status
    // =========================================================================
    private function importTemuan(array $rows): \CodeIgniter\HTTP\RedirectResponse
    {
        $model          = new TemuanModel();
        $ulpModel       = new UlpModel();
        $penyulangModel = new PenyulangModel();

        // Cache by name (lowercase)
        $ulps = [];
        foreach ($ulpModel->findAll() as $u) {
            $ulps[strtolower(trim($u['nama_ulp']))] = $u['id'];
        }
        $penyulangs = [];
        foreach ($penyulangModel->findAll() as $p) {
            $penyulangs[strtolower(trim($p['nama_penyulang']))] = $p['id'];
        }

        $success = 0;
        $errors  = [];
        $userId  = session()->get('user_id');

        foreach ($rows as $i => $row) {
            $row    = array_map('trim', $row);
            $rowNum = $i + 2;

            // Skip empty rows
            if (count($row) < 2 || (empty($row[0]) && empty($row[1]))) continue;

            $nomorTemuan   = $row[1] ?? '';
            $namaUlp       = strtolower($row[2] ?? '');
            $namaPenyulang = strtolower($row[3] ?? '');
            $jenisTem      = $row[4] ?? '';
            $pelaksana     = $row[5] ?? '';
            $prioritas     = strtoupper($row[6] ?? 'LOW');
            $potensi       = $row[7] ?? '';
            $detail        = $row[8] ?? '';
            $alamat        = $row[9] ?? '';
            $lat           = $row[10] ?? null;
            $lng           = $row[11] ?? null;
            $tglTemuan     = $row[12] ?? date('Y-m-d');
            $status        = strtoupper($row[13] ?? 'OPEN');

            if (empty($nomorTemuan)) {
                $errors[] = "Baris $rowNum: Nomor temuan kosong.";
                continue;
            }

            if (!in_array($prioritas, ['HIGH', 'MEDIUM', 'LOW'])) $prioritas = 'LOW';
            if (!in_array($status, ['OPEN', 'CLOSE', 'PROSES'])) $status = 'OPEN';

            $data = [
                'nomor_temuan'     => $nomorTemuan,
                'ulp_id'           => $ulps[$namaUlp] ?? null,
                'penyulang_id'     => $penyulangs[$namaPenyulang] ?? null,
                'jenis_temuan'     => $jenisTem,
                'pelaksana'        => $pelaksana,
                'prioritas'        => $prioritas,
                'potensi_gangguan' => $potensi,
                'detail_temuan'    => $detail,
                'alamat'           => $alamat,
                'latitude'         => is_numeric($lat) ? $lat : null,
                'longitude'        => is_numeric($lng) ? $lng : null,
                'tanggal_temuan'   => $this->parseDate($tglTemuan),
                'status'           => $status,
                'created_by'       => $userId,
            ];

            if (!$model->insert($data)) {
                $errors[] = "Baris $rowNum: " . implode(', ', $model->errors());
            } else {
                $success++;
            }
        }

        $msg = "$success data temuan berhasil diimpor.";
        if (!empty($errors)) {
            $msg .= ' ' . count($errors) . ' baris gagal: ' . implode(' | ', array_slice($errors, 0, 5));
        }
        return redirect()->to(site_url('temuan'))->with('success', $msg);
    }

    // =========================================================================
    // IMPORT PENYULANG
    // Kolom: No | Nama Penyulang | Nama ULP | Status
    // kode_penyulang dan id_unik_penyulang di-generate OTOMATIS oleh sistem
    // =========================================================================
    private function importPenyulang(array $rows): \CodeIgniter\HTTP\RedirectResponse
    {
        $model    = new PenyulangModel();
        $ulpModel = new UlpModel();

        // Cache ULP by name (lowercase) -> data ULP
        $ulps = [];
        foreach ($ulpModel->findAll() as $u) {
            $ulps[strtolower(trim($u['nama_ulp']))] = $u;
        }

        // Cache count per ULP untuk generate sequence kode
        $db       = \Config\Database::connect();
        $success  = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $row    = array_map('trim', $row);
            $rowNum = $i + 2;

            if (count($row) < 2 || (empty($row[0]) && empty($row[1]))) continue;

            // Kolom: No | Nama Penyulang* | Nama ULP | Status
            $nama    = $row[1] ?? '';
            $namaUlp = strtolower($row[2] ?? '');
            $status  = strtoupper($row[3] ?? 'AKTIF');

            if (empty($nama)) {
                $errors[] = "Baris $rowNum: Nama penyulang kosong.";
                continue;
            }

            $ulpData = $ulps[$namaUlp] ?? null;
            $ulpId   = $ulpData['id'] ?? null;

            // === CEK DUPLIKAT (Jangan sampai double) ===
            $existingPenyulang = $model->where('nama_penyulang', $nama)
                                       ->where('ulp_id', $ulpId)
                                       ->first();
            if ($existingPenyulang) {
                $errors[] = "Baris $rowNum: Penyulang '$nama' sudah terdaftar di ULP tersebut (dilewati).";
                continue;
            }

            // === AUTO-GENERATE kode_penyulang & id_unik_penyulang ===
            // Format kode : [KODE_ULP]-[3digit-seq]  → contoh: TJO-001
            // Format unik  : P_[KODE_ULP]_[3digit-seq] → contoh: P_TJO_001
            if ($ulpData) {
                $kodeUlp = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $ulpData['kode_ulp'] ?? $ulpData['nama_ulp']));
                $kodeUlp = substr($kodeUlp, 0, 4);
            } else {
                $kodeUlp = 'PYL';
            }

            // Hitung jumlah existing penyulang di ULP ini untuk sequence
            $count = $db->table('penyulang')
                        ->where($ulpId ? 'ulp_id' : '1', $ulpId ?? '1')
                        ->countAllResults() + $success + 1;

            $seq           = str_pad($count, 3, '0', STR_PAD_LEFT);
            $kode          = $kodeUlp . '-' . $seq;
            $idUnik        = 'P_' . $kodeUlp . '_' . $seq;

            // Pastikan id_unik tidak duplikat (tambahkan random suffix jika perlu)
            $existing = $model->where('id_unik_penyulang', $idUnik)->first();
            if ($existing) {
                $idUnik = 'P_' . $kodeUlp . '_' . $seq . '_' . uniqid();
                $kode   = $kodeUlp . '-' . $seq . '-' . substr(uniqid(), -3);
            }

            $data = [
                'id_unik_penyulang' => $idUnik,
                'kode_penyulang'    => $kode,
                'nama_penyulang'    => $nama,
                'ulp_id'            => $ulpId,
                'status'            => $status,
            ];

            if (!$model->insert($data)) {
                $errors[] = "Baris $rowNum: " . implode(', ', $model->errors());
            } else {
                $success++;
            }
        }

        $msg = "$success data penyulang berhasil diimpor (kode otomatis dibuat sistem).";
        if (!empty($errors)) $msg .= ' ' . count($errors) . ' baris gagal: ' . implode(' | ', array_slice($errors, 0, 5));
        return redirect()->to(site_url('penyulang'))->with('success', $msg);
    }

    // =========================================================================
    // IMPORT SECTION
    // Kolom: No | Nama Section | Nama ULP | Nama Penyulang | Status
    // =========================================================================
    private function importSection(array $rows): \CodeIgniter\HTTP\RedirectResponse
    {
        $model          = new SectionModel();
        $penyulangModel = new PenyulangModel();

        // Cache penyulang by name (lowercase)
        $penyulangs = [];
        foreach ($penyulangModel->findAll() as $p) {
            $penyulangs[strtolower(trim($p['nama_penyulang']))] = $p['id'];
        }

        $success = 0;
        $errors  = [];

        foreach ($rows as $i => $row) {
            $row    = array_map('trim', $row);
            $rowNum = $i + 2;

            if (count($row) < 2 || (empty($row[0]) && empty($row[1]))) continue;

            // Kolom: No | Nama Section* | Nama ULP (ignored - for reference) | Nama Penyulang | Status
            $namaSection   = $row[1] ?? '';
            // kolom 2 = Nama ULP (hanya referensi, tidak dipakai langsung)
            $namaPenyulang = strtolower($row[3] ?? '');
            $status        = strtoupper($row[4] ?? 'AKTIF');

            if (empty($namaSection)) {
                $errors[] = "Baris $rowNum: Nama section kosong.";
                continue;
            }

            $penyulangId = $penyulangs[$namaPenyulang] ?? null;
            if (!$penyulangId) {
                $errors[] = "Baris $rowNum: Penyulang '$namaPenyulang' tidak ditemukan di sistem.";
                continue;
            }

            // === CEK DUPLIKAT (Jangan sampai double) ===
            $existingSection = $model->where('nama_section', $namaSection)
                                     ->where('penyulang_id', $penyulangId)
                                     ->first();
            if ($existingSection) {
                $errors[] = "Baris $rowNum: Section '$namaSection' sudah terdaftar di Penyulang tersebut (dilewati).";
                continue;
            }

            $data = [
                'penyulang_id'  => $penyulangId,
                'nama_section'  => $namaSection,
                'status'        => $status,
            ];

            if (!$model->insert($data)) {
                $errors[] = "Baris $rowNum: " . implode(', ', $model->errors());
            } else {
                $success++;
            }
        }

        $msg = "$success data section berhasil diimpor.";
        if (!empty($errors)) $msg .= ' ' . count($errors) . ' baris gagal: ' . implode(' | ', array_slice($errors, 0, 5));
        return redirect()->to(site_url('sections'))->with('success', $msg);
    }

    // =========================================================================
    // IMPORT ULP
    // Kolom: No | Kode ULP | Nama ULP | Status
    // =========================================================================
    private function importUlp(array $rows): \CodeIgniter\HTTP\RedirectResponse
    {
        $model   = new UlpModel();
        $success = 0;
        $errors  = [];

        foreach ($rows as $i => $row) {
            $row    = array_map('trim', $row);
            $rowNum = $i + 2;

            if (count($row) < 3 || (empty($row[0]) && empty($row[2]))) continue;

            $kode   = $row[1] ?? '';
            $nama   = $row[2] ?? '';
            $status = strtoupper($row[3] ?? 'AKTIF');

            if (empty($nama)) {
                $errors[] = "Baris $rowNum: Nama ULP kosong.";
                continue;
            }

            $data = [
                'kode_ulp' => $kode,
                'nama_ulp' => $nama,
                'status'   => $status,
            ];

            if (!$model->insert($data)) {
                $errors[] = "Baris $rowNum: " . implode(', ', $model->errors());
            } else {
                $success++;
            }
        }

        $msg = "$success data ULP berhasil diimpor.";
        if (!empty($errors)) $msg .= ' ' . count($errors) . ' baris gagal.';
        return redirect()->to(site_url('ulps'))->with('success', $msg);
    }

    // =========================================================================
    // IMPORT USER
    // Kolom: No | Nama | Username | Password | Role | Nama ULP | Status
    // =========================================================================
    private function importUser(array $rows): \CodeIgniter\HTTP\RedirectResponse
    {
        $model    = new UserModel();
        $ulpModel = new UlpModel();

        $ulps = [];
        foreach ($ulpModel->findAll() as $u) {
            $ulps[strtolower(trim($u['nama_ulp']))] = $u['id'];
        }

        $success = 0;
        $errors  = [];

        foreach ($rows as $i => $row) {
            $row    = array_map('trim', $row);
            $rowNum = $i + 2;

            if (count($row) < 4 || (empty($row[0]) && empty($row[2]))) continue;

            $nama     = $row[1] ?? '';
            $username = $row[2] ?? '';
            $password = $row[3] ?? '';
            $role     = strtolower($row[4] ?? 'inspeksi');
            $namaUlp  = strtolower($row[5] ?? '');
            $status   = strtoupper($row[6] ?? 'AKTIF');

            if (empty($username) || empty($password)) {
                $errors[] = "Baris $rowNum: Username atau Password kosong.";
                continue;
            }
            if ($model->where('username', $username)->first()) {
                $errors[] = "Baris $rowNum: Username '$username' sudah ada.";
                continue;
            }

            $data = [
                'nama'     => $nama,
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role'     => $role,
                'ulp_id'   => $ulps[$namaUlp] ?? null,
                'status'   => $status,
            ];

            if (!$model->insert($data)) {
                $errors[] = "Baris $rowNum: " . implode(', ', $model->errors());
            } else {
                $success++;
            }
        }

        $msg = "$success data user berhasil diimpor.";
        if (!empty($errors)) $msg .= ' ' . count($errors) . ' baris gagal: ' . implode(' | ', array_slice($errors, 0, 5));
        return redirect()->to(site_url('users'))->with('success', $msg);
    }

    // =========================================================================
    // TEMPLATE CSV GENERATORS
    // =========================================================================
    private function templateTemuan()
    {
        $headers = [
            'No', 'Nomor Temuan*', 'Nama ULP', 'Nama Penyulang',
            'Jenis Temuan', 'Pelaksana', 'Prioritas (HIGH/MEDIUM/LOW)',
            'Potensi Gangguan', 'Detail Temuan', 'Alamat',
            'Latitude', 'Longitude', 'Tanggal Temuan (YYYY-MM-DD)',
            'Status (OPEN/PROSES/CLOSE)'
        ];
        $sample = [
            '1', 'TEM-2026-001', 'ULP TEJO', 'PENYULANG A',
            'Pohon', 'HAR ROW', 'HIGH',
            'Ganggu Jaringan', 'Pohon menggantung di jaringan', 'Jl. Contoh No.1',
            '-7.1234', '110.5678', date('Y-m-d'), 'OPEN'
        ];
        $this->downloadCsv('template_temuan.csv', $headers, [$sample]);
    }

    private function templatePenyulang()
    {
        // Kode penyulang TIDAK diperlukan lagi - otomatis dibuat sistem
        $headers = [
            'No',
            'Nama Penyulang*',
            'Nama ULP',
            'Status (AKTIF/NONAKTIF)',
        ];
        $sample = ['1', 'PENYULANG CONTOH', 'ULP TEJO', 'AKTIF'];
        $this->downloadCsv('template_penyulang.csv', $headers, [$sample]);
    }

    private function templateUlp()
    {
        $headers = ['No', 'Kode ULP', 'Nama ULP*', 'Status (AKTIF/NONAKTIF)'];
        $sample  = ['1', 'ULP-001', 'ULP CONTOH', 'AKTIF'];
        $this->downloadCsv('template_ulp.csv', $headers, [$sample]);
    }

    private function templateUser()
    {
        $headers = [
            'No', 'Nama Lengkap*', 'Username*', 'Password*',
            'Role (administrator/admin_ulp/inspeksi/har_gardu/har_konstruksi/har_row/har_crane/yantek/pdkb)',
            'Nama ULP', 'Status (AKTIF/NONAKTIF)'
        ];
        $sample = ['1', 'Budi Santoso', 'budi.santoso', 'password123', 'inspeksi', 'ULP TEJO', 'AKTIF'];
        $this->downloadCsv('template_user.csv', $headers, [$sample]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================
    private function downloadCsv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // BOM for Excel UTF-8 compatibility
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function parseDate(?string $raw): string
    {
        if (empty($raw)) return date('Y-m-d');
        $parsed = date_create($raw);
        return $parsed ? $parsed->format('Y-m-d') : date('Y-m-d');
    }

    // =========================================================================
    // EXPORT EX-DATA AS CSV
    // =========================================================================
    public function exportPenyulang()
    {
        $penyulangRepository = new PenyulangRepository();
        
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');
        
        $data = $penyulangRepository->getAllWithUlp();
        
        // Filter by ULP if restricted (Admin ULP)
        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $data = array_filter($data, function ($p) use ($userUlpId) {
                return (int)$p['ulp_id'] === (int)$userUlpId;
            });
        }
        
        $headers = ['No', 'ID Unik Penyulang', 'Kode Penyulang', 'Nama Penyulang', 'Nama ULP', 'Status'];
        $rows = [];
        $no = 1;
        foreach ($data as $p) {
            $rows[] = [
                $no++,
                $p['id_unik_penyulang'],
                $p['kode_penyulang'],
                $p['nama_penyulang'],
                $p['nama_ulp'],
                $p['status']
            ];
        }
        
        $this->downloadCsv('export_penyulang_' . date('Ymd_His') . '.csv', $headers, $rows);
    }

    public function exportSection()
    {
        $sectionRepository = new SectionRepository();
        
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');
        
        $data = $sectionRepository->getAllWithPenyulangAndUlp();
        
        // Filter by ULP if restricted (Admin ULP)
        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $data = array_filter($data, function ($s) use ($userUlpId) {
                return (int)$s['ulp_id'] === (int)$userUlpId;
            });
        }
        
        $headers = ['No', 'Nama Section', 'Nama Penyulang', 'Nama ULP', 'Status'];
        $rows = [];
        $no = 1;
        foreach ($data as $s) {
            $rows[] = [
                $no++,
                $s['nama_section'],
                $s['nama_penyulang'],
                $s['nama_ulp'],
                $s['status']
            ];
        }
        
        $this->downloadCsv('export_section_' . date('Ymd_His') . '.csv', $headers, $rows);
    }
}
