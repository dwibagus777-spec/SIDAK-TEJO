<?php

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Repositories\UlpRepository;

class User extends BaseController
{
    private UserRepository $userRepository;
    private UlpRepository $ulpRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->ulpRepository = new UlpRepository();
    }

    public function index()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $users = $this->userRepository->getAllWithUlp();

        // Admin ULP hanya bisa melihat pengguna di ULP-nya sendiri
        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $users = array_filter($users, function ($u) use ($userUlpId) {
                return (int)$u['ulp_id'] === (int)$userUlpId;
            });
        }

        return view('users/index', ['users' => $users]);
    }

    public function create()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
            ? [$this->ulpRepository->find($userUlpId)] 
            : $this->ulpRepository->getActiveUlps();

        return view('users/create', ['ulps' => $ulps]);
    }

    public function store()
    {
        $rules = [
            'nama'         => 'required|max_length[150]',
            'nama_pegawai' => 'permit_empty|max_length[255]',
            'nip'          => 'permit_empty|max_length[50]',
            'username'     => 'required|is_unique[users.username]|alpha_dash|max_length[100]',
            'password'     => 'required|min_length[6]|max_length[255]',
            'role'         => 'required|in_list[administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3]',
            'status'       => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        if (!$this->validate($rules)) {
            $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? [$this->ulpRepository->find($userUlpId)] 
                : $this->ulpRepository->getActiveUlps();

            return view('users/create', [
                'ulps' => $ulps,
                'validation' => $this->validator
            ]);
        }

        $ulpIdInput = $this->request->getPost('ulp_id');
        $ulpIdInputVal = ($ulpIdInput === '' || $ulpIdInput === null) ? null : (int)$ulpIdInput;

        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $ulpIdInputVal = (int)$userUlpId;
        }

        $namaInput = trim((string)$this->request->getPost('nama'));
        $namaPegawaiInput = trim((string)$this->request->getPost('nama_pegawai')) ?: $namaInput;
        $nipInput = trim((string)$this->request->getPost('nip'));
        $ulpNameInput = trim((string)$this->request->getPost('ulp'));

        $data = [
            'nama'         => $namaInput,
            'nama_pegawai' => $namaPegawaiInput,
            'nip'          => $nipInput,
            'username'     => trim((string)$this->request->getPost('username')),
            'password'     => password_hash((string)$this->request->getPost('password'), PASSWORD_DEFAULT),
            'role'         => $this->request->getPost('role'),
            'ulp_id'       => $ulpIdInputVal,
            'ulp'          => $ulpNameInput ?: 'ADMIN',
            'status'       => $this->request->getPost('status')
        ];

        if ($this->userRepository->insert($data)) {
            log_activity('CREATE_USER', 'Menambahkan user baru: ' . $data['username'] . ' (' . $data['nama_pegawai'] . ')');
            return redirect()->to(site_url('users'))->with('success', 'User berhasil ditambahkan.');
        }

        return redirect()->to(site_url('users/create'))->with('error', 'Gagal menambahkan User.');
    }

    public function edit(int $id)
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return redirect()->to(site_url('users'))->with('error', 'User tidak ditemukan.');
        }

        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$user['ulp_id']) {
            return redirect()->to(site_url('users'))->with('error', 'Anda tidak memiliki akses ke data User ini.');
        }

        $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
            ? [$this->ulpRepository->find($userUlpId)] 
            : $this->ulpRepository->getActiveUlps();

        return view('users/edit', [
            'user' => $user,
            'ulps' => $ulps
        ]);
    }

    public function update(int $id)
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return redirect()->to(site_url('users'))->with('error', 'User tidak ditemukan.');
        }

        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$user['ulp_id']) {
            return redirect()->to(site_url('users'))->with('error', 'Anda tidak memiliki akses untuk mengubah data User ini.');
        }

        $postedUsername = trim((string)$this->request->getPost('username'));
        $usernameRule = 'required|alpha_dash|max_length[100]';
        if ($postedUsername !== $user['username']) {
            $usernameRule .= '|is_unique[users.username]';
        }

        $rules = [
            'nama'         => 'required|max_length[150]',
            'nama_pegawai' => 'permit_empty|max_length[255]',
            'nip'          => 'permit_empty|max_length[50]',
            'username'     => $usernameRule,
            'role'         => 'required|in_list[administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3]',
            'status'       => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        if (!$this->validate($rules)) {
            $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? [$this->ulpRepository->find($userUlpId)] 
                : $this->ulpRepository->getActiveUlps();

            return view('users/edit', [
                'user' => $user,
                'ulps' => $ulps,
                'validation' => $this->validator
            ]);
        }

        $ulpIdInput = $this->request->getPost('ulp_id');
        $ulpIdInputVal = ($ulpIdInput === '' || $ulpIdInput === null) ? null : (int)$ulpIdInput;

        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $ulpIdInputVal = (int)$userUlpId;
        }

        $namaInput = trim((string)$this->request->getPost('nama'));
        $namaPegawaiInput = trim((string)$this->request->getPost('nama_pegawai')) ?: $namaInput;
        $nipInput = trim((string)$this->request->getPost('nip'));
        $ulpNameInput = trim((string)$this->request->getPost('ulp'));

        $data = [
            'nama'         => $namaInput,
            'nama_pegawai' => $namaPegawaiInput,
            'nip'          => $nipInput,
            'username'     => $postedUsername,
            'role'         => $this->request->getPost('role'),
            'ulp_id'       => $ulpIdInputVal,
            'ulp'          => $ulpNameInput ?: 'ADMIN',
            'status'       => $this->request->getPost('status')
        ];

        $passwordInput = (string)$this->request->getPost('password');
        if ($passwordInput !== '') {
            $data['password'] = password_hash($passwordInput, PASSWORD_DEFAULT);
        }

        if ($this->userRepository->update($id, $data)) {
            log_activity('UPDATE_USER', 'Mengubah data user ID: ' . $id . ' (' . $data['username'] . ')');
            return redirect()->to(site_url('users'))->with('success', 'User berhasil diperbarui.');
        }

        return redirect()->to(site_url('users/edit/' . $id))->with('error', 'Gagal memperbarui User.');
    }

    public function delete(int $id)
    {
        try {
            $user = $this->userRepository->find($id);
            if (!$user) {
                if ($this->request->isAJAX()) {
                    return $this->response->setJSON(['success' => false, 'message' => 'User tidak ditemukan.']);
                }
                return redirect()->to(site_url('users'))->with('error', 'User tidak ditemukan.');
            }

            $session = session();
            $role = strtolower((string)$session->get('user_role'));
            $userUlpId = $session->get('user_ulp_id');

            if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$user['ulp_id']) {
                if ($this->request->isAJAX()) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk menghapus data User ini.']);
                }
                return redirect()->to(site_url('users'))->with('error', 'Anda tidak memiliki hak akses untuk menghapus data User ini.');
            }

            if ((int)$session->get('user_id') === $id) {
                if ($this->request->isAJAX()) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.']);
                }
                return redirect()->to(site_url('users'))->with('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
            }

            // Unlink references in audit_logs and temuan before delete to guarantee safety
            $db = \Config\Database::connect();
            $db->table('audit_logs')->where('user_id', $id)->update(['user_id' => null]);
            $db->table('temuan')->where('created_by', $id)->update(['created_by' => null]);
            $db->table('temuan')->where('updated_by', $id)->update(['updated_by' => null]);

            if ($this->userRepository->delete($id)) {
                log_activity('DELETE_USER', 'Menghapus user: ' . $user['username']);
                if ($this->request->isAJAX()) {
                    return $this->response->setJSON(['success' => true, 'message' => 'User berhasil dihapus.']);
                }
                return redirect()->to(site_url('users'))->with('success', 'User berhasil dihapus.');
            }

            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus User dari database.']);
            }
            return redirect()->to(site_url('users'))->with('error', 'Gagal menghapus User.');
        } catch (\Throwable $e) {
            log_message('error', 'Delete User Error: ' . $e->getMessage());
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            return redirect()->to(site_url('users'))->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function resetPassword(int $id)
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return redirect()->to(site_url('users'))->with('error', 'User tidak ditemukan.');
        }

        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$user['ulp_id']) {
            return redirect()->to(site_url('users'))->with('error', 'Anda tidak memiliki hak akses untuk mereset password User ini.');
        }

        $newPassword = $this->request->getPost('new_password');
        if (empty($newPassword)) {
            $newPassword = 'admin123';
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($this->userRepository->update($id, ['password' => $newHash])) {
            log_activity('RESET_PASSWORD', 'Mereset password user: ' . $user['username']);
            return redirect()->to(site_url('users'))->with('success', 'Password user "' . esc($user['username']) . '" berhasil direset menjadi: ' . esc($newPassword));
        }

        return redirect()->to(site_url('users'))->with('error', 'Gagal mereset password User.');
    }
}
