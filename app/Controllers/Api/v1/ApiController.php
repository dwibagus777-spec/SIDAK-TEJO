<?php

namespace App\Controllers\Api\v1;

use App\Controllers\Api\BaseApiController;
use App\Repositories\TemuanRepository;
use App\Repositories\WorkOrderRepository;
use App\Repositories\AssetRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\DocumentRepository;
use App\Models\UserModel;

class ApiController extends BaseApiController
{
    // ========================================================================
    // AUTHENTICATION ENDPOINTS
    // ========================================================================

    public function login()
    {
        $json = $this->request->getJSON(true) ?: [];
        $username = $json['username'] ?? '';
        $password = $json['password'] ?? '';

        $userModel = new UserModel();
        $user = $userModel->where('username', $username)->first();

        if (!$user || !password_verify($password, $user['password'])) {
            return $this->respondStandard(false, 401, 'Username atau password salah');
        }

        $token = $this->integrationService->generateJWT($user);
        $apiKey = $this->integrationService->generateApiKey($user['id']);

        return $this->respondStandard(true, 200, 'Login berhasil', [
            'token'     => $token,
            'api_key'   => $apiKey['api_key'],
            'user'      => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'nama'     => $user['nama'],
                'role'     => $user['role'],
            ]
        ]);
    }

    public function refreshToken()
    {
        $json = $this->request->getJSON(true) ?: [];
        $token = $json['token'] ?? '';

        $newToken = $this->integrationService->refreshToken($token);
        if (!$newToken) {
            return $this->respondStandard(false, 401, 'Invalid or expired token');
        }

        return $this->respondStandard(true, 200, 'Token refreshed', ['token' => $newToken]);
    }

    // ========================================================================
    // TEMUAN ENDPOINTS
    // ========================================================================

    public function getTemuan()
    {
        if (!$this->verifyAuth()) return;

        $repo = new TemuanRepository();
        $filters = $this->request->getGet();
        $data = $repo->getFilteredTemuan($filters);

        return $this->respondStandard(true, 200, 'Data temuan berhasil diambil', $data, ['count' => count($data)]);
    }

    public function getTemuanDetail($id)
    {
        if (!$this->verifyAuth()) return;

        $repo = new TemuanRepository();
        $doc = $repo->getDetail((int)$id);

        if (!$doc) {
            return $this->respondStandard(false, 404, 'Data temuan tidak ditemukan');
        }

        return $this->respondStandard(true, 200, 'Detail temuan', $doc);
    }

    // ========================================================================
    // WORK ORDER ENDPOINTS
    // ========================================================================

    public function getWorkOrders()
    {
        if (!$this->verifyAuth()) return;

        $repo = new WorkOrderRepository();
        $filters = $this->request->getGet();
        $data = $repo->getFilteredWorkOrders($filters);

        return $this->respondStandard(true, 200, 'Data Work Order berhasil diambil', $data, ['count' => count($data)]);
    }

    public function getWorkOrderDetail($id)
    {
        if (!$this->verifyAuth()) return;

        $repo = new WorkOrderRepository();
        $wo = $repo->find((int)$id);

        if (!$wo) {
            return $this->respondStandard(false, 404, 'Work Order tidak ditemukan');
        }

        return $this->respondStandard(true, 200, 'Detail Work Order', $wo);
    }

    // ========================================================================
    // ASSET ENDPOINTS
    // ========================================================================

    public function getAssets()
    {
        if (!$this->verifyAuth()) return;

        $repo = new AssetRepository();
        $filters = $this->request->getGet();
        $data = $repo->getFilteredAssets($filters);

        return $this->respondStandard(true, 200, 'Data Asset berhasil diambil', $data, ['count' => count($data)]);
    }

    // ========================================================================
    // USER ENDPOINTS
    // ========================================================================

    public function getUsers()
    {
        if (!$this->verifyAuth()) return;

        $userModel = new UserModel();
        $users = $userModel->select('id, username, nama, email, role, ulp_id, status, created_at')->findAll();

        return $this->respondStandard(true, 200, 'Data User berhasil diambil', $users, ['count' => count($users)]);
    }

    // ========================================================================
    // DASHBOARD & ANALYTICS ENDPOINTS
    // ========================================================================

    public function getDashboardStats()
    {
        if (!$this->verifyAuth()) return;

        $repo = new TemuanRepository();
        $stats = $repo->getDashboardStats();

        return $this->respondStandard(true, 200, 'Dashboard Stats', $stats);
    }

    // ========================================================================
    // NOTIFICATION ENDPOINTS
    // ========================================================================

    public function getNotifications()
    {
        if (!$this->verifyAuth()) return;

        $repo = new NotificationRepository();
        $notifs = $repo->getUserNotifications($this->apiUser['user_id'] ?? null);

        return $this->respondStandard(true, 200, 'Notifikasi berhasil diambil', $notifs);
    }

    // ========================================================================
    // DOCUMENT ENDPOINTS
    // ========================================================================

    public function getDocuments()
    {
        if (!$this->verifyAuth()) return;

        $repo = new DocumentRepository();
        $filters = $this->request->getGet();
        $docs = $repo->getFilteredDocuments($filters);

        return $this->respondStandard(true, 200, 'Data Dokumen berhasil diambil', $docs, ['count' => count($docs)]);
    }
}
