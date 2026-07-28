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
            $db = \Config\Database::connect();
            try {
                $tables = $db->listTables();
            } catch (\Throwable $e) {
                return $this->response->setStatusCode(500)->setBody("DB Error: " . $e->getMessage());
            }

            $output = "-- SIDAK TEJO Live Database Export from Railway\n";
            $output .= "-- Exported: " . date('Y-m-d H:i:s') . "\n";
            $output .= "SET FOREIGN_KEY_CHECKS = 0;\n";
            $output .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
            $output .= "SET NAMES utf8mb4;\n\n";

            foreach ($tables as $table) {
                try {
                    $query = $db->query("SHOW CREATE TABLE `{$table}`");
                    $row = $query->getRowArray();
                    $createTableSql = array_values($row)[1];

                    $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
                    $output .= $createTableSql . ";\n\n";

                    $rows = $db->table($table)->get()->getResultArray();
                    if (!empty($rows)) {
                        foreach ($rows as $r) {
                            $cols = array_keys($r);
                            $escapedCols = array_map(fn($c) => "`{$c}`", $cols);
                            $escapedVals = array_map(function($val) use ($db) {
                                if ($val === null) return 'NULL';
                                return $db->escape($val);
                            }, array_values($r));

                            $output .= "INSERT INTO `{$table}` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
                        }
                        $output .= "\n";
                    }
                } catch (\Throwable $ex) {
                    continue;
                }
            }

            $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            return $this->response
                ->setHeader('Content-Type', 'application/sql')
                ->setHeader('Content-Disposition', 'attachment; filename="railway_live_sidak_tejo.sql"')
                ->setBody($output);
        }

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
