<?php

namespace App\Repositories;

use App\Models\UserModel;
use CodeIgniter\Database\BaseBuilder;

class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new UserModel());
    }

    protected function getJoinedWithUlp(): BaseBuilder
    {
        return $this->getBuilder('users')
            ->select('users.*, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = users.ulp_id', 'left');
    }

    public function findByUsername(string $username): ?array
    {
        return $this->model->where('username', $username)->first();
    }

    public function getAllWithUlp(): array
    {
        return $this->getJoinedWithUlp()
            ->orderBy('users.id', 'DESC')
            ->get()->getResultArray();
    }

    public function findWithUlp(int $id): ?array
    {
        return $this->getJoinedWithUlp()
            ->where('users.id', $id)
            ->get()->getRowArray();
    }

    public function updateLastLogin(int $userId): bool
    {
        return $this->update($userId, [
            'last_login' => date('Y-m-d H:i:s')
        ]);
    }
}
