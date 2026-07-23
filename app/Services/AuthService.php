<?php

namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    /**
     * Otentikasi pengguna
     *
     * @param string $username
     * @param string $password
     * @return array [success => bool, message => string]
     */
    public function login(string $username, string $password): array
    {
        $user = $this->userRepository->findByUsername($username);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Username tidak terdaftar.'
            ];
        }

        if ($user['status'] !== 'AKTIF') {
            return [
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan.'
            ];
        }

        // Verifikasi password hash bawaan PHP/CI4
        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Password salah.'
            ];
        }

        // Update last login
        $this->userRepository->updateLastLogin($user['id']);

        // Set session
        $session = session();
        $session->set([
            'user_id'      => $user['id'],
            'user_name'    => !empty($user['nama_pegawai']) ? $user['nama_pegawai'] : $user['nama'],
            'nama_pegawai' => !empty($user['nama_pegawai']) ? $user['nama_pegawai'] : $user['nama'],
            'nip'          => $user['nip'] ?? '',
            'user_role'    => strtolower($user['role']),
            'user_ulp_id'  => $user['ulp_id'],
            'ulp_id'       => $user['ulp_id'],
            'user_ulp'     => $user['ulp'] ?? '',
            'logged_in'    => true
        ]);

        // Catat Audit Log
        log_activity('LOGIN', 'User ' . $user['username'] . ' berhasil login.');

        return [
            'success' => true,
            'message' => 'Login berhasil.'
        ];
    }

    /**
     * Keluar dari sistem
     */
    public function logout(): void
    {
        $session = session();
        if ($session->has('user_id')) {
            log_activity('LOGOUT', 'User logout dari sistem.');
        }
        $session->destroy();
    }

    /**
     * Membatasi akses menu berdasarkan role pengguna
     *
     * @param array $allowedRoles
     * @return bool
     */
    public function authorize(array $allowedRoles): bool
    {
        $session = session();
        if (!$session->get('logged_in')) {
            return false;
        }

        $role = $session->get('user_role');
        
        // Administrator memiliki akses penuh ke segala hal
        if ($role === 'administrator') {
            return true;
        }

        return in_array($role, $allowedRoles);
    }
}
