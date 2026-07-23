<?php

namespace App\Controllers;

use App\Services\AuthService;

class Auth extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login()
    {
        // Jika sudah login, langsung ke dashboard
        if (session()->get('logged_in')) {
            return redirect()->to(site_url('dashboard'));
        }

        if ($this->request->getMethod() === 'POST') {
            
            // Validasi Input
            $rules = [
                'username' => 'required',
                'password' => 'required'
            ];

            if (!$this->validate($rules)) {
                return view('layouts/auth', [
                    'validation' => $this->validator
                ]);
            }

            $username = $this->request->getPost('username');
            $password = $this->request->getPost('password');

            $res = $this->authService->login($username, $password);

            if ($res['success']) {
                return redirect()->to(site_url('dashboard'));
            }

            return view('layouts/auth', [
                'error' => $res['message']
            ]);
        }

        return view('layouts/auth');
    }

    public function logout()
    {
        $this->authService->logout();
        return redirect()->to(site_url('login'))->with('success', 'Anda telah berhasil logout.');
    }

    public function changePassword()
    {
        $session = session();
        if (!$session->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        if ($this->request->getMethod() === 'POST') {
            $rules = [
                'current_password' => 'required',
                'new_password'     => 'required|min_length[6]|max_length[255]',
                'confirm_password' => 'required|matches[new_password]',
            ];

            if (!$this->validate($rules)) {
                return view('auth/change_password', [
                    'validation' => $this->validator
                ]);
            }

            $userId = (int) $session->get('user_id');
            $userRepo = new \App\Repositories\UserRepository();
            $user = $userRepo->find($userId);

            if (!$user || !password_verify($this->request->getPost('current_password'), $user['password'])) {
                return view('auth/change_password', [
                    'error' => 'Password lama yang Anda masukkan tidak cocok.'
                ]);
            }

            $newHash = password_hash($this->request->getPost('new_password'), PASSWORD_DEFAULT);
            $userRepo->update($userId, ['password' => $newHash]);

            log_activity('CHANGE_PASSWORD', 'User ' . $user['username'] . ' mengubah password-nya sendiri.');

            return redirect()->to(site_url('dashboard'))->with('success', 'Password Anda berhasil diperbarui.');
        }

        return view('auth/change_password');
    }
}
