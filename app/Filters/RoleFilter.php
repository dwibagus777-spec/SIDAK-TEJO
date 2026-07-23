<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (!$session->get('logged_in')) {
            return redirect()->to(site_url('login'))->with('error', 'Silakan login terlebih dahulu.');
        }

        $role = $session->get('user_role');

        // Administrator memiliki akses ke semua halaman
        if ($role === 'administrator') {
            return;
        }

        if (empty($arguments)) {
            return;
        }

        // normalisasi argumen ke huruf kecil
        $allowedRoles = array_map('strtolower', $arguments);

        if (!in_array(strtolower($role), $allowedRoles)) {
            // Catat log percobaan akses ilegal
            log_activity('UNAUTHORIZED_ACCESS_ATTEMPT', 'Mencoba mengakses rute: ' . $request->getPath());
            
            return redirect()->to(site_url('dashboard'))->with('error', 'Anda tidak memiliki hak akses untuk membuka halaman tersebut.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // ...
    }
}
