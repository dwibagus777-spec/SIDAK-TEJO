<?php

namespace App\Controllers\Api;

use App\Services\AuthService;
use App\Libraries\JwtLibrary;
use App\Repositories\UserRepository;

class AuthController extends BaseApiController
{
    private AuthService $authService;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userRepository = new UserRepository();
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login()
    {
        $json = $this->request->getJSON(true) ?: $this->request->getPost();
        $username = trim((string)($json['username'] ?? ''));
        $password = trim((string)($json['password'] ?? ''));

        if (empty($username) || empty($password)) {
            return $this->respondError('Username dan password wajib diisi.', 400);
        }

        $user = $this->userRepository->findByUsername($username);
        if (!$user) {
            return $this->respondError('Username tidak terdaftar.', 401);
        }

        if (($user['status'] ?? '') !== 'AKTIF') {
            return $this->respondError('Akun Anda dinonaktifkan.', 403);
        }

        if (!password_verify($password, $user['password'])) {
            return $this->respondError('Password salah.', 401);
        }

        // Update Last Login
        $this->userRepository->updateLastLogin($user['id']);

        // Generate JWT Payload
        $jwtPayload = [
            'user_id'      => (int)$user['id'],
            'username'     => $user['username'],
            'nama_pegawai' => !empty($user['nama_pegawai']) ? $user['nama_pegawai'] : $user['nama'],
            'nip'          => $user['nip'] ?? '',
            'role'         => strtolower($user['role']),
            'ulp_id'       => $user['ulp_id'] !== null ? (int)$user['ulp_id'] : null,
            'ulp'          => $user['ulp'] ?? ''
        ];

        $token = JwtLibrary::encode($jwtPayload);
        log_activity('API_LOGIN', 'User ' . $user['username'] . ' login via Flutter REST API.');

        return $this->respondSuccess([
            'token' => $token,
            'user'  => $jwtPayload
        ], 'Login berhasil.');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me()
    {
        $user = $this->getJwtUser();
        if (!$user) {
            return $this->respondError('Sesi tidak valid.', 401);
        }

        return $this->respondSuccess($user, 'Profil pengguna terotentikasi.');
    }

    /**
     * POST /api/v1/auth/change-password
     */
    public function changePassword()
    {
        $userPayload = $this->getJwtUser();
        if (!$userPayload) {
            return $this->respondError('Sesi tidak valid.', 401);
        }

        $json = $this->request->getJSON(true) ?: $this->request->getPost();
        $currentPassword = $json['current_password'] ?? '';
        $newPassword     = $json['new_password'] ?? '';
        $confirmPassword = $json['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            return $this->respondError('Password lama dan password baru wajib diisi.', 400);
        }

        if (strlen($newPassword) < 6) {
            return $this->respondError('Password baru minimal 6 karakter.', 400);
        }

        if (!empty($confirmPassword) && $newPassword !== $confirmPassword) {
            return $this->respondError('Konfirmasi password tidak cocok.', 400);
        }

        $userId = (int)$userPayload['user_id'];
        $dbUser = $this->userRepository->find($userId);

        if (!$dbUser || !password_verify($currentPassword, $dbUser['password'])) {
            return $this->respondError('Password lama yang Anda masukkan tidak cocok.', 400);
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userRepository->update($userId, ['password' => $newHash]);

        log_activity('API_CHANGE_PASSWORD', 'User ' . $dbUser['username'] . ' mengubah password via Flutter REST API.');

        return $this->respondSuccess(null, 'Password Anda berhasil diperbarui.');
    }
}
