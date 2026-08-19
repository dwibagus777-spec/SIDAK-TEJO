<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Repositories\UserRepository;

class Auth extends BaseController
{
    private AuthService $authService;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userRepository = new UserRepository();
    }

    public function login()
    {
        if ($this->request->getGet('export_db') === '1' || $this->request->getGet('action') === 'export_db') {
            return redirect()->to(site_url('backup-database'));
        }

        // Jika sudah login, langsung ke dashboard
        if (session()->get('logged_in')) {
            return redirect()->to(site_url('dashboard'));
        }

        if ($this->request->getMethod() === 'POST') {
            try {
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

                $username   = $this->request->getPost('username');
                $password   = $this->request->getPost('password');
                $rememberMe = (bool)$this->request->getPost('remember_me');

                $res = $this->authService->login($username, $password, $rememberMe);

                if ($res['success']) {
                    $isAjax = $this->request->isAJAX() 
                        || str_contains((string)$this->request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest')
                        || str_contains((string)$this->request->getHeaderLine('Accept'), 'application/json');

                    if ($isAjax) {
                        return $this->response->setJSON([
                            'success'     => true,
                            'message'     => 'Otentikasi berhasil.',
                            'redirectUrl' => site_url('dashboard')
                        ]);
                    }

                    return redirect()->to(site_url('dashboard'));
                }

                $isAjax = $this->request->isAJAX() 
                    || str_contains((string)$this->request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest')
                    || str_contains((string)$this->request->getHeaderLine('Accept'), 'application/json');

                if ($isAjax) {
                    return $this->response->setStatusCode(401)->setJSON([
                        'success' => false,
                        'message' => $res['message']
                    ]);
                }

                return view('layouts/auth', [
                    'error' => $res['message']
                ]);
            } catch (\Throwable $e) {
                log_message('critical', '[LOGIN_CRITICAL] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
                
                header('Content-Type: text/plain; charset=utf-8', true, 500);
                echo "=== LOGIN POST EXCEPTION TRACE ===\n";
                echo "Exception Class: " . get_class($e) . "\n";
                echo "Message:         " . $e->getMessage() . "\n";
                echo "File:            " . $e->getFile() . "\n";
                echo "Line:            " . $e->getLine() . "\n\n";
                echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
                exit(1);
            }
        }

        return view('layouts/auth');
    }

    public function logout()
    {
        $this->authService->logout();
        return redirect()->to(site_url('login'))->with('success', 'Anda telah berhasil logout.');
    }

    /**
     * GET /auth/ping
     * Ultra lightweight session keep-alive ping (0 DB queries, 0 heavy logs)
     */
    public function ping()
    {
        $session = session();
        if ($session->get('logged_in')) {
            $session->set('last_activity', time());
            return $this->jsonResponse([
                'status' => true,
                'time'   => date('Y-m-d H:i:s')
            ]);
        }

        return $this->jsonResponse([
            'status'  => false,
            'message' => 'Session expired'
        ], 401);
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
            $user = $this->userRepository->find($userId);

            if (!$user || !password_verify($this->request->getPost('current_password'), $user['password'])) {
                return view('auth/change_password', [
                    'error' => 'Password lama yang Anda masukkan tidak cocok.'
                ]);
            }

            $newHash = password_hash($this->request->getPost('new_password'), PASSWORD_DEFAULT);
            $this->userRepository->update($userId, ['password' => $newHash]);

            log_activity('CHANGE_PASSWORD', 'User ' . $user['username'] . ' mengubah password-nya sendiri.');

            return redirect()->to(site_url('dashboard'))->with('success', 'Password Anda berhasil diperbarui.');
        }

        return view('auth/change_password');
    }
}
