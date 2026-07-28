<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\JwtLibrary;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response = \Config\Services::response();
            return $response->setStatusCode(401)->setJSON([
                'status'  => false,
                'message' => 'Akses ditolak. Token JWT Authorization (Bearer) tidak ditemukan.'
            ]);
        }

        $token = trim($matches[1]);
        $userData = JwtLibrary::decode($token);

        if (!$userData) {
            $response = \Config\Services::response();
            return $response->setStatusCode(401)->setJSON([
                'status'  => false,
                'message' => 'Token JWT tidak valid atau telah kadaluarsa. Silakan login kembali.'
            ]);
        }

        // Simpan data pengguna ke dalam Request
        $request->jwtUser = $userData;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after request
    }
}
