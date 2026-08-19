<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // 0. Check Build Version — Automatic Session Invalidation on Deployment Build Update
        $currentBuild = \Config\BuildVersion::BUILD_ID;
        if ($session->get('logged_in')) {
            $sessionBuild = (string)$session->get('session_build');
            
            // Temporary Debug Logging (Section 13 Master Prompt)
            log_message('info', "[DASHBOARD_AUTH_CHECK] User: {$session->get('user_name')} | SESSION_BUILD: '{$sessionBuild}' | CURRENT_BUILD: '{$currentBuild}'");

            if ($sessionBuild === '') {
                // Assign current build ID if missing on active session
                $session->set('session_build', $currentBuild);
            } elseif ($sessionBuild !== $currentBuild) {
                log_message('notice', "[SESSION_INVALIDATED] Build mismatch: Session '{$sessionBuild}' vs Current '{$currentBuild}'");
                $session->destroy();
                if (isset($_COOKIE['sidaktejo_remember'])) {
                    setcookie('sidaktejo_remember', '', time() - 3600, '/');
                }
                return redirect()->to(site_url('login?session_expired=build_update'))->with('error', 'Sistem telah diperbarui ke versi enterprise terbaru (' . $currentBuild . '). Silakan login kembali.');
            }
        }

        // 1. Cek jika session logged_in belum ada, periksa Cookie "Ingat Saya" (Remember Me 30 Hari)
        if (!$session->get('logged_in')) {
            $rememberCookie = $_COOKIE['sidaktejo_remember'] ?? null;
            if (!empty($rememberCookie)) {
                $db = \Config\Database::connect();
                $user = $db->table('users')
                    ->where('remember_token', $rememberCookie)
                    ->where('status', 'AKTIF')
                    ->get()->getRowArray();

                if ($user) {
                    $session->set([
                        'user_id'      => $user['id'],
                        'user_name'    => !empty($user['nama_pegawai']) ? $user['nama_pegawai'] : $user['nama'],
                        'nama_pegawai' => !empty($user['nama_pegawai']) ? $user['nama_pegawai'] : $user['nama'],
                        'nip'          => $user['nip'] ?? '',
                        'user_role'    => strtolower($user['role']),
                        'user_ulp_id'  => $user['ulp_id'],
                        'ulp_id'       => $user['ulp_id'],
                        'user_ulp'     => $user['ulp'] ?? '',
                        'logged_in'    => true,
                        'last_activity'=> time()
                    ]);

                    log_activity('SESSION_RESTORED', 'Session berhasil dipulihkan otomatis dari Cookie Ingat Saya (30 Hari).');
                    return;
                }
            }

            // Jika benar-benar tidak ada session & cookie
            $path = (string)$request->getUri()->getPath();
            $isJsonRequest = $request->isAJAX() 
                || str_contains($path, 'ajax') 
                || str_contains($path, 'api')
                || str_contains((string)$request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest')
                || str_contains((string)$request->getHeaderLine('Accept'), 'application/json');

            if ($isJsonRequest) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Sesi Anda telah berakhir setelah 8 jam tidak aktif. Silakan login kembali.'
                    ]);
            }

            return redirect()->to(site_url('login'))->with('error', 'Sesi Anda telah berakhir setelah 8 jam tidak aktif. Silakan login kembali.');
        }

        // 2. Jika user logged_in, perbarui timestamp last_activity
        $session->set('last_activity', time());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // ...
    }
}
