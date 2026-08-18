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
    public function login(string $username, string $password, bool $rememberMe = false): array
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

        // Handle "Ingat Saya" (Remember Me 30 Hari Token)
        if ($rememberMe) {
            $token = bin2hex(random_bytes(32));
            try {
                $this->userRepository->update($user['id'], ['remember_token' => $token]);
                setcookie('sidaktejo_remember', $token, time() + (30 * 86400), '/', '', true, true);
            } catch (\Throwable $e) {
                log_message('error', 'Failed to save remember token: ' . $e->getMessage());
            }
        }

        // Set session
        $session = session();
        $session->set([
            'user_id'       => $user['id'],
            'user_name'     => !empty($user['nama_pegawai']) ? $user['nama_pegawai'] : $user['nama'],
            'nama_pegawai'  => !empty($user['nama_pegawai']) ? $user['nama_pegawai'] : $user['nama'],
            'nip'           => $user['nip'] ?? '',
            'user_role'     => strtolower($user['role']),
            'user_ulp_id'   => $user['ulp_id'],
            'ulp_id'        => $user['ulp_id'],
            'user_ulp'      => $user['ulp'] ?? '',
            'logged_in'     => true,
            'session_build' => \Config\BuildVersion::BUILD_ID,
            'last_activity' => time()
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
        $userId = $session->get('user_id');

        if ($userId) {
            log_activity('LOGOUT', 'User logout dari sistem.');
            try {
                $this->userRepository->update($userId, ['remember_token' => null]);
            } catch (\Throwable $e) {}
        }

        setcookie('sidaktejo_remember', '', time() - 3600, '/', '', true, true);
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
