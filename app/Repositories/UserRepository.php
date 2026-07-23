<?php

namespace App\Repositories;

use App\Models\UserModel;

class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new UserModel());
    }

    public function findByUsername(string $username): ?array
    {
        return $this->model->where('username', $username)->first();
    }

    public function getAllWithUlp(): array
    {
        return $this->model
            ->select('users.*, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = users.ulp_id', 'left')
            ->orderBy('users.id', 'DESC')
            ->findAll();
    }

    public function findWithUlp(int $id): ?array
    {
        return $this->model
            ->select('users.*, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = users.ulp_id', 'left')
            ->where('users.id', $id)
            ->first();
    }

    public function updateLastLogin(int $userId): bool
    {
        return $this->model->update($userId, [
            'last_login' => date('Y-m-d H:i:s')
        ]);
    }
}
