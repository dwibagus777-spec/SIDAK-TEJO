<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends BaseController
{
    /**
     * Respon JSON Sukses
     */
    protected function respondSuccess($data = null, string $message = 'Sukses', int $statusCode = 200): ResponseInterface
    {
        $payload = [
            'status'  => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return $this->response->setStatusCode($statusCode)->setJSON($payload);
    }

    /**
     * Respon JSON Error
     */
    protected function respondError(string $message = 'Terjadi kesalahan', int $statusCode = 400, $errors = null): ResponseInterface
    {
        $payload = [
            'status'  => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return $this->response->setStatusCode($statusCode)->setJSON($payload);
    }

    /**
     * Dapatkan data user dari JWT Token yang telah terotentikasi
     */
    protected function getJwtUser(): ?array
    {
        return $this->request->jwtUser ?? null;
    }
}
