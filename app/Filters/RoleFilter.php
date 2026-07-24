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
        $isJsonRequest = $request->isAJAX()
            || str_contains((string)$request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest')
            || str_contains((string)$request->getHeaderLine('Accept'), 'application/json');

        if (!$session->get('logged_in')) {
            if ($isJsonRequest) {
                return service('response')->setJSON(['success' => false, 'message' => 'Sesi Anda telah berakhir. Silakan login kembali.'])->setStatusCode(401);
            }
            return redirect()->to(site_url('login'))->with('error', 'Silakan login terlebih dahulu.');
        }

        $role = strtolower((string)$session->get('user_role'));

        // Administrator & Admin Pusat memiliki akses ke semua fitur
        if (in_array($role, ['administrator', 'admin', 'admin_pusat'])) {
            return;
        }

        if (empty($arguments)) {
            return;
        }

        // Normalisasi argumen ke huruf kecil
        $allowedRoles = array_map('strtolower', $arguments);

        if (!in_array($role, $allowedRoles)) {
            // Catat log percobaan akses ilegal
            helper('app');
            if (function_exists('log_activity')) {
                log_activity('UNAUTHORIZED_ACCESS_ATTEMPT', 'Mencoba mengakses rute: ' . $request->getPath());
            }
            
            if ($isJsonRequest) {
                return service('response')->setJSON(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk aksi ini.'])->setStatusCode(403);
            }

            return redirect()->to(site_url('dashboard'))->with('error', 'Anda tidak memiliki hak akses untuk membuka halaman tersebut.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // ...
    }
}
